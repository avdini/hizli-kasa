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
        // Rol oluşturma (Eklenti her yüklendiğinde/güncellendiğinde çalışır)
        add_action('init', [__CLASS__, 'ensure_kasa_role']);
        
        // Admin paneli erişim kısıtlaması
        add_action('admin_init', [__CLASS__, 'restrict_admin_access']);
        
        // Admin barı gizle
        add_filter('show_admin_bar', [__CLASS__, 'hide_admin_bar']);
    }

    /**
     * 'hizli_kasa' adında özel bir rol oluşturur.
     * Bu rol sadece POS işlemlerini yapabilir, admin paneline giremez.
     */
    public static function ensure_kasa_role() {
        $role_id = 'hizli_kasa';
        $role_name = 'Hızlı Kasa Kasiyer';
        
        // Rol zaten varsa yetkileri güncelle, yoksa oluştur
        $capabilities = [
            'read'                       => true,
            'view_admin_dashboard'       => true, // API yetkilendirme katmanı için gerekli olabilir
            'edit_shop_orders'           => true,
            'publish_shop_orders'        => true,
            'edit_others_shop_orders'    => true,
            'read_private_shop_orders'   => true,
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
}
