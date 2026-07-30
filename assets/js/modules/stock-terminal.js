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
            sortBy: 'date_desc',
            total: 0,
            warehouses: [],
            filterData: {
                categories: [],
                brands: [],
                attributes: [],
                tags: []
            },
            filters: {
                scope: 'all',
                minStock: '',
                maxStock: '',
                stockStatus: 'all',
                category: 0,
                brand: 0,
                attribute: '',
                attributeTerm: 0,
                daysUnsold: 0
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
                                self.loadProducts();
                            }
                        }
                    });
                }
                return;
            }

            this.initialized = true;

            console.group('%c[Hızlı Kasa StockTerminal] Modül Başlatılıyor (init)', 'color: #3b82f6; font-weight: bold;');
            console.log('Arama girdisi ve olay dinleyicileri yüklendi.');
            console.groupEnd();

            setTimeout(() => {
                if (typeof logDiagnostics === 'function') logDiagnostics('init()');
            }, 100);

            // Depo değişince (görüntüleme veya aktif kasa deposu) listeyi yenile
            var handleDepoChange = function() {
                self.state.currentPage = 1;
                self.loadProducts();
            };
            document.addEventListener('hkViewDepoChanged', handleDepoChange);
            document.addEventListener('hkActiveDepoChanged', handleDepoChange);

            // --- Filtre Çekmecesi (Drawer) Toggle & Backdrop ---
            const filterToggleBtn = document.getElementById('btn-terminal-filtre-toggle');
            const drawer = document.getElementById('stock-filter-drawer');
            const backdrop = document.getElementById('drawer-backdrop');
            const closeDrawerBtn = document.getElementById('btn-close-drawer');
            const applyFiltersBtn = document.getElementById('btn-apply-filters');
            const clearFiltersBtn = document.getElementById('btn-clear-filters');
            const clearAllChipsBtn = document.getElementById('btn-clear-all-chips');

            const logDiagnostics = function(label) {
                const liveDrawer = document.getElementById('stock-filter-drawer');
                const liveBackdrop = document.getElementById('drawer-backdrop');
                console.group(`%c[Hızlı Kasa StockTerminal] Diagnostics: ${label}`, 'color: #eab308; font-weight: bold;');
                const getStyles = (el) => {
                    if (!el) return null;
                    const comp = window.getComputedStyle(el);
                    return {
                        display: comp.display,
                        visibility: comp.visibility,
                        opacity: comp.opacity,
                        position: comp.position,
                        zIndex: comp.zIndex,
                        width: comp.width,
                        height: comp.height,
                        top: comp.top,
                        left: comp.left,
                        transform: comp.transform,
                        clip: comp.clip,
                        rect: 'check bounding client rect below'
                    };
                };
                
                const logEl = (id, el) => {
                    if (!el) {
                        console.log(`${id} not found in DOM`);
                        return;
                    }
                    console.log(`--- ${id} ---`);
                    console.table(getStyles(el));
                    console.log(`Rect (${id}):`, el.getBoundingClientRect());
                    console.log(`Offsets (${id}): offsetWidth=${el.offsetWidth}, offsetHeight=${el.offsetHeight}, offsetLeft=${el.offsetLeft}, offsetTop=${el.offsetTop}`);
                };

                const elsToLog = [
                    { id: '#stock-filter-drawer', el: liveDrawer },
                    { id: '#drawer-backdrop', el: liveBackdrop },
                    { id: '.stok-terminali', el: document.querySelector('.stok-terminali') || document.getElementById('stok-terminali') },
                    { id: '.tab-content-urunler', el: document.querySelector('.tab-content-urunler') || document.getElementById('tab-content-urunler') },
                    { id: '#app-view-container', el: document.getElementById('app-view-container') },
                    { id: '#hizli-kasa-app', el: document.getElementById('hizli-kasa-app') }
                ];

                elsToLog.forEach(item => logEl(item.id, item.el));
                console.groupEnd();
            };

            const toggleDrawer = function(show) {
                const liveDrawer = document.getElementById('stock-filter-drawer');
                const liveBackdrop = document.getElementById('drawer-backdrop');
                const liveToggleBtn = document.getElementById('btn-terminal-filtre-toggle');

                if (!liveDrawer) {
                    console.error('[Hızlı Kasa StockTerminal] ❌ #stock-filter-drawer DOM\'da bulunamadı!');
                    return;
                }
                const isVisible = show !== undefined ? show : (liveDrawer.style.display === 'none' || window.getComputedStyle(liveDrawer).display === 'none');
                liveDrawer.style.display = isVisible ? 'flex' : 'none';
                if (liveBackdrop) liveBackdrop.style.display = isVisible ? 'block' : 'none';
                if (liveToggleBtn) liveToggleBtn.classList.toggle('active', isVisible);

                console.log('%c[Hızlı Kasa StockTerminal] Filtre Çekmecesi Durumu:', 'color: #8b5cf6; font-weight: bold;', isVisible ? 'AÇIK' : 'KAPALI');

                setTimeout(() => logDiagnostics(`toggleDrawer(isVisible=${isVisible})`), 50);

                if (isVisible && !self._filtersLoaded) {
                    self.loadFilterOptions();
                }
            };

            // --- Event Delegation for Drawer Buttons & Backdrop ---
            document.addEventListener('click', function(e) {
                const toggleBtn = e.target.closest('#btn-terminal-filtre-toggle');
                if (toggleBtn) {
                    e.preventDefault();
                    toggleDrawer();
                    return;
                }
                const closeBtn = e.target.closest('#btn-close-drawer');
                if (closeBtn) {
                    e.preventDefault();
                    toggleDrawer(false);
                    return;
                }
                const backdropEl = e.target.closest('#drawer-backdrop');
                if (backdropEl && e.target === backdropEl) {
                    e.preventDefault();
                    toggleDrawer(false);
                    return;
                }
                const applyBtn = e.target.closest('#btn-apply-filters');
                if (applyBtn) {
                    e.preventDefault();
                    console.log('%c[Hızlı Kasa StockTerminal] Sonuçları Göster Butonuna Tıklandı', 'color: #059669; font-weight: bold;');
                    handleFilterChange(e);
                    toggleDrawer(false);
                    return;
                }
                const clearBtn = e.target.closest('#btn-clear-filters');
                if (clearBtn) {
                    e.preventDefault();
                    self.resetFiltersUI();
                    handleFilterChange(e);
                    return;
                }
                const clearChipsBtn = e.target.closest('#btn-clear-all-chips');
                if (clearChipsBtn) {
                    e.preventDefault();
                    self.resetFiltersUI();
                    handleFilterChange(e);
                    return;
                }
            });

            const handleFilterChange = function(e) {
                console.log('%c[Hızlı Kasa StockTerminal] Filtre Değişti / Tetiklendi:', 'color: #10b981; font-weight: bold;', e ? e.target.id : 'Manuel');
                self.readFiltersFromUI();
                self.state.currentPage = 1;
                self.loadProducts();
                self.renderActiveChips();
            };

            // --- Tüm Çekmece Filtre Elemanlarına Canlı Dinleyici Bağlama ---
            const filterInputIds = [
                'filter-scope', 'filter-min-stock', 'filter-max-stock', 
                'filter-stock-status', 'filter-category', 'filter-brand', 
                'filter-attribute', 'filter-attribute-term', 'filter-days-unsold'
            ];

            filterInputIds.forEach(id => {
                const el = document.getElementById(id);
                if (el) {
                    const eventType = (el.tagName === 'INPUT' && el.type === 'number') ? 'input' : 'change';
                    el.addEventListener(eventType, function(e) {
                        if (id === 'filter-attribute') {
                            self.updateAttributeTermsUI(this.value);
                        }
                        handleFilterChange(e);
                    });
                }
            });

            // --- Sıralama Seçici ---
            const sortSelect = document.getElementById('terminal-siralama-select');
            if (sortSelect) {
                sortSelect.addEventListener('change', function() {
                    self.state.sortBy = this.value;
                    self.state.currentPage = 1;
                    self.loadProducts();
                });
            }

            // --- Sayfalandırma ---
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

            // Metin Araması Dinleyicisi
            input.addEventListener('input', function() {
                clearTimeout(self.state.searchTimer);
                console.log(`%c[StockTerminal Search] ⏳ Debounce Başlatıldı (300ms) - Girdi: "${this.value}"`, 'color: #8b5cf6; font-style: italic;');
                self.state.searchTimer = setTimeout(function() {
                    self.state.currentPage = 1;
                    self.loadProducts();
                }, 300);
            });

            // Barkod okuyucu desteği (Enter tuşu)
            input.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    clearTimeout(self.state.searchTimer);
                    console.log(`%c[StockTerminal Search] ⚡ Enter / Barkod Okuyucu Tetiklendi - Girdi: "${this.value}"`, 'color: #ec4899; font-weight: bold;');
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
                            console.log('%c[Hızlı Kasa StockTerminal] Varyasyon Kartı Tıklandı:', 'color: #f59e0b; font-weight: bold;', 'Ürün ID:', id, 'Durum:', isHidden ? 'Açıldı' : 'Kapatıldı');
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
         * Arayüzdeki filtre girdilerini okur ve state'e yazar.
         */
        readFiltersFromUI: function() {
            const scope = document.getElementById('filter-scope');
            const minStock = document.getElementById('filter-min-stock');
            const maxStock = document.getElementById('filter-max-stock');
            const stockStatus = document.getElementById('filter-stock-status');
            const category = document.getElementById('filter-category');
            const brand = document.getElementById('filter-brand');
            const attribute = document.getElementById('filter-attribute');
            const attributeTerm = document.getElementById('filter-attribute-term');
            const daysUnsold = document.getElementById('filter-days-unsold');

            this.state.filters.scope         = scope ? scope.value : 'all';
            this.state.filters.minStock      = minStock ? minStock.value : '';
            this.state.filters.maxStock      = maxStock ? maxStock.value : '';
            this.state.filters.stockStatus   = stockStatus ? stockStatus.value : 'all';
            this.state.filters.category      = category ? parseInt(category.value) || 0 : 0;
            this.state.filters.brand         = brand ? parseInt(brand.value) || 0 : 0;
            this.state.filters.attribute     = attribute ? attribute.value : '';
            this.state.filters.attributeTerm = attributeTerm ? parseInt(attributeTerm.value) || 0 : 0;
            this.state.filters.daysUnsold    = daysUnsold ? parseInt(daysUnsold.value) || 0 : 0;

            console.group('%c[Hızlı Kasa StockTerminal] Filtreler Okundu:', 'color: #059669; font-weight: bold;');
            console.log(JSON.parse(JSON.stringify(this.state.filters)));
            console.groupEnd();
        },

        /**
         * Filtre arayüzünü varsayılan değerlere sıfırlar.
         */
        resetFiltersUI: function() {
            const scope = document.getElementById('filter-scope');
            const minStock = document.getElementById('filter-min-stock');
            const maxStock = document.getElementById('filter-max-stock');
            const stockStatus = document.getElementById('filter-stock-status');
            const category = document.getElementById('filter-category');
            const brand = document.getElementById('filter-brand');
            const attribute = document.getElementById('filter-attribute');
            const attributeTerm = document.getElementById('filter-attribute-term');
            const daysUnsold = document.getElementById('filter-days-unsold');

            if (scope) scope.value = 'all';
            if (minStock) minStock.value = '';
            if (maxStock) maxStock.value = '';
            if (stockStatus) stockStatus.value = 'all';
            if (category) category.value = 0;
            if (brand) brand.value = 0;
            if (attribute) attribute.value = '';
            if (attributeTerm) attributeTerm.value = 0;
            if (daysUnsold) daysUnsold.value = 0;

            const termsWrapper = document.getElementById('attribute-terms-wrapper');
            if (termsWrapper) termsWrapper.style.display = 'none';
        },

        /**
         * Seçilen nitelik tipine göre değerlerini açılır kutuya doldurur.
         */
        updateAttributeTermsUI: function(slug) {
            const wrapper = document.getElementById('attribute-terms-wrapper');
            const termSelect = document.getElementById('filter-attribute-term');
            if (!wrapper || !termSelect) return;

            termSelect.innerHTML = '<option value="0">Tümü</option>';

            if (!slug) {
                wrapper.style.display = 'none';
                return;
            }

            const attrObj = (this.state.filterData.attributes || []).find(a => a.slug === slug);
            if (attrObj && attrObj.terms && attrObj.terms.length > 0) {
                attrObj.terms.forEach(t => {
                    const opt = document.createElement('option');
                    opt.value = t.id;
                    opt.textContent = t.name;
                    termSelect.appendChild(opt);
                });
                wrapper.style.display = 'block';
            } else {
                wrapper.style.display = 'none';
            }
        },

        /**
         * Aktif filtre rozetlerini (Chips) ve filtre sayısı göstergesini çizer.
         */
        renderActiveChips: function() {
            const chipsBar = document.getElementById('active-filter-chips');
            const chipsContainer = document.getElementById('chips-container');
            const badge = document.getElementById('active-filter-badge');

            if (!chipsBar || !chipsContainer) return;

            const chips = [];
            const f = this.state.filters;

            if (f.scope && f.scope !== 'all') {
                let scopeText = 'Tümü';
                if (f.scope === 'parent_sum') scopeText = 'Toplamsal Ürün Stoğu';
                else if (f.scope === 'variation') scopeText = 'Tekil Varyasyon Stoğu';
                else if (f.scope === 'simple') scopeText = 'Sadece Basit Ürünler';
                chips.push({ key: 'scope', label: 'Kapsam: ' + scopeText });
            }

            if (f.minStock !== '' || f.maxStock !== '') {
                const min = f.minStock !== '' ? f.minStock : '0';
                const max = f.maxStock !== '' ? f.maxStock : '∞';
                chips.push({ key: 'stockRange', label: `Stok: ${min} - ${max}` });
            }

            if (f.stockStatus && f.stockStatus !== 'all') {
                let statusText = f.stockStatus;
                if (f.stockStatus === 'instock') statusText = 'Stokta Var';
                else if (f.stockStatus === 'lowstock') statusText = 'Kritik Stok (<=2)';
                else if (f.stockStatus === 'outofstock') statusText = 'Stokta Yok';
                chips.push({ key: 'stockStatus', label: 'Stok Durumu: ' + statusText });
            }

            if (f.category > 0) {
                const catObj = (this.state.filterData.categories || []).find(c => c.id === f.category);
                const catName = catObj ? catObj.name : '#' + f.category;
                chips.push({ key: 'category', label: 'Kategori: ' + catName });
            }

            if (f.brand > 0) {
                const brandObj = (this.state.filterData.brands || []).find(b => b.id === f.brand);
                const brandName = brandObj ? brandObj.name : '#' + f.brand;
                chips.push({ key: 'brand', label: 'Marka: ' + brandName });
            }

            if (f.attribute) {
                const attrObj = (this.state.filterData.attributes || []).find(a => a.slug === f.attribute);
                let attrLabel = attrObj ? attrObj.label : f.attribute;
                if (f.attributeTerm > 0 && attrObj) {
                    const termObj = (attrObj.terms || []).find(t => t.id === f.attributeTerm);
                    if (termObj) attrLabel += ': ' + termObj.name;
                }
                chips.push({ key: 'attribute', label: 'Nitelik: ' + attrLabel });
            }

            if (f.daysUnsold > 0) {
                chips.push({ key: 'daysUnsold', label: `Hareketsiz: Son ${f.daysUnsold} Gündür Satılmayan` });
            }

            if (chips.length > 0) {
                console.log('%c[Hızlı Kasa StockTerminal] Aktif Filtre Rozetleri Çizdirildi:', 'color: #6366f1; font-weight: bold;', chips.map(c => c.label));
                chipsBar.style.display = 'flex';
                if (badge) {
                    badge.textContent = chips.length;
                    badge.style.display = 'inline-block';
                }

                const self = this;
                chipsContainer.innerHTML = chips.map(c => `
                    <span class="filter-chip">
                        ${c.label}
                        <span class="remove-chip" data-key="${c.key}">&times;</span>
                    </span>
                `).join('');

                chipsContainer.querySelectorAll('.remove-chip').forEach(el => {
                    el.addEventListener('click', function() {
                        const key = this.dataset.key;
                        self.removeSingleFilter(key);
                    });
                });
            } else {
                chipsBar.style.display = 'none';
                if (badge) badge.style.display = 'none';
                chipsContainer.innerHTML = '';
            }
        },

        /**
         * Tek bir filtre rozetini kaldırır.
         */
        removeSingleFilter: function(key) {
            if (key === 'scope') {
                const el = document.getElementById('filter-scope');
                if (el) el.value = 'all';
            } else if (key === 'stockRange') {
                const min = document.getElementById('filter-min-stock');
                const max = document.getElementById('filter-max-stock');
                if (min) min.value = '';
                if (max) max.value = '';
            } else if (key === 'stockStatus') {
                const el = document.getElementById('filter-stock-status');
                if (el) el.value = 'all';
            } else if (key === 'category') {
                const el = document.getElementById('filter-category');
                if (el) el.value = 0;
            } else if (key === 'brand') {
                const el = document.getElementById('filter-brand');
                if (el) el.value = 0;
            } else if (key === 'attribute') {
                const el = document.getElementById('filter-attribute');
                const elTerm = document.getElementById('filter-attribute-term');
                if (el) el.value = '';
                if (elTerm) elTerm.value = 0;
                this.updateAttributeTermsUI('');
            } else if (key === 'daysUnsold') {
                const el = document.getElementById('filter-days-unsold');
                if (el) el.value = 0;
            }

            this.readFiltersFromUI();
            this.state.currentPage = 1;
            this.loadProducts();
            this.renderActiveChips();
        },

        /**
         * Filtre seçeneklerini V2 REST API'den yükler.
         */
        loadFilterOptions: async function() {
            if (this._filtersLoading) return;
            this._filtersLoading = true;

            try {
                var optsUrl = kasaAyar.rootApiUrl + 'hizli-kasa/v2/products/filter-options';
                console.log('%c[Hızlı Kasa StockTerminal] Filtre Seçenekleri API İsteği Atılıyor:', 'color: #0284c7; font-weight: bold;', optsUrl);
                const response = await fetch(optsUrl, {
                    headers: { 'X-WP-Nonce': kasaAyar.nonce }
                });
                const res = await response.json();

                if (res.success && res.data) {
                    const data = res.data;
                    this.state.filterData = data;

                    console.group('%c[Hızlı Kasa StockTerminal] Filtre Seçenekleri Yüklendi:', 'color: #0284c7; font-weight: bold;');
                    console.log('Seçenekler:', {
                        categories: data.categories ? data.categories.length : 0,
                        brands: data.brands ? data.brands.length : 0,
                        attributes: data.attributes ? data.attributes.length : 0
                    });
                    console.groupEnd();

                    const filterCat   = document.getElementById('filter-category');
                    const filterBrand = document.getElementById('filter-brand');
                    const filterAttr  = document.getElementById('filter-attribute');

                    if (filterCat && data.categories) {
                        filterCat.innerHTML = '<option value="0">Tüm Kategoriler</option>';
                        data.categories.forEach(cat => {
                            const opt = document.createElement('option');
                            opt.value = cat.id;
                            opt.textContent = cat.name + (cat.count ? ` (${cat.count})` : '');
                            filterCat.appendChild(opt);
                        });
                    }

                    if (filterBrand && data.brands) {
                        filterBrand.innerHTML = '<option value="0">Tüm Markalar</option>';
                        data.brands.forEach(brand => {
                            const opt = document.createElement('option');
                            opt.value = brand.id;
                            opt.textContent = brand.name + (brand.count ? ` (${brand.count})` : '');
                            filterBrand.appendChild(opt);
                        });
                    }

                    if (filterAttr && data.attributes) {
                        filterAttr.innerHTML = '<option value="">Seçiniz...</option>';
                        data.attributes.forEach(attr => {
                            const opt = document.createElement('option');
                            opt.value = attr.slug;
                            opt.textContent = attr.label;
                            filterAttr.appendChild(opt);
                        });
                    }
                }

                this._filtersLoaded = true;
            } catch (e) {
                console.error("V2 Filtreler yüklenemedi", e);
            } finally {
                this._filtersLoading = false;
            }
        },

        /**
         * Ürünleri V2 REST API'den yükler.
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

            var requestStartTime = performance.now();

            if (this.state.requestController) {
                console.group('%c[StockTerminal Search] ⚠️ Önceki İstek İptal Edildi (AbortController)', 'color: #f59e0b; font-weight: bold;');
                console.log('İptal edilen Token ID:', this.state.lastRequestToken);
                console.groupEnd();
                this.state.requestController.abort();
            }

            var controller = typeof AbortController !== 'undefined' ? new AbortController() : null;
            var requestToken = ++this.state.lastRequestToken;
            this.state.requestController = controller;

            this.state.products = [];
            container.innerHTML = '<div class="terminal-loading"><div class="spin"></div><p>Ürünler yükleniyor...</p></div>';
            
            this.state.isLoading = true;

            try {
                if (window.HizliKasa && HizliKasa.DepoManager && HizliKasa.DepoManager.state) {
                    this.state.warehouses = HizliKasa.DepoManager.state.view || HizliKasa.DepoManager.state.manage || [];
                }

                var f = this.state.filters;
                var queryParams = [
                    'page=' + this.state.currentPage,
                    'per_page=' + this.state.perPage,
                    'scope=' + encodeURIComponent(f.scope || 'all'),
                    'stock_status=' + encodeURIComponent(f.stockStatus || 'all'),
                    'sort_by=' + encodeURIComponent(this.state.sortBy || 'date_desc'),
                    '_=' + Date.now()
                ];

                if (depoId) queryParams.push('depo_id=' + encodeURIComponent(depoId));
                if (s) queryParams.push('search=' + encodeURIComponent(s));
                if (f.minStock !== '') queryParams.push('min_stock=' + encodeURIComponent(f.minStock));
                if (f.maxStock !== '') queryParams.push('max_stock=' + encodeURIComponent(f.maxStock));
                if (f.category > 0) queryParams.push('category_ids=' + f.category);
                if (f.brand > 0) queryParams.push('brand_ids=' + f.brand);
                if (f.attribute) queryParams.push('attribute_slug=' + encodeURIComponent(f.attribute));
                if (f.attributeTerm > 0) queryParams.push('attribute_term_ids=' + f.attributeTerm);
                if (f.daysUnsold > 0) queryParams.push('days_since_last_sale=' + f.daysUnsold);

                var url = kasaAyar.rootApiUrl + 'hizli-kasa/v2/products/stock-search?' + queryParams.join('&');

                console.group(`%c[StockTerminal Search] 🚀 API İsteği Gönderiliyor (Token #${requestToken})`, 'color: #3b82f6; font-weight: bold;');
                console.log('Arama Sorgusu (Search):', s ? `"${s}"` : '(Boş - Tüm Ürünler)');
                console.log('Request Token:', requestToken);
                console.log('Depo ID:', depoId);
                console.log('Sayfa:', this.state.currentPage, '| Per Page:', this.state.perPage);
                console.log('URL:', url);
                console.groupEnd();

                var fetchOptions = { headers: { 'X-WP-Nonce': kasaAyar.nonce } };
                if (controller) {
                    fetchOptions.signal = controller.signal;
                }

                var response = await fetch(url, fetchOptions);
                if (!response.ok) throw new Error("Sunucu hatası: " + response.status);
                
                var res = await response.json();
                var requestDurationMs = Math.round(performance.now() - requestStartTime);

                if (requestToken !== this.state.lastRequestToken) {
                    console.group(`%c[StockTerminal Search] 🛑 Yanıt Yok Sayıldı (Token Mismatch #${requestToken})`, 'color: #ef4444; font-weight: bold;');
                    console.log(`Tamamlanan Token: #${requestToken}, Güncel Son Token: #${this.state.lastRequestToken}`);
                    console.groupEnd();
                    return;
                }

                if (res.success && res.data) {
                    var data = res.data;
                    var meta = data.meta || {};
                    var isExact = !!meta.exact_sku_match;
                    var strategy = meta.search_strategy || (s ? 'LIKE_SEARCH' : 'NO_SEARCH');

                    this.state.products = data.items || [];
                    this.state.total = (data.pagination && data.pagination.total_items) ? data.pagination.total_items : this.state.products.length;

                    console.group(`%c[StockTerminal Search] ✅ API Yanıtı Alındı (Token #${requestToken} - ${requestDurationMs}ms)`, 'color: #10b981; font-weight: bold;');
                    console.log('Arama Sorgusu:', s ? `"${s}"` : '(Tüm Ürünler)');
                    console.log('Arama Stratejisi (Strategy):', strategy);
                    console.log('SKU Eşleşme Tipi:', isExact ? '%c[EXACT SKU MATCH]' : '%c[LIKE / PARTIAL MATCH]', isExact ? 'color: #10b981; font-weight: bold;' : 'color: #d97706; font-weight: bold;');
                    if (meta.exact_sku_product_id) {
                        console.log('Eşleşen Ürün ID:', meta.exact_sku_product_id);
                    }
                    console.log('Ağ Süresi (Network Timing):', requestDurationMs + ' ms');
                    console.log('Sonuç Sayısı (Total):', this.state.total);
                    console.log('Gelen Kalem Sayısı:', this.state.products.length);
                    console.log('Ürün Listesi:', this.state.products);
                    console.groupEnd();

                    this.updatePaginationUI();
                    
                    if (document.getElementById('toplam-kalem-sayisi')) {
                        document.getElementById('toplam-kalem-sayisi').innerText = this.state.total;
                    }

                    const applyBtnText = document.getElementById('btn-apply-text');
                    if (applyBtnText) {
                        applyBtnText.innerText = `${this.state.total} Ürünü Göster`;
                    }

                    this.renderProducts();
                } else {
                    throw new Error("Veri formatı geçersiz");
                }
            } catch (e) {
                if (e && e.name === 'AbortError') {
                    console.group(`%c[StockTerminal Search] ℹ️ Network İsteği Abort Edildi (Token #${requestToken})`, 'color: #6b7280; font-style: italic;');
                    console.log('Hızlı yazma / yeni istek sebebiyle tarayıcı düzeyinde iptal edildi.');
                    console.groupEnd();
                    return;
                }
                console.error("[StockTerminal Search] ❌ Yükleme hatası", e);
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

            var mainCount = 0;
            var variationCount = 0;
            for (var c = 0; c < this.state.products.length; c++) {
                var item = this.state.products[c];
                if (item.type === 'variation') {
                    variationCount++;
                } else {
                    mainCount++;
                    if (item.variations && item.variations.length > 0) {
                        variationCount += item.variations.length;
                    }
                }
            }

            console.group('%c[Hızlı Kasa StockTerminal] Ürün Listesi Çizdirildi:', 'color: #ec4899; font-weight: bold;');
            console.log(mainCount + ' adet ana ürün, ' + variationCount + ' adet varyasyon');
            console.log('Ürün Verisi (state.products):', this.state.products);
            console.groupEnd();

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

                var isVariable = (p.is_variable || p.type === 'variable') && p.variations && p.variations.length > 0;
                var pName = p.name || p.title || ('Ürün #' + p.id);
                
                // Stok kaynağı: all_stocks[depoId] tercih, yoksa warehouse_stock / stock_quantity fallback
                var pDepoStock = (p.all_stocks && depoId && p.all_stocks[String(depoId)] != null) 
                    ? parseFloat(p.all_stocks[String(depoId)]) 
                    : parseFloat(p.warehouse_stock !== undefined ? p.warehouse_stock : (p.stock_quantity || 0));

                // Stok durumunu belirle (Grup toplamı)
                var totalGroupStock = isVariable 
                    ? p.variations.reduce(function(sum, v) {
                        var vStock = (v.all_stocks && depoId && v.all_stocks[String(depoId)] != null) 
                            ? parseFloat(v.all_stocks[String(depoId)]) 
                            : parseFloat(v.warehouse_stock !== undefined ? v.warehouse_stock : (v.stock_quantity || 0));
                        return sum + vStock;
                    }, 0)
                    : pDepoStock;
                
                var isStockOut = totalGroupStock <= 0;
                var isCritical = !isVariable && pDepoStock > 0 && pDepoStock <= threshold;
                var img = (p.images && p.images[0] && p.images[0].src) ? p.images[0].src : (p.image_url || '');

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
                                <div class="urun-ad">${pName} ${isVariable ? '<span class="var-badge">VARYASYONLU</span>' : ''}</div>
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
                        var vDepoStock = (v.all_stocks && depoId && v.all_stocks[String(depoId)] != null) 
                            ? parseFloat(v.all_stocks[String(depoId)]) 
                            : parseFloat(v.warehouse_stock !== undefined ? v.warehouse_stock : (v.stock_quantity || 0));
                        var vCritical = vDepoStock > 0 && vDepoStock <= threshold;
                        var vImg = (v.images && v.images[0] && v.images[0].src) ? v.images[0].src : (v.image_url || img);
                        var vName = v.name || v.title || ('Varyasyon #' + v.id);
                        
                        // Varyasyon Fiyatları
                        var vCurrentPrice = parseFloat(v.price || 0);
                        var vRegularPrice = parseFloat(v.regular_price || 0);
                        var vHasSale = vRegularPrice > 0 && vRegularPrice > vCurrentPrice;
                        var vCashPrice = vCurrentPrice * 0.95;

                        html += `
                            <div class="terminal-urun-kart variation-item" data-id="${v.parent_id}" data-vid="${v.id}">
                                <div class="variation-indent"></div>
                                <img src="${vImg}" class="variation-img" alt="">
                                <div class="urun-detay">
                                    <div class="urun-temel-bilgi">
                                        <div class="urun-ad">${vName}</div>
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

            if (isNaN(change) || change === 0) return HK.UI.alert("Lütfen geçerli bir miktar girin.");

            // Yönetim yetkisi kontrolü
            var activeDepo = (window.HizliKasa && HizliKasa.DepoManager)
                ? HizliKasa.DepoManager.getActiveDepo()
                : null;
            
            if (!activeDepo || !HizliKasa.DepoManager.canManageDepo(activeDepo)) {
                return HK.UI.alert('Bu depoda stok değiştirme yetkiniz yok.');
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
                    HK.UI.alert("Hata: " + (res.message || res.data?.message || 'Bilinmeyen hata'));
                }
            } catch (e) {
                console.error(e);
                HK.UI.alert("Bir hata oluştu.");
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
                HK.UI.alert('Bu depoda depo kodu değiştirme yetkiniz yok.');
                return;
            }

            var rawValue = input.value.trim().toUpperCase();
            
            if (rawValue !== '') {
                if (rawValue.length > 6 || !/^[A-Z0-9]+$/.test(rawValue)) {
                    HK.UI.alert('Depo kodu en fazla 6 haneli olmalı, yalnızca harf ve rakamlardan oluşmalıdır.');
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
                    HK.UI.alert('Hata: ' + (res.errors ? res.errors.join(', ') : 'Depo kodu güncellenemedi.'));
                    input.style.borderColor = '#ef4444';
                }
            } catch (e) {
                console.error(e);
                HK.UI.alert('İletişim hatası oluştu.');
                input.style.borderColor = '#ef4444';
            } finally {
                input.disabled = false;
            }
        }
    };

})(window.HizliKasa = window.HizliKasa || {});
