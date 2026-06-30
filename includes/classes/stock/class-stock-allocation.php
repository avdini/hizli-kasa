<?php
if (!defined('ABSPATH')) {
    exit;
}

class Hizli_Kasa_Stock_Allocation {
    public static function priority_reservation($product_id, $variation_id, $total_to_deduct, $item = null) {
        global $wpdb;
        $tables = Hizli_Kasa_Database::get_tables();

        $online_depo_id = get_option('hizli_kasa_varsayilan_online_depo');

        $depolar = $wpdb->get_results("SELECT id FROM {$tables['depolar']} ORDER BY
            (CASE WHEN id = " . intval($online_depo_id) . " THEN 1 ELSE 0 END) DESC,
            priority DESC");

        $remaining = $total_to_deduct;
        $reservations = [];

        foreach ($depolar as $d) {
            if ($remaining <= 0) {
                break;
            }

            $stock_data = $wpdb->get_row($wpdb->prepare("
                SELECT quantity, reserved FROM {$tables['stok_konumlari']}
                WHERE product_id = %d AND variation_id = %d AND location_id = %d
            ", $product_id, $variation_id, $d->id));

            $qty = $stock_data ? floatval($stock_data->quantity) : 0;
            $res = $stock_data ? floatval($stock_data->reserved) : 0;
            $available = $qty - $res;

            if ($available <= 0) {
                continue;
            }

            $to_take = min($available, $remaining);
            Hizli_Kasa_Stock_Manager::update_warehouse_stock_reservation($product_id, $variation_id, $d->id, $to_take);

            $reservations[] = ['depo_id' => $d->id, 'qty' => $to_take];
            $remaining -= $to_take;
        }

        if ($remaining > 0 && $online_depo_id) {
            Hizli_Kasa_Stock_Manager::update_warehouse_stock_reservation($product_id, $variation_id, $online_depo_id, $remaining);

            $found = false;
            foreach ($reservations as &$r) {
                if ($r['depo_id'] == $online_depo_id) {
                    $r['qty'] += $remaining;
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $reservations[] = ['depo_id' => $online_depo_id, 'qty' => $remaining];
            }
        }

        if ($item && $reservations !== []) {
            wc_update_order_item_meta($item->get_id(), '_hk_reservations', $reservations);
        }
    }

    public static function priority_deduction($product_id, $variation_id, $total_to_deduct, $item = null) {
        global $wpdb;
        $tables = Hizli_Kasa_Database::get_tables();

        $online_depo_id = get_option('hizli_kasa_varsayilan_online_depo');

        $depolar = $wpdb->get_results("SELECT id FROM {$tables['depolar']} ORDER BY
            (CASE WHEN id = " . intval($online_depo_id) . " THEN 1 ELSE 0 END) DESC,
            priority DESC");

        $remaining = $total_to_deduct;
        $deductions = [];

        foreach ($depolar as $d) {
            if ($remaining <= 0) {
                break;
            }

            $stock = $wpdb->get_var($wpdb->prepare("
                SELECT quantity FROM {$tables['stok_konumlari']}
                WHERE product_id = %d AND variation_id = %d AND location_id = %d
            ", $product_id, $variation_id, $d->id));

            if (!$stock || $stock <= 0) {
                continue;
            }

            $to_take = min($stock, $remaining);
            Hizli_Kasa_Stock_Manager::update_warehouse_stock($product_id, $variation_id, $d->id, -$to_take, "Online Satış (Otomatik)");

            $deductions[] = ['depo_id' => $d->id, 'qty' => $to_take];
            $remaining -= $to_take;
        }

        if ($remaining > 0 && $online_depo_id) {
            Hizli_Kasa_Stock_Manager::update_warehouse_stock($product_id, $variation_id, $online_depo_id, -$remaining, "Online Satış (Stok Yetersiz - Eksiye Düştü)");

            $found = false;
            foreach ($deductions as &$ded) {
                if ($ded['depo_id'] == $online_depo_id) {
                    $ded['qty'] += $remaining;
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $deductions[] = ['depo_id' => $online_depo_id, 'qty' => $remaining];
            }
        }

        if ($item && $deductions !== []) {
            wc_update_order_item_meta($item->get_id(), '_hk_deductions', $deductions);
        }
    }

    public static function transfer_out($product_id, $variation_id, $kaynak_depo_id, $qty, $sevk_id) {
        $sevk_no = self::get_transfer_sevk_no($sevk_id);
        return Hizli_Kasa_Stock_Manager::update_warehouse_stock($product_id, $variation_id, $kaynak_depo_id, -abs((float) $qty), "Sevk Çıkış (#$sevk_no)");
    }

    public static function transfer_in($product_id, $variation_id, $hedef_depo_id, $qty, $sevk_id) {
        $sevk_no = self::get_transfer_sevk_no($sevk_id);
        return Hizli_Kasa_Stock_Manager::update_warehouse_stock($product_id, $variation_id, $hedef_depo_id, abs((float) $qty), "Sevk Giriş (#$sevk_no)");
    }

    private static function get_transfer_sevk_no($sevk_id) {
        global $wpdb;
        $tables = Hizli_Kasa_Database::get_tables();
        $sevk_no = $wpdb->get_var($wpdb->prepare("SELECT sevk_no FROM {$tables['sevkler']} WHERE id = %d", $sevk_id));
        return $sevk_no ?: (string) $sevk_id;
    }
}
