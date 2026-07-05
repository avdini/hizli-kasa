/**
 * Hızlı Kasa - Arayüz Render (UI Renderer)
 *
 * Sepet listesi render, toplam hesaplama,
 * sidebar durum güncelleme ve ödeme tipi seçici.
 *
 * @package HizliKasa
 */

window.HizliKasa = window.HizliKasa || {};
(function(HK) {
    'use strict';

    HK.UIRenderer = {

        // DOM Referansları (init sırasında dolacak)
        els: {},

        /**
         * DOM elementlerini cache'le
         */
        init: function() {
            this.els = {
                sepetListesi: document.getElementById("sepet-listesi"),
                durumMetni: document.getElementById("durum"),
                genelToplamArea: document.getElementById("genel-toplam"),
                indirimSatiri: document.getElementById("indirim-satiri"),
                indirimDegerArea: document.getElementById("indirim-deger"),
                nakitIndirimSatiri: document.getElementById("nakit-indirim-satiri"),
                nakitIndirimDegerArea: document.getElementById("nakit-indirim-deger"),
                nakitIndirimEtiket: document.getElementById("nakit-indirim-etiket"),
                araToplamArea: document.getElementById("ara-toplam-deger"),
                listeToplamiSatiri: document.getElementById("liste-toplami-satiri"),
                listeToplamiArea: document.getElementById("liste-toplami-deger"),
                sepetIstatistikArea: document.getElementById("sepet-istatistik-watermark"),
                odemeOzetiAlani: document.getElementById("odeme-ozeti-alani"),
                bolButon: document.getElementById("bol-buton"),
                siparisNotuBtn: document.getElementById("siparis-notu-btn"),
                sidebarButtons: document.querySelectorAll(".sidebar-btn"),
                iskontoTemizleBtn: document.getElementById("iskonto-temizle-btn"),
                kuponSatiri: document.getElementById("kupon-satiri"),
                kuponDegerArea: document.getElementById("kupon-deger")
            };

            this._bindOdemeTipiSecici();
            this._bindSidebarButonlari();
            this._bindIskontoTemizle();
        },

        /**
         * Sidebar butonlarının aktif/dolu durumunu güncelle
         */
        sidebarGuncelle: function() {
            var state = HK.State;
            this.els.sidebarButtons.forEach(function(btn) {
                var id = btn.dataset.id;
                btn.classList.toggle("aktif", parseInt(id) === state.aktifKasaId);

                // Dolu kasa kontrolü (içinde ürün var mı?)
                var key = HK.CartManager ? HK.CartManager._slotKey(id) : ('hizli_kasa_hafiza_slot_' + id);
                var slotVeri = localStorage.getItem(key);
                if (slotVeri) {
                    var veri = JSON.parse(slotVeri);
                    var hasItems = veri.sepet && veri.sepet.length > 0;
                    btn.classList.toggle("dolu", hasItems);
                    
                    if (hasItems) {
                        var hasExchange = veri.sepet.some(function(item) { return item._is_exchange_return; });
                        var isEditing = !!veri.editingOrderId;
                        
                        btn.classList.toggle("duzenleme", isEditing);
                        btn.classList.toggle("degisim", !isEditing && hasExchange);
                    } else {
                        btn.classList.remove("duzenleme", "degisim");
                    }
                } else {
                    btn.classList.remove("dolu", "duzenleme", "degisim");
                }
            });
        },

        /**
         * Ana sepet arayüzünü yeniden çiz
         */
        arayuzuGuncelle: function() {
            var state = HK.State;
            var els = this.els;
            var self = this;

            els.sepetListesi.innerHTML = "";
            var genelToplam = 0;

            state.sepet.forEach(function(item, index) {
                // --- Kupon satırı ---
                if (item.product_id === "COUPON") {
                    var lineTotal = item.price * item.quantity;
                    var li = document.createElement("li");
                    li.className = "coupon-cart-item";

                    li.innerHTML =
                        '<div class="urun-sol-kolon">' +
                            '<div style="width:40px; height:40px; background:#e8f8f5; border: 1px dashed #27ae60; border-radius:4px; margin-right:10px; flex-shrink:0; display:flex; align-items:center; justify-content:center;">' +
                                '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#27ae60" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' +
                                    '<path d="M2 9a3 3 0 0 1 0 6v2a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2a3 3 0 0 1 0-6V7a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2Z"></path>' +
                                    '<path d="M13 5v14" stroke-dasharray="3 3"></path>' +
                                '</svg>' +
                            '</div>' +
                            '<span class="urun-bilgi">' +
                                '<strong class="urun-ad">' + item.name + '</strong>' +
                                '<span class="urun-sku" style="color:#27ae60; font-size:12px; font-weight:bold;">' + (item.sku || '') + ' <span class="exchange-badge" style="background:#27ae60; color:#fff; padding: 2px 6px; border-radius: 4px; font-size: 10px; margin-left: 5px;">KUPON</span></span>' +
                            '</span>' +
                        '</div>' +
                        '<div class="urun-orta-detay">' +
                            '<span class="urun-detay-metin" style="font-size:15px; font-weight:bold; color:#27ae60;">' +
                                item.quantity + ' Adet</span>' +
                        '</div>' +
                        '<div class="urun-sag-aksiyonlar">' +
                            '<span class="urun-fiyat-grup" style="text-align:right; flex-shrink:0;">' +
                                '<div class="ara-toplam" style="font-size: 19px; color: #27ae60; font-weight: 800; line-height: 1.1;">' + lineTotal.toFixed(2) + ' TL</div>' +
                            '</span>' +
                        '</div>';

                    // Silme (çıkarma) butonu
                    var silButon = document.createElement("button");
                    silButon.innerText = "✕";
                    silButon.className = "btn-adet exchange-remove-btn";
                    silButon.title = "Kuponu kaldır";
                    silButon.addEventListener("click", (function(idx) {
                        return function() {
                            state.sepet.splice(idx, 1);
                            self.arayuzuGuncelle();
                        };
                    })(index));

                    var sagAksiyonlar = li.querySelector(".urun-sag-aksiyonlar");
                    sagAksiyonlar.prepend(silButon);
                    els.sepetListesi.appendChild(li);
                    return; // forEach'in sonraki iterasyonuna geç
                }

                // --- Negatif satır (Değişim iade ürünü) ---
                if (item.quantity < 0) {
                    var absQty = Math.abs(item.quantity);
                    var lineTotal = item.price * absQty;
                    var li = document.createElement("li");
                    li.className = "exchange-return-item";

                    li.innerHTML =
                        '<div class="urun-sol-kolon">' +
                            (item.image ? '<img src="' + item.image + '" class="urun-resim" style="width:40px; height:40px; object-fit:cover; border-radius:4px; margin-right:10px; flex-shrink:0; opacity:0.7;">' : '<div style="width:40px; height:40px; background:var(--hk-danger, #e74c3c); border-radius:4px; margin-right:10px; flex-shrink:0; display:flex; align-items:center; justify-content:center; font-size:18px;">🔄</div>') +
                            '<span class="urun-bilgi">' +
                                '<strong class="urun-ad">' + item.name + '</strong>' +
                                '<span class="urun-sku" style="color:#e74c3c; font-size:12px; font-weight:bold;">' + (item.sku || '') + ' <span class="exchange-badge">İADE</span></span>' +
                            '</span>' +
                        '</div>' +
                        '<div class="urun-orta-detay">' +
                            '<span class="urun-detay-metin" style="font-size:15px; font-weight:bold; color:var(--hk-danger, #e74c3c);">' +
                                absQty + ' Adet x ' + item.price.toFixed(2) + ' TL</span>' +
                        '</div>' +
                        '<div class="urun-sag-aksiyonlar">' +
                            '<span class="urun-fiyat-grup" style="text-align:right; flex-shrink:0;">' +
                                '<div class="ara-toplam" style="font-size: 19px; color: #e74c3c; font-weight: 800; line-height: 1.1;">-' + lineTotal.toFixed(2) + ' TL</div>' +
                            '</span>' +
                        '</div>';

                    // Silme (çıkarma) butonu
                    var silButon = document.createElement("button");
                    silButon.innerText = "✕";
                    silButon.className = "btn-adet exchange-remove-btn";
                    silButon.title = "Değişim iade ürününü kaldır";
                    silButon.addEventListener("click", (function(idx) {
                        return function() {
                            state.sepet.splice(idx, 1);
                            self.arayuzuGuncelle();
                        };
                    })(index));

                    var sagAksiyonlar = li.querySelector(".urun-sag-aksiyonlar");
                    sagAksiyonlar.prepend(silButon);
                    els.sepetListesi.appendChild(li);
                    return; // forEach'in sonraki iterasyonuna geç
                }

                // --- Normal ürün satırı ---
                // Fiyat Katmanları
                var etiketFiyat = (item.regular_price || item.price) * item.quantity;
                var kampanyaFiyat = item.price * item.quantity;
                var hasAutoDiscount = (state.odemeTipi === "cash" || state.odemeTipi === "iban");

                // Ürüne düşen iskonto tutarı
                var urunIskonto = item.line_discount || 0;
                var iskontoluToplam = kampanyaFiyat - urunIskonto;

                // %5 önce (orijinal fiyata), iskonto sonra (üstünden düşülür)
                var netFiyat = (kampanyaFiyat * (hasAutoDiscount ? 0.95 : 1)) - urunIskonto;
                if (netFiyat < 0) netFiyat = 0;

                var itemId = item.product_id + '-' + (item.variation_id || 0);
                var isUpdated = (state.lastUpdatedId === itemId);
                var glowClass = (isUpdated && item.quantity > 1) ? 'hk-qty-glow' : '';

                var li = document.createElement("li");

                // Ekranda gösterilecek sanal birim fiyat (sadece görsel)
                var gosterilenBirim = item.quantity > 0 ? (netFiyat / item.quantity) : 0;
                
                var fiyatGosterim = gosterilenBirim.toFixed(2) + " TL";
                if (item.regular_price > item.price) {
                    fiyatGosterim = '<span style="text-decoration: line-through; color: #999; font-size: 0.9em; margin-right: 5px;">' + item.regular_price.toFixed(2) + ' TL</span> ' + gosterilenBirim.toFixed(2) + ' TL';
                } else if (urunIskonto > 0 || hasAutoDiscount) {
                    fiyatGosterim = '<span style="text-decoration: line-through; color: #999; font-size: 0.9em; margin-right: 5px;">' + item.price.toFixed(2) + ' TL</span> ' + gosterilenBirim.toFixed(2) + ' TL';
                }

                // İskonto badge
                var iskontoBadge = '';
                if (urunIskonto > 0.001) {
                    iskontoBadge = '<span class="urun-iskonto-badge">-' + urunIskonto.toFixed(2) + ' ₺</span>';
                }

                li.innerHTML =
                    '<div class="urun-sol-kolon">' +
                        (item.image ? '<img src="' + item.image + '" class="urun-resim" style="width:40px; height:40px; object-fit:cover; border-radius:4px; margin-right:10px; flex-shrink:0;">' : '<div style="width:40px; height:40px; background:#eee; border-radius:4px; margin-right:10px; flex-shrink:0;"></div>') +
                        '<span class="urun-bilgi">' +
                            '<strong class="urun-ad">' + item.name + '</strong>' +
                            '<span class="urun-sku" style="color:#666; font-size:13px; font-weight:bold; margin-bottom:2px;">' + item.sku + '</span>' +
                        '</span>' +
                    '</div>' +
                    '<div class="urun-orta-detay">' +
                        '<span class="urun-detay-metin" style="font-size:15px; font-weight:bold; color:var(--hk-text-main);">' + 
                            '<span class="' + glowClass + '">' + item.quantity + '</span>' + 
                            ' Adet x <span class="urun-birim-fiyat-alan" data-index="' + index + '">' + gosterilenBirim.toFixed(2) + '</span> TL</span>' +
                        iskontoBadge +
                    '</div>' +
                    '<div class="urun-sag-aksiyonlar">' +
                        '<span class="urun-fiyat-grup" style="text-align:right; flex-shrink:0;">' +
                            (etiketFiyat > kampanyaFiyat ? '<div style="font-size: 11px; color: #bbb; text-decoration: line-through; line-height: 1.1;">' + etiketFiyat.toFixed(2) + ' TL</div>' : '') +
                            (kampanyaFiyat > iskontoluToplam + 0.001 ? '<div style="font-size: 11px; color: #bbb; text-decoration: line-through; line-height: 1.1;">' + kampanyaFiyat.toFixed(2) + ' TL</div>' : '') +
                            (iskontoluToplam > netFiyat + 0.001 ? '<div style="font-size: 11px; color: #bbb; text-decoration: line-through; line-height: 1.1;">' + iskontoluToplam.toFixed(2) + ' TL</div>' : '') +
                            '<div class="ara-toplam urun-satir-toplam-alan" data-index="' + index + '" style="font-size: 19px; color: #27ae60; font-weight: 800; line-height: 1.1; margin-top: 2px;">' + netFiyat.toFixed(2) + ' TL</div>' +
                        '</span>' +
                    '</div>';

                // Click-to-edit: Birim fiyat alanı
                var birimFiyatSpan = li.querySelector(".urun-birim-fiyat-alan");
                if (birimFiyatSpan) {
                    birimFiyatSpan.style.cursor = "pointer";
                    birimFiyatSpan.title = "Tıkla → Birim fiyatı düzenle";
                    birimFiyatSpan.addEventListener("click", (function(idx, el) {
                        return function(e) {
                            e.stopPropagation();
                            self._clickToEdit(el, idx, 'birim');
                        };
                    })(index, birimFiyatSpan));
                }

                // Click-to-preview: Ürün görselini büyüt
                var imgEl = li.querySelector(".urun-resim");
                if (imgEl) {
                    imgEl.style.cursor = "zoom-in";
                    imgEl.addEventListener("click", function(e) {
                        e.stopPropagation();
                        var src = item.image;
                        if (window.HizliKasa && window.HizliKasa.StockTerminal && typeof window.HizliKasa.StockTerminal.openImagePreview === 'function') {
                            window.HizliKasa.StockTerminal.openImagePreview(src);
                        } else {
                            self.openImagePreview(src);
                        }
                    });
                }

                // Click-to-edit: Satır toplam alanı
                var satirToplamSpan = li.querySelector(".urun-satir-toplam-alan");
                if (satirToplamSpan) {
                    satirToplamSpan.style.cursor = "pointer";
                    satirToplamSpan.title = "Tıkla → Satır toplamını düzenle";
                    satirToplamSpan.addEventListener("click", (function(idx, el) {
                        return function(e) {
                            e.stopPropagation();
                            self._clickToEdit(el, idx, 'toplam');
                        };
                    })(index, satirToplamSpan));
                }

                var sagAksiyonlar = li.querySelector(".urun-sag-aksiyonlar");
                var azaltButon = document.createElement("button");
                azaltButon.innerText = "-";
                azaltButon.className = "btn-adet";
                azaltButon.addEventListener("click", (function(idx) {
                    return function() {
                        if (state.sepet[idx].quantity > 1) {
                            state.sepet[idx].quantity -= 1;
                        } else {
                            state.sepet.splice(idx, 1);
                        }

                        // Ürün eksiltildiğinde iskonto tutarını güncelle (yeniden dağıtmadan), ödeme yöntemini sıfırla
                        var resetMesajlari = [];
                        
                        if (state.iskontoTutar > 0) {
                            if (state.sepet.length === 0) {
                                HK.CartManager.iskontoTemizle();
                                resetMesajlari.push("iskonto sıfırlandı");
                            } else {
                                HK.CartManager.iskontoTutariniGuncelle();
                                resetMesajlari.push("iskonto güncellendi");
                            }
                        }
                        
                        if (state.odemeTipi !== "card" || state.splitData !== null) {
                            state.odemeTipi = "card";
                            state.splitData = null;
                            resetMesajlari.push("ödeme yöntemi sıfırlandı");
                        }

                        if (resetMesajlari.length > 0) {
                            var mesaj = "Sepet değişti: " + resetMesajlari.join(", ") + ".";
                            self.showToast(mesaj, "info");
                        }

                        self.arayuzuGuncelle();
                    };
                })(index));

                sagAksiyonlar.prepend(azaltButon);
                els.sepetListesi.appendChild(li);
            });

            // Toplam Hesaplamalar
            var sepetAraToplam = 0;
            var sepetListeToplami = 0;
            var sepetIskontoluToplam = 0;
            var autoDiscountBase = 0;
            var couponAmount = 0;
            
            var sepetKalem = 0;
            var sepetAdet = 0;
            var iadeAdet = 0;

            state.sepet.forEach(function(item) {
                if (item.product_id === "COUPON") {
                    couponAmount += Math.abs(item.price * item.quantity);
                    return;
                }

                sepetAraToplam += (item.price * item.quantity);
                sepetListeToplami += ((item.regular_price || item.price) * item.quantity);
                sepetIskontoluToplam += ((item.price * item.quantity) - (item.line_discount || 0));

                if (item.quantity < 0) {
                    // İade/Değişim ürünü
                    iadeAdet += Math.abs(item.quantity);
                } else {
                    // Normal ürün
                    sepetKalem++;
                    sepetAdet += item.quantity;
                }

                if (!item._is_exchange_return && item.quantity > 0) {
                    autoDiscountBase += (item.price * item.quantity);
                }
            });

            if (els.sepetIstatistikArea) {
                var istatistikMetni = sepetKalem + " Kalem / " + sepetAdet + " Adet";
                if (iadeAdet > 0) {
                    istatistikMetni += " <span style='color:#e74c3c; margin-left:5px;'>(+ " + iadeAdet + " İade)</span>";
                }
                els.sepetIstatistikArea.innerHTML = istatistikMetni;
            }

            var nakitIndirimTutar = 0;
            // %5 önce (orijinal fiyata uygulanır), iskonto sonra (üstünden düşülür)
            if ((state.odemeTipi === "cash" || state.odemeTipi === "iban")) {
                nakitIndirimTutar = autoDiscountBase * 0.05;
                els.nakitIndirimSatiri.style.setProperty("display", "flex", "important");
                els.nakitIndirimDegerArea.innerText = "-" + nakitIndirimTutar.toFixed(2) + " TL";
                els.nakitIndirimEtiket.innerText = state.odemeTipi === "cash" ? "NAKİT İNDİRİMİ (%5):" : "HAVALE İNDİRİMİ (%5):";
            } else {
                els.nakitIndirimSatiri.style.setProperty("display", "none", "important");
            }

            if (state.iskontoTutar > 0) {
                els.indirimSatiri.style.setProperty("display", "flex", "important");
                els.indirimDegerArea.innerText = "-" + state.iskontoTutar.toFixed(2) + " TL";
                if (els.iskontoTemizleBtn) els.iskontoTemizleBtn.style.display = "inline-block";
            } else {
                els.indirimSatiri.style.setProperty("display", "none", "important");
                if (els.iskontoTemizleBtn) els.iskontoTemizleBtn.style.display = "none";
            }

            // Son Toplam = (AraToplam - NakitIndirim) - İskonto - Kupon
            var sonToplam = sepetAraToplam - nakitIndirimTutar - state.iskontoTutar;
            if (couponAmount > 0) {
                sonToplam -= couponAmount;
            }
            // Değişim iade satırları olsa bile toplam negatif gözükmesin (Müşteriye para üstü vermiyoruz)
            var hasExchangeItems = state.sepet.some(function(item) { return item._is_exchange_return; });
            if (sonToplam < 0) sonToplam = 0;

            els.listeToplamiSatiri.style.setProperty("display", "flex", "important");
            els.listeToplamiArea.innerText = sepetListeToplami.toFixed(2) + " TL";

            els.araToplamArea.innerText = sepetAraToplam.toFixed(2) + " TL";

            if (couponAmount > 0) {
                if (els.kuponSatiri) els.kuponSatiri.style.setProperty("display", "flex", "important");
                if (els.kuponDegerArea) els.kuponDegerArea.innerText = "-" + couponAmount.toFixed(2) + " TL";
            } else {
                if (els.kuponSatiri) els.kuponSatiri.style.setProperty("display", "none", "important");
            }

            els.genelToplamArea.innerText = sonToplam.toFixed(2) + " TL";

            // Ödeme Tipi Butonlarını Senkronize Et
            document.querySelectorAll(".odeme-btn").forEach(function(btn) {
                var tip = btn.dataset.tip;
                if (tip === "split") {
                    btn.classList.toggle("aktif", state.splitData !== null);
                } else {
                    // Eğer bölünmüş ödeme varsa diğerleri aktif görünmesin
                    btn.classList.toggle("aktif", tip === state.odemeTipi && state.splitData === null);
                }
            });

            this._guncelleModBelirtecleri(hasExchangeItems);

            HK.CartManager.sepetiKaydet();
            this.notButonunuGuncelle();
            this._odemeOzetiniGuncelle();
            state.lastUpdatedId = null;
        },

        _guncelleModBelirtecleri: function(hasExchangeItems) {
            var state = HK.State;
            var frame = document.getElementById("kasa-dis-cerceve");
            var modAlani = document.getElementById("kasa-aktif-mod-alani");
            var onaylaBtn = document.getElementById("onayla-buton");
            
            // Eski block banner'lar varsa temizle
            var oldDuzenlemeBanner = document.getElementById("duzenleme-aktif-banner");
            var oldDegisimBanner = document.getElementById("degisim-aktif-banner");
            if (oldDuzenlemeBanner) oldDuzenlemeBanner.parentNode.removeChild(oldDuzenlemeBanner);
            if (oldDegisimBanner) oldDegisimBanner.parentNode.removeChild(oldDegisimBanner);

            if (state.editingOrderId) {
                // SİPARİŞ DÜZENLEME MODU (Mavi)
                if (frame) {
                    frame.classList.add("duzenleme-aktif");
                    frame.classList.remove("degisim-aktif");
                }

                if (modAlani) {
                    modAlani.innerHTML = '<div class="kasa-mod-badge duzenleme">' +
                                         '<span>✏️ Düzenleme: #' + state.editingOrderId + '</span>' +
                                         '<button class="mod-iptal-btn">İptal</button>' +
                                         '</div>';
                    
                    modAlani.querySelector(".mod-iptal-btn").addEventListener("click", function() {
                        if (confirm("Sipariş düzenleme modundan çıkmak istiyor musunuz? Sepet temizlenecektir.")) {
                            state.editingOrderId = null;
                            HK.CartManager.sepetiTemizle(state.aktifKasaId);
                            HK.UIRenderer.arayuzuGuncelle();
                        }
                    });
                }

                if (onaylaBtn) {
                    onaylaBtn.innerText = "Değişiklikleri Kaydet";
                    onaylaBtn.style.setProperty("background", "#3498db", "important");
                }
            } else if (hasExchangeItems) {
                // DEĞİŞİM MODU AKTİF (Turuncu)
                if (frame) {
                    frame.classList.add("degisim-aktif");
                    frame.classList.remove("duzenleme-aktif");
                }

                if (modAlani) {
                    var originalOrder = state.sepet.find(function(item) { return item._is_exchange_return && item._exchange_original_order; });
                    var orderNum = originalOrder ? originalOrder._exchange_original_order : "";
                    
                    modAlani.innerHTML = '<div class="kasa-mod-badge degisim">' +
                                         '<span>🔄 Değişim ' + (orderNum ? '(#' + orderNum + ')' : '') + '</span>' +
                                         '<button class="mod-iptal-btn">İptal</button>' +
                                         '</div>';
                    
                    modAlani.querySelector(".mod-iptal-btn").addEventListener("click", function() {
                        if (confirm("Değişim/İade işlemini iptal etmek istediğinize emin misiniz? Sepetteki tüm iade ürünleri çıkarılacaktır.")) {
                            state.sepet = state.sepet.filter(function(item) {
                                return !item._is_exchange_return;
                            });
                            HK.CartManager.sepetiKaydet();
                            HK.UIRenderer.arayuzuGuncelle();
                        }
                    });
                }

                if (onaylaBtn) {
                    onaylaBtn.innerText = "🔄 Değişimi Tamamla";
                    onaylaBtn.style.setProperty("background", "#e67e22", "important");
                }
            } else {
                // NORMAL SATIŞ MODU
                if (frame) {
                    frame.classList.remove("duzenleme-aktif", "degisim-aktif");
                }

                if (modAlani) {
                    modAlani.innerHTML = '';
                }

                if (onaylaBtn) {
                    onaylaBtn.innerText = "Sipariş Oluştur";
                    onaylaBtn.style.setProperty("background", "", "");
                }
            }
        },

        notButonunuGuncelle: function() {
            var btn = this.els.siparisNotuBtn || document.getElementById("siparis-notu-btn");
            if (!btn) return;

            var hasNote = !!(HK.State.siparisNotu && HK.State.siparisNotu.trim());
            btn.classList.toggle("not-var", hasNote);
            btn.title = hasNote ? "Sipariş Notunu Düzenle" : "Sipariş Notu Ekle";
        },

        /**
         * Click-to-edit: Fiyat alanını tıklayınca input'a çevir
         * @param {HTMLElement} el Tıklanan span
         * @param {number} index Sepet indeksi
         * @param {string} tip 'birim' veya 'toplam'
         */
        _clickToEdit: function(el, index, tip) {
            var state = HK.State;
            var item = state.sepet[index];
            if (!item) return;
            var self = this;

            // Zaten input modundaysa çık
            if (el.querySelector("input")) return;

            var hasAutoDiscount = (state.odemeTipi === "cash" || state.odemeTipi === "iban");
            var satirNakitIndirim = hasAutoDiscount ? (item.price * item.quantity * 0.05) : 0;
            var netSatirFiyati = (item.price * item.quantity) - satirNakitIndirim - (item.line_discount || 0);
            if (netSatirFiyati < 0) netSatirFiyati = 0;
            
            var mevcutDeger = tip === 'birim' ? (netSatirFiyati / item.quantity) : netSatirFiyati;

            var orijinalText = el.textContent;
            var input = document.createElement("input");
            input.type = "text";
            input.className = "urun-fiyat-edit-input";
            input.value = HK.CurrencyMask ? HK.CurrencyMask.format(mevcutDeger) : mevcutDeger.toFixed(2);
            input.inputMode = "decimal";

            el.textContent = "";
            el.appendChild(input);

            // CurrencyMask uygula
            if (HK.CurrencyMask && HK.CurrencyMask.apply) {
                HK.CurrencyMask.apply(input);
            }

            input.focus();
            input.select();

            var onayFunc = function() {
                var yeniDeger = HK.CurrencyMask ? HK.CurrencyMask.parse(input.value) : parseFloat(input.value.replace(',', '.'));
                if (isNaN(yeniDeger) || yeniDeger < 0) {
                    // Geçersiz → eski değere dön
                    self.arayuzuGuncelle();
                    return;
                }
                HK.CartManager.urunIskontoGuncelle(index, yeniDeger, tip);
            };

            var iptalFunc = function() {
                self.arayuzuGuncelle();
            };

            input.addEventListener("keydown", function(e) {
                if (e.key === "Enter") {
                    e.preventDefault();
                    onayFunc();
                } else if (e.key === "Escape") {
                    e.preventDefault();
                    iptalFunc();
                }
            });

            input.addEventListener("blur", function() {
                // Kısa gecikme: Enter tıklanmışsa blur'dan önce tetiklensin
                setTimeout(function() {
                    if (document.activeElement !== input) {
                        onayFunc();
                    }
                }, 150);
            });
        },

        /**
         * Bölünmüş ödeme varsa sağ taraftaki özeti güncelle
         */
        _odemeOzetiniGuncelle: function() {
            var state = HK.State;
            var els = this.els;
            if (!els.odemeOzetiAlani) return;

            els.odemeOzetiAlani.innerHTML = "";

            // Toplamı tekrar hesapla (Özet için) — %5 önce, iskonto sonra
            var sepetAraToplam = 0;
            var autoDiscountBase = 0;
            var couponAmount = 0;
            state.sepet.forEach(function(item) {
                if (item.product_id === "COUPON") {
                    couponAmount += Math.abs(item.price * item.quantity);
                    return;
                }
                sepetAraToplam += (item.price * item.quantity);
                if (!item._is_exchange_return && item.quantity > 0) {
                    autoDiscountBase += (item.price * item.quantity);
                }
            });
            var nakitIndirimTutar = ((state.odemeTipi === "cash" || state.odemeTipi === "iban")) ? (autoDiscountBase * 0.05) : 0;
            var sonToplam = sepetAraToplam - nakitIndirimTutar - state.iskontoTutar;
            if (couponAmount > 0) {
                sonToplam -= couponAmount;
            }
            var hasExchangeItems = state.sepet.some(function(item) { return item._is_exchange_return; });
            if (sonToplam < 0) sonToplam = 0;

            if (!state.splitData) {
                if (els.bolButon) els.bolButon.classList.remove("bol-aktif-glow");
                
                // Bölünmüş ödeme yoksa aktif kanalı göster
                var html = '<div style="font-weight:bold; font-size:11px; color:#95a5a6; margin-bottom:5px; text-transform:uppercase;">Ödeme Kanalı</div>';
                var kanalAd = "💳 Kredi Kartı";
                if (state.odemeTipi === "cash") kanalAd = "💵 Nakit";
                if (state.odemeTipi === "iban") kanalAd = "🏦 IBAN";

                html += '<div class="odeme-ozet-kart"> <span class="ozet-kanal">' + kanalAd + '</span> <span class="ozet-tutar">' + sonToplam.toFixed(2) + ' TL</span> </div>';
                els.odemeOzetiAlani.innerHTML = html;
                return;
            }

            // Glow efektini aç
            if (els.bolButon) els.bolButon.classList.add("bol-aktif-glow");

            var html = '<div style="font-weight:bold; font-size:11px; color:#95a5a6; margin-bottom:5px; text-transform:uppercase;">Ödeme Dağılımı</div>';
            
            if (state.splitData.nakit > 0) {
                html += '<div class="odeme-ozet-kart"> <span class="ozet-kanal">💵 Nakit</span> <span class="ozet-tutar">' + state.splitData.nakit.toFixed(2) + ' TL</span> </div>';
            }
            if (state.splitData.kart > 0) {
                html += '<div class="odeme-ozet-kart"> <span class="ozet-kanal">💳 Kredi Kartı</span> <span class="ozet-tutar">' + state.splitData.kart.toFixed(2) + ' TL</span> </div>';
            }
            if (state.splitData.iban > 0) {
                html += '<div class="odeme-ozet-kart"> <span class="ozet-kanal">🏦 IBAN</span> <span class="ozet-tutar">' + state.splitData.iban.toFixed(2) + ' TL</span> </div>';
            }

            // Tutar Uyuşmazlığı Kontrolü
            var girenToplam = (state.splitData.nakit || 0) + (state.splitData.kart || 0) + (state.splitData.iban || 0);
            var fark = sonToplam - girenToplam;
            if (Math.abs(fark) >= 0.01) {
                html += '<div style="color:#e74c3c; font-weight:bold; font-size:11px; margin-top:5px; border:1px solid #e74c3c; padding:5px; border-radius:4px; background:#fff5f5; text-align:center;">⚠️ Tutar Uyuşmazlığı! <br>Lütfen ödemeyi tekrar ayarla.</div>';
            }

            els.odemeOzetiAlani.innerHTML = html;
        },

        /**
         * Ödeme tipi butonlarını bağla
         */
        _bindOdemeTipiSecici: function() {
            var self = this;
            document.querySelectorAll(".odeme-btn").forEach(function(btn) {
                btn.addEventListener("click", function() {
                    var tip = this.dataset.tip;
                    if (tip === "split") return; // ModalManager zaten bol-buton ID'sini dinliyor

                    // Ödeme tipi değişirse veya split iptal olursa iskontoyu sıfırla
                    if (HK.State.odemeTipi !== tip || HK.State.splitData !== null) {
                        HK.CartManager.iskontoTemizle();
                    }

                    // Diğer butonlara basıldığında bölünmüş ödemeyi sıfırla
                    HK.State.splitData = null;
                    HK.State.odemeTipi = tip;
                    self.arayuzuGuncelle();
                });
            });
        },

        /**
         * İskonto temizleme butonunu bağla
         */
        _bindIskontoTemizle: function() {
            var self = this;
            if (!this.els.iskontoTemizleBtn) return;

            this.els.iskontoTemizleBtn.addEventListener("click", function() {
                HK.CartManager.iskontoTemizle();
                self.arayuzuGuncelle();
                self.showToast("İskonto sıfırlandı.", "info");
            });
        },

        /**
         * Sidebar kasa butonlarını bağla
         */
        _bindSidebarButonlari: function() {
            var state = HK.State;
            this.els.sidebarButtons.forEach(function(btn) {
                btn.addEventListener("click", function() {
                    var id = this.dataset.id;
                    if (!id) return; // Kasa ID yoksa işlem yapma (Rapor butonları vb.)

                    var yeniId = parseInt(id);
                    if (yeniId === state.aktifKasaId) return;

                    // Mevcut kasayı kaydet, yenisini yükle
                    HK.CartManager.sepetiKaydet();
                    HK.CartManager.sepetiYukle(yeniId);

                    var durumMetni = document.getElementById("durum");
                    durumMetni.innerText = "Kasa " + yeniId + " Aktif (v" + state.CURRENT_VERSION + ")";
                    durumMetni.style.color = "#2c3e50";

                    jQuery(document).trigger('hk:kasa-degisti');
                });
            });
        },

        /**
         * Toast bildirim gösterir
         * @param {string} msg Mesaj
         * @param {string} type 'success', 'warning', 'error', 'info'
         * @param {boolean} requireClose Manuel kapatma gerektirir mi?
         */
        showToast: function(msg, type, requireClose) {
            console.log("HK Toast:", type, msg); // Debug log
            
            type = type || 'info';
            // Hatalar veya açıkça istenen durumlar manuel kapatılır
            requireClose = (requireClose === true) || (type === 'error'); 

            var container = document.getElementById('hk-toast-container');
            if (!container) {
                console.error("Toast container (hk-toast-container) bulunamadı!");
                // Yedek: Eğer konteyner yoksa ama çok gerekliyse (hata) alert bas
                if (type === 'error') alert("HATA: " + msg);
                return;
            }

            var toast = document.createElement('div');
            toast.className = 'hk-toast ' + type;
            
            var icon = 'ℹ️';
            if (type === 'success') icon = '✅';
            if (type === 'warning') icon = '⚠️';
            if (type === 'error')   icon = '❌';

            var closeBtnHtml = requireClose ? '<span class="toast-close" style="cursor:pointer; margin-left:auto; font-weight:bold; font-size:20px; padding:0 5px; line-height:1;">&times;</span>' : '';

            toast.innerHTML = 
                '<span class="toast-icon">' + icon + '</span>' +
                '<span class="toast-msg">' + msg + '</span>' +
                closeBtnHtml;

            container.appendChild(toast);

            if (requireClose) {
                var closeBtn = toast.querySelector('.toast-close');
                if (closeBtn) {
                    closeBtn.onclick = function() {
                        toast.classList.add('fade-out');
                        setTimeout(function() {
                            if (toast.parentNode) toast.parentNode.removeChild(toast);
                        }, 500);
                    };
                }
            } else {
                // 4 saniye sonra otomatik kaldır
                setTimeout(function() {
                    if (toast && toast.parentNode) {
                        toast.classList.add('fade-out');
                        setTimeout(function() {
                            if (toast.parentNode) {
                                toast.parentNode.removeChild(toast);
                            }
                        }, 500);
                    }
                }, 4000);
            }
        },

        /**
         * Para birimini formatlar (Örn: 1.234,56)
         * @param {number} tutar 
         * @returns {string}
         */
        formatPara: function(tutar) {
            if (isNaN(tutar)) return "0,00";
            return parseFloat(tutar).toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
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
