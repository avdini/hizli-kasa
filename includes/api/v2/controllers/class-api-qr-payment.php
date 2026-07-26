<?php
/**
 * Hızlı Kasa V2 REST API - QR Payment Controller
 *
 * Kasadan Sanal POS ile QR Taksitli Ödeme oluşturma,
 * durum sorgulama ve iptal işlemlerini yönetir.
 *
 * @package HizliKasa
 */

if (!defined('ABSPATH')) {
    exit;
}

class Hizli_Kasa_API_QR_Payment extends Hizli_Kasa_API_Controller_Base {

    public function register_routes() {
        register_rest_route($this->namespace, '/qr-payment/create', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'create_qr_payment_callback'],
            'permission_callback' => [$this, 'check_permission'],
        ]);

        register_rest_route($this->namespace, '/qr-payment/status/(?P<order_id>\d+)', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_qr_payment_status_callback'],
            'permission_callback' => [$this, 'check_permission'],
        ]);

        register_rest_route($this->namespace, '/qr-payment/cancel/(?P<order_id>\d+)', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'cancel_qr_payment_callback'],
            'permission_callback' => [$this, 'check_permission'],
        ]);
    }

    public function create_qr_payment_callback($request) {
        return $this->handle_request([$this, 'create_qr_payment'], $request);
    }

    public function get_qr_payment_status_callback($request) {
        return $this->handle_request([$this, 'get_qr_payment_status'], $request);
    }

    public function cancel_qr_payment_callback($request) {
        return $this->handle_request([$this, 'cancel_qr_payment'], $request);
    }

    /**
     * QR Taksitli Ödeme Siparişi Oluşturur (status: pending)
     */
    protected function create_qr_payment($request) {
        $params = $request->get_json_params();
        if (empty($params)) {
            $params = $request->get_params();
        }

        $line_items    = $params['line_items'] ?? [];
        $fee_lines     = $params['fee_lines'] ?? [];
        $meta_data     = $params['meta_data'] ?? [];
        $customer_note = sanitize_text_field($params['customer_note'] ?? '');
        $billing       = $params['billing'] ?? [];
        $shipping      = $params['shipping'] ?? [];

        if (empty($line_items)) {
            return Hizli_Kasa_API_Response::error('Sepette satışı yapılacak ürün bulunmamaktadır.', 400);
        }

        $order = wc_create_order();
        if (is_wp_error($order)) {
            return Hizli_Kasa_API_Response::error('Sipariş oluşturulamadı: ' . $order->get_error_message(), 500);
        }

        // 1. Ürünleri ekle
        foreach ($line_items as $item) {
            $product_id   = (int) ($item['product_id'] ?? 0);
            $variation_id = (int) ($item['variation_id'] ?? 0);
            $quantity     = (float) ($item['quantity'] ?? 1);
            $subtotal     = (float) ($item['subtotal'] ?? 0);
            $total        = (float) ($item['total'] ?? 0);

            $product = wc_get_product($variation_id ?: $product_id);
            if (!$product) {
                continue;
            }

            $item_id = $order->add_product($product, $quantity, [
                'subtotal' => $subtotal,
                'total'    => $total,
            ]);

            if ($item_id && !empty($item['meta_data']) && is_array($item['meta_data'])) {
                $order_item = $order->get_item($item_id);
                if ($order_item) {
                    foreach ($item['meta_data'] as $m) {
                        if (isset($m['key'], $m['value'])) {
                            $order_item->update_meta_data($m['key'], $m['value']);
                        }
                    }
                    $order_item->save();
                }
            }
        }

        // 2. Ekstra Ücret/İndirim Satırları (fee_lines)
        foreach ($fee_lines as $fee) {
            $fee_item = new WC_Order_Item_Fee();
            $fee_item->set_name(sanitize_text_field($fee['name'] ?? 'Ücret'));
            $fee_item->set_total((float) ($fee['total'] ?? 0));
            $fee_item->set_tax_status($fee['tax_status'] ?? 'none');
            $order->add_item($fee_item);
        }

        $current_user   = wp_get_current_user();
        $store_name     = get_bloginfo('name') ?: 'Hızlı Kasa Mağaza';
        $store_address  = get_option('woocommerce_store_address') ?: 'Merkez Mahallesi Atatürk Caddesi No 1';
        $store_city     = get_option('woocommerce_store_city') ?: 'Istanbul';
        $store_postcode = get_option('woocommerce_store_postcode') ?: '34000';
        $raw_country    = get_option('woocommerce_default_country') ?: 'TR';
        $country_parts  = explode(':', $raw_country);
        $store_country  = !empty($country_parts[0]) ? $country_parts[0] : 'TR';
        $store_state    = !empty($country_parts[1]) ? $country_parts[1] : '34';

        $first_name = sanitize_text_field($billing['first_name'] ?? ($current_user->display_name ?: 'Kasa'));
        $last_name  = sanitize_text_field($billing['last_name'] ?? 'Müşterisi');
        $phone      = !empty($billing['phone']) ? sanitize_text_field($billing['phone']) : '05555555555';
        $email      = sanitize_email($billing['email'] ?? ('pos-guest-' . time() . '@' . (parse_url(home_url(), PHP_URL_HOST) ?: 'magaza.com')));
        $raw_billing_address = !empty($billing['address_1']) ? sanitize_text_field($billing['address_1']) : '';
        if (empty($raw_billing_address) || mb_strlen(trim($raw_billing_address)) < 10 || $raw_billing_address === 'POS Satış') {
            $raw_billing_address = $store_address;
        }

        $raw_billing_city = !empty($billing['city']) ? sanitize_text_field($billing['city']) : '';
        if (empty($raw_billing_city) || $raw_billing_city === 'Mağaza') {
            $raw_billing_city = $store_city;
        }

        $billing_address_data = [
            'first_name' => $first_name,
            'last_name'  => $last_name,
            'company'    => sanitize_text_field($billing['company'] ?? $store_name),
            'address_1'  => $raw_billing_address,
            'address_2'  => sanitize_text_field($billing['address_2'] ?? ''),
            'city'       => $raw_billing_city,
            'state'      => !empty($billing['state']) ? sanitize_text_field($billing['state']) : $store_state,
            'postcode'   => !empty($billing['postcode']) ? sanitize_text_field($billing['postcode']) : $store_postcode,
            'country'    => !empty($billing['country']) ? sanitize_text_field($billing['country']) : $store_country,
            'email'      => $email,
            'phone'      => $phone,
        ];

        $raw_shipping_address = !empty($shipping['address_1']) ? sanitize_text_field($shipping['address_1']) : '';
        if (empty($raw_shipping_address) || mb_strlen(trim($raw_shipping_address)) < 10 || $raw_shipping_address === 'POS Satış') {
            $raw_shipping_address = $billing_address_data['address_1'];
        }

        $raw_shipping_city = !empty($shipping['city']) ? sanitize_text_field($shipping['city']) : '';
        if (empty($raw_shipping_city) || $raw_shipping_city === 'Mağaza') {
            $raw_shipping_city = $billing_address_data['city'];
        }

        $shipping_address_data = [
            'first_name' => sanitize_text_field($shipping['first_name'] ?? $first_name),
            'last_name'  => sanitize_text_field($shipping['last_name'] ?? $last_name),
            'company'    => sanitize_text_field($shipping['company'] ?? $store_name),
            'address_1'  => $raw_shipping_address,
            'address_2'  => sanitize_text_field($shipping['address_2'] ?? ''),
            'city'       => $raw_shipping_city,
            'state'      => !empty($shipping['state']) ? sanitize_text_field($shipping['state']) : $billing_address_data['state'],
            'postcode'   => !empty($shipping['postcode']) ? sanitize_text_field($shipping['postcode']) : $billing_address_data['postcode'],
            'country'    => !empty($shipping['country']) ? sanitize_text_field($shipping['country']) : $billing_address_data['country'],
            'phone'      => !empty($shipping['phone']) ? sanitize_text_field($shipping['phone']) : $phone,
        ];

        $order->set_address($billing_address_data, 'billing');
        $order->set_address($shipping_address_data, 'shipping');

        if ($customer_note) {
            $order->set_customer_note($customer_note);
        }

        // 4. Ödeme Yöntemi Belirleme (QR Sanal POS)
        $order->set_payment_method('qr_sanal_pos');
        $order->set_payment_method_title('QR Taksitli Ödeme');

        // 5. Özel Hızlı Kasa Meta Verileri
        $order->update_meta_data('_hizli_kasa_qr_payment', 'yes');
        $order->update_meta_data('_hizli_kasa_qr_created_at', time());

        if (is_array($meta_data)) {
            foreach ($meta_data as $m) {
                if (isset($m['key'], $m['value'])) {
                    $order->update_meta_data($m['key'], $m['value']);
                }
            }
        }

        // 6. Tutar Hesapla & Statü Belirle (PENDING)
        $order->calculate_totals(false);
        $order->set_status('pending', 'Hızlı Kasa: QR Taksitli Ödeme bekleniyor.');
        $order->save();

        // 7. Stok Düşüşünü Anında Tetikle (POS satış mantığı)
        do_action('woocommerce_new_order', $order->get_id(), $order);

        $pay_url = $order->get_checkout_payment_url();

        return Hizli_Kasa_API_Response::success([
            'order_id'        => $order->get_id(),
            'order_number'    => '#' . $order->get_order_number(),
            'pay_url'         => $pay_url,
            'total'           => number_format((float)$order->get_total(), 2, '.', ''),
            'timeout_minutes' => 15,
            'created_at'      => current_time('mysql'),
        ]);
    }

    /**
     * Bekleyen QR Ödeme Durumunu Sorgular
     */
    protected function get_qr_payment_status($request) {
        $order_id = (int) $request->get_param('order_id');
        $order    = wc_get_order($order_id);

        if (!$order) {
            return Hizli_Kasa_API_Response::error('Sipariş bulunamadı.', 404);
        }

        if ($order->get_meta('_hizli_kasa_qr_payment') !== 'yes') {
            return Hizli_Kasa_API_Response::error('Bu sipariş bir QR ödeme siparişi değil.', 400);
        }

        $status = $order->get_status();
        $created_at = (int) $order->get_meta('_hizli_kasa_qr_created_at');
        $timeout_seconds = 15 * 60; // 15 dakika

        // 1. Ödeme Tamamlandı mı? (processing veya completed)
        if (in_array($status, ['processing', 'completed'], true)) {
            $gateway_data = $this->extract_gateway_data($order);
            return Hizli_Kasa_API_Response::success([
                'status'               => 'paid',
                'order_id'             => $order->get_id(),
                'order_number'         => '#' . $order->get_order_number(),
                'total'                => number_format((float)$order->get_total(), 2, '.', ''),
                'payment_method_title' => $order->get_payment_method_title(),
                'gateway_data'         => $gateway_data,
                'message'              => 'Ödeme başarıyla alındı!',
            ]);
        }

        // 2. İptal veya Başarısız mı?
        if (in_array($status, ['cancelled', 'failed', 'refunded'], true)) {
            return Hizli_Kasa_API_Response::success([
                'status'   => 'failed',
                'order_id' => $order->get_id(),
                'message'  => 'Ödeme başarısız veya sipariş iptal edildi.',
            ]);
        }

        // 3. Bekliyor durumunda Zaman Aşımı Kontrolü
        $now = time();
        $elapsed = $now - ($created_at ?: $now);
        $remaining = max(0, $timeout_seconds - $elapsed);

        if ($elapsed > $timeout_seconds && $status === 'pending') {
            $order->set_status('cancelled', 'Hızlı Kasa: QR Ödeme 15 dakikalık zaman aşımına uğradı.');
            $order->save();

            return Hizli_Kasa_API_Response::success([
                'status'   => 'failed',
                'order_id' => $order->get_id(),
                'message'  => '15 dakikalık zaman aşımı nedeniyle sipariş otomatik iptal edildi.',
            ]);
        }

        // 4. Halen bekliyor
        return Hizli_Kasa_API_Response::success([
            'status'            => 'waiting',
            'order_id'          => $order->get_id(),
            'order_number'      => '#' . $order->get_order_number(),
            'total'             => number_format((float)$order->get_total(), 2, '.', ''),
            'elapsed_seconds'   => $elapsed,
            'remaining_seconds' => $remaining,
            'message'           => 'Ödeme bekleniyor...',
        ]);
    }

    /**
     * Kasiyer veya sistem tarafından QR Ödemeyi İptal Eder
     */
    protected function cancel_qr_payment($request) {
        $order_id = (int) $request->get_param('order_id');
        $order    = wc_get_order($order_id);

        if (!$order) {
            return Hizli_Kasa_API_Response::error('Sipariş bulunamadı.', 404);
        }

        if ($order->get_meta('_hizli_kasa_qr_payment') !== 'yes') {
            return Hizli_Kasa_API_Response::error('Bu sipariş bir QR ödeme siparişi değil.', 400);
        }

        if ($order->get_status() !== 'pending') {
            return Hizli_Kasa_API_Response::error('Sadece ödeme bekleyen (pending) siparişler iptal edilebilir.', 400);
        }

        $order->set_status('cancelled', 'Hızlı Kasa: Kasiyer tarafından QR Ödeme iptal edildi.');
        $order->save();

        return Hizli_Kasa_API_Response::success([
            'order_id' => $order->get_id(),
            'message'  => 'QR Ödeme siparişi iptal edildi.',
        ]);
    }

    /**
     * Ödeme Sağlayıcı Meta Verilerini Tarayarak Taksit & Komisyon Bilgilerini Çeker
     */
    protected function extract_gateway_data($order) {
        $data = [];

        // 1. Taksit Sayısı Taraması (iyzico, PayTR, Param, Garanti vb.)
        $iyzico_inst  = $order->get_meta('_iyzico_installment', true) ?: $order->get_meta('iyzico_installment', true);
        $paytr_inst   = $order->get_meta('_paytr_installment', true) ?: $order->get_meta('paytr_installment', true);
        $generic_inst = $order->get_meta('_installment_count', true) ?: $order->get_meta('_taksit_sayisi', true);

        $inst = (int) ($iyzico_inst ?: ($paytr_inst ?: $generic_inst));
        if ($inst > 1) {
            $data['installment']       = $inst;
            $data['installment_label'] = $inst . ' Taksit';
        } else {
            $data['installment']       = 1;
            $data['installment_label'] = 'Tek Çekim';
        }

        // 2. Net Ele Geçen Tutarlar & Komisyon Oranı Taraması
        $payout = $order->get_meta('_iyzico_merchant_payout_amount', true) 
               ?: ($order->get_meta('_iyzico_net_amount', true) 
               ?: ($order->get_meta('_paytr_net_amount', true) 
               ?: $order->get_meta('_net_payout', true)));

        $commission = $order->get_meta('_iyzico_merchant_commission_amount', true) 
                   ?: ($order->get_meta('_paytr_commission_amount', true) 
                   ?: $order->get_meta('_commission_amount', true));

        $comm_rate = $order->get_meta('_iyzico_merchant_commission_rate', true) 
                  ?: ($order->get_meta('_paytr_commission_rate', true) 
                  ?: $order->get_meta('_commission_rate', true));

        if (!empty($payout) && is_numeric($payout)) {
            $data['merchant_payout'] = wc_format_decimal($payout, 2) . ' ' . $order->get_currency();
        }

        if (!empty($commission) && is_numeric($commission)) {
            $data['commission_amount'] = wc_format_decimal($commission, 2) . ' ' . $order->get_currency();
        }

        if (!empty($comm_rate) && is_numeric($comm_rate)) {
            $data['commission_rate'] = '%' . wc_format_decimal($comm_rate, 2);
        }

        $txn_id = $order->get_transaction_id();
        if ($txn_id) {
            $data['transaction_id'] = $txn_id;
        }

        return $data;
    }
}
