<?php
/**
 * Hızlı Kasa V2 API Refund Cancellation Controller
 *
 * POST /hizli-kasa/v2/refund/cancel
 *
 * @package HizliKasa
 */

if (!defined('ABSPATH')) {
    exit;
}

class Hizli_Kasa_API_Refund_Cancellation extends Hizli_Kasa_API_Controller_Base {

    public function register_routes() {
        register_rest_route($this->namespace, '/refund/cancel', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'cancel_refund_callback'],
            'permission_callback' => [$this, 'check_permission'],
            'args'                => [
                'refund_order_id' => [
                    'required'          => true,
                    'sanitize_callback' => 'absint',
                ],
                'cancel_reason'   => [
                    'required'          => true,
                    'sanitize_callback' => 'sanitize_textarea_field',
                ],
            ],
        ]);
    }

    public function cancel_refund_callback(WP_REST_Request $request) {
        $refund_order_id = intval($request->get_param('refund_order_id'));
        $cancel_reason   = sanitize_textarea_field(trim($request->get_param('cancel_reason') ?? ''));

        if (!$refund_order_id) {
            return Hizli_Kasa_API_Response::error('Geçersiz veya eksik iade sipariş ID.', 400);
        }

        if (mb_strlen($cancel_reason) < 3) {
            return Hizli_Kasa_API_Response::error('Lütfen geçerli bir iade iptal nedeni belirtiniz (en az 3 karakter).', 400);
        }

        $refund_order = wc_get_order($refund_order_id);
        if (!$refund_order) {
            return Hizli_Kasa_API_Response::error('İade siparişi bulunamadı.', 404);
        }

        if ($refund_order->get_meta('_hizli_kasa_is_refund') !== 'yes') {
            return Hizli_Kasa_API_Response::error('Bu sipariş bir iade işlemi değildir.', 400);
        }

        if ($refund_order->get_status() === 'cancelled' || $refund_order->get_meta('_hizli_kasa_refund_cancelled') === 'yes') {
            return Hizli_Kasa_API_Response::error('Bu iade işlemi zaten iptal edilmiş.', 400);
        }

        $coupon_code = $refund_order->get_meta('_verilen_kupon_kodu');
        if (!empty($coupon_code)) {
            $coupon = new WC_Coupon($coupon_code);
            if ($coupon && $coupon->get_id() > 0) {
                if ($coupon->get_usage_count() > 0) {
                    return Hizli_Kasa_API_Response::error(
                        sprintf('Bu iadeye ait verilen %s kodlu kupon başka bir alışverişte kullanıldığı için iade iptal edilemez.', $coupon_code),
                        400
                    );
                }
                $coupon->set_amount(0);
                $coupon->save();
            }
        }

        require_once HIZLI_KASA_PATH . 'includes/classes/class-stock-manager.php';

        $user_id          = get_current_user_id();
        $fallback_depo_id = (int) $refund_order->get_meta('_hk_cikis_depo_id');
        if (!$fallback_depo_id) {
            $fallback_depo_id = hizli_kasa_get_user_active_depo($user_id);
        }

        foreach ($refund_order->get_items() as $item) {
            if (!$item instanceof WC_Order_Item_Product) {
                continue;
            }

            $product_id   = intval($item->get_product_id());
            $variation_id = intval($item->get_variation_id());
            $qty          = abs($item->get_quantity());

            if ($qty <= 0) {
                continue;
            }

            if ($fallback_depo_id > 0 && hizli_kasa_can_user_manage_depo($user_id, $fallback_depo_id)) {
                Hizli_Kasa_Stock_Manager::update_warehouse_stock(
                    $product_id,
                    $variation_id,
                    $fallback_depo_id,
                    -$qty,
                    "İade İptali (#{$refund_order_id}): {$cancel_reason}"
                );

                Hizli_Kasa_Logger::stock(
                    "İade #{$refund_order_id} iptali nedeniyle depodan (Depo #{$fallback_depo_id}) {$qty} adet stok düşüldü.",
                    [
                        'refund_order_id' => $refund_order_id,
                        'product_id'      => $product_id,
                        'variation_id'    => $variation_id,
                        'depo_id'         => $fallback_depo_id,
                        'qty'             => -$qty,
                    ],
                    'info',
                    'product',
                    $product_id
                );
            }

            $target_product = wc_get_product($variation_id ?: $product_id);
            if ($target_product && $target_product->managing_stock()) {
                wc_update_product_stock($target_product, $qty, 'decrease');
            }
        }

        $original_order_id = $refund_order->get_meta('_hizli_kasa_original_order');
        if ($original_order_id) {
            $original_order = wc_get_order($original_order_id);
            if ($original_order) {
                foreach ($refund_order->get_items() as $refund_item) {
                    if (!$refund_item instanceof WC_Order_Item_Product) {
                        continue;
                    }

                    $p_id   = intval($refund_item->get_product_id());
                    $v_id   = intval($refund_item->get_variation_id());
                    $r_qty  = abs($refund_item->get_quantity());

                    foreach ($original_order->get_items() as $orig_item_id => $orig_item) {
                        if (!$orig_item instanceof WC_Order_Item_Product) {
                            continue;
                        }

                        $match_p = ($orig_item->get_product_id() == $p_id);
                        $match_v = ($orig_item->get_variation_id() == $v_id);

                        if ($match_p && ($v_id === 0 || $match_v)) {
                            $current_refunded = (int) wc_get_order_item_meta($orig_item_id, '_hk_refunded_qty', true);
                            $new_refunded     = max(0, $current_refunded - $r_qty);
                            wc_update_order_item_meta($orig_item_id, '_hk_refunded_qty', $new_refunded);
                        }
                    }
                }

                if ($original_order->get_meta('_hk_is_fully_refunded') === 'yes') {
                    $original_order->update_meta_data('_hk_is_fully_refunded', 'no');
                    $original_order->save();
                }
            }
        }

        $current_user = wp_get_current_user();
        $full_name    = trim($current_user->first_name . ' ' . $current_user->last_name);
        $user_name    = !empty($full_name) ? $full_name : $current_user->display_name;

        $refund_order->set_status('cancelled', sprintf('İade İptal Edildi. Nedeni: %s (Kasiyer: %s)', $cancel_reason, $user_name));
        $refund_order->update_meta_data('_hizli_kasa_refund_cancelled', 'yes');
        $refund_order->update_meta_data('_hizli_kasa_refund_cancel_reason', $cancel_reason);
        $refund_order->update_meta_data('_hizli_kasa_refund_cancelled_by', $user_name);
        $refund_order->update_meta_data('_hizli_kasa_refund_cancelled_at', current_time('Y-m-d H:i:s'));
        $refund_order->save();

        $cache_version = (int) get_option('hk_reports_cache_version', '1');
        update_option('hk_reports_cache_version', (string) ($cache_version + 1));

        Hizli_Kasa_Logger::pos(
            "İade #{$refund_order_id} iptal edildi. Nedeni: {$cancel_reason}",
            [
                'refund_order_id'   => $refund_order_id,
                'original_order_id' => $original_order_id,
                'cancel_reason'     => $cancel_reason,
                'kasiyer'           => $user_name,
            ],
            'info',
            'order',
            $refund_order_id
        );

        return Hizli_Kasa_API_Response::success([
            'refund_order_id' => $refund_order_id,
            'message'         => "İade #{$refund_order_id} başarıyla iptal edildi.",
        ]);
    }
}
