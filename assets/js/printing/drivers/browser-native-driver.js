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

            // Render Barcode image/SVG via JsBarcode if present
            var jsBarcodeFn = window.JsBarcode || (window.parent && window.parent.JsBarcode);
            if (jsBarcodeFn) {
                var barcodeImgs = targetEl.querySelectorAll('.hk-print-barcode-img');
                barcodeImgs.forEach(function(img) {
                    var val = img.getAttribute('data-barcode');
                    if (val) {
                        try {
                            jsBarcodeFn(img, String(val), {
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

                var barcodeSvgs = targetEl.querySelectorAll('svg.barcode-svg[data-barcode]');
                barcodeSvgs.forEach(function(svg) {
                    var val = svg.getAttribute('data-barcode');
                    if (val && !svg.hasChildNodes()) {
                        try {
                            jsBarcodeFn(svg, String(val), {
                                format: 'CODE128',
                                width: 2.0,
                                height: 50,
                                displayValue: false,
                                margin: 0,
                                background: '#ffffff',
                                lineColor: '#000000'
                            });
                            svg.setAttribute('preserveAspectRatio', 'none');
                        } catch (ex) {}
                    }
                });
            }

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
