/**
 * Hızlı Kasa - Barkod Okuyucu (Barcode Scanner)
 *
 * Klavye dinleyicisi ile barkod okuyucu cihazından
 * gelen karakter akışını yakalayıp ürün arama tetikler.
 *
 * @package HizliKasa
 */

(function(HK) {
    'use strict';

    HK.BarcodeScanner = {

        aktifBarkod: "",

        /**
         * Barkod okuyucu dinleyicisini başlat
         */
        init: function() {
            var self = this;
            var barkodIzleme = document.getElementById("barkod-izleme");
            var durumMetni = document.getElementById("durum");

            document.addEventListener("keydown", async function(e) {
                // Sadece Kasa sekmesi aktifken dinle
                const activeTab = document.querySelector('.ust-sekme.aktif');
                if (!activeTab || activeTab.getAttribute('data-tab') !== 'kasa') {
                    return;
                }

                // Eğer bir input veya textarea odaklıysa barkod dinleyiciyi devre dışı bırak
                // (Manuel giriş yapılmasına izin ver)
                if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') {
                    return;
                }

                // Modal açıkken barkod dinleme
                var iskontoModal = document.getElementById("iskonto-modal");
                var fisOnayModal = document.getElementById("fis-onay-modal");
                var urunAramaModal = document.getElementById("urun-arama-modal");
                var bolModal = document.getElementById("odeme-bol-modal");

                if ((iskontoModal && iskontoModal.style.display === "flex") ||
                    (fisOnayModal && fisOnayModal.style.display === "flex") ||
                    (urunAramaModal && urunAramaModal.style.display === "flex") ||
                    (bolModal && bolModal.style.display === "flex")) {
                    return;
                }

                var sistemTuslari = ["Shift", "Control", "Alt", "Meta", "CapsLock", "Tab", "Escape"];
                if (sistemTuslari.includes(e.key)) return;

                if (e.key === "Enter") {
                    if (self.aktifBarkod.trim() !== "") {
                        durumMetni.innerText = "Ürün aranıyor: " + self.aktifBarkod;
                        await self._urunuBulVeEkle(self.aktifBarkod);
                        self.aktifBarkod = "";
                        barkodIzleme.innerText = "...";
                    }
                } else if (e.key === "Backspace") {
                    self.aktifBarkod = self.aktifBarkod.slice(0, -1);
                    barkodIzleme.innerText = self.aktifBarkod || "...";
                    e.preventDefault();
                } else if (e.key.length === 1) {
                    self.aktifBarkod += e.key;
                    barkodIzleme.innerText = self.aktifBarkod;
                }
            });
        },

        /**
         * SKU ile ürün ara ve sepete ekle
         * @param {string} sku Barkod/SKU değeri
         */
        _urunuBulVeEkle: async function(sku) {
            var durumMetni = document.getElementById("durum");
            try {
                // kasaAyar.rootApiUrl kullanıyoruz (daha güvenli)
                var apiUrl = (typeof kasaAyar !== 'undefined' && kasaAyar.rootApiUrl) 
                    ? kasaAyar.rootApiUrl + 'hizli-kasa/v1/search'
                    : window.location.origin + '/wp-json/hizli-kasa/v1/search';

                // Aktif depo ID'sini gönder (depo stoğu bilgisi için)
                var depoId = (typeof HizliKasa !== 'undefined' && HizliKasa.DepoManager)
                    ? HizliKasa.DepoManager.getActiveDepo() : '';

                var response = await fetch(apiUrl + '?exact=1&s=' + encodeURIComponent(sku) + (depoId ? '&depo_id=' + depoId : ''), {
                    headers: { 'X-WP-Nonce': (typeof kasaAyar !== 'undefined' ? kasaAyar.nonce : '') }
                });

                if (!response.ok) {
                    throw new Error("Sunucu hatası: " + response.status);
                }

                var data = await response.json();
                
                // Response bir array değilse (WP Error dönerse) hata ver
                if (!Array.isArray(data)) {
                    throw new Error(data.message || "Bilinmeyen API Hatası");
                }

                var urun = data.find(function(item) { 
                    var trimmedSku = (item.sku ? item.sku.trim() : "");
                    return trimmedSku === sku.trim(); 
                }) || data[0];

                if (urun) {
                    HK.CartManager.ekleUrunObjesiyle(urun);
                } else {
                    durumMetni.innerText = "HATA: [" + sku + "] bulunamadı!";
                    durumMetni.style.color = "#e74c3c";
                    
                    // 3 saniye sonra mesajı temizle
                    setTimeout(function() {
                        if (durumMetni.innerText.includes("bulunamadı")) {
                            durumMetni.innerText = "Hazır (Barkod Bekleniyor)";
                            durumMetni.style.color = "";
                        }
                    }, 3000);
                }
            } catch (error) {
                console.error("API Hatası", error);
                durumMetni.innerText = "API HATASI: " + error.message;
                durumMetni.style.color = "red";
            }
        }
    };

})(window.HizliKasa);
