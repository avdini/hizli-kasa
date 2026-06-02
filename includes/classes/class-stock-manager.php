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
    public static function listen() {
        // Online Sipariş: İşleniyor durumuna geçtiğinde stok AYIRT (Reserve)
        add_action('woocommerce_order_status_processing', [self::class, 'handle_online_order_reservation'], 10, 2);
        
        // Online Sipariş: Tamamlandığında stoğu KESİNLEŞTİR (Deduct)
        add_action('woocommerce_order_status_completed', [self::class, 'handle_online_order_completion'], 10, 2);
        
        // Sipariş iptal edildiğinde (Online ise rezervasyonu bırak, POS ise stoğu geri koy)
        add_action('woocommerce_order_status_cancelled', [self::class, 'handle_cancelled_order_stock'], 10, 2);
        add_action('woocommerce_order_status_refunded', [self::class, 'handle_cancelled_order_stock'], 10, 2);
        add_action('woocommerce_order_status_failed', [self::class, 'handle_cancelled_order_stock'], 10, 2);
        
        // POS Siparişi: Yeni sipariş oluşturulduğunda direkt fiziksel stoktan düş
        add_action('woocommerce_new_order', [self::class, 'handle_pos_order_stock'], 10, 2);
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

        // Öncelikle sipariş üzerindeki depoyu kontrol et (REST API ile gelmiş olabilir)
        $depo_id = $order->get_meta('_hk_cikis_depo_id');

        if (!$depo_id) {
            // Kasiyerin kullanıcı ID'sini bul (veya şu anki kullanıcıyı kullan)
            $user_id = get_current_user_id();
            
            // REST API çağrılarında bazen user_id 0 gelebilir, bu durumda meta'dan bulmaya çalışabiliriz
            if (!$user_id) {
                 hizli_kasa_log("Uyarı: current_user_id 0 döndü. REST API auth kontrol edilmeli.");
            }

            // Yeni çoklu depo sisteminden aktif depoyu al
            $depo_id = get_user_meta($user_id, '_hizli_kasa_active_depo', true);

            // Fallback: Eski sisteme bak
            if (!$depo_id) {
                $depo_id = get_user_meta($user_id, '_hizli_kasa_depo_id', true);
            }
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
    /**
     * Online siparişlerde stok rezervasyonu yapar.
     */
    public static function handle_online_order_reservation($order_id, $order = false) {
        if (!$order) $order = wc_get_order($order_id);
        if (!$order) return;

        // Eğer sipariş "Hızlı Kasa" üzerinden gelmişse bu fonksiyonu atla 
        if ($order->get_meta('_hizli_kasa_kasiyer')) return;

        hizli_kasa_log("handle_online_order_reservation tetiklendi. Sipariş ID: $order_id");

        foreach ($order->get_items() as $item) {
            $product_id = $item->get_product_id();
            $variation_id = $item->get_variation_id();
            $qty = $item->get_quantity();

            self::priority_stock_reservation($product_id, $variation_id, $qty, $item);
        }
    }

    /**
     * Online sipariş tamamlandığında rezervasyonu fiziksel düşüme çevirir.
     */
    public static function handle_online_order_completion($order_id, $order = false) {
        if (!$order) $order = wc_get_order($order_id);
        if (!$order) return;

        // Eğer sipariş "Hızlı Kasa" üzerinden gelmişse bu fonksiyonu atla 
        if ($order->get_meta('_hizli_kasa_kasiyer')) return;

        hizli_kasa_log("handle_online_order_completion tetiklendi. Sipariş ID: $order_id");

        foreach ($order->get_items() as $item_id => $item) {
            $product_id = $item->get_product_id();
            $variation_id = $item->get_variation_id();
            
            // Rezerve bilgisini al
            $reservations = wc_get_order_item_meta($item_id, '_hk_reservations', true);
            if (empty($reservations)) continue;

            foreach ($reservations as $res) {
                $depo_id = intval($res['depo_id']);
                $qty = floatval($res['qty']);

                // 1. Rezervasyonu kaldır
                self::update_warehouse_stock_reservation($product_id, $variation_id, $depo_id, -$qty);
                
                // 2. Fiziksel stoğu düş (Zaten rezerve olduğu için conflict kontrolüne gerek yok)
                self::update_warehouse_stock($product_id, $variation_id, $depo_id, -$qty, "Online Sipariş Tamamlandı (#$order_id)");
            }
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
            WHERE p.post_status IN ('publish', 'private') 
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

        // Rezervasyon Çatışma Kontrolü: Fiziksel stok rezerve edilenin altına düştü mü?
        if ($change_amount < 0) {
            $reserved = $current ? floatval($current->reserved) : 0;
            if ($new_qty < $reserved) {
                self::resolve_stock_reservation_conflict($product_id, $variation_id, $location_id, $reserved - $new_qty);
            }
        }
        self::log_movement($product_id, $variation_id, $location_id, $old_qty, $new_qty, $reason);

        // Uyuşmazlık önbelleğini ertelenmiş olarak sıfırla (istek sonunda 1 kez çalışır).
        // Bir siparişteki N ürün N kez değil, tüm stok güncellemeleri bittikten sonra 1 kez tetiklenir.
        self::schedule_deferred_invalidation();

        return $new_qty;
    }

    /**
     * Mismatch ve rapor önbelleklerini PHP isteği kapanırken 1 kez sıfırla.
     * Bu sayede N ürünlü sipariş N değil 1 delete_option + 1 update_option işlemi yapar.
     */
    private static $deferred_invalidation_scheduled = false;

    public static function schedule_deferred_invalidation() {
        if (self::$deferred_invalidation_scheduled) {
            return; // Zaten planlandı, tekrar ekleme
        }
        self::$deferred_invalidation_scheduled = true;

        add_action('shutdown', function () {
            if (class_exists('Hizli_Kasa_Mismatch_Notifier')) {
                Hizli_Kasa_Mismatch_Notifier::reset_status();
            }
        }, 99);
    }

    public static function transfer_out($product_id, $variation_id, $kaynak_depo_id, $qty, $sevk_id) {
        $sevk_no = self::get_transfer_sevk_no($sevk_id);
        return self::update_warehouse_stock($product_id, $variation_id, $kaynak_depo_id, -abs((float) $qty), "Sevk Çıkış (#$sevk_no)");
    }

    public static function transfer_in($product_id, $variation_id, $hedef_depo_id, $qty, $sevk_id) {
        $sevk_no = self::get_transfer_sevk_no($sevk_id);
        return self::update_warehouse_stock($product_id, $variation_id, $hedef_depo_id, abs((float) $qty), "Sevk Giriş (#$sevk_no)");
    }

    private static function get_transfer_sevk_no($sevk_id) {
        global $wpdb;
        $tables = Hizli_Kasa_Database::get_tables();
        $sevk_no = $wpdb->get_var($wpdb->prepare("SELECT sevk_no FROM {$tables['sevkler']} WHERE id = %d", $sevk_id));
        return $sevk_no ?: (string) $sevk_id;
    }

    /**
     * Belirli bir depoda rezervasyon miktarını günceller.
     */
    public static function update_warehouse_stock_reservation($product_id, $variation_id, $location_id, $change_amount) {
        global $wpdb;
        $tables = Hizli_Kasa_Database::get_tables();

        $current = $wpdb->get_row($wpdb->prepare("
            SELECT id, reserved FROM {$tables['stok_konumlari']} 
            WHERE product_id = %d AND variation_id = %d AND location_id = %d
        ", $product_id, $variation_id, $location_id));

        $old_res = $current ? floatval($current->reserved) : 0;
        $new_res = max(0, $old_res + $change_amount);

        if ($current) {
            $wpdb->update($tables['stok_konumlari'], ['reserved' => $new_res], ['id' => $current->id]);
        } else {
            $wpdb->insert($tables['stok_konumlari'], [
                'product_id'   => $product_id,
                'variation_id' => $variation_id,
                'location_id'  => $location_id,
                'reserved'     => $new_res
            ]);
        }
        
        hizli_kasa_log("Rezervasyon Güncellendi: L:$location_id, P:$product_id, V:$variation_id, Old:$old_res, New:$new_res");
        return $new_res;
    }


    /**
     * Online satışlar için öncelikli stok rezervasyonu.
     */
    public static function priority_stock_reservation($product_id, $variation_id, $total_to_deduct, $item = null) {
        global $wpdb;
        $tables = Hizli_Kasa_Database::get_tables();
        
        $online_depo_id = get_option('hizli_kasa_varsayilan_online_depo');
        
        // Depoları önceliğe göre getir
        $depolar = $wpdb->get_results("SELECT id FROM {$tables['depolar']} ORDER BY 
            (CASE WHEN id = " . intval($online_depo_id) . " THEN 1 ELSE 0 END) DESC, 
            priority DESC");

        $remaining = $total_to_deduct;
        $reservations = [];

        foreach ($depolar as $d) {
            if ($remaining <= 0) break;

            $stock_data = $wpdb->get_row($wpdb->prepare("
                SELECT quantity, reserved FROM {$tables['stok_konumlari']} 
                WHERE product_id = %d AND variation_id = %d AND location_id = %d
            ", $product_id, $variation_id, $d->id));

            $qty = $stock_data ? floatval($stock_data->quantity) : 0;
            $res = $stock_data ? floatval($stock_data->reserved) : 0;
            $available = $qty - $res;

            if ($available <= 0) continue;

            $to_take = min($available, $remaining);
            self::update_warehouse_stock_reservation($product_id, $variation_id, $d->id, $to_take);
            
            $reservations[] = ['depo_id' => $d->id, 'qty' => $to_take];
            $remaining -= $to_take;
        }

        // Eğer hala rezerve edilecek miktar kaldıysa, öncelikli depodan eksiye düşerek rezerve et
        if ($remaining > 0 && $online_depo_id) {
            self::update_warehouse_stock_reservation($product_id, $variation_id, $online_depo_id, $remaining);
            
            $found = false;
            foreach ($reservations as &$r) {
                if ($r['depo_id'] == $online_depo_id) {
                    $r['qty'] += $remaining;
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $reservations[] = ['depo_id' => $online_depo_id, 'qty' => $remaining];
            }
        }

        // Rezervasyonları sipariş kalemine kaydet
        if ($item && !empty($reservations)) {
            wc_update_order_item_meta($item->get_id(), '_hk_reservations', $reservations);
        }
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
        // BOM (Byte Order Mark) temizle
        $content = preg_replace('/^[\xef\xbb\xbf]+/', '', $content);
        $rows = [];

        if ($format === 'json') {
            $rows = json_decode($content, true);
        } else {
            $lines = explode("\n", str_replace("\r", "", $content));
            $headers = str_getcsv(array_shift($lines));
            
            // CSV başlıklarındaki olası görünmez karakterleri (BOM vs) temizle
            foreach ($headers as &$h) {
                $h = trim($h);
                $h = preg_replace('/^[\xef\xbb\xbf]+/', '', $h);
            }
            unset($h);

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
            // Güvenli anahtar okuma (farklı klavye, harf büyüklüğü ve boşluk hatalarına karşı)
            $safe_row = [];
            foreach ($row as $k => $v) {
                $safe_k = mb_strtolower(trim((string)$k), 'UTF-8');
                // Türkçe karakterleri ve boşlukları standardize et
                $safe_k = str_replace(
                    ['ı', 'i', 'ğ', 'g', 'ü', 'u', 'ş', 's', 'ö', 'o', 'ç', 'c', ' ', '_', '-'], 
                    ['i', 'i', 'g', 'g', 'u', 'u', 's', 's', 'o', 'o', 'c', 'c', '', '', ''], 
                    $safe_k
                );
                $safe_row[$safe_k] = $v;
            }

            $warehouse_name = $safe_row['depoadi'] ?? $safe_row['warehouse'] ?? $row['Depo Adı'] ?? '';
            $priority       = intval($safe_row['oncelik'] ?? $safe_row['priority'] ?? $row['Öncelik'] ?? 0);
            $address        = $safe_row['depoadresi'] ?? $safe_row['warehouseaddress'] ?? $row['Depo Adresi'] ?? '';
            $sku            = $safe_row['sku'] ?? $row['SKU'] ?? '';
            
            $raw_qty        = $safe_row['stokmiktari'] ?? $safe_row['quantity'] ?? $safe_row['qty'] ?? $row['Stok Miktarı'] ?? 0;
            // Sayı formatlarını düzelt (örn: " 5,5 " -> "5.5")
            $raw_qty        = str_replace(',', '.', trim((string)$raw_qty));
            $qty            = floatval($raw_qty);

            $product_name   = $safe_row['urunadi'] ?? $safe_row['productname'] ?? $row['Ürün Adı'] ?? '';

            // SKU veya Ürün Adı yoksa atla
            if (empty($warehouse_name) || (empty($sku) && empty($product_name))) continue;

            // 1. Depoyu Bul veya Oluştur/Güncelle
            $depo_id = self::get_or_create_warehouse($warehouse_name, $stats, $priority, $address);

            // 2. Ürünü Bul
            $ids = false;
            if (!empty($sku)) {
                $ids = self::find_product_by_sku($sku);
            }
            // SKU boşsa veya SKU'dan bulunamadıysa Ürün Adı ile ara
            if (!$ids && !empty($product_name)) {
                $ids = self::find_product_by_name($product_name);
            }

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
     * İsim (post_title) ile ürün veya varyasyon bulur (SKU Fallback).
     */
    private static function find_product_by_name($product_name) {
        if (empty($product_name)) return false;
        global $wpdb;
        
        $id = $wpdb->get_var($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE post_title = %s AND post_type IN ('product', 'product_variation') AND post_status IN ('publish', 'private') LIMIT 1", $product_name));
        
        if (!$id) return false;

        $post = get_post($id);
        if (!$post) return false;

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

        // Uyuşmazlık önbelleğini ertelenmiş olarak sıfırla
        self::schedule_deferred_invalidation();

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
     * Sipariş iptal edildiğinde (veya iade) stokları ilgili depolara geri iade eder veya rezervasyonu kaldırır.
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

            // Zaten iade edilmiş mi kontrolü (Mükerrer stok iadesini önle)
            if ($item->get_meta('_hk_restocked_on_cancel')) continue;

            // 1. Online Sipariş Rezervasyonları (_hk_reservations)
            $reservations = wc_get_order_item_meta($item_id, '_hk_reservations', true);
            
            if (!empty($reservations) && is_array($reservations)) {
                foreach ($reservations as $res) {
                    $depo_id = intval($res['depo_id']);
                    $qty = floatval($res['qty']);
                    
                    if ($depo_id && $qty > 0) {
                        self::update_warehouse_stock_reservation($product_id, $variation_id, $depo_id, -$qty);
                        hizli_kasa_log("İptal: Online rezervasyon bırakıldı. Depo: $depo_id, Ürün: $product_id, Adet: $qty");
                    }
                }
                wc_update_order_item_meta($item_id, '_hk_restocked_on_cancel', 'yes');
            } else {
                // 2. POS Siparişi Düşümü (_hk_cikis_depo_id)
                $depo_id = (int) wc_get_order_item_meta($item_id, '_hk_cikis_depo_id', true);
                
                // Fallback: Sipariş seviyesindeki depoya bak
                if (!$depo_id) {
                    $depo_id = (int) $order->get_meta('_hk_cikis_depo_id');
                }

                $qty = (float) wc_get_order_item_meta($item_id, '_hk_cikis_depo_adet', true);
                
                // Eğer adet meta'sı yoksa item adetini kullan
                if (!$qty) $qty = $item->get_quantity();

                if ($depo_id && $qty > 0) {
                    self::update_warehouse_stock($product_id, $variation_id, $depo_id, $qty, "Sipariş İptali/İade (#$order_id)");
                    hizli_kasa_log("İptal: POS stok iade edildi. Depo: $depo_id, Ürün: $product_id, Adet: $qty");
                    
                    // İşlendi olarak işaretle
                    wc_update_order_item_meta($item_id, '_hk_restocked_on_cancel', 'yes');
                }
            }
        }
    }

    /**
     * Fiziksel stok yetersiz kaldığında çakışan rezervasyonları iptal eder.
     */
    public static function resolve_stock_reservation_conflict($product_id, $variation_id, $location_id, $conflict_qty) {
        global $wpdb;
        
        hizli_kasa_log("STOK ÇAKIŞMASI: P:$product_id, V:$variation_id, L:$location_id, Çakışan Adet: $conflict_qty");

        // Bu ürün/varyasyon ve depo için AKTİF rezervasyonu olan siparişleri bul
        // En yeni siparişten başlayarak iptal et
        $orders_query = $wpdb->prepare("
            SELECT im.order_item_id, i.order_id, im.meta_value as reservations
            FROM {$wpdb->prefix}woocommerce_order_itemmeta im
            JOIN {$wpdb->prefix}woocommerce_order_items i ON im.order_item_id = i.order_item_id
            JOIN {$wpdb->prefix}woocommerce_order_itemmeta im2 ON im.order_item_id = im2.order_item_id
            JOIN {$wpdb->posts} p ON i.order_id = p.ID
            WHERE im.meta_key = '_hk_reservations'
              AND im2.meta_key = '_product_id' AND im2.meta_value = %d
              AND p.post_status = 'wc-processing'
            ORDER BY p.post_date DESC
        ", $product_id);
        
        $results = $wpdb->get_results($orders_query);
        $remaining_to_fix = $conflict_qty;

        foreach ($results as $row) {
            if ($remaining_to_fix <= 0) break;

            $item_variation_id = (int) wc_get_order_item_meta($row->order_item_id, '_variation_id', true);
            if ($variation_id > 0 && $item_variation_id != $variation_id) {
                continue;
            }
            if ($variation_id == 0 && $item_variation_id > 0) {
                continue;
            }

            $reservations = maybe_unserialize($row->reservations);
            if (!is_array($reservations)) continue;

            foreach ($reservations as &$res) {
                if ($res['depo_id'] == $location_id) {
                    $order = wc_get_order($row->order_id);
                    if (!$order) continue;

                    // Bu siparişten ne kadar rezerve edilmiş?
                    $res_qty = floatval($res['qty']);
                    $to_cancel = min($res_qty, $remaining_to_fix);

                    // Siparişi "Failed" (Başarısız) durumuna al
                    // Not: Bu işlem 'woocommerce_order_status_failed' kancasını tetikleyecek ve 
                    // handle_cancelled_order_stock fonksiyonu rezervasyonları otomatik olarak temizleyecektir.
                    $order->update_status('failed', sprintf('Stok yetersizliği nedeniyle sistem tarafından iptal edildi. (POS Satışı Çakışması, Ürün ID: %d, Depo ID: %d)', ($variation_id ?: $product_id), $location_id));
                    
                    $remaining_to_fix -= $to_cancel;
                    hizli_kasa_log("Çatışma Çözüldü: Sipariş #{$row->order_id} başarısız durumuna alındı.");
                    break; 
                }
            }
        }
    }
}
