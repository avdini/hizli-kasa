/**
 * Hızlı Kasa - Birleşik Yazdırma Çekirdeği (PrintCore)
 */
(function(HK) {
    'use strict';

    HK.PrintCore = {
        drivers: {},

        init: function() {
            if (HK.PrintDrivers.HelperAppDriver) {
                this.drivers['helper-app'] = new HK.PrintDrivers.HelperAppDriver();
            }
            if (HK.PrintDrivers.BrowserNativeDriver) {
                this.drivers['browser-native'] = new HK.PrintDrivers.BrowserNativeDriver();
            }
        },

        /**
         * Yazdırma İşlemini Tetikler
         * @param {Object} options { type: 'order'|'zreport'|'barcode', id: orderId, silent: boolean, printerName: string }
         */
        print: async function(options) {
            options = options || {};
            var type = options.type || 'order';

            try {
                // 1. Fiş HTML'ini al (eğer parametrede verilmediyse V2 REST API'den çek)
                var html = options.html;
                if (!html && options.element) {
                    html = options.element.outerHTML || options.element.innerHTML;
                }
                if (!html) {
                    var printData = await this.fetchPrintData(options);
                    html = printData.rendered_html;
                }

                // 2. İzole edilmiş görünmez Iframe içine yazdırılacak içeriği yerleştir
                var iframeBody = this.prepareIframe(type, html);
                options.element = iframeBody;

                // 3. Sürücü Haritasından Yazıcı Adı Seçimi
                if (!options.printerName) {
                    options.printerName = this.getPrinterNameForType(type);
                }

                // 4. Uygun Sürücüyü Çalıştır
                var helperDriver = this.drivers['helper-app'];
                var isHelperAvailable = helperDriver && await helperDriver.isAvailable();

                if (isHelperAvailable && options.silent !== false) {
                    await helperDriver.print(options);
                } else {
                    var nativeDriver = this.drivers['browser-native'];
                    await nativeDriver.print(options);
                }

                return true;
            } catch (err) {
                console.error('[PrintCore Error]', err);
                if (window.HK && window.HK.UI && typeof window.HK.UI.alert === 'function') {
                    await window.HK.UI.alert({ title: '🖨️ Yazdırma Hatası', message: err.message, type: 'error' });
                } else if (HK.UIRenderer && typeof HK.UIRenderer.showToast === 'function') {
                    HK.UIRenderer.showToast('Yazdırma Hatası: ' + err.message, 'error', true);
                }
                throw err;
            }
        },

        fetchPrintData: async function(options) {
            var type = options.type || 'order';
            var endpoint = type;
            if (type === 'zreport' || type === 'z-report') {
                endpoint = 'z-report';
            }
            var url = kasaAyar.rootApiUrl + 'hizli-kasa/v2/print/' + endpoint;

            if (type === 'order') {
                url += '/' + (options.id || options.order_id || 0);
            } else if (type === 'zreport' || type === 'z-report') {
                var formatVal = options.format || (options.include_details === true ? 'detayli' : (options.include_details === 'basit' ? 'basit' : 'ozet'));
                url += '?kasa_no=' + encodeURIComponent(options.kasa_no || '1') +
                       '&depo_id=' + (options.depo_id || 0) +
                       '&tarih=' + encodeURIComponent(options.tarih || '') +
                       '&include_qr=' + (options.include_qr ? '1' : '0') +
                       '&format=' + encodeURIComponent(formatVal) +
                       '&include_details=' + encodeURIComponent(formatVal);
            }

            var method = (type === 'barcode') ? 'POST' : 'GET';
            var fetchOpts = {
                method: method,
                headers: {
                    'X-WP-Nonce': kasaAyar.nonce,
                    'Content-Type': 'application/json'
                }
            };

            if (type === 'barcode') {
                fetchOpts.body = JSON.stringify({
                    product_id: options.product_id || 0,
                    variation_id: options.variation_id || 0,
                    qty: options.qty || 1
                });
            }

            var response = await fetch(url, fetchOpts);
            var json = await response.json();

            if (!response.ok || !json.success) {
                var msg = (json && json.errors && json.errors.length) ? json.errors.join(', ') : 'Yazdırma verisi alınamadı.';
                throw new Error(msg);
            }

            return json.data;
        },

        getPrintIframe: function() {
            var iframe = document.getElementById('hk-print-iframe');
            if (!iframe) {
                iframe = document.createElement('iframe');
                iframe.id = 'hk-print-iframe';
                iframe.name = 'hk-print-iframe';
                Object.assign(iframe.style, {
                    position: 'fixed',
                    right: '0',
                    bottom: '0',
                    width: '300px',
                    height: '600px',
                    border: 'none',
                    opacity: '0',
                    pointerEvents: 'none',
                    zIndex: '-1'
                });
                document.body.appendChild(iframe);
            }
            return iframe;
        },

        prepareIframe: function(type, html) {
            var iframe = this.getPrintIframe();
            var width = (type === 'barcode') ? '50mm' : '300px';
            iframe.style.width = width;

            var doc = iframe.contentDocument || iframe.contentWindow.document;
            doc.open();

            var barcodeCss = (type === 'barcode') ?
                '@import url("https://fonts.googleapis.com/css2?family=Inter:wght@100..900&display=swap"); ' +
                '@page { size: 50mm 35mm; margin: 0; } ' +
                'html, body { margin: 0; padding: 0; background: #ffffff; color: #000000; font-family: "Inter", sans-serif; width: 50mm; box-sizing: border-box; overflow: hidden; } ' +
                '.barcode-label { width: 50mm; height: 35mm; max-height: 35mm; padding: 2mm; box-sizing: border-box; background: #fff; color: #000; display: flex; flex-direction: column; page-break-after: always; break-after: page; overflow: hidden; font-family: "Inter", sans-serif; } ' +
                '.barcode-label:last-child { page-break-after: avoid !important; break-after: auto !important; } ' +
                '.label-header { text-align: center; margin-bottom: 1mm; } ' +
                '.product-name { font-size: 9pt; font-weight: 800; line-height: 1.1; display: -webkit-box; -webkit-line-clamp: 2; line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; text-transform: uppercase; } ' +
                '.label-body { display: flex; flex: 1; gap: 1mm; padding-left: 2mm; } ' +
                '.col-left { width: 48%; display: flex; flex-direction: column; justify-content: flex-end; align-items: center; padding-bottom: 1mm; } ' +
                '.col-right { width: 52%; display: flex; flex-direction: column; justify-content: flex-end; padding-left: 1mm; padding-right: 1.5mm; padding-bottom: 0.2mm; } ' +
                '.model-no { font-size: 6pt; font-weight: 600; margin-bottom: 0; transform: translateY(-3mm); } ' +
                '.barcode-container { width: 100%; height: 11mm; display: flex; align-items: center; justify-content: center; margin: 0; overflow: hidden; transform: translateY(-3mm); } ' +
                '.barcode-svg { width: 100% !important; height: 100% !important; display: block; } ' +
                '.sku-text { font-size: 7pt; font-weight: 700; margin-top: 0; transform: translateY(-3mm); } ' +
                '.attributes { font-size: 7pt; line-height: 1.2; } ' +
                '.attr-color { margin-bottom: 0.7mm; display: flex; align-items: center; gap: 2px; line-height: 0.9; transform: translateY(-3mm); max-width: 100%; overflow: hidden; } ' +
                '.attr-size { margin-bottom: 0mm; display: flex; align-items: center; gap: 2px; line-height: 0.9; transform: translateY(-3mm); max-width: 100%; overflow: hidden; } ' +
                '.color-label, .size-label { font-family: "Inter", sans-serif !important; font-weight: 100 !important; font-size: 7pt; color: #000; white-space: nowrap; display: inline-block; transform: scaleX(0.8); transform-origin: left center; } ' +
                '.color-val { font-size: 8pt; font-weight: 600; line-height: 0.9; white-space: nowrap; overflow: hidden; display: block; max-width: 100%; } ' +
                '.color-val.text-shrink { font-size: 6.5pt; } ' +
                '.size-val { font-size: 14pt; font-weight: 900; line-height: 0.9; white-space: nowrap; overflow: hidden; display: block; max-width: 100%; } ' +
                '.size-val.text-shrink { font-size: 10pt; } ' +
                '.price-section { margin-top: -1.5mm; text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; font-feature-settings: "tnum"; } ' +
                '.price-single { font-size: 13pt; font-weight: 900; letter-spacing: -0.5px; } ' +
                '.price-old { font-size: 10pt; color: #000; position: relative; text-decoration: none; display: inline-block; line-height: 0.9; margin-bottom: -0.5mm; } ' +
                '.price-old::after { content: ""; position: absolute; left: 0; top: 50%; width: 100%; height: 1px; background: #000; transform: translateY(-50%) scaleY(0.3); transform-origin: center; } ' +
                '.price-new { font-size: 13.5pt; font-weight: 900; letter-spacing: -0.8px; line-height: 1; } ' :
                '@page { size: auto; margin: 0; } ' +
                'html, body { margin: 0; padding: 0; background: #ffffff; color: #000000; font-family: "Courier New", Courier, monospace; width: ' + width + '; box-sizing: border-box; } ' +
                '.hk-unified-print-container { padding: 4px 8px !important; box-sizing: border-box !important; } ' +
                'table { border-collapse: collapse; width: 100%; border: none; margin: 0; padding: 0; } ' +
                'td, th { padding: 2px 0; border: none; color: #000000; font-family: "Courier New", Courier, monospace; } ' +
                '.fis-item-td-left { padding: 1px 0; line-height: 1.1; } ' +
                '.fis-item-name { font-weight: bold; font-size: 12px; text-transform: uppercase; } ' +
                '.fis-item-sku-qty { font-size: 10px; } ' +
                '.fis-item-td-right { text-align: right; padding: 1px 0 1px 10px; vertical-align: middle; white-space: nowrap; } ' +
                '.fis-item-price { font-weight: bold; font-size: 13px; } ';

            doc.write('<!DOCTYPE html><html><head><meta charset="utf-8"><style>' + barcodeCss + '</style></head><body>' + html + '</body></html>');
            doc.close();

            var jsBarcodeFn = window.JsBarcode || (iframe.contentWindow && iframe.contentWindow.JsBarcode);
            if (typeof jsBarcodeFn === 'function') {
                var barcodeImgs = doc.querySelectorAll('.hk-print-barcode-img, [data-barcode]');
                barcodeImgs.forEach(function(img) {
                    var val = img.dataset ? img.dataset.barcode : img.getAttribute('data-barcode');
                    if (val) {
                        try {
                            jsBarcodeFn(img, val, {
                                format: 'CODE128',
                                width: 2,
                                height: 50,
                                displayValue: false,
                                margin: 0,
                                background: '#ffffff',
                                lineColor: '#000000'
                            });
                        } catch (err) {
                            console.error('[PrintCore] Barcode render error:', err);
                        }
                    }
                });
            }

            return doc.body;
        },

        destroySandbox: function(el) {
            var target = el || document.getElementById('hk-unified-print-container');
            if (target && target.parentNode) {
                target.parentNode.removeChild(target);
            }
        },

        getPrinterNameForType: function(type) {
            if (type === 'barcode') {
                return localStorage.getItem('hk_barcode_printer') || '';
            }
            if (type === 'zreport' || type === 'report') {
                return localStorage.getItem('hk_report_printer') || '';
            }
            return localStorage.getItem('hk_receipt_printer') || '';
        }
    };

    HK.PrintCore.init();

})(window.HizliKasa = window.HizliKasa || {});
