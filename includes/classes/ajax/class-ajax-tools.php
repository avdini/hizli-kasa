<?php
if (!defined('ABSPATH')) exit;

class Hizli_Kasa_Ajax_Tools {
    public static function init() {
        add_action('wp_ajax_hizli_kasa_setup', [__CLASS__, 'setup']);
        add_action('wp_ajax_hizli_kasa_reset', [__CLASS__, 'reset']);
        add_action('wp_ajax_hizli_kasa_repair_db', [__CLASS__, 'repair_db']);
        add_action('wp_ajax_hizli_kasa_debug_db', [__CLASS__, 'debug_db']);
        add_action('wp_ajax_hizli_kasa_manual_mismatch_check', [__CLASS__, 'mismatch_check']);
        add_action('wp_ajax_hizli_kasa_clear_cache', [__CLASS__, 'clear_cache']);
        add_action('wp_ajax_hizli_kasa_sync_wh_to_wc_start', [__CLASS__, 'sync_start']);
        add_action('wp_ajax_hizli_kasa_sync_wh_to_wc_step', [__CLASS__, 'sync_step']);
    }

public static function clear_cache() {
    if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'Yetkisiz eriÅŸim']);
    
    $type = isset($_POST['cache_type']) ? sanitize_text_field($_POST['cache_type']) : '';
    
    if ($type === 'depolar') {
        delete_transient('hk_depo_list_all');
        wp_send_json_success(['message' => 'Depo listesi Ã¶nbelleÄŸi temizlendi.']);
    } elseif ($type === 'arama') {
        update_option('hizli_kasa_search_cache_version', time());
        wp_send_json_success(['message' => 'ÃœrÃ¼n arama Ã¶nbelleÄŸi temizlendi.']);
    } elseif ($type === 'raporlar') {
        update_option('hk_reports_cache_version', time());
        wp_send_json_success(['message' => 'Raporlar ve istatistik Ã¶nbelleÄŸi temizlendi.']);
    } elseif ($type === 'yetkiler') {
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_hk_user_view_depos_%' OR option_name LIKE '_transient_hk_user_manage_depos_%'");
        wp_send_json_success(['message' => 'TÃ¼m kullanÄ±cÄ± yetki Ã¶nbellekleri temizlendi.']);
    } elseif ($type === 'all') {
        delete_transient('hk_depo_list_all');
        update_option('hizli_kasa_search_cache_version', time());
        update_option('hk_reports_cache_version', time());
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_hk_user_view_depos_%' OR option_name LIKE '_transient_hk_user_manage_depos_%'");
        wp_send_json_success(['message' => 'TÃ¼m Ã¶nbellek modÃ¼lleri baÅŸarÄ±yla temizlendi.']);
    }
    
    wp_send_json_error(['message' => 'GeÃ§ersiz Ã¶nbellek tÃ¼rÃ¼.']);
}

/**
 * AJAX Start Warehouse to WooCommerce Stock Sync
 */
public static function sync_start() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Yetkisiz eriÅŸim!']);
    }

    global $wpdb;
    $table = Hizli_Kasa_Database::get_tables()['stok_konumlari'];

    // Get all unique active product and variation IDs from stok_konumlari
    $results = $wpdb->get_results("
        SELECT DISTINCT 
            (CASE WHEN variation_id > 0 THEN variation_id ELSE product_id END) as id
        FROM $table
    ");

    $ids = array_map(function($r) { return (int)$r->id; }, $results);

    wp_send_json_success(['ids' => $ids]);
}

/**
 * AJAX Step Warehouse to WooCommerce Stock Sync
 */
public static function sync_step() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Yetkisiz eriÅŸim!']);
    }

    $ids = isset($_POST['ids']) ? array_map('intval', $_POST['ids']) : [];

    if (empty($ids)) {
        wp_send_json_success(['processed' => 0]);
    }

    global $wpdb;
    $table = Hizli_Kasa_Database::get_tables()['stok_konumlari'];

    foreach ($ids as $id) {
        $post_type = get_post_field('post_type', $id);
        if ($post_type === 'product_variation') {
            $product_id = get_post_field('post_parent', $id);
            $variation_id = $id;
        } else {
            $product_id = $id;
            $variation_id = 0;
        }

        // Calculate sum quantity across all warehouses
        $total_wh = $wpdb->get_var($wpdb->prepare("
            SELECT SUM(quantity) FROM $table 
            WHERE product_id = %d AND variation_id = %d
        ", $product_id, $variation_id));

        $total_wh = $total_wh !== null ? (float)$total_wh : 0.0;

        // Sync to WooCommerce stock
        wc_update_product_stock($id, $total_wh, 'set');
    }

    wp_send_json_success(['processed' => count($ids)]);
}

/**
 * Manuel UyuÅŸmazlÄ±k KontrolÃ¼ AJAX
 */
public static function mismatch_check() {
    if (!current_user_can('manage_options')) wp_send_json_error();
    
    require_once HIZLI_KASA_PATH . 'includes/classes/class-mismatch-notifier.php';
    $found = Hizli_Kasa_Mismatch_Notifier::run_check();
    
    wp_send_json_success([
        'found' => $found,
        'last_check' => get_option('hizli_kasa_mismatch_last_check')
    ]);
}

public static function repair_db() {
    if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'Yetkisiz iÅŸlem!']);
    
    require_once HIZLI_KASA_PATH . 'includes/classes/class-database.php';
    Hizli_Kasa_Database::init(); // TablolarÄ± eksikse oluÅŸturur, varsa gÃ¼nceller

    wp_send_json_success(['message' => 'VeritabanÄ± tablolarÄ± baÅŸarÄ±yla onarÄ±ldÄ±.']);
}

public static function debug_db() {
    if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'Yetkisiz eriÅŸim']);
    
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

public static function setup() {
    if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'Yetkisiz iÅŸlem!']);
    
    $depo_id = intval($_POST['depo_id']);
    if (!$depo_id) wp_send_json_error(['message' => 'GeÃ§ersiz depo ID.']);

    require_once HIZLI_KASA_PATH . 'includes/classes/class-stock-manager.php';
    $result = Hizli_Kasa_Stock_Manager::initial_sync($depo_id);

    if ($result) {
        wp_send_json_success(['message' => 'Sistem baÅŸarÄ±yla baÅŸlatÄ±ldÄ±. TÃ¼m stoklar kopyalandÄ±.']);
    } else {
        wp_send_json_error(['message' => 'Bir hata oluÅŸtu.']);
    }
}

public static function reset() {
    if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'Yetkisiz iÅŸlem!']);
    
    require_once HIZLI_KASA_PATH . 'includes/classes/class-database.php';
    Hizli_Kasa_Database::drop_everything();
    Hizli_Kasa_Database::init(); // TablolarÄ± boÅŸ olarak tekrar oluÅŸtur

    wp_send_json_success(['message' => 'Sistem tamamen sÄ±fÄ±rlandÄ±.']);
}
}
