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
                if (HK.PrintCore && typeof HK.PrintCore.print === 'function') {
                    await HK.PrintCore.print({ type: 'order', id: orderId });
                }

            } catch (err) {
                console.error('Rapor fişi yazdırma hatası:', err);
                if (HK.UIRenderer && HK.UIRenderer.showToast) {
                    HK.UIRenderer.showToast('Fiş yazdırılamadı: ' + (err.message || 'Bilinmeyen hata'), 'error', true);
                }
            } finally {
                if (btn) btn.disabled = false;
            }
        },
    };
})(window.HizliKasa = window.HizliKasa || {});
