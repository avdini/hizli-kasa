<?php if (!defined('ABSPATH')) exit; ?>
<?php 
$current_user_id = get_current_user_id();
$user_theme = get_user_meta($current_user_id, '_hizli_kasa_tema', true) ?: 'light'; 
?>
<div class="terminal-ayarlar-konteyner hk-tab-scrollable-container" style="padding: 30px; max-width: 900px; margin: 0 auto; color: var(--hk-text-main); height: 100%; overflow-y: auto; box-sizing: border-box;">
    
    <!-- Alt Sekme Navigasyonu -->
    <div class="ayarlar-alt-sekmeler" style="display: flex; gap: 15px; margin-bottom: 25px; border-bottom: 2px solid var(--hk-border); padding-bottom: 5px;">
        <button class="ayarlar-alt-btn aktif" data-target="ayarlar-genel" style="padding: 12px 20px; border: none; background: none; font-size: 16px; font-weight: bold; cursor: pointer; color: var(--hk-text-muted); border-bottom: 3px solid transparent; transition: all 0.3s ease;">
            ⚙️ Genel Ayarlar
        </button>
        <button class="ayarlar-alt-btn" data-target="ayarlar-yazdirma" style="padding: 12px 20px; border: none; background: none; font-size: 16px; font-weight: bold; cursor: pointer; color: var(--hk-text-muted); border-bottom: 3px solid transparent; transition: all 0.3s ease;">
            🖨️ Yazdırma Ayarları
        </button>
        <button class="ayarlar-alt-btn" data-target="ayarlar-ses" style="padding: 12px 20px; border: none; background: none; font-size: 16px; font-weight: bold; cursor: pointer; color: var(--hk-text-muted); border-bottom: 3px solid transparent; transition: all 0.3s ease;">
            🔊 Ses Ayarları
        </button>
    </div>

    <!-- 1. GENEL AYARLAR PANELI -->
    <div id="ayarlar-genel" class="ayarlar-icerik-paneli aktif">
        <div style="background: var(--hk-bg-card); padding: 30px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); margin-bottom: 20px; border: 1px solid var(--hk-border);">
            <h2 style="margin-top: 0; border-bottom: 2px solid var(--hk-border); padding-bottom: 10px; margin-bottom: 20px; font-size: 20px;">Görünüm Ayarları</h2>
            
            <div class="ayar-satir" style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 20px;">
                <div style="flex: 1; min-width: 250px;">
                    <strong style="display: block; font-size: 16px;">Görünüm Teması</strong>
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

        <div style="background: var(--hk-bg-card); padding: 30px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); border: 1px solid var(--hk-border);">
            <h2 style="margin-top: 0; border-bottom: 2px solid var(--hk-border); padding-bottom: 10px; margin-bottom: 20px; font-size: 20px;">Sistem Bilgileri</h2>
            <p style="margin: 8px 0; font-size: 15px;"><strong>Kullanıcı:</strong> <?php echo wp_get_current_user()->display_name; ?></p>
            <p style="margin: 8px 0; font-size: 15px;"><strong>Versiyon:</strong> <?php echo HIZLI_KASA_VERSION; ?></p>
        </div>
    </div>

    <!-- 2. YAZDIRMA AYARLARI PANELI -->
    <div id="ayarlar-yazdirma" class="ayarlar-icerik-paneli" style="display: none;">
        <div style="background: var(--hk-bg-card); padding: 30px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); border: 1px solid var(--hk-border);">
            <h2 style="margin-top: 0; border-bottom: 2px solid var(--hk-border); padding-bottom: 10px; margin-bottom: 20px; font-size: 20px;">Yerel Yazdırma Yardımcısı (Local Print Helper)</h2>
            <p style="color: var(--hk-text-muted); font-size: 14px; margin-bottom: 20px;">Bu ayarlar sadece bu bilgisayar/tarayıcı için geçerlidir. Farklı bilgisayarlarda (kasalarda) farklı yazıcılar seçebilirsiniz. Tüm veriler yerel hafızada (localStorage) saklanır.</p>

            <!-- Sessiz Yazdırma Aktif/Pasif Toggle -->
            <div style="display: flex; align-items: center; justify-content: space-between; padding: 15px; margin-bottom: 20px; border-radius: 8px; background: var(--hk-bg-body); border: 1px solid var(--hk-border);">
                <div>
                    <strong style="display: block; font-size: 16px;">Sessiz Yazdırmayı Aktif Et (Sürücü Modu)</strong>
                    <span style="color: var(--hk-text-muted); font-size: 13px;">Tarayıcının yazdırma penceresini atlayarak doğrudan yerel servise gönderir.</span>
                </div>
                <div>
                    <input type="checkbox" id="hk-silent-print-enabled" style="width: 22px; height: 22px; cursor: pointer; vertical-align: middle;">
                </div>
            </div>

            <!-- Yardımcı Konfigürasyon Alanı (Sessiz yazdırma aktifken görünür) -->
            <div id="hk-helper-config-container" style="display: none;">
                <!-- Durum Paneli -->
                <div id="hk-helper-status-card" style="padding: 20px; margin-bottom: 25px; border-radius: 8px; border-left: 5px solid #d63638; background: var(--hk-bg-body); border-top: 1px solid var(--hk-border); border-right: 1px solid var(--hk-border); border-bottom: 1px solid var(--hk-border);">
                    <h3 style="margin-top: 0; font-size: 18px;" id="hk-status-title">Durum: Bağlantı Kuruluyor...</h3>
                    <p id="hk-status-desc" style="margin-bottom: 0; color: var(--hk-text-muted); font-size: 14px;">Yerel yazdırma servisi kontrol ediliyor.</p>
                    <div id="hk-download-action" style="display: none; margin-top: 15px;">
                        <a href="<?php echo esc_url(HIZLI_KASA_URL . 'assets/bin/hizli-kasa-print-helper.exe'); ?>" download="hizli-kasa-print-helper.exe" class="hk-btn-primary" style="display: inline-block; padding: 10px 20px; border-radius: 6px; font-weight: bold; text-decoration: none; background: var(--hk-accent); color: white; margin-right: 10px;">Yazdırma Yardımcısını İndir (.exe)</a>
                        <p style="color: var(--hk-text-muted); font-size: 13px; margin-top: 10px; line-height: 1.4;">İndirdiğiniz dosyayı çalıştırın. Arka planda sessizce çalışacaktır (saat simgesinin yanında yeşil yazıcı simgesi ile görünür). Sistem tepsi menüsünden "Windows ile Birlikte Başlat" seçeneğini aktif etmeniz tavsiye edilir.</p>
                    </div>
                </div>

                <!-- Eşleştirme ve Ayarlar Formu -->
                <div id="hk-settings-form-wrapper" style="display: none;">
                    <div style="display: grid; grid-template-columns: 1fr; gap: 20px; margin-bottom: 25px;">
                        
                        <!-- Güvenlik Token -->
                        <div style="display: flex; flex-direction: column; gap: 8px;">
                            <label style="font-weight: bold; font-size: 15px;">Güvenlik Token'ı</label>
                            <div style="display: flex; gap: 10px;">
                                <input type="text" id="hk-print-token" readonly style="flex: 1; padding: 12px; border: 1px solid var(--hk-border); border-radius: 6px; background: var(--hk-bg-body); color: var(--hk-text-muted); font-family: monospace; font-size: 14px;">
                                <button type="button" id="hk-pair-btn" style="padding: 0 20px; border: 1px solid var(--hk-border); border-radius: 6px; background: var(--hk-bg-body); color: var(--hk-text-main); font-weight: bold; cursor: pointer; transition: all 0.3s ease;">Yeniden Eşleştir</button>
                            </div>
                            <span style="color: var(--hk-text-muted); font-size: 13px;">Bilgisayarınızdaki yerel yazıcı servisinin sadece bu siteden gelen yazdırma isteklerini kabul etmesi için kullanılan benzersiz anahtardır.</span>
                        </div>

                        <!-- Fiş Yazıcısı -->
                        <div style="display: flex; flex-direction: column; gap: 8px;">
                            <label for="hk-receipt-printer" style="font-weight: bold; font-size: 15px;">Fiş Yazıcısı (Termal)</label>
                            <select id="hk-receipt-printer" style="padding: 12px; border: 1px solid var(--hk-border); border-radius: 6px; background: var(--hk-bg-body); color: var(--hk-text-main); font-size: 15px;">
                                <option value="">-- Yazıcı Seçin --</option>
                            </select>
                            <span style="color: var(--hk-text-muted); font-size: 13px;">Satış sonrası adisyon ve fişlerin otomatik basılacağı termal yazıcı.</span>
                        </div>

                        <!-- Barkod Yazıcısı -->
                        <div style="display: flex; flex-direction: column; gap: 8px;">
                            <label for="hk-barcode-printer" style="font-weight: bold; font-size: 15px;">Barkod Yazıcısı (Etiket)</label>
                            <select id="hk-barcode-printer" style="padding: 12px; border: 1px solid var(--hk-border); border-radius: 6px; background: var(--hk-bg-body); color: var(--hk-text-main); font-size: 15px;">
                                <option value="">-- Yazıcı Seçin --</option>
                            </select>
                            <span style="color: var(--hk-text-muted); font-size: 13px;">Barkod basım modüllerinden gönderilen etiketlerin basılacağı yazıcı.</span>
                        </div>

                        <!-- Rapor Yazıcısı -->
                        <div style="display: flex; flex-direction: column; gap: 8px;">
                            <label for="hk-report-printer" style="font-weight: bold; font-size: 15px;">Rapor Yazıcısı</label>
                            <select id="hk-report-printer" style="padding: 12px; border: 1px solid var(--hk-border); border-radius: 6px; background: var(--hk-bg-body); color: var(--hk-text-main); font-size: 15px;">
                                <option value="">-- Yazıcı Seçin --</option>
                            </select>
                            <span style="color: var(--hk-text-muted); font-size: 13px;">Gün sonu ve detaylı raporların basılacağı yazıcı.</span>
                        </div>

                    </div>
                </div>
            </div>
            
            <div style="display: flex; align-items: center; gap: 15px; border-top: 1px solid var(--hk-border); padding-top: 20px;">
                <button type="button" id="hk-save-print-settings" style="padding: 12px 25px; border: none; border-radius: 6px; background: var(--hk-accent); color: white; font-weight: bold; cursor: pointer; font-size: 15px; transition: all 0.3s ease;">Yazdırma Ayarlarını Kaydet</button>
                <span id="hk-save-success-msg" style="color: #00a32a; font-weight: bold; display: none; font-size: 15px;">✓ Ayarlar kaydedildi!</span>
            </div>
        </div>
    </div>

    <!-- 3. SES AYARLARI PANELI -->
    <div id="ayarlar-ses" class="ayarlar-icerik-paneli" style="display: none;">
        <div style="background: var(--hk-bg-card); padding: 30px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); border: 1px solid var(--hk-border);">
            <h2 style="margin-top: 0; border-bottom: 2px solid var(--hk-border); padding-bottom: 10px; margin-bottom: 20px; font-size: 20px;">Bildirim Sesleri</h2>
            <p style="color: var(--hk-text-muted); font-size: 14px; margin-bottom: 20px;">Terminal bildirim seslerinin seviyesini ve çeşidini özelleştirin. Bu ayarlar hesabınıza kaydedilir.</p>

            <div style="display: grid; grid-template-columns: 1fr; gap: 20px; margin-bottom: 25px;">
                <div style="display: flex; flex-direction: column; gap: 8px;">
                    <label for="hk-sound-volume" style="font-weight: bold; font-size: 15px; display: flex; justify-content: space-between;">
                        <span>Ses Seviyesi</span>
                        <span id="hk-sound-volume-val">80%</span>
                    </label>
                    <input type="range" id="hk-sound-volume" min="0" max="100" value="80" style="width: 100%; height: 6px; border-radius: 3px; cursor: pointer; accent-color: var(--hk-accent);">
                </div>

                <div style="display: flex; flex-direction: column; gap: 8px;">
                    <label for="hk-sound-preset" style="font-weight: bold; font-size: 15px;">Ses Efekti</label>
                    <select id="hk-sound-preset" style="padding: 12px; border: 1px solid var(--hk-border); border-radius: 6px; background: var(--hk-bg-body); color: var(--hk-text-main); font-size: 15px;">
                        <option value="classic">Klasik Bip (Varsayılan)</option>
                        <option value="soft">Yumuşak Melodi (Market Kasa)</option>
                        <option value="retro">Retro Atari (Oyun Tarzı)</option>
                        <option value="digital">Dijital Çift Bip</option>
                        <option value="sharp_click">Keskin Klik (Tarayıcı Tipi)</option>
                        <option value="high_alert">Keskin Alarm (Yüksek Frekans)</option>
                    </select>
                </div>

                <div style="display: flex; gap: 15px; margin-top: 10px;">
                    <button type="button" id="hk-test-success-sound" style="flex: 1; padding: 12px; border: 1px solid var(--hk-border); border-radius: 6px; background: var(--hk-bg-body); color: var(--hk-text-main); font-weight: bold; cursor: pointer; transition: all 0.2s;">
                        🟢 Başarılı Sesi Test Et
                    </button>
                    <button type="button" id="hk-test-error-sound" style="flex: 1; padding: 12px; border: 1px solid var(--hk-border); border-radius: 6px; background: var(--hk-bg-body); color: var(--hk-text-main); font-weight: bold; cursor: pointer; transition: all 0.2s;">
                        🔴 Hata Sesi Test Et
                    </button>
                </div>
            </div>

            <div style="display: flex; align-items: center; gap: 15px; border-top: 1px solid var(--hk-border); padding-top: 20px;">
                <button type="button" id="hk-save-sound-settings" style="padding: 12px 25px; border: none; border-radius: 6px; background: var(--hk-accent); color: white; font-weight: bold; cursor: pointer; font-size: 15px; transition: all 0.3s ease;">Ses Ayarlarını Kaydet</button>
                <span id="hk-sound-save-success-msg" style="color: #00a32a; font-weight: bold; display: none; font-size: 15px;">✓ Ayarlar kaydedildi!</span>
            </div>
        </div>
    </div>
</div>

<style>
.ayarlar-alt-btn.aktif {
    color: var(--hk-accent) !important;
    border-bottom-color: var(--hk-accent) !important;
}
.ayarlar-alt-btn:hover {
    color: var(--hk-text-main) !important;
}
</style>
