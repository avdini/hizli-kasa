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
            var sandbox = null;

            try {
                // 1. Eğer DOM elementi doğrudan aktarılmadıysa V2 REST API'den çek ve dinamik sandbox oluştur
                if (!options.element) {
                    var printData = await this.fetchPrintData(options);
                    sandbox = this.createSandbox(type, printData.rendered_html);
                    options.element = sandbox;
                }

                // 2. Sürücü Haritasından Yazıcı Adı Seçimi
                if (!options.printerName) {
                    options.printerName = this.getPrinterNameForType(type);
                }

                // 3. Uygun Sürücüyü Çalıştır
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
            } finally {
                // 4. Yazdırma işlemi bittiğinde sandbox elementini DOM'dan tamamen kaldır
                if (sandbox) {
                    this.destroySandbox(sandbox);
                }
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
                url += '?kasa_no=' + encodeURIComponent(options.kasa_no || '1') + '&depo_id=' + (options.depo_id || 0) + '&depo_name=' + encodeURIComponent(options.depo_name || '') + '&tarih=' + encodeURIComponent(options.tarih || '') + '&include_qr=' + (options.include_qr ? '1' : '0') + '&include_details=' + (options.include_details === false ? '0' : '1');
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

        createSandbox: function(type, html) {
            this.destroySandbox(); // Var olan eski kalıntı varsa temizle

            var sandbox = document.createElement('div');
            sandbox.id = 'hk-unified-print-container';
            sandbox.className = 'hk-unified-print-container print-type-' + type;

            var width = (type === 'barcode') ? '50mm' : '300px';

            Object.assign(sandbox.style, {
                display: 'block',
                position: 'fixed',
                left: '-9999px',
                top: '0',
                width: width,
                maxWidth: width,
                backgroundColor: '#ffffff',
                color: '#000000',
                zIndex: '-9999',
                overflow: 'hidden',
                boxSizing: 'border-box'
            });

            sandbox.innerHTML = html;
            document.body.appendChild(sandbox);
            return sandbox;
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
