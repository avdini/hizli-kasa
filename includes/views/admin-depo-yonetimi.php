<?php
/**
 * Admin Depo Yönetimi Görünümü
 */
if (!defined('ABSPATH')) exit;

global $wpdb;
$table_name = $wpdb->prefix . 'hizli_kasa_depolar';

$depolar = $wpdb->get_results("SELECT * FROM $table_name ORDER BY priority DESC");
?>

<div class="depo-yonetimi-konteyner">
    <div class="card" style="max-width: 100%; margin-top: 0;">
        <h3>Yeni Depo Ekle</h3>
        <form method="post" action="admin.php?page=hizli-kasa&tab=depolar">
            <input type="hidden" name="tab" value="depolar">
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
                <th style="width: 100px;">Adres</th>
                <th style="width: 150px;">İşlemler</th>
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
                            <button type="button" class="button button-secondary" onclick='openEditDepoModal(<?php echo json_encode($depo); ?>)'>Düzenle</button>
                            <a href="<?php echo wp_nonce_url('admin.php?page=hizli-kasa&tab=depolar&delete_depo=' . $depo->id, 'delete_depo_' . $depo->id); ?>" 
                               class="button button-link-delete" 
                               onclick="return confirm('Bu depoyu silmek üzeresiniz. Stok verileri de etkilenebilir. Emin misiniz?')">Sil</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Depo Düzenleme Modalı -->
<div id="hk-edit-depo-modal" style="display:none; position:fixed; z-index:100000; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.5); align-items:center; justify-content:center;">
    <div style="background:#fff; padding:30px; border-radius:12px; width:500px; box-shadow:0 20px 40px rgba(0,0,0,0.2);">
        <h2 style="margin-top:0;">Depo Düzenle</h2>
        <form method="post" action="admin.php?page=hizli-kasa&tab=depolar">
            <?php wp_nonce_field('depo_guncelle_action', 'depo_guncelle_nonce'); ?>
            <input type="hidden" name="depo_id" id="edit_depo_id">
            
            <table class="form-table">
                <tr>
                    <th>Depo Adı</th>
                    <td><input type="text" name="depo_name" id="edit_depo_name" class="regular-text" required></td>
                </tr>
                <tr>
                    <th>Adres</th>
                    <td><textarea name="depo_address" id="edit_depo_address" class="regular-text" rows="2"></textarea></td>
                </tr>
                <tr>
                    <th>Açıklama</th>
                    <td><textarea name="depo_desc" id="edit_depo_desc" class="regular-text" rows="2"></textarea></td>
                </tr>
                <tr>
                    <th>Öncelik Sırası</th>
                    <td><input type="number" name="depo_priority" id="edit_depo_priority" class="small-text"></td>
                </tr>
            </table>

            <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:20px;">
                <button type="button" class="button button-secondary" onclick="closeEditDepoModal()">İptal</button>
                <input type="submit" name="hizli_kasa_depo_guncelle" class="button button-primary" value="Değişiklikleri Kaydet">
            </div>
        </form>
    </div>
</div>

<script>
function openEditDepoModal(depo) {
    document.getElementById('edit_depo_id').value = depo.id;
    document.getElementById('edit_depo_name').value = depo.name;
    document.getElementById('edit_depo_address').value = depo.address;
    document.getElementById('edit_depo_desc').value = depo.description;
    document.getElementById('edit_depo_priority').value = depo.priority;
    document.getElementById('hk-edit-depo-modal').style.display = 'flex';
}

function closeEditDepoModal() {
    document.getElementById('hk-edit-depo-modal').style.display = 'none';
}
</script>
