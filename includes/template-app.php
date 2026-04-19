<?php
/**
 * Hızlı Kasa - Ana Uygulama Kabuğu (Shell)
 * 
 * Üst navigasyon ve sekmelerin yükleneceği ana yapı.
 */
if (!defined('ABSPATH')) exit;
?>

<div id="hizli-kasa-app">
    <!-- Üst Sekme Menüsü -->
    <div id="hizli-kasa-ust-menu">
        <div class="kasa-logo">
            <span>🚀</span> HIZLI KASA
        </div>
        <div class="ust-sekme-listesi">
            <div class="ust-sekme aktif" data-tab="kasa">
                <span class="sekme-ikon">📠</span> Kasa
            </div>
            <div class="ust-sekme" data-tab="urunler">
                <span class="sekme-ikon">📦</span> Ürünler
            </div>
            <div class="ust-sekme" data-tab="raporlar">
                <span class="sekme-ikon">📊</span> Raporlar
            </div>
            <div class="ust-sekme" data-tab="ayarlar">
                <span class="sekme-ikon">⚙️</span> Ayarlar
            </div>
            <div class="ust-sekme" data-tab="iade">
                <span class="sekme-ikon">↩️</span> İade
            </div>
            <div class="ust-sekme" data-tab="masraf">
                <span class="sekme-ikon">💸</span> Masraf
            </div>
        </div>
        <div class="kullanici-bilgi">
            <?php echo wp_get_current_user()->display_name; ?>
        </div>
    </div>

    <!-- Sekme İçerik Alanı -->
    <div id="app-view-container">
        <!-- Kasa Sekmesi (Varsayılan olarak yüklü gelir) -->
        <div id="tab-content-kasa" class="tab-content aktif">
            <?php include HIZLI_KASA_PATH . 'includes/views/tab-kasa.php'; ?>
        </div>

        <!-- Diğer sekmeler dinamik olarak buraya eklenecek -->
        <div id="tab-content-urunler" class="tab-content"></div>
        <div id="tab-content-raporlar" class="tab-content"></div>
        <div id="tab-content-ayarlar" class="tab-content"></div>
        <div id="tab-content-iade" class="tab-content"></div>
        <div id="tab-content-masraf" class="tab-content"></div>
    </div>

    <!-- Global Yükleniyor Göstergesi -->
    <div id="app-loading" style="display: none;">
        <div class="spinner"></div>
        <span>Sayfa Yükleniyor...</span>
    </div>
</div>
