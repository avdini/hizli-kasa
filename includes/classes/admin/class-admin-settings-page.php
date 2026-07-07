<?php
if (!defined('ABSPATH')) exit;

class Hizli_Kasa_Admin_Settings_Page {
    public static function render() {
        if (!defined('DONOTCACHEPAGE')) define('DONOTCACHEPAGE', true);
        $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'hub';

        global $wpdb;
        $depo_table = $wpdb->prefix . 'hizli_kasa_depolar';
        $depolar = $wpdb->get_results("SELECT id, name FROM $depo_table ORDER BY priority DESC");
        ?>
        <div class="wrap">
            <?php settings_errors('hizli_kasa_messages'); ?>

            <?php if ($active_tab === 'hub'): ?>
                <?php include HIZLI_KASA_PATH . 'includes/views/admin-hub-dashboard.php'; ?>
            <?php else: 
                $tab_titles = [
                    'stok' => 'Stok Yönetimi',
                    'depolar' => 'Depo Yönetimi',
                    'unmatched' => 'Eşleşmeyen Ürünler',
                    'bildirimler' => 'Bildirimler',
                    'genel' => 'Genel Ayarlar',
                    'onbellek' => 'Önbellek (Cache)',
                    'oto-sku' => 'Otomatik SKU',
                    'araclar' => 'Sistem Araçları'
                ];
                $current_title = isset($tab_titles[$active_tab]) ? $tab_titles[$active_tab] : 'Ayarlar';
                ?>
                <div class="hk-breadcrumb-nav">
                    <a href="?page=hizli-kasa"><span class="dashicons dashicons-grid-view"></span> Hızlı Kasa Hub</a>
                    <span class="hk-sep">/</span>
                    <span class="current"><?php echo esc_html($current_title); ?></span>
                </div>

                <div style="margin-top: 20px;">
                    <?php
                    if ($active_tab === 'genel') {
                        include HIZLI_KASA_PATH . 'includes/views/admin-settings-genel.php';
                    } elseif ($active_tab === 'stok') {
                        include HIZLI_KASA_PATH . 'includes/views/admin-stok-yonetimi.php';
                    } elseif ($active_tab === 'unmatched') {
                        include HIZLI_KASA_PATH . 'includes/views/admin-stok-uyusmazlik.php';
                    } elseif ($active_tab === 'depolar') {
                        include HIZLI_KASA_PATH . 'includes/views/admin-depo-yonetimi.php';
                    } elseif ($active_tab === 'bildirimler') {
                        include HIZLI_KASA_PATH . 'includes/views/tab-bildirimler.php';
                    } elseif ($active_tab === 'onbellek') {
                        include HIZLI_KASA_PATH . 'includes/views/admin-settings-onbellek.php';
                    } elseif ($active_tab === 'oto-sku') {
                        include HIZLI_KASA_PATH . 'includes/views/admin-settings-auto-sku.php';
                    } elseif ($active_tab === 'araclar') {
                        include HIZLI_KASA_PATH . 'includes/views/admin-settings-araclar.php';
                    }
                    ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
}