<?php
if (!defined('ABSPATH')) exit;

class Hizli_Kasa_User_Warehouse_Permissions {
    public static function init() {
        add_action('show_user_profile', [__CLASS__, 'render_field']);
        add_action('edit_user_profile', [__CLASS__, 'render_field']);
        add_action('personal_options_update', [__CLASS__, 'save_field']);
        add_action('edit_user_profile_update', [__CLASS__, 'save_field']);
    }

public static function get_view_depos($user_id) {
    $cache_aktif = get_option('hizli_kasa_cache_aktif', '1') === '1';
    $cache_key = "hk_user_view_depos_{$user_id}";
    
    if ($cache_aktif) {
        $cached = get_transient($cache_key);
        if ($cached !== false) return $cached;
    }

    $raw = get_user_meta($user_id, '_hizli_kasa_depo_ids_view', true);
    if (empty($raw)) {
        if ($cache_aktif) {
            $ttl_hours = (int) get_option('hizli_kasa_user_perms_cache_ttl', 12);
            set_transient($cache_key, [], $ttl_hours * HOUR_IN_SECONDS);
        }
        return [];
    }
    
    $ids = is_array($raw) ? $raw : json_decode($raw, true);
    $result = array_map('intval', (array) $ids);
    
    if ($cache_aktif) {
        $ttl_hours = (int) get_option('hizli_kasa_user_perms_cache_ttl', 12);
        set_transient($cache_key, $result, $ttl_hours * HOUR_IN_SECONDS);
    }
    return $result;
}

/**
 * Kullanıcının yönetebileceği (stok değiştirebileceği) depo ID listesini döner.
 */
public static function get_manage_depos($user_id) {
    $cache_aktif = get_option('hizli_kasa_cache_aktif', '1') === '1';
    $cache_key = "hk_user_manage_depos_{$user_id}";
    
    if ($cache_aktif) {
        $cached = get_transient($cache_key);
        if ($cached !== false) return $cached;
    }

    $raw = get_user_meta($user_id, '_hizli_kasa_depo_ids_manage', true);
    if (empty($raw)) {
        if ($cache_aktif) {
            $ttl_hours = (int) get_option('hizli_kasa_user_perms_cache_ttl', 12);
            set_transient($cache_key, [], $ttl_hours * HOUR_IN_SECONDS);
        }
        return [];
    }
    
    $ids = is_array($raw) ? $raw : json_decode($raw, true);
    $result = array_map('intval', (array) $ids);
    
    if ($cache_aktif) {
        $ttl_hours = (int) get_option('hizli_kasa_user_perms_cache_ttl', 12);
        set_transient($cache_key, $result, $ttl_hours * HOUR_IN_SECONDS);
    }
    return $result;
}

/**
 * Kullanıcının belirtilen depoyu görüntüleme yetkisi var mı?
 */
public static function can_view($user_id, $depo_id) {
    // Admin her depoyu görebilir
    if (user_can($user_id, 'manage_options')) return true;
    $ids = self::get_view_depos($user_id);
    return in_array(intval($depo_id), $ids);
}

/**
 * Kullanıcının belirtilen depoda yönetim (stok işlemi) yetkisi var mı?
 */
public static function can_manage($user_id, $depo_id) {
    // Admin her depoda işlem yapabilir
    if (user_can($user_id, 'manage_options')) return true;
    $ids = self::get_manage_depos($user_id);
    return in_array(intval($depo_id), $ids);
}

/**
 * Kullanıcının şu an aktif seçili deposunu döner (sunucu tarafı).
 * Öncelik: user_meta → ilk görüntüleme deposu
 */
public static function get_active_depo($user_id) {
    // Admin ise global admin deposunu veya ilk depoya bak
    $active = intval(get_user_meta($user_id, '_hizli_kasa_active_depo', true));
    
    if (!$active) return null;
    
    // Hala bu depoya yetkisi var mı kontrol et
    if (!self::can_view($user_id, $active)) {
        // Yetkisi kaldırılmış, ilk yetkili depoya dön
        $view_ids = self::get_view_depos($user_id);
        return !empty($view_ids) ? $view_ids[0] : null;
    }
    
    return $active;
}

/**
 * Eski tek depo meta'sını yeni çoklu sisteme geçirir.
 * Bir kullanıcıya ilk kez baktığınızda otomatik çalışır.
 */
public static function migrate_legacy($user_id) {
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

public static function render_field($user) {
    if (!current_user_can('manage_options')) return;
    
    // Eski sisteme geçiş (Migration)
    self::migrate_legacy($user->ID);

    global $wpdb;
    $depo_table = $wpdb->prefix . 'hizli_kasa_depolar';
    $depolar = $wpdb->get_results("SELECT id, name FROM $depo_table ORDER BY priority DESC, name ASC");
    
    $view_ids   = self::get_view_depos($user->ID);
    $manage_ids = self::get_manage_depos($user->ID);
    ?>
    <h3>Hızlı Kasa Yetkilendirme</h3>
    <table class="form-table">
        <tr>
            <th>Görünüm Teması</th>
            <td>
                <?php $current_theme = get_user_meta($user->ID, '_hizli_kasa_tema', true) ?: 'light'; ?>
                <select name="hizli_kasa_tema">
                    <option value="light" <?php selected($current_theme, 'light'); ?>>Aydınlık</option>
                    <option value="dark" <?php selected($current_theme, 'dark'); ?>>Karanlık</option>
                </select>
            </td>
        </tr>
        <tr>
            <th>Depo Yetkileri</th>
            <td>
                <?php if (empty($depolar)): ?>
                    <p class="hk-depo-empty">Henüz depo eklenmemiş. Önce <a href="<?php echo admin_url('options-general.php?page=hizli-kasa-ayarlar&tab=depolar'); ?>">Depo Yönetimi</a> sayfasından depo ekleyin.</p>
                <?php else: ?>
                <div class="hk-depo-yetki-grid">
                    <div class="hk-depo-yetki-grup">
                        <h4>�? Görüntüleyebileceği Depolar</h4>
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
                        <h4>⚙�? Yönetebileceği Depolar</h4>
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
                        <p class="hk-yonetim-not">⚠�? Yönetim yetkisi için görüntüleme yetkisi de gereklidir. Kayıt sırasında otomatik eklenir.</p>
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

public static function save_field($user_id) {
    if (!current_user_can('manage_options')) return false;
    if (!isset($_POST['hizli_kasa_depo_ids_view']) && !isset($_POST['hizli_kasa_depo_ids_manage'])) {
        // Eğer checkboxlar gönderilmediyse (hepsi işaretsiz) �? boşalt
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

    if (isset($_POST['hizli_kasa_tema'])) {
        update_user_meta($user_id, '_hizli_kasa_tema', sanitize_text_field($_POST['hizli_kasa_tema']));
    }
    
    // Aktif depo artık görüntüleme listesinde değilse temizle
    $active = intval(get_user_meta($user_id, '_hizli_kasa_active_depo', true));
    if ($active && !in_array($active, $view_ids)) {
        $new_active = !empty($view_ids) ? $view_ids[0] : 0;
        update_user_meta($user_id, '_hizli_kasa_active_depo', $new_active);
    }
    
    // Eski meta varsa temizle
    delete_user_meta($user_id, '_hizli_kasa_depo_id');
}
}
