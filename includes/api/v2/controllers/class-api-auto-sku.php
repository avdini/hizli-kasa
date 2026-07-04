<?php
if (!defined('ABSPATH')) {
    exit;
}

class Hizli_Kasa_API_Auto_Sku extends Hizli_Kasa_API_Controller_Base {
    public function register_routes() {
        register_rest_route($this->namespace, '/auto-sku/status', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_status_callback'],
            'permission_callback' => [$this, 'check_permission'],
        ]);

        register_rest_route($this->namespace, '/auto-sku/generate', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'generate_skus_callback'],
            'permission_callback' => [$this, 'check_permission'],
            'args'                => [
                'limit' => [
                    'required'          => false,
                    'type'              => 'integer',
                    'sanitize_callback' => 'absint',
                    'default'           => 50
                ]
            ]
        ]);
    }

    public function check_permission($request) {
        if (!current_user_can('manage_options')) {
            return new WP_Error(
                'rest_forbidden',
                __('Bu işlem için yönetici yetkiniz bulunmuyor.', 'hizli-kasa'),
                ['status' => 403]
            );
        }
        return true;
    }

    public function get_status_callback($request) {
        return $this->handle_request([$this, 'get_status'], $request);
    }

    public function generate_skus_callback($request) {
        return $this->handle_request([$this, 'generate_skus'], $request);
    }

    protected function get_status($request) {
        global $wpdb;

        $allowed_types = get_option('hizli_kasa_auto_sku_tipler', ['simple', 'product_variation']);
        if (empty($allowed_types)) {
            return Hizli_Kasa_API_Response::success([
                'total_empty_skus' => 0
            ]);
        }

        $db_types = [];
        if (in_array('simple', $allowed_types) || in_array('variable', $allowed_types)) {
            $db_types[] = 'product';
        }
        if (in_array('product_variation', $allowed_types)) {
            $db_types[] = 'product_variation';
        }

        if (empty($db_types)) {
            return Hizli_Kasa_API_Response::success([
                'total_empty_skus' => 0
            ]);
        }

        $db_types_in = implode("','", array_map('esc_sql', $db_types));

        $total = (int)$wpdb->get_var("
            SELECT COUNT(DISTINCT p.ID)
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_sku'
            WHERE p.post_type IN ('$db_types_in')
              AND p.post_status IN ('publish', 'private', 'draft')
              AND (pm.meta_value IS NULL OR pm.meta_value = '')
        ");

        return Hizli_Kasa_API_Response::success([
            'total_empty_skus' => $total
        ]);
    }

    protected function generate_skus($request) {
        global $wpdb;
        $limit = intval($request->get_param('limit'));
        if ($limit <= 0 || $limit > 200) {
            $limit = 50;
        }

        $allowed_types = get_option('hizli_kasa_auto_sku_tipler', ['simple', 'product_variation']);
        if (empty($allowed_types)) {
            return Hizli_Kasa_API_Response::success([
                'processed' => 0,
                'remaining' => 0
            ]);
        }

        $db_types = [];
        if (in_array('simple', $allowed_types) || in_array('variable', $allowed_types)) {
            $db_types[] = 'product';
        }
        if (in_array('product_variation', $allowed_types)) {
            $db_types[] = 'product_variation';
        }

        if (empty($db_types)) {
            return Hizli_Kasa_API_Response::success([
                'processed' => 0,
                'remaining' => 0
            ]);
        }

        $db_types_in = implode("','", array_map('esc_sql', $db_types));

        $missing_sku_ids = $wpdb->get_col($wpdb->prepare("
            SELECT DISTINCT p.ID
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_sku'
            WHERE p.post_type IN ('$db_types_in')
              AND p.post_status IN ('publish', 'private', 'draft')
              AND (pm.meta_value IS NULL OR pm.meta_value = '')
            LIMIT %d
        ", $limit));

        $processed = 0;
        if (!empty($missing_sku_ids)) {
            foreach ($missing_sku_ids as $id) {
                $p = wc_get_product((int)$id);
                if ($p) {
                    $type = $p->get_type();
                    $mapped_type = ($type === 'variation') ? 'product_variation' : $type;
                    if (in_array($mapped_type, $allowed_types)) {
                        $synced = Hizli_Kasa_Auto_Sku_Manager::sync_sku_by_id((int)$id);
                        if ($synced) {
                            $processed++;
                        }
                    }
                }
            }
        }

        $remaining = (int)$wpdb->get_var("
            SELECT COUNT(DISTINCT p.ID)
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_sku'
            WHERE p.post_type IN ('$db_types_in')
              AND p.post_status IN ('publish', 'private', 'draft')
              AND (pm.meta_value IS NULL OR pm.meta_value = '')
        ");

        return Hizli_Kasa_API_Response::success([
            'processed' => $processed,
            'remaining' => $remaining
        ]);
    }
}
