<?php
if (!defined('ABSPATH')) {
    exit;
}

class Hizli_Kasa_Shipment_Print_Builder {
    public static function build(int $shipment_id): array|false {
        global $wpdb;
        $tables = Hizli_Kasa_Database::get_tables();

        $sevk = $wpdb->get_row($wpdb->prepare(
            "SELECT s.*, 
                    dk.name as kaynak_depo_adi, 
                    dh.name as hedef_depo_adi 
             FROM {$tables['sevkler']} s 
             LEFT JOIN {$tables['depolar']} dk ON s.kaynak_depo_id = dk.id 
             LEFT JOIN {$tables['depolar']} dh ON s.hedef_depo_id = dh.id 
             WHERE s.id = %d",
            $shipment_id
        ));

        if (!$sevk) {
            return false;
        }

        $items = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$tables['sevk_kalemleri']} WHERE sevk_id = %d ORDER BY id ASC",
            $shipment_id
        ));

        $toplam_cesit = count($items);
        $toplam_adet  = 0;

        $kalemler = array_map(function($i) use (&$toplam_adet) {
            $qty = (float) $i->gonderilen_adet;
            $toplam_adet += $qty;
            return [
                'id'              => (int) $i->id,
                'product_id'      => (int) $i->product_id,
                'variation_id'    => (int) $i->variation_id,
                'sku'             => $i->sku ?: '-',
                'urun_adi'        => $i->urun_adi,
                'gonderilen_adet' => $qty,
            ];
        }, $items);

        $site_name = get_bloginfo('name') ?: 'Hızlı Kasa';

        return [
            'site_name'    => $site_name,
            'sevk'         => $sevk,
            'kalemler'     => $kalemler,
            'toplam_cesit' => $toplam_cesit,
            'toplam_adet'  => $toplam_adet,
            'tarih'        => current_time('d.m.Y H:i'),
        ];
    }
}
