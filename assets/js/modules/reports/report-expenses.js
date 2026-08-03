/**
 * Hızlı Kasa - Masraf & Gider Analizi Raporu Modülü
 */
(function (HK) {
    'use strict';

    HK.ExpenseReport = {
        lastData: null,
        charts: {},

        init: function () {
            var self = this;

            if (HK.ReportHub) {
                HK.ReportHub.registerReport({
                    id: 'masraf-analiz',
                    categoryId: 'finans',
                    title: 'Masraf & Gider Analizi',
                    icon: '💸',
                    panelId: 'rapor-masraf-analiz',
                    onActivate: function () { self.load(); },
                    hasDateFilter: true,
                    hasSearch: false,
                    description: 'Harcama kalemleri, ödeme yöntemleri ve kategori bazlı gider analizi',
                    order: 1
                });
            }

            document.addEventListener('hkActiveDepoChanged', function () {
                if (HK.ReportHub && HK.ReportHub.activeCategory === 'finans' && HK.ReportHub.activeReport === 'masraf-analiz') {
                    self.load();
                }
            });

            document.addEventListener('hkThemeChanged', function () {
                if (self.lastData) {
                    self._destroyCharts();
                    self._renderCharts(self.lastData);
                }
            });

            // Masraf Fişi Yazdır Buton Tıklama Dinleyicisi
            document.addEventListener('click', function (e) {
                if (e.target && e.target.closest('#re-print-expense-btn')) {
                    e.preventDefault();
                    self._printExpenseReceipt();
                }
            });
        },

        load: function () {
            var self = this;
            var panel = document.getElementById('rapor-masraf-analiz');
            if (!panel) return;

            var dateStart = (document.getElementById('rhub-tarih-bas') || {}).value || self._today();
            var dateEnd   = (document.getElementById('rhub-tarih-bit') || {}).value || self._today();
            var depoId    = HK.DepoManager ? HK.DepoManager.getActiveDepo() : 0;

            panel.innerHTML = '<div class="re-loading"><div class="hk-spinner"></div><p>Masraf verileri analizi yükleniyor...</p></div>';

            var url = '/hizli-kasa/v2/expenses/summary?date_start=' + dateStart + '&date_end=' + dateEnd + '&depo_id=' + depoId;

            if (typeof wp === 'undefined' || !wp.apiFetch) {
                panel.innerHTML = '<div class="re-error">API bağlantısı kurulamadı.</div>';
                return;
            }

            wp.apiFetch({ path: url })
                .then(function (response) {
                    if (response && response.success && response.data) {
                        self.lastData = response.data;
                        self._renderDashboard(response.data);
                    } else {
                        panel.innerHTML = '<div class="re-error">Veri alınamadı: ' + (response.message || 'Bilinmeyen hata') + '</div>';
                    }
                })
                .catch(function (err) {
                    panel.innerHTML = '<div class="re-error">Sunucu hatası: ' + (err.message || 'Bağlantı hatası') + '</div>';
                });
        },

        _renderDashboard: function (data) {
            var self = this;
            var panel = document.getElementById('rapor-masraf-analiz');
            if (!panel) return;

            var kpi = data.kpi || {};
            var kategoriler = data.kategori_dagilim || [];
            var masraflar = data.masraf_listesi || [];
            var enYuksek = kpi.en_yuksek_kategori || { category: 'Yok', total: 0 };

            var html = '';

            // Master 2-Sütunlu Izgara Yapısı (Sol %66 Tam Yükseklikte Trend Grafiği / Sağ %33 KPI Kartları + Pasta Grafiği)
            html += '<div class="re-master-grid">';

            // Sol Ana Sütun (%66) - TAM YÜKSEKLİKTE MASRAF ZAMAN TRENDİ
            html += '<div class="re-master-left">';
            html +=   '<div class="stat-chart-card re-chart-card re-chart-full-height">';
            html +=     '<div class="stat-chart-header">';
            html +=       '<h4 class="stat-chart-title">📈 Masraf Zaman Trendi</h4>';
            html +=       '<span class="stat-chart-badge">' + (data.gunluk_trend || []).length + ' gün</span>';
            html +=     '</div>';
            html +=     '<div class="stat-chart-body re-chart-body-full"><canvas id="re-chart-trend"></canvas></div>';
            html +=   '</div>';
            html += '</div>';

            // Sağ Ana Sütun (%33) - 4 KPI KARTI (2x2 GRID) + KATEGORİ DAĞILIMI
            html += '<div class="re-master-right">';
            // 4 KPI Kartı 2x2 Izgara Yapısı
            html +=   '<div class="re-kpi-vertical-grid">';
            html +=     self._kpiCard('💸', 'TOPLAM MASRAF', self._currency(kpi.toplam_masraf), kpi.toplam_kayit_sayisi + ' harcama kaydı', 'kpi-masraf-toplam');
            html +=     self._kpiCard('💵', 'NAKİT KASA MASRAFI', self._currency(kpi.nakit_masraf), 'Kasa Bakiyesinden Düşen', 'kpi-masraf-nakit');
            html +=     self._kpiCard('💳', 'KART & IBAN MASRAFI', self._currency(kpi.kart_iban_masraf), 'Harici Banka / Hesaptan', 'kpi-masraf-kart');
            html +=     self._kpiCard('📊', 'EN YÜKSEK GİDER', enYuksek.category, self._currency(enYuksek.total) + ' harcama', 'kpi-masraf-yuksek');
            html +=   '</div>';
            // Kategori Doughnut Grafiği
            html +=   '<div class="stat-chart-card re-chart-card">';
            html +=     '<div class="stat-chart-header">';
            html +=       '<h4 class="stat-chart-title">🍩 Kategori Dağılımı</h4>';
            html +=       '<span class="stat-chart-badge">' + kategoriler.length + ' kategori</span>';
            html +=     '</div>';
            html +=     '<div class="stat-chart-body re-doughnut-wrap"><canvas id="re-chart-category" height="240"></canvas></div>';
            html +=   '</div>';
            html += '</div>';

            html += '</div>';

            // Kategori Özet Tablosu
            if (kategoriler.length > 0) {
                html += '<div class="stat-chart-card re-kat-table-card">';
                html +=   '<div class="stat-chart-header"><h4 class="stat-chart-title">📋 Kategori Bazlı Gider Dağılımı</h4></div>';
                html +=   '<table class="hk-table re-table">';
                html +=     '<thead><tr><th>Kategori</th><th style="text-align:center;">İşlem Adeti</th><th style="text-align:right;">Toplam Tutar</th><th style="text-align:right;">Gider Payı</th></tr></thead>';
                html +=     '<tbody>';
                kategoriler.forEach(function (cat) {
                    html += '<tr>';
                    html +=   '<td><strong>' + self._esc(cat.category) + '</strong></td>';
                    html +=   '<td style="text-align:center;">' + cat.count + ' adet</td>';
                    html +=   '<td style="text-align:right; font-weight:600; color:var(--hk-danger,#ef4444);">' + self._currency(cat.total) + '</td>';
                    html +=   '<td style="text-align:right;"><span class="re-pay-badge">%' + cat.percentage + '</span></td>';
                    html += '</tr>';
                });
                html +=     '</tbody>';
                html +=   '</table>';
                html += '</div>';
            }

            // Detaylı Masraf Kayıt Tablosu (Canlı Arama & Filtre)
            html += '<div class="stat-chart-card re-log-card">';
            html +=   '<div class="stat-chart-header re-log-header">';
            html +=     '<h4 class="stat-chart-title">🧾 Detaylı Masraf Kayıtları</h4>';
            html +=     '<div class="re-filter-tools">';
            html +=       '<input type="text" id="re-search-input" class="hk-input re-search-input" placeholder="🔍 Açıklama veya kategori ara... (örn: Ahmet, Fatura, Çay)" autocomplete="off">';
            html +=       '<select id="re-method-select" class="hk-input re-method-select">';
            html +=         '<option value="tumu">Tüm Yöntemler</option>';
            html +=         '<option value="nakit">💵 Nakit</option>';
            html +=         '<option value="kart">💳 Kart</option>';
            html +=         '<option value="iban">🏦 IBAN</option>';
            html +=       '</select>';
            html +=       '<button id="re-export-csv" class="hk-btn-secondary re-export-btn">📥 CSV İndir</button>';
            html +=     '</div>';
            html +=   '</div>';

            html +=   '<div class="re-table-wrapper">';
            html +=     '<table class="hk-table re-table" id="re-log-table">';
            html +=       '<thead><tr><th>Tarih / Saat</th><th>Kategori</th><th>Yöntem</th><th>Açıklama</th><th>Kaydeden</th><th style="text-align:right;">Tutar</th></tr></thead>';
            html +=       '<tbody id="re-log-tbody">';
            html +=         self._renderLogRows(masraflar);
            html +=       '</tbody>';
            html +=       '<tfoot>';
            html +=         '<tr>';
            html +=           '<td colspan="5" style="text-align:right; font-weight:bold;" id="re-foot-label">TOPLAM (' + masraflar.length + ' harcama):</td>';
            html +=           '<td style="text-align:right; font-weight:bold; color:var(--hk-danger,#ef4444);" id="re-foot-sum">' + self._currency(kpi.toplam_masraf) + '</td>';
            html +=         '</tr>';
            html +=       '</tfoot>';
            html +=     '</table>';
            html +=   '</div>';
            html += '</div>';

            panel.innerHTML = html;

            self._bindEvents(data);
            self._renderCharts(data);
        },

        _renderLogRows: function (rows) {
            var self = this;
            if (!rows || rows.length === 0) {
                return '<tr><td colspan="6" class="masraf-empty-row" style="text-align:center; padding:20px;">Seçilen filtrelere uygun masraf kaydı bulunamadı.</td></tr>';
            }

            return rows.map(function (m) {
                var methodLabel = m.payment_method === 'nakit' ? '💵 Nakit' : (m.payment_method === 'kart' ? '💳 Kart' : '🏦 IBAN');
                var methodClass = 're-method-' + m.payment_method;
                var dtFormatted = m.created_at ? new Date(m.created_at).toLocaleString('tr-TR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : '—';

                return [
                    '<tr>',
                    '  <td><small style="color:var(--hk-text-muted);">' + dtFormatted + '</small></td>',
                    '  <td><span class="re-cat-tag">' + self._esc(m.category) + '</span></td>',
                    '  <td><span class="re-method-badge ' + methodClass + '">' + methodLabel + '</span></td>',
                    '  <td>' + (self._esc(m.description) || '<em style="opacity:0.5;">Açıklama yok</em>') + '</td>',
                    '  <td><small>' + self._esc(m.user_name) + '</small></td>',
                    '  <td style="text-align:right; font-weight:bold; color:var(--hk-danger,#ef4444);">' + self._currency(m.amount) + '</td>',
                    '</tr>'
                ].join('');
            }).join('');
        },

        _bindEvents: function (data) {
            var self = this;
            var searchInput  = document.getElementById('re-search-input');
            var methodSelect = document.getElementById('re-method-select');
            var exportBtn    = document.getElementById('re-export-csv');

            var filterHandler = function () {
                var query  = (searchInput ? searchInput.value : '').toLowerCase().trim();
                var method = methodSelect ? methodSelect.value : 'tumu';

                var masraflar = data.masraf_listesi || [];
                var filtered = masraflar.filter(function (m) {
                    var matchQuery  = !query || (m.category || '').toLowerCase().indexOf(query) > -1 || (m.description || '').toLowerCase().indexOf(query) > -1 || (m.user_name || '').toLowerCase().indexOf(query) > -1;
                    var matchMethod = method === 'tumu' || m.payment_method === method;
                    return matchQuery && matchMethod;
                });

                var tbody = document.getElementById('re-log-tbody');
                if (tbody) tbody.innerHTML = self._renderLogRows(filtered);

                var sum = filtered.reduce(function (acc, curr) { return acc + (parseFloat(curr.amount) || 0); }, 0);
                var footLabel = document.getElementById('re-foot-label');
                var footSum   = document.getElementById('re-foot-sum');
                if (footLabel) footLabel.innerText = 'FİLTRELENEN TOPLAM (' + filtered.length + ' harcama):';
                if (footSum)   footSum.innerText   = self._currency(sum);
            };

            if (searchInput)  searchInput.addEventListener('keyup', filterHandler);
            if (methodSelect) methodSelect.addEventListener('change', filterHandler);

            if (exportBtn) {
                exportBtn.addEventListener('click', function () {
                    self._exportCSV(data.masraf_listesi || []);
                });
            }
        },

        _renderCharts: function (data) {
            var self = this;
            if (typeof Chart === 'undefined') return;

            var isDark = document.getElementById('hizli-kasa-app') &&
                document.getElementById('hizli-kasa-app').classList.contains('theme-dark');

            var gridColor  = isDark ? 'rgba(255,255,255,0.07)' : 'rgba(0,0,0,0.06)';
            var tickColor  = isDark ? '#94a3b8' : '#64748b';
            var tooltipBg  = isDark ? '#1e293b' : '#ffffff';
            var tooltipTxt = isDark ? '#f1f5f9' : '#1e293b';

            Chart.defaults.color = tickColor;
            Chart.defaults.font.family = "'Inter', 'Segoe UI', system-ui, sans-serif";

            var commonTooltip = {
                backgroundColor: tooltipBg,
                titleColor: tooltipTxt,
                bodyColor: tickColor,
                borderColor: isDark ? '#334155' : '#e2e8f0',
                borderWidth: 1,
                padding: 12,
                cornerRadius: 8,
            };

            // 1. Trend Çubuk Grafiği
            var trendCanvas = document.getElementById('re-chart-trend');
            var trend = data.gunluk_trend || [];
            if (trendCanvas && trend.length > 0) {
                self.charts.trend = new Chart(trendCanvas, {
                    type: 'bar',
                    data: {
                        labels: trend.map(function (g) { return g.tarih_kisa; }),
                        datasets: [
                            {
                                label: 'Nakit Masraf (₺)',
                                data: trend.map(function (g) { return g.nakit; }),
                                backgroundColor: '#10b981',
                                borderRadius: 4,
                            },
                            {
                                label: 'Kart & IBAN Masrafı (₺)',
                                data: trend.map(function (g) { return g.kart_iban; }),
                                backgroundColor: '#3b82f6',
                                borderRadius: 4,
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        interaction: { mode: 'index', intersect: false },
                        scales: {
                            x: { grid: { color: gridColor }, ticks: { color: tickColor } },
                            y: {
                                grid: { color: gridColor },
                                ticks: {
                                    color: tickColor,
                                    callback: function (v) { return '₺' + self._num(v); }
                                },
                                beginAtZero: true,
                            }
                        },
                        plugins: {
                            legend: { position: 'top', labels: { color: tickColor, font: { size: 12 } } },
                            tooltip: Object.assign({}, commonTooltip, {
                                callbacks: {
                                    label: function (ctx) {
                                        return ' ' + ctx.dataset.label + ': ₺ ' + self._num(ctx.parsed.y);
                                    }
                                }
                            })
                        }
                    }
                });
            }

            // 2. Kategori Pasta / Doughnut Grafiği
            var catCanvas = document.getElementById('re-chart-category');
            var kategoriler = data.kategori_dagilim || [];
            if (catCanvas && kategoriler.length > 0) {
                var colors = ['#f59e0b', '#ec4899', '#8b5cf6', '#3b82f6', '#10b981', '#06b6d4', '#f97316', '#64748b'];

                self.charts.category = new Chart(catCanvas, {
                    type: 'doughnut',
                    data: {
                        labels: kategoriler.map(function (c) { return c.category; }),
                        datasets: [{
                            data: kategoriler.map(function (c) { return c.total; }),
                            backgroundColor: colors.slice(0, kategoriler.length),
                            borderWidth: 2,
                            borderColor: isDark ? '#1e293b' : '#ffffff',
                            hoverOffset: 6,
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        cutout: '60%',
                        plugins: {
                            legend: {
                                position: 'right',
                                labels: {
                                    color: tickColor,
                                    font: { size: 12, weight: '600' },
                                    padding: 14,
                                    boxWidth: 12,
                                    usePointStyle: true,
                                }
                            },
                            tooltip: Object.assign({}, commonTooltip, {
                                callbacks: {
                                    label: function (ctx) {
                                        var val = ctx.parsed;
                                        var catObj = kategoriler[ctx.dataIndex];
                                        return ' ' + ctx.label + ': ₺ ' + self._num(val) + ' (%' + (catObj ? catObj.percentage : 0) + ')';
                                    }
                                }
                            })
                        }
                    }
                });
            }
        },

        _exportCSV: function (rows) {
            var self = this;
            if (!rows || rows.length === 0) {
                HK.UI && HK.UI.showToast && HK.UI.showToast('İndirilecek masraf kaydı bulunmuyor.', 'warning');
                return;
            }

            var csvContent = "\uFEFFTarih,Kategori,Odeme Yontemi,Aciklama,Kaydeden,Tutar (TL)\n";
            rows.forEach(function (r) {
                var line = [
                    '"' + (r.created_at || '') + '"',
                    '"' + (r.category || '').replace(/"/g, '""') + '"',
                    '"' + (r.payment_method || '') + '"',
                    '"' + (r.description || '').replace(/"/g, '""') + '"',
                    '"' + (r.user_name || '').replace(/"/g, '""') + '"',
                    (r.amount || 0)
                ].join(',');
                csvContent += line + "\n";
            });

            var blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            var url  = URL.createObjectURL(blob);
            var a    = document.createElement('a');
            a.href     = url;
            a.download = 'hk-masraf-raporu-' + self._today() + '.csv';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
        },

        _kpiCard: function (icon, title, value, sub, customClass) {
            return '<div class="stat-kpi-card ' + (customClass || '') + '">'
                + '<div class="stat-kpi-icon">' + icon + '</div>'
                + '<span class="stat-kpi-title">' + title + '</span>'
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

        _printExpenseReceipt: function () {
            var self = this;
            if (!self.lastData) {
                if (HK.UIRenderer && HK.UIRenderer.showToast) {
                    HK.UIRenderer.showToast('Yazdırılacak masraf verisi bulunamadı.', 'warning');
                }
                return;
            }

            try {
                var data = self.lastData;
                var kpi = data.kpi || {};
                var kategoriler = data.kategori_dagilim || [];
                var masraflar = data.masraf_listesi || [];

                var dateStart = (document.getElementById('rhub-tarih-bas') || {}).value || self._today();
                var dateEnd   = (document.getElementById('rhub-tarih-bit') || {}).value || self._today();
                var tarihLabel = (dateStart === dateEnd) ? dateStart : (dateStart + ' - ' + dateEnd);

                var storeName = (window.kasaAyar && (window.kasaAyar.magazaAdi || window.kasaAyar.siteName)) ? (window.kasaAyar.magazaAdi || window.kasaAyar.siteName) : 'HIZLI KASA POS';
                var depoName = '';
                if (HK.DepoManager && typeof HK.DepoManager.getActiveDepoObj === 'function') {
                    var depoObj = HK.DepoManager.getActiveDepoObj();
                    if (depoObj && depoObj.adi) depoName = depoObj.adi;
                }

                var html = '<div class="hk-unified-print-container receipt-zreport" style="font-family:\'Courier New\', \'Consolas\', \'Lucida Console\', \'Monaco\', monospace; color:#000000 !important; background-color:#ffffff !important; width:100%; max-width:300px; margin:0 auto; padding:4px 8px; box-sizing:border-box; font-size:12px; line-height:1.25; letter-spacing:-0.2px; box-shadow:none !important; border:none !important; -webkit-font-smoothing:none !important; -moz-osx-font-smoothing:unset !important; font-smooth:never !important; text-rendering:pixelated !important;">';

                // Header
                html += '<div style="text-align:center; margin-bottom:8px; border-bottom:2px solid #000000; padding-bottom:8px;">';
                html += '<h2 style="margin:0; font-size:16px; font-weight:bold; color:#000000; font-family:\'Courier New\', \'Consolas\', \'Lucida Console\', \'Monaco\', monospace;">' + self._esc(storeName) + '</h2>';
                html += '<p style="margin:4px 0 2px 0; font-size:13px; font-weight:bold; color:#000000; font-family:\'Courier New\', \'Consolas\', \'Lucida Console\', \'Monaco\', monospace;">KASA MASRAF RAPORU</p>';
                html += '<p style="margin:2px 0 0 0; font-size:11px; color:#000000; font-family:\'Courier New\', \'Consolas\', \'Lucida Console\', \'Monaco\', monospace;">Tarih: ' + self._esc(tarihLabel) + '</p>';
                if (depoName) {
                    html += '<p style="margin:2px 0 0 0; font-size:11px; color:#000000; font-family:\'Courier New\', \'Consolas\', \'Lucida Console\', \'Monaco\', monospace;">Depo: ' + self._esc(depoName) + '</p>';
                }
                html += '</div>';

                // Özet Gider
                html += '<div style="margin-bottom:8px;">';
                html += '<p style="font-weight:bold; margin:0 0 4px; font-size:12px; border-bottom:1px solid #000000; padding-bottom:2px; color:#000000;">ÖZET GİDER</p>';
                html += '<table style="width:100%; font-size:12px; border-collapse:collapse; color:#000000; table-layout:fixed;">';
                html += '<tr><td>Harcama Kaydı</td><td style="text-align:right; font-weight:bold;">' + (kpi.toplam_kayit_sayisi || 0) + ' ad.</td></tr>';
                html += '<tr><td>Nakit Masraf</td><td style="text-align:right; font-weight:bold;">' + self._currency(kpi.nakit_masraf) + '</td></tr>';
                html += '<tr><td>Kart / IBAN Masrafı</td><td style="text-align:right; font-weight:bold;">' + self._currency(kpi.kart_iban_masraf) + '</td></tr>';
                html += '<tr style="border-top:2px solid #000000;"><td style="font-weight:bold; font-size:13px; padding-top:3px;">TOPLAM MASRAF</td><td style="text-align:right; font-weight:bold; font-size:13px; padding-top:3px;">' + self._currency(kpi.toplam_masraf) + '</td></tr>';
                html += '</table></div>';

                // Kategori Dağılımı
                if (kategoriler && kategoriler.length > 0) {
                    html += '<div style="margin-bottom:8px;">';
                    html += '<p style="font-weight:bold; margin:0 0 4px; font-size:12px; border-bottom:1px solid #000000; padding-bottom:2px; color:#000000;">KATEGORİ DAĞILIMI</p>';
                    html += '<table style="width:100%; font-size:11px; border-collapse:collapse; color:#000000; table-layout:fixed;">';
                    html += '<tr style="border-bottom:1px solid #000000;"><th style="text-align:left;">Kategori</th><th style="text-align:right; width:45px;">Adet</th><th style="text-align:right; width:75px;">Tutar</th></tr>';
                    kategoriler.forEach(function (c) {
                        html += '<tr>';
                        html += '<td style="padding:1px 0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">' + self._esc(c.category) + ' (%' + c.percentage + ')</td>';
                        html += '<td style="text-align:right; padding:1px 0;">' + (c.count || 0) + '</td>';
                        html += '<td style="text-align:right; padding:1px 0;">' + self._currency(c.total) + '</td>';
                        html += '</tr>';
                    });
                    html += '</table></div>';
                }

                // Masraf Kayıtları Detayı
                if (masraflar && masraflar.length > 0) {
                    html += '<div style="margin-bottom:8px;">';
                    html += '<p style="font-weight:bold; margin:0 0 4px; font-size:12px; border-bottom:1px solid #000000; padding-bottom:2px; color:#000000;">MASRAF KAYITLARI (' + masraflar.length + ')</p>';
                    html += '<table style="width:100%; font-size:11px; border-collapse:collapse; color:#000000; table-layout:fixed;">';
                    html += '<tr style="border-bottom:1px solid #000000;"><th style="text-align:left;">Kat / Açıklama</th><th style="text-align:right; width:75px;">Tutar</th></tr>';
                    masraflar.forEach(function (m) {
                        var dt = m.created_at ? new Date(m.created_at).toLocaleString('tr-TR', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' }) : '';
                        var methodStr = m.payment_method === 'nakit' ? 'Nakit' : (m.payment_method === 'kart' ? 'Kart' : 'IBAN');
                        html += '<tr style="border-bottom:1px stroke #eee;">';
                        html += '<td style="padding:2px 0; word-break:break-all;">';
                        html += '<strong>[' + self._esc(m.category) + ']</strong> ' + self._esc(m.description || '') + '<br>';
                        html += '<small style="font-size:9px; color:#333;">' + dt + ' • ' + methodStr + ' • ' + self._esc(m.user_name || '') + '</small>';
                        html += '</td>';
                        html += '<td style="text-align:right; padding:2px 0; vertical-align:top; font-weight:bold;">' + self._currency(m.amount) + '</td>';
                        html += '</tr>';
                    });
                    html += '</table></div>';
                }

                // Footer
                var simdi = new Date().toLocaleString('tr-TR');
                html += '<div style="text-align:center; margin-top:10px; border-top:1px solid #000000; padding-top:8px; font-size:10px; color:#000000;">';
                html += '<p style="margin:0;">Rapor Tarihi: ' + self._esc(simdi) + '</p>';
                html += '<p style="margin:2px 0 0 0;">Hızlı Kasa POS Masraf Yönetimi</p>';
                html += '</div>';

                html += '</div>';

                if (HK.PrintCore && typeof HK.PrintCore.print === 'function') {
                    HK.PrintCore.print({ type: 'report', html: html });
                } else {
                    throw new Error('PrintCore modülü yüklü değil.');
                }
            } catch (err) {
                console.error('HK.ExpenseReport: Print expense receipt error', err);
                if (HK.UIRenderer && HK.UIRenderer.showToast) {
                    HK.UIRenderer.showToast('Masraf fişi yazdırılamadı: ' + (err.message || 'Bilinmeyen hata'), 'error', true);
                }
            }
        },

        _esc: function (s) {
            return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }
    };

    HK.ExpenseReport.init();

})(window.HizliKasa);
