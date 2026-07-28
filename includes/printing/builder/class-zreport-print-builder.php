<?php
if (!defined('ABSPATH')) exit;

class Hizli_Kasa_ZReport_Print_Builder {

    public function build(array $params): Hizli_Kasa_Print_Payload {
        $kasa_no = $params['kasa_no'] ?? '1';
        $depo_id = isset($params['depo_id']) ? intval($params['depo_id']) : 0;
        $depo_name = sanitize_text_field($params['depo_name'] ?? '');
        $tarih   = !empty($params['tarih']) ? sanitize_text_field($params['tarih']) : current_time('Y-m-d');
        $include_qr = !empty($params['include_qr']);
        $include_details = !isset($params['include_details']) || $params['include_details'] !== false;

        if (!function_exists('hizli_kasa_gun_sonu_raporu')) {
            require_once HIZLI_KASA_PATH . 'includes/api/api-raporlar.php';
        }

        $request = new WP_REST_Request('GET', '/hizli-kasa/v1/gun-sonu-raporu');
        $request->set_query_params([
            'kasa_no' => $kasa_no,
            'depo_id' => $depo_id,
            'depo_name' => $depo_name,
            'tarih'   => $tarih
        ]);
        $report_data = hizli_kasa_gun_sonu_raporu($request);

        if (is_wp_error($report_data)) {
            throw new Exception($report_data->get_error_message());
        }

        $payload = new Hizli_Kasa_Print_Payload('zreport', 'standard', 0);

        $payload->set_header([
            'store_name'  => get_bloginfo('name'),
            'title'       => 'GÜN SONU (Z-RAPORU)',
            'kasa_no'     => $kasa_no,
            'depo_id'     => $depo_id,
            'tarih'       => $tarih,
            'report_time' => current_time('Y-m-d H:i:s')
        ]);

        $report_data['print_options'] = [
            'include_qr' => $include_qr,
            'include_details' => $include_details,
            'depo_name' => $depo_name
        ];
        $payload->set_extra_data($report_data);

        return $payload;
    }
}
