<?php
if (!defined('ABSPATH')) exit;

class Hizli_Kasa_Hooks {
    public static function init() {
        add_action('woocommerce_new_order', [__CLASS__, 'invalidate_reports_cache']);
        add_action('woocommerce_update_order', [__CLASS__, 'invalidate_reports_cache']);
        add_action('woocommerce_order_refunded', [__CLASS__, 'invalidate_reports_cache']);
        add_action('profile_update', [__CLASS__, 'invalidate_user_perms_cache']);
        add_action('user_register', [__CLASS__, 'invalidate_user_perms_cache']);
        add_filter('rest_pre_serve_request', [__CLASS__, 'enforce_no_cache'], 10, 4);
        add_action('woocommerce_new_order', [__CLASS__, 'handle_coupon_use'], 10, 2);
    }

    public static function invalidate_reports_cache() {
        update_option('hk_reports_cache_version', time());
    }

    public static function invalidate_user_perms_cache($user_id) {
        delete_transient("hk_user_view_depos_{$user_id}");
        delete_transient("hk_user_manage_depos_{$user_id}");
    }

    public static function enforce_no_cache($served, $result, $request, $server) {
        $route = $request->get_route();

        if (strpos($route, '/hizli-kasa/v1/') === 0 || strpos($route, '/hizli-kasa/v2/') === 0) {
            if (!defined('DONOTCACHEPAGE')) {
                define('DONOTCACHEPAGE', true);
            }

            do_action('litespeed_control_force_nocache');

            if (!headers_sent()) {
                header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
                header('Pragma: no-cache');
                header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
                header('X-LiteSpeed-Cache-Control: no-cache');
            }
        }

        return $served;
    }

    public static function handle_coupon_use($order_id, $order = false) {
        if (!$order) {
            $order = wc_get_order($order_id);
        }
        if (!$order) {
            return;
        }

        $kasiyer = $order->get_meta('_hizli_kasa_kasiyer');
        if (!$kasiyer) {
            return;
        }

        $coupon_code = $order->get_meta('_hizli_kasa_used_coupon_code');
        if ($coupon_code) {
            $coupon = new WC_Coupon($coupon_code);
            if ($coupon->get_id()) {
                $coupon->increase_usage_count();
            }

            $lock_key = 'hk_coupon_lock_' . md5(strtoupper($coupon_code));
            delete_transient($lock_key);
        }
    }
}

function hizli_kasa_invalidate_reports_cache() {
    return Hizli_Kasa_Hooks::invalidate_reports_cache();
}

function hizli_kasa_invalidate_user_perms_cache($user_id) {
    return Hizli_Kasa_Hooks::invalidate_user_perms_cache($user_id);
}

function hizli_kasa_handle_coupon_use_on_new_order($order_id, $order = false) {
    return Hizli_Kasa_Hooks::handle_coupon_use($order_id, $order);
}
