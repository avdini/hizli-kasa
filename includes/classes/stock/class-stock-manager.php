<?php
if (!defined('ABSPATH')) {
    exit;
}

class Hizli_Kasa_Stock_Manager {

    private static $deferred_invalidation_scheduled = false;

    /**
     * @deprecated Hook kaydı artık Hizli_Kasa_Stock_Order_Handler::listen() tarafından yapılıyor.
     */
    public static function listen() {
        Hizli_Kasa_Stock_Order_Handler::listen();
    }

    public static function initial_sync($depo_id) {
        global $wpdb;
        $tables = Hizli_Kasa_Database::get_tables();

        $wpdb->query("TRUNCATE TABLE {$tables['stok_konumlari']}");

        $products = $wpdb->get_results("
            SELECT p.ID, p.post_parent, p.post_type,
                   MAX(CASE WHEN pm.meta_key = '_stock' THEN pm.meta_value END) as stock
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_status IN ('publish', 'private')
              AND p.post_type IN ('product', 'product_variation')
              AND pm.meta_key = '_stock'
            GROUP BY p.ID
        ");

        foreach ($products as $p) {
            $stock_qty = floatval($p->stock);
            if ($stock_qty == 0) {
                continue;
            }

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

    public static function update_warehouse_stock($product_id, $variation_id, $location_id, $change_amount, $reason = "") {
        global $wpdb;
        $tables = Hizli_Kasa_Database::get_tables();

        hizli_kasa_log("update_warehouse_stock çağrıldı: P:$product_id, V:$variation_id, L:$location_id, Change:$change_amount");

        $current = $wpdb->get_row($wpdb->prepare("
            SELECT id, quantity, reserved FROM {$tables['stok_konumlari']}
            WHERE product_id = %d AND variation_id = %d AND location_id = %d
        ", $product_id, $variation_id, $location_id));

        $old_qty = $current ? floatval($current->quantity) : 0;
        $new_qty = $old_qty + $change_amount;

        if ($current) {
            $result = $wpdb->update($tables['stok_konumlari'], ['quantity' => $new_qty], ['id' => $current->id]);
            hizli_kasa_log("DB Update (ID:{$current->id}): " . ($result !== false ? "BAŞARILI" : "HATA: " . $wpdb->last_error));
        } else {
            $result = $wpdb->insert($tables['stok_konumlari'], [
                'product_id'   => $product_id,
                'variation_id' => $variation_id,
                'location_id'  => $location_id,
                'quantity'     => $new_qty
            ]);
            hizli_kasa_log("DB Insert: " . ($result !== false ? "BAŞARILI" : "HATA: " . $wpdb->last_error));
        }

        if ($change_amount < 0) {
            $reserved = $current ? floatval($current->reserved) : 0;
            if ($new_qty < $reserved) {
                Hizli_Kasa_Stock_Order_Handler::resolve_conflict($product_id, $variation_id, $location_id, $reserved - $new_qty);
            }
        }
        self::log_movement($product_id, $variation_id, $location_id, $old_qty, $new_qty, $reason);

        self::schedule_deferred_invalidation();

        return $new_qty;
    }

    public static function schedule_deferred_invalidation() {
        if (self::$deferred_invalidation_scheduled) {
            return;
        }
        self::$deferred_invalidation_scheduled = true;

        add_action('shutdown', function () {
            if (class_exists('Hizli_Kasa_Mismatch_Notifier')) {
                Hizli_Kasa_Mismatch_Notifier::reset_status();
            }
        }, 99);
    }

    public static function update_warehouse_stock_reservation($product_id, $variation_id, $location_id, $change_amount) {
        global $wpdb;
        $tables = Hizli_Kasa_Database::get_tables();

        $current = $wpdb->get_row($wpdb->prepare("
            SELECT id, reserved FROM {$tables['stok_konumlari']}
            WHERE product_id = %d AND variation_id = %d AND location_id = %d
        ", $product_id, $variation_id, $location_id));

        $old_res = $current ? floatval($current->reserved) : 0;
        $new_res = max(0, $old_res + $change_amount);

        if ($current) {
            $wpdb->update($tables['stok_konumlari'], ['reserved' => $new_res], ['id' => $current->id]);
        } else {
            $wpdb->insert($tables['stok_konumlari'], [
                'product_id'   => $product_id,
                'variation_id' => $variation_id,
                'location_id'  => $location_id,
                'reserved'     => $new_res
            ]);
        }

        hizli_kasa_log("Rezervasyon Güncellendi: L:$location_id, P:$product_id, V:$variation_id, Old:$old_res, New:$new_res");
        return $new_res;
    }

    public static function update_warehouse_stock_set($product_id, $variation_id, $location_id, $new_qty, $reason = "") {
        global $wpdb;
        $tables = Hizli_Kasa_Database::get_tables();

        $current = $wpdb->get_var($wpdb->prepare("
            SELECT quantity FROM {$tables['stok_konumlari']}
            WHERE product_id = %d AND variation_id = %d AND location_id = %d
        ", $product_id, $variation_id, $location_id));

        $old_qty = $current ? floatval($current) : 0;

        if ($current !== null) {
            $wpdb->update($tables['stok_konumlari'], ['quantity' => $new_qty], [
                'product_id' => $product_id,
                'variation_id' => $variation_id,
                'location_id' => $location_id
            ]);
        } else {
            $wpdb->insert($tables['stok_konumlari'], [
                'product_id'   => $product_id,
                'variation_id' => $variation_id,
                'location_id'  => $location_id,
                'quantity'     => $new_qty,
                'updated_at'   => current_time('mysql')
            ]);
        }

        self::log_movement($product_id, $variation_id, $location_id, $old_qty, $new_qty, $reason);
        self::schedule_deferred_invalidation();

        return $new_qty;
    }

    public static function log_movement($product_id, $variation_id, $location_id, $old_qty, $new_qty, $reason) {
        global $wpdb;
        $tables = Hizli_Kasa_Database::get_tables();

        $wpdb->insert($tables['stok_hareketleri'], [
            'product_id'   => $product_id,
            'variation_id' => $variation_id,
            'location_id'  => $location_id,
            'user_id'      => get_current_user_id() ?: 0,
            'old_qty'      => $old_qty,
            'new_qty'      => $new_qty,
            'amount'       => $new_qty - $old_qty,
            'reason'       => $reason,
            'created_at'   => current_time('mysql')
        ]);
    }

    /**
     * @deprecated Devre dışı.
     */
    public static function sync_to_wc_stock($product_id, $variation_id)
    {
    }

    // --- Backward compatibility wrappers ---

    public static function handle_pos_order_stock($order_id, $order = false) {
        return Hizli_Kasa_Stock_Order_Handler::handle_pos_order($order_id, $order);
    }

    public static function handle_online_order_reservation($order_id, $order = false) {
        return Hizli_Kasa_Stock_Order_Handler::handle_reservation($order_id, $order);
    }

    public static function handle_online_order_completion($order_id, $order = false) {
        return Hizli_Kasa_Stock_Order_Handler::handle_completion($order_id, $order);
    }

    public static function handle_cancelled_order_stock($order_id, $order = false) {
        return Hizli_Kasa_Stock_Order_Handler::handle_cancellation($order_id, $order);
    }

    public static function resolve_stock_reservation_conflict($product_id, $variation_id, $location_id, $conflict_qty) {
        return Hizli_Kasa_Stock_Order_Handler::resolve_conflict($product_id, $variation_id, $location_id, $conflict_qty);
    }

    public static function priority_stock_reservation($product_id, $variation_id, $total_to_deduct, $item = null) {
        return Hizli_Kasa_Stock_Allocation::priority_reservation($product_id, $variation_id, $total_to_deduct, $item);
    }

    public static function priority_stock_deduction($product_id, $variation_id, $total_to_deduct, $item = null) {
        return Hizli_Kasa_Stock_Allocation::priority_deduction($product_id, $variation_id, $total_to_deduct, $item);
    }

    public static function transfer_out($product_id, $variation_id, $kaynak_depo_id, $qty, $sevk_id) {
        return Hizli_Kasa_Stock_Allocation::transfer_out($product_id, $variation_id, $kaynak_depo_id, $qty, $sevk_id);
    }

    public static function transfer_in($product_id, $variation_id, $hedef_depo_id, $qty, $sevk_id) {
        return Hizli_Kasa_Stock_Allocation::transfer_in($product_id, $variation_id, $hedef_depo_id, $qty, $sevk_id);
    }

    public static function export_stocks($format = 'csv', $depo_id = 0) {
        return Hizli_Kasa_Stock_Import_Export::export($format, $depo_id);
    }

    public static function process_import($file_path, $format = 'csv') {
        return Hizli_Kasa_Stock_Import_Export::import($file_path, $format);
    }

    public static function add_unmatched_item($warehouse_name, $product_name, $sku, $qty, $error) {
        return Hizli_Kasa_Stock_Import_Export::add_unmatched_item($warehouse_name, $product_name, $sku, $qty, $error);
    }
}
