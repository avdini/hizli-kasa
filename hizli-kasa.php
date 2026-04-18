<?php
/**
 * Plugin Name: Hızlı Kasa
 * Description: WooCommerce için sadece barkod ve enter tuşuyla çalışan hızlı POS sistemi.
 * Version: 3.2.7
 * Author: Seyfullah Kurt
 */

if (!defined('ABSPATH'))
	exit;

// Sabitler
define('HIZLI_KASA_VERSION', '3.2.7');
define('HIZLI_KASA_PATH', plugin_dir_path(__FILE__));
define('HIZLI_KASA_URL', plugin_dir_url(__FILE__));

require_once HIZLI_KASA_PATH . 'includes/classes/class-database.php';
require_once HIZLI_KASA_PATH . 'includes/classes/class-stock-manager.php';
require_once HIZLI_KASA_PATH . 'includes/classes/class-admin-settings.php';
require_once HIZLI_KASA_PATH . 'includes/classes/class-rest-api.php';
require_once HIZLI_KASA_PATH . 'includes/classes/class-shortcode.php';
require_once HIZLI_KASA_PATH . 'includes/classes/class-menu-filter.php';

// Veritabanı Aktivasyonu
register_activation_hook(__FILE__, ['Hizli_Kasa_Database', 'init']);

// Başlatıcılar
Hizli_Kasa_Stock_Manager::listen();

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