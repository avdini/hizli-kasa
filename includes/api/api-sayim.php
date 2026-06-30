<?php
if (!defined('ABSPATH')) {
    exit;
}

add_action('rest_api_init', function () {
    $permission = (fn() => hizli_kasa_can_access_app());

    // Aktif sayım oturumunu getir
    register_rest_route('hizli-kasa/v1', '/sayim/active', [
        'methods' => 'GET',
        'callback' => 'hizli_kasa_sayim_get_active',
        'permission_callback' => $permission
    ]);

    // Yeni sayım oturumu başlat
    register_rest_route('hizli-kasa/v1', '/sayim/start', [
        'methods' => 'POST',
        'callback' => 'hizli_kasa_sayim_start',
        'permission_callback' => $permission
    ]);

    // Barkod okutarak ürün ekle/artır
    register_rest_route('hizli-kasa/v1', '/sayim/scan-item', [
        'methods' => 'POST',
        'callback' => 'hizli_kasa_sayim_scan_item',
        'permission_callback' => $permission
    ]);

    // Sayım kalemi miktarını el ile güncelle
    register_rest_route('hizli-kasa/v1', '/sayim/update-item-qty', [
        'methods' => 'POST',
        'callback' => 'hizli_kasa_sayim_update_item_qty',
        'permission_callback' => $permission
    ]);

    // Sayım kalemini sil
    register_rest_route('hizli-kasa/v1', '/sayim/delete-item', [
        'methods' => 'POST',
        'callback' => 'hizli_kasa_sayim_delete_item',
        'permission_callback' => $permission
    ]);

    // Sayımı iptal et
    register_rest_route('hizli-kasa/v1', '/sayim/discard', [
        'methods' => 'POST',
        'callback' => 'hizli_kasa_sayim_discard',
        'permission_callback' => $permission
    ]);

    // Sayımı tamamla (Stok eşitleme)
    register_rest_route('hizli-kasa/v1', '/sayim/complete', [
        'methods' => 'POST',
        'callback' => 'hizli_kasa_sayim_complete',
        'permission_callback' => $permission
    ]);

    // Sayım geçmişini getir
    register_rest_route('hizli-kasa/v1', '/reports/sayim-history', [
        'methods' => 'GET',
        'callback' => 'hizli_kasa_sayim_history',
        'permission_callback' => $permission
    ]);
});

/**
 * Barkod/SKU/ID ile ürün/varyasyon bulur.
 */
function hizli_kasa_sayim_find_product($barcode) {
    global $wpdb;
    $barcode = trim($barcode);
    if (empty($barcode)) {
        return false;
    }

    // 1. SKU ile bulmayı dene
    $id = $wpdb->get_var($wpdb->prepare("SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_sku' AND meta_value = %s LIMIT 1", $barcode));
    if ($id) {
        $post = get_post($id);
        if ($post && ($post->post_type === 'product' || $post->post_type === 'product_variation')) {
            if ($post->post_type === 'product_variation') {
                return ['product_id' => $post->post_parent, 'variation_id' => (int)$id];
            }
            return ['product_id' => (int)$id, 'variation_id' => 0];
        }
    }

    // 2. ID ile bulmayı dene (Eğer tamamen sayısal ise)
    if (is_numeric($barcode)) {
        $id = intval($barcode);
        $post = get_post($id);
        if ($post && ($post->post_type === 'product' || $post->post_type === 'product_variation')) {
            if ($post->post_type === 'product_variation') {
                return ['product_id' => $post->post_parent, 'variation_id' => $id];
            }
            return ['product_id' => $id, 'variation_id' => 0];
        }
    }

    return false;
}

/**
 * Sayım kalemini JSON formatına hazırlar.
 */
function hizli_kasa_sayim_format_kalem($row) {
    $product_id = (int)$row->product_id;
    $variation_id = (int)$row->variation_id;

    $product = wc_get_product($variation_id ?: $product_id);
    if (!$product) {
        return [
            'id' => (int)$row->id,
            'product_id' => $product_id,
            'variation_id' => $variation_id,
            'name' => 'Silinmiş Ürün',
            'sku' => 'Bilinmiyor',
            'attributes' => '',
            'image' => '',
            'counted_qty' => (float)$row->counted_qty,
            'system_qty' => (float)$row->system_qty,
            'diff' => (float)$row->counted_qty - (float)$row->system_qty,
            'updated_at' => $row->updated_at
        ];
    }

    $name = $product->get_name();
    $sku = $product->get_sku();

    $attr_desc = '';
    if ($product->is_type('variation')) {
        $attributes = $product->get_variation_attributes();
        $attr_parts = [];
        foreach ($attributes as $key => $value) {
            $taxonomy = str_replace('attribute_', '', $key);
            $label = wc_attribute_label($taxonomy, $product);
            $term = get_term_by('slug', $value, $taxonomy);
            $display_value = $term ? $term->name : $value;
            $attr_parts[] = $label . ': ' . $display_value;
        }
        $attr_desc = implode(', ', $attr_parts);
    }

    $image_id = $product->get_image_id();
    $image_url = $image_id ? wp_get_attachment_image_url($image_id, 'thumbnail') : '';
    if (!$image_url && $product->is_type('variation')) {
        $parent = wc_get_product($product_id);
        if ($parent) {
            $parent_image_id = $parent->get_image_id();
            $image_url = $parent_image_id ? wp_get_attachment_image_url($parent_image_id, 'thumbnail') : '';
        }
    }

    return [
        'id' => (int)$row->id,
        'product_id' => $product_id,
        'variation_id' => $variation_id,
        'name' => $name,
        'sku' => $sku ?: (string)($variation_id ?: $product_id),
        'attributes' => $attr_desc,
        'image' => $image_url,
        'counted_qty' => (float)$row->counted_qty,
        'system_qty' => (float)$row->system_qty,
        'diff' => (float)$row->counted_qty - (float)$row->system_qty,
        'updated_at' => $row->updated_at
    ];
}

/**
 * Aktif sayım oturumunu ve kalemlerini çeker.
 */
function hizli_kasa_sayim_get_active($request) {
    global $wpdb;
    $depo_id = intval($request->get_param('depo_id'));
    if ($depo_id === 0) {
        return new WP_Error('missing_depo', 'Depo ID gerekli.', ['status' => 400]);
    }

    $tables = Hizli_Kasa_Database::get_tables();
    
    // Aktif oturumu bul
    $session = $wpdb->get_row($wpdb->prepare("
        SELECT * FROM {$tables['sayim_sessions']} 
        WHERE location_id = %d AND status IN ('aktif', 'processing')
        LIMIT 1
    ", $depo_id));

    $is_other_warehouse_processing = false;
    $other_warehouse_name = '';

    if (!$session) {
        $session = $wpdb->get_row("
            SELECT * FROM {$tables['sayim_sessions']} 
            WHERE status = 'processing'
            LIMIT 1
        ");
        if ($session) {
            $is_other_warehouse_processing = true;
            $other_warehouse_name = $wpdb->get_var($wpdb->prepare("
                SELECT name FROM {$tables['depolar']} WHERE id = %d
            ", $session->location_id));
        }
    }

    if (!$session) {
        return ['active' => false];
    }

    // Oturumun kalemlerini en son güncellenen en üstte olacak şekilde getir
    $items = [];
    if (!$is_other_warehouse_processing) {
        $kalemler = $wpdb->get_results($wpdb->prepare("
            SELECT * FROM {$tables['sayim_kalemleri']}
            WHERE session_id = %d
            ORDER BY updated_at DESC
        ", $session->id));

        foreach ($kalemler as $row) {
            $items[] = hizli_kasa_sayim_format_kalem($row);
        }
    }

    $creator = get_userdata($session->created_by);

    $progress = null;
    if ($session->status === 'processing') {
        $processed = (int)$session->total_items;
        $remaining = (int)$wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$tables['sayim_kalemleri']} WHERE session_id = %d
        ", $session->id));
        $total = $processed + $remaining;
        $pct = $total > 0 ? round(($processed / $total) * 100) : 0;
        $progress = [
            'processed' => $processed,
            'remaining' => $remaining,
            'total' => $total,
            'percentage' => $pct
        ];
    }

    return [
        'active' => true,
        'is_other_warehouse_processing' => $is_other_warehouse_processing,
        'other_warehouse_name' => $other_warehouse_name ?: '',
        'session' => [
            'id' => (int)$session->id,
            'location_id' => (int)$session->location_id,
            'status' => $session->status,
            'created_by' => $creator ? $creator->display_name : 'Bilinmeyen',
            'created_at' => $session->created_at
        ],
        'items' => $items,
        'progress' => $progress
    ];
}

/**
 * Yeni sayım oturumu başlatır.
 */
function hizli_kasa_sayim_start($request) {
    global $wpdb;
    $params = $request->get_json_params();
    $depo_id = intval($params['depo_id'] ?? 0);

    if ($depo_id === 0) {
        return new WP_Error('missing_depo', 'Depo ID gerekli.', ['status' => 400]);
    }

    $tables = Hizli_Kasa_Database::get_tables();

    // Zaten aktif veya işlenen bir oturum var mı kontrol et
    $active_session = $wpdb->get_row($wpdb->prepare("
        SELECT id, status FROM {$tables['sayim_sessions']}
        WHERE location_id = %d AND status = 'aktif'
        LIMIT 1
    ", $depo_id));

    if ($active_session) {
        return new WP_Error('session_exists', 'Bu depo için halihazırda aktif bir sayım oturumu bulunuyor.', ['status' => 400]);
    }

    $processing_session = $wpdb->get_row("
        SELECT id, location_id FROM {$tables['sayim_sessions']}
        WHERE status = 'processing'
        LIMIT 1
    ");

    if ($processing_session) {
        $depo_name = $wpdb->get_var($wpdb->prepare("
            SELECT name FROM {$tables['depolar']} WHERE id = %d
        ", $processing_session->location_id));
        $depo_label = $depo_name ? "'{$depo_name}'" : "Bir başka";
        return new WP_Error('processing_exists', "{$depo_label} deposu için arka planda stok eşitleme işlemi (cron) devam ediyor. Lütfen bu işlem bitene kadar yeni bir sayım başlatmayın.", ['status' => 400]);
    }

    $user_id = get_current_user_id();
    $now = current_time('mysql');

    $inserted = $wpdb->insert($tables['sayim_sessions'], [
        'location_id' => $depo_id,
        'status' => 'aktif',
        'created_by' => $user_id,
        'created_at' => $now
    ]);

    if (!$inserted) {
        return new WP_Error('db_error', 'Sayım oturumu başlatılamadı.', ['status' => 500]);
    }

    $session_id = $wpdb->insert_id;

    return [
        'success' => true,
        'message' => 'Sayım oturumu başarıyla başlatıldı.',
        'session' => [
            'id' => $session_id,
            'location_id' => $depo_id,
            'status' => 'aktif',
            'created_at' => $now
        ]
    ];
}

/**
 * Barkod okutulduğunda sayım kalemini ekler veya artırır.
 */
function hizli_kasa_sayim_scan_item($request) {
    global $wpdb;
    $params = $request->get_json_params();
    $session_id = intval($params['session_id'] ?? 0);
    $barcode = sanitize_text_field($params['barcode'] ?? '');

    if (!$session_id || empty($barcode)) {
        return new WP_Error('invalid_params', 'Geçersiz parametreler.', ['status' => 400]);
    }

    $tables = Hizli_Kasa_Database::get_tables();

    // Oturumu ve deposunu doğrula
    $session = $wpdb->get_row($wpdb->prepare("
        SELECT * FROM {$tables['sayim_sessions']} WHERE id = %d AND status = 'aktif'
    ", $session_id));

    if (!$session) {
        return new WP_Error('session_not_found', 'Aktif sayım oturumu bulunamadı.', ['status' => 404]);
    }

    // Barkodla ürünü bul
    $prod_info = hizli_kasa_sayim_find_product($barcode);
    if (!$prod_info) {
        return new WP_Error('product_not_found', 'Bu barkod ile eşleşen ürün bulunamadı.', ['status' => 404]);
    }

    $product_id = $prod_info['product_id'];
    $variation_id = $prod_info['variation_id'];
    $now = current_time('mysql');

    // Bu seansa ait daha önce eklenmiş mi kontrol et
    $existing = $wpdb->get_row($wpdb->prepare("
        SELECT * FROM {$tables['sayim_kalemleri']}
        WHERE session_id = %d AND product_id = %d AND variation_id = %d
    ", $session_id, $product_id, $variation_id));

    if ($existing) {
        // Miktarı 1 artır
        $new_qty = (float)$existing->counted_qty + 1;
        $wpdb->update($tables['sayim_kalemleri'], [
            'counted_qty' => $new_qty,
            'updated_at' => $now
        ], ['id' => $existing->id]);

        $row_id = $existing->id;
    } else {
        // Mevcut depo stoğunu sistem stoğu olarak snapshot al
        $system_qty = $wpdb->get_var($wpdb->prepare("
            SELECT quantity FROM {$tables['stok_konumlari']}
            WHERE product_id = %d AND variation_id = %d AND location_id = %d
            LIMIT 1
        ", $product_id, $variation_id, $session->location_id));

        $system_qty = $system_qty !== null ? (float)$system_qty : 0.0;

        $wpdb->insert($tables['sayim_kalemleri'], [
            'session_id' => $session_id,
            'product_id' => $product_id,
            'variation_id' => $variation_id,
            'counted_qty' => 1.0,
            'system_qty' => $system_qty,
            'updated_at' => $now
        ]);

        $row_id = $wpdb->insert_id;
    }

    // Güncellenmiş kalemi çek ve formatla
    $updated_row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$tables['sayim_kalemleri']} WHERE id = %d", $row_id));
    $formatted_item = hizli_kasa_sayim_format_kalem($updated_row);

    return [
        'success' => true,
        'message' => 'Ürün başarıyla eklendi.',
        'item' => $formatted_item
    ];
}

/**
 * Sayım kalemi miktarını manuel günceller.
 */
function hizli_kasa_sayim_update_item_qty($request) {
    global $wpdb;
    $params = $request->get_json_params();
    $session_id = intval($params['session_id'] ?? 0);
    $product_id = intval($params['product_id'] ?? 0);
    $variation_id = intval($params['variation_id'] ?? 0);
    $qty = floatval($params['qty'] ?? 0);

    if (!$session_id || !$product_id) {
        return new WP_Error('invalid_params', 'Geçersiz parametreler.', ['status' => 400]);
    }

    $tables = Hizli_Kasa_Database::get_tables();

    $updated = $wpdb->update($tables['sayim_kalemleri'], [
        'counted_qty' => $qty,
        'updated_at' => current_time('mysql')
    ], [
        'session_id' => $session_id,
        'product_id' => $product_id,
        'variation_id' => $variation_id
    ]);

    if ($updated === false) {
        return new WP_Error('db_error', 'Miktar güncellenemedi.', ['status' => 500]);
    }

    $updated_row = $wpdb->get_row($wpdb->prepare("
        SELECT * FROM {$tables['sayim_kalemleri']}
        WHERE session_id = %d AND product_id = %d AND variation_id = %d
        LIMIT 1
    ", $session_id, $product_id, $variation_id));

    $formatted = hizli_kasa_sayim_format_kalem($updated_row);

    return [
        'success' => true,
        'message' => 'Miktar güncellendi.',
        'item' => $formatted
    ];
}

/**
 * Sayım kalemini siler.
 */
function hizli_kasa_sayim_delete_item($request) {
    global $wpdb;
    $params = $request->get_json_params();
    $session_id = intval($params['session_id'] ?? 0);
    $product_id = intval($params['product_id'] ?? 0);
    $variation_id = intval($params['variation_id'] ?? 0);

    if (!$session_id || !$product_id) {
        return new WP_Error('invalid_params', 'Geçersiz parametreler.', ['status' => 400]);
    }

    $tables = Hizli_Kasa_Database::get_tables();

    $deleted = $wpdb->delete($tables['sayim_kalemleri'], [
        'session_id' => $session_id,
        'product_id' => $product_id,
        'variation_id' => $variation_id
    ]);

    if (!$deleted) {
        return new WP_Error('db_error', 'Kalem silinemedi.', ['status' => 500]);
    }

    return [
        'success' => true,
        'message' => 'Kalem sayımdan kaldırıldı.'
    ];
}

/**
 * Sayım oturumunu iptal eder (discard).
 */
function hizli_kasa_sayim_discard($request) {
    global $wpdb;
    $params = $request->get_json_params();
    $session_id = intval($params['session_id'] ?? 0);

    if ($session_id === 0) {
        return new WP_Error('invalid_params', 'Geçersiz parametreler.', ['status' => 400]);
    }

    $tables = Hizli_Kasa_Database::get_tables();

    $session = $wpdb->get_row($wpdb->prepare("
        SELECT * FROM {$tables['sayim_sessions']} WHERE id = %d AND status = 'aktif'
    ", $session_id));

    if (!$session) {
        return new WP_Error('session_not_found', 'Aktif sayım oturumu bulunamadı.', ['status' => 404]);
    }

    // Kalemleri topla ve JSON formatında sıkıştır
    $kalemler = $wpdb->get_results($wpdb->prepare("
        SELECT * FROM {$tables['sayim_kalemleri']} WHERE session_id = %d
    ", $session_id));

    $report_items = [];
    $total_items = 0;
    $total_diff = 0.0;

    foreach ($kalemler as $row) {
        $formatted = hizli_kasa_sayim_format_kalem($row);
        $report_items[] = [
            'product_id' => $formatted['product_id'],
            'variation_id' => $formatted['variation_id'],
            'name' => $formatted['name'],
            'sku' => $formatted['sku'],
            'attributes' => $formatted['attributes'],
            'system_qty' => $formatted['system_qty'],
            'counted_qty' => $formatted['counted_qty'],
            'diff' => $formatted['diff']
        ];
        $total_items++;
        $total_diff += $formatted['diff'];
    }

    // Oturumu iptal olarak güncelle
    $wpdb->update($tables['sayim_sessions'], [
        'status' => 'iptal',
        'total_items' => $total_items,
        'total_diff' => $total_diff,
        'report_data' => wp_json_encode($report_items),
        'completed_at' => current_time('mysql')
    ], ['id' => $session_id]);

    // Kalem detaylarını temizle
    $wpdb->delete($tables['sayim_kalemleri'], ['session_id' => $session_id]);

    return [
        'success' => true,
        'message' => 'Sayım oturumu iptal edildi ve arşivlendi.'
    ];
}

/**
 * Sayımı tamamlar ve asenkron WooCommerce depolarını eşitlemeyi başlatır.
 */
function hizli_kasa_sayim_complete($request) {
    global $wpdb;
    $params = $request->get_json_params();
    $session_id = intval($params['session_id'] ?? 0);
    $update_type = sanitize_text_field($params['update_type'] ?? 'partial'); // 'partial' veya 'full'

    if (!$session_id || !in_array($update_type, ['partial', 'full'])) {
        return new WP_Error('invalid_params', 'Geçersiz parametreler.', ['status' => 400]);
    }

    $tables = Hizli_Kasa_Database::get_tables();

    $processing_session = $wpdb->get_row("
        SELECT id, location_id FROM {$tables['sayim_sessions']}
        WHERE status = 'processing'
        LIMIT 1
    ");

    if ($processing_session) {
        $depo_name = $wpdb->get_var($wpdb->prepare("
            SELECT name FROM {$tables['depolar']} WHERE id = %d
        ", $processing_session->location_id));
        $depo_label = $depo_name ? "'{$depo_name}'" : "Bir başka";
        return new WP_Error('processing_exists', "{$depo_label} deposu için arka planda stok eşitleme işlemi (cron) devam ediyor. Lütfen bu işlem bitene kadar sayımı sonlandırmayın.", ['status' => 400]);
    }

    $session = $wpdb->get_row($wpdb->prepare("
        SELECT * FROM {$tables['sayim_sessions']} WHERE id = %d AND status = 'aktif'
    ", $session_id));

    if (!$session) {
        return new WP_Error('session_not_found', 'Aktif sayım oturumu bulunamadı.', ['status' => 404]);
    }

    $location_id = $session->location_id;
    $now = current_time('mysql');

    // Durumu 'processing' yap ve rapor alanını sıfırla
    $wpdb->update($tables['sayim_sessions'], [
        'status' => 'processing',
        'update_type' => $update_type,
        'report_data' => wp_json_encode([]),
        'total_items' => 0,
        'total_diff' => 0.0
    ], ['id' => $session_id]);

    // Eğer güncelleme tipi 'full' (Tam Eşitleme) ise:
    // Bu depoda kaydı olan ama sayılmayan TÜM diğer ürünleri 0 olarak sayım kalemlerine ekle.
    if ($update_type === 'full') {
        $wpdb->query($wpdb->prepare("
            INSERT INTO {$tables['sayim_kalemleri']} (session_id, product_id, variation_id, counted_qty, system_qty, updated_at)
            SELECT %d, sk.product_id, sk.variation_id, 0.0, sk.quantity, %s
            FROM {$tables['stok_konumlari']} sk
            WHERE sk.location_id = %d AND sk.quantity > 0
              AND NOT EXISTS (
                  SELECT 1 FROM {$tables['sayim_kalemleri']} k
                  WHERE k.session_id = %d AND k.product_id = sk.product_id AND k.variation_id = sk.variation_id
              )
        ", $session_id, $now, $location_id, $session_id));
    }

    // Tek seferlik WP Cron olayını planla
    wp_schedule_single_event(time(), 'hizli_kasa_sayim_background_sync', [$session_id]);

    // Cron'u tetikle (asenkron istek atarak hemen başlat)
    wp_remote_post(
        site_url('wp-cron.php'),
        [
            'timeout'   => 0.01,
            'blocking'  => false,
            'sslverify' => false,
        ]
    );

    return [
        'success' => true,
        'message' => 'Sayım başarıyla kuyruğa alındı. Sunucu işlemleri devraldı, tarayıcıyı kapatabilirsiniz.',
        'session_id' => $session_id,
        'status' => 'processing'
    ];
}

// Cron Kancasını Kaydet
add_action('hizli_kasa_sayim_background_sync', 'hizli_kasa_sayim_background_sync_callback');

/**
 * Arka planda sayım kalemlerini 100'erli paketler halinde işler.
 */
function hizli_kasa_sayim_background_sync_callback($session_id) {
    global $wpdb;
    $tables = Hizli_Kasa_Database::get_tables();

    $session = $wpdb->get_row($wpdb->prepare("
        SELECT * FROM {$tables['sayim_sessions']} WHERE id = %d AND status = 'processing'
    ", $session_id));

    if (!$session) {
        return;
    }

    $location_id = $session->location_id;
    $update_type = $session->update_type;

    // Sıradaki 100 kalemi çek
    $kalemler = $wpdb->get_results($wpdb->prepare("
        SELECT * FROM {$tables['sayim_kalemleri']} WHERE session_id = %d LIMIT 100
    ", $session_id));

    if (empty($kalemler)) {
        // İşlenecek kalem kalmadı -> Oturumu tamamla
        $wpdb->update($tables['sayim_sessions'], [
            'status' => 'tamamlandi',
            'completed_at' => current_time('mysql')
        ], ['id' => $session_id]);

        if (class_exists('Hizli_Kasa_Mismatch_Notifier')) {
            Hizli_Kasa_Mismatch_Notifier::reset_status();
        }
        return;
    }

    $processed_ids = [];
    $batch_report_items = [];
    $batch_total_items = 0;
    $batch_total_diff = 0.0;

    require_once HIZLI_KASA_PATH . 'includes/classes/class-stock-manager.php';

    foreach ($kalemler as $row) {
        $product_id = (int)$row->product_id;
        $variation_id = (int)$row->variation_id;
        $counted_qty = (float)$row->counted_qty;

        // Depo stoğunu güncelle
        Hizli_Kasa_Stock_Manager::update_warehouse_stock_set(
            $product_id,
            $variation_id,
            $location_id,
            $counted_qty,
            sprintf('Fiziksel Sayım (%s - Seans #%d)', $update_type === 'full' ? 'Tam' : 'Kısmi', $session_id)
        );

        $processed_ids[] = (int)$row->id;

        $formatted = hizli_kasa_sayim_format_kalem($row);
        $batch_report_items[] = [
            'product_id' => $product_id,
            'variation_id' => $variation_id,
            'name' => $formatted['name'],
            'sku' => $formatted['sku'],
            'attributes' => $formatted['attributes'],
            'system_qty' => $formatted['system_qty'],
            'counted_qty' => $counted_qty,
            'diff' => $counted_qty - $formatted['system_qty']
        ];

        $batch_total_items++;
        $batch_total_diff += ($counted_qty - $formatted['system_qty']);
    }

    // Mevcut rapor verilerini yükle
    $existing_report = [];
    if (!empty($session->report_data)) {
        $existing_report = json_decode($session->report_data, true);
        if (!is_array($existing_report)) {
            $existing_report = [];
        }
    }

    $updated_report = array_merge($existing_report, $batch_report_items);
    $new_total_items = (int)$session->total_items + $batch_total_items;
    $new_total_diff = (float)$session->total_diff + $batch_total_diff;

    // Oturum özetini güncelle
    $wpdb->update($tables['sayim_sessions'], [
        'total_items' => $new_total_items,
        'total_diff' => $new_total_diff,
        'report_data' => wp_json_encode($updated_report)
    ], ['id' => $session_id]);

    // İşlenen kalemleri detay tablosundan sil
    $id_list = implode(',', $processed_ids);
    $wpdb->query("DELETE FROM {$tables['sayim_kalemleri']} WHERE id IN ($id_list)");

    // Sonraki 100 kalem için kendisini 1 saniye sonra tekrar planla
    wp_schedule_single_event(time() + 1, 'hizli_kasa_sayim_background_sync', [$session_id]);

    // Cron'u tetikle
    wp_remote_post(
        site_url('wp-cron.php'),
        [
            'timeout'   => 0.01,
            'blocking'  => false,
            'sslverify' => false,
        ]
    );
}

/**
 * Sayım geçmişini raporlar sekmesi için getirir.
 */
function hizli_kasa_sayim_history($request) {
    global $wpdb;
    $depo_id = intval($request->get_param('depo_id'));
    $date_start = sanitize_text_field($request->get_param('date_start'));
    $date_end = sanitize_text_field($request->get_param('date_end'));

    $tables = Hizli_Kasa_Database::get_tables();

    $where = "status IN ('tamamlandi', 'iptal')";
    $params = [];

    if ($depo_id > 0) {
        $where .= " AND location_id = %d";
        $params[] = $depo_id;
    }

    if (!empty($date_start)) {
        $where .= " AND DATE(created_at) >= %s";
        $params[] = $date_start;
    }

    if (!empty($date_end)) {
        $where .= " AND DATE(created_at) <= %s";
        $params[] = $date_end;
    }

    $query = "SELECT * FROM {$tables['sayim_sessions']} WHERE $where ORDER BY created_at DESC";

    $results = $params === [] ? $wpdb->get_results($query) : $wpdb->get_results($wpdb->prepare($query, ...$params));

    $history = [];
    foreach ($results as $row) {
        $user_info = get_userdata($row->created_by);
        
        // Depo adını al
        $depo_name = $wpdb->get_var($wpdb->prepare("
            SELECT name FROM {$tables['depolar']} WHERE id = %d
        ", $row->location_id));

        $history[] = [
            'id' => (int)$row->id,
            'location_id' => (int)$row->location_id,
            'depo_name' => $depo_name ?: 'Bilinmeyen Depo',
            'status' => $row->status,
            'update_type' => $row->update_type ?: '-',
            'total_items' => (int)$row->total_items,
            'total_diff' => (float)$row->total_diff,
            'created_by' => $user_info ? $user_info->display_name : 'Bilinmeyen',
            'created_at' => date('d.m.Y H:i', strtotime($row->created_at)),
            'completed_at' => $row->completed_at ? date('d.m.Y H:i', strtotime($row->completed_at)) : '-',
            'report_data' => json_decode($row->report_data)
        ];
    }

    return $history;
}
