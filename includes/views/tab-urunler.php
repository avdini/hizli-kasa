<?php 
if (!defined('ABSPATH')) exit; 

$user_id = get_current_user_id();
$depo_id = get_user_meta($user_id, '_hizli_kasa_depo_id', true);

global $wpdb;
$depo_name = "Depo Seçilmedi";
if ($depo_id) {
    $depo_name = $wpdb->get_var($wpdb->prepare("SELECT name FROM {$wpdb->prefix}hizli_kasa_depolar WHERE id = %d", $depo_id));
}
?>
<div id="stok-terminali">
    <!-- Üst Bar: Depo Bilgisi ve Arama -->
    <div class="terminal-header">
        <div class="depo-bilgi">
            <span class="ikon">🏢</span>
            <div>
                <label>Aktif Depo</label>
                <h3 id="aktif-depo-adi"><?php echo esc_html($depo_name ?: "Bilinmeyen Depo"); ?></h3>
            </div>
        </div>
        <div class="arama-kutusu">
            <input type="text" id="terminal-arama-input" placeholder="Ürün adı veya barkod okutun..." autocomplete="off">
            <span class="arama-ikon">🔍</span>
        </div>
    </div>

    <!-- Ana İçerik: Ürün Listesi -->
    <div class="terminal-body" id="terminal-urun-listesi">
        <?php if (!$depo_id): ?>
            <div class="terminal-uyari">
                <span style="font-size: 48px;">⚠️</span>
                <h3>Profilinize bir depo atanmamış!</h3>
                <p>İşlem yapabilmek için yöneticinizden size bir depo atamasını isteyin.</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Hata ve Debug Paneli -->
    <div id="terminal-debug" style="display:none; margin:10px; padding:10px; background:#f8d7da; color:#721c24; border-radius:4px; font-family:monospace; font-size:12px;">
        <strong>Hata Detayı:</strong> <span id="debug-error-msg"></span>
        <br><strong>API URL:</strong> <span id="debug-api-url"></span>
    </div>

    <!-- Alt Bar: İstatistikler (Opsiyonel) -->
    <div class="terminal-footer">
        <div class="stat-item">
            <span id="toplam-urun-sayisi">0</span>
            <label>Kayıtlı Ürün</label>
        </div>
        <div class="stat-item">
            <span id="kritik-stok-sayisi">0</span>
            <label>Kritik Stok</label>
        </div>
    </div>
</div>

<!-- Stok Düzenleme Modalı -->
<div id="stok-duzenle-modal" class="modal-cerceve" style="display:none;">
    <div class="modal-icerik glass">
        <h3 id="modal-urun-adi">Ürün Adı</h3>
        <p id="modal-urun-detay">SKU: ---</p>
        
        <div class="stok-kontrol-grup">
            <div class="mevcut-stok">
                <label>Mevcut Stok</label>
                <span id="modal-mevcut-qty">0</span>
            </div>
            <div class="degisim-input">
                <label>Değişim Miktarı</label>
                <div class="input-row">
                    <button class="btn-eksilt">-</button>
                    <input type="number" id="modal-degisim-input" value="1" step="0.01">
                    <button class="btn-artir">+</button>
                </div>
            </div>
        </div>

        <div class="modal-butonlar">
            <button id="stok-kaydet-iptal" class="btn-secondary">İptal</button>
            <button id="stok-kaydet-onay" class="btn-primary">Hareketi Kaydet</button>
        </div>
    </div>
</div>
