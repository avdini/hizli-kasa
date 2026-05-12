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
                    <!-- Zoom Slider -->
                    <div class="slider-container">
                        <div class="slider-row">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/><line x1="8" y1="11" x2="14" y2="11"/></svg>
                            <input type="range" id="zoom-slider" min="1" max="5" step="0.1" value="1" class="camera-slider">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/><line x1="11" y1="8" x2="11" y2="14"/><line x1="8" y1="11" x2="14" y2="11"/></svg>
                        </div>
                    </div>

                    <div class="controls-row">
                        <button type="button" id="switch-camera-btn" class="camera-tool-btn" title="Kamera Değiştir">
                            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="1 4 1 10 7 10"/><polyline points="23 20 23 14 17 14"/><path d="M20.49 9A9 9 0 0 0 5.64 5.64L1 10m22 4l-4.64 4.36A9 9 0 0 1 3.51 15"/></svg>
                        </button>
                        <button type="button" id="refocus-camera-btn" class="camera-tool-btn" title="Netleştir">
                            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7V5a2 2 0 0 1 2-2h2"/><path d="M17 3h2a2 2 0 0 1 2 2v2"/><path d="M21 17v2a2 2 0 0 1-2 2h-2"/><path d="M7 21H5a2 2 0 0 1-2-2v-2"/><circle cx="12" cy="12" r="3"/><line x1="12" y1="12" x2="12" y2="12"/></svg>
                        </button>
                        <button type="button" id="torch-camera-btn" class="camera-tool-btn" disabled title="Flaş">
                            <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><path d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                        </button>
                        
                        <!-- Quality Selector -->
                        <div class="quality-selector">
                            <button type="button" id="quality-menu-btn" class="camera-tool-btn" title="Kalite">
                                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 20h20"/><path d="M7 20v-4"/><path d="M12 20v-8"/><path d="M17 20V4"/></svg>
                            </button>
                            <div id="quality-menu" class="quality-menu">
                                <button type="button" class="quality-option" data-quality="low">Hafif (LQ)</button>
                                <button type="button" class="quality-option" data-quality="medium">Dengeli (MQ)</button>
                                <button type="button" class="quality-option" data-quality="high">Hızlı (HQ)</button>
                            </div>
                        </div>
                    </div>
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
