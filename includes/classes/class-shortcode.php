<?php
/**
 * Hızlı Kasa - Shortcode
 *
 * [hizli_kasa] shortcode kaydı, yetki kontrolü ve
 * CSS/JS dosyalarının enqueue edilmesi.
 *
 * @package HizliKasa
 */

if (!defined('ABSPATH'))
    exit;

// Shortcode Kaydı
add_shortcode('hizli_kasa', 'hizli_kasa_uygulamasi');

/**
 * Hızlı Kasa shortcode callback fonksiyonu.
 *
 * Yetki kontrolü yapar, gerekli asset'leri yükler
 * ve HTML template'i render eder.
 *
 * @return string Shortcode HTML çıktısı
 */
function hizli_kasa_uygulamasi()
{
    // Yetki Kontrolü
    $user = wp_get_current_user();
    $user_roles = (array) $user->roles;
    $yetkili_roller = get_option('hizli_kasa_yetkili_roller', array('administrator', 'shop_manager'));

    $yetkili_mi = false;
    foreach ($user_roles as $role) {
        if (in_array($role, (array) $yetkili_roller)) {
            $yetkili_mi = true;
            break;
        }
    }

    if (!$yetkili_mi) {
        return '<div style="padding:20px; color:red; font-weight:bold;">Bu sayfayı görüntülemek için yetkiniz bulunmamaktadır.</div>';
    }

    // POS Sayfasını Önbelleğe Almayı Engelle
    if (!defined('DONOTCACHEPAGE')) define('DONOTCACHEPAGE', true);

    // Admin Bar'ı Gizle (Gerçek Uygulama Hissi İçin)
    add_filter('show_admin_bar', '__return_false');

    $pos_version = HIZLI_KASA_VERSION;

    // CSS Dosyasını Yükle
    wp_enqueue_style(
        'kasa-css',
        HIZLI_KASA_URL . 'assets/css/kasa.css',
        array(),
        $pos_version
    );

    // JavaScript Modüllerini Yükle (doğru sırada)
    $js_base = HIZLI_KASA_URL . 'assets/js/';

    // Barkod Kütüphanesi
    wp_enqueue_script('jsbarcode', 'https://cdn.jsdelivr.net/npm/jsbarcode@3.11.0/dist/JsBarcode.all.min.js', array(), '3.11.0', true);

    wp_enqueue_script('kasa-cart-manager', $js_base . 'modules/cart-manager.js', array(), $pos_version, true);
    wp_enqueue_script('kasa-ui-renderer', $js_base . 'modules/ui-renderer.js', array('kasa-cart-manager'), $pos_version, true);
    wp_enqueue_script('kasa-barcode-scanner', $js_base . 'modules/barcode-scanner.js', array('kasa-cart-manager', 'kasa-ui-renderer'), $pos_version, true);
    wp_enqueue_script('kasa-modal-manager', $js_base . 'modules/modal-manager.js', array('kasa-cart-manager', 'kasa-ui-renderer'), $pos_version, true);
    wp_enqueue_script('kasa-order-processor', $js_base . 'modules/order-processor.js', array('kasa-cart-manager', 'kasa-ui-renderer'), $pos_version, true);
    wp_enqueue_script('kasa-receipt-printer', $js_base . 'modules/receipt-printer.js', array('kasa-order-processor'), $pos_version, true);
    wp_enqueue_script('kasa-day-end-report', $js_base . 'modules/day-end-report.js', array('kasa-cart-manager'), $pos_version, true);
    wp_enqueue_script('kasa-app-navigation', $js_base . 'modules/app-navigation.js', array('kasa-ui-renderer'), $pos_version, true);
    wp_enqueue_script('kasa-refund-manager', $js_base . 'modules/refund-manager.js', array('kasa-ui-renderer'), $pos_version, true);
    wp_enqueue_script('kasa-stock-terminal', $js_base . 'modules/stock-terminal.js', array('kasa-ui-renderer'), $pos_version, true);
    wp_enqueue_script('kasa-js', $js_base . 'kasa.js', array(
        'kasa-cart-manager',
        'kasa-ui-renderer',
        'kasa-barcode-scanner',
        'kasa-modal-manager',
        'kasa-order-processor',
        'kasa-receipt-printer',
        'kasa-day-end-report',
        'kasa-app-navigation',
        'kasa-refund-manager',
        'kasa-stock-terminal'
    ), $pos_version, true);

    // JavaScript'e veri aktarımı
    $guncel_durum = get_option('hizli_kasa_siparis_durumu', 'processing');
    $full_name = trim($user->first_name . ' ' . $user->last_name);
    $display_name = !empty($full_name) ? $full_name : $user->display_name;

    wp_localize_script('kasa-cart-manager', 'kasaAyar', array(
        'apiUrl' => rest_url('wc/v3/'),
        'rootApiUrl' => rest_url(),
        'nonce' => wp_create_nonce('wp_rest'),
        'siparisDurumu' => $guncel_durum,
        'userName' => $display_name,
        'version' => HIZLI_KASA_VERSION,
        'yuvarlamaAktif' => get_option('hizli_kasa_yuvarlama_aktif', '1'),
        'yuvarlaModu' => get_option('hizli_kasa_yuvarlama_modu', '1')
    ));

    // HTML Template'i Render Et
    ob_start();
    include HIZLI_KASA_PATH . 'includes/template-app.php';
    return ob_get_clean();
}
