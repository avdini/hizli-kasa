<?php

if (!defined('ABSPATH'))
    exit;

/**
 * Otomatik Güncelleme Sistemi (Plugin Update Checker)
 * POS AJAX ve REST isteklerinde GitHub cURL kilitlenmesini önlemek için PUC'ı sadece WP Admin, Cron veya WP Eklenti Güncelleme AJAX isteğinde çalıştırıyoruz.
 */
function hizli_kasa_init_updater($plugin_file)
{
    $is_wp_update_ajax = wp_doing_ajax() && isset($_REQUEST['action']) && $_REQUEST['action'] === 'update-plugin';

    if ((!wp_doing_ajax() || $is_wp_update_ajax) && (!defined('REST_REQUEST') || !REST_REQUEST)) {
        require_once HIZLI_KASA_PATH . 'includes/plugin-update-checker/plugin-update-checker.php';

        // PUC'ın Release/Tag aramayı bırakıp doğrudan hedef branch'i (main/master) takip etmesini sağlıyoruz
        add_filter('puc_vcs_update_detection_strategies-hizli-kasa', function ($strategies) {
            unset($strategies['latest_release']);
            unset($strategies['latest_tag']);
            return $strategies;
        });

        $repo_url   = defined('HIZLI_KASA_UPDATE_REPO') ? HIZLI_KASA_UPDATE_REPO : 'https://github.com/Seyfullahkurt9/hizli-kasa/';
        $repo_branch = defined('HIZLI_KASA_UPDATE_BRANCH') ? HIZLI_KASA_UPDATE_BRANCH : 'main';

        $hizli_kasa_update_checker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
            $repo_url,
            $plugin_file,
            'hizli-kasa'
        );

        $hizli_kasa_update_checker->setBranch($repo_branch);

        // WP Admin'de "Güncellemeleri Kontrol Et" isteğinde önbelleği temizleyip zorla kontrol ettiriyoruz
        if (is_admin() && isset($_GET['force-check'])) {
            $hizli_kasa_update_checker->requestUpdate();
        }

        // DNS ve yavaş ağ kilitlenmelerine karşı 15 saniyelik dengeli cURL zaman aşımı
        add_filter('http_request_args', function ($args, $url) {
            if (strpos($url, 'api.github.com') !== false || strpos($url, 'github.com') !== false || strpos($url, 'codeload.github.com') !== false) {
                $args['timeout'] = 15;
            }
            return $args;
        }, 10, 2);
    }
}
