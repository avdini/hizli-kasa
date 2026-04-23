/**
 * Hızlı Kasa - Detaylı Raporlar (Siparişler ve İadeler)
 *
 * @package HizliKasa
 */

(function(HK) {
    'use strict';

    HK.DetailedReports = {
        currentPageOrders: 1,
        currentPageRefunds: 1,
        perPage: 20,

        init: function() {
            var self = this;
            console.log("HK.DetailedReports: Init started");
            
            document.addEventListener('hkTabLoaded', function(e) {
                console.log("HK.DetailedReports: hkTabLoaded event received", e.detail.tab);
                if (e.detail.tab === 'raporlar') {
                    self.bindEvents();
                }
            });

            // Sayfa zaten yüklüyse
            if (document.querySelector('.rapor-alt-sekmeler')) {
                console.log("HK.DetailedReports: Tab buttons found in DOM, binding events immediately");
                self.bindEvents();
            }
        },

        bindEvents: function() {
            var self = this;
            
            // Alt sekme geçişleri
            document.querySelectorAll(".rapor-alt-btn").forEach(function(btn) {
                if (btn.dataset.detailedBound) return;
                btn.dataset.detailedBound = "true";

                btn.addEventListener("click", function() {
                    var target = this.dataset.target;
                    console.log("HK.DetailedReports: Sub-tab clicked", target);
                    if (target === 'rapor-tum-siparisler') {
                        self.loadOrders(1);
                    } else if (target === 'rapor-iade-listesi') {
                        self.loadRefunds(1);
                    }
                });
            });

            // Arama inputları
            var orderSearch = document.getElementById("order-search-input");
            if (orderSearch) {
                console.log("HK.DetailedReports: Order search input found");
                orderSearch.addEventListener("keyup", HK.utils.debounce(function() {
                    console.log("HK.DetailedReports: Order search triggered", this.value);
                    self.loadOrders(1);
                }, 500));
            }

            var refundSearch = document.getElementById("refund-search-input");
            if (refundSearch) {
                console.log("HK.DetailedReports: Refund search input found");
                refundSearch.addEventListener("keyup", HK.utils.debounce(function() {
                    console.log("HK.DetailedReports: Refund search triggered", this.value);
                    self.loadRefunds(1);
                }, 500));
            }

            // Global rapor yenile butonu
            var refreshBtn = document.getElementById("rapor-yenile");
            if (refreshBtn) {
                console.log("HK.DetailedReports: Refresh button found");
                refreshBtn.addEventListener("click", function() {
                    var activeBtn = document.querySelector(".rapor-alt-btn.aktif");
                    if (!activeBtn) return;
                    var activeTarget = activeBtn.dataset.target;
                    console.log("HK.DetailedReports: Refresh clicked, active target:", activeTarget);
                    if (activeTarget === 'rapor-tum-siparisler') self.loadOrders(1);
                    if (activeTarget === 'rapor-iade-listesi') self.loadRefunds(1);
                });
            }
        },

        loadOrders: async function(page) {
            this.currentPageOrders = page || 1;
            var tbody = document.getElementById("all-orders-body");
            var pagin = document.getElementById("all-orders-pagination");
            if (!tbody) {
                console.warn("HK.DetailedReports: all-orders-body not found");
                return;
            }

            tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; padding:40px;">Yükleniyor...</td></tr>';
            
            var dateStart = document.getElementById("rapor-tarih-bas").value;
            var dateEnd = document.getElementById("rapor-tarih-bit").value;
            var search = document.getElementById("order-search-input").value;

            try {
                var url = `${kasaAyar.rootApiUrl}hizli-kasa/v1/reports/orders?page=${this.currentPageOrders}&per_page=${this.perPage}&date_start=${dateStart}&date_end=${dateEnd}&search=${encodeURIComponent(search)}`;
                console.log("HK.DetailedReports: Fetching orders from:", url);
                
                var response = await fetch(url, { headers: { 'X-WP-Nonce': kasaAyar.nonce } });
                console.log("HK.DetailedReports: Response status:", response.status);
                
                var res = await response.json();
                console.log("HK.DetailedReports: Orders data received:", res);

                this.renderTable(tbody, res.orders, 'orders');
                this.renderPagination(pagin, res.max_pages, this.currentPageOrders, 'orders');

            } catch (e) {
                console.error("HK.DetailedReports: Load orders error", e);
                tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; padding:40px; color:red;">Hata: Veriler çekilemedi. Konsola bakınız.</td></tr>';
            }
        },

        loadRefunds: async function(page) {
            this.currentPageRefunds = page || 1;
            var tbody = document.getElementById("refund-list-body");
            var pagin = document.getElementById("refund-list-pagination");
            if (!tbody) {
                console.warn("HK.DetailedReports: refund-list-body not found");
                return;
            }

            tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; padding:40px;">Yükleniyor...</td></tr>';
            
            var dateStart = document.getElementById("rapor-tarih-bas").value;
            var dateEnd = document.getElementById("rapor-tarih-bit").value;
            var search = document.getElementById("refund-search-input").value;

            try {
                var url = `${kasaAyar.rootApiUrl}hizli-kasa/v1/reports/refunds?page=${this.currentPageRefunds}&per_page=${this.perPage}&date_start=${dateStart}&date_end=${dateEnd}&search=${encodeURIComponent(search)}`;
                console.log("HK.DetailedReports: Fetching refunds from:", url);
                
                var response = await fetch(url, { headers: { 'X-WP-Nonce': kasaAyar.nonce } });
                console.log("HK.DetailedReports: Response status:", response.status);
                
                var res = await response.json();
                console.log("HK.DetailedReports: Refunds data received:", res);

                this.renderTable(tbody, res.orders, 'refunds');
                this.renderPagination(pagin, res.max_pages, this.currentPageRefunds, 'refunds');

            } catch (e) {
                console.error("HK.DetailedReports: Load refunds error", e);
                tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; padding:40px; color:red;">Hata: Veriler çekilemedi. Konsola bakınız.</td></tr>';
            }
        },

        renderTable: function(tbody, orders, type) {
            console.log(`HK.DetailedReports: Rendering ${type} table, count:`, orders ? orders.length : 0);
            tbody.innerHTML = "";
            if (!orders || orders.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; padding:40px;">Kayıt bulunamadı.</td></tr>';
                return;
            }

            orders.forEach(order => {
                var itemsHtml = '<ul class="order-item-list">';
                order.items.forEach(item => {
                    var metaStr = "";
                    if (item.meta) {
                        Object.keys(item.meta).forEach(k => {
                            metaStr += `<span class="meta-tag" title="${k}">${item.meta[k]}</span>`;
                        });
                    }
                    var priceFormatted = parseFloat(item.price || 0).toLocaleString('tr-TR', {minimumFractionDigits:2});
                    var subtotalFormatted = parseFloat(item.subtotal || 0).toLocaleString('tr-TR', {minimumFractionDigits:2});
                    itemsHtml += `<li><strong>${item.name}</strong> x ${item.qty} <span class="item-price-calc">(${priceFormatted} TL x ${item.qty} = ${subtotalFormatted} TL)</span> ${metaStr}</li>`;
                });
                itemsHtml += '</ul>';

                var metaDetails = "";
                if (order.meta) {
                    Object.keys(order.meta).forEach(k => {
                        metaDetails += `<div class="meta-item"><span class="meta-key">${k}:</span> ${order.meta[k]}</div>`;
                    });
                }

                var tr = document.createElement("tr");
                tr.innerHTML = `
                    <td>${order.date}</td>
                    <td><span style="font-weight:bold; color:var(--hk-accent);">#${order.id}</span></td>
                    <td>${order.cashier} <br><small style="color:#888;">${order.kasa_no}</small></td>
                    <td>${itemsHtml}</td>
                    <td style="font-weight:bold;">${parseFloat(order.total).toLocaleString('tr-TR', {minimumFractionDigits:2})} TL</td>
                    <td><button class="btn-detail" data-id="${order.id}">🔍 Meta</button></td>
                `;
                tbody.appendChild(tr);

                var detailTr = document.createElement("tr");
                detailTr.className = "meta-details-row";
                detailTr.id = `meta-row-${order.id}`;
                detailTr.innerHTML = `<td colspan="6"><div class="meta-details-container">${metaDetails || 'Meta bilgisi yok.'}</div></td>`;
                tbody.appendChild(detailTr);
            });

            tbody.querySelectorAll(".btn-detail").forEach(btn => {
                btn.addEventListener("click", function() {
                    var id = this.dataset.id;
                    var row = document.getElementById(`meta-row-${id}`);
                    if (row) {
                        row.style.display = (row.style.display === "table-row") ? "none" : "table-row";
                    }
                });
            });
        },

        renderPagination: function(container, maxPages, currentPage, type) {
            container.innerHTML = "";
            if (!maxPages || maxPages <= 1) return;

            for (let i = 1; i <= maxPages; i++) {
                var btn = document.createElement("button");
                btn.className = `hk-page-btn ${i === currentPage ? 'aktif' : ''}`;
                btn.innerText = i;
                btn.addEventListener("click", () => {
                    if (type === 'orders') this.loadOrders(i);
                    else this.loadRefunds(i);
                });
                container.appendChild(btn);
            }
        }
    };

    if (!HK.utils) HK.utils = {};
    if (!HK.utils.debounce) {
        HK.utils.debounce = function(func, wait) {
            let timeout;
            return function() {
                const context = this, args = arguments;
                clearTimeout(timeout);
                timeout = setTimeout(() => func.apply(context, args), wait);
            };
        };
    }

    HK.DetailedReports.init();

})(window.HizliKasa);
