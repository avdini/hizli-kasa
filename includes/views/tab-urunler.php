<?php 
if (!defined('ABSPATH')) exit;
// Sekme içeriği JS tarafından yönetilir — PHP yetki kontrolü yok
?>
<div id="stok-terminali">
    <!-- Üst Bar: Depo Switcher ve Arama -->
    <div class="terminal-header">
        <!-- Depo Seçici -->
        <div class="depo-switcher" id="depo-switcher">
            <span class="ikon">🏢</span>
            <div class="depo-switcher-content">
                <label>Aktif Depo</label>
                <div class="depo-switcher-trigger" id="depo-switcher-trigger">
                    <span id="aktif-depo-adi">Yükleniyor...</span>
                    <span class="depo-dropdown-arrow">▾</span>
                </div>
                <div class="depo-dropdown" id="depo-dropdown" style="display:none;">
                    <!-- JS tarafından doldurulur -->
                </div>
            </div>
            <!-- Sadece görüntüleme rozeti -->
            <div class="depo-readonly-badge" id="depo-readonly-badge" style="display:none;">
                👁 Sadece Görüntüleme
            </div>
        </div>

        <div class="arama-kutusu">
            <input type="text" id="terminal-arama-input" placeholder="Ürün adı veya barkod okutun..." autocomplete="off">
            <span class="arama-ikon">🔍</span>
        </div>
    </div>

    <!-- Ana İçerik: Ürün Listesi -->
    <div class="terminal-body" id="terminal-urun-listesi">
        <!-- JS tarafından doldurulur -->
        <div class="terminal-loading">
            <div class="spin"></div>
            <p>Yükleniyor...</p>
        </div>
    </div>

    <!-- Sayfalama Kontrolleri -->
    <div class="terminal-pagination">
        <div class="pagination-info">
            <label>Sayfa Başına:</label>
            <select id="per-page-select">
                <option value="24">24</option>
                <option value="48">48</option>
                <option value="96">96</option>
            </select>
        </div>
        <div class="pagination-controls">
            <button id="prev-page" class="btn-pagination" disabled>❮</button>
            <span id="current-page-display">Sayfa 1</span>
            <button id="next-page" class="btn-pagination">❯</button>
        </div>
        <div class="pagination-stats">
            <span id="range-display">Gösterilen: 0-0 / 0</span>
        </div>
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
            <label for="toplam-urun-sayisi">Kayıtlı Ürün</label>
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
            <p id="modal-readonly-msg" style="display:none; color:#f59e0b; font-size:12px; margin:0 0 8px; text-align:center;">👁 Bu depoda sadece görüntüleme yetkiniz var. Stok değiştiremezsiniz.</p>
            <button id="stok-kaydet-iptal" class="btn-secondary">İptal</button>
            <button id="stok-kaydet-onay" class="btn-primary">Hareketi Kaydet</button>
        </div>
    </div>
</div>
