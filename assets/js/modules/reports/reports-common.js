/**
 * Hızlı Kasa - Raporlar Ortak Yardımcı Modülü
 */
(function(HK) {
    'use strict';

    HK.ReportsCommon = {
        fieldLabelMap: {
            _hk_cikis_depo_id: 'Depo ID',
            _hk_cikis_depo_adi: 'Çıkış Deposu',
            _hizli_kasa_kasa_no: 'Kasa No',
            _hizli_kasa_kasiyer: 'Kasiyer',
            _hizli_kasa_kasiyer_id: 'Kasiyer ID',
            _hizli_kasa_musteri_telefon: 'Müşteri Telefonu',
            _hizli_kasa_original_order: 'Orijinal Sipariş ID',
            _hizli_kasa_is_refund: 'İade Kaydı',
            _odeme_nakit: 'Nakit Ödeme',
            _odeme_kart: 'Kart Ödeme',
            _odeme_iban: 'IBAN Ödeme',
            _odeme_qr_taksit: 'QR Taksit Ödeme',
            _odeme_coupon: 'Kupon Ödeme',
            _ara_toplam: 'Ara Toplam',
            _etiket_toplami: 'Etiket Toplamı',
            _hk_kaynak: 'İşlem Kaynağı',
            _hizli_kasa_kaynak: 'İşlem Kaynağı',
            _hk_has_refund: 'İade Durumu',
            _hk_refunded_qty: 'İade Edilen Adet',
            _hk_is_fully_refunded: 'Tam İade',
            _hk_iade_depo_ozet: 'İade Edilen Depolar',
            _hk_refunded_discount: 'İade İskontosu',
            _billing_first_name: 'Müşteri Adı',
            _billing_last_name: 'Müşteri Soyadı',
            _billing_phone: 'Müşteri Telefonu',
            _billing_email: 'Müşteri E-Posta',
            _payment_method_title: 'Ödeme Yöntemi',
            _hizli_kasa_siparis_notu: 'Sipariş Notu',
            _hizli_kasa_base_odeme_tipi: 'Ödeme Tipi',
            _order_total: 'Sipariş Toplamı',
            _order_tax: 'Vergi Toplamı',
            _order_shipping: 'Kargo Tutarı'
        },

        currencyFieldKeys: ['_odeme_nakit', '_odeme_kart', '_odeme_iban', '_odeme_qr_taksit', '_odeme_coupon', '_ara_toplam', '_etiket_toplami', '_hk_refunded_discount', '_order_total', '_order_tax', '_order_shipping'],

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
            var qrTaksit = parseFloat(meta._odeme_qr_taksit || 0);
            var kupon = parseFloat(meta._odeme_coupon || 0);

            if (qrTaksit === 0 && (order.payment === 'qr_sanal_pos' || (order.payment_method && order.payment_method === 'qr_sanal_pos'))) {
                qrTaksit = parseFloat(order.total || 0);
                kart = 0;
            }
            
            var activeMethods = [];
            if (Math.abs(nakit) > 0.01) activeMethods.push({ type: 'nakit', name: 'Nakit', amount: nakit });
            if (Math.abs(kart) > 0.01) activeMethods.push({ type: 'kart', name: 'Kart', amount: kart });
            if (Math.abs(iban) > 0.01) activeMethods.push({ type: 'iban', name: 'IBAN', amount: iban });
            if (Math.abs(qrTaksit) > 0.01) activeMethods.push({ type: 'qr_taksit', name: 'QR Taksit', amount: qrTaksit });
            if (Math.abs(kupon) > 0.01) activeMethods.push({ type: 'kupon', name: 'Kupon', amount: kupon });
            
            var nonCouponMethods = [];
            if (Math.abs(nakit) > 0.01) nonCouponMethods.push({ type: 'nakit', name: 'Nakit', amount: nakit });
            if (Math.abs(kart) > 0.01) nonCouponMethods.push({ type: 'kart', name: 'Kart', amount: kart });
            if (Math.abs(iban) > 0.01) nonCouponMethods.push({ type: 'iban', name: 'IBAN', amount: iban });
            if (Math.abs(qrTaksit) > 0.01) nonCouponMethods.push({ type: 'qr_taksit', name: 'QR Taksit', amount: qrTaksit });
            
            var badges = [];
            var icons = { nakit: '💵', kart: '💳', iban: '🏦', qr_taksit: '📱', kupon: '🎟️' };

            if (Math.abs(kupon) > 0.01) {
                badges.push('<div class="payment-badge payment-kupon" title="Kupon Ödeme: ' + this.escapeHtml(this.formatCurrency(kupon)) + '">🎟️ Kupon</div>');
            }

            if (nonCouponMethods.length > 1) {
                var tooltipText = nonCouponMethods.map(m => m.name + ': ' + this.formatCurrency(m.amount)).join(' | ');
                badges.push('<div class="payment-badge payment-bolunmus" title="' + this.escapeHtml(tooltipText) + '">🔀 Bölünmüş</div>');
            } else if (nonCouponMethods.length === 1) {
                var method = nonCouponMethods[0];
                badges.push('<div class="payment-badge payment-' + method.type + '" title="' + method.name + ' Ödeme: ' + this.escapeHtml(this.formatCurrency(method.amount)) + '">' + (icons[method.type] || '') + ' ' + method.name + '</div>');
            }

            if (badges.length === 0) {
                var paymentTitle = order.payment || 'Belirsiz';
                var paymentType = 'belirsiz';
                var icon = '❓';
                var normalized = paymentTitle.toLowerCase();
                if (normalized.indexOf('qr') !== -1 || normalized.indexOf('sanal') !== -1) {
                    paymentType = 'qr_taksit';
                    icon = '📱';
                } else if (normalized.indexOf('nakit') !== -1 || normalized.indexOf('cash') !== -1) {
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
                    return null;
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

            if (window.HizliKasa && window.HizliKasa.StockTerminal && typeof window.HizliKasa.StockTerminal.openImagePreview === 'function') {
                window.HizliKasa.StockTerminal.openImagePreview(src);
                return;
            }

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
                return '<div class="meta-empty-notice">Detay bilgisi yok.</div>';
            }

            // Kategori tanımları
            var financeKeys = ['_odeme_nakit', '_odeme_kart', '_odeme_iban', '_odeme_qr_taksit', '_odeme_coupon', '_ara_toplam', '_etiket_toplami', '_hk_refunded_discount', '_order_total', '_order_tax', '_order_shipping'];
            var userKeys = ['_hizli_kasa_kasiyer', '_hizli_kasa_kasa_no', '_hizli_kasa_musteri_telefon', '_hk_cikis_depo_adi', '_billing_first_name', '_billing_last_name', '_billing_phone', '_billing_email'];
            var processKeys = ['_hk_kaynak', '_hizli_kasa_kaynak', '_hizli_kasa_original_order', '_hizli_kasa_is_refund', '_hk_has_refund', '_hk_refunded_qty', '_hk_is_fully_refunded', '_hk_iade_depo_ozet', '_payment_method_title', '_hizli_kasa_siparis_notu', '_hizli_kasa_base_odeme_tipi'];

            // Her zaman 2. katmana düşmesi gereken teknik/gereksiz anahtarlar
            var alwaysExtraKeys = ['_hk_cikis_depo_id', '_hizli_kasa_kasiyer_id', '_hk_idempotency', '_hk_idemp', '_hk_key', '_transaction_id', '_created_via', '_cart_hash', '_order_key', '_edit_lock', '_edit_last', '_wp_old_date'];

            var financeItems = [];
            var userItems = [];
            var processItems = [];
            var extraItems = [];

            Object.keys(meta).forEach(function(key) {
                var field = self.formatMetaField(key, meta[key]);
                if (!field) return;
                
                var rawVal = meta[key];
                var displayVal = field.value;

                // Boş/null kontrol
                if (displayVal === null || displayVal === undefined || displayVal === '' || displayVal === '-') return;

                // Para birimi alanlarında sıfır değerini filtreleme
                if (self.currencyFieldKeys.indexOf(key) !== -1 || key.indexOf('_odeme_') === 0) {
                    var numVal = parseFloat(rawVal);
                    if (isNaN(numVal) || Math.abs(numVal) < 0.01) return;
                }

                // Teknik/ID anahtarlarını her zaman extra'ya at
                var isAlwaysExtra = alwaysExtraKeys.indexOf(key) !== -1 
                    || key.indexOf('_idemp') !== -1 
                    || key.indexOf('_edit_') !== -1
                    || key.indexOf('_cart_') !== -1
                    || key.indexOf('_order_key') !== -1
                    || key.indexOf('_transaction') !== -1;

                if (isAlwaysExtra) {
                    extraItems.push('<span class="meta-extra-chip" title="' + self.escapeHtml(key) + '"><strong>' + self.escapeHtml(field.label) + ':</strong> ' + displayVal + '</span>');
                    return;
                }

                var itemRowHtml = '<div class="meta-item-row"><span class="meta-item-label">' + self.escapeHtml(field.label) + '</span><span class="meta-item-val">' + displayVal + '</span></div>';

                if (financeKeys.indexOf(key) !== -1 || key.indexOf('_odeme_') === 0) {
                    financeItems.push(itemRowHtml);
                } else if (userKeys.indexOf(key) !== -1 || key.indexOf('_billing_') === 0) {
                    userItems.push(itemRowHtml);
                } else if (processKeys.indexOf(key) !== -1) {
                    processItems.push(itemRowHtml);
                } else {
                    extraItems.push('<span class="meta-extra-chip" title="' + self.escapeHtml(key) + '"><strong>' + self.escapeHtml(field.label) + ':</strong> ' + displayVal + '</span>');
                }
            });

            var cardsHtml = '';

            if (financeItems.length) {
                cardsHtml += '<div class="meta-card meta-card-finance"><div class="meta-card-header"><span class="meta-card-icon">💳</span><span class="meta-card-title">Finans Özeti</span></div><div class="meta-card-body">' + financeItems.join('') + '</div></div>';
            }
            if (userItems.length) {
                cardsHtml += '<div class="meta-card meta-card-user"><div class="meta-card-header"><span class="meta-card-icon">👤</span><span class="meta-card-title">Kasiyer & Müşteri</span></div><div class="meta-card-body">' + userItems.join('') + '</div></div>';
            }
            if (processItems.length) {
                cardsHtml += '<div class="meta-card meta-card-process"><div class="meta-card-header"><span class="meta-card-icon">ℹ️</span><span class="meta-card-title">İşlem Detayları</span></div><div class="meta-card-body">' + processItems.join('') + '</div></div>';
            }

            if (!cardsHtml && !extraItems.length) {
                return '<div class="meta-empty-notice">Detay bilgisi yok.</div>';
            }

            var extraSectionHtml = '';
            if (extraItems.length) {
                extraSectionHtml = `
                    <div class="meta-extra-wrapper">
                        <button type="button" class="btn-toggle-meta-extra">
                            <span class="toggle-icon">⚙️</span> Diğer Teknik Detaylar <span class="meta-extra-count">(+${extraItems.length})</span>
                        </button>
                        <div class="meta-extra-container" style="display:none;">
                            ${extraItems.join('')}
                        </div>
                    </div>
                `;
            }

            return `
                <div class="meta-details-layout">
                    <div class="meta-grid">${cardsHtml}</div>
                    ${extraSectionHtml}
                </div>
            `;
        },

        renderTable: function(tbody, orders, type) {
            console.log(`HK.ReportsCommon: Rendering ${type} table, count:`, orders ? orders.length : 0);
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
                    } else if (type === 'refunds') {
                        var isCancelled = (order.status === 'İptal Edildi' || order.status === 'Cancelled' || (order.meta && order.meta._hizli_kasa_refund_cancelled === 'yes'));
                        if (isCancelled) {
                            var cancelReason = (order.meta && order.meta._hizli_kasa_refund_cancel_reason) ? order.meta._hizli_kasa_refund_cancel_reason : '';
                            var cancelledBy = (order.meta && order.meta._hizli_kasa_refund_cancelled_by) ? order.meta._hizli_kasa_refund_cancelled_by : '';
                            var tooltip = 'İptal Eden: ' + this.escapeHtml(cancelledBy) + (cancelReason ? ' | Nedeni: ' + this.escapeHtml(cancelReason) : '');
                            actionButtons += '<span class="badge-cancelled-refund" style="background:#fee2e2; color:#991b1b; padding:4px 8px; border-radius:4px; font-size:12px; font-weight:600; display:inline-block;" title="' + tooltip + '">❌ İptal Edildi</span>';
                        } else {
                            actionButtons += '<button class="btn-cancel-refund" data-id="' + this.escapeHtml(order.id || '') + '" style="background:#ef4444; color:#fff; border:none; padding:5px 10px; border-radius:4px; cursor:pointer; font-size:12px; font-weight:600; margin-left:4px;">🚫 İadeyi İptal Et</button>';
                        }
                    }
                    
                    tr.innerHTML = `
                        <td>${this.escapeHtml(order.date || '-')}</td>
                        <td><span class="report-order-id">#${this.escapeHtml(order.id || '-')}</span></td>
                        <td>${this.escapeHtml(order.cashier || '-')} <br><small class="report-kasa-no">${kasaNoLabel}</small></td>
                        <td>${itemsHtml}</td>
                        <td class="report-total-cell">
                            <div class="report-total-amount">${orderTotal}</div>
                            <div class="report-payment-container">${paymentBadge}</div>
                        </td>
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

            tbody.querySelectorAll(".btn-cancel-refund").forEach(btn => {
                btn.addEventListener("click", function() {
                    var id = this.dataset.id;
                    if (HK.ReportRefunds && typeof HK.ReportRefunds.openCancelModal === 'function') {
                        HK.ReportRefunds.openCancelModal(id);
                    }
                });
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

            tbody.querySelectorAll(".btn-toggle-meta-extra").forEach(btn => {
                btn.addEventListener("click", function(e) {
                    e.stopPropagation();
                    var wrapper = this.closest(".meta-extra-wrapper");
                    if (!wrapper) return;
                    var container = wrapper.querySelector(".meta-extra-container");
                    if (container) {
                        var isHidden = (container.style.display === "none" || !container.style.display);
                        container.style.display = isHidden ? "flex" : "none";
                        this.classList.toggle("is-active", isHidden);
                        var chips = container.querySelectorAll(".meta-extra-chip");
                        var count = chips ? chips.length : 0;
                        this.innerHTML = isHidden 
                            ? `<span class="toggle-icon">🔼</span> Diğer Teknik Detayları Gizle` 
                            : `<span class="toggle-icon">⚙️</span> Diğer Teknik Detayları Göster (+${count})`;
                    }
                });
            });

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

        renderPagination: function(container, maxPages, currentPage, onPageChange) {
            container.innerHTML = "";
            if (!maxPages || maxPages <= 1) return;

            for (let i = 1; i <= maxPages; i++) {
                var btn = document.createElement("button");
                btn.className = `hk-page-btn ${i === currentPage ? 'aktif' : ''}`;
                btn.innerText = i;
                btn.addEventListener("click", () => {
                    if (typeof onPageChange === 'function') {
                        onPageChange(i);
                    }
                });
                container.appendChild(btn);
            }
        }
    };
})(window.HizliKasa);
