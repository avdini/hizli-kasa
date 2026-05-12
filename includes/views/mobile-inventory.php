<?php
if (!defined('ABSPATH')) exit;
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Hızlı Kasa - Mobil Envanter</title>
    
    <!-- PWA Meta Tags -->
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Envanter">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="theme-color" content="#0f172a">
    
    <link rel="manifest" href="<?php echo home_url('/?hizli-kasa-manifest=1'); ?>">
    <link rel="apple-touch-icon" href="<?php echo HIZLI_KASA_URL; ?>assets/img/icon-192.png">
    
    <?php wp_head(); ?>
</head>
<body class="mobile-inventory-app theme-<?php echo esc_attr($tema ?? 'dark'); ?>">

    <div id="mobile-app-container">
        <!-- Header -->
        <header class="app-header">
            <div class="header-top">
                <div id="app-exit-logo" class="logo" style="cursor:pointer;">🚀 <span>HK</span></div>
                <div id="header-depo-selector" class="header-depo">
                    <span id="current-depo-name" class="name">Yükleniyor...</span>
                    <span class="chevron">▼</span>
                </div>
                <div class="user-badge"><?php echo esc_html($display_name); ?></div>
            </div>
            
            <!-- Scanner Toggle Area -->
            <div id="scanner-wrapper" class="collapsed">
                <div id="reader"></div>
                <div id="scanner-camera-controls" class="scanner-camera-controls">
                    <button type="button" id="refocus-camera-btn" class="camera-tool-btn">Netleştir</button>
                    <button type="button" id="torch-camera-btn" class="camera-tool-btn" disabled>Flaş</button>
                    <span id="camera-status" class="camera-status">Kamera kapalı</span>
                </div>
                <button id="toggle-scanner-btn" class="scanner-btn">
                    <span class="icon">📷</span> Barkod Tara
                </button>
            </div>

            <!-- Search Area -->
            <div class="search-container">
                <div class="search-input-wrapper">
                    <input type="text" id="mobile-search-input" placeholder="Ürün adı, SKU veya Barkod..." autocomplete="off" enterkeyhint="search">
                    <span class="search-icon">🔍</span>
                    <button id="clear-search" style="display:none;">✕</button>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main id="results-container">
            <div class="initial-state">
                <div class="welcome-icon">📦</div>
                <h2>Envanter Aracı</h2>
                <p>Ürün aramak için yukarıdaki kutuyu kullanın veya barkod taratın.</p>
            </div>
            <!-- Dinamik sonuçlar buraya gelecek -->
        </main>

        <!-- Footer Stats -->
        <footer class="app-footer">
            <div class="footer-info">
                <span id="result-count">0 Ürün bulundu</span>
            </div>
        </footer>
    </div>

    <!-- Depo Seçim Modalı -->
    <div id="depo-select-modal" class="mobile-modal-overlay" style="display:none;">
        <div class="mobile-modal-content glass">
            <h3>Depo Seçin</h3>
            <div id="depo-list-wrapper" class="modal-list">
                <!-- JS ile dolacak -->
            </div>
            <button id="close-depo-modal" class="btn-cancel">Kapat</button>
        </div>
    </div>

    <!-- Global Loading -->
    <div id="app-loader" style="display:none;">
        <div class="spinner"></div>
    </div>

    <!-- Toast Notifications -->
    <div id="mobile-toast-container"></div>

    <!-- Image Preview Modal -->
    <div id="image-preview-modal" class="image-preview-overlay" style="display: none;">
        <div id="preview-loader" class="spinner"></div>
        <div class="preview-content">
            <img id="preview-img" src="" alt="">
            <div class="preview-close">✕ Kapatmak için dokunun</div>
        </div>
    </div>

    <?php wp_footer(); ?>
</body>
</html>
