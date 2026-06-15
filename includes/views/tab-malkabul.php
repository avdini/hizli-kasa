<?php
/**
 * Hızlı Kasa - Mal Kabul Sekmesi
 */
if (!defined('ABSPATH'))
    exit;
?>

<div class="hk-tab-container sevk-shell">
    <header class="sevk-header">
        <div>
            <h2>Mal Kabul & Tedarik İşlemleri</h2>
            <p>Tedarikçi yönetimi ve dışarıdan gelen ürünlerin kabul akışı.</p>
        </div>
        <div class="sevk-header-actions">
            <span class="sevk-live-dot"></span>
            <span id="malkabul-active-depo-label">Depo hazırlanıyor...</span>
        </div>
    </header>

    <nav class="sevk-alt-sekmeler" aria-label="Mal Kabul alt sekmeleri" id="malkabul-nav">
        <button class="sevk-alt-btn aktif" data-target="malkabul-siparisler">Alım Siparişleri</button>
        <button class="sevk-alt-btn" data-target="malkabul-yeni-siparis">Yeni Sipariş Oluştur</button>
        <button class="sevk-alt-btn" data-target="malkabul-tedarikciler">Tedarikçiler</button>
    </nav>

    <!-- Alım Siparişleri Listesi -->
    <section id="malkabul-siparisler" class="sevk-icerik-paneli aktif">
        <div class="sevk-filter-bar">
            <button type="button" id="malkabul-siparisler-yenile" class="sevk-btn secondary">Yenile</button>
        </div>
        <div class="sevk-table-wrap">
            <table class="sevk-table">
                <thead>
                    <tr>
                        <th>Sipariş No</th>
                        <th>Tedarikçi</th>
                        <th>Referans</th>
                        <th>Tarih</th>
                        <th>Durum</th>
                        <th>İşlem</th>
                    </tr>
                </thead>
                <tbody id="malkabul-siparisler-listesi">
                    <tr><td colspan="6" class="sevk-empty">Siparişler yükleniyor...</td></tr>
                </tbody>
            </table>
        </div>
    </section>

    <!-- Yeni Sipariş Oluşturma Ekranı -->
    <section id="malkabul-yeni-siparis" class="sevk-icerik-paneli" style="display:none;">
        <div class="sevk-wizard">
            <div class="sevk-form-grid">
                <label>
                    Tedarikçi Seçimi
                    <select id="malkabul-yeni-tedarikci" class="hk-input">
                        <option value="">Seçiniz...</option>
                    </select>
                </label>
                <label>
                    Referans / Fatura No
                    <input type="text" id="malkabul-yeni-referans" class="hk-input" placeholder="Opsiyonel">
                </label>
            </div>
            
            <div class="hk-malkabul-yeni-urun-ekle" style="margin-top: 20px;">
                <h4>Sipariş Kalemleri</h4>
                <div style="display: flex; gap: 10px; margin-bottom: 10px;">
                    <input type="text" id="malkabul-yeni-barkod" class="hk-input" style="flex: 1;" placeholder="Barkod okutun veya ürün adı yazın">
                </div>
                <div class="sevk-table-wrap compact">
                    <table class="sevk-table">
                        <thead>
                            <tr>
                                <th>Ürün</th>
                                <th>Beklenen Adet</th>
                                <th>Maliyet (Opsiyonel)</th>
                                <th>İşlem</th>
                            </tr>
                        </thead>
                        <tbody id="malkabul-yeni-kalemler">
                            <tr id="malkabul-yeni-kalemler-empty"><td colspan="4" class="sevk-empty">Henüz ürün eklenmedi.</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="sevk-summary-row" style="margin-top: 20px;">
                <textarea id="malkabul-yeni-not" class="hk-input" rows="2" placeholder="Sipariş notu..."></textarea>
                <button type="button" id="malkabul-yeni-kaydet" class="sevk-btn primary">Siparişi Oluştur</button>
            </div>
        </div>
    </section>

    <!-- Tedarikçiler Ekranı -->
    <section id="malkabul-tedarikciler" class="sevk-icerik-paneli" style="display:none;">
        <div class="sevk-split">
            <aside class="sevk-list-panel">
                <div class="sevk-panel-title">
                    <h3>Tedarikçi Listesi</h3>
                    <button type="button" id="malkabul-tedarikciler-yenile" class="sevk-icon-btn" title="Yenile">↻</button>
                </div>
                <div id="malkabul-tedarikci-listesi" class="sevk-card-list">
                    <!-- Dinamik doldurulacak -->
                </div>
            </aside>
            <main class="sevk-detail-panel">
                <div class="sevk-detail-content">
                    <h3>Yeni Tedarikçi Ekle</h3>
                    <div class="sevk-form-grid">
                        <label>
                            Firma Adı <span style="color:red">*</span>
                            <input type="text" id="tedarikci-yeni-ad" class="hk-input">
                        </label>
                        <label>
                            Telefon
                            <input type="text" id="tedarikci-yeni-tel" class="hk-input">
                        </label>
                        <label>
                            E-Posta
                            <input type="email" id="tedarikci-yeni-email" class="hk-input">
                        </label>
                        <label>
                            Vergi Dairesi & No
                            <input type="text" id="tedarikci-yeni-vergi" class="hk-input">
                        </label>
                    </div>
                    <label style="margin-top:10px; display:block;">
                        Adres
                        <textarea id="tedarikci-yeni-adres" class="hk-input" rows="3"></textarea>
                    </label>
                    <button type="button" id="tedarikci-yeni-kaydet" class="sevk-btn primary" style="margin-top:15px;">Kaydet</button>
                </div>
            </main>
        </div>
    </section>
</div>

<!-- Sipariş Detay ve Teslim Alma Modalı -->
<div id="malkabul-detay-modal" class="sevk-modal" style="display:none;">
    <div class="sevk-modal-content" style="max-width: 800px;">
        <button type="button" id="malkabul-modal-kapat" class="sevk-modal-close">×</button>
        <div id="malkabul-modal-body" style="padding: 20px;">
            <h2 style="margin-top:0;">Sipariş Teslim Alma</h2>
            <div id="malkabul-modal-info" style="margin-bottom: 20px; font-weight: bold;"></div>
            
            <div class="sevk-table-wrap">
                <table class="sevk-table">
                    <thead>
                        <tr>
                            <th>Ürün</th>
                            <th>Beklenen</th>
                            <th>Önceki Gelen</th>
                            <th>Şimdi Gelen</th>
                        </tr>
                    </thead>
                    <tbody id="malkabul-teslim-kalemleri">
                        <!-- Dinamik -->
                    </tbody>
                </table>
            </div>

            <div style="margin-top: 20px; text-align: right;">
                <button type="button" id="malkabul-teslim-al-btn" class="sevk-btn primary">Seçili Miktarları Teslim Al (Stoğa Ekle)</button>
            </div>
        </div>
    </div>
</div>
