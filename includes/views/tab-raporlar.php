<?php if (!defined('ABSPATH')) exit; ?>
<div class="hk-tab-container rapor-tab-container" style="padding: 0;">
    
    <!-- Reports Hub Ana Kapsayıcı -->
    <div id="rapor-hub-root"></div>

    <!-- Rapor İçerik Panelleri (Hub JS tarafından target alanına taşınacak) -->
    <div class="rhub-content-wrapper" style="display: none;">
        
        <div id="rapor-tum-siparisler" class="rapor-icerik-paneli">
            <div class="rapor-kart">
                <div class="rapor-kart-header">
                    <h3 class="rapor-kart-title">Tüm Kasa Siparişleri</h3>
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
                        <tr><td colspan="6" class="rapor-empty-td">Sorgulama yapın...</td></tr>
                    </tbody>
                </table>
                <div id="all-orders-pagination" class="hk-pagination"></div>
            </div>
        </div>

        <div id="rapor-internet-siparisleri" class="rapor-icerik-paneli">
            <div class="rapor-kart">
                <div class="rapor-kart-header">
                    <h3 class="rapor-kart-title">İnternet Siparişleri</h3>
                </div>
                
                <table class="gs-tablo" id="internet-orders-table">
                    <thead>
                        <tr>
                            <th>Tarih/Saat</th>
                            <th>Sipariş ID</th>
                            <th>Müşteri</th>
                            <th>Ürünler</th>
                            <th>Durum</th>
                            <th>Toplam</th>
                            <th>Detay</th>
                        </tr>
                    </thead>
                    <tbody id="internet-orders-body">
                        <tr><td colspan="7" class="rapor-empty-td">Sorgulama yapın...</td></tr>
                    </tbody>
                </table>
                <div id="internet-orders-pagination" class="hk-pagination"></div>
            </div>
        </div>

        <div id="rapor-iade-listesi" class="rapor-icerik-paneli">
            <div class="rapor-kart">
                <div class="rapor-kart-header">
                    <h3 class="rapor-kart-title">İade Kayıtları</h3>
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
                        <tr><td colspan="6" class="rapor-empty-td">Sorgulama yapın...</td></tr>
                    </tbody>
                </table>
                <div id="refund-list-pagination" class="hk-pagination"></div>
            </div>
        </div>

        <div id="rapor-siparis-duzenleme" class="rapor-icerik-paneli">
            <div class="rapor-kart">
                <h3 class="rapor-kart-title">Sipariş Müdahaleleri ve Denetim Kayıtları</h3>
                <p class="rapor-kart-desc" style="margin: 5px 0 15px 0; font-size: 12px; color: var(--hk-text-muted);">
                    Kasiyerler tarafından yapılan miktar azaltma, ürün silme ve ödeme yöntemi değişiklikleri burada listelenir.
                </p>
                
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
                        <tr><td colspan="5" class="rapor-empty-td">Veriler yükleniyor...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div id="rapor-gun-sonu-arsivi" class="rapor-icerik-paneli">
            <div class="rapor-kart">
                <div class="rapor-kart-header" style="margin-bottom: 15px;">
                    <div>
                        <h3 class="rapor-kart-title">Gün Sonu Raporları Arşivi</h3>
                        <p class="rapor-kart-sub" style="margin: 5px 0 0 0; font-size: 12px; color: var(--hk-text-muted);">Geçmiş tarihlere ait gün sonu raporlarını buradan görüntüleyebilir ve yazdırabilirsiniz.</p>
                    </div>
                </div>
                
                <table class="gs-tablo" id="day-end-history-table">
                    <thead>
                        <tr>
                            <th>Tarih</th>
                            <th>Sipariş Adeti</th>
                            <th>Toplam Satış</th>
                            <th>Toplam İade</th>
                            <th>Net Ciro</th>
                            <th>İşlemler</th>
                        </tr>
                    </thead>
                    <tbody id="day-end-history-body">
                        <tr><td colspan="6" class="rapor-empty-td">Veriler yükleniyor...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div id="rapor-ozet-istatistik" class="rapor-icerik-paneli" style="display:none;">
            <!-- İçerik HK.StatisticsDashboard tarafından dinamik olarak doldurulur -->
        </div>

        <div id="rapor-depo-sayimlari" class="rapor-icerik-paneli">
            <div class="rapor-kart">
                <h3 class="rapor-kart-title" style="margin-bottom: 5px;">Depo Sayım Geçmişi</h3>
                <p class="rapor-kart-desc" style="margin: 0 0 15px 0; font-size: 12px; color: var(--hk-text-muted);">Tamamlanan veya iptal edilen depo sayım seanslarının detayları ve envanter farkları burada listelenir.</p>
                
                <table class="gs-tablo" id="sayim-history-table">
                    <thead>
                        <tr>
                            <th>Tarih/Saat</th>
                            <th>Depo</th>
                            <th>Personel</th>
                            <th>Durum</th>
                            <th>Güncelleme Türü</th>
                            <th>Toplam Çeşit</th>
                            <th>Fark (Net)</th>
                            <th style="width: 100px; text-align: center;">Detay</th>
                        </tr>
                    </thead>
                    <tbody id="sayim-history-body">
                        <tr><td colspan="8" class="rapor-empty-td">Sorgulama yapın...</td></tr>
                    </tbody>
                </table>
            </div>
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
                <img id="report-fis-barkod" style="width: 100%; max-width: 240px; height: auto; margin: 0 auto; display: block;" />
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
