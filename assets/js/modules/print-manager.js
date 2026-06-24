/**
 * Hızlı Kasa - Yazdırma Yöneticisi (Print Manager)
 * 
 * Farklı yazdırma modları (fiş, barkod, rapor) için dinamik CSS ve @page kurallarını yönetir.
 */

(function(HK) {
    'use strict';

    HK.PrintManager = {
        
        // Element map
        modeToElement: {
            'receipt': '#fis-sablon',
            'barcode': '#hk-barcode-print-area',
            'report': '#gun-sonu-sablon',
            'report-receipt': '#report-fis-sablon',
            'coupon': '#fis-coupon-sablon'
        },

        // LocalStorage printer map
        modeToPrinterKey: {
            'receipt': 'hk_receipt_printer',
            'barcode': 'hk_barcode_printer',
            'report': 'hk_report_printer',
            'report-receipt': 'hk_receipt_printer',
            'coupon': 'hk_receipt_printer'
        },

        /**
         * Yazdırma işlemini başlatır
         * @param {'receipt'|'barcode'|'report'|'report-receipt'|'coupon'} mode - Yazdırma modu
         */
        print: function(mode) {
            var self = this;
            var token = localStorage.getItem('hk_print_token');
            var printerName = localStorage.getItem(this.modeToPrinterKey[mode] || '');
            var selector = this.modeToElement[mode];
            var element = selector ? document.querySelector(selector) : null;

            // Eğer yerel yazıcı kurulmuşsa ve element mevcutsa doğrudan sessiz yazdır
            if (token && printerName && element) {
                this.printSilently(element, printerName, token, function(success) {
                    if (!success) {
                        console.warn('Yerel servis yazdıramadı, normal yazdırmaya geçiliyor...');
                        self.printNative(mode);
                    }
                });
            } else {
                // Yerel servis yok veya ayarlanmamışsa normal tarayıcı yazdırmasına geç
                this.printNative(mode);
            }
        },

        /**
         * html2canvas kullanarak elementi yerel servise resim olarak gönderir
         */
        printSilently: function(element, printerName, token, callback) {
            if (typeof html2canvas !== 'function') {
                callback(false);
                return;
            }

            // Elementin görünürlüğünü geçici olarak aç (off-screen)
            var originalStyle = element.getAttribute('style') || '';
            element.style.display = 'block';
            element.style.position = 'absolute';
            element.style.left = '-9999px';
            element.style.top = '0';
            element.style.background = '#ffffff';

            // Barkodlar için daha yüksek çözünürlük ölçeği (tarayıcı barkodu okuyabilsin diye)
            var isBarcode = element.id === 'hk-barcode-print-area';
            var scale = isBarcode ? 3.0 : 2.0;

            html2canvas(element, {
                scale: scale,
                useCORS: true,
                logging: false,
                backgroundColor: '#ffffff'
            }).then(function(canvas) {
                // Orijinal stili geri yükle
                element.setAttribute('style', originalStyle);

                var imageData = canvas.toDataURL('image/png');
                
                // Servise gönder
                var port = 5001;
                var url = 'http://127.0.0.1:' + port + '/print';

                var xhr = new XMLHttpRequest();
                xhr.open('POST', url, true);
                xhr.setRequestHeader('Content-Type', 'application/json');
                xhr.setRequestHeader('Authorization', 'Bearer ' + token);
                xhr.timeout = 3000; // 3 saniye zaman aşımı

                xhr.onreadystatechange = function() {
                    if (xhr.readyState === 4) {
                        if (xhr.status === 200) {
                            console.log('Sessiz yazdırma başarılı:', printerName);
                            callback(true);
                        } else {
                            console.error('Yazdırma servisi hata verdi:', xhr.responseText);
                            callback(false);
                        }
                    }
                };

                xhr.ontimeout = function() {
                    console.error('Yazdırma servisi zaman aşımına uğradı.');
                    callback(false);
                };

                xhr.send(JSON.stringify({
                    printer_name: printerName,
                    image: imageData
                }));

            }).catch(function(err) {
                console.error('Görsel dönüştürme hatası:', err);
                element.setAttribute('style', originalStyle);
                callback(false);
            });
        },

        /**
         * Standart Tarayıcı Yazdırma (Fallback)
         */
        printNative: function(mode) {
            var body = document.body;
            var modeClass = 'print-mode-' + mode;
            
            // 1. Body'ye mod sınıfı ekle
            body.classList.add(modeClass);
            
            // 2. Dinamik @page stili enjekte et
            var pageStyle = document.getElementById('hk-dynamic-page-style');
            if (!pageStyle) {
                pageStyle = document.createElement('style');
                pageStyle.id = 'hk-dynamic-page-style';
                document.head.appendChild(pageStyle);
            }
            
            if (mode === 'barcode') {
                pageStyle.textContent = '@media print { @page { size: 50mm 35mm; margin: 0; } }';
            } else {
                pageStyle.textContent = '@media print { @page { size: auto; margin: 0; } }';
            }
            
            // 3. Temizlik
            var cleanup = function() {
                body.classList.remove(modeClass);
                var style = document.getElementById('hk-dynamic-page-style');
                if (style) {
                    style.textContent = '';
                }
                window.removeEventListener('afterprint', cleanup);
            };
            
            window.addEventListener('afterprint', cleanup);
            
            // 4. Yazdır
            setTimeout(function() {
                window.print();
            }, 50);
        }
    };

})(window.HizliKasa = window.HizliKasa || {});
