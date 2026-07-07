/**
 * Hızlı Kasa - Sipariş İşlemci (Order Processor)
 *
 * Sipariş oluşturma, stok kontrolü ve WooCommerce API iletişimi.
 *
 * @package HizliKasa
 */

(function (HK) {
    'use strict';

    HK.OrderProcessor = {

        /**
         * Yükleme ekranını göster/gizle
         */
        toggleLoading: function (show) {
            var overlay = document.getElementById("order-loading-overlay");
            if (overlay) overlay.style.display = show ? "flex" : "none";
        },

        /**
         * Sipariş onay butonunu bağla
         */
        init: function () {
            var self = this;

            // Stok Uyarı Modal Butonları
            var stokVazgec = document.getElementById("stok-vazgec");
            var stokDevam = document.getElementById("stok-devam");
            var stokModal = document.getElementById("stok-uyari-modal");

            if (stokVazgec) {
                stokVazgec.addEventListener("click", function () {
                    stokModal.style.display = "none";
                });
            }

            if (stokDevam) {
                stokDevam.addEventListener("click", function () {
                    stokModal.style.display = "none";
                    self.siparisIsleminiGerceklestir(HK.State.splitData);
                });
            }

            document.getElementById("onayla-buton").addEventListener("click", async function () {
                this.blur();
                var state = HK.State;
                if (state.sepet.length === 0) return;

                // İskonto Telefon Zorunluluğu Kontrolü
                // Mevcut durumda sadece kasada manuel girilen iskonto tutarı (iskontoTutar) baz alınmaktadır.
                // Ürünlerin liste fiyatı ile indirimli fiyatı arasındaki farklar (sale price) veya ödeme tipine bağlı 
                // otomatik indirimler bu eşiğe dahil edilmez.
                // Not: İlerleyen süreçte bu mantık; 1 alana 1 bedava veya sepet bazlı özel kampanyaları da 
                // kapsayacak şekilde geliştirilerek toplam kazanılan faydayı ölçecek hale getirilebilir.
                var toplamIskonto = state.iskontoTutar || 0;

                var esik = (typeof kasaAyar !== 'undefined' && kasaAyar.iskontoTelefonEsigi) ? kasaAyar.iskontoTelefonEsigi : 2000;
                var phoneInput = document.getElementById("musteri-telefon");
                var phoneInfo = self._getPhoneInfo();
                var rawPhone = phoneInfo.digits;

                if (toplamIskonto >= esik) {
                    var hasValidPhone = phoneInfo.isValid;
                    var hasOrderNote = !!(state.siparisNotu && state.siparisNotu.trim());

                    if (!hasValidPhone && !hasOrderNote) {
                        HK.UIRenderer.showToast(esik + " TL ve üzeri iskontolarda müşteri telefonu veya sipariş notu zorunludur!", "error", true);
                        var musteriPanel = document.getElementById("musteri-telefon-panel");
                        if (musteriPanel) musteriPanel.style.display = "block";

                        var noteButton = document.getElementById("siparis-notu-btn");
                        if (noteButton) {
                            noteButton.click();
                        } else if (phoneInput) {
                            phoneInput.focus();
                        }
                        return;
                    }
                }

                // Müşteri Telefonu Doğrulaması (Eğer girilmişse ama eşik altında kalmışsa bile formatı kontrol et)
                if (rawPhone.length > 0) {
                    if (!phoneInfo.isValid) {
                        HK.UIRenderer.showToast(phoneInfo.countryCode === "+90" ? "Lütfen geçerli bir telefon numarası giriniz (5xx...)" : "Lütfen geçerli bir telefon numarası giriniz.", "error", true);
                        var musteriPanel = document.getElementById("musteri-telefon-panel");
                        if (musteriPanel) musteriPanel.style.display = "block";
                        if (phoneInput) phoneInput.focus();
                        return;
                    }
                }

                // Yetki Kontrolü: Yönetme yetkisi olmayan depodan satış yapılamaz
                var currentDepoId = HK.DepoManager ? HK.DepoManager.getActiveDepo() : 0;
                if (!HK.DepoManager.canManageDepo(currentDepoId)) {
                    HK.UIRenderer.showToast("Bu depodan satış yapma (yönetme) yetkiniz bulunmamaktadır!", "error", true);
                    return;
                }

                self.toggleLoading(true);

                var sorunlar = await self.sonStokKontrolu();

                if (sorunlar.length > 0) {
                    self.toggleLoading(false);
                    self._stokUyarisiGoster(sorunlar);
                } else {
                    self.siparisIsleminiGerceklestir(state.splitData);
                }
            });

            // Müşteri Telefon Paneli Toggle ve intlTelInput Başlatma
            var musterEkleBtn = document.getElementById("musteri-ekle-btn");
            var musteriPanel = document.getElementById("musteri-telefon-panel");
            var musteriKapat = document.getElementById("musteri-telefon-kapat");
            var phoneInput = document.getElementById("musteri-telefon");

            // intlTelInput Başlatma
            if (phoneInput && window.intlTelInput) {
                HK.iti = window.intlTelInput(phoneInput, {
                    initialCountry: "tr",
                    separateDialCode: true,
                    strictMode: true,
                    countryOrder: ["tr", "de", "nl", "be", "at", "fr", "gb", "us"],
                    loadUtils: function () {
                        return import("https://cdn.jsdelivr.net/npm/intl-tel-input@29.0.3/dist/js/utils.js");
                    },
                    placeholderNumberPolicy: "AGGRESSIVE",
                    formatAsYouType: true,
                    countrySearch: true,
                    countryNameLocale: "tr",
                    uiTranslations: {
                        searchPlaceholder: "Ülke ara...",
                        searchEmptyState: "Sonuç bulunamadı",
                    }
                });

                // Ülke değiştiğinde state'i güncelle ve placeholder'ı temizle/yenile
                phoneInput.addEventListener("countrychange", function() {
                    var countryData = typeof HK.iti.getSelectedCountryData === 'function' ? HK.iti.getSelectedCountryData() : {};
                    HK.State.musteriTelefonUlkeKodu = countryData.dialCode ? "+" + countryData.dialCode : HK.State.musteriTelefonUlkeKodu;
                    HK.State.musteriTelefonUlkeIso = countryData.iso2 || HK.State.musteriTelefonUlkeIso || "tr";

                    if (HK._telefonProgramatikGuncelleniyor) {
                        return;
                    }
                    
                    // Ülke değiştiğinde inputu temizle (farklı maske çakışmalarını önlemek için)
                    phoneInput.value = "";
                    HK.State.musteriTelefon = "";
                    
                    HK.CartManager.sepetiKaydet();
                    self._telefonGrupDurumunuGuncelle();
                });
            }

            if (musterEkleBtn && musteriPanel) {
                musterEkleBtn.addEventListener("click", function () {
                    musteriPanel.style.display = musteriPanel.style.display === "none" ? "block" : "none";
                    if (musteriPanel.style.display === "block" && phoneInput) {
                        phoneInput.focus();
                    }
                });
            }

            if (musteriKapat && musteriPanel) {
                musteriKapat.addEventListener("click", function () {
                    musteriPanel.style.display = "none";
                    if (phoneInput) {
                        phoneInput.value = "";
                        HK.State.musteriTelefon = "";
                        HK.State.musteriTelefonUlkeKodu = "+90";
                        HK.State.musteriTelefonUlkeIso = "tr";
                        if (HK.iti && typeof HK.iti.setCountry === 'function') HK.iti.setCountry("tr");
                    }
                    HK.CartManager.sepetiKaydet();
                    self._telefonGrupDurumunuGuncelle();
                });
            }

            if (phoneInput) {
                phoneInput.addEventListener("input", function (e) {
                    if (HK.iti && typeof HK.iti.getSelectedCountryData === 'function') {
                        var countryData = HK.iti.getSelectedCountryData();
                        if (countryData && countryData.dialCode) {
                            HK.State.musteriTelefonUlkeKodu = "+" + countryData.dialCode;
                            HK.State.musteriTelefonUlkeIso = countryData.iso2 || "tr";
                        }
                    }
                    HK.State.musteriTelefon = e.target.value;
                    HK.CartManager.sepetiKaydet();

                    self._telefonGrupDurumunuGuncelle();
                });
            }
        },

        _getPhoneInfo: function() {
            var countryCode = HK.State.musteriTelefonUlkeKodu || "+90";
            var digits = (HK.State.musteriTelefon || "").replace(/\D/g, '');
            var isValid = false;

            if (HK.iti && typeof HK.iti.isValidNumber === 'function') {
                isValid = HK.iti.isValidNumber();
            } else {
                if (countryCode === "+90") {
                    isValid = digits.length === 10 || (digits.length === 11 && digits[0] === '0');
                } else {
                    isValid = digits.length >= 6 && digits.length <= 15;
                }
            }

            return {
                countryCode: countryCode,
                digits: digits,
                isValid: isValid,
                fullPhone: this._formatFullPhone(countryCode, digits)
            };
        },

        _formatFullPhone: function(countryCode, digits) {
            if (!digits) return "";

            if (countryCode === "+90" && digits[0] === '0') {
                digits = digits.substring(1);
            }

            return countryCode + " " + digits;
        },

        _telefonGrupDurumunuGuncelle: function() {
            var phoneInput = document.getElementById("musteri-telefon");
            if (!phoneInput) return;

            var grup = phoneInput.closest('.musteri-input-grup');
            if (!grup) return;

            var info = this._getPhoneInfo();
            if (info.digits.length === 0) {
                grup.classList.remove('gecerli', 'gecersiz');
            } else if (info.isValid) {
                grup.classList.add('gecerli');
                grup.classList.remove('gecersiz');
            } else {
                grup.classList.add('gecersiz');
                grup.classList.remove('gecerli');
            }
        },

        /**
         * Son stok kontrolü — sipariş öncesi hem site hem depo stoklarını toplu doğrula
         * @returns {Promise<Array>} Sorunlu ürünler listesi
         */
        sonStokKontrolu: async function () {
            var state = HK.State;
            var durumMetni = document.getElementById("durum");
            var stokUyariListe = document.getElementById("stok-uyari-liste");

            durumMetni.innerText = "Site ve depo stokları kontrol ediliyor...";
            stokUyariListe.innerHTML = "";
            var sorunluUrunler = [];

            var depoId = HK.DepoManager ? HK.DepoManager.getActiveDepo() : 0;

            try {
                // Toplu kontrol — tek API çağrısı (negatif satırlar hariç)
                var checkItems = state.sepet.filter(function (item) {
                    return item.quantity > 0; // Değişim iade satırlarını stok kontrolünden hariç tut
                }).map(function (item) {
                    return {
                        product_id: item.product_id,
                        variation_id: item.variation_id || 0,
                        qty: item.quantity
                    };
                });

                // Sadece pozitif ürünler varsa stok kontrolü yap
                if (checkItems.length === 0) return sorunluUrunler;

                var apiBase = kasaAyar.rootApiUrl || (window.location.origin + '/wp-json/');
                var response = await fetch(apiBase + 'hizli-kasa/v1/warehouse-stock-check', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': kasaAyar.nonce
                    },
                    body: JSON.stringify({
                        items: checkItems,
                        depo_id: depoId,
                        order_id: state.editingOrderId || 0
                    })
                });

                var results = await response.json();

                if (Array.isArray(results)) {
                    results.forEach(function (r) {
                        if (!r.site_ok || !r.depo_ok) {
                            sorunluUrunler.push({
                                name: r.name,
                                cartQty: r.requested_qty,
                                serverQty: r.site_stock !== null ? r.site_stock : '?',
                                depoQty: r.depo_stock,
                                warning: r.warning
                            });
                        }
                    });
                }
            } catch (e) {
                console.error("Stok kontrol hatası", e);
            }

            return sorunluUrunler;
        },

        /**
         * Sipariş oluşturma ana fonksiyonu
         * @param {Object|null} splitData Bölünmüş ödeme verisi (opsiyonel)
         */
        siparisIsleminiGerceklestir: async function (splitData) {
            splitData = splitData || null;
            var state = HK.State;
            var siparisKasaId = state.aktifKasaId;
            var durumMetni = document.getElementById("durum");

            // Sipariş öncesi sekmeler arası çakışmayı önle
            HK.CartManager.sepetiYukle(state.aktifKasaId);

            if (state.editingOrderId) {
                await this.siparisGuncelleIsleminiGerceklestir();
                return;
            }

            var hasRefundItems = state.sepet.some(function(item) {
                return item._is_exchange_return && item.quantity < 0;
            });
            var hasRealProducts = state.sepet.some(function(item) {
                return item.product_id !== "COUPON" && !(item._is_exchange_return && item.quantity < 0);
            });

            if (!hasRefundItems && !hasRealProducts) {
                this.toggleLoading(false);
                if (HK.UIRenderer && typeof HK.UIRenderer.showToast === "function") {
                    HK.UIRenderer.showToast("Sepette satışı yapılacak ürün bulunmamaktadır!", "error", true);
                } else {
                    alert("Sepette satışı yapılacak ürün bulunmamaktadır!");
                }
                return;
            }

            this.toggleLoading(true);
            durumMetni.innerText = "İşlem onaylanıyor...";

            // Eğer ödeme bölünmüşse OTOMATİK %5 indirimleri IPTAL et
            var isAutoDiscount = (state.odemeTipi === "cash" || state.odemeTipi === "iban");

            // Sepeti İade (negatif) ve Satış (pozitif) olarak ikiye ayır
            var refundItems = [];
            var saleItems = [];
            var refundOriginalOrder = null;
            var refundTotal = 0; // Toplam iade tutarı (pozitif değer olarak tutacağız)

            state.sepet.forEach(function (item) {
                if (item._is_exchange_return && item.quantity < 0) {
                    refundItems.push(item);
                    if (item._exchange_original_order && !refundOriginalOrder) {
                        refundOriginalOrder = item._exchange_original_order;
                    }
                    refundTotal += Math.abs(item.price * item.quantity);
                } else {
                    saleItems.push(item);
                }
            });

            var apiBase = kasaAyar.rootApiUrl || (window.location.origin + '/wp-json/');
            var exchangeRefundOrderId = null;

            // 1. ADIM: İADE İŞLEMİ (Refund)
            if (refundItems.length > 0) {
                durumMetni.innerText = "İade işlemi arka planda oluşturuluyor...";
                try {
                    var refundResponse = await fetch(apiBase + 'hizli-kasa/v1/process-refund', {
                        method: 'POST',
                        headers: {
                            'X-WP-Nonce': kasaAyar.nonce,
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            original_order_id: refundOriginalOrder || '',
                            is_manual: !refundOriginalOrder,
                            active_depo_id: HK.DepoManager ? HK.DepoManager.getActiveDepo() : 0,
                            kasa_no: state.aktifKasaId.toString(),
                            payment_method: 'nakit', // Değişim iadeleri sanal olarak nakit gibi işlenir (kasada durur)
                            split_data: null,
                            refund_discount: 0,
                            items: refundItems.map(function(item) {
                                return {
                                    id: item.product_id,
                                    item_id: item._exchange_item_id || ('exchange_' + item.product_id), // Orijinal item_id varsa kullan, yoksa sahte üret
                                    variation_id: item.variation_id || 0,
                                    qty: Math.abs(item.quantity), // Pozitif miktar
                                    price: item.price,
                                    depo_id: item._exchange_depo_id || 0
                                };
                            })
                        })
                    });

                    var refundResult = await refundResponse.json();
                    if (refundResult.success) {
                        exchangeRefundOrderId = refundResult.order_id;
                    } else {
                        throw new Error(refundResult.message || "İade işlemi başarısız.");
                    }
                } catch (error) {
                    this.toggleLoading(false);
                    durumMetni.innerText = "HATA: Değişim iadesi oluşturulamadı: " + error.message;
                    durumMetni.style.color = "red";
                    console.error("Değişim iade hatası", error);
                    return; // İade başarısızsa satışı durdur
                }
            }

            // Eğer sepette satılacak yeni ürün yoksa (sadece iade okutulup kasa üzerinden bitirilmişse)
            if (saleItems.length === 0) {
                this.toggleLoading(false);
                durumMetni.innerText = "Sadece iade işlemi tamamlandı.";
                durumMetni.style.color = "#27ae60";
                HK.CartManager.sepetiTemizle(siparisKasaId);
                alert("Sadece iade işlemi yapıldı. İade Sipariş No: #" + exchangeRefundOrderId);
                if (HK.UIRenderer) {
                    HK.UIRenderer.arayuzuGuncelle();
                }
                return;
            }

            // 2. ADIM: SATIŞ İŞLEMİ (Sale)
            durumMetni.innerText = "Satış işlemi tamamlanıyor...";

            // Toplamlar için ön çalışma (sadece pozitif satırlar, kupon hariç)
            var sepetAraToplam = 0;
            var sepetListeToplami = 0;
            var sepetIskontoluToplam = 0;
            var couponAmount = 0;
            var couponCode = "";
            saleItems.forEach(function (item) {
                if (item.product_id === "COUPON") {
                    couponAmount += Math.abs(item.price * item.quantity);
                    couponCode = item.sku;
                } else {
                    sepetAraToplam += (item.price * item.quantity);
                    sepetListeToplami += ((item.regular_price || item.price) * item.quantity);
                    sepetIskontoluToplam += ((item.price * item.quantity) - (item.line_discount || 0));
                }
            });

            if (couponAmount > 0) {
                var currentDurumMetni = durumMetni.innerText;
                durumMetni.innerText = "Kupon geçerliliği kontrol ediliyor...";
                try {
                    var couponItem = saleItems.find(function(si) { return si.product_id === "COUPON"; });
                    var phone = couponItem ? couponItem.verified_phone : "";

                    var validateResponse = await fetch(apiBase + 'hizli-kasa/v2/validate-coupon', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-WP-Nonce': kasaAyar.nonce
                        },
                        body: JSON.stringify({
                            coupon_code: couponCode,
                            phone_number: phone || ""
                        })
                    });

                    var validateResult = await validateResponse.json();
                    if (!validateResult.success) {
                        this.toggleLoading(false);
                        var errorMsg = validateResult.errors ? validateResult.errors[0] : "Kupon geçerliliğini yitirmiş veya başka bir yerde kullanılmış.";
                        durumMetni.innerText = "HATA: " + errorMsg;
                        durumMetni.style.color = "red";
                        HK.UIRenderer.showToast(errorMsg, "error", true);
                        return;
                    }
                } catch (err) {
                    this.toggleLoading(false);
                    durumMetni.innerText = "HATA: Kupon doğrulaması başarısız.";
                    durumMetni.style.color = "red";
                    HK.UIRenderer.showToast("Kupon doğrulanırken bir hata oluştu.", "error", true);
                    return;
                }
                durumMetni.innerText = currentDurumMetni;
            }

            var temizSepet = saleItems.map(function (item) {
                var lineEtiketFiyati = (item.regular_price || item.price);
                var lineSubtotal = item.price * item.quantity;
                var urunIskonto = item.line_discount || 0;
                var satirNakitIndirim = isAutoDiscount ? (lineSubtotal * 0.05) : 0;
                var lineTotal = lineSubtotal - satirNakitIndirim - urunIskonto;
                if (lineTotal < 0) lineTotal = 0;

                var p = {
                    product_id: item.product_id,
                    quantity: item.quantity,
                    subtotal: lineSubtotal.toFixed(2),
                    total: lineTotal.toFixed(2),
                    meta_data: [
                        { key: "Kasiyer", value: kasaAyar.userName || "Kasa Personeli" },
                        { key: "Kasa No", value: state.aktifKasaId.toString() },
                        { key: "_etiket_fiyat", value: lineEtiketFiyati.toFixed(2) },
                        { key: "_kampanya_fiyat", value: item.price.toFixed(2) }
                    ]
                };

                if (urunIskonto > 0.001) {
                    p.meta_data.push({ key: "_hk_item_discount", value: urunIskonto.toFixed(2) });
                    var sanalBirimFiyat = (lineSubtotal - urunIskonto) / item.quantity;
                    p.meta_data.push({ key: "_iskontolu_birim_fiyat", value: sanalBirimFiyat.toFixed(2) });
                }

                if (item.variation_id) p.variation_id = item.variation_id;
                return p;
            });

            // Kuponları ayıkla
            var couponLines = [];
            var gercekSatisGorenUrunler = [];
            temizSepet.forEach(function(item) {
                if (item.product_id === "COUPON") {
                    // Kuponu WooCommerce coupon_lines olarak göndermiyoruz, çünkü fee_lines olarak ekleyeceğiz
                } else {
                    gercekSatisGorenUrunler.push(item);
                }
            });
            temizSepet = gercekSatisGorenUrunler;

            // %5 önce, iskonto sonra
            var autoDiscountTotal = isAutoDiscount ? (sepetAraToplam * 0.05) : 0;
            var netSatisToplami = sepetAraToplam - autoDiscountTotal - state.iskontoTutar;

            var appliedCouponAmount = 0;
            if (couponAmount > 0) {
                appliedCouponAmount = Math.min(couponAmount, netSatisToplami);
                netSatisToplami -= appliedCouponAmount;
            }

            var gercekOdenen = netSatisToplami - refundTotal; // Müşteriden alınacak / kasaya giren net para
            
            var feeLines = [];
            var eklenenFark = 0;

            if (appliedCouponAmount > 0) {
                feeLines.push({
                    name: "İade Çeki (" + couponCode + ")",
                    total: (-appliedCouponAmount).toFixed(2),
                    tax_status: "none"
                });
            }

            if (gercekOdenen < 0) {
                // İade edilen tutar yeni satış tutarından büyük.
                // Müşteriye para üstü vermiyoruz.
                // Muhasebenin düzgün çıkması (Nakit nötrlenmesi) için satış faturasının genel toplamını
                // iade tutarına tamamlıyoruz.
                eklenenFark = Math.abs(gercekOdenen);
                feeLines.push({
                    name: "Ekstra Değişim Farkı",
                    total: eklenenFark.toFixed(2),
                    tax_status: "none"
                });
                gercekOdenen = 0;
            }

            // Ödeme Bölünmüşse Tutar Kontrolü Yap (Son Kontrol)
            if (splitData) {
                var girenToplam = splitData.nakit + splitData.kart + splitData.iban;
                // Bölünmüş ödeme girişi gerçek ödenen (kalan tahsilat) ile eşleşmeli
                var fark = gercekOdenen - girenToplam;
                if (Math.abs(fark) >= 0.01 && gercekOdenen > 0) { // Sadece pozitif ödemelerde kontrol et
                    this.toggleLoading(false);
                    HK.UIRenderer.showToast("Ödenecek tutarla ödeme dağılımı uyuşmuyor! Hesaplarda bir yanlışlık var, ödemeyi tekrar ayarla.", "error", true);
                    durumMetni.innerText = "HATA: Ödeme tutarı uyuşmazlığı!";
                    durumMetni.style.color = "#e74c3c";
                    return;
                }
            }

            // Ödeme Yöntemleri (Raporlama İçin)
            var oNakit = 0, oKart = 0, oIban = 0;
            
            // Değişim iadesi varsa, bu iade tutarı satış faturasına "Nakit" olarak girer.
            // Çünkü iade faturası kesilirken nakit çıkışı (-Nakit) yazıldı, burada satışa (+Nakit) yazılarak nötrlenir.
            if (refundTotal > 0) {
                oNakit += refundTotal;
            }

            if (gercekOdenen > 0) {
                if (splitData) {
                    oNakit += splitData.nakit;
                    oKart += splitData.kart;
                    oIban += splitData.iban;
                } else {
                    if (state.odemeTipi === "cash") oNakit += gercekOdenen;
                    else if (state.odemeTipi === "iban") oIban += gercekOdenen;
                    else oKart += gercekOdenen;
                }
            }

            var paymentMethod = "cod";
            var paymentTitle = "Nakit";

            if (gercekOdenen > 0) {
                paymentMethod = splitData ? "split" : (state.odemeTipi === "card" ? "other" : (state.odemeTipi === "cash" ? "cod" : "bacs"));
                paymentTitle = splitData ? (refundTotal > 0 ? "Değişim" : "Bölünmüş Ödeme") : (state.odemeTipi === "card" ? "Kredi Kartı" : (state.odemeTipi === "cash" ? "Nakit" : "IBAN / Havale"));
            } else if (refundTotal > 0) {
                paymentMethod = "cod";
                paymentTitle = "Nakit (Değişim ile Nötrlendi)";
            }

            var customerNote = "Kasiyer: " + (kasaAyar.userName || "Kasa Personeli") + ", Kasa " + state.aktifKasaId + " | ";
            if (refundTotal > 0) {
                customerNote += "Değişim İşlemi (İade: " + refundTotal.toFixed(2) + " TL Nakit Sayıldı) | ";
            }
            if (gercekOdenen > 0) {
                customerNote += (splitData
                    ? "Kalan Tahsilat: Nakit: " + (oNakit - refundTotal).toFixed(2) + " TL, Kart: " + oKart.toFixed(2) + " TL, IBAN: " + oIban.toFixed(2) + " TL"
                    : "Kalan Tahsilat: " + paymentTitle);
            } else if (refundTotal > 0) {
                customerNote += "Müşteriden ilave tahsilat yapılmadı.";
            }

            var siparisNotu = (state.siparisNotu || "").trim();
            if (siparisNotu) {
                customerNote += " | Not: " + siparisNotu;
            }

            var siparisVerisi = {
                status: kasaAyar.siparisDurumu,
                line_items: temizSepet,
                payment_method: paymentMethod,
                payment_method_title: paymentTitle,
                customer_note: customerNote,
                billing: {
                    first_name: kasaAyar.userName || "Kasa",
                    last_name: "Kasa " + state.aktifKasaId,
                    address_1: "POS Satış",
                    city: "Mağaza",
                    country: "TR",
                    phone: this._getPhoneInfo().fullPhone
                },
                shipping: {
                    first_name: kasaAyar.userName || "Kasa",
                    last_name: "Kasa " + state.aktifKasaId
                },
                fee_lines: feeLines,
                meta_data: [
                    { key: "_hizli_kasa_kasiyer", value: kasaAyar.userName || "Kasa Personeli" },
                    { key: "_hizli_kasa_kasa_no", value: state.aktifKasaId.toString() },
                    { key: "_odeme_nakit", value: oNakit.toFixed(2) },
                    { key: "_odeme_kart", value: oKart.toFixed(2) },
                    { key: "_odeme_iban", value: oIban.toFixed(2) },
                    { key: "_odeme_coupon", value: appliedCouponAmount.toFixed(2) },
                    { key: "_hizli_kasa_used_coupon_code", value: couponCode },
                    { key: "_hizli_kasa_used_coupon_amount", value: appliedCouponAmount.toFixed(2) },
                    { key: "_etiket_toplami", value: sepetListeToplami.toFixed(2) },
                    { key: "_ara_toplam", value: sepetAraToplam.toFixed(2) },
                    { key: "_hk_toplam_iskonto", value: state.iskontoTutar.toFixed(2) },
                    { key: "_hk_otomatik_indirim", value: autoDiscountTotal.toFixed(2) },
                    { key: "_hk_exchange_refund_total", value: refundTotal.toFixed(2) },
                    { key: "_hk_customer_paid_total", value: gercekOdenen.toFixed(2) },
                    { key: "Ödeme (Nakit)", value: oNakit.toFixed(2) + " TL" },
                    { key: "Ödeme (Kart)", value: oKart.toFixed(2) + " TL" },
                    { key: "Ödeme (IBAN)", value: oIban.toFixed(2) + " TL" },
                    { key: "_hk_cikis_depo_id", value: (HK.DepoManager ? HK.DepoManager.getActiveDepo() : 0).toString() },
                    { key: "_hk_cikis_depo_adi", value: HK.DepoManager ? HK.DepoManager.getActiveDepoName() : '' },
                    { key: "_hizli_kasa_kaynak", value: refundTotal > 0 ? "pos_degisim" : "pos_satis" },
                    { key: "_hizli_kasa_musteri_telefon", value: this._getPhoneInfo().fullPhone },
                    { key: "_hizli_kasa_musteri_telefon_ulke_kodu", value: state.musteriTelefonUlkeKodu || "+90" },
                    { key: "_hizli_kasa_siparis_notu", value: siparisNotu },
                    { key: "_hizli_kasa_base_odeme_tipi", value: state.odemeTipi }
                ],
                coupon_lines: []
            };

            // İade ile bağlantı meta verisi
            if (exchangeRefundOrderId) {
                siparisVerisi.meta_data.push({ key: "_exchange_refund_order_id", value: exchangeRefundOrderId.toString() });
            }

            try {
                var response = await fetch(kasaAyar.apiUrl + 'orders', {
                    method: "POST",
                    headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': kasaAyar.nonce },
                    body: JSON.stringify(siparisVerisi)
                });
                var orderResult = await response.json();

                this.toggleLoading(false);

                if (response.ok) {
                    if (HK.ReceiptPrinter) {
                        HK.ReceiptPrinter.fisHazirla(orderResult);
                    }
                    durumMetni.innerText = "Sipariş oluşturuldu.";
                    durumMetni.style.color = "#27ae60";
                    HK.CartManager.sepetiTemizle(siparisKasaId);
                    document.getElementById("fis-onay-modal").style.display = "flex";
                    jQuery(document).trigger('hk:siparis-tamamlandi');
                    if (HK.PrintManager) {
                        HK.PrintManager.print('receipt');
                    }
                } else {
                    durumMetni.innerText = "HATA: " + (orderResult.message || "API sorunu!");
                    durumMetni.style.color = "red";
                }
            } catch (error) {
                this.toggleLoading(false);
                console.error("Sipariş hatası", error);
            }
        },

        /**
         * Stok uyarı modalını göster
         * @param {Array} sorunlar Sorunlu ürün listesi
         */
        _stokUyarisiGoster: function (sorunlar) {
            var stokUyariListe = document.getElementById("stok-uyari-liste");
            var stokUyariModal = document.getElementById("stok-uyari-modal");
            var durumMetni = document.getElementById("durum");

            stokUyariListe.innerHTML = "";
            sorunlar.forEach(function (u) {
                var li = document.createElement("li");
                var detay = 'İhtiyaç: ' + u.cartQty + ' / Site: ' + u.serverQty;
                if (u.depoQty !== undefined) {
                    detay += ' / Depo: ' + u.depoQty;
                }
                li.innerHTML = '<span>' + u.name + '</span> <span>' + detay + '</span>' +
                    (u.warning ? '<br><small style="color:#e67e22;font-size:11px;">⚠️ ' + u.warning + '</small>' : '');
                stokUyariListe.appendChild(li);
            });

            stokUyariModal.style.display = "flex";
            durumMetni.innerText = "Stok uyarısı verildi!";
        },

        siparisGuncelleIsleminiGerceklestir: async function() {
            var self = this;
            var state = HK.State;
            var durumMetni = document.getElementById("durum");

            // Düzenleme Sebebi Girişi (Zorunlu)
            var reason = prompt("Lütfen bu siparişi düzenleme sebebinizi giriniz (Zorunlu):");
            if (reason === null) {
                return; // Kasiyer iptal etti
            }

            reason = reason.trim();
            if (reason.length < 4) {
                HK.UIRenderer.showToast("Lütfen geçerli ve açıklayıcı bir düzenleme sebebi giriniz (en az 4 karakter)!", "error", true);
                return;
            }

            if (!confirm("Sipariş düzenlenecek ve stoklar güncellenecek. Emin misiniz?")) {
                return;
            }

            self.toggleLoading(true);
            durumMetni.innerText = "Değişiklikler kaydediliyor...";

            var changes = state.sepet.map(function(item) {
                return {
                    product_id: item.product_id,
                    variation_id: item.variation_id || 0,
                    qty: item.quantity,
                    price: item.price
                };
            });

            var payMethod = state.splitData ? "split" : state.odemeTipi;
            if (payMethod === 'card') payMethod = 'other';
            else if (payMethod === 'cash') payMethod = 'cod';
            else if (payMethod === 'iban') payMethod = 'bacs';

            // Ödeme Bölünmüşse Tutar Kontrolü Yap
            if (payMethod === 'split' && state.splitData) {
                var girenToplam = state.splitData.nakit + state.splitData.kart + state.splitData.iban;
                var netSatis = changes.reduce(function(acc, item) { return acc + (item.price * item.qty); }, 0) - (state.iskontoTutar || 0);
                if (netSatis < 0) netSatis = 0;
                
                var fark = netSatis - girenToplam;
                if (Math.abs(fark) >= 0.05 && netSatis > 0) {
                    self.toggleLoading(false);
                    HK.UIRenderer.showToast("Ödenecek tutarla ödeme dağılımı uyuşmuyor! Ödemeyi tekrar bölümlendirin.", "error", true);
                    durumMetni.innerText = "HATA: Ödeme tutarı uyuşmazlığı!";
                    durumMetni.style.color = "#e74c3c";
                    return;
                }
            }

            var payload = {
                order_id: state.editingOrderId,
                payment_method: payMethod,
                base_odeme_tipi: state.odemeTipi,
                phone: self._getPhoneInfo().fullPhone,
                discount: state.iskontoTutar || 0,
                note: (state.siparisNotu || "").trim(),
                edit_reason: reason,
                split_data: state.splitData,
                items: changes
            };

            try {
                var response = await fetch(kasaAyar.rootApiUrl + 'hizli-kasa/v1/update-order', {
                    method: 'POST',
                    headers: { 
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': kasaAyar.nonce 
                    },
                    body: JSON.stringify(payload)
                });
                var result = await response.json();

                self.toggleLoading(false);

                if (result.success) {
                    HK.UIRenderer.showToast("Sipariş başarıyla güncellendi.", 'success');
                    
                    // Düzenleme modundan çık
                    state.editingOrderId = null;
                    HK.CartManager.sepetiTemizle(state.aktifKasaId);
                    
                    durumMetni.innerText = "Sipariş güncellendi.";
                    durumMetni.style.color = "#27ae60";
                    HK.UIRenderer.arayuzuGuncelle();
                } else {
                    HK.UIRenderer.showToast("Hata: " + (result.message || "Bilinmeyen bir hata"), 'error');
                    durumMetni.innerText = "HATA: " + (result.message || "Bilinmeyen hata");
                    durumMetni.style.color = "red";
                }
            } catch (e) {
                self.toggleLoading(false);
                console.error("Save edit error", e);
                HK.UIRenderer.showToast("İşlem sırasında bir hata oluştu.", 'error');
                durumMetni.innerText = "Sipariş güncellenemedi!";
                durumMetni.style.color = "red";
            }
        }
    };

})(window.HizliKasa);
