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
                var state = HK.State;
                if (state.sepet.length === 0) return;

                // İskonto Telefon Zorunluluğu Kontrolü
                var sepetListeToplami = 0;
                var sepetAraToplam = 0;
                state.sepet.forEach(function(item) {
                    sepetListeToplami += ((item.regular_price || item.price) * item.quantity);
                    sepetAraToplam += (item.price * item.quantity);
                });
                
                var isAutoDiscount = (state.odemeTipi === "cash" || state.odemeTipi === "iban");
                var netToplam = sepetAraToplam - (isAutoDiscount ? (sepetAraToplam * 0.05) : 0) - (state.iskontoTutar || 0);
                var toplamIskonto = sepetListeToplami - netToplam;

                var esik = (typeof kasaAyar !== 'undefined' && kasaAyar.iskontoTelefonEsigi) ? kasaAyar.iskontoTelefonEsigi : 2000;
                var phoneInput = document.getElementById("musteri-telefon");
                var rawPhone = (state.musteriTelefon || "").replace(/\D/g, '');

                if (toplamIskonto >= esik) {
                    if (rawPhone.length !== 11 || rawPhone[0] !== '0') {
                        HK.UIRenderer.showToast(esik + " TL ve üzeri iskontolarda müşteri telefonu zorunludur!", "error", true);
                        var musteriPanel = document.getElementById("musteri-telefon-panel");
                        if (musteriPanel) musteriPanel.style.display = "block";
                        if (phoneInput) phoneInput.focus();
                        return;
                    }
                }

                // Müşteri Telefonu Doğrulaması (Eğer girilmişse ama eşik altında kalmışsa bile formatı kontrol et)
                if (rawPhone.length > 0) {
                    if (rawPhone.length !== 11 || rawPhone[0] !== '0') {
                        HK.UIRenderer.showToast("Lütfen geçerli bir telefon numarası giriniz (05xx...)", "error", true);
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

            // Müşteri Telefon Paneli Toggle
            var musterEkleBtn = document.getElementById("musteri-ekle-btn");
            var musteriPanel = document.getElementById("musteri-telefon-panel");
            var musteriKapat = document.getElementById("musteri-telefon-kapat");
            var phoneInput = document.getElementById("musteri-telefon");

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
                        HK.CartManager.sepetiKaydet();
                    }
                });
            }

            if (phoneInput) {
                phoneInput.addEventListener("input", function (e) {
                    var val = e.target.value.replace(/\D/g, '');
                    
                    // Eğer kullanıcı doğrudan 5 ile başlıyorsa başına 0 ekle (Türkiye için kolaylık)
                    if (val.length > 0 && val[0] === '5' && val.length <= 10) {
                        val = '0' + val;
                    }
                    
                    // Max 11 hane
                    if (val.length > 11) val = val.substring(0, 11);

                    var x = val.match(/(\d{0,1})(\d{0,3})(\d{0,3})(\d{0,2})(\d{0,2})/);
                    if (!x[1]) {
                        e.target.value = '';
                    } else {
                        e.target.value = !x[2] ? x[1] : x[1] + ' (' + x[2] + (x[3] ? ') ' + x[3] : '') + (x[4] ? ' ' + x[4] : '') + (x[5] ? ' ' + x[5] : '');
                    }

                    // State'i güncelle ve kaydet
                    HK.State.musteriTelefon = e.target.value;
                    HK.CartManager.sepetiKaydet();

                    // Görsel doğrulama geri bildirimi
                    var grup = e.target.closest('.musteri-input-grup');
                    if (grup) {
                        if (val.length === 0) {
                            grup.classList.remove('gecerli', 'gecersiz');
                        } else if (val.length === 11 && val[0] === '0') {
                            grup.classList.add('gecerli');
                            grup.classList.remove('gecersiz');
                        } else {
                            grup.classList.add('gecersiz');
                            grup.classList.remove('gecerli');
                        }
                    }
                });
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
                // Toplu kontrol — tek API çağrısı
                var checkItems = state.sepet.map(function (item) {
                    return {
                        product_id: item.product_id,
                        variation_id: item.variation_id || 0,
                        qty: item.quantity
                    };
                });

                var apiBase = kasaAyar.rootApiUrl || (window.location.origin + '/wp-json/');
                var response = await fetch(apiBase + 'hizli-kasa/v1/warehouse-stock-check', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': kasaAyar.nonce
                    },
                    body: JSON.stringify({ items: checkItems, depo_id: depoId })
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
            var durumMetni = document.getElementById("durum");

            // Sipariş öncesi sekmeler arası çakışmayı önle
            HK.CartManager.sepetiYukle(state.aktifKasaId);

            if (state.sepet.length === 0) {
                this.toggleLoading(false);
                return;
            }

            this.toggleLoading(true);
            durumMetni.innerText = "İşlem onaylanıyor...";

            // Eğer ödeme bölünmüşse OTOMATİK %5 indirimleri IPTAL et
            var isAutoDiscount = (state.odemeTipi === "cash" || state.odemeTipi === "iban");

            // Toplamlar için ön çalışma
            var sepetAraToplam = 0;
            var sepetListeToplami = 0;
            var sepetIskontoluToplam = 0;
            state.sepet.forEach(function (item) {
                sepetAraToplam += (item.price * item.quantity);
                sepetListeToplami += ((item.regular_price || item.price) * item.quantity);
                sepetIskontoluToplam += ((item.price * item.quantity) - (item.line_discount || 0));
            });

            var temizSepet = state.sepet.map(function (item) {
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

                // Ürün bazlı iskonto meta
                if (urunIskonto > 0.001) {
                    p.meta_data.push({ key: "_hk_item_discount", value: urunIskonto.toFixed(2) });
                    // Fişler vs. için sanal iskontolu birim fiyat göndermek faydalı olabilir
                    var sanalBirimFiyat = (lineSubtotal - urunIskonto) / item.quantity;
                    p.meta_data.push({ key: "_iskontolu_birim_fiyat", value: sanalBirimFiyat.toFixed(2) });
                }

                if (item.variation_id) p.variation_id = item.variation_id;
                return p;
            });

            // Fee line: Bilgi amaçlı tutulur (gün sonu raporu vb. için)
            // Artık ürün bazlı iskonto uygulandığı için, mükerrer düşüşü önlemek amacıyla negatif fee eklemiyoruz.
            var feeLines = [];


            // %5 önce, iskonto sonra
            var netToplam = sepetAraToplam - (isAutoDiscount ? (sepetAraToplam * 0.05) : 0) - state.iskontoTutar;

            // Ödeme Bölünmüşse Tutar Kontrolü Yap (Son Kontrol)
            if (splitData) {
                var girenToplam = splitData.nakit + splitData.kart + splitData.iban;
                var fark = netToplam - girenToplam;
                if (Math.abs(fark) >= 0.01) {
                    this.toggleLoading(false);
                    HK.UIRenderer.showToast("Ödenecek tutarla ödeme dağılımı uyuşmuyor! Hesaplarda bir yanlışlık var, ödemeyi tekrar ayarla.", "error", true);
                    durumMetni.innerText = "HATA: Ödeme tutarı uyuşmazlığı!";
                    durumMetni.style.color = "#e74c3c";
                    return;
                }
            }

            // Ödeme Yöntemleri (Raporlama İçin)
            var oNakit = 0, oKart = 0, oIban = 0;
            if (splitData) {
                oNakit = splitData.nakit;
                oKart = splitData.kart;
                oIban = splitData.iban;
            } else {
                if (state.odemeTipi === "cash") oNakit = netToplam;
                else if (state.odemeTipi === "iban") oIban = netToplam;
                else oKart = netToplam;
            }

            var paymentMethod = splitData ? "split" : (state.odemeTipi === "card" ? "other" : (state.odemeTipi === "cash" ? "cod" : "bacs"));
            var paymentTitle = splitData ? "Bölünmüş Ödeme" : (state.odemeTipi === "card" ? "Kredi Kartı" : (state.odemeTipi === "cash" ? "Nakit" : "IBAN / Havale"));

            var customerNote = "Kasiyer: " + (kasaAyar.userName || "Kasa Personeli") + ", Kasa " + state.aktifKasaId + " | " +
                (splitData
                    ? "Ödeme Bölündü: Nakit: " + oNakit.toFixed(2) + " TL, Kart: " + oKart.toFixed(2) + " TL, IBAN: " + oIban.toFixed(2) + " TL"
                    : "Ödeme: " + paymentTitle);

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
                    country: "TR"
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
                    { key: "_etiket_toplami", value: sepetListeToplami.toFixed(2) },
                    { key: "_ara_toplam", value: sepetAraToplam.toFixed(2) },
                    { key: "_hk_toplam_iskonto", value: state.iskontoTutar.toFixed(2) },
                    { key: "Ödeme (Nakit)", value: oNakit.toFixed(2) + " TL" },
                    { key: "Ödeme (Kart)", value: oKart.toFixed(2) + " TL" },
                    { key: "Ödeme (IBAN)", value: oIban.toFixed(2) + " TL" },
                    { key: "_hk_cikis_depo_id", value: (HK.DepoManager ? HK.DepoManager.getActiveDepo() : 0).toString() },
                    { key: "_hk_cikis_depo_adi", value: HK.DepoManager ? HK.DepoManager.getActiveDepoName() : '' },
                    { key: "_hizli_kasa_kaynak", value: "pos_satis" },
                    { key: "_hizli_kasa_musteri_telefon", value: state.musteriTelefon || "" }
                ]
            };

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
                    HK.CartManager.sepetiTemizle();
                    document.getElementById("fis-onay-modal").style.display = "flex";
                    jQuery(document).trigger('hk:siparis-tamamlandi');
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
        }
    };

})(window.HizliKasa);
