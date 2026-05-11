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

        <div class="arama-kutusu-wrapper">
            <!-- Mobil Araç Erişimi -->
            <div class="mobil-arac-trigger-wrapper">
                <button id="btn-mobil-arac-ac" class="terminal-btn btn-mobil-arac" title="Mobil Envanter Aracını Aç (QR Kod)">
                    <span class="ikon">📱</span> Mobil Araç
                </button>
            </div>

            <!-- Sıralama Seçici -->
            <div class="siralama-kutusu">
                <select id="terminal-siralama-select" class="terminal-select">
                    <option value="date|desc">Yeni Eklenenler</option>
                    <option value="date|asc">Eski Eklenenler</option>
                    <option value="title|asc">Ürün Adı (A-Z)</option>
                    <option value="title|desc">Ürün Adı (Z-A)</option>
                    <option value="stock|desc">Stok (Azalan)</option>
                    <option value="stock|asc">Stok (Artan)</option>
                    <option value="price|asc">Fiyat (Düşükten Yükseğe)</option>
                    <option value="price|desc">Fiyat (Yüksekten Düşüğe)</option>
                </select>
            </div>

            <div class="arama-kutusu">
                <input type="text" id="terminal-arama-input" placeholder="Ürün adı veya barkod okutun..." autocomplete="off">
                <span class="arama-ikon">🔍</span>
            </div>
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

    <!-- Sayfalama ve İstatistik Birleştirilmiş Footer -->
    <div class="terminal-footer unified-footer">
        <!-- Sol: Sayfa Başına Seçimi -->
        <div class="pagination-info">
            <label>Sayfa Başına:</label>
            <select id="per-page-select">
                <option value="24">24</option>
                <option value="48">48</option>
                <option value="96">96</option>
            </select>
        </div>

        <!-- Orta: İstatistikler (Kompakt) -->
        <div class="footer-stats-combined">
            <div class="stat-item">
                <span id="basit-urun-sayisi">0</span>
                <label>Basit Ürün</label>
            </div>
            <div class="stat-item">
                <span id="varyasyonlu-urun-sayisi">0</span>
                <label>Varyasyonlu (Ana)</label>
            </div>
            <div class="stat-item">
                <span id="toplam-kalem-sayisi">0</span>
                <label>Toplam Kalem</label>
            </div>
            <div class="stat-item">
                <span id="kritik-stok-sayisi">0</span>
                <label>Kritik Stok</label>
            </div>
        </div>

        <!-- Sağ: Sayfalama Kontrolleri -->
        <div class="pagination-controls-wrapper">
            <div class="pagination-controls">
                <button id="prev-page" class="btn-pagination" disabled>❮</button>
                <span id="current-page-display">Sayfa 1</span>
                <button id="next-page" class="btn-pagination">❯</button>
            </div>
            <div class="pagination-stats">
                <span id="range-display">Gösterilen: 0-0 / 0</span>
            </div>
        </div>
    </div>


</div>

<!-- Stok Düzenleme Modalı -->
<div id="stok-duzenle-modal" class="modal-cerceve" style="display:none; position:fixed; z-index:10007; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.8); align-items:center; justify-content:center;">
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

<!-- Barkod Yazdırma Modalı -->
<div id="barkod-yazdir-modal" class="modal-cerceve" style="display:none; position:fixed; z-index:10008; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.8); align-items:center; justify-content:center;">
    <div class="modal-icerik glass barkod-modal-genis">
        <div class="modal-baslik-alan">
            <h3 id="barkod-modal-baslik">Barkod Yazdır</h3>
            <p id="barkod-modal-alt-baslik">Yazdırılacak adetleri kontrol edin.</p>
        </div>

        <div id="barkod-modal-filtreler" class="modal-filtreler" style="display:none;">
            <!-- Filtreler JS tarafından dinamik oluşturulacak -->
        </div>
        
        <div id="barkod-urun-listesi-konteynir" class="barkod-secim-listesi">
            <!-- Dinamik olarak dolacak: Ürün adı, varyant detayı ve adet girişi -->
        </div>

        <div class="modal-butonlar">
            <button id="barkod-iptal" class="btn-secondary">Vazgeç</button>
            <button id="barkod-onay-yazdir" class="btn-primary">
                <span class="ikon">🖨️</span> Yazıcıya Gönder
            </button>
        </div>
    </div>
</div>

<!-- Mobil Araç QR Kod Modalı -->
<div id="mobil-qr-modal" class="modal-cerceve" style="display:none; position:fixed; z-index:10009; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.8); align-items:center; justify-content:center;">
    <div class="modal-icerik glass text-center" style="max-width: 400px; padding: 30px; text-align: center;">
        <h3 style="margin-bottom: 15px;">Mobil Envanter Aracı</h3>
        <p style="color: #94a3b8; font-size: 14px;">Aşağıdaki kodu telefonunuzun kamerasından okutarak mobil araca hızlıca erişebilirsiniz.</p>
        
        <div id="qr-code-display" style="background: white; padding: 15px; border-radius: 12px; margin: 20px auto; display: inline-block;">
            <!-- QR Kod Buraya Gelecek -->
        </div>

        <div style="margin-top: 15px; font-size: 13px; color: #6366f1;">
            Link: <span id="mobile-tool-url-text">...</span>
        </div>

        <div class="modal-butonlar" style="margin-top: 25px; justify-content: center;">
            <button id="close-qr-modal" class="btn-secondary">Kapat</button>
        </div>
    </div>
</div>
