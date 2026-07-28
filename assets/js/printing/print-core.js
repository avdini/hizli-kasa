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
                url += '?kasa_no=' + encodeURIComponent(options.kasa_no || '1') + '&depo_id=' + (options.depo_id || 0) + '&tarih=' + encodeURIComponent(options.tarih || '');
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
            doc.write('<!DOCTYPE html><html><head><meta charset="utf-8">' +
                '<style>' +
                '@page { size: ' + (type === 'barcode' ? '50mm 35mm' : 'auto') + '; margin: 0; } ' +
                'html, body { margin: 0; padding: 0; background: #ffffff; color: #000000; font-family: "Courier New", Courier, monospace; width: ' + width + '; box-sizing: border-box; } ' +
                'table { border-collapse: collapse; width: 100%; border: none; margin: 0; padding: 0; } ' +
                'td, th { padding: 2px 0; border: none; color: #000000; font-family: "Courier New", Courier, monospace; } ' +
                '.fis-item-td-left { padding: 1px 0; line-height: 1.1; } ' +
                '.fis-item-name { font-weight: bold; font-size: 12px; text-transform: uppercase; } ' +
                '.fis-item-sku-qty { font-size: 10px; } ' +
                '.fis-item-td-right { text-align: right; padding: 1px 0 1px 10px; vertical-align: middle; white-space: nowrap; } ' +
                '.fis-item-price { font-weight: bold; font-size: 13px; } ' +
                '</style></head><body>' + html + '</body></html>');
            doc.close();

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
