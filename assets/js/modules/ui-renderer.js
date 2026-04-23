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
                sidebarButtons: document.querySelectorAll(".sidebar-btn")
            };

            this._bindOdemeTipiSecici();
            this._bindSidebarButonlari();
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
                var slotVeri = localStorage.getItem('hizli_kasa_hafiza_slot_' + id);
                if (slotVeri) {
                    var veri = JSON.parse(slotVeri);
                    btn.classList.toggle("dolu", veri.sepet && veri.sepet.length > 0);
                } else {
                    btn.classList.remove("dolu");
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
                // Fiyat Katmanları
                var etiketFiyat = (item.regular_price || item.price) * item.quantity;
                var kampanyaFiyat = item.price * item.quantity;
                var hasAutoDiscount = (state.odemeTipi === "cash" || state.odemeTipi === "iban");
                var netFiyat = kampanyaFiyat * (hasAutoDiscount ? 0.95 : 1);

                var li = document.createElement("li");

                var fiyatGosterim = item.price.toFixed(2) + " TL";
                if (item.regular_price > item.price) {
                    fiyatGosterim = '<span style="text-decoration: line-through; color: #999; font-size: 0.9em; margin-right: 5px;">' + item.regular_price.toFixed(2) + ' TL</span> ' + item.price.toFixed(2) + ' TL';
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
                        '<span class="urun-detay-metin" style="font-size:15px; font-weight:bold; color:var(--hk-text-main);">' + item.quantity + ' Adet x ' + fiyatGosterim + '</span>' +
                    '</div>' +
                    '<div class="urun-sag-aksiyonlar">' +
                        '<span class="urun-fiyat-grup" style="text-align:right; flex-shrink:0;">' +
                            (etiketFiyat > kampanyaFiyat ? '<div style="font-size: 11px; color: #bbb; text-decoration: line-through; line-height: 1.1;">' + etiketFiyat.toFixed(2) + ' TL</div>' : '') +
                            (kampanyaFiyat > netFiyat ? '<div style="font-size: 11px; color: #bbb; text-decoration: line-through; line-height: 1.1;">' + kampanyaFiyat.toFixed(2) + ' TL</div>' : '') +
                            '<div class="ara-toplam" style="font-size: 19px; color: #27ae60; font-weight: 800; line-height: 1.1; margin-top: 2px;">' + netFiyat.toFixed(2) + ' TL</div>' +
                        '</span>' +
                    '</div>';

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
                        self.arayuzuGuncelle();
                    };
                })(index));

                sagAksiyonlar.prepend(azaltButon);
                els.sepetListesi.appendChild(li);
            });

            // Toplam Hesaplamalar
            var sepetAraToplam = 0;
            var sepetListeToplami = 0;
            state.sepet.forEach(function(item) {
                sepetAraToplam += (item.price * item.quantity);
                sepetListeToplami += ((item.regular_price || item.price) * item.quantity);
            });

            var nakitIndirimTutar = 0;
            if (state.odemeTipi === "cash" || state.odemeTipi === "iban") {
                nakitIndirimTutar = sepetAraToplam * 0.05;
                els.nakitIndirimSatiri.style.setProperty("display", "flex", "important");
                els.nakitIndirimDegerArea.innerText = "-" + nakitIndirimTutar.toFixed(2) + " TL";
                els.nakitIndirimEtiket.innerText = state.odemeTipi === "cash" ? "NAKİT İNDİRİMİ (%5):" : "HAVALE İNDİRİMİ (%5):";
            } else {
                els.nakitIndirimSatiri.style.setProperty("display", "none", "important");
            }

            if (state.iskontoTutar > 0) {
                els.indirimSatiri.style.setProperty("display", "flex", "important");
                els.indirimDegerArea.innerText = "-" + state.iskontoTutar.toFixed(2) + " TL";
            } else {
                els.indirimSatiri.style.setProperty("display", "none", "important");
            }

            var sonToplam = sepetAraToplam - nakitIndirimTutar - state.iskontoTutar;
            if (sonToplam < 0) sonToplam = 0;

            els.listeToplamiSatiri.style.setProperty("display", "flex", "important");
            els.listeToplamiArea.innerText = sepetListeToplami.toFixed(2) + " TL";

            els.araToplamArea.innerText = sepetAraToplam.toFixed(2) + " TL";
            els.genelToplamArea.innerText = sonToplam.toFixed(2) + " TL";

            // Ödeme Tipi Butonlarını Senkronize Et
            document.querySelectorAll(".odeme-btn").forEach(function(btn) {
                btn.classList.toggle("aktif", btn.dataset.tip === state.odemeTipi);
            });

            HK.CartManager.sepetiKaydet();
        },

        /**
         * Ödeme tipi butonlarını bağla
         */
        _bindOdemeTipiSecici: function() {
            var self = this;
            document.querySelectorAll(".odeme-btn").forEach(function(btn) {
                btn.addEventListener("click", function() {
                    HK.State.odemeTipi = this.dataset.tip;
                    self.arayuzuGuncelle();
                });
            });
        },

        /**
         * Sidebar kasa butonlarını bağla
         */
        _bindSidebarButonlari: function() {
            var state = HK.State;
            this.els.sidebarButtons.forEach(function(btn) {
                btn.addEventListener("click", function() {
                    var yeniId = parseInt(this.dataset.id);
                    if (yeniId === state.aktifKasaId) return;

                    // Mevcut kasayı kaydet, yenisini yükle
                    HK.CartManager.sepetiKaydet();
                    HK.CartManager.sepetiYukle(yeniId);

                    var durumMetni = document.getElementById("durum");
                    durumMetni.innerText = "Kasa " + yeniId + " Aktif (v" + state.CURRENT_VERSION + ")";
                    durumMetni.style.color = "#2c3e50";
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
        }
    };

})(window.HizliKasa);
