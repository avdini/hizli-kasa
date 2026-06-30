<?php
/**
 * Hızlı Kasa V2 API Shipments Controller
 *
 * @package HizliKasa
 */

if (!defined('ABSPATH')) {
    exit;
}

class Hizli_Kasa_API_Shipments extends Hizli_Kasa_API_Controller_Base {

    private ?array $tables          = null;
    private ?int   $current_user_id = null;

    public function register_routes() {
        register_rest_route($this->namespace, '/sevk/olustur', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'create_sevk_callback'],
            'permission_callback' => [$this, 'check_permission'],
        ]);

        register_rest_route($this->namespace, '/sevk/kalem-ekle', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'add_item_callback'],
            'permission_callback' => [$this, 'check_permission'],
        ]);

        register_rest_route($this->namespace, '/sevk/kalem-sil', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'delete_item_callback'],
            'permission_callback' => [$this, 'check_permission'],
        ]);

        register_rest_route($this->namespace, '/sevk/kalem-miktar-guncelle', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'update_item_qty_callback'],
            'permission_callback' => [$this, 'check_permission'],
        ]);

        register_rest_route($this->namespace, '/sevk/teslim-miktar-guncelle', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'update_receipt_qty_callback'],
            'permission_callback' => [$this, 'check_permission'],
        ]);

        register_rest_route($this->namespace, '/sevk/gonder-onayla', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'submit_for_approval_callback'],
            'permission_callback' => [$this, 'check_permission'],
        ]);

        register_rest_route($this->namespace, '/sevk/alici-onayla', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'receiver_accept_callback'],
            'permission_callback' => [$this, 'check_permission'],
        ]);

        register_rest_route($this->namespace, '/sevk/alici-reddet', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'receiver_reject_callback'],
            'permission_callback' => [$this, 'check_permission'],
        ]);

        register_rest_route($this->namespace, '/sevk/yola-cikart', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'dispatch_callback'],
            'permission_callback' => [$this, 'check_permission'],
        ]);

        register_rest_route($this->namespace, '/sevk/teslim-barkod', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'scan_delivery_barcode_callback'],
            'permission_callback' => [$this, 'check_permission'],
        ]);

        register_rest_route($this->namespace, '/sevk/teslim-onayla', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'approve_delivery_callback'],
            'permission_callback' => [$this, 'check_permission'],
        ]);

        register_rest_route($this->namespace, '/sevk/liste', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_shipments_callback'],
            'permission_callback' => [$this, 'check_permission'],
        ]);

        register_rest_route($this->namespace, '/sevk/detay/(?P<id>\d+)', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_single_shipment_callback'],
            'permission_callback' => [$this, 'check_permission'],
        ]);

        register_rest_route($this->namespace, '/sevk/bekleyen-sayisi', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_pending_count_callback'],
            'permission_callback' => [$this, 'check_permission'],
        ]);

        register_rest_route($this->namespace, '/sevk/active-draft', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_active_draft_callback'],
            'permission_callback' => [$this, 'check_permission'],
        ]);

        register_rest_route($this->namespace, '/sevk/delete-draft', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'delete_draft_callback'],
            'permission_callback' => [$this, 'check_permission'],
        ]);
    }

    public function create_sevk_callback($request) {
        return $this->handle_request([$this, 'create_sevk'], $request);
    }

    public function add_item_callback($request) {
        return $this->handle_request([$this, 'add_item'], $request);
    }

    public function delete_item_callback($request) {
        return $this->handle_request([$this, 'delete_item'], $request);
    }

    public function update_item_qty_callback($request) {
        return $this->handle_request([$this, 'update_item_qty'], $request);
    }

    public function update_receipt_qty_callback($request) {
        return $this->handle_request([$this, 'update_receipt_qty'], $request);
    }

    public function submit_for_approval_callback($request) {
        return $this->handle_request([$this, 'submit_for_approval'], $request);
    }

    public function receiver_accept_callback($request) {
        return $this->handle_request([$this, 'receiver_accept'], $request);
    }

    public function receiver_reject_callback($request) {
        return $this->handle_request([$this, 'receiver_reject'], $request);
    }

    public function dispatch_callback($request) {
        return $this->handle_request([$this, 'dispatch'], $request);
    }

    public function scan_delivery_barcode_callback($request) {
        return $this->handle_request([$this, 'scan_delivery_barcode'], $request);
    }

    public function approve_delivery_callback($request) {
        return $this->handle_request([$this, 'approve_delivery'], $request);
    }

    public function get_shipments_callback($request) {
        return $this->handle_request([$this, 'get_shipments'], $request);
    }

    public function get_single_shipment_callback($request) {
        return $this->handle_request([$this, 'get_single_shipment'], $request);
    }

    public function get_pending_count_callback($request) {
        return $this->handle_request([$this, 'get_pending_count'], $request);
    }

    public function get_active_draft_callback($request) {
        return $this->handle_request([$this, 'get_active_draft'], $request);
    }

    public function delete_draft_callback($request) {
        return $this->handle_request([$this, 'delete_draft'], $request);
    }

    protected function create_sevk($request) {
        global $wpdb;
        $data    = $request->get_json_params();
        $kaynak  = intval($data['kaynak_depo_id'] ?? 0);
        $hedef   = intval($data['hedef_depo_id'] ?? 0);
        $user_id = $this->get_current_user();

        if (!$kaynak || !$hedef || $kaynak === $hedef) {
            return Hizli_Kasa_API_Response::error('Kaynak ve hedef depo seçimi geçersiz.', 400);
        }
        if (!hizli_kasa_can_user_manage_depo($user_id, $kaynak)) {
            return Hizli_Kasa_API_Response::error('Kaynak depoda yönetim yetkiniz yok.', 403);
        }
        if (!hizli_kasa_can_user_view_depo($user_id, $hedef)) {
            return Hizli_Kasa_API_Response::error('Hedef depoyu görüntüleme yetkiniz yok.', 403);
        }

        $tables = $this->get_tables();
        $wpdb->insert($tables['sevkler'], [
            'sevk_no'           => $this->generate_no(),
            'kaynak_depo_id'    => $kaynak,
            'hedef_depo_id'     => $hedef,
            'durum'             => 'taslak',
            'olusturan_user_id' => $user_id,
            'created_at'        => current_time('mysql'),
            'updated_at'        => current_time('mysql'),
        ]);

        if (!$wpdb->insert_id) {
            return Hizli_Kasa_API_Response::error('Sevk oluşturulamadı.', 500);
        }

        return Hizli_Kasa_API_Response::success([
            'sevk' => $this->format_shipment($this->get_shipment($wpdb->insert_id), true),
        ]);
    }

    protected function add_item($request) {
        global $wpdb;
        $data    = $request->get_json_params();
        $sevk_id = intval($data['sevk_id'] ?? 0);
        $sku     = sanitize_text_field($data['sku'] ?? '');
        $qty     = max(0.0001, (float) ($data['qty'] ?? 1));

        $sevk = $this->get_shipment($sevk_id);
        if (!$sevk || $sevk->durum !== 'taslak') {
            return Hizli_Kasa_API_Response::error('Sadece taslak sevke ürün eklenebilir.', 400);
        }
        if (!$this->user_can_source($sevk, true)) {
            return Hizli_Kasa_API_Response::error('Bu sevki düzenleme yetkiniz yok.', 403);
        }

        $product = $this->find_product_by_sku($sku);
        if (!$product) {
            return Hizli_Kasa_API_Response::error('Barkod/SKU ile ürün bulunamadı.', 404);
        }

        $tables   = $this->get_tables();
        $existing = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM {$tables['sevk_kalemleri']}
            WHERE sevk_id = %d AND product_id = %d AND variation_id = %d
        ", $sevk_id, $product['product_id'], $product['variation_id']));

        if ($existing) {
            $wpdb->update($tables['sevk_kalemleri'], [
                'gonderilen_adet' => (float) $existing->gonderilen_adet + $qty,
                'updated_at'      => current_time('mysql'),
            ], ['id' => $existing->id]);
            $kalem_id = $existing->id;
        } else {
            $wpdb->insert($tables['sevk_kalemleri'], [
                'sevk_id'         => $sevk_id,
                'product_id'      => $product['product_id'],
                'variation_id'    => $product['variation_id'],
                'sku'             => $product['sku'],
                'urun_adi'        => $product['name'],
                'gonderilen_adet' => $qty,
                'created_at'      => current_time('mysql'),
                'updated_at'      => current_time('mysql'),
            ]);
            $kalem_id = $wpdb->insert_id;
        }

        $this->refresh_totals($sevk_id);
        $updated_sevk = $this->get_shipment($sevk_id);
        $kalem        = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$tables['sevk_kalemleri']} WHERE id = %d",
            $kalem_id
        ));

        return Hizli_Kasa_API_Response::success(
            $this->format_mutation_response($updated_sevk, $kalem)
        );
    }

    protected function delete_item($request) {
        global $wpdb;
        $data     = $request->get_json_params();
        $sevk_id  = intval($data['sevk_id'] ?? 0);
        $kalem_id = intval($data['kalem_id'] ?? 0);

        $sevk = $this->get_shipment($sevk_id);
        if (!$sevk || $sevk->durum !== 'taslak' || !$this->user_can_source($sevk, true)) {
            return Hizli_Kasa_API_Response::error('Kalem silme yetkiniz yok.', 403);
        }

        $tables = $this->get_tables();
        $wpdb->delete($tables['sevk_kalemleri'], ['id' => $kalem_id, 'sevk_id' => $sevk->id]);
        $this->refresh_totals($sevk->id);

        return Hizli_Kasa_API_Response::success(
            $this->format_mutation_response($this->get_shipment($sevk->id))
        );
    }

    protected function update_item_qty($request) {
        global $wpdb;
        $data     = $request->get_json_params();
        $sevk_id  = intval($data['sevk_id'] ?? 0);
        $kalem_id = intval($data['kalem_id'] ?? 0);
        $qty      = max(0.0, (float) ($data['qty'] ?? 0));

        $sevk = $this->get_shipment($sevk_id);
        if (!$sevk || $sevk->durum !== 'taslak') {
            return Hizli_Kasa_API_Response::error('Sadece taslak sevke ürün miktarı güncellenebilir.', 400);
        }
        if (!$this->user_can_source($sevk, true)) {
            return Hizli_Kasa_API_Response::error('Bu sevki düzenleme yetkiniz yok.', 403);
        }

        $tables = $this->get_tables();
        $kalem  = null;

        if ($qty <= 0) {
            $wpdb->delete($tables['sevk_kalemleri'], ['id' => $kalem_id, 'sevk_id' => $sevk_id]);
        } else {
            $wpdb->update($tables['sevk_kalemleri'], [
                'gonderilen_adet' => $qty,
                'updated_at'      => current_time('mysql'),
            ], ['id' => $kalem_id, 'sevk_id' => $sevk_id]);
            $kalem = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$tables['sevk_kalemleri']} WHERE id = %d",
                $kalem_id
            ));
        }

        $this->refresh_totals($sevk_id);

        return Hizli_Kasa_API_Response::success(
            $this->format_mutation_response($this->get_shipment($sevk_id), $kalem)
        );
    }

    protected function update_receipt_qty($request) {
        global $wpdb;
        $data     = $request->get_json_params();
        $sevk_id  = intval($data['sevk_id'] ?? 0);
        $kalem_id = intval($data['kalem_id'] ?? 0);
        $qty      = max(0.0, (float) ($data['qty'] ?? 0));

        $sevk = $this->get_shipment($sevk_id);
        if (!$sevk || !in_array($sevk->durum, ['gonderildi', 'teslim_kontrol', 'uyusmazlik'], true) || !$this->user_can_target($sevk, true)) {
            return Hizli_Kasa_API_Response::error('Teslim miktarı güncellenemez.', 400);
        }

        $tables = $this->get_tables();
        $wpdb->update($tables['sevk_kalemleri'], [
            'teslim_alinan_adet' => $qty,
            'updated_at'         => current_time('mysql'),
        ], ['id' => $kalem_id, 'sevk_id' => $sevk->id]);

        $status = $this->has_mismatch($sevk->id) ? 'uyusmazlik' : 'teslim_kontrol';
        $wpdb->update($tables['sevkler'], ['durum' => $status, 'updated_at' => current_time('mysql')], ['id' => $sevk->id]);

        $updated_sevk = $this->get_shipment($sevk->id);
        $kalem        = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$tables['sevk_kalemleri']} WHERE id = %d",
            $kalem_id
        ));

        return Hizli_Kasa_API_Response::success(
            $this->format_mutation_response($updated_sevk, $kalem)
        );
    }

    protected function submit_for_approval($request) {
        global $wpdb;
        $data    = $request->get_json_params();
        $sevk_id = intval($data['sevk_id'] ?? 0);

        $sevk = $this->get_shipment($sevk_id);
        if (!$sevk || $sevk->durum !== 'taslak' || !$this->user_can_source($sevk, true)) {
            return Hizli_Kasa_API_Response::error('Sevk onaya gönderilemez.', 400);
        }
        if ((float) $sevk->toplam_adet <= 0) {
            return Hizli_Kasa_API_Response::error('En az bir ürün eklenmelidir.', 400);
        }

        $tables = $this->get_tables();
        $wpdb->update($tables['sevkler'], [
            'durum'         => 'onay_bekliyor',
            'not_gonderici' => sanitize_textarea_field($data['not_gonderici'] ?? ''),
            'updated_at'    => current_time('mysql'),
        ], ['id' => $sevk->id]);

        return Hizli_Kasa_API_Response::success(
            $this->format_mutation_response($this->get_shipment($sevk->id))
        );
    }

    protected function receiver_accept($request) {
        global $wpdb;
        $data    = $request->get_json_params();
        $sevk_id = intval($data['sevk_id'] ?? 0);

        $sevk = $this->get_shipment($sevk_id);
        if (!$sevk || $sevk->durum !== 'onay_bekliyor' || !$this->user_can_target($sevk, true)) {
            return Hizli_Kasa_API_Response::error('Sevk onaylanamaz.', 400);
        }

        $tables = $this->get_tables();
        $wpdb->update($tables['sevkler'], [
            'durum'             => 'onaylandi',
            'onaylayan_user_id' => $this->get_current_user(),
            'not_alici'         => sanitize_textarea_field($data['not_alici'] ?? ''),
            'updated_at'        => current_time('mysql'),
        ], ['id' => $sevk->id]);

        return Hizli_Kasa_API_Response::success(
            $this->format_mutation_response($this->get_shipment($sevk->id))
        );
    }

    protected function receiver_reject($request) {
        global $wpdb;
        $data    = $request->get_json_params();
        $sevk_id = intval($data['sevk_id'] ?? 0);

        $sevk = $this->get_shipment($sevk_id);
        if (!$sevk || $sevk->durum !== 'onay_bekliyor' || !$this->user_can_target($sevk, true)) {
            return Hizli_Kasa_API_Response::error('Sevk reddedilemez.', 400);
        }

        $tables = $this->get_tables();
        $wpdb->update($tables['sevkler'], [
            'durum'             => 'reddedildi',
            'onaylayan_user_id' => $this->get_current_user(),
            'not_alici'         => sanitize_textarea_field($data['not_alici'] ?? ''),
            'updated_at'        => current_time('mysql'),
        ], ['id' => $sevk->id]);

        return Hizli_Kasa_API_Response::success(
            $this->format_mutation_response($this->get_shipment($sevk->id))
        );
    }

    protected function dispatch($request) {
        global $wpdb;
        $data    = $request->get_json_params();
        $sevk_id = intval($data['sevk_id'] ?? 0);

        $sevk = $this->get_shipment($sevk_id);
        if (!$sevk || $sevk->durum !== 'onaylandi' || !$this->user_can_source($sevk, true)) {
            return Hizli_Kasa_API_Response::error('Sevk yola çıkarılamaz.', 400);
        }

        $tables = $this->get_tables();
        $items  = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$tables['sevk_kalemleri']} WHERE sevk_id = %d",
            $sevk->id
        ));

        foreach ($items as $item) {
            Hizli_Kasa_Stock_Manager::transfer_out(
                $item->product_id, $item->variation_id,
                $sevk->kaynak_depo_id, $item->gonderilen_adet, $sevk->id
            );
        }

        $wpdb->update($tables['sevkler'], [
            'durum'      => 'gonderildi',
            'updated_at' => current_time('mysql'),
        ], ['id' => $sevk->id]);

        return Hizli_Kasa_API_Response::success(
            $this->format_mutation_response($this->get_shipment($sevk->id))
        );
    }

    protected function scan_delivery_barcode($request) {
        global $wpdb;
        $data    = $request->get_json_params();
        $sevk_id = intval($data['sevk_id'] ?? 0);
        $sku     = sanitize_text_field($data['sku'] ?? '');

        $sevk = $this->get_shipment($sevk_id);
        if (!$sevk || !in_array($sevk->durum, ['gonderildi', 'teslim_kontrol', 'uyusmazlik'], true) || !$this->user_can_target($sevk, true)) {
            return Hizli_Kasa_API_Response::error('Teslim barkodu işlenemez.', 400);
        }

        $tables = $this->get_tables();
        $item   = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM {$tables['sevk_kalemleri']}
            WHERE sevk_id = %d AND sku = %s
        ", $sevk->id, $sku));

        if (!$item) {
            return Hizli_Kasa_API_Response::error('Bu barkod sevk listesinde yok.', 404);
        }

        $new_qty = ($item->teslim_alinan_adet === null ? 0 : (float) $item->teslim_alinan_adet) + (float) ($data['qty'] ?? 1);
        $wpdb->update($tables['sevk_kalemleri'], [
            'teslim_alinan_adet' => $new_qty,
            'updated_at'         => current_time('mysql'),
        ], ['id' => $item->id]);

        $status = $this->has_mismatch($sevk->id) ? 'uyusmazlik' : 'teslim_kontrol';
        $wpdb->update($tables['sevkler'], [
            'durum'      => $status,
            'updated_at' => current_time('mysql'),
        ], ['id' => $sevk->id]);

        $updated_sevk = $this->get_shipment($sevk->id);
        $kalem        = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$tables['sevk_kalemleri']} WHERE id = %d",
            $item->id
        ));

        return Hizli_Kasa_API_Response::success(
            $this->format_mutation_response($updated_sevk, $kalem)
        );
    }

    protected function approve_delivery($request) {
        global $wpdb;
        $data    = $request->get_json_params();
        $sevk_id = intval($data['sevk_id'] ?? 0);
        $force   = !empty($data['force']);

        $sevk = $this->get_shipment($sevk_id);
        if (!$sevk || !in_array($sevk->durum, ['teslim_kontrol', 'uyusmazlik', 'gonderildi'], true) || !$this->user_can_target($sevk, true)) {
            return Hizli_Kasa_API_Response::error('Teslim onaylanamaz.', 400);
        }
        if ($this->has_mismatch($sevk->id) && !$force) {
            return Hizli_Kasa_API_Response::error('Teslim listesinde uyuşmazlık var.', 409);
        }

        $tables = $this->get_tables();
        $items  = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$tables['sevk_kalemleri']} WHERE sevk_id = %d",
            $sevk->id
        ));

        foreach ($items as $item) {
            $qty = $item->teslim_alinan_adet === null ? (float) $item->gonderilen_adet : (float) $item->teslim_alinan_adet;
            if ($qty > 0) {
                Hizli_Kasa_Stock_Manager::transfer_in(
                    $item->product_id, $item->variation_id,
                    $sevk->hedef_depo_id, $qty, $sevk->id
                );
            }
        }

        $wpdb->update($tables['sevkler'], [
            'durum'      => 'tamamlandi',
            'not_alici'  => sanitize_textarea_field($data['not_alici'] ?? $sevk->not_alici),
            'updated_at' => current_time('mysql'),
        ], ['id' => $sevk->id]);

        return Hizli_Kasa_API_Response::success(
            $this->format_mutation_response($this->get_shipment($sevk->id))
        );
    }

    protected function get_shipments($request) {
        global $wpdb;
        $tables   = $this->get_tables();
        $view_ids = current_user_can('manage_options')
            ? $wpdb->get_col("SELECT id FROM {$tables['depolar']}")
            : hizli_kasa_get_user_view_depos($this->get_current_user());

        if (empty($view_ids)) {
            return Hizli_Kasa_API_Response::success([
                'items' => [],
                'stats' => ['total' => 0, 'yolda' => 0, 'bekleyen' => 0, 'tamamlanan' => 0],
            ]);
        }

        $ids_ph = implode(',', array_map('intval', $view_ids));
        $where  = "(s.kaynak_depo_id IN ($ids_ph) OR s.hedef_depo_id IN ($ids_ph))";
        $scope  = sanitize_text_field($request->get_param('scope') ?: '');

        if ($scope === 'incoming') {
            $where = "s.hedef_depo_id IN ($ids_ph)";
        }
        if ($scope === 'outgoing') {
            $where = "s.kaynak_depo_id IN ($ids_ph)";
        }

        $durum = sanitize_text_field($request->get_param('durum') ?: '');
        if ($durum && $durum !== 'all') {
            $where .= $wpdb->prepare(' AND s.durum = %s', $durum);
        }

        $date_start = sanitize_text_field($request->get_param('date_start') ?: '');
        $date_end   = sanitize_text_field($request->get_param('date_end') ?: '');

        if ($date_start) {
            $where .= $wpdb->prepare(' AND DATE(s.created_at) >= %s', $date_start);
        }
        if ($date_end) {
            $where .= $wpdb->prepare(' AND DATE(s.created_at) <= %s', $date_end);
        }

        $rows = $wpdb->get_results("
            SELECT s.*, kd.name as kaynak_depo_adi, hd.name as hedef_depo_adi
            FROM {$tables['sevkler']} s
            LEFT JOIN {$tables['depolar']} kd ON kd.id = s.kaynak_depo_id
            LEFT JOIN {$tables['depolar']} hd ON hd.id = s.hedef_depo_id
            WHERE $where
            ORDER BY s.updated_at DESC LIMIT 100
        ");

        $stats_rows = $wpdb->get_results("
            SELECT s.durum, COUNT(*) as cnt
            FROM {$tables['sevkler']} s
            WHERE (s.kaynak_depo_id IN ($ids_ph) OR s.hedef_depo_id IN ($ids_ph))
            GROUP BY s.durum
        ");

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

        return Hizli_Kasa_API_Response::success([
            'items' => array_map(fn($row) => $this->format_shipment($row, false), $rows),
            'stats' => $stats,
        ]);
    }

    protected function get_single_shipment($request) {
        $sevk = $this->get_shipment(intval($request->get_param('id')));
        if (!$sevk || (!$this->user_can_source($sevk, false) && !$this->user_can_target($sevk, false))) {
            return Hizli_Kasa_API_Response::error('Sevk bulunamadı.', 404);
        }

        return Hizli_Kasa_API_Response::success([
            'sevk' => $this->format_shipment($sevk, true),
        ]);
    }

    protected function get_pending_count($request) {
        global $wpdb;
        $tables     = $this->get_tables();
        $manage_ids = current_user_can('manage_options')
            ? $wpdb->get_col("SELECT id FROM {$tables['depolar']}")
            : hizli_kasa_get_user_manage_depos($this->get_current_user());

        if (empty($manage_ids)) {
            return Hizli_Kasa_API_Response::success(['count' => 0]);
        }

        $ids_ph = implode(',', array_map('intval', $manage_ids));
        $count  = $wpdb->get_var("
            SELECT COUNT(*) FROM {$tables['sevkler']}
            WHERE hedef_depo_id IN ($ids_ph)
            AND durum IN ('onay_bekliyor', 'gonderildi', 'teslim_kontrol', 'uyusmazlik')
        ");

        return Hizli_Kasa_API_Response::success(['count' => (int) $count]);
    }

    protected function get_active_draft($request) {
        global $wpdb;
        $kaynak = intval($request->get_param('kaynak_depo_id'));
        if (!$kaynak) {
            return Hizli_Kasa_API_Response::error('Kaynak depo ID belirtilmelidir.', 400);
        }

        $tables = $this->get_tables();
        $row    = $wpdb->get_row($wpdb->prepare("
            SELECT s.*, kd.name as kaynak_depo_adi, hd.name as hedef_depo_adi
            FROM {$tables['sevkler']} s
            LEFT JOIN {$tables['depolar']} kd ON kd.id = s.kaynak_depo_id
            LEFT JOIN {$tables['depolar']} hd ON hd.id = s.hedef_depo_id
            WHERE s.kaynak_depo_id = %d AND s.durum = 'taslak'
            LIMIT 1
        ", $kaynak));

        if (!$row) {
            return Hizli_Kasa_API_Response::success(null);
        }

        return Hizli_Kasa_API_Response::success([
            'sevk' => $this->format_shipment($row, true),
        ]);
    }

    protected function delete_draft($request) {
        global $wpdb;
        $data    = $request->get_json_params();
        $sevk_id = intval($data['sevk_id'] ?? 0);

        $sevk = $this->get_shipment($sevk_id);
        if (!$sevk) {
            return Hizli_Kasa_API_Response::error('Sevk bulunamadı.', 404);
        }
        if ($sevk->durum !== 'taslak') {
            return Hizli_Kasa_API_Response::error('Sadece taslak durumundaki sevkler silinebilir.', 400);
        }
        if (!$this->user_can_source($sevk, true)) {
            return Hizli_Kasa_API_Response::error('Bu taslağı silme yetkiniz bulunmuyor.', 403);
        }

        $tables = $this->get_tables();
        $wpdb->delete($tables['sevk_kalemleri'], ['sevk_id' => $sevk->id]);
        $wpdb->delete($tables['sevkler'], ['id' => $sevk->id]);

        return Hizli_Kasa_API_Response::success([
            'message' => 'Taslak sevk başarıyla silindi.',
        ]);
    }

    protected function get_tables(): array {
        if ($this->tables === null) {
            $this->tables = Hizli_Kasa_Database::get_tables();
        }
        return $this->tables;
    }

    protected function get_current_user(): int {
        return $this->current_user_id ??= get_current_user_id();
    }

    protected function status_label(string $durum): string {
        $labels = [
            'taslak'         => 'Taslak',
            'onay_bekliyor'  => 'Onay Bekliyor',
            'onaylandi'      => 'Onaylandı',
            'reddedildi'     => 'Reddedildi',
            'gonderildi'     => 'Gönderildi',
            'teslim_kontrol' => 'Teslim Kontrol',
            'tamamlandi'     => 'Tamamlandı',
            'uyusmazlik'     => 'Uyuşmazlık',
        ];
        return $labels[$durum] ?? $durum;
    }

    protected function generate_no(): string {
        global $wpdb;
        $tables = $this->get_tables();
        $prefix = 'SVK-' . current_time('Ymd') . '-';
        $last   = $wpdb->get_var($wpdb->prepare(
            "SELECT sevk_no FROM {$tables['sevkler']} WHERE sevk_no LIKE %s ORDER BY id DESC LIMIT 1",
            $prefix . '%'
        ));
        $next = ($last && preg_match('/(\d+)$/', $last, $m)) ? intval($m[1]) + 1 : 1;
        return $prefix . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    protected function refresh_totals(int $sevk_id): void {
        global $wpdb;
        $tables  = $this->get_tables();
        $summary = $wpdb->get_row($wpdb->prepare(
            "SELECT COUNT(*) as cesit, COALESCE(SUM(gonderilen_adet), 0) as adet FROM {$tables['sevk_kalemleri']} WHERE sevk_id = %d",
            $sevk_id
        ));
        $wpdb->update($tables['sevkler'], [
            'toplam_cesit' => (int) ($summary->cesit ?? 0),
            'toplam_adet'  => (float) ($summary->adet ?? 0),
            'updated_at'   => current_time('mysql'),
        ], ['id' => $sevk_id]);
    }

    protected function get_shipment(int $sevk_id): ?object {
        global $wpdb;
        $tables = $this->get_tables();
        return $wpdb->get_row($wpdb->prepare(
            "SELECT s.*, kd.name as kaynak_depo_adi, hd.name as hedef_depo_adi
             FROM {$tables['sevkler']} s
             LEFT JOIN {$tables['depolar']} kd ON kd.id = s.kaynak_depo_id
             LEFT JOIN {$tables['depolar']} hd ON hd.id = s.hedef_depo_id
             WHERE s.id = %d",
            $sevk_id
        ));
    }

    protected function user_can_source(object $sevk, bool $manage = true): bool {
        $uid = $this->get_current_user();
        return $manage
            ? hizli_kasa_can_user_manage_depo($uid, (int) $sevk->kaynak_depo_id)
            : hizli_kasa_can_user_view_depo($uid, (int) $sevk->kaynak_depo_id);
    }

    protected function user_can_target(object $sevk, bool $manage = true): bool {
        $uid = $this->get_current_user();
        return $manage
            ? hizli_kasa_can_user_manage_depo($uid, (int) $sevk->hedef_depo_id)
            : hizli_kasa_can_user_view_depo($uid, (int) $sevk->hedef_depo_id);
    }

    protected function format_shipment(object $sevk, bool $with_items = false): array {
        global $wpdb;
        $tables = $this->get_tables();

        $row = [
            'id'              => (int) $sevk->id,
            'sevk_no'         => $sevk->sevk_no,
            'kaynak_depo_id'  => (int) $sevk->kaynak_depo_id,
            'kaynak_depo_adi' => $sevk->kaynak_depo_adi ?: '',
            'hedef_depo_id'   => (int) $sevk->hedef_depo_id,
            'hedef_depo_adi'  => $sevk->hedef_depo_adi ?: '',
            'durum'           => $sevk->durum,
            'durum_label'     => $this->status_label($sevk->durum),
            'toplam_cesit'    => (int) $sevk->toplam_cesit,
            'toplam_adet'     => (float) $sevk->toplam_adet,
            'not_gonderici'   => $sevk->not_gonderici ?: '',
            'not_alici'       => $sevk->not_alici ?: '',
            'created_at'      => $sevk->created_at,
            'updated_at'      => $sevk->updated_at,
        ];

        if ($with_items) {
            $items = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$tables['sevk_kalemleri']} WHERE sevk_id = %d ORDER BY COALESCE(updated_at, created_at) DESC, id DESC",
                $sevk->id
            ));

            $pids      = array_unique(array_map(fn($i) => (int) ($i->variation_id ?: $i->product_id), $items));
            $image_map = $this->get_product_image_map($pids);

            $row['kalemler'] = array_map(function ($item) use ($image_map) {
                $pid = (int) ($item->variation_id ?: $item->product_id);
                return [
                    'id'                 => (int) $item->id,
                    'product_id'         => (int) $item->product_id,
                    'variation_id'       => (int) $item->variation_id,
                    'sku'                => $item->sku,
                    'urun_adi'           => $item->urun_adi,
                    'gonderilen_adet'    => (float) $item->gonderilen_adet,
                    'teslim_alinan_adet' => $item->teslim_alinan_adet === null ? null : (float) $item->teslim_alinan_adet,
                    'image'              => $image_map[$pid] ?? '',
                ];
            }, $items);
        }

        return $row;
    }

    protected function format_mutation_response(object $sevk, ?object $kalem = null): array {
        $result = [
            'sevk_id'      => (int) $sevk->id,
            'toplam_cesit' => (int) $sevk->toplam_cesit,
            'toplam_adet'  => (float) $sevk->toplam_adet,
            'durum'        => $sevk->durum,
            'durum_label'  => $this->status_label($sevk->durum),
            'updated_at'   => $sevk->updated_at,
        ];

        if ($kalem) {
            $pid             = (int) ($kalem->variation_id ?: $kalem->product_id);
            $image_map       = $this->get_product_image_map([$pid]);
            $result['kalem'] = [
                'id'                 => (int) $kalem->id,
                'product_id'         => (int) $kalem->product_id,
                'variation_id'       => (int) $kalem->variation_id,
                'sku'                => $kalem->sku,
                'urun_adi'           => $kalem->urun_adi,
                'gonderilen_adet'    => (float) $kalem->gonderilen_adet,
                'teslim_alinan_adet' => $kalem->teslim_alinan_adet === null ? null : (float) $kalem->teslim_alinan_adet,
                'image'              => $image_map[$pid] ?? '',
            ];
        }

        return $result;
    }

    protected function find_product_by_sku(string $sku): array|false {
        $post_id = wc_get_product_id_by_sku($sku);
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

    protected function has_mismatch(int $sevk_id): bool {
        global $wpdb;
        $tables = $this->get_tables();
        return (int) $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$tables['sevk_kalemleri']}
            WHERE sevk_id = %d AND ABS(gonderilen_adet - COALESCE(teslim_alinan_adet, 0)) > 0.0001
        ", $sevk_id)) > 0;
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
