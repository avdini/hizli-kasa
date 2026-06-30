<?php
/**
 * Hızlı Kasa - Sevk Sekmesi
 */
if (!defined('ABSPATH'))
    exit;
?>

<div class="hk-tab-container sevk-shell" data-active-group="sevk">
    <div class="sevk-header">
        <div style="display: flex; align-items: center; gap: 16px; flex: 1; min-width: 0;">
            <div class="sevk-grup-kontrol">
                <button type="button" class="sevk-grup-btn aktif" data-group="sevk">
                    <span>🚚</span> Sevkler
                </button>
                <button type="button" class="sevk-grup-btn" data-group="tedarik">
                    <span>📦</span> Tedarik
                </button>
            </div>

            <div class="sevk-alt-sekmeler" aria-label="Sevk alt sekmeleri">
                <div class="sevk-alt-grup-wrapper" data-group-wrapper="sevk">
                    <button class="sevk-alt-btn aktif" data-group="sevk" data-target="sevk-genel">Genel Sevk</button>
                    <button class="sevk-alt-btn" data-group="sevk" data-target="sevk-kabul">Sevk Kabul <span id="sevk-kabul-badge" class="sevk-tab-badge" style="display:none;">0</span></button>
                    <button class="sevk-alt-btn" data-group="sevk" data-target="sevk-iste">Sevk İste</button>
                    <button class="sevk-alt-btn" data-group="sevk" data-target="sevk-cikis">Sevk Çıkış</button>
                </div>
                <div class="sevk-alt-grup-wrapper" data-group-wrapper="tedarik">
                    <button class="sevk-alt-btn" data-group="tedarik" data-target="malkabul-siparisler">Alım Siparişleri</button>
                    <button class="sevk-alt-btn" data-group="tedarik" data-target="malkabul-yeni-siparis">Yeni Sipariş Oluştur</button>
                    <button class="sevk-alt-btn" data-group="tedarik" data-target="malkabul-tedarikciler">Tedarikçiler</button>
                </div>
            </div>
        </div>
        <div class="sevk-header-actions">
            <span class="sevk-live-dot"></span>
            <span id="sevk-active-depo-label">Depo hazırlanıyor...</span>
        </div>
    </div>

    <section id="sevk-genel" class="sevk-icerik-paneli aktif">
        <div class="sevk-dashboard-grid">
            <article class="sevk-stat-card">
                <span>Toplam Sevk</span>
                <strong id="sevk-stat-total">0</strong>
            </article>
            <article class="sevk-stat-card">
                <span>Yolda</span>
                <strong id="sevk-stat-yolda">0</strong>
            </article>
            <article class="sevk-stat-card">
                <span>Bekleyen</span>
                <strong id="sevk-stat-bekleyen">0</strong>
            </article>
            <article class="sevk-stat-card">
                <span>Tamamlanan</span>
                <strong id="sevk-stat-tamamlanan">0</strong>
            </article>
        </div>

        <div class="sevk-filter-bar">
            <label>
                Durum
                <select id="sevk-genel-durum" class="hk-input">
                    <option value="all">Tümü</option>
                    <option value="taslak">Taslak</option>
                    <option value="onay_bekliyor">Bekleyen</option>
                    <option value="gonderildi">Yolda</option>
                    <option value="tamamlandi">Tamamlanan</option>
                    <option value="uyusmazlik">Uyuşmazlık</option>
                </select>
            </label>
            <label>
                Başlangıç
                <input type="date" id="sevk-genel-date-start" class="hk-input">
            </label>
            <label>
                Bitiş
                <input type="date" id="sevk-genel-date-end" class="hk-input">
            </label>
            <button type="button" id="sevk-genel-yenile" class="sevk-btn secondary">Yenile</button>
        </div>

        <div class="sevk-table-wrap">
            <table class="sevk-table">
                <thead>
                    <tr>
                        <th>Sevk No</th>
                        <th>Kaynak → Hedef</th>
                        <th>Durum</th>
                        <th>Tarih</th>
                        <th>Çeşit / Adet</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="sevk-genel-listesi">
                    <tr><td colspan="6" class="sevk-empty">Sevkler yükleniyor...</td></tr>
                </tbody>
            </table>
        </div>
    </section>

    <section id="sevk-kabul" class="sevk-icerik-paneli" style="display:none;">
        <div class="sevk-split">
            <div class="sevk-list-panel">
                <div class="sevk-panel-title">
                    <h3>Gelen Sevkler</h3>
                    <button type="button" id="sevk-kabul-yenile" class="sevk-icon-btn" title="Yenile">↻</button>
                </div>
                <div id="sevk-kabul-listesi" class="sevk-card-list"></div>
            </div>
            <main id="sevk-kabul-detay" class="sevk-detail-panel">
                <div class="sevk-empty-state">
                    <h3>Bir sevk seçin</h3>
                    <p>Onay, red ve teslim barkod kontrolü burada yapılır.</p>
                </div>
            </main>
        </div>
    </section>

    <section id="sevk-iste" class="sevk-icerik-paneli" style="display:none;">
        <div class="sevk-placeholder">
            <h3>Sevk İste</h3>
            <p>Ürün talep modülü mevcut plana dahil edilmediği için bu alan şimdilik placeholder olarak korunuyor.</p>
        </div>
    </section>

    <section id="sevk-cikis" class="sevk-icerik-paneli" style="display:none;">
        <div class="sevk-wizard">
            <div class="sevk-steps">
                <span class="active" data-step-indicator="1">1. Oluştur</span>
                <span data-step-indicator="2">2. Barkod</span>
                <span data-step-indicator="3">3. Sonuç</span>
            </div>

            <div class="sevk-step-panel" data-step="1">
                <div id="sevk-taslak-banner" class="sevk-banner" style="display:none; margin-bottom:16px; width:100%; box-sizing:border-box;">
                    <div class="sevk-banner-content">
                        <span>ℹ️</span>
                        <div>
                            <strong>Yarım Kalan Sevk Taslağı Tespit Edildi</strong>
                            <p><span id="sevk-taslak-banner-no" style="font-weight:bold;">SVK-XXXX</span> numaralı sevk taslağına devam edebilir veya bu taslağı silerek yeni bir sevk başlatabilirsiniz.</p>
                        </div>
                    </div>
                    <div class="sevk-banner-actions">
                        <button type="button" id="sevk-taslak-devam-btn" class="sevk-btn primary small">Taslağa Devam Et</button>
                        <button type="button" id="sevk-taslak-sil-btn" class="sevk-btn secondary small">Taslağı Sil</button>
                    </div>
                </div>

                <div class="sevk-form-grid">
                    <label>
                        Kaynak Depo
                        <input type="text" id="sevk-cikis-kaynak-label" class="hk-input" readonly>
                    </label>
                    <label>
                        Hedef Depo
                        <select id="sevk-cikis-hedef" class="hk-input"></select>
                    </label>
                </div>
                <button type="button" id="sevk-cikis-olustur" class="sevk-btn primary">Sevk Oluştur</button>
            </div>

            <div class="sevk-step-panel" data-step="2" style="display:none;">
                <div class="sevk-current-card">
                    <div>
                        <span id="sevk-cikis-no">SVK</span>
                        <strong id="sevk-cikis-route">Kaynak → Hedef</strong>
                    </div>
                    <span class="sevk-scan-pill">Barkod tarama aktif</span>
                </div>
                <input type="text" id="sevk-cikis-barkod" class="hk-input sevk-barcode-input" placeholder="Barkod okutun veya yazıp Enter'a basın">
                <div class="sevk-table-wrap compact">
                    <table class="sevk-table">
                        <thead>
                            <tr>
                                <th>Ürün</th>
                                <th>SKU</th>
                                <th>Adet</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody id="sevk-cikis-kalemler"></tbody>
                    </table>
                </div>
                <div class="sevk-summary-row">
                    <span id="sevk-cikis-ozet">0 çeşit ürün, 0 adet toplam</span>
                    <textarea id="sevk-cikis-not" class="hk-input" rows="2" placeholder="Gönderici notu"></textarea>
                    <button type="button" id="sevk-cikis-onayla" class="sevk-btn primary">Onayla ve Gönder</button>
                </div>
            </div>

            <div class="sevk-step-panel" data-step="3" style="display:none;">
                <div class="sevk-result-card">
                    <h3>Sevk onaya gönderildi</h3>
                    <p id="sevk-cikis-sonuc">Alıcı deponun onayı bekleniyor.</p>
                    <button type="button" id="sevk-cikis-yeni" class="sevk-btn secondary">Yeni Sevk Oluştur</button>
                </div>
            </div>
        </div>
    </section>

    <div id="sevk-detay-modal" class="sevk-modal" style="display:none;">
        <div class="sevk-modal-content">
            <button type="button" id="sevk-modal-kapat" class="sevk-modal-close">×</button>
            <div id="sevk-modal-body"></div>
        </div>
    </div>

    <!-- Alım Siparişleri Listesi -->
    <section id="malkabul-siparisler" class="sevk-icerik-paneli" style="display:none;">
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
                    <button type="button" id="malkabul-yeni-bagimsiz-btn" class="sevk-btn secondary">Bağımsız Ürün Ekle (Sitede Yok)</button>
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
            <div class="sevk-list-panel">
                <div class="sevk-panel-title">
                    <h3>Tedarikçi Listesi</h3>
                    <button type="button" id="malkabul-tedarikciler-yenile" class="sevk-icon-btn" title="Yenile">↻</button>
                </div>
                <div id="malkabul-tedarikci-listesi" class="sevk-card-list">
                    <!-- Dinamik doldurulacak -->
                </div>
            </div>
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
