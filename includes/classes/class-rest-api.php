<?php
/**
 * Hızlı Kasa - REST API Loader
 *
 * Modüler API dosyalarını yükler.
 *
 * @package HizliKasa
 */

if (!defined('ABSPATH'))
    exit;

$api_dir = HIZLI_KASA_PATH . 'includes/api/';

require_once $api_dir . 'api-helpers.php';
require_once $api_dir . 'api-user.php';
require_once $api_dir . 'api-kasa.php';
require_once $api_dir . 'api-siparis.php';
require_once $api_dir . 'api-iade.php';
require_once $api_dir . 'api-sevk.php';
require_once $api_dir . 'api-masraf.php';
require_once $api_dir . 'api-raporlar.php';
require_once $api_dir . 'api-terminal.php';
require_once $api_dir . 'api-istatistik.php';
