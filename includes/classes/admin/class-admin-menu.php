<?php
if (!defined('ABSPATH')) exit;

class Hizli_Kasa_Admin_Menu {
    public static function init() {
        add_action('admin_menu', [__CLASS__, 'register']);
    }

    public static function register()
    {
    // Ana Menü
    add_menu_page(
        'Hızlı Kasa',
        'Hızlı Kasa',
        'manage_options',
        'hizli-kasa',
        [Hizli_Kasa_Admin_Settings_Page::class, 'render'],
        'dashicons-store',
        30
    );

    // Alt Menüler
    // Not: İlk alt menü ana menü ile aynı slug'a sahip olmalı ki varsayılan olarak o gelsin.
    add_submenu_page(
        'hizli-kasa',
        'Stok Yönetimi',
        'Stok Yönetimi',
        'manage_options',
        'hizli-kasa', // Landing Page
        [Hizli_Kasa_Admin_Settings_Page::class, 'render']
    );

    add_submenu_page(
        'hizli-kasa',
        'Depo Yönetimi',
        'Depo Yönetimi',
        'manage_options',
        'hizli-kasa&tab=depolar',
        [Hizli_Kasa_Admin_Settings_Page::class, 'render']
    );

    add_submenu_page(
        'hizli-kasa',
        'Eşleşmeyen Ürünler',
        'Eşleşmeyen Ürünler',
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
        'Önbellek (Cache)',
        'Önbellek (Cache)',
        'manage_options',
        'hizli-kasa&tab=onbellek',
        [Hizli_Kasa_Admin_Settings_Page::class, 'render']
    );

    add_submenu_page(
        'hizli-kasa',
        'Sistem Araçları',
        'Sistem Araçları',
        'manage_options',
        'hizli-kasa&tab=araclar',
        [Hizli_Kasa_Admin_Settings_Page::class, 'render']
    );

    add_submenu_page(
        'hizli-kasa',
        'Terminali Başlat',
        '<span style="color:#f58220; font-weight:bold;">POS Terminali ↗</span>',
        'manage_options',
        'hizli-kasa-terminal-link',
        function() {
            $url = home_url('/hizli-kasa/terminal/');
            echo "<script>window.open('$url', '_blank'); location.href='admin.php?page=hizli-kasa';</script>";
        }
    );
}
}
