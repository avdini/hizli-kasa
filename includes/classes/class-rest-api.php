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
 */
function hizli_kasa_gun_sonu_raporu($request) {
    $kasa_no = sanitize_text_field($request->get_param('kasa_no'));
    $tarih   = sanitize_text_field($request->get_param('tarih'));

    if (empty($kasa_no)) {
        return new WP_Error('missing_param', 'kasa_no parametresi gerekli.', array('status' => 400));
    }

    $is_general = ($kasa_no === 'all');

    if (empty($tarih)) {
        $tarih = current_time('Y-m-d');
    }

    $tarih_baslangic = $tarih . ' 00:00:00';
    $tarih_bitis     = $tarih . ' 23:59:59';

    $args = array(
        'limit'        => -1,
        'status'       => array('processing', 'completed', 'on-hold'),
        'date_created' => $tarih_baslangic . '...' . $tarih_bitis,
        'orderby'      => 'date',
        'order'        => 'ASC',
    );

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
    $urun_map      = array();
    $kasiyer_map   = array();
    $saat_map      = array();

    foreach ($orders as $order) {
        $order_id    = $order->get_id();
        $order_total = (float) $order->get_total();
        $toplam_ciro += $order_total;

        $o_nakit = (float) $order->get_meta('_odeme_nakit');
        $o_kart  = (float) $order->get_meta('_odeme_kart');
        $o_iban  = (float) $order->get_meta('_odeme_iban');
        $nakit_toplam += $o_nakit;
        $kart_toplam  += $o_kart;
        $iban_toplam  += $o_iban;

        $kasiyer = $order->get_meta('_hizli_kasa_kasiyer') ?: 'Bilinmeyen';
        if (!isset($kasiyer_map[$kasiyer])) $kasiyer_map[$kasiyer] = 0;
        $kasiyer_map[$kasiyer]++;

        $saat = $order->get_date_created()->date('H:00');
        if (!isset($saat_map[$saat])) $saat_map[$saat] = 0;
        $saat_map[$saat]++;

        $iskonto = 0;
        foreach ($order->get_fees() as $fee) {
            if (strpos(strtolower($fee->get_name()), 'iskonto') !== false) {
                $iskonto += abs((float) $fee->get_total());
            }
        }
        $toplam_iskonto += $iskonto;

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
 * Özel ürün arama fonksiyonu (Performans Optimizasyonlu).
 */
function hizli_kasa_ozel_arama($data) {
    global $wpdb;
    $s = sanitize_text_field($data['s']);
    if (empty($s)) return [];

    $depo_id = $data->get_param('depo_id');
    $exact   = $data->get_param('exact');
    
    $found_ids = [];

    if ($exact) {
        // Barkod okuyucu için tam SKU eşleşmesi
        $found_ids = $wpdb->get_col($wpdb->prepare("
            SELECT post_id FROM {$wpdb->postmeta} 
            WHERE meta_key = '_sku' AND meta_value = %s 
            LIMIT 10", $s));
    } else {
        // 1. Advanced Woo Search Entegrasyonu
        if (class_exists('AWS_Search')) {
            $aws_search = new AWS_Search();
            $aws_results = $aws_search->search($s);
            if (!empty($aws_results['products'])) {
                foreach ($aws_results['products'] as $p_item) {
                    $found_ids[] = (int)$p_item['id'];
                }
            }
            // Not: AWS varsa ve sonuç bulamadıysa, kullanıcı isteği üzerine fallback çalıştırmıyoruz.
        } else {
            // 2. Fallback Arama (Post Title ve SKU) - Sadece AWS yoksa çalışır
            $words = explode(' ', $s);
            $where_parts_and = [];
            $where_parts_or  = [];
            
            foreach ($words as $word) {
                if (empty($word) || mb_strlen($word) < 2) continue;
                $like = '%' . $wpdb->esc_like($word) . '%';
                $part = $wpdb->prepare("(p.post_title LIKE %s OR pm.meta_value LIKE %s)", $like, $like);
                $where_parts_and[] = $part;
                $where_parts_or[]  = $part;
            }

            if (!empty($where_parts_and)) {
                $where_clause = implode(' AND ', $where_parts_and);
                $sql = "SELECT p.ID FROM {$wpdb->posts} p 
                        LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_sku'
                        WHERE p.post_status = 'publish' 
                        AND p.post_type IN ('product', 'product_variation') 
                        AND ($where_clause) 
                        GROUP BY p.ID LIMIT 30";
                $fallback_ids = $wpdb->get_col($sql);
                
                // Eğer AND ile sonuç gelmediyse, daha geniş bir OR araması yap
                if (empty($fallback_ids)) {
                    $where_clause = implode(' OR ', $where_parts_or);
                    $sql = "SELECT p.ID FROM {$wpdb->posts} p 
                            LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_sku'
                            WHERE p.post_status = 'publish' 
                            AND p.post_type IN ('product', 'product_variation') 
                            AND ($where_clause) 
                            GROUP BY p.ID LIMIT 20";
                    $fallback_ids = $wpdb->get_col($sql);
                }

                if ($fallback_ids) {
                    $found_ids = array_unique(array_map('intval', $fallback_ids));
                }
            }
        }
    }

    if (empty($found_ids)) return [];

    // 3. Batch Hydration: Hızlı Veri Çekme Mimarisi
    $results_map = hizli_kasa_hydrate_products_batch($found_ids, $depo_id);

    // 4. Tek Sonuç (veya çok az) Varsa Varyasyonları Ekle (Dropdown için)
    if (count($results_map) <= 3) {
        $extra_ids = [];
        foreach ($results_map as $item) {
            if ($item['is_variable']) {
                $children = $wpdb->get_col($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE post_parent = %d AND post_status = 'publish'", $item['id']));
                if ($children) $extra_ids = array_merge($extra_ids, array_map('intval', $children));
            } elseif ($item['parent_id'] > 0) {
                $parent_id = $item['parent_id'];
                $extra_ids[] = (int)$parent_id;
                $children = $wpdb->get_col($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE post_parent = %d AND post_status = 'publish'", $parent_id));
                if ($children) $extra_ids = array_merge($extra_ids, array_map('intval', $children));
            }
        }
        
        if (!empty($extra_ids)) {
            $all_ids = array_unique(array_merge($found_ids, $extra_ids));
            $results_map = hizli_kasa_hydrate_products_batch($all_ids, $depo_id);
        }
    }

    return array_values($results_map);
}

/**
 * Veritabanı row'unu JSON formatına çevirir (Performans Optimizasyonlu).
 */
function hizli_kasa_format_urun_row($row, $depo_id = null, $variations_by_parent = []) {
    try {
        $parent_id = (int)$row->ID;
        $is_variable = (isset($row->product_type) && $row->product_type === 'variable');
        
        $active_children_data = [];
        if ($is_variable && isset($variations_by_parent[$parent_id])) {
            foreach ($variations_by_parent[$parent_id] as $v) {
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
            'is_variable'     => $is_variable,
            'variations'      => $active_children_data
        ];
    } catch (Exception $e) {
        error_log('Hızlı Kasa Ürün Formatlama Hatası (ID: ' . $row->ID . '): ' . $e->getMessage());
        return null;
    }
}

/**
 * Terminal üzerinden stok güncelleme.
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
 */
function hizli_kasa_load_tab_content($request) {
    $tab = sanitize_text_field($request->get_param('tab'));
    $allowed_tabs = ['kasa', 'urunler', 'raporlar', 'ayarlar', 'iade', 'masraf'];
    if (!in_array($tab, $allowed_tabs)) {
        return new WP_Error('invalid_tab', 'Geçersiz sekme adı.', array('status' => 400));
    }

    $template_file = HIZLI_KASA_PATH . "includes/views/tab-{$tab}.php";
    if (!file_exists($template_file)) {
        return array(
            'html' => "<div style='padding:40px; text-align:center;'><h3>{$tab} Sayfası Hazırlanıyor...</h3><p>Bu modül yakında aktif edilecek.</p></div>"
        );
    }

    ob_start();
    include $template_file;
    $html = ob_get_clean();

    return array('html' => $html);
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
    $refund_items = $data['items'];

    if (empty($refund_items)) {
        return new WP_Error('no_items', 'İade edilecek ürün seçilmedi.', array('status' => 400));
    }

    $refund_order = wc_create_order(array('status' => 'completed', 'customer_id' => 0));
    $total_refund = 0;

    foreach ($refund_items as $item) {
        $qty = abs($item['qty']) * -1;
        $price = abs($item['price']);
        $line_total = $price * $qty;

        $product = wc_get_product($item['id']);
        if ($product) {
            $item_id = $refund_order->add_product($product, 1, array(
                'totals' => array('subtotal' => $line_total, 'subtotal_tax' => 0, 'total' => $line_total, 'tax' => 0)
            ));
            $refund_item = $refund_order->get_item($item_id);
            $refund_item->set_quantity($qty);
            $refund_item->set_total($line_total);
            $refund_item->save();
            $total_refund += $line_total;
        }
    }

    $refund_order->set_payment_method('cod');
    $refund_order->set_payment_method_title('İade İşlemi');
    $refund_order->update_meta_data('_hizli_kasa_original_order', $original_order_id);
    $refund_order->update_meta_data('_hizli_kasa_is_refund', 'yes');
    $refund_order->update_meta_data('_hizli_kasa_kasiyer', wp_get_current_user()->display_name);
    
    $user_id = get_current_user_id();
    $depo_id = intval($data['active_depo_id'] ?? 0);
    if (!$depo_id) $depo_id = hizli_kasa_get_user_active_depo($user_id);
    
    if ($depo_id && hizli_kasa_can_user_manage_depo($user_id, $depo_id)) {
        require_once HIZLI_KASA_PATH . 'includes/classes/class-stock-manager.php';
        foreach ($refund_items as $item) {
            $product_id = intval($item['id']);
            $variation_id = intval($item['variation_id'] ?? 0);
            $qty = abs($item['qty']);
            Hizli_Kasa_Stock_Manager::update_warehouse_stock($product_id, $variation_id, $depo_id, $qty, "İade İşlemi (Geri Dönüş - #$original_order_id)");
        }
    }

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
 */
function hizli_kasa_terminal_products($request) {
    global $wpdb;
    $limit   = intval($request->get_param('limit') ?: 24);
    $offset  = intval($request->get_param('offset') ?: 0);
    $depo_id = intval($request->get_param('depo_id'));
    $s       = sanitize_text_field($request->get_param('s'));

    $threshold = (int) get_option('hizli_kasa_kritik_stok_esigi', 5);
    $stok_table = $wpdb->prefix . 'hizli_kasa_stok_konumlari';

    $where = "p.post_status = 'publish' AND p.post_type = 'product'";
    $join_extra = "";
    if ($depo_id) {
        $join_extra .= $wpdb->prepare(" INNER JOIN $stok_table sk_filter ON (sk_filter.product_id = p.ID AND sk_filter.location_id = %d)", $depo_id);
    }

    $params = [];
    $aws_ids = [];

    if (!empty($s)) {
        // 1. Advanced Woo Search Entegrasyonu
        if (class_exists('AWS_Search')) {
            $aws_search = new AWS_Search();
            $aws_results = $aws_search->search($s);
            $aws_ids = [];
            if (!empty($aws_results['products'])) {
                foreach ($aws_results['products'] as $p_item) {
                    $aws_ids[] = (int)$p_item['id'];
                }
            }

            if (!empty($aws_ids)) {
                $ids_ph = implode(',', array_map('intval', $aws_ids));
                $where .= " AND p.ID IN ($ids_ph)";
            } else {
                // AWS var ama sonuç yoksa zorla boş döndür
                $where .= " AND p.ID = 0";
            }
        } else {
            // 2. Fallback Arama (Sadece AWS yoksa)
            $like = '%' . $wpdb->esc_like($s) . '%';
            $where .= " AND (p.post_title LIKE %s OR p.ID IN (SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_sku' AND meta_value LIKE %s))";
            $params[] = $like; $params[] = $like;
        }
    }

    $total = $wpdb->get_var($wpdb->prepare("SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p $join_extra WHERE $where", ...$params));
    if (!$total) return ['products' => [], 'total' => 0, 'has_more' => false, 'critical_count' => 0];

    $id_query = $wpdb->prepare("
        SELECT DISTINCT p.ID FROM {$wpdb->posts} p $join_extra WHERE $where ORDER BY p.post_title ASC LIMIT %d OFFSET %d", array_merge($params, [$limit, $offset]));
    $target_ids = $wpdb->get_col($id_query);

    if (empty($target_ids)) return ['products' => [], 'total' => (int)$total, 'has_more' => false, 'critical_count' => 0];

    $placeholders = implode(',', array_fill(0, count($target_ids), '%d'));
    $sql = $wpdb->prepare("
        SELECT p.ID, p.post_title, p.post_type, p.post_parent,
               tt_type.slug as product_type, pm_thumb.meta_value as thumbnail_id, sk_main.quantity as warehouse_stock,
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
    $parent_ids = wp_list_pluck($results, 'ID');
    $variations_by_parent = [];
    if (!empty($parent_ids)) {
        $ids_placeholders = implode(',', array_fill(0, count($parent_ids), '%d'));
        $v_results = $wpdb->get_results($wpdb->prepare("
            SELECT p.ID, p.post_parent, p.post_title, sk.quantity as warehouse_stock,
                   MAX(CASE WHEN pm.meta_key = '_sku' THEN pm.meta_value END) as sku,
                   MAX(CASE WHEN pm.meta_key = '_stock' THEN pm.meta_value END) as stock_quantity,
                   MAX(CASE WHEN pm.meta_key = '_thumbnail_id' THEN pm.meta_value END) as thumbnail_id
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            LEFT JOIN $stok_table sk ON (sk.variation_id = p.ID AND sk.location_id = %d)
            WHERE p.post_type = 'product_variation' AND p.post_status = 'publish' AND p.post_parent IN ($ids_placeholders)
            GROUP BY p.ID
        ", array_merge([$depo_id], $parent_ids)));
        foreach ($v_results as $v) { $variations_by_parent[$v->post_parent][] = $v; }
    }

    $formatted = [];
    foreach ($results as $row) {
        $item = hizli_kasa_format_urun_row($row, $depo_id, $variations_by_parent);
        if ($item) $formatted[] = $item;
    }

    return [
        'products' => $formatted,
        'total'    => (int)$total,
        'has_more' => ($offset + $limit) < $total
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
    if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) Hizli_Kasa_Database::init();
    $query = $wpdb->prepare("SELECT * FROM $table WHERE DATE(created_at) = %s", $tarih);
    if ($depo_id) $query .= $wpdb->prepare(" AND location_id = %d", $depo_id);
    $query .= " ORDER BY created_at DESC";
    $results = $wpdb->get_results($query);
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
    $category = sanitize_text_field($params['category']);
    $amount = floatval($params['amount']);
    $payment_method = sanitize_text_field($params['payment_method'] ?: 'nakit');
    $description = sanitize_textarea_field($params['description']);
    $depo_id = intval($params['depo_id']);
    $kasa_no = sanitize_text_field($params['kasa_no']);
    $user_id = get_current_user_id();
    if (empty($category) || $amount <= 0) return new WP_Error('invalid_data', 'Kategori ve geçerli bir tutar gerekli.', ['status' => 400]);
    $table = Hizli_Kasa_Database::get_tables()['masraflar'];
    if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) Hizli_Kasa_Database::init();
    $result = $wpdb->insert($table, [
        'category' => $category, 'amount' => $amount, 'payment_method' => $payment_method,
        'description' => $description, 'user_id' => $user_id, 'location_id' => $depo_id,
        'kasa_no' => $kasa_no, 'created_at' => current_time('mysql'),
    ]);
    if (!$result) return new WP_Error('db_error', 'Masraf kaydedilemedi.', ['status' => 500]);
    return ['success' => true, 'id' => $wpdb->insert_id, 'message' => 'Masraf başarıyla kaydedildi.'];
}

/**
 * Masraf siler.
 */
function hizli_kasa_delete_masraf($request) {
    global $wpdb;
    $id = intval($request->get_param('id'));
    if (!$id) return new WP_Error('invalid_id', 'Geçersiz ID.', ['status' => 400]);
    $table = Hizli_Kasa_Database::get_tables()['masraflar'];
    $result = $wpdb->delete($table, ['id' => $id]);
    if (!$result) return new WP_Error('db_error', 'Masraf silinemedi.', ['status' => 500]);
    return ['success' => true, 'message' => 'Masraf silindi.'];
}

/**
 * Ürünleri toplu halde ve çok hızlı şekilde doldurur (Hydration).
 */
function hizli_kasa_hydrate_products_batch($ids, $depo_id) {
    global $wpdb;
    if (empty($ids)) return [];

    $ids_str = implode(',', array_map('intval', $ids));
    $stok_table = Hizli_Kasa_Database::get_tables()['stok_konumlari'];

    $posts = $wpdb->get_results("SELECT ID, post_title, post_type, post_parent FROM {$wpdb->posts} WHERE ID IN ($ids_str)");
    $meta_raw = $wpdb->get_results("SELECT post_id, meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id IN ($ids_str)");
    $meta_map = [];
    if (!empty($meta_raw)) {
        foreach ($meta_raw as $m) { $meta_map[$m->post_id][$m->meta_key] = $m->meta_value; }
    }

    $stok_raw = $wpdb->get_results($wpdb->prepare("
        SELECT * FROM $stok_table WHERE location_id = %d AND (product_id IN ($ids_str) OR variation_id IN ($ids_str))", $depo_id));
    $stok_map = [];
    if (!empty($stok_raw)) {
        foreach ($stok_raw as $s) {
            $key = ($s->variation_id > 0) ? 'v_' . $s->variation_id : 'p_' . $s->product_id;
            $stok_map[$key] = (float)$s->quantity;
        }
    }

    $types_raw = $wpdb->get_results("
        SELECT tr.object_id, t.slug FROM {$wpdb->term_relationships} tr
        JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
        JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
        WHERE tr.object_id IN ($ids_str) AND tt.taxonomy = 'product_type'");
    $type_map = [];
    if (!empty($types_raw)) {
        foreach ($types_raw as $t) { $type_map[$t->object_id] = $t->slug; }
    }

    $final = [];
    foreach ($posts as $p) {
        $pid = (int)$p->ID;
        $m = $meta_map[$pid] ?? [];
        $p_type = $type_map[$pid] ?? '';
        $stok_key = ($p->post_type === 'product_variation') ? 'v_' . $pid : 'p_' . $pid;
        $w_stock = $stok_map[$stok_key] ?? 0;
        $thumb_id = $m['_thumbnail_id'] ?? '';
        $img_url = $thumb_id ? wp_get_attachment_image_url($thumb_id, 'thumbnail') : '';

        $final[$pid] = [
            'id' => $pid, 'parent_id' => (int)$p->post_parent,
            'type' => ($p->post_type === 'product_variation') ? 'variation' : 'product',
            'name' => $p->post_title, 'sku' => $m['_sku'] ?? '',
            'price' => $m['_price'] ?? 0, 'regular_price' => $m['_regular_price'] ?? 0,
            'stock_status' => $m['_stock_status'] ?? 'instock',
            'manage_stock' => ($m['_manage_stock'] ?? 'no') === 'yes',
            'stock_quantity' => (float)($m['_stock'] ?? 0),
            'warehouse_stock' => $w_stock,
            'images' => $img_url ? [['src' => $img_url]] : [],
            'is_variable' => $p_type === 'variable',
            'variations' => []
        ];
    }
    return $final;
}
