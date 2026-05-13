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
        $schedules['hk_5mins'] = [
            'interval' => 300,
            'display'  => 'Hızlı Kasa: 5 Dakikada Bir'
        ];
        $schedules['hk_15mins'] = [
            'interval' => 900,
            'display'  => 'Hızlı Kasa: 15 Dakikada Bir'
        ];
        $schedules['hk_30mins'] = [
            'interval' => 1800,
            'display'  => 'Hızlı Kasa: 30 Dakikada Bir'
        ];
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
        
        // Daha güvenilir ve test edilmiş alt sorgu mantığı
        $query = "
            SELECT item_id, total_wh, wc_stock, post_title, post_type
            FROM (
                SELECT 
                    p.ID as item_id,
                    p.post_title,
                    p.post_type,
                    (SELECT COALESCE(SUM(sk.quantity), 0) 
                     FROM $stok_table sk 
                     WHERE (p.post_type = 'product_variation' AND sk.variation_id = p.ID) 
                        OR (p.post_type = 'product' AND sk.product_id = p.ID AND sk.variation_id = 0)
                    ) as total_wh,
                    (SELECT COALESCE(MIN(sk.quantity), 0) 
                     FROM $stok_table sk 
                     WHERE (p.post_type = 'product_variation' AND sk.variation_id = p.ID) 
                        OR (p.post_type = 'product' AND sk.product_id = p.ID AND sk.variation_id = 0)
                    ) as min_wh,
                    (SELECT COALESCE(meta_value, 0) 
                     FROM {$wpdb->postmeta} 
                     WHERE post_id = p.ID AND meta_key = '_stock' 
                     LIMIT 1
                    ) as wc_stock
                FROM {$wpdb->posts} p
                WHERE (p.post_type = 'product_variation' OR (p.post_type = 'product' AND NOT EXISTS (SELECT 1 FROM {$wpdb->posts} as p_child WHERE p_child.post_parent = p.ID AND p_child.post_type = 'product_variation'))) 
                  AND p.post_status IN ('publish', 'private')
            ) as stock_summary
            WHERE ROUND(CAST(total_wh AS DECIMAL(15,4)), 4) != ROUND(CAST(wc_stock AS DECIMAL(15,4)), 4) OR min_wh < 0
            LIMIT 5";
            
        $mismatches = $wpdb->get_results($query);
        $mismatch_exists = !empty($mismatches);

        if ($mismatch_exists) {
            foreach ($mismatches as $m) {
                hizli_kasa_admin_log("Uyuşmazlık Bulundu: [ID: {$m->item_id}] {$m->post_title} ({$m->post_type}) - Depo: {$m->total_wh}, Site: {$m->wc_stock}");
            }
        } else {
            hizli_kasa_admin_log("Uyuşmazlık Kontrolü: Mismatch bulunamadı.");
        }

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
