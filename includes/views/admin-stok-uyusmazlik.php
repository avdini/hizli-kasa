<?php
if (!defined('ABSPATH')) exit;

global $wpdb;
$table = $wpdb->prefix . 'hizli_kasa_unmatched_items';
$results = $wpdb->get_results("SELECT * FROM $table ORDER BY created_at DESC");
?>

<div class="hizli-kasa-admin-stock-wrap">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; background:#fff; padding:15px; border-radius:8px; box-shadow:0 1px 3px rgba(0,0,0,0.1);">
        <h2 style="margin:0; color:#d63638; font-size:22px;">
            <span class="dashicons dashicons-warning" style="font-size:24px; width:24px; height:24px; margin-top:2px;"></span> 
            Eşleşmeyen Ürünler
        </h2>
        <div>
            <button type="button" class="button button-link-delete" id="btn-clear-all-unmatched" style="<?php echo empty($results) ? 'display:none;' : ''; ?>" onclick="deleteAllUnmatched(true)">
                <span class="dashicons dashicons-trash" style="margin-top:4px;"></span> Tüm Listeyi Temizle
            </button>
        </div>
    </div>

    <div class="card" style="padding:0; overflow:hidden; border-radius:8px; border:1px solid #e5e7eb; background:#fff;">
        <table class="wp-list-table widefat fixed striped" style="box-shadow:none; border:none;">
            <thead>
                <tr>
                    <th style="background:#f9fafb; font-weight:600; padding:12px 15px;">Dosyadaki Depo</th>
                    <th style="background:#f9fafb; font-weight:600; padding:12px 15px;">Dosyadaki Ürün Adı</th>
                    <th style="background:#f9fafb; font-weight:600; padding:12px 15px;">SKU (Hatalı/Eksik)</th>
                    <th style="background:#f9fafb; font-weight:600; padding:12px 15px;">Miktar</th>
                    <th style="background:#f9fafb; font-weight:600; padding:12px 15px;">Hata Nedeni</th>
                    <th style="width:100px; background:#f9fafb; font-weight:600; padding:12px 15px; text-align:center;">İşlem</th>
                </tr>
            </thead>
            <tbody id="unmatched-items-body-main">
                <?php if (empty($results)): ?>
                    <tr>
                        <td colspan="6" style="text-align:center; padding:50px; color:#64748b; font-style:italic; background:#fff;">
                            <div style="font-size:40px; margin-bottom:10px; opacity:0.3;"><span class="dashicons dashicons-yes-alt" style="font-size:inherit; width:inherit; height:inherit;"></span></div>
                            Şu an herhangi bir uyuşmazlık bulunmuyor. Temiz bir liste!
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($results as $item): ?>
                        <tr id="unmatched-row-<?php echo $item->id; ?>">
                            <td style="padding:12px 15px;"><strong><?php echo esc_html($item->warehouse_name); ?></strong></td>
                            <td style="padding:12px 15px;"><?php echo esc_html($item->product_name); ?></td>
                            <td style="padding:12px 15px;"><code style="background:#f1f5f9; padding:2px 5px; border-radius:4px; font-weight:bold;"><?php echo esc_html($item->sku); ?></code></td>
                            <td style="padding:12px 15px;"><?php echo esc_html($item->stock_qty); ?></td>
                            <td style="padding:12px 15px;"><span style="color:#ef4444; font-size:12px;"><?php echo esc_html($item->error_msg); ?></span></td>
                            <td style="padding:12px 15px; text-align:center;">
                                <button type="button" class="button button-small" onclick="deleteUnmatched(<?php echo $item->id; ?>)">Kaldır</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function deleteUnmatched(id) {
    if(!confirm('Bu kaydı listeden kaldırmak istediğinize emin misiniz?')) return;
    
    jQuery.post(ajaxurl, {
        action: 'hizli_kasa_delete_unmatched',
        id: id
    }, function(res) {
        if(res.success) {
            jQuery('#unmatched-row-' + id).fadeOut(300, function() {
                jQuery(this).remove();
                if(jQuery('#unmatched-items-body-main tr').length === 0) {
                    location.reload(); // Badge'in güncellenmesi için
                }
            });
        }
    });
}

function deleteAllUnmatched(force = false) {
    if(!force) return;
    if(!confirm('TÜM uyuşmazlık listesini temizlemek istediğinize emin misiniz? Bu işlem geri alınamaz.')) return;
    
    jQuery.post(ajaxurl, {
        action: 'hizli_kasa_clear_all_unmatched'
    }, function(res) {
        if(res.success) {
            alert(res.data.message);
            location.reload();
        }
    });
}
</script>
