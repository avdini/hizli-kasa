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
            var silentEnabled = localStorage.getItem('hk_silent_print_enabled') === '1';
            var token = localStorage.getItem('hk_print_token');
            var printerName = localStorage.getItem(this.modeToPrinterKey[mode] || '');
            var selector = this.modeToElement[mode];
            var element = selector ? document.querySelector(selector) : null;

            // Eğer sessiz yazdırma aktifse, token varsa, yazıcı seçilmişse ve element mevcutsa sessiz yazdır
            if (silentEnabled && token && printerName && element) {
                this.printSilently(element, printerName, token, function(success) {
                    if (!success) {
                        console.warn('Yerel servis yazdıramadı, normal yazdırmaya geçiliyor...');
                        self.printNative(mode);
                    }
                });
            } else {
                // Değilse doğrudan tarayıcı yazdırmasına geç (hiç bekleme/tarama yapma)
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
            var self = this;

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
                
                // Barkodlar için resmi 90 derece saat yönünde çevir (Pillow için 270 derece)
                var rotate = isBarcode ? 270 : 0;

                // Dinamik aktif portu bul ve servise gönder
                self.findActivePort(function(activePort) {
                    if (!activePort) {
                        console.error('Yazdırma servisi aktif portta bulunamadı.');
                        callback(false);
                        return;
                    }

                    var url = 'http://127.0.0.1:' + activePort + '/print';

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
                        image: imageData,
                        rotate: rotate
                    }));
                });

            }).catch(function(err) {
                console.error('Görsel dönüştürme hatası:', err);
                element.setAttribute('style', originalStyle);
                callback(false);
            });
        },

        /**
         * Aktif portu tespit eder (5001-5010 aralığında)
         */
        findActivePort: function(callback) {
            var cachedPort = localStorage.getItem('hk_print_port') || 5001;
            
            // İlk olarak cache'deki portu hızlıca kontrol et (500ms timeout)
            var controller = new AbortController();
            var timeoutId = setTimeout(function() { controller.abort(); }, 500);
            
            fetch('http://127.0.0.1:' + cachedPort + '/status', {
                method: 'GET',
                signal: controller.signal
            }).then(function(res) {
                clearTimeout(timeoutId);
                if (res.ok) {
                    callback(cachedPort);
                } else {
                    scanAll();
                }
            }).catch(function() {
                clearTimeout(timeoutId);
                scanAll();
            });

            function scanAll() {
                var ports = [5001, 5002, 5003, 5004, 5005, 5006, 5007, 5008, 5009, 5010];
                var found = false;
                var checkedCount = 0;

                ports.forEach(function(p) {
                    if (p == cachedPort) {
                        checkedCount++;
                        if (checkedCount === ports.length && !found) {
                            callback(null);
                        }
                        return;
                    }
                    
                    var ctrl = new AbortController();
                    var tId = setTimeout(function() { ctrl.abort(); }, 800);
                    
                    fetch('http://127.0.0.1:' + p + '/status', {
                        method: 'GET',
                        signal: ctrl.signal
                    }).then(function(res) {
                        clearTimeout(tId);
                        if (res.ok && !found) {
                            found = true;
                            localStorage.setItem('hk_print_port', p);
                            callback(p);
                        } else {
                            doneCheck();
                        }
                    }).catch(function() {
                        clearTimeout(tId);
                        doneCheck();
                    });

                    function doneCheck() {
                        checkedCount++;
                        if (checkedCount === ports.length && !found) {
                            callback(null);
                        }
                    }
                });
            }
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
        },

        init: function() {
            var self = this;
            document.addEventListener('hkTabLoaded', function(e) {
                if (e.detail.tab === 'ayarlar') {
                    self.initSettingsTab();
                }
            });

            // Eğer ayarlar sekmesi zaten açık yüklenirse
            if (document.querySelector('.terminal-ayarlar-konteyner')) {
                self.initSettingsTab();
            }
        },

        /**
         * Hızlı Kasa Terminal Ayarları sekmesi yüklendiğinde ayarlar panelini ve olayları başlatır.
         */
        initSettingsTab: function() {
            var $ = window.jQuery;
            if (!$) return;

            // 1. Alt Sekme Geçişleri (Sub-tab switching)
            $('.ayarlar-alt-btn').on('click', function() {
                var target = $(this).data('target');
                $('.ayarlar-alt-btn').removeClass('aktif');
                $(this).addClass('aktif');
                
                $('.ayarlar-icerik-paneli').hide();
                $('#' + target).show();
            });

            // 2. Yazdırma Ayarları Mantığı
            var currentPort = localStorage.getItem('hk_print_port') || 5001;
            var helperUrl = 'http://127.0.0.1:' + currentPort;
            
            // Güvenlik Token'ını al veya üret
            var token = localStorage.getItem('hk_print_token');
            if (!token) {
                token = 'hk_' + Math.random().toString(36).substring(2, 15) + Math.random().toString(36).substring(2, 15);
                localStorage.setItem('hk_print_token', token);
            }
            $('#hk-print-token').val(token);

            // Kaydedilmiş yazıcıları al
            var savedReceiptPrinter = localStorage.getItem('hk_receipt_printer') || '';
            var savedBarcodePrinter = localStorage.getItem('hk_barcode_printer') || '';
            var savedReportPrinter = localStorage.getItem('hk_report_printer') || '';

            // Sessiz Yazdırma Durumunu Yükle
            var silentEnabled = localStorage.getItem('hk_silent_print_enabled') === '1';
            $('#hk-silent-print-enabled').prop('checked', silentEnabled);
            
            if (silentEnabled) {
                $('#hk-helper-config-container').show();
                checkHelperStatus();
            } else {
                $('#hk-helper-config-container').hide();
            }

            // Checkbox durum değişimini izle
            $('#hk-silent-print-enabled').on('change', function() {
                if (this.checked) {
                    $('#hk-helper-config-container').fadeIn();
                    checkHelperStatus();
                } else {
                    $('#hk-helper-config-container').fadeOut();
                }
            });

            // Port tarama fonksiyonu
            function scanActivePort(callback) {
                var ports = [5001, 5002, 5003, 5004, 5005, 5006, 5007, 5008, 5009, 5010];
                var found = false;
                var checked = 0;
                
                // Cache'deki portu hızlıca dene
                $.ajax({
                    url: 'http://127.0.0.1:' + currentPort + '/status',
                    type: 'GET',
                    timeout: 600,
                    success: function() {
                        found = true;
                        callback(currentPort);
                    },
                    error: function() {
                        // Cache'deki port başarısız olursa tüm aralığı tara
                        ports.forEach(function(p) {
                            if (p == currentPort) {
                                checked++;
                                if (checked === ports.length && !found) callback(null);
                                return;
                            }
                            $.ajax({
                                url: 'http://127.0.0.1:' + p + '/status',
                                type: 'GET',
                                timeout: 800,
                                success: function(response) {
                                    if (!found) {
                                        found = true;
                                        currentPort = p;
                                        localStorage.setItem('hk_print_port', p);
                                        helperUrl = 'http://127.0.0.1:' + p;
                                        callback(p);
                                    }
                                },
                                complete: function() {
                                    checked++;
                                    if (checked === ports.length && !found) {
                                        callback(null);
                                    }
                                }
                            });
                        });
                    }
                });
            }

            function checkHelperStatus() {
                scanActivePort(function(activePort) {
                    if (activePort) {
                        $.ajax({
                            url: helperUrl + '/status',
                            type: 'GET',
                            timeout: 2000,
                            success: function(response) {
                                $('#hk-helper-status-card').css({
                                    'border-left-color': '#00a32a',
                                    'background': 'var(--hk-bg-card)'
                                });
                                
                                if (response.paired) {
                                    $('#hk-status-title').text('Durum: Bağlantı Aktif (Port: ' + activePort + ')');
                                    $('#hk-status-desc').html('Yazdırma yardımcısı başarıyla bağlandı ve kullanıma hazır.');
                                    $('#hk-download-action').hide();
                                    $('#hk-settings-form-wrapper').show();
                                    loadPrinters();
                                } else {
                                    $('#hk-status-title').text('Durum: Bağlantı Var (Port: ' + activePort + ' - Eşleşme Bekleniyor)');
                                    $('#hk-status-desc').html('Yerel program çalışıyor ancak bu web sitesiyle güvenli el sıkışma yapmadı. Lütfen <strong>Eşleştir</strong> butonuna basın.');
                                    $('#hk-download-action').hide();
                                    $('#hk-settings-form-wrapper').show();
                                    $('#hk-pair-btn').text('Eşleştir ve Yetkilendir').css({
                                        'background': 'var(--hk-accent)',
                                        'color': 'white',
                                        'border': 'none'
                                    });
                                }
                            }
                        });
                    } else {
                        $('#hk-helper-status-card').css({
                            'border-left-color': '#d63638',
                            'background': 'var(--hk-bg-card)'
                        });
                        $('#hk-status-title').text('Durum: Yazdırma Yardımcısı Çalışmıyor');
                        $('#hk-status-desc').html('Yazdırma yardımcısı bilgisayarınızda açık değil veya henüz kurulmamış. Fişlerin doğrudan basılması için programın çalışıyor olması gerekmektedir.');
                        $('#hk-download-action').show();
                        $('#hk-settings-form-wrapper').hide();
                    }
                });
            }

            // Eşleştirme (Pairing) tetikleyici
            $('#hk-pair-btn').on('click', function() {
                var selfBtn = $(this);
                selfBtn.prop('disabled', true).text('Eşleştiriliyor...');
                
                $.ajax({
                    url: helperUrl + '/pair',
                    type: 'POST',
                    contentType: 'application/json',
                    data: JSON.stringify({
                        token: token,
                        origin: window.location.origin
                    }),
                    headers: {
                        'Authorization': 'Bearer ' + token
                    },
                    success: function() {
                        alert('Eşleştirme Başarılı! Yazıcı listesi alınıyor...');
                        checkHelperStatus();
                    },
                    error: function(xhr) {
                        alert('Eşleştirme başarısız: ' + (xhr.responseJSON ? xhr.responseJSON.error : 'Sunucu yanıt vermedi.'));
                    },
                    complete: function() {
                        selfBtn.prop('disabled', false).text('Yeniden Eşleştir').css({
                            'background': 'var(--hk-bg-body)',
                            'color': 'var(--hk-text-main)',
                            'border': '1px solid var(--hk-border)'
                        });
                    }
                });
            });

            // Yazıcı listesini çek
            function loadPrinters() {
                $.ajax({
                    url: helperUrl + '/printers',
                    type: 'GET',
                    headers: {
                        'Authorization': 'Bearer ' + token
                    },
                    success: function(response) {
                        var printers = response.printers || [];
                        var selects = $('#hk-receipt-printer, #hk-barcode-printer, #hk-report-printer');
                        
                        selects.find('option:not(:first)').remove();
                        printers.forEach(function(printer) {
                            selects.append(new Option(printer, printer));
                        });

                        $('#hk-receipt-printer').val(savedReceiptPrinter);
                        $('#hk-barcode-printer').val(savedBarcodePrinter);
                        $('#hk-report-printer').val(savedReportPrinter);
                    },
                    error: function() {
                        console.error('Yazıcı listesi yüklenemedi.');
                    }
                });
            }

            // Ayarları kaydet
            $('#hk-save-print-settings').on('click', function() {
                var isEnabled = $('#hk-silent-print-enabled').is(':checked') ? '1' : '0';
                localStorage.setItem('hk_silent_print_enabled', isEnabled);
                localStorage.setItem('hk_receipt_printer', $('#hk-receipt-printer').val());
                localStorage.setItem('hk_barcode_printer', $('#hk-barcode-printer').val());
                localStorage.setItem('hk_report_printer', $('#hk-report-printer').val());
                
                $('#hk-save-success-msg').fadeIn().delay(2000).fadeOut();
            });
        }
    };

    HK.PrintManager.init();

    // Export variables or close IIFE cleanly
})(window.HizliKasa = window.HizliKasa || {});
