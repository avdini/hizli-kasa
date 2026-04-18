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
            searchTimer: null,
            offset: 0,
            hasMore: true,
            isLoading: false,
            total: 0
        },

        init: function() {
            var self = this;
            var input = document.getElementById('terminal-arama-input');
            
            // Eğer element henüz yoksa (sekme yüklenmemişse), yüklendiğinde tekrar dene
            if (!input) {
                document.addEventListener('hkTabLoaded', function(e) {
                    if (e.detail.tab === 'urunler') self.init();
                }, { once: true });
                return;
            }

            // Scroll Listener (Sonsuz Kaydırma)
            var container = document.getElementById('terminal-urun-listesi');
            if (container) {
                container.addEventListener('scroll', function() {
                    if (self.state.isLoading || !self.state.hasMore) return;
                    
                    // Alt sınıra 100px kala yeni yükleme yap
                    if (container.scrollTop + container.clientHeight >= container.scrollHeight - 100) {
                        self.loadProducts(true);
                    }
                });
            }

            // İlk ürünleri yükle
            this.loadProducts();

            // Arama dinleyicisi
            input.addEventListener('input', function() {
                clearTimeout(self.state.searchTimer);
                self.state.searchTimer = setTimeout(() => {
                    self.loadProducts(false); // Arama yaparken append false olmalı
                }, 300);
            });

            // Barkod okuyucu desteği (Enter tuşu)
            input.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    self.loadProducts(false); // Enter'a basınca aramayı tazele
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
        loadProducts: async function(append = false) {
            if (this.state.isLoading) return;

            var container = document.getElementById('terminal-urun-listesi');
            if (!container) return;

            var input = document.getElementById('terminal-arama-input');
            var s = input ? input.value : '';

            // Arama yapılıyorsa veya başa dönülüyorsa resetle
            if (!append) {
                this.state.offset = 0;
                this.state.products = [];
                this.state.hasMore = true;
                container.innerHTML = '<div class="terminal-loading"><div class="spin"></div><p>Ürünler yükleniyor...</p></div>';
            }

            this.state.isLoading = true;

            try {
                var url = kasaAyar.rootApiUrl + 'hizli-kasa/v1/terminal/products?limit=50&offset=' + this.state.offset;
                if (s) url += '&s=' + encodeURIComponent(s);

                var response = await fetch(url, { headers: { 'X-WP-Nonce': kasaAyar.nonce } });
                if (!response.ok) throw new Error("Sunucu hatası: " + response.status);
                
                var data = await response.json();

                // Yeni unified API yapısını işle
                var newProducts = data.products || [];
                this.state.products = append ? this.state.products.concat(newProducts) : newProducts;
                this.state.total = data.total || 0;
                this.state.hasMore = data.has_more || false;
                this.state.offset += newProducts.length;
                
                // İstatistikleri Güncelle
                if (document.getElementById('toplam-urun-sayisi')) {
                    var label = s ? 'Arama Sonucu' : 'Kayıtlı Ürün';
                    var labelEl = document.querySelector('.stat-item label[for="toplam-urun-sayisi"]');
                    if (labelEl) labelEl.innerText = label;
                    document.getElementById('toplam-urun-sayisi').innerText = this.state.total;
                }
                if (document.getElementById('kritik-stok-sayisi')) {
                    document.getElementById('kritik-stok-sayisi').innerText = data.critical_count || 0;
                }

                this.renderProducts(append);
            } catch (e) {
                console.error("Hızlı Kasa: Stok yükleme hatası", e);
                if (!append) {
                    container.innerHTML = '<div class="terminal-uyari"><p>Ürünler yüklenirken bir hata oluştu.</p><button onclick="location.reload()" class="button">Tekrar Dene</button></div>';
                }
                
                var debugPanel = document.getElementById('terminal-debug');
                if (debugPanel) {
                    debugPanel.style.display = 'block';
                    document.getElementById('debug-error-msg').innerText = e.message;
                    document.getElementById('debug-api-url').innerText = url;
                }
            } finally {
                this.state.isLoading = false;
            }
        },

        /**
         * Ürün listesini HTML olarak basar.
         */
        renderProducts: function(append = false) {
            var container = document.getElementById('terminal-urun-listesi');
            var criticalEl = document.getElementById('kritik-stok-sayisi');

            if (this.state.products.length === 0 && !append) {
                container.innerHTML = '<div class="terminal-uyari"><p>Ürün bulunamadı.</p></div>';
                return;
            }

            // Sadece yeni eklenenleri render etmek daha performanslıdır
            // Ancak mevcut yapı tüm state'i tuttuğu için basitleştirelim:
            var html = '';
            var criticalCount = 0;

            // Eğer append ise sadece son eklenenleri (son 50) dönelim
            var startIdx = append ? (this.state.products.length - 50) : 0;
            if (startIdx < 0) startIdx = 0;

            for (var i = startIdx; i < this.state.products.length; i++) {
                var p = this.state.products[i];
                if (!p) continue;

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
            }

            if (append) {
                // Eğer daha önce loading varsa temizle (opsiyonel)
                container.insertAdjacentHTML('beforeend', html);
            } else {
                container.innerHTML = html;
            }

            // Kartlara tıklama olayı ekle (Sadece yeni eklenenlere veya tümüne)
            var self = this;
            var targetKarts = append ? container.querySelectorAll('.terminal-urun-kart:nth-last-child(-n+50)') : container.querySelectorAll('.terminal-urun-kart');
            
            targetKarts.forEach(kart => {
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
