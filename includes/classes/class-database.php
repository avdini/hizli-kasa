<?php
/**
 * Hızlı Kasa - Veritabanı Yönetimi
 *
 * Özel tabloların oluşturulması ve güncellenmesi.
 *
 * @package HizliKasa
 */

if (!defined('ABSPATH'))
    exit;

class Hizli_Kasa_Database {

    /**
     * Tablo isimlerini döner (prefix dahil).
     */
    public static function get_tables() {
        global $wpdb;
        return [
            'depolar'         => $wpdb->prefix . 'hizli_kasa_depolar',
            'stok_konumlari'  => $wpdb->prefix . 'hizli_kasa_stok_konumlari',
            'stok_hareketleri' => $wpdb->prefix . 'hizli_kasa_stok_hareketleri',
        ];
    }

    /**
     * Veritabanı tablolarını oluşturur veya günceller.
     */
    public static function init() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        $tables = self::get_tables();

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        // 1. Depolar Tablosu
        $sql1 = "CREATE TABLE {$tables['depolar']} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            address text DEFAULT '',
            description text DEFAULT '',
            priority int(11) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) $charset_collate;";
        dbDelta($sql1);

        // 2. Stok Konumları Tablosu
        $sql2 = "CREATE TABLE {$tables['stok_konumlari']} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            product_id bigint(20) NOT NULL,
            variation_id bigint(20) DEFAULT 0,
            location_id bigint(20) NOT NULL,
            quantity float NOT NULL DEFAULT 0,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY product_id (product_id),
            KEY variation_id (variation_id),
            KEY location_id (location_id)
        ) $charset_collate;";
        dbDelta($sql2);

        // 3. Stok Hareketleri (Log) Tablosu
        $sql3 = "CREATE TABLE {$tables['stok_hareketleri']} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            product_id bigint(20) NOT NULL,
            variation_id bigint(20) DEFAULT 0,
            location_id bigint(20) NOT NULL,
            user_id bigint(20) NOT NULL,
            old_qty float NOT NULL,
            new_qty float NOT NULL,
            change_amount float NOT NULL,
            reason varchar(255) DEFAULT '',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY product_id (product_id),
            KEY location_id (location_id)
        ) $charset_collate;";
        dbDelta($sql3);
    }

    /**
     * Tüm tabloları ve ayarları siler (Reset işlemi için).
     */
    public static function drop_everything() {
        global $wpdb;
        $tables = self::get_tables();

        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS $table");
        }

        // Ayarları temizle
        delete_option('hizli_kasa_varsayilan_online_depo');
        delete_option('hizli_kasa_depo_oncelikleri');
        
        // Eklenti tamamen devre dışı bırakıldığında silinecek diğer ayarlar
        // delete_option('hizli_kasa_siparis_durumu'); // Bunu silmeyelim, ana eklenti ayarı
    }
}
