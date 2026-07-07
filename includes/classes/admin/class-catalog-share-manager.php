<?php
if (!defined('ABSPATH')) {
    exit;
}

class Hizli_Kasa_Catalog_Share_Manager {

    public static function create_share($product_ids, $options = []) {
        global $wpdb;
        $tables = Hizli_Kasa_Database::get_tables();

        $ttl_days = isset($options['ttl_days']) ? (int) $options['ttl_days'] : 7;
        $token    = wp_generate_password(32, false);

        $wpdb->insert(
            $tables['catalog_shares'],
            [
                'token'       => $token,
                'product_ids' => wp_json_encode(array_map('intval', $product_ids)),
                'options'     => wp_json_encode($options),
                'created_at'  => current_time('mysql'),
                'expires_at'  => date('Y-m-d H:i:s', strtotime("+{$ttl_days} days", current_time('timestamp'))),
            ],
            ['%s', '%s', '%s', '%s', '%s']
        );

        if (!$wpdb->insert_id) {
            return new WP_Error('db_error', __('Token oluşturulamadı.', 'hizli-kasa'));
        }

        return $token;
    }

    public static function get_share($token) {
        global $wpdb;
        $tables = Hizli_Kasa_Database::get_tables();
        $token  = sanitize_text_field($token);

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$tables['catalog_shares']} WHERE token = %s AND expires_at > %s",
                $token,
                current_time('mysql')
            )
        );

        if (!$row) {
            return null;
        }

        return [
            'product_ids' => json_decode($row->product_ids, true),
            'options'     => json_decode($row->options, true) ?: [],
            'expires_at'  => $row->expires_at,
        ];
    }

    public static function delete_share($token) {
        global $wpdb;
        $tables = Hizli_Kasa_Database::get_tables();
        $wpdb->delete($tables['catalog_shares'], ['token' => sanitize_text_field($token)], ['%s']);
    }

    public static function purge_expired() {
        global $wpdb;
        $tables = Hizli_Kasa_Database::get_tables();
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$tables['catalog_shares']} WHERE expires_at <= %s",
                current_time('mysql')
            )
        );
    }

    public static function get_public_url($token) {
        return add_query_arg('hk_catalog', rawurlencode($token), home_url('/'));
    }
}
