<?php
require_once __DIR__ . '/../hizli-kasa.php';
require_once ABSPATH . 'wp-admin/includes/upgrade.php';

echo "Veritabanı tablosu oluşturuluyor...\n";
Hizli_Kasa_Database::init();
echo "İşlem tamamlandı.";
