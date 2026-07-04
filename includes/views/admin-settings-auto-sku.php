<?php
if (!defined('ABSPATH')) exit;

$aktif         = get_option('hizli_kasa_auto_sku_aktif', '0');
$prefix        = get_option('hizli_kasa_auto_sku_prefix', 'AVD-');
$cron_aktif    = get_option('hizli_kasa_auto_sku_cron_aktif', '0');
$cron_seconds  = get_option('hizli_kasa_auto_sku_cron_seconds', 3600);
$tipler        = get_option('hizli_kasa_auto_sku_tipler', ['simple', 'product_variation']);
$tetikleyiciler = get_option('hizli_kasa_auto_sku_tetikleyiciler', ['save', 'import']);

if (isset($_POST['hizli_kasa_save_auto_sku']) && check_admin_referer('hizli_kasa_auto_sku_actions', 'hizli_kasa_auto_sku_nonce')) {
    $aktif         = isset($_POST['hizli_kasa_auto_sku_aktif']) ? '1' : '0';
    $prefix        = sanitize_text_field($_POST['hizli_kasa_auto_sku_prefix']);
    $cron_aktif    = isset($_POST['hizli_kasa_auto_sku_cron_aktif']) ? '1' : '0';
    $cron_seconds  = max(10, intval($_POST['hizli_kasa_auto_sku_cron_seconds']));
    $tipler        = isset($_POST['hizli_kasa_auto_sku_tipler']) ? array_map('sanitize_text_field', $_POST['hizli_kasa_auto_sku_tipler']) : [];
    $tetikleyiciler = isset($_POST['hizli_kasa_auto_sku_tetikleyiciler']) ? array_map('sanitize_text_field', $_POST['hizli_kasa_auto_sku_tetikleyiciler']) : [];

    update_option('hizli_kasa_auto_sku_aktif', $aktif);
    update_option('hizli_kasa_auto_sku_prefix', $prefix);
    update_option('hizli_kasa_auto_sku_cron_aktif', $cron_aktif);
    update_option('hizli_kasa_auto_sku_cron_seconds', $cron_seconds);
    update_option('hizli_kasa_auto_sku_tipler', $tipler);
    update_option('hizli_kasa_auto_sku_tetikleyiciler', $tetikleyiciler);

    echo '<div class="notice notice-success is-dismissible"><p>Ayarlar başarıyla kaydedildi.</p></div>';
}
?>

<div class="hizli-kasa-auto-sku-wrap" style="max-width: 900px; margin-top: 20px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen-Sans, Ubuntu, Cantarell, 'Helvetica Neue', sans-serif;">
    <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); padding: 25px; margin-bottom: 25px;">
        <h3 style="margin-top: 0; color: #1e293b; font-size: 1.25rem; font-weight: 600; border-bottom: 1px solid #e2e8f0; padding-bottom: 15px;">Otomatik SKU Yapılandırması</h3>
        
        <form method="post" action="">
            <?php wp_nonce_field('hizli_kasa_auto_sku_actions', 'hizli_kasa_auto_sku_nonce'); ?>
            
            <table class="form-table">
                <tr valign="top">
                    <th scope="row" style="width: 250px; font-weight: 500; color: #475569;">Otomatik SKU Motoru</th>
                    <td>
                        <label class="hk-switch" style="position: relative; display: inline-block; width: 44px; height: 24px; vertical-align: middle; margin-right: 10px;">
                            <input type="checkbox" name="hizli_kasa_auto_sku_aktif" value="1" <?php checked($aktif, '1'); ?> style="opacity: 0; width: 0; height: 0;">
                            <span class="hk-slider" style="position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #cbd5e1; transition: .4s; border-radius: 24px;"></span>
                        </label>
                        <span style="font-size: 0.9rem; color: #64748b;">Eksik veya boş SKU'ları otomatik olarak doldurmayı etkinleştir.</span>
                    </td>
                </tr>

                <tr valign="top">
                    <th scope="row" style="font-weight: 500; color: #475569;">SKU Öneki (Prefix)</th>
                    <td>
                        <input type="text" name="hizli_kasa_auto_sku_prefix" value="<?php echo esc_attr($prefix); ?>" class="regular-text" style="border: 1px solid #cbd5e1; border-radius: 6px; padding: 6px 12px;" placeholder="Örn: AVD-">
                        <p class="description" style="color: #64748b; margin-top: 5px;">Üretilecek SKU'nun başına eklenecek harfler. Boş bırakırsanız direkt ürün/varyasyon ID'si SKU olur.</p>
                    </td>
                </tr>

                <tr valign="top">
                    <th scope="row" style="font-weight: 500; color: #475569;">Ürün Tipleri</th>
                    <td>
                        <fieldset>
                            <label style="display:block; margin-bottom: 8px; color: #334155;">
                                <input type="checkbox" name="hizli_kasa_auto_sku_tipler[]" value="simple" <?php checked(in_array('simple', $tipler)); ?>>
                                <span>Basit Ürünler (Simple Product)</span>
                            </label>
                            <label style="display:block; margin-bottom: 8px; color: #334155;">
                                <input type="checkbox" name="hizli_kasa_auto_sku_tipler[]" value="variable" <?php checked(in_array('variable', $tipler)); ?>>
                                <span>Değişken Ana Ürünler (Variable Product)</span>
                            </label>
                            <label style="display:block; margin-bottom: 8px; color: #334155;">
                                <input type="checkbox" name="hizli_kasa_auto_sku_tipler[]" value="product_variation" <?php checked(in_array('product_variation', $tipler)); ?>>
                                <span>Varyasyonlu Çocuk Ürünler (Product Variation)</span>
                            </label>
                        </fieldset>
                    </td>
                </tr>

                <tr valign="top">
                    <th scope="row" style="font-weight: 500; color: #475569;">Tetikleyici Olaylar</th>
                    <td>
                        <fieldset>
                            <label style="display:block; margin-bottom: 8px; color: #334155;">
                                <input type="checkbox" name="hizli_kasa_auto_sku_tetikleyiciler[]" value="save" <?php checked(in_array('save', $tetikleyiciler)); ?>>
                                <span>Ürün Eklendiğinde / Güncellendiğinde</span>
                            </label>
                            <label style="display:block; margin-bottom: 8px; color: #334155;">
                                <input type="checkbox" name="hizli_kasa_auto_sku_tetikleyiciler[]" value="import" <?php checked(in_array('import', $tetikleyiciler)); ?>>
                                <span>Ürün İçe Aktarıldığında (CSV Import vb.)</span>
                            </label>
                        </fieldset>
                    </td>
                </tr>

                <tr valign="top">
                    <th scope="row" style="font-weight: 500; color: #475569;">Arka Plan Cron Kontrolü</th>
                    <td>
                        <label class="hk-switch" style="position: relative; display: inline-block; width: 44px; height: 24px; vertical-align: middle; margin-right: 10px;">
                            <input type="checkbox" name="hizli_kasa_auto_sku_cron_aktif" value="1" <?php checked($cron_aktif, '1'); ?> style="opacity: 0; width: 0; height: 0;">
                            <span class="hk-slider" style="position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #cbd5e1; transition: .4s; border-radius: 24px;"></span>
                        </label>
                        <span style="font-size: 0.9rem; color: #64748b;">Belirli aralıklarla arka planda boş SKU'ları tarayıp otomatik düzelt.</span>
                    </td>
                </tr>

                <tr valign="top" class="hk-cron-row" style="<?php echo $cron_aktif === '1' ? '' : 'display: none;'; ?>">
                    <th scope="row" style="font-weight: 500; color: #475569;">Cron Taraması Sıklığı (Saniye)</th>
                    <td>
                        <input type="number" name="hizli_kasa_auto_sku_cron_seconds" value="<?php echo esc_attr($cron_seconds); ?>" min="10" step="10" class="small-text" style="border: 1px solid #cbd5e1; border-radius: 6px; padding: 6px 12px;"> Saniye
                        <p class="description" style="color: #64748b; margin-top: 5px;">Tavsiye edilen sıklık: `3600` (1 saat) veya `86400` (1 gün) saniyedir.</p>
                    </td>
                </tr>
            </table>

            <div style="margin-top: 20px; border-top: 1px solid #e2e8f0; padding-top: 15px;">
                <input type="submit" name="hizli_kasa_save_auto_sku" class="button button-primary" value="Ayarları Kaydet" style="background: #2563eb; border-color: #2563eb; box-shadow: none; font-weight: 500; padding: 2px 16px; height: auto; min-height: 32px; border-radius: 6px;">
            </div>
        </form>
    </div>

    <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); padding: 25px;">
        <h3 style="margin-top: 0; color: #1e293b; font-size: 1.25rem; font-weight: 600; border-bottom: 1px solid #e2e8f0; padding-bottom: 15px;">Toplu SKU Doldurma Aracı</h3>
        <p style="color: #475569; font-size: 0.95rem; line-height: 1.5; margin-bottom: 20px;">
            Sistemdeki tüm WooCommerce ürünlerini ve varyasyonlarını analiz ederek SKU'su boş olan ürünleri tek tıkla doldurabilirsiniz. 
            Bu işlem AJAX altyapısı kullanarak ürünlerinizi parça parça işler, sunucunuzu yormaz ve zaman aşımı (timeout) hatasına yol açmaz.
        </p>

        <div class="hk-bulk-stats" style="margin-bottom: 20px;">
            <div style="display: inline-block; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 12px 20px; min-width: 200px;">
                <span style="display: block; font-size: 0.8rem; color: #64748b; text-transform: uppercase; font-weight: 600; letter-spacing: 0.05em; margin-bottom: 4px;">Boş SKU'lu Ürün Sayısı</span>
                <span id="hk-empty-sku-count" style="font-size: 1.5rem; font-weight: 700; color: #0f172a;">Yükleniyor...</span>
            </div>
        </div>

        <div id="hk-progress-box" style="display: none; background: #f1f5f9; border-radius: 6px; padding: 15px 20px; margin-bottom: 20px;">
            <div style="display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 0.9rem; font-weight: 600; color: #334155;">
                <span id="hk-progress-label">İşlem Yapılıyor...</span>
                <span id="hk-progress-percent">0%</span>
            </div>
            <div style="background: #e2e8f0; border-radius: 8px; height: 10px; overflow: hidden; position: relative;">
                <div id="hk-progress-bar" style="background: #2563eb; width: 0%; height: 100%; transition: width 0.3s ease;"></div>
            </div>
            <div id="hk-progress-details" style="font-size: 0.8rem; color: #64748b; margin-top: 6px;">0 / 0 ürün işlendi.</div>
        </div>

        <div id="hk-bulk-actions">
            <button id="hk-start-bulk-btn" class="button button-secondary" disabled style="font-weight: 500; padding: 4px 20px; height: auto; min-height: 34px; border-radius: 6px; color: #2563eb; border-color: #2563eb;">
                ⚡ Şimdi SKU Boşluklarını Doldur
            </button>
            <button id="hk-stop-bulk-btn" class="button" style="display: none; font-weight: 500; padding: 4px 20px; height: auto; min-height: 34px; border-radius: 6px; color: #dc2626; border-color: #fca5a5; background: #fff5f5; margin-left: 10px;">
                Durdur
            </button>
        </div>
    </div>
</div>

<style>
.hk-switch input:checked + .hk-slider {
    background-color: #2563eb;
}
.hk-switch input:checked + .hk-slider:before {
    transform: translateX(20px);
}
.hk-slider:before {
    position: absolute;
    content: "";
    height: 18px;
    width: 18px;
    left: 3px;
    bottom: 3px;
    background-color: white;
    transition: .4s;
    border-radius: 50%;
}
</style>

<script>
jQuery(document).ready(function($) {
    $('input[name="hizli_kasa_auto_sku_cron_aktif"]').on('change', function() {
        if ($(this).is(':checked')) {
            $('.hk-cron-row').fadeIn(200);
        } else {
            $('.hk-cron-row').fadeOut(200);
        }
    });

    let totalToProcess = 0;
    let processedCount = 0;
    let isProcessing = false;
    const batchLimit = 50;

    function loadStatus() {
        $.ajax({
            url: '<?php echo esc_url_raw(rest_url('hizli-kasa/v2/auto-sku/status')); ?>',
            method: 'GET',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', '<?php echo wp_create_nonce('wp_rest'); ?>');
            },
            success: function(response) {
                if (response.success) {
                    totalToProcess = response.data.total_empty_skus;
                    $('#hk-empty-sku-count').text(totalToProcess + ' Ürün');
                    if (totalToProcess > 0) {
                        $('#hk-start-bulk-btn').removeAttr('disabled');
                    } else {
                        $('#hk-start-bulk-btn').attr('disabled', 'disabled');
                    }
                } else {
                    $('#hk-empty-sku-count').text('Hata oluştu');
                }
            },
            error: function() {
                $('#hk-empty-sku-count').text('Hata oluştu');
            }
        });
    }

    loadStatus();

    $('#hk-start-bulk-btn').on('click', function(e) {
        e.preventDefault();
        if (totalToProcess <= 0 || isProcessing) return;

        isProcessing = true;
        processedCount = 0;

        $('#hk-start-bulk-btn').attr('disabled', 'disabled').text('İşlem Başlatılıyor...');
        $('#hk-stop-bulk-btn').show();
        $('#hk-progress-box').slideDown();

        updateProgress();
        runBatch();
    });

    $('#hk-stop-bulk-btn').on('click', function(e) {
        e.preventDefault();
        isProcessing = false;
        $(this).hide();
        $('#hk-start-bulk-btn').removeAttr('disabled').text('⚡ Şimdi SKU Boşluklarını Doldur');
        $('#hk-progress-label').text('İşlem durduruldu.');
    });

    function runBatch() {
        if (!isProcessing) return;

        $.ajax({
            url: '<?php echo esc_url_raw(rest_url('hizli-kasa/v2/auto-sku/generate')); ?>',
            method: 'POST',
            data: JSON.stringify({ limit: batchLimit }),
            contentType: 'application/json',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', '<?php echo wp_create_nonce('wp_rest'); ?>');
            },
            success: function(response) {
                if (!isProcessing) return;

                if (response.success) {
                    let batchProcessed = response.data.processed;
                    processedCount += batchProcessed;
                    let remaining = response.data.remaining;

                    $('#hk-empty-sku-count').text(remaining + ' Ürün');

                    updateProgress();

                    if (remaining > 0 && batchProcessed > 0) {
                        runBatch();
                    } else {
                        isProcessing = false;
                        $('#hk-stop-bulk-btn').hide();
                        $('#hk-progress-label').text('Tamamlandı! Tüm boş SKU alanları dolduruldu.');
                        $('#hk-progress-bar').css('background', '#10b981');
                        $('#hk-start-bulk-btn').text('⚡ Yeniden Kontrol Et');
                        $('#hk-start-bulk-btn').removeAttr('disabled');
                        loadStatus();
                    }
                } else {
                    handleError('API hatası: ' + (response.errors ? response.errors.join(', ') : 'Bilinmeyen hata'));
                }
            },
            error: function(xhr) {
                handleError('Sunucu hatası oluştu. Lütfen tekrar deneyin.');
            }
        });
    }

    function updateProgress() {
        let percent = 0;
        if (totalToProcess > 0) {
            percent = Math.min(100, Math.round((processedCount / totalToProcess) * 100));
        }

        $('#hk-progress-bar').css('width', percent + '%');
        $('#hk-progress-percent').text(percent + '%');
        $('#hk-progress-details').text(processedCount + ' / ' + totalToProcess + ' ürün işlendi.');
        $('#hk-progress-label').text('SKU üretiliyor...');
    }

    function handleError(message) {
        isProcessing = false;
        $('#hk-stop-bulk-btn').hide();
        $('#hk-start-bulk-btn').removeAttr('disabled').text('⚡ Şimdi SKU Boşluklarını Doldur');
        $('#hk-progress-label').text('Hata: ' + message);
        $('#hk-progress-bar').css('background', '#ef4444');
    }
});
</script>
