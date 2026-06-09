<?php
/**
 * Hızlı Kasa V2 API Product Codes Controller
 *
 * @package HizliKasa
 */

if (!defined('ABSPATH')) {
    exit;
}

class Hizli_Kasa_API_Product_Codes extends Hizli_Kasa_API_Controller_Base {
    /**
     * Register endpoints.
     */
    public function register_routes() {
        register_rest_route($this->namespace, '/product/warehouse-code', [
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'update_warehouse_code_callback'],
                'permission_callback' => [$this, 'check_permission'],
                'args'                => [
                    'product_id' => [
                        'required'          => true,
                        'sanitize_callback' => 'absint',
                    ],
                    'variation_id' => [
                        'required'          => false,
                        'sanitize_callback' => 'absint',
                        'default'           => 0,
                    ],
                    'depo_id' => [
                        'required'          => true,
                        'sanitize_callback' => 'absint',
                    ],
                    'depo_kodu' => [
                        'required'          => false,
                        'sanitize_callback' => 'sanitize_text_field',
                        'default'           => '',
                    ],
                ],
            ],
        ]);
    }

    /**
     * Callback for updating warehouse code.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function update_warehouse_code_callback($request) {
        return $this->handle_request([$this, 'update_warehouse_code'], $request);
    }

    /**
     * Inner logic for updating warehouse code.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    protected function update_warehouse_code($request) {
        global $wpdb;

        $product_id   = $request->get_param('product_id');
        $variation_id = $request->get_param('variation_id') ?: 0;
        $depo_id      = $request->get_param('depo_id');
        $depo_kodu    = strtoupper(trim($request->get_param('depo_kodu')));

        // Validations
        if (!$depo_id) {
            return Hizli_Kasa_API_Response::error('Depo ID belirtilmelidir.', 400);
        }

        $user_id = get_current_user_id();
        if (!function_exists('hizli_kasa_can_user_manage_depo') || !hizli_kasa_can_user_manage_depo($user_id, $depo_id)) {
            return Hizli_Kasa_API_Response::error('Bu depoda işlem yapma yetkiniz bulunmuyor.', 403);
        }

        if (!empty($depo_kodu)) {
            if (strlen($depo_kodu) > 6 || !preg_match('/^[A-Z0-9]+$/', $depo_kodu)) {
                return Hizli_Kasa_API_Response::error('Depo kodu en fazla 6 haneli olmalı, yalnızca harf ve rakamlardan oluşmalıdır.', 400);
            }
        } else {
            $depo_kodu = null; // Save as null if empty
        }

        $tables = Hizli_Kasa_Database::get_tables();
        $table_stok_konumlari = $tables['stok_konumlari'];

        $exists = $wpdb->get_var($wpdb->prepare("
            SELECT id FROM {$table_stok_konumlari}
            WHERE product_id = %d AND variation_id = %d AND location_id = %d
        ", $product_id, $variation_id, $depo_id));

        if ($exists) {
            $updated = $wpdb->update(
                $table_stok_konumlari,
                [
                    'depo_kodu'  => $depo_kodu,
                    'updated_at' => current_time('mysql'),
                ],
                ['id' => $exists]
            );
            $success = ($updated !== false);
        } else {
            $inserted = $wpdb->insert(
                $table_stok_konumlari,
                [
                    'product_id'   => $product_id,
                    'variation_id' => $variation_id,
                    'location_id'  => $depo_id,
                    'quantity'     => 0.0000,
                    'reserved'     => 0.0000,
                    'depo_kodu'    => $depo_kodu,
                    'updated_at'   => current_time('mysql'),
                ]
            );
            $success = ($inserted !== false);
        }

        if (!$success) {
            return Hizli_Kasa_API_Response::error('Depo kodu kaydedilirken veritabanı hatası oluştu: ' . $wpdb->last_error, 500);
        }

        return Hizli_Kasa_API_Response::success([
            'product_id'   => $product_id,
            'variation_id' => $variation_id,
            'depo_id'      => $depo_id,
            'depo_kodu'    => $depo_kodu,
            'message'      => 'Depo kodu başarıyla güncellendi.',
        ]);
    }
}
