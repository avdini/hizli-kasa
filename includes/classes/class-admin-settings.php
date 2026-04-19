<?php
/**
 * Hızlı Kasa - Admin Ayarları
 *
 * Admin menüsü, ayar kayıtları ve ayarlar sayfası HTML çıktısı.
 *
 * @package HizliKasa
 */

if (!defined('ABSPATH'))
    exit;

// Admin Menüsü Ekleme
add_action('admin_menu', 'hizli_kasa_admin_menu');
function hizli_kasa_admin_menu()
{
    // Ana Menü
    add_menu_page(
        'Hızlı Kasa',
        'Hızlı Kasa',
        'manage_options',
        'hizli-kasa',
        'hizli_kasa_ayarlar_sayfasi',
        'dashicons-store',
        30
    );

    // Alt Menüler
    add_submenu_page(
        'hizli-kasa',
        'Genel Ayarlar',
        'Genel Ayarlar',
        'manage_options',
        'hizli-kasa', // Ana menü ile aynı slug (Landing)
        'hizli_kasa_ayarlar_sayfasi'
    );

    add_submenu_page(
        'hizli-kasa',
        'Stok Yönetimi',
        'Stok Yönetimi',
        'manage_options',
        'hizli-kasa&tab=stok',
        'hizli_kasa_ayarlar_sayfasi'
    );

    add_submenu_page(
        'hizli-kasa',
        'Depo Yönetimi',
        'Depo Yönetimi',
        'manage_options',
        'hizli-kasa&tab=depolar',
        'hizli_kasa_ayarlar_sayfasi'
    );

    add_submenu_page(
        'hizli-kasa',
        'Terminali Başlat',
        '<span style="color:#f58220; font-weight:bold;">POS Terminali ↗</span>',
        'manage_options',
        'hizli-kasa-terminal-link',
        function() {
            $url = home_url('/hizli-kasa/terminal/');
            echo "<script>window.open('$url', '_blank'); location.href='admin.php?page=hizli-kasa';</script>";
        }
    );
}

// Ayarları Kaydetme
add_action('admin_init', 'hizli_kasa_ayarlari_kaydet');
function hizli_kasa_ayarlari_kaydet()
{
    register_setting('hizli_kasa_ayar_grubu', 'hizli_kasa_siparis_durumu');
    register_setting('hizli_kasa_ayar_grubu', 'hizli_kasa_yetkili_roller');
    register_setting('hizli_kasa_ayar_grubu', 'hizli_kasa_yuvarlama_aktif', array(
        'sanitize_callback' => function($val) { return $val ? '1' : '0'; }
    ));
    register_setting('hizli_kasa_ayar_grubu', 'hizli_kasa_yuvarlama_modu');
    register_setting('hizli_kasa_ayar_grubu', 'hizli_kasa_varsayilan_online_depo');
    register_setting('hizli_kasa_ayar_grubu', 'hizli_kasa_kritik_stok_esigi', array(
        'type' => 'integer',
        'default' => 5,
        'sanitize_callback' => 'intval'
    ));
}

/**
 * Depo İşlemlerini Yönetir (Ekleme/Silme/Mesajlar)
 */
add_action('admin_init', 'hizli_kasa_handle_depo_actions');
function hizli_kasa_handle_depo_actions() {
    if (!isset($_GET['page']) || $_GET['page'] !== 'hizli-kasa') return;
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'hizli_kasa_depolar';

    // Mesajları Yönet (Yönlendirme sonrası gösterim için)
    if (isset($_GET['hizli_kasa_msg'])) {
        switch ($_GET['hizli_kasa_msg']) {
            case 'depo_eklendi':
                add_settings_error('hizli_kasa_messages', 'depo_eklendi', 'Yeni depo başarıyla eklendi.', 'updated');
                break;
            case 'depo_hata':
                $err = isset($_GET['hizli_kasa_err']) ? sanitize_text_field($_GET['hizli_kasa_err']) : 'Depo eklenirken bir hata oluştu.';
                add_settings_error('hizli_kasa_messages', 'depo_hata', $err, 'error');
                break;
            case 'depo_silindi':
                add_settings_error('hizli_kasa_messages', 'depo_silindi', 'Depo başarıyla silindi.', 'updated');
                break;
            case 'depo_silme_hata':
                add_settings_error('hizli_kasa_messages', 'depo_silme_hata', 'Depo silinirken bir hata oluştu.', 'error');
                break;
            case 'db_onarildi':
                add_settings_error('hizli_kasa_messages', 'db_onarildi', 'Veritabanı tabloları kontrol edildi ve onarıldı.', 'updated');
                break;
            case 'depo_guncellendi':
                add_settings_error('hizli_kasa_messages', 'depo_guncellendi', 'Depo bilgileri başarıyla güncellendi.', 'updated');
                break;
        }
    }

    // Yeni Depo Ekleme
    if (isset($_POST['hizli_kasa_depo_ekle'])) {
        check_admin_referer('depo_ekle_action', 'depo_ekle_nonce');
        
        $name = sanitize_text_field($_POST['depo_name']);
        
        // Mükerrer Kayıt Kontrolü
        $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table_name WHERE name = %s", $name));
        if ($exists) {
            wp_safe_redirect(admin_url('admin.php?page=hizli-kasa&tab=depolar&hizli_kasa_msg=depo_hata&hizli_kasa_err=' . urlencode('Bu isimde bir depo zaten mevcut.')));
            exit;
        }

        $inserted = $wpdb->insert($table_name, [
            'name'        => $name,
            'address'     => sanitize_textarea_field($_POST['depo_address']),
            'description' => sanitize_textarea_field($_POST['depo_desc']),
            'priority'    => intval($_POST['depo_priority']),
            'created_at'  => current_time('mysql')
        ]);

        if ($inserted === false) {
            $error_msg = $wpdb->last_error ?: "Veritabanı hatası.";
            wp_safe_redirect(admin_url('admin.php?page=hizli-kasa&tab=depolar&hizli_kasa_msg=depo_hata&hizli_kasa_err=' . urlencode($error_msg)));
            exit;
        }

        wp_redirect(admin_url('admin.php?page=hizli-kasa&tab=depolar&hizli_kasa_msg=depo_eklendi'));
        exit;
    }

    // Depo Güncelleme
    if (isset($_POST['hizli_kasa_depo_guncelle'])) {
        check_admin_referer('depo_guncelle_action', 'depo_guncelle_nonce');
        
        $id = intval($_POST['depo_id']);
        $name = sanitize_text_field($_POST['depo_name']);
        
        // İsim çakışma kontrolü (Kendi ID'si hariç)
        $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table_name WHERE name = %s AND id != %d", $name, $id));
        if ($exists) {
            wp_safe_redirect(admin_url('admin.php?page=hizli-kasa&tab=depolar&hizli_kasa_msg=depo_hata&hizli_kasa_err=' . urlencode('Bu isimde başka bir depo zaten mevcut.')));
            exit;
        }

        $updated = $wpdb->update($table_name, [
            'name'        => $name,
            'address'     => sanitize_textarea_field($_POST['depo_address']),
            'description' => sanitize_textarea_field($_POST['depo_desc']),
            'priority'    => intval($_POST['depo_priority'])
        ], ['id' => $id]);

        wp_redirect(admin_url('admin.php?page=hizli-kasa&tab=depolar&hizli_kasa_msg=depo_guncellendi'));
        exit;
    }

    // Depo Silme
    if (isset($_GET['delete_depo'])) {
        $depo_id = intval($_GET['delete_depo']);
        check_admin_referer('delete_depo_' . $depo_id);

        $depo_name = $wpdb->get_var($wpdb->prepare("SELECT name FROM $table_name WHERE id = %d", $depo_id));
        $deleted = $wpdb->delete($table_name, ['id' => $depo_id]);

        if ($deleted) {
            // Sıkı İzolasyon: Deponun tüm stoklarını, loglarını ve uyuşmazlıklarını sil
            $wpdb->delete($wpdb->prefix . 'hizli_kasa_stok_konumlari',  ['location_id' => $depo_id]);
            $wpdb->delete($wpdb->prefix . 'hizli_kasa_stok_hareketleri', ['location_id' => $depo_id]);
            if ($depo_name) {
                $wpdb->delete($wpdb->prefix . 'hizli_kasa_unmatched_items', ['warehouse_name' => $depo_name]);
            }
        }

        $msg = $deleted ? 'depo_silindi' : 'depo_silme_hata';
        wp_redirect(admin_url('admin.php?page=hizli-kasa&tab=depolar&hizli_kasa_msg=' . $msg));
        exit;
    }

}

/**
 * ==========================================================================
 * YARDIMCI FONKSİYONLAR: Kullanıcı Depo Yetki Sistemi
 * ==========================================================================
 */

/**
 * Kullanıcının görebileceği depo ID listesini döner.
 */
function hizli_kasa_get_user_view_depos($user_id) {
    $raw = get_user_meta($user_id, '_hizli_kasa_depo_ids_view', true);
    if (empty($raw)) return [];
    $ids = is_array($raw) ? $raw : json_decode($raw, true);
    return array_map('intval', (array) $ids);
}

/**
 * Kullanıcının yönetebileceği (stok değiştirebileceği) depo ID listesini döner.
 */
function hizli_kasa_get_user_manage_depos($user_id) {
    $raw = get_user_meta($user_id, '_hizli_kasa_depo_ids_manage', true);
    if (empty($raw)) return [];
    $ids = is_array($raw) ? $raw : json_decode($raw, true);
    return array_map('intval', (array) $ids);
}

/**
 * Kullanıcının belirtilen depoyu görüntüleme yetkisi var mı?
 */
function hizli_kasa_can_user_view_depo($user_id, $depo_id) {
    // Admin her depoyu görebilir
    if (user_can($user_id, 'manage_options')) return true;
    $ids = hizli_kasa_get_user_view_depos($user_id);
    return in_array(intval($depo_id), $ids);
}

/**
 * Kullanıcının belirtilen depoda yönetim (stok işlemi) yetkisi var mı?
 */
function hizli_kasa_can_user_manage_depo($user_id, $depo_id) {
    // Admin her depoda işlem yapabilir
    if (user_can($user_id, 'manage_options')) return true;
    $ids = hizli_kasa_get_user_manage_depos($user_id);
    return in_array(intval($depo_id), $ids);
}

/**
 * Kullanıcının şu an aktif seçili deposunu döner (sunucu tarafı).
 * Öncelik: user_meta → ilk görüntüleme deposu
 */
function hizli_kasa_get_user_active_depo($user_id) {
    // Admin ise global admin deposunu veya ilk depoya bak
    $active = intval(get_user_meta($user_id, '_hizli_kasa_active_depo', true));
    
    if (!$active) return null;
    
    // Hala bu depoya yetkisi var mı kontrol et
    if (!hizli_kasa_can_user_view_depo($user_id, $active)) {
        // Yetkisi kaldırılmış, ilk yetkili depoya dön
        $view_ids = hizli_kasa_get_user_view_depos($user_id);
        return !empty($view_ids) ? $view_ids[0] : null;
    }
    
    return $active;
}

/**
 * Eski tek depo meta'sını yeni çoklu sisteme geçirir.
 * Bir kullanıcıya ilk kez baktığınızda otomatik çalışır.
 */
function hizli_kasa_migrate_legacy_depo($user_id) {
    $legacy = get_user_meta($user_id, '_hizli_kasa_depo_id', true);
    if (!$legacy) return;
    
    // Zaten yeni sisteme geçmişse tekrar yapma
    $already_view = get_user_meta($user_id, '_hizli_kasa_depo_ids_view', true);
    if (!empty($already_view)) {
        // Eski alanı temizle
        delete_user_meta($user_id, '_hizli_kasa_depo_id');
        return;
    }
    
    $depo_id = intval($legacy);
    if ($depo_id > 0) {
        update_user_meta($user_id, '_hizli_kasa_depo_ids_view',   json_encode([$depo_id]));
        update_user_meta($user_id, '_hizli_kasa_depo_ids_manage', json_encode([$depo_id]));
        update_user_meta($user_id, '_hizli_kasa_active_depo', $depo_id);
    }
    delete_user_meta($user_id, '_hizli_kasa_depo_id');
}

/**
 * ==========================================================================
 * KULLANICI PROFİL ENTEGRASYONU
 * ==========================================================================
 */
add_action('show_user_profile', 'hizli_kasa_user_warehouse_field');
add_action('edit_user_profile', 'hizli_kasa_user_warehouse_field');
add_action('personal_options_update', 'hizli_kasa_save_user_warehouse_field');
add_action('edit_user_profile_update', 'hizli_kasa_save_user_warehouse_field');

function hizli_kasa_user_warehouse_field($user) {
    if (!current_user_can('manage_options')) return;
    
    // Eski sisteme geçiş (Migration)
    hizli_kasa_migrate_legacy_depo($user->ID);

    global $wpdb;
    $depo_table = $wpdb->prefix . 'hizli_kasa_depolar';
    $depolar = $wpdb->get_results("SELECT id, name FROM $depo_table ORDER BY priority DESC, name ASC");
    
    $view_ids   = hizli_kasa_get_user_view_depos($user->ID);
    $manage_ids = hizli_kasa_get_user_manage_depos($user->ID);
    ?>
    <h3>Hızlı Kasa Yetkilendirme</h3>
    <style>
    .hk-depo-yetki-grid { display: flex; gap: 30px; flex-wrap: wrap; }
    .hk-depo-yetki-grup { flex: 1; min-width: 220px; }
    .hk-depo-yetki-grup h4 { margin: 0 0 8px; font-size: 13px; color: #1d2327; }
    .hk-depo-checkbox-list { display: flex; flex-direction: column; gap: 6px; }
    .hk-depo-checkbox-list label { display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 13px; }
    .hk-depo-checkbox-list label:hover { color: #2271b1; }
    .hk-yonetim-not { font-size: 11px; color: #646970; margin-top: 8px; font-style: italic; }
    .hk-depo-empty { color: #999; font-style: italic; font-size: 13px; }
    </style>
    <table class="form-table">
        <tr>
            <th>Depo Yetkileri</th>
            <td>
                <?php if (empty($depolar)): ?>
                    <p class="hk-depo-empty">Henüz depo eklenmemiş. Önce <a href="<?php echo admin_url('options-general.php?page=hizli-kasa-ayarlar&tab=depolar'); ?>">Depo Yönetimi</a> sayfasından depo ekleyin.</p>
                <?php else: ?>
                <div class="hk-depo-yetki-grid">
                    <div class="hk-depo-yetki-grup">
                        <h4>👁 Görüntüleyebileceği Depolar</h4>
                        <p style="font-size:12px; color:#646970; margin:0 0 8px;">Bu personel aşağıdaki depoların stoklarını görebilir.</p>
                        <div class="hk-depo-checkbox-list" id="hk-view-depolar">
                            <?php foreach ($depolar as $d): ?>
                                <label>
                                    <input type="checkbox" 
                                           name="hizli_kasa_depo_ids_view[]" 
                                           value="<?php echo intval($d->id); ?>"
                                           <?php checked(in_array(intval($d->id), $view_ids)); ?>>
                                    <?php echo esc_html($d->name); ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="hk-depo-yetki-grup">
                        <h4>⚙️ Yönetebileceği Depolar</h4>
                        <p style="font-size:12px; color:#646970; margin:0 0 8px;">Bu personel aşağıdaki depoların stoklarını değiştirebilir.</p>
                        <div class="hk-depo-checkbox-list" id="hk-manage-depolar">
                            <?php foreach ($depolar as $d): ?>
                                <label>
                                    <input type="checkbox" 
                                           name="hizli_kasa_depo_ids_manage[]" 
                                           value="<?php echo intval($d->id); ?>"
                                           class="hk-manage-cb"
                                           data-depo-id="<?php echo intval($d->id); ?>"
                                           <?php checked(in_array(intval($d->id), $manage_ids)); ?>>
                                    <?php echo esc_html($d->name); ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <p class="hk-yonetim-not">⚠️ Yönetim yetkisi için görüntüleme yetkisi de gereklidir. Kayıt sırasında otomatik eklenir.</p>
                    </div>
                </div>
                <?php endif; ?>

                <script>
                // Yönetim seçilince görüntülemeyi otomatik işaretle
                document.querySelectorAll('.hk-manage-cb').forEach(function(cb) {
                    cb.addEventListener('change', function() {
                        if (this.checked) {
                            var depoId = this.dataset.depoId;
                            var viewCb = document.querySelector('#hk-view-depolar input[value="' + depoId + '"]');
                            if (viewCb && !viewCb.checked) viewCb.checked = true;
                        }
                    });
                });
                </script>
            </td>
        </tr>
    </table>
    <?php
}

function hizli_kasa_save_user_warehouse_field($user_id) {
    if (!current_user_can('manage_options')) return false;
    if (!isset($_POST['hizli_kasa_depo_ids_view']) && !isset($_POST['hizli_kasa_depo_ids_manage'])) {
        // Eğer checkboxlar gönderilmediyse (hepsi işaretsiz) — boşalt
        update_user_meta($user_id, '_hizli_kasa_depo_ids_view',   json_encode([]));
        update_user_meta($user_id, '_hizli_kasa_depo_ids_manage', json_encode([]));
        return;
    }
    
    $view_ids   = isset($_POST['hizli_kasa_depo_ids_view'])
                    ? array_map('intval', $_POST['hizli_kasa_depo_ids_view'])
                    : [];
    $manage_ids = isset($_POST['hizli_kasa_depo_ids_manage'])
                    ? array_map('intval', $_POST['hizli_kasa_depo_ids_manage'])
                    : [];
    
    // Yönetim yetkisi olanlar görüntüleme listesinde de olmalı
    $view_ids = array_unique(array_merge($view_ids, $manage_ids));
    
    update_user_meta($user_id, '_hizli_kasa_depo_ids_view',   json_encode(array_values($view_ids)));
    update_user_meta($user_id, '_hizli_kasa_depo_ids_manage', json_encode(array_values($manage_ids)));
    
    // Aktif depo artık görüntüleme listesinde değilse temizle
    $active = intval(get_user_meta($user_id, '_hizli_kasa_active_depo', true));
    if ($active && !in_array($active, $view_ids)) {
        $new_active = !empty($view_ids) ? $view_ids[0] : 0;
        update_user_meta($user_id, '_hizli_kasa_active_depo', $new_active);
    }
    
    // Eski meta varsa temizle
    delete_user_meta($user_id, '_hizli_kasa_depo_id');
}

/**
 * AJAX Handlers for Admin Tools
 */
add_action('wp_ajax_hizli_kasa_setup', 'hizli_kasa_ajax_setup');
add_action('wp_ajax_hizli_kasa_reset', 'hizli_kasa_ajax_reset');
add_action('wp_ajax_hizli_kasa_repair_db', 'hizli_kasa_ajax_repair_db');
add_action('wp_ajax_hizli_kasa_debug_db', 'hizli_kasa_ajax_debug_db');
add_action('wp_ajax_hizli_kasa_get_admin_stock_list', 'hizli_kasa_ajax_get_admin_stock_list');
add_action('wp_ajax_hizli_kasa_admin_update_stock', 'hizli_kasa_ajax_admin_update_stock');

// İçe / Dışa Aktar AJAX
add_action('wp_ajax_hizli_kasa_export_stocks', 'hizli_kasa_ajax_export_stocks');
add_action('wp_ajax_hizli_kasa_import_stocks', 'hizli_kasa_ajax_import_stocks');
add_action('wp_ajax_hizli_kasa_get_unmatched', 'hizli_kasa_ajax_get_unmatched');
add_action('wp_ajax_hizli_kasa_delete_unmatched', 'hizli_kasa_ajax_delete_unmatched');
add_action('wp_ajax_hizli_kasa_clear_all_unmatched', 'hizli_kasa_ajax_clear_all_unmatched');

/**
 * Ürün ve Depo Stok Listesini Getir (Optimize)
 */
function hizli_kasa_ajax_get_admin_stock_list() {
    try {
        hizli_kasa_admin_log("ADMIN_STOCK_LIST START");
        if (!current_user_can('manage_options')) {
            hizli_kasa_admin_log("Access denied for current user");
            wp_send_json_error(['message' => 'Yetkisiz erişim']);
        }

        global $wpdb;
        $stok_table = Hizli_Kasa_Database::get_tables()['stok_konumlari'];
        $depo_table = Hizli_Kasa_Database::get_tables()['depolar'];
        $s = sanitize_text_field($_POST['s'] ?? '');
        $filter_mismatch = (isset($_POST['filter_mismatch']) && $_POST['filter_mismatch'] === 'true');
        $paged = max(1, intval($_POST['paged'] ?? 1));
        $per_page = 24;
        $offset = ($paged - 1) * $per_page;

        $params = [];
        $where_sql = "p.post_type IN ('product', 'product_variation') AND p.post_status = 'publish'";

        if ($s) {
            $like = '%' . $wpdb->esc_like($s) . '%';
            $where_sql .= " AND (p.post_title LIKE %s OR pm_sku.meta_value LIKE %s)";
            $params[] = $like; $params[] = $like;
        }

        if ($filter_mismatch) {
        // Miktar uyuşmazlığı olanları bulmak için JOIN kullanan performanslı query
        $base_sql = "
            SELECT 
                (CASE WHEN p.post_type = 'product_variation' THEN p.post_parent ELSE p.ID END) as main_id,
                CAST(pm_stock.meta_value AS DECIMAL(15,4)) as wc_stock,
                SUM(sk.quantity) as total_wh_stock
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm_sku ON (p.ID = pm_sku.post_id AND pm_sku.meta_key = '_sku')
            LEFT JOIN {$wpdb->postmeta} pm_stock ON (p.ID = pm_stock.post_id AND pm_stock.meta_key = '_stock')
            LEFT JOIN $stok_table sk ON (p.ID = sk.variation_id OR (p.post_type = 'product' AND p.ID = sk.product_id AND sk.variation_id = 0))
            WHERE $where_sql
            GROUP BY p.ID
            HAVING total_wh_stock > wc_stock";
    } else {
        $base_sql = "
            SELECT DISTINCT (CASE WHEN p.post_type = 'product_variation' THEN p.post_parent ELSE p.ID END) as main_id
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm_sku ON (p.ID = pm_sku.post_id AND pm_sku.meta_key = '_sku')
            WHERE $where_sql";
    }

        // Toplam sayıyı bul
        $total_items = $wpdb->get_var($wpdb->prepare("SELECT COUNT(DISTINCT main_id) FROM ($base_sql) as t", $params));
        
        // Sayfalanmış ana ID'leri çek
        $main_ids = $wpdb->get_col($wpdb->prepare("SELECT DISTINCT main_id FROM ($base_sql) as ids ORDER BY main_id DESC LIMIT %d OFFSET %d", array_merge($params, [$per_page, $offset])));
        
        hizli_kasa_admin_log("Main IDs Found: " . count($main_ids));
        if (!empty($main_ids)) {
            hizli_kasa_admin_log("Main IDs: " . implode(',', $main_ids));
        }

        if ($wpdb->last_error) {
            hizli_kasa_admin_log("SQL Error: " . $wpdb->last_error);
            wp_send_json_error(['message' => 'Veritabanı hatası: ' . $wpdb->last_error]);
        }

    if (empty($main_ids)) {
        wp_send_json_success(['products' => [], 'total_pages' => 0]);
    }

    // ADIM 2: Detayları Topla (Ana ürünler + Onların tüm varyasyonları)
    $main_placeholders = implode(',', array_fill(0, count($main_ids), '%d'));
    
    // Varyasyonları bul
    $variation_ids = $wpdb->get_col($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE post_type = 'product_variation' AND post_parent IN ($main_placeholders)", $main_ids));
    
    $all_target_ids = array_unique(array_merge($main_ids, $variation_ids));
    $all_placeholders = implode(',', array_fill(0, count($all_target_ids), '%d'));

    // Metataları çek
    $meta_results = $wpdb->get_results($wpdb->prepare("SELECT post_id, meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id IN ($all_placeholders) AND meta_key IN ('_sku', '_stock', '_thumbnail_id', '_product_attributes')", $all_target_ids));
    $metas_by_id = [];
    foreach ($meta_results as $m) { $metas_by_id[$m->post_id][$m->meta_key] = $m->meta_value; }

    // Post detaylarını çek
    $p_details = $wpdb->get_results($wpdb->prepare("SELECT ID, post_title, post_type, post_parent FROM {$wpdb->posts} WHERE ID IN ($all_placeholders)", $all_target_ids));
    $details_by_id = [];
    foreach ($p_details as $pd) { $details_by_id[$pd->ID] = $pd; }

    // ADIM 3: Depo Stoklarını Topla
    $depolar = $wpdb->get_results("SELECT id, name FROM $depo_table ORDER BY priority DESC");
    $stock_results = $wpdb->get_results($wpdb->prepare("SELECT location_id, product_id, variation_id, quantity FROM $stok_table WHERE (product_id IN ($all_placeholders) OR variation_id IN ($all_placeholders))", array_merge($all_target_ids, $all_target_ids)));

    $stocks_by_loc = [];
    foreach ($stock_results as $sr) {
        $key = ($sr->variation_id > 0) ? "v_{$sr->variation_id}" : "p_{$sr->product_id}";
        $stocks_by_loc[$sr->location_id][$key] = $sr->quantity;
    }
    hizli_kasa_admin_log("Step 3 Complete (Stocks Fetched)");

    $output = [];
    foreach ($main_ids as $m_id) {
        $parent = $details_by_id[$m_id] ?? null;
        if (!$parent) continue;

        $m = $metas_by_id[$m_id] ?? [];
        $thumb_id = $m['_thumbnail_id'] ?? 0;
        $thumbnail = $thumb_id ? wp_get_attachment_image_url($thumb_id, 'thumbnail') : wc_placeholder_img_src();

        // Varyasyonları yapılandır
        $children = [];
        foreach ($variation_ids as $v_id) {
            $v_post = $details_by_id[$v_id] ?? null;
            if (!$v_post || $v_post->post_parent != $m_id) continue;

            $vm = $metas_by_id[$v_id] ?? [];
            $v_thumb_id = $vm['_thumbnail_id'] ?? 0;
            $v_thumbnail = $v_thumb_id ? wp_get_attachment_image_url($v_thumb_id, 'thumbnail') : wc_placeholder_img_src();

            $v_item = [
                'id' => $m_id,
                'variation_id' => $v_id,
                'name' => $v_post->post_title,
                'sku' => $vm['_sku'] ?? '',
                'wc_stock' => (float)($vm['_stock'] ?? 0),
                'thumbnail' => $v_thumbnail,
                'warehouse_stocks' => []
            ];
            foreach ($depolar as $d) {
                $qty = $stocks_by_loc[$d->id]["v_{$v_id}"] ?? 0;
                $v_item['warehouse_stocks'][] = ['depo_id' => $d->id, 'qty' => (float)$qty];
            }
            
            // Mismatch kontrolü
            $v_total_wh = array_sum(array_column($v_item['warehouse_stocks'], 'qty'));
            $v_item['total_warehouse_stock'] = $v_total_wh;
            $v_item['has_mismatch'] = ($v_total_wh > $v_item['wc_stock']);

            $children[] = $v_item;
        }

        $item = [
            'id' => $m_id,
            'variation_id' => 0,
            'name' => $parent->post_title,
            'sku' => $m['_sku'] ?? '',
            'wc_stock' => (float)($m['_stock'] ?? 0),
            'thumbnail' => $thumbnail,
            'type' => empty($children) ? 'simple' : 'variable',
            'variations' => $children,
            'warehouse_stocks' => []
        ];

        foreach ($depolar as $d) {
            $qty = $stocks_by_loc[$d->id]["p_{$m_id}"] ?? 0;
            $item['warehouse_stocks'][] = ['depo_id' => $d->id, 'qty' => (float)$qty];
        }

        // Mismatch kontrolü (Basit ürün için veya değişken ürünün genel durumu için)
        $total_wh = array_sum(array_column($item['warehouse_stocks'], 'qty'));
        $item['total_warehouse_stock'] = $total_wh;
        
        if ($item['type'] === 'simple') {
            $item['has_mismatch'] = ($total_wh > $item['wc_stock']);
        } else {
            // Değişken üründe herhangi bir varyasyonda uyuşmazlık varsa true dön
            $item['has_mismatch'] = false;
            foreach($children as $child) {
                if ($child['has_mismatch']) {
                    $item['has_mismatch'] = true;
                    break;
                }
            }
        }

        $output[] = $item;
    }

    hizli_kasa_admin_log("Final Output Prepared. Count: " . count($output));

    wp_send_json_success([
        'products'    => $output,
        'total_pages' => ceil($total_items / $per_page)
    ]);
    hizli_kasa_admin_log("Response Sent Successfully");

    } catch (Exception $e) {
        hizli_kasa_admin_log("AJAX Hatası: " . $e->getMessage());
        wp_send_json_error(['message' => 'İstisnai bir hata oluştu: ' . $e->getMessage()]);
    }
}

/**
 * Manuel Stok Güncelleme
 */
function hizli_kasa_ajax_admin_update_stock() {
    if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'Yetkisiz erişim!']);

    $pid    = intval($_POST['product_id']);
    $vid    = intval($_POST['variation_id']);
    $did    = intval($_POST['depo_id']);
    $change = intval($_POST['change']);

    if (!$did || !$pid) wp_send_json_error(['message' => 'Eksik veri!']);

    require_once HIZLI_KASA_PATH . 'includes/classes/class-stock-manager.php';
    
    // Stok Güncelle (Stock Manager metodunu kullan ki log tutulsun)
    // variation_id 0 ise basit ürün, değilse varyasyondur.
    // product_id her zaman parent ID (veya basit ürün ID) olmalıdır.
    
    $change = isset($_POST['change']) ? floatval($_POST['change']) : 0;
    $set_qty = isset($_POST['set_qty']) ? sanitize_text_field($_POST['set_qty']) : null;

    global $wpdb;
    $tables = Hizli_Kasa_Database::get_tables();
    $table = $tables['stok_konumlari'];

    $current = $wpdb->get_var($wpdb->prepare(
        "SELECT quantity FROM $table WHERE location_id = %d AND product_id = %d AND variation_id = %d",
        $did, ($vid > 0 ? get_post_field('post_parent', $vid) : $pid), $vid
    )) ?: 0;
    $current = floatval($current);

    // Akıllı miktar belirleme (Smart Syntax)
    if ($set_qty !== null && $set_qty !== '') {
        $new_val = floatval($set_qty);
        $change = $new_val - $current;
    }

    $new_qty = $current + $change;
    if ($new_qty < 0) $new_qty = 0;

    $user = wp_get_current_user();
    $reason = "Admin Manuel Müdahale (Kullanıcı: " . $user->display_name . ")";

    Hizli_Kasa_Stock_Manager::update_warehouse_stock(
        ($vid > 0 ? get_post_field('post_parent', $vid) : $pid), 
        $vid, 
        $did, 
        $change, 
        $reason
    );

    wp_send_json_success(['new_qty' => $new_qty]);
}

function hizli_kasa_ajax_repair_db() {
    if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'Yetkisiz işlem!']);
    
    require_once HIZLI_KASA_PATH . 'includes/classes/class-database.php';
    Hizli_Kasa_Database::init(); // Tabloları eksikse oluşturur, varsa günceller

    wp_send_json_success(['message' => 'Veritabanı tabloları başarıyla onarıldı.']);
}

function hizli_kasa_ajax_debug_db() {
    if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'Yetkisiz erişim']);
    
    global $wpdb;
    $tables = Hizli_Kasa_Database::get_tables();
    $report = [];

    foreach ($tables as $key => $table) {
        $exists = $wpdb->get_var("SHOW TABLES LIKE '$table'");
        $count = $exists ? $wpdb->get_var("SELECT COUNT(*) FROM $table") : 'TABLE MISSING';
        $report[$key] = [
            'table' => $table,
            'exists' => $exists ? true : false,
            'row_count' => $count
        ];
    }

    wp_send_json_success($report);
}

function hizli_kasa_ajax_setup() {
    if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'Yetkisiz işlem!']);
    
    $depo_id = intval($_POST['depo_id']);
    if (!$depo_id) wp_send_json_error(['message' => 'Geçersiz depo ID.']);

    require_once HIZLI_KASA_PATH . 'includes/classes/class-stock-manager.php';
    $result = Hizli_Kasa_Stock_Manager::initial_sync($depo_id);

    if ($result) {
        wp_send_json_success(['message' => 'Sistem başarıyla başlatıldı. Tüm stoklar kopyalandı.']);
    } else {
        wp_send_json_error(['message' => 'Bir hata oluştu.']);
    }
}

function hizli_kasa_ajax_reset() {
    if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'Yetkisiz işlem!']);
    
    require_once HIZLI_KASA_PATH . 'includes/classes/class-database.php';
    Hizli_Kasa_Database::drop_everything();
    Hizli_Kasa_Database::init(); // Tabloları boş olarak tekrar oluştur

    wp_send_json_success(['message' => 'Sistem tamamen sıfırlandı.']);
}

/**
 * Stok Dışa Aktarma (Export)
 */
function hizli_kasa_ajax_export_stocks() {
    if (!current_user_can('manage_options')) wp_die('Yetkisiz erişim');
    
    $format = isset($_GET['format']) ? sanitize_text_field($_GET['format']) : 'csv';
    $depo_id = isset($_GET['depo_id']) ? intval($_GET['depo_id']) : 0;
    
    require_once HIZLI_KASA_PATH . 'includes/classes/class-stock-manager.php';
    
    $data = Hizli_Kasa_Stock_Manager::export_stocks($format, $depo_id);
    
    $filename = "hizli-kasa-stok-" . date('Y-m-d') . "." . $format;
    
    header('Content-Description: File Transfer');
    header('Content-Type: ' . ($format === 'json' ? 'application/json' : 'text/csv'));
    header('Content-Disposition: attachment; filename=' . $filename);
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    
    echo $data;
    exit;
}

/**
 * Stok İçe Aktarma (Import)
 */
function hizli_kasa_ajax_import_stocks() {
    if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'Yetkisiz erişim']);
    
    if (!isset($_FILES['import_file'])) {
        wp_send_json_error(['message' => 'Dosya seçilmedi.']);
    }

    $file = $_FILES['import_file'];
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $format = ($ext === 'json') ? 'json' : 'csv';

    require_once HIZLI_KASA_PATH . 'includes/classes/class-stock-manager.php';
    $result = Hizli_Kasa_Stock_Manager::process_import($file['tmp_name'], $format);

    if ($result['success']) {
        wp_send_json_success($result);
    } else {
        wp_send_json_error($result);
    }
}

/**
 * Eşleşmeyen Ürünleri Getir
 */
function hizli_kasa_ajax_get_unmatched() {
    if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'Yetkisiz erişim']);
    
    global $wpdb;
    $table = $wpdb->prefix . 'hizli_kasa_unmatched_items';
    
    // Tablo kontrolü ve gerekirse oluşturma
    if (!$wpdb->get_var("SHOW TABLES LIKE '$table'")) {
        require_once HIZLI_KASA_PATH . 'includes/classes/class-database.php';
        Hizli_Kasa_Database::init();
    }

    $results = $wpdb->get_results("SELECT * FROM $table ORDER BY created_at DESC");
    
    wp_send_json_success($results);
}

/**
 * Eşleşmeyen Ürünü Sil
 */
function hizli_kasa_ajax_delete_unmatched() {
    if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'Yetkisiz erişim']);
    
    $id = intval($_POST['id']);
    global $wpdb;
    $table = $wpdb->prefix . 'hizli_kasa_unmatched_items';
    
    // Eğer ID -1 gelirse bu "Tümünü Temizle" demektir
    if ($id === -1) {
        $wpdb->query("TRUNCATE TABLE $table");
    } else {
        $wpdb->delete($table, ['id' => $id]);
    }
    
    wp_send_json_success(['message' => 'İşlem başarılı.']);
}

/**
 * Tüm Eşleşmeyen Ürünleri Sil (Ekstra Güvenlik)
 */
function hizli_kasa_ajax_clear_all_unmatched() {
    if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'Yetkisiz erişim']);
    
    global $wpdb;
    $table = $wpdb->prefix . 'hizli_kasa_unmatched_items';
    $wpdb->query("TRUNCATE TABLE $table");
    wp_send_json_success(['message' => 'Tüm liste temizlendi.']);
}

// Admin Ayarlar Sayfası Arayüzü
function hizli_kasa_ayarlar_sayfasi()
{
    $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'genel';

    global $wpdb;
    $depo_table = $wpdb->prefix . 'hizli_kasa_depolar';
    $depolar = $wpdb->get_results("SELECT id, name FROM $depo_table ORDER BY priority DESC");
    ?>
    <div class="wrap">
        <h1>Hızlı Kasa Ayarları</h1>
        <?php settings_errors('hizli_kasa_messages'); ?>
        
        <?php
        global $wpdb;
        $unmatched_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}hizli_kasa_unmatched_items");
        $badge = $unmatched_count > 0 ? ' <span style="background:#d63638; color:#fff; padding:1px 6px; border-radius:10px; font-size:10px; font-weight:bold; vertical-align:middle; margin-left:4px;">!</span>' : '';
        ?>
        <h2 class="nav-tab-wrapper">
            <a href="?page=hizli-kasa&tab=genel" class="nav-tab <?php echo $active_tab == 'genel' ? 'nav-tab-active' : ''; ?>">Genel Ayarlar</a>
            <a href="?page=hizli-kasa&tab=stok" class="nav-tab <?php echo $active_tab == 'stok' ? 'nav-tab-active' : ''; ?>">Stok Yönetimi</a>
            <a href="?page=hizli-kasa&tab=unmatched" class="nav-tab <?php echo $active_tab == 'unmatched' ? 'nav-tab-active' : ''; ?>">Eşleşmeyen Ürünler<?php echo $badge; ?></a>
            <a href="?page=hizli-kasa&tab=depolar" class="nav-tab <?php echo $active_tab == 'depolar' ? 'nav-tab-active' : ''; ?>">Depo Yönetimi</a>
            <a href="?page=hizli-kasa&tab=araclar" class="nav-tab <?php echo $active_tab == 'araclar' ? 'nav-tab-active' : ''; ?>">Sistem Araçları</a>
        </h2>

        <div style="margin-top: 20px;">
            <?php if ($active_tab == 'genel'): ?>
                <form method="post" action="options.php">
                    <?php settings_fields('hizli_kasa_ayar_grubu'); ?>
                    <table class="form-table">
                        <tr valign="top">
                            <th scope="row">Varsayılan Sipariş Durumu</th>
                            <td>
                                <?php $secili_durum = get_option('hizli_kasa_siparis_durumu', 'processing'); ?>
                                <select name="hizli_kasa_siparis_durumu">
                                    <option value="processing" <?php selected($secili_durum, 'processing'); ?>>Hazırlanıyor (Önerilen)</option>
                                    <option value="completed" <?php selected($secili_durum, 'completed'); ?>>Tamamlandı</option>
                                    <option value="on-hold" <?php selected($secili_durum, 'on-hold'); ?>>Beklemede</option>
                                </select>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">Erişim Yetkisi Olan Roller</th>
                            <td>
                                <?php
                                $secili_roller = get_option('hizli_kasa_yetkili_roller', array('administrator', 'shop_manager'));
                                $tum_roller = wp_roles()->get_names();

                                foreach ($tum_roller as $rol_slug => $rol_adi):
                                    $checked = in_array($rol_slug, (array) $secili_roller) ? 'checked' : '';
                                    ?>
                                    <label style="display:block; margin-bottom:5px;">
                                        <input type="checkbox" name="hizli_kasa_yetkili_roller[]"
                                            value="<?php echo esc_attr($rol_slug); ?>" <?php echo $checked; ?>>
                                        <?php echo translate_user_role($rol_adi); ?>
                                    </label>
                                <?php endforeach; ?>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">Online Satış Deposu (Öncelikli)</th>
                            <td>
                                <?php 
                                $online_depo = get_option('hizli_kasa_varsayilan_online_depo'); 
                                ?>
                                <select name="hizli_kasa_varsayilan_online_depo">
                                    <option value="">-- Depo Seçilmedi --</option>
                                    <?php foreach($depolar as $d): ?>
                                        <option value="<?php echo $d->id; ?>" <?php selected($online_depo, $d->id); ?>><?php echo esc_html($d->name); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description">Online satışlarda stok önce bu depodan düşülür. Yoksa öncelik sırasına göre diğerlerine bakılır.</p>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">Kritik Stok Eşiği</th>
                            <td>
                                <?php $kritik_esik = get_option('hizli_kasa_kritik_stok_esigi', 5); ?>
                                <input type="number" name="hizli_kasa_kritik_stok_esigi" value="<?php echo esc_attr($kritik_esik); ?>" min="0" step="1" class="small-text"> Adet
                                <p class="description">Depo stoğu bu rakama ve altına düştüğünde terminalde kırmızı uyarı gösterilir.</p>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">Küsürat Yuvarlama</th>
                            <td>
                                <?php $yuvarlama_aktif = get_option('hizli_kasa_yuvarlama_aktif', '1'); ?>
                                <label>
                                    <input type="checkbox" name="hizli_kasa_yuvarlama_aktif" value="1" <?php checked($yuvarlama_aktif, '1'); ?>>
                                    "Küsürat Yuvarla" butonunu göster
                                </label>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">Yuvarlama Modu</th>
                            <td>
                                <?php $yuvarlama_modu = get_option('hizli_kasa_yuvarlama_modu', '1'); ?>
                                <select name="hizli_kasa_yuvarlama_modu">
                                    <option value="0.5" <?php selected($yuvarlama_modu, '0.5'); ?>>0.50 TL'ye yuvarla</option>
                                    <option value="1" <?php selected($yuvarlama_modu, '1'); ?>>1 TL'ye yuvarla</option>
                                    <option value="5" <?php selected($yuvarlama_modu, '5'); ?>>5 TL'nin katına yuvarla</option>
                                    <option value="10" <?php selected($yuvarlama_modu, '10'); ?>>10 TL'nin katına yuvarla</option>
                                </select>
                            </td>
                        </tr>
                    </table>
                    <?php submit_button('Ayarları Kaydet'); ?>
                </form>

            <?php elseif ($active_tab == 'stok'): ?>
                <?php include HIZLI_KASA_PATH . 'includes/views/admin-stok-yonetimi.php'; ?>

            <?php elseif ($active_tab == 'unmatched'): ?>
                <?php include HIZLI_KASA_PATH . 'includes/views/admin-stok-uyusmazlik.php'; ?>

            <?php elseif ($active_tab == 'depolar'): ?>
                <?php include HIZLI_KASA_PATH . 'includes/views/admin-depo-yonetimi.php'; ?>

            <?php elseif ($active_tab == 'araclar'): ?>
                <div class="card">
                    <h3>Sistemi Başlat: Stokları Kopyala</h3>
                    <p>Mevcut WooCommerce ana stoklarını seçilen depoya transfer eder ve sistemi kullanıma hazırlar.</p>
                    <form id="hizli-kasa-setup-form">
                        <select id="setup-target-depo" required>
                            <option value="">-- Hedef Depo Seçin --</option>
                            <?php foreach($depolar as $d): ?>
                                <option value="<?php echo $d->id; ?>"><?php echo esc_html($d->name); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" id="btn-hizli-kasa-setup" class="button button-primary">Sistemi Başlat</button>
                    </form>
                </div>

                <div class="card" style="margin-top:20px; border-color:#d63638;">
                    <h3 style="color:#d63638;">⚠️ Tehlikeli Bölge: Sistemi Sıfırla</h3>
                    <p>Bu işlem tüm depo verilerini, stok konumlarını ve hareket loglarını kalıcı olarak siler!</p>
                    <button type="button" id="btn-hizli-kasa-reset" class="button button-link-delete">Sistemi Sıfırla (Fabrika Ayarları)</button>
                </div>

                <div class="card" style="margin-top:20px;">
                    <h3>Sistem Onarımı</h3>
                    <p>Eğer depoları kaydedemiyorsanız veya veritabanı hataları alıyorsanız tabloları onarmayı deneyin. Bu işlem verilerinizi silmez.</p>
                    <button type="button" id="btn-hizli-kasa-repair" class="button button-secondary">Tabloları Onar / Veritabanı Güncelle</button>
                </div>

                <script>
                jQuery(document).ready(function($) {
                    $('#btn-hizli-kasa-setup').on('click', function() {
                        const depoId = $('#setup-target-depo').val();
                        if(!depoId) return alert('Lütfen bir hedef depo seçin.');
                        if(!confirm('Tüm ürün stokları bu depoya kopyalanacak. Devam edilsin mi?')) return;
                        
                        $(this).prop('disabled', true).text('İşleniyor...');
                        $.post(ajaxurl, { action: 'hizli_kasa_setup', depo_id: depoId }, function(res) {
                            alert(res.data.message);
                            location.reload();
                        });
                    });

                    $('#btn-hizli-kasa-reset').on('click', function() {
                        if(!confirm('DİKKAT! Tüm veriler silinecek. Bu işlem geri alınamaz. Emin misiniz?')) return;
                        if(!confirm('SON UYARI: Gerçekten her şeyi silmek istiyor musunuz?')) return;

                        $(this).prop('disabled', true).text('Siliniyor...');
                        $.post(ajaxurl, { action: 'hizli_kasa_reset' }, function(res) {
                            alert(res.data.message);
                            location.reload();
                        });
                    });

                    $('#btn-hizli-kasa-repair').on('click', function() {
                        $(this).prop('disabled', true).text('Onarılıyor...');
                        $.post(ajaxurl, { action: 'hizli_kasa_repair_db' }, function(res) {
                            alert(res.data.message);
                            location.reload();
                        });
                    });
                });
                </script>
            <?php endif; ?>
        </div>
    </div>
    <?php
}
