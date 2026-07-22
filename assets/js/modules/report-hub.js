/**
 * Hızlı Kasa - Raporlar Hub Yönetim Modülü
 */
(function(HK) {
    'use strict';

    // Debounce helper to limit search requests
    function debounce(func, wait) {
        var timeout;
        return function() {
            var context = this, args = arguments;
            clearTimeout(timeout);
            timeout = setTimeout(function() {
                func.apply(context, args);
            }, wait);
        };
    }

    HK.ReportHub = {
        categories: {},
        reports: {},
        activeCategory: null,
        activeReport: null,
        history: [],
        favorites: [],

        init: function() {
            var self = this;
            console.log("HK.ReportHub: Init started");

            document.addEventListener('hkTabLoaded', function(e) {
                console.log("HK.ReportHub: hkTabLoaded triggered", e.detail.tab);
                if (e.detail.tab === 'raporlar') {
                    self.setupHub();
                }
            });

            // Sayfa yenilendiğinde veya sekme zaten DOM'da hazırsa direkt başlat (event kaçırılmasını engeller)
            if (document.getElementById('rapor-hub-root')) {
                console.log("HK.ReportHub: #rapor-hub-root found in DOM on init, running setup immediately");
                setTimeout(function() {
                    self.setupHub();
                }, 50);
            }

            // ESC ile geri dönme
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && HK.State && HK.State.aktifSekme === 'raporlar') {
                    self.geriGit();
                }
            });
        },

        registerCategory: function(cat) {
            this.categories[cat.id] = {
                id: cat.id,
                title: cat.title,
                icon: cat.icon || '📊',
                color: cat.color || '#3b82f6',
                description: cat.description || '',
                order: cat.order || 99,
                badge: cat.badge || ''
            };
            
            // Eğer hub çizilmişse kategorileri güncelle
            var grid = document.getElementById('rhub-categories-grid');
            if (grid) {
                this.renderCategories();
            }
        },

        registerReport: function(rep) {
            this.reports[rep.id] = {
                id: rep.id,
                categoryId: rep.categoryId,
                title: rep.title,
                icon: rep.icon || '📊',
                panelId: rep.panelId,
                onActivate: rep.onActivate || function() {},
                hasDateFilter: rep.hasDateFilter !== false,
                hasSearch: rep.hasSearch || false,
                searchPlaceholder: rep.searchPlaceholder || 'Ara...',
                description: rep.description || '',
                order: rep.order || 99
            };

            // Eğer aktif kategoride bir rapor eklendiyse sidebar'ı yenile
            if (this.activeCategory === rep.categoryId) {
                this.renderSidebarMenu();
            }
            // Kategorilerdeki rapor sayısını güncellemek için grid'i de yenile
            var grid = document.getElementById('rhub-categories-grid');
            if (grid) {
                this.renderCategories();
            }
        },

        getReportsByCategory: function(catId) {
            return Object.values(this.reports)
                .filter(function(r) { return r.categoryId === catId; })
                .sort(function(a, b) { return (a.order || 99) - (b.order || 99); });
        },

        setupHub: function() {
            var self = this;
            console.log("HK.ReportHub: setupHub started");
            var container = document.getElementById('rapor-hub-root');
            if (!container) {
                console.error("HK.ReportHub: #rapor-hub-root not found in DOM!");
                return;
            }

            // Eğer hub zaten kurulmuşsa sadece ana sayfaya dön
            if (container.dataset.initialized === 'true') {
                console.log("HK.ReportHub: already initialized, showing home page");
                this.anaSayfayaDon(true);
                return;
            }

            container.dataset.initialized = 'true';

            // HTML yapısını kur (İki sütunlu Dashboard yerleşimi - Yardımcı sütun solda)
            container.innerHTML = `
                <div class="rhub-wrapper">
                    <!-- Üst Başlık ve Yardımcı Sütun Layoutu -->
                    <div id="rhub-anasayfa-view">
                        <div class="rhub-home-layout">
                            <!-- Sol Sütun: Hızlı Erişim ve Geçmiş -->
                            <div class="rhub-home-sidebar">
                                <div class="rhub-panel-widget">
                                    <h4>⭐ Hızlı Erişim</h4>
                                    <div class="rhub-widget-list" id="rhub-favorites-list"></div>
                                </div>

                                <div class="rhub-panel-widget">
                                    <h4>🕒 Son Kullanılanlar</h4>
                                    <div class="rhub-widget-list" id="rhub-recents-list"></div>
                                </div>
                            </div>

                            <!-- Sağ Sütun: Kategoriler Izgarası -->
                            <div class="rhub-home-main">
                                <div class="rhub-grid" id="rhub-categories-grid"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Kategori Rapor Görünümü -->
                    <div id="rhub-kategori-view" style="display: none;">
                        <div class="rhub-nav-header">
                            <button class="rhub-back-btn" id="rhub-geri-btn">⬅️ Geri</button>
                            <div class="rhub-breadcrumb" id="rhub-breadcrumb-container"></div>
                        </div>

                        <div class="rhub-workspace">
                            <!-- Sol Menü (Sidebar) -->
                            <div class="rhub-sidebar" id="rhub-sidebar-menu"></div>
                            
                            <!-- Sağ İçerik Alanı -->
                            <div class="rhub-content-panel">
                                <!-- Kategori Seçimi Yapıldığında Rapor Filtreleri Buraya Gelir -->
                                <div class="rhub-filtre-alani" id="rhub-filtre-container" style="display:none;">
                                    <div class="rhub-tarih-filtre" id="rhub-tarih-container">
                                        <input type="date" id="rhub-tarih-bas" class="hk-input">
                                        <input type="date" id="rhub-tarih-bit" class="hk-input">
                                        <button id="rhub-sorgula" class="hk-btn-primary">Sorgula</button>
                                    </div>
                                    <div class="rhub-arama-filtre" id="rhub-arama-container">
                                        <input type="text" id="rhub-arama-input" class="hk-input" placeholder="Ara...">
                                    </div>
                                </div>
                                <div class="rhub-active-report-container" id="rhub-report-target"></div>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            // Olayları Bağla
            document.getElementById('rhub-geri-btn').addEventListener('click', function() {
                self.geriGit();
            });

            document.getElementById('rhub-sorgula').addEventListener('click', function() {
                if (self.activeReport) {
                    var rep = self.reports[self.activeReport];
                    if (rep && typeof rep.onActivate === 'function') {
                        rep.onActivate();
                    }
                }
            });

            var searchInput = document.getElementById('rhub-arama-input');
            if (searchInput) {
                searchInput.addEventListener('keyup', debounce(function() {
                    if (self.activeReport) {
                        var rep = self.reports[self.activeReport];
                        if (rep && typeof rep.onActivate === 'function') {
                            rep.onActivate();
                        }
                    }
                }, 500));
            }

            // Tarih seçicilerine varsayılan bugünü ata
            var bugun = new Date().toISOString().split('T')[0];
            document.getElementById('rhub-tarih-bas').value = bugun;
            document.getElementById('rhub-tarih-bit').value = bugun;

            // Favorileri sunucudan/tarayıcıdan yükle, geçmişi yükle ve kategorileri çiz
            this.loadFavorites();
            this.renderRecents();
            this.renderCategories();
        },

        loadFavorites: function() {
            var self = this;
            var local = localStorage.getItem('hk_favori_raporlar');
            if (local) {
                try {
                    self.favorites = JSON.parse(local);
                    self.renderFavorites();
                } catch(e) {
                    self.fetchFavoritesFromServer();
                }
            } else {
                self.fetchFavoritesFromServer();
            }
        },

        fetchFavoritesFromServer: function() {
            var self = this;
            if (typeof wp === 'undefined' || !wp.apiFetch) {
                self.favorites = ['tum-siparisler', 'ozet-istatistik', 'gun-sonu-arsivi'];
                localStorage.setItem('hk_favori_raporlar', JSON.stringify(self.favorites));
                self.renderFavorites();
                return;
            }

            wp.apiFetch({ path: '/hizli-kasa/v2/user/favorite-reports' })
                .then(function(response) {
                    if (response && response.success) {
                        self.favorites = response.data || [];
                    } else {
                        self.favorites = ['tum-siparisler', 'ozet-istatistik', 'gun-sonu-arsivi'];
                    }
                    localStorage.setItem('hk_favori_raporlar', JSON.stringify(self.favorites));
                    self.renderFavorites();
                    self.renderCategories();
                })
                .catch(function(err) {
                    console.error("Error fetching favorites:", err);
                    self.favorites = ['tum-siparisler', 'ozet-istatistik', 'gun-sonu-arsivi'];
                    localStorage.setItem('hk_favori_raporlar', JSON.stringify(self.favorites));
                    self.renderFavorites();
                });
        },

        saveFavoritesToServer: function() {
            var self = this;
            localStorage.setItem('hk_favori_raporlar', JSON.stringify(self.favorites));
            
            if (typeof wp !== 'undefined' && wp.apiFetch) {
                wp.apiFetch({
                    path: '/hizli-kasa/v2/user/favorite-reports',
                    method: 'POST',
                    data: { favorites: self.favorites }
                }).then(function(res) {
                    console.log("Favorites saved to server:", res);
                }).catch(function(err) {
                    console.error("Error saving favorites to server:", err);
                });
            }
        },

        toggleFavorite: function(repId) {
            var self = this;
            var idx = self.favorites.indexOf(repId);
            if (idx > -1) {
                self.favorites.splice(idx, 1);
            } else {
                self.favorites.push(repId);
            }
            self.saveFavoritesToServer();
            
            // Tüm favori ikonlarını ve listelerini güncelle
            self.renderFavorites();
            self.renderCategories();
            if (self.activeCategory) {
                self.renderSidebarMenu();
            }
        },

        renderFavorites: function() {
            var self = this;
            var container = document.getElementById('rhub-favorites-list');
            if (!container) return;

            if (self.favorites.length === 0) {
                container.innerHTML = `<div class="rhub-empty-widget">Favori rapor bulunmuyor.</div>`;
                return;
            }

            var html = self.favorites.map(repId => {
                var rep = self.reports[repId];
                if (!rep) return '';
                return `
                    <button class="rhub-widget-item" data-rep-id="${rep.id}" data-cat-id="${rep.categoryId}">
                        <span>${rep.icon} ${rep.title}</span>
                        <span class="rhub-widget-item-meta">Aç</span>
                    </button>
                `;
            }).join('');

            container.innerHTML = html;

            container.querySelectorAll('.rhub-widget-item').forEach(btn => {
                btn.addEventListener('click', function() {
                    var catId = this.dataset.catId;
                    var repId = this.dataset.repId;
                    self.kategoriAc(catId, repId);
                });
            });
        },

        addToRecents: function(repId) {
            var self = this;
            var recents = [];
            var local = localStorage.getItem('hk_son_raporlar');
            if (local) {
                try {
                    recents = JSON.parse(local);
                } catch(e) {}
            }

            // Eğer zaten varsa sil (listenin en üstüne taşımak için)
            var idx = recents.indexOf(repId);
            if (idx > -1) {
                recents.splice(idx, 1);
            }

            // Başa ekle
            recents.unshift(repId);

            // Maksimum 4 kayıt limitine tabi tut
            if (recents.length > 4) {
                recents = recents.slice(0, 4);
            }

            localStorage.setItem('hk_son_raporlar', JSON.stringify(recents));
            self.renderRecents();
        },

        renderRecents: function() {
            var self = this;
            var container = document.getElementById('rhub-recents-list');
            if (!container) return;

            var recents = [];
            var local = localStorage.getItem('hk_son_raporlar');
            if (local) {
                try {
                    recents = JSON.parse(local);
                } catch(e) {}
            }

            if (recents.length === 0) {
                container.innerHTML = `<div class="rhub-empty-widget">Henüz geçmiş yok.</div>`;
                return;
            }

            var html = recents.map(repId => {
                var rep = self.reports[repId];
                if (!rep) return '';
                return `
                    <button class="rhub-widget-item" data-rep-id="${rep.id}" data-cat-id="${rep.categoryId}">
                        <span>${rep.icon} ${rep.title}</span>
                        <span class="rhub-widget-item-meta">Görüntüle</span>
                    </button>
                `;
            }).join('');

            container.innerHTML = html;

            container.querySelectorAll('.rhub-widget-item').forEach(btn => {
                btn.addEventListener('click', function() {
                    var catId = this.dataset.catId;
                    var repId = this.dataset.repId;
                    self.kategoriAc(catId, repId);
                });
            });
        },

        renderCategories: function() {
            var self = this;
            console.log("HK.ReportHub: renderCategories started");
            var grid = document.getElementById('rhub-categories-grid');
            if (!grid) {
                console.error("HK.ReportHub: #rhub-categories-grid not found in DOM!");
                return;
            }

            var cats = Object.values(this.categories).sort((a, b) => a.order - b.order);
            console.log("HK.ReportHub: Available categories for rendering:", cats);
            
            grid.innerHTML = cats.map((cat, index) => {
                var isComingSoon = cat.badge === 'yakinda';
                var badgeHtml = '';
                if (cat.badge === 'yakinda') {
                    badgeHtml = `<span class="rhub-badge-yk">Yakında</span>`;
                } else if (cat.badge === 'yeni') {
                    badgeHtml = `<span class="rhub-badge-yeni">Yeni</span>`;
                } else if (cat.badge === 'beta') {
                    badgeHtml = `<span class="rhub-badge-beta">Beta</span>`;
                }

                var catReports = self.getReportsByCategory(cat.id);
                var subtextHtml = isComingSoon ? 'Çok Yakında Hizmetinizde' : `${catReports.length} Rapor Aktif`;

                var reportsListHtml = '';
                if (!isComingSoon && catReports.length > 0) {
                    reportsListHtml = `
                        <div class="rhub-card-reports">
                            ${catReports.map(rep => {
                                var isFav = self.favorites.indexOf(rep.id) > -1;
                                return `
                                    <div class="rhub-card-report-item">
                                        <div class="rhub-report-item-click" data-rep-id="${rep.id}" data-cat-id="${cat.id}">
                                            <span>${rep.icon}</span>
                                            <span>${rep.title}</span>
                                        </div>
                                        <button class="rhub-favorite-toggle ${isFav ? 'is-fav' : ''}" data-rep-id="${rep.id}">
                                            ${isFav ? '★' : '☆'}
                                        </button>
                                    </div>
                                `;
                            }).join('')}
                        </div>
                    `;
                }

                // Staggered animation delay using inline CSS variables
                var animationStyle = `style="--cat-color: ${cat.color}; animation-delay: ${index * 60}ms;"`;

                return `
                    <div class="rhub-card ${isComingSoon ? 'coming-soon' : 'animate-in'}" data-id="${cat.id}" ${animationStyle}>
                        <div class="rhub-card-main">
                            <div class="rhub-card-icon">${cat.icon}</div>
                            <div class="rhub-card-body">
                                <h3>${cat.title} ${badgeHtml}</h3>
                                <p>${cat.description}</p>
                                <span class="rhub-card-meta">${subtextHtml}</span>
                            </div>
                        </div>
                        ${reportsListHtml}
                    </div>
                `;
            }).join('');

            // Kartın geneline tıklama (alt raporlar hariç)
            grid.querySelectorAll('.rhub-card:not(.coming-soon)').forEach(card => {
                card.addEventListener('click', function(e) {
                    // Eğer alt butona veya yıldıza tıklanmadıysa kategoriyi aç
                    if (!e.target.closest('.rhub-card-report-item') && !e.target.closest('.rhub-favorite-toggle')) {
                        var catId = this.dataset.id;
                        self.kategoriAc(catId);
                    }
                });
            });

            // Alt rapor öğelerine tıklama (gitme) olayı
            grid.querySelectorAll('.rhub-report-item-click').forEach(link => {
                link.addEventListener('click', function(e) {
                    e.stopPropagation();
                    var catId = this.dataset.catId;
                    var repId = this.dataset.repId;
                    self.kategoriAc(catId, repId); // Doğrudan o rapora odaklan
                });
            });

            // Yıldız butonlarına tıklama (favorileme) olayı
            grid.querySelectorAll('.rhub-favorite-toggle').forEach(btn => {
                btn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    var repId = this.dataset.repId;
                    self.toggleFavorite(repId);
                });
            });
        },

        kategoriAc: function(catId, repId) {
            var self = this;
            var cat = this.categories[catId];
            if (!cat) return;

            this.activeCategory = catId;
            if (this.history.length === 0) {
                this.history.push({ view: 'hub' });
            }

            var hubView = document.getElementById('rhub-anasayfa-view');
            var catView = document.getElementById('rhub-kategori-view');

            // Hızlı ve şık animasyon (slide & fade)
            hubView.style.transform = 'translateY(-10px)';
            hubView.style.opacity = '0';
            setTimeout(function() {
                hubView.style.display = 'none';
                catView.style.display = 'block';
                catView.style.transform = 'translateY(10px)';
                catView.style.opacity = '0';
                
                setTimeout(function() {
                    catView.style.transform = 'translateY(0)';
                    catView.style.opacity = '1';
                }, 50);

                self.renderSidebarMenu();
                
                // Belirtilen raporu veya kategorideki ilk raporu aç
                var activeRepId = repId;
                if (!activeRepId) {
                    var catReports = self.getReportsByCategory(catId);
                    if (catReports.length > 0) {
                        activeRepId = catReports[0].id;
                    }
                }
                if (activeRepId) {
                    self.raporAc(activeRepId);
                }
            }, 150);
        },

        renderSidebarMenu: function() {
            var self = this;
            var sidebar = document.getElementById('rhub-sidebar-menu');
            if (!sidebar) return;

            var cat = this.categories[this.activeCategory];
            var catReports = this.getReportsByCategory(this.activeCategory);

            var headerHtml = cat ? `<div class="rhub-sidebar-header">${cat.title}</div>` : '';

            sidebar.innerHTML = headerHtml + catReports.map(rep => {
                var isFav = self.favorites.indexOf(rep.id) > -1;
                return `
                    <div class="rhub-sidebar-item">
                        <button class="rhub-side-btn" id="rhub-btn-${rep.id}" data-id="${rep.id}">
                            <span class="rhub-side-icon">${rep.icon}</span>
                            <span class="rhub-side-title">${rep.title}</span>
                        </button>
                        <button class="rhub-favorite-toggle sidebar-fav ${isFav ? 'is-fav' : ''}" data-rep-id="${rep.id}">
                            ${isFav ? '★' : '☆'}
                        </button>
                    </div>
                `;
            }).join('');

            sidebar.querySelectorAll('.rhub-side-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    self.raporAc(this.dataset.id);
                });
            });

            sidebar.querySelectorAll('.rhub-favorite-toggle').forEach(btn => {
                btn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    var repId = this.dataset.repId;
                    self.toggleFavorite(repId);
                });
            });
        },

        raporAc: function(repId) {
            var self = this;
            var rep = this.reports[repId];
            if (!rep) return;

            this.activeReport = repId;

            // Sidebar buton durumunu güncelle
            var sidebar = document.getElementById('rhub-sidebar-menu');
            if (sidebar) {
                sidebar.querySelectorAll('.rhub-side-btn').forEach(btn => btn.classList.remove('aktif'));
                var activeBtn = document.getElementById(`rhub-btn-${repId}`);
                if (activeBtn) activeBtn.classList.add('aktif');
            }

            // Breadcrumb güncelle
            var cat = this.categories[this.activeCategory];
            var breadcrumb = document.getElementById('rhub-breadcrumb-container');
            if (breadcrumb) {
                breadcrumb.innerHTML = `
                    <span class="rhub-crumb-link" id="rhub-crumb-home">Raporlar</span>
                    <span class="rhub-crumb-separator">›</span>
                    <span class="rhub-crumb-cat">${cat.title}</span>
                    <span class="rhub-crumb-separator">›</span>
                    <span class="rhub-crumb-report">${rep.title}</span>
                `;

                document.getElementById('rhub-crumb-home').addEventListener('click', () => this.anaSayfayaDon());
            }

            // Filtreleri ayarla
            var filtreContainer = document.getElementById('rhub-filtre-container');
            var tarihContainer = document.getElementById('rhub-tarih-container');
            var aramaContainer = document.getElementById('rhub-arama-container');
            var aramaInput = document.getElementById('rhub-arama-input');

            if (filtreContainer) {
                if (rep.hasDateFilter || rep.hasSearch) {
                    filtreContainer.style.display = 'flex';
                    tarihContainer.style.display = rep.hasDateFilter ? 'flex' : 'none';
                    aramaContainer.style.display = rep.hasSearch ? 'block' : 'none';
                    if (rep.hasSearch && aramaInput) {
                        aramaInput.placeholder = rep.searchPlaceholder;
                        aramaInput.value = ''; // Arama kutusunu temizle
                    }
                } else {
                    filtreContainer.style.display = 'none';
                }
            }

            // Rapor panelini sağ pencereye al/göster
            var targetContainer = document.getElementById('rhub-report-target');
            var originalPanel = document.getElementById(rep.panelId);

            if (targetContainer && originalPanel) {
                // Mevcut tüm panel div'lerini gizle ve tab-raporlar içine geri koy
                var currentChild = targetContainer.firstElementChild;
                if (currentChild) {
                    currentChild.style.display = 'none';
                    currentChild.classList.remove('aktif');
                    document.querySelector('.rhub-content-wrapper').appendChild(currentChild);
                }

                // Seçilen paneli target alanına taşı ve göster
                originalPanel.style.display = 'block';
                originalPanel.classList.add('aktif');
                targetContainer.appendChild(originalPanel);

                // Son kullanılanlar geçmişine ekle
                self.addToRecents(repId);

                // Rapor verisini yükle
                if (typeof rep.onActivate === 'function') {
                    rep.onActivate();
                }
            }
        },

        geriGit: function() {
            if (this.history.length > 0) {
                var prev = this.history.pop();
                if (prev.view === 'hub') {
                    this.anaSayfayaDon();
                } else if (prev.view === 'report' && prev.catId && prev.repId) {
                    if (this.activeCategory === prev.catId) {
                        this.raporAc(prev.repId);
                    } else {
                        this.kategoriAc(prev.catId, prev.repId);
                    }
                }
            } else {
                this.anaSayfayaDon();
            }
        },

        anaSayfayaDon: function(instant) {
            var self = this;
            var hubView = document.getElementById('rhub-anasayfa-view');
            var catView = document.getElementById('rhub-kategori-view');
            if (!hubView || !catView) return;

            this.activeCategory = null;
            this.activeReport = null;
            this.history = [];

            // Açık olan paneli geri ana şablona taşıyalim ki kaybolmasın
            var targetContainer = document.getElementById('rhub-report-target');
            if (targetContainer) {
                var currentChild = targetContainer.firstElementChild;
                if (currentChild) {
                    currentChild.style.display = 'none';
                    currentChild.classList.remove('aktif');
                    document.querySelector('.rhub-content-wrapper').appendChild(currentChild);
                }
            }

            if (instant) {
                catView.style.display = 'none';
                hubView.style.display = 'block';
                hubView.style.transform = 'translateY(0)';
                hubView.style.opacity = '1';
                return;
            }

            catView.style.transform = 'translateY(10px)';
            catView.style.opacity = '0';
            setTimeout(function() {
                catView.style.display = 'none';
                hubView.style.display = 'block';
                hubView.style.transform = 'translateY(-10px)';
                hubView.style.opacity = '0';
                
                setTimeout(function() {
                    hubView.style.transform = 'translateY(0)';
                    hubView.style.opacity = '1';
                    
                    // Ana sayfaya dönüldüğünde dynamic listeleri yenile
                    self.renderFavorites();
                    self.renderRecents();
                }, 50);
            }, 150);
        }
    };

    // Global nesne başlatması
    HK.ReportHub.init();

    // Kategorileri Tanımla
    HK.ReportHub.registerCategory({
        id: 'satis',
        title: 'Satış Raporları',
        icon: '🛍️',
        color: '#3b82f6',
        description: 'Kasa ve internet satış ciro ve sipariş kayıtları.',
        order: 1
    });

    HK.ReportHub.registerCategory({
        id: 'iade',
        title: 'İade & Denetim',
        icon: '🔙',
        color: '#ef4444',
        description: 'İade kayıtları ve sipariş müdahale denetim geçmişi.',
        order: 2
    });

    HK.ReportHub.registerCategory({
        id: 'istatistik',
        title: 'İstatistikler',
        icon: '📈',
        color: '#10b981',
        description: 'Satış grafikleri, en çok satanlar ve genel ciro analizi.',
        order: 3
    });

    HK.ReportHub.registerCategory({
        id: 'arsiv',
        title: 'Arşiv & Envanter',
        icon: '📂',
        color: '#8b5cf6',
        description: 'Gün sonu geçmişi arşivi ve depo sayım seans kayıtları.',
        order: 4
    });

    HK.ReportHub.registerCategory({
        id: 'finans',
        title: 'Finansal Raporlar',
        icon: '💰',
        color: '#f59e0b',
        description: 'Kâr-zarar tabloları, masraf dağılımları ve vergi raporları.',
        order: 5,
        badge: 'yakinda'
    });

    HK.ReportHub.registerCategory({
        id: 'personel',
        title: 'Personel Raporları',
        icon: '👥',
        color: '#06b6d4',
        description: 'Kasiyer performans analizleri ve satış komisyon oranları.',
        order: 6,
        badge: 'yakinda'
    });

})(window.HizliKasa);
