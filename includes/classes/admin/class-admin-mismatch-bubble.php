<?php
if (!defined('ABSPATH')) exit;

class Hizli_Kasa_Admin_Mismatch_Bubble {
    public static function init() {
        add_action('admin_footer', [self::class, 'render']);
    }

    public static function render() {
    // Sadece admin görebilir
    if (!current_user_can('manage_options')) return;
    
    // Ayar kontrolü
    if (get_option('hizli_kasa_mismatch_check_enabled', '1') !== '1') return;

    // Durum kontrolü
    $found = get_option('hizli_kasa_mismatch_found', '0');
    if ($found !== '1') return;

    // Dismissal kontrolü (JS ile de yapılacak ama PHP ile hiç basmamak daha temiz)
    $dismiss_hours = intval(get_option('hizli_kasa_dismiss_hours', 24));
    
    ?>
    <div id="hk-mismatch-bubble" style="display:none; position:fixed; bottom:30px; right:30px; width:320px; background:rgba(255,255,255,0.8); backdrop-filter:blur(12px); -webkit-backdrop-filter:blur(12px); border:1px solid rgba(255,255,255,0.4); border-radius:16px; box-shadow:0 10px 40px rgba(0,0,0,0.15); z-index:99999; padding:20px; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Oxygen-Sans,Ubuntu,Cantarell,'Helvetica Neue',sans-serif; animation:hk-float 3s ease-in-out infinite;">
        <div style="display:flex; gap:15px; align-items:flex-start;">
            <div style="background:#fff7ed; width:40px; height:40px; border-radius:10px; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                <span class="dashicons dashicons-warning" style="color:#f97316; font-size:24px; width:24px; height:24px;"></span>
            </div>
            <div style="flex:1;">
                <h4 style="margin:0 0 5px; color:#1e293b; font-size:15px; font-weight:700;">Stok Uyuşmazlığı Mevcut</h4>
                <p style="margin:0 0 15px; color:#64748b; font-size:13px; line-height:1.5;">Depo toplamları site stoğu ile uyuşmuyor. Kontrol etmek ister misiniz?</p>
                <div style="display:flex; gap:10px;">
                    <a href="<?php echo admin_url('admin.php?page=hizli-kasa&tab=stok&filter_mismatch=true'); ?>" style="background:#2271b1; color:#fff; text-decoration:none; padding:8px 15px; border-radius:8px; font-size:12px; font-weight:600; transition:all 0.2s;">Detayları Gör</a>
                    <button type="button" onclick="dismissHKBubble()" style="background:#f1f5f9; color:#64748b; border:none; padding:8px 15px; border-radius:8px; font-size:12px; font-weight:600; cursor:pointer; transition:all 0.2s;">Kapat</button>
                </div>
            </div>
        </div>
    </div>
    <style>
    @keyframes hk-float {
        0% { transform: translateY(0px); }
        50% { transform: translateY(-8px); }
        100% { transform: translateY(0px); }
    }
    #hk-mismatch-bubble:hover { animation-play-state: paused; background: rgba(255,255,255,0.95); }
    </style>
    <script>
    function dismissHKBubble() {
        const hours = <?php echo $dismiss_hours; ?>;
        const expireTime = Date.now() + (hours * 60 * 60 * 1000);
        localStorage.setItem('hk_mismatch_dismissed_until', expireTime);
        document.getElementById('hk-mismatch-bubble').style.display = 'none';
    }

    (function() {
        const dismissedUntil = localStorage.getItem('hk_mismatch_dismissed_until');
        const bubble = document.getElementById('hk-mismatch-bubble');
        
        if (!dismissedUntil || Date.now() > parseInt(dismissedUntil)) {
            // Eğer uyuşmazlık sayfasındaysak balonu gösterme
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('page') === 'hizli-kasa' && urlParams.get('tab') === 'stok') {
                return;
            }
            setTimeout(() => {
                bubble.style.display = 'block';
                bubble.style.animation = 'hk-fade-in 0.5s ease-out, hk-float 3s ease-in-out infinite';
            }, 1000);
        }
    })();
    </script>
    <?php
}
}
