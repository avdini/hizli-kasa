<?php if (!defined('ABSPATH')) exit; ?>
<div class="hk-yazdirma-ayarlar-container" style="background:#fff; padding:20px; border-radius:5px; border:1px solid #ccd0d4; max-width:800px;">
    <h2>Hızlı Kasa Yerel Yazdırma Yardımcısı (Local Print Helper)</h2>
    <p>Bu ayarlar sadece bu bilgisayar/tarayıcı için geçerlidir. Farklı bilgisayarlarda (kasalarda) farklı yazıcılar seçebilirsiniz.</p>

    <!-- Durum Paneli -->
    <div id="hk-helper-status-card" style="padding:15px; margin-bottom:20px; border-radius:4px; border-left:4px solid #d63638; background:#f6f7f7;">
        <h3 style="margin-top:0;" id="hk-status-title">Durum: Bağlantı Kuruluyor...</h3>
        <p id="hk-status-desc">Yerel yazdırma servisi kontrol ediliyor.</p>
        <div id="hk-download-action" style="display:none; margin-top:15px;">
            <a href="<?php echo esc_url(HIZLI_KASA_URL . 'scratch/print_helper.py'); ?>" download="print_helper.py" class="button button-primary" style="margin-right:10px;">Yazdırma Yardımcısını İndir (.py)</a>
            <span class="description">İndirdikten sonra terminalde <code>pip install pywin32 Pillow</code> kurun ve <code>python print_helper.py</code> ile çalıştırın.</span>
        </div>
    </div>

    <!-- Eşleştirme ve Ayarlar Formu -->
    <div id="hk-settings-form-wrapper" style="display:none;">
        <table class="form-table">
            <tr valign="top">
                <th scope="row">Güvenlik Token'ı</th>
                <td>
                    <input type="text" id="hk-print-token" readonly class="regular-text" style="background:#f0f0f1; font-family:monospace;">
                    <button type="button" id="hk-pair-btn" class="button button-secondary" style="vertical-align:middle; margin-left:5px;">Yeniden Eşleştir</button>
                    <p class="description">Bilgisayarınızdaki yerel yazıcı servisinin sadece bu siteden gelen yazdırma isteklerini kabul etmesi için kullanılan benzersiz anahtardır.</p>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row">Fiş Yazıcısı (Termal)</th>
                <td>
                    <select id="hk-receipt-printer" style="min-width: 250px;">
                        <option value="">-- Yazıcı Seçin --</option>
                    </select>
                    <p class="description">Satış sonrası adisyon ve fişlerin otomatik basılacağı termal yazıcı.</p>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row">Barkod Yazıcısı (Etiket)</th>
                <td>
                    <select id="hk-barcode-printer" style="min-width: 250px;">
                        <option value="">-- Yazıcı Seçin --</option>
                    </select>
                    <p class="description">Barkod basım modüllerinden gönderilen etiketlerin basılacağı yazıcı.</p>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row">Rapor Yazıcısı</th>
                <td>
                    <select id="hk-report-printer" style="min-width: 250px;">
                        <option value="">-- Yazıcı Seçin --</option>
                    </select>
                    <p class="description">Gün sonu ve detaylı raporların basılacağı yazıcı.</p>
                </td>
            </tr>
        </table>
        
        <p class="submit">
            <button type="button" id="hk-save-print-settings" class="button button-primary">Yazdırma Ayarlarını Kaydet</button>
            <span id="hk-save-success-msg" style="color:#00a32a; font-weight:bold; margin-left:10px; display:none;">✓ Ayarlar kaydedildi!</span>
        </p>
    </div>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    const defaultPort = 5001;
    const helperUrl = `http://127.0.0.1:${defaultPort}`;
    
    // 1. Get or Generate Security Token in LocalStorage
    let token = localStorage.getItem('hk_print_token');
    if (!token) {
        token = 'hk_' + Math.random().toString(36).substring(2, 15) + Math.random().toString(36).substring(2, 15);
        localStorage.setItem('hk_print_token', token);
    }
    $('#hk-print-token').val(token);

    // Load saved printers from local storage
    const savedReceiptPrinter = localStorage.getItem('hk_receipt_printer') || '';
    const savedBarcodePrinter = localStorage.getItem('hk_barcode_printer') || '';
    const savedReportPrinter = localStorage.getItem('hk_report_printer') || '';

    // Check helper status
    checkHelperStatus();

    function checkHelperStatus() {
        $.ajax({
            url: `${helperUrl}/status`,
            type: 'GET',
            timeout: 2000,
            success: function(response) {
                // Connected!
                $('#hk-helper-status-card')
                    .css({
                        'border-left-color': '#00a32a',
                        'background': '#f0fcf5'
                    });
                
                if (response.paired) {
                    // Paired, load printers
                    $('#hk-status-title').text('Durum: Bağlantı Aktif (Eşleşti)');
                    $('#hk-status-desc').html('Yazdırma yardımcısı başarıyla bağlandı ve kullanıma hazır.');
                    $('#hk-download-action').hide();
                    $('#hk-settings-form-wrapper').show();
                    loadPrinters();
                } else {
                    // Running but not paired
                    $('#hk-status-title').text('Durum: Bağlantı Var (Eşleşme Bekleniyor)');
                    $('#hk-status-desc').html('Yerel program çalışıyor ancak bu web sitesiyle güvenli el sıkışma yapmadı. Lütfen <strong>Eşleştir</strong> butonuna basın.');
                    $('#hk-download-action').hide();
                    $('#hk-settings-form-wrapper').show();
                    $('#hk-pair-btn').text('Eşleştir ve Yetkilendir').addClass('button-primary');
                }
            },
            error: function() {
                // Not running
                $('#hk-helper-status-card')
                    .css({
                        'border-left-color': '#d63638',
                        'background': '#fcf0f1'
                    });
                $('#hk-status-title').text('Durum: Yazdırma Yardımcısı Çalışmıyor');
                $('#hk-status-desc').html('Yazdırma yardımcısı bilgisayarınızda açık değil veya henüz kurulmamış. Fişlerin doğrudan basılması için programın çalışması gerekmektedir.');
                $('#hk-download-action').show();
                $('#hk-settings-form-wrapper').hide();
            }
        });
    }

    // Trigger Handshake / Pairing
    $('#hk-pair-btn').on('click', function() {
        const self = $(this);
        self.prop('disabled', true).text('Eşleştiriliyor...');
        
        $.ajax({
            url: `${helperUrl}/pair`,
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({
                token: token,
                origin: window.location.origin
            }),
            headers: {
                'Authorization': `Bearer ${token}` // If already paired, we need this
            },
            success: function() {
                alert('Eşleştirme Başarılı! Yazıcı listesi alınıyor...');
                checkHelperStatus();
            },
            error: function(xhr) {
                alert('Eşleştirme başarısız: ' + (xhr.responseJSON ? xhr.responseJSON.error : 'Sunucu yanıt vermedi.'));
            },
            complete: function() {
                self.prop('disabled', false).text('Yeniden Eşleştir').removeClass('button-primary');
            }
        });
    });

    // Fetch printers list
    function loadPrinters() {
        $.ajax({
            url: `${helperUrl}/printers`,
            type: 'GET',
            headers: {
                'Authorization': `Bearer ${token}`
            },
            success: function(response) {
                const printers = response.printers || [];
                
                // Clear and populate dropdowns
                const selects = $('#hk-receipt-printer, #hk-barcode-printer, #hk-report-printer');
                selects.find('option:not(:first)').remove();
                
                printers.forEach(function(printer) {
                    selects.append(new Option(printer, printer));
                });

                // Restore saved choices
                $('#hk-receipt-printer').val(savedReceiptPrinter);
                $('#hk-barcode-printer').val(savedBarcodePrinter);
                $('#hk-report-printer').val(savedReportPrinter);
            },
            error: function() {
                console.error('Yazıcı listesi yüklenemedi.');
            }
        });
    }

    // Save choices to LocalStorage
    $('#hk-save-print-settings').on('click', function() {
        localStorage.setItem('hk_receipt_printer', $('#hk-receipt-printer').val());
        localStorage.setItem('hk_barcode_printer', $('#hk-barcode-printer').val());
        localStorage.setItem('hk_report_printer', $('#hk-report-printer').val());
        
        $('#hk-save-success-msg').fadeIn().delay(2000).fadeOut();
    });
});
</script>
