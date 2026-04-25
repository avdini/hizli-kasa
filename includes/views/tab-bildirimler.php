<?php
if (!defined('ABSPATH')) exit;

$enabled = get_option('hizli_kasa_mismatch_check_enabled', '1');
$interval = get_option('hizli_kasa_mismatch_interval', 'hk_hourly');
$dismiss_hours = get_option('hizli_kasa_dismiss_hours', 24);
$last_check = get_option('hizli_kasa_mismatch_last_check', 'Henüz yapılmadı');
$status = get_option('hizli_kasa_mismatch_found', '0');
$next_check = wp_next_scheduled('hizli_kasa_mismatch_check_event');

// Durum Badge Rengi
$status_color = ($status === '1') ? '#ef4444' : '#10b981';
$status_text = ($status === '1') ? 'Uyuşmazlık Tespit Edildi' : 'Her Şey Yolunda';
?>

<div class="hizli-kasa-notifications-wrap">
    <div style="display:grid; grid-template-columns: 2fr 1fr; gap:20px;">
        <!-- Sol Kolon: Ayarlar -->
        <div>
            <form method="post" action="options.php">
                <?php settings_fields('hizli_kasa_bildirim_grubu'); ?>
                
                <div class="card" style="margin:0; max-width:100%; padding:25px; border-radius:12px; box-shadow:0 1px 3px rgba(0,0,0,0.1);">
                    <h2 style="margin-top:0; font-size:18px; display:flex; align-items:center; gap:10px;">
                        <span class="dashicons dashicons-bell" style="color:#2271b1;"></span>
                        Stok Uyuşmazlık Bildirimleri
                    </h2>
                    <p style="color:#64748b; margin-bottom:25px;">Depo stokları ile site stokları arasındaki farkları takip edin ve uyarılar alın.</p>

                    <table class="form-table">
                        <tr valign="top">
                            <th scope="row">Bildirimler Aktif mi?</th>
                            <td>
                                <label class="hk-switch">
                                    <input type="checkbox" name="hizli_kasa_mismatch_check_enabled" value="1" <?php checked($enabled, '1'); ?>>
                                    <span class="hk-slider"></span>
                                </label>
                                <p class="description">Aktif edildiğinde uyuşmazlık durumunda sağ altta uyarı balonu görünür.</p>
                            </td>
                        </tr>

                        <tr valign="top">
                            <th scope="row">Kontrol Sıklığı (Arka Plan)</th>
                            <td>
                                <select name="hizli_kasa_mismatch_interval" style="min-width:200px;">
                                    <option value="hk_5mins" <?php selected($interval, 'hk_5mins'); ?>>5 Dakikada Bir</option>
                                    <option value="hk_15mins" <?php selected($interval, 'hk_15mins'); ?>>15 Dakikada Bir</option>
                                    <option value="hk_30mins" <?php selected($interval, 'hk_30mins'); ?>>30 Dakikada Bir</option>
                                    <option value="hk_hourly" <?php selected($interval, 'hk_hourly'); ?>>Saatte Bir</option>
                                    <option value="hk_6hours" <?php selected($interval, 'hk_6hours'); ?>>6 Saatte Bir</option>
                                    <option value="hk_twice_daily" <?php selected($interval, 'hk_twice_daily'); ?>>Günde 2 Kez</option>
                                    <option value="daily" <?php selected($interval, 'daily'); ?>>Günde Bir</option>
                                </select>
                                <p class="description">Uyuşmazlık kontrolü arka planda bu aralıklarla otomatik çalışır.</p>
                            </td>
                        </tr>

                        <tr valign="top">
                            <th scope="row">Bildirimi Görmezden Gelme Süresi</th>
                            <td>
                                <input type="number" name="hizli_kasa_dismiss_hours" value="<?php echo esc_attr($dismiss_hours); ?>" min="0" step="1" class="small-text"> Saat
                                <p class="description">Admin uyarıyı "Kapat" butonuna basarak gizlerse, bu süre boyunca tekrar görünmez.</p>
                            </td>
                        </tr>
                    </table>

                    <div style="margin-top:30px; padding-top:20px; border-top:1px solid #eee;">
                        <?php submit_button('Bildirim Ayarlarını Kaydet', 'primary'); ?>
                    </div>
                </div>
            </form>
        </div>

        <!-- Sağ Kolon: Durum ve Bilgi -->
        <div>
            <div class="card" style="margin:0; padding:25px; border-radius:12px; border-left:4px solid <?php echo $status_color; ?>;">
                <h3 style="margin-top:0; font-size:16px;">Sistem Durumu</h3>
                
                <div style="margin:20px 0;">
                    <div style="font-size:12px; color:#64748b; margin-bottom:5px;">Mevcut Durum:</div>
                    <div style="font-weight:700; color:<?php echo $status_color; ?>; display:flex; align-items:center; gap:8px; font-size:15px;">
                        <span class="dashicons <?php echo ($status === '1') ? 'dashicons-warning' : 'dashicons-yes-alt'; ?>"></span>
                        <?php echo $status_text; ?>
                    </div>
                </div>

                <div style="margin:20px 0; padding-top:15px; border-top:1px solid #f1f5f9;">
                    <div style="font-size:12px; color:#64748b; margin-bottom:5px;">Son Kontrol:</div>
                    <div style="font-weight:600; color:#1e293b;"><?php echo $last_check; ?></div>
                </div>

                <div style="margin:20px 0;">
                    <div style="font-size:12px; color:#64748b; margin-bottom:5px;">Sıralı Kontrol (Yaklaşık):</div>
                    <div style="font-weight:600; color:#1e293b;">
                        <?php echo $next_check ? date_i18n('d.m.Y H:i', $next_check + (get_option('gmt_offset') * 3600)) : 'Planlanmadı'; ?>
                    </div>
                </div>

                <div style="margin-top:25px;">
                    <button type="button" id="btn-manual-mismatch-check" class="button button-secondary" style="width:100%; justify-content:center; display:flex; align-items:center; gap:5px; height:35px;">
                        <span class="dashicons dashicons-update" style="margin-top:2px;"></span>
                        Şimdi Manuel Kontrol Et
                    </button>
                    <p style="font-size:11px; color:#94a3b8; text-align:center; margin-top:8px;">Manuel kontrol işlemi biraz zaman alabilir.</p>
                </div>
            </div>

            <div class="card" style="margin-top:20px; padding:20px; border-radius:12px; background:#f8fafc; border:none;">
                <h4 style="margin-top:0; font-size:14px; color:#475569;">Nasıl Çalışır?</h4>
                <ul style="margin:0; padding-left:18px; color:#64748b; font-size:12px; line-height:1.6;">
                    <li>Sistem arka planda (WP Cron) depo toplamlarını site stokları ile karşılaştırır.</li>
                    <li>Herhangi bir ürün veya varyasyonda fark tespit edilirse sistem "Uyuşmazlık" durumuna geçer.</li>
                    <li>Siz uyuşmazlığı giderip manuel kontrol yaptığınızda veya sistem bir sonraki kontrolü tamamladığında uyarı otomatik kalkar.</li>
                    <li>Herhangi bir depoda stok güncellemesi yapıldığında mevcut uyuşmazlık durumu sıfırlanır ve sistem bir sonraki yüklemede taze kontrol yapar.</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<style>
/* Switch Toggle Design */
.hk-switch { position: relative; display: inline-block; width: 44px; height: 24px; vertical-align: middle; }
.hk-switch input { opacity: 0; width: 0; height: 0; }
.hk-slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #cbd5e1; transition: .4s; border-radius: 24px; }
.hk-slider:before { position: absolute; content: ""; height: 18px; width: 18px; left: 3px; bottom: 3px; background-color: white; transition: .4s; border-radius: 50%; }
input:checked + .hk-slider { background-color: #2271b1; }
input:checked + .hk-slider:before { transform: translateX(20px); }

/* Animation */
.spinning { animation: hk-spin 1s linear infinite; }
@keyframes hk-spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
</style>

<script>
jQuery(document).ready(function($) {
    $('#btn-manual-mismatch-check').on('click', function() {
        const $btn = $(this);
        const $icon = $btn.find('.dashicons');
        
        $btn.prop('disabled', true);
        $icon.addClass('spinning');
        
        $.post(ajaxurl, { action: 'hizli_kasa_manual_mismatch_check' }, function(res) {
            $btn.prop('disabled', false);
            $icon.removeClass('spinning');
            
            if (res.success) {
                location.reload();
            } else {
                alert('Kontrol sırasında bir hata oluştu.');
            }
        });
    });
});
</script>
