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
            'unmatched_items'  => $wpdb->prefix . 'hizli_kasa_unmatched_items',
            'masraflar'        => $wpdb->prefix . 'hizli_kasa_masraflar',
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
            address text,
            description text,
            priority int(11) DEFAULT 0,
            created_at datetime,
            PRIMARY KEY  (id)
        ) $charset_collate;";
        $res1 = dbDelta($sql1);
        if ($wpdb->last_error) {
            error_log('Hızlı Kasa DB Delta Hatası (Depolar): ' . $wpdb->last_error);
        } else {
            error_log('Hızlı Kasa DB Delta (Depolar): ' . print_r($res1, true));
        }

        // 2. Stok Konumları Tablosu
        $sql2 = "CREATE TABLE {$tables['stok_konumlari']} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            product_id bigint(20) NOT NULL,
            variation_id bigint(20) DEFAULT 0,
            location_id bigint(20) NOT NULL,
            quantity decimal(15,4) DEFAULT 0.0000,
            updated_at datetime,
            PRIMARY KEY  (id),
            KEY product_id (product_id),
            KEY variation_id (variation_id),
            KEY location_id (location_id)
        ) $charset_collate;";
        $res2 = dbDelta($sql2);
        if ($wpdb->last_error) {
            error_log('Hızlı Kasa DB Delta Hatası (Konumlar): ' . $wpdb->last_error);
        }

        // 3. Stok Hareketleri (Log) Tablosu
        $sql3 = "CREATE TABLE {$tables['stok_hareketleri']} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            product_id bigint(20) NOT NULL,
            variation_id bigint(20) DEFAULT 0,
            location_id bigint(20) NOT NULL,
            user_id bigint(20) NOT NULL,
            old_qty float NOT NULL,
            new_qty float NOT NULL,
            amount decimal(15,4) NOT NULL,
            reason text,
            created_at datetime,
            PRIMARY KEY  (id),
            KEY product_id (product_id),
            KEY location_id (location_id)
        ) $charset_collate;";
        $res3 = dbDelta($sql3);
        if ($wpdb->last_error) {
            error_log('Hızlı Kasa DB Delta Hatası (Hareketler): ' . $wpdb->last_error);
        }

        // 4. Eşleşmeyen Ürünler Tablosu
        $sql4 = "CREATE TABLE {$tables['unmatched_items']} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            warehouse_name varchar(255) NOT NULL,
            product_name varchar(255) DEFAULT NULL,
            sku varchar(100) DEFAULT NULL,
            stock_qty decimal(15,4) DEFAULT '0.0000',
            error_msg text,
            created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";
        $res4 = dbDelta($sql4);
        if ($wpdb->last_error) {
            error_log('Hızlı Kasa DB Delta Hatası (Eşleşmeyenler): ' . $wpdb->last_error);
        }

        // 5. Masraflar Tablosu
        $sql5 = "CREATE TABLE {$tables['masraflar']} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            category varchar(100) NOT NULL,
            amount decimal(15,4) NOT NULL,
            payment_method varchar(50) DEFAULT 'nakit',
            description text,
            user_id bigint(20) NOT NULL,
            location_id bigint(20) DEFAULT 0,
            kasa_no varchar(50) DEFAULT NULL,
            created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            PRIMARY KEY  (id),
            KEY category (category),
            KEY location_id (location_id),
            KEY created_at (created_at)
        ) $charset_collate;";
        $res5 = dbDelta($sql5);
        if ($wpdb->last_error) {
            error_log('Hızlı Kasa DB Delta Hatası (Masraflar): ' . $wpdb->last_error);
        }
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
