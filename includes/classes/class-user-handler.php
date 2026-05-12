<?php
/**
 * Hızlı Kasa - Kullanıcı ve Rol Yönetimi
 *
 * Özel kasiyer rolü oluşturur ve yetkilendirme işlemlerini yönetir.
 *
 * @package HizliKasa
 */

if (!defined('ABSPATH')) exit;

class Hizli_Kasa_User_Handler {

    public static function init() {
        // Rol oluşturma
        add_action('init', [__CLASS__, 'ensure_kasa_role']);
        
        // Admin paneli erişim kısıtlaması
        add_action('admin_init', [__CLASS__, 'restrict_admin_access']);
        
        // Admin barı gizle
        add_filter('show_admin_bar', [__CLASS__, 'hide_admin_bar']);

        // WooCommerce REST API yetki kontrolüne müdahale (Öncelik artırıldı: 20)
        add_filter('user_has_cap', [__CLASS__, 'bypass_woo_rest_permissions'], 20, 4);
    }

    /**
     * 'hizli_kasa' adında özel bir rol oluşturur.
     * Bu rol sadece POS işlemlerini yapabilir, admin paneline giremez.
     */
    public static function ensure_kasa_role() {
        $role_id = 'hizli_kasa';
        $role_name = 'Hızlı Kasa Kasiyer';
        
        // Rol zaten varsa yetkileri güncelle, yoksa oluştur
        // KALICI YETKİLERİ MİNİMAL TUTUYORUZ (GÜVENLİK İÇİN)
        $capabilities = [
            'read'                       => true,
            'view_admin_dashboard'       => false, // Dinamik olarak halledilecek
            'edit_shop_orders'           => false, // Dinamik olarak halledilecek
            'publish_shop_orders'        => false, // Dinamik olarak halledilecek
            'edit_others_shop_orders'    => false,
            'read_private_shop_orders'   => false,
            'edit_products'              => false,
            'manage_woocommerce'         => false,
            'manage_options'             => false
        ];

        $role = get_role($role_id);
        
        if (null === $role) {
            add_role($role_id, $role_name, $capabilities);
        } else {
            // Yetkileri her ihtimale karşı tazele (Güncelleme durumları için)
            foreach ($capabilities as $cap => $grant) {
                if ($grant) {
                    $role->add_cap($cap);
                } else {
                    $role->remove_cap($cap);
                }
            }
        }
    }

    /**
     * Kasiyer rolündeki kullanıcıların admin paneline girmesini engeller.
     */
    public static function restrict_admin_access() {
        if (defined('DOING_AJAX') && DOING_AJAX) {
            return;
        }

        $user = wp_get_current_user();
        if (in_array('hizli_kasa', (array) $user->roles)) {
            // Admin paneline girmeye çalışırsa POS terminaline gönder
            wp_safe_redirect(home_url('/hizli-kasa/terminal/'));
            exit;
        }
    }

    /**
     * Kasiyer rolündeki kullanıcılar için üstteki admin barı gizler.
     */
    public static function hide_admin_bar($show) {
        if (current_user_can('hizli_kasa')) {
            return false;
        }
        return $show;
    }

    /**
     * WooCommerce REST API üzerinden sipariş oluştururken yetki kontrolünü zorla geçer.
     * Bu sayede kasiyer rolü tam WooCommerce yönetici yetkisi olmadan da sipariş yazabilir.
     * GÜVENLİK NOTU: Bu izinler veritabanına yazılmaz, sadece o saniyelik API isteği için geçerlidir.
     */
    public static function bypass_woo_rest_permissions($allcaps, $caps, $args, $user) {
        // Eğer bir REST API isteği değilse veya kullanıcı kasiyer değilse dokunma
        $is_rest = defined('REST_REQUEST') && REST_REQUEST;
        
        // Bazı durumlarda REST_REQUEST henüz tanımlanmamış olabilir, URI kontrolü de yapalım
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        $is_api_path = (strpos($uri, '/wp-json/') !== false || strpos($uri, 'rest_route=') !== false);

        if ((!$is_rest && !$is_api_path) || !in_array('hizli_kasa', (array) $user->roles)) {
            return $allcaps;
        }

        // Sadece belirli WooCommerce sipariş yetkilerini hedefle
        $requested_cap = $args[0] ?? '';
        $allowed_caps = [
            'edit_shop_orders', 
            'publish_shop_orders', 
            'edit_others_shop_orders', 
            'read_private_shop_orders',
            'edit_published_shop_orders',
            'edit_private_shop_orders',
            'edit_shop_order',
            'publish_shop_order',
            'read_shop_order',
            'manage_woocommerce', // Bazı versiyonlarda controller seviyesinde gerekebilir
            'assign_shop_order_terms'
        ];

        if (in_array($requested_cap, $allowed_caps)) {
            // EK GÜVENLİK: Sadece WooCommerce sipariş API yolu üzerindeysek bu yetkiyi havada oluştur
            // /wc/v3/orders veya /wc/v2/orders veya query param olarak rest_route=/wc/v3/orders
            if (stripos($uri, 'wc/v') !== false && stripos($uri, 'orders') !== false) {
                $allcaps[$requested_cap] = true;
            }
        }

        return $allcaps;
    }
}
