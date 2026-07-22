/**
 * Hızlı Kasa - İstatistik Dashboardu JS Modülü
 *
 * Chart.js ile interaktif dashboard grafikleri.
 * hkTabLoaded ve hkActiveDepoChanged event'lerine reaktif.
 *
 * @package HizliKasa
 */

(function (HK) {
    'use strict';

    HK.StatisticsDashboard = {

        charts: {},          // Aktif Chart.js instance'ları
        lastData: null,      // Son çekilen veri (tema değişiminde yeniden render için)
        initialized: false,

        /* -------------------------------------------------
           INIT
        ------------------------------------------------- */
        init: function () {
            var self = this;

            // Raporu Hub'a Kaydet
            if (HK.ReportHub) {
                HK.ReportHub.registerReport({
                    id: 'ozet-istatistik',
                    categoryId: 'istatistik',
                    title: 'İstatistik Dashboardu',
                    icon: '📈',
                    panelId: 'rapor-ozet-istatistik',
                    onActivate: function() { self.load(); },
                    hasDateFilter: true,
                    hasSearch: false,
                    order: 1
                });
            }

            // Depo değişince ve rapor sekmesi açıksa yenile
            document.addEventListener('hkActiveDepoChanged', function () {
                if (HK.ReportHub && HK.ReportHub.activeCategory === 'istatistik' && HK.ReportHub.activeReport === 'ozet-istatistik') {
                    self.load();
                }
            });

            // Tema değişiminde grafikleri yeniden çiz
            document.addEventListener('hkThemeChanged', function () {
                if (self.lastData) {
                    self._destroyCharts();
                    self._renderCharts(self.lastData);
                }
            });
        },

        /* -------------------------------------------------
           VERİYİ ÇEK
        ------------------------------------------------- */
        load: function () {
            var self = this;
            var panel = document.getElementById('rapor-ozet-istatistik');
            if (!panel) return;

            var dateStart = (document.getElementById('rhub-tarih-bas') || {}).value || self._today();
            var dateEnd   = (document.getElementById('rhub-tarih-bit') || {}).value || self._today();
            var depoId    = HK.DepoManager ? HK.DepoManager.getActiveDepo() : 0;

            self._showLoading(panel);

            var url = kasaAyar.rootApiUrl + 'hizli-kasa/v1/statistics/summary'
                + '?date_start=' + dateStart
                + '&date_end='   + dateEnd
                + '&depo_id='    + depoId;

            fetch(url, { headers: { 'X-WP-Nonce': kasaAyar.nonce } })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    self.lastData = data;
                    self._renderDashboard(panel, data);
                })
                .catch(function (err) {
                    console.error('HK.StatisticsDashboard: Fetch error', err);
                    panel.innerHTML = '<div class="stat-empty-state"><span class="stat-empty-icon">⚠️</span><p>Veriler yüklenemedi. Konsola bakınız.</p></div>';
                });
        },

        /* -------------------------------------------------
           DASHBOARD HTML'İ OLUŞTUR
        ------------------------------------------------- */
        _renderDashboard: function (panel, data) {
            var self = this;
            self._destroyCharts();

            var kpi     = data.kpi     || {};
            var odeme   = data.odeme_dagilimi || {};
            var saatlik = data.saatlik_dagilim || [];
            var gunluk  = data.gunluk_trend   || [];
            var kasiy   = data.kasiyerler     || [];
            var urunler = data.top_urunler    || [];

            var html = '<div class="stat-dashboard-wrap">';

            // KPI Kartları
            html += '<div class="stat-kpi-grid">';
            html += self._kpiCard('💰', 'TOPLAM CİRO',    self._currency(kpi.toplam_ciro),  kpi.siparis_sayisi + ' sipariş', 'kpi-ciro');
            html += self._kpiCard('🏦', 'NET CİRO',       self._currency(kpi.net_ciro),      'İade ve masraf düşüldü', 'kpi-net');
            html += self._kpiCard('↩️', 'TOPLAM İADE',    self._currency(kpi.toplam_iade),   kpi.iade_sayisi + ' iade', 'kpi-iade');
            html += self._kpiCard('🧾', 'SİPARİŞ SAYISI', kpi.siparis_sayisi + ' adet',      'Brüt satış adedi', 'kpi-siparis');
            html += '</div>';

            // Veri yoksa mesaj göster
            if (!kpi.siparis_sayisi && !kpi.iade_sayisi) {
                html += '<div class="stat-empty-state"><span class="stat-empty-icon">📊</span><p>Seçili tarih aralığında kayıt bulunamadı.</p></div>';
                html += '</div>';
                panel.innerHTML = html;
                return;
            }

            // Grafik Grid
            html += '<div class="stat-charts-grid">';

            // Günlük trend (tek satır — tam genişlik)
            if (gunluk.length > 1) {
                html += '<div class="stat-chart-card stat-chart-full">';
                html += '<div class="stat-chart-header"><h4 class="stat-chart-title">📈 Günlük Satış Trendi</h4><span class="stat-chart-badge">' + gunluk.length + ' gün • Detay için noktaya tıklayın</span></div>';
                html += '<div class="stat-chart-body"><canvas id="stat-chart-gunluk" height="80"></canvas></div>';
                html += '</div>';
            }

            // Saatlik yoğunluk
            html += '<div class="stat-chart-card">';
            html += '<div class="stat-chart-header"><h4 class="stat-chart-title">🕐 Saatlik Yoğunluk</h4><span class="stat-chart-badge">0–23 saat</span></div>';
            html += '<div class="stat-chart-body"><canvas id="stat-chart-saatlik" height="160"></canvas></div>';
            html += '</div>';

            // Ödeme dağılımı doughnut
            html += '<div class="stat-chart-card">';
            html += '<div class="stat-chart-header"><h4 class="stat-chart-title">💳 Ödeme Dağılımı</h4></div>';
            html += '<div class="stat-chart-body stat-doughnut-wrap"><canvas id="stat-chart-odeme" height="220"></canvas></div>';
            html += '</div>';

            // Kasiyer performansı (sadece birden fazla varsa)
            if (kasiy.length > 0) {
                html += '<div class="stat-chart-card">';
                html += '<div class="stat-chart-header"><h4 class="stat-chart-title">👤 Kasiyer Performansı</h4><span class="stat-chart-badge">' + kasiy.length + ' kasiyer</span></div>';
                html += '<div class="stat-chart-body"><canvas id="stat-chart-kasiyer" height="180"></canvas></div>';
                html += '</div>';
            }

            html += '</div>'; // .stat-charts-grid

            // Top 10 Ürün tablosu
            if (urunler.length > 0) {
                html += '<div class="stat-chart-card">';
                html += '<div class="stat-chart-header"><h4 class="stat-chart-title">🏆 En Çok Satan Ürünler</h4><span class="stat-chart-badge">Top ' + urunler.length + '</span></div>';
                html += self._renderTopTable(urunler);
                html += '</div>';
            }

            html += '</div>'; // .stat-dashboard-wrap
            panel.innerHTML = html;

            // Accordion ve Analiz buton dinleyicileri
            panel.querySelectorAll('.stat-var-toggle-btn').forEach(function (btn) {
                btn.addEventListener('click', function (e) {
                    e.stopPropagation();
                    var targetId = this.dataset.target;
                    var row = document.getElementById(targetId);
                    if (row) {
                        var isExpanded = row.style.display !== 'none';
                        row.style.display = isExpanded ? 'none' : 'table-row';
                        this.classList.toggle('active', !isExpanded);
                    }
                });
            });

            panel.querySelectorAll('.stat-analyze-btn').forEach(function (btn) {
                btn.addEventListener('click', function (e) {
                    e.stopPropagation();
                    var sku = this.dataset.sku;
                    if (sku && HK.ProductStatsReport) {
                        HK.ProductStatsReport.analyzeSKU(sku);
                    }
                });
            });

            // Chart.js mevcut mu?
            if (typeof Chart === 'undefined') {
                console.warn('HK.StatisticsDashboard: Chart.js yüklü değil.');
                return;
            }

            var isDark = document.getElementById('hizli-kasa-app') &&
                document.getElementById('hizli-kasa-app').classList.contains('theme-dark');

            var gridColor  = isDark ? 'rgba(255,255,255,0.07)' : 'rgba(0,0,0,0.06)';
            var tickColor  = isDark ? '#94a3b8' : '#64748b';
            var tooltipBg  = isDark ? '#1e293b' : '#ffffff';
            var tooltipTxt = isDark ? '#f1f5f9' : '#1e293b';

            Chart.defaults.color = tickColor;
            Chart.defaults.font.family = "'Inter', 'Segoe UI', system-ui, sans-serif";
            Chart.defaults.font.size   = 12;

            var commonTooltip = {
                backgroundColor: tooltipBg,
                titleColor: tooltipTxt,
                bodyColor: tickColor,
                borderColor: isDark ? '#334155' : '#e2e8f0',
                borderWidth: 1,
                padding: 12,
                cornerRadius: 8,
            };

            // --- Günlük trend line chart ---
            if (gunluk.length > 1 && document.getElementById('stat-chart-gunluk')) {
                self.charts.gunluk = new Chart(document.getElementById('stat-chart-gunluk'), {
                    type: 'line',
                    data: {
                        labels: gunluk.map(function (g) { return g.tarih_kisa; }),
                        datasets: [{
                            label: 'Net Ciro (₺)',
                            data: gunluk.map(function (g) { return g.toplam; }),
                            borderColor: '#3b82f6',
                            backgroundColor: 'rgba(59,130,246,0.10)',
                            fill: true,
                            tension: 0.4,
                            pointBackgroundColor: '#3b82f6',
                            pointRadius: 4,
                            pointHoverRadius: 6,
                            borderWidth: 2.5,
                        }]
                    },
                    options: {
                        responsive: true,
                        interaction: { mode: 'nearest', intersect: true },
                        onHover: function (evt, elements) {
                            if (evt && evt.chart && evt.chart.canvas) {
                                evt.chart.canvas.style.cursor = (elements && elements.length) ? 'pointer' : 'default';
                            }
                        },
                        onClick: function (evt, elements) {
                            if (elements && elements.length > 0) {
                                var index = elements[0].index;
                                var item = gunluk[index];
                                if (item && item.tarih) {
                                    self._showDayDetails(item.tarih);
                                }
                            }
                        },
                        plugins: {
                            legend: { display: false },
                            tooltip: Object.assign({}, commonTooltip, {
                                callbacks: {
                                    label: function (ctx) { return ' ₺ ' + self._num(ctx.parsed.y); }
                                }
                            })
                        },
                        scales: {
                            x: { grid: { color: gridColor }, ticks: { color: tickColor } },
                            y: {
                                grid: { color: gridColor },
                                ticks: {
                                    color: tickColor,
                                    callback: function (v) { return '₺' + self._num(v); }
                                }
                            }
                        }
                    }
                });
            }

            // --- Saatlik yoğunluk bar chart ---
            if (document.getElementById('stat-chart-saatlik')) {
                var saatCounts = saatlik.map(function (s) { return s.count; });
                var maxSaat    = Math.max.apply(null, saatCounts) || 1;
                var barColors  = saatCounts.map(function (c) {
                    var alpha = 0.3 + (c / maxSaat) * 0.7;
                    return 'rgba(59,130,246,' + alpha.toFixed(2) + ')';
                });

                self.charts.saatlik = new Chart(document.getElementById('stat-chart-saatlik'), {
                    type: 'bar',
                    data: {
                        labels: saatlik.map(function (s) { return s.saat; }),
                        datasets: [{
                            label: 'Sipariş',
                            data: saatCounts,
                            backgroundColor: barColors,
                            borderColor: 'transparent',
                            borderRadius: 6,
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: { display: false },
                            tooltip: Object.assign({}, commonTooltip, {
                                callbacks: {
                                    label: function (ctx) { return ' ' + ctx.parsed.y + ' sipariş'; }
                                }
                            })
                        },
                        scales: {
                            x: { grid: { display: false }, ticks: { color: tickColor, maxTicksLimit: 12 } },
                            y: {
                                grid: { color: gridColor },
                                ticks: { color: tickColor, stepSize: 1 },
                                beginAtZero: true,
                            }
                        }
                    }
                });
            }

            // --- Ödeme dağılımı doughnut ---
            if (document.getElementById('stat-chart-odeme')) {
                var odemeLabels = ['💵 Nakit', '💳 Kart', '🏦 IBAN'];
                var odemeData   = [odeme.nakit || 0, odeme.kart || 0, odeme.iban || 0];
                var odemeColors = ['#10b981', '#3b82f6', '#8b5cf6'];

                self.charts.odeme = new Chart(document.getElementById('stat-chart-odeme'), {
                    type: 'doughnut',
                    data: {
                        labels: odemeLabels,
                        datasets: [{
                            data: odemeData,
                            backgroundColor: odemeColors,
                            borderWidth: 0,
                            hoverOffset: 10,
                        }]
                    },
                    options: {
                        responsive: true,
                        cutout: '65%',
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: { color: tickColor, padding: 16, font: { size: 12 } }
                            },
                            tooltip: Object.assign({}, commonTooltip, {
                                callbacks: {
                                    label: function (ctx) { return ' ₺ ' + self._num(ctx.parsed); }
                                }
                            })
                        }
                    }
                });
            }

            // --- Kasiyer performansı bar chart ---
            if (kasiy.length > 0 && document.getElementById('stat-chart-kasiyer')) {
                self.charts.kasiyer = new Chart(document.getElementById('stat-chart-kasiyer'), {
                    type: 'bar',
                    data: {
                        labels: kasiy.map(function (k) { return k.isim; }),
                        datasets: [
                            {
                                label: 'Sipariş',
                                data: kasiy.map(function (k) { return k.siparis_sayisi; }),
                                backgroundColor: 'rgba(139,92,246,0.75)',
                                borderRadius: 6,
                                yAxisID: 'ySiparis',
                            },
                            {
                                label: 'Ciro (₺)',
                                data: kasiy.map(function (k) { return k.toplam; }),
                                backgroundColor: 'rgba(59,130,246,0.75)',
                                borderRadius: 6,
                                yAxisID: 'yCiro',
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
                                    label: function (ctx) {
                                        if (ctx.dataset.yAxisID === 'yCiro') {
                                            return ' ₺ ' + self._num(ctx.parsed.y);
                                        }
                                        return ' ' + ctx.parsed.y + ' sipariş';
                                    }
                                }
                            })
                        },
                        scales: {
                            x: { grid: { display: false }, ticks: { color: tickColor } },
                            ySiparis: {
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
            }
        },

        /* -------------------------------------------------
           TOP ÜRÜNLER TABLOSU
        ------------------------------------------------- */
        _renderTopTable: function (urunler) {
            var self = this;
            var maxQty = urunler[0] ? urunler[0].qty : 1;
            var html = '<table class="stat-top-table"><thead><tr>'
                + '<th>#</th><th>Ürün</th><th style="text-align:right">Adet</th><th style="text-align:right">Ciro / Analiz</th>'
                + '</tr></thead><tbody>';

            urunler.forEach(function (u, i) {
                var rankClass = i === 0 ? 'top-1' : (i === 1 ? 'top-2' : (i === 2 ? 'top-3' : ''));
                var pct = maxQty > 0 ? Math.round((u.qty / maxQty) * 100) : 0;
                var hasVars = u.variations && u.variations.length > 0;
                var varRowId = 'stat-var-row-' + i;
                var mainSku = u.sku || u.id || u.name;

                html += '<tr>';
                html += '<td><span class="stat-rank-badge ' + rankClass + '">' + (i + 1) + '</span></td>';
                html += '<td>';
                html += '<div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">';
                html += '<span style="font-weight:600;">' + self._esc(u.name) + '</span>';
                if (hasVars) {
                    html += '<button class="stat-var-toggle-btn" data-target="' + varRowId + '">▼ ' + u.variations.length + ' Varyasyon</button>';
                }
                html += '</div>';
                if (u.sku) {
                    html += '<div style="font-size:11px;color:var(--hk-text-muted)">SKU: ' + self._esc(u.sku) + '</div>';
                }
                html += '<div class="stat-progress-bar-wrap"><div class="stat-progress-bar" style="width:' + pct + '%"></div></div>';
                html += '</td>';
                html += '<td style="text-align:right;font-weight:700">' + u.qty + '</td>';
                html += '<td style="text-align:right">';
                html += '<div style="color:var(--hk-success);font-weight:700;margin-bottom:3px;">₺ ' + self._num(u.total) + '</div>';
                html += '<button class="stat-analyze-btn" data-sku="' + self._esc(mainSku) + '" title="Ürün İstatistiğini İncele">🔍 Analiz</button>';
                html += '</td>';
                html += '</tr>';

                if (hasVars) {
                    html += '<tr id="' + varRowId + '" class="stat-var-row" style="display:none;">';
                    html += '<td colspan="4" style="padding:0;background:rgba(0,0,0,0.02);">';
                    html += '<div class="stat-var-wrap">';
                    html += '<table class="stat-var-table"><thead><tr>';
                    html += '<th>Varyasyon</th><th>SKU</th><th style="text-align:right">Adet</th><th style="text-align:right">Ciro</th><th></th>';
                    html += '</tr></thead><tbody>';

                    u.variations.forEach(function (v) {
                        var varSku = v.sku || v.id || v.name;
                        html += '<tr>';
                        html += '<td><span class="stat-var-name">↳ ' + self._esc(v.name) + '</span></td>';
                        html += '<td><span class="stat-var-sku">' + (v.sku ? self._esc(v.sku) : '-') + '</span></td>';
                        html += '<td style="text-align:right;font-weight:600;">' + v.qty + '</td>';
                        html += '<td style="text-align:right;color:var(--hk-success);font-weight:600;">₺ ' + self._num(v.total) + '</td>';
                        html += '<td style="text-align:right;"><button class="stat-analyze-btn stat-analyze-sub" data-sku="' + self._esc(varSku) + '" title="Varyasyon İstatistiğini İncele">🔍</button></td>';
                        html += '</tr>';
                    });

                    html += '</tbody></table>';
                    html += '</div>';
                    html += '</td>';
                    html += '</tr>';
                }
            });

            html += '</tbody></table>';
            return html;
        },

        /* -------------------------------------------------
           KPI KART HTML
        ------------------------------------------------- */
        _kpiCard: function (icon, label, value, sub, cls) {
            return '<div class="stat-kpi-card ' + (cls || '') + '">'
                + '<span class="stat-kpi-icon">' + icon + '</span>'
                + '<span class="stat-kpi-label">' + label + '</span>'
                + '<span class="stat-kpi-value">' + value + '</span>'
                + '<span class="stat-kpi-sub">' + (sub || '') + '</span>'
                + '</div>';
        },

        /* -------------------------------------------------
           YÜKLENİYOR ANIMASYONU
        ------------------------------------------------- */
        _showLoading: function (panel) {
            panel.innerHTML = '<div class="stat-loading"><div class="stat-spinner"></div><p>İstatistikler hazırlanıyor...</p></div>';
        },

        /* -------------------------------------------------
           CHART'LARI TEMİZLE
        ------------------------------------------------- */
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
           YARDIMCILAR
        ------------------------------------------------- */
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

        _showDayDetails: function (targetDate) {
            if (!targetDate || !HK.ReportHub) return;

            var elStart = document.getElementById('rhub-tarih-bas');
            var elEnd   = document.getElementById('rhub-tarih-bit');
            if (elStart) elStart.value = targetDate;
            if (elEnd)   elEnd.value = targetDate;

            if (HK.ReportHub.history) {
                HK.ReportHub.history.push({
                    view: 'report',
                    catId: 'istatistik',
                    repId: 'ozet-istatistik'
                });
            }

            HK.ReportHub.kategoriAc('satis', 'tum-siparisler');
        },

        _esc: function (s) {
            return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }
    };

    HK.StatisticsDashboard.init();

})(window.HizliKasa);
