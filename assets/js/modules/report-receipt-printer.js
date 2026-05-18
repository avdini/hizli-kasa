/**
 * Hızlı Kasa - Raporlar İzole Fiş Yazdırıcı
 *
 * Raporlar > Tüm Siparişler tablosundan güncel durum fişi yazdırır.
 * Kasa tarafındaki anlık satış fişi akışından tamamen bağımsız çalışır.
 */
(function(HK) {
    'use strict';

    HK.ReportReceiptPrinter = {
        init: function() {
            var self = this;

            document.addEventListener('click', function(e) {
                var btn = e.target.closest('.btn-reprint');
                if (!btn) return;

                e.preventDefault();
                var orderId = Number(btn.dataset.id || 0);
                if (!orderId) return;

                self.printCurrentOrderSnapshot(orderId, btn);
            });
        },

        printCurrentOrderSnapshot: async function(orderId, btn) {
            if (btn) btn.disabled = true;

            try {
                var url = kasaAyar.rootApiUrl + 'hizli-kasa/v1/reports/order-receipt/' + orderId;
                var response = await fetch(url, {
                    headers: { 'X-WP-Nonce': kasaAyar.nonce }
                });

                var payload = await response.json();
                if (!response.ok) {
                    throw new Error(payload && payload.message ? payload.message : 'Fiş verisi alınamadı.');
                }

                this.fillTemplate(payload);
                HK.PrintManager.print('report-receipt');

            } catch (err) {
                console.error('Rapor fişi yazdırma hatası:', err);
                if (HK.UIRenderer && HK.UIRenderer.showToast) {
                    HK.UIRenderer.showToast('Fiş yazdırılamadı: ' + (err.message || 'Bilinmeyen hata'), 'error', true);
                }
            } finally {
                if (btn) btn.disabled = false;
            }
        },

        fillTemplate: function(data) {
            var byId = function(id) { return document.getElementById(id); };
            var formatMoney = function(value) {
                var n = Number(value || 0);
                return n.toFixed(2) + ' TL';
            };
            var formatRawAmount = function(value) {
                var n = Number(value || 0);
                return n.toFixed(2);
            };

            var noText = byId('report-fis-no-text');
            var subtitle = byId('report-fis-subtitle');
            var tarihText = byId('report-fis-tarih');
            var body = byId('report-fis-urunler-body');
            var etiketToplam = byId('report-fis-liste-toplami-tutar');
            var araToplam = byId('report-fis-ara-toplam-tutar');
            var autoDiscountRow = byId('report-fis-otomatik-indirim-satiri');
            var autoDiscountLabel = byId('report-fis-otomatik-indirim-etiket');
            var autoDiscountVal = byId('report-fis-otomatik-indirim-tutar');
            var manualDiscountRow = byId('report-fis-iskonto-satiri');
            var manualDiscountVal = byId('report-fis-iskonto-tutar');
            var grandTotal = byId('report-fis-genel-toplam');
            var refundNote = byId('report-fis-refund-note');
            var adjustmentsBox = byId('report-fis-adjustments');
            var adjustmentsTotal = byId('report-fis-adjustments-total');

            if (!noText || !tarihText || !body || !grandTotal) {
                throw new Error('Rapor fiş şablonu bulunamadı.');
            }

            noText.innerText = 'SİPARİŞ NO: ' + (data.order_id || '-');
            tarihText.innerText = data.printed_at || new Date().toLocaleString('tr-TR');
            var hasAdjustment = !!data.has_adjustment;
            if (subtitle) {
                subtitle.innerText = hasAdjustment ? 'RAPORLAR - GÜNCEL DURUM FİŞİ' : 'HIZLI KASA SATIŞ FİŞİ';
            }

            body.innerHTML = '';
            (data.items || []).forEach(function(item) {
                var tr = document.createElement('tr');
                tr.innerHTML =
                    '<td class="fis-item-td-left">' +
                        '<div class="fis-item-name">' + (item.name || '-') + '</div>' +
                        '<div class="fis-item-sku-qty">' + ((item.sku ? item.sku + ' | ' : '') + (item.quantity || 0) + ' Adet') + '</div>' +
                    '</td>' +
                    '<td class="fis-item-td-right">' +
                        '<div class="fis-item-price">' + formatRawAmount(item.line_total) + '</div>' +
                    '</td>';
                body.appendChild(tr);
            });

            var totals = data.totals || {};
            etiketToplam.innerText = formatMoney(totals.etiket_toplami);
            araToplam.innerText = formatMoney(totals.ara_toplam);
            grandTotal.innerText = formatMoney(totals.genel_toplam);

            var autoDiscount = Number(totals.auto_discount || 0);
            if (autoDiscount > 0.01) {
                autoDiscountRow.style.display = 'flex';
                autoDiscountLabel.innerText = 'İNDİRİM:';
                autoDiscountVal.innerText = '-' + formatMoney(autoDiscount);
            } else {
                autoDiscountRow.style.display = 'none';
            }

            var manualDiscount = Number(totals.manual_discount || 0);
            if (manualDiscount > 0.01) {
                manualDiscountRow.style.display = 'flex';
                manualDiscountVal.innerText = '-' + formatMoney(manualDiscount);
            } else {
                manualDiscountRow.style.display = 'none';
            }

            refundNote.style.display = data.has_refund_adjustment ? 'block' : 'none';
            if (adjustmentsBox && adjustmentsTotal) {
                if (hasAdjustment) {
                    var impact = Number((data.adjustments && data.adjustments.impact_total) || 0);
                    adjustmentsTotal.innerText = '-' + formatMoney(impact);
                    adjustmentsBox.style.display = 'block';
                } else {
                    adjustmentsBox.style.display = 'none';
                }
            }

            if (typeof JsBarcode === 'function') {
                try {
                    JsBarcode('#report-fis-barkod', String(data.barcode_value || data.order_id || ''), {
                        format: 'CODE128',
                        width: 2,
                        height: 50,
                        displayValue: false,
                        margin: 0,
                        background: '#ffffff',
                        lineColor: '#000000'
                    });
                } catch (e) {
                    console.error('Rapor fişi barkodu üretilemedi:', e);
                }
            }
        }
    };
})(window.HizliKasa = window.HizliKasa || {});
