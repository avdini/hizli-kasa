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
        currentPageInternetOrders: 1,
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
                    } else if (target === 'rapor-internet-siparisleri') {
                        self.loadInternetOrders(1);
                    } else if (target === 'rapor-iade-listesi') {
                        self.loadRefunds(1);
                    } else if (target === 'rapor-gun-sonu-arsivi') {
                        self.loadDayEndHistory();
                    } else if (target === 'rapor-depo-sayimlari') {
                        self.loadSayimHistory();
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

            var internetOrderSearch = document.getElementById("internet-order-search-input");
            if (internetOrderSearch) {
                internetOrderSearch.addEventListener("keyup", HK.utils.debounce(function() {
                    self.loadInternetOrders(1);
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
                    var activeBtn = document.querySelector(".rapor-alt-btn.aktif");
                    if (!activeBtn) return;
                    var activeTarget = activeBtn.dataset.target;
                    
                    if (activeTarget === 'rapor-tum-siparisler') self.loadOrders(1);
                    if (activeTarget === 'rapor-internet-siparisleri') self.loadInternetOrders(1);
                    if (activeTarget === 'rapor-iade-listesi') self.loadRefunds(1);
                    if (activeTarget === 'rapor-gun-sonu-arsivi') self.loadDayEndHistory();
                    if (activeTarget === 'rapor-depo-sayimlari') self.loadSayimHistory();
                });
            }

            // Depo değiştiğinde aktif raporu yenile
            document.addEventListener('hkActiveDepoChanged', function() {
                var activeBtn = document.querySelector(".rapor-alt-btn.aktif");
                if (!activeBtn) return;
                var activeTarget = activeBtn.dataset.target;
                
                // Raporlar sekmesi ana kapsayıcısı görünür mü?
                var reportsTabContainer = document.querySelector('.rapor-alt-sekmeler');
                if (reportsTabContainer && reportsTabContainer.offsetParent !== null) {
                    if (activeTarget === 'rapor-tum-siparisler') self.loadOrders(1);
                    else if (activeTarget === 'rapor-internet-siparisleri') self.loadInternetOrders(1);
                    else if (activeTarget === 'rapor-iade-listesi') self.loadRefunds(1);
                    else if (activeTarget === 'rapor-gun-sonu-arsivi') self.loadDayEndHistory();
                    else if (activeTarget === 'rapor-depo-sayimlari') self.loadSayimHistory();
                }
            });
        },

        loadInternetOrders: async function(page) {
            this.currentPageInternetOrders = page || 1;
            var tbody = document.getElementById("internet-orders-body");
            var pagin = document.getElementById("internet-orders-pagination");
            if (!tbody) return;

            tbody.innerHTML = '<tr><td colspan="7" style="text-align:center; padding:40px;">Yükleniyor...</td></tr>';
            
            var dateStart = document.getElementById("rapor-tarih-bas").value;
            var dateEnd = document.getElementById("rapor-tarih-bit").value;
            var search = document.getElementById("internet-order-search-input").value;

            try {
                var url = `${kasaAyar.rootApiUrl}hizli-kasa/v1/reports/internet-orders?page=${this.currentPageInternetOrders}&per_page=${this.perPage}&date_start=${dateStart}&date_end=${dateEnd}&search=${encodeURIComponent(search)}`;
                var response = await fetch(url, { headers: { 'X-WP-Nonce': kasaAyar.nonce } });
                var res = await response.json();

                if (!response.ok) {
                    tbody.innerHTML = '<tr><td colspan="7" style="text-align:center; padding:40px; color:orange;">⚠️ ' + (res.message || 'Siparişler yüklenemedi.') + '</td></tr>';
                    return;
                }

                this.renderTable(tbody, res.orders, 'internet_orders');
                this.renderPagination(pagin, res.max_pages, this.currentPageInternetOrders, 'internet_orders');

            } catch (e) {
                console.error("HK.DetailedReports: Load internet orders error", e);
                tbody.innerHTML = '<tr><td colspan="7" style="text-align:center; padding:40px; color:red;">Hata: Veriler çekilemedi.</td></tr>';
            }
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

        getPaymentBadgeHtml: function(order) {
            var meta = order.meta || {};
            var nakit = parseFloat(meta._odeme_nakit || 0);
            var kart = parseFloat(meta._odeme_kart || 0);
            var iban = parseFloat(meta._odeme_iban || 0);
            var kupon = parseFloat(meta._odeme_coupon || 0);
            
            var activeMethods = [];
            if (Math.abs(nakit) > 0.01) activeMethods.push({ type: 'nakit', name: 'Nakit', amount: nakit });
            if (Math.abs(kart) > 0.01) activeMethods.push({ type: 'kart', name: 'Kart', amount: kart });
            if (Math.abs(iban) > 0.01) activeMethods.push({ type: 'iban', name: 'IBAN', amount: iban });
            if (Math.abs(kupon) > 0.01) activeMethods.push({ type: 'kupon', name: 'Kupon', amount: kupon });
            
            var nonCouponMethods = [];
            if (Math.abs(nakit) > 0.01) nonCouponMethods.push({ type: 'nakit', name: 'Nakit', amount: nakit });
            if (Math.abs(kart) > 0.01) nonCouponMethods.push({ type: 'kart', name: 'Kart', amount: kart });
            if (Math.abs(iban) > 0.01) nonCouponMethods.push({ type: 'iban', name: 'IBAN', amount: iban });
            
            var badges = [];
            var icons = { nakit: '💵', kart: '💳', iban: '📱', kupon: '🎟️' };

            // 1. Eğer kupon kullanılmışsa kupon rozetini ayrı üret
            if (Math.abs(kupon) > 0.01) {
                badges.push('<div class="payment-badge payment-kupon" title="Kupon Ödeme: ' + this.escapeHtml(this.formatCurrency(kupon)) + '">🎟️ Kupon</div>');
            }

            // 2. Kalan ödeme kanalları için rozet üret
            if (nonCouponMethods.length > 1) {
                var tooltipText = nonCouponMethods.map(m => m.name + ': ' + this.formatCurrency(m.amount)).join(' | ');
                badges.push('<div class="payment-badge payment-bolunmus" title="' + this.escapeHtml(tooltipText) + '">🔀 Bölünmüş</div>');
            } else if (nonCouponMethods.length === 1) {
                var method = nonCouponMethods[0];
                badges.push('<div class="payment-badge payment-' + method.type + '" title="' + method.name + ' Ödeme: ' + this.escapeHtml(this.formatCurrency(method.amount)) + '">' + (icons[method.type] || '') + ' ' + method.name + '</div>');
            }

            // Eğer hiç yöntem bulunamadıysa fallback kullan
            if (badges.length === 0) {
                var paymentTitle = order.payment || 'Belirsiz';
                var paymentType = 'belirsiz';
                var icon = '❓';
                var normalized = paymentTitle.toLowerCase();
                if (normalized.indexOf('nakit') !== -1 || normalized.indexOf('cash') !== -1) {
                    paymentType = 'nakit';
                    icon = '💵';
                } else if (normalized.indexOf('kart') !== -1 || normalized.indexOf('card') !== -1 || normalized.indexOf('kredi') !== -1) {
                    paymentType = 'kart';
                    icon = '💳';
                } else if (normalized.indexOf('iban') !== -1 || normalized.indexOf('havale') !== -1 || normalized.indexOf('transfer') !== -1) {
                    paymentType = 'iban';
                    icon = '📱';
                } else if (normalized.indexOf('kupon') !== -1) {
                    paymentType = 'kupon';
                    icon = '🎟️';
                }
                badges.push('<div class="payment-badge payment-' + paymentType + '" title="' + this.escapeHtml(paymentTitle) + '">' + icon + ' ' + paymentTitle + '</div>');
            }

            return '<div style="display: flex; gap: 5px; flex-wrap: wrap;">' + badges.join('') + '</div>';
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

        openImagePreview: function(src) {
            if (!src || src.includes('placeholder')) return;

            // Eğer StockTerminal'in kendi openImagePreview metodu yüklüyse onu kullanabiliriz
            if (window.HizliKasa && window.HizliKasa.StockTerminal && typeof window.HizliKasa.StockTerminal.openImagePreview === 'function') {
                window.HizliKasa.StockTerminal.openImagePreview(src);
                return;
            }

            // Yoksa kendimiz fallback modal oluşturup gösterelim
            let modal = document.getElementById('terminal-image-preview-modal');
            if (!modal) {
                modal = document.createElement('div');
                modal.id = 'terminal-image-preview-modal';
                modal.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.85);z-index:999999;display:flex;align-items:center;justify-content:center;backdrop-filter:blur(5px);cursor:zoom-out;';
                
                const loader = document.createElement('div');
                loader.id = 'terminal-preview-loader';
                loader.style.cssText = 'position:absolute;width:40px;height:40px;border:4px solid #fff;border-top:4px solid transparent;border-radius:50%;animation:hk-spin 1s linear infinite;';
                
                if (!document.getElementById('hk-spin-keyframes')) {
                    const style = document.createElement('style');
                    style.id = 'hk-spin-keyframes';
                    style.innerHTML = '@keyframes hk-spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }';
                    document.head.appendChild(style);
                }

                const img = document.createElement('img');
                img.id = 'terminal-preview-img';
                img.style.cssText = 'max-width:90%;max-height:90%;object-fit:contain;border-radius:12px;opacity:0;transition:opacity 0.3s;box-shadow:0 10px 40px rgba(0,0,0,0.5);';
                
                modal.appendChild(loader);
                modal.appendChild(img);
                document.body.appendChild(modal);

                modal.addEventListener('click', () => {
                    modal.style.display = 'none';
                });
            }

            const img = document.getElementById('terminal-preview-img');
            const loader = document.getElementById('terminal-preview-loader');
            
            img.style.opacity = '0';
            img.src = ''; 
            loader.style.display = 'block';
            modal.style.display = 'flex';
            
            // Thumbnail suffix temizle (-150x150 gibi)
            const fullSrc = src.replace(/-\d+x\d+(\.[a-zA-Z]+)$/i, '$1');
            
            img.onload = function() {
                loader.style.display = 'none';
                img.style.opacity = '1';
            };
            img.onerror = function() {
                loader.style.display = 'none';
                img.style.opacity = '1';
                if (img.src !== src) {
                    img.src = src;
                }
            };
            
            img.src = fullSrc;
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
                        (item.sku ? ' <span class="report-item-sku">[' + this.escapeHtml(item.sku) + ']</span>' : '') +
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

                if (!response.ok) {
                    console.error("HK.DetailedReports: Orders API error", response.status, res);
                    tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; padding:40px; color:orange;">⚠️ ' + (res.message || 'Siparişler yüklenemedi. Lütfen sayfayı yenileyip tekrar deneyin.') + '</td></tr>';
                    return;
                }

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

                if (!response.ok) {
                    console.error("HK.DetailedReports: Refunds API error", response.status, res);
                    tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; padding:40px; color:orange;">⚠️ ' + (res.message || 'İadeler yüklenemedi. Lütfen sayfayı yenileyip tekrar deneyin.') + '</td></tr>';
                    return;
                }

                this.renderTable(tbody, res.orders, 'refunds');
                this.renderPagination(pagin, res.max_pages, this.currentPageRefunds, 'refunds');

            } catch (e) {
                console.error("HK.DetailedReports: Load refunds error", e);
                tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; padding:40px; color:red;">Hata: Veriler çekilemedi. Konsola bakınız.</td></tr>';
            }
        },

        renderTable: function(tbody, orders, type) {
            console.log(`HK.DetailedReports: Rendering ${type} table, count:`, orders ? orders.length : 0);
            var self = this;
            tbody.innerHTML = "";
            
            var colCount = (type === 'internet_orders') ? 7 : 6;

            if (!orders || orders.length === 0) {
                tbody.innerHTML = `<tr><td colspan="${colCount}" style="text-align:center; padding:40px;">Kayıt bulunamadı.</td></tr>`;
                return;
            }

            orders.forEach(order => {
                var itemsHtml = this.renderOrderItemsList(order.items);
                var metaDetails = this.renderOrderMetaDetails(order.meta);
                var orderTotal = this.formatCurrency(order.total || 0);
                
                var tr = document.createElement("tr");
                var actionButtons = '<button class="btn-detail" data-id="' + this.escapeHtml(order.id || '') + '">🔍 Detay</button>';
                
                if (type === 'internet_orders') {
                    tr.innerHTML = `
                        <td>${this.escapeHtml(order.date || '-')}</td>
                        <td><span class="report-order-id">#${this.escapeHtml(order.id || '-')}</span></td>
                        <td>${this.escapeHtml(order.customer || '-')}</td>
                        <td>${itemsHtml}</td>
                        <td><span class="status-badge status-${this.escapeHtml(order.status || 'unknown')}">${this.escapeHtml(order.status || '-')}</span></td>
                        <td class="report-total-cell">${orderTotal}</td>
                        <td><div class="report-action-buttons">${actionButtons}</div></td>
                    `;
                } else {
                    var kasaNoLabel = order.kasa_no ? ('Kasa: ' + this.escapeHtml(order.kasa_no)) : '-';
                    var paymentBadge = this.getPaymentBadgeHtml(order);
                    
                    if (type === 'orders') {
                        actionButtons += '<button class="btn-reprint" data-id="' + this.escapeHtml(order.id || '') + '">🧾 Fiş Yazdır</button>';
                    }
                    
                    tr.innerHTML = `
                        <td>${this.escapeHtml(order.date || '-')}</td>
                        <td>
                            <span class="report-order-id">#${this.escapeHtml(order.id || '-')}</span>
                            <div class="report-payment-container">${paymentBadge}</div>
                        </td>
                        <td>${this.escapeHtml(order.cashier || '-')} <br><small class="report-kasa-no">${kasaNoLabel}</small></td>
                        <td>${itemsHtml}</td>
                        <td class="report-total-cell">${orderTotal}</td>
                        <td><div class="report-action-buttons">${actionButtons}</div></td>
                    `;
                }
                tbody.appendChild(tr);

                var detailTr = document.createElement("tr");
                detailTr.className = "meta-details-row";
                detailTr.id = `meta-row-${order.id}`;
                detailTr.innerHTML = `<td colspan="${colCount}"><div class="meta-details-container">${metaDetails || 'Detay bilgisi yok.'}</div></td>`;
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

            // Resim önizleme olayını tanımla
            tbody.querySelectorAll(".report-item-image").forEach(img => {
                img.addEventListener("click", function(e) {
                    e.stopPropagation();
                    self.openImagePreview(this.src);
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
                    else if (type === 'internet_orders') this.loadInternetOrders(i);
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
        },

        loadSayimHistory: async function() {
            var tbody = document.getElementById("sayim-history-body");
            if (!tbody) return;

            tbody.innerHTML = '<tr><td colspan="8" style="text-align:center; padding:40px;">Yükleniyor...</td></tr>';
            
            var dateStart = document.getElementById("rapor-tarih-bas").value;
            var dateEnd = document.getElementById("rapor-tarih-bit").value;
            var depoId = HK.DepoManager ? HK.DepoManager.getActiveDepo() : 0;

            try {
                var url = `${kasaAyar.rootApiUrl}hizli-kasa/v1/reports/sayim-history?date_start=${dateStart}&date_end=${dateEnd}&depo_id=${depoId}`;
                var response = await fetch(url, { headers: { 'X-WP-Nonce': kasaAyar.nonce } });
                var res = await response.json();

                this.renderSayimHistory(tbody, res);

            } catch (e) {
                console.error("HK.DetailedReports: Load sayim history error", e);
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
                    <td style="font-weight:bold;">${self.escapeHtml(row.created_at)}</td>
                    <td>${self.escapeHtml(row.depo_name)}</td>
                    <td>${self.escapeHtml(row.created_by)}</td>
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

            // Event delegation for Details button
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
                // Toggle visibility
                if (nextRow.style.display === "none") {
                    nextRow.style.display = "";
                    btn.textContent = "▲ Kapat";
                } else {
                    nextRow.style.display = "none";
                    btn.textContent = "🔍 Detay";
                }
                return;
            }
            
            // Create new detail row
            var detailRow = document.createElement("tr");
            detailRow.className = "sayim-detail-row";
            
            var items = session.report_data || [];
            var tableHtml = '';
            
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
                                    var name = this.escapeHtml(item.name);
                                    if (item.attributes) {
                                        name += ` <span class="var-badge" style="background: rgba(52, 152, 219, 0.2); color: #3498db; font-size: 9px; padding: 2px 6px; border-radius: 4px; margin-left: 8px; font-weight: 800;">${this.escapeHtml(item.attributes)}</span>`;
                                    }
                                    var diffVal = parseFloat(item.diff);
                                    var diffClass = diffVal > 0 ? 'diff-plus' : (diffVal < 0 ? 'diff-minus' : 'diff-zero');
                                    var diffSign = diffVal > 0 ? '+' : '';
                                    return `
                                        <tr>
                                            <td>${name}</td>
                                            <td>${this.escapeHtml(item.sku)}</td>
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
