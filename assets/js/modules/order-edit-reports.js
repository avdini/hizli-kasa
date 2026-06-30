/**
 * Hızlı Kasa - Sipariş Düzenleme Raporları
 *
 * @package HizliKasa
 */

(function(HK) {
    'use strict';

    HK.OrderEditReports = {
        init: function() {
            var self = this;
            
            // Raporu Hub'a Kaydet
            if (HK.ReportHub) {
                HK.ReportHub.registerReport({
                    id: 'siparis-duzenleme',
                    categoryId: 'iade',
                    title: 'Sipariş Düzenlemeleri',
                    icon: '✏️',
                    panelId: 'rapor-siparis-duzenleme',
                    onActivate: function() { self.loadLogs(); },
                    hasDateFilter: true,
                    hasSearch: false
                });
            }
        },

        loadLogs: async function() {
            var tbody = document.getElementById("edit-logs-body");
            if (!tbody) return;

            var elStart = document.getElementById("rhub-tarih-bas");
            var elEnd = document.getElementById("rhub-tarih-bit");
            var dateStart = elStart ? elStart.value : "";
            var dateEnd = elEnd ? elEnd.value : "";

            tbody.innerHTML = '<tr><td colspan="5" style="text-align:center; padding:40px;">Veriler yükleniyor...</td></tr>';

            try {
                var url = kasaAyar.rootApiUrl + 'hizli-kasa/v1/edit-logs?date_start=' + dateStart + '&date_end=' + dateEnd;
                var response = await fetch(url, {
                    headers: { 'X-WP-Nonce': kasaAyar.nonce }
                });
                var logs = await response.json();

                tbody.innerHTML = "";

                if (logs.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="5" style="text-align:center; padding:40px;">Seçili tarihlerde düzenleme kaydı bulunamadı.</td></tr>';
                    return;
                }

                logs.forEach(function(log) {
                    var changes = JSON.parse(log.new_data);
                    var changesHtml = "";
                    
                    if (Array.isArray(changes)) {
                        changes.forEach(function(c) {
                            changesHtml += `<span class="log-change-item">${c}</span>`;
                        });
                    }

                    var tr = document.createElement("tr");
                    tr.innerHTML = `
                        <td><strong>${log.created_at}</strong></td>
                        <td>${log.user_name}</td>
                        <td><span style="color:var(--hk-accent); font-weight:bold;">#${log.order_id}</span></td>
                        <td>Kasa ${log.kasa_no}</td>
                        <td>${changesHtml}</td>
                    `;
                    tbody.appendChild(tr);
                });

            } catch (e) {
                console.error("Load logs error", e);
                tbody.innerHTML = '<tr><td colspan="5" style="text-align:center; padding:40px; color:red;">Hata: Veriler çekilemedi.</td></tr>';
            }
        }
    };

    HK.OrderEditReports.init();

})(window.HizliKasa);
