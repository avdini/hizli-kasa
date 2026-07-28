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

        <div id="rapor-urun-istatistik" class="rapor-icerik-paneli" style="display:none;">
            <!-- İçerik HK.ProductStatsReport tarafından dinamik olarak doldurulur -->
        </div>

        <div id="rapor-masraf-analiz" class="rapor-icerik-paneli" style="display:none;">
            <!-- İçerik HK.ExpenseReport tarafından dinamik olarak doldurulur -->
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


