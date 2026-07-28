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
                // 1. Eğer DOM elementi doğrudan aktarılmadıysa V2 REST API'den çek
                if (!options.element) {
                    var printData = await this.fetchPrintData(options);
                    var container = this.getOrCreateContainer();
                    container.innerHTML = printData.rendered_html;
                    options.element = container.firstElementChild || container;
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

        getOrCreateContainer: function() {
            var el = document.getElementById('hk-unified-print-container');
            if (!el) {
                el = document.createElement('div');
                el.id = 'hk-unified-print-container';
                document.body.appendChild(el);
            }
            el.style.display = 'block';
            el.style.position = 'fixed';
            el.style.left = '-9999px';
            el.style.top = '0';
            el.style.width = '300px';
            el.style.background = '#ffffff';
            el.style.zIndex = '999999';
            return el;
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
