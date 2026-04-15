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
                var response = await fetch(window.location.origin + '/wp-json/hizli-kasa/v1/search?s=' + encodeURIComponent(sku), {
                    headers: { 'X-WP-Nonce': kasaAyar.nonce }
                });
                var data = await response.json();
                var urun = data.find(function(item) { return item.sku === sku; }) || data[0];

                if (urun) {
                    HK.CartManager.ekleUrunObjesiyle(urun);
                } else {
                    durumMetni.innerText = "HATA: Ürün bulunamadı!";
                    durumMetni.style.color = "red";
                }
            } catch (error) {
                console.error("API Hatası", error);
            }
        }
    };

})(window.HizliKasa);
