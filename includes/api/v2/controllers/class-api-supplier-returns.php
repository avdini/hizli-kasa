<?php
/**
 * Hızlı Kasa V2 API Supplier Returns Controller
 *
 * @package HizliKasa
 */

if (!defined('ABSPATH')) {
    exit;
}

class Hizli_Kasa_API_Supplier_Returns extends Hizli_Kasa_API_Controller_Base {

    private ?array $tables = null;

    public function register_routes() {
        register_rest_route($this->namespace, '/tedarikci-iade/olustur', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'create_return_callback'],
            'permission_callback' => [$this, 'check_permission'],
        ]);

        register_rest_route($this->namespace, '/tedarikci-iade/kalem-ekle', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'add_item_callback'],
            'permission_callback' => [$this, 'check_permission'],
        ]);

        register_rest_route($this->namespace, '/tedarikci-iade/kalem-sil', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'delete_item_callback'],
            'permission_callback' => [$this, 'check_permission'],
        ]);

        register_rest_route($this->namespace, '/tedarikci-iade/kalem-miktar-guncelle', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'update_qty_callback'],
            'permission_callback' => [$this, 'check_permission'],
        ]);

        register_rest_route($this->namespace, '/tedarikci-iade/onayla', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'confirm_return_callback'],
            'permission_callback' => [$this, 'check_permission'],
        ]);

        register_rest_route($this->namespace, '/tedarikci-iade/liste', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_returns_callback'],
            'permission_callback' => [$this, 'check_permission'],
        ]);

        register_rest_route($this->namespace, '/tedarikci-iade/detay/(?P<id>\d+)', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_single_return_callback'],
            'permission_callback' => [$this, 'check_permission'],
        ]);

        register_rest_route($this->namespace, '/tedarikci-iade/sil', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'delete_draft_callback'],
            'permission_callback' => [$this, 'check_permission'],
        ]);
    }

    public function create_return_callback($request) {
        return $this->handle_request([$this, 'create_return'], $request);
    }

    public function add_item_callback($request) {
        return $this->handle_request([$this, 'add_item'], $request);
    }

    public function delete_item_callback($request) {
        return $this->handle_request([$this, 'delete_item'], $request);
    }

    public function update_qty_callback($request) {
        return $this->handle_request([$this, 'update_qty'], $request);
    }

    public function confirm_return_callback($request) {
        return $this->handle_request([$this, 'confirm_return'], $request);
    }

    public function get_returns_callback($request) {
        return $this->handle_request([$this, 'get_returns'], $request);
    }

    public function get_single_return_callback($request) {
        return $this->handle_request([$this, 'get_single_return'], $request);
    }

    public function delete_draft_callback($request) {
        return $this->handle_request([$this, 'delete_draft'], $request);
    }

    private function get_tables(): array {
        if ($this->tables === null) {
            $this->tables = Hizli_Kasa_Database::get_tables();
        }
        return $this->tables;
    }

    private function get_current_user(): int {
        return get_current_user_id() ?: 0;
    }

    protected function create_return($request) {
        global $wpdb;
        $data        = $request->get_json_params();
        $supplier_id = intval($data['supplier_id'] ?? 0);
        $location_id = intval($data['location_id'] ?? 0);
        $user_id     = $this->get_current_user();

        if (!$supplier_id || !$location_id) {
            return Hizli_Kasa_API_Response::error('Tedarikçi ve depo seçimi zorunludur.', 400);
        }

        $tables = $this->get_tables();
        
        $wpdb->delete($tables['tedarikci_iadeleri'], [
            'supplier_id' => $supplier_id,
            'location_id' => $location_id,
            'durum'       => 'taslak'
        ]);

        $iade_no = $this->generate_no();
        $wpdb->insert($tables['tedarikci_iadeleri'], [
            'iade_no'           => $iade_no,
            'supplier_id'       => $supplier_id,
            'location_id'       => $location_id,
            'durum'             => 'taslak',
            'olusturan_user_id' => $user_id,
            'created_at'        => current_time('mysql'),
            'updated_at'        => current_time('mysql'),
        ]);

        if (!$wpdb->insert_id) {
            return Hizli_Kasa_API_Response::error('İade taslağı oluşturulamadı.', 500);
        }

        return Hizli_Kasa_API_Response::success([
            'iade' => $this->get_return_header($wpdb->insert_id),
        ]);
    }

    protected function add_item($request) {
        global $wpdb;
        $data    = $request->get_json_params();
        $iade_id = intval($data['iade_id'] ?? 0);
        $sku     = sanitize_text_field($data['sku'] ?? '');
        $qty     = max(0.0001, (float) ($data['qty'] ?? 1));

        $tables = $this->get_tables();
        $iade   = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$tables['tedarikci_iadeleri']} WHERE id = %d", $iade_id));

        if (!$iade || $iade->durum !== 'taslak') {
            return Hizli_Kasa_API_Response::error('Sadece taslak iadelere ürün eklenebilir.', 400);
        }

        $product = $this->find_product_by_sku($sku);
        if (!$product) {
            return Hizli_Kasa_API_Response::error('Barkod/SKU ile ürün bulunamadı.', 404);
        }

        $existing = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM {$tables['tedarikci_iade_kalemleri']}
            WHERE iade_id = %d AND product_id = %d AND variation_id = %d
        ", $iade_id, $product['product_id'], $product['variation_id']));

        if ($existing) {
            $new_qty = (float) $existing->adet + $qty;
            $wpdb->update($tables['tedarikci_iade_kalemleri'], [
                'adet' => $new_qty
            ], ['id' => $existing->id]);
            $kalem_id = $existing->id;
        } else {
            $wpdb->insert($tables['tedarikci_iade_kalemleri'], [
                'iade_id'      => $iade_id,
                'product_id'   => $product['product_id'],
                'variation_id' => $product['variation_id'],
                'sku'          => $product['sku'],
                'urun_adi'     => $product['name'],
                'adet'         => $qty,
                'created_at'   => current_time('mysql'),
            ]);
            $kalem_id = $wpdb->insert_id;
        }

        $this->refresh_totals($iade_id);

        $updated_iade = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$tables['tedarikci_iadeleri']} WHERE id = %d", $iade_id));
        $kalem        = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$tables['tedarikci_iade_kalemleri']} WHERE id = %d", $kalem_id));
        $image_map    = $this->get_product_image_map([$kalem->variation_id ?: $kalem->product_id]);

        return Hizli_Kasa_API_Response::success([
            'iade_id'      => $iade_id,
            'toplam_cesit' => (int) $updated_iade->toplam_cesit,
            'toplam_adet'  => (float) $updated_iade->toplam_adet,
            'durum'        => $updated_iade->durum,
            'updated_at'   => $updated_iade->updated_at,
            'kalem'        => [
                'id'           => (int) $kalem->id,
                'product_id'   => (int) $kalem->product_id,
                'variation_id' => (int) $kalem->variation_id,
                'sku'          => $kalem->sku,
                'urun_adi'     => $kalem->urun_adi,
                'adet'         => (float) $kalem->adet,
                'image'        => $image_map[$kalem->variation_id ?: $kalem->product_id] ?? '',
            ]
        ]);
    }

    protected function delete_item($request) {
        global $wpdb;
        $data     = $request->get_json_params();
        $iade_id  = intval($data['iade_id'] ?? 0);
        $kalem_id = intval($data['kalem_id'] ?? 0);

        $tables = $this->get_tables();
        $iade   = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$tables['tedarikci_iadeleri']} WHERE id = %d", $iade_id));

        if (!$iade || $iade->durum !== 'taslak') {
            return Hizli_Kasa_API_Response::error('Sadece taslak iadeler düzenlenebilir.', 400);
        }

        $wpdb->delete($tables['tedarikci_iade_kalemleri'], ['id' => $kalem_id, 'iade_id' => $iade_id]);
        $this->refresh_totals($iade_id);

        $updated_iade = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$tables['tedarikci_iadeleri']} WHERE id = %d", $iade_id));

        return Hizli_Kasa_API_Response::success([
            'iade_id'      => $iade_id,
            'toplam_cesit' => (int) $updated_iade->toplam_cesit,
            'toplam_adet'  => (float) $updated_iade->toplam_adet,
            'durum'        => $updated_iade->durum,
            'updated_at'   => $updated_iade->updated_at,
            'kalem'        => null
        ]);
    }

    protected function update_qty($request) {
        global $wpdb;
        $data     = $request->get_json_params();
        $iade_id  = intval($data['iade_id'] ?? 0);
        $kalem_id = intval($data['kalem_id'] ?? 0);
        $qty      = max(0.0001, (float) ($data['qty'] ?? 1));

        $tables = $this->get_tables();
        $iade   = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$tables['tedarikci_iadeleri']} WHERE id = %d", $iade_id));

        if (!$iade || $iade->durum !== 'taslak') {
            return Hizli_Kasa_API_Response::error('Sadece taslak iadeler düzenlenebilir.', 400);
        }

        $wpdb->update($tables['tedarikci_iade_kalemleri'], [
            'adet' => $qty
        ], ['id' => $kalem_id, 'iade_id' => $iade_id]);

        $this->refresh_totals($iade_id);

        $updated_iade = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$tables['tedarikci_iadeleri']} WHERE id = %d", $iade_id));
        $kalem        = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$tables['tedarikci_iade_kalemleri']} WHERE id = %d", $kalem_id));
        $image_map    = $this->get_product_image_map([$kalem->variation_id ?: $kalem->product_id]);

        return Hizli_Kasa_API_Response::success([
            'iade_id'      => $iade_id,
            'toplam_cesit' => (int) $updated_iade->toplam_cesit,
            'toplam_adet'  => (float) $updated_iade->toplam_adet,
            'durum'        => $updated_iade->durum,
            'updated_at'   => $updated_iade->updated_at,
            'kalem'        => [
                'id'           => (int) $kalem->id,
                'product_id'   => (int) $kalem->product_id,
                'variation_id' => (int) $kalem->variation_id,
                'sku'          => $kalem->sku,
                'urun_adi'     => $kalem->urun_adi,
                'adet'         => (float) $kalem->adet,
                'image'        => $image_map[$kalem->variation_id ?: $kalem->product_id] ?? '',
            ]
        ]);
    }

    protected function confirm_return($request) {
        global $wpdb;
        $data        = $request->get_json_params();
        $iade_id     = intval($data['iade_id'] ?? 0);
        $iade_sebep  = sanitize_textarea_field($data['iade_sebep'] ?? '');
        $not         = sanitize_textarea_field($data['not'] ?? '');

        $tables = $this->get_tables();
        $iade   = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$tables['tedarikci_iadeleri']} WHERE id = %d", $iade_id));

        if (!$iade || $iade->durum !== 'taslak') {
            return Hizli_Kasa_API_Response::error('Sadece taslak iadeler onaylanabilir.', 400);
        }

        $items = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$tables['tedarikci_iade_kalemleri']} WHERE iade_id = %d", $iade_id));
        if (empty($items)) {
            return Hizli_Kasa_API_Response::error('Boş iade onaylanamaz.', 400);
        }

        foreach ($items as $item) {
            Hizli_Kasa_Stock_Manager::update_warehouse_stock(
                $item->product_id,
                $item->variation_id,
                $iade->location_id,
                -abs($item->adet),
                "Tedarikçi İade (#" . $iade->iade_no . ")"
            );
        }

        $wpdb->update($tables['tedarikci_iadeleri'], [
            'durum'      => 'tamamlandi',
            'iade_sebep' => $iade_sebep,
            'not'        => $not,
            'updated_at' => current_time('mysql'),
        ], ['id' => $iade_id]);

        return Hizli_Kasa_API_Response::success([
            'iade' => $this->get_return_header($iade_id),
        ]);
    }

    protected function get_returns($request) {
        global $wpdb;
        $tables = $this->get_tables();
        
        $limit = 20;
        $page  = max(1, intval($request->get_param('page')));
        $offset = ($page - 1) * $limit;

        $results = $wpdb->get_results($wpdb->prepare("
            SELECT r.*, s.name as supplier_name, d.name as location_name
            FROM {$tables['tedarikci_iadeleri']} r
            LEFT JOIN {$tables['suppliers']} s ON s.id = r.supplier_id
            LEFT JOIN {$tables['depolar']} d ON d.id = r.location_id
            ORDER BY r.created_at DESC
            LIMIT %d OFFSET %d
        ", $limit, $offset), ARRAY_A);

        return Hizli_Kasa_API_Response::success([
            'items' => $results
        ]);
    }

    protected function get_single_return($request) {
        global $wpdb;
        $id     = intval($request['id']);
        $tables = $this->get_tables();

        $iade = $wpdb->get_row($wpdb->prepare("
            SELECT r.*, s.name as supplier_name, d.name as location_name
            FROM {$tables['tedarikci_iadeleri']} r
            LEFT JOIN {$tables['suppliers']} s ON s.id = r.supplier_id
            LEFT JOIN {$tables['depolar']} d ON d.id = r.location_id
            WHERE r.id = %d
        ", $id), ARRAY_A);

        if (!$iade) {
            return Hizli_Kasa_API_Response::error('İade bulunamadı.', 404);
        }

        $items = $wpdb->get_results($wpdb->prepare("
            SELECT k.*
            FROM {$tables['tedarikci_iade_kalemleri']} k
            WHERE k.iade_id = %d
        ", $id));

        $product_ids = array_map(function ($item) {
            return (int) ($item->variation_id ?: $item->product_id);
        }, $items);

        $image_map = $this->get_product_image_map($product_ids);

        $iade['kalemler'] = array_map(function ($item) use ($image_map) {
            $pid = (int) ($item->variation_id ?: $item->product_id);
            return [
                'id'           => (int) $item->id,
                'product_id'   => (int) $item->product_id,
                'variation_id' => (int) $item->variation_id,
                'sku'          => $item->sku,
                'urun_adi'     => $item->urun_adi,
                'adet'         => (float) $item->adet,
                'image'        => $image_map[$pid] ?? '',
            ];
        }, $items);

        return Hizli_Kasa_API_Response::success([
            'iade' => $iade
        ]);
    }

    protected function delete_draft($request) {
        global $wpdb;
        $data    = $request->get_json_params();
        $iade_id = intval($data['iade_id'] ?? 0);

        $tables = $this->get_tables();
        $iade   = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$tables['tedarikci_iadeleri']} WHERE id = %d", $iade_id));

        if (!$iade || $iade->durum !== 'taslak') {
            return Hizli_Kasa_API_Response::error('Sadece taslak iadeler silinebilir.', 400);
        }

        $wpdb->delete($tables['tedarikci_iade_kalemleri'], ['iade_id' => $iade_id]);
        $wpdb->delete($tables['tedarikci_iadeleri'], ['id' => $iade_id]);

        return Hizli_Kasa_API_Response::success([
            'message' => 'Taslak iade başarıyla silindi.'
        ]);
    }

    private function generate_no(): string {
        global $wpdb;
        $tables = $this->get_tables();
        $prefix = 'TIA-' . date('Ymd') . '-';

        $last = $wpdb->get_var($wpdb->prepare("
            SELECT iade_no FROM {$tables['tedarikci_iadeleri']}
            WHERE iade_no LIKE %s
            ORDER BY id DESC LIMIT 1
        ", $prefix . '%'));

        if ($last) {
            $seq = intval(substr($last, -4)) + 1;
        } else {
            $seq = 1;
        }

        return $prefix . str_pad($seq, 4, '0', STR_PAD_LEFT);
    }

    private function get_return_header(int $id): ?array {
        global $wpdb;
        $tables = $this->get_tables();
        return $wpdb->get_row($wpdb->prepare("
            SELECT r.*, s.name as supplier_name
            FROM {$tables['tedarikci_iadeleri']} r
            LEFT JOIN {$tables['suppliers']} s ON s.id = r.supplier_id
            WHERE r.id = %d
        ", $id), ARRAY_A);
    }

    private function refresh_totals(int $iade_id) {
        global $wpdb;
        $tables = $this->get_tables();

        $totals = $wpdb->get_row($wpdb->prepare("
            SELECT COUNT(id) as cesit, SUM(adet) as adet
            FROM {$tables['tedarikci_iade_kalemleri']}
            WHERE iade_id = %d
        ", $iade_id));

        $wpdb->update($tables['tedarikci_iadeleri'], [
            'toplam_cesit' => (int) $totals->cesit,
            'toplam_adet'  => (float) $totals->adet,
            'updated_at'   => current_time('mysql'),
        ], ['id' => $iade_id]);
    }

    protected function find_product_by_sku(string $sku): array|false {
        global $wpdb;

        $post_id = wc_get_product_id_by_sku($sku);

        if (!$post_id) {
            $post_id = $wpdb->get_var($wpdb->prepare("
                SELECT pm.post_id 
                FROM {$wpdb->postmeta} pm
                JOIN {$wpdb->posts} p ON pm.post_id = p.ID
                WHERE pm.meta_key = '_sku' AND pm.meta_value = %s
                AND p.post_status IN ('publish', 'private')
                AND p.post_type IN ('product', 'product_variation')
                LIMIT 1
            ", $sku));
        }

        if (!$post_id && is_numeric($sku)) {
            $product_by_id = wc_get_product(intval($sku));
            if ($product_by_id) {
                $post_id = $product_by_id->get_id();
            }
        }

        if (!$post_id) {
            return false;
        }

        $product = wc_get_product($post_id);
        if (!$product) {
            return false;
        }

        $variation_id = $product->is_type('variation') ? $product->get_id() : 0;
        return [
            'product_id'   => $variation_id ? $product->get_parent_id() : $product->get_id(),
            'variation_id' => $variation_id,
            'sku'          => $product->get_sku() ?: $sku,
            'name'         => $product->get_name(),
        ];
    }

    private function get_product_image_map(array $product_ids): array {
        global $wpdb;
        if (empty($product_ids)) {
            return [];
        }

        $ids  = implode(',', array_map('intval', $product_ids));
        $rows = $wpdb->get_results("
            SELECT p.ID as product_id,
                   COALESCE(NULLIF(pm.meta_value, ''), pm2.meta_value) as thumbnail_id
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm
                ON pm.post_id = p.ID AND pm.meta_key = '_thumbnail_id'
            LEFT JOIN {$wpdb->postmeta} pm2
                ON pm2.post_id = p.post_parent AND pm2.meta_key = '_thumbnail_id'
            WHERE p.ID IN ($ids)
        ");

        $map = [];
        foreach ($rows as $row) {
            $url = $row->thumbnail_id
                ? wp_get_attachment_image_url((int) $row->thumbnail_id, 'thumbnail')
                : '';
            $map[(int) $row->product_id] = $url ?: '';
        }
        return $map;
    }
}
