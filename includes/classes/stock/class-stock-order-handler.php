<?php
if (!defined('ABSPATH')) exit;

class Hizli_Kasa_Stock_Order_Handler {
    public static function listen() {
        add_action('woocommerce_order_status_processing', [__CLASS__, 'handle_reservation'], 10, 2);
        add_action('woocommerce_order_status_completed', [__CLASS__, 'handle_completion'], 10, 2);
        add_action('woocommerce_order_status_cancelled', [__CLASS__, 'handle_cancellation'], 10, 2);
        add_action('woocommerce_order_status_refunded', [__CLASS__, 'handle_cancellation'], 10, 2);
        add_action('woocommerce_order_status_failed', [__CLASS__, 'handle_cancellation'], 10, 2);
        add_action('woocommerce_new_order', [__CLASS__, 'handle_pos_order'], 10, 2);
    }

    public static function handle_pos_order($order_id, $order = false) {
        if (!$order) {
            $order = wc_get_order($order_id);
        }

        if (!$order) return;

        $kasiyer_name = $order->get_meta('_hizli_kasa_kasiyer');

        hizli_kasa_log("handle_pos_order_stock tetiklendi. Sipariş ID: $order_id, Kasiyer: " . ($kasiyer_name ?: 'Yok'));

        if (!$kasiyer_name) return;

        $depo_id = $order->get_meta('_hk_cikis_depo_id');
        $user_id = get_current_user_id();

        if (!$depo_id) {
            if (!$user_id) {
                 hizli_kasa_log("Uyarı: current_user_id 0 döndü. REST API auth kontrol edilmeli.");
            }

            $depo_id = get_user_meta($user_id, '_hizli_kasa_active_depo', true);

            if (!$depo_id) {
                $depo_id = get_user_meta($user_id, '_hizli_kasa_depo_id', true);
            }
        }

        hizli_kasa_log("Kasiyer User ID: $user_id, Tespit Edilen Depo ID: " . ($depo_id ?: 'Yok'));

        if (!$depo_id) {
            hizli_kasa_log("HATA: Depo ID bulunamadığı için stok düşülemedi.");
            return;
        }

        global $wpdb;
        $tables = Hizli_Kasa_Database::get_tables();

        $depo_name = $wpdb->get_var($wpdb->prepare(
            "SELECT name FROM {$tables['depolar']} WHERE id = %d", $depo_id
        ));

        foreach ($order->get_items() as $item_id => $item) {
            $product_id = $item->get_product_id();
            $variation_id = $item->get_variation_id();
            $qty = $item->get_quantity();

            hizli_kasa_log("Stok Düşülüyor: Prod: $product_id, Var: $variation_id, Adet: $qty, Depo: $depo_id");

            Hizli_Kasa_Stock_Manager::update_warehouse_stock($product_id, $variation_id, $depo_id, -$qty, "POS Satışı (#$order_id)");

            wc_update_order_item_meta($item_id, '_hk_cikis_depo_id', $depo_id);
            wc_update_order_item_meta($item_id, '_hk_cikis_depo_adet', $qty);
            wc_update_order_item_meta($item_id, '_hk_cikis_depo_adi', $depo_name ?: 'Bilinmeyen');
        }

        $order->update_meta_data('_hk_cikis_depo_id', $depo_id);
        $order->update_meta_data('_hk_cikis_depo_adi', $depo_name ?: 'Bilinmeyen');
        $order->save();

        hizli_kasa_log("Sipariş #$order_id için depo stok düşümü tamamlandı.");
    }

    public static function handle_reservation($order_id, $order = false) {
        if (!$order) $order = wc_get_order($order_id);
        if (!$order) return;

        if ($order->get_meta('_hizli_kasa_kasiyer')) return;

        hizli_kasa_log("handle_online_order_reservation tetiklendi. Sipariş ID: $order_id");

        foreach ($order->get_items() as $item) {
            $product_id = $item->get_product_id();
            $variation_id = $item->get_variation_id();
            $qty = $item->get_quantity();

            Hizli_Kasa_Stock_Allocation::priority_reservation($product_id, $variation_id, $qty, $item);
        }
    }

    public static function handle_completion($order_id, $order = false) {
        if (!$order) $order = wc_get_order($order_id);
        if (!$order) return;

        if ($order->get_meta('_hizli_kasa_kasiyer')) return;

        hizli_kasa_log("handle_online_order_completion tetiklendi. Sipariş ID: $order_id");

        foreach ($order->get_items() as $item_id => $item) {
            $product_id = $item->get_product_id();
            $variation_id = $item->get_variation_id();

            $reservations = wc_get_order_item_meta($item_id, '_hk_reservations', true);
            if (empty($reservations)) continue;

            foreach ($reservations as $res) {
                $depo_id = intval($res['depo_id']);
                $qty = floatval($res['qty']);

                Hizli_Kasa_Stock_Manager::update_warehouse_stock_reservation($product_id, $variation_id, $depo_id, -$qty);
                Hizli_Kasa_Stock_Manager::update_warehouse_stock($product_id, $variation_id, $depo_id, -$qty, "Online Sipariş Tamamlandı (#$order_id)");
            }
        }
    }

    public static function handle_cancellation($order_id, $order = false) {
        if (!$order) {
            $order = wc_get_order($order_id);
        }
        if (!$order) return;

        hizli_kasa_log("handle_cancelled_order_stock tetiklendi. Sipariş ID: $order_id");

        foreach ($order->get_items() as $item_id => $item) {
            $product_id = $item->get_product_id();
            $variation_id = $item->get_variation_id();

            if ($item->get_meta('_hk_restocked_on_cancel')) continue;

            $reservations = wc_get_order_item_meta($item_id, '_hk_reservations', true);

            if (!empty($reservations) && is_array($reservations)) {
                foreach ($reservations as $res) {
                    $depo_id = intval($res['depo_id']);
                    $qty = floatval($res['qty']);

                    if ($depo_id && $qty > 0) {
                        Hizli_Kasa_Stock_Manager::update_warehouse_stock_reservation($product_id, $variation_id, $depo_id, -$qty);
                        hizli_kasa_log("İptal: Online rezervasyon bırakıldı. Depo: $depo_id, Ürün: $product_id, Adet: $qty");
                    }
                }
                wc_update_order_item_meta($item_id, '_hk_restocked_on_cancel', 'yes');
            } else {
                $depo_id = (int) wc_get_order_item_meta($item_id, '_hk_cikis_depo_id', true);

                if (!$depo_id) {
                    $depo_id = (int) $order->get_meta('_hk_cikis_depo_id');
                }

                $qty = (float) wc_get_order_item_meta($item_id, '_hk_cikis_depo_adet', true);

                if (!$qty) $qty = $item->get_quantity();

                if ($depo_id && $qty > 0) {
                    Hizli_Kasa_Stock_Manager::update_warehouse_stock($product_id, $variation_id, $depo_id, $qty, "Sipariş İptali/İade (#$order_id)");
                    hizli_kasa_log("İptal: POS stok iade edildi. Depo: $depo_id, Ürün: $product_id, Adet: $qty");

                    wc_update_order_item_meta($item_id, '_hk_restocked_on_cancel', 'yes');
                }
            }
        }
    }

    public static function resolve_conflict($product_id, $variation_id, $location_id, $conflict_qty) {
        global $wpdb;

        hizli_kasa_log("STOK ÇAKIŞMASI: P:$product_id, V:$variation_id, L:$location_id, Çakışan Adet: $conflict_qty");

        $orders_query = $wpdb->prepare("
            SELECT im.order_item_id, i.order_id, im.meta_value as reservations
            FROM {$wpdb->prefix}woocommerce_order_itemmeta im
            JOIN {$wpdb->prefix}woocommerce_order_items i ON im.order_item_id = i.order_item_id
            JOIN {$wpdb->prefix}woocommerce_order_itemmeta im2 ON im.order_item_id = im2.order_item_id
            JOIN {$wpdb->posts} p ON i.order_id = p.ID
            WHERE im.meta_key = '_hk_reservations'
              AND im2.meta_key = '_product_id' AND im2.meta_value = %d
              AND p.post_status = 'wc-processing'
            ORDER BY p.post_date DESC
        ", $product_id);

        $results = $wpdb->get_results($orders_query);
        $remaining_to_fix = $conflict_qty;

        foreach ($results as $row) {
            if ($remaining_to_fix <= 0) break;

            $item_variation_id = (int) wc_get_order_item_meta($row->order_item_id, '_variation_id', true);
            if ($variation_id > 0 && $item_variation_id != $variation_id) {
                continue;
            }
            if ($variation_id == 0 && $item_variation_id > 0) {
                continue;
            }

            $reservations = maybe_unserialize($row->reservations);
            if (!is_array($reservations)) continue;

            foreach ($reservations as &$res) {
                if ($res['depo_id'] == $location_id) {
                    $order = wc_get_order($row->order_id);
                    if (!$order) continue;

                    $res_qty = floatval($res['qty']);
                    $to_cancel = min($res_qty, $remaining_to_fix);

                    $order->update_status('failed', sprintf('Stok yetersizliği nedeniyle sistem tarafından iptal edildi. (POS Satışı Çakışması, Ürün ID: %d, Depo ID: %d)', ($variation_id ?: $product_id), $location_id));

                    $remaining_to_fix -= $to_cancel;
                    hizli_kasa_log("Çatışma Çözüldü: Sipariş #{$row->order_id} başarısız durumuna alındı.");
                    break;
                }
            }
        }
    }
}
