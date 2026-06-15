<?php
/**
 * Hızlı Kasa V2 API Suppliers Controller
 *
 * @package HizliKasa
 */

if (!defined('ABSPATH')) {
    exit;
}

class Hizli_Kasa_API_Suppliers extends Hizli_Kasa_API_Controller_Base {
    /**
     * Register endpoints.
     */
    public function register_routes() {
        register_rest_route($this->namespace, '/suppliers', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'get_suppliers_callback'],
                'permission_callback' => [$this, 'check_permission'],
            ],
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'create_supplier_callback'],
                'permission_callback' => [$this, 'check_permission'],
                'args'                => [
                    'name' => [
                        'required'          => true,
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                    'contact_info' => [
                        'required'          => false,
                        'sanitize_callback' => 'sanitize_textarea_field',
                        'default'           => '',
                    ],
                    'phone' => [
                        'required'          => false,
                        'sanitize_callback' => 'sanitize_text_field',
                        'default'           => '',
                    ],
                    'email' => [
                        'required'          => false,
                        'sanitize_callback' => 'sanitize_email',
                        'default'           => '',
                    ],
                    'tax_id' => [
                        'required'          => false,
                        'sanitize_callback' => 'sanitize_text_field',
                        'default'           => '',
                    ],
                    'address' => [
                        'required'          => false,
                        'sanitize_callback' => 'sanitize_textarea_field',
                        'default'           => '',
                    ],
                ],
            ],
        ]);
    }

    /**
     * GET /suppliers
     */
    public function get_suppliers_callback($request) {
        return $this->handle_request([$this, 'get_suppliers'], $request);
    }

    protected function get_suppliers($request) {
        global $wpdb;
        $tables = Hizli_Kasa_Database::get_tables();
        $table = $tables['suppliers'];

        $suppliers = $wpdb->get_results("SELECT * FROM {$table} ORDER BY name ASC", ARRAY_A);

        return Hizli_Kasa_API_Response::success([
            'suppliers' => $suppliers
        ]);
    }

    /**
     * POST /suppliers
     */
    public function create_supplier_callback($request) {
        return $this->handle_request([$this, 'create_supplier'], $request);
    }

    protected function create_supplier($request) {
        global $wpdb;
        $tables = Hizli_Kasa_Database::get_tables();
        $table = $tables['suppliers'];

        $name = $request->get_param('name');
        if (empty($name)) {
            return Hizli_Kasa_API_Response::error('Tedarikçi adı boş olamaz.', 400);
        }

        $data = [
            'name'         => $name,
            'contact_info' => $request->get_param('contact_info'),
            'phone'        => $request->get_param('phone'),
            'email'        => $request->get_param('email'),
            'tax_id'       => $request->get_param('tax_id'),
            'address'      => $request->get_param('address'),
            'created_at'   => current_time('mysql'),
            'updated_at'   => current_time('mysql'),
        ];

        $inserted = $wpdb->insert($table, $data);

        if ($inserted === false) {
            return Hizli_Kasa_API_Response::error('Tedarikçi eklenirken veritabanı hatası oluştu.', 500);
        }

        $supplier_id = $wpdb->insert_id;
        $data['id'] = $supplier_id;

        return Hizli_Kasa_API_Response::success([
            'supplier' => $data,
            'message'  => 'Tedarikçi başarıyla eklendi.'
        ]);
    }
}
