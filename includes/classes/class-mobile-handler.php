<?php
/**
 * Hızlı Kasa - Mobil İşleyici Sınıfı
 * 
 * Mobil envanter sayfasının yönlendirmesini ve asset yüklemelerini yönetir.
 */

if (!defined('ABSPATH')) exit;

class Hizli_Kasa_Mobile_Handler {

    public static function init() {
        add_action('template_redirect', [__CLASS__, 'handle_mobile_mode']);
        add_action('template_redirect', [__CLASS__, 'serve_dynamic_manifest']);
    }

    public static function serve_dynamic_manifest() {
        if (isset($_GET['hizli-kasa-manifest'])) {
            header('Content-Type: application/json; charset=utf-8');
            
            $site_url = home_url('/hizli-kasa/'); // Varsayılan olarak bu sayfaya döner
            // Eğer sayfa adı farklıysa bunu otomatik bulmaya çalışabiliriz veya ayarlardan alabiliriz
            
            $manifest = [
                "name" => "Hızlı Kasa Envanter",
                "short_name" => "Envanter",
                "description" => "Hızlı Kasa Mobil Envanter ve Barkod Tarayıcı",
                "start_url" => $site_url . "?mode=mobile",
                "display" => "standalone",
                "background_color" => "#0f172a",
                "theme_color" => "#6366f1",
                "icons" => [
                    [
                        "src" => HIZLI_KASA_URL . "assets/img/icon-192.png",
                        "sizes" => "192x192",
                        "type" => "image/png",
                        "purpose" => "any maskable"
                    ],
                    [
                        "src" => HIZLI_KASA_URL . "assets/img/icon-512.png",
                        "sizes" => "512x512",
                        "type" => "image/png",
                        "purpose" => "any maskable"
                    ]
                ]
            ];

            echo json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
            exit;
        }
    }

    public static function handle_mobile_mode() {
        if (isset($_GET['mode']) && $_GET['mode'] === 'mobile') {
            
            // Giriş yapmamışsa login sayfasına yönlendir (Giriş sonrası bu sayfaya geri döner)
            if (!is_user_logged_in()) {
                auth_redirect();
            }

            // Giriş yapmış ama yetkisi yoksa hata ver
            require_once HIZLI_KASA_PATH . 'includes/classes/class-shortcode.php';
            if (!hizli_kasa_can_access_app()) {
                wp_die('Bu sayfaya erişim yetkiniz yok.');
            }

            $user = wp_get_current_user();
            $display_name = $user->display_name;
            $tema = get_user_meta($user->ID, '_hizli_kasa_tema', true) ?: 'dark';
            $pos_version = HIZLI_KASA_VERSION;

            // Mobil Assetleri Yükle
            add_action('wp_enqueue_scripts', function() use ($pos_version, $display_name, $user, $tema) {
                wp_enqueue_style('kasa-theme-vars', HIZLI_KASA_URL . 'assets/css/modules/theme-vars.css', array(), $pos_version);
                wp_enqueue_style('kasa-mobile-inventory', HIZLI_KASA_URL . 'assets/css/modules/mobile-inventory.css', array(), $pos_version);
                
                // Kütüphaneler
                wp_enqueue_script('html5-qrcode', 'https://unpkg.com/html5-qrcode', array(), '2.3.8', true);
                
                // Mobil JS
                wp_enqueue_script('kasa-mobile-inventory', HIZLI_KASA_URL . 'assets/js/modules/mobile-inventory.js', array('html5-qrcode'), $pos_version, true);

                wp_localize_script('kasa-mobile-inventory', 'kasaAyar', array(
                    'rootApiUrl' => rest_url(),
                    'nonce'      => wp_create_nonce('wp_rest'),
                    'userName'   => $display_name,
                    'userId'     => $user->ID,
                    'version'    => HIZLI_KASA_VERSION,
                    'tema'       => $tema
                ));
            });

            // Standalone Şablonu Yükle
            include HIZLI_KASA_PATH . 'includes/views/mobile-inventory.php';
            exit;
        }
    }
}
