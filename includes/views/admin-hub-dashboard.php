<?php
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$unmatched_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}hizli_kasa_unmatched_items");
$terminal_url = home_url('/hizli-kasa/terminal/');
?>

<div class="hk-hub-container">
    <!-- Header -->
    <header class="hk-hub-header">
        <div class="hk-hub-brand">
            <h1>Hızlı Kasa <span class="badge">v<?php echo esc_html(HIZLI_KASA_VERSION); ?></span></h1>
            <p>Hızlı ve modern POS yönetim paneli. Tüm modüllere buradan erişebilirsiniz.</p>
        </div>
        <a href="<?php echo esc_url($terminal_url); ?>" target="_blank" class="hk-btn-terminal">
            <span class="dashicons dashicons-external"></span> POS Terminali Başlat
        </a>
    </header>

    <!-- Group 1: İşlemler -->
    <section class="hk-hub-group">
        <h2 class="hk-hub-group-title">İşlemler</h2>
        <div class="hk-hub-grid">
            <!-- Stok Yönetimi -->
            <a href="?page=hizli-kasa&tab=stok" class="hk-hub-card hk-hub-card-ops">
                <div class="hk-card-icon-wrapper">
                    <div class="hk-card-icon hk-icon-ops">
                        <span class="dashicons dashicons-database"></span>
                    </div>
                    <span class="hk-card-arrow dashicons dashicons-arrow-right-alt2"></span>
                </div>
                <h3 class="hk-card-title">Stok Yönetimi</h3>
                <p class="hk-card-desc">Depo bazlı stok durumlarını inceleyin, toplu güncelleme ve Excel içe/dışa aktarım yapın.</p>
            </a>

            <!-- Depo Yönetimi -->
            <a href="?page=hizli-kasa&tab=depolar" class="hk-hub-card hk-hub-card-depo">
                <div class="hk-card-icon-wrapper">
                    <div class="hk-card-icon hk-icon-depo">
                        <span class="dashicons dashicons-building"></span>
                    </div>
                    <span class="hk-card-arrow dashicons dashicons-arrow-right-alt2"></span>
                </div>
                <h3 class="hk-card-title">Depo Yönetimi</h3>
                <p class="hk-card-desc">Sistemdeki depoları tanımlayın, çalışma önceliklerini ve kapasitelerini belirleyin.</p>
            </a>

            <!-- Eşleşmeyen Ürünler -->
            <a href="?page=hizli-kasa&tab=unmatched" class="hk-hub-card hk-hub-card-unmatched">
                <div class="hk-card-icon-wrapper">
                    <div class="hk-card-icon hk-icon-unmatched">
                        <span class="dashicons dashicons-warning"></span>
                    </div>
                    <?php if ($unmatched_count > 0): ?>
                        <span class="hk-mismatch-badge"><?php echo $unmatched_count; ?></span>
                    <?php else: ?>
                        <span class="hk-card-arrow dashicons dashicons-arrow-right-alt2"></span>
                    <?php endif; ?>
                </div>
                <h3 class="hk-card-title">Eşleşmeyen Ürünler</h3>
                <p class="hk-card-desc">Barkod veya SKU uyuşmazlığı nedeniyle eşleşemeyen ürünlerin listesi ve çözüm araçları.</p>
            </a>

            <!-- Paylaşılan Kataloglar -->
            <a href="?page=hizli-kasa&tab=kataloglar" class="hk-hub-card hk-hub-card-kataloglar">
                <div class="hk-card-icon-wrapper">
                    <div class="hk-card-icon hk-icon-kataloglar">
                        <span class="dashicons dashicons-share"></span>
                    </div>
                    <span class="hk-card-arrow dashicons dashicons-arrow-right-alt2"></span>
                </div>
                <h3 class="hk-card-title">Paylaşılan Kataloglar</h3>
                <p class="hk-card-desc">Müşterilerinizle paylaştığınız ürün katalog bağlantılarını listeleyin, kopyalayın veya silin.</p>
            </a>
        </div>
    </section>

    <!-- Group 2: Yapılandırma -->
    <section class="hk-hub-group">
        <h2 class="hk-hub-group-title">Yapılandırma</h2>
        <div class="hk-hub-grid">
            <!-- Genel Ayarlar -->
            <a href="?page=hizli-kasa&tab=genel" class="hk-hub-card hk-hub-card-settings">
                <div class="hk-card-icon-wrapper">
                    <div class="hk-card-icon hk-icon-settings">
                        <span class="dashicons dashicons-admin-generic"></span>
                    </div>
                    <span class="hk-card-arrow dashicons dashicons-arrow-right-alt2"></span>
                </div>
                <h3 class="hk-card-title">Genel Ayarlar</h3>
                <p class="hk-card-desc">Temel entegrasyon ayarları, POS cihaz bağlantıları ve kullanıcı bazlı depo yetkilendirmeleri.</p>
            </a>

            <!-- Bildirimler -->
            <a href="?page=hizli-kasa&tab=bildirimler" class="hk-hub-card hk-hub-card-notify">
                <div class="hk-card-icon-wrapper">
                    <div class="hk-card-icon hk-icon-notify">
                        <span class="dashicons dashicons-bell"></span>
                    </div>
                    <span class="hk-card-arrow dashicons dashicons-arrow-right-alt2"></span>
                </div>
                <h3 class="hk-card-title">Bildirimler</h3>
                <p class="hk-card-desc">Kritik stok eşikleri, uyuşmazlıklar ve günlük raporlar için e-posta bildirim kuralları.</p>
            </a>

            <!-- Önbellek (Cache) -->
            <a href="?page=hizli-kasa&tab=onbellek" class="hk-hub-card hk-hub-card-cache">
                <div class="hk-card-icon-wrapper">
                    <div class="hk-card-icon hk-icon-cache">
                        <span class="dashicons dashicons-update"></span>
                    </div>
                    <span class="hk-card-arrow dashicons dashicons-arrow-right-alt2"></span>
                </div>
                <h3 class="hk-card-title">Önbellek (Cache)</h3>
                <p class="hk-card-desc">Stok sorgu performansını artırmaya yönelik bellek önbellekleme ayarları ve temizleme araçları.</p>
            </a>

            <!-- Otomatik SKU -->
            <a href="?page=hizli-kasa&tab=oto-sku" class="hk-hub-card hk-hub-card-sku">
                <div class="hk-card-icon-wrapper">
                    <div class="hk-card-icon hk-icon-sku">
                        <span class="dashicons dashicons-tag"></span>
                    </div>
                    <span class="hk-card-arrow dashicons dashicons-arrow-right-alt2"></span>
                </div>
                <h3 class="hk-card-title">Otomatik SKU</h3>
                <p class="hk-card-desc">Yeni eklenen varyasyonlar veya ürünler için şablonlara göre otomatik SKU ve barkod kuralları.</p>
            </a>
        </div>
    </section>

    <!-- Group 3: Sistem -->
    <section class="hk-hub-group">
        <h2 class="hk-hub-group-title">Sistem</h2>
        <div class="hk-hub-grid">
            <!-- Sistem Araçları -->
            <a href="?page=hizli-kasa&tab=araclar" class="hk-hub-card hk-hub-card-tools">
                <div class="hk-card-icon-wrapper">
                    <div class="hk-card-icon hk-icon-tools">
                        <span class="dashicons dashicons-admin-tools"></span>
                    </div>
                    <span class="hk-card-arrow dashicons dashicons-arrow-right-alt2"></span>
                </div>
                <h3 class="hk-card-title">Sistem Araçları</h3>
                <p class="hk-card-desc">Veritabanı onarım, sıfırlama, el terminali senkronizasyonu ve log görüntüleme araçları.</p>
            </a>
        </div>
    </section>
</div>
