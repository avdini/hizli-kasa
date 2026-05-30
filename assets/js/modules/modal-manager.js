/**
 * Hızlı Kasa - Modal Yöneticisi (Modal Manager)
 *
 * İskonto, ürün arama, ödeme bölme modallarının
 * açma/kapama/hesaplama işlemleri.
 *
 * @package HizliKasa
 */

(function(HK) {
    'use strict';

    HK.ModalManager = {

        // DOM Referansları
        els: {},

        /**
         * Tüm modal event listener'larını bağla
         */
        init: function() {
            this.els = {
                iskontoModal: document.getElementById("iskonto-modal"),
                iskontoInput: document.getElementById("iskonto-input"),
                iskontoHedefInput: document.getElementById("iskonto-hedef-input"),
                iskontoLimitBilgi: document.getElementById("iskonto-limit-bilgi"),
                iskontoButon: document.getElementById("iskonto-buton"),
                iskontoOnay: document.getElementById("iskonto-onay"),
                iskontoIptal: document.getElementById("iskonto-iptal"),
                urunAramaModal: document.getElementById("urun-arama-modal"),
                urunAramaInput: document.getElementById("urun-arama-input"),
                aramaSonuclariListe: document.getElementById("arama-sonuclari"),
                urunAramaKapat: document.getElementById("urun-arama-kapat"),
                manuelUrunButon: document.getElementById("manuel-urun-buton"),
                bolButon: document.getElementById("bol-buton"),
                bolModal: document.getElementById("odeme-bol-modal"),
                bolNetToplamArea: document.getElementById("bol-net-toplam"),
                bolKalanTutarArea: document.getElementById("bol-kalan-tutar"),
                bolKalanUyari: document.getElementById("bol-kalan-uyari"),
                bolNakitInput: document.getElementById("bol-nakit"),
                bolKartInput: document.getElementById("bol-kart"),
                bolIbanInput: document.getElementById("bol-iban"),
                bolOnayla: document.getElementById("bol-onayla"),
                bolVazgec: document.getElementById("bol-vazgec"),
                yuvarlaButon: document.getElementById("yuvarla-buton")
            };

            this._bindIskontoModal();
            this._bindUrunAramaModal();
            this._bindOdemeBolModal();
            this._bindYuvarlaButon();
            this._bindModalDismiss();
        },

        // =========================================
        //  İSKONTO MODALI
        // =========================================

        _bindIskontoModal: function() {
            var self = this;
            var els = this.els;

            els.iskontoButon.addEventListener("click", function() {
                var araToplam = self._getSepetAraToplam();
                var bazToplam = self._getBazToplam();
                var mevcutIskonto = self._normalizeIskonto(HK.State.iskontoTutar, araToplam);
                var limitMetni = "En fazla " + HK.CurrencyMask.format(bazToplam);

                els.iskontoModal.style.display = "flex";
                els.iskontoInput.value = mevcutIskonto > 0 ? HK.CurrencyMask.format(mevcutIskonto) : "";
                els.iskontoInput.placeholder = "0,00";
                els.iskontoHedefInput.value = HK.CurrencyMask.format(bazToplam - mevcutIskonto);
                els.iskontoHedefInput.placeholder = limitMetni;
                els.iskontoLimitBilgi.innerText = limitMetni + " girebilirsiniz.";
                els.iskontoHedefInput.focus();
            });

            [els.iskontoInput, els.iskontoHedefInput].forEach(function(input) {
                input.addEventListener("keydown", function(e) {
                    if (e.key === "Enter") {
                        e.preventDefault();
                        els.iskontoOnay.click();
                    }
                });
            });

            els.iskontoInput.addEventListener("input", function() {
                self._syncIskontoInputs("iskonto");
            });

            els.iskontoHedefInput.addEventListener("input", function() {
                self._syncIskontoInputs("hedef");
            });

            els.iskontoIptal.addEventListener("click", function() {
                els.iskontoModal.style.display = "none";
            });

            els.iskontoOnay.addEventListener("click", function() {
                var araToplam = self._getSepetAraToplam();
                var bazToplam = self._getBazToplam();
                var iskonto = self._normalizeIskonto(HK.CurrencyMask.parse(els.iskontoInput.value), araToplam);

                // İskontoyu ürünlere dağıt
                HK.CartManager.dagitimiHesapla(iskonto);

                els.iskontoInput.value = iskonto > 0 ? HK.CurrencyMask.format(iskonto) : "";
                els.iskontoHedefInput.value = HK.CurrencyMask.format(bazToplam - iskonto);
                els.iskontoModal.style.display = "none";
                HK.UIRenderer.arayuzuGuncelle();
            });
        },

        _getSepetAraToplam: function() {
            var toplam = 0;
            HK.State.sepet.forEach(function(item) {
                toplam += (item.price * item.quantity);
            });
            return parseFloat(toplam.toFixed(2));
        },

        /**
         * %5 otomatik indirim sonrası (manuel iskonto öncesi) toplamı döner.
         * Pazarcının kafasındaki "baz fiyat" budur — %5 sonrası gördüğü rakam.
         */
        _getBazToplam: function() {
            var state = HK.State;
            var araToplam = this._getSepetAraToplam();
            var nakitIndirim = 0;
            
            if (!state.splitData && (state.odemeTipi === "cash" || state.odemeTipi === "iban")) {
                nakitIndirim = araToplam * 0.05;
            }
            
            return parseFloat((araToplam - nakitIndirim).toFixed(2));
        },

        _normalizeIskonto: function(tutar, sepetToplami) {
            var deger = parseFloat(tutar) || 0;
            var maksimum = Math.max(parseFloat(sepetToplami) || 0, 0);

            if (deger < 0) deger = 0;
            if (deger > maksimum) deger = maksimum;

            return parseFloat(deger.toFixed(2));
        },

        _syncIskontoInputs: function(kaynak) {
            var els = this.els;
            var araToplam = this._getSepetAraToplam();
            var bazToplam = this._getBazToplam();
            var iskonto = 0;

            if (kaynak === "hedef") {
                var hedefTutar = HK.CurrencyMask.parse(els.iskontoHedefInput.value);
                if (hedefTutar < 0) hedefTutar = 0;
                if (hedefTutar > bazToplam) hedefTutar = bazToplam;

                hedefTutar = parseFloat(hedefTutar.toFixed(2));
                iskonto = this._normalizeIskonto(bazToplam - hedefTutar, araToplam);

                // Kendi değerimizi güncellemiyoruz (imleç kaçmasın diye), sadece diğerini güncelliyoruz
                els.iskontoInput.value = iskonto > 0 ? HK.CurrencyMask.format(iskonto) : "";
                return;
            }

            iskonto = this._normalizeIskonto(HK.CurrencyMask.parse(els.iskontoInput.value), araToplam);
            // Kendi değerimizi güncellemiyoruz (imleç kaçmasın diye), sadece diğerini güncelliyoruz
            els.iskontoHedefInput.value = HK.CurrencyMask.format(bazToplam - iskonto);
        },

        // =========================================
        //  ÜRÜN ARAMA MODALI
        // =========================================

        _aramaTimeout: null,

        _bindUrunAramaModal: function() {
            var self = this;
            var els = this.els;

            if (els.manuelUrunButon) {
                els.manuelUrunButon.addEventListener("click", function() {
                    els.urunAramaModal.style.display = "flex";
                    els.urunAramaInput.value = "";
                    els.aramaSonuclariListe.innerHTML = "";
                    els.urunAramaInput.focus();
                });
            } else {
                console.error("Hızlı Kasa: 'manuel-urun-buton' bulunamadı!");
            }

            els.urunAramaKapat.addEventListener("click", function() {
                els.urunAramaModal.style.display = "none";
            });

            els.urunAramaInput.addEventListener("input", function() {
                clearTimeout(self._aramaTimeout);
                var query = els.urunAramaInput.value.trim();
                self._lastSearchQuery = query; // Takip için son sorguyu kaydet

                if (query.length < 2) {
                    els.aramaSonuclariListe.innerHTML = "";
                    return;
                }

                self._aramaTimeout = setTimeout(async function() {
                    try {
                        var depoId = HK.DepoManager ? HK.DepoManager.getActiveDepo() : 0;
                        var apiBase = kasaAyar.rootApiUrl || (window.location.origin + '/wp-json/');
                        var response = await fetch(apiBase + 'hizli-kasa/v1/search?s=' + encodeURIComponent(query) + '&depo_id=' + depoId, {
                            headers: { 'X-WP-Nonce': kasaAyar.nonce }
                        });
                        var data = await response.json();
                        
                        // ÖNEMLİ: Yarış durumunu (race condition) engelle.
                        // Sadece en son yapılan aramanın sonuçlarını göster.
                        if (self._lastSearchQuery !== query) {
                            return;
                        }

                        self._sonuclariGoster(data);
                    } catch (error) {
                        console.error("Arama hatası:", error);
                    }
                }, 400);
            });
        },

        /**
         * Arama sonuçlarını listeye render et
         * @param {Array} urunler Ürün listesi
         */
        _sonuclariGoster: function(urunler) {
            var self = this;
            var els = this.els;
            els.aramaSonuclariListe.innerHTML = "";

            if (!urunler || urunler.length === 0) {
                els.aramaSonuclariListe.innerHTML = '<li style="cursor:default; justify-content:center; color:#999;">Sonuç bulunamadı.</li>';
                return;
            }

            // 1. Ürün hiyerarşisini kur (Map tabanlı gruplama)
            var roots = [];
            var productMap = {};

            // Önce tüm ürünleri Map'e koy ve varyasyon listelerini temizle
            urunler.forEach(function(u) {
                u.tempVariations = [];
                productMap[u.id] = u;
            });

            // Ürünleri dolaşarak ağacı oluştur
            urunler.forEach(function(u) {
                if (u.parent_id > 0 && productMap[u.parent_id]) {
                    // Varyasyon: Parent'ın altına ekle (mükerrer eklemeyi önle)
                    var parent = productMap[u.parent_id];
                    if (!parent.tempVariations.some(v => v.id === u.id)) {
                        parent.tempVariations.push(u);
                    }
                } else if (!u.parent_id || u.parent_id === 0 || u.is_variable) {
                    // Ana ürün veya yetim varyasyon: Root olarak ekle
                    if (!roots.some(r => r.id === u.id)) {
                        roots.push(u);
                    }
                }
            });

            // 2. DOM'a render et
            roots.forEach(function(anaUrun) {
                var varyasyonlar = anaUrun.tempVariations || [];
                
                // Ana ürün satırını oluştur
                var li = self._urunSatiriOlustur(anaUrun, true, true);
                els.aramaSonuclariListe.appendChild(li);

                if (varyasyonlar.length > 0 || anaUrun.is_variable) {
                    li.classList.add("parent-row");
                    
                    var vContainer = document.createElement("div");
                    vContainer.className = "variation-container";
                    
                    // Arama sorgusuyla tam eşleşen SKU'yu en başa getir (Orijinal diziyi bozmadan)
                    var query = (els.urunAramaInput.value || "").trim().toLowerCase();
                    var siraliVaryasyonlar = [...varyasyonlar].sort(function(a, b) {
                        var aSku = (a.sku || "").toLowerCase();
                        var bSku = (b.sku || "").toLowerCase();
                        if (aSku === query && bSku !== query) return -1;
                        if (bSku === query && aSku !== query) return 1;
                        return 0;
                    });

                    siraliVaryasyonlar.forEach(function(v) {
                        var vLi = self._urunSatiriOlustur(v, false, true);
                        vLi.classList.add("variation-row");
                        vContainer.appendChild(vLi);
                    });

                    els.aramaSonuclariListe.appendChild(vContainer);

                    // Tıklama ile aç/kapat
                    li.addEventListener("click", function(e) {
                        e.preventDefault();
                        var isOpen = li.classList.toggle("is-open");
                        vContainer.classList.toggle("is-open", isOpen);
                    });

                    // ÖZEL DURUM: Eğer çok az root varsa, otomatik aç
                    if (roots.length <= 2) {
                        li.classList.add("is-open");
                        vContainer.classList.add("is-open");
                    }
                }
            });
        },

        /**
         * Tekil ürün satırı DOM öğesi oluşturur
         */
        _urunSatiriOlustur: function(urun, isMain, showVariationHint) {
            var self = this;
            var regularPrice = parseFloat(urun.regular_price || 0);
            var salePrice = parseFloat(urun.price || 0);
            var isVariableParent = !!urun.is_variable;
            var outOfStock = !isVariableParent && (urun.stock_status === 'outofstock' || (urun.manage_stock && urun.stock_quantity !== null && urun.stock_quantity <= 0));

            var li = document.createElement("li");
            
            // Kolon Yapısı HTML
            var imgHtml = (urun.images && urun.images.length > 0)
                ? '<img src="' + urun.images[0].src + '" class="arama-urun-resim" style="width:30px; height:30px; object-fit:cover; border-radius:3px; cursor:zoom-in; ' + (outOfStock ? 'filter:grayscale(1); opacity:0.5;' : '') + '">'
                : '<div style="width:30px; height:30px; background:#f0f0f0; border-radius:3px;"></div>';

            var nameHtml = 
                '<span style="font-weight:bold; font-size:14px; color: ' + (outOfStock ? '#c0392b' : 'inherit') + '">' +
                    urun.name +
                    (outOfStock ? ' <small style="color:#e74c3c; font-weight:bold;">(SİTE STOK YOK)</small>' : '') +
                '</span>' +
                '<span class="sonuc-sku">' + (urun.sku || 'SKU yok') + '</span>';

            var warehouseStock = urun.warehouse_stock;
            var warehouseHtml = '';
            if (!isVariableParent && warehouseStock !== undefined && warehouseStock !== null) {
                if (warehouseStock <= 0) {
                    warehouseHtml = '<div style="background:#e74c3c; color:#fff; font-size:10px; padding:2px 5px; border-radius:3px; font-weight:bold; display:inline-block; margin-top:2px;">DEPODA YOK</div>';
                } else if (warehouseStock <= 3) {
                    warehouseHtml = '<div style="background:#f39c12; color:#fff; font-size:10px; padding:2px 5px; border-radius:3px; font-weight:bold; display:inline-block; margin-top:2px;">AZ STOK: ' + warehouseStock + '</div>';
                } else {
                    warehouseHtml = '<div style="background:#27ae60; color:#fff; font-size:10px; padding:2px 5px; border-radius:3px; font-weight:bold; display:inline-block; margin-top:2px;">DEPO: ' + warehouseStock + '</div>';
                }
            }

            var stockHtml = (!isVariableParent && urun.manage_stock) ? 'Site: ' + (urun.stock_quantity || 0) : '';
            if (warehouseHtml) stockHtml += '<br>' + warehouseHtml;
            
            var priceHtml = '';
            if (!isVariableParent) {
                if (regularPrice > salePrice && salePrice > 0) {
                    priceHtml = '<div style="display:flex; flex-direction:column; align-items:flex-end;">' +
                        '<span style="text-decoration:line-through; color:#999; font-size:11px;">' + regularPrice.toFixed(2) + ' TL</span>' +
                        '<span class="sonuc-fiyat">' + salePrice.toFixed(2) + ' TL</span>' +
                    '</div>';
                } else {
                    priceHtml = '<span class="sonuc-fiyat">' + salePrice.toFixed(2) + ' TL</span>';
                }
            } else if (showVariationHint !== false) {
                priceHtml = '<small style="color:#7f8c8d; font-size:11px;">Seçenekleri Gör</small>';
            }

            li.innerHTML = 
                '<div class="sonuc-img-cell">' + imgHtml + '</div>' +
                '<div class="sonuc-info-cell">' + nameHtml + '</div>' +
                '<div class="sonuc-stock-cell">' + stockHtml + '</div>' +
                '<div class="sonuc-price-cell">' + priceHtml + '</div>';

            if (outOfStock) {
                li.style.backgroundColor = "#fff5f5";
                li.style.opacity = "0.7";
                li.style.cursor = "not-allowed";
                li.style.borderLeft = "4px solid #e74c3c";
            }

            // Tıklama olayı
            li.addEventListener("click", function(e) {
                if (isVariableParent) {
                    // Sadece çekmeceyi tetikle (diğer listener'da), sepete eklemeyi engelle
                    return;
                }
                
                if (outOfStock) {
                    HK.UIRenderer.showToast("Bu ürün stokta yok!", "error");
                    return;
                }

                HK.CartManager.ekleUrunObjesiyle(urun);
                document.getElementById("urun-arama-modal").style.display = "none";
            });

            // Resme tıklandığında önizleme
            var imgEl = li.querySelector(".arama-urun-resim");
            if (imgEl) {
                imgEl.addEventListener("click", function(e) {
                    e.stopPropagation();
                    e.preventDefault();
                    var src = urun.images[0].src;
                    if (window.HizliKasa && window.HizliKasa.StockTerminal && typeof window.HizliKasa.StockTerminal.openImagePreview === 'function') {
                        window.HizliKasa.StockTerminal.openImagePreview(src);
                    } else {
                        self.openImagePreview(src);
                    }
                });
            }

            return li;
        },

        // =========================================
        //  ÖDEME BÖLME MODALI
        // =========================================

        _bindOdemeBolModal: function() {
            var self = this;
            var els = this.els;

            els.bolButon.addEventListener("click", function() {
                var state = HK.State;
                if (state.sepet.length === 0) return;

                // Bölmede %5 otomatik indirim geçersizdir, sadece sepetteki manuel iskonto geçerlidir
                var toplamPara = 0;
                state.sepet.forEach(function(item) { toplamPara += (item.price * item.quantity); });
                var netHedef = toplamPara - state.iskontoTutar;

                els.bolNetToplamArea.innerText = netHedef.toFixed(2);
                els.bolNakitInput.value = "";
                els.bolKartInput.value = "";
                els.bolIbanInput.value = "";
                self._bolHesapla();
                els.bolModal.style.display = "flex";
            });

            [els.bolNakitInput, els.bolKartInput, els.bolIbanInput].forEach(function(inp) {
                inp.addEventListener("input", function() { self._bolHesapla(); });
            });

            els.bolVazgec.addEventListener("click", function() {
                els.bolModal.style.display = "none";
            });

            els.bolOnayla.addEventListener("click", async function() {
                var state = HK.State;
                var toplamPara = 0;
                state.sepet.forEach(function(item) { toplamPara += (item.price * item.quantity); });
                var netHedef = toplamPara - state.iskontoTutar;

                var nakit = HK.CurrencyMask.parse(els.bolNakitInput.value) || 0;
                var kart = HK.CurrencyMask.parse(els.bolKartInput.value) || 0;
                var iban = HK.CurrencyMask.parse(els.bolIbanInput.value) || 0;

                var girenToplam = nakit + kart + iban;
                var fark = netHedef - girenToplam;

                if (Math.abs(fark) >= 0.01) {
                    alert("Dikkat! Ödeme tutarı ile sepet toplamı eşleşmiyor.\nFark: " + fark.toFixed(2) + " TL\nLütfen tutarları kontrol edin.");
                    return;
                }

                // Siparişi oluşturmak yerine state'e kaydet ve kapat
                state.splitData = { nakit: nakit, kart: kart, iban: iban };
                els.bolModal.style.display = "none";
                
                if (HK.UIRenderer) {
                    HK.UIRenderer.arayuzuGuncelle();
                    HK.UIRenderer.showToast("Ödeme planı hazırlandı. 'Sipariş Oluştur' butonu ile onaylayabilirsiniz.", "success");
                }
            });
        },

        /**
         * Ödeme bölme kalan tutarını hesapla
         */
        _bolHesapla: function() {
            var state = HK.State;
            var els = this.els;

            var toplamPara = 0;
            state.sepet.forEach(function(item) { toplamPara += (item.price * item.quantity); });
            var netHedef = toplamPara - state.iskontoTutar;

            var nakit = HK.CurrencyMask.parse(els.bolNakitInput.value) || 0;
            var kart = HK.CurrencyMask.parse(els.bolKartInput.value) || 0;
            var iban = HK.CurrencyMask.parse(els.bolIbanInput.value) || 0;

            var girenToplam = nakit + kart + iban;
            var kalan = netHedef - girenToplam;

            els.bolKalanTutarArea.innerText = kalan.toFixed(2);

            if (Math.abs(kalan) < 0.01) {
                els.bolKalanUyari.innerText = "Toplam Tamamlandı!";
                els.bolKalanUyari.className = "kalan-tamam";
            } else {
                els.bolKalanUyari.innerText = kalan > 0 ? "Kalan: " + kalan.toFixed(2) + " TL" : "Fazla: " + Math.abs(kalan).toFixed(2) + " TL";
                els.bolKalanUyari.className = "kalan-eksik";
            }
        },

        // =========================================
        //  KÜSÜRAT YUVARLAMA
        // =========================================

        _bindYuvarlaButon: function() {
            var els = this.els;

            // Ayarlardan buton aktifliğini kontrol et
            if (!kasaAyar.yuvarlamaAktif || kasaAyar.yuvarlamaAktif === '0') {
                if (els.yuvarlaButon) {
                    els.yuvarlaButon.style.display = "none";
                }
                return;
            }

            els.yuvarlaButon.addEventListener("click", function() {
                var state = HK.State;
                if (state.sepet.length === 0) return;

                // Yuvarlama adımı (ayarlardan)
                var adim = parseFloat(kasaAyar.yuvarlaModu) || 1;

                // Mevcut toplam hesapla (%5 önce, iskonto sonra — ui-renderer ile aynı mantık)
                var sepetAraToplam = 0;
                state.sepet.forEach(function(item) {
                    sepetAraToplam += (item.price * item.quantity);
                });

                var nakitIndirimTutar = 0;
                if (!state.splitData && (state.odemeTipi === "cash" || state.odemeTipi === "iban")) {
                    nakitIndirimTutar = sepetAraToplam * 0.05;
                }

                var mevcutToplam = sepetAraToplam - nakitIndirimTutar - state.iskontoTutar;
                if (mevcutToplam <= 0) return;

                // Yuvarlanan hedefi hesapla (adımın alt katına yuvarla)
                var yuvarlanmis = Math.floor(mevcutToplam / adim) * adim;

                // Eğer zaten yuvarlak ise bir şey yapma
                var fark = mevcutToplam - yuvarlanmis;
                if (fark < 0.01) {
                    document.getElementById("durum").innerText = "Toplam zaten yuvarlak.";
                    document.getElementById("durum").style.color = "#95a5a6";
                    return;
                }

                // Farkı iskontoya ekle ve ürünlere yeniden dağıt
                var yeniIskonto = parseFloat((state.iskontoTutar + fark).toFixed(2));
                HK.CartManager.dagitimiHesapla(yeniIskonto);
                HK.UIRenderer.arayuzuGuncelle();

                // Bilgi mesajı
                var modLabel = adim < 1 ? (adim * 100) + " kuruş" : adim + " TL";
                document.getElementById("durum").innerText = "Küsürat yuvarlandı (" + modLabel + "): -" + fark.toFixed(2) + " TL iskonto eklendi.";
                document.getElementById("durum").style.color = "#27ae60";
            });
        },

        // =========================================
        //  MODAL DIŞ TIKLA KAPAMA
        // =========================================

        _bindModalDismiss: function() {
            var els = this.els;
            window.addEventListener("click", function(event) {
                if (event.target == els.iskontoModal) els.iskontoModal.style.display = "none";
                if (event.target == els.urunAramaModal) els.urunAramaModal.style.display = "none";
                if (event.target == els.bolModal) els.bolModal.style.display = "none";
            });
        },

        openImagePreview: function(src) {
            if (!src || src.includes('placeholder')) return;

            if (window.HizliKasa && window.HizliKasa.StockTerminal && typeof window.HizliKasa.StockTerminal.openImagePreview === 'function') {
                window.HizliKasa.StockTerminal.openImagePreview(src);
                return;
            }

            let modal = document.getElementById('terminal-image-preview-modal');
            if (!modal) {
                modal = document.createElement('div');
                modal.id = 'terminal-image-preview-modal';
                modal.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.85);z-index:999999;display:flex;align-items:center;justify-content:center;backdrop-filter:blur(5px);cursor:zoom-out;';
                
                const loader = document.createElement('div');
                loader.id = 'terminal-preview-loader';
                loader.style.cssText = 'position:absolute;width:40px;height:40px;border:4px solid #fff;border-top:4px solid transparent;border-radius:50%;animation:hk-spin 1s linear infinite;';
                
                if (!document.getElementById('hk-spin-keyframes')) {
                    const style = document.createElement('style');
                    style.id = 'hk-spin-keyframes';
                    style.innerHTML = '@keyframes hk-spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }';
                    document.head.appendChild(style);
                }

                const img = document.createElement('img');
                img.id = 'terminal-preview-img';
                img.style.cssText = 'max-width:90%;max-height:90%;object-fit:contain;border-radius:12px;opacity:0;transition:opacity 0.3s;box-shadow:0 10px 40px rgba(0,0,0,0.5);';
                
                modal.appendChild(loader);
                modal.appendChild(img);
                document.body.appendChild(modal);

                modal.addEventListener('click', () => {
                    modal.style.display = 'none';
                });
            }

            const img = document.getElementById('terminal-preview-img');
            const loader = document.getElementById('terminal-preview-loader');
            
            img.style.opacity = '0';
            img.src = '';
            loader.style.display = 'block';
            modal.style.display = 'flex';
            
            const fullSrc = src.replace(/-\d+x\d+(\.[a-zA-Z]+)$/i, '$1');
            
            img.onload = function() {
                loader.style.display = 'none';
                img.style.opacity = '1';
            };
            img.onerror = function() {
                loader.style.display = 'none';
                img.style.opacity = '1';
                if (img.src !== src) {
                    img.src = src;
                }
            };
            
            img.src = fullSrc;
        }
    };

})(window.HizliKasa);
