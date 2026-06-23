<?php if (!defined('ABSPATH')) exit; ?>
                <form method="post" action="options.php" class="card" style="margin-bottom:20px;">
                    <?php settings_fields('hizli_kasa_araclar_grubu'); ?>
                    <h3>Geliştirici Ayarları</h3>
                    <p>Hızlı Kasa'nın performansını ve hata kayıtlarını yönetin.</p>
                    <table class="form-table">
                        <tr>
                            <th scope="row">Debug Logu (Sistem Kayıtları)</th>
                            <td>
                                <?php $debug_aktif = get_option('hizli_kasa_debug_log_aktif', '0'); ?>
                                <label>
                                    <input type="checkbox" name="hizli_kasa_debug_log_aktif" value="1" <?php checked($debug_aktif, '1'); ?>>
                                    Debug Logu Aktif
                                </label>
                                <p class="description">
                                    Etkinleştirildiğinde <code>hizli-kasa-debug.log</code> dosyasına ve PHP error_log sistemine sipariş/stok süreçleri detaylı olarak yazılır.<br>
                                    <strong style="color:#d63638;">Sadece sorun tespiti sırasında açın!</strong> Sürekli açık kalması disk I/O işlemlerini artırır ve POS sipariş onay hızını düşürür.
                                </p>
                            </td>
                        </tr>
                    </table>
                    <?php submit_button('Ayarları Kaydet', 'primary', 'submit', false); ?>
                </form>

                <div class="card">
                    <h3>Sistemi Başlat: Stokları Kopyala</h3>
                    <p>Mevcut WooCommerce ana stoklarını seçilen depoya transfer eder ve sistemi kullanıma hazırlar.</p>
                    <form id="hizli-kasa-setup-form">
                        <select id="setup-target-depo" required>
                            <option value="">-- Hedef Depo Seçin --</option>
                            <?php foreach($depolar as $d): ?>
                                <option value="<?php echo $d->id; ?>"><?php echo esc_html($d->name); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" id="btn-hizli-kasa-setup" class="button button-primary">Sistemi Başlat</button>
                    </form>
                </div>

                <div class="card" style="margin-top:20px;">
                    <h3>Depo Stoklarını Siteyle Senkronize Et</h3>
                    <p>Tüm depoların stok toplamlarını hesaplayıp WooCommerce ana site stoğu olarak günceller. Stok sayımı sonrası oluşan uyuşmazlıkları gidermek için kullanabilirsiniz.</p>
                    <button type="button" id="btn-hizli-kasa-sync-wh-to-wc" class="button button-primary">Depo Stoklarını Siteye Eşitle</button>
                    <div id="hk-sync-progress-wrapper" style="display:none; margin-top:15px; background:#f0f0f1; border-radius:4px; height:20px; overflow:hidden; position:relative; width:300px;">
                        <div id="hk-sync-progress-bar" style="background:#2271b1; height:100%; width:0%; transition:width 0.2s;"></div>
                        <span id="hk-sync-progress-text" style="position:absolute; inset:0; display:flex; align-items:center; justify-content:center; font-size:11px; font-weight:600; color:#1d2327;">0%</span>
                    </div>
                    <span id="hk-sync-status" style="display:block; margin-top:5px; font-size:12px; color:#646970;"></span>
                </div>

                <div class="card" style="margin-top:20px; border-color:#d63638;">
                    <h3 style="color:#d63638;">⚠� Tehlikeli Bölge: Sistemi Sıfırla</h3>
                    <p>Bu işlem tüm depo verilerini, stok konumlarını ve hareket loglarını kalıcı olarak siler!</p>
                    <button type="button" id="btn-hizli-kasa-reset" class="button button-link-delete">Sistemi Sıfırla (Fabrika Ayarları)</button>
                </div>

                <div class="card" style="margin-top:20px;">
                    <h3>Sistem Onarımı</h3>
                    <p>Eğer depoları kaydedemiyorsanız veya veritabanı hataları alıyorsanız tabloları onarmayı deneyin. Bu işlem verilerinizi silmez.</p>
                    <button type="button" id="btn-hizli-kasa-repair" class="button button-secondary">Tabloları Onar / Veritabanı Güncelle</button>
                </div>

                <script>
                jQuery(document).ready(function($) {
                    $('#btn-hizli-kasa-setup').on('click', function() {
                        const depoId = $('#setup-target-depo').val();
                        if(!depoId) return alert('Lütfen bir hedef depo seçin.');
                        if(!confirm('Tüm ürün stokları bu depoya kopyalanacak. Devam edilsin mi?')) return;
                        
                        $(this).prop('disabled', true).text('İşleniyor...');
                        $.post(ajaxurl, { action: 'hizli_kasa_setup', depo_id: depoId }, function(res) {
                            alert(res.data.message);
                            location.reload();
                        });
                    });

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
                                btn.prop('disabled', false).text('Depo Stoklarını Siteye Eşitle');
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
                                    btn.prop('disabled', false).text('Depo Stoklarını Siteye Eşitle');
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
                                        btn.prop('disabled', false).text('Depo Stoklarını Siteye Eşitle');
                                    }
                                }).fail(function() {
                                    alert('Bağlantı hatası oluştu, işlem durduruldu.');
                                    btn.prop('disabled', false).text('Depo Stoklarını Siteye Eşitle');
                                });
                            }
                            
                            processNextBatch();
                        });
                    });

                    $('#btn-hizli-kasa-reset').on('click', function() {
                        if(!confirm('DİKKAT! Tüm veriler silinecek. Bu işlem geri alınamaz. Emin misiniz?')) return;
                        if(!confirm('SON UYARI: Gerçekten her şeyi silmek istiyor musunuz?')) return;

                        $(this).prop('disabled', true).text('Siliniyor...');
                        $.post(ajaxurl, { action: 'hizli_kasa_reset' }, function(res) {
                            alert(res.data.message);
                            location.reload();
                        });
                    });

                    $('#btn-hizli-kasa-repair').on('click', function() {
                        $(this).prop('disabled', true).text('Onarılıyor...');
                        $.post(ajaxurl, { action: 'hizli_kasa_repair_db' }, function(res) {
                            alert(res.data.message);
                            location.reload();
                        });
                    });
                });
                </script>