<?php
/**
 * Hızlı Kasa - POS HTML Template
 *
 * Kasa arayüzünün tüm HTML yapısı.
 * Bu dosya doğrudan include edilir, shortcode tarafından çağrılır.
 *
 * @package HizliKasa
 */

if (!defined('ABSPATH'))
    exit;
?>

<div id="kasa-dis-cerceve">
    <div id="kasa-ana-duzen">
        <!-- Sidebar: Kasa Sekmeleri -->
        <div id="kasa-sidebar">
            <div class="sidebar-btn aktif" data-id="1"><span>📠</span> Kasa 1</div>
            <div class="sidebar-btn" data-id="2"><span>📠</span> Kasa 2</div>
            <div class="sidebar-btn" data-id="3"><span>📠</span> Kasa 3</div>
            <div class="sidebar-btn" data-id="4"><span>📠</span> Kasa 4</div>
        </div>

        <!-- Ana Kasa Konteyner -->
        <div id="kasa-konteyner">
            <!-- Başlık Alanı -->
            <div class="kasa-baslik">
                <h2 id="durum">Kasa Hazır - Barkod Okutun</h2>
                <button id="manuel-urun-buton" class="ust-buton-kucuk">El ile Ürün Ekle</button>
            </div>
        
            <!-- Barkod İzleme Alanı -->
            <div id="barkod-izleme-konteyner">
                <span id="barkod-izleme-etiket">Giriş:</span>
                <div id="barkod-izleme">...</div>
            </div>
            
            <!-- Sepet Listesi -->
            <ul id="sepet-listesi">
                <!-- Ürünler buraya gelecek -->
            </ul>

            <!-- Toplam Alanı -->
            <div class="toplam-alani">
                <div class="toplam-satir" id="liste-toplami-satiri">
                    <span class="toplam-etiket">ETİKET TOPLAMI:</span>
                    <span id="liste-toplami-deger" style="font-size: 18px; font-weight: bold; color: #95a5a6;">0.00 TL</span>
                </div>
                <div class="toplam-satir" id="ara-toplam-satiri">
                    <span class="toplam-etiket">ARA TOPLAM:</span>
                    <span id="ara-toplam-deger" style="font-size: 20px; font-weight: bold; color: #7f8c8d;">0.00 TL</span>
                </div>
                <div class="toplam-satir" id="nakit-indirim-satiri" style="display:none !important;">
                    <span class="toplam-etiket" id="nakit-indirim-etiket">İNDİRİM (%5):</span>
                    <span id="nakit-indirim-deger" class="indirim-deger">-0.00 TL</span>
                </div>
                <div class="toplam-satir" id="indirim-satiri" style="display:none !important;">
                    <span class="toplam-etiket">İSKONTO:</span>
                    <span id="indirim-deger" class="indirim-deger">-0.00 TL</span>
                </div>
                <div class="toplam-satir">
                    <span class="toplam-etiket">GENEL TOPLAM:</span>
                    <span id="genel-toplam" class="toplam-deger">0.00 TL</span>
                </div>
            </div>

            <!-- Ödeme Tipi Seçici -->
            <div class="odeme-secici">
                <div class="odeme-btn aktif" data-tip="card">💳 Kredi Kartı</div>
                <div class="odeme-btn" data-tip="cash">💵 Nakit (-%5)</div>
                <div class="odeme-btn" data-tip="iban">🏦 IBAN (-%5)</div>
            </div>

            <!-- İşlem Butonları -->
            <div class="islem-grubu">
                <div class="islem-sol-grup">
                    <button id="bol-buton">Ödemeyi Böl</button>
                    <button id="yuvarla-buton">Küsürat Yuvarla</button>
                    <button id="iskonto-buton">İskonto</button>
                </div>
                <button id="onayla-buton">Sipariş Oluştur</button>
            </div>

            <div class="kasa-alt-bilgi">
                <button id="gun-sonu-buton" class="gun-sonu-alt"><span>📋</span> Gün Sonu</button>
                <div class="v-check">POS v<?php echo HIZLI_KASA_VERSION; ?> - Stok Kontrolü Aktif</div>
            </div>
        </div>
    </div>

    <!-- ==================== MODALLER ==================== -->

    <!-- Stok Uyarı Modalı -->
    <div id="stok-uyari-modal" class="modal-cerceve" style="display:none; position:fixed; z-index:10000; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.6); align-items:center; justify-content:center;">
        <div class="modal-icerik" style="background:white; padding:30px; border-radius:12px; box-shadow: 0 10px 25px rgba(0,0,0,0.2); max-width: 500px; width: 90%; max-height: 90vh; display:flex; flex-direction:column;">
            <h3 style="color:#e67e22; margin-top:0;">⚠️ Kritik Stok Uyarısı!</h3>
            <p>Sepetteki şu ürünler siz işlem yaparken internetten satılmış veya tükenmiş görünüyor:</p>
            <ul id="stok-uyari-liste"></ul>
            <p style="font-size: 13px; color: #666; background: #fef9e7; padding: 10px; border-radius: 5px; border-left: 3px solid #f1c40f;">
                <strong>Not:</strong> "Yine de Satış Yap" derseniz işlem tamamlanır ancak ilgili ürünlerin stoğu eksiye düşebilir.
            </p>
            <div style="display:flex; gap:10px; margin-top:20px;">
                <button id="stok-vazgec" style="flex:1; padding:12px; border:none; border-radius:6px; cursor:pointer; font-weight:bold; background:#ddd; color:#333;">❌ Geri Dön</button>
                <button id="stok-devam" style="flex:1; padding:12px; border:none; border-radius:6px; cursor:pointer; font-weight:bold; background:#e67e22; color:white;">✅ Yine de Satış Yap</button>
            </div>
        </div>
    </div>

    <!-- İskonto Modalı -->
    <div id="iskonto-modal">
        <div class="modal-icerik">
            <h3>İskonto Tutari (TL)</h3>
            <input type="number" id="iskonto-input" placeholder="0.00" step="0.01" min="0">
            <div class="modal-butonlar">
                <button id="iskonto-iptal">İptal</button>
                <button id="iskonto-onay">Uygula</button>
            </div>
        </div>
    </div>

    <!-- Ödemeyi Böl Modalı -->
    <div id="odeme-bol-modal" class="modal-cerceve" style="display:none; position:fixed; z-index:10005; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.7); align-items:center; justify-content:center;">
        <div class="modal-icerik" style="width: 450px !important;">
            <h3>Ödemeyi Böl</h3>
            <p style="margin-bottom:15px; background:#f0f7ff; padding:10px; border-radius:6px; font-size:14px;">Ödeme bölünürse otomatik %5 indirimler iptal edilir. <br><strong>Ödenecek Net Tutar: <span id="bol-net-toplam">0.00</span> TL</strong></p>
            
            <div class="bol-satir">
                <span>💵 Nakit</span>
                <div class="bol-input-grup">
                    <label>Tutar (TL)</label>
                    <input type="number" id="bol-nakit" placeholder="0.00" step="0.01" min="0">
                </div>
            </div>
            <div class="bol-satir">
                <span>💳 Kredi Kartı</span>
                <div class="bol-input-grup">
                    <label>Tutar (TL)</label>
                    <input type="number" id="bol-kart" placeholder="0.00" step="0.01" min="0">
                </div>
            </div>
            <div class="bol-satir">
                <span>🏦 IBAN</span>
                <div class="bol-input-grup">
                    <label>Tutar (TL)</label>
                    <input type="number" id="bol-iban" placeholder="0.00" step="0.01" min="0">
                </div>
            </div>

            <div id="bol-kalan-uyari" class="kalan-eksik">Kalan: <span id="bol-kalan-tutar">0.00</span> TL</div>
            
            <div class="modal-butonlar" style="margin-top:20px;">
                <button id="bol-vazgec" style="background:#eee; color:#333;">Vazgeç</button>
                <button id="bol-onayla" style="background:#2c3e50; color:white;">Siparişi Onayla</button>
            </div>
        </div>
    </div>

    <!-- Ürün Arama Modalı -->
    <div id="urun-arama-modal">
        <div class="modal-icerik" style="width: 500px !important;">
            <h3>Ürün Ara</h3>
            <input type="text" id="urun-arama-input" placeholder="Ürün adı veya SKU yazın..." autocomplete="off">
            <ul id="arama-sonuclari">
                <!-- Aramalar buraya gelecek -->
            </ul>
            <div class="modal-butonlar" style="margin-top:15px;">
                <button id="urun-arama-kapat" style="background:#eee; color:#333;">Kapat</button>
            </div>
        </div>
    </div>

    <!-- Başarı ve Fiş Modalı -->
    <div id="fis-onay-modal">
        <div class="fis-onay-icerik">
            <div class="basari-ikon">✓</div>
            <h2 style="margin-bottom:10px;">Sipariş Oluşturuldu!</h2>
            <p style="font-size:18px; color:#666;">Sipariş No: <strong id="fis-order-no">#----</strong></p>
            <div class="fis-butonlar">
                <button class="fis-yazdir-btn" id="fis-yazdir-tetik">Fiş Yazdir (Enter)</button>
                <button class="fis-kapat-btn" id="fis-yazdir-kapat">Yeni Satışa Geç (Esc)</button>
            </div>
        </div>
    </div>

    <!-- Gizli Fiş Şablonu (Sadece Yazdırma İçin) -->
    <div id="fis-sablon">
        <div style="text-align:center; margin-bottom:10px; border-bottom:1px dashed #000; padding-bottom:10px;">
            <h2 style="margin:0; font-size:18px;"><?php echo get_bloginfo('name'); ?></h2>
            <p style="margin:5px 0; font-size:12px;">HIZLI KASA SATIŞ FİŞİ</p>
            <p id="fis-tarih" style="margin:0; font-size:11px;"></p>
            <p id="fis-no-text" style="font-weight:bold; margin:5px 0; font-size:14px;"></p>
        </div>
        
        <table style="width:100%; border-collapse:collapse; font-size:12px; margin-bottom:10px;">
            <thead>
                <tr style="border-bottom:1px solid #000;">
                    <th style="text-align:left; padding:5px 0;">Ürün</th>
                    <th style="text-align:right; padding:5px 0;">Toplam</th>
                </tr>
            </thead>
            <tbody id="fis-urunler-body">
                <!-- Ürünler buraya gelecek -->
            </tbody>
        </table>

        <div style="border-top:1px dashed #000; padding-top:10px; font-size:13px;">
            <div style="display:flex; justify-content:space-between; margin-bottom:3px;" id="fis-liste-toplami-satiri">
                <span>Etiket Toplamı:</span>
                <span id="fis-liste-toplami-tutar"></span>
            </div>
            <div style="display:flex; justify-content:space-between; margin-bottom:3px; display:none;" id="fis-nakit-indirim-satiri">
                <span id="fis-nakit-indirim-etiket">İndirim (%5):</span>
                <span id="fis-nakit-indirim-tutar"></span>
            </div>
            <div style="display:flex; justify-content:space-between; margin-bottom:3px;" id="fis-iskonto-satiri">
                <span>İskonto:</span>
                <span id="fis-iskonto-tutar"></span>
            </div>
            <div style="display:flex; justify-content:space-between; font-weight:bold; font-size:16px; margin-top:5px;">
                <span>TOPLAM:</span>
                <span id="fis-genel-toplam"></span>
            </div>
        </div>

        <div style="text-align:center; margin-top:20px; font-size:11px; border-top:1px solid #000; padding-top:10px;">
            Bizi tercih ettiğiniz için teşekkür ederiz.
        </div>
    </div>

    <!-- ==================== GÜN SONU RAPORU ==================== -->

    <!-- Gün Sonu Raporu Modalı -->
    <div id="gun-sonu-modal" class="modal-cerceve" style="display:none; position:fixed; z-index:10002; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.7); align-items:center; justify-content:center;">
        <div class="modal-icerik" style="width:600px !important; max-height:85vh; display:flex; flex-direction:column;">
            <!-- Yükleniyor Durumu -->
            <div id="gun-sonu-yukleniyor" style="text-align:center; padding:40px;">
                <div style="font-size:36px; margin-bottom:15px; animation: gs-spin 1s linear infinite;">⏳</div>
                <p style="margin:0; color:#666;">Günün raporu hazırlanıyor...</p>
            </div>

            <!-- Rapor İçeriği -->
            <div id="gun-sonu-icerik" style="display:none; overflow-y:auto; flex:1;"></div>

            <!-- Butonlar -->
            <div class="modal-butonlar" style="margin-top:15px; flex-shrink:0;">
                <button id="gun-sonu-kapat" style="background:#eee; color:#333;">Kapat</button>
                <button id="gun-sonu-yazdir" style="background:#2c3e50; color:white; display:none;">🖨️ Fiş Yazdır</button>
            </div>
        </div>
    </div>

    <!-- Gün Sonu Fiş Şablonu (Sadece Yazdırma İçin) -->
    <div id="gun-sonu-sablon">
        <!-- JS tarafından doldurulacak -->
    </div>
</div>
