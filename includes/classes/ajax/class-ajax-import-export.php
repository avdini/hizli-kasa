<?php
if (!defined('ABSPATH')) {
    exit;
}

class Hizli_Kasa_Ajax_Import_Export {
    public static function init() {
        add_action('wp_ajax_hizli_kasa_export_stocks', [self::class, 'export']);
        add_action('wp_ajax_hizli_kasa_import_stocks', [self::class, 'import']);
    }

public static function export() {
    if (!current_user_can('manage_options')) {
        wp_die('Yetkisiz erişim');
    }
    
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
public static function import() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Yetkisiz erişim']);
    }
    
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
}
