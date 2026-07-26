<?php
/**
 * Hızlı Kasa - Merkezi Log Yönetim Sınıfı
 *
 * Eklenti içi log işlemlerinin veritabanı (wp_hizli_kasa_logs) üzerinde
 * modüler, ilişkili (request_id) ve güvenli şekilde saklanmasını sağlar.
 *
 * @package HizliKasa
 */

if (!defined('ABSPATH')) {
    exit;
}

class Hizli_Kasa_Logger {

    /**
     * Mevcut HTTP/CLI isteğine ait benzersiz Correlation ID.
     * @var string|null
     */
    private static $current_request_id = null;

    /**
     * Geçerli log kanalları.
     * @var array
     */
    private static $allowed_channels = ['pos', 'stock', 'sku', 'payment', 'sync', 'system'];

    /**
     * Mevcut request_id değerini döner (yoksa üretir).
     *
     * @return string
     */
    public static function get_request_id() {
        if (self::$current_request_id === null) {
            self::$current_request_id = 'req_' . substr(md5(uniqid(mt_rand(), true)), 0, 12);
        }
        return self::$current_request_id;
    }

    /**
     * Veritabanına yeni bir log kaydı ekler.
     *
     * @param string      $message     İnsan tarafından okunabilir mesaj.
     * @param string      $channel     Log kanalı ('pos', 'stock', 'sku', 'payment', 'sync', 'system').
     * @param string      $level       Log seviyesi ('info', 'warning', 'error', 'debug').
     * @param array|mixed $context     Geliştirici için detaylı teknik veri/payload.
     * @param string|null $object_type Nesne türü ('order', 'product', 'variation', 'shift' vb.).
     * @param int         $object_id   Nesne ID.
     * @param int|null    $user_id     İşlemi yapan kullanıcı ID (null ise get_current_user_id kullanır).
     * @return bool|int                Başarılı ise eklenen log ID, değilse false.
     */
    public static function log($message, $channel = 'system', $level = 'info', $context = [], $object_type = null, $object_id = 0, $user_id = null) {
        global $wpdb;

        $table_name = Hizli_Kasa_Database::get_tables()['logs'];

        if (!in_array($channel, self::$allowed_channels, true)) {
            $channel = 'system';
        }

        if ($user_id === null) {
            $user_id = function_exists('get_current_user_id') ? get_current_user_id() : 0;
        }

        $context_json = null;
        if (!empty($context)) {
            $context_json = wp_json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $data = [
            'request_id'  => self::get_request_id(),
            'channel'     => sanitize_key($channel),
            'level'       => sanitize_key($level),
            'message'     => sanitize_text_field($message),
            'user_id'     => absint($user_id),
            'object_type' => $object_type ? sanitize_key($object_type) : null,
            'object_id'   => absint($object_id),
            'context'     => $context_json,
            'created_at'  => current_time('mysql'),
        ];

        $format = ['%s', '%s', '%s', '%s', '%d', '%s', '%d', '%s', '%s'];

        $inserted = $wpdb->insert($table_name, $data, $format);

        if ($level === 'error') {
            error_log(sprintf('HK ERROR [%s] [%s]: %s', $channel, self::get_request_id(), $message));
        }

        return $inserted ? $wpdb->insert_id : false;
    }

    /**
     * Kasa (POS) işlemleri için log ekler.
     */
    public static function pos($message, $context = [], $level = 'info', $object_type = 'order', $object_id = 0) {
        return self::log($message, 'pos', $level, $context, $object_type, $object_id);
    }

    /**
     * Stok ve depo hareketleri için log ekler.
     */
    public static function stock($message, $context = [], $level = 'info', $object_type = 'product', $object_id = 0) {
        return self::log($message, 'stock', $level, $context, $object_type, $object_id);
    }

    /**
     * SKU ve Barkod işlemleri için log ekler.
     */
    public static function sku($message, $context = [], $level = 'info', $object_type = 'product', $object_id = 0) {
        return self::log($message, 'sku', $level, $context, $object_type, $object_id);
    }

    /**
     * Tahsilat ve Ödeme işlemleri için log ekler.
     */
    public static function payment($message, $context = [], $level = 'info', $object_type = 'order', $object_id = 0) {
        return self::log($message, 'payment', $level, $context, $object_type, $object_id);
    }

    /**
     * Senkronizasyon ve dış sistem entegrasyonları için log ekler.
     */
    public static function sync($message, $context = [], $level = 'info', $object_type = null, $object_id = 0) {
        return self::log($message, 'sync', $level, $context, $object_type, $object_id);
    }

    /**
     * Sistem ve ayar değişiklikleri için log ekler.
     */
    public static function system($message, $context = [], $level = 'info', $object_type = null, $object_id = 0) {
        return self::log($message, 'system', $level, $context, $object_type, $object_id);
    }

    /**
     * Kritik hatalar için kısayol metod (Hem DB'ye hem WP debug.log'a yazar).
     */
    public static function error($message, $context = [], $channel = 'system', $object_type = null, $object_id = 0) {
        return self::log($message, $channel, 'error', $context, $object_type, $object_id);
    }

    /**
     * Eski logları veritabanından temizler.
     *
     * @param int $days     Kaç günden eski logların silineceği (varsayılan: 14 gün).
     * @param int $max_rows Maksimum tutulacak satır sayısı (varsayılan: 50.000).
     * @return int          Silinen satır sayısı.
     */
    public static function purge_old_logs($days = 14, $max_rows = 50000) {
        global $wpdb;

        $table_name = Hizli_Kasa_Database::get_tables()['logs'];
        $deleted_count = 0;

        $days = max(1, absint($days));
        $date_threshold = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        $sql_date = $wpdb->prepare("DELETE FROM {$table_name} WHERE created_at < %s", $date_threshold);
        $deleted_count += (int) $wpdb->query($sql_date);

        $total_rows = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
        if ($total_rows > $max_rows) {
            $excess = $total_rows - $max_rows;
            $sql_excess = $wpdb->prepare(
                "DELETE FROM {$table_name} ORDER BY id ASC LIMIT %d",
                $excess
            );
            $deleted_count += (int) $wpdb->query($sql_excess);
        }

        return $deleted_count;
    }
}
