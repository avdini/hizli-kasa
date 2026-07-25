<?php
/**
 * Hızlı Kasa V2 API Expenses Controller
 *
 * GET /hizli-kasa/v2/expenses/summary
 *
 * @package HizliKasa
 */

if (!defined('ABSPATH')) {
    exit;
}

class Hizli_Kasa_API_Expenses extends Hizli_Kasa_API_Controller_Base {

    public function register_routes() {
        register_rest_route($this->namespace, '/expenses/summary', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_expenses_summary_callback'],
            'permission_callback' => [$this, 'check_permission'],
            'args'                => [
                'date_start' => [
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'date_end' => [
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'depo_id' => [
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);
    }

    public function get_expenses_summary_callback(WP_REST_Request $request) {
        global $wpdb;

        $date_start = sanitize_text_field($request->get_param('date_start') ?: current_time('Y-m-d'));
        $date_end   = sanitize_text_field($request->get_param('date_end') ?: current_time('Y-m-d'));
        $depo_id    = intval($request->get_param('depo_id'));

        $table = Hizli_Kasa_Database::get_tables()['masraflar'];
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
            Hizli_Kasa_Database::init();
        }

        $query = $wpdb->prepare(
            "SELECT * FROM {$table} WHERE DATE(created_at) >= %s AND DATE(created_at) <= %s",
            $date_start,
            $date_end
        );

        if ($depo_id > 0) {
            $query .= $wpdb->prepare(" AND location_id = %d", $depo_id);
        }

        $query .= " ORDER BY created_at DESC";
        $results = $wpdb->get_results($query);

        $toplam_masraf    = 0.0;
        $nakit_masraf     = 0.0;
        $kart_iban_masraf = 0.0;
        $kategori_map     = [];
        $gun_map          = [];

        foreach ($results as &$row) {
            $user_info      = get_userdata($row->user_id);
            $row->user_name = $user_info ? $user_info->display_name : 'Sistem';
            $amt            = floatval($row->amount);
            $row->amount    = $amt;

            $toplam_masraf += $amt;

            if ($row->payment_method === 'nakit') {
                $nakit_masraf += $amt;
            } else {
                $kart_iban_masraf += $amt;
            }

            $cat = !empty($row->category) ? $row->category : 'Diğer';
            if (!isset($kategori_map[$cat])) {
                $kategori_map[$cat] = [
                    'category'   => $cat,
                    'count'      => 0,
                    'total'      => 0.0,
                    'percentage' => 0,
                ];
            }
            $kategori_map[$cat]['count']++;
            $kategori_map[$cat]['total'] += $amt;

            $dt = date('Y-m-d', strtotime($row->created_at));
            if (!isset($gun_map[$dt])) {
                $gun_map[$dt] = [
                    'nakit'     => 0.0,
                    'kart_iban' => 0.0,
                    'toplam'    => 0.0,
                ];
            }
            if ($row->payment_method === 'nakit') {
                $gun_map[$dt]['nakit'] += $amt;
            } else {
                $gun_map[$dt]['kart_iban'] += $amt;
            }
            $gun_map[$dt]['toplam'] += $amt;
        }
        unset($row);

        uasort($kategori_map, function ($a, $b) {
            return $b['total'] <=> $a['total'];
        });

        foreach ($kategori_map as $cat => &$val) {
            $val['total']      = round($val['total'], 2);
            $val['percentage'] = $toplam_masraf > 0 ? round(($val['total'] / $toplam_masraf) * 100, 1) : 0;
        }
        unset($val);

        $kategori_list = array_values($kategori_map);
        $en_yuksek     = !empty($kategori_list) ? $kategori_list[0] : ['category' => 'Yok', 'total' => 0.0];

        $gunluk_trend = [];
        $period       = new DatePeriod(
            new DateTime($date_start),
            new DateInterval('P1D'),
            (new DateTime($date_end))->modify('+1 day')
        );

        foreach ($period as $d_obj) {
            $d_str = $d_obj->format('Y-m-d');
            $data  = isset($gun_map[$d_str]) ? $gun_map[$d_str] : ['nakit' => 0.0, 'kart_iban' => 0.0, 'toplam' => 0.0];
            $gunluk_trend[] = [
                'tarih'      => $d_str,
                'tarih_kisa' => $d_obj->format('d.m'),
                'nakit'      => round($data['nakit'], 2),
                'kart_iban'  => round($data['kart_iban'], 2),
                'toplam'     => round($data['toplam'], 2),
            ];
        }

        return Hizli_Kasa_API_Response::success([
            'kpi' => [
                'toplam_masraf'       => round($toplam_masraf, 2),
                'nakit_masraf'        => round($nakit_masraf, 2),
                'kart_iban_masraf'    => round($kart_iban_masraf, 2),
                'en_yuksek_kategori'  => $en_yuksek,
                'toplam_kayit_sayisi' => count($results),
            ],
            'kategori_dagilim' => $kategori_list,
            'gunluk_trend'     => $gunluk_trend,
            'masraf_listesi'   => $results,
        ]);
    }
}
