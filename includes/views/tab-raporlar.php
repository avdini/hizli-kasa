<?php if (!defined('ABSPATH')) exit; ?>
<div class="hk-tab-container" style="padding: 20px; height: 100%; overflow-y: auto; box-sizing: border-box;">
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
        <button class="rapor-alt-btn aktif" data-target="rapor-tum-siparisler">🛍️ Tüm Siparişler</button>
        <button class="rapor-alt-btn" data-target="rapor-iade-listesi">🔙 İadeler</button>
        <button class="rapor-alt-btn" data-target="rapor-siparis-duzenleme">✏️ Sipariş Düzenlemeleri</button>
        <button class="rapor-alt-btn" data-target="rapor-ozet-istatistik">📈 Özel İstatistikler (Yakında)</button>
    </div>

    <!-- Rapor İçerik Alanları -->
    <div id="rapor-tum-siparisler" class="rapor-icerik-paneli aktif">
        <div style="background: var(--hk-bg-card); border-radius: 12px; padding: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                <h3 style="margin:0; color:var(--hk-accent);">Tüm Kasa Siparişleri</h3>
                <input type="text" id="order-search-input" class="hk-input" placeholder="Sipariş ID veya Ürün Ara..." style="width:250px;">
            </div>
            
            <table class="gs-tablo" id="all-orders-table">
                <thead>
                    <tr>
                        <th>Tarih/Saat</th>
                        <th>Sipariş ID</th>
                        <th>Kasiyer / Kasa</th>
                        <th>Ürünler</th>
                        <th>Toplam</th>
                        <th>Detay</th>
                    </tr>
                </thead>
                <tbody id="all-orders-body">
                    <tr><td colspan="6" style="text-align:center; padding:40px;">Sorgulama yapın...</td></tr>
                </tbody>
            </table>
            <div id="all-orders-pagination" class="hk-pagination"></div>
        </div>
    </div>

    <div id="rapor-iade-listesi" class="rapor-icerik-paneli">
        <div style="background: var(--hk-bg-card); border-radius: 12px; padding: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                <h3 style="margin:0; color:var(--hk-accent);">İade Kayıtları</h3>
                <input type="text" id="refund-search-input" class="hk-input" placeholder="İade Ara..." style="width:250px;">
            </div>
            
            <table class="gs-tablo" id="refund-list-table">
                <thead>
                    <tr>
                        <th>Tarih/Saat</th>
                        <th>İade ID</th>
                        <th>Kasiyer / Kasa</th>
                        <th>İade Edilen Ürünler</th>
                        <th>Toplam Tutar</th>
                        <th>Detay</th>
                    </tr>
                </thead>
                <tbody id="refund-list-body">
                    <tr><td colspan="6" style="text-align:center; padding:40px;">Sorgulama yapın...</td></tr>
                </tbody>
            </table>
            <div id="refund-list-pagination" class="hk-pagination"></div>
        </div>
    </div>

    <div id="rapor-siparis-duzenleme" class="rapor-icerik-paneli">
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

    <!-- Raporlar İçin İzole Fiş Şablonu (Sadece Yazdırma İçin) -->
    <div id="report-fis-sablon" style="display:none; color:#000;">
        <div style="text-align:center; margin-bottom:10px; border-bottom:1px solid #000; padding-bottom:10px;">
            <h2 style="margin:0; font-size:18px;"><?php echo get_bloginfo('name'); ?></h2>
            <p id="report-fis-subtitle" style="margin:5px 0; font-size:12px;">HIZLI KASA SATIŞ FİŞİ</p>
            <p id="report-fis-tarih" style="margin:0; font-size:11px;"></p>
            <p id="report-fis-no-text" style="font-weight:bold; margin:5px 0; font-size:14px;"></p>
            <div style="text-align:center; margin-bottom:10px;">
                <svg id="report-fis-barkod" style="max-width:100%; height:auto;"></svg>
            </div>
        </div>

        <table style="width:100%; border-collapse:collapse; font-size:12px; margin-bottom:10px;">
            <thead>
                <tr style="border-bottom:1px solid #000;">
                    <th style="text-align:left; padding:5px 0;">Ürün</th>
                    <th style="text-align:right; padding:5px 0;">Toplam</th>
                </tr>
            </thead>
            <tbody id="report-fis-urunler-body"></tbody>
        </table>

        <div style="border-top:1px solid #000; padding-top:10px; font-size:13px;">
            <div style="display:flex; justify-content:space-between; margin-bottom:3px;" id="report-fis-liste-toplami-satiri">
                <span>ETİKET TOPLAMI:</span>
                <span id="report-fis-liste-toplami-tutar"></span>
            </div>
            <div style="display:flex; justify-content:space-between; margin-bottom:3px;" id="report-fis-ara-toplam-satiri">
                <span>ARA TOPLAM:</span>
                <span id="report-fis-ara-toplam-tutar"></span>
            </div>
            <div style="display:flex; justify-content:space-between; margin-bottom:3px;" id="report-fis-otomatik-indirim-satiri">
                <span id="report-fis-otomatik-indirim-etiket">İNDİRİM:</span>
                <span id="report-fis-otomatik-indirim-tutar"></span>
            </div>
            <div style="display:flex; justify-content:space-between; margin-bottom:3px;" id="report-fis-iskonto-satiri">
                <span>İSKONTO:</span>
                <span id="report-fis-iskonto-tutar"></span>
            </div>
            <div style="display:flex; justify-content:space-between; font-weight:bold; font-size:16px; margin-top:5px;">
                <span>TOPLAM:</span>
                <span id="report-fis-genel-toplam"></span>
            </div>
        </div>

        <div id="report-fis-refund-note" style="text-align:center; margin-top:12px; font-size:11px; display:none;">
            Bu fiş güncel durumu gösterir (iade/düzenlemeler dahil).
        </div>
        <div id="report-fis-adjustments" style="display:none; margin-top:8px; font-size:11px; border-top:1px solid #000; padding-top:8px;">
            <div style="display:flex; justify-content:space-between;">
                <span>İade/Düzenleme Etkisi:</span>
                <span id="report-fis-adjustments-total"></span>
            </div>
        </div>
    </div>
</div>

