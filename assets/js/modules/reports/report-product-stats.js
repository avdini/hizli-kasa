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
                    onActivate: function () { self._renderShell(); },
                    hasDateFilter: true,
                    hasSearch: false,
                    description: 'SKU bazlı satış, iade ve kâr analizi'
                });
            }

            document.addEventListener('hkThemeChanged', function () {
                if (self.lastData) {
                    self._destroyCharts();
                    self._renderCharts(self.lastData);
                }
            });
        },

        _renderShell: function () {
            var self = this;
            var panel = document.getElementById('rapor-urun-istatistik');
            if (!panel) return;

            panel.innerHTML = [
                '<div class="psr-wrap">',
                  '<div class="psr-search-bar">',
                    '<div class="psr-sku-field">',
                      '<label class="psr-label">SKU Girin</label>',
                      '<div class="psr-input-row">',
                        '<input type="text" id="psr-sku-input" class="hk-input psr-sku-input" placeholder="Örn: ABC-001" autocomplete="off">',
                        '<button id="psr-sorgula-btn" class="hk-btn-primary psr-sorgula-btn">Analiz Et</button>',
                      '</div>',
                      '<div id="psr-product-preview" class="psr-product-preview" style="display:none;"></div>',
                    '</div>',
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

            if (self.lastData) {
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
                      '<span class="psr-preview-icon">✅</span>',
                      '<div class="psr-preview-info">',
                        '<strong>' + self._esc(p.name) + '</strong>',
                        '<span class="psr-preview-meta">SKU: ' + self._esc(p.sku) + ' &bull; ' + (typeLabel[p.type] || p.type) + '</span>',
                      '</div>',
                    '</div>'
                ].join('');
            })
            .catch(function () {
                previewEl.innerHTML = '<span class="psr-preview-notfound">Ürün önizlemesi alınamadı.</span>';
            });
        },

        _loadStats: function (sku) {
            var self = this;
            var dashboard = document.getElementById('psr-dashboard');
            if (!dashboard) return;

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

            var html = '';

            html += '<div class="psr-product-header">';
            html +=   '<div class="psr-product-title">';
            html +=     '<h3>' + self._esc(p.name) + '</h3>';
            html +=     '<span class="psr-type-badge">' + (typeLabel[p.type] || p.type) + '</span>';
            html +=   '</div>';
            html +=   '<span class="psr-sku-chip">SKU: ' + self._esc(p.sku) + '</span>';
            html += '</div>';

            if (!kpi.toplam_satis_adet && !kpi.toplam_iade_adet) {
                html += '<div class="psr-empty"><span>📦</span><p>Seçili tarih aralığında bu ürün için kayıt bulunamadı.</p></div>';
                dashboard.innerHTML = html;
                return;
            }

            // KPI Grid
            html += '<div class="psr-kpi-grid">';
            html += self._kpiCard('📦', 'TOPLAM SATIŞ', kpi.toplam_satis_adet + ' adet', self._currency(kpi.toplam_satis_ciro), 'kpi-satis');
            html += self._kpiCard('↩️', 'TOPLAM İADE', kpi.toplam_iade_adet + ' adet', self._currency(kpi.toplam_iade_tutar), 'kpi-iade');
            html += self._kpiCard('📉', 'NET CİRO', self._currency(kpi.net_ciro), 'Ciro – İade', 'kpi-net');

            if (kpi.brut_kar !== null && kpi.brut_kar !== undefined) {
                var karClass = kpi.brut_kar >= 0 ? 'kpi-kar-pozitif' : 'kpi-kar-negatif';
                html += self._kpiCard('💰', 'BRÜT KÂR', self._currency(kpi.brut_kar), 'Maliyet: ' + self._currency(kpi.maliyet_birim) + '/adet', karClass);
            } else {
                html += self._kpiCard('💰', 'BRÜT KÂR', '—', 'Maliyet verisi girilmemiş', 'kpi-kar-yok');
            }
            html += '</div>';

            if (kpi.maliyet_kaynak && kpi.maliyet_kaynak !== 'yok') {
                var kaynakLabel = kpi.maliyet_kaynak === 'wc_cog' ? 'WooCommerce COG eklentisi' : 'HK Alım Siparişleri (ort. maliyet)';
                html += '<div class="psr-cost-source-note">📌 Maliyet kaynağı: <strong>' + kaynakLabel + '</strong></div>';
            }

            // Günlük trend grafiği
            if (trend.length > 0) {
                html += '<div class="psr-chart-wrap psr-chart-full">';
                html +=   '<div class="psr-chart-header"><h4 class="stat-chart-title">📈 Günlük Satış Trendi</h4><span class="stat-chart-badge">' + trend.length + ' gün</span></div>';
                html +=   '<div class="psr-chart-hint">💡 Grafikteki bir noktaya tıklayarak o günün satışlarını inceleyin</div>';
                html +=   '<div class="psr-chart-body"><canvas id="psr-chart-trend" height="90"></canvas></div>';
                html +=   '<div id="psr-day-accordion" class="psr-day-accordion" style="display:none;"></div>';
                html += '</div>';
            }

            // Satış vs İade Bar Chart
            if (trend.length > 0) {
                html += '<div class="psr-charts-row">';
                html +=   '<div class="psr-chart-wrap">';
                html +=     '<div class="psr-chart-header"><h4 class="stat-chart-title">📊 Satış vs İade (Adet)</h4></div>';
                html +=     '<div class="psr-chart-body"><canvas id="psr-chart-compare" height="160"></canvas></div>';
                html +=   '</div>';

                if (vars.length > 1) {
                    html += '<div class="psr-chart-wrap">';
                    html +=   '<div class="psr-chart-header"><h4 class="stat-chart-title">🎨 Varyasyon Dağılımı</h4><span class="stat-chart-badge">' + vars.length + ' varyasyon</span></div>';
                    html +=   '<div class="psr-chart-body stat-doughnut-wrap"><canvas id="psr-chart-vars" height="220"></canvas></div>';
                    html += '</div>';
                }
                html += '</div>';
            }

            // Varyasyon tablosu (parent ürünse)
            if (vars.length > 1) {
                html += '<div class="stat-chart-card">';
                html +=   '<div class="psr-chart-header"><h4 class="stat-chart-title">📋 Varyasyon Detay Tablosu</h4></div>';
                html +=   self._renderVarTable(vars);
                html += '</div>';
            }

            // Satış Listesi
            if (satis.length > 0) {
                html += '<div class="stat-chart-card psr-satis-listesi-wrap">';
                html +=   '<div class="psr-chart-header"><h4 class="stat-chart-title">🧾 Satış Kayıtları</h4><span class="stat-chart-badge">' + satis.length + ' kayıt</span></div>';
                html +=   self._renderSatisTable(satis);
                html += '</div>';
            }

            dashboard.innerHTML = html;

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

            // Trend Chart
            if (trend.length > 0 && document.getElementById('psr-chart-trend')) {
                self.charts.trend = new Chart(document.getElementById('psr-chart-trend'), {
                    type: 'line',
                    data: {
                        labels: trend.map(function (g) { return g.tarih_kisa; }),
                        datasets: [
                            {
                                label: 'Satış Adeti',
                                data: trend.map(function (g) { return g.satis_adet; }),
                                borderColor: '#10b981',
                                backgroundColor: 'rgba(16,185,129,0.12)',
                                fill: true,
                                tension: 0.4,
                                pointBackgroundColor: '#10b981',
                                pointRadius: 5,
                                pointHoverRadius: 8,
                                borderWidth: 2.5,
                                yAxisID: 'yAdet',
                            },
                            {
                                label: 'Ciro (₺)',
                                data: trend.map(function (g) { return g.satis_ciro; }),
                                borderColor: '#3b82f6',
                                backgroundColor: 'transparent',
                                fill: false,
                                tension: 0.4,
                                pointBackgroundColor: '#3b82f6',
                                pointRadius: 5,
                                pointHoverRadius: 8,
                                borderWidth: 2,
                                yAxisID: 'yCiro',
                                borderDash: [5, 3],
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        interaction: { mode: 'index', intersect: false },
                        onClick: function (evt, elements) {
                            if (elements && elements.length > 0) {
                                var idx = elements[0].index;
                                self._showDayAccordion(trend[idx]);
                            }
                        },
                        plugins: {
                            legend: { labels: { color: tickColor } },
                            tooltip: Object.assign({}, commonTooltip, {
                                callbacks: {
                                    label: function (ctx) {
                                        if (ctx.dataset.yAxisID === 'yCiro') {
                                            return ' ₺ ' + self._num(ctx.parsed.y);
                                        }
                                        return ' ' + ctx.parsed.y + ' adet';
                                    }
                                }
                            })
                        },
                        scales: {
                            x: { grid: { color: gridColor }, ticks: { color: tickColor } },
                            yAdet: {
                                type: 'linear',
                                position: 'left',
                                grid: { color: gridColor },
                                ticks: { color: tickColor, stepSize: 1 },
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
                        }
                    }
                });

                // pointer cursor on hover
                document.getElementById('psr-chart-trend').style.cursor = 'pointer';
            }

            // Compare Chart
            if (trend.length > 0 && document.getElementById('psr-chart-compare')) {
                self.charts.compare = new Chart(document.getElementById('psr-chart-compare'), {
                    type: 'bar',
                    data: {
                        labels: trend.map(function (g) { return g.tarih_kisa; }),
                        datasets: [
                            {
                                label: 'Satış',
                                data: trend.map(function (g) { return g.satis_adet; }),
                                backgroundColor: 'rgba(16,185,129,0.75)',
                                borderRadius: 5,
                            },
                            {
                                label: 'İade',
                                data: trend.map(function (g) { return g.iade_adet; }),
                                backgroundColor: 'rgba(239,68,68,0.75)',
                                borderRadius: 5,
                            }
                        ]
                    },
                    options: {
                        responsive: true,
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
                            y: { grid: { color: gridColor }, ticks: { color: tickColor, stepSize: 1 }, beginAtZero: true }
                        }
                    }
                });
            }

            // Variation Doughnut
            if (vars.length > 1 && document.getElementById('psr-chart-vars')) {
                var varColors = ['#3b82f6','#10b981','#f59e0b','#8b5cf6','#ef4444','#06b6d4','#ec4899','#84cc16'];
                self.charts.vars = new Chart(document.getElementById('psr-chart-vars'), {
                    type: 'doughnut',
                    data: {
                        labels: vars.map(function (v) { return v.name; }),
                        datasets: [{
                            data: vars.map(function (v) { return v.satis_adet; }),
                            backgroundColor: vars.map(function (_, i) { return varColors[i % varColors.length]; }),
                            borderWidth: 0,
                            hoverOffset: 10,
                        }]
                    },
                    options: {
                        responsive: true,
                        cutout: '65%',
                        plugins: {
                            legend: { position: 'bottom', labels: { color: tickColor, padding: 12, font: { size: 11 } } },
                            tooltip: Object.assign({}, commonTooltip, {
                                callbacks: {
                                    label: function (ctx) { return ' ' + ctx.parsed + ' adet'; }
                                }
                            })
                        }
                    }
                });
            }
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
