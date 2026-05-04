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
            'depolar'          => $wpdb->prefix . 'hizli_kasa_depolar',
            'stok_konumlari'   => $wpdb->prefix . 'hizli_kasa_stok_konumlari',
            'stok_hareketleri' => $wpdb->prefix . 'hizli_kasa_stok_hareketleri',
            'unmatched_items'  => $wpdb->prefix . 'hizli_kasa_unmatched_items',
            'masraflar'        => $wpdb->prefix . 'hizli_kasa_masraflar',
            'order_edits'      => $wpdb->prefix . 'hizli_kasa_order_edits',
            'sevkler'          => $wpdb->prefix . 'hizli_kasa_sevkler',
            'sevk_kalemleri'   => $wpdb->prefix . 'hizli_kasa_sevk_kalemleri',
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

        $sql1 = "CREATE TABLE {$tables['depolar']} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            address text,
            description text,
            priority int(11) DEFAULT 0,
            created_at datetime,
            PRIMARY KEY  (id)
        ) $charset_collate;";
        dbDelta($sql1);
        if ($wpdb->last_error) {
            error_log('Hızlı Kasa DB Delta Hatası (Depolar): ' . $wpdb->last_error);
        }

        $sql2 = "CREATE TABLE {$tables['stok_konumlari']} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            product_id bigint(20) NOT NULL,
            variation_id bigint(20) DEFAULT 0,
            location_id bigint(20) NOT NULL,
            quantity decimal(15,4) DEFAULT 0.0000,
            reserved decimal(15,4) DEFAULT 0.0000,
            updated_at datetime,
            PRIMARY KEY  (id),
            KEY product_id (product_id),
            KEY variation_id (variation_id),
            KEY location_id (location_id)
        ) $charset_collate;";
        dbDelta($sql2);
        if ($wpdb->last_error) {
            error_log('Hızlı Kasa DB Delta Hatası (Konumlar): ' . $wpdb->last_error);
        }

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
        dbDelta($sql3);
        if ($wpdb->last_error) {
            error_log('Hızlı Kasa DB Delta Hatası (Hareketler): ' . $wpdb->last_error);
        }

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
        dbDelta($sql4);
        if ($wpdb->last_error) {
            error_log('Hızlı Kasa DB Delta Hatası (Eşleşmeyenler): ' . $wpdb->last_error);
        }

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
        dbDelta($sql5);
        if ($wpdb->last_error) {
            error_log('Hızlı Kasa DB Delta Hatası (Masraflar): ' . $wpdb->last_error);
        }

        $sql6 = "CREATE TABLE {$tables['order_edits']} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            order_id bigint(20) NOT NULL,
            kasa_no varchar(50) NOT NULL,
            user_id bigint(20) NOT NULL,
            action_type varchar(50) NOT NULL,
            old_data longtext NOT NULL,
            new_data longtext NOT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY order_id (order_id),
            KEY created_at (created_at)
        ) $charset_collate;";
        dbDelta($sql6);
        if ($wpdb->last_error) {
            error_log('Hızlı Kasa DB Delta Hatası (Düzenleme Logları): ' . $wpdb->last_error);
        }

        $sql7 = "CREATE TABLE {$tables['sevkler']} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            sevk_no varchar(50) NOT NULL,
            kaynak_depo_id bigint(20) NOT NULL,
            hedef_depo_id bigint(20) NOT NULL,
            durum varchar(30) NOT NULL DEFAULT 'taslak',
            olusturan_user_id bigint(20) NOT NULL,
            onaylayan_user_id bigint(20) DEFAULT NULL,
            toplam_cesit int(11) DEFAULT 0,
            toplam_adet decimal(15,4) DEFAULT 0.0000,
            not_gonderici text,
            not_alici text,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY sevk_no (sevk_no),
            KEY kaynak_depo_id (kaynak_depo_id),
            KEY hedef_depo_id (hedef_depo_id),
            KEY durum (durum),
            KEY created_at (created_at)
        ) $charset_collate;";
        dbDelta($sql7);
        if ($wpdb->last_error) {
            error_log('Hızlı Kasa DB Delta Hatası (Sevkler): ' . $wpdb->last_error);
        }

        $sql8 = "CREATE TABLE {$tables['sevk_kalemleri']} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            sevk_id bigint(20) NOT NULL,
            product_id bigint(20) NOT NULL,
            variation_id bigint(20) DEFAULT 0,
            sku varchar(100) NOT NULL,
            urun_adi varchar(255) NOT NULL,
            gonderilen_adet decimal(15,4) DEFAULT 0.0000,
            teslim_alinan_adet decimal(15,4) DEFAULT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY sevk_id (sevk_id),
            KEY product_id (product_id),
            KEY variation_id (variation_id),
            KEY sku (sku)
        ) $charset_collate;";
        dbDelta($sql8);
        if ($wpdb->last_error) {
            error_log('Hızlı Kasa DB Delta Hatası (Sevk Kalemleri): ' . $wpdb->last_error);
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

        delete_option('hizli_kasa_varsayilan_online_depo');
        delete_option('hizli_kasa_depo_oncelikleri');
    }
}
