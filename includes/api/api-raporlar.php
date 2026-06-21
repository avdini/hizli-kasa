<?php
if (!defined('ABSPATH')) exit;

add_action('rest_api_init', function () {
    register_rest_route('hizli-kasa/v1', '/gun-sonu-raporu', array(
        'methods' => 'GET',
        'callback' => 'hizli_kasa_gun_sonu_raporu',
        'permission_callback' => function () {
            return hizli_kasa_can_access_app();
        }
    ));

    register_rest_route('hizli-kasa/v1', '/reports/orders', array(
        'methods' => 'GET',
        'callback' => 'hizli_kasa_get_reports_orders',
        'permission_callback' => function () {
            return hizli_kasa_can_access_app();
        }
    ));

    register_rest_route('hizli-kasa/v1', '/reports/refunds', array(
        'methods' => 'GET',
        'callback' => 'hizli_kasa_get_reports_refunds',
        'permission_callback' => function () {
            return hizli_kasa_can_access_app();
        }
    ));

    register_rest_route('hizli-kasa/v1', '/reports/internet-orders', array(
        'methods' => 'GET',
        'callback' => 'hizli_kasa_get_reports_internet_orders',
        'permission_callback' => function () {
            return hizli_kasa_can_access_app();
        }
    ));

    register_rest_route('hizli-kasa/v1', '/reports/order-receipt/(?P<id>\d+)', array(
        'methods' => 'GET',
        'callback' => 'hizli_kasa_get_reports_order_receipt',
        'permission_callback' => function () {
            return hizli_kasa_can_access_app();
        }
    ));

    register_rest_route('hizli-kasa/v1', '/reports/day-end-history', array(
        'methods' => 'GET',
        'callback' => 'hizli_kasa_get_day_end_history',
        'permission_callback' => function () {
            return hizli_kasa_can_access_app();
        }
    ));

});

/**
 * Gün Sonu Raporu API endpoint'i.
 */
function hizli_kasa_gun_sonu_raporu($request)
{
    $kasa_no = sanitize_text_field($request->get_param('kasa_no'));
    $tarih = sanitize_text_field($request->get_param('tarih'));

    if (empty($kasa_no)) {
        return new WP_Error('missing_param', 'kasa_no parametresi gerekli.', array('status' => 400));
    }

    $is_general = ($kasa_no === 'all');

    if (empty($tarih)) {
        $tarih = current_time('Y-m-d');
    }

    $tarih_baslangic = $tarih . ' 00:00:00';
    $tarih_bitis = $tarih . ' 23:59:59';

    $depo_id = intval($request->get_param('depo_id'));

    // --- Önbellek (Cache) Kontrolü ---
    $cache_aktif = get_option('hizli_kasa_cache_aktif', '1') === '1';
    $cache_version = get_option('hk_reports_cache_version', '1');
    $cache_key = 'hk_gs_' . $cache_version . '_' . md5($kasa_no . '_' . $tarih . '_' . $depo_id);

    if ($cache_aktif) {
        $cached_report = get_transient($cache_key);
        if ($cached_report !== false) {
            return $cached_report;
        }
    }
    // --- Önbellek Sonu ---

    $args = array(
        'limit' => -1,
        'status' => array('processing', 'completed', 'on-hold'),
        'date_created' => $tarih_baslangic . '...' . $tarih_bitis,
        'orderby' => 'date',
        'order' => 'ASC',
    );

    $meta_query = array();

    if (!$is_general) {
        $meta_query[] = array(
            'key' => '_hizli_kasa_kasa_no',
            'value' => $kasa_no,
        );
    } else {
        $meta_query[] = array(
            'key' => '_hizli_kasa_kasa_no',
            'compare' => 'EXISTS',
        );
    }

    if ($depo_id > 0) {
        $meta_query[] = array(
            'key' => '_hk_cikis_depo_id',
            'value' => $depo_id,
        );
    }

    if (!empty($meta_query)) {
        $args['meta_query'] = $meta_query;
    }

    $orders = wc_get_orders($args);

    if (empty($orders)) {
        return array(
            'kasa_no' => ($kasa_no === 'all') ? 'Genel' : $kasa_no,
            'tarih' => $tarih,
            'siparis_sayisi' => 0,
            'siparisler' => array(),
            'ozet' => array(
                'toplam_ciro' => 0,
                'toplam_iskonto' => 0,
                'nakit_toplam' => 0,
                'kart_toplam' => 0,
                'iban_toplam' => 0,
                'urun_adet_toplam' => 0,
            ),
            'urun_dagilimi' => array(),
            'kasiyerler' => array(),
        );
    }

    $siparisler = array();
    $nakit_toplam = 0;
    $kart_toplam = 0;
    $iban_toplam = 0;
    $kupon_toplam = 0;
    $toplam_ciro = 0;
    $toplam_iskonto = 0;
    $urun_adet = 0;
    $urun_map = array();
    $kasiyer_map = array();
    $saat_map = array();

    $iade_siparisler = array();
    $iade_toplam = 0;
    $iade_adet = 0;
    $iade_nakit = 0;
    $iade_kart = 0;
    $iade_iban = 0;
    $iade_kupon = 0;

    foreach ($orders as $order) {
        $order_id = $order->get_id();
        $order_total = (float) $order->get_total();

        $o_nakit = (float) $order->get_meta('_odeme_nakit');
        $o_kart = (float) $order->get_meta('_odeme_kart');
        $o_iban = (float) $order->get_meta('_odeme_iban');
        $o_kupon = (float) $order->get_meta('_odeme_coupon');

        $kasiyer = $order->get_meta('_hizli_kasa_kasiyer') ?: 'Bilinmeyen';
        $odeme_tipi = $order->get_payment_method_title();

        $is_refund = ($order->get_meta('_hizli_kasa_is_refund') === 'yes');

        if ($is_refund) {
            $iade_toplam += abs($order_total);
            $iade_adet++;
            $iade_nakit += abs($o_nakit);
            $iade_kart += abs($o_kart);
            $iade_iban += abs($o_iban);
            $iade_kupon += abs($o_kupon);

            $iade_siparisler[] = array(
                'id' => $order_id,
                'saat' => $order->get_date_created()->date('H:i'),
                'toplam' => abs($order_total),
                'odeme_tipi' => $odeme_tipi,
                'kasiyer' => $kasiyer
            );
            continue;
        }

        $toplam_ciro += $order_total;
        $nakit_toplam += $o_nakit;
        $kart_toplam += $o_kart;
        $iban_toplam += $o_iban;
        $kupon_toplam += $o_kupon;

        if (!isset($kasiyer_map[$kasiyer]))
            $kasiyer_map[$kasiyer] = 0;
        $kasiyer_map[$kasiyer]++;

        $saat = $order->get_date_created()->date('H:00');
        if (!isset($saat_map[$saat]))
            $saat_map[$saat] = 0;
        $saat_map[$saat]++;

        // Gün sonu raporunda, sipariş düzenleme ve iade akışlarıyla aynı
        // indirim hesabını kullanarak Türkçe karakter / negatif fee farklarını önle.
        $iskonto = hizli_kasa_get_order_total_discount($order);
        $toplam_iskonto += $iskonto;

        $urunler = array();
        foreach ($order->get_items() as $item) {
            $qty = $item->get_quantity();
            $total = (float) $item->get_total();
            $name = $item->get_name();
            $sku = '';

            $product = $item->get_product();
            if ($product) {
                $sku = $product->get_sku();
            }

            $urun_adet += $qty;

            $key = $sku ?: sanitize_title($name);
            if (!isset($urun_map[$key])) {
                $urun_map[$key] = array('name' => $name, 'sku' => $sku, 'qty' => 0, 'total' => 0);
            }
            $urun_map[$key]['qty'] += $qty;
            $urun_map[$key]['total'] += $total;

            $urunler[] = array(
                'name' => $name,
                'sku' => $sku,
                'qty' => $qty,
                'total' => $total,
            );
        }

        $odeme_tipi = $order->get_payment_method_title();

        $siparisler[] = array(
            'id' => $order_id,
            'saat' => $order->get_date_created()->date('H:i'),
            'toplam' => $order_total,
            'odeme_tipi' => $odeme_tipi,
            'nakit' => $o_nakit,
            'kart' => $o_kart,
            'iban' => $o_iban,
            'kupon' => $o_kupon,
            'iskonto' => $iskonto,
            'kasiyer' => $kasiyer,
            'urunler' => $urunler,
        );
    }

    global $wpdb;
    $masraf_table = Hizli_Kasa_Database::get_tables()['masraflar'];
    $toplam_masraf = 0;
    $nakit_masraf = 0;
    $kart_masraf = 0;
    $iban_masraf = 0;
    $masraf_listesi = array();

    if ($is_general || $depo_id > 0) {
        if ($depo_id > 0) {
            $m_query = $wpdb->prepare("SELECT category, amount, payment_method, description FROM $masraf_table WHERE DATE(created_at) = %s AND location_id = %d ORDER BY created_at ASC", $tarih, $depo_id);
        } else {
            $m_query = $wpdb->prepare("SELECT category, amount, payment_method, description FROM $masraf_table WHERE DATE(created_at) = %s ORDER BY created_at ASC", $tarih);
        }
        
        $masraflar_raw = $wpdb->get_results($m_query);

        foreach ($masraflar_raw as $m) {
            $amt = (float) $m->amount;
            $toplam_masraf += $amt;
            if ($m->payment_method === 'nakit') {
                $nakit_masraf += $amt;
            } elseif ($m->payment_method === 'kart') {
                $kart_masraf += $amt;
            } elseif ($m->payment_method === 'iban') {
                $iban_masraf += $amt;
            }
            $masraf_listesi[] = array(
                'kategori' => $m->category,
                'aciklama' => $m->description,
                'yontem' => $m->payment_method,
                'tutar' => $amt
            );
        }
    }

    uasort($urun_map, function ($a, $b) {
        return $b['qty'] - $a['qty'];
    });

    $report_data = array(
        'kasa_no' => ($kasa_no === 'all') ? 'Genel' : $kasa_no,
        'tarih' => $tarih,
        'tarih_okunabilir' => date_i18n('d.m.Y l', strtotime($tarih)),
        'rapor_zamani' => current_time('d.m.Y H:i:s'),
        'siparis_sayisi' => count($siparisler),
        'siparisler' => $siparisler,
        'ozet' => array(
            'toplam_ciro' => round($toplam_ciro, 2),
            'toplam_iskonto' => round($toplam_iskonto, 2),
            'nakit_toplam' => round($nakit_toplam, 2),
            'kart_toplam' => round($kart_toplam, 2),
            'iban_toplam' => round($iban_toplam, 2),
            'toplam_masraf' => round($toplam_masraf, 2),
            'nakit_masraf' => round($nakit_masraf, 2),
            'kart_masraf' => round($kart_masraf, 2),
            'iban_masraf' => round($iban_masraf, 2),
            'net_nakit' => round($nakit_toplam - $nakit_masraf - $iade_nakit, 2),
            'net_kart' => round($kart_toplam - $kart_masraf - $iade_kart, 2),
            'net_iban' => round($iban_toplam - $iban_masraf - $iade_iban, 2),
            'urun_adet_toplam' => $urun_adet,
            'toplam_iade' => round($iade_toplam, 2),
            'iade_adet' => $iade_adet,
            'iade_nakit' => round($iade_nakit, 2),
            'iade_kart' => round($iade_kart, 2),
            'iade_iban' => round($iade_iban, 2),
            'iade_kupon' => round($iade_kupon, 2),
            'kupon_toplam' => round($kupon_toplam ?? 0, 2),
        ),
        'iade_siparisler' => $iade_siparisler,
        'masraf_detay' => $masraf_listesi,
        'urun_dagilimi' => array_values($urun_map),
        'kasiyerler' => $kasiyer_map,
        'saat_dagilimi' => $saat_map,
    );

    if (isset($cache_aktif) && $cache_aktif) {
        $ttl_mins = (int) get_option('hizli_kasa_reports_cache_ttl', 15);
        set_transient($cache_key, $report_data, $ttl_mins * MINUTE_IN_SECONDS);
    }

    return $report_data;
}

/**
 * Raporlar için tüm Kasa siparişlerini getirir.
 */
function hizli_kasa_get_reports_orders($request)
{
    return hizli_kasa_get_reports_data($request, 'pos_orders');
}

/**
 * Raporlar için tüm POS iadelerini getirir.
 */
function hizli_kasa_get_reports_refunds($request)
{
    return hizli_kasa_get_reports_data($request, 'pos_refunds');
}

/**
 * Raporlar için tüm internet siparişlerini getirir.
 */
function hizli_kasa_get_reports_internet_orders($request)
{
    return hizli_kasa_get_reports_data($request, 'internet_orders');
}

/**
 * Raporlar sekmesinden izole fiş yazdırma için güncel sipariş snapshot'u döner.
 * Bu çıktı kasa anlık satış fişinden bağımsızdır, sadece rapor kullanımına yöneliktir.
 */
function hizli_kasa_get_reports_order_receipt($request)
{
    $order_id = (int) $request->get_param('id');
    $order = wc_get_order($order_id);

    if (!$order) {
        return new WP_Error('no_order', 'Sipariş bulunamadı.', array('status' => 404));
    }

    if ($order->get_meta('_hizli_kasa_is_refund') === 'yes') {
        return new WP_Error('invalid_order', 'İade siparişi için bu fiş oluşturulamaz.', array('status' => 400));
    }

    $get_meta_value = function ($meta_array, $key, $default = '') {
        foreach ((array) $meta_array as $meta_obj) {
            if (!is_object($meta_obj) || !isset($meta_obj->key)) {
                continue;
            }
            if ($meta_obj->key === $key) {
                return $meta_obj->value;
            }
        }
        return $default;
    };

    $refund_orders = wc_get_orders(array(
        'limit' => -1,
        'status' => array('processing', 'completed', 'on-hold'),
        'meta_key' => '_hizli_kasa_original_order',
        'meta_value' => (string) $order_id,
    ));

    $refunded_qty_map = array();
    $refunded_total_map = array();
    $refund_total_abs = 0.0;
    $refunded_manual_discount = 0.0;
    $payment_adjustments = array('nakit' => 0.0, 'kart' => 0.0, 'iban' => 0.0);

    foreach ($refund_orders as $refund_order) {
        if (!$refund_order instanceof WC_Order) {
            continue;
        }

        $refund_total_abs += abs((float) $refund_order->get_total());
        $refunded_manual_discount += (float) $refund_order->get_meta('_hk_refunded_discount');

        $payment_adjustments['nakit'] += (float) $refund_order->get_meta('_odeme_nakit');
        $payment_adjustments['kart'] += (float) $refund_order->get_meta('_odeme_kart');
        $payment_adjustments['iban'] += (float) $refund_order->get_meta('_odeme_iban');

        foreach ($refund_order->get_items() as $refund_item) {
            $product_id = (int) $refund_item->get_product_id();
            $variation_id = (int) $refund_item->get_variation_id();
            $key = $product_id . ':' . $variation_id;

            $qty_abs = abs((float) $refund_item->get_quantity());
            $total_abs = abs((float) $refund_item->get_total());

            if (!isset($refunded_qty_map[$key])) {
                $refunded_qty_map[$key] = 0.0;
            }
            if (!isset($refunded_total_map[$key])) {
                $refunded_total_map[$key] = 0.0;
            }

            $refunded_qty_map[$key] += $qty_abs;
            $refunded_total_map[$key] += $total_abs;
        }
    }

    global $wpdb;
    $tables = Hizli_Kasa_Database::get_tables();
    $order_edits_table = $tables['order_edits'];
    $edit_count = 0;
    if (!empty($order_edits_table)) {
        $edit_count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$order_edits_table} WHERE order_id = %d",
            $order_id
        ));
    }

    $items = array();
    $current_etiket_toplami = 0.0;
    $current_ara_toplam = 0.0;

    foreach ($order->get_items() as $item) {
        $product_id = (int) $item->get_product_id();
        $variation_id = (int) $item->get_variation_id();
        $key = $product_id . ':' . $variation_id;

        $original_qty = (float) $item->get_quantity();
        $original_total = (float) $item->get_total();
        $refunded_qty = isset($refunded_qty_map[$key]) ? (float) $refunded_qty_map[$key] : 0.0;
        $refunded_total = isset($refunded_total_map[$key]) ? (float) $refunded_total_map[$key] : 0.0;

        $current_qty = max(0.0, $original_qty - $refunded_qty);
        if ($current_qty <= 0.00001) {
            continue;
        }

        $current_total = max(0.0, $original_total - $refunded_total);
        $item_meta = $item->get_meta_data();
        $etiket_unit = (float) $get_meta_value($item_meta, '_etiket_fiyat', ($original_qty > 0 ? $original_total / $original_qty : 0));
        $kampanya_unit = (float) $get_meta_value($item_meta, '_kampanya_fiyat', ($original_qty > 0 ? $original_total / $original_qty : 0));

        $line_etiket_total = $etiket_unit * $current_qty;
        $line_kampanya_total = $kampanya_unit * $current_qty;

        $product = $item->get_product();
        $sku = $product ? $product->get_sku() : '';

        $items[] = array(
            'name' => $item->get_name(),
            'sku' => $sku ?: '',
            'quantity' => (float) $current_qty,
            'line_total' => round($current_total, 2),
            'etiket_total' => round($line_etiket_total, 2),
            'kampanya_total' => round($line_kampanya_total, 2),
        );

        $current_etiket_toplami += $line_etiket_total;
        $current_ara_toplam += $line_kampanya_total;
    }

    $original_total = (float) $order->get_total();
    $current_total = max(0.0, $original_total - $refund_total_abs);
    $manual_discount = max(0.0, hizli_kasa_get_order_manual_discount($order) - $refunded_manual_discount);
    $auto_discount = max(0.0, $current_ara_toplam - $current_total - $manual_discount);
    $has_adjustment = ($refund_total_abs > 0.00001) || ($edit_count > 0);

    $payment = array(
        'nakit' => (float) $order->get_meta('_odeme_nakit') + $payment_adjustments['nakit'],
        'kart' => (float) $order->get_meta('_odeme_kart') + $payment_adjustments['kart'],
        'iban' => (float) $order->get_meta('_odeme_iban') + $payment_adjustments['iban'],
    );

    return array(
        'order_id' => $order_id,
        'order_number' => $order->get_order_number(),
        'barcode_value' => (string) $order_id,
        'created_at' => $order->get_date_created() ? $order->get_date_created()->date('d.m.Y H:i') : '',
        'printed_at' => current_time('d.m.Y H:i:s'),
        'has_refund_adjustment' => $refund_total_abs > 0.00001,
        'has_edit_adjustment' => $edit_count > 0,
        'has_adjustment' => $has_adjustment,
        'cashier' => $order->get_meta('_hizli_kasa_kasiyer') ?: 'Bilinmiyor',
        'kasa_no' => $order->get_meta('_hizli_kasa_kasa_no') ?: 'Bilinmiyor',
        'items' => $items,
        'adjustments' => array(
            'refund_total' => round($refund_total_abs, 2),
            'edit_count' => $edit_count,
            'impact_total' => round($original_total - $current_total, 2),
        ),
        'totals' => array(
            'etiket_toplami' => round($current_etiket_toplami, 2),
            'ara_toplam' => round($current_ara_toplam, 2),
            'auto_discount' => round($auto_discount, 2),
            'manual_discount' => round($manual_discount, 2),
            'genel_toplam' => round($current_total, 2),
        ),
        'payment' => array(
            'nakit' => round($payment['nakit'], 2),
            'kart' => round($payment['kart'], 2),
            'iban' => round($payment['iban'], 2),
        ),
    );
}

/**
 * Rapor verilerini çeken ortak fonksiyon.
 */
function hizli_kasa_get_reports_data($request, $report_type = 'pos_orders')
{
    $paged = $request->get_param('page') ? intval($request->get_param('page')) : 1;
    $per_page = $request->get_param('per_page') ? intval($request->get_param('per_page')) : 20;
    if ($per_page < 1)
        $per_page = 20;

    $date_start = $request->get_param('date_start');
    $date_end = $request->get_param('date_end');
    $search = $request->get_param('search');

    $args = array(
        'limit' => $per_page,
        'page' => $paged,
        'paginate' => true,
        'status' => array('processing', 'completed', 'on-hold'),
        'orderby' => 'date',
        'order' => 'DESC',
    );

    // Tarih Filtresi
    if ($date_start && $date_end) {
        $args['date_created'] = $date_start . '...' . $date_end;
    }

    $meta_query = array();

    // Rapor Türüne Göre Filtreleme
    if ($report_type === 'pos_orders' || $report_type === 'pos_refunds') {
        // Sadece POS Siparişlerini Getir
        $meta_query[] = array(
            'key' => '_hizli_kasa_kasa_no',
            'compare' => 'EXISTS',
        );

        $depo_id = intval($request->get_param('depo_id'));
        if ($depo_id > 0) {
            $meta_query[] = array(
                'key' => '_hk_cikis_depo_id',
                'value' => $depo_id,
            );
        }

        // İade / Satış Ayrımı
        if ($report_type === 'pos_refunds') {
            $meta_query[] = array(
                'key' => '_hizli_kasa_is_refund',
                'value' => 'yes',
                'compare' => '=',
            );
        } else {
            $meta_query[] = array(
                'key' => '_hizli_kasa_is_refund',
                'compare' => 'NOT EXISTS',
            );
        }
    } else if ($report_type === 'internet_orders') {
        // POS Dışı (İnternet) Siparişleri Getir
        $meta_query[] = array(
            'key' => '_hizli_kasa_kasa_no',
            'compare' => 'NOT EXISTS',
        );
    }

    if (!empty($meta_query)) {
        $meta_query['relation'] = 'AND';
        $args['meta_query'] = $meta_query;
    }

    // Arama
    if ($search) {
        $args['s'] = $search;
    }

    try {
        $results = wc_get_orders($args);

        $orders = is_object($results) && isset($results->orders) ? $results->orders : (is_array($results) ? $results : array());
        $total_count = is_object($results) && isset($results->total) ? $results->total : count($orders);
        $max_pages = is_object($results) && isset($results->max_num_pages) ? $results->max_num_pages : 1;
    } catch (Throwable $e) {
        return array('orders' => array(), 'error' => $e->getMessage(), 'total' => 0);
    }

    $data = array();
    foreach ($orders as $order) {
        if (!$order instanceof WC_Order)
            continue;

        // HPOS Güvenlik Filtresi
        $order_is_refund = ($order->get_meta('_hizli_kasa_is_refund') === 'yes');
        $has_kasa_no = $order->get_meta('_hizli_kasa_kasa_no') ? true : false;

        if ($report_type === 'pos_refunds' && (!$order_is_refund || !$has_kasa_no)) continue;
        if ($report_type === 'pos_orders' && ($order_is_refund || !$has_kasa_no)) continue;
        if ($report_type === 'internet_orders' && $has_kasa_no) continue;

        $date_created = $order->get_date_created();
        $date_str = $date_created ? $date_created->date('Y-m-d H:i:s') : 'Bilinmiyor';

        $order_data = array(
            'id' => $order->get_id(),
            'date' => $date_str,
            'total' => $order->get_total(),
            'cashier' => $order->get_meta('_hizli_kasa_kasiyer') ?: ($has_kasa_no ? 'Bilinmeyen' : 'Müşteri'),
            'kasa_no' => $order->get_meta('_hizli_kasa_kasa_no') ?: ($has_kasa_no ? 'Bilinmeyen' : 'Online'),
            'depo_id' => (int) $order->get_meta('_hk_cikis_depo_id'),
            'depo_adi' => $order->get_meta('_hk_cikis_depo_adi') ?: '-',
            'status' => wc_get_order_status_name($order->get_status()),
            'customer' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
            'payment' => $order->get_payment_method_title(),
            'items' => array(),
            'meta' => array(),
        );

        // Ürünleri topla
        foreach ($order->get_items() as $item) {
            $product_meta = array();
            $all_item_meta = $item->get_meta_data(); // FIX: get_all_meta_data -> get_meta_data
            foreach ($all_item_meta as $m) {
                if (strpos($m->key, '_hk_') === 0 || strpos($m->key, '_hizli_kasa') === 0) {
                    $product_meta[$m->key] = $m->value;
                }
            }

            $qty = $item->get_quantity();
            $total = (float) $item->get_total();
            $unit_price = ($qty != 0) ? $total / $qty : 0;
            $product = $item->get_product();
            $image_url = '';
            if ($product) {
                $image_id = $product->get_image_id();
                if (!$image_id && $product->is_type('variation')) {
                    $parent_id = $product->get_parent_id();
                    if ($parent_id) {
                        $parent_product = wc_get_product($parent_id);
                        if ($parent_product) {
                            $image_id = $parent_product->get_image_id();
                        }
                    }
                }
                if ($image_id) {
                    $image_url = wp_get_attachment_image_url($image_id, 'thumbnail') ?: '';
                }
            }

            $order_data['items'][] = array(
                'name' => $item->get_name(),
                'sku' => $product ? $product->get_sku() : '',
                'qty' => $qty,
                'price' => round($unit_price, 2),
                'subtotal' => round($total, 2),
                'meta' => $product_meta,
                'image' => $image_url,
            );
        }

        $all_meta = $order->get_meta_data();
        foreach ($all_meta as $m) {
            $key = $m->key;
            if (strpos($key, '_hizli_kasa') === 0 || strpos($key, '_hk_') === 0 || strpos($key, '_odeme_') === 0 || strpos($key, 'Ödeme (') === 0) {
                $order_data['meta'][$key] = $m->value;
            }
        }

        $data[] = $order_data;
    }

    return array(
        'orders' => $data,
        'total' => $total_count,
        'max_pages' => $max_pages,
        'page' => $paged
    );
}

/**
 * Gün sonu arşivi için günlük özetleri döner.
 */
function hizli_kasa_get_day_end_history($request)
{
    global $wpdb;
    
    $date_start = $request->get_param('date_start');
    $date_end = $request->get_param('date_end');

    if (!$date_start || !$date_end) {
        $date_start = date('Y-m-d', strtotime('-30 days'));
        $date_end = current_time('Y-m-d');
    }

    $depo_id = intval($request->get_param('depo_id'));
    $depo_where = "";
    if ($depo_id > 0) {
        $depo_where = $wpdb->prepare(" AND pm_depo.meta_value = %d ", $depo_id);
    }

    $sql = $wpdb->prepare("
        SELECT 
            DATE(p.post_date) as order_date,
            COUNT(CASE WHEN pm_refund.meta_value IS NULL OR pm_refund.meta_value != 'yes' THEN p.ID END) as sale_count,
            SUM(CASE WHEN pm_refund.meta_value IS NULL OR pm_refund.meta_value != 'yes' THEN pm_total.meta_value ELSE 0 END) as total_sales,
            SUM(CASE WHEN pm_refund.meta_value = 'yes' THEN ABS(pm_total.meta_value) ELSE 0 END) as total_refunds
        FROM {$wpdb->posts} p
        INNER JOIN {$wpdb->postmeta} pm_pos ON p.ID = pm_pos.post_id AND pm_pos.meta_key = '_hizli_kasa_kasa_no'
        LEFT JOIN {$wpdb->postmeta} pm_refund ON p.ID = pm_refund.post_id AND pm_refund.meta_key = '_hizli_kasa_is_refund'
        LEFT JOIN {$wpdb->postmeta} pm_total ON p.ID = pm_total.post_id AND pm_total.meta_key = '_order_total'
        " . ($depo_id > 0 ? "INNER JOIN {$wpdb->postmeta} pm_depo ON p.ID = pm_depo.post_id AND pm_depo.meta_key = '_hk_cikis_depo_id'" : "") . "
        WHERE p.post_type = 'shop_order' 
          AND p.post_status IN ('wc-processing', 'wc-completed', 'wc-on-hold')
          AND DATE(p.post_date) BETWEEN %s AND %s
          $depo_where
        GROUP BY DATE(p.post_date)
        ORDER BY order_date DESC
    ", $date_start, $date_end);

    $results = $wpdb->get_results($sql);

    $formatted = [];
    foreach ($results as $row) {
        $sales = (float)$row->total_sales;
        $refunds = (float)$row->total_refunds;
        $formatted[] = [
            'date' => $row->order_date,
            'date_formatted' => date_i18n('d.m.Y l', strtotime($row->order_date)),
            'sale_count' => (int)$row->sale_count,
            'total_sales' => round($sales, 2),
            'total_refunds' => round($refunds, 2),
            'net_total' => round($sales - $refunds, 2)
        ];
    }

    return $formatted;
}

