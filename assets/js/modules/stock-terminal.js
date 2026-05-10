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
            requestController: null,
            lastRequestToken: 0,
            currentPage: 1,
            perPage: 24,
            isLoading: false,
            orderby: 'date',
            order: 'desc',
            total: 0,
            warehouses: []
        },

        init: function() {
            var self = this;
            
            // Çift başlatmayı önle
            if (this.initialized) return;

            var input = document.getElementById('terminal-arama-input');
            
            if (!input) {
                document.addEventListener('hkTabLoaded', function(e) {
                    if (e.detail.tab === 'urunler') {
                        self.init();
                    }
                });
                return;
            }

            this.initialized = true;

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

            // --- Sıralama Dinleyicisi ---
            var sortSelect = document.getElementById('terminal-siralama-select');
            if (sortSelect) {
                sortSelect.addEventListener('change', function() {
                    var parts = this.value.split('|');
                    self.state.orderby = parts[0];
                    self.state.order   = parts[1];
                    self.state.currentPage = 1;
                    self.loadProducts();
                });
            }

            // İlk ürünleri yükle
            this.loadProducts();

            // Arama dinleyicisi
            input.addEventListener('input', function() {
                clearTimeout(self.state.searchTimer);
                self.state.searchTimer = setTimeout(function() {
                    self.state.currentPage = 1;
                    self.loadProducts();
                }, 300);
            });

            // Barkod okuyucu desteği (Enter tuşu)
            input.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    clearTimeout(self.state.searchTimer);
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
                    var target = e.target;
                    var barkodTekliBtn = target.closest('.btn-barkod-tekli');
                    var urunGitBtn = target.closest('.btn-urun-git');
                    var kart = target.closest('.terminal-urun-kart');
                    
                    if (!kart) return;

                    var id = parseInt(kart.dataset.id);
                    var vid = parseInt(kart.dataset.vid || 0);
                    var isParent = kart.classList.contains('terminal-parent-card');

                    // Toplu Barkod Tıklama
                    if (barkodTopluBtn) {
                        e.stopPropagation();
                        e.preventDefault();
                        var product = self.state.products.find(p => p.id === id);
                        if (product && product.variations) {
                            if (window.HizliKasa.BarcodeRenderer) {
                                window.HizliKasa.BarcodeRenderer.openBulkModal(product);
                            } else {
                                console.error("Barkod motoru yüklenemedi!");
                            }
                        }
                        return;
                    }

                    // Tekli Barkod Tıklama
                    if (barkodTekliBtn) {
                        e.stopPropagation();
                        e.preventDefault();
                        var product = self.state.products.find(p => p.id === id);
                        if (vid > 0 && product && product.variations) {
                            var variation = product.variations.find(v => v.id === vid);
                            if (variation && window.HizliKasa.BarcodeRenderer) {
                                window.HizliKasa.BarcodeRenderer.openSingleModal(variation);
                            }
                        } else if (product) {
                            if (window.HizliKasa.BarcodeRenderer) {
                                window.HizliKasa.BarcodeRenderer.openSingleModal(product);
                            } else {
                                console.error("Barkod motoru yüklenemedi!");
                            }
                        }
                        return;
                    }

                    // Ürün Sayfasına Git Tıklama
                    if (urunGitBtn) {
                        e.stopPropagation();
                        e.preventDefault();
                        var url = urunGitBtn.dataset.url;
                        if (url) {
                            window.open(url, '_blank');
                        }
                        return;
                    }

                    // Kartın kendisine tıklanması (Stok Düzenleme veya Expand)
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
                        // Stok hareket modalı yarım kaldığı için geçici olarak devre dışı bırakıldı
                        /*
                        var product = self.state.products.find(p => p.id === id);
                        if (vid > 0 && product && product.variations) {
                            var variation = product.variations.find(v => v.id === vid);
                            if (variation) self.openEditModal(variation);
                        } else if (product) {
                            self.openEditModal(product);
                        }
                        */
                    }
                });
            }
        },

        /**
         * Ürünleri API'den yükler.
         */
        loadProducts: async function() {
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
            var rawSearch = input ? input.value : '';
            var s = rawSearch ? rawSearch.trim() : '';

            if (this.state.requestController) {
                this.state.requestController.abort();
            }

            var controller = typeof AbortController !== 'undefined' ? new AbortController() : null;
            var requestToken = ++this.state.lastRequestToken;
            this.state.requestController = controller;

            // Liste başa sarılıyor (her sayfa değişiminde liste temizlenir)
            this.state.products = [];
            container.innerHTML = '<div class="terminal-loading"><div class="spin"></div><p>Ürünler yükleniyor...</p></div>';
            
            this.state.isLoading = true;

            try {
                var offset = (this.state.currentPage - 1) * this.state.perPage;
                var url = kasaAyar.rootApiUrl + 'hizli-kasa/v1/terminal/products?limit=' + this.state.perPage + '&offset=' + offset + '&depo_id=' + depoId;
                if (s) url += '&s=' + encodeURIComponent(s);
                if (this.state.orderby) url += '&orderby=' + this.state.orderby;
                if (this.state.order)   url += '&order=' + this.state.order;
                var fetchOptions = { headers: { 'X-WP-Nonce': kasaAyar.nonce } };
                if (controller) {
                    fetchOptions.signal = controller.signal;
                }

                var response = await fetch(url, fetchOptions);
                if (!response.ok) throw new Error("Sunucu hatası: " + response.status);
                
                var data = await response.json();
                if (requestToken !== this.state.lastRequestToken) {
                    return;
                }

                this.state.products = data.products || [];
                this.state.warehouses = data.warehouses || [];
                this.state.total = data.total || 0;

                // UI Güncelleme
                this.updatePaginationUI();
                
                if (document.getElementById('basit-urun-sayisi')) {
                    document.getElementById('basit-urun-sayisi').innerText = data.simple_count || 0;
                }
                if (document.getElementById('varyasyonlu-urun-sayisi')) {
                    document.getElementById('varyasyonlu-urun-sayisi').innerText = data.variable_count || 0;
                }
                if (document.getElementById('toplam-kalem-sayisi')) {
                    document.getElementById('toplam-kalem-sayisi').innerText = data.grand_total_items || 0;
                }
                if (document.getElementById('kritik-stok-sayisi')) {
                    document.getElementById('kritik-stok-sayisi').innerText = data.critical_count || 0;
                }



                this.renderProducts();
            } catch (e) {
                if (e && e.name === 'AbortError') {
                    return;
                }
                console.error("Hızlı Kasa: Yükleme hatası", e);
                container.innerHTML = '<div class="terminal-uyari"><p>Ürünler yüklenirken bir hata oluştu.</p></div>';
            } finally {
                if (requestToken === this.state.lastRequestToken) {
                    this.state.isLoading = false;
                    this.state.requestController = null;
                }
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
            var self = this;
            var container = document.getElementById('terminal-urun-listesi');
            if (this.state.products.length === 0) {
                container.innerHTML = '<div class="terminal-uyari"><p>Ürün bulunamadı.</p></div>';
                return;
            }

            var depoId = (window.HizliKasa && HizliKasa.DepoManager)
                ? HizliKasa.DepoManager.getActiveDepo()
                : null;

            var html = '';
            var threshold = (typeof kasaAyar !== 'undefined' && kasaAyar.kritikStokEsigi) ? parseInt(kasaAyar.kritikStokEsigi) : 5;

            var getOtherStocksHtml = function(item) {
                var sHtml = '<div class="other-stocks-wrapper">';
                
                // Site Stoğu
                sHtml += `
                    <div class="other-stock-badge site-stock" title="WooCommerce Site Stoğu">
                        <span class="os-label">Site:</span>
                        <span class="os-val">${item.stock_quantity || 0}</span>
                    </div>
                `;

                // Diğer Depolar Toplamı
                var otherTotal = 0;
                var breakdown = [];
                if (item.all_stocks && self.state.warehouses) {
                    self.state.warehouses.forEach(w => {
                        if (w.id == depoId) return;
                        var qty = item.all_stocks[w.id] || 0;
                        otherTotal += qty;
                        if (qty > 0) {
                            breakdown.push(`${w.name}: ${qty}`);
                        }
                    });
                }

                sHtml += `
                    <div class="other-stock-badge other-depo" title="${breakdown.length > 0 ? breakdown.join(' | ') : 'Diğer depolarda stok yok'}">
                        <span class="os-label">D. Depo:</span>
                        <span class="os-val">${otherTotal}</span>
                    </div>
                `;

                sHtml += '</div>';
                return sHtml;
            };

            var isOnlyProduct = this.state.products.length === 1;
            for (var i = 0; i < this.state.products.length; i++) {
                var p = this.state.products[i];
                if (!p) continue;

                var isVariable = p.is_variable && p.variations && p.variations.length > 0;
                
                // Stok durumunu belirle (Grup toplamı)
                var totalGroupStock = isVariable 
                    ? p.variations.reduce((sum, v) => sum + parseFloat(v.warehouse_stock || 0), 0)
                    : parseFloat(p.warehouse_stock || 0);
                
                var isStockOut = totalGroupStock <= 0;
                var isCritical = !isVariable && p.warehouse_stock > 0 && p.warehouse_stock <= threshold;
                var img = p.images && p.images[0] ? p.images[0].src : '';
                
                html += `
                    <div class="terminal-urun-kart ${isVariable ? 'terminal-parent-card is-variable' : ''} ${isStockOut ? 'stock-out' : ''}" data-id="${p.id}" data-vid="0">
                        ${isStockOut ? '<div class="stock-out-overlay"></div>' : ''}
                        <img src="${img}" class="urun-img" alt="">
                        <div class="urun-detay">
                            <div class="urun-ad">${p.name} ${isVariable ? '<span class="var-badge">VARYASYONLU</span>' : ''}</div>
                            <div class="urun-sku">${p.sku || 'SKU YOK'} | Toplam: ${totalGroupStock}</div>
                        </div>
                        <div class="urun-ek-bilgi">
                            ${!isVariable ? getOtherStocksHtml(p) : ''}
                        </div>
                        <div class="urun-aksiyonlar">
                            ${isVariable ? `
                                <button class="btn-barkod-toplu" title="Tüm varyasyonlar için barkod çıkart">
                                    <span>🏷️</span> Toplu Barkod
                                </button>
                            ` : `
                                <button class="btn-barkod-tekli" title="Barkod çıkart">
                                    <span>🏷️</span> Barkod
                                </button>
                            `}
                            <button class="btn-urun-git" title="Ürün sayfasına git" data-url="${p.permalink}">
                                <span>ℹ️</span>
                            </button>
                        </div>
                        ${!isVariable ? `
                        <div class="urun-stok ${isStockOut ? 'stok-bitti' : (isCritical ? 'stok-kritik' : 'stok-tamam')}">
                            <span class="stok-sayi">${p.warehouse_stock}</span>
                            <span class="stok-etiket">MEVCUT STOK</span>
                        </div>
                        ` : ''}
                        ${isVariable ? `<div class="expand-icon" style="${isOnlyProduct ? 'transform: rotate(180deg);' : ''}">▼</div>` : ''}
                    </div>
                `;

                if (isVariable) {
                    html += `<div class="terminal-variations-container" id="vars-${p.id}" style="${isOnlyProduct ? 'display:block;' : 'display:none;'}">`;
                    
                    // Arama sorgusuyla tam eşleşen SKU'yu en başa getir (Orijinal diziyi bozmadan)
                    var query = (document.getElementById('terminal-arama-input')?.value || "").trim().toLowerCase();
                    var sortedVariations = [...p.variations].sort(function(a, b) {
                        var aSku = (a.sku || "").toLowerCase();
                        var bSku = (b.sku || "").toLowerCase();
                        if (aSku === query && bSku !== query) return -1;
                        if (bSku === query && aSku !== query) return 1;
                        return 0;
                    });

                    sortedVariations.forEach(v => {
                        var vCritical = v.warehouse_stock > 0 && v.warehouse_stock <= threshold;
                        var vImg = (v.images && v.images[0]) ? v.images[0].src : '';
                        
                        html += `
                            <div class="terminal-urun-kart variation-item" data-id="${v.parent_id}" data-vid="${v.id}">
                                <div class="variation-indent"></div>
                                <img src="${vImg || img}" class="variation-img" alt="">
                                <div class="urun-detay">
                                    <div class="urun-ad">${v.name}</div>
                                    <div class="urun-sku">${v.sku || 'SKU YOK'} | Toplam: ${v.warehouse_stock}</div>
                                </div>
                                <div class="urun-ek-bilgi">
                                    ${getOtherStocksHtml(v)}
                                </div>
                                <div class="urun-aksiyonlar">
                                    <button class="btn-barkod-tekli" title="Barkod çıkart">
                                        <span>🏷️</span> Barkod
                                    </button>
                                </div>
                                <div class="urun-stok ${v.warehouse_stock <= 0 ? 'stok-bitti' : (vCritical ? 'stok-kritik' : 'stok-tamam')}">
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
