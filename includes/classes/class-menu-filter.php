<?php
/**
 * Hızlı Kasa - Menü Filtreleme
 *
 * Yetkisiz kullanıcılardan menü bağlantısını gizleme
 * ve POS sayfasında admin bar'ı gizleme.
 *
 * @package HizliKasa
 */

if (!defined('ABSPATH'))
    exit;

// Menü Bağlantısını Yetkisiz Kişilerden Gizleme
add_filter('wp_nav_menu_objects', 'hizli_kasa_menuyu_gizle', 10, 2);
function hizli_kasa_menuyu_gizle($items, $args)
{
    $user = wp_get_current_user();
    $yetkili_roller = get_option('hizli_kasa_yetkili_roller', array('administrator', 'shop_manager'));

    $yetkili_mi = false;
    foreach ((array) $user->roles as $role) {
        if (in_array($role, (array) $yetkili_roller)) {
            $yetkili_mi = true;
            break;
        }
    }

    foreach ($items as $key => $item) {
        if ($item->object == 'page') {
            $page = get_post($item->object_id);
            if ($page && has_shortcode($page->post_content, 'hizli_kasa')) {
                if (!$yetkili_mi) {
                    unset($items[$key]);
                } else {
                    // Yeni Sekmede Açmayı Zorla
                    $item->target = '_blank';
                }
            }
        }
    }

    return $items;
}

// POS Sayfasında Admin Barı Gizle
add_action('wp', 'hizli_kasa_admin_bar_gizle');
function hizli_kasa_admin_bar_gizle() {
    if (is_page() && has_shortcode(get_post()->post_content, 'hizli_kasa')) {
        add_filter('show_admin_bar', '__return_false');
        add_filter('body_class', function($classes) {
            $classes[] = 'hizli-kasa-aktif';
            return $classes;
        });
    }
}
