<?php
if (!defined('ABSPATH')) {
    exit;
}

class Hizli_Kasa_Stock_Import_Export {
    public static function export($format = 'csv', $depo_id = 0) {
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

        $output = "Depo Adı,Öncelik,Depo Adresi,Ürün Adı,SKU,Stok Miktarı\n";
        foreach ($data as $row) {
            $clean_row = array_map(fn($v) => '"' . str_replace('"', '""', $v) . '"', $row);
            $output .= implode(',', $clean_row) . "\n";
        }
        return $output;
    }

    public static function import($file_path, $format = 'csv') {
        $content = file_get_contents($file_path);
        $content = preg_replace('/^[\xef\xbb\xbf]+/', '', $content);
        $rows = [];

        if ($format === 'json') {
            $rows = json_decode($content, true);
        } else {
            $lines = explode("\n", str_replace("\r", "", $content));
            $headers = str_getcsv(array_shift($lines));

            foreach ($headers as &$h) {
                $h = trim($h);
                $h = preg_replace('/^[\xef\xbb\xbf]+/', '', $h);
            }
            unset($h);

            foreach ($lines as $line) {
                if (empty($line)) {
                    continue;
                }
                $row_data = str_getcsv($line);
                if (count($row_data) === count($headers)) {
                    $rows[] = array_combine($headers, $row_data);
                }
            }
        }

        if (empty($rows)) {
            return ['success' => false, 'message' => 'Dosya boş veya geçersiz format.'];
        }

        $stats = ['updated' => 0, 'unmatched' => 0, 'new_warehouses' => 0];

        foreach ($rows as $row) {
            $safe_row = [];
            foreach ($row as $k => $v) {
                $safe_k = mb_strtolower(trim((string)$k), 'UTF-8');
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
            $raw_qty        = str_replace(',', '.', trim((string)$raw_qty));
            $qty            = floatval($raw_qty);

            $product_name   = $safe_row['urunadi'] ?? $safe_row['productname'] ?? $row['Ürün Adı'] ?? '';

            if (empty($warehouse_name) || (empty($sku) && empty($product_name))) {
                continue;
            }

            $depo_id = self::get_or_create_warehouse($warehouse_name, $stats, $priority, $address);

            $ids = false;
            if (!empty($sku)) {
                $ids = self::find_product_by_sku($sku);
            }
            if (!$ids && !empty($product_name)) {
                $ids = self::find_product_by_name($product_name);
            }

            if ($ids) {
                Hizli_Kasa_Stock_Manager::update_warehouse_stock_set($ids['product_id'], $ids['variation_id'], $depo_id, $qty, "İçe Aktarma (Import)");
                $stats['updated']++;
            } else {
                self::add_unmatched_item($warehouse_name, $product_name, $sku, $qty, "Sistemde bu SKU ile eşleşen ürün bulunamadı.");
                $stats['unmatched']++;
            }
        }

        return ['success' => true, 'stats' => $stats];
    }

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
            $update_data = [];
            if (!empty($address)) {
                $update_data['address'] = $address;
            }
            if ($priority > 0) {
                $update_data['priority'] = $priority;
            }

            if ($update_data !== []) {
                $wpdb->update($table, $update_data, ['id' => $id]);
            }
        }

        return $id;
    }

    private static function find_product_by_sku($sku) {
        if (empty($sku)) {
            return false;
        }
        global $wpdb;

        $id = $wpdb->get_var($wpdb->prepare("SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_sku' AND meta_value = %s LIMIT 1", $sku));

        if (!$id) {
            return false;
        }

        $post = get_post($id);
        if (!$post || ($post->post_type !== 'product' && $post->post_type !== 'product_variation')) {
            return false;
        }

        if ($post->post_type === 'product_variation') {
            return ['product_id' => $post->post_parent, 'variation_id' => $id];
        }

        return ['product_id' => $id, 'variation_id' => 0];
    }

    private static function find_product_by_name($product_name) {
        if (empty($product_name)) {
            return false;
        }
        global $wpdb;

        $id = $wpdb->get_var($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE post_title = %s AND post_type IN ('product', 'product_variation') AND post_status IN ('publish', 'private') LIMIT 1", $product_name));

        if (!$id) {
            return false;
        }

        $post = get_post($id);
        if (!$post) {
            return false;
        }

        if ($post->post_type === 'product_variation') {
            return ['product_id' => $post->post_parent, 'variation_id' => $id];
        }

        return ['product_id' => $id, 'variation_id' => 0];
    }

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
}
