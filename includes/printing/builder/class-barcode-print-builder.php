<?php
if (!defined('ABSPATH')) exit;

class Hizli_Kasa_Barcode_Print_Builder {

    public function build(array $params): Hizli_Kasa_Print_Payload {
        $product_id = intval($params['product_id'] ?? 0);
        $variation_id = intval($params['variation_id'] ?? 0);
        $qty = max(1, intval($params['qty'] ?? 1));

        if (!class_exists('Hizli_Kasa_Barcode_Helper')) {
            require_once HIZLI_KASA_PATH . 'includes/classes/class-barcode-helper.php';
        }

        $label_data = Hizli_Kasa_Barcode_Helper::prepare_label_data($product_id, $variation_id);
        if (!$label_data) {
            throw new Exception("Barkod etiket verisi oluşturulamadı: Product {$product_id}");
        }

        $payload = new Hizli_Kasa_Print_Payload('barcode', 'standard', 0);
        $payload->set_items([
            'label' => $label_data,
            'qty'   => $qty
        ]);
        $payload->set_barcode_value($label_data['barcode_value'] ?? '');

        return $payload;
    }
}
