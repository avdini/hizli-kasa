/**
 * Hızlı Kasa - Silent Local Executable Print Driver
 */
(function(HK) {
    'use strict';

    HK.PrintDrivers = HK.PrintDrivers || {};

    class HelperAppDriver extends HK.PrintDrivers.BasePrintDriver {
        constructor() {
            super('helper-app', 'Sessiz Yerel Yazıcı Servisi (EXE)');
            this.activePort = localStorage.getItem('hk_print_port') || 5001;
        }

        async isAvailable() {
            var silentEnabled = localStorage.getItem('hk_silent_print_enabled') === '1';
            if (!silentEnabled) return false;

            var port = await this.detectPort();
            return !!port;
        }

        async detectPort() {
            var self = this;
            var cachedPort = localStorage.getItem('hk_print_port') || 5001;
            
            try {
                var ctrl = new AbortController();
                var tid = setTimeout(function() { ctrl.abort(); }, 600);
                var res = await fetch('http://127.0.0.1:' + cachedPort + '/status', { signal: ctrl.signal });
                clearTimeout(tid);
                if (res.ok) return cachedPort;
            } catch (e) {
                // Fallback scan
            }

            var ports = [5001, 5002, 5003, 5004, 5005, 5006, 5007, 5008, 5009, 5010];
            for (var i = 0; i < ports.length; i++) {
                var p = ports[i];
                try {
                    var ctrl2 = new AbortController();
                    var tid2 = setTimeout(function() { ctrl2.abort(); }, 600);
                    var res2 = await fetch('http://127.0.0.1:' + p + '/status', { signal: ctrl2.signal });
                    clearTimeout(tid2);
                    if (res2.ok) {
                        localStorage.setItem('hk_print_port', p);
                        self.activePort = p;
                        return p;
                    }
                } catch (err) {}
            }
            return null;
        }

        async print(options) {
            var port = await this.detectPort();
            if (!port) throw new Error('Yerel yazıcı servisine (port 5001-5010) bağlanılamadı.');

            var token = localStorage.getItem('hk_print_token') || '';
            var printerName = options.printerName || '';
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

            // html2canvas rasterization directly from targetEl inside print iframe
            var scale = (options.type === 'barcode') ? 3.0 : 2.0;
            var canvas = await html2canvas(targetEl, {
                scale: scale,
                backgroundColor: '#ffffff',
                logging: false,
                useCORS: true
            });

            var base64Image = canvas.toDataURL('image/png');
            var rotateAngle = (options.type === 'barcode') ? 270 : 0;

            var res = await fetch('http://127.0.0.1:' + port + '/print', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': 'Bearer ' + token
                },
                body: JSON.stringify({
                    printer_name: printerName,
                    image: base64Image,
                    rotate: rotateAngle
                })
            });

            if (!res.ok) {
                var errJson = await res.json().catch(function() { return {}; });
                throw new Error(errJson.message || 'Yazıcı servisi yazdırma işlemini reddetti.');
            }

            return true;
        }
    }

    HK.PrintDrivers.HelperAppDriver = HelperAppDriver;

})(window.HizliKasa = window.HizliKasa || {});
