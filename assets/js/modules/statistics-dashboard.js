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
            var register = function () {
                if (HK.ReportHub) {
                    HK.ReportHub.registerReport({
                        id: 'ozet-istatistik',
                        categoryId: 'istatistik',
                        title: 'İstatistik Dashboardu',
                        icon: '📈',
                        panelId: 'rapor-ozet-istatistik',
                        onActivate: function () { self.load(); },
                        hasDateFilter: true,
                        hasSearch: false,
                        order: 1
                    });
                }
            };
            register();
            document.addEventListener('hkReportHubReady', register);

            // Depo değişince ve rapor sekmesi açıksa yenile
            document.addEventListener('hkActiveDepoChanged', function () {
                if (HK.ReportHub && HK.ReportHub.activeCategory === 'istatistik' && HK.ReportHub.activeReport === 'ozet-istatistik') {
                    self.load();
                }
            });

            // Özet Fişi Yazdır Buton Tıklama Dinleyicisi
            document.addEventListener('click', function (e) {
                if (e.target && e.target.closest('#stat-print-summary-btn')) {
                    e.preventDefault();
                    self._printSummaryReceipt();
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
            var iskonto = data.iskonto_trend  || {};
            var kasiy   = data.kasiyerler     || [];
            var urunler = data.top_urunler    || [];

            var html = '<div class="stat-dashboard-wrap">';

            // KPI Kartları — Hero Strip (2 Ana Metrik Kartı)
            html += '<div class="stat-kpi-hero-strip">';
            html += self._kpiCardHeroDual(
                '💰', 'CİRO ANALİZİ',
                'TOPLAM CİRO', self._currency(kpi.toplam_ciro),
                'NET CİRO', self._currency(kpi.net_ciro),
                kpi.siparis_sayisi + ' sipariş (iade düşüldü)',
                'kpi-ciro stat-kpi-clickable',
                'Siparişleri Gör ➔'
            );
            html += self._kpiCardHeroDual(
                '🧾', 'SATIŞ HACMİ',
                'SİPARİŞ SAYISI', kpi.siparis_sayisi + ' adet',
                'SATILAN ÜRÜN', (kpi.toplam_urun_adedi || 0) + ' adet',
                (kpi.sepet_urun_ortalamasi || 0) + ' ürün/sepet ortalama',
                'kpi-siparis stat-kpi-clickable',
                'Siparişleri Gör ➔'
            );
            html += '</div>';

            // KPI Kartları — Compact Grid (5 Detay Metrik Kartı)
            html += '<div class="stat-kpi-compact-grid">';
            html += self._kpiCardCompact('💸', 'MASRAF', self._currency(kpi.toplam_masraf || 0), (kpi.masraf_sayisi || 0) + ' masraf kaydı', 'kpi-masraf stat-kpi-clickable', 'Masraflara Git ➔');
            html += self._kpiCardCompact('↩️', 'İADE', self._currency(kpi.toplam_iade), kpi.iade_sayisi + ' iade', 'kpi-iade stat-kpi-clickable', 'İadeleri Gör ➔');
            html += self._kpiCardCompact('✂️', 'İSKONTO', self._currency(kpi.toplam_iskonto || 0), (kpi.iskonto_siparis_sayisi || 0) + ' siparişte', 'kpi-iskonto stat-kpi-clickable', 'İskontolu Siparişler ➔');
            html += self._kpiCardCompact('🛒', 'SEPET ORT.', self._currency(kpi.sepet_ortalamasi), 'Sepet başı ortalama', 'kpi-sepet-tutar stat-kpi-clickable', 'Dağılımı Gör ➔');
            html += self._kpiCardCompact('🛍️', 'SEPET ÜRÜN', (kpi.sepet_urun_ortalamasi || 0) + ' adet/sepet', 'Sepet başı ortalama', 'kpi-sepet-adet stat-kpi-clickable', 'Dağılımı Gör ➔');
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

            // Birleşik Trend Kartı (Satış & İskonto Analizi)
            var iskNoktalar = iskonto.noktalar || [];
            if (!self.activeTrendMode) {
                self.activeTrendMode = 'satis';
            }

            if (gunluk.length > 0 || iskNoktalar.length > 0) {
                html += '<div class="stat-chart-card stat-chart-full">';
                html += '<div class="stat-chart-header stat-main-trend-header">';
                html += '<div>';
                html += '<h4 class="stat-chart-title" id="stat-main-trend-title">📈 Satış ve Ürün Trendi</h4>';
                html += '<span class="stat-chart-badge" id="stat-main-trend-badge">Yükleniyor...</span>';
                html += '</div>';
                html += '<div class="stat-trend-toggle-wrap">';
                html += '<button class="stat-trend-toggle-btn' + (self.activeTrendMode === 'satis' ? ' active' : '') + '" data-mode="satis">📈 Satış & Ürün Trendi</button>';
                html += '<button class="stat-trend-toggle-btn' + (self.activeTrendMode === 'iskonto' ? ' active' : '') + '" data-mode="iskonto">✂️ İskonto Analizi</button>';
                html += '</div>';
                html += '</div>';
                html += '<div class="stat-chart-body stat-chart-body-main"><canvas id="stat-chart-main"></canvas></div>';
                html += '</div>';
            }

            // Saatlik yoğunluk
            html += '<div class="stat-chart-card">';
            html += '<div class="stat-chart-header"><h4 class="stat-chart-title">🕐 Saatlik Yoğunluk</h4><span class="stat-chart-badge">0–23 saat</span></div>';
            html += '<div class="stat-chart-body"><canvas id="stat-chart-saatlik"></canvas></div>';
            html += '</div>';

            // Ödeme dağılımı doughnut (Yan Yana Sol/Sağ Düzen)
            html += '<div class="stat-chart-card stat-odeme-card">';
            html += '<div class="stat-chart-header"><h4 class="stat-chart-title">💳 Ödeme Dağılımı</h4><span class="stat-chart-badge" id="stat-odeme-total-badge">Net Dağılım</span></div>';
            html += '<div class="stat-chart-body stat-odeme-body">';
            html += '<div class="stat-odeme-chart-wrap">';
            html += '<canvas id="stat-chart-odeme"></canvas>';
            html += '<div class="stat-odeme-center-text"><span class="stat-odeme-center-label">TOPLAM</span><span class="stat-odeme-center-val" id="stat-odeme-center-total">₺ 0</span></div>';
            html += '</div>';
            html += '<div class="stat-odeme-legend-list" id="stat-odeme-legend-list"></div>';
            html += '</div>';
            html += '</div>';

            // Kasiyer performansı (sadece birden fazla varsa)
            if (kasiy.length > 0) {
                html += '<div class="stat-chart-card">';
                html += '<div class="stat-chart-header stat-main-trend-header">';
                html += '<div>';
                html += '<h4 class="stat-chart-title">👤 Kasiyer Performansı</h4>';
                html += '<span class="stat-chart-badge">' + kasiy.length + ' kasiyer</span>';
                html += '</div>';
                html += '<div class="stat-kasiyer-legend-wrap">';
                html += '<span class="stat-kasiyer-legend-item"><i class="dot" style="background:#8b5cf6"></i> Sipariş</span>';
                html += '<span class="stat-kasiyer-legend-item"><i class="dot" style="background:#3b82f6"></i> Ciro (₺)</span>';
                html += '</div>';
                html += '</div>';
                html += '<div class="stat-chart-body"><canvas id="stat-chart-kasiyer"></canvas></div>';
                html += '</div>';
            }

            // Sepet Dağılım Analizi Grafiği (En son sırada)
            if (!self.activeDagilimMode) {
                self.activeDagilimMode = 'tutar';
            }
            html += '<div class="stat-chart-card">';
            html += '<div class="stat-chart-header stat-main-trend-header">';
            html += '<div>';
            html += '<h4 class="stat-chart-title">📊 Sepet Dağılım Analizi</h4>';
            html += '<span class="stat-chart-badge" id="stat-dagilim-badge">Sepet Harcama Dilimleri</span>';
            html += '</div>';
            html += '<div class="stat-trend-toggle-wrap">';
            html += '<button class="stat-dagilim-toggle-btn' + (self.activeDagilimMode === 'tutar' ? ' active' : '') + '" data-mode="tutar">💰 Tutar Aralığı</button>';
            html += '<button class="stat-dagilim-toggle-btn' + (self.activeDagilimMode === 'adet' ? ' active' : '') + '" data-mode="adet">📦 Ürün Adedi</button>';
            html += '</div>';
            html += '</div>';
            html += '<div class="stat-chart-body"><canvas id="stat-chart-ortalamalar"></canvas></div>';
            html += '</div>';

            html += '</div>'; // .stat-charts-grid

            // Top Ürünler tablosu
            if (urunler.length > 0) {
                if (!self.topState) {
                    self.topState = { limit: 10, search: '', sortField: 'qty', sortDir: 'desc' };
                }

                html += '<div class="stat-chart-card">';
                html += '<div class="stat-chart-header stat-top-header">';
                html += '<div>';
                html += '<h4 class="stat-chart-title">🏆 En Çok Satan Ürünler</h4>';
                html += '<span class="stat-chart-badge" id="stat-top-count-badge">' + urunler.length + ' ürün listelendi</span>';
                html += '</div>';
                html += '<div class="stat-top-controls">';
                html += '<div class="stat-top-search-wrap">';
                html += '<input type="text" id="stat-top-search" class="stat-top-search-input" value="' + self._esc(self.topState.search) + '" placeholder="🔍 Ürün veya SKU ara..." autocomplete="off">';
                html += '</div>';
                html += '<select id="stat-top-limit-select" class="stat-top-limit-select">';
                html += '<option value="10"' + (self.topState.limit === 10 ? ' selected' : '') + '>Top 10</option>';
                html += '<option value="25"' + (self.topState.limit === 25 ? ' selected' : '') + '>Top 25</option>';
                html += '<option value="50"' + (self.topState.limit === 50 ? ' selected' : '') + '>Top 50</option>';
                html += '</select>';
                html += '</div>';
                html += '</div>';

                html += self._renderTopTable(urunler, self.topState);
                html += '</div>';
            }

            html += '</div>'; // .stat-dashboard-wrap
            panel.innerHTML = html;

            if (urunler.length > 0) {
                self._bindTopTableEvents(panel, urunler);
            }

            // Tıklanabilir KPI Kart Eventleri
            panel.querySelectorAll('.stat-kpi-card.stat-kpi-clickable').forEach(function (card) {
                card.addEventListener('click', function () {
                    if (!HK.ReportHub) return;
                    if (HK.ReportHub.history) {
                        HK.ReportHub.history.push({
                            view: 'report',
                            catId: 'istatistik',
                            repId: 'ozet-istatistik'
                        });
                    }
                    if (card.classList.contains('kpi-masraf')) {
                        var masrafTab = document.querySelector('.ust-sekme[data-tab="masraf"]');
                        if (masrafTab) {
                            masrafTab.click();
                        }
                    } else if (card.classList.contains('kpi-iade')) {
                        HK.ReportHub.kategoriAc('iade', 'iade-listesi');
                    } else if (card.classList.contains('kpi-iskonto')) {
                        if (HK.ReportSales) {
                            HK.ReportSales.filterHasDiscount = true;
                        }
                        HK.ReportHub.kategoriAc('satis', 'tum-siparisler');
                    } else if (card.classList.contains('kpi-urun')) {
                        var mainCard = document.getElementById('stat-chart-main');
                        if (mainCard) {
                            mainCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        }
                        var btn = panel.querySelector('.stat-trend-toggle-btn[data-mode="urun"]');
                        if (btn) btn.click();
                    } else if (card.classList.contains('kpi-sepet-tutar') || card.classList.contains('kpi-sepet-adet')) {
                        var ortCard = document.getElementById('stat-chart-ortalamalar');
                        if (ortCard) {
                            ortCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        }
                    } else {
                        if (HK.ReportSales) {
                            HK.ReportSales.filterHasDiscount = false;
                        }
                        HK.ReportHub.kategoriAc('satis', 'tum-siparisler');
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

            // --- Ana Trend Chart (Satış veya İskonto) ---
            if (document.getElementById('stat-chart-main')) {
                self._initMainTrendChart(data, gridColor, tickColor, commonTooltip);

                // Toggle buton dinleyicileri
                panel.querySelectorAll('.stat-trend-toggle-btn').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        var mode = this.dataset.mode;
                        if (mode === self.activeTrendMode) return;
                        self.activeTrendMode = mode;

                        panel.querySelectorAll('.stat-trend-toggle-btn').forEach(function (b) {
                            b.classList.toggle('active', b.dataset.mode === mode);
                        });

                        self._updateMainTrendChart(data, gridColor, tickColor, commonTooltip);
                    });
                });
            }

            // --- Sepet Dağılım Analizi Chart ---
            if (document.getElementById('stat-chart-ortalamalar')) {
                self._initDagilimChart(data, gridColor, tickColor, commonTooltip);

                panel.querySelectorAll('.stat-dagilim-toggle-btn').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        var mode = this.dataset.mode;
                        if (mode === self.activeDagilimMode) return;
                        self.activeDagilimMode = mode;

                        panel.querySelectorAll('.stat-dagilim-toggle-btn').forEach(function (b) {
                            b.classList.toggle('active', b.dataset.mode === mode);
                        });

                        self._updateDagilimChart(data, gridColor, tickColor, commonTooltip);
                    });
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
                        maintainAspectRatio: false,
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

            // --- Ödeme dağılımı doughnut (Yan Yana Sol/Sağ Düzen) ---
            if (document.getElementById('stat-chart-odeme')) {
                var nakitVal = parseFloat(odeme.nakit) || 0;
                var kartVal  = parseFloat(odeme.kart) || 0;
                var ibanVal  = parseFloat(odeme.iban) || 0;
                var qrVal    = parseFloat(odeme.qr_taksit) || 0;

                var totalOdeme = nakitVal + kartVal + ibanVal + qrVal;

                var centerTotalEl = document.getElementById('stat-odeme-center-total');
                if (centerTotalEl) {
                    centerTotalEl.innerText = self._currency(totalOdeme);
                }

                var items = [
                    { label: 'Nakit',     icon: '💵', val: nakitVal, color: '#10b981' },
                    { label: 'Kart',      icon: '💳', val: kartVal,  color: '#3b82f6' },
                    { label: 'IBAN',      icon: '🏦', val: ibanVal,  color: '#8b5cf6' },
                    { label: 'QR/Taksit', icon: '📱', val: qrVal,    color: '#f59e0b' }
                ];

                // Özel Yan Legend Listesini Oluştur
                var legendListEl = document.getElementById('stat-odeme-legend-list');
                if (legendListEl) {
                    var legendHtml = '';
                    items.forEach(function (item, idx) {
                        var pct = totalOdeme > 0 ? ((item.val / totalOdeme) * 100).toFixed(1) : '0';
                        legendHtml += '<div class="stat-odeme-legend-item" data-idx="' + idx + '">';
                        legendHtml += '<div class="stat-odeme-item-left">';
                        legendHtml += '<span class="stat-odeme-dot" style="background-color:' + item.color + '"></span>';
                        legendHtml += '<span class="stat-odeme-item-name">' + item.icon + ' ' + item.label + '</span>';
                        legendHtml += '</div>';
                        legendHtml += '<div class="stat-odeme-item-right">';
                        legendHtml += '<span class="stat-odeme-item-val">' + self._currency(item.val) + '</span>';
                        legendHtml += '<span class="stat-odeme-item-pct">%' + pct + '</span>';
                        legendHtml += '</div>';
                        legendHtml += '</div>';
                    });
                    legendListEl.innerHTML = legendHtml;

                    // Hover Etkileşimi (Liste elemanına gelindiğinde grafiğin ilgili dilimi öne çıkar)
                    legendListEl.querySelectorAll('.stat-odeme-legend-item').forEach(function (el) {
                        el.addEventListener('mouseenter', function () {
                            var idx = parseInt(this.dataset.idx, 10);
                            if (self.charts.odeme) {
                                self.charts.odeme.setActiveElements([{ datasetIndex: 0, index: idx }]);
                                self.charts.odeme.tooltip.setActiveElements([{ datasetIndex: 0, index: idx }]);
                                self.charts.odeme.update();
                            }
                        });
                        el.addEventListener('mouseleave', function () {
                            if (self.charts.odeme) {
                                self.charts.odeme.setActiveElements([]);
                                self.charts.odeme.tooltip.setActiveElements([]);
                                self.charts.odeme.update();
                            }
                        });
                    });
                }

                self.charts.odeme = new Chart(document.getElementById('stat-chart-odeme'), {
                    type: 'doughnut',
                    data: {
                        labels: items.map(function (i) { return i.icon + ' ' + i.label; }),
                        datasets: [{
                            data: items.map(function (i) { return i.val; }),
                            backgroundColor: items.map(function (i) { return i.color; }),
                            borderWidth: 0,
                            hoverOffset: 10,
                            clip: false,
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        cutout: '74%',
                        layout: {
                            padding: 4
                        },
                        plugins: {
                            legend: { display: false },
                            tooltip: Object.assign({}, commonTooltip, {
                                callbacks: {
                                    label: function (ctx) {
                                        var val = ctx.parsed;
                                        var pct = totalOdeme > 0 ? ((val / totalOdeme) * 100).toFixed(1) : '0';
                                        return ' Tutar: ₺ ' + self._num(val) + ' (%' + pct + ')';
                                    }
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
                        maintainAspectRatio: false,
                        interaction: { mode: 'index', intersect: false },
                        layout: {
                            padding: {
                                top: 6,
                                bottom: 0,
                                left: 0,
                                right: 0
                            }
                        },
                        plugins: {
                            legend: { display: false },
                            tooltip: Object.assign({}, commonTooltip, {
                                callbacks: {
                                    label: function (ctx) {
                                        if (ctx.dataset.yAxisID === 'yCiro') {
                                            return ' Ciro: ₺ ' + self._num(ctx.parsed.y);
                                        }
                                        return ' Sipariş: ' + ctx.parsed.y + ' adet';
                                    }
                                }
                            })
                        },
                        scales: {
                            x: {
                                grid: { display: false },
                                ticks: { color: tickColor, padding: 4 }
                            },
                            ySiparis: {
                                type: 'linear',
                                position: 'left',
                                grid: { color: gridColor },
                                ticks: { color: tickColor, stepSize: 1, padding: 4 },
                                beginAtZero: true,
                            },
                            yCiro: {
                                type: 'linear',
                                position: 'right',
                                grid: { drawOnChartArea: false },
                                ticks: {
                                    color: tickColor,
                                    padding: 4,
                                    callback: function (v) { return '₺' + self._num(v); }
                                },
                                beginAtZero: true,
                            }
                        }
                    }
                });
            }

            // --- ResizeObserver & Sekme Dinleyicisi ---
            var panel = document.getElementById('rapor-ozet-istatistik');
            var gridEl = panel ? panel.querySelector('.stat-charts-grid') : null;
            if (gridEl && window.ResizeObserver) {
                if (self._resizeObserver) {
                    self._resizeObserver.disconnect();
                }
                self._resizeObserver = new ResizeObserver(function () {
                    self.resizeCharts();
                });
                self._resizeObserver.observe(gridEl);
            }
        },

        /* -------------------------------------------------
           TOP ÜRÜNLER TABLOSU
        ------------------------------------------------- */
        _renderTopTable: function (urunler, options) {
            var self = this;
            options = options || {};
            var limit = options.limit || 10;
            var search = (options.search || '').toLowerCase().trim();
            var sortField = options.sortField || 'qty';
            var sortDir = options.sortDir || 'desc';

            var filtered = urunler.filter(function (u) {
                if (!search) return true;
                var matchParent = (u.name || '').toLowerCase().includes(search) || (u.sku || '').toLowerCase().includes(search);
                if (matchParent) return true;
                if (u.variations && u.variations.length > 0) {
                    return u.variations.some(function (v) {
                        return (v.name || '').toLowerCase().includes(search) || (v.sku || '').toLowerCase().includes(search);
                    });
                }
                return false;
            });

            filtered.sort(function (a, b) {
                var valA = parseFloat(a[sortField]) || 0;
                var valB = parseFloat(b[sortField]) || 0;
                return sortDir === 'desc' ? valB - valA : valA - valB;
            });

            var totalMatching = filtered.length;
            var visibleItems = filtered.slice(0, limit);
            var maxQty = urunler[0] ? urunler[0].qty : 1;

            var html = '<div id="stat-top-table-container">';
            html += '<table class="stat-top-table"><thead><tr>';
            html += '<th style="width:40px">#</th>';
            html += '<th>Ürün</th>';
            html += '<th style="text-align:right;cursor:pointer;" class="stat-sort-header" data-sort="qty" title="Adede göre sırala">Adet ' + (sortField === 'qty' ? (sortDir === 'desc' ? '↓' : '↑') : '') + '</th>';
            html += '<th style="text-align:right;cursor:pointer;" class="stat-sort-header" data-sort="total" title="Ciroya göre sırala">Ciro / Analiz ' + (sortField === 'total' ? (sortDir === 'desc' ? '↓' : '↑') : '') + '</th>';
            html += '</tr></thead><tbody>';

            if (visibleItems.length === 0) {
                html += '<tr><td colspan="4" style="text-align:center;padding:30px;color:var(--hk-text-muted);">Aramaya uygun ürün bulunamadı.</td></tr>';
            } else {
                visibleItems.forEach(function (u, i) {
                    var rankNum = i + 1;
                    var rankBadge = '';
                    if (rankNum === 1) rankBadge = '<span class="stat-rank-badge top-1" title="1. Sıra">🥇</span>';
                    else if (rankNum === 2) rankBadge = '<span class="stat-rank-badge top-2" title="2. Sıra">🥈</span>';
                    else if (rankNum === 3) rankBadge = '<span class="stat-rank-badge top-3" title="3. Sıra">🥉</span>';
                    else rankBadge = '<span class="stat-rank-badge">' + rankNum + '</span>';

                    var pct = maxQty > 0 ? Math.round((u.qty / maxQty) * 100) : 0;
                    var hasVars = u.variations && u.variations.length > 0;
                    var varRowId = 'stat-var-row-' + i;
                    var mainSku = u.sku || u.id || u.name;

                    html += '<tr>';
                    html += '<td>' + rankBadge + '</td>';
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
            }

            html += '</tbody></table>';

            if (totalMatching > limit) {
                var remaining = totalMatching - limit;
                html += '<div class="stat-top-expand-wrap">';
                html += '<button id="stat-top-expand-btn" class="stat-top-expand-btn">Daha Fazla Göster (+' + remaining + ' Ürün)</button>';
                html += '</div>';
            } else if (limit > 10 && totalMatching > 10) {
                html += '<div class="stat-top-expand-wrap">';
                html += '<button id="stat-top-collapse-btn" class="stat-top-expand-btn stat-top-collapse">Daha Az Göster (Top 10)</button>';
                html += '</div>';
            }

            html += '</div>'; // #stat-top-table-container
            return html;
        },

        _bindTopTableEvents: function (panel, urunler) {
            var self = this;
            if (!self.topState) {
                self.topState = { limit: 10, search: '', sortField: 'qty', sortDir: 'desc' };
            }

            var container = document.getElementById('stat-top-table-container');
            var searchInput = document.getElementById('stat-top-search');
            var limitSelect = document.getElementById('stat-top-limit-select');

            var updateTable = function () {
                if (container) {
                    container.outerHTML = self._renderTopTable(urunler, self.topState);
                    self._bindTopTableEvents(panel, urunler);
                }
            };

            if (searchInput) {
                searchInput.addEventListener('input', function () {
                    self.topState.search = this.value;
                    updateTable();
                });
            }

            if (limitSelect) {
                limitSelect.addEventListener('change', function () {
                    self.topState.limit = parseInt(this.value, 10) || 10;
                    updateTable();
                });
            }

            // Accordion toggle dinleyicileri
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

            // Analiz buton dinleyicileri
            panel.querySelectorAll('.stat-analyze-btn').forEach(function (btn) {
                btn.addEventListener('click', function (e) {
                    e.stopPropagation();
                    var sku = this.dataset.sku;
                    if (sku && HK.ProductStatsReport) {
                        HK.ProductStatsReport.analyzeSKU(sku);
                    }
                });
            });

            // Sıralama başlıkları
            panel.querySelectorAll('.stat-sort-header').forEach(function (th) {
                th.addEventListener('click', function () {
                    var field = this.dataset.sort;
                    if (self.topState.sortField === field) {
                        self.topState.sortDir = self.topState.sortDir === 'desc' ? 'asc' : 'desc';
                    } else {
                        self.topState.sortField = field;
                        self.topState.sortDir = 'desc';
                    }
                    updateTable();
                });
            });

            // Daha fazla göster
            var expandBtn = document.getElementById('stat-top-expand-btn');
            if (expandBtn) {
                expandBtn.addEventListener('click', function () {
                    self.topState.limit += 15;
                    updateTable();
                });
            }

            var collapseBtn = document.getElementById('stat-top-collapse-btn');
            if (collapseBtn) {
                collapseBtn.addEventListener('click', function () {
                    self.topState.limit = 10;
                    updateTable();
                });
            }
        },

        /* -------------------------------------------------
           KPI KART HTML — Hero Dual (ikili metrik kartı)
        ------------------------------------------------- */
        _kpiCardHeroDual: function (icon, title, val1Label, val1Val, val2Label, val2Val, sub, cls, actionText) {
            var actionHtml = actionText ? '<span class="stat-kpi-action-link">' + actionText + '</span>' : '';
            return '<div class="stat-kpi-card stat-kpi-hero stat-kpi-hero-dual ' + (cls || '') + '">'
                + '<div class="stat-kpi-hero-header">'
                + '<div class="stat-kpi-hero-title-group">'
                + '<span class="stat-kpi-icon">' + icon + '</span>'
                + '<span class="stat-kpi-label">' + title + '</span>'
                + '</div>'
                + actionHtml
                + '</div>'
                + '<div class="stat-kpi-hero-dual-grid">'
                + '<div class="stat-kpi-hero-dual-item">'
                + '<span class="stat-kpi-dual-label">' + val1Label + '</span>'
                + '<span class="stat-kpi-value">' + val1Val + '</span>'
                + '</div>'
                + '<div class="stat-kpi-hero-dual-divider"></div>'
                + '<div class="stat-kpi-hero-dual-item">'
                + '<span class="stat-kpi-dual-label">' + val2Label + '</span>'
                + '<span class="stat-kpi-value stat-kpi-sub-val">' + val2Val + '</span>'
                + '</div>'
                + '</div>'
                + '</div>';
        },

        /* -------------------------------------------------
           KPI KART HTML — Hero (büyük tekli ana metrikler)
        ------------------------------------------------- */
        _kpiCardHero: function (icon, label, value, sub, cls, actionText) {
            var actionHtml = actionText ? '<span class="stat-kpi-action-link">' + actionText + '</span>' : '';
            return '<div class="stat-kpi-card stat-kpi-hero ' + (cls || '') + '">'
                + '<div class="stat-kpi-hero-header">'
                + '<div class="stat-kpi-hero-title-group">'
                + '<span class="stat-kpi-icon">' + icon + '</span>'
                + '<span class="stat-kpi-label">' + label + '</span>'
                + '</div>'
                + actionHtml
                + '</div>'
                + '<span class="stat-kpi-value">' + value + '</span>'
                + '</div>';
        },

        /* -------------------------------------------------
           KPI KART HTML — Compact (küçük detay metrikler)
        ------------------------------------------------- */
        _kpiCardCompact: function (icon, label, value, sub, cls, actionText) {
            var actionHtml = actionText ? '<span class="stat-kpi-action-link">' + actionText + '</span>' : '';
            return '<div class="stat-kpi-card stat-kpi-compact ' + (cls || '') + '">'
                + '<div class="stat-kpi-compact-top">'
                + '<span class="stat-kpi-icon">' + icon + '</span>'
                + '<div class="stat-kpi-compact-info">'
                + '<span class="stat-kpi-label">' + label + '</span>'
                + '<span class="stat-kpi-value">' + value + '</span>'
                + '</div>'
                + '</div>'
                + '<div class="stat-kpi-sub-row">'
                + '<span class="stat-kpi-sub">' + (sub || '') + '</span>'
                + actionHtml
                + '</div>'
                + '</div>';
        },

        /* -------------------------------------------------
           KPI KART HTML — Eski (uyumluluk için korundu)
        ------------------------------------------------- */
        _kpiCard: function (icon, label, value, sub, cls, actionText) {
            var actionHtml = actionText ? '<span class="stat-kpi-action-link">' + actionText + '</span>' : '';
            return '<div class="stat-kpi-card ' + (cls || '') + '">'
                + '<span class="stat-kpi-icon">' + icon + '</span>'
                + '<span class="stat-kpi-label">' + label + '</span>'
                + '<span class="stat-kpi-value">' + value + '</span>'
                + '<div class="stat-kpi-sub-row">'
                + '<span class="stat-kpi-sub">' + (sub || '') + '</span>'
                + actionHtml
                + '</div>'
                + '</div>';
        },

        /* -------------------------------------------------
           YÜKLENİYOR ANIMASYONU
        ------------------------------------------------- */
        _showLoading: function (panel) {
            panel.innerHTML = '<div class="stat-loading"><div class="stat-spinner"></div><p>İstatistikler hazırlanıyor...</p></div>';
        },

        /* -------------------------------------------------
           CHART'LARI TEMİZLE & RESIZE
        ------------------------------------------------- */
        resizeCharts: function () {
            var self = this;
            Object.keys(self.charts).forEach(function (key) {
                if (self.charts[key] && typeof self.charts[key].resize === 'function') {
                    try {
                        self.charts[key].resize();
                    } catch (e) {}
                }
            });
        },

        _destroyCharts: function () {
            var self = this;
            if (self._resizeObserver) {
                self._resizeObserver.disconnect();
                self._resizeObserver = null;
            }
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
            var canvas = document.getElementById('stat-chart-main');
            if (!canvas) return;

            self.charts.main = new Chart(canvas, {
                type: 'line',
                data: { labels: [], datasets: [] },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
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

            self._updateMainTrendChart(data, gridColor, tickColor, commonTooltip);
        },

        _updateMainTrendChart: function (data, gridColor, tickColor, commonTooltip) {
            var self = this;
            var chart = self.charts.main;
            if (!chart) return;

            var mode = self.activeTrendMode || 'satis';
            var titleEl = document.getElementById('stat-main-trend-title');
            var badgeEl = document.getElementById('stat-main-trend-badge');

            var gunluk  = data.gunluk_trend || [];
            var saatlik = data.saatlik_dagilim || [];
            var iskonto = data.iskonto_trend || {};
            var iskNoktalar = iskonto.noktalar || [];

            if (mode === 'satis') {
                if (titleEl) titleEl.innerText = '📈 Satış ve Ürün Trendi';

                var isSingleDay = (gunluk.length <= 1);
                var labels = [];
                var ciroData = [];
                var adetData = [];
                var urunData = [];

                if (!isSingleDay) {
                    labels   = gunluk.map(function (g) { return g.tarih_kisa; });
                    ciroData = gunluk.map(function (g) { return g.toplam; });
                    adetData = gunluk.map(function (g) { return g.siparis_sayisi; });
                    urunData = gunluk.map(function (g) { return g.urun_adedi || 0; });
                    if (badgeEl) badgeEl.innerText = gunluk.length + ' gün • Detay için noktaya tıklayın';
                } else {
                    labels   = saatlik.map(function (s) { return s.saat; });
                    ciroData = saatlik.map(function (s) { return s.total; });
                    adetData = saatlik.map(function (s) { return s.count; });
                    urunData = saatlik.map(function (s) { return s.items || 0; });
                    if (badgeEl) badgeEl.innerText = 'Saatlik Yoğunluk • 24 Saat';
                }

                chart.data.labels = labels;
                chart.data.datasets = [
                    {
                        label: 'Net Ciro (₺)',
                        data: ciroData,
                        borderColor: '#3b82f6',
                        backgroundColor: 'rgba(59,130,246,0.10)',
                        fill: true,
                        tension: 0.4,
                        pointBackgroundColor: '#3b82f6',
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        borderWidth: 2.5,
                        yAxisID: 'yCiro',
                        hidden: false,
                    },
                    {
                        label: 'Sipariş Sayısı',
                        data: adetData,
                        borderColor: '#8b5cf6',
                        backgroundColor: 'rgba(139,92,246,0.12)',
                        fill: false,
                        tension: 0.4,
                        pointBackgroundColor: '#8b5cf6',
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        borderWidth: 2,
                        yAxisID: 'ySiparis',
                        hidden: true, // Varsayılan olarak kapalı! Lejanttan açılabilir
                    },
                    {
                        label: 'Satılan Ürün Adedi',
                        data: urunData,
                        borderColor: '#06b6d4',
                        backgroundColor: 'rgba(6,182,212,0.12)',
                        fill: false,
                        tension: 0.4,
                        pointBackgroundColor: '#06b6d4',
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        borderWidth: 2,
                        yAxisID: 'ySiparis',
                        hidden: true, // Varsayılan olarak kapalı! Lejanttan açılabilir
                    }
                ];

                chart.options.scales = {
                    x: { grid: { color: gridColor }, ticks: { color: tickColor } },
                    ySiparis: {
                        type: 'linear',
                        position: 'left',
                        display: 'auto',
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
                };

                chart.options.plugins.legend = { display: true, position: 'top', labels: { color: tickColor, font: { size: 12 } } };
                chart.options.plugins.tooltip = Object.assign({}, commonTooltip, {
                    callbacks: {
                        label: function (ctx) {
                            if (ctx.dataset.yAxisID === 'yCiro') {
                                return ' Net Ciro: ₺ ' + self._num(ctx.parsed.y);
                            }
                            if (ctx.dataset.label && ctx.dataset.label.indexOf('Ürün') !== -1) {
                                return ' Satılan Ürün: ' + ctx.parsed.y + ' adet';
                            }
                            return ' Sipariş Sayısı: ' + ctx.parsed.y + ' adet';
                        }
                    }
                });

                chart.options.onClick = function (evt, elements) {
                    if (!isSingleDay && elements && elements.length > 0) {
                        var index = elements[0].index;
                        var item = gunluk[index];
                        if (item && item.tarih) {
                            self._showDayDetails(item.tarih);
                        }
                    }
                };

            } else {
                if (titleEl) titleEl.innerText = '✂️ İskonto Analizi';
                var modLabel = iskonto.mod === 'saatlik' ? 'Saatlik Yoğunluk' : iskNoktalar.length + ' Günlük Trend';
                if (badgeEl) badgeEl.innerText = modLabel + ' • Etiket vs. Gerçek Satış';

                chart.data.labels = iskNoktalar.map(function (n) { return n.label; });
                chart.data.datasets = [
                    {
                        label: 'Etiket Toplamı (₺)',
                        data: iskNoktalar.map(function (n) { return n.etiket; }),
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
                        data: iskNoktalar.map(function (n) { return n.gercek; }),
                        borderColor: '#10b981',
                        backgroundColor: 'rgba(16,185,129,0.18)',
                        fill: '-1',
                        tension: 0.4,
                        borderWidth: 2.5,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                    }
                ];

                chart.options.scales = {
                    x: { grid: { color: gridColor }, ticks: { color: tickColor } },
                    y: {
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
                            var point = iskNoktalar[idx];
                            if (!point) return [];
                            var diff = point.iskonto;
                            var pct = point.etiket > 0 ? ((diff / point.etiket) * 100).toFixed(1) : '0';
                            return [
                                '-----------------------',
                                '✂️ Uygulanan İskonto: ₺ ' + self._num(diff) + ' (%' + pct + ' indirim)'
                            ];
                        }
                    }
                });

                chart.options.onClick = null;
            }

            chart.update();
        },

        /* -------------------------------------------------
           SEPET DAĞILIM ANALİZİ CHART (TUTAR / ÜRÜN ADEDİ)
        ------------------------------------------------- */
        _initDagilimChart: function (data, gridColor, tickColor, commonTooltip) {
            var self = this;
            var canvas = document.getElementById('stat-chart-ortalamalar');
            if (!canvas) return;

            self.charts.ortalamalar = new Chart(canvas, {
                type: 'bar',
                data: { labels: [], datasets: [] },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    borderRadius: 6,
                    plugins: {
                        legend: { display: false },
                        tooltip: Object.assign({}, commonTooltip, {
                            callbacks: {
                                label: function (ctx) {
                                    var dagilim = data.sepet_dagilimi || {};
                                    var totalSip = (data.kpi || {}).siparis_sayisi || 0;
                                    var mode = self.activeDagilimMode || 'tutar';
                                    var list = dagilim[mode] || [];
                                    var item = list[ctx.dataIndex];
                                    if (!item) return '';

                                    var cnt = item.count || 0;
                                    var pct = totalSip > 0 ? ((cnt / totalSip) * 100).toFixed(1) : '0';
                                    var lines = [' Sipariş: ' + cnt + ' adet (%' + pct + ')'];
                                    if (mode === 'tutar' && item.total !== undefined) {
                                        lines.push(' Toplam Ciro: ₺ ' + self._num(item.total));
                                    }
                                    return lines;
                                }
                            }
                        })
                    },
                    scales: {
                        x: { grid: { display: false }, ticks: { color: tickColor } },
                        y: {
                            grid: { color: gridColor },
                            ticks: {
                                color: tickColor,
                                precision: 0,
                                callback: function (v) { return v + ' sp'; }
                            },
                            beginAtZero: true
                        }
                    }
                }
            });

            self._updateDagilimChart(data, gridColor, tickColor, commonTooltip);
        },

        _updateDagilimChart: function (data, gridColor, tickColor, commonTooltip) {
            var self = this;
            var chart = self.charts.ortalamalar;
            if (!chart) return;

            var mode = self.activeDagilimMode || 'tutar';
            var badgeEl = document.getElementById('stat-dagilim-badge');
            var dagilim = data.sepet_dagilimi || {};
            var list = dagilim[mode] || [];

            var labels = list.map(function (item) { return item.label; });
            var counts = list.map(function (item) { return item.count || 0; });

            if (mode === 'tutar') {
                if (badgeEl) badgeEl.innerText = 'Dinamik Sepet Dilimleri (AOV Bazlı)';
                chart.data.labels = labels;
                chart.data.datasets = [{
                    label: 'Sipariş Sayısı',
                    data: counts,
                    backgroundColor: 'rgba(99, 102, 241, 0.85)',
                    hoverBackgroundColor: '#4f46e5',
                    borderRadius: 6,
                }];
            } else {
                if (badgeEl) badgeEl.innerText = 'Sepetteki Ürün Sayısı';
                chart.data.labels = labels;
                chart.data.datasets = [{
                    label: 'Sipariş Sayısı',
                    data: counts,
                    backgroundColor: 'rgba(236, 72, 153, 0.85)',
                    hoverBackgroundColor: '#db2777',
                    borderRadius: 6,
                }];
            }

            chart.update();
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

        _fmtTRDate: function (str) {
            if (!str) return '';
            var parts = String(str).split('-');
            if (parts.length === 3 && parts[0].length === 4) {
                return parts[2] + '.' + parts[1] + '.' + parts[0];
            }
            return str;
        },

        _printSummaryReceipt: function () {
            var self = this;
            if (!self.lastData) {
                if (HK.UIRenderer && HK.UIRenderer.showToast) {
                    HK.UIRenderer.showToast('Yazdırılacak rapor verisi bulunamadı.', 'warning');
                }
                return;
            }

            try {
                var data = self.lastData;
                var kpi = data.kpi || {};
                var odeme = data.odeme_dagilimi || {};
                var kasiy = data.kasiyerler || [];
                var urunler = data.top_urunler || [];

                var dateStart = (document.getElementById('rhub-tarih-bas') || {}).value || self._today();
                var dateEnd   = (document.getElementById('rhub-tarih-bit') || {}).value || self._today();
                var dStartFmt = self._fmtTRDate(dateStart);
                var dEndFmt   = self._fmtTRDate(dateEnd);
                var tarihLabel = (dateStart === dateEnd) ? dStartFmt : (dStartFmt + ' - ' + dEndFmt);

                var storeName = (window.kasaAyar && (window.kasaAyar.magazaAdi || window.kasaAyar.siteName)) ? (window.kasaAyar.magazaAdi || window.kasaAyar.siteName) : 'HIZLI KASA POS';
                var depoName = '';
                if (HK.DepoManager && typeof HK.DepoManager.getActiveDepoObj === 'function') {
                    var depoObj = HK.DepoManager.getActiveDepoObj();
                    if (depoObj && depoObj.adi) depoName = depoObj.adi;
                }

                var fontStack = "Consolas, 'Segoe UI Mono', 'Courier New', Courier, monospace, sans-serif";
                var html = '<div class="hk-unified-print-container receipt-zreport" style="font-family:' + fontStack + '; font-weight:600; color:#000000 !important; background-color:#ffffff !important; width:100%; max-width:300px; margin:0 auto; padding:4px 8px; box-sizing:border-box; font-size:12px; line-height:1.25; letter-spacing:-0.2px; box-shadow:none !important; border:none !important;">';

                // Header
                html += '<div style="text-align:center; margin-bottom:8px; border-bottom:2px solid #000000; padding-bottom:8px;">';
                html += '<h2 style="margin:0; font-size:16px; font-weight:bold; color:#000000; font-family:' + fontStack + ';">' + self._esc(storeName) + '</h2>';
                html += '<p style="margin:4px 0 2px 0; font-size:13px; font-weight:bold; color:#000000; font-family:' + fontStack + ';">SATIŞ ÖZET RAPORU</p>';
                html += '<p style="margin:2px 0 0 0; font-size:11px; font-weight:bold; color:#000000; font-family:' + fontStack + ';">Tarih: ' + self._esc(tarihLabel) + '</p>';
                if (depoName) {
                    html += '<p style="margin:2px 0 0 0; font-size:11px; font-weight:bold; color:#000000; font-family:' + fontStack + ';">Depo: ' + self._esc(depoName) + '</p>';
                }
                html += '</div>';

                // Genel Özet
                html += '<div style="margin-bottom:8px;">';
                html += '<p style="font-weight:bold; margin:0 0 4px; font-size:12px; border-bottom:1px solid #000000; padding-bottom:2px; color:#000000;">GENEL ÖZET</p>';
                html += '<table style="width:100%; font-size:12px; border-collapse:collapse; color:#000000; table-layout:fixed;">';
                html += '<tr><td style="font-weight:600;">Sipariş Sayısı</td><td style="text-align:right; font-weight:bold;">' + (kpi.siparis_sayisi || 0) + ' ad.</td></tr>';
                html += '<tr><td style="font-weight:600;">Satılan Ürün</td><td style="text-align:right; font-weight:bold;">' + (kpi.toplam_urun_adedi || 0) + ' ad.</td></tr>';
                html += '</table></div>';

                // Ciro & Hesap Analizi
                var brutCiro  = parseFloat(kpi.toplam_ciro) || 0;
                var iadeVal   = parseFloat(kpi.toplam_iade) || 0;
                var iskontoVal= parseFloat(kpi.toplam_iskonto) || 0;
                var masrafVal = parseFloat(kpi.toplam_masraf) || 0;
                var netSatis  = brutCiro - iadeVal;
                var genelNet  = brutCiro - iadeVal - masrafVal;

                html += '<div style="margin-bottom:8px;">';
                html += '<p style="font-weight:bold; margin:0 0 4px; font-size:12px; border-bottom:1px solid #000000; padding-bottom:2px; color:#000000;">CİRO & HESAP ÖZETİ</p>';
                html += '<table style="width:100%; font-size:12px; border-collapse:collapse; color:#000000; table-layout:fixed;">';
                html += '<tr><td style="font-weight:600;">Brüt Ciro</td><td style="text-align:right; font-weight:bold;">' + self._currency(brutCiro) + '</td></tr>';
                if (iadeVal > 0) {
                    html += '<tr><td style="font-weight:600;">Toplam İade (' + (kpi.iade_sayisi || 0) + ' ad.)</td><td style="text-align:right; font-weight:bold;">-' + self._currency(iadeVal) + '</td></tr>';
                }
                if (iskontoVal > 0) {
                    html += '<tr><td style="font-weight:600;">Toplam İskonto</td><td style="text-align:right; font-weight:bold;">-' + self._currency(iskontoVal) + '</td></tr>';
                }
                html += '<tr style="border-top:1px solid #000000;"><td style="font-weight:bold; padding-top:2px;">NET SATIŞ CİROSU</td><td style="text-align:right; font-weight:bold; padding-top:2px;">' + self._currency(netSatis) + '</td></tr>';
                if (masrafVal > 0) {
                    html += '<tr><td style="font-weight:600;">Kasa Gideri / Masraf</td><td style="text-align:right; font-weight:bold;">-' + self._currency(masrafVal) + '</td></tr>';
                }
                html += '<tr style="border-top:2px solid #000000;"><td style="font-weight:bold; font-size:13px; padding-top:3px;">GENEL NET BAKİYE</td><td style="text-align:right; font-weight:bold; font-size:13px; padding-top:3px;">' + self._currency(genelNet) + '</td></tr>';
                html += '</table></div>';

                // Ödeme Dağılımı
                var nakitVal = parseFloat(odeme.nakit) || 0;
                var kartVal  = parseFloat(odeme.kart) || 0;
                var ibanVal  = parseFloat(odeme.iban) || 0;
                var qrVal    = parseFloat(odeme.qr_taksit) || 0;

                html += '<div style="margin-bottom:8px;">';
                html += '<p style="font-weight:bold; margin:0 0 4px; font-size:12px; border-bottom:1px solid #000000; padding-bottom:2px; color:#000000;">ÖDEME DAĞILIMI</p>';
                html += '<table style="width:100%; font-size:12px; border-collapse:collapse; color:#000000; table-layout:fixed;">';
                html += '<tr><td style="font-weight:600;">Nakit</td><td style="text-align:right; font-weight:bold;">' + self._currency(nakitVal) + '</td></tr>';
                html += '<tr><td style="font-weight:600;">Kredi Kartı</td><td style="text-align:right; font-weight:bold;">' + self._currency(kartVal) + '</td></tr>';
                html += '<tr><td style="font-weight:600;">IBAN / Havale</td><td style="text-align:right; font-weight:bold;">' + self._currency(ibanVal) + '</td></tr>';
                if (qrVal > 0) {
                    html += '<tr><td style="font-weight:600;">QR / Taksit</td><td style="text-align:right; font-weight:bold;">' + self._currency(qrVal) + '</td></tr>';
                }
                html += '</table></div>';

                // Footer
                var simdi = new Date().toLocaleString('tr-TR');
                html += '<div style="text-align:center; margin-top:10px; border-top:1px solid #000000; padding-top:8px; font-size:10px; font-weight:600; color:#000000;">';
                html += '<p style="margin:0;">Rapor Tarihi: ' + self._esc(simdi) + '</p>';
                html += '<p style="margin:2px 0 0 0;">Hızlı Kasa POS İstatistik Sistemi</p>';
                html += '</div>';

                html += '</div>';

                if (HK.PrintCore && typeof HK.PrintCore.print === 'function') {
                    HK.PrintCore.print({ type: 'report', html: html });
                } else {
                    throw new Error('PrintCore modülü yüklü değil.');
                }
            } catch (err) {
                console.error('HK.StatisticsDashboard: Print summary receipt error', err);
                if (HK.UIRenderer && HK.UIRenderer.showToast) {
                    HK.UIRenderer.showToast('Fiş yazdırılamadı: ' + (err.message || 'Bilinmeyen hata'), 'error', true);
                }
            }
        },

        _esc: function (s) {
            return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }
    };

    HK.StatisticsDashboard.init();

})(window.HizliKasa);
