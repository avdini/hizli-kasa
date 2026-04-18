/**
 * Hızlı Kasa - Stok Terminali Modülü
 *
 * Ürün arama, stok terminali arayüzü ve stok güncellemeleri.
 *
 * @package HizliKasa
 */

(function(HK) {
    'use strict';

    HK.StockTerminal = {
        
        state: {
            products: [],
            selectedProduct: null,
            searchTimer: null
        },

        init: function() {
            var self = this;
            var input = document.getElementById('terminal-arama-input');
            if (!input) return;

            // İlk ürünleri yükle
            this.loadProducts();

            // Arama dinleyicisi
            input.addEventListener('input', function() {
                clearTimeout(self.state.searchTimer);
                self.state.searchTimer = setTimeout(() => {
                    self.loadProducts(this.value);
                }, 300);
            });

            // Barkod okuyucu desteği (Enter tuşu)
            input.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    self.loadProducts(this.value, true);
                }
            });

            // Modal Butonları
            document.getElementById('stok-kaydet-iptal').addEventListener('click', () => {
                document.getElementById('stok-duzenle-modal').style.display = 'none';
            });

            document.getElementById('stok-kaydet-onay').addEventListener('click', () => {
                self.saveStockChange();
            });

            // Artırma/Eksiltme Butonları
            document.querySelector('.btn-artir').addEventListener('click', () => {
                var input = document.getElementById('modal-degisim-input');
                input.value = parseFloat(input.value) + 1;
            });

            document.querySelector('.btn-eksilt').addEventListener('click', () => {
                var input = document.getElementById('modal-degisim-input');
                input.value = parseFloat(input.value) - 1;
            });
        },

        /**
         * Ürünleri API'den yükler.
         */
        loadProducts: async function(s = '', exact = false) {
            var container = document.getElementById('terminal-urun-listesi');
            if (!container) return;

            try {
                var url = kasaAyar.rootApiUrl + 'hizli-kasa/v1/terminal/products?s=' + encodeURIComponent(s);
                if (exact) url += '&exact=1';

                var response = await fetch(url, { headers: { 'X-WP-Nonce': kasaAyar.nonce } });
                if (!response.ok) throw new Error("Sunucu hatası: " + response.status);
                
                var data = await response.json();

                this.state.products = (data && Array.isArray(data)) ? data : [];
                this.renderProducts();
            } catch (e) {
                console.error("Hızlı Kasa: Stok yükleme hatası", e);
                container.innerHTML = '<div class="terminal-uyari"><p>Ürünler yüklenirken bir hata oluştu: ' + e.message + '</p><button onclick="location.reload()" class="button">Tekrar Dene</button></div>';
            }
        },

        /**
         * Ürün listesini HTML olarak basar.
         */
        renderProducts: function() {
            var container = document.getElementById('terminal-urun-listesi');
            var totalEl = document.getElementById('toplam-urun-sayisi');
            var criticalEl = document.getElementById('kritik-stok-sayisi');

            if (this.state.products.length === 0) {
                container.innerHTML = '<div class="terminal-uyari"><p>Ürün bulunamadı.</p></div>';
                return;
            }

            var html = '';
            var criticalCount = 0;

            this.state.products.forEach(p => {
                if (!p) return; // Geçersiz ürünleri atla

                var isCritical = p.warehouse_stock <= 5;
                if (isCritical) criticalCount++;

                var img = p.images && p.images[0] ? p.images[0].src : '';
                
                html += `
                    <div class="terminal-urun-kart" data-id="${p.id}" data-vid="${p.variation_id || 0}">
                        <img src="${img}" class="urun-img" alt="">
                        <div class="urun-detay">
                            <div class="urun-ad">${p.name}</div>
                            <div class="urun-sku">${p.sku || 'SKU YOK'} | Toplam: ${p.stock_quantity}</div>
                        </div>
                        <div class="urun-stok ${isCritical ? 'stok-kritik' : 'stok-tamam'}">
                            <span class="stok-sayi">${p.warehouse_stock}</span>
                            <span class="stok-etiket">MEVCUT STOK</span>
                        </div>
                    </div>
                `;
            });

            container.innerHTML = html;
            totalEl.innerText = this.state.products.length;
            criticalEl.innerText = criticalCount;

            // Kartlara tıklama olayı ekle
            var self = this;
            container.querySelectorAll('.terminal-urun-kart').forEach(kart => {
                kart.addEventListener('click', function() {
                    var id = parseInt(this.dataset.id);
                    var vid = parseInt(this.dataset.vid);
                    var product = self.state.products.find(p => p.id === id);
                    self.openEditModal(product);
                });
            });
        },

        /**
         * Stok düzenleme modalını açar.
         */
        openEditModal: function(product) {
            this.state.selectedProduct = product;
            document.getElementById('modal-urun-adi').innerText = product.name;
            document.getElementById('modal-urun-detay').innerText = 'SKU: ' + (product.sku || '---');
            document.getElementById('modal-mevcut-qty').innerText = product.warehouse_stock;
            document.getElementById('modal-degisim-input').value = 1;

            document.getElementById('stok-duzenle-modal').style.display = 'flex';
        },

        /**
         * Stok değişimini sunucuya kaydeder.
         */
        saveStockChange: async function() {
            var p = this.state.selectedProduct;
            var change = parseFloat(document.getElementById('modal-degisim-input').value);
            var btn = document.getElementById('stok-kaydet-onay');

            if (isNaN(change) || change === 0) return alert("Lütfen geçerli bir miktar girin.");

            btn.disabled = true;
            btn.innerText = 'Kaydediliyor...';

            try {
                var response = await fetch(kasaAyar.rootApiUrl + 'hizli-kasa/v1/terminal/update-stock', {
                    method: 'POST',
                    headers: { 
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': kasaAyar.nonce 
                    },
                    body: JSON.stringify({
                        product_id: p.id,
                        variation_id: p.variation_id || 0,
                        change: change,
                        reason: "Terminal Manuel Güncelleme"
                    })
                });

                var res = await response.json();
                if (res.success) {
                    document.getElementById('stok-duzenle-modal').style.display = 'none';
                    this.loadProducts(document.getElementById('terminal-arama-input').value);
                } else {
                    alert("Hata: " + res.message);
                }
            } catch (e) {
                console.error(e);
                alert("Bir hata oluştu.");
            } finally {
                btn.disabled = false;
                btn.innerText = 'Hareketi Kaydet';
            }
        }
    };

})(window.HizliKasa);
