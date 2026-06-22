<?php
if (!defined('ABSPATH')) exit;

class Hizli_Kasa_Admin_Menu {
    public static function init() {
        add_action('admin_menu', [__CLASS__, 'register']);
    }

    public static function register()
    {
    // Ana MenÃ¼
    add_menu_page(
        'HÄ±zlÄ± Kasa',
        'HÄ±zlÄ± Kasa',
        'manage_options',
        'hizli-kasa',
        [Hizli_Kasa_Admin_Settings_Page::class, 'render'],
        'dashicons-store',
        30
    );

    // Alt MenÃ¼ler
    // Not: Ä°lk alt menÃ¼ ana menÃ¼ ile aynÄ± slug'a sahip olmalÄ± ki varsayÄ±lan olarak o gelsin.
    add_submenu_page(
        'hizli-kasa',
        'Stok YÃ¶netimi',
        'Stok YÃ¶netimi',
        'manage_options',
        'hizli-kasa', // Landing Page
        [Hizli_Kasa_Admin_Settings_Page::class, 'render']
    );

    add_submenu_page(
        'hizli-kasa',
        'Depo YÃ¶netimi',
        'Depo YÃ¶netimi',
        'manage_options',
        'hizli-kasa&tab=depolar',
        [Hizli_Kasa_Admin_Settings_Page::class, 'render']
    );

    add_submenu_page(
        'hizli-kasa',
        'EÅŸleÅŸmeyen ÃœrÃ¼nler',
        'EÅŸleÅŸmeyen ÃœrÃ¼nler',
        'manage_options',
        'hizli-kasa&tab=unmatched',
        [Hizli_Kasa_Admin_Settings_Page::class, 'render']
    );

    add_submenu_page(
        'hizli-kasa',
        'Bildirimler',
        'Bildirimler',
        'manage_options',
        'hizli-kasa&tab=bildirimler',
        [Hizli_Kasa_Admin_Settings_Page::class, 'render']
    );

    add_submenu_page(
        'hizli-kasa',
        'Genel Ayarlar',
        'Genel Ayarlar',
        'manage_options',
        'hizli-kasa&tab=genel',
        [Hizli_Kasa_Admin_Settings_Page::class, 'render']
    );

    add_submenu_page(
        'hizli-kasa',
        'Ã–nbellek (Cache)',
        'Ã–nbellek (Cache)',
        'manage_options',
        'hizli-kasa&tab=onbellek',
        [Hizli_Kasa_Admin_Settings_Page::class, 'render']
    );

    add_submenu_page(
        'hizli-kasa',
        'Sistem AraÃ§larÄ±',
        'Sistem AraÃ§larÄ±',
        'manage_options',
        'hizli-kasa&tab=araclar',
        [Hizli_Kasa_Admin_Settings_Page::class, 'render']
    );

    add_submenu_page(
        'hizli-kasa',
        'Terminali BaÅŸlat',
        '<span style="color:#f58220; font-weight:bold;">POS Terminali â†—</span>',
        'manage_options',
        'hizli-kasa-terminal-link',
        function() {
            $url = home_url('/hizli-kasa/terminal/');
            echo "<script>window.open('$url', '_blank'); location.href='admin.php?page=hizli-kasa';</script>";
        }
    );
}
}
