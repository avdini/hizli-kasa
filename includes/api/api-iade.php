<?php
if (!defined('ABSPATH')) exit;

add_action('rest_api_init', function () {
    register_rest_route('hizli-kasa/v1', '/process-refund', array(
        'methods' => 'POST',
        'callback' => 'hizli_kasa_process_refund',
        'permission_callback' => function () {
            return hizli_kasa_can_access_app();
        }
    ));

});

/**
 * İade (Negatif Sipariş) oluşturur.
 * Orijinal siparişin çıkış deposuna geri stok ekler.
 */
function hizli_kasa_process_refund($request)
{
    // WooCommerce email bildirimlerini bu işlem için devre dışı bırakıyoruz.
    // İade (negatif) siparişleri standart şablonlarda hatalı göründüğü için özel bir mail göndereceğiz.
    $emails_to_disable = ['new_order', 'customer_completed_order', 'customer_processing_order', 'customer_on_hold_order', 'customer_refunded_order', 'customer_invoice'];
    foreach ($emails_to_disable as $email_id) {
        add_filter("woocommerce_email_enabled_{$email_id}", '__return_false', 999);
    }

    $data = $request->get_json_params();
    $original_order_id = !empty($data['original_order_id']) ? sanitize_text_field($data['original_order_id']) : null;
    $is_manual = !empty($data['is_manual']) && $data['is_manual'] === true;
    $refund_items = $data['items'];

    if (empty($refund_items)) {
        return new WP_Error('no_items', 'İade edilecek ürün seçilmedi.', array('status' => 400));
    }

    // Orijinal siparişi yükle (eğer varsa)
    $original_order = $original_order_id ? wc_get_order($original_order_id) : null;

    if ($original_order_id && !$original_order) {
        return new WP_Error('invalid_order', 'Orijinal sipariş bulunamadı.', array('status' => 404));
    }

    // --- Orijinal Sipariş İade Validasyonları ---
    if ($original_order) {
        foreach ($refund_items as $item) {
            if (empty($item['item_id'])) {
                return new WP_Error('invalid_item', 'İade edilmek istenen ürünün orijinal sipariş satır bilgisi eksik.', array('status' => 400));
            }
            
            $orig_item_id = intval($item['item_id']);
            $original_item = $original_order->get_item($orig_item_id);
            
            if (!$original_item || !is_a($original_item, 'WC_Order_Item_Product')) {
                return new WP_Error('invalid_item', 'Orijinal siparişte böyle bir ürün bulunamadı.', array('status' => 400));
            }
            
            $item_product_id = intval($original_item->get_product_id());
            $item_variation_id = intval($original_item->get_variation_id());
            $returned_product_id = intval($item['id']);
            $returned_variation_id = intval($item['variation_id'] ?? 0);
            
            if (($returned_variation_id > 0 && $item_variation_id !== $returned_variation_id) || 
                ($returned_variation_id === 0 && $item_product_id !== $returned_product_id)) {
                return new WP_Error('item_mismatch', 'İade edilen ürün orijinal siparişteki ürünle eşleşmiyor.', array('status' => 400));
            }
            
            $qty = abs($item['qty']);
            $price = abs($item['price']);
            
            $orig_qty = $original_item->get_quantity();
            $orig_total = $original_item->get_total();
            $orig_unit_price = $orig_qty > 0 ? ($orig_total / $orig_qty) : 0;
            
            // Fiyat kontrolü (2 basamak yuvarlama ile)
            if (round($price, 2) > round($orig_unit_price, 2)) {
                return new WP_Error('price_limit_exceeded', sprintf(
                    'İade birim fiyatı (%s TL), orijinal satın alma fiyatını (%s TL) geçemez.',
                    number_format($price, 2, '.', ''),
                    number_format($orig_unit_price, 2, '.', '')
                ), array('status' => 400));
            }
            
            // Miktar kontrolü
            $already_refunded = (int) wc_get_order_item_meta($orig_item_id, '_hk_refunded_qty', true);
            if ($already_refunded + $qty > $orig_qty) {
                return new WP_Error('qty_limit_exceeded', sprintf(
                    'İade adedi (%d), satın alınan adetten (%d) fazla olamaz. (Daha önce iade edilen: %d)',
                    $qty,
                    $orig_qty,
                    $already_refunded
                ), array('status' => 400));
            }
        }
    }

    $refund_order = wc_create_order(array('status' => 'completed', 'customer_id' => 0));

    // Kasiyer ve Kasa Bilgilerini Al
    $current_user = wp_get_current_user();
    $full_name = trim($current_user->first_name . ' ' . $current_user->last_name);
    $display_name = !empty($full_name) ? $full_name : $current_user->display_name;
    $kasa_no = sanitize_text_field($data['kasa_no'] ?? '1');

    // Fatura ve Teslimat Bilgilerini Set Et (Sipariş listesinde görünmesi için)
    $address = array(
        'first_name' => $display_name,
        'last_name' => 'Kasa ' . $kasa_no,
        'company' => $is_manual ? 'Manuel İade' : 'POS İade',
        'address_1' => 'POS Terminali',
        'city' => 'Mağaza',
        'country' => 'TR'
    );
    $refund_order->set_address($address, 'billing');
    $refund_order->set_address($address, 'shipping');

    $total_refund = 0;

    foreach ($refund_items as $item) {
        $qty = abs($item['qty']);
        $neg_qty = $qty * -1;
        $price = abs($item['price']);
        $line_total = $price * $neg_qty;

        $product = wc_get_product($item['id']);
        if ($product) {
            $item_id = $refund_order->add_product($product, 1, array(
                'totals' => array('subtotal' => $line_total, 'subtotal_tax' => 0, 'total' => $line_total, 'tax' => 0)
            ));
            /** @var WC_Order_Item_Product $refund_item */
            $refund_item = $refund_order->get_item($item_id);
            $refund_item->set_quantity($neg_qty);
            $refund_item->set_total(wc_format_decimal($line_total));
            $refund_item->save();
            $total_refund += $line_total;

            // --- Orijinal Siparişte İade Edilen Adedi Güncelle ---
            if ($original_order && !empty($item['item_id'])) {
                $orig_item_id = intval($item['item_id']);
                $current_refunded = (int) wc_get_order_item_meta($orig_item_id, '_hk_refunded_qty', true);
                wc_update_order_item_meta($orig_item_id, '_hk_refunded_qty', $current_refunded + $qty);
            }
        }
    }

    // --- İade İskonto Kesintisi Ekle ---
    $refund_discount = floatval($data['refund_discount'] ?? 0);
    if ($refund_discount > 0) {
        $fee = new WC_Order_Item_Fee();
        $fee->set_name('İade İskonto Kesintisi');
        $fee->set_amount($refund_discount);
        $fee->set_total(wc_format_decimal($refund_discount));
        $refund_order->add_item($fee);

        // Orijinal siparişteki iade edilen iskonto bilgisini güncelle
        if ($original_order) {
            $current_refunded_discount = (float) $original_order->get_meta('_hk_refunded_discount');
            $original_order->update_meta_data('_hk_refunded_discount', $current_refunded_discount + $refund_discount);
        }
    }

    // Orijinal siparişi iade/değişim görmüş olarak işaretle ve kaydet
    if ($original_order) {
        $original_order->update_meta_data('_hk_has_refund', 'yes');
        $original_order->save();
    }

    $refund_order->set_payment_method('cod');
    $refund_order->set_payment_method_title('İade İşlemi');

    // POS Standart Meta Verileri
    $iade_toplam = $total_refund; // Döngüde hesapladığımız toplam (negatif değer)

    if ($original_order_id) {
        $refund_order->update_meta_data('_hizli_kasa_original_order', $original_order_id);
    }
    $refund_order->update_meta_data('_hizli_kasa_is_refund', 'yes');
    if ($is_manual) {
        $refund_order->update_meta_data('_hizli_kasa_manual_refund', 'yes');
    }
    $refund_order->update_meta_data('_hizli_kasa_kaynak', 'pos_iade');
    $refund_order->update_meta_data('_hizli_kasa_kasiyer', $display_name); // Yukarıdaki değişkeni kullan
    $refund_order->update_meta_data('_hizli_kasa_kasa_no', $kasa_no); // Yukarıdaki değişkeni kullan

    // Ödeme Detayları
    $final_refund_total = $total_refund + $refund_discount;
    $payment_method = sanitize_text_field($data['payment_method'] ?? 'nakit');
    
    if ($payment_method === 'nakit') {
        $refund_order->update_meta_data('_odeme_nakit', $final_refund_total);
        $refund_order->update_meta_data('Ödeme (Nakit)', number_format(abs($final_refund_total), 2, '.', '') . ' TL');
    } elseif ($payment_method === 'kart') {
        $refund_order->update_meta_data('_odeme_kart', $final_refund_total);
        $refund_order->update_meta_data('Ödeme (Kart)', number_format(abs($final_refund_total), 2, '.', '') . ' TL');
    } elseif ($payment_method === 'iban') {
        $refund_order->update_meta_data('_odeme_iban', $final_refund_total);
        $refund_order->update_meta_data('Ödeme (IBAN)', number_format(abs($final_refund_total), 2, '.', '') . ' TL');
    } elseif ($payment_method === 'split') {
        $split_data = $data['split_data'] ?? [];
        $s_nakit = floatval($split_data['nakit'] ?? 0) * -1;
        $s_kart = floatval($split_data['kart'] ?? 0) * -1;
        $s_iban = floatval($split_data['iban'] ?? 0) * -1;
        
        if ($s_nakit != 0) {
            $refund_order->update_meta_data('_odeme_nakit', $s_nakit);
            $refund_order->update_meta_data('Ödeme (Nakit)', number_format(abs($s_nakit), 2, '.', '') . ' TL');
        }
        if ($s_kart != 0) {
            $refund_order->update_meta_data('_odeme_kart', $s_kart);
            $refund_order->update_meta_data('Ödeme (Kart)', number_format(abs($s_kart), 2, '.', '') . ' TL');
        }
        if ($s_iban != 0) {
            $refund_order->update_meta_data('_odeme_iban', $s_iban);
            $refund_order->update_meta_data('Ödeme (IBAN)', number_format(abs($s_iban), 2, '.', '') . ' TL');
        }
    } elseif ($payment_method === 'coupon') {
        $refund_order->set_payment_method('coupon');
        $refund_order->set_payment_method_title('Kupon');
        $coupon_phone = sanitize_text_field($data['coupon_phone'] ?? '');
        $refund_order->update_meta_data('_odeme_coupon', $final_refund_total);
        $refund_order->update_meta_data('Ödeme (Kupon)', number_format(abs($final_refund_total), 2, '.', '') . ' TL');
        $coupon_suffix = strtoupper(implode('-', str_split(bin2hex(random_bytes(4)), 4)));
        $coupon_code = 'KUPON-' . $coupon_suffix;
        $coupon = new WC_Coupon();
        $coupon->set_code($coupon_code);
        $coupon->set_discount_type('fixed_cart');
        $coupon->set_amount(abs($final_refund_total));
        $coupon->set_usage_limit(1);
        $coupon->set_description('POS İade Çeki. Kaynak Sipariş: #' . ($original_order_id ?: 'Manuel'));
        $coupon->add_meta_data('_hizli_kasa_coupon_phone', $coupon_phone);
        $coupon->save();

        $refund_order->update_meta_data('_verilen_kupon_kodu', $coupon_code);

        $generated_coupon = array(
            'code' => $coupon_code,
            'amount' => abs($final_refund_total),
            'phone' => $coupon_phone,
            'date' => date_i18n('d.m.Y H:i')
        );
    }

    // Toplamlar (Raporlar için)
    $refund_order->update_meta_data('_ara_toplam', $final_refund_total);
    $refund_order->update_meta_data('_etiket_toplami', $final_refund_total);

    $user_id = get_current_user_id();
    $fallback_depo_id = intval($data['active_depo_id'] ?? 0);
    if (!$fallback_depo_id)
        $fallback_depo_id = hizli_kasa_get_user_active_depo($user_id);

    // İade işleminin yapıldığı depoyu sipariş seviyesinde kaydet (raporlama için)
    if ($fallback_depo_id) {
        $refund_order->update_meta_data('_hk_cikis_depo_id', $fallback_depo_id);
        
        global $wpdb;
        $tables = Hizli_Kasa_Database::get_tables();
        $depo_name = $wpdb->get_var($wpdb->prepare(
            "SELECT name FROM {$tables['depolar']} WHERE id = %d",
            $fallback_depo_id
        ));

        if ($depo_name) {
            $refund_order->update_meta_data('_hk_cikis_depo_adi', $depo_name);
        }
    }

    // Depo stok iadesi — orijinal çıkış deposuna geri yaz
    require_once HIZLI_KASA_PATH . 'includes/classes/class-stock-manager.php';
    $iade_depo_ozet = []; // Hangi depoya ne kadar iade edildi

    foreach ($refund_items as $item) {
        $product_id = intval($item['id']);
        $variation_id = intval($item['variation_id'] ?? 0);
        $qty = abs($item['qty']);

        // 1. İade item'ından gelen depo_id (JS'den gönderilen, orijinal siparişten okunan)
        $target_depo_id = intval($item['depo_id'] ?? 0);

        // 2. Fallback: Orijinal siparişin item meta'sından çıkış deposunu bul
        if (!$target_depo_id && $original_order) {
            foreach ($original_order->get_items() as $orig_item_id => $orig_item) {
                if (!$orig_item instanceof WC_Order_Item_Product) {
                    continue;
                }
                $match_product = ($orig_item->get_product_id() == $product_id);
                $match_variation = ($orig_item->get_variation_id() == $variation_id);
                if ($match_product && ($variation_id == 0 || $match_variation)) {
                    $target_depo_id = (int) wc_get_order_item_meta($orig_item_id, '_hk_cikis_depo_id', true);
                    break;
                }
            }
        }

        // 3. Son fallback: Aktif depo
        if (!$target_depo_id) {
            $target_depo_id = $fallback_depo_id;
        }

        if ($target_depo_id && hizli_kasa_can_user_manage_depo($user_id, $target_depo_id)) {
            // 1. DEPO STOĞUNU ARTIR
            Hizli_Kasa_Stock_Manager::update_warehouse_stock(
                $product_id,
                $variation_id,
                $target_depo_id,
                $qty,
                $is_manual ? "Manuel İade İşlemi (#{$refund_order->get_id()})" : "İade İşlemi (Geri Dönüş - #$original_order_id, Depo: $target_depo_id)"
            );

            // 2. ANA SİTE STOĞUNU ARTIR
            $target_product = wc_get_product($variation_id ?: $product_id);
            if ($target_product && $target_product->managing_stock()) {
                wc_update_product_stock($target_product, $qty, 'increase');
                hizli_kasa_log("İade: Ana site stoğu artırıldı. Ürün: $product_id, Adet: $qty");
            }

            // Özete ekle
            if (!isset($iade_depo_ozet[$target_depo_id]))
                $iade_depo_ozet[$target_depo_id] = 0;
            $iade_depo_ozet[$target_depo_id] += $qty;
        }
    }

    // İade siparişine de depo özetini yaz
    if (!empty($iade_depo_ozet)) {
        $refund_order->update_meta_data('_hk_iade_depo_ozet', json_encode($iade_depo_ozet));
    }

    $refund_order->calculate_totals();
    $refund_order->save();

    // Özel iade bildirim mailini gönder
    hizli_kasa_send_custom_refund_email($refund_order);

    // --- Orijinal Siparişin Tamamının İade Edilip Edilmediğini Kontrol Et ---
    if ($original_order) {
        $all_refunded = true;
        foreach ($original_order->get_items() as $orig_item_id => $orig_item) {
            if (!$orig_item instanceof WC_Order_Item_Product) {
                continue;
            }
            $orig_qty = $orig_item->get_quantity();
            $total_refunded = (int) wc_get_order_item_meta($orig_item_id, '_hk_refunded_qty', true);

            if ($total_refunded < $orig_qty) {
                $all_refunded = false;
                break;
            }
        }

        if ($all_refunded) {
            $original_order->update_meta_data('_hk_is_fully_refunded', 'yes');
            $original_order->save();
        }
    }

    $response_data = array(
        'success' => true,
        'order_id' => $refund_order->get_id(),
        'total' => $refund_order->get_total(),
        'message' => 'İade başarıyla oluşturuldu.'
    );

    if (isset($generated_coupon)) {
        $response_data['coupon'] = $generated_coupon;
    }

    return $response_data;
}

/**
 * İade siparişi için özel HTML e-postası gönderir.
 */
function hizli_kasa_send_custom_refund_email($order)
{
    if (!$order || !is_a($order, 'WC_Order'))
        return;

    $order_id = $order->get_id();
    $original_order_id = $order->get_meta('_hizli_kasa_original_order');
    $kasiyer = $order->get_meta('_hizli_kasa_kasiyer') ?: 'Bilinmeyen';
    $kasa_no = $order->get_meta('_hizli_kasa_kasa_no') ?: '1';
    $total = number_format(abs($order->get_total()), 2, ',', '.');
    $date = $order->get_date_created()->date('d.m.Y H:i');

    $admin_email = get_option('admin_email');
    $subject = "🔄 Yeni İade İşlemi Bildirimi (#{$order_id})";

    $items_html = '';
    foreach ($order->get_items() as $item) {
        if (!$item instanceof WC_Order_Item_Product) {
            continue;
        }
        $items_html .= sprintf(
            '<li style="margin-bottom: 8px;"><strong>%s</strong><br><span style="color:#7f8c8d; font-size:13px;">%d adet x %s TL</span></li>',
            $item->get_name(),
            abs($item->get_quantity()),
            number_format(abs($item->get_total() / $item->get_quantity()), 2, ',', '.')
        );
    }

    $message = "
    <html>
    <body style='font-family: Arial, sans-serif; background-color: #f4f7f6; padding: 20px; margin: 0;'>
        <div style='max-width: 600px; margin: 20px auto; background-color: #ffffff; padding: 30px; border-radius: 12px; border: 1px solid #e0e0e0; box-shadow: 0 4px 12px rgba(0,0,0,0.08);'>
            <div style='text-align: center; margin-bottom: 30px;'>
                <div style='background-color: #fff4e6; width: 70px; height: 70px; line-height: 70px; border-radius: 50%; margin: 0 auto 15px; font-size: 35px;'>🔄</div>
                <h2 style='color: #e67e22; margin: 0; font-size: 24px;'>Yeni İade İşlemi</h2>
                <p style='color: #7f8c8d; margin: 5px 0 0; font-size: 16px;'>POS terminalinden iade faturası kesildi</p>
            </div>
            
            <div style='background-color: #f9f9f9; border-radius: 10px; padding: 20px; margin-bottom: 30px;'>
                <table style='width: 100%; border-collapse: collapse;'>
                    <tr>
                        <td style='padding: 10px 0; color: #7f8c8d; font-size: 14px;'>İade Numarası:</td>
                        <td style='padding: 10px 0; font-weight: bold; text-align: right;'>#{$order_id}</td>
                    </tr>
                    " . ($original_order_id ? "
                    <tr>
                        <td style='padding: 10px 0; color: #7f8c8d; font-size: 14px;'>Asıl Sipariş:</td>
                        <td style='padding: 10px 0; font-weight: bold; text-align: right;'>#{$original_order_id}</td>
                    </tr>" : "") . "
                    <tr>
                        <td style='padding: 10px 0; color: #7f8c8d; font-size: 14px;'>İşlem Tarihi:</td>
                        <td style='padding: 10px 0; text-align: right;'>{$date}</td>
                    </tr>
                    <tr>
                        <td style='padding: 10px 0; color: #7f8c8d; font-size: 14px;'>Kasiyer / Kasa:</td>
                        <td style='padding: 10px 0; text-align: right;'>{$kasiyer} (Kasa {$kasa_no})</td>
                    </tr>
                    <tr style='border-top: 2px solid #eeeeee;'>
                        <td style='padding: 20px 0 0; font-size: 20px; font-weight: bold; color: #e67e22;'>Toplam İade:</td>
                        <td style='padding: 20px 0 0; font-size: 20px; font-weight: bold; color: #e67e22; text-align: right;'>{$total} TL</td>
                    </tr>
                </table>
            </div>
            
            <div style='margin-bottom: 10px; font-weight: bold; color: #2c3e50; font-size: 16px; border-bottom: 2px solid #f4f7f6; padding-bottom: 8px;'>İade Edilen Ürünler</div>
            <ul style='padding-left: 20px; color: #34495e; line-height: 1.5; margin-top: 15px;'>
                {$items_html}
            </ul>
            
            <div style='margin-top: 40px; padding-top: 20px; border-top: 1px solid #eeeeee; text-align: center; font-size: 13px; color: #95a5a6;'>
                <p>Bu bilgilendirme e-postası <strong>Hızlı Kasa POS</strong> sistemi tarafından otomatik olarak oluşturulmuştur.</p>
            </div>
        </div>
    </body>
    </html>";

    $headers = array('Content-Type: text/html; charset=UTF-8');
    wp_mail($admin_email, $subject, $message, $headers);
}

