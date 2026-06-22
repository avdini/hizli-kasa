<?php if (!defined('ABSPATH')) exit; ?>
                <form method="post" action="options.php" class="card" style="margin-bottom:20px;">
                    <?php settings_fields('hizli_kasa_araclar_grubu'); ?>
                    <h3>GeliÅŸtirici AyarlarÄ±</h3>
                    <p>HÄ±zlÄ± Kasa'nÄ±n performansÄ±nÄ± ve hata kayÄ±tlarÄ±nÄ± yÃ¶netin.</p>
                    <table class="form-table">
                        <tr>
                            <th scope="row">Debug Logu (Sistem KayÄ±tlarÄ±)</th>
                            <td>
                                <?php $debug_aktif = get_option('hizli_kasa_debug_log_aktif', '0'); ?>
                                <label>
                                    <input type="checkbox" name="hizli_kasa_debug_log_aktif" value="1" <?php checked($debug_aktif, '1'); ?>>
                                    Debug Logu Aktif
                                </label>
                                <p class="description">
                                    EtkinleÅŸtirildiÄŸinde <code>hizli-kasa-debug.log</code> dosyasÄ±na ve PHP error_log sistemine sipariÅŸ/stok sÃ¼reÃ§leri detaylÄ± olarak yazÄ±lÄ±r.<br>
                                    <strong style="color:#d63638;">Sadece sorun tespiti sÄ±rasÄ±nda aÃ§Ä±n!</strong> SÃ¼rekli aÃ§Ä±k kalmasÄ± disk I/O iÅŸlemlerini artÄ±rÄ±r ve POS sipariÅŸ onay hÄ±zÄ±nÄ± dÃ¼ÅŸÃ¼rÃ¼r.
                                </p>
                            </td>
                        </tr>
                    </table>
                    <?php submit_button('AyarlarÄ± Kaydet', 'primary', 'submit', false); ?>
                </form>

                <div class="card">
                    <h3>Sistemi BaÅŸlat: StoklarÄ± Kopyala</h3>
                    <p>Mevcut WooCommerce ana stoklarÄ±nÄ± seÃ§ilen depoya transfer eder ve sistemi kullanÄ±ma hazÄ±rlar.</p>
                    <form id="hizli-kasa-setup-form">
                        <select id="setup-target-depo" required>
                            <option value="">-- Hedef Depo SeÃ§in --</option>
                            <?php foreach($depolar as $d): ?>
                                <option value="<?php echo $d->id; ?>"><?php echo esc_html($d->name); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" id="btn-hizli-kasa-setup" class="button button-primary">Sistemi BaÅŸlat</button>
                    </form>
                </div>

                <div class="card" style="margin-top:20px;">
                    <h3>Depo StoklarÄ±nÄ± Siteyle Senkronize Et</h3>
                    <p>TÃ¼m depolarÄ±n stok toplamlarÄ±nÄ± hesaplayÄ±p WooCommerce ana site stoÄŸu olarak gÃ¼nceller. Stok sayÄ±mÄ± sonrasÄ± oluÅŸan uyuÅŸmazlÄ±klarÄ± gidermek iÃ§in kullanabilirsiniz.</p>
                    <button type="button" id="btn-hizli-kasa-sync-wh-to-wc" class="button button-primary">Depo StoklarÄ±nÄ± Siteye EÅŸitle</button>
                    <div id="hk-sync-progress-wrapper" style="display:none; margin-top:15px; background:#f0f0f1; border-radius:4px; height:20px; overflow:hidden; position:relative; width:300px;">
                        <div id="hk-sync-progress-bar" style="background:#2271b1; height:100%; width:0%; transition:width 0.2s;"></div>
                        <span id="hk-sync-progress-text" style="position:absolute; inset:0; display:flex; align-items:center; justify-content:center; font-size:11px; font-weight:600; color:#1d2327;">0%</span>
                    </div>
                    <span id="hk-sync-status" style="display:block; margin-top:5px; font-size:12px; color:#646970;"></span>
                </div>

                <div class="card" style="margin-top:20px; border-color:#d63638;">
                    <h3 style="color:#d63638;">âš ï¸ Tehlikeli BÃ¶lge: Sistemi SÄ±fÄ±rla</h3>
                    <p>Bu iÅŸlem tÃ¼m depo verilerini, stok konumlarÄ±nÄ± ve hareket loglarÄ±nÄ± kalÄ±cÄ± olarak siler!</p>
                    <button type="button" id="btn-hizli-kasa-reset" class="button button-link-delete">Sistemi SÄ±fÄ±rla (Fabrika AyarlarÄ±)</button>
                </div>

                <div class="card" style="margin-top:20px;">
                    <h3>Sistem OnarÄ±mÄ±</h3>
                    <p>EÄŸer depolarÄ± kaydedemiyorsanÄ±z veya veritabanÄ± hatalarÄ± alÄ±yorsanÄ±z tablolarÄ± onarmayÄ± deneyin. Bu iÅŸlem verilerinizi silmez.</p>
                    <button type="button" id="btn-hizli-kasa-repair" class="button button-secondary">TablolarÄ± Onar / VeritabanÄ± GÃ¼ncelle</button>
                </div>

                <script>
                jQuery(document).ready(function($) {
                    $('#btn-hizli-kasa-setup').on('click', function() {
                        const depoId = $('#setup-target-depo').val();
                        if(!depoId) return alert('LÃ¼tfen bir hedef depo seÃ§in.');
                        if(!confirm('TÃ¼m Ã¼rÃ¼n stoklarÄ± bu depoya kopyalanacak. Devam edilsin mi?')) return;
                        
                        $(this).prop('disabled', true).text('Ä°ÅŸleniyor...');
                        $.post(ajaxurl, { action: 'hizli_kasa_setup', depo_id: depoId }, function(res) {
                            alert(res.data.message);
                            location.reload();
                        });
                    });

                    $('#btn-hizli-kasa-sync-wh-to-wc').on('click', function() {
                        if(!confirm('TÃ¼m depolarÄ±n toplam stoÄŸu WooCommerce ana site stoÄŸu olarak yazÄ±lacak. Emin misiniz?')) return;
                        
                        const btn = $(this);
                        btn.prop('disabled', true).text('Ä°ÅŸleniyor...');
                        
                        const progressWrapper = $('#hk-sync-progress-wrapper');
                        const progressBar = $('#hk-sync-progress-bar');
                        const progressText = $('#hk-sync-progress-text');
                        const statusSpan = $('#hk-sync-status');
                        
                        progressWrapper.show();
                        progressBar.css('width', '0%');
                        progressText.text('0%');
                        statusSpan.text('ÃœrÃ¼nler taranÄ±yor...');
                        
                        $.post(ajaxurl, { action: 'hizli_kasa_sync_wh_to_wc_start' }, function(res) {
                            if(!res.success || !res.data.ids || res.data.ids.length === 0) {
                                alert(res.data.message || 'EÅŸitlenecek Ã¼rÃ¼n bulunamadÄ±.');
                                btn.prop('disabled', false).text('Depo StoklarÄ±nÄ± Siteye EÅŸitle');
                                progressWrapper.hide();
                                return;
                            }
                            
                            const ids = res.data.ids;
                            const total = ids.length;
                            let processed = 0;
                            const batchSize = 100;
                            
                            function processNextBatch() {
                                if(processed >= total) {
                                    statusSpan.text('EÅŸitleme baÅŸarÄ±yla tamamlandÄ±!');
                                    btn.prop('disabled', false).text('Depo StoklarÄ±nÄ± Siteye EÅŸitle');
                                    alert('TÃ¼m depo stoklarÄ± baÅŸarÄ±yla siteye eÅŸitlendi!');
                                    location.reload();
                                    return;
                                }
                                
                                const batch = ids.slice(processed, processed + batchSize);
                                statusSpan.text('Ä°ÅŸleniyor: ' + processed + ' / ' + total);
                                
                                $.post(ajaxurl, { action: 'hizli_kasa_sync_wh_to_wc_step', ids: batch }, function(stepRes) {
                                    if(stepRes.success) {
                                        processed += batch.length;
                                        const pct = Math.round((processed / total) * 100);
                                        progressBar.css('width', pct + '%');
                                        progressText.text(pct + '%');
                                        processNextBatch();
                                    } else {
                                        alert('EÅŸitleme sÄ±rasÄ±nda hata oluÅŸtu: ' + (stepRes.data.message || 'Bilinmeyen Hata'));
                                        btn.prop('disabled', false).text('Depo StoklarÄ±nÄ± Siteye EÅŸitle');
                                    }
                                }).fail(function() {
                                    alert('BaÄŸlantÄ± hatasÄ± oluÅŸtu, iÅŸlem durduruldu.');
                                    btn.prop('disabled', false).text('Depo StoklarÄ±nÄ± Siteye EÅŸitle');
                                });
                            }
                            
                            processNextBatch();
                        });
                    });

                    $('#btn-hizli-kasa-reset').on('click', function() {
                        if(!confirm('DÄ°KKAT! TÃ¼m veriler silinecek. Bu iÅŸlem geri alÄ±namaz. Emin misiniz?')) return;
                        if(!confirm('SON UYARI: GerÃ§ekten her ÅŸeyi silmek istiyor musunuz?')) return;

                        $(this).prop('disabled', true).text('Siliniyor...');
                        $.post(ajaxurl, { action: 'hizli_kasa_reset' }, function(res) {
                            alert(res.data.message);
                            location.reload();
                        });
                    });

                    $('#btn-hizli-kasa-repair').on('click', function() {
                        $(this).prop('disabled', true).text('OnarÄ±lÄ±yor...');
                        $.post(ajaxurl, { action: 'hizli_kasa_repair_db' }, function(res) {
                            alert(res.data.message);
                            location.reload();
                        });
                    });
                });
                </script>