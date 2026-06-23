<?php
if (!defined('ABSPATH')) {
    exit;
}

class Hizli_Kasa_API_Validate_Coupon extends Hizli_Kasa_API_Controller_Base {
    public function register_routes() {
        register_rest_route($this->namespace, '/validate-coupon', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'validate_coupon_callback'],
            'permission_callback' => [$this, 'check_permission'],
            'args'                => [
                'coupon_code' => [
                    'required'          => true,
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'phone_number' => [
                    'required'          => true,
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);
    }

    public function validate_coupon_callback($request) {
        return $this->handle_request([$this, 'validate_coupon'], $request);
    }

    protected function validate_coupon($request) {
        // 1. Rate Limiting (IP Bazlı: 10 saniyede maks 10 istek)
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $rate_key = 'hk_coupon_rate_' . md5($ip);
        $attempts = (int) get_transient($rate_key);
        if ($attempts >= 10) {
            return Hizli_Kasa_API_Response::error('Çok fazla kupon doğrulama isteği gönderildi. Lütfen bekleyin.', 429);
        }
        set_transient($rate_key, $attempts + 1, 10);

        $coupon_code = strtoupper(trim($request->get_param('coupon_code')));
        $phone_number = trim($request->get_param('phone_number'));

        // 2. Double-Spend Koruması (Transient Lock)
        $lock_key = 'hk_coupon_lock_' . md5($coupon_code);
        if (get_transient($lock_key)) {
            return Hizli_Kasa_API_Response::error('Bu kupon şu anda başka bir işlemde kullanılıyor.', 409);
        }

        // 3. Kuponu Getir
        $coupon = new WC_Coupon($coupon_code);

        if (!$coupon->get_id()) {
            return Hizli_Kasa_API_Response::error('Geçersiz kupon kodu.', 404);
        }

        if ($coupon->get_usage_count() >= $coupon->get_usage_limit() && $coupon->get_usage_limit() > 0) {
            return Hizli_Kasa_API_Response::error('Bu kupon daha önce kullanılmış.', 400);
        }

        // 4. Telefon Numarası Doğrulaması (Sadece POS'ta iade ile üretilmiş kuponlarda doğrulanır)
        $saved_phone = $coupon->get_meta('_hizli_kasa_coupon_phone', true);
        if (!empty($saved_phone)) {
            $clean_saved = preg_replace('/[^0-9]/', '', $saved_phone);
            $clean_input = preg_replace('/[^0-9]/', '', $phone_number);
            
            $saved_last10 = substr($clean_saved, -10);
            $input_last10 = substr($clean_input, -10);

            if ($saved_last10 !== $input_last10 || empty($saved_last10)) {
                return Hizli_Kasa_API_Response::error('Telefon numarası eşleşmiyor.', 403);
            }
        }

        // 5. Geçici kilit at (Sipariş işlemi bitene kadar, örn. 30 saniye)
        set_transient($lock_key, true, 30);

        return Hizli_Kasa_API_Response::success([
            'coupon_code' => $coupon_code,
            'amount'      => $coupon->get_amount(),
            'message'     => 'Kupon doğrulandı.'
        ]);
    }
}
