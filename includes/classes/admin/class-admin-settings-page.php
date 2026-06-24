<?php
if (!defined('ABSPATH')) exit;

class Hizli_Kasa_Admin_Settings_Page {
    public static function render() {
        if (!defined('DONOTCACHEPAGE')) define('DONOTCACHEPAGE', true);
        $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'stok';

        global $wpdb;
        $depo_table = $wpdb->prefix . 'hizli_kasa_depolar';
        $depolar = $wpdb->get_results("SELECT id, name FROM $depo_table ORDER BY priority DESC");
        ?>
        <div class="wrap">
            <h1>Hızlı Kasa Ayarları</h1>
            <?php settings_errors('hizli_kasa_messages'); ?>
            <?php
            $unmatched_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}hizli_kasa_unmatched_items");
            $badge = $unmatched_count > 0 ? ' <span style="background:#d63638; color:#fff; padding:1px 6px; border-radius:10px; font-size:10px; font-weight:bold; vertical-align:middle; margin-left:4px;">!</span>' : '';
            ?>
            <h2 class="nav-tab-wrapper">
                <a href="?page=hizli-kasa&tab=stok" class="nav-tab <?php echo $active_tab == 'stok' ? 'nav-tab-active' : ''; ?>">Stok Yönetimi</a>
                <a href="?page=hizli-kasa&tab=depolar" class="nav-tab <?php echo $active_tab == 'depolar' ? 'nav-tab-active' : ''; ?>">Depo Yönetimi</a>
                <a href="?page=hizli-kasa&tab=unmatched" class="nav-tab <?php echo $active_tab == 'unmatched' ? 'nav-tab-active' : ''; ?>">Eşleşmeyen Ürünler<?php echo $badge; ?></a>
                <a href="?page=hizli-kasa&tab=bildirimler" class="nav-tab <?php echo $active_tab == 'bildirimler' ? 'nav-tab-active' : ''; ?>">Bildirimler</a>
                <a href="?page=hizli-kasa&tab=genel" class="nav-tab <?php echo $active_tab == 'genel' ? 'nav-tab-active' : ''; ?>">Genel Ayarlar</a>
                <a href="?page=hizli-kasa&tab=onbellek" class="nav-tab <?php echo $active_tab == 'onbellek' ? 'nav-tab-active' : ''; ?>">Önbellek (Cache)</a>
                <a href="?page=hizli-kasa&tab=araclar" class="nav-tab <?php echo $active_tab == 'araclar' ? 'nav-tab-active' : ''; ?>">Sistem Araçları</a>
            </h2>
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
                } elseif ($active_tab === 'araclar') {
                    include HIZLI_KASA_PATH . 'includes/views/admin-settings-araclar.php';
                }
                ?>
            </div>
        </div>
        <?php
    }
}