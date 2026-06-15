<?php
if (!defined('ABSPATH')) exit;

add_action('rest_api_init', function () {
    register_rest_route('hizli-kasa/v1', '/load-tab', array(
        'methods' => 'GET',
        'callback' => 'hizli_kasa_load_tab_content',
        'permission_callback' => function () {
            return hizli_kasa_can_access_app();
        }
    ));

    register_rest_route('hizli-kasa/v1', '/user/depolar', array(
        'methods' => 'GET',
        'callback' => 'hizli_kasa_api_user_depolar',
        'permission_callback' => function () {
            return hizli_kasa_can_access_app();
        }
    ));

    register_rest_route('hizli-kasa/v1', '/user/set-active-depo', array(
        'methods' => 'POST',
        'callback' => 'hizli_kasa_api_set_active_depo',
        'permission_callback' => function () {
            return hizli_kasa_can_access_app();
        }
    ));

    register_rest_route('hizli-kasa/v1', '/user/set-theme', array(
        'methods' => 'POST',
        'callback' => 'hizli_kasa_api_set_user_theme',
        'permission_callback' => function () {
            return hizli_kasa_can_access_app();
        }
    ));

});

/**
 * Kullanıcının depo listesini ve aktif deposunu döner.
 */
function hizli_kasa_api_user_depolar($request)
{
    if (!headers_sent()) {
        nocache_headers();
        header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    }
    $user_id = get_current_user_id();

    $cache_aktif = get_option('hizli_kasa_cache_aktif', '1') === '1';

    // Admin ise tüm depoları görebilir
    if (current_user_can('manage_options')) {
        global $wpdb;
        $depolar_raw = $cache_aktif ? get_transient('hk_depo_list_all') : false;
        if (false === $depolar_raw) {
            $depolar_raw = $wpdb->get_results("SELECT id, name FROM {$wpdb->prefix}hizli_kasa_depolar ORDER BY priority DESC, name ASC");
            if ($cache_aktif) {
                set_transient('hk_depo_list_all', $depolar_raw, 24 * HOUR_IN_SECONDS);
            }
        }
        $view = array_map(fn($d) => ['id' => (int) $d->id, 'name' => $d->name], $depolar_raw);
        $manage_ids = array_column($view, 'id');
    } else {
        $view_ids = hizli_kasa_get_user_view_depos($user_id);
        $manage_ids = hizli_kasa_get_user_manage_depos($user_id);

        if (empty($view_ids)) {
            return new WP_Error('no_depo', 'Profilinize depo atanmamış.', ['status' => 403]);
        }

        global $wpdb;
        $all_depolar_raw = $cache_aktif ? get_transient('hk_depo_list_all') : false;
        if (false === $all_depolar_raw) {
            $all_depolar_raw = $wpdb->get_results("SELECT id, name FROM {$wpdb->prefix}hizli_kasa_depolar ORDER BY priority DESC, name ASC");
            if ($cache_aktif) {
                set_transient('hk_depo_list_all', $all_depolar_raw, 24 * HOUR_IN_SECONDS);
            }
        }

        $depolar_raw = [];
        if (!empty($view_ids) && !empty($all_depolar_raw)) {
            foreach ($all_depolar_raw as $d) {
                if (in_array((int)$d->id, $view_ids)) {
                    $depolar_raw[] = $d;
                }
            }
        }
        $view = array_map(fn($d) => ['id' => (int) $d->id, 'name' => $d->name], $depolar_raw);
    }

    // Aktif depoyu al (sunucu meta)
    $active_depo_id = hizli_kasa_get_user_active_depo($user_id);

    // Aktif depo yoksa ilk görüntüleme deposunu seç
    if (!$active_depo_id && !empty($view)) {
        $active_depo_id = $view[0]['id'];
        update_user_meta($user_id, '_hizli_kasa_active_depo', $active_depo_id);
    }

    return [
        'view' => $view,
        'manage_ids' => array_values($manage_ids),
        'active_depo_id' => $active_depo_id ? (int) $active_depo_id : null,
    ];
}

/**
 * Kullanıcının aktif deposunu user_meta'ya kaydeder.
 */
function hizli_kasa_api_set_active_depo($request)
{
    $data = $request->get_json_params();
    $depo_id = intval($data['depo_id'] ?? 0);
    $user_id = get_current_user_id();

    if (!$depo_id) {
        return new WP_Error('invalid_depo', 'Geçersiz depo ID.', ['status' => 400]);
    }

    // Yetki kontrolü: Bu depoyu görüntüleme yetkisi var mı?
    if (!hizli_kasa_can_user_view_depo($user_id, $depo_id)) {
        return new WP_Error('no_permission', 'Bu depoya erişim yetkiniz yok.', ['status' => 403]);
    }

    update_user_meta($user_id, '_hizli_kasa_active_depo', $depo_id);

    return [
        'success' => true,
        'active_depo_id' => $depo_id,
        'message' => 'Aktif depo güncellendi.',
    ];
}

/**
 * Kullanıcının tema tercihini kaydeder.
 */
function hizli_kasa_api_set_user_theme($request)
{
    $data = $request->get_json_params();
    $theme = sanitize_text_field($data['theme'] ?? 'light');
    $user_id = get_current_user_id();

    update_user_meta($user_id, '_hizli_kasa_tema', $theme);

    return [
        'success' => true,
        'theme' => $theme,
        'message' => 'Tema tercihi kaydedildi.',
    ];
}

function hizli_kasa_load_tab_content($request)
{
    if (!headers_sent()) {
        nocache_headers();
        header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    }
    $tab = sanitize_text_field($request->get_param('tab'));
    $allowed_tabs = ['kasa', 'urunler', 'raporlar', 'ayarlar', 'iade', 'masraf', 'sevk'];
    if (!in_array($tab, $allowed_tabs)) {
        return new WP_Error('invalid_tab', 'Geçersiz sekme adı.', array('status' => 400));
    }

    $template_file = HIZLI_KASA_PATH . "includes/views/tab-{$tab}.php";
    if (!file_exists($template_file)) {
        return array(
            'html' => "<div style='padding:40px; text-align:center;'><h3>{$tab} Sayfası Hazırlanıyor...</h3><p>Bu modül yakında aktif edilecek.</p></div>"
        );
    }

    $user_id = get_current_user_id();
    $user_theme = get_user_meta($user_id, '_hizli_kasa_tema', true) ?: 'light';

    ob_start();
    include $template_file;
    $html = ob_get_clean();

    return array('html' => $html);
}

