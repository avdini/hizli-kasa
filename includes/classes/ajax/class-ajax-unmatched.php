<?php
if (!defined('ABSPATH')) {
    exit;
}

class Hizli_Kasa_Ajax_Unmatched {
    public static function init() {
        add_action('wp_ajax_hizli_kasa_get_unmatched', [self::class, 'get_list']);
        add_action('wp_ajax_hizli_kasa_delete_unmatched', [self::class, 'delete']);
        add_action('wp_ajax_hizli_kasa_clear_all_unmatched', [self::class, 'clear_all']);
    }

public static function get_list() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Yetkisiz erişim']);
    }
    
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
public static function delete() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Yetkisiz erişim']);
    }
    
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
public static function clear_all() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Yetkisiz erişim']);
    }
    
    global $wpdb;
    $table = $wpdb->prefix . 'hizli_kasa_unmatched_items';
    $wpdb->query("TRUNCATE TABLE $table");
    wp_send_json_success(['message' => 'Tüm liste temizlendi.']);
}
}
