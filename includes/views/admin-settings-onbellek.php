<?php if (!defined('ABSPATH')) exit; ?>
                <div class="wrap hk-cache-wrap">
                    <style>
                        .hk-cache-grid { width: 100%; border-collapse: collapse; margin-top: 15px; background: #fff; border: 1px solid #c3c4c7; box-shadow: 0 1px 1px rgba(0,0,0,.04); }
                        .hk-cache-grid th, .hk-cache-grid td { padding: 15px; border-bottom: 1px solid #c3c4c7; text-align: left; vertical-align: middle; }
                        .hk-cache-grid th { background: #f6f7f7; font-weight: 600; color: #1d2327; }
                        .hk-cache-grid tr:last-child td { border-bottom: none; }
                        .hk-cache-desc { font-size: 13px; color: #646970; margin-top: 4px; }
                        .hk-cache-title { font-size: 14px; font-weight: 600; color: #1d2327; }
                        .hk-cache-status-on { color: #00a32a; font-weight: bold; }
                        .hk-cache-status-off { color: #d63638; font-weight: bold; }
                        .hk-cache-ttl-input { width: 80px; text-align: center; }
                    </style>

                    <div class="card" style="max-width: 100%; padding: 20px;">
                        <h2 style="margin-top:0;">Ã–nbellek (Cache) Kontrol Merkezi</h2>
                        <p>Sistemin aÄŸÄ±r yÃ¼k Ã§eken kÄ±sÄ±mlarÄ± iÃ§in Ã¶nbellekleme sÃ¼relerini ayarlayabilir ve yÃ¶netebilirsiniz.</p>

                        <!-- Katman Durum KartlarÄ± -->
                        <div style="display: flex; gap: 12px; margin-bottom: 20px; flex-wrap: wrap;">
                            <div style="flex: 1; min-width: 200px; background: #f0f9eb; border: 1px solid #c2e0b4; border-radius: 6px; padding: 14px 16px; display: flex; align-items: flex-start; gap: 12px;">
                                <span style="font-size: 20px; line-height: 1;">âœ…</span>
                                <div>
                                    <strong style="display: block; color: #1d2327; margin-bottom: 3px;">Sunucu KatmanÄ± KorumasÄ±: AKTÄ°F</strong>
                                    <span style="font-size: 12px; color: #646970;">TÃ¼m REST API yanÄ±tlarÄ±na <code>no-store</code> ve <code>X-LiteSpeed-Cache-Control: no-cache</code> header'larÄ± otomatik ekleniyor.</span>
                                </div>
                            </div>
                            <div style="flex: 1; min-width: 200px; background: #f0f9eb; border: 1px solid #c2e0b4; border-radius: 6px; padding: 14px 16px; display: flex; align-items: flex-start; gap: 12px;">
                                <span style="font-size: 20px; line-height: 1;">âœ…</span>
                                <div>
                                    <strong style="display: block; color: #1d2327; margin-bottom: 3px;">TarayÄ±cÄ± KatmanÄ± KorumasÄ±: AKTÄ°F</strong>
                                    <span style="font-size: 12px; color: #646970;">Frontend'deki tÃ¼m GET isteklerine otomatik <code>?_=timestamp</code> parametresi ekleniyor. TarayÄ±cÄ± hiÃ§bir yanÄ±tÄ± cache'den servis edemez.</span>
                                </div>
                            </div>
                        </div>

                        <!-- LiteSpeed Hosting Rehberi -->
                        <div style="background: #fff8e1; border: 1px solid #ffe082; border-radius: 6px; padding: 14px 16px; margin-bottom: 20px; display: flex; align-items: flex-start; gap: 12px;">
                            <span style="font-size: 20px; line-height: 1;">âš¡</span>
                            <div style="font-size: 13px; color: #5d4037;">
                                <strong style="display: block; margin-bottom: 4px;">LiteSpeed Web Sunucusu KullanÄ±yorsanÄ±z</strong>
                                Eklentimiz artÄ±k tÃ¼m API yanÄ±tlarÄ±na <code>X-LiteSpeed-Cache-Control: no-cache</code> header'Ä± gÃ¶nderiyor.
                                Ancak LiteSpeed'in <strong>sayfa (HTML) Ã¶nbelleÄŸi</strong> hosting panelinizden (cPanel / CyberPanel â†’ LiteSpeed Cache Manager) ayrÄ±ca kontrol edilmelidir.
                                Eklentinin kendi Ã¶nbellek toggle'Ä± yalnÄ±zca eklentinin iÃ§ transient sistemini (arama, raporlar, depo, yetki) etkiler.
                            </div>
                        </div>

                        <form method="post" action="options.php">
                            <?php settings_fields('hizli_kasa_cache_grubu'); ?>
                            
                            <?php $cache_aktif = get_option('hizli_kasa_cache_aktif', '1'); ?>
                            <div style="background: <?php echo $cache_aktif ? '#f0f9eb' : '#fcf0f1'; ?>; border: 1px solid <?php echo $cache_aktif ? '#c2e0b4' : '#f5c6cb'; ?>; padding: 15px; border-radius: 4px; margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between;">
                                <div>
                                    <h3 style="margin: 0 0 5px;">Sistem Durumu: <?php echo $cache_aktif ? '<span class="hk-cache-status-on">AKTÄ°F</span>' : '<span class="hk-cache-status-off">PASÄ°F</span>'; ?></h3>
                                    <p style="margin: 0; color: #666; font-size: 13px;">TÃ¼m Ã¶nbellek sistemini tek tuÅŸla kapatabilirsiniz. Acil durumlarda sorun tespiti iÃ§in kullanÄ±lÄ±r.</p>
                                </div>
                                <div>
                                    <label class="button button-secondary">
                                        <input type="checkbox" name="hizli_kasa_cache_aktif" value="1" <?php checked($cache_aktif, '1'); ?> style="margin-right: 8px;">
                                        Sistemi AÃ§ / Kapat
                                    </label>
                                </div>
                            </div>

                            <table class="hk-cache-grid">
                                <thead>
                                    <tr>
                                        <th style="width: 25%;">Ã–nbellek Grubu</th>
                                        <th style="width: 40%;">AÃ§Ä±klama</th>
                                        <th style="width: 15%;">TTL SÃ¼resi</th>
                                        <th style="width: 20%;">Manuel Ä°ÅŸlem</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- ÃœrÃ¼n Arama -->
                                    <tr>
                                        <td>
                                            <div class="hk-cache-title">ğŸ”Â ÃœrÃ¼n AramasÄ±</div>
                                        </td>
                                        <td>
                                            POS ekranÄ±nda yapÄ±lan metin aramalarÄ±nÄ±n sonuÃ§larÄ±nÄ± (ID listesini) saklar.
                                            <div class="hk-cache-desc">Not: Stok ve fiyatlar her zaman canlÄ± Ã§ekilmeye devam eder. Eski stok gÃ¶sterilmez.</div>
                                        </td>
                                        <td>
                                            <input type="number" name="hizli_kasa_search_cache_ttl" value="<?php echo esc_attr(get_option('hizli_kasa_search_cache_ttl', 5)); ?>" min="1" max="1440" class="hk-cache-ttl-input"> <span class="hk-cache-desc">dk</span>
                                        </td>
                                        <td>
                                            <button type="button" class="button button-small" onclick="clearHKCache('arama', this)">Arama Ã–nbelleÄŸini Temizle</button>
                                        </td>
                                    </tr>

                                    <!-- Raporlar ve Ä°statistikler -->
                                    <tr>
                                        <td>
                                            <div class="hk-cache-title">ğŸ“Š Raporlar ve Ä°statistikler</div>
                                        </td>
                                        <td>
                                            GÃ¼n Sonu, Dashboard ve Raporlar sekmelerindeki hesaplamalarÄ± saklar.
                                            <div class="hk-cache-desc">AkÄ±llÄ± YÄ±kÄ±m: Yeni sipariÅŸ geldiÄŸinde veya iptal edildiÄŸinde otomatik temizlenir.</div>
                                        </td>
                                        <td>
                                            <input type="number" name="hizli_kasa_reports_cache_ttl" value="<?php echo esc_attr(get_option('hizli_kasa_reports_cache_ttl', 15)); ?>" min="1" max="1440" class="hk-cache-ttl-input"> <span class="hk-cache-desc">dk</span>
                                        </td>
                                        <td>
                                            <button type="button" class="button button-small" onclick="clearHKCache('raporlar', this)">Rapor Ã–nbelleÄŸini Temizle</button>
                                        </td>
                                    </tr>

                                    <!-- Depo Listesi -->
                                    <tr>
                                        <td>
                                            <div class="hk-cache-title">ğŸÂ¢ Depo Listesi</div>
                                        </td>
                                        <td>
                                            Sistemdeki tÃ¼m depolarÄ±n temel bilgilerini saklar.
                                            <div class="hk-cache-desc">AkÄ±llÄ± YÄ±kÄ±m: Yeni depo eklendiÄŸinde, silindiÄŸinde veya gÃ¼ncellendiÄŸinde otomatik temizlenir.</div>
                                        </td>
                                        <td>
                                            <input type="number" name="hizli_kasa_depo_cache_ttl" value="<?php echo esc_attr(get_option('hizli_kasa_depo_cache_ttl', 24)); ?>" min="1" max="720" class="hk-cache-ttl-input"> <span class="hk-cache-desc">saat</span>
                                        </td>
                                        <td>
                                            <button type="button" class="button button-small" onclick="clearHKCache('depolar', this)">Depo Ã–nbelleÄŸini Temizle</button>
                                        </td>
                                    </tr>

                                    <!-- KullanÄ±cÄ± Yetkileri -->
                                    <tr>
                                        <td>
                                            <div class="hk-cache-title">ğŸ”Â KullanÄ±cÄ± Yetkileri</div>
                                        </td>
                                        <td>
                                            Personelin hangi depoyu gÃ¶rebildiÄŸi ve yÃ¶netebildiÄŸi bilgisini saklar.
                                            <div class="hk-cache-desc">AkÄ±llÄ± YÄ±kÄ±m: Profil gÃ¼ncellendiÄŸinde otomatik temizlenir.</div>
                                        </td>
                                        <td>
                                            <input type="number" name="hizli_kasa_user_perms_cache_ttl" value="<?php echo esc_attr(get_option('hizli_kasa_user_perms_cache_ttl', 12)); ?>" min="1" max="720" class="hk-cache-ttl-input"> <span class="hk-cache-desc">saat</span>
                                        </td>
                                        <td>
                                            <button type="button" class="button button-small" onclick="clearHKCache('yetkiler', this)">Yetki Ã–nbelleÄŸini Temizle</button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                            
                            <div style="margin-top: 20px; display: flex; align-items: center; justify-content: space-between;">
                                <?php submit_button('AyarlarÄ± Kaydet', 'primary', 'submit', false); ?>
                                <button type="button" class="button button-link-delete" style="color: #d63638;" onclick="(async () => { if(await HK.UI.confirm('Tüm önbellek silinecek emin misiniz?')) clearHKCache('all', this); })()">Tüm Sistemi Temizle (Flush All)</button>
                            </div>
                        </form>
                    </div>

                    <script>
                    function clearHKCache(type, btn) {
                        const originalText = btn.innerText;
                        btn.innerText = "Ä°ÅŸleniyor...";
                        btn.disabled = true;
                        
                        jQuery.post(ajaxurl, {
                            action: 'hizli_kasa_clear_cache',
                            cache_type: type
                        }, function(res) {
                            HK.UI.alert(res.data.message);
                            btn.innerText = originalText;
                            btn.disabled = false;
                        }).fail(function() {
                            HK.UI.alert("Bir hata oluştu.");
                            btn.innerText = originalText;
                            btn.disabled = false;
                        });
                    }
                    </script>
                </div>
