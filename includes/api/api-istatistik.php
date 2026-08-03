<?php
if (!defined('ABSPATH')) {
    exit;
}

add_action('rest_api_init', function () {
    register_rest_route('hizli-kasa/v1', '/statistics/summary', [
        'methods'             => 'GET',
        'callback'            => 'hizli_kasa_statistics_summary',
        'permission_callback' => fn() => hizli_kasa_can_access_app()
    ]);

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
    $toplam_iskonto = 0;
    $toplam_urun_adedi = 0;
    $iskonto_siparis_sayisi = 0;
    $nakit_toplam  = 0;
    $kart_toplam   = 0;
    $iban_toplam   = 0;
    $qr_taksit_toplam = 0;
    $siparis_sayisi = 0;
    $iade_sayisi   = 0;

    $saat_map         = [];
    $gun_map          = [];
    $kasiyer_map      = [];
    $urun_map         = [];
    $iskonto_saat_map = [];
    $iskonto_gun_map  = [];

    // Sepet Dağılım Map'leri
    $sepet_tutar_map = [
        '0_100'     => ['label' => '0-100 ₺',     'count' => 0, 'total' => 0],
        '100_250'   => ['label' => '100-250 ₺',   'count' => 0, 'total' => 0],
        '250_500'   => ['label' => '250-500 ₺',   'count' => 0, 'total' => 0],
        '500_1000'  => ['label' => '500-1.000 ₺', 'count' => 0, 'total' => 0],
        '1000_plus' => ['label' => '1.000 ₺+',    'count' => 0, 'total' => 0],
    ];

    $sepet_adet_map = [
        '1'      => ['label' => '1 Ürün',   'count' => 0],
        '2_3'    => ['label' => '2-3 Ürün', 'count' => 0],
        '4_5'    => ['label' => '4-5 Ürün', 'count' => 0],
        '6_plus' => ['label' => '6+ Ürün',  'count' => 0],
    ];

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
        $o_qr_taksit   = (float) $order->get_meta('_odeme_qr_taksit');

        if ($o_qr_taksit == 0 && $order->get_payment_method() === 'qr_sanal_pos') {
            $o_qr_taksit = $order_total;
            $kart_toplam -= $order_total;
            if ($kart_toplam < 0) $kart_toplam = 0;
        }
        $qr_taksit_toplam += $o_qr_taksit;

        $order_discount = (float) $order->get_meta('_hk_toplam_iskonto');
        $order_subtotal = 0;
        $order_item_qty = 0;
        foreach ($order->get_items() as $item) {
            if ($item instanceof WC_Order_Item_Product) {
                $order_subtotal += (float) $item->get_subtotal();
                $order_item_qty += (int) $item->get_quantity();
            }
        }
        if ($order_subtotal <= 0) {
            $order_subtotal = $order_total + $order_discount;
        }

        $toplam_urun_adedi += $order_item_qty;

        if ($order_discount > 0) {
            $toplam_iskonto += $order_discount;
            $iskonto_siparis_sayisi++;
        }

        // Sepet Tutar Aralığı Dağılımı
        if ($order_total < 100) {
            $sepet_tutar_map['0_100']['count']++;
            $sepet_tutar_map['0_100']['total'] += $order_total;
        } elseif ($order_total < 250) {
            $sepet_tutar_map['100_250']['count']++;
            $sepet_tutar_map['100_250']['total'] += $order_total;
        } elseif ($order_total < 500) {
            $sepet_tutar_map['250_500']['count']++;
            $sepet_tutar_map['250_500']['total'] += $order_total;
        } elseif ($order_total < 1000) {
            $sepet_tutar_map['500_1000']['count']++;
            $sepet_tutar_map['500_1000']['total'] += $order_total;
        } else {
            $sepet_tutar_map['1000_plus']['count']++;
            $sepet_tutar_map['1000_plus']['total'] += $order_total;
        }

        // Sepet Ürün Adet Aralığı Dağılımı
        if ($order_item_qty <= 1) {
            $sepet_adet_map['1']['count']++;
        } elseif ($order_item_qty <= 3) {
            $sepet_adet_map['2_3']['count']++;
        } elseif ($order_item_qty <= 5) {
            $sepet_adet_map['4_5']['count']++;
        } else {
            $sepet_adet_map['6_plus']['count']++;
        }

        // Saatlik dağılım
        $saat_key = $created_dt->date('H:00');
        if (!isset($saat_map[$saat_key])) {
            $saat_map[$saat_key] = ['count' => 0, 'total' => 0, 'items' => 0];
        }
        $saat_map[$saat_key]['count']++;
        $saat_map[$saat_key]['total'] += $order_total;
        $saat_map[$saat_key]['items'] += $order_item_qty;

        if (!isset($iskonto_saat_map[$saat_key])) {
            $iskonto_saat_map[$saat_key] = ['etiket' => 0, 'gercek' => 0, 'iskonto' => 0];
        }
        $iskonto_saat_map[$saat_key]['etiket']  += $order_subtotal;
        $iskonto_saat_map[$saat_key]['gercek']  += $order_total;
        $iskonto_saat_map[$saat_key]['iskonto'] += $order_discount;

        // Günlük trend
        $gun_key = $created_dt->date('Y-m-d');
        if (!isset($gun_map[$gun_key])) {
            $gun_map[$gun_key] = ['count' => 0, 'total' => 0, 'items' => 0];
        }
        $gun_map[$gun_key]['count']++;
        $gun_map[$gun_key]['total'] += $order_total;
        $gun_map[$gun_key]['items'] += $order_item_qty;

        if (!isset($iskonto_gun_map[$gun_key])) {
            $iskonto_gun_map[$gun_key] = ['etiket' => 0, 'gercek' => 0, 'iskonto' => 0];
        }
        $iskonto_gun_map[$gun_key]['etiket']  += $order_subtotal;
        $iskonto_gun_map[$gun_key]['gercek']  += $order_total;
        $iskonto_gun_map[$gun_key]['iskonto'] += $order_discount;

        // Kasiyer performansı
        $kasiyer = $order->get_meta('_hizli_kasa_kasiyer') ?: 'Bilinmeyen';
        if (!isset($kasiyer_map[$kasiyer])) {
            $kasiyer_map[$kasiyer] = ['count' => 0, 'total' => 0];
        }
        $kasiyer_map[$kasiyer]['count']++;
        $kasiyer_map[$kasiyer]['total'] += $order_total;

        // Ürün dağılımı
        foreach ($order->get_items() as $item) {
            if (!$item instanceof WC_Order_Item_Product) {
                continue;
            }
            $product = $item->get_product();
            $qty  = $item->get_quantity();
            $tot  = (float) $item->get_total();

            $is_variation = $product && $product->is_type('variation');
            if ($is_variation) {
                $parent_id   = $product->get_parent_id();
                $parent_prod = wc_get_product($parent_id);
                $parent_name = $parent_prod ? $parent_prod->get_name() : $item->get_name();
                $parent_sku  = $parent_prod ? $parent_prod->get_sku() : '';
                $key = 'p_' . $parent_id;

                $var_id   = $product->get_id();
                $var_name = $item->get_name();
                $var_sku  = $product->get_sku();

                if (!isset($urun_map[$key])) {
                    $urun_map[$key] = [
                        'id'         => $parent_id,
                        'name'       => $parent_name,
                        'sku'        => $parent_sku,
                        'qty'        => 0,
                        'total'      => 0,
                        'variations' => []
                    ];
                }
                $urun_map[$key]['qty']   += $qty;
                $urun_map[$key]['total'] += $tot;

                if (!isset($urun_map[$key]['variations'][$var_id])) {
                    $urun_map[$key]['variations'][$var_id] = [
                        'id'    => $var_id,
                        'name'  => $var_name,
                        'sku'   => $var_sku,
                        'qty'   => 0,
                        'total' => 0
                    ];
                }
                $urun_map[$key]['variations'][$var_id]['qty']   += $qty;
                $urun_map[$key]['variations'][$var_id]['total'] += $tot;
            } else {
                $p_id = $product ? $product->get_id() : 0;
                $name = $item->get_name();
                $sku  = $product ? $product->get_sku() : '';
                $key  = $p_id ? ('p_' . $p_id) : ($sku ?: sanitize_title($name));

                if (!isset($urun_map[$key])) {
                    $urun_map[$key] = [
                        'id'         => $p_id,
                        'name'       => $name,
                        'sku'        => $sku,
                        'qty'        => 0,
                        'total'      => 0,
                        'variations' => []
                    ];
                }
                $urun_map[$key]['qty']   += $qty;
                $urun_map[$key]['total'] += $tot;
            }
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

    // Saatlik diziyi mağaza çalışma vardiyasına uygun sırala (06:00 – 05:00)
    $saatlik = [];
    for ($i = 0; $i < 24; $i++) {
        $h = ($i + 6) % 24;
        $k = sprintf('%02d:00', $h);
        $saatlik[] = [
            'saat'  => $k,
            'count' => $saat_map[$k]['count'] ?? 0,
            'items' => $saat_map[$k]['items'] ?? 0,
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
            'urun_adedi'     => $v['items'] ?? 0,
            'toplam'         => round($v['total'], 2),
        ];
    }

    // İskonto Trend (Tek gün ise 06:00–05:00 saatlik, çoklu gün ise günlük)
    $is_single_day = ($date_start === $date_end);
    $iskonto_noktalar = [];

    if ($is_single_day) {
        for ($i = 0; $i < 24; $i++) {
            $h = ($i + 6) % 24;
            $k = sprintf('%02d:00', $h);
            $iskonto_noktalar[] = [
                'label'   => $k,
                'etiket'  => round($iskonto_saat_map[$k]['etiket'] ?? 0, 2),
                'gercek'  => round($iskonto_saat_map[$k]['gercek'] ?? 0, 2),
                'iskonto' => round($iskonto_saat_map[$k]['iskonto'] ?? 0, 2),
            ];
        }
    } else {
        ksort($iskonto_gun_map);
        foreach ($iskonto_gun_map as $tarih => $v) {
            $iskonto_noktalar[] = [
                'label'   => date_i18n('d.m', strtotime($tarih)),
                'tarih'   => $tarih,
                'etiket'  => round($v['etiket'], 2),
                'gercek'  => round($v['gercek'], 2),
                'iskonto' => round($v['iskonto'], 2),
            ];
        }
    }

    $iskonto_trend = [
        'mod'      => $is_single_day ? 'saatlik' : 'gunluk',
        'noktalar' => $iskonto_noktalar,
    ];

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

    // Top 50 ürün (adet desc)
    uasort($urun_map, fn($a, $b) => $b['qty'] <=> $a['qty']);
    $top_urunler = array_values(array_slice($urun_map, 0, 50));
    foreach ($top_urunler as &$u) {
        $u['total'] = round($u['total'], 2);
        if (!empty($u['variations'])) {
            $vars = array_values($u['variations']);
            usort($vars, fn($a, $b) => $b['qty'] <=> $a['qty']);
            foreach ($vars as &$v) {
                $v['total'] = round($v['total'], 2);
            }
            unset($v);
            $u['variations'] = $vars;
        } else {
            $u['variations'] = [];
        }
    }
    unset($u);

    $sepet_ortalamasi      = $siparis_sayisi > 0 ? round($toplam_ciro / $siparis_sayisi, 2) : 0;
    $sepet_urun_ortalamasi = $siparis_sayisi > 0 ? round($toplam_urun_adedi / $siparis_sayisi, 1) : 0;

    $response_data = [
        'kpi' => [
            'toplam_ciro'            => round($toplam_ciro, 2),
            'toplam_iade'            => round($toplam_iade, 2),
            'toplam_masraf'          => round($toplam_masraf, 2),
            'toplam_iskonto'         => round($toplam_iskonto, 2),
            'toplam_urun_adedi'      => $toplam_urun_adedi,
            'sepet_ortalamasi'       => $sepet_ortalamasi,
            'sepet_urun_ortalamasi'  => $sepet_urun_ortalamasi,
            'iskonto_siparis_sayisi' => $iskonto_siparis_sayisi,
            'net_ciro'               => round($toplam_ciro - $toplam_iade - $toplam_masraf, 2),
            'siparis_sayisi'         => $siparis_sayisi,
            'iade_sayisi'            => $iade_sayisi,
        ],
        'odeme_dagilimi' => [
            'nakit'     => round($nakit_toplam, 2),
            'kart'      => round($kart_toplam, 2),
            'iban'      => round($iban_toplam, 2),
            'qr_taksit' => round($qr_taksit_toplam, 2),
        ],
        'saatlik_dagilim' => $saatlik,
        'gunluk_trend'    => $gunluk,
        'iskonto_trend'   => $iskonto_trend,
        'kasiyerler'      => $kasiyerler,
        'top_urunler'     => $top_urunler,
        'sepet_dagilimi'  => [
            'tutar' => array_values(array_map(function($v) {
                $v['total'] = round($v['total'], 2);
                return $v;
            }, $sepet_tutar_map)),
            'adet' => array_values($sepet_adet_map),
        ],
    ];

    if ($cache_aktif) {
        $ttl_mins = (int) get_option('hizli_kasa_reports_cache_ttl', 15);
        set_transient($cache_key, $response_data, $ttl_mins * MINUTE_IN_SECONDS);
    }

    return $response_data;
}

