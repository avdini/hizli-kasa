<?php
if (!defined('ABSPATH')) {
    exit;
}

add_action('rest_api_init', function () {
    $sevk_permission = (fn() => hizli_kasa_can_access_app());
    register_rest_route('hizli-kasa/v1', '/sevk/olustur', ['methods' => 'POST', 'callback' => 'hizli_kasa_sevk_olustur', 'permission_callback' => $sevk_permission]);

    register_rest_route('hizli-kasa/v1', '/sevk/kalem-ekle', ['methods' => 'POST', 'callback' => 'hizli_kasa_sevk_kalem_ekle', 'permission_callback' => $sevk_permission]);

    register_rest_route('hizli-kasa/v1', '/sevk/kalem-sil', ['methods' => 'POST', 'callback' => 'hizli_kasa_sevk_kalem_sil', 'permission_callback' => $sevk_permission]);

    register_rest_route('hizli-kasa/v1', '/sevk/kalem-miktar-guncelle', ['methods' => 'POST', 'callback' => 'hizli_kasa_sevk_kalem_miktar_guncelle', 'permission_callback' => $sevk_permission]);

    register_rest_route('hizli-kasa/v1', '/sevk/teslim-miktar-guncelle', ['methods' => 'POST', 'callback' => 'hizli_kasa_sevk_teslim_miktar_guncelle', 'permission_callback' => $sevk_permission]);

    register_rest_route('hizli-kasa/v1', '/sevk/gonder-onayla', ['methods' => 'POST', 'callback' => 'hizli_kasa_sevk_gonder_onayla', 'permission_callback' => $sevk_permission]);

    register_rest_route('hizli-kasa/v1', '/sevk/alici-onayla', ['methods' => 'POST', 'callback' => 'hizli_kasa_sevk_alici_onayla', 'permission_callback' => $sevk_permission]);

    register_rest_route('hizli-kasa/v1', '/sevk/alici-reddet', ['methods' => 'POST', 'callback' => 'hizli_kasa_sevk_alici_reddet', 'permission_callback' => $sevk_permission]);

    register_rest_route('hizli-kasa/v1', '/sevk/yola-cikart', ['methods' => 'POST', 'callback' => 'hizli_kasa_sevk_yola_cikart', 'permission_callback' => $sevk_permission]);

    register_rest_route('hizli-kasa/v1', '/sevk/teslim-barkod', ['methods' => 'POST', 'callback' => 'hizli_kasa_sevk_teslim_barkod', 'permission_callback' => $sevk_permission]);

    register_rest_route('hizli-kasa/v1', '/sevk/teslim-onayla', ['methods' => 'POST', 'callback' => 'hizli_kasa_sevk_teslim_onayla', 'permission_callback' => $sevk_permission]);

    register_rest_route('hizli-kasa/v1', '/sevk/liste', ['methods' => 'GET', 'callback' => 'hizli_kasa_sevk_liste', 'permission_callback' => $sevk_permission]);

    register_rest_route('hizli-kasa/v1', '/sevk/detay/(?P<id>\d+)', ['methods' => 'GET', 'callback' => 'hizli_kasa_sevk_detay', 'permission_callback' => $sevk_permission]);

    register_rest_route('hizli-kasa/v1', '/sevk/bekleyen-sayisi', ['methods' => 'GET', 'callback' => 'hizli_kasa_sevk_bekleyen_sayisi', 'permission_callback' => $sevk_permission]);

});

/**
 * Sekme içeriğini yükler ve döner.
 */
function hizli_kasa_sevk_tables() {
    return Hizli_Kasa_Database::get_tables();
}

function hizli_kasa_sevk_status_label($durum) {
    $labels = [
        'taslak' => 'Taslak',
        'onay_bekliyor' => 'Onay Bekliyor',
        'onaylandi' => 'Onaylandı',
        'reddedildi' => 'Reddedildi',
        'gonderildi' => 'Gönderildi',
        'teslim_kontrol' => 'Teslim Kontrol',
        'tamamlandi' => 'Tamamlandı',
        'uyusmazlik' => 'Uyuşmazlık',
    ];
    return $labels[$durum] ?? $durum;
}

function hizli_kasa_sevk_generate_no() {
    global $wpdb;
    $tables = hizli_kasa_sevk_tables();
    $prefix = 'SVK-' . current_time('Ymd') . '-';
    $last = $wpdb->get_var($wpdb->prepare("SELECT sevk_no FROM {$tables['sevkler']} WHERE sevk_no LIKE %s ORDER BY id DESC LIMIT 1", $prefix . '%'));
    $next = ($last && preg_match('/-(\d+)$/', $last, $m)) ? intval($m[1]) + 1 : 1;
    return $prefix . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
}

function hizli_kasa_sevk_refresh_totals($sevk_id) {
    global $wpdb;
    $tables = hizli_kasa_sevk_tables();
    $summary = $wpdb->get_row($wpdb->prepare("SELECT COUNT(*) as cesit, COALESCE(SUM(gonderilen_adet), 0) as adet FROM {$tables['sevk_kalemleri']} WHERE sevk_id = %d", $sevk_id));
    $wpdb->update($tables['sevkler'], ['toplam_cesit' => (int) ($summary->cesit ?? 0), 'toplam_adet' => (float) ($summary->adet ?? 0), 'updated_at' => current_time('mysql')], ['id' => $sevk_id]);
}

function hizli_kasa_sevk_get($sevk_id) {
    global $wpdb;
    $tables = hizli_kasa_sevk_tables();
    return $wpdb->get_row($wpdb->prepare("SELECT s.*, kd.name as kaynak_depo_adi, hd.name as hedef_depo_adi FROM {$tables['sevkler']} s LEFT JOIN {$tables['depolar']} kd ON kd.id = s.kaynak_depo_id LEFT JOIN {$tables['depolar']} hd ON hd.id = s.hedef_depo_id WHERE s.id = %d", $sevk_id));
}

function hizli_kasa_sevk_user_can_source($sevk, $manage = true) {
    return $manage ? hizli_kasa_can_user_manage_depo(get_current_user_id(), (int) $sevk->kaynak_depo_id) : hizli_kasa_can_user_view_depo(get_current_user_id(), (int) $sevk->kaynak_depo_id);
}

function hizli_kasa_sevk_user_can_target($sevk, $manage = true) {
    return $manage ? hizli_kasa_can_user_manage_depo(get_current_user_id(), (int) $sevk->hedef_depo_id) : hizli_kasa_can_user_view_depo(get_current_user_id(), (int) $sevk->hedef_depo_id);
}

function hizli_kasa_sevk_format($sevk, $with_items = false) {
    global $wpdb;
    $tables = hizli_kasa_sevk_tables();
    $row = ['id' => (int) $sevk->id, 'sevk_no' => $sevk->sevk_no, 'kaynak_depo_id' => (int) $sevk->kaynak_depo_id, 'kaynak_depo_adi' => $sevk->kaynak_depo_adi ?: '', 'hedef_depo_id' => (int) $sevk->hedef_depo_id, 'hedef_depo_adi' => $sevk->hedef_depo_adi ?: '', 'durum' => $sevk->durum, 'durum_label' => hizli_kasa_sevk_status_label($sevk->durum), 'toplam_cesit' => (int) $sevk->toplam_cesit, 'toplam_adet' => (float) $sevk->toplam_adet, 'not_gonderici' => $sevk->not_gonderici ?: '', 'not_alici' => $sevk->not_alici ?: '', 'created_at' => $sevk->created_at, 'updated_at' => $sevk->updated_at];
    if ($with_items) {
        $items = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$tables['sevk_kalemleri']} WHERE sevk_id = %d ORDER BY COALESCE(updated_at, created_at) DESC, id DESC", $sevk->id));
        $row['kalemler'] = array_map(function($item) {
            $product = wc_get_product($item->variation_id ?: $item->product_id);
            $image = '';
            if ($product) {
                $image_id = $product->get_image_id();
                if (!$image_id && $product->is_type('variation')) {
                    $parent = wc_get_product($product->get_parent_id());
                    $image_id = $parent ? $parent->get_image_id() : 0;
                }
                $image = $image_id ? wp_get_attachment_image_url($image_id, 'thumbnail') : '';
            }
            return ['id' => (int) $item->id, 'product_id' => (int) $item->product_id, 'variation_id' => (int) $item->variation_id, 'sku' => $item->sku, 'urun_adi' => $item->urun_adi, 'gonderilen_adet' => (float) $item->gonderilen_adet, 'teslim_alinan_adet' => $item->teslim_alinan_adet === null ? null : (float) $item->teslim_alinan_adet, 'image' => $image ?: ''];
        }, $items);
    }
    return $row;
}

function hizli_kasa_sevk_find_product_by_sku($sku) {
    global $wpdb;
    $post_id = $wpdb->get_var($wpdb->prepare("SELECT pm.post_id FROM {$wpdb->postmeta} pm JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE pm.meta_key = '_sku' AND pm.meta_value = %s AND p.post_status IN ('publish', 'private') LIMIT 1", $sku));
    if (!$post_id) {
        return false;
    }
    $product = wc_get_product($post_id);
    if (!$product) {
        return false;
    }
    $variation_id = $product->is_type('variation') ? $product->get_id() : 0;
    return ['product_id' => $variation_id ? $product->get_parent_id() : $product->get_id(), 'variation_id' => $variation_id, 'sku' => $product->get_sku() ?: $sku, 'name' => $product->get_name()];
}

function hizli_kasa_sevk_olustur($request) {
    global $wpdb;
    $data = $request->get_json_params();
    $kaynak = intval($data['kaynak_depo_id'] ?? 0);
    $hedef = intval($data['hedef_depo_id'] ?? 0);
    $user_id = get_current_user_id();
    if (!$kaynak || !$hedef || $kaynak === $hedef) {
        return new WP_Error('invalid_depo', 'Kaynak ve hedef depo seçimi geçersiz.', ['status' => 400]);
    }
    if (!hizli_kasa_can_user_manage_depo($user_id, $kaynak)) {
        return new WP_Error('no_permission', 'Kaynak depoda yönetim yetkiniz yok.', ['status' => 403]);
    }
    if (!hizli_kasa_can_user_view_depo($user_id, $hedef)) {
        return new WP_Error('no_permission', 'Hedef depoyu görüntüleme yetkiniz yok.', ['status' => 403]);
    }
    $tables = hizli_kasa_sevk_tables();
    $wpdb->insert($tables['sevkler'], ['sevk_no' => hizli_kasa_sevk_generate_no(), 'kaynak_depo_id' => $kaynak, 'hedef_depo_id' => $hedef, 'durum' => 'taslak', 'olusturan_user_id' => $user_id, 'created_at' => current_time('mysql'), 'updated_at' => current_time('mysql')]);
    if (!$wpdb->insert_id) {
        return new WP_Error('db_error', 'Sevk oluşturulamadı.', ['status' => 500]);
    }
    return ['success' => true, 'sevk' => hizli_kasa_sevk_format(hizli_kasa_sevk_get($wpdb->insert_id), true)];
}

function hizli_kasa_sevk_kalem_ekle($request) {
    global $wpdb;
    $data = $request->get_json_params();
    $sevk_id = intval($data['sevk_id'] ?? 0);
    $sku = sanitize_text_field($data['sku'] ?? '');
    $qty = max(0.0001, (float) ($data['qty'] ?? 1));
    $sevk = hizli_kasa_sevk_get($sevk_id);
    if (!$sevk || $sevk->durum !== 'taslak') {
        return new WP_Error('invalid_sevk', 'Sadece taslak sevke ürün eklenebilir.', ['status' => 400]);
    }
    if (!hizli_kasa_sevk_user_can_source($sevk, true)) {
        return new WP_Error('no_permission', 'Bu sevki düzenleme yetkiniz yok.', ['status' => 403]);
    }
    $product = hizli_kasa_sevk_find_product_by_sku($sku);
    if (!$product) {
        return new WP_Error('not_found', 'Barkod/SKU ile ürün bulunamadı.', ['status' => 404]);
    }
    $tables = hizli_kasa_sevk_tables();
    $existing = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$tables['sevk_kalemleri']} WHERE sevk_id = %d AND product_id = %d AND variation_id = %d", $sevk_id, $product['product_id'], $product['variation_id']));
    if ($existing) {
        $wpdb->update($tables['sevk_kalemleri'], ['gonderilen_adet' => (float) $existing->gonderilen_adet + $qty, 'updated_at' => current_time('mysql')], ['id' => $existing->id]);
    } else {
        $wpdb->insert($tables['sevk_kalemleri'], ['sevk_id' => $sevk_id, 'product_id' => $product['product_id'], 'variation_id' => $product['variation_id'], 'sku' => $product['sku'], 'urun_adi' => $product['name'], 'gonderilen_adet' => $qty, 'created_at' => current_time('mysql'), 'updated_at' => current_time('mysql')]);
    }
    hizli_kasa_sevk_refresh_totals($sevk_id);
    return ['success' => true, 'sevk' => hizli_kasa_sevk_format(hizli_kasa_sevk_get($sevk_id), true)];
}

function hizli_kasa_sevk_kalem_sil($request) {
    global $wpdb;
    $data = $request->get_json_params();
    $sevk = hizli_kasa_sevk_get(intval($data['sevk_id'] ?? 0));
    if (!$sevk || $sevk->durum !== 'taslak' || !hizli_kasa_sevk_user_can_source($sevk, true)) {
        return new WP_Error('no_permission', 'Kalem silme yetkiniz yok.', ['status' => 403]);
    }
    $tables = hizli_kasa_sevk_tables();
    $wpdb->delete($tables['sevk_kalemleri'], ['id' => intval($data['kalem_id'] ?? 0), 'sevk_id' => $sevk->id]);
    hizli_kasa_sevk_refresh_totals($sevk->id);
    return ['success' => true, 'sevk' => hizli_kasa_sevk_format(hizli_kasa_sevk_get($sevk->id), true)];
}

function hizli_kasa_sevk_kalem_miktar_guncelle($request) {
    global $wpdb;
    $data = $request->get_json_params();
    $sevk_id = intval($data['sevk_id'] ?? 0);
    $kalem_id = intval($data['kalem_id'] ?? 0);
    $qty = max(0.0, (float) ($data['qty'] ?? 0));
    $sevk = hizli_kasa_sevk_get($sevk_id);
    if (!$sevk || $sevk->durum !== 'taslak') {
        return new WP_Error('invalid_sevk', 'Sadece taslak sevke ürün miktarı güncellenebilir.', ['status' => 400]);
    }
    if (!hizli_kasa_sevk_user_can_source($sevk, true)) {
        return new WP_Error('no_permission', 'Bu sevki düzenleme yetkiniz yok.', ['status' => 403]);
    }
    $tables = hizli_kasa_sevk_tables();
    if ($qty <= 0) {
        $wpdb->delete($tables['sevk_kalemleri'], ['id' => $kalem_id, 'sevk_id' => $sevk_id]);
    } else {
        $wpdb->update($tables['sevk_kalemleri'], ['gonderilen_adet' => $qty, 'updated_at' => current_time('mysql')], ['id' => $kalem_id, 'sevk_id' => $sevk_id]);
    }
    hizli_kasa_sevk_refresh_totals($sevk_id);
    return ['success' => true, 'sevk' => hizli_kasa_sevk_format(hizli_kasa_sevk_get($sevk_id), true)];
}

function hizli_kasa_sevk_teslim_miktar_guncelle($request) {
    global $wpdb;
    $data = $request->get_json_params();
    $sevk = hizli_kasa_sevk_get(intval($data['sevk_id'] ?? 0));
    $kalem_id = intval($data['kalem_id'] ?? 0);
    $qty = max(0.0, (float) ($data['qty'] ?? 0));
    if (!$sevk || !in_array($sevk->durum, ['gonderildi', 'teslim_kontrol', 'uyusmazlik'], true) || !hizli_kasa_sevk_user_can_target($sevk, true)) {
        return new WP_Error('invalid_state', 'Teslim miktarı güncellenemez.', ['status' => 400]);
    }
    $tables = hizli_kasa_sevk_tables();
    $wpdb->update($tables['sevk_kalemleri'], ['teslim_alinan_adet' => $qty, 'updated_at' => current_time('mysql')], ['id' => $kalem_id, 'sevk_id' => $sevk->id]);
    $status = hizli_kasa_sevk_has_mismatch($sevk->id) ? 'uyusmazlik' : 'teslim_kontrol';
    $wpdb->update($tables['sevkler'], ['durum' => $status, 'updated_at' => current_time('mysql')], ['id' => $sevk->id]);
    return ['success' => true, 'sevk' => hizli_kasa_sevk_format(hizli_kasa_sevk_get($sevk->id), true)];
}

function hizli_kasa_sevk_gonder_onayla($request) {
    global $wpdb;
    $data = $request->get_json_params();
    $sevk = hizli_kasa_sevk_get(intval($data['sevk_id'] ?? 0));
    if (!$sevk || $sevk->durum !== 'taslak' || !hizli_kasa_sevk_user_can_source($sevk, true)) {
        return new WP_Error('invalid_state', 'Sevk onaya gönderilemez.', ['status' => 400]);
    }
    if ((float) $sevk->toplam_adet <= 0) {
        return new WP_Error('empty_sevk', 'En az bir ürün eklenmelidir.', ['status' => 400]);
    }
    $tables = hizli_kasa_sevk_tables();
    $wpdb->update($tables['sevkler'], ['durum' => 'onay_bekliyor', 'not_gonderici' => sanitize_textarea_field($data['not_gonderici'] ?? ''), 'updated_at' => current_time('mysql')], ['id' => $sevk->id]);
    return ['success' => true, 'sevk' => hizli_kasa_sevk_format(hizli_kasa_sevk_get($sevk->id), true)];
}

function hizli_kasa_sevk_alici_onayla($request) {
    global $wpdb;
    $data = $request->get_json_params();
    $sevk = hizli_kasa_sevk_get(intval($data['sevk_id'] ?? 0));
    if (!$sevk || $sevk->durum !== 'onay_bekliyor' || !hizli_kasa_sevk_user_can_target($sevk, true)) {
        return new WP_Error('invalid_state', 'Sevk onaylanamaz.', ['status' => 400]);
    }
    $tables = hizli_kasa_sevk_tables();
    $wpdb->update($tables['sevkler'], ['durum' => 'onaylandi', 'onaylayan_user_id' => get_current_user_id(), 'not_alici' => sanitize_textarea_field($data['not_alici'] ?? ''), 'updated_at' => current_time('mysql')], ['id' => $sevk->id]);
    return ['success' => true, 'sevk' => hizli_kasa_sevk_format(hizli_kasa_sevk_get($sevk->id), true)];
}

function hizli_kasa_sevk_alici_reddet($request) {
    global $wpdb;
    $data = $request->get_json_params();
    $sevk = hizli_kasa_sevk_get(intval($data['sevk_id'] ?? 0));
    if (!$sevk || $sevk->durum !== 'onay_bekliyor' || !hizli_kasa_sevk_user_can_target($sevk, true)) {
        return new WP_Error('invalid_state', 'Sevk reddedilemez.', ['status' => 400]);
    }
    $tables = hizli_kasa_sevk_tables();
    $wpdb->update($tables['sevkler'], ['durum' => 'reddedildi', 'onaylayan_user_id' => get_current_user_id(), 'not_alici' => sanitize_textarea_field($data['not_alici'] ?? ''), 'updated_at' => current_time('mysql')], ['id' => $sevk->id]);
    return ['success' => true, 'sevk' => hizli_kasa_sevk_format(hizli_kasa_sevk_get($sevk->id), true)];
}

function hizli_kasa_sevk_yola_cikart($request) {
    global $wpdb;
    $data = $request->get_json_params();
    $sevk = hizli_kasa_sevk_get(intval($data['sevk_id'] ?? 0));
    if (!$sevk || $sevk->durum !== 'onaylandi' || !hizli_kasa_sevk_user_can_source($sevk, true)) {
        return new WP_Error('invalid_state', 'Sevk yola çıkarılamaz.', ['status' => 400]);
    }
    $tables = hizli_kasa_sevk_tables();
    $items = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$tables['sevk_kalemleri']} WHERE sevk_id = %d", $sevk->id));
    foreach ($items as $item) {
        Hizli_Kasa_Stock_Manager::transfer_out($item->product_id, $item->variation_id, $sevk->kaynak_depo_id, $item->gonderilen_adet, $sevk->id);
    }
    $wpdb->update($tables['sevkler'], ['durum' => 'gonderildi', 'updated_at' => current_time('mysql')], ['id' => $sevk->id]);
    return ['success' => true, 'sevk' => hizli_kasa_sevk_format(hizli_kasa_sevk_get($sevk->id), true)];
}

function hizli_kasa_sevk_has_mismatch($sevk_id) {
    global $wpdb;
    $tables = hizli_kasa_sevk_tables();
    return (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$tables['sevk_kalemleri']} WHERE sevk_id = %d AND ABS(gonderilen_adet - COALESCE(teslim_alinan_adet, 0)) > 0.0001", $sevk_id)) > 0;
}

function hizli_kasa_sevk_teslim_barkod($request) {
    global $wpdb;
    $data = $request->get_json_params();
    $sevk = hizli_kasa_sevk_get(intval($data['sevk_id'] ?? 0));
    $sku = sanitize_text_field($data['sku'] ?? '');
    if (!$sevk || !in_array($sevk->durum, ['gonderildi', 'teslim_kontrol', 'uyusmazlik'], true) || !hizli_kasa_sevk_user_can_target($sevk, true)) {
        return new WP_Error('invalid_state', 'Teslim barkodu işlenemez.', ['status' => 400]);
    }
    $tables = hizli_kasa_sevk_tables();
    $item = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$tables['sevk_kalemleri']} WHERE sevk_id = %d AND sku = %s", $sevk->id, $sku));
    if (!$item) {
        return new WP_Error('not_in_list', 'Bu barkod sevk listesinde yok.', ['status' => 404]);
    }
    $new_qty = ($item->teslim_alinan_adet === null ? 0 : (float) $item->teslim_alinan_adet) + (float) ($data['qty'] ?? 1);
    $wpdb->update($tables['sevk_kalemleri'], ['teslim_alinan_adet' => $new_qty, 'updated_at' => current_time('mysql')], ['id' => $item->id]);
    $status = hizli_kasa_sevk_has_mismatch($sevk->id) ? 'uyusmazlik' : 'teslim_kontrol';
    $wpdb->update($tables['sevkler'], ['durum' => $status, 'updated_at' => current_time('mysql')], ['id' => $sevk->id]);
    return ['success' => true, 'sevk' => hizli_kasa_sevk_format(hizli_kasa_sevk_get($sevk->id), true)];
}

function hizli_kasa_sevk_teslim_onayla($request) {
    global $wpdb;
    $data = $request->get_json_params();
    $sevk = hizli_kasa_sevk_get(intval($data['sevk_id'] ?? 0));
    $force = !empty($data['force']);
    if (!$sevk || !in_array($sevk->durum, ['teslim_kontrol', 'uyusmazlik', 'gonderildi'], true) || !hizli_kasa_sevk_user_can_target($sevk, true)) {
        return new WP_Error('invalid_state', 'Teslim onaylanamaz.', ['status' => 400]);
    }
    if (hizli_kasa_sevk_has_mismatch($sevk->id) && !$force) {
        return new WP_Error('mismatch', 'Teslim listesinde uyuşmazlık var.', ['status' => 409]);
    }
    $tables = hizli_kasa_sevk_tables();
    $items = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$tables['sevk_kalemleri']} WHERE sevk_id = %d", $sevk->id));
    foreach ($items as $item) {
        $qty = $item->teslim_alinan_adet === null ? (float) $item->gonderilen_adet : (float) $item->teslim_alinan_adet;
        if ($qty > 0) {
            Hizli_Kasa_Stock_Manager::transfer_in($item->product_id, $item->variation_id, $sevk->hedef_depo_id, $qty, $sevk->id);
        }
    }
    $wpdb->update($tables['sevkler'], ['durum' => 'tamamlandi', 'not_alici' => sanitize_textarea_field($data['not_alici'] ?? $sevk->not_alici), 'updated_at' => current_time('mysql')], ['id' => $sevk->id]);
    return ['success' => true, 'sevk' => hizli_kasa_sevk_format(hizli_kasa_sevk_get($sevk->id), true)];
}

function hizli_kasa_sevk_liste($request) {
    global $wpdb;
    $tables = hizli_kasa_sevk_tables();
    $view_ids = current_user_can('manage_options') ? $wpdb->get_col("SELECT id FROM {$tables['depolar']}") : hizli_kasa_get_user_view_depos(get_current_user_id());
    if (empty($view_ids)) {
        return ['items' => [], 'stats' => ['total' => 0, 'yolda' => 0, 'bekleyen' => 0, 'tamamlanan' => 0]];
    }
    $ids_ph = implode(',', array_map('intval', $view_ids));
    $where = "(s.kaynak_depo_id IN ($ids_ph) OR s.hedef_depo_id IN ($ids_ph))";
    $scope = sanitize_text_field($request->get_param('scope') ?: '');
    if ($scope === 'incoming') {
        $where = "s.hedef_depo_id IN ($ids_ph)";
    }
    if ($scope === 'outgoing') {
        $where = "s.kaynak_depo_id IN ($ids_ph)";
    }
    $durum = sanitize_text_field($request->get_param('durum') ?: '');
    if ($durum && $durum !== 'all') {
        $where .= $wpdb->prepare(" AND s.durum = %s", $durum);
    }
    $date_start = sanitize_text_field($request->get_param('date_start') ?: '');
    $date_end = sanitize_text_field($request->get_param('date_end') ?: '');
    if ($date_start) {
        $where .= $wpdb->prepare(" AND DATE(s.created_at) >= %s", $date_start);
    }
    if ($date_end) {
        $where .= $wpdb->prepare(" AND DATE(s.created_at) <= %s", $date_end);
    }
    $rows = $wpdb->get_results("SELECT s.*, kd.name as kaynak_depo_adi, hd.name as hedef_depo_adi FROM {$tables['sevkler']} s LEFT JOIN {$tables['depolar']} kd ON kd.id = s.kaynak_depo_id LEFT JOIN {$tables['depolar']} hd ON hd.id = s.hedef_depo_id WHERE $where ORDER BY s.updated_at DESC LIMIT 100");
    $stats_rows = $wpdb->get_results("SELECT s.durum, COUNT(*) as cnt FROM {$tables['sevkler']} s WHERE (s.kaynak_depo_id IN ($ids_ph) OR s.hedef_depo_id IN ($ids_ph)) GROUP BY s.durum");
    $stats = ['total' => 0, 'yolda' => 0, 'bekleyen' => 0, 'tamamlanan' => 0];
    foreach ($stats_rows as $sr) {
        $stats['total'] += (int) $sr->cnt;
        if (in_array($sr->durum, ['gonderildi', 'teslim_kontrol', 'uyusmazlik'], true)) {
            $stats['yolda'] += (int) $sr->cnt;
        }
        if (in_array($sr->durum, ['onay_bekliyor', 'onaylandi'], true)) {
            $stats['bekleyen'] += (int) $sr->cnt;
        }
        if ($sr->durum === 'tamamlandi') {
            $stats['tamamlanan'] += (int) $sr->cnt;
        }
    }
    return ['items' => array_map(fn($row) => hizli_kasa_sevk_format($row, false), $rows), 'stats' => $stats];
}

function hizli_kasa_sevk_detay($request) {
    $sevk = hizli_kasa_sevk_get(intval($request->get_param('id')));
    if (!$sevk || (!hizli_kasa_sevk_user_can_source($sevk, false) && !hizli_kasa_sevk_user_can_target($sevk, false))) {
        return new WP_Error('not_found', 'Sevk bulunamadı.', ['status' => 404]);
    }
    return ['success' => true, 'sevk' => hizli_kasa_sevk_format($sevk, true)];
}

function hizli_kasa_sevk_bekleyen_sayisi($request) {
    global $wpdb;
    $tables = hizli_kasa_sevk_tables();
    $manage_ids = current_user_can('manage_options') ? $wpdb->get_col("SELECT id FROM {$tables['depolar']}") : hizli_kasa_get_user_manage_depos(get_current_user_id());
    if (empty($manage_ids)) {
        return ['count' => 0];
    }
    $ids_ph = implode(',', array_map('intval', $manage_ids));
    $count = $wpdb->get_var("SELECT COUNT(*) FROM {$tables['sevkler']} WHERE hedef_depo_id IN ($ids_ph) AND durum IN ('onay_bekliyor', 'gonderildi', 'teslim_kontrol', 'uyusmazlik')");
    return ['count' => (int) $count];
}

