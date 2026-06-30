<?php
if (!defined('ABSPATH')) {
    exit;
}

class Hizli_Kasa_API_User_Sound extends Hizli_Kasa_API_Controller_Base {
    public function register_routes() {
        register_rest_route($this->namespace, '/user/sound-settings', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'save_sound_settings_callback'],
            'permission_callback' => [$this, 'check_permission'],
            'args'                => [
                'volume' => [
                    'required'          => true,
                    'type'              => 'integer',
                    'sanitize_callback' => 'absint',
                    'validate_callback' => function($param) {
                        return $param >= 0 && $param <= 100;
                    }
                ],
                'preset' => [
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => function($param) {
                        return in_array($param, ['classic', 'soft', 'retro', 'digital', 'sharp_click', 'high_alert'], true);
                    }
                ],
            ],
        ]);
    }

    public function save_sound_settings_callback($request) {
        return $this->handle_request([$this, 'save_sound_settings'], $request);
    }

    protected function save_sound_settings($request) {
        $user_id = get_current_user_id();
        $volume = intval($request->get_param('volume'));
        $preset = sanitize_text_field($request->get_param('preset'));

        $settings = [
            'volume' => $volume,
            'preset' => $preset,
        ];

        update_user_meta($user_id, '_hizli_kasa_ses_ayarlari', $settings);

        return Hizli_Kasa_API_Response::success([
            'volume'  => $volume,
            'preset'  => $preset,
            'message' => __('Ses ayarları kaydedildi.', 'hizli-kasa')
        ]);
    }
}
