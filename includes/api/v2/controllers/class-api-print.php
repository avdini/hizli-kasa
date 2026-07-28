<?php
if (!defined('ABSPATH')) {
    exit;
}

class Hizli_Kasa_API_Print extends Hizli_Kasa_API_Controller_Base {

    public function register_routes() {
        register_rest_route($this->namespace, '/print/order/(?P<id>\d+)', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_order_print_callback'],
            'permission_callback' => [$this, 'check_permission'],
        ]);

        register_rest_route($this->namespace, '/print/z-report', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_zreport_print_callback'],
            'permission_callback' => [$this, 'check_permission'],
        ]);

        register_rest_route($this->namespace, '/print/zreport', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_zreport_print_callback'],
            'permission_callback' => [$this, 'check_permission'],
        ]);

        register_rest_route($this->namespace, '/print/barcode', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'get_barcode_print_callback'],
            'permission_callback' => [$this, 'check_permission'],
        ]);
    }

    public function get_order_print_callback($request) {
        return $this->handle_request([$this, 'get_order_print'], $request);
    }

    public function get_zreport_print_callback($request) {
        return $this->handle_request([$this, 'get_zreport_print'], $request);
    }

    public function get_barcode_print_callback($request) {
        return $this->handle_request([$this, 'get_barcode_print'], $request);
    }

    protected function get_order_print($request) {
        $order_id = intval($request->get_param('id'));
        if (!$order_id) {
            return Hizli_Kasa_API_Response::error(['Sipariş ID geçerli değil.'], 400);
        }

        if (!class_exists('Hizli_Kasa_Print_Engine')) {
            require_once HIZLI_KASA_PATH . 'includes/printing/class-print-engine.php';
        }

        try {
            $result = Hizli_Kasa_Print_Engine::render('order', ['order_id' => $order_id]);
            return Hizli_Kasa_API_Response::success($result);
        } catch (Exception $e) {
            return Hizli_Kasa_API_Response::error([$e->getMessage()], 400);
        }
    }

    protected function get_zreport_print($request) {
        $kasa_no = sanitize_text_field($request->get_param('kasa_no') ?: '1');
        if (strtolower($kasa_no) === 'genel') {
            $kasa_no = 'all';
        }
        $depo_id = intval($request->get_param('depo_id') ?: 0);
        $depo_name = sanitize_text_field($request->get_param('depo_name') ?: '');
        $tarih   = sanitize_text_field($request->get_param('tarih') ?: '');
        $include_qr = filter_var($request->get_param('include_qr'), FILTER_VALIDATE_BOOLEAN);
        $include_details = $request->get_param('include_details') !== '0';

        if (!class_exists('Hizli_Kasa_Print_Engine')) {
            require_once HIZLI_KASA_PATH . 'includes/printing/class-print-engine.php';
        }

        try {
            $result = Hizli_Kasa_Print_Engine::render('zreport', [
                'kasa_no' => $kasa_no,
                'depo_id' => $depo_id,
                'depo_name' => $depo_name,
                'tarih'   => $tarih,
                'include_qr' => $include_qr,
                'include_details' => $include_details
            ]);
            return Hizli_Kasa_API_Response::success($result);
        } catch (Exception $e) {
            return Hizli_Kasa_API_Response::error([$e->getMessage()], 400);
        }
    }

    protected function get_barcode_print($request) {
        $product_id   = intval($request->get_param('product_id'));
        $variation_id = intval($request->get_param('variation_id') ?: 0);
        $qty          = max(1, intval($request->get_param('qty') ?: 1));

        if (!$product_id) {
            return Hizli_Kasa_API_Response::error(['Ürün ID geçerli değil.'], 400);
        }

        if (!class_exists('Hizli_Kasa_Print_Engine')) {
            require_once HIZLI_KASA_PATH . 'includes/printing/class-print-engine.php';
        }

        try {
            $result = Hizli_Kasa_Print_Engine::render('barcode', [
                'product_id'   => $product_id,
                'variation_id' => $variation_id,
                'qty'          => $qty
            ]);
            return Hizli_Kasa_API_Response::success($result);
        } catch (Exception $e) {
            return Hizli_Kasa_API_Response::error([$e->getMessage()], 400);
        }
    }
}
