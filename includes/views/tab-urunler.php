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
                <button id="btn-stok-sayimi-toggle" class="terminal-btn btn-stok-sayimi" title="Stok Sayımı Görünümüne Geç">
                    <span class="ikon">📋</span> <span class="btn-text">Depo Sayımı</span>
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

            <button id="btn-terminal-filtre-toggle" class="terminal-btn btn-filtre-toggle" title="Gelişmiş Filtreler">
                <span class="ikon">🔍</span> Filtrele
            </button>

            <div class="arama-kutusu">
                <input type="text" id="terminal-arama-input" placeholder="Ürün adı veya barkod okutun..." autocomplete="off">
                <span class="arama-ikon">🔍</span>
            </div>
        </div>
    </div>

    <!-- Gelişmiş Filtre Paneli -->
    <div id="terminal-filtre-bar" class="terminal-filter-bar glass">
        <div class="filter-group">
            <label>Kategori:</label>
            <select id="filter-category" class="terminal-select">
                <option value="0">Tüm Kategoriler</option>
            </select>
        </div>
        <div class="filter-group">
            <label>Marka:</label>
            <select id="filter-brand" class="terminal-select">
                <option value="0">Tüm Markalar</option>
            </select>
        </div>
        <div class="filter-group">
            <label>Stok:</label>
            <select id="filter-stock-status" class="terminal-select">
                <option value="all">Hepsi</option>
                <option value="instock">Stokta Var</option>
                <option value="lowstock">Kritik Stok</option>
                <option value="outofstock">Stokta Yok</option>
            </select>
        </div>
        <div class="filter-actions">
            <button id="btn-clear-filters" class="btn-clear-link">Temizle</button>
        </div>
    </div>

    <div id="terminal-liste-paneli">
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

    <!-- Depo Sayım Paneli -->
    <div id="terminal-sayim-paneli" style="display: none;">
        <!-- Sayım Başlangıç Ekranı -->
        <div id="sayim-baslangic-ekrani" class="sayim-baslangic-kart glass">
            <span class="sayim-baslangic-ikon">📦</span>
            <h3>Depo Sayımı Başlat</h3>
            <p>Seçili depo için fiziksel sayım oturumu başlatın. Sayım sırasında barkod okuyucu kullanarak veya manuel ürün arayarak envanteri güncelleyebilirsiniz.</p>
            <button id="btn-sayim-baslat" class="terminal-btn btn-sayim-baslat-action">
                <span class="ikon">🚀</span> Yeni Sayım Başlat
            </button>
        </div>

        <!-- Aktif Sayım Ekranı -->
        <div id="sayim-aktif-ekrani" style="display: none;" class="sayim-aktif-layout">
            <div class="sayim-sol-kolon">
                <div class="sayim-kart glass">
                    <h4>Barkod Okutun</h4>
                    <div class="sayim-barkod-kontrol">
                        <input type="text" id="sayim-barkod-input" placeholder="Barkodu okutun ve Enter'a basın..." autocomplete="off">
                        <span class="sayim-barkod-icon">🏷️</span>
                    </div>
                    <p class="sayim-yardim-metni">Barkod okutulduğunda ürün listede varsa adeti 1 artar, yoksa listeye 1 adet olarak eklenir.</p>
                </div>

                <!-- Son Okutulan Ürün HUD Kartı -->
                <div id="sayim-hud-kart" class="sayim-kart glass sayim-hud-kart-visual" style="display: none;">
                    <h4>Son Okutulan Ürün</h4>
                    <div class="hud-urun-icerik">
                        <div class="hud-urun-gorsel-wrapper">
                            <img id="hud-urun-resim" src="" alt="Ürün Görseli" style="display: none; width: 50px; height: 50px; border-radius: 6px; object-fit: cover;">
                            <span id="hud-urun-placeholder" class="ikon" style="font-size: 32px; display: block; text-align: center;">📦</span>
                        </div>
                        <div class="hud-urun-detaylar" style="flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 4px;">
                            <span id="hud-urun-ad" class="hud-baslik" style="font-weight: 600; font-size: 14px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; display: block;">-</span>
                            <span id="hud-urun-sku" class="hud-alt" style="font-size: 11px; color: var(--hk-text-muted); display: block;">-</span>
                            <div class="hud-adet-info" style="display: flex; justify-content: space-between; font-size: 12px; margin-top: 4px;">
                                <span class="hud-etiket" style="color: var(--hk-text-sub);">Sayılan Adet:</span>
                                <span id="hud-urun-adet" class="hud-deger" style="font-weight: 700; color: var(--hk-text-main);">-</span>
                            </div>
                            <div class="hud-fark-info" style="display: flex; justify-content: space-between; font-size: 12px;">
                                <span class="hud-etiket" style="color: var(--hk-text-sub);">Fark:</span>
                                <span id="hud-urun-fark" class="hud-deger-fark" style="font-weight: 700;">-</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="sayim-kart glass sayim-ayarlar-kart">
                    <h4>Ayarlar & Bilgi</h4>
                    <div class="sayim-info-item">
                        <label>Depo:</label>
                        <span id="sayim-depo-adi">-</span>
                    </div>
                    <div class="sayim-info-item">
                        <label>Başlatan:</label>
                        <span id="sayim-personel-adi">-</span>
                    </div>
                    <div class="sayim-info-item">
                        <label>Başlangıç:</label>
                        <span id="sayim-tarihi">-</span>
                    </div>
                    <div class="sayim-ses-kontrol">
                        <label class="toggle-switch">
                            <input type="checkbox" id="chk-sayim-ses" checked>
                            <span class="slider"></span>
                        </label>
                        <span>Sesli Geri Bildirim</span>
                    </div>
                    <div class="sayim-ses-kontrol" style="margin-top: 10px;">
                        <label class="toggle-switch">
                            <input type="checkbox" id="chk-sayim-miktar-sor">
                            <span class="slider"></span>
                        </label>
                        <span>Barkod Okununca Miktar Sor</span>
                    </div>
                </div>
            </div>

            <div class="sayim-sag-kolon">
                <div class="sayim-kart glass sayim-kalemler-kart">
                    <div class="sayim-kalemler-header">
                        <h4>Sayılan Kalemler (<span id="sayim-kalem-sayisi-lbl">0</span>)</h4>
                        <div class="sayim-arama-kutusu">
                            <input type="text" id="sayim-urun-ekle-input" placeholder="Manuel ürün ara ve ekle..." autocomplete="off">
                            <div id="sayim-urun-ekle-results" class="sayim-ekle-arama-sonuclari" style="display:none;"></div>
                        </div>
                    </div>

                    <div class="sayim-tablo-wrapper">
                        <table class="gs-tablo sayim-tablo">
                            <thead>
                                <tr>
                                    <th style="width: 60px;">Görsel</th>
                                    <th>Ürün Adı / Varyant</th>
                                    <th>SKU</th>
                                    <th style="width: 100px; text-align: center;">Sistem Stoğu</th>
                                    <th style="width: 130px; text-align: center;">Sayılan</th>
                                    <th style="width: 100px; text-align: center;">Fark</th>
                                    <th style="width: 50px; text-align: center;">Aksiyon</th>
                                </tr>
                            </thead>
                            <tbody id="sayim-items-body">
                                <tr>
                                    <td colspan="7" class="rapor-empty-td">Henüz ürün sayılmadı. Barkod okutun veya manuel ekleyin.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="sayim-aksiyon-bar">
                        <button id="btn-sayim-iptal" class="terminal-btn btn-sayim-iptal-action">
                            <span class="ikon">❌</span> Sayımı İptal Et
                        </button>
                        <button id="btn-sayim-bitir" class="terminal-btn btn-sayim-bitir-action">
                            <span class="ikon">💾</span> Sayımı Bitir & Eşitle
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Sayım Bitirme Seçenekleri Modalı -->
    <div id="sayim-bitir-modal" class="modal-cerceve" style="display:none;">
        <div class="modal-icerik glass">
            <h3>Stok Eşitleme Seçenekleri</h3>
            <p>Sayım işlemini bitirmek üzeresiniz. Lütfen stok güncelleme yöntemini seçin:</p>
            
            <div class="sayim-bitir-secenekler">
                <label class="sayim-secenek-kart">
                    <input type="radio" name="sayim_update_type" value="partial" checked>
                    <div class="secenek-detay">
                        <span class="secenek-baslik">Sadece Sayılanları Güncelle (Kısmi Eşitleme)</span>
                        <span class="secenek-aciklama">Yalnızca listedeki ürünlerin stokları girdiğiniz sayım adetleriyle güncellenir. Listede olmayan ürünlerin stoklarına dokunulmaz.</span>
                    </div>
                </label>

                <label class="sayim-secenek-kart">
                    <input type="radio" name="sayim_update_type" value="full">
                    <div class="secenek-detay">
                        <span class="secenek-baslik">Tüm Envanteri Eşitle (Tam Eşitleme)</span>
                        <span class="secenek-aciklama">Listelediğiniz ürünler güncellenir. Bu depoda yer alan ancak sayım listesinde **hiç bulunmayan** diğer tüm ürünlerin stokları otomatik olarak <strong>0</strong> yapılır.</span>
                    </div>
                </label>
            </div>

            <!-- Büyük Farklılık Uyuşmazlık Uyarıları -->
            <div id="sayim-discrepancy-warnings" class="sayim-bitir-uyarilar" style="display: none; margin-top: 15px; border-top: 1px solid var(--hk-border); padding-top: 15px;">
                <h4 style="color: #e74c3c; margin: 0 0 10px; display: flex; align-items: center; gap: 8px; font-size: 14px;">
                    <span>⚠️</span> Büyük Stok Farkları Tespit Edildi
                </h4>
                <div style="max-height: 150px; overflow-y: auto; border: 1px solid var(--hk-border); border-radius: 8px;">
                    <table class="gs-tablo" style="width: 100%; font-size: 11px; margin: 0;">
                        <thead>
                            <tr>
                                <th>Ürün Adı</th>
                                <th style="text-align: center; width: 60px;">Sistem</th>
                                <th style="text-align: center; width: 60px;">Sayılan</th>
                                <th style="text-align: center; width: 60px;">Fark</th>
                            </tr>
                        </thead>
                        <tbody id="sayim-discrepancy-body">
                            <!-- JS tarafından doldurulacak -->
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="modal-butonlar" style="margin-top: 20px;">
                <button id="sayim-bitir-vazgec" class="btn-secondary">İptal</button>
                <button id="sayim-bitir-onayla" class="btn-primary">Sayımı Bitir ve Eşitle</button>
            </div>
        </div>
    </div>

    <!-- Miktar Doğrulama Hızlı Giriş Modalı -->
    <div id="sayim-qty-prompt-modal" class="modal-cerceve" style="display:none; z-index: 1100;">
        <div class="modal-icerik glass" style="max-width: 350px; text-align: center; padding: 25px;">
            <h3 id="sayim-qty-prompt-title" style="margin-top: 0;">Miktar Girin</h3>
            <p id="sayim-qty-prompt-product" style="font-weight: 600; font-size: 14px; color: var(--hk-text-main); margin-bottom: 15px; word-break: break-word;">-</p>
            <div class="sayim-qty-prompt-input-wrapper" style="margin: 20px 0;">
                <input type="number" id="sayim-qty-prompt-input" style="font-size: 28px; text-align: center; padding: 10px; width: 140px; border-radius: 10px; border: 2px solid var(--hk-border); background: var(--hk-bg-input); color: var(--hk-text-main); font-weight: 700;" min="0" step="1" value="1">
            </div>
            <div class="modal-butonlar" style="justify-content: center; gap: 10px; margin-top: 15px;">
                <button id="btn-sayim-qty-prompt-cancel" class="btn-secondary" style="padding: 8px 20px;">İptal</button>
                <button id="btn-sayim-qty-prompt-confirm" class="btn-primary" style="padding: 8px 25px;">Onayla</button>
            </div>
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
            <p id="modal-readonly-msg" style="display:none;">👁 Bu depoda sadece görüntüleme yetkiniz var. Stok değiştiremezsiniz.</p>
            <button id="stok-kaydet-iptal" class="btn-secondary">İptal</button>
            <button id="stok-kaydet-onay" class="btn-primary">Hareketi Kaydet</button>
        </div>
    </div>
</div>

<!-- Barkod Yazdırma Modalı -->
<div id="barkod-yazdir-modal" class="modal-cerceve" style="display:none;">
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
<div id="mobil-qr-modal" class="modal-cerceve" style="display:none;">
    <div class="modal-icerik glass mobil-qr-icerik">
        <h3>Mobil Envanter Aracı</h3>
        <p>Aşağıdaki kodu telefonunuzun kamerasından okutarak mobil araca hızlıca erişebilirsiniz.</p>
        
        <div id="qr-code-display">
            <!-- QR Kod Buraya Gelecek -->
        </div>

        <div class="mobile-tool-url-wrapper">
            Link: <span id="mobile-tool-url-text">...</span>
        </div>

        <div class="modal-butonlar modal-butonlar-center">
            <button id="close-qr-modal" class="btn-secondary">Kapat</button>
        </div>
    </div>
</div>
