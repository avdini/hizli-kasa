<?php
if (!defined('ABSPATH')) {
    exit;
}

add_action('rest_api_init', function () {
    register_rest_route('hizli-kasa/v1', '/masraflar', [
        'methods' => 'GET',
        'callback' => 'hizli_kasa_get_masraflar',
        'permission_callback' => fn() => hizli_kasa_can_access_app()
    ]);

    register_rest_route('hizli-kasa/v1', '/masraflar', [
        'methods' => 'POST',
        'callback' => 'hizli_kasa_add_masraf',
        'permission_callback' => fn() => hizli_kasa_can_access_app()
    ]);

    register_rest_route('hizli-kasa/v1', '/masraflar/(?P<id>\d+)', [
        'methods' => 'DELETE',
        'callback' => 'hizli_kasa_delete_masraf',
        'permission_callback' => fn() => hizli_kasa_can_access_app()
    ]);

});

/**
 * Masrafları listeler.
 */
function hizli_kasa_get_masraflar($request)
{
    global $wpdb;
    $tarih = sanitize_text_field($request->get_param('tarih') ?: current_time('Y-m-d'));
    $depo_id = intval($request->get_param('depo_id'));
    $table = Hizli_Kasa_Database::get_tables()['masraflar'];
    if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
        Hizli_Kasa_Database::init();
    }
    $query = $wpdb->prepare("SELECT * FROM $table WHERE DATE(created_at) = %s", $tarih);
    if ($depo_id !== 0) {
        $query .= $wpdb->prepare(" AND location_id = %d", $depo_id);
    }
    $query .= " ORDER BY created_at DESC";
    $results = $wpdb->get_results($query);
    foreach ($results as &$row) {
        $user_info = get_userdata($row->user_id);
        $row->user_name = $user_info ? $user_info->display_name : 'Bilinmeyen';
    }
    return $results;
}

/**
 * Yeni masraf ekler.
 */
function hizli_kasa_add_masraf($request)
{
    global $wpdb;
    $params = $request->get_json_params();
    $category = sanitize_text_field($params['category']);
    $amount = floatval($params['amount']);
    $payment_method = sanitize_text_field($params['payment_method'] ?: 'nakit');
    $description = sanitize_textarea_field($params['description']);
    $depo_id = intval($params['depo_id']);
    $kasa_no = sanitize_text_field($params['kasa_no']);
    $user_id = get_current_user_id();
    if (empty($category) || $amount <= 0) {
        return new WP_Error('invalid_data', 'Kategori ve geçerli bir tutar gerekli.', ['status' => 400]);
    }
    $table = Hizli_Kasa_Database::get_tables()['masraflar'];
    if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
        Hizli_Kasa_Database::init();
    }
    $result = $wpdb->insert($table, [
        'category' => $category,
        'amount' => $amount,
        'payment_method' => $payment_method,
        'description' => $description,
        'user_id' => $user_id,
        'location_id' => $depo_id,
        'kasa_no' => $kasa_no,
        'created_at' => current_time('mysql'),
    ]);
    if (!$result) {
        return new WP_Error('db_error', 'Masraf kaydedilemedi.', ['status' => 500]);
    }
    return ['success' => true, 'id' => $wpdb->insert_id, 'message' => 'Masraf başarıyla kaydedildi.'];
}

/**
 * Masraf siler.
 */
function hizli_kasa_delete_masraf($request)
{
    global $wpdb;
    $id = intval($request->get_param('id'));
    if ($id === 0) {
        return new WP_Error('invalid_id', 'Geçersiz ID.', ['status' => 400]);
    }
    $table = Hizli_Kasa_Database::get_tables()['masraflar'];
    $result = $wpdb->delete($table, ['id' => $id]);
    if (!$result) {
        return new WP_Error('db_error', 'Masraf silinemedi.', ['status' => 500]);
    }
    return ['success' => true, 'message' => 'Masraf silindi.'];
}

