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
            
            // Sekme yüklendiğinde (lazy-load) tetiklenmesi için dinle
            document.addEventListener('hkTabLoaded', function(e) {
                if (e.detail.tab === 'raporlar') {
                    self.bindSubTabEvents();
                    self.loadLogs();
                }
            });

            // Eğer sayfa yüklendiğinde elementler zaten varsa (sayfa yenileme)
            this.bindSubTabEvents();
            this.loadLogs();
        },

        bindSubTabEvents: function() {
            var self = this;
            
            // Alt Sekme Geçişleri
            document.querySelectorAll(".rapor-alt-btn").forEach(function(btn) {
                if (btn.dataset.bound) return;
                btn.dataset.bound = "true";

                btn.addEventListener("click", function() {
                    var target = this.dataset.target;
                    
                    // Butonları güncelle
                    document.querySelectorAll(".rapor-alt-btn").forEach(b => b.classList.remove("aktif"));
                    this.classList.add("aktif");
                    
                    // Panelleri güncelle
                    document.querySelectorAll(".rapor-icerik-paneli").forEach(p => p.style.display = "none");
                    var panel = document.getElementById(target);
                    if (panel) panel.style.display = "block";
                    
                    if (target === 'rapor-siparis-duzenleme') {
                        self.loadLogs();
                    }
                });
            });

            // Yenile Butonu
            var refreshBtn = document.getElementById("rapor-yenile");
            if (refreshBtn && !refreshBtn.dataset.bound) {
                refreshBtn.dataset.bound = "true";
                refreshBtn.addEventListener("click", function() {
                    self.loadLogs();
                });
            }
        },

        loadLogs: async function() {
            var tbody = document.getElementById("edit-logs-body");
            if (!tbody) return;

            var elStart = document.getElementById("rapor-tarih-bas");
            var elEnd = document.getElementById("rapor-tarih-bit");
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

})(window.HizliKasa);
