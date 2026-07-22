<?php
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$table_name = $wpdb->prefix . 'hizli_kasa_depolar';
$depolar    = $wpdb->get_results("SELECT * FROM $table_name ORDER BY priority DESC");

$total_depolar = count($depolar);
$max_priority_depo = !empty($depolar) ? $depolar[0] : null;
$online_depo_id = get_option('hizli_kasa_varsayilan_online_depo');
?>

<div class="hk-settings-container">
    <!-- Header -->
    <div class="hk-settings-header">
        <div class="hk-settings-header-title">
            <h1>
                <span class="dashicons dashicons-building" style="color:var(--hk-primary); font-size:26px; width:26px; height:26px;"></span>
                Depo Yönetimi
            </h1>
            <p>Sistemdeki depoları tanımlayın, çalışma önceliklerini ve adreslerini yapılandırın.</p>
        </div>
        <div>
            <a href="#hk-add-depo-card" class="hk-btn-action hk-btn-action-primary" style="text-decoration:none;">
                <span class="dashicons dashicons-plus-alt2"></span> Yeni Depo Ekle
            </a>
        </div>
    </div>

    <!-- Summary Metrics -->
    <div class="hk-metrics-grid">
        <div class="hk-metric-card">
            <div class="hk-metric-icon" style="background:#eff6ff; color:#2563eb;">
                <span class="dashicons dashicons-building"></span>
            </div>
            <div>
                <div class="hk-metric-val"><?php echo $total_depolar; ?></div>
                <div class="hk-metric-lbl">Aktif Depo Sayısı</div>
            </div>
        </div>

        <div class="hk-metric-card">
            <div class="hk-metric-icon" style="background:#fff7ed; color:#ea580c;">
                <span class="dashicons dashicons-star-filled"></span>
            </div>
            <div>
                <div class="hk-metric-val" style="font-size:16px;">
                    <?php echo $max_priority_depo ? esc_html($max_priority_depo->name) : '—'; ?>
                </div>
                <div class="hk-metric-lbl">En Yüksek Öncelikli Depo</div>
            </div>
        </div>

        <div class="hk-metric-card">
            <div class="hk-metric-icon" style="background:#f0fdf4; color:#16a34a;">
                <span class="dashicons dashicons-cart"></span>
            </div>
            <div>
                <div class="hk-metric-val" style="font-size:15px; color:#16a34a;">
                    <?php 
                    $online_depo_name = 'Tüm Depolar';
                    if ($online_depo_id) {
                        foreach ($depolar as $dp) {
                            if ($dp->id == $online_depo_id) {
                                $online_depo_name = esc_html($dp->name);
                                break;
                            }
                        }
                    }
                    echo $online_depo_name;
                    ?>
                </div>
                <div class="hk-metric-lbl">Online Satış Deposu</div>
            </div>
        </div>
    </div>

    <!-- Grid Layout: Form (Left) & Table (Right) -->
    <div class="hk-settings-layout" style="grid-template-columns: 360px 1fr;">
        
        <!-- Left: Yeni Depo Ekle Form Card -->
        <div class="hk-settings-card" id="hk-add-depo-card">
            <div class="hk-settings-card-header">
                <div class="hk-settings-card-icon">
                    <span class="dashicons dashicons-plus-alt2"></span>
                </div>
                <div class="hk-settings-card-title">
                    <h3>Yeni Depo Ekle</h3>
                    <p>Sisteme yeni bir mağaza veya şube deposu tanımlayın.</p>
                </div>
            </div>

            <form method="post" action="admin.php?page=hizli-kasa&tab=depolar">
                <input type="hidden" name="tab" value="depolar">
                <?php wp_nonce_field('depo_ekle_action', 'depo_ekle_nonce'); ?>

                <div style="display:flex; flex-direction:column; gap:16px;">
                    <div>
                        <label for="add_depo_name" style="font-size:13.5px; font-weight:600; color:var(--hk-text-main); display:block; margin-bottom:4px;">Depo Adı *</label>
                        <input type="text" name="depo_name" id="add_depo_name" class="hk-input" style="width:100%; box-sizing:border-box;" required placeholder="Örn: Merkez Depo">
                    </div>

                    <div>
                        <label for="add_depo_priority" style="font-size:13.5px; font-weight:600; color:var(--hk-text-main); display:block; margin-bottom:4px;">Öncelik Sırası</label>
                        <input type="number" name="depo_priority" id="add_depo_priority" value="0" class="hk-input" style="width:100%; box-sizing:border-box;">
                        <p style="font-size:12px; color:var(--hk-text-muted); margin:4px 0 0 0;">Yüksek öncelikli depolar online satışlarda önce tercih edilir.</p>
                    </div>

                    <div>
                        <label for="add_depo_address" style="font-size:13.5px; font-weight:600; color:var(--hk-text-main); display:block; margin-bottom:4px;">Adres</label>
                        <textarea name="depo_address" id="add_depo_address" class="hk-input" style="width:100%; box-sizing:border-box;" rows="2" placeholder="Fiziki depo adresi..."></textarea>
                    </div>

                    <div>
                        <label for="add_depo_desc" style="font-size:13.5px; font-weight:600; color:var(--hk-text-main); display:block; margin-bottom:4px;">Açıklama</label>
                        <textarea name="depo_desc" id="add_depo_desc" class="hk-input" style="width:100%; box-sizing:border-box;" rows="2" placeholder="Depo hakkında notlar..."></textarea>
                    </div>

                    <button type="submit" name="hizli_kasa_depo_ekle" class="hk-btn-action hk-btn-action-primary" style="justify-content:center; width:100%; margin-top:8px;">
                        <span class="dashicons dashicons-saved"></span> Depoyu Kaydet
                    </button>
                </div>
            </form>
        </div>

        <!-- Right: Mevcut Depolar Table -->
        <div class="hk-table-container">
            <!-- Toolbar -->
            <div class="hk-table-toolbar">
                <div class="hk-table-search">
                    <span class="dashicons dashicons-search" style="color:var(--hk-text-muted);"></span>
                    <input type="text" id="hk-depo-search" class="hk-input" placeholder="Depo adı veya adres ara..." style="width:100%;">
                </div>
                <div style="font-size:13px; color:var(--hk-text-muted); font-weight:500;">
                    Toplam <strong style="color:var(--hk-text-main);"><?php echo $total_depolar; ?></strong> Depo Listeleniyor
                </div>
            </div>

            <!-- Table -->
            <table class="hk-table-modern">
                <thead>
                    <tr>
                        <th style="width:70px;">ID</th>
                        <th>Depo Adı</th>
                        <th>Öncelik</th>
                        <th>Adres & Açıklama</th>
                        <th style="width:140px; text-align:center;">İşlemler</th>
                    </tr>
                </thead>
                <tbody id="hk-depolar-body">
                    <?php if (empty($depolar)): ?>
                        <tr>
                            <td colspan="5">
                                <div class="hk-empty-state">
                                    <div class="hk-empty-icon">
                                        <span class="dashicons dashicons-building"></span>
                                    </div>
                                    <h3 style="margin:0 0 6px 0; color:var(--hk-text-main); font-size:18px; font-weight:700;">Henüz Depo Eklenmemiş</h3>
                                    <p style="margin:0; color:var(--hk-text-muted); font-size:13.5px;">Sol taraftaki formu kullanarak ilk deponuzu oluşturun.</p>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($depolar as $depo): ?>
                            <tr class="hk-depo-row">
                                <td><code style="background:#f1f5f9; padding:2px 6px; border-radius:4px; font-weight:700; color:#475569;">#<?php echo $depo->id; ?></code></td>
                                <td>
                                    <div style="display:flex; align-items:center; gap:8px;">
                                        <span class="dashicons dashicons-building" style="color:var(--hk-primary);"></span>
                                        <strong style="font-size:14px; color:var(--hk-text-main);"><?php echo esc_html($depo->name); ?></strong>
                                    </div>
                                </td>
                                <td>
                                    <span class="hk-priority-badge">
                                        <span class="dashicons dashicons-star-filled" style="font-size:13px; width:13px; height:13px;"></span>
                                        Öncelik: <?php echo intval($depo->priority); ?>
                                    </span>
                                </td>
                                <td>
                                    <div style="font-size:13px; color:var(--hk-text-main);"><?php echo esc_html($depo->address ?: '—'); ?></div>
                                    <?php if (!empty($depo->description)): ?>
                                        <div style="font-size:12px; color:var(--hk-text-muted); margin-top:2px;"><?php echo esc_html($depo->description); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align:center;">
                                    <div style="display:flex; justify-content:center; gap:6px;">
                                        <button type="button" class="hk-btn-action" style="padding:4px 10px; font-size:12px;" onclick='openEditDepoModal(<?php echo json_encode($depo); ?>)'>
                                            <span class="dashicons dashicons-edit" style="font-size:14px; width:14px; height:14px;"></span> Düzenle
                                        </button>
                                        <a href="<?php echo wp_nonce_url('admin.php?page=hizli-kasa&tab=depolar&delete_depo=' . $depo->id, 'delete_depo_' . $depo->id); ?>" 
                                           class="hk-btn-action hk-btn-action-danger" style="padding:4px 10px; font-size:12px; text-decoration:none;" 
                                           onclick="return confirm('Bu depoyu silmek üzeresiniz. Stok verileri de etkilenebilir. Emin misiniz?')">
                                            <span class="dashicons dashicons-trash" style="font-size:14px; width:14px; height:14px;"></span> Sil
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modern Glassmorphic Depo Düzenleme Modalı -->
<div id="hk-edit-depo-modal" class="hk-modal-overlay">
    <div class="hk-modal-box">
        <div class="hk-modal-header">
            <h3>
                <span class="dashicons dashicons-edit" style="color:var(--hk-primary);"></span>
                Depo Düzenle
            </h3>
            <button type="button" class="hk-modal-close" onclick="closeEditDepoModal()">&times;</button>
        </div>

        <form method="post" action="admin.php?page=hizli-kasa&tab=depolar">
            <?php wp_nonce_field('depo_guncelle_action', 'depo_guncelle_nonce'); ?>
            <input type="hidden" name="depo_id" id="edit_depo_id">

            <div class="hk-modal-body" style="display:flex; flex-direction:column; gap:16px;">
                <div>
                    <label for="edit_depo_name" style="font-size:13.5px; font-weight:600; color:var(--hk-text-main); display:block; margin-bottom:4px;">Depo Adı *</label>
                    <input type="text" name="depo_name" id="edit_depo_name" class="hk-input" style="width:100%; box-sizing:border-box;" required>
                </div>

                <div>
                    <label for="edit_depo_priority" style="font-size:13.5px; font-weight:600; color:var(--hk-text-main); display:block; margin-bottom:4px;">Öncelik Sırası</label>
                    <input type="number" name="depo_priority" id="edit_depo_priority" class="hk-input" style="width:100%; box-sizing:border-box;">
                </div>

                <div>
                    <label for="edit_depo_address" style="font-size:13.5px; font-weight:600; color:var(--hk-text-main); display:block; margin-bottom:4px;">Adres</label>
                    <textarea name="depo_address" id="edit_depo_address" class="hk-input" style="width:100%; box-sizing:border-box;" rows="2"></textarea>
                </div>

                <div>
                    <label for="edit_depo_desc" style="font-size:13.5px; font-weight:600; color:var(--hk-text-main); display:block; margin-bottom:4px;">Açıklama</label>
                    <textarea name="depo_desc" id="edit_depo_desc" class="hk-input" style="width:100%; box-sizing:border-box;" rows="2"></textarea>
                </div>
            </div>

            <div class="hk-modal-footer">
                <button type="button" class="hk-btn-action" onclick="closeEditDepoModal()">İptal</button>
                <button type="submit" name="hizli_kasa_depo_guncelle" class="hk-btn-action hk-btn-action-primary">
                    <span class="dashicons dashicons-saved"></span> Değişiklikleri Kaydet
                </button>
            </div>
        </form>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Live Search for Warehouses
    $('#hk-depo-search').on('keyup', function() {
        const query = $(this).val().toLowerCase();
        $('.hk-depo-row').each(function() {
            const text = $(this).text().toLowerCase();
            if (!query || text.indexOf(query) !== -1) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
    });
});

function openEditDepoModal(depo) {
    document.getElementById('edit_depo_id').value = depo.id;
    document.getElementById('edit_depo_name').value = depo.name;
    document.getElementById('edit_depo_address').value = depo.address || '';
    document.getElementById('edit_depo_desc').value = depo.description || '';
    document.getElementById('edit_depo_priority').value = depo.priority || 0;
    
    const modal = document.getElementById('hk-edit-depo-modal');
    modal.style.display = 'flex';
}

function closeEditDepoModal() {
    const modal = document.getElementById('hk-edit-depo-modal');
    modal.style.display = 'none';
}
</script>
