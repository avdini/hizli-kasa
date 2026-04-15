<?php
/**
 * Plugin Name: Hızlı Kasa (Barkod Okuyucu)
 * Description: WooCommerce için sadece barkod ve enter tuşuyla çalışan hızlı POS sistemi.
 * Version: 2.9.7
 * Author: Seyfullah Kurt
 */

if (!defined('ABSPATH'))
    exit;

// Sabitler
define('HIZLI_KASA_VERSION', '2.9.7');
define('HIZLI_KASA_PATH', plugin_dir_path(__FILE__));
define('HIZLI_KASA_URL', plugin_dir_url(__FILE__));

// Modülleri Yükle
require_once HIZLI_KASA_PATH . 'includes/class-admin-settings.php';
require_once HIZLI_KASA_PATH . 'includes/class-rest-api.php';
require_once HIZLI_KASA_PATH . 'includes/class-shortcode.php';
require_once HIZLI_KASA_PATH . 'includes/class-menu-filter.php';

// Otomatik Güncelleme Sistemi (Plugin Update Checker)
require_once HIZLI_KASA_PATH . 'includes/plugin-update-checker/plugin-update-checker.php';

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$hizli_kasa_update_checker = PucFactory::buildUpdateChecker(
	'https://github.com/Seyfullahkurt9/hizli-kasa/',
	__FILE__,
	'hizli-kasa'
);

// Private Repo izinleri için GitHub Token
// Hangi branch'i takip edeceğini belirliyoruz (main)
$hizli_kasa_update_checker->setAuthentication('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');
$hizli_kasa_update_checker->setBranch('main');