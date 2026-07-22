/**
 * Hızlı Kasa - Raporlar İade Modülü
 */
(function(HK) {
    'use strict';

    HK.ReportRefunds = {
        currentPageRefunds: 1,
        perPage: 50,

        init: function() {
            var self = this;
            console.log("HK.ReportRefunds: Init started");

            if (HK.ReportHub) {
                HK.ReportHub.registerReport({
                    id: 'iade-listesi',
                    categoryId: 'iade',
                    title: 'İadeler',
                    icon: '🔙',
                    panelId: 'rapor-iade-listesi',
                    onActivate: function() { self.loadRefunds(1); },
                    hasDateFilter: true,
                    hasSearch: true,
                    searchPlaceholder: 'İade ID veya Kasiyer Ara...',
                    order: 1
                });
            }

            document.addEventListener('hkActiveDepoChanged', function() {
                if (HK.ReportHub && HK.ReportHub.activeCategory === 'iade' && HK.ReportHub.activeReport === 'iade-listesi') {
                    self.loadRefunds(1);
                }
            });
        },

        loadRefunds: async function(page) {
            this.currentPageRefunds = page || 1;
            var tbody = document.getElementById("refund-list-body");
            var pagin = document.getElementById("refund-list-pagination");
            if (!tbody) return;

            tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; padding:40px;">Yükleniyor...</td></tr>';
            
            var dateStart = document.getElementById("rhub-tarih-bas").value;
            var dateEnd = document.getElementById("rhub-tarih-bit").value;
            var search = document.getElementById("rhub-arama-input").value;
            var depoId = HK.DepoManager ? HK.DepoManager.getActiveDepo() : 0;

            try {
                var url = `${kasaAyar.rootApiUrl}hizli-kasa/v1/reports/refunds?page=${this.currentPageRefunds}&per_page=${this.perPage}&date_start=${dateStart}&date_end=${dateEnd}&search=${encodeURIComponent(search)}&depo_id=${depoId}`;
                var response = await fetch(url, { headers: { 'X-WP-Nonce': kasaAyar.nonce } });
                var res = await response.json();

                if (!response.ok) {
                    tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; padding:40px; color:orange;">⚠️ ' + (res.message || 'İadeler yüklenemedi.') + '</td></tr>';
                    return;
                }

                if (HK.ReportsCommon) {
                    HK.ReportsCommon.renderTable(tbody, res.orders, 'refunds');
                    HK.ReportsCommon.renderPagination(pagin, res.max_pages, this.currentPageRefunds, (p) => this.loadRefunds(p));
                }

            } catch (e) {
                console.error("HK.ReportRefunds: Load refunds error", e);
                tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; padding:40px; color:red;">Hata: Veriler çekilemedi.</td></tr>';
            }
        }
    };

    HK.ReportRefunds.init();
})(window.HizliKasa);
