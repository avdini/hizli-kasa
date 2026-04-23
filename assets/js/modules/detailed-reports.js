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
            
            document.addEventListener('hkTabLoaded', function(e) {
                if (e.detail.tab === 'raporlar') {
                    self.bindEvents();
                }
            });

            // Sayfa zaten yüklüyse
            if (document.querySelector('.rapor-alt-sekmeler')) {
                self.bindEvents();
            }
        },

        bindEvents: function() {
            var self = this;
            
            // Alt sekme geçişleri (Mevcut logic ile uyumlu)
            document.querySelectorAll(".rapor-alt-btn").forEach(function(btn) {
                if (btn.dataset.detailedBound) return;
                btn.dataset.detailedBound = "true";

                btn.addEventListener("click", function() {
                    var target = this.dataset.target;
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
                orderSearch.addEventListener("keyup", HK.utils.debounce(function() {
                    self.loadOrders(1);
                }, 500));
            }

            var refundSearch = document.getElementById("refund-search-input");
            if (refundSearch) {
                refundSearch.addEventListener("keyup", HK.utils.debounce(function() {
                    self.loadRefunds(1);
                }, 500));
            }

            // Global rapor yenile butonu
            var refreshBtn = document.getElementById("rapor-yenile");
            if (refreshBtn) {
                refreshBtn.addEventListener("click", function() {
                    var activeTarget = document.querySelector(".rapor-alt-btn.aktif").dataset.target;
                    if (activeTarget === 'rapor-tum-siparisler') self.loadOrders(1);
                    if (activeTarget === 'rapor-iade-listesi') self.loadRefunds(1);
                });
            }
        },

        loadOrders: async function(page) {
            this.currentPageOrders = page || 1;
            var tbody = document.getElementById("all-orders-body");
            var pagin = document.getElementById("all-orders-pagination");
            if (!tbody) return;

            tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; padding:40px;">Yükleniyor...</td></tr>';
            
            var dateStart = document.getElementById("rapor-tarih-bas").value;
            var dateEnd = document.getElementById("rapor-tarih-bit").value;
            var search = document.getElementById("order-search-input").value;

            try {
                var url = `${kasaAyar.rootApiUrl}hizli-kasa/v1/reports/orders?page=${this.currentPageOrders}&per_page=${this.per_page}&date_start=${dateStart}&date_end=${dateEnd}&search=${encodeURIComponent(search)}`;
                var response = await fetch(url, { headers: { 'X-WP-Nonce': kasaAyar.nonce } });
                var res = await response.json();

                this.renderTable(tbody, res.orders, 'orders');
                this.renderPagination(pagin, res.max_pages, this.currentPageOrders, 'orders');

            } catch (e) {
                console.error("Load orders error", e);
                tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; padding:40px; color:red;">Hata: Veriler çekilemedi.</td></tr>';
            }
        },

        loadRefunds: async function(page) {
            this.currentPageRefunds = page || 1;
            var tbody = document.getElementById("refund-list-body");
            var pagin = document.getElementById("refund-list-pagination");
            if (!tbody) return;

            tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; padding:40px;">Yükleniyor...</td></tr>';
            
            var dateStart = document.getElementById("rapor-tarih-bas").value;
            var dateEnd = document.getElementById("rapor-tarih-bit").value;
            var search = document.getElementById("refund-search-input").value;

            try {
                var url = `${kasaAyar.rootApiUrl}hizli-kasa/v1/reports/refunds?page=${this.currentPageRefunds}&per_page=${this.per_page}&date_start=${dateStart}&date_end=${dateEnd}&search=${encodeURIComponent(search)}`;
                var response = await fetch(url, { headers: { 'X-WP-Nonce': kasaAyar.nonce } });
                var res = await response.json();

                this.renderTable(tbody, res.orders, 'refunds');
                this.renderPagination(pagin, res.max_pages, this.currentPageRefunds, 'refunds');

            } catch (e) {
                console.error("Load refunds error", e);
                tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; padding:40px; color:red;">Hata: Veriler çekilemedi.</td></tr>';
            }
        },

        renderTable: function(tbody, orders, type) {
            var self = this;
            tbody.innerHTML = "";
            if (orders.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; padding:40px;">Kayıt bulunamadı.</td></tr>';
                return;
            }

            orders.forEach(order => {
                var itemsHtml = '<ul class="order-item-list">';
                order.items.forEach(item => {
                    var metaStr = "";
                    Object.keys(item.meta).forEach(k => {
                        metaStr += `<span class="meta-tag" title="${k}">${item.meta[k]}</span>`;
                    });
                    itemsHtml += `<li><strong>${item.name}</strong> x ${item.qty} ${metaStr}</li>`;
                });
                itemsHtml += '</ul>';

                var metaDetails = "";
                Object.keys(order.meta).forEach(k => {
                    metaDetails += `<div class="meta-item"><span class="meta-key">${k}:</span> ${order.meta[k]}</div>`;
                });

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

                // Meta detay satırı
                var detailTr = document.createElement("tr");
                detailTr.className = "meta-details-row";
                detailTr.id = `meta-row-${order.id}`;
                detailTr.innerHTML = `<td colspan="6"><div class="meta-details-container">${metaDetails || 'Meta bilgisi yok.'}</div></td>`;
                tbody.appendChild(detailTr);
            });

            // Detay butonu eventleri
            tbody.querySelectorAll(".btn-detail").forEach(btn => {
                btn.addEventListener("click", function() {
                    var id = this.dataset.id;
                    var row = document.getElementById(`meta-row-${id}`);
                    if (row.style.display === "table-row") {
                        row.style.display = "none";
                    } else {
                        row.style.display = "table-row";
                    }
                });
            });
        },

        renderPagination: function(container, maxPages, currentPage, type) {
            var self = this;
            container.innerHTML = "";
            if (maxPages <= 1) return;

            for (let i = 1; i <= maxPages; i++) {
                var btn = document.createElement("button");
                btn.className = `hk-page-btn ${i === currentPage ? 'aktif' : ''}`;
                btn.innerText = i;
                btn.addEventListener("click", function() {
                    if (type === 'orders') self.loadOrders(i);
                    else self.loadRefunds(i);
                });
                container.appendChild(btn);
            }
        }
    };

    // Debounce helper if not exists
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
