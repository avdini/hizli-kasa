/**
 * Hızlı Kasa - Ürün İstatistik Analizi Raporu
 *
 * HK.ReportHub'a "istatistik" kategorisi altında kaydedilir.
 * Belirli bir SKU için tarih aralığında satış, iade, maliyet ve kâr analizi sunar.
 *
 * @package HizliKasa
 */
(function (HK) {
    'use strict';

    HK.ProductStatsReport = {

        charts: {},
        lastData: null,
        selectedDayData: null,

        init: function () {
            var self = this;

            if (HK.ReportHub) {
                HK.ReportHub.registerReport({
                    id: 'urun-istatistik',
                    categoryId: 'istatistik',
                    title: 'Ürün İstatistik Analizi',
                    icon: '🔍',
                    panelId: 'rapor-urun-istatistik',
                    onActivate: function () { self.onActivateReport(); },
                    hasDateFilter: true,
                    hasSearch: false,
                    description: 'SKU bazlı satış, iade ve kâr analizi',
                    order: 2
                });
            }

            // Depo değiştiğinde ve Ürün İstatistik Raporu aktifse yeni depoya göre verileri yenile
            document.addEventListener('hkActiveDepoChanged', function () {
                if (HK.ReportHub && HK.ReportHub.activeCategory === 'istatistik' && HK.ReportHub.activeReport === 'urun-istatistik') {
                    var sku = self.getCurrentSKU();
                    if (sku) {
                        self._loadStats(sku);
                    }
                }
            });

            document.addEventListener('hkThemeChanged', function () {
                if (self.lastData) {
                    self._destroyCharts();
                    self._renderCharts(self.lastData);
                }
            });
        },

        getCurrentSKU: function () {
            var self = this;
            var input = document.getElementById('psr-sku-input');
            var inputSku = input ? input.value.trim() : '';
            if (inputSku) return inputSku;
            if (self.lastData && self.lastData.product && self.lastData.product.sku) {
                return self.lastData.product.sku;
            }
            return self.lastSKU || '';
        },

        onActivateReport: function () {
            var self = this;
            var input = document.getElementById('psr-sku-input');
            if (!input) {
                self._renderShell();
            } else {
                var sku = self.getCurrentSKU();
                if (sku) {
                    self._loadStats(sku);
                }
            }
        },

        analyzeSKU: function (sku) {
            var self = this;
            if (!sku || !HK.ReportHub) return;

            self.pendingSKU = sku;

            if (HK.ReportHub.history) {
                HK.ReportHub.history.push({
                    view: 'report',
                    catId: 'istatistik',
                    repId: 'ozet-istatistik'
                });
            }

            HK.ReportHub.kategoriAc('istatistik', 'urun-istatistik');

            setTimeout(function () {
                if (self.pendingSKU) {
                    var skuToLoad = self.pendingSKU;
                    self.pendingSKU = null;
                    var input = document.getElementById('psr-sku-input');
                    if (input) input.value = skuToLoad;
                    self._loadStats(skuToLoad);
                }
            }, 100);
        },

        _renderShell: function () {
            var self = this;
            var panel = document.getElementById('rapor-urun-istatistik');
            if (!panel) return;

            panel.innerHTML = [
                '<div class="psr-wrap">',
                  '<div class="psr-product-bar">',
                    '<div class="psr-bar-search-row">',
                      '<div class="psr-sku-field">',
                        '<label class="psr-label">SKU Girin</label>',
                        '<div class="psr-input-row">',
                          '<input type="text" id="psr-sku-input" class="hk-input psr-sku-input" placeholder="Örn: ABC-001" autocomplete="off">',
                          '<button id="psr-sorgula-btn" class="hk-btn-primary psr-sorgula-btn">Analiz Et</button>',
                        '</div>',
                        '<div id="psr-product-preview" class="psr-product-preview" style="display:none;"></div>',
                      '</div>',
                    '</div>',
                    '<div id="psr-bar-info" class="psr-bar-info" style="display:none;"></div>',
                    '<div id="psr-bar-relations" class="psr-bar-relations" style="display:none;"></div>',
                  '</div>',
                  '<div id="psr-dashboard" class="psr-dashboard" style="display:none;"></div>',
                '</div>'
            ].join('');

            var skuInput = document.getElementById('psr-sku-input');
            var sorgulaBtn = document.getElementById('psr-sorgula-btn');

            var previewTimeout = null;
            skuInput.addEventListener('input', function () {
                clearTimeout(previewTimeout);
                var val = this.value.trim();
                if (val.length < 2) {
                    document.getElementById('psr-product-preview').style.display = 'none';
                    return;
                }
                previewTimeout = setTimeout(function () {
                    self._fetchPreview(val);
                }, 400);
            });

            skuInput.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') {
                    sorgulaBtn.click();
                }
            });

            sorgulaBtn.addEventListener('click', function () {
                var sku = document.getElementById('psr-sku-input').value.trim();
                if (!sku) {
                    HK.UI && HK.UI.showToast && HK.UI.showToast('Lütfen bir SKU girin.', 'warning');
                    return;
                }
                self._loadStats(sku);
            });

            if (self.pendingSKU) {
                var skuToLoad = self.pendingSKU;
                self.pendingSKU = null;
                skuInput.value = skuToLoad;
                self._loadStats(skuToLoad);
            } else if (self.lastData) {
                self._renderDashboard(self.lastData);
            }
        },

        _fetchPreview: function (sku) {
            var self = this;
            var previewEl = document.getElementById('psr-product-preview');
            if (!previewEl) return;

            previewEl.innerHTML = '<span class="psr-preview-loading">⏳ Ürün aranıyor...</span>';
            previewEl.style.display = 'block';

            fetch(kasaAyar.rootApiUrl + 'hizli-kasa/v2/statistics/product/preview?sku=' + encodeURIComponent(sku), {
                headers: { 'X-WP-Nonce': kasaAyar.nonce }
            })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (!res.success || !res.data || !res.data.product) {
                    previewEl.innerHTML = '<span class="psr-preview-notfound">❌ Bu SKU ile ürün bulunamadı.</span>';
                    return;
                }
                var p = res.data.product;
                var typeLabel = { simple: 'Basit Ürün', variable: 'Varyasyonlu Ürün (Parent)', variation: 'Varyasyon' };
                previewEl.innerHTML = [
                    '<div class="psr-preview-card">',
                      '<div class="psr-preview-img-wrap" title="Resmi Büyüt">',
                        '<img src="' + p.image_url + '" class="psr-preview-img" alt="' + self._esc(p.name) + '">',
                        '<span class="psr-zoom-overlay">🔍</span>',
                      '</div>',
                      '<div class="psr-preview-info">',
                        '<div class="psr-preview-title-row">',
                          '<strong>' + self._esc(p.name) + '</strong>',
                          '<span class="psr-preview-badge">Aktif Ürün</span>',
                        '</div>',
                        '<span class="psr-preview-meta">SKU: ' + self._esc(p.sku) + ' &bull; ' + (typeLabel[p.type] || p.type) + '</span>',
                      '</div>',
                    '</div>'
                ].join('');

                var imgWrap = previewEl.querySelector('.psr-preview-img-wrap');
                if (imgWrap) {
                    imgWrap.addEventListener('click', function (e) {
                        e.stopPropagation();
                        if (HK.UIRenderer && typeof HK.UIRenderer.openImagePreview === 'function') {
                            HK.UIRenderer.openImagePreview(p.image_full_url_real || p.image_url);
                        }
                    });
                }
            })
            .catch(function () {
                previewEl.innerHTML = '<span class="psr-preview-notfound">Ürün önizlemesi alınamadı.</span>';
            });
        },

        _loadStats: function (sku) {
            var self = this;
            self.lastSKU = sku;
            var dashboard = document.getElementById('psr-dashboard');
            if (!dashboard) return;

            var input = document.getElementById('psr-sku-input');
            if (input && input.value !== sku) {
                input.value = sku;
            }
            self._fetchPreview(sku);

            var dateStart = (document.getElementById('rhub-tarih-bas') || {}).value || self._today();
            var dateEnd   = (document.getElementById('rhub-tarih-bit') || {}).value || self._today();
            var depoId    = HK.DepoManager ? HK.DepoManager.getActiveDepo() : 0;

            dashboard.style.display = 'block';
            dashboard.innerHTML = '<div class="psr-loading"><div class="stat-spinner"></div><p>Ürün verileri yükleniyor...</p></div>';

            self._destroyCharts();
            self.lastData = null;
            self.selectedDayData = null;

            var url = kasaAyar.rootApiUrl + 'hizli-kasa/v2/statistics/product'
                + '?sku='        + encodeURIComponent(sku)
                + '&date_start=' + dateStart
                + '&date_end='   + dateEnd
                + '&depo_id='    + depoId;

            fetch(url, { headers: { 'X-WP-Nonce': kasaAyar.nonce } })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (!res.success || !res.data) {
                    dashboard.innerHTML = '<div class="psr-empty"><span>⚠️</span><p>' + (res.errors ? res.errors[0] : 'Veri yüklenemedi.') + '</p></div>';
                    return;
                }
                self.lastData = res.data;
                self._renderDashboard(res.data);
            })
            .catch(function (err) {
                console.error('HK.ProductStatsReport:', err);
                dashboard.innerHTML = '<div class="psr-empty"><span>⚠️</span><p>Bağlantı hatası. Konsola bakın.</p></div>';
            });
        },

        _renderDashboard: function (data) {
            var self = this;
            self._destroyCharts();

            var dashboard = document.getElementById('psr-dashboard');
            if (!dashboard) return;

            var p   = data.product  || {};
            var kpi = data.kpi      || {};
            var trend = data.gunluk_trend  || [];
            var satis = data.satis_listesi || [];
            var vars  = data.variations    || [];

            var typeLabel = { simple: 'Basit Ürün', variable: 'Varyasyonlu Ürün', variation: 'Varyasyon' };

            var html = '<div class="stat-dashboard-wrap">';

            // Update top product bar with product details and relations
            self._updateProductBar(p);

            if (!kpi.toplam_satis_adet && !kpi.toplam_iade_adet) {
                html += '<div class="psr-empty"><span>📦</span><p>Seçili tarih aralığında bu ürün için kayıt bulunamadı.</p></div>';
                html += '</div>';
                dashboard.innerHTML = html;
                return;
            }

            // KPI Grid
            html += '<div class="psr-kpi-grid">';
            html += self._kpiCard('📦', 'TOPLAM SATIŞ', kpi.toplam_satis_adet + ' adet', self._currency(kpi.toplam_satis_ciro), 'kpi-satis');
            html += self._kpiCard('↩️', 'TOPLAM İADE', kpi.toplam_iade_adet + ' adet', self._currency(kpi.toplam_iade_tutar), 'kpi-iade');
            html += self._kpiCard('📉', 'NET CİRO', self._currency(kpi.net_ciro), 'Ciro – İade', 'kpi-net');
            if (kpi.toplam_iskonto_tutar > 0) {
                html += self._kpiCard('✂️', 'TOPLAM İSKONTO', self._currency(kpi.toplam_iskonto_tutar), 'Liste Fiyatı Düşüşü', 'kpi-kar-pozitif');
            }
            html += '</div>';

            // Main Trend Line Chart & Distribution Charts Grid
            html += '<div class="stat-charts-grid">';

            if (!self.activeTrendMode) {
                self.activeTrendMode = 'satis';
            }

            var trend   = data.gunluk_trend  || [];
            var saatlik = data.saatlik_trend || [];
            var isSingleDay = (trend.length <= 1 && saatlik.length > 0);

            // 1. Ana Trend Line Chart (Full Width)
            if (trend.length > 0) {
                html += '<div class="stat-chart-card stat-chart-full">';
                html +=   '<div class="stat-chart-header stat-main-trend-header">';
                html +=     '<div>';
                html +=       '<h4 class="stat-chart-title" id="psr-main-trend-title">' + (isSingleDay ? '📈 Saatlik Satış Trendi' : '📈 Günlük Satış Trendi') + '</h4>';
                html +=       '<span class="stat-chart-badge" id="psr-main-trend-badge">' + (isSingleDay ? 'Saatlik Yoğunluk • 24 Saat' : trend.length + ' gün') + '</span>';
                html +=     '</div>';
                html +=     '<div class="stat-trend-toggle-wrap">';
                html +=       '<button class="stat-trend-toggle-btn' + (self.activeTrendMode === 'satis' ? ' active' : '') + '" data-mode="satis">📈 Satış Trendi</button>';
                html +=       '<button class="stat-trend-toggle-btn' + (self.activeTrendMode === 'iskonto' ? ' active' : '') + '" data-mode="iskonto">✂️ İskonto Analizi</button>';
                html +=     '</div>';
                html +=   '</div>';
                html +=   '<div class="psr-chart-hint" id="psr-main-trend-hint">💡 Grafikteki bir noktaya tıklayarak o günün satışlarını inceleyin</div>';
                html +=   '<div class="stat-chart-body psr-chart-body-main"><canvas id="psr-chart-main"></canvas></div>';
                html +=   '<div id="psr-day-accordion" class="psr-day-accordion" style="display:none;"></div>';
                html += '</div>';
            }

            // 2. Satış vs İade Bar Chart (Half Width) & 3. Dağılım Doughnut Kartı (Half Width)
            if (trend.length > 0) {
                if (!self.activeDistMode) {
                    self.activeDistMode = vars.length > 1 ? 'vars' : 'odeme';
                }

                html += '<div class="stat-chart-card">';
                html +=   '<div class="stat-chart-header"><h4 class="stat-chart-title">📊 Satış vs İade (Adet)</h4></div>';
                html +=   '<div class="stat-chart-body"><canvas id="psr-chart-compare"></canvas></div>';
                html += '</div>';

                html += '<div class="stat-chart-card stat-odeme-card" id="psr-dist-card">';
                html +=   '<div class="stat-chart-header stat-main-trend-header">';
                html +=     '<div>';
                html +=       '<h4 class="stat-chart-title" id="psr-dist-title">' + (self.activeDistMode === 'vars' ? '🎨 Varyasyon Dağılımı' : '💳 Ödeme Tipi Dağılımı') + '</h4>';
                html +=       '<span class="stat-chart-badge" id="psr-dist-badge"></span>';
                html +=     '</div>';

                if (vars.length > 1) {
                    html +=   '<div class="stat-trend-toggle-wrap">';
                    html +=     '<button class="stat-trend-toggle-btn' + (self.activeDistMode === 'vars' ? ' active' : '') + '" data-dist-mode="vars">🎨 Varyasyon</button>';
                    html +=     '<button class="stat-trend-toggle-btn' + (self.activeDistMode === 'odeme' ? ' active' : '') + '" data-dist-mode="odeme">💳 Ödeme</button>';
                    html +=   '</div>';
                }

                html +=   '</div>';
                html +=   '<div class="stat-chart-body stat-odeme-body">';
                html +=     '<div class="stat-odeme-chart-wrap">';
                html +=       '<canvas id="psr-chart-dist"></canvas>';
                html +=       '<div class="stat-odeme-center-text">';
                html +=         '<span class="stat-odeme-center-label">TOPLAM</span>';
                html +=         '<span class="stat-odeme-center-val" id="psr-dist-center-total">0</span>';
                html +=       '</div>';
                html +=     '</div>';
                html +=     '<div class="stat-odeme-legend-list" id="psr-dist-legend-list"></div>';
                html +=   '</div>';
                html += '</div>';
            }

            // 4. Varyasyon tablosu (Full Width)
            if (vars.length > 1) {
                html += '<div class="stat-chart-card stat-chart-full">';
                html +=   '<div class="psr-chart-header"><h4 class="stat-chart-title">📋 Varyasyon Detay Tablosu</h4></div>';
                html +=   self._renderVarTable(vars);
                html += '</div>';
            }

            // 5. Satış Kayıtları Tablosu (Full Width)
            if (satis.length > 0) {
                html += '<div class="stat-chart-card stat-chart-full psr-satis-listesi-wrap">';
                html +=   '<div class="psr-chart-header"><h4 class="stat-chart-title">🧾 Satış Kayıtları</h4><span class="stat-chart-badge">' + satis.length + ' kayıt</span></div>';
                html +=   self._renderSatisTable(satis);
                html += '</div>';
            }

            html += '</div>'; // End stat-charts-grid
            html += '</div>'; // End stat-dashboard-wrap

            dashboard.innerHTML = html;
            self._bindDashboardEvents(data);
        },

        _bindDashboardEvents: function (data) {
            var self = this;
            var dashEl = document.getElementById('psr-dashboard');
            if (!dashEl) return;

            // Bind collapse toggle button
            var toggleBtn = dashEl.querySelector('#psr-toggle-siblings');
            var panelEl = dashEl.querySelector('#psr-siblings-panel');
            if (toggleBtn && panelEl) {
                toggleBtn.addEventListener('click', function () {
                    var isHidden = panelEl.style.display === 'none';
                    if (isHidden) {
                        panelEl.style.display = 'block';
                        toggleBtn.classList.add('active');
                        toggleBtn.textContent = toggleBtn.textContent.replace('Göster', 'Gizle').replace('▾', '▴');
                        panelEl.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                    } else {
                        panelEl.style.display = 'none';
                        toggleBtn.classList.remove('active');
                        toggleBtn.textContent = toggleBtn.textContent.replace('Gizle', 'Göster').replace('▴', '▾');
                    }
                });
            }

            dashEl.querySelectorAll('.psr-clickable-card').forEach(function (card) {
                card.addEventListener('click', function (e) {
                    if (e.target.closest('.psr-node-img-wrap')) {
                        return;
                    }
                    var clickedSku = this.dataset.sku;
                    if (clickedSku) {
                        var inputEl = document.getElementById('psr-sku-input');
                        if (inputEl) {
                            inputEl.value = clickedSku;
                        }
                        self._loadStats(clickedSku);
                        self._fetchPreview(clickedSku);
                    }
                });
            });

            dashEl.querySelectorAll('.psr-node-img-wrap, .psr-header-img-wrap').forEach(function (wrap) {
                wrap.addEventListener('click', function (e) {
                    e.stopPropagation();
                    var fullSrc = this.dataset.fullSrc;
                    if (fullSrc && HK.UIRenderer && typeof HK.UIRenderer.openImagePreview === 'function') {
                        HK.UIRenderer.openImagePreview(fullSrc);
                    }
                });
            });

            self._bindCharts(data);
        },

        _bindCharts: function (data) {
            var self = this;
            var trend = data.gunluk_trend  || [];
            var vars  = data.variations    || [];

            if (typeof Chart === 'undefined') return;

            var isDark = document.getElementById('hizli-kasa-app') &&
                document.getElementById('hizli-kasa-app').classList.contains('theme-dark');

            var gridColor  = isDark ? 'rgba(255,255,255,0.07)' : 'rgba(0,0,0,0.06)';
            var tickColor  = isDark ? '#94a3b8' : '#64748b';
            var tooltipBg  = isDark ? '#1e293b' : '#ffffff';
            var tooltipTxt = isDark ? '#f1f5f9' : '#1e293b';

            Chart.defaults.color = tickColor;
            Chart.defaults.font.family = "'Inter', 'Segoe UI', system-ui, sans-serif";
            Chart.defaults.font.size = 12;

            var commonTooltip = {
                backgroundColor: tooltipBg,
                titleColor: tooltipTxt,
                bodyColor: tickColor,
                borderColor: isDark ? '#334155' : '#e2e8f0',
                borderWidth: 1,
                padding: 12,
                cornerRadius: 8,
            };

            // Ana Trend Chart (Satış veya İskonto)
            if (trend.length > 0 && document.getElementById('psr-chart-main')) {
                self._initMainTrendChart(data, gridColor, tickColor, commonTooltip);

                var dashEl = document.getElementById('psr-dashboard');
                if (dashEl) {
                    dashEl.querySelectorAll('.stat-trend-toggle-btn').forEach(function (btn) {
                        btn.addEventListener('click', function () {
                            var mode = this.dataset.mode;
                            if (mode === self.activeTrendMode) return;
                            self.activeTrendMode = mode;

                            dashEl.querySelectorAll('.stat-trend-toggle-btn').forEach(function (b) {
                                b.classList.toggle('active', b.dataset.mode === mode);
                            });

                            self._updateMainTrendChart(data, gridColor, tickColor, commonTooltip);
                        });
                    });
                }
            }

            // Compare Chart
            if (trend.length > 0 && document.getElementById('psr-chart-compare')) {
                var isSingleDayCompare = (trend.length <= 1 && (data.saatlik_trend || []).length > 0);
                var saatlikCompare = data.saatlik_trend || [];
                var compareLabels = isSingleDayCompare ? saatlikCompare.map(function (s) { return s.saat; }) : trend.map(function (g) { return g.tarih_kisa; });
                var compareSatis  = isSingleDayCompare ? saatlikCompare.map(function (s) { return s.satis_adet; }) : trend.map(function (g) { return g.satis_adet; });
                var compareIade   = isSingleDayCompare ? saatlikCompare.map(function (s) { return s.iade_adet; }) : trend.map(function (g) { return g.iade_adet; });

                self.charts.compare = new Chart(document.getElementById('psr-chart-compare'), {
                    type: 'bar',
                    data: {
                        labels: compareLabels,
                        datasets: [
                            {
                                label: 'Satış',
                                data: compareSatis,
                                backgroundColor: 'rgba(16,185,129,0.75)',
                                borderRadius: 5,
                            },
                            {
                                label: 'İade',
                                data: compareIade,
                                backgroundColor: 'rgba(239,68,68,0.75)',
                                borderRadius: 5,
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: { mode: 'index', intersect: false },
                        plugins: {
                            legend: { labels: { color: tickColor } },
                            tooltip: Object.assign({}, commonTooltip, {
                                callbacks: {
                                    label: function (ctx) { return ' ' + ctx.parsed.y + ' adet'; }
                                }
                            })
                        },
                        scales: {
                            x: { grid: { display: false }, ticks: { color: tickColor } },
                            y: { grid: { color: gridColor }, ticks: { color: tickColor, precision: 0 }, beginAtZero: true }
                        }
                    }
                });
            }

            // Distribution Doughnut Chart (Varyasyon & Ödeme Dağılımı Dönüşümlü)
            if (document.getElementById('psr-chart-dist')) {
                self._initDistChart(data, commonTooltip);

                var distCardEl = document.getElementById('psr-dist-card');
                if (distCardEl) {
                    distCardEl.querySelectorAll('.stat-trend-toggle-btn').forEach(function (btn) {
                        btn.addEventListener('click', function () {
                            var mode = this.dataset.distMode;
                            if (mode === self.activeDistMode) return;
                            self.activeDistMode = mode;

                            distCardEl.querySelectorAll('.stat-trend-toggle-btn').forEach(function (b) {
                                b.classList.toggle('active', b.dataset.distMode === mode);
                            });

                            self._updateDistChart(data, commonTooltip);
                        });
                    });
                }
            }
        },

        /* -------------------------------------------------
           DAĞILIM GRAFİĞİ (VARYASYON & ÖDEME DÖNÜŞÜMLÜ)
        ------------------------------------------------- */
        _initDistChart: function (data, commonTooltip) {
            var self = this;
            var canvas = document.getElementById('psr-chart-dist');
            if (!canvas) return;

            self.charts.dist = new Chart(canvas, {
                type: 'doughnut',
                data: { labels: [], datasets: [] },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '74%',
                    layout: { padding: 4 },
                    plugins: {
                        legend: { display: false },
                        tooltip: Object.assign({}, commonTooltip, {
                            callbacks: {
                                label: function (ctx) { return ' ' + ctx.label + ': ' + ctx.parsed; }
                            }
                        })
                    }
                }
            });

            self._updateDistChart(data, commonTooltip);
        },

        _updateDistChart: function (data, commonTooltip) {
            var self = this;
            var chart = self.charts.dist;
            if (!chart) return;

            var vars = data.variations || [];
            var kpi  = data.kpi || {};
            var od   = kpi.odeme_dagilimi || {};

            var mode = self.activeDistMode || (vars.length > 1 ? 'vars' : 'odeme');
            self.activeDistMode = mode;

            var titleEl       = document.getElementById('psr-dist-title');
            var badgeEl       = document.getElementById('psr-dist-badge');
            var centerTotalEl = document.getElementById('psr-dist-center-total');
            var legendListEl  = document.getElementById('psr-dist-legend-list');

            if (mode === 'vars' && vars.length > 1) {
                if (titleEl) titleEl.innerText = '🎨 Varyasyon Dağılımı';
                if (badgeEl) badgeEl.innerText = vars.length + ' varyasyon';

                var varColors = ['#3b82f6','#10b981','#f59e0b','#8b5cf6','#ef4444','#06b6d4','#ec4899','#84cc16'];
                var totalVarsAdet = vars.reduce(function (acc, v) { return acc + (parseInt(v.satis_adet, 10) || 0); }, 0);

                if (centerTotalEl) centerTotalEl.innerText = totalVarsAdet + ' Adet';

                if (legendListEl) {
                    var legendHtml = '';
                    vars.forEach(function (v, idx) {
                        var color = varColors[idx % varColors.length];
                        var vAdet = parseInt(v.satis_adet, 10) || 0;
                        var pct = totalVarsAdet > 0 ? ((vAdet / totalVarsAdet) * 100).toFixed(1) : '0';
                        legendHtml += '<div class="stat-odeme-legend-item" data-idx="' + idx + '" title="' + self._esc(v.full_name || v.name) + '">';
                        legendHtml +=   '<div class="stat-odeme-item-left">';
                        legendHtml +=     '<span class="stat-odeme-dot" style="background-color:' + color + '"></span>';
                        legendHtml +=     '<span class="stat-odeme-item-name">' + self._esc(v.name) + '</span>';
                        legendHtml +=   '</div>';
                        legendHtml +=   '<div class="stat-odeme-item-right">';
                        legendHtml +=     '<span class="stat-odeme-item-val">' + vAdet + ' adet</span>';
                        legendHtml +=     '<span class="stat-odeme-item-pct">%' + pct + '</span>';
                        legendHtml +=   '</div>';
                        legendHtml += '</div>';
                    });
                    legendListEl.innerHTML = legendHtml;

                    self._bindDistLegendEvents(legendListEl);
                }

                chart.data.labels = vars.map(function (v) { return v.name; });
                chart.data.datasets = [{
                    data: vars.map(function (v) { return v.satis_adet; }),
                    backgroundColor: vars.map(function (_, i) { return varColors[i % varColors.length]; }),
                    borderWidth: 0,
                    hoverOffset: 10,
                    clip: false,
                }];

                chart.options.plugins.tooltip = Object.assign({}, commonTooltip, {
                    callbacks: {
                        label: function (ctx) {
                            var val = ctx.parsed;
                            var pct = totalVarsAdet > 0 ? ((val / totalVarsAdet) * 100).toFixed(1) : '0';
                            return ' ' + ctx.label + ': ' + val + ' adet (%' + pct + ')';
                        }
                    }
                });

            } else {
                if (titleEl) titleEl.innerText = '💳 Ödeme Tipi Dağılımı';
                if (badgeEl) badgeEl.innerText = 'Net Dağılım';

                var nakitVal = parseFloat(od.nakit) || 0;
                var kartVal  = parseFloat(od.kart) || 0;
                var ibanVal  = parseFloat(od.iban) || 0;
                var qrVal    = parseFloat(od.qr_taksit) || 0;
                var totalOd  = nakitVal + kartVal + ibanVal + qrVal;

                if (centerTotalEl) centerTotalEl.innerText = self._currency(totalOd);

                var items = [
                    { label: 'Nakit',     icon: '💵', val: nakitVal, color: '#10b981' },
                    { label: 'Kart',      icon: '💳', val: kartVal,  color: '#3b82f6' },
                    { label: 'IBAN',      icon: '🏦', val: ibanVal,  color: '#8b5cf6' },
                    { label: 'QR/Taksit', icon: '📱', val: qrVal,    color: '#f59e0b' }
                ];

                if (legendListEl) {
                    var legendHtml = '';
                    items.forEach(function (item, idx) {
                        var pct = totalOd > 0 ? ((item.val / totalOd) * 100).toFixed(1) : '0';
                        legendHtml += '<div class="stat-odeme-legend-item" data-idx="' + idx + '" title="' + item.label + ': ' + self._currency(item.val) + '">';
                        legendHtml +=   '<div class="stat-odeme-item-left">';
                        legendHtml +=     '<span class="stat-odeme-dot" style="background-color:' + item.color + '"></span>';
                        legendHtml +=     '<span class="stat-odeme-item-name">' + item.icon + ' ' + item.label + '</span>';
                        legendHtml +=   '</div>';
                        legendHtml +=   '<div class="stat-odeme-item-right">';
                        legendHtml +=     '<span class="stat-odeme-item-val">' + self._currency(item.val) + '</span>';
                        legendHtml +=     '<span class="stat-odeme-item-pct">%' + pct + '</span>';
                        legendHtml +=   '</div>';
                        legendHtml += '</div>';
                    });
                    legendListEl.innerHTML = legendHtml;

                    self._bindDistLegendEvents(legendListEl);
                }

                chart.data.labels = items.map(function (i) { return i.icon + ' ' + i.label; });
                chart.data.datasets = [{
                    data: items.map(function (i) { return i.val; }),
                    backgroundColor: items.map(function (i) { return i.color; }),
                    borderWidth: 0,
                    hoverOffset: 10,
                    clip: false,
                }];

                chart.options.plugins.tooltip = Object.assign({}, commonTooltip, {
                    callbacks: {
                        label: function (ctx) {
                            var val = ctx.parsed;
                            var pct = totalOd > 0 ? ((val / totalOd) * 100).toFixed(1) : '0';
                            return ' Tutar: ' + self._currency(val) + ' (%' + pct + ')';
                        }
                    }
                });
            }

            chart.update();
        },

        _bindDistLegendEvents: function (legendListEl) {
            var self = this;
            legendListEl.querySelectorAll('.stat-odeme-legend-item').forEach(function (el) {
                el.addEventListener('mouseenter', function () {
                    var idx = parseInt(this.dataset.idx, 10);
                    if (self.charts.dist) {
                        self.charts.dist.setActiveElements([{ datasetIndex: 0, index: idx }]);
                        self.charts.dist.tooltip.setActiveElements([{ datasetIndex: 0, index: idx }]);
                        self.charts.dist.update();
                    }
                    var nameEl = this.querySelector('.stat-odeme-item-name');
                    if (nameEl && nameEl.scrollWidth > nameEl.clientWidth) {
                        var diff = nameEl.scrollWidth - nameEl.clientWidth;
                        nameEl.style.setProperty('--scroll-dist', '-' + (diff + 4) + 'px');
                        nameEl.classList.add('is-marquee');
                    }
                });
                el.addEventListener('mouseleave', function () {
                    if (self.charts.dist) {
                        self.charts.dist.setActiveElements([]);
                        self.charts.dist.tooltip.setActiveElements([]);
                        self.charts.dist.update();
                    }
                    var nameEl = this.querySelector('.stat-odeme-item-name');
                    if (nameEl) {
                        nameEl.classList.remove('is-marquee');
                        nameEl.style.removeProperty('--scroll-dist');
                    }
                });
            });
        },

        _showDayAccordion: function (dayData) {
            var self = this;
            var accordion = document.getElementById('psr-day-accordion');
            if (!accordion) return;

            var allSatis = (self.lastData && self.lastData.satis_listesi) || [];
            var daySatis = allSatis.filter(function (s) {
                return s.tarih && s.tarih.startsWith(dayData.tarih);
            });

            var isSameDay = self.selectedDayData && self.selectedDayData.tarih === dayData.tarih;
            if (isSameDay && accordion.style.display !== 'none') {
                accordion.style.display = 'none';
                accordion.innerHTML = '';
                self.selectedDayData = null;
                return;
            }

            self.selectedDayData = dayData;
            accordion.style.display = 'block';

            var dateFormatted = dayData.tarih.split('-').reverse().join('.');
            var html = '<div class="psr-accordion-header">';
            html +=   '<span>📅 ' + dateFormatted + ' tarihli satışlar</span>';
            html +=   '<span class="psr-accordion-badge">' + daySatis.length + ' sipariş &bull; ' + dayData.satis_adet + ' adet &bull; ' + self._currency(dayData.satis_ciro) + '</span>';
            html +=   '<button class="psr-accordion-close" id="psr-accordion-close-btn">✕</button>';
            html += '</div>';

            if (daySatis.length === 0) {
                html += '<div class="psr-accordion-body"><p style="color:var(--hk-text-muted);padding:12px;">Bu güne ait satış listesi bulunamadı.</p></div>';
            } else {
                html += '<div class="psr-accordion-body">';
                html += '<table class="gs-tablo psr-day-table">';
                html += '<thead><tr><th>Saat</th><th>Sipariş</th><th>Kasiyer</th><th>Varyasyon</th><th style="text-align:right">Adet</th><th style="text-align:right">Birim</th><th style="text-align:right">Toplam</th></tr></thead>';
                html += '<tbody>';
                daySatis.forEach(function (s) {
                    var saat = s.tarih ? s.tarih.split(' ')[1] : '';
                    html += '<tr>';
                    html +=   '<td>' + self._esc(saat) + '</td>';
                    html +=   '<td><a href="#" class="psr-order-link" data-order-id="' + s.order_id + '">#' + s.order_id + '</a></td>';
                    html +=   '<td>' + self._esc(s.kasiyer) + '</td>';
                    html +=   '<td>' + (s.variation ? '<span class="psr-var-chip">' + self._esc(s.variation) + '</span>' : '—') + '</td>';
                    html +=   '<td style="text-align:right;font-weight:700">' + s.adet + '</td>';
                    html +=   '<td style="text-align:right">' + self._currency(s.birim_fiyat) + '</td>';
                    html +=   '<td style="text-align:right;color:var(--hk-success);font-weight:700">' + self._currency(s.toplam) + '</td>';
                    html += '</tr>';
                });
                html += '</tbody></table>';
                html += '</div>';
            }

            accordion.innerHTML = html;

            document.getElementById('psr-accordion-close-btn').addEventListener('click', function () {
                accordion.style.display = 'none';
                accordion.innerHTML = '';
                self.selectedDayData = null;
            });

            accordion.querySelectorAll('.psr-order-link').forEach(function (link) {
                link.addEventListener('click', function (e) {
                    e.preventDefault();
                    var orderId = this.dataset.orderId;
                    if (HK.ReportsCommon && typeof HK.ReportsCommon.openOrderDetail === 'function') {
                        HK.ReportsCommon.openOrderDetail(orderId);
                    } else {
                        window.open('/wp-admin/post.php?post=' + orderId + '&action=edit', '_blank');
                    }
                });
            });

            accordion.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        },

        _updateProductBar: function (p) {
            var self = this;
            var barInfo = document.getElementById('psr-bar-info');
            var barRelations = document.getElementById('psr-bar-relations');
            if (!barInfo) return;

            var typeLabel = { simple: 'Basit Ürün', variable: 'Varyasyonlu Ürün', variation: 'Varyasyon' };
            var relations = p.relations || {};

            var html = '';

            // Product image
            if (p.image_url) {
                html += '<div class="psr-bar-img-wrap" data-full-src="' + (p.image_full_url_real || p.image_url) + '" title="Resmi Büyüt">';
                html +=   '<img src="' + p.image_url + '" class="psr-bar-img" alt="' + self._esc(p.name) + '">';
                html +=   '<span class="psr-zoom-overlay-small">🔍</span>';
                html += '</div>';
            }

            // Details (Title, SKU, Type Badge)
            html += '<div class="psr-bar-details">';
            html +=   '<div class="psr-bar-name">' + self._esc(p.name) + '</div>';
            html +=   '<div class="psr-bar-meta">';
            html +=     '<span class="psr-sku-chip">SKU: ' + self._esc(p.sku) + '</span>';
            html +=     '<span class="psr-type-badge">' + (typeLabel[p.type] || p.type) + '</span>';

            // Relation meta links in info row
            if (p.type === 'variation' && relations.parent) {
                html += '<span class="psr-parent-link psr-clickable-card" data-sku="' + self._esc(relations.parent.sku) + '" title="Parent Ürüne Git">';
                html +=   '🔗 Parent: ' + self._esc(relations.parent.name) + ' (' + self._esc(relations.parent.sku) + ') ▸';
                html += '</span>';
            }

            html +=   '</div>';
            html += '</div>';

            // Action buttons on the right side of info row (Toggle siblings/children)
            var hasRelationsList = false;
            var relCardsHtml = '';
            var toggleBtnHtml = '';

            if (p.type === 'variation' && relations.siblings && relations.siblings.length > 0) {
                hasRelationsList = true;
                toggleBtnHtml = '<button class="psr-toggle-siblings-btn" id="psr-toggle-siblings">🎨 Diğer Varyasyonlar (' + relations.siblings.length + ') ▾</button>';
                
                relCardsHtml += '<div class="psr-collapse-title">Diğer Varyasyonlar</div>';
                relCardsHtml += '<div class="psr-node-list scrollable-x">';
                relations.siblings.forEach(function (sib) {
                    relCardsHtml += '<div class="psr-node-card psr-clickable-card" data-sku="' + self._esc(sib.sku) + '">';
                    relCardsHtml +=   '<div class="psr-node-img-wrap" data-full-src="' + (sib.image_full_url || sib.image_url) + '">';
                    relCardsHtml +=     '<img src="' + sib.image_url + '" class="psr-node-img">';
                    relCardsHtml +=     '<span class="psr-zoom-overlay-small">🔍</span>';
                    relCardsHtml +=   '</div>';
                    relCardsHtml +=   '<div class="psr-node-info">';
                    relCardsHtml +=     '<span class="psr-node-name">' + self._esc(sib.name) + '</span>';
                    relCardsHtml +=     '<span class="psr-node-sku">SKU: ' + self._esc(sib.sku) + '</span>';
                    relCardsHtml +=   '</div>';
                    relCardsHtml += '</div>';
                });
                relCardsHtml += '</div>';
            } else if (p.type === 'variable' && relations.children && relations.children.length > 0) {
                hasRelationsList = true;
                toggleBtnHtml = '<button class="psr-toggle-siblings-btn" id="psr-toggle-siblings">🎨 Alt Varyasyonlar (' + relations.children.length + ') ▾</button>';

                relCardsHtml += '<div class="psr-collapse-title">Alt Varyasyonlar</div>';
                relCardsHtml += '<div class="psr-node-list scrollable-x">';
                relations.children.forEach(function (child) {
                    relCardsHtml += '<div class="psr-node-card psr-clickable-card" data-sku="' + self._esc(child.sku) + '">';
                    relCardsHtml +=   '<div class="psr-node-img-wrap" data-full-src="' + (child.image_full_url || child.image_url) + '">';
                    relCardsHtml +=     '<img src="' + child.image_url + '" class="psr-node-img">';
                    relCardsHtml +=     '<span class="psr-zoom-overlay-small">🔍</span>';
                    relCardsHtml +=   '</div>';
                    relCardsHtml +=   '<div class="psr-node-info">';
                    relCardsHtml +=     '<span class="psr-node-name">' + self._esc(child.name) + '</span>';
                    relCardsHtml +=     '<span class="psr-node-sku">SKU: ' + self._esc(child.sku) + '</span>';
                    relCardsHtml +=   '</div>';
                    relCardsHtml += '</div>';
                });
                relCardsHtml += '</div>';
            }

            if (toggleBtnHtml) {
                html += '<div class="psr-bar-actions">' + toggleBtnHtml + '</div>';
            }

            barInfo.innerHTML = html;
            barInfo.style.display = 'flex';

            if (barRelations) {
                if (hasRelationsList) {
                    barRelations.innerHTML = relCardsHtml;
                    barRelations.style.display = 'none';
                } else {
                    barRelations.innerHTML = '';
                    barRelations.style.display = 'none';
                }
            }

            self._bindBarEvents();
        },

        _bindBarEvents: function () {
            var self = this;
            var productBar = document.querySelector('.psr-product-bar');
            if (!productBar) return;

            var toggleBtn = productBar.querySelector('#psr-toggle-siblings');
            var relEl = productBar.querySelector('#psr-bar-relations');
            if (toggleBtn && relEl) {
                toggleBtn.addEventListener('click', function () {
                    var isHidden = relEl.style.display === 'none';
                    if (isHidden) {
                        relEl.style.display = 'block';
                        toggleBtn.classList.add('active');
                        toggleBtn.textContent = toggleBtn.textContent.replace('▾', '▴');
                        relEl.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                    } else {
                        relEl.style.display = 'none';
                        toggleBtn.classList.remove('active');
                        toggleBtn.textContent = toggleBtn.textContent.replace('▴', '▾');
                    }
                });
            }

            productBar.querySelectorAll('.psr-clickable-card').forEach(function (card) {
                card.addEventListener('click', function (e) {
                    if (e.target.closest('.psr-node-img-wrap, .psr-bar-img-wrap')) {
                        return;
                    }
                    var clickedSku = this.dataset.sku;
                    if (clickedSku) {
                        var inputEl = document.getElementById('psr-sku-input');
                        if (inputEl) {
                            inputEl.value = clickedSku;
                        }
                        self._loadStats(clickedSku);
                        self._fetchPreview(clickedSku);
                    }
                });
            });

            document.querySelectorAll('.psr-bar-img-wrap, .psr-node-img-wrap').forEach(function (wrap) {
                wrap.addEventListener('click', function (e) {
                    e.stopPropagation();
                    var fullSrc = this.dataset.fullSrc;
                    if (fullSrc && HK.UIRenderer && typeof HK.UIRenderer.openImagePreview === 'function') {
                        HK.UIRenderer.openImagePreview(fullSrc);
                    }
                });
            });
        },

        _renderVarTable: function (vars) {
            var self = this;
            var max = vars[0] ? vars[0].satis_adet : 1;
            var html = '<table class="stat-top-table"><thead><tr>';
            html += '<th>#</th><th>Varyasyon</th><th>SKU</th><th style="text-align:right">Satış Adeti</th><th style="text-align:right">Ciro</th><th style="text-align:right">İade</th>';
            html += '</tr></thead><tbody>';
            vars.forEach(function (v, i) {
                var pct = max > 0 ? Math.round((v.satis_adet / max) * 100) : 0;
                var rankClass = i === 0 ? 'top-1' : (i === 1 ? 'top-2' : (i === 2 ? 'top-3' : ''));
                html += '<tr>';
                html += '<td><span class="stat-rank-badge ' + rankClass + '">' + (i + 1) + '</span></td>';
                html += '<td><div>' + self._esc(v.name) + '</div>';
                html += '<div class="stat-progress-bar-wrap"><div class="stat-progress-bar" style="width:' + pct + '%"></div></div></td>';
                html += '<td style="font-size:11px;color:var(--hk-text-muted)">' + self._esc(v.sku) + '</td>';
                html += '<td style="text-align:right;font-weight:700">' + v.satis_adet + '</td>';
                html += '<td style="text-align:right;color:var(--hk-success);font-weight:700">' + self._currency(v.satis_ciro) + '</td>';
                html += '<td style="text-align:right;color:var(--hk-danger)">' + (v.iade_adet > 0 ? v.iade_adet + ' adet' : '—') + '</td>';
                html += '</tr>';
            });
            html += '</tbody></table>';
            return html;
        },

        _renderSatisTable: function (satis) {
            var self = this;
            var html = '<div class="psr-satis-table-wrap"><table class="gs-tablo psr-satis-table"><thead><tr>';
            html += '<th>Tarih</th><th>Sipariş</th><th>Kasiyer</th><th>Varyasyon</th><th style="text-align:right">Adet</th><th style="text-align:right">Birim</th><th style="text-align:right">Toplam</th>';
            html += '</tr></thead><tbody>';
            satis.forEach(function (s) {
                html += '<tr>';
                html += '<td>' + self._esc(s.tarih_kisa) + '</td>';
                html += '<td><a href="#" class="psr-order-link" data-order-id="' + s.order_id + '">#' + s.order_id + '</a></td>';
                html += '<td>' + self._esc(s.kasiyer) + '</td>';
                html += '<td>' + (s.variation ? '<span class="psr-var-chip">' + self._esc(s.variation) + '</span>' : '—') + '</td>';
                html += '<td style="text-align:right;font-weight:700">' + s.adet + '</td>';
                html += '<td style="text-align:right">' + self._currency(s.birim_fiyat) + '</td>';
                html += '<td style="text-align:right;color:var(--hk-success);font-weight:700">' + self._currency(s.toplam) + '</td>';
                html += '</tr>';
            });
            html += '</tbody></table></div>';

            // Bind order links after render via event delegation on document
            setTimeout(function () {
                document.querySelectorAll('.psr-satis-table .psr-order-link').forEach(function (link) {
                    link.addEventListener('click', function (e) {
                        e.preventDefault();
                        var orderId = this.dataset.orderId;
                        if (HK.ReportsCommon && typeof HK.ReportsCommon.openOrderDetail === 'function') {
                            HK.ReportsCommon.openOrderDetail(orderId);
                        } else {
                            window.open('/wp-admin/post.php?post=' + orderId + '&action=edit', '_blank');
                        }
                    });
                });
            }, 0);

            return html;
        },

        _kpiCard: function (icon, label, value, sub, cls) {
            return '<div class="psr-kpi-card stat-kpi-card ' + (cls || '') + '">'
                + '<span class="stat-kpi-icon">' + icon + '</span>'
                + '<span class="stat-kpi-label">' + label + '</span>'
                + '<span class="stat-kpi-value">' + value + '</span>'
                + '<span class="stat-kpi-sub">' + (sub || '') + '</span>'
                + '</div>';
        },

        _destroyCharts: function () {
            var self = this;
            Object.keys(self.charts).forEach(function (key) {
                if (self.charts[key]) {
                    self.charts[key].destroy();
                    self.charts[key] = null;
                }
            });
            self.charts = {};
        },

        /* -------------------------------------------------
           ANA TREND CHART (SATIŞ & İSKONTO DÖNÜŞÜMLÜ)
        ------------------------------------------------- */
        _initMainTrendChart: function (data, gridColor, tickColor, commonTooltip) {
            var self = this;
            var canvas = document.getElementById('psr-chart-main');
            if (!canvas) return;

            self.charts.main = new Chart(canvas, {
                type: 'line',
                data: { labels: [], datasets: [] },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    scales: {
                        x: { grid: { color: gridColor }, ticks: { color: tickColor } }
                    }
                }
            });

            self._updateMainTrendChart(data, gridColor, tickColor, commonTooltip);
        },

        _updateMainTrendChart: function (data, gridColor, tickColor, commonTooltip) {
            var self = this;
            var chart = self.charts.main;
            if (!chart) return;

            var mode = self.activeTrendMode || 'satis';
            var titleEl = document.getElementById('psr-main-trend-title');
            var badgeEl = document.getElementById('psr-main-trend-badge');
            var hintEl  = document.getElementById('psr-main-trend-hint');

            var trend   = data.gunluk_trend  || [];
            var saatlik = data.saatlik_trend || [];
            var isSingleDay = (trend.length <= 1 && saatlik.length > 0);

            if (mode === 'satis') {
                if (isSingleDay) {
                    if (titleEl) titleEl.innerText = '📈 Saatlik Satış Trendi';
                    if (badgeEl) badgeEl.innerText = 'Saatlik Yoğunluk • 24 Saat';
                    if (hintEl)  hintEl.style.display = 'none';

                    chart.data.labels = saatlik.map(function (s) { return s.saat; });
                    chart.data.datasets = [
                        {
                            label: 'Satış Adeti',
                            data: saatlik.map(function (s) { return s.satis_adet; }),
                            borderColor: '#8b5cf6',
                            backgroundColor: 'rgba(139,92,246,0.12)',
                            fill: false,
                            tension: 0.4,
                            pointBackgroundColor: '#8b5cf6',
                            pointRadius: 4,
                            pointHoverRadius: 6,
                            borderWidth: 2,
                            yAxisID: 'yAdet',
                            hidden: true,
                        },
                        {
                            label: 'Ciro (₺)',
                            data: saatlik.map(function (s) { return s.satis_ciro; }),
                            borderColor: '#3b82f6',
                            backgroundColor: 'rgba(59,130,246,0.10)',
                            fill: true,
                            tension: 0.4,
                            pointBackgroundColor: '#3b82f6',
                            pointRadius: 4,
                            pointHoverRadius: 6,
                            borderWidth: 2.5,
                            yAxisID: 'yCiro',
                        }
                    ];
                } else {
                    if (titleEl) titleEl.innerText = '📈 Günlük Satış Trendi';
                    if (badgeEl) badgeEl.innerText = trend.length + ' gün';
                    if (hintEl) {
                        hintEl.style.display = 'block';
                        hintEl.innerText = '💡 Grafikteki bir noktaya tıklayarak o günün satışlarını inceleyin';
                    }

                    chart.data.labels = trend.map(function (g) { return g.tarih_kisa; });
                    chart.data.datasets = [
                        {
                            label: 'Satış Adeti',
                            data: trend.map(function (g) { return g.satis_adet; }),
                            borderColor: '#8b5cf6',
                            backgroundColor: 'rgba(139,92,246,0.12)',
                            fill: false,
                            tension: 0.4,
                            pointBackgroundColor: '#8b5cf6',
                            pointRadius: 4,
                            pointHoverRadius: 6,
                            borderWidth: 2,
                            yAxisID: 'yAdet',
                            hidden: true,
                        },
                        {
                            label: 'Ciro (₺)',
                            data: trend.map(function (g) { return g.satis_ciro; }),
                            borderColor: '#3b82f6',
                            backgroundColor: 'rgba(59,130,246,0.10)',
                            fill: true,
                            tension: 0.4,
                            pointBackgroundColor: '#3b82f6',
                            pointRadius: 4,
                            pointHoverRadius: 6,
                            borderWidth: 2.5,
                            yAxisID: 'yCiro',
                        }
                    ];
                }

                chart.options.scales = {
                    x: { grid: { color: gridColor }, ticks: { color: tickColor } },
                    yAdet: {
                        type: 'linear',
                        position: 'left',
                        display: 'auto',
                        grid: { color: gridColor },
                        ticks: { color: tickColor, precision: 0 },
                        beginAtZero: true,
                    },
                    yCiro: {
                        type: 'linear',
                        position: 'right',
                        grid: { drawOnChartArea: false },
                        ticks: {
                            color: tickColor,
                            callback: function (v) { return '₺' + self._num(v); }
                        },
                        beginAtZero: true,
                    }
                };

                chart.options.plugins.legend = { labels: { color: tickColor } };
                chart.options.plugins.tooltip = Object.assign({}, commonTooltip, {
                    callbacks: {
                        label: function (ctx) {
                            if (ctx.dataset.yAxisID === 'yCiro') {
                                return ' ₺ ' + self._num(ctx.parsed.y);
                            }
                            return ' ' + ctx.parsed.y + ' adet';
                        }
                    }
                });

                chart.options.onClick = function (evt, elements) {
                    if (!isSingleDay && elements && elements.length > 0) {
                        var idx = elements[0].index;
                        self._showDayAccordion(trend[idx]);
                    }
                };

                var canvasEl = document.getElementById('psr-chart-main');
                if (canvasEl) canvasEl.style.cursor = isSingleDay ? 'default' : 'pointer';

            } else {
                if (isSingleDay) {
                    if (titleEl) titleEl.innerText = '✂️ İskonto Analizi (Saatlik)';
                    if (badgeEl) badgeEl.innerText = 'Saatlik Yoğunluk • Etiket vs. Gerçek Satış';
                    if (hintEl) {
                        hintEl.style.display = 'block';
                        hintEl.innerText = '💡 Bu ürün için uygulanan liste/etiket fiyatı ve fiili satış tutarı saatlik karşılaştırması';
                    }

                    chart.data.labels = saatlik.map(function (s) { return s.saat; });
                    chart.data.datasets = [
                        {
                            label: 'Etiket Toplamı (₺)',
                            data: saatlik.map(function (s) { return s.etiket_ciro; }),
                            borderColor: '#94a3b8',
                            backgroundColor: 'rgba(148,163,184,0.15)',
                            fill: false,
                            tension: 0.4,
                            borderWidth: 2,
                            borderDash: [5, 3],
                            pointRadius: 4,
                        },
                        {
                            label: 'Gerçek Satış (₺)',
                            data: saatlik.map(function (s) { return s.satis_ciro; }),
                            borderColor: '#10b981',
                            backgroundColor: 'rgba(16,185,129,0.18)',
                            fill: '-1',
                            tension: 0.4,
                            borderWidth: 2.5,
                            pointRadius: 4,
                            pointHoverRadius: 6,
                        }
                    ];
                } else {
                    if (titleEl) titleEl.innerText = '✂️ İskonto Analizi';
                    if (badgeEl) badgeEl.innerText = trend.length + ' gün • Etiket vs. Gerçek Satış';
                    if (hintEl) {
                        hintEl.style.display = 'block';
                        hintEl.innerText = '💡 Bu ürün için uygulanan liste/etiket fiyatı ve fiili satış tutarı karşılaştırması';
                    }

                    chart.data.labels = trend.map(function (g) { return g.tarih_kisa; });
                    chart.data.datasets = [
                        {
                            label: 'Etiket Toplamı (₺)',
                            data: trend.map(function (g) { return g.etiket_ciro; }),
                            borderColor: '#94a3b8',
                            backgroundColor: 'rgba(148,163,184,0.15)',
                            fill: false,
                            tension: 0.4,
                            borderWidth: 2,
                            borderDash: [5, 3],
                            pointRadius: 4,
                        },
                        {
                            label: 'Gerçek Satış (₺)',
                            data: trend.map(function (g) { return g.satis_ciro; }),
                            borderColor: '#10b981',
                            backgroundColor: 'rgba(16,185,129,0.18)',
                            fill: '-1',
                            tension: 0.4,
                            borderWidth: 2.5,
                            pointRadius: 4,
                            pointHoverRadius: 6,
                        }
                    ];
                }

                chart.options.scales = {
                    x: { grid: { color: gridColor }, ticks: { color: tickColor } },
                    y: {
                        type: 'linear',
                        grid: { color: gridColor },
                        ticks: {
                            color: tickColor,
                            callback: function (v) { return '₺' + self._num(v); }
                        },
                        beginAtZero: true,
                    }
                };

                chart.options.plugins.legend = {
                    display: true,
                    position: 'top',
                    labels: { color: tickColor, font: { size: 12 } }
                };

                chart.options.plugins.tooltip = Object.assign({}, commonTooltip, {
                    callbacks: {
                        label: function (ctx) {
                            return ' ' + ctx.dataset.label + ': ₺ ' + self._num(ctx.parsed.y);
                        },
                        afterBody: function (items) {
                            if (!items || !items.length) return [];
                            var idx = items[0].dataIndex;
                            var point = isSingleDay ? saatlik[idx] : trend[idx];
                            if (!point) return [];
                            var diff = point.iskonto_tutar || 0;
                            var pct = point.etiket_ciro > 0 ? ((diff / point.etiket_ciro) * 100).toFixed(1) : '0';
                            return [
                                '-----------------------',
                                '✂️ Uygulanan İskonto: ₺ ' + self._num(diff) + ' (%' + pct + ' indirim)'
                            ];
                        }
                    }
                });

                chart.options.onClick = null;
                var canvasEl = document.getElementById('psr-chart-main');
                if (canvasEl) canvasEl.style.cursor = 'default';
            }

            chart.update();
        },

        _currency: function (v) {
            var n = parseFloat(v) || 0;
            return '₺ ' + n.toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        },

        _num: function (v) {
            var n = parseFloat(v) || 0;
            return n.toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        },

        _today: function () {
            return new Date().toISOString().split('T')[0];
        },

        _esc: function (s) {
            return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }
    };

    HK.ProductStatsReport.init();

})(window.HizliKasa);
