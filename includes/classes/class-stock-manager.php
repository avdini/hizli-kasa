<?php
/**
 * Hızlı Kasa - Stok Yönetim Motoru
 *
 * Çoklu depo stok mantığı, senkronizasyon ve loglama.
 *
 * @package HizliKasa
 */

if (!defined('ABSPATH'))
    exit;

class Hizli_Kasa_Stock_Manager {

    /**
     * Hookları Başlat
     */
    public static function listen() {
        add_action('woocommerce_order_status_processing', [self::class, 'handle_online_order_stock'], 10, 2);
        add_action('woocommerce_checkout_order_processed', [self::class, 'handle_pos_order_stock'], 10, 3);
    }

    /**
     * POS üzerinden gelen siparişlerde personelin deposundan düşüm yapar.
     */
    public static function handle_pos_order_stock($order_id, $posted_data, $order) {
        $kasiyer_name = $order->get_meta('_hizli_kasa_kasiyer');
        if (!$kasiyer_name) return; // POS siparişi değilse çık

        // Kasiyerin kullanıcı ID'sini bul (veya şu anki kullanıcıyı kullan)
        $user_id = get_current_user_id();
        $depo_id = get_user_meta($user_id, '_hizli_kasa_depo_id', true);

        if (!$depo_id) return; // Depo atanmamışsa çık (normal WC stok düşümü zaten olacak)

        foreach ($order->get_items() as $item) {
            $product_id = $item->get_product_id();
            $variation_id = $item->get_variation_id();
            $qty = $item->get_quantity();

            // SADECE gölge katmandan düş (WC ana stoğu sync_to_wc_stock ile zaten güncellenecek)
            self::update_warehouse_stock($product_id, $variation_id, $depo_id, -$qty, "POS Satışı (#$order_id)");
        }
    }

    /**
     * Online siparişlerde (web) öncelikli stok düşümü yapar.
     */
    public static function handle_online_order_stock($order_id, $order) {
        // Eğer sipariş "Hızlı Kasa" üzerinden gelmişse bu fonksiyonu atla 
        // (Çünkü POS kendi deposundan zaten düşüm yapacak)
        if ($order->get_meta('_hizli_kasa_kasiyer')) return;

        foreach ($order->get_items() as $item) {
            $product_id = $item->get_product_id();
            $variation_id = $item->get_variation_id();
            $qty = $item->get_quantity();

            self::priority_stock_deduction($product_id, $variation_id, $qty);
        }
    }

    /**
     * İlk Kurulum: WC ana stoklarını seçilen depoya kopyalar.
     */
    public static function initial_sync($depo_id) {
        global $wpdb;
        $tables = Hizli_Kasa_Database::get_tables();

        // Mevcut shadow stock verilerini temizle (temiz bir başlangıç için)
        $wpdb->query("TRUNCATE TABLE {$tables['stok_konumlari']}");

        // Tüm ürünleri çek (varyantlar dahil)
        $products = $wpdb->get_results("
            SELECT p.ID, p.post_parent, p.post_type,
                   MAX(CASE WHEN pm.meta_key = '_stock' THEN pm.meta_value END) as stock
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_status = 'publish' 
              AND p.post_type IN ('product', 'product_variation')
              AND pm.meta_key = '_stock'
            GROUP BY p.ID
        ");

        foreach ($products as $p) {
            $stock_qty = floatval($p->stock);
            if ($stock_qty == 0) continue;

            $product_id = ($p->post_type === 'product_variation') ? $p->post_parent : $p->ID;
            $variation_id = ($p->post_type === 'product_variation') ? $p->ID : 0;

            $wpdb->insert($tables['stok_konumlari'], [
                'product_id'   => $product_id,
                'variation_id' => $variation_id,
                'location_id'  => $depo_id,
                'quantity'     => $stock_qty
            ]);

            self::log_movement($product_id, $variation_id, $depo_id, 0, $stock_qty, "İlk Kurulum Senkronizasyonu");
        }

        return true;
    }

    /**
     * Belirli bir depodan stok düşer.
     */
    public static function update_warehouse_stock($product_id, $variation_id, $location_id, $change_amount, $reason = "") {
        global $wpdb;
        $tables = Hizli_Kasa_Database::get_tables();

        $current = $wpdb->get_row($wpdb->prepare("
            SELECT id, quantity FROM {$tables['stok_konumlari']} 
            WHERE product_id = %d AND variation_id = %d AND location_id = %d
        ", $product_id, $variation_id, $location_id));

        $old_qty = $current ? floatval($current->quantity) : 0;
        $new_qty = $old_qty + $change_amount;

        if ($current) {
            $wpdb->update($tables['stok_konumlari'], ['quantity' => $new_qty], ['id' => $current->id]);
        } else {
            $wpdb->insert($tables['stok_konumlari'], [
                'product_id'   => $product_id,
                'variation_id' => $variation_id,
                'location_id'  => $location_id,
                'quantity'     => $new_qty
            ]);
        }

        self::log_movement($product_id, $variation_id, $location_id, $old_qty, $new_qty, $reason);
        // self::sync_to_wc_stock($product_id, $variation_id);

        return $new_qty;
    }

    /**
     * Online satışlar için öncelikli stok düşümü.
     */
    public static function priority_stock_deduction($product_id, $variation_id, $total_to_deduct) {
        global $wpdb;
        $tables = Hizli_Kasa_Database::get_tables();
        
        $online_depo_id = get_option('hizli_kasa_varsayilan_online_depo');
        
        // Depoları önceliğe göre getir
        $depolar = $wpdb->get_results("SELECT id FROM {$tables['depolar']} ORDER BY 
            (CASE WHEN id = " . intval($online_depo_id) . " THEN 1 ELSE 0 END) DESC, 
            priority DESC");

        $remaining = $total_to_deduct;

        foreach ($depolar as $d) {
            if ($remaining <= 0) break;

            $stock = $wpdb->get_var($wpdb->prepare("
                SELECT quantity FROM {$tables['stok_konumlari']} 
                WHERE product_id = %d AND variation_id = %d AND location_id = %d
            ", $product_id, $variation_id, $d->id));

            if (!$stock || $stock <= 0) continue;

            $to_take = min($stock, $remaining);
            self::update_warehouse_stock($product_id, $variation_id, $d->id, -$to_take, "Online Satış (Otomatik)");
            $remaining -= $to_take;
        }

        // Eğer hala düşülecek stok kaldıysa (tüm depolar tükendiyse), öncelikli depodan eksiye düşür
        if ($remaining > 0 && $online_depo_id) {
            self::update_warehouse_stock($product_id, $variation_id, $online_depo_id, -$remaining, "Online Satış (Stok Yetersiz - Eksiye Düştü)");
        }
    }

    /**
     * Tüm depoları toplayıp WC ana stoğunu günceller.
     */
    public static function sync_to_wc_stock($product_id, $variation_id) {
        global $wpdb;
        $tables = Hizli_Kasa_Database::get_tables();

        $total_qty = $wpdb->get_var($wpdb->prepare("
            SELECT SUM(quantity) FROM {$tables['stok_konumlari']} 
            WHERE product_id = %d AND variation_id = %d
        ", $product_id, $variation_id));

        $id_to_update = $variation_id ? $variation_id : $product_id;
        
        update_post_meta($id_to_update, '_stock', ($total_qty ?: 0));
        
        // Stok durumunu güncelle
        $status = ($total_qty > 0) ? 'instock' : 'outofstock';
        update_post_meta($id_to_update, '_stock_status', $status);
        
        // Cache temizle
        wp_cache_delete($id_to_update, 'post_meta');
        if($variation_id) wc_delete_product_transients($product_id);
        else wc_delete_product_transients($id_to_update);
    }

    /**
     * Hareket kaydı tutar.
     */
    public static function log_movement($product_id, $variation_id, $location_id, $old_qty, $new_qty, $reason) {
        global $wpdb;
        $tables = Hizli_Kasa_Database::get_tables();

        $wpdb->insert($tables['stok_hareketleri'], [
            'product_id'   => $product_id,
            'variation_id' => $variation_id,
            'location_id'  => $location_id,
            'user_id'      => get_current_user_id(),
            'old_qty'      => $old_qty,
            'new_qty'      => $new_qty,
            'change_amount' => $new_qty - $old_qty,
            'reason'       => $reason
        ]);
    }
}
