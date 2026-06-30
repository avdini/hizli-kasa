<?php
/**
 * Hızlı Kasa - Mobil İşleyici Sınıfı
 * 
 * Mobil envanter sayfasının yönlendirmesini ve asset yüklemelerini yönetir.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Hizli_Kasa_Mobile_Handler {

    public static function init() {
        add_action('template_redirect', [self::class, 'handle_mobile_mode']);
        add_action('template_redirect', [self::class, 'serve_dynamic_manifest']);
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
                wp_enqueue_style('kasa-theme-vars', HIZLI_KASA_URL . 'assets/css/modules/theme-vars.css', [], $pos_version);
                wp_enqueue_style('kasa-mobile-inventory', HIZLI_KASA_URL . 'assets/css/modules/mobile-inventory.css', [], $pos_version);
                
                // Kütüphaneler
                wp_enqueue_script('html5-qrcode', 'https://cdn.jsdelivr.net/npm/html5-qrcode/html5-qrcode.min.js', [], '2.3.8', true);

                // Önbellek Yıkıcı — Mobile fetch isteklerini de kapsar
                wp_enqueue_script('kasa-cache-buster', HIZLI_KASA_URL . 'assets/js/modules/cache-buster.js', [], $pos_version, true);

                // Mobil JS
                wp_enqueue_script('kasa-mobile-inventory', HIZLI_KASA_URL . 'assets/js/modules/mobile-inventory.js', ['html5-qrcode', 'kasa-cache-buster'], $pos_version, true);

                // Kullanıcının yetkili olduğu depoları çek
                $view_depos = get_user_meta($user->ID, '_hizli_kasa_depo_ids_view', true);
                
                // Meta veri JSON string veya array olabilir, normalize et
                if (is_string($view_depos) && !empty($view_depos)) {
                    $all_allowed_ids = json_decode($view_depos, true) ?: [];
                } else {
                    $all_allowed_ids = (array)$view_depos;
                }

                global $wpdb;
                $depolar = [];
                
                // Eğer Admin ise ve özel liste boşsa TÜM depoları getir
                if (current_user_can('manage_options') && empty($all_allowed_ids)) {
                    $depolar = $wpdb->get_results("SELECT id, name FROM {$wpdb->prefix}hizli_kasa_depolar ORDER BY priority DESC");
                } 
                // Yoksa sadece yetkili olduğu listeyi getir
                elseif (!empty($all_allowed_ids)) {
                    $ids_ph = implode(',', array_map('intval', $all_allowed_ids));
                    $depolar = $wpdb->get_results("SELECT id, name FROM {$wpdb->prefix}hizli_kasa_depolar WHERE id IN ($ids_ph) ORDER BY priority DESC");
                }

                $aktif_depo_id = get_user_meta($user->ID, '_hizli_kasa_active_depo', true);
                
                // Aktif deponun yetkili listede olduğundan emin ol (Güvenlik ve iOS 0 Stok Önlemi)
                $allowed_ids = wp_list_pluck($depolar, 'id');
                if (!$aktif_depo_id || !in_array((int)$aktif_depo_id, array_map('intval', $allowed_ids))) {
                    $aktif_depo_id = empty($depolar) ? 0 : $depolar[0]->id;
                }

                wp_localize_script('kasa-mobile-inventory', 'kasaAyar', [
                    'rootApiUrl' => rest_url(),
                    'nonce'      => wp_create_nonce('wp_rest'),
                    'userName'   => $display_name,
                    'userId'     => $user->ID,
                    'version'    => HIZLI_KASA_VERSION,
                    'tema'       => $tema,
                    'depolar'    => $depolar,
                    'aktifDepo'  => (int)$aktif_depo_id
                ]);
            });

            // Standalone Şablonu Yükle
            include HIZLI_KASA_PATH . 'includes/views/mobile-inventory.php';
            exit;
        }
    }
}
