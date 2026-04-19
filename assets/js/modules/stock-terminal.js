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
            currentPage: 1,
            perPage: 24,
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

            // Depo değişince listeyi yenile
            document.addEventListener('hkActiveDepoChanged', function() {
                self.state.currentPage = 1;
                self.loadProducts();
            });

            // --- Paginaton Listeners ---
            var prevBtn = document.getElementById('prev-page');
            var nextBtn = document.getElementById('next-page');
            var perPageSelect = document.getElementById('per-page-select');

            if (prevBtn) {
                prevBtn.addEventListener('click', function() {
                    if (self.state.currentPage > 1) {
                        self.state.currentPage--;
                        self.loadProducts();
                    }
                });
            }

            if (nextBtn) {
                nextBtn.addEventListener('click', function() {
                    var maxPage = Math.ceil(self.state.total / self.state.perPage);
                    if (self.state.currentPage < maxPage) {
                        self.state.currentPage++;
                        self.loadProducts();
                    }
                });
            }

            if (perPageSelect) {
                perPageSelect.addEventListener('change', function() {
                    self.state.perPage = parseInt(this.value);
                    self.state.currentPage = 1;
                    self.loadProducts();
                });
            }

            // İlk ürünleri yükle
            this.loadProducts();

            // Arama dinleyicisi
            input.addEventListener('input', function() {
                clearTimeout(self.state.searchTimer);
                self.state.searchTimer = setTimeout(() => {
                    self.state.currentPage = 1;
                    self.loadProducts();
                }, 300);
            });

            // Barkod okuyucu desteği (Enter tuşu)
            input.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    self.state.currentPage = 1;
                    self.loadProducts();
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

            // --- ETKİNLİK DELEGASYONU ---
            var listContainer = document.getElementById('terminal-urun-listesi');
            if (listContainer) {
                listContainer.addEventListener('click', function(e) {
                    var kart = e.target.closest('.terminal-urun-kart');
                    if (!kart) return;

                    var id = parseInt(kart.dataset.id);
                    var vid = parseInt(kart.dataset.vid || 0);
                    var isParent = kart.classList.contains('terminal-parent-card');

                    if (isParent) {
                        var childContainer = document.getElementById('vars-' + id);
                        if (childContainer) {
                            var isHidden = window.getComputedStyle(childContainer).display === 'none';
                            childContainer.style.display = isHidden ? 'block' : 'none';
                            var icon = kart.querySelector('.expand-icon');
                            if (icon) {
                                icon.style.transform = isHidden ? 'rotate(180deg)' : 'rotate(0deg)';
                            }
                        }
                    } else {
                        var product = self.state.products.find(p => p.id === id);
                        if (vid > 0 && product && product.variations) {
                            var variation = product.variations.find(v => v.id === vid);
                            if (variation) self.openEditModal(variation);
                        } else if (product) {
                            self.openEditModal(product);
                        }
                    }
                });
            }
        },

        /**
         * Ürünleri API'den yükler.
         */
        loadProducts: async function() {
            if (this.state.isLoading) return;

            var container = document.getElementById('terminal-urun-listesi');
            if (!container) return;

            var depoId = (window.HizliKasa && HizliKasa.DepoManager)
                ? HizliKasa.DepoManager.getActiveDepo()
                : null;

            if (!depoId) {
                container.innerHTML = '<div class="terminal-uyari no-depo-warning"><h3>Profilinize depo atanmamış!</h3></div>';
                return;
            }

            var input = document.getElementById('terminal-arama-input');
            var s = input ? input.value : '';

            // Liste başa sarılıyor (her sayfa değişiminde liste temizlenir)
            this.state.products = [];
            container.innerHTML = '<div class="terminal-loading"><div class="spin"></div><p>Ürünler yükleniyor...</p></div>';
            
            this.state.isLoading = true;

            try {
                var offset = (this.state.currentPage - 1) * this.state.perPage;
                var url = kasaAyar.rootApiUrl + 'hizli-kasa/v1/terminal/products?limit=' + this.state.perPage + '&offset=' + offset + '&depo_id=' + depoId;
                if (s) url += '&s=' + encodeURIComponent(s);

                var response = await fetch(url, { headers: { 'X-WP-Nonce': kasaAyar.nonce } });
                if (!response.ok) throw new Error("Sunucu hatası: " + response.status);
                
                var data = await response.json();
                this.state.products = data.products || [];
                this.state.total = data.total || 0;

                // UI Güncelleme
                this.updatePaginationUI();
                
                if (document.getElementById('toplam-urun-sayisi')) {
                    document.getElementById('toplam-urun-sayisi').innerText = this.state.total;
                }
                if (document.getElementById('kritik-stok-sayisi')) {
                    document.getElementById('kritik-stok-sayisi').innerText = data.critical_count || 0;
                }

                this.renderProducts();
            } catch (e) {
                console.error("Hızlı Kasa: Yükleme hatası", e);
                container.innerHTML = '<div class="terminal-uyari"><p>Ürünler yüklenirken bir hata oluştu.</p></div>';
            } finally {
                this.state.isLoading = false;
            }
        },

        /**
         * Sayfalama arayüzünü günceller.
         */
        updatePaginationUI: function() {
            var total = this.state.total;
            var perPage = this.state.perPage;
            var current = this.state.currentPage;
            var maxPage = Math.ceil(total / perPage) || 1;

            var prevBtn = document.getElementById('prev-page');
            var nextBtn = document.getElementById('next-page');
            var pageDisp = document.getElementById('current-page-display');
            var rangeDisp = document.getElementById('range-display');

            if (prevBtn) prevBtn.disabled = (current <= 1);
            if (nextBtn) nextBtn.disabled = (current >= maxPage);
            if (pageDisp) pageDisp.innerText = 'Sayfa ' + current + ' / ' + maxPage;

            if (rangeDisp) {
                var start = (current - 1) * perPage + 1;
                var end = Math.min(current * perPage, total);
                if (total === 0) start = 0;
                rangeDisp.innerText = 'Gösterilen: ' + start + '-' + end + ' / ' + total;
            }
        },

        /**
         * Ürün listesini HTML olarak basar.
         */
        renderProducts: function() {
            var container = document.getElementById('terminal-urun-listesi');
            if (this.state.products.length === 0) {
                container.innerHTML = '<div class="terminal-uyari"><p>Ürün bulunamadı.</p></div>';
                return;
            }

            var html = '';
            var threshold = (typeof kasaAyar !== 'undefined' && kasaAyar.kritikStokEsigi) ? parseInt(kasaAyar.kritikStokEsigi) : 5;

            for (var i = 0; i < this.state.products.length; i++) {
                var p = this.state.products[i];
                if (!p) continue;

                var isVariable = p.is_variable && p.variations && p.variations.length > 0;
                var isCritical = !isVariable && p.warehouse_stock <= threshold;
                var img = p.images && p.images[0] ? p.images[0].src : '';
                
                html += `
                    <div class="terminal-urun-kart ${isVariable ? 'terminal-parent-card is-variable' : ''}" data-id="${p.id}" data-vid="0">
                        <img src="${img}" class="urun-img" alt="">
                        <div class="urun-detay">
                            <div class="urun-ad">${p.name} ${isVariable ? '<span class="var-badge">VARYASYONLU</span>' : ''}</div>
                            <div class="urun-sku">${p.sku || 'SKU YOK'} | Toplam: ${p.stock_quantity}</div>
                        </div>
                        ${!isVariable ? `
                        <div class="urun-stok ${isCritical ? 'stok-kritik' : 'stok-tamam'}">
                            <span class="stok-sayi">${p.warehouse_stock}</span>
                            <span class="stok-etiket">MEVCUT STOK</span>
                        </div>
                        ` : ''}
                        ${isVariable ? '<div class="expand-icon">▼</div>' : ''}
                    </div>
                `;

                if (isVariable) {
                    html += `<div class="terminal-variations-container" id="vars-${p.id}" style="display:none;">`;
                    p.variations.forEach(v => {
                        var vCritical = v.warehouse_stock <= threshold;
                        html += `
                            <div class="terminal-urun-kart variation-item" data-id="${v.parent_id}" data-vid="${v.id}">
                                <div class="variation-indent"></div>
                                <div class="urun-detay">
                                    <div class="urun-ad">${v.name}</div>
                                    <div class="urun-sku">${v.sku || 'SKU YOK'} | Toplam: ${v.stock_quantity}</div>
                                </div>
                                <div class="urun-stok ${vCritical ? 'stok-kritik' : 'stok-tamam'}">
                                    <span class="stok-sayi">${v.warehouse_stock}</span>
                                    <span class="stok-etiket">STOK</span>
                                </div>
                            </div>
                        `;
                    });
                    html += `</div>`;
                }
            }

            container.innerHTML = html;
        },


        /**
         * Stok düzenleme modalını açar.
         * Yönetim yetkisi yoksa butonlar devre dışı.
         */
        openEditModal: function(product) {
            this.state.selectedProduct = product;
            document.getElementById('modal-urun-adi').innerText = product.name;
            document.getElementById('modal-urun-detay').innerText = 'SKU: ' + (product.sku || '---');
            document.getElementById('modal-mevcut-qty').innerText = product.warehouse_stock;
            document.getElementById('modal-degisim-input').value = 1;

            var activeDepo = (window.HizliKasa && HizliKasa.DepoManager)
                ? HizliKasa.DepoManager.getActiveDepo()
                : null;
            var canManage = activeDepo && HizliKasa.DepoManager.canManageDepo(activeDepo);

            var saveBtn = document.getElementById('stok-kaydet-onay');
            if (saveBtn) {
                saveBtn.disabled = !canManage;
                saveBtn.title = canManage ? '' : 'Bu depoda yönetim yetkiniz yok';
                saveBtn.style.opacity = canManage ? '1' : '0.4';
            }

            // Readonly mesajı
            var readonlyMsg = document.getElementById('modal-readonly-msg');
            if (readonlyMsg) {
                readonlyMsg.style.display = canManage ? 'none' : 'block';
            }

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

            // Yönetim yetkisi kontrolü
            var activeDepo = (window.HizliKasa && HizliKasa.DepoManager)
                ? HizliKasa.DepoManager.getActiveDepo()
                : null;
            
            if (!activeDepo || !HizliKasa.DepoManager.canManageDepo(activeDepo)) {
                return alert('Bu depoda stok değiştirme yetkiniz yok.');
            }

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
                        product_id:    p.id,
                        variation_id:  p.variation_id || 0,
                        change:        change,
                        reason:        "Terminal Manuel Güncelleme",
                        active_depo_id: activeDepo
                    })
                });

                var res = await response.json();
                if (res.success) {
                    document.getElementById('stok-duzenle-modal').style.display = 'none';
                    this.loadProducts(false);
                } else {
                    alert("Hata: " + (res.message || res.data?.message || 'Bilinmeyen hata'));
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

})(window.HizliKasa = window.HizliKasa || {});
