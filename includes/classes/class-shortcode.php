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

function hizli_kasa_can_access_app($user_id = null)
{
    $user = $user_id ? get_userdata($user_id) : wp_get_current_user();

    if (!$user || empty($user->ID)) {
        hizli_kasa_log("Yetki Hatası: Kullanıcı bulunamadı veya oturum kapalı.");
        return false;
    }

    if (user_can($user, 'manage_options')) {
        return true;
    }

    $user_roles = (array) $user->roles;
    $yetkili_roller = get_option('hizli_kasa_yetkili_roller', array('administrator', 'shop_manager', 'hizli_kasa'));
    
    $has_access = false;
    foreach ($user_roles as $role) {
        if (in_array($role, (array) $yetkili_roller, true)) {
            $has_access = true;
            break;
        }
    }

    if (!$has_access) {
        hizli_kasa_log("Yetki Reddedildi: User ID: " . $user->ID . " | Roller: " . implode(', ', $user_roles));
    }

    return $has_access;
}

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
    $yetkili_roller = get_option('hizli_kasa_yetkili_roller', array('administrator', 'shop_manager', 'hizli_kasa'));

    $yetkili_mi = hizli_kasa_can_access_app();
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
    nocache_headers(); // Cloudflare ve Tarayıcı Cache Engelleme


    $pos_version = HIZLI_KASA_VERSION;

    // CSS Modüllerini Yükle
    $css_modules = [
        'theme-vars', 'reset', 'utilities', 'layout', 'sidebar',
        'cart', 'barcode', 'totals', 'modals', 'refund',
        'stock-terminal', 'reports', 'statistics', 'toast', 'print', 'responsive', 'barcode-print', 'order-editor', 'sevk'
    ];

    foreach ($css_modules as $module) {
        wp_enqueue_style(
            'kasa-' . $module,
            HIZLI_KASA_URL . 'assets/css/modules/' . $module . '.css',
            array(),
            $pos_version
        );
    }

    // JavaScript Modüllerini Yükle (doğru sırada)
    $js_base = HIZLI_KASA_URL . 'assets/js/';

    // Barkod Kütüphanesi
    wp_enqueue_script('jsbarcode', 'https://cdn.jsdelivr.net/npm/jsbarcode@3.11.0/dist/JsBarcode.all.min.js', array(), '3.11.0', true);

    // intl-tel-input Kütüphanesi (v29.0.3 Yerelleştirme ve Gelişmiş Arama Desteği)
    wp_enqueue_style('intl-tel-input', 'https://cdn.jsdelivr.net/npm/intl-tel-input@29.0.3/dist/css/intlTelInput.css', array(), '29.0.3');
    wp_enqueue_script('intl-tel-input', 'https://cdn.jsdelivr.net/npm/intl-tel-input@29.0.3/dist/js/intlTelInput.min.js', array(), '29.0.3', true);

    // Chart.js Kütüphanesi (İstatistik Dashboardu)
    wp_enqueue_script('chartjs', 'https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js', array(), '4.4.3', true);

    // Önbellek Yıkıcı (Cache Buster) — HER ŞEYDEN ÖNCE yüklenmeli
    wp_enqueue_script('kasa-cache-buster', $js_base . 'modules/cache-buster.js', array(), $pos_version, true);

    wp_enqueue_script('kasa-html2canvas', $js_base . 'lib/html2canvas.min.js', array(), $pos_version, true);
    wp_enqueue_script('kasa-print-manager', $js_base . 'modules/print-manager.js', array('kasa-html2canvas'), $pos_version, true);
    wp_enqueue_script('kasa-currency-mask', $js_base . 'modules/currency-mask.js', array(), $pos_version, true);
    wp_enqueue_script('kasa-cart-manager', $js_base . 'modules/cart-manager.js', array(), $pos_version, true);
    wp_enqueue_script('kasa-ui-renderer', $js_base . 'modules/ui-renderer.js', array('kasa-cart-manager', 'kasa-currency-mask'), $pos_version, true);
    wp_enqueue_script('kasa-barcode-scanner', $js_base . 'modules/barcode-scanner.js', array('kasa-cart-manager', 'kasa-ui-renderer'), $pos_version, true);
    wp_enqueue_script('kasa-modal-manager', $js_base . 'modules/modal-manager.js', array('kasa-cart-manager', 'kasa-ui-renderer'), $pos_version, true);
    wp_enqueue_script('kasa-order-processor', $js_base . 'modules/order-processor.js', array('intl-tel-input', 'kasa-cart-manager', 'kasa-ui-renderer'), $pos_version, true);
    wp_enqueue_script('kasa-receipt-printer', $js_base . 'modules/receipt-printer.js', array('kasa-order-processor'), $pos_version, true);
    wp_enqueue_script('kasa-day-end-report', $js_base . 'modules/day-end-report.js', array('kasa-cart-manager'), $pos_version, true);
    wp_enqueue_script('kasa-anlik-kasa', $js_base . 'modules/anlik-kasa.js', array('kasa-ui-renderer'), $pos_version, true);
    wp_enqueue_script('kasa-app-navigation', $js_base . 'modules/app-navigation.js', array('kasa-ui-renderer'), $pos_version, true);
    wp_enqueue_script('kasa-refund-manager', $js_base . 'modules/refund-manager.js', array('kasa-ui-renderer'), $pos_version, true);
    wp_enqueue_script('kasa-expense-manager', $js_base . 'modules/expense-manager.js', array('kasa-ui-renderer'), $pos_version, true);
    wp_enqueue_script('kasa-stock-terminal', $js_base . 'modules/stock-terminal.js', array('kasa-ui-renderer'), $pos_version, true);
    wp_enqueue_script('kasa-sayim-manager', $js_base . 'modules/sayim-manager.js', array('kasa-ui-renderer', 'kasa-depo-manager'), $pos_version, true);
    wp_enqueue_script('kasa-theme-manager', $js_base . 'modules/theme-manager.js', array('kasa-ui-renderer'), $pos_version, true);
    wp_enqueue_script('kasa-depo-manager',   $js_base . 'modules/depo-manager.js',   array('kasa-ui-renderer'), $pos_version, true);
    wp_enqueue_script('kasa-barcode-renderer', $js_base . 'modules/barcode-renderer.js', array('kasa-ui-renderer', 'jsbarcode'), $pos_version, true);
    wp_enqueue_script('kasa-order-editor', $js_base . 'modules/order-editor.js', array('kasa-ui-renderer'), $pos_version, true);
    wp_enqueue_script('kasa-order-edit-reports', $js_base . 'modules/order-edit-reports.js', array('kasa-ui-renderer'), $pos_version, true);
    wp_enqueue_script('kasa-detailed-reports', $js_base . 'modules/detailed-reports.js', array('kasa-ui-renderer'), $pos_version, true);
    wp_enqueue_script('kasa-report-receipt-printer', $js_base . 'modules/report-receipt-printer.js', array('kasa-detailed-reports', 'kasa-print-manager', 'jsbarcode'), $pos_version, true);
    wp_enqueue_script('kasa-sevk-manager', $js_base . 'modules/sevk-manager.js', array('kasa-ui-renderer', 'kasa-depo-manager'), $pos_version, true);
    wp_enqueue_script('kasa-malkabul-manager', $js_base . 'modules/malkabul-manager.js', array('kasa-ui-renderer', 'kasa-depo-manager'), $pos_version, true);
    wp_enqueue_script('kasa-statistics-dashboard', $js_base . 'modules/statistics-dashboard.js', array('kasa-ui-renderer', 'kasa-depo-manager', 'chartjs'), $pos_version, true);
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
        'kasa-stock-terminal',
        'kasa-sayim-manager',
        'kasa-barcode-renderer',
        'kasa-order-editor',
        'kasa-order-edit-reports',
        'kasa-detailed-reports',
        'kasa-report-receipt-printer',
        'kasa-sevk-manager',
        'kasa-malkabul-manager',
        'kasa-statistics-dashboard',
        'kasa-currency-mask'
    ), $pos_version, true);

    // JavaScript'e veri aktarımı
    $guncel_durum = get_option('hizli_kasa_siparis_durumu', 'processing');
    $full_name = trim($user->first_name . ' ' . $user->last_name);
    $display_name = !empty($full_name) ? $full_name : $user->display_name;

    wp_localize_script('kasa-cart-manager', 'kasaAyar', array(
        'apiUrl'          => rest_url('wc/v3/'),
        'rootApiUrl'      => rest_url(),
        'nonce'           => wp_create_nonce('wp_rest'),
        'siparisDurumu'   => $guncel_durum,
        'userName'        => $display_name,
        'userId'          => get_current_user_id(),
        'version'         => HIZLI_KASA_VERSION,
        'yuvarlamaAktif'  => get_option('hizli_kasa_yuvarlama_aktif', '1'),
        'yuvarlaModu'     => get_option('hizli_kasa_yuvarlama_modu', '1'),
        'kritikStokEsigi' => (int)get_option('hizli_kasa_kritik_stok_esigi', 5),
        'toplamKasa'      => (int)get_option('hizli_kasa_toplam_kasa', 3),
        'anlikKasaKapsam' => get_option('hizli_kasa_anlik_kasa_kapsam', 'secili'),
        'tema'            => get_user_meta(get_current_user_id(), '_hizli_kasa_tema', true) ?: 'light',
        'fallbackSkuToId' => get_option('hizli_kasa_fallback_sku_to_id', '0'),
        'iskontoTelefonEsigi' => (int)get_option('hizli_kasa_iskonto_telefon_esigi', 2000)
    ));

    // HTML Template'i Render Et
    ob_start();
    include HIZLI_KASA_PATH . 'includes/template-app.php';
    return ob_get_clean();
}
