<?php
if (!defined('ABSPATH')) exit;

class Hizli_Kasa_Order_Print_Builder {

    public function build(int $order_id): Hizli_Kasa_Print_Payload {
        $order = wc_get_order($order_id);
        if (!$order) {
            throw new Exception("Sipariş bulunamadı: {$order_id}");
        }

        $is_modified = $this->is_order_modified($order);
        $variant = $is_modified ? 'modified' : 'original';

        $payload = new Hizli_Kasa_Print_Payload('order', $variant, $order_id);

        // Header
        $cashier = $order->get_meta('_hizli_kasa_kasiyer_adi') ?: $order->get_meta('_hizli_kasa_cashier_name') ?: 'Kasiyer';
        $register_no = $order->get_meta('_hizli_kasa_kasa_no') ?: '1';
        $payment_method_title = $order->get_payment_method_title();

        $header = [
            'store_name'    => get_bloginfo('name'),
            'order_number'  => '#' . $order->get_order_number(),
            'order_id'      => $order->get_id(),
            'date'          => $order->get_date_created() ? $order->get_date_created()->date('Y-m-d H:i') : '',
            'cashier'       => $cashier,
            'register_no'   => 'Kasa ' . $register_no,
            'payment_title' => $payment_method_title,
            'badge'         => $is_modified ? 'İŞLEM GÖRMÜŞ FİŞ' : 'SATIŞ FİŞİ'
        ];
        $payload->set_header($header);

        // Calculate Items & Refunds
        $refunds = $order->get_refunds();
        $refunded_qty_map = [];
        $refunded_total_map = [];

        foreach ($refunds as $refund) {
            foreach ($refund->get_items() as $item_id => $item) {
                $product_id = $item->get_product_id();
                $variation_id = $item->get_variation_id();
                $key = $variation_id ? "v_{$variation_id}" : "p_{$product_id}";
                
                $refunded_qty_map[$key] = ($refunded_qty_map[$key] ?? 0) + absint($item->get_quantity());
                $refunded_total_map[$key] = ($refunded_total_map[$key] ?? 0) + abs($item->get_total());
            }
        }

        $items = [];
        $gross_total = 0;
        $total_item_discount = 0;

        foreach ($order->get_items() as $item_id => $item) {
            $product_id = $item->get_product_id();
            $variation_id = $item->get_variation_id();
            $key = $variation_id ? "v_{$variation_id}" : "p_{$product_id}";

            $qty = $item->get_quantity();
            $line_total = (float)$item->get_total();
            $line_subtotal = (float)$item->get_subtotal();
            $item_discount = max(0, $line_subtotal - $line_total);

            $refunded_qty = $refunded_qty_map[$key] ?? 0;
            $refunded_total = $refunded_total_map[$key] ?? 0;
            $net_qty = max(0, $qty - $refunded_qty);
            $net_total = max(0, $line_total - $refunded_total);

            $gross_total += $line_subtotal;
            $total_item_discount += $item_discount;

            $item_status = 'original';
            if ($refunded_qty > 0) {
                $item_status = ($net_qty === 0) ? 'fully_refunded' : 'partially_refunded';
            }

            $sku = '';
            $product = $item->get_product();
            if ($product) {
                $sku = $product->get_sku();
            }

            $meta_etiket = $item->get_meta('_etiket_fiyat');
            $meta_kampanya = $item->get_meta('_kampanya_fiyat');
            $unit_subtotal = $qty > 0 ? ($line_subtotal / $qty) : 0;
            
            $etiket_fiyat = ($meta_etiket !== '' && $meta_etiket !== null) ? (float)$meta_etiket : $unit_subtotal;
            $kampanya_fiyat = ($meta_kampanya !== '' && $meta_kampanya !== null) ? (float)$meta_kampanya : $unit_subtotal;

            $items[] = [
                'item_id'         => $item_id,
                'name'            => $item->get_name(),
                'sku'             => $sku,
                'qty'             => $qty,
                'price'           => $unit_subtotal,
                'etiket_fiyat'    => $etiket_fiyat,
                'kampanya_fiyat'  => $kampanya_fiyat,
                'line_subtotal'   => $line_subtotal,
                'line_total'      => $line_total,
                'item_discount'   => $item_discount,
                'refunded_qty'    => $refunded_qty,
                'refunded_total'  => $refunded_total,
                'net_qty'         => $net_qty,
                'net_total'       => $net_total,
                'status'          => $item_status
            ];
        }
        $payload->set_items($items);

        // Totals
        $order_total = (float)$order->get_total();
        $total_refunded = (float)$order->get_total_refunded();
        $auto_discount = (float)($order->get_meta('_hk_otomatik_indirim') ?: 0);
        $coupon_discount = (float)$order->get_discount_total();
        $exchange_diff = (float)($order->get_meta('_hk_exchange_refund_total') ?: 0);
        $totals = [
            'gross_total'         => $gross_total,
            'item_discount_total' => $total_item_discount,
            'auto_discount'       => $auto_discount,
            'coupon_discount'     => $coupon_discount,
            'exchange_diff'       => $exchange_diff,
            'order_total'         => $order_total,
            'refunded_total'      => $total_refunded,
            'net_paid'            => max(0, $order_total - $total_refunded)
        ];
        $payload->set_totals($totals);

        // Audit Trail for Modified Orders
        $audit_trail = [];
        if ($is_modified) {
            $audit_trail[] = [
                'timestamp' => $order->get_date_created() ? $order->get_date_created()->date('Y-m-d H:i') : '',
                'action'    => 'İlk Satış İşlemi Oluşturuldu',
                'amount'    => $order_total
            ];

            if (!empty($refunds)) {
                foreach ($refunds as $rf) {
                    $audit_trail[] = [
                        'timestamp'  => $rf->get_date_created() ? $rf->get_date_created()->date('Y-m-d H:i') : '',
                        'action'     => 'İade İşlemi (' . $rf->get_id() . ')',
                        'amount'     => -abs((float)$rf->get_total()),
                        'reason'     => $rf->get_reason() ?: 'İade İşlemi'
                    ];
                }
            }

            $exchange_meta = $order->get_meta('_hk_order_exchange_history');
            if (!empty($exchange_meta) && is_array($exchange_meta)) {
                foreach ($exchange_meta as $ex) {
                    $audit_trail[] = [
                        'timestamp' => $ex['date'] ?? '',
                        'action'    => 'Ürün Değişimi Gerçekleştirildi',
                        'amount'    => (float)($ex['difference'] ?? 0)
                    ];
                }
            }
        }
        $payload->set_audit_trail($audit_trail);

        $payload->set_barcode_value((string)$order->get_id());

        return $payload;
    }

    private function is_order_modified(WC_Order $order): bool {
        if (count($order->get_refunds()) > 0) {
            return true;
        }

        if ($order->get_meta('_hk_has_refund') === 'yes' || $order->get_meta('_hk_is_fully_refunded') === 'yes') {
            return true;
        }

        if ($order->get_meta('_hk_order_exchange_history') || $order->get_meta('_hk_order_edits')) {
            return true;
        }

        if (in_array($order->get_status(), ['refunded', 'partially-refunded'])) {
            return true;
        }

        // Check wp_hizli_kasa_order_edits database table
        global $wpdb;
        $table_edits = $wpdb->prefix . 'hizli_kasa_order_edits';
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_edits)) === $table_edits) {
            $count = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table_edits} WHERE order_id = %d", $order->get_id()));
            if ($count > 0) {
                return true;
            }
        }

        return false;
    }
}
