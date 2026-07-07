<?php
if (!defined('ABSPATH')) {
    exit;
}

class Hizli_Kasa_Admin_Menu {
    public static function init() {
        add_action('admin_menu', [self::class, 'register']);
        add_action('admin_enqueue_scripts', [self::class, 'enqueue_assets']);
    }

    public static function enqueue_assets($hook) {
        wp_enqueue_style('hizli-kasa-admin-hub', HIZLI_KASA_URL . 'assets/css/admin-hub.css', [], HIZLI_KASA_VERSION);
    }

    public static function register()
    {
        global $wpdb;
        $unmatched_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}hizli_kasa_unmatched_items");
        $unmatched_badge = $unmatched_count > 0 ? " <span class='update-plugins count-{$unmatched_count}' style='background-color:#d63638; color:#fff; font-size:9px; padding:1px 6px; border-radius:10px; font-weight:bold; vertical-align:middle; margin-left:4px;'>{$unmatched_count}</span>" : '';

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
            'Kontrol Paneli',
            '<span class="dashicons dashicons-grid-view"></span> Kontrol Paneli',
            'manage_options',
            'hizli-kasa', // Landing Page
            [Hizli_Kasa_Admin_Settings_Page::class, 'render']
        );

        add_submenu_page(
            'hizli-kasa',
            'Stok Yönetimi',
            '<span class="dashicons dashicons-database"></span> Stok Yönetimi',
            'manage_options',
            'hizli-kasa&tab=stok',
            [Hizli_Kasa_Admin_Settings_Page::class, 'render']
        );

        add_submenu_page(
            'hizli-kasa',
            'Depo Yönetimi',
            '<span class="dashicons dashicons-building"></span> Depo Yönetimi',
            'manage_options',
            'hizli-kasa&tab=depolar',
            [Hizli_Kasa_Admin_Settings_Page::class, 'render']
        );

        add_submenu_page(
            'hizli-kasa',
            'Paylaşılan Kataloglar',
            '<span class="dashicons dashicons-share"></span> Paylaşılan Kataloglar',
            'manage_options',
            'hizli-kasa&tab=kataloglar',
            [Hizli_Kasa_Admin_Settings_Page::class, 'render']
        );

        add_submenu_page(
            'hizli-kasa',
            'Eşleşmeyen Ürünler',
            '<span class="dashicons dashicons-warning"></span> Eşleşmeyen Ürünler' . $unmatched_badge,
            'manage_options',
            'hizli-kasa&tab=unmatched',
            [Hizli_Kasa_Admin_Settings_Page::class, 'render']
        );

        add_submenu_page(
            'hizli-kasa',
            'Bildirimler',
            '<span class="dashicons dashicons-bell"></span> Bildirimler',
            'manage_options',
            'hizli-kasa&tab=bildirimler',
            [Hizli_Kasa_Admin_Settings_Page::class, 'render']
        );

        add_submenu_page(
            'hizli-kasa',
            'Genel Ayarlar',
            '<span class="dashicons dashicons-admin-generic"></span> Genel Ayarlar',
            'manage_options',
            'hizli-kasa&tab=genel',
            [Hizli_Kasa_Admin_Settings_Page::class, 'render']
        );

        add_submenu_page(
            'hizli-kasa',
            'Önbellek (Cache)',
            '<span class="dashicons dashicons-update"></span> Önbellek (Cache)',
            'manage_options',
            'hizli-kasa&tab=onbellek',
            [Hizli_Kasa_Admin_Settings_Page::class, 'render']
        );

        add_submenu_page(
            'hizli-kasa',
            'Otomatik SKU',
            '<span class="dashicons dashicons-tag"></span> Otomatik SKU',
            'manage_options',
            'hizli-kasa&tab=oto-sku',
            [Hizli_Kasa_Admin_Settings_Page::class, 'render']
        );

        add_submenu_page(
            'hizli-kasa',
            'Sistem Araçları',
            '<span class="dashicons dashicons-admin-tools"></span> Sistem Araçları',
            'manage_options',
            'hizli-kasa&tab=araclar',
            [Hizli_Kasa_Admin_Settings_Page::class, 'render']
        );

        add_submenu_page(
            'hizli-kasa',
            'Terminali Başlat',
            '<span class="dashicons dashicons-external" style="color:#f58220; margin-right:4px;"></span><span style="color:#f58220; font-weight:bold;">POS Terminali ↗</span>',
            'manage_options',
            'hizli-kasa-terminal-link',
            function() {
                $url = home_url('/hizli-kasa/terminal/');
                echo "<script>window.open('$url', '_blank'); location.href='admin.php?page=hizli-kasa';</script>";
            }
        );
    }
}
