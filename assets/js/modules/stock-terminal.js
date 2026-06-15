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
            warehouses: [],
            filters: {
                category: 0,
                brand: 0,
                stockStatus: 'all'
            }
        },

        init: function() {
            var self = this;
            
            // Çift başlatmayı önle
            if (this.initialized) return;

            var input = document.getElementById('terminal-arama-input');
            
            if (!input) {
                if (!this._tabListenerAdded) {
                    this._tabListenerAdded = true;
                    document.addEventListener('hkTabLoaded', function(e) {
                        if (e.detail.tab === 'urunler') {
                            if (!self.initialized) {
                                self.init();
                            } else {
                                // Sekme tekrar açıldığında en güncel stokları çek
                                self.loadProducts();
                            }
                        }
                    });
                }
                return;
            }

            this.initialized = true;

            // Depo değişince listeyi yenile
            document.addEventListener('hkViewDepoChanged', function() {
                self.state.currentPage = 1;
                self.loadProducts();
            });

            // --- Filtre Paneli Toggle ---
            const filterToggleBtn = document.getElementById('btn-terminal-filtre-toggle');
            const filterBar = document.getElementById('terminal-filtre-bar');
            if (filterToggleBtn && filterBar) {
                filterToggleBtn.addEventListener('click', function() {
                    const isHidden = filterBar.style.display === 'none';
                    filterBar.style.display = isHidden ? 'flex' : 'none';
                    filterToggleBtn.classList.toggle('active', isHidden);
                    
                    // Eğer ilk kez açılıyorsa filtreleri yükle
                    if (isHidden && !self._filtersLoaded) {
                        self.loadFilterOptions();
                    }
                });
            }

            // --- Filtre Dinleyicileri ---
            const filterCat = document.getElementById('filter-category');
            const filterBrand = document.getElementById('filter-brand');
            const filterStock = document.getElementById('filter-stock-status');
            const clearFilters = document.getElementById('btn-clear-filters');

            if (filterCat) {
                filterCat.addEventListener('change', function() {
                    self.state.filters.category = parseInt(this.value);
                    self.state.currentPage = 1;
                    self.loadProducts();
                });
            }

            if (filterBrand) {
                filterBrand.addEventListener('change', function() {
                    self.state.filters.brand = parseInt(this.value);
                    self.state.currentPage = 1;
                    self.loadProducts();
                });
            }

            if (filterStock) {
                filterStock.addEventListener('change', function() {
                    self.state.filters.stockStatus = this.value;
                    self.state.currentPage = 1;
                    self.loadProducts();
                });
            }

            if (clearFilters) {
                clearFilters.addEventListener('click', function() {
                    if (filterCat) filterCat.value = 0;
                    if (filterBrand) filterBrand.value = 0;
                    if (filterStock) filterStock.value = 'all';
                    
                    self.state.filters.category = 0;
                    self.state.filters.brand = 0;
                    self.state.filters.stockStatus = 'all';
                    self.state.currentPage = 1;
                    self.loadProducts();
                });
            }

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

            // --- MOBİL ARAÇ QR MODALI ---
            const mobilAracBtn = document.getElementById('btn-mobil-arac-ac');
            const qrModal = document.getElementById('mobil-qr-modal');
            const closeQrBtn = document.getElementById('close-qr-modal');

            if (mobilAracBtn) {
                mobilAracBtn.addEventListener('click', () => {
                    const baseUrl = window.location.origin + window.location.pathname;
                    const mobileUrl = baseUrl + (baseUrl.includes('?') ? '&' : '?') + 'mode=mobile';
                    
                    // QR Kod API (QRServer)
                    const qrImg = `<img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=${encodeURIComponent(mobileUrl)}" alt="QR Code" style="display:block;">`;
                    document.getElementById('qr-code-display').innerHTML = qrImg;
                    document.getElementById('mobile-tool-url-text').innerText = mobileUrl;
                    
                    qrModal.style.display = 'flex';
                });
            }

            if (closeQrBtn) {
                closeQrBtn.addEventListener('click', () => {
                    qrModal.style.display = 'none';
                });
            }

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
                    var barkodTopluBtn = target.closest('.btn-barkod-toplu');
                    var barkodTekliBtn = target.closest('.btn-barkod-tekli');
                    var urunGitBtn = target.closest('.btn-urun-git');
                    var imgTarget = target.closest('.urun-img') || target.closest('.variation-img');
                    var depoKoduTarget = target.closest('.depo-kodu-container');
                    var kart = target.closest('.terminal-urun-kart');
                    
                    if (!kart) return;

                    // Depo Kodu / RAF alanına tıklanınca kart açılıp kapanmasını engelle
                    if (depoKoduTarget) {
                        e.stopPropagation();
                        return;
                    }

                    // Resme Tıklama (Önizleme)
                    if (imgTarget) {
                        e.stopPropagation();
                        e.preventDefault();
                        self.openImagePreview(imgTarget.src);
                        return;
                    }

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

                // Depo Kodu Değişikliği Event Delegation
                listContainer.addEventListener('change', function(e) {
                    var target = e.target;
                    if (target.classList.contains('hk-depo-kodu-input')) {
                        self.saveWarehouseCode(target);
                    }
                });

                listContainer.addEventListener('keypress', function(e) {
                    var target = e.target;
                    if (target.classList.contains('hk-depo-kodu-input') && e.key === 'Enter') {
                        target.blur(); // Triggers change event
                    }
                });
            }
        },

        /**
         * Filtre seçeneklerini (kategori ve marka) API'den yükler.
         */
        loadFilterOptions: async function() {
            if (this._filtersLoading) return;
            this._filtersLoading = true;

            try {
                const response = await fetch(kasaAyar.rootApiUrl + 'hizli-kasa/v1/terminal/filters', {
                    headers: { 'X-WP-Nonce': kasaAyar.nonce }
                });
                const data = await response.json();

                const filterCat = document.getElementById('filter-category');
                const filterBrand = document.getElementById('filter-brand');

                if (filterCat && data.categories) {
                    data.categories.forEach(cat => {
                        const opt = document.createElement('option');
                        opt.value = cat.id;
                        opt.textContent = cat.name;
                        filterCat.appendChild(opt);
                    });
                }

                if (filterBrand && data.brands) {
                    data.brands.forEach(brand => {
                        const opt = document.createElement('option');
                        opt.value = brand.id;
                        opt.textContent = brand.name;
                        filterBrand.appendChild(opt);
                    });
                }

                this._filtersLoaded = true;
            } catch (e) {
                console.error("Filtreler yüklenemedi", e);
            } finally {
                this._filtersLoading = false;
            }
        },

        /**
         * Ürünleri API'den yükler.
         */
        loadProducts: async function() {
            var container = document.getElementById('terminal-urun-listesi');
            if (!container) return;

            var depoId = (window.HizliKasa && HizliKasa.DepoManager)
                ? HizliKasa.DepoManager.getViewDepo()
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
                var url = kasaAyar.rootApiUrl + 'hizli-kasa/v1/terminal/products?limit=' + this.state.perPage + '&offset=' + offset + '&depo_id=' + depoId + '&_=' + Date.now();
                
                if (s) url += '&s=' + encodeURIComponent(s);
                if (this.state.orderby) url += '&orderby=' + this.state.orderby;
                if (this.state.order)   url += '&order=' + this.state.order;

                // Gelişmiş Filtreler
                if (this.state.filters.category > 0) url += '&cat=' + this.state.filters.category;
                if (this.state.filters.brand > 0)    url += '&brand=' + this.state.filters.brand;
                if (this.state.filters.stockStatus !== 'all') url += '&stock_status=' + this.state.filters.stockStatus;

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
                ? HizliKasa.DepoManager.getViewDepo()
                : null;
            var canManage = depoId && window.HizliKasa && HizliKasa.DepoManager && HizliKasa.DepoManager.canManageDepo(depoId);

            var html = '';
            var threshold = (typeof kasaAyar !== 'undefined' && kasaAyar.kritikStokEsigi) ? parseInt(kasaAyar.kritikStokEsigi) : 5;
            var priceFormatter = (window.HizliKasa && HizliKasa.CurrencyMask) ? HizliKasa.CurrencyMask.format : (n) => parseFloat(n).toFixed(2);

            var getOtherStocksHtml = function(item) {
                var sHtml = '<div class="other-stocks-wrapper">';
                
                // Site Stoğu
                sHtml += `
                    <div class="other-stock-badge site-stock" title="WooCommerce Site Stoğu">
                        <span class="os-label">Site:</span>
                        <span class="os-val">${item.stock_quantity || 0}</span>
                    </div>
                `;

                // Diğer Depolar (Ayrı ayrı göster)
                var hasOtherStock = false;
                if (item.all_stocks && self.state.warehouses) {
                    self.state.warehouses.forEach(w => {
                        if (w.id == depoId) return;
                        var qty = item.all_stocks[w.id] || 0;
                        if (qty > 0) {
                            hasOtherStock = true;
                            // İsim uzunsa kısalt
                            var shortName = w.name.length > 12 ? w.name.substring(0, 10) + '..' : w.name;
                            sHtml += `
                                <div class="other-stock-badge other-depo" title="${w.name} Stoğu">
                                    <span class="os-label">${shortName}:</span>
                                    <span class="os-val">${qty}</span>
                                </div>
                            `;
                        }
                    });
                }

                // Hiçbir diğer depoda stok yoksa "Diğer: 0" göster
                if (!hasOtherStock) {
                    sHtml += `
                        <div class="other-stock-badge other-depo" title="Diğer depolarda stok yok">
                            <span class="os-label">Diğer:</span>
                            <span class="os-val">0</span>
                        </div>
                    `;
                }

                sHtml += '</div>';
                return sHtml;
            };

            var isOnlyProduct = this.state.products.length === 1;
            for (var i = 0; i < this.state.products.length; i++) {
                var p = this.state.products[i];
                if (!p) continue;

                var isVariable = p.is_variable && p.variations && p.variations.length > 0;
                
                // Stok kaynağı: all_stocks[depoId] tercih, yoksa warehouse_stock fallback
                var pDepoStock = (p.all_stocks && depoId && p.all_stocks[String(depoId)] != null) ? parseFloat(p.all_stocks[String(depoId)]) : parseFloat(p.warehouse_stock || 0);

                // Stok durumunu belirle (Grup toplamı)
                var totalGroupStock = isVariable 
                    ? p.variations.reduce(function(sum, v) {
                        var vStock = (v.all_stocks && depoId && v.all_stocks[String(depoId)] != null) ? parseFloat(v.all_stocks[String(depoId)]) : parseFloat(v.warehouse_stock || 0);
                        return sum + vStock;
                    }, 0)
                    : pDepoStock;
                
                var isStockOut = totalGroupStock <= 0;
                var isCritical = !isVariable && pDepoStock > 0 && pDepoStock <= threshold;
                var img = p.images && p.images[0] ? p.images[0].src : '';

                // Fiyat Hesaplamaları
                var currentPrice = parseFloat(p.price || 0);
                var regularPrice = parseFloat(p.regular_price || 0);
                var hasSale = regularPrice > 0 && regularPrice > currentPrice;
                var cashPrice = currentPrice * 0.95;
                
                html += `
                    <div class="terminal-urun-kart ${isVariable ? 'terminal-parent-card is-variable' : ''} ${isStockOut ? 'stock-out' : ''}" data-id="${p.id}" data-vid="0">
                        ${isStockOut ? '<div class="stock-out-overlay"></div>' : ''}
                        <img src="${img}" class="urun-img" alt="">
                        <div class="urun-detay">
                            <div class="urun-temel-bilgi">
                                <div class="urun-ad">${p.name} ${isVariable ? '<span class="var-badge">VARYASYONLU</span>' : ''}</div>
                                <div class="urun-sku-grup">
                                    <div class="urun-sku">${p.sku || 'SKU YOK'} | Toplam: ${totalGroupStock}</div>
                                    <div class="depo-kodu-container ${canManage ? '' : 'readonly'}">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="opacity:0.7"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg>
                                        <span class="depo-kodu-label">RAF:</span>
                                        ${canManage ? `
                                        <input type="text" 
                                               class="hk-depo-kodu-input" 
                                               value="${p.all_codes && p.all_codes[String(depoId)] ? p.all_codes[String(depoId)] : ''}" 
                                               placeholder="---" 
                                               maxlength="6" 
                                        >
                                        ` : `
                                        <span class="hk-depo-kodu-text">${p.all_codes && p.all_codes[String(depoId)] ? p.all_codes[String(depoId)] : '---'}</span>
                                        `}
                                    </div>
                                </div>
                            </div>
                            
                            ${!isVariable ? `
                            <div class="terminal-fiyat-alani">
                                <div class="fiyat-satiri main-fiyat">
                                    ${hasSale ? `<span class="eski-fiyat">${priceFormatter(regularPrice)} TL</span>` : ''}
                                    <span class="liste-fiyat">${priceFormatter(currentPrice)} TL</span>
                                </div>
                                <div class="fiyat-satiri nakit-fiyat">
                                    <span class="nakit-etiket">%5 Nakit/IBAN:</span>
                                    <span class="nakit-deger">${priceFormatter(cashPrice)} TL</span>
                                </div>
                            </div>
                            ` : ''}
                        </div>
                        <div class="urun-aksiyonlar" style="margin-left: auto; margin-right: 15px;">
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
                                <span>i</span>
                            </button>
                        </div>
                        <div class="urun-stok-grup" style="display: flex; align-items: center; gap: 15px;">
                            ${!isVariable ? `
                            <div class="urun-ek-bilgi" style="flex: unset;">
                                ${getOtherStocksHtml(p)}
                            </div>
                            <div class="stok-ayirici" style="width: 2px; height: 35px; background: var(--hk-border); opacity: 0.4; border-radius: 2px;"></div>
                            <div class="urun-stok ${isStockOut ? 'stok-bitti' : (isCritical ? 'stok-kritik' : 'stok-tamam')}" style="min-width: 85px; text-align: right;">
                                <span class="stok-sayi">${pDepoStock}</span>
                                <span class="stok-etiket">MEVCUT STOK</span>
                            </div>
                            ` : ''}
                        </div>
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
                        var vDepoStock = (v.all_stocks && depoId && v.all_stocks[String(depoId)] != null) ? parseFloat(v.all_stocks[String(depoId)]) : parseFloat(v.warehouse_stock || 0);
                        var vCritical = vDepoStock > 0 && vDepoStock <= threshold;
                        var vImg = (v.images && v.images[0]) ? v.images[0].src : '';
                        
                        // Varyasyon Fiyatları
                        var vCurrentPrice = parseFloat(v.price || 0);
                        var vRegularPrice = parseFloat(v.regular_price || 0);
                        var vHasSale = vRegularPrice > 0 && vRegularPrice > vCurrentPrice;
                        var vCashPrice = vCurrentPrice * 0.95;

                        html += `
                            <div class="terminal-urun-kart variation-item" data-id="${v.parent_id}" data-vid="${v.id}">
                                <div class="variation-indent"></div>
                                <img src="${vImg || img}" class="variation-img" alt="">
                                <div class="urun-detay">
                                    <div class="urun-temel-bilgi">
                                        <div class="urun-ad">${v.name}</div>
                                        <div class="urun-sku-grup">
                                            <div class="urun-sku">${v.sku || 'SKU YOK'} | Toplam: ${vDepoStock}</div>
                                            <div class="depo-kodu-container ${canManage ? '' : 'readonly'}">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="opacity:0.7"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg>
                                                <span class="depo-kodu-label">RAF:</span>
                                                ${canManage ? `
                                                <input type="text" 
                                                       class="hk-depo-kodu-input" 
                                                       value="${v.all_codes && v.all_codes[String(depoId)] ? v.all_codes[String(depoId)] : ''}" 
                                                       placeholder="---" 
                                                       maxlength="6" 
                                                >
                                                ` : `
                                                <span class="hk-depo-kodu-text">${v.all_codes && v.all_codes[String(depoId)] ? v.all_codes[String(depoId)] : '---'}</span>
                                                `}
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="terminal-fiyat-alani">
                                        <div class="fiyat-satiri main-fiyat">
                                            ${vHasSale ? `<span class="eski-fiyat">${priceFormatter(vRegularPrice)} TL</span>` : ''}
                                            <span class="liste-fiyat">${priceFormatter(vCurrentPrice)} TL</span>
                                        </div>
                                        <div class="fiyat-satiri nakit-fiyat">
                                            <span class="nakit-etiket">%5 Nakit/IBAN:</span>
                                            <span class="nakit-deger">${priceFormatter(vCashPrice)} TL</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="urun-aksiyonlar" style="margin-left: auto; margin-right: 15px;">
                                    <button class="btn-barkod-tekli" title="Barkod çıkart">
                                        <span>🏷️</span> Barkod
                                    </button>
                                </div>
                                <div class="urun-stok-grup" style="display: flex; align-items: center; gap: 15px;">
                                    <div class="urun-ek-bilgi" style="flex: unset;">
                                        ${getOtherStocksHtml(v)}
                                    </div>
                                    <div class="stok-ayirici" style="width: 2px; height: 35px; background: var(--hk-border); opacity: 0.4; border-radius: 2px;"></div>
                                    <div class="urun-stok ${vDepoStock <= 0 ? 'stok-bitti' : (vCritical ? 'stok-kritik' : 'stok-tamam')}" style="min-width: 85px; text-align: right;">
                                        <span class="stok-sayi">${vDepoStock}</span>
                                        <span class="stok-etiket">STOK</span>
                                    </div>
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
        },

        /**
         * Ürün resmine tıklanınca yüksek çözünürlüklü halini gösterir
         */
        openImagePreview: function(src) {
            if (!src || src.includes('placeholder')) return;

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
            
            // Thumbnail suffix temizle (-150x150 gibi)
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
        },

        saveWarehouseCode: async function(input) {
            var kart = input.closest('.terminal-urun-kart');
            if (!kart) return;

            var id = parseInt(kart.dataset.id);
            var vid = parseInt(kart.dataset.vid || 0);
            var depoId = (window.HizliKasa && HizliKasa.DepoManager)
                ? HizliKasa.DepoManager.getViewDepo()
                : null;

            if (!depoId) return;

            // Yetki kontrolü (Yönetim yetkisi yoksa kaydetme)
            if (!window.HizliKasa || !HizliKasa.DepoManager || !HizliKasa.DepoManager.canManageDepo(depoId)) {
                alert('Bu depoda depo kodu değiştirme yetkiniz yok.');
                return;
            }

            var rawValue = input.value.trim().toUpperCase();
            
            if (rawValue !== '') {
                if (rawValue.length > 6 || !/^[A-Z0-9]+$/.test(rawValue)) {
                    alert('Depo kodu en fazla 6 haneli olmalı, yalnızca harf ve rakamlardan oluşmalıdır.');
                    var product = this.state.products.find(p => p.id === id);
                    var originalValue = '';
                    if (vid > 0 && product && product.variations) {
                        var variation = product.variations.find(v => v.id === vid);
                        originalValue = (variation && variation.all_codes && variation.all_codes[String(depoId)]) ? variation.all_codes[String(depoId)] : '';
                    } else if (product) {
                        originalValue = (product.all_codes && product.all_codes[String(depoId)]) ? product.all_codes[String(depoId)] : '';
                    }
                    input.value = originalValue;
                    return;
                }
            }

            input.style.borderColor = 'var(--hk-border)';
            input.disabled = true;

            try {
                var response = await fetch(kasaAyar.rootApiUrl + 'hizli-kasa/v2/product/warehouse-code', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': kasaAyar.nonce
                    },
                    body: JSON.stringify({
                        product_id: id,
                        variation_id: vid,
                        depo_id: depoId,
                        depo_kodu: rawValue
                    })
                });

                var res = await response.json();
                if (res.success) {
                    input.style.borderColor = '#10b981';
                    setTimeout(() => {
                        input.style.borderColor = 'var(--hk-border)';
                    }, 1500);

                    var product = this.state.products.find(p => p.id === id);
                    if (product) {
                        if (vid > 0 && product.variations) {
                            var variation = product.variations.find(v => v.id === vid);
                            if (variation) {
                                if (!variation.all_codes) variation.all_codes = {};
                                variation.all_codes[String(depoId)] = rawValue || null;
                            }
                        } else {
                            if (!product.all_codes) product.all_codes = {};
                            product.all_codes[String(depoId)] = rawValue || null;
                        }
                    }
                } else {
                    alert('Hata: ' + (res.errors ? res.errors.join(', ') : 'Depo kodu güncellenemedi.'));
                    input.style.borderColor = '#ef4444';
                }
            } catch (e) {
                console.error(e);
                alert('İletişim hatası oluştu.');
                input.style.borderColor = '#ef4444';
            } finally {
                input.disabled = false;
            }
        }
    };

})(window.HizliKasa = window.HizliKasa || {});
