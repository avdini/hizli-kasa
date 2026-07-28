<?php
if (!defined('ABSPATH')) exit;

class Hizli_Kasa_ZReport_Print_Builder {

    public function build(array $params): Hizli_Kasa_Print_Payload {
        $kasa_no = $params['kasa_no'] ?? '1';
        $depo_id = isset($params['depo_id']) ? intval($params['depo_id']) : 0;
        $tarih   = !empty($params['tarih']) ? sanitize_text_field($params['tarih']) : current_time('Y-m-d');

        // Fetch day end report data from helper/function
        if (!function_exists('hizli_kasa_gun_sonu_raporu')) {
            require_once HIZLI_KASA_PATH . 'includes/api/api-raporlar.php';
        }

        $report_data = hizli_kasa_gun_sonu_raporu([
            'kasa_no' => $kasa_no,
            'depo_id' => $depo_id,
            'tarih'   => $tarih
        ]);

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

        $payload->set_extra_data($report_data);

        return $payload;
    }
}
