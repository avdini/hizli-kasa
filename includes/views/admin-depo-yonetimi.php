<?php
/**
 * Admin Depo Yönetimi Görünümü
 */
if (!defined('ABSPATH')) exit;

global $wpdb;
$table_name = $wpdb->prefix . 'hizli_kasa_depolar';

// İşlemleri Yakala (PHP tarafında basit CRUD)
if (isset($_POST['hizli_kasa_depo_ekle'])) {
    check_admin_referer('depo_ekle_action', 'depo_ekle_nonce');
    $wpdb->insert($table_name, [
        'name'        => sanitize_text_field($_POST['depo_name']),
        'address'     => sanitize_textarea_field($_POST['depo_address']),
        'description' => sanitize_textarea_field($_POST['depo_desc']),
        'priority'    => intval($_POST['depo_priority']),
    ]);
}

if (isset($_GET['delete_depo'])) {
    check_admin_referer('delete_depo_' . $_GET['delete_depo']);
    $wpdb->delete($table_name, ['id' => intval($_GET['delete_depo'])]);
}

$depolar = $wpdb->get_results("SELECT * FROM $table_name ORDER BY priority DESC");
?>

<div class="depo-yonetimi-konteyner">
    <div class="card" style="max-width: 100%; margin-top: 0;">
        <h3>Yeni Depo Ekle</h3>
        <form method="post" action="">
            <?php wp_nonce_field('depo_ekle_action', 'depo_ekle_nonce'); ?>
            <table class="form-table">
                <tr>
                    <th>Depo Adı</th>
                    <td><input type="text" name="depo_name" class="regular-text" required placeholder="Örn: Merkez Depo"></td>
                </tr>
                <tr>
                    <th>Adres</th>
                    <td><textarea name="depo_address" class="regular-text" rows="2"></textarea></td>
                </tr>
                <tr>
                    <th>Açıklama</th>
                    <td><textarea name="depo_desc" class="regular-text" rows="2"></textarea></td>
                </tr>
                <tr>
                    <th>Öncelik Sırası</th>
                    <td>
                        <input type="number" name="depo_priority" value="0" class="small-text">
                        <p class="description">Yüksek olanlar online satışlarda önce tercih edilir.</p>
                    </td>
                </tr>
            </table>
            <p class="submit">
                <input type="submit" name="hizli_kasa_depo_ekle" class="button button-primary" value="Depoyu Kaydet">
            </p>
        </form>
    </div>

    <h3 style="margin-top:40px;">Mevcut Depolar</h3>
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th style="width: 50px;">ID</th>
                <th>Depo Adı</th>
                <th>Öncelik</th>
                <th>Adres</th>
                <th style="width: 100px;">İşlemler</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($depolar)): ?>
                <tr><td colspan="5">Henüz depo eklenmemiş.</td></tr>
            <?php else: ?>
                <?php foreach ($depolar as $depo): ?>
                    <tr>
                        <td>#<?php echo $depo->id; ?></td>
                        <td><strong><?php echo esc_html($depo->name); ?></strong></td>
                        <td><?php echo intval($depo->priority); ?></td>
                        <td><?php echo esc_html($depo->address); ?></td>
                        <td>
                            <a href="<?php echo wp_nonce_url('?page=hizli-kasa-ayarlar&tab=depolar&delete_depo=' . $depo->id, 'delete_depo_' . $depo->id); ?>" 
                               class="button button-link-delete" 
                               onclick="return confirm('Bu depoyu silmek üzeresiniz. Stok verileri de etkilenebilir. Emin misiniz?')">Sil</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
