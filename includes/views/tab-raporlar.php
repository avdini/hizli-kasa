<?php if (!defined('ABSPATH')) exit; ?>
<div class="hk-tab-container" style="padding: 20px;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; border-bottom: 2px solid var(--hk-border); padding-bottom: 15px;">
        <h2 style="margin:0;">📊 Detaylı Raporlar</h2>
        <div class="rapor-filtre-bar" style="display:flex; gap:10px;">
            <input type="date" id="rapor-tarih-bas" class="hk-input" value="<?php echo date('Y-m-d'); ?>">
            <input type="date" id="rapor-tarih-bit" class="hk-input" value="<?php echo date('Y-m-d'); ?>">
            <button id="rapor-yenile" class="hk-btn-primary">Sorgula</button>
        </div>
    </div>

    <!-- Alt Sekme Navigasyonu -->
    <div class="rapor-alt-sekmeler" style="display:flex; gap:10px; margin-bottom:20px;">
        <button class="rapor-alt-btn aktif" data-target="rapor-siparis-duzenleme">✏️ Sipariş Düzenlemeleri</button>
        <button class="rapor-alt-btn" data-target="rapor-ozet-istatistik">📈 Özet İstatistikler (Yakında)</button>
    </div>

    <!-- Rapor İçerik Alanları -->
    <div id="rapor-siparis-duzenleme" class="rapor-icerik-paneli aktif">
        <div style="background: var(--hk-bg-card); border-radius: 12px; padding: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
            <h3 style="margin-top:0; color:var(--hk-accent);">Sipariş Müdahaleleri ve Denetim Kayıtları</h3>
            <p style="color:var(--hk-text-muted); font-size:14px; margin-bottom:20px;">Kasiyerler tarafından yapılan miktar azaltma, ürün silme ve ödeme yöntemi değişiklikleri burada listelenir.</p>
            
            <table class="gs-tablo" id="edit-logs-table">
                <thead>
                    <tr>
                        <th>Tarih/Saat</th>
                        <th>Kasiyer</th>
                        <th>Sipariş</th>
                        <th>Kasa</th>
                        <th>Yapılan Değişiklikler</th>
                    </tr>
                </thead>
                <tbody id="edit-logs-body">
                    <tr><td colspan="5" style="text-align:center; padding:40px;">Veriler yükleniyor...</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <div id="rapor-ozet-istatistik" class="rapor-icerik-paneli" style="display:none;">
        <div style="text-align:center; padding:50px;">
            <div style="font-size:48px;">📈</div>
            <h3>Gelişmiş İstatistikler</h3>
            <p>Satış grafiklerini ve performans analizlerini içeren bu modül bir sonraki güncelleme ile eklenecektir.</p>
        </div>
    </div>
</div>

<style>
.rapor-alt-btn {
    padding: 10px 20px;
    border: 1px solid var(--hk-border);
    border-radius: 8px;
    background: var(--hk-bg-body);
    color: var(--hk-text-main);
    cursor: pointer;
    font-weight: bold;
    transition: all 0.2s;
}
.rapor-alt-btn.aktif {
    background: var(--hk-accent);
    color: white;
    border-color: var(--hk-accent);
}
.rapor-alt-btn:hover:not(.aktif) {
    background: var(--hk-bg-hover);
}
.rapor-icerik-paneli {
    display: none;
}
.rapor-icerik-paneli.aktif {
    display: block;
}
#edit-logs-table {
    width: 100%;
    border-collapse: collapse;
}
#edit-logs-table th {
    text-align: left;
    padding: 12px;
    background: var(--hk-bg-body);
    border-bottom: 2px solid var(--hk-border);
}
#edit-logs-table td {
    padding: 12px;
    border-bottom: 1px solid var(--hk-border);
    font-size: 14px;
}
.log-change-item {
    display: inline-block;
    padding: 2px 8px;
    background: #fff3cd;
    color: #856404;
    border-radius: 4px;
    font-size: 12px;
    margin-right: 5px;
    margin-bottom: 5px;
}
</style>
