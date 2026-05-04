<?php
/**
 * Hızlı Kasa - Sevk Sekmesi
 */
if (!defined('ABSPATH'))
    exit;
?>

<div class="hk-tab-container" style="padding: 20px; height: 100%; overflow-y: auto; box-sizing: border-box;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; border-bottom: 2px solid var(--hk-border); padding-bottom: 15px;">
        <div>
            <h2 style="margin:0; color: var(--hk-text-main); display: flex; align-items: center; gap: 10px;">🚚 Depo ve Sevk Yönetimi</h2>
            <p style="color: var(--hk-text-muted); margin: 5px 0 0 0; font-size: 13px;">Şubeler arası stok transferi, ürün talebi ve çıkış işlemleri.</p>
        </div>
    </div>

    <!-- Alt Sekme Navigasyonu -->
    <div class="sevk-alt-sekmeler" style="display:flex; gap:10px; margin-bottom:20px;">
        <button class="sevk-alt-btn aktif" data-target="sevk-genel">📋 Genel</button>
        <button class="sevk-alt-btn" data-target="sevk-kabul">📥 Sevk Kabul</button>
        <button class="sevk-alt-btn" data-target="sevk-iste">📤 Sevk İste</button>
        <button class="sevk-alt-btn" data-target="sevk-cikis">📦 Sevk Çıkış</button>
    </div>

    <!-- 1. GENEL SEKMESİ -->
    <div id="sevk-genel" class="sevk-icerik-paneli aktif">
        <div style="background: var(--hk-bg-card); border-radius: 12px; padding: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                <h3 style="margin:0; color:var(--hk-accent);">Tüm Sevk İşlemleri</h3>
                <div style="display:flex; gap:10px;">
                    <select class="hk-input" id="sevk-filtre-durum">
                        <option value="all">Tüm Durumlar</option>
                        <option value="pending">Bekliyor</option>
                        <option value="transit">Yolda</option>
                        <option value="completed">Tamamlandı</option>
                    </select>
                </div>
            </div>
            
            <table class="gs-tablo" id="sevk-listesi-tablosu">
                <thead>
                    <tr>
                        <th>Tarih</th>
                        <th>Sevk No</th>
                        <th>Gönderen</th>
                        <th>Alıcı</th>
                        <th>Durum</th>
                        <th>İşlem</th>
                    </tr>
                </thead>
                <tbody>
                    <tr><td colspan="6" style="text-align:center; padding:40px;">Veriler yükleniyor...</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- 2. SEVK KABUL SEKMESİ -->
    <div id="sevk-kabul" class="sevk-icerik-paneli" style="display: none;">
        <div style="background: var(--hk-bg-card); border-radius: 12px; padding: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
            <h3 style="margin-top:0; color:var(--hk-accent);">Gelen Sevkleri Kabul Et</h3>
            <p style="color:var(--hk-text-muted); font-size:14px;">Şubenize yola çıkmış olan sevkleri buradan onaylayarak stoğunuza katabilirsiniz.</p>
            <div style="padding: 40px; text-align: center; border: 2px dashed var(--hk-border); border-radius: 8px; margin-top: 20px;">
                <span style="font-size: 32px; margin-bottom: 10px; display: block;">📦</span>
                <p>Şu an bekleyen gelen sevk bulunmuyor.</p>
            </div>
        </div>
    </div>

    <!-- 3. SEVK İSTE SEKMESİ -->
    <div id="sevk-iste" class="sevk-icerik-paneli" style="display: none;">
        <div style="background: var(--hk-bg-card); border-radius: 12px; padding: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
            <h3 style="margin-top:0; color:var(--hk-accent);">Yeni Sevk Talebi Oluştur</h3>
            <p style="color:var(--hk-text-muted); font-size:14px;">Diğer depolardan veya merkezden ürün talebinde bulunun.</p>
            <div style="display: flex; gap: 15px; margin-top: 20px;">
                <select class="hk-input" style="flex: 1;">
                    <option value="">Hedef Depo Seçin...</option>
                </select>
                <input type="text" class="hk-input" placeholder="Barkod okutun veya ürün arayın..." style="flex: 2;">
                <button class="hk-btn-primary">Ürün Ekle</button>
            </div>
        </div>
    </div>

    <!-- 4. SEVK ÇIKIŞ SEKMESİ -->
    <div id="sevk-cikis" class="sevk-icerik-paneli" style="display: none;">
        <div style="background: var(--hk-bg-card); border-radius: 12px; padding: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
            <h3 style="margin-top:0; color:var(--hk-accent);">Sevk Çıkışı Yap</h3>
            <p style="color:var(--hk-text-muted); font-size:14px;">Başka bir şubeye elinizdeki stoklardan ürün gönderin.</p>
            <div style="display: flex; gap: 15px; margin-top: 20px;">
                <select class="hk-input" style="flex: 1;">
                    <option value="">Gönderilecek Depo Seçin...</option>
                </select>
                <input type="text" class="hk-input" placeholder="Gönderilecek ürün barkodu okutun..." style="flex: 2;">
                <button class="hk-btn-primary">Ürün Ekle</button>
            </div>
        </div>
    </div>
</div>

<style>
/* Sevk Sekmesi Özel CSS */
.sevk-alt-btn {
    padding: 10px 20px;
    border: 1px solid var(--hk-border);
    border-radius: 8px;
    background: var(--hk-bg-body);
    color: var(--hk-text-main);
    cursor: pointer;
    font-weight: 700;
    transition: all 0.2s;
}

.sevk-alt-btn.aktif {
    background: var(--hk-accent);
    color: #fff;
    border-color: var(--hk-accent);
}

.sevk-alt-btn:hover:not(.aktif) {
    background: var(--hk-bg-hover);
}

#hizli-kasa-app.theme-dark .sevk-alt-btn {
    background: #1e293b;
    border-color: #334155;
    color: #f1f5f9;
}
#hizli-kasa-app.theme-dark .sevk-alt-btn.aktif {
    background: var(--hk-accent);
    color: #fff;
    border-color: var(--hk-accent);
}
</style>

<script>
// Sevk sekmesi alt menü geçiş mantığı
document.addEventListener('DOMContentLoaded', function() {
    // Sayfa DOM yüklendiğinde ve tab-sevk çağrıldığında çalışır
    const sevkTabContainer = document.querySelector('.sevk-alt-sekmeler');
    if(sevkTabContainer) {
        document.querySelectorAll('.sevk-alt-btn').forEach(button => {
            button.addEventListener('click', function() {
                // Aktif butonu güncelle
                document.querySelectorAll('.sevk-alt-btn').forEach(btn => btn.classList.remove('aktif'));
                this.classList.add('aktif');

                // Hedef paneli göster
                const targetId = this.getAttribute('data-target');
                document.querySelectorAll('.sevk-icerik-paneli').forEach(panel => {
                    panel.style.display = 'none';
                    panel.classList.remove('aktif');
                });
                
                const targetPanel = document.getElementById(targetId);
                if(targetPanel) {
                    targetPanel.style.display = 'block';
                    targetPanel.classList.add('aktif');
                }
            });
        });
    }
});
</script>