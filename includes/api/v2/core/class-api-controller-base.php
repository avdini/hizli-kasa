<?php
/**
 * Hızlı Kasa V2 API Controller Base Class
 *
 * @package HizliKasa
 */

if (!defined('ABSPATH')) {
    exit;
}

abstract class Hizli_Kasa_API_Controller_Base {
    /**
     * API namespace.
     *
     * @var string
     */
    protected $namespace = 'hizli-kasa/v2';

    /**
     * Register endpoints.
     */
    abstract public function register_routes();

    /**
     * Standard permissions callback.
     *
     * @param WP_REST_Request $request
     * @return bool|WP_Error
     */
    public function check_permission($request) {
        if (!function_exists('hizli_kasa_can_access_app') || !hizli_kasa_can_access_app()) {
            return new WP_Error(
                'rest_forbidden',
                __('Bu işlem için yetkiniz bulunmuyor.', 'hizli-kasa'),
                ['status' => 403]
            );
        }
        return true;
    }

    /**
     * Handle requests by enforcing no-cache headers and catching exceptions.
     *
     * @param callable        $callback
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    protected function handle_request($callback, $request) {
        // Enforce no-cache headers
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

        try {
            return call_user_func($callback, $request);
        } catch (Exception $e) {
            return Hizli_Kasa_API_Response::error($e->getMessage(), 500);
        }
    }
}
