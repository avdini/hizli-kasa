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

// REST API Route Kaydı
add_action('rest_api_init', function () {
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
});

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

    $results = [];
    $exact = $data->get_param('exact');

    if ($exact) {
        // Barkod okuyucu için tam SKU eşleşmesi
        $results = $wpdb->get_results($wpdb->prepare("
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
              AND p.post_type IN ('product', 'product_variation')
              AND p.ID IN (SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_sku' AND meta_value = %s)
            GROUP BY p.ID
            LIMIT 20
        ", $s));
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
            $results = $wpdb->get_results("
                SELECT p.ID, p.post_title, p.post_type, p.post_parent,
                       MAX(CASE WHEN pm.meta_key = '_sku' THEN pm.meta_value END) as sku,
                       MAX(CASE WHEN pm.meta_key = '_price' THEN pm.meta_value END) as price,
                       MAX(CASE WHEN pm.meta_key = '_regular_price' THEN pm.meta_value END) as regular_price,
                       MAX(CASE WHEN pm.meta_key = '_stock_status' THEN pm.meta_value END) as stock_status,
                       MAX(CASE WHEN pm.meta_key = '_manage_stock' THEN pm.meta_value END) as manage_stock,
                       MAX(CASE WHEN pm.meta_key = '_stock' THEN pm.meta_value END) as stock_quantity
                FROM {$wpdb->posts} p
                LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                WHERE p.ID IN ($ids_str)
                GROUP BY p.ID
                ORDER BY FIELD(p.ID, $ids_str)
            ");
        }
    }

    $formatted = [];
    foreach ($results as $row) {
        $formatted[] = hizli_kasa_format_urun_row($row);
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
                    $formatted[] = hizli_kasa_format_urun_row($grow);
                }
            }
        }
    }

    return $formatted;
}

/**
 * Veritabanından gelen ürün satırını formatlar.
 */
function hizli_kasa_format_urun_row($row) {
    $urun = wc_get_product($row->ID);
    if (!$urun) return null;

    $is_variable = $urun->is_type('variable');
    if ($is_variable) {
        $children = $urun->get_children();
        // Sadece yayında (publish) olan varyasyonları sayalım
        $active_children = array_filter($children, function($child_id) {
            return get_post_status($child_id) === 'publish';
        });
        if (empty($active_children)) {
            $is_variable = false;
        }
    }

    $name = $urun->get_name();
    if ($row->post_type === 'product_variation') {
        $parent = wc_get_product($row->post_parent);
        if ($parent) {
            $attributes = $urun->get_variation_attributes();
            $attr_values = implode(', ', array_values($attributes));
            $name = $parent->get_name() . ' - ' . $attr_values;
        }
    }

    $image_id = $urun->get_image_id();
    $image_url = $image_id ? wp_get_attachment_image_url($image_id, 'thumbnail') : '';

    return [
        'id' => (int)$row->ID,
        'parent_id' => (int)$row->post_parent,
        'type' => $row->post_type === 'product_variation' ? 'variation' : 'product',
        'name' => $name,
        'sku' => $row->sku,
        'price' => $row->price,
        'regular_price' => $row->regular_price,
        'stock_status' => $row->stock_status,
        'manage_stock' => $row->manage_stock === 'yes',
        'stock_quantity' => (float)$row->stock_quantity,
        'images' => $image_url ? [['src' => $image_url]] : [],
        'is_variable' => $is_variable
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
    $allowed_tabs = ['kasa', 'urunler', 'raporlar', 'ayarlar', 'iade'];
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
    $refund_order->set_payment_method('cod'); // Kapıda ödeme / İade faturası mantığı
    $refund_order->set_payment_method_title('İade İşlemi');
    $refund_order->update_meta_data('_hizli_kasa_original_order', $original_order_id);
    $refund_order->update_meta_data('_hizli_kasa_is_refund', 'yes');
    $refund_order->update_meta_data('_hizli_kasa_kasiyer', wp_get_current_user()->display_name);
    
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
