<?php
/**
 * Hızlı Kasa V2 API Response Standardizer
 *
 * @package HizliKasa
 */

if (!defined('ABSPATH')) {
    exit;
}

class Hizli_Kasa_API_Response {
    /**
     * Send a standardized JSON response.
     *
     * @param bool  $success     Status of the request.
     * @param mixed $data        Data payload.
     * @param array $errors      List of errors if any.
     * @param int   $status_code HTTP status code.
     * @return WP_REST_Response
     */
    public static function send($success, $data = null, $errors = null, $status_code = 200) {
        $response = [
            'success' => (bool)$success,
            'data'    => $data,
            'errors'  => $errors ? (is_array($errors) ? $errors : [$errors]) : null,
            'meta'    => [
                'timestamp' => current_time('mysql'),
                'version'   => 'v2'
            ]
        ];

        return new WP_REST_Response($response, $status_code);
    }

    /**
     * Send a successful response.
     *
     * @param mixed $data        Data payload.
     * @param int   $status_code HTTP status code.
     * @return WP_REST_Response
     */
    public static function success($data = null, $status_code = 200) {
        return self::send(true, $data, null, $status_code);
    }

    /**
     * Send an error response.
     *
     * @param mixed $message_or_array Error message or list of messages.
     * @param int   $status_code      HTTP status code.
     * @return WP_REST_Response
     */
    public static function error($message_or_array, $status_code = 400) {
        return self::send(false, null, $message_or_array, $status_code);
    }
}
