<?php
/**
 * Hızlı Kasa - Ana Uygulama Kabuğu (Shell)
 * 
 * Üst navigasyon ve sekmelerin yükleneceği ana yapı.
 */
if (!defined('ABSPATH')) exit;
?>

<?php 
$current_user_id = get_current_user_id();
$user_theme = get_user_meta($current_user_id, '_hizli_kasa_tema', true) ?: 'light'; 
?>
<div id="hizli-kasa-app" class="theme-<?php echo esc_attr($user_theme); ?>">
    <!-- Üst Sekme Menüsü -->
    <div id="hizli-kasa-ust-menu">
        <button id="mobile-menu-toggle" class="mobile-toggle">
            <span></span>
            <span></span>
            <span></span>
        </button>
        <div class="kasa-logo">
            <span>🚀</span> <span class="logo-text">HIZLI KASA</span>
        </div>
        <div class="ust-sekme-listesi" id="ust-sekme-listesi">
            <div class="ust-sekme aktif" data-tab="kasa">
                <span class="sekme-ikon">📠</span> Kasa
            </div>
            <div class="ust-sekme" data-tab="masraf">
                <span class="sekme-ikon">💸</span> Masraf
            </div>
            <div class="ust-sekme" data-tab="iade">
                <span class="sekme-ikon">↩️</span> İade
            </div>
            <div class="ust-sekme" data-tab="urunler">
                <span class="sekme-ikon">📦</span> Ürünler
            </div>
            <div class="ust-sekme" data-tab="sevk">
                <span class="sekme-ikon">🚚</span> Sevk & Tedarik
            </div>
            <div class="ust-sekme" data-tab="raporlar">
                <span class="sekme-ikon">📊</span> Raporlar
            </div>
            <div class="ust-sekme" data-tab="ayarlar">
                <span class="sekme-ikon">⚙️</span> Ayarlar
            </div>
        </div>
        <div class="ust-menu-sag-aksiyonlar">
            <a href="<?php echo esc_url(home_url('/')); ?>" class="ust-menu-aksiyon site-link" target="_blank" title="Site Ana Sayfası">
                <span class="site-name-text"><?php bloginfo('name'); ?></span>
            </a>

            <div class="ust-menu-aksiyon depo-secici-ust" id="ust-depo-switcher-container">
                <div id="ust-depo-switcher-trigger" class="depo-trigger">
                    <span id="ust-aktif-depo-adi">...</span>
                </div>
                <div id="ust-depo-dropdown" class="depo-dropdown-menu" style="display: none;">
                    <!-- Dinamik olarak dolacak -->
                </div>
            </div>

            <div class="kullanici-bilgi">
                <?php echo wp_get_current_user()->display_name; ?>
            </div>

            <button id="tam-ekran-toggle" class="ust-menu-aksiyon tam-ekran-btn" title="Tam Ekran (F11)">
                ⛶
            </button>
        </div>
    </div>

    <!-- Sekme İçerik Alanı -->
    <div id="app-view-container">
        <!-- Kasa Sekmesi (Varsayılan olarak yüklü gelir) -->
        <div id="tab-content-kasa" class="tab-content aktif">
            <?php include HIZLI_KASA_PATH . 'includes/views/tab-kasa.php'; ?>
        </div>

        <!-- Diğer sekmeler dinamik olarak buraya eklenecek -->
        <div id="tab-content-masraf" class="tab-content"></div>
        <div id="tab-content-iade" class="tab-content"></div>
        <div id="tab-content-urunler" class="tab-content"></div>
        <div id="tab-content-sevk" class="tab-content"></div>
        <div id="tab-content-raporlar" class="tab-content"></div>
        <div id="tab-content-ayarlar" class="tab-content"></div>
    </div>
    
    <!-- Global Modallar -->
    <?php include HIZLI_KASA_PATH . 'includes/views/modals.php'; ?>

    <!-- Global Yükleniyor Göstergesi -->
    <div id="app-loading" style="display: none;">
        <div class="spinner"></div>
        <span>Sayfa Yükleniyor...</span>
    </div>

    <!-- Sipariş Oluşturuluyor Overlay -->
    <div id="order-loading-overlay">
        <div class="order-loading-content">
            <div class="order-loader"></div>
            <div class="order-loading-text">Sipariş Oluşturuluyor</div>
            <div class="order-loading-subtext">Lütfen bekleyin, WooCommerce ile senkronize ediliyor...</div>
        </div>
    </div>

    <!-- Toast Bildirim Konteyneri (Global overlay) -->
    <div id="hk-toast-container"></div>
</div>
