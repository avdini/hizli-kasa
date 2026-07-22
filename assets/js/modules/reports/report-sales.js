/**
 * Hızlı Kasa - Raporlar Satış Modülü
 */
(function(HK) {
    'use strict';

    HK.ReportSales = {
        currentPageOrders: 1,
        currentPageInternetOrders: 1,
        perPage: 50,

        init: function() {
            var self = this;
            console.log("HK.ReportSales: Init started");

            // Raporları Hub'a Kaydet
            if (HK.ReportHub) {
                HK.ReportHub.registerReport({
                    id: 'tum-siparisler',
                    categoryId: 'satis',
                    title: 'Tüm Siparişler',
                    icon: '🛍️',
                    panelId: 'rapor-tum-siparisler',
                    onActivate: function() { self.loadOrders(1); },
                    hasDateFilter: true,
                    hasSearch: true,
                    searchPlaceholder: 'Sipariş ID veya Ürün Ara...',
                    order: 1
                });

                HK.ReportHub.registerReport({
                    id: 'internet-siparisleri',
                    categoryId: 'satis',
                    title: 'İnternet Siparişleri',
                    icon: '🌐',
                    panelId: 'rapor-internet-siparisleri',
                    onActivate: function() { self.loadInternetOrders(1); },
                    hasDateFilter: true,
                    hasSearch: true,
                    searchPlaceholder: 'Sipariş ID veya Müşteri Ara...',
                    order: 2
                });
            }

            // Depo değiştiğinde ve satış sekmesi aktifse yenile
            document.addEventListener('hkActiveDepoChanged', function() {
                if (HK.ReportHub && HK.ReportHub.activeCategory === 'satis') {
                    if (HK.ReportHub.activeReport === 'tum-siparisler') {
                        self.loadOrders(1);
                    } else if (HK.ReportHub.activeReport === 'internet-siparisleri') {
                        self.loadInternetOrders(1);
                    }
                }
            });
        },

        loadOrders: async function(page) {
            this.currentPageOrders = page || 1;
            var tbody = document.getElementById("all-orders-body");
            var pagin = document.getElementById("all-orders-pagination");
            if (!tbody) return;

            tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; padding:40px;">Yükleniyor...</td></tr>';
            
            var dateStart = document.getElementById("rhub-tarih-bas").value;
            var dateEnd = document.getElementById("rhub-tarih-bit").value;
            var search = document.getElementById("rhub-arama-input").value;
            var depoId = HK.DepoManager ? HK.DepoManager.getActiveDepo() : 0;

            try {
                var url = `${kasaAyar.rootApiUrl}hizli-kasa/v1/reports/orders?page=${this.currentPageOrders}&per_page=${this.perPage}&date_start=${dateStart}&date_end=${dateEnd}&search=${encodeURIComponent(search)}&depo_id=${depoId}`;
                var response = await fetch(url, { headers: { 'X-WP-Nonce': kasaAyar.nonce } });
                var res = await response.json();

                if (!response.ok) {
                    tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; padding:40px; color:orange;">⚠️ ' + (res.message || 'Siparişler yüklenemedi.') + '</td></tr>';
                    return;
                }

                if (HK.ReportsCommon) {
                    HK.ReportsCommon.renderTable(tbody, res.orders, 'orders');
                    HK.ReportsCommon.renderPagination(pagin, res.max_pages, this.currentPageOrders, (p) => this.loadOrders(p));
                }

            } catch (e) {
                console.error("HK.ReportSales: Load orders error", e);
                tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; padding:40px; color:red;">Hata: Veriler çekilemedi.</td></tr>';
            }
        },

        loadInternetOrders: async function(page) {
            this.currentPageInternetOrders = page || 1;
            var tbody = document.getElementById("internet-orders-body");
            var pagin = document.getElementById("internet-orders-pagination");
            if (!tbody) return;

            tbody.innerHTML = '<tr><td colspan="7" style="text-align:center; padding:40px;">Yükleniyor...</td></tr>';
            
            var dateStart = document.getElementById("rhub-tarih-bas").value;
            var dateEnd = document.getElementById("rhub-tarih-bit").value;
            var search = document.getElementById("rhub-arama-input").value;

            try {
                var url = `${kasaAyar.rootApiUrl}hizli-kasa/v1/reports/internet-orders?page=${this.currentPageInternetOrders}&per_page=${this.perPage}&date_start=${dateStart}&date_end=${dateEnd}&search=${encodeURIComponent(search)}`;
                var response = await fetch(url, { headers: { 'X-WP-Nonce': kasaAyar.nonce } });
                var res = await response.json();

                if (!response.ok) {
                    tbody.innerHTML = '<tr><td colspan="7" style="text-align:center; padding:40px; color:orange;">⚠️ ' + (res.message || 'İnternet siparişleri yüklenemedi.') + '</td></tr>';
                    return;
                }

                if (HK.ReportsCommon) {
                    HK.ReportsCommon.renderTable(tbody, res.orders, 'internet_orders');
                    HK.ReportsCommon.renderPagination(pagin, res.max_pages, this.currentPageInternetOrders, (p) => this.loadInternetOrders(p));
                }

            } catch (e) {
                console.error("HK.ReportSales: Load internet orders error", e);
                tbody.innerHTML = '<tr><td colspan="7" style="text-align:center; padding:40px; color:red;">Hata: Veriler çekilemedi.</td></tr>';
            }
        }
    };

    HK.ReportSales.init();
})(window.HizliKasa);
