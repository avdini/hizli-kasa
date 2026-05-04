<?php
/**
 * Hızlı Kasa - Sevk Sekmesi
 */
if (!defined('ABSPATH'))
    exit;
?>

<div class="hk-tab-container sevk-shell">
    <header class="sevk-header">
        <div>
            <h2>Sevk İşlemleri</h2>
            <p>Depolar arası çıkış, kabul ve teslim doğrulama akışı.</p>
        </div>
        <div class="sevk-header-actions">
            <span class="sevk-live-dot"></span>
            <span id="sevk-active-depo-label">Depo hazırlanıyor...</span>
        </div>
    </header>

    <nav class="sevk-alt-sekmeler" aria-label="Sevk alt sekmeleri">
        <button class="sevk-alt-btn aktif" data-target="sevk-genel">Genel</button>
        <button class="sevk-alt-btn" data-target="sevk-kabul">Sevk Kabul <span id="sevk-kabul-badge" class="sevk-tab-badge" style="display:none;">0</span></button>
        <button class="sevk-alt-btn" data-target="sevk-iste">Sevk İste</button>
        <button class="sevk-alt-btn" data-target="sevk-cikis">Sevk Çıkış</button>
    </nav>

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
            <aside class="sevk-list-panel">
                <div class="sevk-panel-title">
                    <h3>Gelen Sevkler</h3>
                    <button type="button" id="sevk-kabul-yenile" class="sevk-icon-btn" title="Yenile">↻</button>
                </div>
                <div id="sevk-kabul-listesi" class="sevk-card-list"></div>
            </aside>
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
</div>
