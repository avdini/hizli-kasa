<?php
if (!defined('ABSPATH')) {
    exit;
}

$debug_aktif = get_option('hizli_kasa_debug_log_aktif', '0');
?>

<div class="hk-settings-container">
    <!-- Header -->
    <div class="hk-settings-header">
        <div class="hk-settings-header-title">
            <h1>
                <span class="dashicons dashicons-admin-tools" style="color:var(--hk-primary); font-size:26px; width:26px; height:26px;"></span>
                Sistem Araçları & Bakım
            </h1>
            <p>Hızlı Kasa performansını yönetin, veritabanını senkronize edin ve sistem araçlarını çalıştırın.</p>
        </div>
    </div>

    <!-- 1. Geliştirici Ayarları & Debug Logu -->
    <form method="post" action="options.php" class="hk-settings-card">
        <?php settings_fields('hizli_kasa_araclar_grubu'); ?>

        <div class="hk-settings-card-header">
            <div class="hk-settings-card-icon">
                <span class="dashicons dashicons-code-standards"></span>
            </div>
            <div class="hk-settings-card-title">
                <h3>Geliştirici Ayarları & Loglama</h3>
                <p>Hızlı Kasa sistem loglarını ve hata kayıtlarını yapılandırın.</p>
            </div>
        </div>

        <div class="hk-settings-row">
            <div class="hk-settings-label">
                <label>Debug Logu (Sistem Kayıtları)</label>
                <p class="hk-settings-desc">
                    Etkinleştirildiğinde <code>hizli-kasa-debug.log</code> dosyasına sipariş/stok süreçleri detaylı olarak yazılır.<br>
                    <strong style="color:#dc2626;">Sadece sorun tespiti sırasında açın!</strong> Sürekli açık kalması disk I/O işlemlerini artırır ve sipariş onay hızını düşürür.
                </p>
            </div>
            <div class="hk-settings-field">
                <label class="hk-switch-label">
                    <input type="hidden" name="hizli_kasa_debug_log_aktif" value="0">
                    <span class="hk-switch">
                        <input type="checkbox" name="hizli_kasa_debug_log_aktif" value="1" <?php checked($debug_aktif, '1'); ?>>
                        <span class="hk-slider"></span>
                    </span>
                </label>
            </div>
        </div>

        <div style="display:flex; justify-content:flex-end; margin-top:16px;">
            <button type="submit" class="hk-btn-action hk-btn-action-primary">
                <span class="dashicons dashicons-saved"></span> Log Ayarını Kaydet
            </button>
        </div>
    </form>

    <!-- 2. Stok Senkronizasyonu ve İlk Kurulum -->
    <div class="hk-settings-card">
        <div class="hk-settings-card-header">
            <div class="hk-settings-card-icon">
                <span class="dashicons dashicons-update"></span>
            </div>
            <div class="hk-settings-card-title">
                <h3>Stok Senkronizasyonu & Kurulum</h3>
                <p>Mevcut WooCommerce ürün stoklarını depolara kopyalayın veya depo stoklarını siteye eşitleyin.</p>
            </div>
        </div>

        <!-- Sub-Section: İlk Kurulum -->
        <div class="hk-settings-row">
            <div class="hk-settings-label">
                <label>Sistemi Başlat: Ana Stokları Kopyala</label>
                <p class="hk-settings-desc">
                    Mevcut WooCommerce ana stoklarını seçeceğiniz depoya varsayılan stok olarak aktarır ve eklentiyi kullanıma hazırlar.
                </p>
            </div>
            <div class="hk-settings-field">
                <div style="display:flex; gap:10px; align-items:center;">
                    <select id="setup-target-depo" class="hk-select" required>
                        <option value="">-- Hedef Depo Seçin --</option>
                        <?php foreach($depolar as $d): ?>
                            <option value="<?php echo $d->id; ?>"><?php echo esc_html($d->name); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="button" id="btn-hizli-kasa-setup" class="hk-btn-action hk-btn-action-primary">
                        <span class="dashicons dashicons-migrate"></span> Sistemi Başlat
                    </button>
                </div>
            </div>
        </div>

        <!-- Sub-Section: Depo Stoklarını Siteyle Senkronize Et -->
        <div class="hk-settings-row">
            <div class="hk-settings-label">
                <label>Depo Stoklarını Siteyle Senkronize Et</label>
                <p class="hk-settings-desc">
                    Tüm depoların stok toplamlarını hesaplayıp WooCommerce ana site stoğu olarak toplu günceller. Stok sayımı sonrası uyuşmazlıkları gidermek için kullanabilirsiniz.
                </p>
            </div>
            <div class="hk-settings-field" style="width:100%; max-width:320px; align-items:stretch;">
                <button type="button" id="btn-hizli-kasa-sync-wh-to-wc" class="hk-btn-action hk-btn-action-primary" style="justify-content:center;">
                    <span class="dashicons dashicons-update"></span> Depo Stoklarını Siteye Eşitle
                </button>

                <div id="hk-sync-progress-wrapper" class="hk-progress-wrapper" style="display:none;">
                    <div id="hk-sync-progress-bar" class="hk-progress-bar"></div>
                    <span id="hk-sync-progress-text" class="hk-progress-text">0%</span>
                </div>
                <span id="hk-sync-status" style="display:block; margin-top:6px; font-size:12px; color:var(--hk-text-muted); text-align:center;"></span>
            </div>
        </div>
    </div>

    <!-- 3. Sistem Onarımı -->
    <div class="hk-settings-card">
        <div class="hk-settings-card-header">
            <div class="hk-settings-card-icon">
                <span class="dashicons dashicons-hammer"></span>
            </div>
            <div class="hk-settings-card-title">
                <h3>Veritabanı & Sistem Onarımı</h3>
                <p>Eksik veritabanı tablolarını otomatik onarın ve veritabanı şemasını tazeleyin.</p>
            </div>
        </div>

        <div class="hk-settings-row">
            <div class="hk-settings-label">
                <label>Veritabanı Tablolarını Onar</label>
                <p class="hk-settings-desc">
                    Depo veya stok kayıtlarında veritabanı hatası alıyorsanız tabloları onarmayı deneyin. Bu işlem mevcut verilerinizi silmez.
                </p>
            </div>
            <div class="hk-settings-field">
                <button type="button" id="btn-hizli-kasa-repair" class="hk-btn-action">
                    <span class="dashicons dashicons-wrench"></span> Tabloları Onar / Güncelle
                </button>
            </div>
        </div>
    </div>

    <!-- 4. Tehlikeli Bölge: Sistemi Sıfırla -->
    <div class="hk-settings-card hk-danger-card">
        <div class="hk-settings-card-header">
            <div class="hk-settings-card-icon">
                <span class="dashicons dashicons-warning"></span>
            </div>
            <div class="hk-settings-card-title">
                <h3>Tehlikeli Bölge: Fabrika Ayarlarına Sıfırlama</h3>
                <p>Tüm Hızlı Kasa verilerini, depo tanımlarını ve stok konumlarını temizler.</p>
            </div>
        </div>

        <div class="hk-settings-row">
            <div class="hk-settings-label">
                <label style="color:#991b1b;">Sistemi Sıfırla (Fabrika Ayarları)</label>
                <p class="hk-settings-desc" style="color:#b91c1c;">
                    <strong>DİKKAT:</strong> Bu işlem tüm depo verilerini, stok konumlarını ve hareket loglarını veritabanından kalıcı olarak siler!
                </p>
            </div>
            <div class="hk-settings-field">
                <button type="button" id="btn-hizli-kasa-reset" class="hk-btn-action hk-btn-action-danger">
                    <span class="dashicons dashicons-trash"></span> Sistemi Sıfırla
                </button>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // 1. Sistemi Başlat
    $('#btn-hizli-kasa-setup').on('click', function() {
        const depoId = $('#setup-target-depo').val();
        if(!depoId) return alert('Lütfen bir hedef depo seçin.');
        if(!confirm('Tüm ürün stokları bu depoya kopyalanacak. Devam edilsin mi?')) return;
        
        const btn = $(this);
        btn.prop('disabled', true).text('İşleniyor...');
        $.post(ajaxurl, { action: 'hizli_kasa_setup', depo_id: depoId }, function(res) {
            alert(res.data.message);
            location.reload();
        });
    });

    // 2. Depo Stoklarını Siteye Eşitle
    $('#btn-hizli-kasa-sync-wh-to-wc').on('click', function() {
        if(!confirm('Tüm depoların toplam stoğu WooCommerce ana site stoğu olarak yazılacak. Emin misiniz?')) return;
        
        const btn = $(this);
        btn.prop('disabled', true).text('İşleniyor...');
        
        const progressWrapper = $('#hk-sync-progress-wrapper');
        const progressBar = $('#hk-sync-progress-bar');
        const progressText = $('#hk-sync-progress-text');
        const statusSpan = $('#hk-sync-status');
        
        progressWrapper.show();
        progressBar.css('width', '0%');
        progressText.text('0%');
        statusSpan.text('Ürünler taranıyor...');
        
        $.post(ajaxurl, { action: 'hizli_kasa_sync_wh_to_wc_start' }, function(res) {
            if(!res.success || !res.data.ids || res.data.ids.length === 0) {
                alert(res.data.message || 'Eşitlenecek ürün bulunamadı.');
                btn.prop('disabled', false).html('<span class="dashicons dashicons-update"></span> Depo Stoklarını Siteye Eşitle');
                progressWrapper.hide();
                return;
            }
            
            const ids = res.data.ids;
            const total = ids.length;
            let processed = 0;
            const batchSize = 100;
            
            function processNextBatch() {
                if(processed >= total) {
                    statusSpan.text('Eşitleme başarıyla tamamlandı!');
                    btn.prop('disabled', false).html('<span class="dashicons dashicons-update"></span> Depo Stoklarını Siteye Eşitle');
                    alert('Tüm depo stokları başarıyla siteye eşitlendi!');
                    location.reload();
                    return;
                }
                
                const batch = ids.slice(processed, processed + batchSize);
                statusSpan.text('İşleniyor: ' + processed + ' / ' + total);
                
                $.post(ajaxurl, { action: 'hizli_kasa_sync_wh_to_wc_step', ids: batch }, function(stepRes) {
                    if(stepRes.success) {
                        processed += batch.length;
                        const pct = Math.round((processed / total) * 100);
                        progressBar.css('width', pct + '%');
                        progressText.text(pct + '%');
                        processNextBatch();
                    } else {
                        alert('Eşitleme sırasında hata oluştu: ' + (stepRes.data.message || 'Bilinmeyen Hata'));
                        btn.prop('disabled', false).html('<span class="dashicons dashicons-update"></span> Depo Stoklarını Siteye Eşitle');
                    }
                }).fail(function() {
                    alert('Bağlantı hatası oluştu, işlem durduruldu.');
                    btn.prop('disabled', false).html('<span class="dashicons dashicons-update"></span> Depo Stoklarını Siteye Eşitle');
                });
            }
            
            processNextBatch();
        });
    });

    // 3. Sistemi Sıfırla
    $('#btn-hizli-kasa-reset').on('click', function() {
        if(!confirm('DİKKAT! Tüm veriler silinecek. Bu işlem geri alınamaz. Emin misiniz?')) return;
        if(!confirm('SON UYARI: Gerçekten her şeyi silmek istiyor musunuz?')) return;

        const btn = $(this);
        btn.prop('disabled', true).text('Siliniyor...');
        $.post(ajaxurl, { action: 'hizli_kasa_reset' }, function(res) {
            alert(res.data.message);
            location.reload();
        });
    });

    // 4. Tabloları Onar
    $('#btn-hizli-kasa-repair').on('click', function() {
        const btn = $(this);
        btn.prop('disabled', true).text('Onarılıyor...');
        $.post(ajaxurl, { action: 'hizli_kasa_repair_db' }, function(res) {
            alert(res.data.message);
            location.reload();
        });
    });
});
</script>
