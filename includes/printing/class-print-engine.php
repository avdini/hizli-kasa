<?php
if (!defined('ABSPATH')) exit;

require_once HIZLI_KASA_PATH . 'includes/printing/class-print-payload.php';
require_once HIZLI_KASA_PATH . 'includes/printing/builder/class-order-print-builder.php';
require_once HIZLI_KASA_PATH . 'includes/printing/builder/class-zreport-print-builder.php';
require_once HIZLI_KASA_PATH . 'includes/printing/builder/class-barcode-print-builder.php';

class Hizli_Kasa_Print_Engine {

    public static function render(string $type, array $params = []): array {
        $payload = self::build_payload($type, $params);
        $html = self::render_template($payload);

        return [
            'type'          => $payload->get_type(),
            'variant'       => $payload->get_variant(),
            'order_id'      => $payload->get_order_id(),
            'payload'       => $payload->to_array(),
            'rendered_html' => $html
        ];
    }

    public static function build_payload(string $type, array $params = []): Hizli_Kasa_Print_Payload {
        switch ($type) {
            case 'order':
                $order_id = intval($params['order_id'] ?? $params['id'] ?? 0);
                $builder = new Hizli_Kasa_Order_Print_Builder();
                return $builder->build($order_id);

            case 'zreport':
                $builder = new Hizli_Kasa_ZReport_Print_Builder();
                return $builder->build($params);

            case 'barcode':
                $builder = new Hizli_Kasa_Barcode_Print_Builder();
                return $builder->build($params);

            default:
                throw new Exception("Bilinmeyen yazdırma türü: {$type}");
        }
    }

    public static function render_template(Hizli_Kasa_Print_Payload $payload): string {
        $template_name = $payload->get_template_name();
        $template_file = HIZLI_KASA_PATH . 'includes/printing/templates/' . $template_name . '.php';

        if (!file_exists($template_file)) {
            throw new Exception("Yazdırma şablonu bulunamadı: {$template_name}");
        }

        ob_start();
        $data = $payload->to_array();
        include $template_file;
        return ob_get_clean();
    }
}
