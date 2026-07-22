/**
 * Hızlı Kasa - Raporlar Arşiv & Envanter Modülü
 */
(function(HK) {
    'use strict';

    HK.ReportArchive = {
        init: function() {
            var self = this;
            console.log("HK.ReportArchive: Init started");

            if (HK.ReportHub) {
                HK.ReportHub.registerReport({
                    id: 'gun-sonu-arsivi',
                    categoryId: 'arsiv',
                    title: 'Gün Sonu Arşivi',
                    icon: '📂',
                    panelId: 'rapor-gun-sonu-arsivi',
                    onActivate: function() { self.loadDayEndHistory(); },
                    hasDateFilter: true,
                    hasSearch: false,
                    order: 1
                });

                HK.ReportHub.registerReport({
                    id: 'depo-sayimlari',
                    categoryId: 'arsiv',
                    title: 'Depo Sayımları',
                    icon: '📋',
                    panelId: 'rapor-depo-sayimlari',
                    onActivate: function() { self.loadSayimHistory(); },
                    hasDateFilter: true,
                    hasSearch: false,
                    order: 2
                });
            }

            document.addEventListener('hkActiveDepoChanged', function() {
                if (HK.ReportHub && HK.ReportHub.activeCategory === 'arsiv') {
                    if (HK.ReportHub.activeReport === 'gun-sonu-arsivi') {
                        self.loadDayEndHistory();
                    } else if (HK.ReportHub.activeReport === 'depo-sayimlari') {
                        self.loadSayimHistory();
                    }
                }
            });
        },

        loadDayEndHistory: async function() {
            var tbody = document.getElementById("day-end-history-body");
            if (!tbody) return;

            tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; padding:40px;">Yükleniyor...</td></tr>';
            
            var dateStart = document.getElementById("rhub-tarih-bas").value;
            var dateEnd = document.getElementById("rhub-tarih-bit").value;
            var depoId = HK.DepoManager ? HK.DepoManager.getActiveDepo() : 0;

            try {
                var url = `${kasaAyar.rootApiUrl}hizli-kasa/v1/reports/day-end-history?date_start=${dateStart}&date_end=${dateEnd}&depo_id=${depoId}`;
                var response = await fetch(url, { headers: { 'X-WP-Nonce': kasaAyar.nonce } });
                var res = await response.json();

                this.renderDayEndHistory(tbody, res);

            } catch (e) {
                console.error("HK.ReportArchive: Load day end history error", e);
                tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; padding:40px; color:red;">Hata: Veriler çekilemedi.</td></tr>';
            }
        },

        renderDayEndHistory: function(tbody, history) {
            tbody.innerHTML = "";
            if (!history || history.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; padding:40px;">Bu tarih aralığında gün sonu kaydı bulunamadı.</td></tr>';
                return;
            }

            var formatVal = function(val) {
                return HK.ReportsCommon ? HK.ReportsCommon.formatCurrency(val) : '₺' + val.toFixed(2);
            };
            var escapeVal = function(val) {
                return HK.ReportsCommon ? HK.ReportsCommon.escapeHtml(val) : val;
            };

            history.forEach(row => {
                var tr = document.createElement("tr");
                tr.innerHTML = `
                    <td style="font-weight:bold;">${escapeVal(row.date_formatted)}</td>
                    <td>${escapeVal(row.sale_count)} Sipariş</td>
                    <td style="color:#27ae60;">${formatVal(row.total_sales)}</td>
                    <td style="color:#e67e22;">${formatVal(row.total_refunds)}</td>
                    <td style="font-weight:bold; background: rgba(0,0,0,0.02);">${formatVal(row.net_total)}</td>
                    <td>
                        <button class="hk-btn-outline btn-view-report" data-date="${row.date}">📊 Raporu Aç</button>
                    </td>
                `;
                tbody.appendChild(tr);
            });

            if (!tbody.dataset.listenerBound) {
                tbody.addEventListener("click", function(e) {
                    var btn = e.target.closest(".btn-view-report");
                    if (btn) {
                        var date = btn.dataset.date;
                        if (HK.DayEndReport) {
                            HK.DayEndReport.raporuGetir('all', date);
                        } else {
                            if (HK.UIRenderer) HK.UIRenderer.showToast("Hata: Gün Sonu modülü yüklenemedi!", "error");
                        }
                    }
                });
                tbody.dataset.listenerBound = "true";
            }
        },

        loadSayimHistory: async function() {
            var tbody = document.getElementById("sayim-history-body");
            if (!tbody) return;

            tbody.innerHTML = '<tr><td colspan="8" style="text-align:center; padding:40px;">Yükleniyor...</td></tr>';
            
            var dateStart = document.getElementById("rhub-tarih-bas").value;
            var dateEnd = document.getElementById("rhub-tarih-bit").value;
            var depoId = HK.DepoManager ? HK.DepoManager.getActiveDepo() : 0;

            try {
                var url = `${kasaAyar.rootApiUrl}hizli-kasa/v1/reports/sayim-history?date_start=${dateStart}&date_end=${dateEnd}&depo_id=${depoId}`;
                var response = await fetch(url, { headers: { 'X-WP-Nonce': kasaAyar.nonce } });
                var res = await response.json();

                this.renderSayimHistory(tbody, res);

            } catch (e) {
                console.error("HK.ReportArchive: Load sayim history error", e);
                tbody.innerHTML = '<tr><td colspan="8" style="text-align:center; padding:40px; color:red;">Hata: Veriler çekilemedi.</td></tr>';
            }
        },

        renderSayimHistory: function(tbody, history) {
            var self = this;
            tbody.innerHTML = "";
            if (!history || history.length === 0) {
                tbody.innerHTML = '<tr><td colspan="8" style="text-align:center; padding:40px;">Sayım kaydı bulunamadı.</td></tr>';
                return;
            }

            var escapeVal = function(val) {
                return HK.ReportsCommon ? HK.ReportsCommon.escapeHtml(val) : val;
            };

            history.forEach(row => {
                var tr = document.createElement("tr");
                tr.dataset.sessionId = row.id;
                
                var statusText = row.status === 'tamamlandi' ? '<span class="status-badge status-tamamlandi" style="color:#10b981; font-weight:bold;">Tamamlandı</span>' : 
                                 (row.status === 'iptal' ? '<span class="status-badge status-iptal" style="color:#ef4444; font-weight:bold;">İptal Edildi</span>' : 
                                 '<span class="status-badge status-aktif" style="color:#3b82f6; font-weight:bold;">Aktif</span>');
                                 
                var updateTypeText = row.update_type === 'partial' ? 'Kısmi Eşitleme' : 
                                     (row.update_type === 'full' ? 'Tam Eşitleme' : '-');

                var diffClass = row.total_diff > 0 ? 'diff-plus' : (row.total_diff < 0 ? 'diff-minus' : 'diff-zero');
                var diffSign = row.total_diff > 0 ? '+' : '';

                tr.innerHTML = `
                    <td style="font-weight:bold;">${escapeVal(row.created_at)}</td>
                    <td>${escapeVal(row.depo_name)}</td>
                    <td>${escapeVal(row.created_by)}</td>
                    <td>${statusText}</td>
                    <td>${updateTypeText}</td>
                    <td>${row.total_items} Kalem</td>
                    <td class="${diffClass}">${diffSign}${row.total_diff}</td>
                    <td style="text-align: center;">
                        <button class="hk-btn-outline btn-sayim-detay" data-session-id="${row.id}">🔍 Detay</button>
                    </td>
                `;
                tbody.appendChild(tr);
            });

            if (!tbody.dataset.sayimListenerBound) {
                tbody.addEventListener("click", function(e) {
                    var btn = e.target.closest(".btn-sayim-detay");
                    if (btn) {
                        var sessionId = parseInt(btn.dataset.sessionId);
                        var row = history.find(r => r.id === sessionId);
                        if (row) {
                            self.toggleSayimDetailRow(btn, row);
                        }
                    }
                });
                tbody.dataset.sayimListenerBound = "true";
            }
        },

        toggleSayimDetailRow: function(btn, session) {
            var mainRow = btn.closest("tr");
            var nextRow = mainRow.nextElementSibling;
            
            if (nextRow && nextRow.classList.contains("sayim-detail-row")) {
                if (nextRow.style.display === "none") {
                    nextRow.style.display = "";
                    btn.textContent = "▲ Kapat";
                } else {
                    nextRow.style.display = "none";
                    btn.textContent = "🔍 Detay";
                }
                return;
            }
            
            var detailRow = document.createElement("tr");
            detailRow.className = "sayim-detail-row";
            
            var items = session.report_data || [];
            var tableHtml = '';

            var escapeVal = function(val) {
                return HK.ReportsCommon ? HK.ReportsCommon.escapeHtml(val) : val;
            };
            
            if (items.length === 0) {
                tableHtml = '<p style="padding: 15px; color: var(--hk-text-muted); text-align: center;">Bu oturumda sayılmış ürün bulunmamaktadır.</p>';
            } else {
                tableHtml = `
                    <div class="sayim-detail-inline">
                        <h5 style="margin: 0 0 10px; font-size: 14px; font-weight: 700; color: var(--hk-text-main);">Sayım Detayları (Seans #${session.id})</h5>
                        <table class="gs-tablo" style="width: 100%; border-collapse: collapse;">
                            <thead>
                                <tr>
                                    <th>Ürün Adı / Varyant</th>
                                    <th>SKU</th>
                                    <th style="width: 120px; text-align: center;">Sistem Stoğu</th>
                                    <th style="width: 120px; text-align: center;">Sayılan Stok</th>
                                    <th style="width: 120px; text-align: center;">Fark</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${items.map(item => {
                                    var name = escapeVal(item.name);
                                    if (item.attributes) {
                                        name += ` <span class="var-badge" style="background: rgba(52, 152, 219, 0.2); color: #3498db; font-size: 9px; padding: 2px 6px; border-radius: 4px; margin-left: 8px; font-weight: 800;">${escapeVal(item.attributes)}</span>`;
                                    }
                                    var diffVal = parseFloat(item.diff);
                                    var diffClass = diffVal > 0 ? 'diff-plus' : (diffVal < 0 ? 'diff-minus' : 'diff-zero');
                                    var diffSign = diffVal > 0 ? '+' : '';
                                    return `
                                        <tr>
                                            <td>${name}</td>
                                            <td>${escapeVal(item.sku)}</td>
                                            <td style="text-align: center;">${parseFloat(item.system_qty)}</td>
                                            <td style="text-align: center; font-weight: bold;">${parseFloat(item.counted_qty)}</td>
                                            <td style="text-align: center;" class="${diffClass}">${diffSign}${diffVal}</td>
                                        </tr>
                                    `;
                                }).join('')}
                            </tbody>
                        </table>
                    </div>
                `;
            }
            
            detailRow.innerHTML = `<td colspan="8">${tableHtml}</td>`;
            mainRow.parentNode.insertBefore(detailRow, mainRow.nextSibling);
            btn.textContent = "▲ Kapat";
        }
    };

    HK.ReportArchive.init();
})(window.HizliKasa);
