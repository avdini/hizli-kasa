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
    add_options_page(
        'Hızlı Kasa Ayarları',
        'Hızlı Kasa',
        'manage_options',
        'hizli-kasa-ayarlar',
        'hizli_kasa_ayarlar_sayfasi'
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
    if (!isset($_GET['page']) || $_GET['page'] !== 'hizli-kasa-ayarlar') return;
    
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
        }
    }

    // Yeni Depo Ekleme
    if (isset($_POST['hizli_kasa_depo_ekle'])) {
        error_log('Hızlı Kasa: Depo ekleme tetiklendi.');
        check_admin_referer('depo_ekle_action', 'depo_ekle_nonce');
        
        $inserted = $wpdb->insert($table_name, [
            'name'        => sanitize_text_field($_POST['depo_name']),
            'address'     => sanitize_textarea_field($_POST['depo_address']),
            'description' => sanitize_textarea_field($_POST['depo_desc']),
            'priority'    => intval($_POST['depo_priority']),
            'created_at'  => current_time('mysql')
        ]);

        if ($inserted === false) {
            $error_msg = $wpdb->last_error ?: "Bilinmeyen veritabanı hatası. Tablolar eksik olabilir. Lütfen 'Sistem Araçları' sekmesinden tabloları onarın.";
            wp_safe_redirect(admin_url('options-general.php?page=hizli-kasa-ayarlar&tab=depolar&hizli_kasa_msg=depo_hata&hizli_kasa_err=' . urlencode($error_msg)));
            exit;
        }

        wp_redirect(admin_url('options-general.php?page=hizli-kasa-ayarlar&tab=depolar&hizli_kasa_msg=depo_eklendi'));
        exit;
    }

    // Depo Silme
    if (isset($_GET['delete_depo'])) {
        $depo_id = intval($_GET['delete_depo']);
        check_admin_referer('delete_depo_' . $depo_id);
        
        $deleted = $wpdb->delete($table_name, ['id' => $depo_id]);

        if ($deleted) {
            // Sıkı İzolasyon: Deponun tüm stoklarını ve loglarını da sil
            $wpdb->delete($wpdb->prefix . 'hizli_kasa_stok_konumlari',  ['location_id' => $depo_id]);
            $wpdb->delete($wpdb->prefix . 'hizli_kasa_stok_hareketleri', ['location_id' => $depo_id]);
        }

        $msg = $deleted ? 'depo_silindi' : 'depo_silme_hata';
        wp_redirect(admin_url('options-general.php?page=hizli-kasa-ayarlar&tab=depolar&hizli_kasa_msg=' . $msg));
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

function hizli_kasa_ajax_repair_db() {
    if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'Yetkisiz işlem!']);
    
    require_once HIZLI_KASA_PATH . 'includes/classes/class-database.php';
    Hizli_Kasa_Database::init(); // Tabloları eksikse oluşturur, varsa günceller

    wp_send_json_success(['message' => 'Veritabanı tabloları başarıyla onarıldı.']);
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
        
        <h2 class="nav-tab-wrapper">
            <a href="?page=hizli-kasa-ayarlar&tab=genel" class="nav-tab <?php echo $active_tab == 'genel' ? 'nav-tab-active' : ''; ?>">Genel Ayarlar</a>
            <a href="?page=hizli-kasa-ayarlar&tab=depolar" class="nav-tab <?php echo $active_tab == 'depolar' ? 'nav-tab-active' : ''; ?>">Depo Yönetimi</a>
            <a href="?page=hizli-kasa-ayarlar&tab=araclar" class="nav-tab <?php echo $active_tab == 'araclar' ? 'nav-tab-active' : ''; ?>">Sistem Araçları</a>
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
