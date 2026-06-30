<?php
/**
 * Hızlı Kasa V2 API Purchase Orders Controller
 *
 * @package HizliKasa
 */

if (!defined('ABSPATH')) {
    exit;
}

class Hizli_Kasa_API_Purchase_Orders extends Hizli_Kasa_API_Controller_Base {
    /**
     * Register endpoints.
     */
    public function register_routes() {
        // Get all or single PO
        register_rest_route($this->namespace, '/purchase-orders', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'get_purchase_orders_callback'],
                'permission_callback' => [$this, 'check_permission'],
            ],
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'create_purchase_order_callback'],
                'permission_callback' => [$this, 'check_permission'],
                'args'                => [
                    'supplier_id' => [
                        'required'          => true,
                        'sanitize_callback' => 'absint',
                    ],
                    'items' => [
                        'required' => true,
                        'type'     => 'array',
                    ]
                ],
            ],
        ]);

        register_rest_route($this->namespace, '/purchase-orders/(?P<id>\d+)', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'get_single_purchase_order_callback'],
                'permission_callback' => [$this, 'check_permission'],
            ]
        ]);

        register_rest_route($this->namespace, '/purchase-orders/(?P<id>\d+)/receive', [
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'receive_purchase_order_callback'],
                'permission_callback' => [$this, 'check_permission'],
                'args'                => [
                    'items' => [
                        'required' => true,
                        'type'     => 'array',
                    ],
                    'depo_id' => [
                        'required'          => true,
                        'sanitize_callback' => 'absint',
                    ]
                ],
            ],
        ]);
    }

    public function get_purchase_orders_callback($request) {
        return $this->handle_request([$this, 'get_purchase_orders'], $request);
    }

    protected function get_purchase_orders($request) {
        global $wpdb;
        $tables = Hizli_Kasa_Database::get_tables();
        
        $sql = "SELECT po.*, s.name as supplier_name 
                FROM {$tables['purchase_orders']} po 
                LEFT JOIN {$tables['suppliers']} s ON po.supplier_id = s.id 
                ORDER BY po.created_at DESC";
        
        $orders = $wpdb->get_results($sql, ARRAY_A);
        return Hizli_Kasa_API_Response::success(['purchase_orders' => $orders]);
    }

    public function get_single_purchase_order_callback($request) {
        return $this->handle_request([$this, 'get_single_purchase_order'], $request);
    }

    protected function get_single_purchase_order($request) {
        global $wpdb;
        $po_id = absint($request->get_param('id'));
        $tables = Hizli_Kasa_Database::get_tables();

        $po = $wpdb->get_row($wpdb->prepare("SELECT po.*, s.name as supplier_name FROM {$tables['purchase_orders']} po LEFT JOIN {$tables['suppliers']} s ON po.supplier_id = s.id WHERE po.id = %d", $po_id), ARRAY_A);

        if (!$po) {
            return Hizli_Kasa_API_Response::error('Alım siparişi bulunamadı.', 404);
        }

        $items = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$tables['purchase_order_items']} WHERE purchase_order_id = %d", $po_id), ARRAY_A);
        
        // Ürün detaylarını çek
        foreach($items as &$item) {
            if ($item['product_id'] == 0 && !empty($item['custom_product_name'])) {
                $item['product_name'] = $item['custom_product_name'];
                $item['sku'] = 'BAĞIMSIZ';
                $item['is_custom'] = true;
            } else {
                $product = wc_get_product($item['variation_id'] > 0 ? $item['variation_id'] : $item['product_id']);
                if ($product) {
                    $item['product_name'] = $product->get_name();
                    $item['sku'] = $product->get_sku();
                } else {
                    $item['product_name'] = 'Bilinmeyen Ürün';
                    $item['sku'] = '';
                }
                $item['is_custom'] = false;
            }
        }

        $po['items'] = $items;
        return Hizli_Kasa_API_Response::success(['purchase_order' => $po]);
    }

    public function create_purchase_order_callback($request) {
        return $this->handle_request([$this, 'create_purchase_order'], $request);
    }

    protected function create_purchase_order($request) {
        global $wpdb;
        $tables = Hizli_Kasa_Database::get_tables();
        
        $supplier_id = $request->get_param('supplier_id');
        $items = $request->get_param('items');
        $reference_no = sanitize_text_field($request->get_param('reference_no') ?: '');
        $expected_date = sanitize_text_field($request->get_param('expected_date') ?: '');
        $notes = sanitize_textarea_field($request->get_param('notes') ?: '');

        if (empty($items)) {
            return Hizli_Kasa_API_Response::error('Siparişte en az bir ürün olmalıdır.', 400);
        }

        $wpdb->query("START TRANSACTION");

        $po_data = [
            'supplier_id'   => $supplier_id,
            'reference_no'  => $reference_no,
            'status'        => 'pending',
            'order_date'    => current_time('Y-m-d'),
            'expected_date' => $expected_date ?: null,
            'created_by'    => get_current_user_id(),
            'notes'         => $notes,
            'created_at'    => current_time('mysql'),
            'updated_at'    => current_time('mysql'),
        ];

        $inserted = $wpdb->insert($tables['purchase_orders'], $po_data);
        if (!$inserted) {
            $wpdb->query("ROLLBACK");
            return Hizli_Kasa_API_Response::error('Alım siparişi oluşturulamadı.', 500);
        }

        $po_id = $wpdb->insert_id;

        foreach ($items as $item) {
            $product_id = absint($item['product_id']);
            $variation_id = isset($item['variation_id']) ? absint($item['variation_id']) : 0;
            $expected_qty = floatval($item['expected_qty']);
            $unit_cost = isset($item['unit_cost']) ? floatval($item['unit_cost']) : 0;
            $custom_name = isset($item['custom_product_name']) ? sanitize_text_field($item['custom_product_name']) : '';

            if ($expected_qty <= 0) {
                continue;
            }
            if ($product_id <= 0 && empty($custom_name)) {
                continue;
            }

            $item_data = [
                'purchase_order_id'   => $po_id,
                'product_id'          => $product_id,
                'variation_id'        => $variation_id,
                'custom_product_name' => $product_id <= 0 ? $custom_name : null,
                'expected_qty'        => $expected_qty,
                'received_qty'        => 0,
                'unit_cost'           => $unit_cost
            ];

            if (!$wpdb->insert($tables['purchase_order_items'], $item_data)) {
                $wpdb->query("ROLLBACK");
                return Hizli_Kasa_API_Response::error('Sipariş ürünleri eklenirken hata oluştu.', 500);
            }
        }

        $wpdb->query("COMMIT");

        return Hizli_Kasa_API_Response::success([
            'purchase_order_id' => $po_id,
            'message' => 'Alım siparişi başarıyla oluşturuldu.'
        ]);
    }

    public function receive_purchase_order_callback($request) {
        return $this->handle_request([$this, 'receive_purchase_order'], $request);
    }

    protected function receive_purchase_order($request) {
        global $wpdb;
        $tables = Hizli_Kasa_Database::get_tables();
        
        $po_id = absint($request->get_param('id'));
        $received_items = $request->get_param('items'); // array of { id: item_id, received_qty: num }
        $depo_id = absint($request->get_param('depo_id'));

        $po = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$tables['purchase_orders']} WHERE id = %d", $po_id));
        if (!$po) {
            return Hizli_Kasa_API_Response::error('Sipariş bulunamadı.', 404);
        }
        if ($po->status === 'completed') {
            return Hizli_Kasa_API_Response::error('Bu sipariş zaten tamamlanmış.', 400);
        }

        $wpdb->query("START TRANSACTION");

        $all_completed = true;
        $any_received = false;

        foreach ($received_items as $r_item) {
            $item_id = absint($r_item['id']);
            $new_received_qty = floatval($r_item['received_qty']); // Gelen ek miktar

            if ($new_received_qty <= 0) {
                continue;
            }

            $db_item = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$tables['purchase_order_items']} WHERE id = %d AND purchase_order_id = %d", $item_id, $po_id));
            if (!$db_item) {
                continue;
            }

            $total_received = $db_item->received_qty + $new_received_qty;
            if ($total_received > $db_item->expected_qty) {
                 $total_received = $db_item->expected_qty; // Fazla girilmesini engelle (veya izin ver, iş kuralına bağlı. Şimdilik beklenen kadar diyelim veya serbest bırakalım. Serbest bırakmak daha iyi olabilir gerçeği yansıtması için. Şimdilik serbest bırakalım.)
            }
            
            $total_received = floatval($db_item->received_qty) + $new_received_qty;

            // Stok artırma (Hızlı Kasa Stock Manager kullanarak)
            if ($db_item->product_id > 0 && class_exists('Hizli_Kasa_Stock_Manager')) {
                // Stock Manager'da genelde set_stock_quantity var ama biz üzerine ekleyeceğiz
                // WooCommerce ürün objesini alıp güncelliyoruz, Hızlı Kasa hookları geri kalanı halleder.
                $product_obj_id = $db_item->variation_id > 0 ? $db_item->variation_id : $db_item->product_id;
                $product = wc_get_product($product_obj_id);
                
                if ($product && $product->managing_stock()) {
                    $current_stock = $product->get_stock_quantity();
                    $new_stock = $current_stock + $new_received_qty;
                    wc_update_product_stock($product, $new_stock);

                    // Stok Hareketi Logla
                    Hizli_Kasa_Stock_Manager::log_movement(
                        $db_item->product_id,
                        $db_item->variation_id,
                        $depo_id,
                        get_current_user_id(),
                        $current_stock,
                        $new_stock
                    );
                }
            }

            // Item güncelle
            $wpdb->update(
                $tables['purchase_order_items'],
                ['received_qty' => $total_received],
                ['id' => $item_id]
            );

            $any_received = true;
            if ($total_received < $db_item->expected_qty) {
                $all_completed = false;
            }
        }

        // Kalan itemler var mı kontrolü
        $all_items = $wpdb->get_results($wpdb->prepare("SELECT expected_qty, received_qty FROM {$tables['purchase_order_items']} WHERE purchase_order_id = %d", $po_id));
        foreach($all_items as $i) {
            if ($i->received_qty < $i->expected_qty) {
                $all_completed = false;
            }
        }

        $new_status = $po->status;
        if ($all_completed) {
            $new_status = 'completed';
        } elseif ($any_received || $po->status === 'partial') {
            $new_status = 'partial';
        }

        $wpdb->update(
            $tables['purchase_orders'],
            [
                'status' => $new_status,
                'received_date' => $new_status === 'completed' ? current_time('mysql') : $po->received_date,
                'updated_at' => current_time('mysql')
            ],
            ['id' => $po_id]
        );

        $wpdb->query("COMMIT");

        return Hizli_Kasa_API_Response::success([
            'status' => $new_status,
            'message' => 'Ürünler başarıyla teslim alındı ve stoklar güncellendi.'
        ]);
    }
}
