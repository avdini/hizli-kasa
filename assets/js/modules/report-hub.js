/**
 * Hızlı Kasa - Raporlar Hub Yönetim Modülü
 */
(function(HK) {
    'use strict';

    HK.ReportHub = {
        categories: {},
        reports: {},
        activeCategory: null,
        activeReport: null,
        history: [],

        init: function() {
            var self = this;
            console.log("HK.ReportHub: Init started");

            document.addEventListener('hkTabLoaded', function(e) {
                if (e.detail.tab === 'raporlar') {
                    self.setupHub();
                    self.updateAnlikKasaSummary();
                }
            });

            // ESC ile geri dönme
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && HK.State && HK.State.aktifSekme === 'raporlar') {
                    self.geriGit();
                }
            });

            // Diğer modüllerden Anlık Kasa güncellendiğinde ciro kartlarını yenile
            $(document).on('hk:anlik-kasa-guncellendi', function(e, ozet) {
                self.renderAnlikKasaSummary(ozet);
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
                searchPlaceholder: rep.searchPlaceholder || 'Ara...'
            };
        },

        setupHub: function() {
            var self = this;
            var container = document.getElementById('rapor-hub-root');
            if (!container) return;

            // Eğer hub zaten kurulmuşsa sadece ana sayfaya dön
            if (container.dataset.initialized === 'true') {
                this.anaSayfayaDon(true);
                return;
            }

            container.dataset.initialized = 'true';

            // HTML yapısını kur
            container.innerHTML = `
                <div class="rhub-wrapper">
                    <!-- Üst Başlık ve Anlık Kasa -->
                    <div id="rhub-anasayfa-view">
                        <div class="rhub-header">
                            <div>
                                <h2>📊 Rapor Merkezi</h2>
                                <p class="rhub-subtitle">Tüm operasyonel ve finansal raporlara tek bir yerden erişin.</p>
                            </div>
                            <div class="rhub-anlik-kasa-card" id="rhub-summary-card">
                                <div class="rhub-anlik-kasa-header">
                                    <span>⚡ Anlık Kasa Durumu</span>
                                    <button class="hk-btn-outline compact" id="rhub-anlik-kasa-detay-btn">Detay</button>
                                </div>
                                <div class="rhub-anlik-kasa-grid">
                                    <div class="rhub-anlik-item">
                                        <span class="rhub-anlik-label">Nakit</span>
                                        <span class="rhub-anlik-val" id="rhub-val-nakit">0.00 TL</span>
                                    </div>
                                    <div class="rhub-anlik-item">
                                        <span class="rhub-anlik-label">Kart</span>
                                        <span class="rhub-anlik-val" id="rhub-val-kart">0.00 TL</span>
                                    </div>
                                    <div class="rhub-anlik-item">
                                        <span class="rhub-anlik-label">IBAN</span>
                                        <span class="rhub-anlik-val" id="rhub-val-iban">0.00 TL</span>
                                    </div>
                                    <div class="rhub-anlik-item total">
                                        <span class="rhub-anlik-label">Genel Net</span>
                                        <span class="rhub-anlik-val" id="rhub-val-genel">0.00 TL</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Kategoriler Grid -->
                        <div class="rhub-grid" id="rhub-categories-grid"></div>
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

            document.getElementById('rhub-anlik-kasa-detay-btn').addEventListener('click', function() {
                if (HK.AnlikKasa && HK.AnlikKasa.$buton) {
                    HK.AnlikKasa.$buton.trigger('click');
                }
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
                searchInput.addEventListener('keyup', HK.utils.debounce(function() {
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

            // Kategorileri listele
            this.renderCategories();
        },

        renderCategories: function() {
            var self = this;
            var grid = document.getElementById('rhub-categories-grid');
            if (!grid) return;

            var cats = Object.values(this.categories).sort((a, b) => a.order - b.order);
            grid.innerHTML = cats.map(cat => {
                var isComingSoon = cat.badge === 'yakinda';
                var badgeHtml = isComingSoon ? `<span class="rhub-badge-yk">Yakında</span>` : '';
                var catReports = Object.values(this.reports).filter(r => r.categoryId === cat.id);
                var subtextHtml = isComingSoon ? 'Çok Yakında Hizmetinizde' : `${catReports.length} Rapor Aktif`;

                return `
                    <div class="rhub-card ${isComingSoon ? 'coming-soon' : ''}" data-id="${cat.id}" style="--cat-color: ${cat.color}">
                        <div class="rhub-card-icon">${cat.icon}</div>
                        <div class="rhub-card-body">
                            <h3>${cat.title} ${badgeHtml}</h3>
                            <p>${cat.description}</p>
                            <span class="rhub-card-meta">${subtextHtml}</span>
                        </div>
                    </div>
                `;
            }).join('');

            // Kartlara tıklama olayı
            grid.querySelectorAll('.rhub-card:not(.coming-soon)').forEach(card => {
                card.addEventListener('click', function() {
                    var catId = this.dataset.id;
                    self.kategoriAc(catId);
                });
            });
        },

        kategoriAc: function(catId) {
            var self = this;
            var cat = this.categories[catId];
            if (!cat) return;

            this.activeCategory = catId;
            this.history.push({ view: 'hub' });

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
                
                // Kategorideki ilk raporu aç
                var catReports = Object.values(self.reports).filter(r => r.categoryId === catId);
                if (catReports.length > 0) {
                    self.raporAc(catReports[0].id);
                }
            }, 150);
        },

        renderSidebarMenu: function() {
            var self = this;
            var sidebar = document.getElementById('rhub-sidebar-menu');
            if (!sidebar) return;

            var catReports = Object.values(this.reports).filter(r => r.categoryId === this.activeCategory);

            sidebar.innerHTML = catReports.map(rep => `
                <button class="rhub-side-btn" id="rhub-btn-${rep.id}" data-id="${rep.id}">
                    <span class="rhub-side-icon">${rep.icon}</span>
                    <span class="rhub-side-title">${rep.title}</span>
                </button>
            `).join('');

            sidebar.querySelectorAll('.rhub-side-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    self.raporAc(this.dataset.id);
                });
            });
        },

        raporAc: function(repId) {
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
                }
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
                    self.updateAnlikKasaSummary();
                }, 50);
            }, 150);
        },

        updateAnlikKasaSummary: function() {
            if (HK.AnlikKasa && typeof HK.AnlikKasa.guncelle === 'function') {
                HK.AnlikKasa.guncelle();
            }
        },

        renderAnlikKasaSummary: function(ozet) {
            var nakitVal = document.getElementById('rhub-val-nakit');
            var kartVal = document.getElementById('rhub-val-kart');
            var ibanVal = document.getElementById('rhub-val-iban');
            var genelVal = document.getElementById('rhub-val-genel');

            if (!ozet) return;

            var nakit = ozet.nakit_toplam - ozet.iade_nakit;
            var kart = ozet.kart_toplam - ozet.iade_kart;
            var iban = ozet.iban_toplam - ozet.iade_iban;
            var genel = nakit + kart + iban;

            var format = function(val) {
                return HK.UIRenderer ? HK.UIRenderer.formatPara(val) + ' TL' : val.toFixed(2) + ' TL';
            };

            if (nakitVal) {
                nakitVal.textContent = format(nakit);
            }
            if (kartVal) {
                kartVal.textContent = format(kart);
            }
            if (ibanVal) {
                ibanVal.textContent = format(iban);
            }
            if (genelVal) {
                genelVal.textContent = format(genel);
            }
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
