<?php
if (!defined('ABSPATH')) exit;

class Hizli_Kasa_Ajax_Unmatched {
    public static function init() {
        add_action('wp_ajax_hizli_kasa_get_unmatched', [__CLASS__, 'get_list']);
        add_action('wp_ajax_hizli_kasa_delete_unmatched', [__CLASS__, 'delete']);
        add_action('wp_ajax_hizli_kasa_clear_all_unmatched', [__CLASS__, 'clear_all']);
    }

public static function get_list() {
    if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'Yetkisiz eriÅŸim']);
    
    global $wpdb;
    $table = $wpdb->prefix . 'hizli_kasa_unmatched_items';
    
    // Tablo kontrolÃ¼ ve gerekirse oluÅŸturma
    if (!$wpdb->get_var("SHOW TABLES LIKE '$table'")) {
        require_once HIZLI_KASA_PATH . 'includes/classes/class-database.php';
        Hizli_Kasa_Database::init();
    }

    $results = $wpdb->get_results("SELECT * FROM $table ORDER BY created_at DESC");
    
    wp_send_json_success($results);
}

/**
 * EÅŸleÅŸmeyen ÃœrÃ¼nÃ¼ Sil
 */
public static function delete() {
    if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'Yetkisiz eriÅŸim']);
    
    $id = intval($_POST['id']);
    global $wpdb;
    $table = $wpdb->prefix . 'hizli_kasa_unmatched_items';
    
    // EÄŸer ID -1 gelirse bu "TÃ¼mÃ¼nÃ¼ Temizle" demektir
    if ($id === -1) {
        $wpdb->query("TRUNCATE TABLE $table");
    } else {
        $wpdb->delete($table, ['id' => $id]);
    }
    
    wp_send_json_success(['message' => 'Ä°ÅŸlem baÅŸarÄ±lÄ±.']);
}

/**
 * TÃ¼m EÅŸleÅŸmeyen ÃœrÃ¼nleri Sil (Ekstra GÃ¼venlik)
 */
public static function clear_all() {
    if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'Yetkisiz eriÅŸim']);
    
    global $wpdb;
    $table = $wpdb->prefix . 'hizli_kasa_unmatched_items';
    $wpdb->query("TRUNCATE TABLE $table");
    wp_send_json_success(['message' => 'TÃ¼m liste temizlendi.']);
}
}
