<?php
/**
 * Hızlı Kasa - Stok Uyuşmazlık Bildirim Yöneticisi
 *
 * Arka planda uyuşmazlık kontrolü ve WP Cron yönetimi.
 *
 * @package HizliKasa
 */

if (!defined('ABSPATH')) exit;

class Hizli_Kasa_Mismatch_Notifier {

    /**
     * Başlatıcı
     */
    public static function init() {
        add_action('hizli_kasa_mismatch_check_event', [self::class, 'run_check']);
        add_filter('cron_schedules', [self::class, 'add_cron_schedules']);
        add_action('admin_init', [self::class, 'maybe_schedule_event']);
    }

    /**
     * Özel Cron Aralıkları Ekle
     */
    public static function add_cron_schedules($schedules) {
        $schedules['hk_hourly'] = [
            'interval' => 3600,
            'display'  => 'Hızlı Kasa: Saatte Bir'
        ];
        $schedules['hk_6hours'] = [
            'interval' => 21600,
            'display'  => 'Hızlı Kasa: 6 Saatte Bir'
        ];
        $schedules['hk_twice_daily'] = [
            'interval' => 43200,
            'display'  => 'Hızlı Kasa: Günde 2 Kez'
        ];
        return $schedules;
    }

    /**
     * Cron Görevini Planla
     */
    public static function maybe_schedule_event() {
        $enabled = get_option('hizli_kasa_mismatch_check_enabled', '1');
        $interval = get_option('hizli_kasa_mismatch_interval', 'hk_hourly');

        if ($enabled === '1') {
            if (!wp_next_scheduled('hizli_kasa_mismatch_check_event')) {
                wp_schedule_event(time(), $interval, 'hizli_kasa_mismatch_check_event');
            } else {
                // Eğer aralık değişmişse güncelle
                $current_schedule = wp_get_schedule('hizli_kasa_mismatch_check_event');
                if ($current_schedule !== $interval) {
                    wp_clear_scheduled_hook('hizli_kasa_mismatch_check_event');
                    wp_schedule_event(time(), $interval, 'hizli_kasa_mismatch_check_event');
                }
            }
        } else {
            wp_clear_scheduled_hook('hizli_kasa_mismatch_check_event');
        }
    }

    /**
     * Kontrolü Çalıştır
     */
    public static function run_check() {
        global $wpdb;
        
        $stok_table = $wpdb->prefix . 'hizli_kasa_stok_konumlari';
        
        // Optimize edilmiş sorgu: Sadece uyuşmazlık var mı yok mu?
        $mismatch_exists = $wpdb->get_var("
            SELECT 1 
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm_stock ON (p.ID = pm_stock.post_id AND pm_stock.meta_key = '_stock')
            LEFT JOIN $stok_table sk ON (p.ID = sk.variation_id OR (p.post_type = 'product' AND p.ID = sk.product_id AND sk.variation_id = 0))
            WHERE p.post_type IN ('product', 'product_variation') AND p.post_status = 'publish'
            GROUP BY p.ID
            HAVING SUM(sk.quantity) != CAST(pm_stock.meta_value AS DECIMAL(15,4))
            LIMIT 1
        ");

        update_option('hizli_kasa_mismatch_found', $mismatch_exists ? '1' : '0');
        update_option('hizli_kasa_mismatch_last_check', current_time('mysql'));
        
        return (bool)$mismatch_exists;
    }

    /**
     * Uyuşmazlık Durumunu Sıfırla (Bir sonraki yüklemede tekrar bakılması için)
     */
    public static function reset_status() {
        delete_option('hizli_kasa_mismatch_found');
    }
}
