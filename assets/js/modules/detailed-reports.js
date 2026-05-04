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
        perPage: 50,

        init: function() {
            var self = this;
            console.log("HK.DetailedReports: Init started");
            
            document.addEventListener('hkTabLoaded', function(e) {
                console.log("HK.DetailedReports: hkTabLoaded event received", e.detail.tab);
                if (e.detail.tab === 'raporlar') {
                    self.bindEvents();
                    self.loadOrders(1); // Varsayılan olarak siparişleri yükle
                }
            });

            // Sayfa zaten yüklüyse
            if (document.querySelector('.rapor-alt-sekmeler')) {
                console.log("HK.DetailedReports: Tab buttons found in DOM, binding events immediately");
                self.bindEvents();
                self.loadOrders(1);
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
                    
                    // Buton aktiflik durumunu güncelle
                    document.querySelectorAll(".rapor-alt-btn").forEach(b => b.classList.remove("aktif"));
                    this.classList.add("aktif");

                    // Panelleri gizle/göster
                    document.querySelectorAll(".rapor-icerik-paneli").forEach(p => {
                        p.classList.remove("aktif");
                        p.style.display = "none";
                    });

                    var panel = document.getElementById(target);
                    if (panel) {
                        panel.classList.add("aktif");
                        panel.style.display = "block";
                    }

                    if (target === 'rapor-tum-siparisler') {
                        self.loadOrders(1);
                    } else if (target === 'rapor-iade-listesi') {
                        self.loadRefunds(1);
                    } else if (target === 'rapor-gun-sonu-arsivi') {
                        self.loadDayEndHistory();
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
                    if (activeTarget === 'rapor-gun-sonu-arsivi') self.loadDayEndHistory();
                });
            }

            // Depo değiştiğinde aktif raporu yenile
            document.addEventListener('hkActiveDepoChanged', function() {
                var activeBtn = document.querySelector(".rapor-alt-btn.aktif");
                if (!activeBtn) return;
                var activeTarget = activeBtn.dataset.target;
                
                // Sadece raporlar sekmesi görünürse yenile
                if (document.getElementById('rapor-tum-siparisler')?.offsetParent !== null) {
                    if (activeTarget === 'rapor-tum-siparisler') self.loadOrders(1);
                    else if (activeTarget === 'rapor-iade-listesi') self.loadRefunds(1);
                    else if (activeTarget === 'rapor-gun-sonu-arsivi') self.loadDayEndHistory();
                }
            });
        },

        fieldLabelMap: {
            _hk_cikis_depo_id: 'Depo ID',
            _hk_cikis_depo_adi: 'Depo',
            _hizli_kasa_kasa_no: 'Kasa No',
            _hizli_kasa_kasiyer: 'Kasiyer',
            _hizli_kasa_musteri_telefon: 'Telefon',
            _hizli_kasa_original_order: 'Orijinal Sipariş',
            _hizli_kasa_is_refund: 'İade Kaydı',
            _odeme_nakit: 'Nakit Ödeme',
            _odeme_kart: 'Kart Ödeme',
            _odeme_iban: 'IBAN Ödeme',
            _ara_toplam: 'Ara Toplam',
            _etiket_toplami: 'Etiket Toplamı',
            _hk_kaynak: 'Kaynak',
            _hk_has_refund: 'İade Durumu',
            _hk_refunded_qty: 'İade Edilen Adet',
            _hk_is_fully_refunded: 'Tam İade',
            _hk_iade_depo_ozet: 'İade Edilen Depolar',
            _hk_refunded_discount: 'İade Edilen İskonto'
        },

        currencyFieldKeys: ['_odeme_nakit', '_odeme_kart', '_odeme_iban', '_ara_toplam', '_etiket_toplami', '_hk_refunded_discount'],

        escapeHtml: function(value) {
            return String(value === null || value === undefined ? '' : value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        },

        formatCurrency: function(value) {
            var amount = Number(value);
            if (isNaN(amount)) return '-';
            return '₺ ' + amount.toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        },

        humanizeKey: function(key) {
            if (!key) return '-';
            return key
                .replace(/^_+/, '')
                .replace(/hizli_kasa/g, '')
                .replace(/hk_/g, '')
                .replace(/_/g, ' ')
                .replace(/\s+/g, ' ')
                .trim()
                .replace(/\b\w/g, function(l) { return l.toUpperCase(); });
        },

        formatMetaField: function(key, value) {
            var normalizedKey = String(key || '');
            var label = this.fieldLabelMap[normalizedKey] || this.humanizeKey(normalizedKey);
            var raw = value;
            var display = '-';

            if (raw !== null && raw !== undefined && raw !== '') {
                if (this.currencyFieldKeys.indexOf(normalizedKey) !== -1) {
                    display = this.formatCurrency(raw);
                } else if (normalizedKey === '_hk_cikis_depo_id') {
                    return null; // Depo ID gitsin
                } else if (normalizedKey === '_hizli_kasa_is_refund' || normalizedKey === '_hk_has_refund' || normalizedKey === '_hk_is_fully_refunded') {
                    display = (String(raw) === 'yes') ? 'Evet' : 'Hayır';
                } else if (normalizedKey === '_hk_kaynak' || normalizedKey === '_hizli_kasa_kaynak') {
                    if (raw === 'pos_satis') display = 'Kasa Satışı';
                    else if (raw === 'pos_iade') display = 'Kasa İadesi';
                    else display = this.escapeHtml(raw);
                } else if (normalizedKey === '_hk_iade_depo_ozet') {
                    try {
                        var obj = typeof raw === 'string' ? JSON.parse(raw) : raw;
                        var parts = [];
                        for (var id in obj) {
                            parts.push('Depo #' + id + ': ' + obj[id] + ' adet');
                        }
                        display = parts.join(', ');
                    } catch (e) {
                        display = this.escapeHtml(raw);
                    }
                } else {
                    display = this.escapeHtml(raw);
                }
            }

            return { label: label, value: display };
        },

        getItemImageUrl: function(item) {
            if (!item) return '';
            if (typeof item.image === 'string' && item.image) return item.image;
            if (typeof item.thumbnail === 'string' && item.thumbnail) return item.thumbnail;
            if (item.images && item.images.length && item.images[0] && item.images[0].src) return item.images[0].src;
            return '';
        },

        renderItemMetaTags: function(meta) {
            var self = this;
            if (!meta || typeof meta !== 'object') return '';

            return Object.keys(meta).map(function(key) {
                var field = self.formatMetaField(key, meta[key]);
                if (!field) return '';
                return '<span class="meta-tag" title="' + self.escapeHtml(field.label) + '">' + self.escapeHtml(field.label) + ': ' + field.value + '</span>';
            }).join('');
        },

        renderOrderItemRow: function(item) {
            var imageUrl = this.getItemImageUrl(item);
            var imageHtml = imageUrl
                ? '<img class="report-item-image" src="' + this.escapeHtml(imageUrl) + '" alt="' + this.escapeHtml(item.name || 'Ürün') + '" loading="lazy" onerror="this.style.display=\'none\'; this.nextElementSibling.style.display=\'flex\';" /><div class="report-item-image-placeholder" style="display:none;">🖼️</div>'
                : '<div class="report-item-image-placeholder">🖼️</div>';

            var priceFormatted = this.formatCurrency(item.price || 0);
            var subtotalFormatted = this.formatCurrency(item.subtotal || 0);
            var qty = Number(item.qty || 0);
            var metaStr = this.renderItemMetaTags(item.meta);

            return (
                '<li class="order-item-row">' +
                    '<div class="report-item-media">' + imageHtml + '</div>' +
                    '<div class="report-item-content">' +
                        '<strong>' + this.escapeHtml(item.name || 'Ürün') + '</strong>' +
                        '<div class="report-item-subline">Adet: ' + this.escapeHtml(qty) + ' <span class="item-price-calc">(' + priceFormatted + ' x ' + this.escapeHtml(qty) + ' = ' + subtotalFormatted + ')</span></div>' +
                        (metaStr ? '<div class="report-item-meta-tags">' + metaStr + '</div>' : '') +
                    '</div>' +
                '</li>'
            );
        },

        renderOrderItemsList: function(items) {
            var self = this;
            if (!Array.isArray(items) || !items.length) {
                return '<div class="report-empty-items">Ürün bulunamadı.</div>';
            }
            var firstItemHtml = self.renderOrderItemRow(items[0]);
            if (items.length === 1) {
                return '<ul class="order-item-list">' + firstItemHtml + '</ul>';
            }

            var remainingItemsHtml = items.slice(1).map(function(item) {
                return self.renderOrderItemRow(item);
            }).join('');

            return '' +
                '<div class="report-item-list-wrapper">' +
                    '<ul class="order-item-list">' + firstItemHtml + '</ul>' +
                    '<div class="report-items-toggle-row">' +
                        '<button type="button" class="report-items-toggle-icon-btn is-closed" data-more-count="' + (items.length - 1) + '" aria-expanded="false" aria-label="Diğer ürünleri göster" title="Diğer ürünleri göster">' +
                            '<span class="report-toggle-icon" aria-hidden="true">+' + (items.length - 1) + '</span>' +
                        '</button>' +
                    '</div>' +
                    '<div class="report-items-extra" style="display:none;">' +
                        '<ul class="order-item-list order-item-list-extra">' + remainingItemsHtml + '</ul>' +
                    '</div>' +
                '</div>';
        },

        renderOrderMetaDetails: function(meta) {
            var self = this;
            if (!meta || typeof meta !== 'object' || !Object.keys(meta).length) {
                return 'Detay bilgisi yok.';
            }

            return Object.keys(meta).map(function(key) {
                var field = self.formatMetaField(key, meta[key]);
                if (!field) return '';
                return '<div class="meta-item"><span class="meta-key">' + self.escapeHtml(field.label) + ':</span><span class="meta-value">' + field.value + '</span></div>';
            }).join('');
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
            var depoId = HK.DepoManager ? HK.DepoManager.getActiveDepo() : 0;

            try {
                var url = `${kasaAyar.rootApiUrl}hizli-kasa/v1/reports/orders?page=${this.currentPageOrders}&per_page=${this.perPage}&date_start=${dateStart}&date_end=${dateEnd}&search=${encodeURIComponent(search)}&depo_id=${depoId}`;
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
            var depoId = HK.DepoManager ? HK.DepoManager.getActiveDepo() : 0;

            try {
                var url = `${kasaAyar.rootApiUrl}hizli-kasa/v1/reports/refunds?page=${this.currentPageRefunds}&per_page=${this.perPage}&date_start=${dateStart}&date_end=${dateEnd}&search=${encodeURIComponent(search)}&depo_id=${depoId}`;
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
                var itemsHtml = this.renderOrderItemsList(order.items);
                var metaDetails = this.renderOrderMetaDetails(order.meta);
                var orderTotal = this.formatCurrency(order.total || 0);
                var kasaNoLabel = order.kasa_no ? ('Kasa: ' + this.escapeHtml(order.kasa_no)) : '-';

                var tr = document.createElement("tr");
                var actionButtons = '<button class="btn-detail" data-id="' + this.escapeHtml(order.id || '') + '">🔍 Detay</button>';
                if (type === 'orders') {
                    actionButtons += '<button class="btn-reprint" data-id="' + this.escapeHtml(order.id || '') + '">🧾 Fiş Yazdır</button>';
                }
                tr.innerHTML = `
                    <td>${this.escapeHtml(order.date || '-')}</td>
                    <td><span class="report-order-id">#${this.escapeHtml(order.id || '-')}</span></td>
                    <td>${this.escapeHtml(order.cashier || '-')} <br><small class="report-kasa-no">${kasaNoLabel}</small></td>
                    <td>${itemsHtml}</td>
                    <td class="report-total-cell">${orderTotal}</td>
                    <td><div class="report-action-buttons">${actionButtons}</div></td>
                `;
                tbody.appendChild(tr);

                var detailTr = document.createElement("tr");
                detailTr.className = "meta-details-row";
                detailTr.id = `meta-row-${order.id}`;
                detailTr.innerHTML = `<td colspan="6"><div class="meta-details-container">${metaDetails || 'Detay bilgisi yok.'}</div></td>`;
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

            tbody.querySelectorAll(".report-items-toggle-row").forEach(function(row) {
                row.style.cursor = "pointer";
                row.addEventListener("click", function(e) {
                    var wrapper = this.closest(".report-item-list-wrapper");
                    if (!wrapper) return;

                    var extra = wrapper.querySelector(".report-items-extra");
                    if (!extra) return;
                    var btn = wrapper.querySelector(".report-items-toggle-icon-btn");
                    var label = wrapper.querySelector(".report-items-toggle-label");
                    var moreCount = label ? Number(label.dataset.moreCount || 0) : 0;

                    var isOpen = extra.style.display === "block";
                    extra.style.display = isOpen ? "none" : "block";
                    
                    if (btn) {
                        var moreCount = btn.dataset.moreCount;
                        btn.setAttribute("aria-expanded", isOpen ? "false" : "true");
                        btn.setAttribute("aria-label", isOpen ? "Diğer ürünleri göster" : "Diğer ürünleri gizle");
                        btn.setAttribute("title", isOpen ? "Diğer ürünleri göster" : "Diğer ürünleri gizle");
                        btn.classList.toggle("is-open", !isOpen);
                        btn.classList.toggle("is-closed", isOpen);
                        
                        var icon = btn.querySelector(".report-toggle-icon");
                        if (icon) {
                            icon.textContent = isOpen ? ("+" + moreCount) : "✕";
                        }
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
        },

        loadDayEndHistory: async function() {
            var tbody = document.getElementById("day-end-history-body");
            if (!tbody) return;

            tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; padding:40px;">Yükleniyor...</td></tr>';
            
            var dateStart = document.getElementById("rapor-tarih-bas").value;
            var dateEnd = document.getElementById("rapor-tarih-bit").value;
            var depoId = HK.DepoManager ? HK.DepoManager.getActiveDepo() : 0;

            try {
                var url = `${kasaAyar.rootApiUrl}hizli-kasa/v1/reports/day-end-history?date_start=${dateStart}&date_end=${dateEnd}&depo_id=${depoId}`;
                var response = await fetch(url, { headers: { 'X-WP-Nonce': kasaAyar.nonce } });
                var res = await response.json();

                this.renderDayEndHistory(tbody, res);

            } catch (e) {
                console.error("HK.DetailedReports: Load day end history error", e);
                tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; padding:40px; color:red;">Hata: Veriler çekilemedi.</td></tr>';
            }
        },

        renderDayEndHistory: function(tbody, history) {
            var self = this;
            tbody.innerHTML = "";
            if (!history || history.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; padding:40px;">Bu tarih aralığında gün sonu kaydı bulunamadı.</td></tr>';
                return;
            }

            history.forEach(row => {
                var tr = document.createElement("tr");
                tr.innerHTML = `
                    <td style="font-weight:bold;">${this.escapeHtml(row.date_formatted)}</td>
                    <td>${this.escapeHtml(row.sale_count)} Sipariş</td>
                    <td style="color:#27ae60;">${this.formatCurrency(row.total_sales)}</td>
                    <td style="color:#e67e22;">${this.formatCurrency(row.total_refunds)}</td>
                    <td style="font-weight:bold; background: rgba(0,0,0,0.02);">${this.formatCurrency(row.net_total)}</td>
                    <td>
                        <button class="hk-btn-outline btn-view-report" data-date="${row.date}">📊 Raporu Aç</button>
                    </td>
                `;
                tbody.appendChild(tr);
            });

            // Event Delegation: Tbody'ye bir kez dinleyici ekle (eğer daha önce eklenmemişse)
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
