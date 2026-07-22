<?php
if (!defined('ABSPATH')) {
    exit;
}

class Hizli_Kasa_Admin_Settings_Register {
    public static function init() {
        add_action('admin_init', [self::class, 'register']);
    }

    public static function register()
    {
    register_setting('hizli_kasa_ayar_grubu', 'hizli_kasa_siparis_durumu');
    register_setting('hizli_kasa_ayar_grubu', 'hizli_kasa_pos_page_id', [
        'type' => 'integer',
        'sanitize_callback' => 'intval'
    ]);
    register_setting('hizli_kasa_ayar_grubu', 'hizli_kasa_yetkili_roller');
    register_setting('hizli_kasa_ayar_grubu', 'hizli_kasa_fallback_sku_to_id', [
        'sanitize_callback' => fn($val) => $val ? '1' : '0'
    ]);
    register_setting('hizli_kasa_ayar_grubu', 'hizli_kasa_iskonto_telefon_esigi', [
        'type' => 'integer',
        'default' => 2000,
        'sanitize_callback' => 'intval'
    ]);
    register_setting('hizli_kasa_ayar_grubu', 'hizli_kasa_yuvarlama_aktif', [
        'sanitize_callback' => fn($val) => $val ? '1' : '0'
    ]);
    register_setting('hizli_kasa_ayar_grubu', 'hizli_kasa_yuvarlama_modu');
    register_setting('hizli_kasa_ayar_grubu', 'hizli_kasa_varsayilan_online_depo');
    register_setting('hizli_kasa_ayar_grubu', 'hizli_kasa_kritik_stok_esigi', [
        'type' => 'integer',
        'default' => 5,
        'sanitize_callback' => 'intval'
    ]);
    register_setting('hizli_kasa_ayar_grubu', 'hizli_kasa_toplam_kasa', [
        'type' => 'integer',
        'default' => 3,
        'sanitize_callback' => 'intval'
    ]);
    register_setting('hizli_kasa_ayar_grubu', 'hizli_kasa_edit_order_limit', [
        'type' => 'integer',
        'default' => 5,
        'sanitize_callback' => 'intval'
    ]);
    register_setting('hizli_kasa_ayar_grubu', 'hizli_kasa_siparis_duzenle_aktif', [
        'sanitize_callback' => fn($val) => $val ? '1' : '0'
    ]);
    register_setting('hizli_kasa_ayar_grubu', 'hizli_kasa_siparis_duzenle_kapsam');
    register_setting('hizli_kasa_ayar_grubu', 'hizli_kasa_gun_sonu_aktif', [
        'sanitize_callback' => fn($val) => $val ? '1' : '0'
    ]);
    register_setting('hizli_kasa_ayar_grubu', 'hizli_kasa_genel_rapor_aktif', [
        'sanitize_callback' => fn($val) => $val ? '1' : '0'
    ]);
    register_setting('hizli_kasa_ayar_grubu', 'hizli_kasa_anlik_kasa_kapsam', [
        'type' => 'string',
        'default' => 'secili'
    ]);
    register_setting('hizli_kasa_cache_grubu', 'hizli_kasa_cache_aktif', [
        'sanitize_callback' => fn($val) => $val ? '1' : '0'
    ]);
    register_setting('hizli_kasa_cache_grubu', 'hizli_kasa_search_cache_ttl', [
        'type' => 'integer',
        'default' => 5,
        'sanitize_callback' => 'intval'
    ]);
    register_setting('hizli_kasa_cache_grubu', 'hizli_kasa_reports_cache_ttl', [
        'type' => 'integer',
        'default' => 15,
        'sanitize_callback' => 'intval'
    ]);
    register_setting('hizli_kasa_cache_grubu', 'hizli_kasa_depo_cache_ttl', [
        'type' => 'integer',
        'default' => 24,
        'sanitize_callback' => 'intval'
    ]);
    register_setting('hizli_kasa_cache_grubu', 'hizli_kasa_user_perms_cache_ttl', [
        'type' => 'integer',
        'default' => 12,
        'sanitize_callback' => 'intval'
    ]);
    // Bildirim Ayarları (Ayrı grup - Resetlenmeyi önlemek için)
    register_setting('hizli_kasa_bildirim_grubu', 'hizli_kasa_mismatch_check_enabled');
    register_setting('hizli_kasa_bildirim_grubu', 'hizli_kasa_mismatch_interval');
    register_setting('hizli_kasa_bildirim_grubu', 'hizli_kasa_dismiss_hours');
    // Debug log ayarı (Sistem Araçları sekmesi)
    register_setting('hizli_kasa_araclar_grubu', 'hizli_kasa_debug_log_aktif', [
        'sanitize_callback' => fn($val) => $val ? '1' : '0'
    ]);

    // Otomatik SKU Ayarları
    register_setting('hizli_kasa_auto_sku_grubu', 'hizli_kasa_auto_sku_aktif', [
        'sanitize_callback' => fn($val) => $val ? '1' : '0'
    ]);
    register_setting('hizli_kasa_auto_sku_grubu', 'hizli_kasa_auto_sku_prefix', [
        'type' => 'string',
        'default' => 'AVD-',
        'sanitize_callback' => 'sanitize_text_field'
    ]);
    register_setting('hizli_kasa_auto_sku_grubu', 'hizli_kasa_auto_sku_cron_aktif', [
        'sanitize_callback' => fn($val) => $val ? '1' : '0'
    ]);
    register_setting('hizli_kasa_auto_sku_grubu', 'hizli_kasa_auto_sku_cron_seconds', [
        'type' => 'integer',
        'default' => 3600,
        'sanitize_callback' => 'intval'
    ]);
    register_setting('hizli_kasa_auto_sku_grubu', 'hizli_kasa_auto_sku_tipler', [
        'type' => 'array',
        'default' => ['simple', 'product_variation']
    ]);
    register_setting('hizli_kasa_auto_sku_grubu', 'hizli_kasa_auto_sku_tetikleyiciler', [
        'type' => 'array',
        'default' => ['save', 'import']
    ]);
}
}
