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

            var body = document.body;
            var modeClass = 'print-mode-' + type;
            body.classList.add(modeClass);

            var pageStyle = document.getElementById('hk-dynamic-page-style');
            if (!pageStyle) {
                pageStyle = document.createElement('style');
                pageStyle.id = 'hk-dynamic-page-style';
                document.head.appendChild(pageStyle);
            }

            if (type === 'barcode') {
                pageStyle.textContent = '@media print { @page { size: 50mm 35mm; margin: 0; } }';
            } else {
                pageStyle.textContent = '@media print { @page { size: auto; margin: 0; } }';
            }

            var cleanup = function() {
                body.classList.remove(modeClass);
                if (pageStyle) pageStyle.textContent = '';
                window.removeEventListener('afterprint', cleanup);
            };

            window.addEventListener('afterprint', cleanup);

            setTimeout(function() {
                window.print();
            }, 50);

            return true;
        }
    }

    HK.PrintDrivers.BrowserNativeDriver = BrowserNativeDriver;

})(window.HizliKasa = window.HizliKasa || {});
