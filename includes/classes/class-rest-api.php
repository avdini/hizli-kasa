<?php
/**
 * Hızlı Kasa - REST API
 *
 * Özel ürün arama endpoint'i (varyant desteği dahil).
 *
 * @package HizliKasa
 */

if (!defined('ABSPATH'))
    exit;

// REST API Route Kayıtları
add_action('rest_api_init', function () {
    // --- Mevcut Endpoint'ler ---
    register_rest_route('hizli-kasa/v1', '/search', array(
        'methods' => 'GET',
        'callback' => 'hizli_kasa_ozel_arama',
        'permission_callback' => function () {
            return current_user_can('edit_posts');
        }
    ));

    register_rest_route('hizli-kasa/v1', '/gun-sonu-raporu', array(
        'methods' => 'GET',
        'callback' => 'hizli_kasa_gun_sonu_raporu',
        'permission_callback' => function () {
            return current_user_can('edit_posts');
        }
    ));

    register_rest_route('hizli-kasa/v1', '/load-tab', array(
        'methods' => 'GET',
        'callback' => 'hizli_kasa_load_tab_content',
        'permission_callback' => function () {
            return current_user_can('edit_posts');
        }
    ));

    register_rest_route('hizli-kasa/v1', '/get-order', array(
        'methods' => 'GET',
        'callback' => 'hizli_kasa_get_order_details',
        'permission_callback' => function () {
            return current_user_can('edit_posts');
        }
    ));

    register_rest_route('hizli-kasa/v1', '/process-refund', array(
        'methods' => 'POST',
        'callback' => 'hizli_kasa_process_refund',
        'permission_callback' => function () {
            return current_user_can('edit_posts');
        }
    ));

    register_rest_route('hizli-kasa/v1', '/terminal/products', array(
        'methods' => 'GET',
        'callback' => 'hizli_kasa_terminal_products',
        'permission_callback' => function () {
            return current_user_can('edit_posts');
        }
    ));

    register_rest_route('hizli-kasa/v1', '/terminal/update-stock', array(
        'methods' => 'POST',
        'callback' => 'hizli_kasa_terminal_update_stock',
        'permission_callback' => function () {
            return current_user_can('edit_posts');
        }
    ));

    // --- Yeni: Kullanıcı Depo Yönetimi Endpoint'leri ---

    /**
     * Kullanıcının depo listesini ve aktif deposunu döner.
     * Response: { view: [{id, name}], manage_ids: [1,3], active_depo_id: 2 }
     */
    register_rest_route('hizli-kasa/v1', '/user/depolar', array(
        'methods'             => 'GET',
        'callback'            => 'hizli_kasa_api_user_depolar',
        'permission_callback' => function () {
            return current_user_can('edit_posts');
        }
    ));

    /**
     * Kullanıcının aktif deposunu hem localStorage hem user_meta olarak ayarlar.
     * Body: { depo_id: 5 }
     */
    register_rest_route('hizli-kasa/v1', '/user/set-active-depo', array(
        'methods'             => 'POST',
        'callback'            => 'hizli_kasa_api_set_active_depo',
        'permission_callback' => function () {
            return current_user_can('edit_posts');
        }
    ));

    // --- Masraf Yönetimi Endpoint'leri ---
    register_rest_route('hizli-kasa/v1', '/masraflar', array(
        'methods'             => 'GET',
        'callback'            => 'hizli_kasa_get_masraflar',
        'permission_callback' => function () {
            return current_user_can('edit_posts');
        }
    ));

    register_rest_route('hizli-kasa/v1', '/masraflar', array(
        'methods'             => 'POST',
        'callback'            => 'hizli_kasa_add_masraf',
        'permission_callback' => function () {
            return current_user_can('edit_posts');
        }
    ));

    register_rest_route('hizli-kasa/v1', '/masraflar/(?P<id>\d+)', array(
        'methods'             => 'DELETE',
        'callback'            => 'hizli_kasa_delete_masraf',
        'permission_callback' => function () {
            return current_user_can('edit_posts');
        }
    ));
});

/**
 * Kullanıcının depo listesini ve aktif deposunu döner.
 */
function hizli_kasa_api_user_depolar($request) {
    $user_id = get_current_user_id();

    // Admin ise tüm depoları görebilir
    if (current_user_can('manage_options')) {
        global $wpdb;
        $depolar_raw = $wpdb->get_results("SELECT id, name FROM {$wpdb->prefix}hizli_kasa_depolar ORDER BY priority DESC, name ASC");
        $view        = array_map(fn($d) => ['id' => (int)$d->id, 'name' => $d->name], $depolar_raw);
        $manage_ids  = array_column($view, 'id');
    } else {
        $view_ids    = hizli_kasa_get_user_view_depos($user_id);
        $manage_ids  = hizli_kasa_get_user_manage_depos($user_id);

        if (empty($view_ids)) {
            return new WP_Error('no_depo', 'Profilinize depo atanmamış.', ['status' => 403]);
        }

        global $wpdb;
        if (!empty($view_ids)) {
            $ids_ph  = implode(',', array_map('intval', $view_ids));
            $depolar_raw = $wpdb->get_results("SELECT id, name FROM {$wpdb->prefix}hizli_kasa_depolar WHERE id IN ($ids_ph) ORDER BY priority DESC, name ASC");
        } else {
            $depolar_raw = [];
        }
        $view = array_map(fn($d) => ['id' => (int)$d->id, 'name' => $d->name], $depolar_raw);
    }

    // Aktif depoyu al (sunucu meta)
    $active_depo_id = hizli_kasa_get_user_active_depo($user_id);
    
    // Aktif depo yoksa ilk görüntüleme deposunu seç
    if (!$active_depo_id && !empty($view)) {
        $active_depo_id = $view[0]['id'];
        update_user_meta($user_id, '_hizli_kasa_active_depo', $active_depo_id);
    }

    return [
        'view'           => $view,
        'manage_ids'     => array_values($manage_ids),
        'active_depo_id' => $active_depo_id ? (int)$active_depo_id : null,
    ];
}

/**
 * Kullanıcının aktif deposunu user_meta'ya kaydeder.
 */
function hizli_kasa_api_set_active_depo($request) {
    $data    = $request->get_json_params();
    $depo_id = intval($data['depo_id'] ?? 0);
    $user_id = get_current_user_id();

    if (!$depo_id) {
        return new WP_Error('invalid_depo', 'Geçersiz depo ID.', ['status' => 400]);
    }

    // Yetki kontrolü: Bu depoyu görüntüleme yetkisi var mı?
    if (!hizli_kasa_can_user_view_depo($user_id, $depo_id)) {
        return new WP_Error('no_permission', 'Bu depoya erişim yetkiniz yok.', ['status' => 403]);
    }

    update_user_meta($user_id, '_hizli_kasa_active_depo', $depo_id);

    return [
        'success'        => true,
        'active_depo_id' => $depo_id,
        'message'        => 'Aktif depo güncellendi.',
    ];
}

/**
 * Gün Sonu Raporu API endpoint'i.
 *
 * Belirli bir kasanın belirli bir gündeki tüm siparişlerini
 * özetleyerek döner. Termal fiş yazdırma için kullanılır.
 *
 * @param WP_REST_Request $request İstek verisi (kasa_no, tarih)
 * @return array|WP_Error Gün sonu rapor özeti
 */
function hizli_kasa_gun_sonu_raporu($request) {
    $kasa_no = sanitize_text_field($request->get_param('kasa_no'));
    $tarih   = sanitize_text_field($request->get_param('tarih'));

    if (empty($kasa_no)) {
        return new WP_Error('missing_param', 'kasa_no parametresi gerekli.', array('status' => 400));
    }

    $is_general = ($kasa_no === 'all');

    // Tarih verilmezse bugünü kullan
    if (empty($tarih)) {
        $tarih = current_time('Y-m-d');
    }

    $tarih_baslangic = $tarih . ' 00:00:00';
    $tarih_bitis     = $tarih . ' 23:59:59';

    // Siparişleri çek
    $args = array(
        'limit'        => -1,
        'status'       => array('processing', 'completed', 'on-hold'),
        'date_created' => $tarih_baslangic . '...' . $tarih_bitis,
        'orderby'      => 'date',
        'order'        => 'ASC',
    );

    // Genel rapor değilse belirli kasaya göre filtrele
    if (!$is_general) {
        $args['meta_key']   = '_hizli_kasa_kasa_no';
        $args['meta_value'] = $kasa_no;
    }

    $orders = wc_get_orders($args);

    if (empty($orders)) {
        return array(
            'kasa_no'        => ($kasa_no === 'all') ? 'Genel' : $kasa_no,
            'tarih'          => $tarih,
            'siparis_sayisi' => 0,
            'siparisler'     => array(),
            'ozet'           => array(
                'toplam_ciro'       => 0,
                'toplam_iskonto'    => 0,
                'nakit_toplam'      => 0,
                'kart_toplam'       => 0,
                'iban_toplam'       => 0,
                'urun_adet_toplam'  => 0,
            ),
            'urun_dagilimi'  => array(),
            'kasiyerler'     => array(),
        );
    }

    $siparisler    = array();
    $nakit_toplam  = 0;
    $kart_toplam   = 0;
    $iban_toplam   = 0;
    $toplam_ciro   = 0;
    $toplam_iskonto = 0;
    $urun_adet     = 0;
    $urun_map      = array(); // SKU => { name, qty, total }
    $kasiyer_map   = array(); // Kasiyer adı => sipariş sayısı
    $saat_map      = array(); // Saat => sipariş sayısı

    foreach ($orders as $order) {
        $order_id    = $order->get_id();
        $order_total = (float) $order->get_total();
        $toplam_ciro += $order_total;

        // Ödeme bilgileri (custom meta)
        $o_nakit = (float) $order->get_meta('_odeme_nakit');
        $o_kart  = (float) $order->get_meta('_odeme_kart');
        $o_iban  = (float) $order->get_meta('_odeme_iban');
        $nakit_toplam += $o_nakit;
        $kart_toplam  += $o_kart;
        $iban_toplam  += $o_iban;

        // Kasiyer
        $kasiyer = $order->get_meta('_hizli_kasa_kasiyer') ?: 'Bilinmeyen';
        if (!isset($kasiyer_map[$kasiyer])) $kasiyer_map[$kasiyer] = 0;
        $kasiyer_map[$kasiyer]++;

        // Saat dağılımı
        $saat = $order->get_date_created()->date('H:00');
        if (!isset($saat_map[$saat])) $saat_map[$saat] = 0;
        $saat_map[$saat]++;

        // İskonto (fee_lines)
        $iskonto = 0;
        foreach ($order->get_fees() as $fee) {
            if (strpos(strtolower($fee->get_name()), 'iskonto') !== false) {
                $iskonto += abs((float) $fee->get_total());
            }
        }
        $toplam_iskonto += $iskonto;

        // Ürün detayları
        $urunler = array();
        foreach ($order->get_items() as $item) {

            $qty   = $item->get_quantity();
            $total = (float) $item->get_total();
            $name  = $item->get_name();
            $sku   = '';

            $product = $item->get_product();
            if ($product) {
                $sku = $product->get_sku();
            }

            $urun_adet += $qty;

            // Ürün haritası (benzersiz ürün dağılımı)
            $key = $sku ?: sanitize_title($name);
            if (!isset($urun_map[$key])) {
                $urun_map[$key] = array('name' => $name, 'sku' => $sku, 'qty' => 0, 'total' => 0);
            }
            $urun_map[$key]['qty']   += $qty;
            $urun_map[$key]['total'] += $total;

            $urunler[] = array(
                'name'  => $name,
                'sku'   => $sku,
                'qty'   => $qty,
                'total' => $total,
            );
        }
        
        // Ödeme tipi etiketi
        $odeme_tipi = $order->get_payment_method_title();

        $siparisler[] = array(
            'id'         => $order_id,
            'saat'       => $order->get_date_created()->date('H:i'),
            'toplam'     => $order_total,
            'odeme_tipi' => $odeme_tipi,
            'nakit'      => $o_nakit,
            'kart'       => $o_kart,
            'iban'       => $o_iban,
            'iskonto'    => $iskonto,
            'kasiyer'    => $kasiyer,
            'urunler'    => $urunler,
        );
    }

    // --- Masraf Entegrasyonu ---
    global $wpdb;
    $masraf_table = Hizli_Kasa_Database::get_tables()['masraflar'];
    $m_query = $wpdb->prepare("SELECT amount, payment_method FROM $masraf_table WHERE DATE(created_at) = %s", $tarih);
    
    if (!$is_general) {
        $m_query .= $wpdb->prepare(" AND kasa_no = %s", $kasa_no);
    }
    
    $masraflar_raw = $wpdb->get_results($m_query);
    $toplam_masraf = 0;
    $nakit_masraf  = 0;
    
    foreach ($masraflar_raw as $m) {
        $amt = (float)$m->amount;
        $toplam_masraf += $amt;
        if ($m->payment_method === 'nakit') {
            $nakit_masraf += $amt;
        }
    }

    // Ürün dağılımını sırala (en çok satılandan)
    uasort($urun_map, function($a, $b) {
        return $b['qty'] - $a['qty'];
    });

    return array(
        'kasa_no'        => ($kasa_no === 'all') ? 'Genel' : $kasa_no,
        'tarih'          => $tarih,
        'tarih_okunabilir' => date_i18n('d.m.Y l', strtotime($tarih)),
        'rapor_zamani'   => current_time('d.m.Y H:i:s'),
        'siparis_sayisi' => count($orders),
        'siparisler'     => $siparisler,
        'ozet'           => array(
            'toplam_ciro'       => round($toplam_ciro, 2),
            'toplam_iskonto'    => round($toplam_iskonto, 2),
            'nakit_toplam'      => round($nakit_toplam, 2),
            'kart_toplam'       => round($kart_toplam, 2),
            'iban_toplam'       => round($iban_toplam, 2),
            'toplam_masraf'     => round($toplam_masraf, 2),
            'nakit_masraf'      => round($nakit_masraf, 2),
            'net_nakit'         => round($nakit_toplam - $nakit_masraf, 2),
            'urun_adet_toplam'  => $urun_adet,
        ),
        'urun_dagilimi'  => array_values($urun_map),
        'kasiyerler'     => $kasiyer_map,
        'saat_dagilimi'  => $saat_map,
    );
}

/**
 * Özel ürün arama fonksiyonu.
 *
 * Hem ana ürünleri hem varyantları isim ve SKU'dan arar.
 * Varyant ürünlerde nitelik bilgilerini isme ekler.
 *
 * @param WP_REST_Request $data İstek verisi
 * @return array Formatlı ürün listesi
 */
function hizli_kasa_ozel_arama($data) {
    global $wpdb;
    $s = sanitize_text_field($data['s']);
    if (empty($s)) return [];

    $stok_table = Hizli_Kasa_Database::get_tables()['stok_konumlari'];
    $depo_id    = $data->get_param('depo_id');
    $results    = [];
    $exact      = $data->get_param('exact');

    if ($exact) {
        // Barkod okuyucu için tam SKU eşleşmesi
        $results = $wpdb->get_results($wpdb->prepare("
            SELECT p.ID, p.post_title, p.post_type, p.post_parent,
                   tt_type.slug as product_type,
                   pm_thumb.meta_value as thumbnail_id,
                   sk.quantity as warehouse_stock,
                   MAX(CASE WHEN pm.meta_key = '_sku' THEN pm.meta_value END) as sku,
                   MAX(CASE WHEN pm.meta_key = '_price' THEN pm.meta_value END) as price,
                   MAX(CASE WHEN pm.meta_key = '_regular_price' THEN pm.meta_value END) as regular_price,
                   MAX(CASE WHEN pm.meta_key = '_stock_status' THEN pm.meta_value END) as stock_status,
                   MAX(CASE WHEN pm.meta_key = '_manage_stock' THEN pm.meta_value END) as manage_stock,
                   MAX(CASE WHEN pm.meta_key = '_stock' THEN pm.meta_value END) as stock_quantity
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            LEFT JOIN {$wpdb->postmeta} pm_thumb ON p.ID = pm_thumb.post_id AND pm_thumb.meta_key = '_thumbnail_id'
            LEFT JOIN $stok_table sk ON (sk.product_id = p.ID AND sk.location_id = %d AND sk.variation_id = 0)
            LEFT JOIN {$wpdb->term_relationships} tr_type ON p.ID = tr_type.object_id
            LEFT JOIN {$wpdb->term_taxonomy} tt_tax ON tr_type.term_taxonomy_id = tt_tax.term_taxonomy_id AND tt_tax.taxonomy = 'product_type'
            LEFT JOIN {$wpdb->terms} tt_type ON tt_tax.term_id = tt_type.term_id
            WHERE p.post_status = 'publish'
              AND p.post_type IN ('product', 'product_variation')
              AND p.ID IN (SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_sku' AND meta_value = %s)
            GROUP BY p.ID
            LIMIT 20
        ", $depo_id, $s));
    } else {
        // --- AKILLI ARAMA MANTIĞI ---
        $found_ids = [];

        // 1. Advanced Woo Search Entegrasyonu (Eğer kuruluysa)
        if (class_exists('AWS_Search')) {
            $aws_search = new AWS_Search();
            $aws_results = $aws_search->search($s);
            if (!empty($aws_results['products'])) {
                foreach ($aws_results['products'] as $p_item) {
                     $pid = $p_item['id'];
                     $found_ids[] = $pid;
                     
                     // Değişiklik: Her ana ürünün varyasyonlarını da ekle
                     $product = wc_get_product($pid);
                     if ($product && $product->is_type('variable')) {
                         $children = $product->get_children();
                         foreach($children as $child_id) {
                             if (get_post_status($child_id) === 'publish') {
                                 $found_ids[] = $child_id;
                             }
                         }
                     }
                }
            }
        }

        // 2. Eğer AWS yoksa veya sonuç bulamadıysa, Gelişmiş Yedek Arama (Fallback)
        if (empty($found_ids)) {
            $words = explode(' ', $s);
            $where_parts = [];
            foreach ($words as $word) {
                if (empty($word)) continue;
                $like = '%' . $wpdb->esc_like($word) . '%';
                $where_parts[] = $wpdb->prepare("(p.post_title LIKE %s OR pm.meta_value LIKE %s)", $like, $like);
            }
            $where_clause = implode(' AND ', $where_parts);

            $fallback_results = $wpdb->get_results("
                SELECT p.ID, p.post_type
                FROM {$wpdb->posts} p
                LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_sku'
                WHERE p.post_status = 'publish'
                  AND p.post_type IN ('product', 'product_variation')
                  AND ($where_clause)
                GROUP BY p.ID
                LIMIT 20
            ");
            
            foreach ($fallback_results as $fr) {
                $found_ids[] = $fr->ID;
                
                // Değişiklik: Fallback sonuçlarında da varyasyonları ekle (eğer ana ürünse)
                if ($fr->post_type === 'product') {
                    $product = wc_get_product($fr->ID);
                    if ($product && $product->is_type('variable')) {
                        $children = $product->get_children();
                        foreach($children as $child_id) {
                            if (get_post_status($child_id) === 'publish') {
                                $found_ids[] = $child_id;
                            }
                        }
                    }
                }
            }
        }

        // Tekrarları temizle (hem varyant hem ana ürün bulunduysa)
        $found_ids = array_unique($found_ids);

        // 3. Bulunan ID'lerin detaylarını getir (Hydration)
        if (!empty($found_ids)) {
            $ids_str = implode(',', array_map('intval', $found_ids));
            $results = $wpdb->get_results($wpdb->prepare("
                SELECT p.ID, p.post_title, p.post_type, p.post_parent,
                       tt_type.slug as product_type,
                       pm_thumb.meta_value as thumbnail_id,
                       sk.quantity as warehouse_stock,
                       MAX(CASE WHEN pm.meta_key = '_sku' THEN pm.meta_value END) as sku,
                       MAX(CASE WHEN pm.meta_key = '_price' THEN pm.meta_value END) as price,
                       MAX(CASE WHEN pm.meta_key = '_regular_price' THEN pm.meta_value END) as regular_price,
                       MAX(CASE WHEN pm.meta_key = '_stock_status' THEN pm.meta_value END) as stock_status,
                       MAX(CASE WHEN pm.meta_key = '_manage_stock' THEN pm.meta_value END) as manage_stock,
                       MAX(CASE WHEN pm.meta_key = '_stock' THEN pm.meta_value END) as stock_quantity
                FROM {$wpdb->posts} p
                LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                LEFT JOIN {$wpdb->postmeta} pm_thumb ON p.ID = pm_thumb.post_id AND pm_thumb.meta_key = '_thumbnail_id'
                LEFT JOIN $stok_table sk ON (sk.product_id = p.ID AND sk.location_id = %d AND sk.variation_id = 0)
                LEFT JOIN {$wpdb->term_relationships} tr_type ON p.ID = tr_type.object_id
                LEFT JOIN {$wpdb->term_taxonomy} tt_tax ON tr_type.term_taxonomy_id = tt_tax.term_taxonomy_id AND tt_tax.taxonomy = 'product_type'
                LEFT JOIN {$wpdb->terms} tt_type ON tt_tax.term_id = tt_type.term_id
                WHERE p.ID IN ($ids_str)
                GROUP BY p.ID
                ORDER BY FIELD(p.ID, $ids_str)
            ", $depo_id));
        }
    }

    $formatted = [];
    foreach ($results as $row) {
        $item = hizli_kasa_format_urun_row($row, $depo_id);
        if ($item) {
            $formatted[] = $item;
        }
    }

    // Eğer sadece tek bir sonuç geldiyse ve bu bir variable ürün (veya varyantı) ise,
    // tüm kardeşlerini ve ana ürünü de listeye ekle.
    if (count($formatted) === 1) {
        $tek_sonuc = $formatted[0];
        $parent_id = ($tek_sonuc['type'] === 'variation') ? $tek_sonuc['parent_id'] : $tek_sonuc['id'];

        $ana_urun = wc_get_product($parent_id);
        if ($ana_urun && $ana_urun->is_type('variable')) {
            $genis_results = $wpdb->get_results($wpdb->prepare("
                SELECT p.ID, p.post_title, p.post_type, p.post_parent,
                       MAX(CASE WHEN pm.meta_key = '_sku' THEN pm.meta_value END) as sku,
                       MAX(CASE WHEN pm.meta_key = '_price' THEN pm.meta_value END) as price,
                       MAX(CASE WHEN pm.meta_key = '_regular_price' THEN pm.meta_value END) as regular_price,
                       MAX(CASE WHEN pm.meta_key = '_stock_status' THEN pm.meta_value END) as stock_status,
                       MAX(CASE WHEN pm.meta_key = '_manage_stock' THEN pm.meta_value END) as manage_stock,
                       MAX(CASE WHEN pm.meta_key = '_stock' THEN pm.meta_value END) as stock_quantity
                FROM {$wpdb->posts} p
                LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                WHERE p.post_status = 'publish'
                  AND (p.ID = %d OR p.post_parent = %d)
                GROUP BY p.ID
                ORDER BY 
                  (CASE WHEN p.ID = %d THEN 0 ELSE 1 END) ASC,
                  (CASE WHEN p.post_type = 'product' THEN 0 ELSE 1 END) ASC,
                  p.ID ASC
            ", $parent_id, $parent_id, $tek_sonuc['id']));

            if (!empty($genis_results)) {
                $formatted = [];
                foreach ($genis_results as $grow) {
                    $gitem = hizli_kasa_format_urun_row($grow, $depo_id);
                    if ($gitem) {
                        $formatted[] = $gitem;
                    }
                }
            }
        }
    }

    return $formatted;
}

/**
 * Veritabanından gelen ürün satırını formatlar.
 * 
 * @param object $row     DB satırı
 * @param int|null $depo_id Hangi deponun stoğuna bakılacak
 */
/**
 * Veritabanı row'unu JSON formatına çevirir (Performans Optimizasyonlu).
 */
function hizli_kasa_format_urun_row($row, $depo_id = null, $variations_by_parent = []) {
    try {
        $parent_id = (int)$row->ID;
        // product_type SQL'den gelecek
        $is_variable = ($row->product_type === 'variable');
        
        $active_children_data = [];
        if ($is_variable && isset($variations_by_parent[$parent_id])) {
            foreach ($variations_by_parent[$parent_id] as $v) {
                // Görsel Çözümleme
                $var_img = '';
                if (!empty($v->thumbnail_id)) {
                    $var_img = wp_get_attachment_image_url($v->thumbnail_id, 'thumbnail');
                }

                $active_children_data[] = [
                    'id'               => (int)$v->ID,
                    'parent_id'        => $parent_id,
                    'type'             => 'variation',
                    'name'             => $v->post_title,
                    'sku'              => $v->sku ?: '',
                    'warehouse_stock'  => (float)$v->warehouse_stock,
                    'stock_quantity'   => (float)$v->stock_quantity,
                    'images'           => $var_img ? [['src' => $var_img]] : []
                ];
            }
        }

        // Resim (SQL'den thumbnail_id gelecek)
        $image_url = '';
        if (!empty($row->thumbnail_id)) {
            $image_url = wp_get_attachment_image_url($row->thumbnail_id, 'thumbnail');
        }

        return [
            'id'              => $parent_id,
            'parent_id'       => (int)$row->post_parent,
            'type'            => $row->post_type === 'product_variation' ? 'variation' : 'product',
            'name'            => $row->post_title,
            'sku'             => $row->sku,
            'price'           => $row->price,
            'regular_price'   => $row->regular_price,
            'stock_status'    => $row->stock_status,
            'manage_stock'    => $row->manage_stock === 'yes',
            'stock_quantity'  => (float)$row->stock_quantity,
            'warehouse_stock' => (float)$row->warehouse_stock,
            'images'          => $image_url ? [['src' => $image_url]] : [],
            'is_variable'     => ($is_variable && !empty($active_children_data)),
            'variations'      => $active_children_data
        ];
    } catch (Exception $e) {
        error_log('Hızlı Kasa Ürün Formatlama Hatası (ID: ' . $row->ID . '): ' . $e->getMessage());
        return null;
    }
}


/**
 * Terminal üzerinden stok güncelleme.
 * 
 * Body: { product_id, variation_id, change, reason, active_depo_id }
 * active_depo_id zorunlu; kullanıcının yönetim yetkisi kontrol edilir.
 */
function hizli_kasa_terminal_update_stock($request) {
    $data         = $request->get_json_params();
    $product_id   = intval($data['product_id']);
    $variation_id = intval($data['variation_id'] ?? 0);
    $change       = floatval($data['change']);
    $reason       = sanitize_text_field($data['reason'] ?: "Terminal Manuel Güncelleme");
    $depo_id      = intval($data['active_depo_id'] ?? 0);

    $user_id = get_current_user_id();

    if (!$depo_id) {
        return new WP_Error('no_depo', 'active_depo_id belirtilmedi.', ['status' => 400]);
    }

    // Yönetim yetkisi kontrolü
    if (!hizli_kasa_can_user_manage_depo($user_id, $depo_id)) {
        return new WP_Error('no_permission', 'Bu depoda stok değiştirme yetkiniz yok.', ['status' => 403]);
    }

    require_once HIZLI_KASA_PATH . 'includes/classes/class-stock-manager.php';
    $new_qty = Hizli_Kasa_Stock_Manager::update_warehouse_stock($product_id, $variation_id, $depo_id, $change, $reason);

    return [
        'success' => true,
        'new_qty' => $new_qty,
        'message' => 'Stok başarıyla güncellendi.'
    ];
}

/**
 * Sekme içeriğini yükler ve döner.
 * 
 * @param WP_REST_Request $request
 * @return array JSON formatında HTML çıktısı
 */
function hizli_kasa_load_tab_content($request) {
    $tab = sanitize_text_field($request->get_param('tab'));
    
    // Güvenlik: Sadece izin verilen sekme dosyalarını yükle
    $allowed_tabs = ['kasa', 'urunler', 'raporlar', 'ayarlar', 'iade', 'masraf'];
    if (!in_array($tab, $allowed_tabs)) {
        return new WP_Error('invalid_tab', 'Geçersiz sekme adı.', array('status' => 400));
    }

    $template_file = HIZLI_KASA_PATH . "includes/views/tab-{$tab}.php";
    
    if (!file_exists($template_file)) {
        // Eğer dosya yoksa geçici bir içerik dön (placeholder)
        return array(
            'html' => "<div style='padding:40px; text-align:center;'><h3>{$tab} Sayfası Hazırlanıyor...</h3><p>Bu modül yakında aktif edilecek.</p></div>"
        );
    }

    ob_start();
    include $template_file;
    $html = ob_get_clean();

    return array(
        'html' => $html
    );
}

/**
 * İade işlemi için sipariş detaylarını getirir.
 */
function hizli_kasa_get_order_details($request) {
    $order_id = sanitize_text_field($request->get_param('id'));
    $order = wc_get_order($order_id);

    if (!$order) {
        return new WP_Error('no_order', 'Sipariş bulunamadı.', array('status' => 404));
    }

    $items = [];
    foreach ($order->get_items() as $item_id => $item) {
        $product = $item->get_product();
        $items[] = [
            'item_id' => $item_id,
            'id'      => $item->get_product_id(),
            'name'    => $item->get_name(),
            'sku'     => $product ? $product->get_sku() : '',
            'qty'     => $item->get_quantity(),
            'price'   => $item->get_total() / $item->get_quantity(),
            'total'   => $item->get_total(),
        ];
    }

    return [
        'id'         => $order->get_id(),
        'date'       => $order->get_date_created()->date('d.m.Y H:i'),
        'total'      => $order->get_total(),
        'items'      => $items,
        'payment'    => $order->get_payment_method_title(),
        'kasiyer'    => $order->get_meta('_hizli_kasa_kasiyer') ?: 'Bilinmiyor'
    ];
}

/**
 * İade (Negatif Sipariş) oluşturur.
 */
function hizli_kasa_process_refund($request) {
    $data = $request->get_json_params();
    $original_order_id = sanitize_text_field($data['original_order_id']);
    $refund_items = $data['items']; // [{id, qty, price, name}]

    if (empty($refund_items)) {
        return new WP_Error('no_items', 'İade edilecek ürün seçilmedi.', array('status' => 400));
    }

    // Yeni negatif sipariş oluştur
    $refund_order = wc_create_order(array(
        'status'      => 'completed',
        'customer_id' => 0, // Misafir iade
    ));

    $total_refund = 0;

    foreach ($refund_items as $item) {
        // Negatif fiyat ve miktar
        $qty = abs($item['qty']) * -1;
        $price = abs($item['price']);
        $line_total = $price * $qty;

        $product = wc_get_product($item['id']);
        if ($product) {
            $item_id = $refund_order->add_product($product, 1, array(
                'totals' => array(
                    'subtotal'     => $line_total,
                    'subtotal_tax' => 0,
                    'total'        => $line_total,
                    'tax'          => 0,
                )
            ));
            
            // Adedi manuel düzenle (add_product negatif adedi sevmezse)
            $refund_item = $refund_order->get_item($item_id);
            $refund_item->set_quantity($qty);
            $refund_item->set_total($line_total);
            $refund_item->save();

            $total_refund += $line_total;
        }
    }

    // Meta veriler
    $refund_order->set_payment_method('cod');
    $refund_order->set_payment_method_title('İade İşlemi');
    $refund_order->update_meta_data('_hizli_kasa_original_order', $original_order_id);
    $refund_order->update_meta_data('_hizli_kasa_is_refund', 'yes');
    $refund_order->update_meta_data('_hizli_kasa_kasiyer', wp_get_current_user()->display_name);
    
    // Çoklu Depo Entegrasyonu: İade edilen ürünleri aktif depoya geri koy
    $user_id = get_current_user_id();
    $depo_id = intval($data['active_depo_id'] ?? 0);
    if (!$depo_id) {
        $depo_id = hizli_kasa_get_user_active_depo($user_id);
    }
    
    if ($depo_id && hizli_kasa_can_user_manage_depo($user_id, $depo_id)) {
        require_once HIZLI_KASA_PATH . 'includes/classes/class-stock-manager.php';
        foreach ($refund_items as $item) {
            $product_id = intval($item['id']);
            $variation_id = intval($item['variation_id'] ?? 0);
            $qty = abs($item['qty']); // İade edilen miktar
            
            Hizli_Kasa_Stock_Manager::update_warehouse_stock($product_id, $variation_id, $depo_id, $qty, "İade İşlemi (Geri Dönüş - #$original_order_id)");
        }
    }

    // Toplamı hesaplat
    $refund_order->calculate_totals();
    $refund_order->save();

    return array(
        'success'  => true,
        'order_id' => $refund_order->get_id(),
        'total'    => $refund_order->get_total(),
        'message'  => 'İade başarıyla oluşturuldu.'
    );
}

/**
 * Terminal/Stok Yönetimi sayfası için ürünleri listeler.
 * 
 * @param WP_REST_Request $request
 * @return array {products: [], total: 0, has_more: false, critical_count: 0}
 */
function hizli_kasa_terminal_products($request) {
    global $wpdb;
    hizli_kasa_log("API Başladı: depo_id=" . $request->get_param('depo_id') . " s=" . $request->get_param('s'));
    
    $limit   = intval($request->get_param('limit') ?: 24);
    $offset  = intval($request->get_param('offset') ?: 0);
    $depo_id = intval($request->get_param('depo_id'));
    $s       = sanitize_text_field($request->get_param('s'));

    $threshold = (int) get_option('hizli_kasa_kritik_stok_esigi', 5);
    $stok_table = $wpdb->prefix . 'hizli_kasa_stok_konumlari';

    // ANA ÜRÜN FİLTRELEME
    $where = "p.post_status = 'publish' AND p.post_type = 'product'";
    $join_extra = "";
    
    if ($depo_id) {
        $join_extra .= $wpdb->prepare(" INNER JOIN $stok_table sk_filter ON (sk_filter.product_id = p.ID AND sk_filter.location_id = %d)", $depo_id);
    }

    $params = [];
    if (!empty($s)) {
        $like = '%' . $wpdb->esc_like($s) . '%';
        $where .= " AND (
            p.post_title LIKE %s 
            OR p.ID IN (SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_sku' AND meta_value LIKE %s)
            OR p.ID IN (
                SELECT DISTINCT sub_p.post_parent 
                FROM {$wpdb->posts} sub_p 
                INNER JOIN {$wpdb->postmeta} sub_pm ON sub_p.ID = sub_pm.post_id AND sub_pm.meta_key = '_sku'
                WHERE sub_p.post_type = 'product_variation' AND sub_pm.meta_value LIKE %s
            )
        )";
        $params[] = $like; $params[] = $like; $params[] = $like;
    }

    // Toplam sayıyı bul
    $total = $wpdb->get_var($wpdb->prepare("SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p $join_extra WHERE $where", ...$params));
    hizli_kasa_log("Ana sorgu total: $total");

    if (!$total) {
        return ['products' => [], 'total' => 0, 'has_more' => false, 'critical_count' => 0];
    }

    // ADIM 1: ID KEŞFİ (Hafif Sorgu)
    // Sadece gösterilecek 24 ID'yi buluyoruz (Meta tablolarına dokunmuyoruz)
    $id_query = $wpdb->prepare("
        SELECT DISTINCT p.ID 
        FROM {$wpdb->posts} p 
        $join_extra 
        WHERE $where 
        ORDER BY p.post_title ASC 
        LIMIT %d OFFSET %d
    ", array_merge($params, [$limit, $offset]));
    
    $target_ids = $wpdb->get_col($id_query);
    hizli_kasa_log("ID Keşfi Bitti: " . count($target_ids) . " adet ID bulundu.");

    if (empty($target_ids)) {
        return ['products' => [], 'total' => (int)$total, 'has_more' => false, 'critical_count' => 0];
    }

    // ADIM 2: DETAYLARI ÇEK (Nokta Atışı)
    // Sadece bulduğumuz 24 ID için ağır meta bilgilerini çekiyoruz
    $placeholders = implode(',', array_fill(0, count($target_ids), '%d'));
    $sql = $wpdb->prepare("
        SELECT p.ID, p.post_title, p.post_type, p.post_parent,
               tt_type.slug as product_type,
               pm_thumb.meta_value as thumbnail_id,
               sk_main.quantity as warehouse_stock,
               MAX(CASE WHEN pm.meta_key = '_sku' THEN pm.meta_value END) as sku,
               MAX(CASE WHEN pm.meta_key = '_price' THEN pm.meta_value END) as price,
               MAX(CASE WHEN pm.meta_key = '_regular_price' THEN pm.meta_value END) as regular_price,
               MAX(CASE WHEN pm.meta_key = '_stock_status' THEN pm.meta_value END) as stock_status,
               MAX(CASE WHEN pm.meta_key = '_manage_stock' THEN pm.meta_value END) as manage_stock,
               MAX(CASE WHEN pm.meta_key = '_stock' THEN pm.meta_value END) as stock_quantity
        FROM {$wpdb->posts} p
        LEFT JOIN $stok_table sk_main ON (sk_main.product_id = p.ID AND sk_main.location_id = %d AND sk_main.variation_id = 0)
        LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
        LEFT JOIN {$wpdb->postmeta} pm_thumb ON p.ID = pm_thumb.post_id AND pm_thumb.meta_key = '_thumbnail_id'
        LEFT JOIN {$wpdb->term_relationships} tr_type ON p.ID = tr_type.object_id
        LEFT JOIN {$wpdb->term_taxonomy} tt_tax ON tr_type.term_taxonomy_id = tt_tax.term_taxonomy_id AND tt_tax.taxonomy = 'product_type'
        LEFT JOIN {$wpdb->terms} tt_type ON tt_tax.term_id = tt_type.term_id
        WHERE p.ID IN ($placeholders)
        GROUP BY p.ID
        ORDER BY p.post_title ASC
    ", array_merge([$depo_id], $target_ids));

    $results = $wpdb->get_results($sql);
    hizli_kasa_log("Hydration (Detay Çekme) Bitti: " . count($results) . " satır.");

    // Varyasyonları Toplu Çek
    $parent_ids = wp_list_pluck($results, 'ID');
    $variations_by_parent = [];
    if (!empty($parent_ids)) {
        $ids_placeholders = implode(',', array_fill(0, count($parent_ids), '%d'));
        $v_sql = $wpdb->prepare("
            SELECT p.ID, p.post_parent, p.post_title,
                   sk.quantity as warehouse_stock,
                   MAX(CASE WHEN pm.meta_key = '_sku' THEN pm.meta_value END) as sku,
                   MAX(CASE WHEN pm.meta_key = '_stock' THEN pm.meta_value END) as stock_quantity,
                   MAX(CASE WHEN pm.meta_key = '_thumbnail_id' THEN pm.meta_value END) as thumbnail_id
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            LEFT JOIN $stok_table sk ON (sk.variation_id = p.ID AND sk.location_id = %d)
            WHERE p.post_type = 'product_variation' AND p.post_status = 'publish' AND p.post_parent IN ($ids_placeholders)
            GROUP BY p.ID
        ", array_merge([$depo_id], $parent_ids));
        
        $v_results = $wpdb->get_results($v_sql);
        foreach ($v_results as $v) { $variations_by_parent[$v->post_parent][] = $v; }
    }

    $formatted = [];
    foreach ($results as $row) {
        $item = hizli_kasa_format_urun_row($row, $depo_id, $variations_by_parent);
        if ($item) $formatted[] = $item;
    }

    // SAYIMLAR (YÜKSEK PERFORMANS)
    hizli_kasa_log("Sayımlar başlıyor...");
    $simple_count = 0; $variable_count = 0; $grand_total_items = 0; $critical_count = 0;

    if ($total > 0) {
        // Tip sayımları
        $counts_sql = $wpdb->prepare("
            SELECT 
                SUM(CASE WHEN tt_terms.slug = 'simple' THEN 1 ELSE 0 END) as simple,
                SUM(CASE WHEN tt_terms.slug = 'variable' THEN 1 ELSE 0 END) as variable
            FROM {$wpdb->posts} p
            $join_extra
            INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
            INNER JOIN {$wpdb->term_taxonomy} ttt ON tr.term_taxonomy_id = ttt.term_taxonomy_id AND ttt.taxonomy = 'product_type'
            INNER JOIN {$wpdb->terms} tt_terms ON ttt.term_id = tt_terms.term_id
            WHERE $where
        ", ...$params);
        $counts = $wpdb->get_row($counts_sql);
        if ($counts) { $simple_count = (int)$counts->simple; $variable_count = (int)$counts->variable; }

        // Toplam Kalem (Basit Ürün + Tüm Varyasyonlar) - HIZLI JOIN
        $grand_total_sql = $wpdb->prepare("
            SELECT COUNT(sk.id)
            FROM $stok_table sk
            INNER JOIN {$wpdb->posts} p ON p.ID = (CASE WHEN sk.variation_id > 0 THEN sk.variation_id ELSE sk.product_id END)
            INNER JOIN {$wpdb->posts} p_parent ON p_parent.ID = (CASE WHEN p.post_type = 'product' THEN p.ID ELSE p.post_parent END)
            WHERE sk.location_id = %d AND p_parent.post_status = 'publish' AND p_parent.post_type = 'product'
              AND (
                  p_parent.post_title LIKE %s
                  OR p_parent.ID IN (SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_sku' AND meta_value LIKE %s)
              )
        ", array_merge([$depo_id], !empty($params) ? [$params[0], $params[1]] : ['%%', '%%']));
        
        $grand_total_items = $wpdb->get_var($grand_total_sql);

        // Kritik Stok
        $critical_count = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(sk.id) FROM $stok_table sk 
            WHERE sk.location_id = %d AND sk.quantity <= %d
        ", $depo_id, $threshold));
    }

    hizli_kasa_log("API Bitti: simple=$simple_count variable=$variable_count total_items=$grand_total_items");

    return [
        'products'          => $formatted,
        'total'             => (int)$total,
        'simple_count'      => $simple_count,
        'variable_count'    => $variable_count,
        'grand_total_items' => (int)$grand_total_items,
        'critical_count'    => (int)$critical_count,
        'has_more'          => ($offset + $limit) < $total
    ];
}

/**
 * Masrafları listeler.
 */
function hizli_kasa_get_masraflar($request) {
    global $wpdb;
    $tarih   = sanitize_text_field($request->get_param('tarih') ?: current_time('Y-m-d'));
    $depo_id = intval($request->get_param('depo_id'));
    
    $table = Hizli_Kasa_Database::get_tables()['masraflar'];
    
    // Tablo var mı kontrol et, yoksa init çalıştır
    if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
        Hizli_Kasa_Database::init();
    }
    
    $query = $wpdb->prepare(
        "SELECT * FROM $table WHERE DATE(created_at) = %s",
        $tarih
    );
    
    if ($depo_id) {
        $query .= $wpdb->prepare(" AND location_id = %d", $depo_id);
    }
    
    $query .= " ORDER BY created_at DESC";
    
    $results = $wpdb->get_results($query);
    
    // User display name'leri ekle
    foreach ($results as &$row) {
        $user_info = get_userdata($row->user_id);
        $row->user_name = $user_info ? $user_info->display_name : 'Bilinmeyen';
    }
    
    return $results;
}

/**
 * Yeni masraf ekler.
 */
function hizli_kasa_add_masraf($request) {
    global $wpdb;
    $params = $request->get_json_params();
    
    $category       = sanitize_text_field($params['category']);
    $amount         = floatval($params['amount']);
    $payment_method = sanitize_text_field($params['payment_method'] ?: 'nakit');
    $description    = sanitize_textarea_field($params['description']);
    $depo_id        = intval($params['depo_id']);
    $kasa_no        = sanitize_text_field($params['kasa_no']);
    $user_id        = get_current_user_id();
    
    if (empty($category) || $amount <= 0) {
        return new WP_Error('invalid_data', 'Kategori ve geçerli bir tutar gerekli.', ['status' => 400]);
    }
    
    $table = Hizli_Kasa_Database::get_tables()['masraflar'];
    
    // Tablo var mı kontrol et, yoksa init çalıştır
    if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
        Hizli_Kasa_Database::init();
    }
    
    $result = $wpdb->insert($table, [
        'category'       => $category,
        'amount'         => $amount,
        'payment_method' => $payment_method,
        'description'    => $description,
        'user_id'        => $user_id,
        'location_id'    => $depo_id,
        'kasa_no'        => $kasa_no,
        'created_at'     => current_time('mysql'),
    ]);
    
    if (!$result) {
        return new WP_Error('db_error', 'Masraf kaydedilemedi.', ['status' => 500]);
    }
    
    return [
        'success' => true,
        'id'      => $wpdb->insert_id,
        'message' => 'Masraf başarıyla kaydedildi.'
    ];
}

/**
 * Masraf siler.
 */
function hizli_kasa_delete_masraf($request) {
    global $wpdb;
    $id = intval($request->get_param('id'));
    
    if (!$id) {
        return new WP_Error('invalid_id', 'Geçersiz ID.', ['status' => 400]);
    }
    
    $table = Hizli_Kasa_Database::get_tables()['masraflar'];
    $result = $wpdb->delete($table, ['id' => $id]);
    
    if (!$result) {
        return new WP_Error('db_error', 'Masraf silinemedi.', ['status' => 500]);
    }
    
    return [
        'success' => true,
        'message' => 'Masraf silindi.'
    ];
}

