<?php
/**
 * Hızlı Kasa - Stok Yönetim Motoru
 *
 * Çoklu depo stok mantığı, senkronizasyon ve loglama.
 *
 * @package HizliKasa
 */

if (!defined('ABSPATH'))
    exit;

class Hizli_Kasa_Stock_Manager {

    /**
     * Hookları Başlat
     */
    /**
     * Hookları Başlat
     */
    public static function listen() {
        add_action('woocommerce_order_status_processing', [self::class, 'handle_online_order_stock'], 10, 2);
        // woocommerce_checkout_order_processed yerine woocommerce_new_order daha geneldir (REST API'yi de kapsar)
        add_action('woocommerce_new_order', [self::class, 'handle_pos_order_stock'], 10, 2);
        // Sipariş iptal edildiğinde stok iadesi tetikleyici
        add_action('woocommerce_order_status_cancelled', [self::class, 'handle_cancelled_order_stock'], 10, 2);
    }

    /**
     * POS üzerinden gelen siparişlerde personelin aktif deposundan düşüm yapar.
     * Her sipariş kalemine çıkış deposu bilgisini yazar (iade takibi için).
     */
    public static function handle_pos_order_stock($order_id, $order = false) {
        if (!$order) {
            $order = wc_get_order($order_id);
        }

        if (!$order) return;

        $kasiyer_name = $order->get_meta('_hizli_kasa_kasiyer');
        
        hizli_kasa_log("handle_pos_order_stock tetiklendi. Sipariş ID: $order_id, Kasiyer: " . ($kasiyer_name ?: 'Yok'));

        if (!$kasiyer_name) return; // POS siparişi değilse çık

        // Kasiyerin kullanıcı ID'sini bul (veya şu anki kullanıcıyı kullan)
        $user_id = get_current_user_id();
        
        // REST API çağrılarında bazen user_id 0 gelebilir, bu durumda meta'dan bulmaya çalışabiliriz
        if (!$user_id) {
             // Opsiyonel: Kasiyer isminden user bulma mantığı eklenebilir
             hizli_kasa_log("Uyarı: current_user_id 0 döndü. REST API auth kontrol edilmeli.");
        }

        // Yeni çoklu depo sisteminden aktif depoyu al
        $depo_id = get_user_meta($user_id, '_hizli_kasa_active_depo', true);

        // Fallback: Eski sisteme bak
        if (!$depo_id) {
            $depo_id = get_user_meta($user_id, '_hizli_kasa_depo_id', true);
        }

        hizli_kasa_log("Kasiyer User ID: $user_id, Tespit Edilen Depo ID: " . ($depo_id ?: 'Yok'));

        if (!$depo_id) {
            hizli_kasa_log("HATA: Depo ID bulunamadığı için stok düşülemedi.");
            return;
        }

        global $wpdb;
        $tables = Hizli_Kasa_Database::get_tables();

        $depo_name = $wpdb->get_var($wpdb->prepare(
            "SELECT name FROM {$tables['depolar']} WHERE id = %d", $depo_id
        ));

        foreach ($order->get_items() as $item_id => $item) {
            $product_id = $item->get_product_id();
            $variation_id = $item->get_variation_id();
            $qty = $item->get_quantity();

            hizli_kasa_log("Stok Düşülüyor: Prod: $product_id, Var: $variation_id, Adet: $qty, Depo: $depo_id");

            // Gölge katmandan (depo) düş
            self::update_warehouse_stock($product_id, $variation_id, $depo_id, -$qty, "POS Satışı (#$order_id)");

            // Sipariş kalemine çıkış deposu bilgisini yaz
            wc_update_order_item_meta($item_id, '_hk_cikis_depo_id', $depo_id);
            wc_update_order_item_meta($item_id, '_hk_cikis_depo_adet', $qty);
            wc_update_order_item_meta($item_id, '_hk_cikis_depo_adi', $depo_name ?: 'Bilinmeyen');
        }

        $order->update_meta_data('_hk_cikis_depo_id', $depo_id);
        $order->update_meta_data('_hk_cikis_depo_adi', $depo_name ?: 'Bilinmeyen');
        $order->save();
        
        hizli_kasa_log("Sipariş #$order_id için depo stok düşümü tamamlandı.");
    }

    /**
     * Online siparişlerde (web) öncelikli stok düşümü yapar.
     */
    public static function handle_online_order_stock($order_id, $order) {
        // Eğer sipariş "Hızlı Kasa" üzerinden gelmişse bu fonksiyonu atla 
        // (Çünkü POS kendi deposundan zaten düşüm yapacak)
        if ($order->get_meta('_hizli_kasa_kasiyer')) return;

        foreach ($order->get_items() as $item) {
            $product_id = $item->get_product_id();
            $variation_id = $item->get_variation_id();
            $qty = $item->get_quantity();

            // Item objesini de gönderiyoruz ki hangi depodan ne kadar düşüldüğünü kaydedebilelim
            self::priority_stock_deduction($product_id, $variation_id, $qty, $item);
        }
    }

    /**
     * İlk Kurulum: WC ana stoklarını seçilen depoya kopyalar.
     */
    public static function initial_sync($depo_id) {
        global $wpdb;
        $tables = Hizli_Kasa_Database::get_tables();

        // Mevcut shadow stock verilerini temizle (temiz bir başlangıç için)
        $wpdb->query("TRUNCATE TABLE {$tables['stok_konumlari']}");

        // Tüm ürünleri çek (varyantlar dahil)
        $products = $wpdb->get_results("
            SELECT p.ID, p.post_parent, p.post_type,
                   MAX(CASE WHEN pm.meta_key = '_stock' THEN pm.meta_value END) as stock
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_status = 'publish' 
              AND p.post_type IN ('product', 'product_variation')
              AND pm.meta_key = '_stock'
            GROUP BY p.ID
        ");

        foreach ($products as $p) {
            $stock_qty = floatval($p->stock);
            if ($stock_qty == 0) continue;

            $product_id = ($p->post_type === 'product_variation') ? $p->post_parent : $p->ID;
            $variation_id = ($p->post_type === 'product_variation') ? $p->ID : 0;

            $wpdb->insert($tables['stok_konumlari'], [
                'product_id'   => $product_id,
                'variation_id' => $variation_id,
                'location_id'  => $depo_id,
                'quantity'     => $stock_qty
            ]);

            self::log_movement($product_id, $variation_id, $depo_id, 0, $stock_qty, "İlk Kurulum Senkronizasyonu");
        }

        return true;
    }

    /**
     * Belirli bir depodan stok düşer.
     */
    public static function update_warehouse_stock($product_id, $variation_id, $location_id, $change_amount, $reason = "") {
        global $wpdb;
        $tables = Hizli_Kasa_Database::get_tables();

        hizli_kasa_log("update_warehouse_stock çağrıldı: P:$product_id, V:$variation_id, L:$location_id, Change:$change_amount");

        $current = $wpdb->get_row($wpdb->prepare("
            SELECT id, quantity FROM {$tables['stok_konumlari']} 
            WHERE product_id = %d AND variation_id = %d AND location_id = %d
        ", $product_id, $variation_id, $location_id));

        $old_qty = $current ? floatval($current->quantity) : 0;
        $new_qty = $old_qty + $change_amount;

        if ($current) {
            $result = $wpdb->update($tables['stok_konumlari'], ['quantity' => $new_qty], ['id' => $current->id]);
            hizli_kasa_log("DB Update (ID:{$current->id}): " . ($result !== false ? "BAŞARILI" : "HATA: " . $wpdb->last_error));
        } else {
            $result = $wpdb->insert($tables['stok_konumlari'], [
                'product_id'   => $product_id,
                'variation_id' => $variation_id,
                'location_id'  => $location_id,
                'quantity'     => $new_qty
            ]);
            hizli_kasa_log("DB Insert: " . ($result !== false ? "BAŞARILI" : "HATA: " . $wpdb->last_error));
        }

        self::log_movement($product_id, $variation_id, $location_id, $old_qty, $new_qty, $reason);

        // Uyuşmazlık önbelleğini sıfırla
        if (class_exists('Hizli_Kasa_Mismatch_Notifier')) {
            Hizli_Kasa_Mismatch_Notifier::reset_status();
        }

        return $new_qty;
    }

    /**
     * Online satışlar için öncelikli stok düşümü.
     */
    public static function priority_stock_deduction($product_id, $variation_id, $total_to_deduct, $item = null) {
        global $wpdb;
        $tables = Hizli_Kasa_Database::get_tables();
        
        $online_depo_id = get_option('hizli_kasa_varsayilan_online_depo');
        
        // Depoları önceliğe göre getir
        $depolar = $wpdb->get_results("SELECT id FROM {$tables['depolar']} ORDER BY 
            (CASE WHEN id = " . intval($online_depo_id) . " THEN 1 ELSE 0 END) DESC, 
            priority DESC");

        $remaining = $total_to_deduct;
        $deductions = [];

        foreach ($depolar as $d) {
            if ($remaining <= 0) break;

            $stock = $wpdb->get_var($wpdb->prepare("
                SELECT quantity FROM {$tables['stok_konumlari']} 
                WHERE product_id = %d AND variation_id = %d AND location_id = %d
            ", $product_id, $variation_id, $d->id));

            if (!$stock || $stock <= 0) continue;

            $to_take = min($stock, $remaining);
            self::update_warehouse_stock($product_id, $variation_id, $d->id, -$to_take, "Online Satış (Otomatik)");
            
            $deductions[] = ['depo_id' => $d->id, 'qty' => $to_take];
            $remaining -= $to_take;
        }

        // Eğer hala düşülecek stok kaldıysa (tüm depolar tükendiyse), öncelikli depodan eksiye düşür
        if ($remaining > 0 && $online_depo_id) {
            self::update_warehouse_stock($product_id, $variation_id, $online_depo_id, -$remaining, "Online Satış (Stok Yetersiz - Eksiye Düştü)");
            
            // Eğer bu depo zaten listeye eklenmişse miktarını artır, yoksa yeni ekle
            $found = false;
            if (!empty($deductions)) {
                foreach ($deductions as &$ded) {
                    if ($ded['depo_id'] == $online_depo_id) {
                        $ded['qty'] += $remaining;
                        $found = true;
                        break;
                    }
                }
            }
            if (!$found) {
                $deductions[] = ['depo_id' => $online_depo_id, 'qty' => $remaining];
            }
        }

        // Kesintileri sipariş kalemine kaydet (İptal durumunda geri iade için)
        if ($item && !empty($deductions)) {
            wc_update_order_item_meta($item->get_id(), '_hk_deductions', $deductions);
        }
    }

    /**
     * Tüm depoları toplayıp WC ana stoğunu günceller.
     */
    public static function sync_to_wc_stock($product_id, $variation_id) {
        // Bu fonksiyon devre dışı bırakılmıştır. 
        // Depo stoklarının WooCommerce ana stoğuyla senkronize edilmesini engeller.
        return;
    }

    /**
     * Hareket kaydı tutar.
     */
    public static function log_movement($product_id, $variation_id, $location_id, $old_qty, $new_qty, $reason) {
        global $wpdb;
        $tables = Hizli_Kasa_Database::get_tables();

        $wpdb->insert($tables['stok_hareketleri'], [
            'product_id'   => $product_id,
            'variation_id' => $variation_id,
            'location_id'  => $location_id,
            'user_id'      => get_current_user_id() ?: 0,
            'old_qty'      => $old_qty,
            'new_qty'      => $new_qty,
            'amount'       => $new_qty - $old_qty, // amount sütunu SQL tanımında var
            'reason'       => $reason,
            'created_at'   => current_time('mysql')
        ]);
    }

    /**
     * Tüm depo stoklarını dışa aktarır.
     */
    public static function export_stocks($format = 'csv', $depo_id = 0) {
        global $wpdb;
        $tables = Hizli_Kasa_Database::get_tables();
        
        $where = "";
        if ($depo_id > 0) {
            $where = $wpdb->prepare(" WHERE sk.location_id = %d", $depo_id);
        }

        $results = $wpdb->get_results("
            SELECT d.name as warehouse, d.priority, d.address as warehouse_address, p.post_title as product_name, sk.quantity, sk.product_id, sk.variation_id
            FROM {$tables['stok_konumlari']} sk
            JOIN {$tables['depolar']} d ON sk.location_id = d.id
            JOIN {$wpdb->posts} p ON (CASE WHEN sk.variation_id > 0 THEN sk.variation_id ELSE sk.product_id END) = p.ID
            $where
        ");

        $data = [];
        foreach ($results as $row) {
            $sku = get_post_meta($row->variation_id ?: $row->product_id, '_sku', true);
            $data[] = [
                'Depo Adı'     => $row->warehouse,
                'Öncelik'     => $row->priority,
                'Depo Adresi' => $row->warehouse_address,
                'Ürün Adı'    => $row->product_name,
                'SKU'         => $sku,
                'Stok Miktarı' => $row->quantity
            ];
        }

        if ($format === 'json') {
            return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        }

        // CSV Hazırla
        $output = "Depo Adı,Öncelik,Depo Adresi,Ürün Adı,SKU,Stok Miktarı\n";
        foreach ($data as $row) {
            $clean_row = array_map(function($v) {
                return '"' . str_replace('"', '""', $v) . '"';
            }, $row);
            $output .= implode(',', $clean_row) . "\n";
        }
        return $output;
    }

    /**
     * İçe aktarma dosyasını işler.
     */
    public static function process_import($file_path, $format = 'csv') {
        $content = file_get_contents($file_path);
        $rows = [];

        if ($format === 'json') {
            $rows = json_decode($content, true);
        } else {
            $lines = explode("\n", str_replace("\r", "", $content));
            $headers = str_getcsv(array_shift($lines));
            foreach ($lines as $line) {
                if (empty($line)) continue;
                $row_data = str_getcsv($line);
                if (count($row_data) === count($headers)) {
                    $rows[] = array_combine($headers, $row_data);
                }
            }
        }

        if (empty($rows)) return ['success' => false, 'message' => 'Dosya boş veya geçersiz format.'];

        $stats = ['updated' => 0, 'unmatched' => 0, 'new_warehouses' => 0];

        foreach ($rows as $row) {
            $warehouse_name = $row['Depo Adı'] ?? $row['warehouse'] ?? '';
            $priority       = intval($row['Öncelik'] ?? $row['priority'] ?? 0);
            $address        = $row['Depo Adresi'] ?? $row['warehouse_address'] ?? '';
            $sku            = $row['SKU'] ?? $row['sku'] ?? '';
            $qty            = floatval($row['Stok Miktarı'] ?? $row['quantity'] ?? 0);
            $product_name   = $row['Ürün Adı'] ?? $row['product_name'] ?? '';

            if (empty($warehouse_name) || empty($sku)) continue;

            // 1. Depoyu Bul veya Oluştur/Güncelle
            $depo_id = self::get_or_create_warehouse($warehouse_name, $stats, $priority, $address);

            // 2. Ürünü Bul
            $ids = self::find_product_by_sku($sku);

            if ($ids) {
                // Eşleşti -> Güncelle
                self::update_warehouse_stock_set($ids['product_id'], $ids['variation_id'], $depo_id, $qty, "İçe Aktarma (Import)");
                $stats['updated']++;
            } else {
                // Eşleşmedi -> Kaydet
                self::add_unmatched_item($warehouse_name, $product_name, $sku, $qty, "Sistemde bu SKU ile eşleşen ürün bulunamadı.");
                $stats['unmatched']++;
            }
        }

        return ['success' => true, 'stats' => $stats];
    }

    /**
     * Depoyu isme göre bulur yoksa oluşturur.
     */
    private static function get_or_create_warehouse($name, &$stats, $priority = 0, $address = '') {
        global $wpdb;
        $table = Hizli_Kasa_Database::get_tables()['depolar'];
        
        $id = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE name = %s", $name));
        
        if (!$id) {
            $wpdb->insert($table, [
                'name' => $name,
                'address' => $address,
                'priority' => $priority,
                'created_at' => current_time('mysql')
            ]);
            $id = $wpdb->insert_id;
            $stats['new_warehouses']++;
        } else {
            // Mevcut depoyu güncelle (Eğer dosyada bilgi varsa)
            $update_data = [];
            if (!empty($address)) $update_data['address'] = $address;
            if ($priority > 0) $update_data['priority'] = $priority;

            if (!empty($update_data)) {
                $wpdb->update($table, $update_data, ['id' => $id]);
            }
        }
        
        return $id;
    }

    /**
     * SKU'ya göre ürün veya varyasyon bulur.
     */
    private static function find_product_by_sku($sku) {
        if (empty($sku)) return false;
        global $wpdb;
        
        $id = $wpdb->get_var($wpdb->prepare("SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_sku' AND meta_value = %s LIMIT 1", $sku));
        
        if (!$id) return false;

        $post = get_post($id);
        if (!$post || ($post->post_type !== 'product' && $post->post_type !== 'product_variation')) return false;

        if ($post->post_type === 'product_variation') {
            return ['product_id' => $post->post_parent, 'variation_id' => $id];
        }

        return ['product_id' => $id, 'variation_id' => 0];
    }

    /**
     * Belirli bir miktarı direkt set eder (Log tutarak).
     */
    public static function update_warehouse_stock_set($product_id, $variation_id, $location_id, $new_qty, $reason = "") {
        global $wpdb;
        $tables = Hizli_Kasa_Database::get_tables();
        
        $current = $wpdb->get_var($wpdb->prepare("
            SELECT quantity FROM {$tables['stok_konumlari']} 
            WHERE product_id = %d AND variation_id = %d AND location_id = %d
        ", $product_id, $variation_id, $location_id));

        $old_qty = $current ? floatval($current) : 0;
        
        if ($current !== null) {
            $wpdb->update($tables['stok_konumlari'], ['quantity' => $new_qty], [
                'product_id' => $product_id, 
                'variation_id' => $variation_id, 
                'location_id' => $location_id
            ]);
        } else {
            $wpdb->insert($tables['stok_konumlari'], [
                'product_id'   => $product_id,
                'variation_id' => $variation_id,
                'location_id'  => $location_id,
                'quantity'     => $new_qty,
                'updated_at'   => current_time('mysql')
            ]);
        }

        self::log_movement($product_id, $variation_id, $location_id, $old_qty, $new_qty, $reason);

        // Uyuşmazlık önbelleğini sıfırla
        if (class_exists('Hizli_Kasa_Mismatch_Notifier')) {
            Hizli_Kasa_Mismatch_Notifier::reset_status();
        }

        return $new_qty;
    }

    /**
     * Eşleşmeyen ürünü kaydeder.
     */
    public static function add_unmatched_item($warehouse_name, $product_name, $sku, $qty, $error) {
        global $wpdb;
        $tables = Hizli_Kasa_Database::get_tables();
        $table = $tables['unmatched_items'];
        
        $inserted = $wpdb->insert($table, [
            'warehouse_name' => $warehouse_name,
            'product_name'   => $product_name,
            'sku'            => $sku,
            'stock_qty'      => $qty,
            'error_msg'      => $error,
            'created_at'     => current_time('mysql')
        ]);

        if ($inserted === false) {
            error_log('Hızlı Kasa DB Hatası (Unmatched Insert): ' . $wpdb->last_error);
        }
    }
    /**
     * Sipariş iptal edildiğinde stokları ilgili depolara geri iade eder.
     */
    public static function handle_cancelled_order_stock($order_id, $order = false) {
        if (!$order) {
            $order = wc_get_order($order_id);
        }
        if (!$order) return;

        hizli_kasa_log("handle_cancelled_order_stock tetiklendi. Sipariş ID: $order_id");

        foreach ($order->get_items() as $item_id => $item) {
            $product_id = $item->get_product_id();
            $variation_id = $item->get_variation_id();

            // 1. Online Sipariş Düşümleri (_hk_deductions)
            $deductions = wc_get_order_item_meta($item_id, '_hk_deductions', true);
            
            if (!empty($deductions) && is_array($deductions)) {
                foreach ($deductions as $ded) {
                    $depo_id = intval($ded['depo_id']);
                    $qty = floatval($ded['qty']);
                    
                    if ($depo_id && $qty > 0) {
                        self::update_warehouse_stock($product_id, $variation_id, $depo_id, $qty, "Sipariş İptali (#$order_id)");
                        hizli_kasa_log("İptal: Online stok iade edildi. Depo: $depo_id, Ürün: $product_id, Adet: $qty");
                    }
                }
            } else {
                // 2. POS Siparişi Düşümü (_hk_cikis_depo_id)
                $depo_id = (int) wc_get_order_item_meta($item_id, '_hk_cikis_depo_id', true);
                $qty = (float) wc_get_order_item_meta($item_id, '_hk_cikis_depo_adet', true);
                
                // Eğer adet meta'sı yoksa item adetini kullan
                if (!$qty) $qty = $item->get_quantity();

                if ($depo_id && $qty > 0) {
                    self::update_warehouse_stock($product_id, $variation_id, $depo_id, $qty, "Sipariş İptali (#$order_id)");
                    hizli_kasa_log("İptal: POS stok iade edildi. Depo: $depo_id, Ürün: $product_id, Adet: $qty");
                }
            }
        }
    }
}
