<?php
if (!defined('ABSPATH')) exit;

add_action('rest_api_init', function () {
    register_rest_route('hizli-kasa/v1', '/statistics/summary', array(
        'methods'             => 'GET',
        'callback'            => 'hizli_kasa_statistics_summary',
        'permission_callback' => function () {
            return hizli_kasa_can_access_app();
        }
    ));

});

/**
 * İstatistik Dashboard — Özet Verileri
 * GET /hizli-kasa/v1/statistics/summary
 * Params: date_start, date_end, depo_id
 */
function hizli_kasa_statistics_summary($request) {
    global $wpdb;

    $date_start = sanitize_text_field($request->get_param('date_start') ?: current_time('Y-m-d'));
    $date_end   = sanitize_text_field($request->get_param('date_end')   ?: current_time('Y-m-d'));
    $depo_id    = intval($request->get_param('depo_id'));

    // --- Önbellek (Cache) Kontrolü ---
    $cache_aktif = get_option('hizli_kasa_cache_aktif', '1') === '1';
    $cache_version = get_option('hk_reports_cache_version', '1');
    $cache_key = 'hk_stats_' . $cache_version . '_' . md5($date_start . '_' . $date_end . '_' . $depo_id);

    if ($cache_aktif) {
        $cached_stats = get_transient($cache_key);
        if ($cached_stats !== false) {
            return $cached_stats;
        }
    }
    // --- Önbellek Sonu ---

    $ts_start = $date_start . ' 00:00:00';
    $ts_end   = $date_end   . ' 23:59:59';

    // --- Siparişleri çek ---
    $args = [
        'limit'        => -1,
        'status'       => ['processing', 'completed', 'on-hold'],
        'date_created' => $ts_start . '...' . $ts_end,
    ];

    $meta_query = [['key' => '_hizli_kasa_kasa_no', 'compare' => 'EXISTS']];
    if ($depo_id > 0) {
        $meta_query[] = ['key' => '_hk_cikis_depo_id', 'value' => $depo_id];
    }
    $args['meta_query'] = $meta_query;

    $orders = wc_get_orders($args);

    // Akümülatörler
    $toplam_ciro   = 0;
    $toplam_iade   = 0;
    $nakit_toplam  = 0;
    $kart_toplam   = 0;
    $iban_toplam   = 0;
    $siparis_sayisi = 0;
    $iade_sayisi   = 0;

    $saat_map    = [];   // "09:00" => ['count'=>N, 'total'=>X]
    $gun_map     = [];   // "2024-05-01" => ['count'=>N, 'total'=>X]
    $kasiyer_map = [];   // "Ad Soyad" => ['count'=>N, 'total'=>X]
    $urun_map    = [];   // sku => ['name'=>..., 'qty'=>N, 'total'=>X]

    foreach ($orders as $order) {
        $order_total = (float) $order->get_total();
        $is_refund   = ($order->get_meta('_hizli_kasa_is_refund') === 'yes');
        $created_dt  = $order->get_date_created();

        if ($is_refund) {
            $toplam_iade += abs($order_total);
            $iade_sayisi++;
            continue;
        }

        $siparis_sayisi++;
        $toplam_ciro  += $order_total;
        $nakit_toplam += (float) $order->get_meta('_odeme_nakit');
        $kart_toplam  += (float) $order->get_meta('_odeme_kart');
        $iban_toplam  += (float) $order->get_meta('_odeme_iban');

        // Saatlik dağılım
        $saat_key = $created_dt->date('H:00');
        if (!isset($saat_map[$saat_key])) $saat_map[$saat_key] = ['count' => 0, 'total' => 0];
        $saat_map[$saat_key]['count']++;
        $saat_map[$saat_key]['total'] += $order_total;

        // Günlük trend
        $gun_key = $created_dt->date('Y-m-d');
        if (!isset($gun_map[$gun_key])) $gun_map[$gun_key] = ['count' => 0, 'total' => 0];
        $gun_map[$gun_key]['count']++;
        $gun_map[$gun_key]['total'] += $order_total;

        // Kasiyer performansı
        $kasiyer = $order->get_meta('_hizli_kasa_kasiyer') ?: 'Bilinmeyen';
        if (!isset($kasiyer_map[$kasiyer])) $kasiyer_map[$kasiyer] = ['count' => 0, 'total' => 0];
        $kasiyer_map[$kasiyer]['count']++;
        $kasiyer_map[$kasiyer]['total'] += $order_total;

        // Ürün dağılımı
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            $sku  = $product ? $product->get_sku() : '';
            $name = $item->get_name();
            $qty  = $item->get_quantity();
            $tot  = (float) $item->get_total();
            $key  = $sku ?: sanitize_title($name);
            if (!isset($urun_map[$key])) {
                $urun_map[$key] = ['name' => $name, 'sku' => $sku, 'qty' => 0, 'total' => 0];
            }
            $urun_map[$key]['qty']   += $qty;
            $urun_map[$key]['total'] += $tot;
        }
    }

    // Masraflar
    $tables  = Hizli_Kasa_Database::get_tables();
    $m_table = $tables['masraflar'];
    if ($depo_id > 0) {
        $masraf_rows = $wpdb->get_results($wpdb->prepare(
            "SELECT SUM(amount) as total FROM $m_table WHERE created_at BETWEEN %s AND %s AND location_id = %d",
            $ts_start, $ts_end, $depo_id
        ));
    } else {
        $masraf_rows = $wpdb->get_results($wpdb->prepare(
            "SELECT SUM(amount) as total FROM $m_table WHERE created_at BETWEEN %s AND %s",
            $ts_start, $ts_end
        ));
    }
    $toplam_masraf = (float) ($masraf_rows[0]->total ?? 0);

    // Saatlik diziyi tüm saatleri kapsayacak şekilde doldur (0–23)
    $saatlik = [];
    for ($h = 0; $h < 24; $h++) {
        $k = sprintf('%02d:00', $h);
        $saatlik[] = [
            'saat'  => $k,
            'count' => $saat_map[$k]['count'] ?? 0,
            'total' => round($saat_map[$k]['total'] ?? 0, 2),
        ];
    }

    // Günlük trendi tarih sırasına koy
    ksort($gun_map);
    $gunluk = [];
    foreach ($gun_map as $tarih => $v) {
        $gunluk[] = [
            'tarih'          => $tarih,
            'tarih_kisa'     => date_i18n('d.m', strtotime($tarih)),
            'siparis_sayisi' => $v['count'],
            'toplam'         => round($v['total'], 2),
        ];
    }

    // Kasiyer sıralaması (ciro desc)
    uasort($kasiyer_map, fn($a, $b) => $b['total'] <=> $a['total']);
    $kasiyerler = [];
    foreach ($kasiyer_map as $isim => $v) {
        $kasiyerler[] = [
            'isim'           => $isim,
            'siparis_sayisi' => $v['count'],
            'toplam'         => round($v['total'], 2),
        ];
    }

    // Top 10 ürün (adet desc)
    uasort($urun_map, fn($a, $b) => $b['qty'] <=> $a['qty']);
    $top_urunler = array_values(array_slice($urun_map, 0, 10));
    foreach ($top_urunler as &$u) {
        $u['total'] = round($u['total'], 2);
    }
    unset($u);

    $response_data = [
        'kpi' => [
            'toplam_ciro'    => round($toplam_ciro, 2),
            'toplam_iade'    => round($toplam_iade, 2),
            'toplam_masraf'  => round($toplam_masraf, 2),
            'net_ciro'       => round($toplam_ciro - $toplam_iade - $toplam_masraf, 2),
            'siparis_sayisi' => $siparis_sayisi,
            'iade_sayisi'    => $iade_sayisi,
        ],
        'odeme_dagilimi' => [
            'nakit' => round($nakit_toplam, 2),
            'kart'  => round($kart_toplam, 2),
            'iban'  => round($iban_toplam, 2),
        ],
        'saatlik_dagilim' => $saatlik,
        'gunluk_trend'    => $gunluk,
        'kasiyerler'      => $kasiyerler,
        'top_urunler'     => $top_urunler,
    ];

    if (isset($cache_aktif) && $cache_aktif) {
        $ttl_mins = (int) get_option('hizli_kasa_reports_cache_ttl', 15);
        set_transient($cache_key, $response_data, $ttl_mins * MINUTE_IN_SECONDS);
    }

    return $response_data;
}

