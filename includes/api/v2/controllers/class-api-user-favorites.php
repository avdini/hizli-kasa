<?php
if (!defined('ABSPATH')) {
    exit;
}

class Hizli_Kasa_API_User_Favorites extends Hizli_Kasa_API_Controller_Base {
    public function register_routes() {
        // GET method to fetch favorite reports
        register_rest_route($this->namespace, '/user/favorite-reports', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_favorite_reports_callback'],
            'permission_callback' => [$this, 'check_permission'],
        ]);

        // POST method to save favorite reports
        register_rest_route($this->namespace, '/user/favorite-reports', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'save_favorite_reports_callback'],
            'permission_callback' => [$this, 'check_permission'],
            'args'                => [
                'favorites' => [
                    'required'          => true,
                    'type'              => 'array',
                    'description'       => 'Array of favorite report IDs',
                    'sanitize_callback' => function($param) {
                        if (!is_array($param)) {
                            return [];
                        }
                        return array_map('sanitize_text_field', $param);
                    },
                    'validate_callback' => function($param) {
                        return is_array($param);
                    }
                ],
            ],
        ]);
    }

    public function get_favorite_reports_callback($request) {
        return $this->handle_request([$this, 'get_favorite_reports'], $request);
    }

    public function save_favorite_reports_callback($request) {
        return $this->handle_request([$this, 'save_favorite_reports'], $request);
    }

    protected function get_favorite_reports($request) {
        $user_id = get_current_user_id();
        $favorites = get_user_meta($user_id, '_hizli_kasa_favori_raporlar', true);

        // Fallback: If not set, return defaults
        if (!is_array($favorites)) {
            $favorites = ['tum-siparisler', 'ozet-istatistik', 'gun-sonu-arsivi'];
        }

        return Hizli_Kasa_API_Response::success($favorites);
    }

    protected function save_favorite_reports($request) {
        $user_id = get_current_user_id();
        $favorites = $request->get_param('favorites');

        if (!is_array($favorites)) {
            $favorites = [];
        }

        update_user_meta($user_id, '_hizli_kasa_favori_raporlar', $favorites);

        return Hizli_Kasa_API_Response::success([
            'favorites' => $favorites,
            'message'   => __('Favori raporlarınız başarıyla kaydedildi.', 'hizli-kasa')
        ]);
    }
}
