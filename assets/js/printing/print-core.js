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
                // 1. Eğer DOM'da önceden oluşturulmuş bir alan varsa (örn: Toplu Barkod #hk-barcode-print-area) kullan
                if (!options.element) {
                    var modeSelector = HK.PrintManager && HK.PrintManager.modeToElement ? HK.PrintManager.modeToElement[type] : null;
                    if (modeSelector) {
                        var existing = document.querySelector(modeSelector);
                        if (existing && existing.children.length > 0) {
                            options.element = existing;
                        }
                    }
                }

                // 2. Eğer DOM elementi henüz yoksa V2 REST API'den çek
                if (!options.element) {
                    var printData = await this.fetchPrintData(options);
                    var container = this.getOrCreateContainer();
                    container.innerHTML = printData.rendered_html;
                    options.element = container.firstElementChild || container;
                }

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

                return printData;
            } catch (err) {
                console.error('[PrintCore Error]', err);
                if (HK.ModalManager && typeof HK.ModalManager.alert === 'function') {
                    HK.ModalManager.alert('Yazdırma Hatası: ' + err.message);
                } else {
                    alert('Yazdırma Hatası: ' + err.message);
                }
                throw err;
            }
        },

        fetchPrintData: async function(options) {
            var type = options.type || 'order';
            var url = kasaAyar.rootApiUrl + 'hizli-kasa/v2/print/' + type;

            if (type === 'order') {
                url += '/' + (options.id || options.order_id || 0);
            } else if (type === 'zreport') {
                url += '?kasa_no=' + (options.kasa_no || '1') + '&depo_id=' + (options.depo_id || 0) + '&tarih=' + (options.tarih || '');
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
                el.style.display = 'none';
                document.body.appendChild(el);
            }
            return el;
        },

        getPrinterNameForType: function(type) {
            if (type === 'barcode') {
                return localStorage.getItem('hk_barcode_printer') || '';
            }
            if (type === 'zreport') {
                return localStorage.getItem('hk_report_printer') || '';
            }
            return localStorage.getItem('hk_receipt_printer') || '';
        }
    };

    HK.PrintCore.init();

})(window.HizliKasa = window.HizliKasa || {});
