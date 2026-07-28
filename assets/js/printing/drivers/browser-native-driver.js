/**
 * Hızlı Kasa - Native Browser Print Fallback Driver
 */
(function(HK) {
    'use strict';

    HK.PrintDrivers = HK.PrintDrivers || {};

    class BrowserNativeDriver extends HK.PrintDrivers.BasePrintDriver {
        constructor() {
            super('browser-native', 'Tarayıcı Yerel Yazdırma (Fallback)');
        }

        async isAvailable() {
            return true; // Always available
        }

        async print(options) {
            var type = options.type || 'order';
            var targetEl = options.element;
            if (!targetEl) throw new Error('Yazdırılacak DOM elementi bulunamadı.');

            // Render Barcode image via JsBarcode if present
            var barcodeImgs = targetEl.querySelectorAll('.hk-print-barcode-img');
            barcodeImgs.forEach(function(img) {
                var val = img.getAttribute('data-barcode');
                if (val && typeof JsBarcode === 'function') {
                    try {
                        JsBarcode(img, String(val), {
                            format: 'CODE128',
                            width: 2,
                            height: 45,
                            displayValue: false,
                            margin: 0,
                            background: '#ffffff',
                            lineColor: '#000000'
                        });
                    } catch (ex) {}
                }
            });

            var iframe = document.getElementById('hk-print-iframe');
            if (iframe && iframe.contentWindow) {
                iframe.contentWindow.focus();
                iframe.contentWindow.print();
                return true;
            }

            window.print();
            return true;
        }
    }

    HK.PrintDrivers.BrowserNativeDriver = BrowserNativeDriver;

})(window.HizliKasa = window.HizliKasa || {});
