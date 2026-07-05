<?php
if (!defined('ABSPATH')) exit;

add_action('rest_api_init', function () {
    register_rest_route('hizli-kasa/v1', '/get-order', array(
        'methods' => 'GET',
        'callback' => 'hizli_kasa_get_order_details',
        'permission_callback' => function () {
            return hizli_kasa_can_access_app();
        }
    ));

    register_rest_route('hizli-kasa/v1', '/search-orders', array(
        'methods' => 'GET',
        'callback' => 'hizli_kasa_search_orders',
        'permission_callback' => function () {
            return hizli_kasa_can_access_app();
        }
    ));

    register_rest_route('hizli-kasa/v1', '/recent-orders', array(
        'methods' => 'GET',
        'callback' => 'hizli_kasa_get_recent_orders',
        'permission_callback' => function () {
            return hizli_kasa_can_access_app();
        }
    ));

    register_rest_route('hizli-kasa/v1', '/update-order', array(
        'methods' => 'POST',
        'callback' => 'hizli_kasa_update_order',
        'permission_callback' => function () {
            return hizli_kasa_can_access_app();
        }
    ));

    register_rest_route('hizli-kasa/v1', '/edit-logs', array(
        'methods' => 'GET',
        'callback' => 'hizli_kasa_get_edit_logs',
        'permission_callback' => function () {
            return hizli_kasa_can_access_app();
        }
    ));

});

/**
 * İade işlemi için sipariş detaylarını getirir.
 * Her ürün kalemine çıkış deposu bilgisini de ekler.
 */
function hizli_kasa_get_order_details($request)
{
    $order_id = sanitize_text_field($request->get_param('id'));
    $order = wc_get_order($order_id);

    if (!$order) {
        return new WP_Error('no_order', 'Sipariş bulunamadı.', array('status' => 404));
    }

    $depo_id = intval($request->get_param('depo_id'));
    if ($depo_id > 0) {
        $order_depo = (int) $order->get_meta('_hk_cikis_depo_id');
        if ($order_depo !== $depo_id) {
            return new WP_Error('wrong_depo', 'Bu sipariş farklı bir depoya ait olduğu için bu ekrandan iade edilemez.', array('status' => 403));
        }
    }

    // Depo adlarını ID'ye göre cache'le (aynı depo birden fazla item'da olabilir)
    $depo_names_cache = [];

    $items = [];
    $is_fully_refunded = ($order->get_meta('_hk_is_fully_refunded') === 'yes');

    foreach ($order->get_items() as $item_id => $item) {
        if (!$item instanceof WC_Order_Item_Product) {
            continue;
        }
        $product = $item->get_product();
        $cikis_depo_id = (int) wc_get_order_item_meta($item_id, '_hk_cikis_depo_id', true);
        $cikis_depo_adi = wc_get_order_item_meta($item_id, '_hk_cikis_depo_adi', true);

        // İade Takibi
        $refunded_qty = (int) wc_get_order_item_meta($item_id, '_hk_refunded_qty', true);
        $available_qty = $item->get_quantity() - $refunded_qty;

        if ($available_qty <= 0)
            continue;

        // Eğer depo adı meta'sı yoksa DB'den çek
        if ($cikis_depo_id && !$cikis_depo_adi) {
            if (!isset($depo_names_cache[$cikis_depo_id])) {
                global $wpdb;
                $tables = Hizli_Kasa_Database::get_tables();
                $depo_names_cache[$cikis_depo_id] = $wpdb->get_var($wpdb->prepare(
                    "SELECT name FROM {$tables['depolar']} WHERE id = %d",
                    $cikis_depo_id
                )) ?: 'Bilinmeyen';
            }
            $cikis_depo_adi = $depo_names_cache[$cikis_depo_id];
        }

        $image = '';
        if ($product) {
            $image_id = $product->get_image_id();
            if (!$image_id && $product->is_type('variation')) {
                $parent = wc_get_product($product->get_parent_id());
                $image_id = $parent ? $parent->get_image_id() : 0;
            }
            $image = $image_id ? wp_get_attachment_image_url($image_id, 'thumbnail') : '';
        }

        $items[] = [
            'item_id' => $item_id,
            'id' => $item->get_product_id(),
            'variation_id' => $item->get_variation_id(),
            'name' => $item->get_name(),
            'sku' => $product ? $product->get_sku() : '',
            'qty' => $available_qty,
            'original_qty' => $item->get_quantity(),
            'refunded_qty' => $refunded_qty,
            'price' => $item->get_total() / max($item->get_quantity(), 1),
            'total' => $item->get_total(),
            'depo_id' => $cikis_depo_id,
            'depo_adi' => $cikis_depo_adi ?: '',
            'item_discount' => (float) wc_get_order_item_meta($item_id, '_hk_item_discount', true),
            'image' => $image ?: '',
        ];
    }

    if (empty($items) && $is_fully_refunded) {
        return new WP_Error('fully_refunded', 'Bu siparişteki tüm ürünler zaten iade edilmiş.', array('status' => 400));
    }

    return [
        'id' => $order->get_id(),
        'date' => $order->get_date_created()->date('d.m.Y H:i'),
        'total' => $order->get_total(),
        'items' => $items,
        'payment' => $order->get_payment_method_title(),
        'payment_method' => $order->get_payment_method(),
        'payment_details' => [
            'nakit' => (float) $order->get_meta('_odeme_nakit'),
            'kart'  => (float) $order->get_meta('_odeme_kart'),
            'iban'  => (float) $order->get_meta('_odeme_iban'),
        ],
        'kasiyer' => $order->get_meta('_hizli_kasa_kasiyer') ?: 'Bilinmiyor',
        'kasa_no' => $order->get_meta('_hizli_kasa_kasa_no') ?: 'Bilinmiyor',
        'depo_id' => (int) $order->get_meta('_hk_cikis_depo_id'),
        'depo_adi' => $order->get_meta('_hk_cikis_depo_adi') ?: '',
        'telefon' => $order->get_meta('_hizli_kasa_musteri_telefon') ?: '',
        'siparis_notu' => $order->get_meta('_hizli_kasa_siparis_notu') ?: '',
        'is_fully_refunded' => $is_fully_refunded,
        'manual_discount' => hizli_kasa_get_order_manual_discount($order),
        'refunded_manual_discount' => (float) $order->get_meta('_hk_refunded_discount'),
        'total_discount' => hizli_kasa_get_order_total_discount($order),
        'refunded_discount' => (float) $order->get_meta('_hk_refunded_discount'),
        'has_item_discount' => (float) $order->get_meta('_hk_toplam_iskonto') > 0
    ];
}

/**
 * Gelişmiş sipariş arama (Telefon, Barkod, Tarih, Fiyat).
 */
function hizli_kasa_search_orders($request)
{
    $phone = sanitize_text_field($request->get_param('phone'));
    $barcode = sanitize_text_field($request->get_param('barcode'));
    $date_bas = sanitize_text_field($request->get_param('date_start'));
    $date_bit = sanitize_text_field($request->get_param('date_end'));
    $price_min = floatval($request->get_param('price_min'));
    $price_max = floatval($request->get_param('price_max'));
    $page = max(1, intval($request->get_param('page')));

    $args = array(
        'limit' => 50,
        'paged' => $page,
        'status' => array('processing', 'completed'),
        'orderby' => 'date',
        'order' => 'DESC',
    );

    $meta_query = array('relation' => 'AND');

    if (!empty($phone)) {
        $meta_query[] = array(
            'key' => '_hizli_kasa_musteri_telefon',
            'value' => $phone,
            'compare' => 'LIKE',
        );
    }

    $depo_id = intval($request->get_param('depo_id'));
    if ($depo_id > 0) {
        $meta_query[] = array(
            'key' => '_hk_cikis_depo_id',
            'value' => $depo_id,
        );
    }

    if (!empty($meta_query) && count($meta_query) > 1) {
        $args['meta_query'] = $meta_query;
    }

    if (!empty($date_bas) || !empty($date_bit)) {
        $date_query = '';
        if ($date_bas && $date_bit) {
            $date_query = $date_bas . '...' . $date_bit . ' 23:59:59';
        } elseif ($date_bas) {
            $date_query = '>=' . $date_bas;
        } else {
            $date_query = '<=' . $date_bit . ' 23:59:59';
        }
        $args['date_created'] = $date_query;
    }

    $orders = wc_get_orders($args);
    $results = [];

    foreach ($orders as $order) {
        // İade işlemi olarak oluşturulan negatif siparişleri listeleme
        if ($order->get_meta('_hizli_kasa_is_refund') === 'yes') {
            continue;
        }

        $total = (float) $order->get_total();

        // Fiyat filtresi (Manuel kontrol çünkü wc_get_orders ile karmaşık olabilir)
        if ($price_min > 0 && $total < $price_min)
            continue;
        if ($price_max > 0 && $total > $price_max)
            continue;

        // Barkod/Ürün filtresi
        if (!empty($barcode)) {
            $found = false;
            foreach ($order->get_items() as $item) {
                if (!$item instanceof WC_Order_Item_Product) {
                    continue;
                }
                $product = $item->get_product();
                if ($product && ($product->get_sku() === $barcode || (string) $product->get_id() === $barcode)) {
                    $found = true;
                    break;
                }
            }
            if (!$found)
                continue;
        }

        $results[] = [
            'id' => $order->get_id(),
            'date' => $order->get_date_created()->date('d.m.Y H:i'),
            'total' => $total,
            'kasiyer' => $order->get_meta('_hizli_kasa_kasiyer') ?: '-',
            'telefon' => $order->get_meta('_hizli_kasa_musteri_telefon') ?: '-',
            'is_fully_refunded' => ($order->get_meta('_hk_is_fully_refunded') === 'yes')
        ];
    }

    $has_more = (count($orders) === 50);

    return array(
        'results'  => $results,
        'has_more' => $has_more,
    );
}

/**
 * Kasiyerin düzenleyebileceği son siparişleri getirir.
 */
function hizli_kasa_get_recent_orders($request)
{
    $kasa_no = sanitize_text_field($request->get_param('kasa_no'));
    $depo_id = intval($request->get_param('depo_id'));
    $limit = get_option('hizli_kasa_edit_order_limit', 5);
    $user_id = get_current_user_id();

    // Eğer parametre gelmemişse aktif depoyu kullan (fallback)
    if (!$depo_id) {
        $depo_id = hizli_kasa_get_user_active_depo($user_id);
    }

    $kapsam = get_option('hizli_kasa_siparis_duzenle_kapsam', 'secili');

    $args = array(
        'limit' => $limit,
        'status' => array('processing', 'completed'),
        'date_created' => current_time('Y-m-d') . ' 00:00:00...' . current_time('Y-m-d') . ' 23:59:59',
        'orderby' => 'date',
        'order' => 'DESC',
    );

    $meta_query = array();

    if ($kapsam === 'tum') {
        $meta_query[] = array(
            'key' => '_hizli_kasa_kasa_no',
            'compare' => 'EXISTS',
        );
    } else {
        $meta_query[] = array(
            'key' => '_hizli_kasa_kasa_no',
            'value' => $kasa_no,
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
    $results = [];

    global $wpdb;
    $tables = Hizli_Kasa_Database::get_tables();
    $stok_table = $tables['stok_konumlari'];

    foreach ($orders as $order) {
        if ($order->get_meta('_hizli_kasa_is_refund') === 'yes')
            continue;

        $items = [];
        foreach ($order->get_items() as $item_id => $item) {
            if (!$item instanceof WC_Order_Item_Product) {
                continue;
            }
            $p_id = $item->get_product_id();
            $v_id = $item->get_variation_id();
            $target_id = $v_id ?: $p_id;
            $product = $item->get_product();

            // Site Stoku
            $site_stock = $product && $product->managing_stock() ? (float) $product->get_stock_quantity() : 999;

            // Depo Stoku
            $depo_stock = 0;
            if ($depo_id) {
                $depo_stock = (float) $wpdb->get_var($wpdb->prepare(
                    "SELECT quantity FROM $stok_table WHERE product_id = %d AND variation_id = %d AND location_id = %d",
                    $p_id,
                    $v_id,
                    $depo_id
                ));
            }

            $items[] = [
                'item_id' => $item_id,
                'name' => $item->get_name(),
                'qty' => $item->get_quantity(),
                'total' => $item->get_total(),
                'product_id' => $p_id,
                'variation_id' => $v_id,
                'site_stock' => $site_stock,
                'depo_stock' => $depo_stock,
                'max_qty' => $item->get_quantity() + min($site_stock, $depo_stock)
            ];
        }

        $payment_method = $order->get_payment_method();
        $is_split = ($payment_method === 'split');
        $has_refund = (!empty($order->get_refunds()) || $order->get_meta('_hk_has_refund') === 'yes' || $order->get_meta('_hk_is_fully_refunded') === 'yes');

        $results[] = [
            'id' => $order->get_id(),
            'total' => $order->get_total(),
            'payment_method' => $payment_method,
            'payment_title' => $order->get_payment_method_title(),
            'date' => $order->get_date_created()->date('H:i'),
            'has_refund' => $has_refund,
            'is_split' => $is_split,
            'discount' => hizli_kasa_get_order_manual_discount($order),
            'manual_discount' => hizli_kasa_get_order_manual_discount($order),
            'phone' => $order->get_meta('_hizli_kasa_musteri_telefon') ?: '',
            'items' => $items
        ];
    }

    return $results;
}

/**
 * Sipariş düzenleme işlemini gerçekleştirir.
 */
function hizli_kasa_update_order($request)
{
    $data = $request->get_json_params();
    $order_id = intval($data['order_id']);
    $new_payment = sanitize_text_field($data['payment_method'] ?? '');
    $new_discount = isset($data['discount']) ? floatval($data['discount']) : null;
    $new_phone = sanitize_text_field($data['phone'] ?? '');
    $new_note = sanitize_text_field($data['note'] ?? '');
    $new_split = $data['split_data'] ?? null;
    $items = $data['items'] ?? [];

    $order = wc_get_order($order_id);
    if (!$order)
        return new WP_Error('no_order', 'Sipariş bulunamadı.');

    // Guard: İade görmüş sipariş düzenlenemez
    $has_refund = (!empty($order->get_refunds()) || $order->get_meta('_hk_has_refund') === 'yes' || $order->get_meta('_hk_is_fully_refunded') === 'yes');
    if ($has_refund) {
        return new WP_Error('edit_not_allowed', 'Bu sipariş iade işlemi gördüğü için düzenlenemez.');
    }

    $old_data = [
        'total' => $order->get_total(),
        'payment' => $order->get_payment_method(),
        'phone' => $order->get_meta('_hizli_kasa_musteri_telefon') ?: '',
        'note' => $order->get_meta('_hizli_kasa_siparis_notu') ?: '',
        'discount' => hizli_kasa_get_order_manual_discount($order),
        'items' => []
    ];

    $depo_id = (int) $order->get_meta('_hk_cikis_depo_id');
    $depo_adi = $order->get_meta('_hk_cikis_depo_adi') ?: '';
    $edit_reason = sanitize_text_field($data['edit_reason'] ?? 'Belirtilmedi');
    $log_details = [];
    $log_details[] = "Sebep: " . $edit_reason;

    // 0. Telefon Güncelleme
    $old_phone = $old_data['phone'];
    if ($new_phone !== $old_phone) {
        $order->update_meta_data('_hizli_kasa_musteri_telefon', $new_phone);
        $order->set_billing_phone($new_phone);
        $log_details[] = "Telefon: " . ($old_phone ?: 'Yok') . " -> " . ($new_phone ?: 'Yok');
    }

    // 1. Sipariş Notu Güncelleme
    $old_note = $old_data['note'];
    if ($new_note !== $old_note) {
        $order->update_meta_data('_hizli_kasa_siparis_notu', $new_note);
        $order->set_customer_note($new_note);
        $log_details[] = "Not: " . ($old_note ?: 'Yok') . " -> " . ($new_note ?: 'Yok');
    }

    // 2. Ödeme Yöntemi Değişikliği
    if ($new_payment && $new_payment !== $order->get_payment_method()) {
        $payment_titles = [
            'cod' => 'Nakit',
            'bacs' => 'IBAN / Havale',
            'other' => 'Kredi Kartı',
            'split' => 'Bölünmüş Ödeme'
        ];
        $old_p = $order->get_payment_method();
        $order->set_payment_method($new_payment);
        $order->set_payment_method_title($payment_titles[$new_payment] ?? $new_payment);
        $log_details[] = "Ödeme: $old_p -> $new_payment";
    }

    // 3. Ürünler ve Stoklar Senkronizasyonu
    require_once HIZLI_KASA_PATH . 'includes/classes/class-stock-manager.php';

    // Mevcut sipariş kalemlerini çek
    $existing_items = [];
    foreach ($order->get_items() as $item_id => $item) {
        if (!$item instanceof WC_Order_Item_Product) {
            continue;
        }
        $p_id = $item->get_product_id();
        $v_id = $item->get_variation_id();
        $key = $p_id . '_' . $v_id;
        $existing_items[$key] = [
            'item_id' => $item_id,
            'qty' => $item->get_quantity(),
            'item' => $item
        ];
        $old_data['items'][] = [
            'product_id' => $p_id,
            'variation_id' => $v_id,
            'qty' => $item->get_quantity(),
            'name' => $item->get_name()
        ];
    }

    // Stok güncelleme yardımcı fonksiyonu
    $adjust_stock = function($p_id, $v_id, $diff) use ($order_id, $depo_id) {
        if ($diff == 0) return;
        $product = wc_get_product($v_id ?: $p_id);

        if ($diff > 0) {
            // Miktar arttı -> Stok düşür
            if ($depo_id) {
                Hizli_Kasa_Stock_Manager::update_warehouse_stock(
                    $p_id,
                    $v_id,
                    $depo_id,
                    -$diff,
                    "Sipariş Düzenleme (#$order_id) - Arttırma"
                );
            }
            if ($product && $product->managing_stock()) {
                wc_update_product_stock($product, $diff, 'decrease');
            }
        } else {
            // Miktar azaldı -> Stok geri ekle
            $abs_diff = abs($diff);
            if ($depo_id) {
                Hizli_Kasa_Stock_Manager::update_warehouse_stock(
                    $p_id,
                    $v_id,
                    $depo_id,
                    $abs_diff,
                    "Sipariş Düzenleme (#$order_id) - İade"
                );
            }
            if ($product && $product->managing_stock()) {
                wc_update_product_stock($product, $abs_diff, 'increase');
            }
        }
    };

    $new_items_keys = [];
    foreach ($items as $new_item) {
        $p_id = intval($new_item['product_id']);
        $v_id = intval($new_item['variation_id'] ?? 0);
        $new_qty = intval($new_item['qty']);
        $key = $p_id . '_' . $v_id;
        $new_items_keys[] = $key;

        if (isset($existing_items[$key])) {
            // Var olan ürün
            $old_qty = $existing_items[$key]['qty'];
            $item = $existing_items[$key]['item'];
            $item_id = $existing_items[$key]['item_id'];

            if ($new_qty != $old_qty) {
                $diff = $new_qty - $old_qty;
                $adjust_stock($p_id, $v_id, $diff);

                if ($new_qty <= 0) {
                    $order->remove_item($item_id);
                    $log_details[] = $item->get_name() . " çıkarıldı.";
                } else {
                    $item->set_quantity($new_qty);
                    $item->set_subtotal(wc_format_decimal(($item->get_subtotal() / $old_qty) * $new_qty));
                    $item->set_total(wc_format_decimal(($item->get_total() / $old_qty) * $new_qty));
                    $item->save();
                    $log_details[] = $item->get_name() . ": $old_qty -> $new_qty";
                }
            }
        } else {
            // Yeni eklenen ürün
            if ($new_qty > 0) {
                $adjust_stock($p_id, $v_id, $new_qty);

                $product = wc_get_product($v_id ?: $p_id);
                if ($product) {
                    $item_id = $order->add_product($product, $new_qty);
                    if ($item_id) {
                        wc_add_order_item_meta($item_id, '_hk_cikis_depo_id', $depo_id, true);
                        wc_add_order_item_meta($item_id, '_hk_cikis_depo_adi', $depo_adi, true);
                        $log_details[] = $product->get_name() . " eklendi (Adet: $new_qty).";
                    }
                }
            }
        }
    }

    // Siparişte olan ama yeni sepette olmayan ürünleri çıkar
    foreach ($existing_items as $key => $data) {
        if (!in_array($key, $new_items_keys)) {
            $item = $data['item'];
            $item_id = $data['item_id'];
            $old_qty = $data['qty'];
            $p_id = $item->get_product_id();
            $v_id = $item->get_variation_id();

            // Miktar azaldığı için fark negatif, stokları geri ekle
            $adjust_stock($p_id, $v_id, -$old_qty);

            $order->remove_item($item_id);
            $log_details[] = $item->get_name() . " çıkarıldı.";
        }
    }

    // 4. İskonto Güncelleme
    if ($new_discount !== null && round($new_discount, 2) != round($old_data['discount'], 2)) {
        // Mevcut manual fee'leri sil
        foreach ($order->get_fees() as $fee_id => $fee) {
            if (hizli_kasa_is_manual_discount_fee($fee)) {
                $order->remove_item($fee_id);
            }
        }

        // Ürün bazlı indirimleri topla
        $product_discount_total = 0;
        foreach ($order->get_items() as $item_id => $item) {
            if (!$item instanceof WC_Order_Item_Product) {
                continue;
            }
            $product_discount_total += (float) wc_get_order_item_meta($item_id, '_hk_item_discount', true);
        }

        $fee_amount = $new_discount - $product_discount_total;
        if (round($fee_amount, 2) != 0.0) {
            $item_fee = new WC_Order_Item_Fee();
            $item_fee->set_name('Düzenlenmiş İskonto');
            $item_fee->set_amount(-$fee_amount);
            $item_fee->set_total(wc_format_decimal(-$fee_amount));
            $item_fee->add_meta_data('_hk_manual_discount', 'yes', true);
            $order->add_item($item_fee);
        }

        $order->update_meta_data('_hk_toplam_iskonto', number_format($new_discount, 2, '.', ''));
        $log_details[] = "İskonto: " . $old_data['discount'] . " -> " . $new_discount;
    }

    $order->calculate_totals();

    // 5. Ödeme Metalarını Güncelle
    $final_total = (float) $order->get_total();
    $payment_method = $order->get_payment_method();

    $order->update_meta_data('_odeme_nakit', 0);
    $order->update_meta_data('_odeme_kart', 0);
    $order->update_meta_data('_odeme_iban', 0);
    $order->delete_meta_data('Ödeme (Nakit)');
    $order->delete_meta_data('Ödeme (Kredi Kartı)');
    $order->delete_meta_data('Ödeme (IBAN)');

    if ($payment_method === 'cod') {
        $order->update_meta_data('_odeme_nakit', $final_total);
        $order->update_meta_data('Ödeme (Nakit)', number_format($final_total, 2, '.', '') . ' TL');
    } elseif ($payment_method === 'other') {
        $order->update_meta_data('_odeme_kart', $final_total);
        $order->update_meta_data('Ödeme (Kredi Kartı)', number_format($final_total, 2, '.', '') . ' TL');
    } elseif ($payment_method === 'bacs') {
        $order->update_meta_data('_odeme_iban', $final_total);
        $order->update_meta_data('Ödeme (IBAN)', number_format($final_total, 2, '.', '') . ' TL');
    } elseif ($payment_method === 'split') {
        $nakit = isset($new_split['nakit']) ? floatval($new_split['nakit']) : 0;
        $kart = isset($new_split['kart']) ? floatval($new_split['kart']) : 0;
        $iban = isset($new_split['iban']) ? floatval($new_split['iban']) : 0;

        $sum = $nakit + $kart + $iban;
        if (abs($sum - $final_total) > 0.05) {
            if ($sum == 0) {
                $nakit = $final_total;
            } else {
                $factor = $final_total / $sum;
                $nakit = round($nakit * $factor, 2);
                $kart = round($kart * $factor, 2);
                $iban = $final_total - $nakit - $kart;
            }
        }

        $order->update_meta_data('_odeme_nakit', $nakit);
        $order->update_meta_data('_odeme_kart', $kart);
        $order->update_meta_data('_odeme_iban', $iban);
        if ($nakit > 0) $order->update_meta_data('Ödeme (Nakit)', number_format($nakit, 2, '.', '') . ' TL');
        if ($kart > 0) $order->update_meta_data('Ödeme (Kredi Kartı)', number_format($kart, 2, '.', '') . ' TL');
        if ($iban > 0) $order->update_meta_data('Ödeme (IBAN)', number_format($iban, 2, '.', '') . ' TL');
    }

    // Custom Raporlama Metalarını Güncelle
    $sepet_ara_toplam = 0;
    $sepet_regular_toplam = 0;
    foreach ($order->get_items() as $item) {
        if (!$item instanceof WC_Order_Item_Product) continue;
        $product = $item->get_product();
        $qty = $item->get_quantity();
        $sepet_ara_toplam += $item->get_total();
        if ($product) {
            $sepet_regular_toplam += ($product->get_regular_price() ?: $product->get_price()) * $qty;
        } else {
            $sepet_regular_toplam += $item->get_total();
        }
    }
    
    $order->update_meta_data('_ara_toplam', number_format($sepet_ara_toplam, 2, '.', ''));
    $order->update_meta_data('_etiket_toplami', number_format($sepet_regular_toplam, 2, '.', ''));
    $order->update_meta_data('_hk_customer_paid_total', number_format($final_total, 2, '.', ''));

    $order->save();

    // 6. Log Kaydı
    global $wpdb;
    $table = Hizli_Kasa_Database::get_tables()['order_edits'];
    $wpdb->insert($table, [
        'order_id' => $order_id,
        'kasa_no' => $order->get_meta('_hizli_kasa_kasa_no') ?: '1',
        'user_id' => get_current_user_id(),
        'action_type' => 'manual_edit',
        'old_data' => json_encode($old_data),
        'new_data' => json_encode($log_details),
        'created_at' => current_time('mysql')
    ]);

    return ['success' => true, 'new_total' => $order->get_total()];
}

/**
 * Düzenleme loglarını raporlar için getirir.
 */
function hizli_kasa_get_edit_logs($request)
{
    global $wpdb;
    $table = Hizli_Kasa_Database::get_tables()['order_edits'];

    $date_start = $request->get_param('date_start') ?: current_time('Y-m-d');
    $date_end = $request->get_param('date_end') ?: current_time('Y-m-d');

    $results = $wpdb->get_results($wpdb->prepare(
        "SELECT l.*, u.display_name as user_name 
         FROM $table l 
         LEFT JOIN {$wpdb->users} u ON l.user_id = u.ID 
         WHERE DATE(l.created_at) BETWEEN %s AND %s 
         ORDER BY l.created_at DESC",
        $date_start,
        $date_end
    ));

    return $results;
}

