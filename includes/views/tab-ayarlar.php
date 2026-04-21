<?php if (!defined('ABSPATH')) exit; ?>
<div class="terminal-ayarlar-konteyner" style="padding: 30px; max-width: 800px; margin: 0 auto; color: var(--hk-text-main);">
    
    <div style="background: var(--hk-bg-card); padding: 30px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); margin-bottom: 20px;">
        <h2 style="margin-top: 0; border-bottom: 2px solid var(--hk-border); padding-bottom: 10px; margin-bottom: 20px;">Görünüm Ayarları</h2>
        
        <div class="ayar-satir" style="display: flex; align-items: center; justify-content: space-between;">
            <div>
                <strong style="display: block; font-size: 18px;">Görünüm Teması</strong>
                <span style="color: var(--hk-text-muted); font-size: 14px;">Terminalin renk şemasını değiştirin.</span>
            </div>
            <div class="tema-secici" style="display: flex; gap: 10px;">
                <button class="btn-tema <?php echo ($user_theme === 'light') ? 'aktif' : ''; ?>" data-tema="light" style="padding: 10px 20px; border: 1px solid var(--hk-border); border-radius: 6px; cursor: pointer; background: <?php echo ($user_theme === 'light') ? 'var(--hk-accent)' : 'var(--hk-bg-body)'; ?>; color: <?php echo ($user_theme === 'light') ? 'white' : 'var(--hk-text-main)'; ?>; font-weight: bold;">
                    ☀️ Aydınlık
                </button>
                <button class="btn-tema <?php echo ($user_theme === 'dark') ? 'aktif' : ''; ?>" data-tema="dark" style="padding: 10px 20px; border: 1px solid var(--hk-border); border-radius: 6px; cursor: pointer; background: <?php echo ($user_theme === 'dark') ? 'var(--hk-accent)' : 'var(--hk-bg-body)'; ?>; color: <?php echo ($user_theme === 'dark') ? 'white' : 'var(--hk-text-main)'; ?>; font-weight: bold;">
                    🌙 Karanlık
                </button>
            </div>
        </div>
    </div>

    <div style="background: var(--hk-bg-card); padding: 30px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
        <h2 style="margin-top: 0; border-bottom: 2px solid var(--hk-border); padding-bottom: 10px; margin-bottom: 20px;">Sistem Bilgileri</h2>
        <p><strong>Kullanıcı:</strong> <?php echo wp_get_current_user()->display_name; ?></p>
        <p><strong>Versiyon:</strong> <?php echo HIZLI_KASA_VERSION; ?></p>
    </div>

</div>

<script>
// Bu script sadece settings sekmesi yüklendiğinde çalışacak şekilde HK.AppNavigation tarafından yönetilmelidir.
// Ancak basitlik adına buraya ekliyoruz, JS tarafında delegasyon yapılacak.
</script>
