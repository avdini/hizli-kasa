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
            <p style="color: var(--hk-text-muted); margin: 5px 0 0 0; font-size: 13px;">Şubeler arası stok transferi, ürün talebi ve çıkış işlemleri (Geliştirme Aşamasında).</p>
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
        <div style="background: var(--hk-bg-card); border-radius: 12px; padding: 40px; text-align: center; border: 2px dashed var(--hk-border); box-shadow: 0 4px 15px rgba(0,0,0,0.05);">
            <span style="font-size: 48px; margin-bottom: 15px; display: block;">📊</span>
            <h3 style="margin-top:0; color:var(--hk-accent);">Gelişmiş Sevk Takip Paneli (Çok Yakında)</h3>
            <p style="color:var(--hk-text-muted); font-size:15px; max-width: 600px; margin: 0 auto; line-height: 1.6;">
                Bu alanda şubeler arası tüm sevk hareketlerini, bekleyen/yolda/tamamlanan transferleri grafiklerle ve gelişmiş filtrelerle detaylı bir şekilde takip edebileceğiniz bir kontrol paneli planlıyoruz.
            </p>
        </div>
    </div>

    <!-- 2. SEVK KABUL SEKMESİ -->
    <div id="sevk-kabul" class="sevk-icerik-paneli" style="display: none;">
        <div style="background: var(--hk-bg-card); border-radius: 12px; padding: 40px; text-align: center; border: 2px dashed var(--hk-border); box-shadow: 0 4px 15px rgba(0,0,0,0.05);">
            <span style="font-size: 48px; margin-bottom: 15px; display: block;">📥</span>
            <h3 style="margin-top:0; color:var(--hk-accent);">Sevk Kabul ve Onay İşlemleri (Çok Yakında)</h3>
            <p style="color:var(--hk-text-muted); font-size:15px; max-width: 600px; margin: 0 auto; line-height: 1.6;">
                Burada, şubenize gelen kolileri açarken içinden çıkan ürünleri tek tek barkod okutarak sisteme girecek ve gönderen şubenin/deponun çıkış yaptığı sayılarla sizin teslim aldıklarınızın uyuşup uyuşmadığını milimetrik olarak kontrol edebileceğiniz bir sistem üzerinde çalışıyoruz.
            </p>
        </div>
    </div>

    <!-- 3. SEVK İSTE SEKMESİ -->
    <div id="sevk-iste" class="sevk-icerik-paneli" style="display: none;">
        <div style="background: var(--hk-bg-card); border-radius: 12px; padding: 40px; text-align: center; border: 2px dashed var(--hk-border); box-shadow: 0 4px 15px rgba(0,0,0,0.05);">
            <span style="font-size: 48px; margin-bottom: 15px; display: block;">📤</span>
            <h3 style="margin-top:0; color:var(--hk-accent);">Şubeler Arası Ürün Talebi (Çok Yakında)</h3>
            <p style="color:var(--hk-text-muted); font-size:15px; max-width: 600px; margin: 0 auto; line-height: 1.6;">
                Stoğunuzda tükenen ürünler için merkezden veya çevre şubelerden kolayca talep oluşturabilmeniz adına pratik bir sipariş modülü hazırlıyoruz. İhtiyacınız olan ürünleri aratarak veya okutarak hızlıca "Sevk İste" listesine ekleyebileceksiniz.
            </p>
        </div>
    </div>

    <!-- 4. SEVK ÇIKIŞ SEKMESİ -->
    <div id="sevk-cikis" class="sevk-icerik-paneli" style="display: none;">
        <div style="background: var(--hk-bg-card); border-radius: 12px; padding: 40px; text-align: center; border: 2px dashed var(--hk-border); box-shadow: 0 4px 15px rgba(0,0,0,0.05);">
            <span style="font-size: 48px; margin-bottom: 15px; display: block;">📦</span>
            <h3 style="margin-top:0; color:var(--hk-accent);">Sevk Çıkış ve Barkod Kontrolü (Çok Yakında)</h3>
            <p style="color:var(--hk-text-muted); font-size:15px; max-width: 600px; margin: 0 auto; line-height: 1.6;">
                Diğer şubelere ürün gönderirken paketlediğiniz ürünleri barkod okutarak "Sevk Listesi"ne alacak ve yola çıkarabileceksiniz. Karşı taraf da bu sayede yoldaki ürünleri eksiksiz takip edebilecek.
            </p>
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
// Event Delegation ile sekme geçişi (Ajax ile yüklense bile çalışır)
// window nesnesinde event listener varsa tekrar eklememek için kontrol
if (!window.sevkTabEventsBound) {
    document.body.addEventListener('click', function(e) {
        if (e.target.classList.contains('sevk-alt-btn')) {
            const btn = e.target;
            const container = btn.closest('.hk-tab-container');
            
            if (container) {
                // Aktif butonu güncelle
                container.querySelectorAll('.sevk-alt-btn').forEach(b => b.classList.remove('aktif'));
                btn.classList.add('aktif');

                // Hedef paneli göster
                const targetId = btn.getAttribute('data-target');
                container.querySelectorAll('.sevk-icerik-paneli').forEach(panel => {
                    panel.style.display = 'none';
                    panel.classList.remove('aktif');
                });
                
                const targetPanel = container.querySelector('#' + targetId);
                if(targetPanel) {
                    targetPanel.style.display = 'block';
                    targetPanel.classList.add('aktif');
                }
            }
        }
    });
    window.sevkTabEventsBound = true;
}
</script>