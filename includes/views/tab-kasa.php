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
            <div class="kasa-sekmeleri-listesi">
                <?php
                $toplam_kasa = get_option('hizli_kasa_toplam_kasa', 3);
                for ($i = 1; $i <= $toplam_kasa; $i++):
                    ?>
                    <div class="sidebar-btn <?php echo ($i === 1) ? 'aktif' : ''; ?>" data-id="<?php echo $i; ?>">
                        <span>📠</span> Kasa <?php echo $i; ?>
                    </div>
                <?php endfor; ?>
            </div>

            <div class="kasa-sabit-butonlar">
                <?php if (get_option('hizli_kasa_siparis_duzenle_aktif', '1') === '1'): ?>
                    <div id="siparis-duzenle-buton" class="sidebar-btn siparis-duzenle"><span>✏️</span> Sipariş Düzenle
                    </div>
                <?php endif; ?>

                <?php if (get_option('hizli_kasa_gun_sonu_aktif', '1') === '1'): ?>
                    <div id="gun-sonu-buton" class="sidebar-btn gun-sonu"><span>📋</span> Kasa Gün Sonu</div>
                <?php endif; ?>

                <?php if (get_option('hizli_kasa_genel_rapor_aktif', '1') === '1'): ?>
                    <div id="genel-rapor-buton" class="sidebar-btn genel-rapor"><span>📊</span> Gün Sonu</div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Ana Kasa Konteyner -->
        <div id="kasa-konteyner">
            <!-- Başlık Alanı -->
            <div class="kasa-baslik">
                <h2 id="durum">Kasa Hazır</h2>
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
                <div class="toplam-ozet" id="odeme-ozeti-alani">
                    <!-- Ödeme kanalları JS ile buraya gelecek -->
                </div>
                <div class="toplam-degerler">
                <div class="toplam-satir" id="liste-toplami-satiri">
                    <span class="toplam-etiket">ETİKET TOPLAMI:</span>
                    <span id="liste-toplami-deger" style="font-size: 18px; font-weight: bold; color: #95a5a6;">0.00
                        TL</span>
                </div>
                <div class="toplam-satir" id="ara-toplam-satiri">
                    <span class="toplam-etiket">ARA TOPLAM:</span>
                    <span id="ara-toplam-deger" style="font-size: 20px; font-weight: bold; color: #7f8c8d;">0.00
                        TL</span>
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
            </div>

            <!-- Ödeme Tipi Seçici -->
            <div class="odeme-secici">
                <div class="odeme-btn" id="bol-buton" data-tip="split">🌓 Ödemeyi Böl</div>
                <div class="odeme-btn aktif" data-tip="card">💳 Kredi Kartı</div>
                <div class="odeme-btn" data-tip="cash">💵 Nakit (-%5)</div>
                <div class="odeme-btn" data-tip="iban">🏦 IBAN (-%5)</div>
            </div>

            <!-- Müşteri Bilgisi (Gizli - Butonla açılır) -->
            <div id="musteri-telefon-panel" class="musteri-bilgi-alani" style="display:none;">
                <div class="musteri-input-grup">
                    <span class="musteri-ikon">👤</span>
                    <input type="text" id="musteri-telefon" class="musteri-telefon-input"
                        placeholder="0 (5xx) xxx xx xx" autocomplete="off">
                    <button id="musteri-telefon-kapat" class="input-temizle-btn">✕</button>
                </div>
            </div>

            <!-- İşlem Butonları -->
            <div class="islem-grubu">
                <div class="islem-sol-grup">
                    <button id="musteri-ekle-btn" title="Müşteri Telefonu Ekle">👤 Müşteri</button>

                    <button id="yuvarla-buton">Küsürat Yuvarla</button>
                    <button id="iskonto-buton">İskonto</button>
                </div>
                <button id="onayla-buton">Sipariş Oluştur</button>
            </div>

        </div>
    </div>

    <!-- ==================== MODALLER ==================== -->

    <!-- Stok Uyarı Modalı -->
    <div id="stok-uyari-modal" class="modal-cerceve"
        style="display:none; position:fixed; z-index:10000; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.7); align-items:center; justify-content:center;">
        <div class="modal-icerik" style="max-width: 500px; width: 90%;">
            <h3 style="color:var(--hk-warning);">⚠️ Kritik Stok Uyarısı!</h3>
            <p>Sepetteki şu ürünler siz işlem yaparken internetten satılmış veya tükenmiş görünüyor:</p>
            <ul id="stok-uyari-liste"></ul>
            <p
                style="font-size: 13px; color: var(--hk-text-muted); background: var(--hk-bg-hover); padding: 10px; border-radius: 8px; border-left: 4px solid var(--hk-warning);">
                <strong>Not:</strong> "Yine de Satış Yap" derseniz işlem tamamlanır ancak ilgili ürünlerin stoğu eksiye
                düşebilir.
            </p>
            <div style="display:flex; gap:10px; margin-top:20px;">
                <button id="stok-vazgec"
                    style="flex:1; padding:12px; border:none; border-radius:8px; cursor:pointer; font-weight:bold; background:var(--hk-border); color:var(--hk-text-main);">❌
                    Geri Dön</button>
                <button id="stok-devam"
                    style="flex:1; padding:12px; border:none; border-radius:8px; cursor:pointer; font-weight:bold; background:var(--hk-warning); color:white;">✅
                    Yine de Satış Yap</button>
            </div>
        </div>
    </div>

    <!-- İskonto Modalı -->
    <div id="iskonto-modal" class="modal-cerceve"
        style="display:none; position:fixed; z-index:10001; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.8); align-items:center; justify-content:center;">
        <div class="modal-icerik">
            <h3>İskonto Tutarı (TL)</h3>
            <div class="iskonto-modal-alan">
                <label for="iskonto-hedef-input">Ödenecek Tutar (TL)</label>
                <input type="text" id="iskonto-hedef-input" class="hk-input hk-currency-mask" placeholder="0,00"
                    inputmode="decimal" style="font-size:24px; text-align:center; font-weight:800; color:var(--hk-text-main);">
            </div>
            <div class="iskonto-modal-alan" style="margin-top:14px;">
                <label for="iskonto-input">İskonto Tutarı (TL)</label>
                <input type="text" id="iskonto-input" class="hk-input hk-currency-mask" placeholder="0,00"
                    inputmode="decimal" style="font-size:24px; text-align:center; font-weight:800; color:var(--hk-danger);">
            </div>
            <small id="iskonto-limit-bilgi" class="iskonto-modal-yardimci-metin"></small>
            <div class="modal-butonlar" style="margin-top:20px;">
                <button id="iskonto-iptal"
                    style="background:var(--hk-bg-hover); color:var(--hk-text-main); border:1px solid var(--hk-border);">İptal</button>
                <button id="iskonto-onay" class="hk-btn-primary" style="padding:12px;">Uygula</button>
            </div>
        </div>
    </div>

    <!-- El ile Ürün Ekleme Modalı -->
    <div id="manuel-urun-modal" class="modal-cerceve"
        style="display:none; position:fixed; z-index:10006; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.8); align-items:center; justify-content:center;">
        <div class="modal-icerik" style="width: 450px !important;">
            <h3>📦 El ile Ürün Ekle</h3>
            <div style="margin-bottom:15px;">
                <label>Ürün Adı</label>
                <input type="text" id="manuel-urun-ad" class="hk-input" placeholder="Örn: Poşet, Özel İndirimli Ürün">
            </div>
            <div style="margin-bottom:20px;">
                <label>Fiyat (TL)</label>
                <input type="number" id="manuel-urun-fiyat" class="hk-input" placeholder="0.00" step="0.01"
                    style="font-size:20px; font-weight:800; color:var(--hk-success);">
            </div>
            <div class="modal-butonlar">
                <button id="manuel-vazgec"
                    style="background:var(--hk-bg-hover); color:var(--hk-text-main); border:1px solid var(--hk-border);">İptal</button>
                <button id="manuel-onayla" class="hk-btn-primary" style="padding:12px;">Sepete Ekle</button>
            </div>
        </div>
    </div>

    <!-- Ödemeyi Böl Modalı -->
    <div id="odeme-bol-modal" class="modal-cerceve"
        style="display:none; position:fixed; z-index:10005; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.8); align-items:center; justify-content:center;">
        <div class="modal-icerik" style="width: 500px !important;">
            <h3>Ödemeyi Böl</h3>
            <p class="modal-not">Ödeme bölünürse otomatik %5 indirimler iptal edilir. <br><strong>Ödenecek Net Tutar:
                    <span id="bol-net-toplam">0.00</span> TL</strong></p>

            <div class="bol-satir">
                <span>💵 Nakit</span>
                <div class="bol-input-grup">
                    <label>Tutar (TL)</label>
                    <input type="text" id="bol-nakit" class="hk-input hk-currency-mask" placeholder="0,00"
                        inputmode="decimal">
                </div>
            </div>
            <div class="bol-satir">
                <span>💳 Kredi Kartı</span>
                <div class="bol-input-grup">
                    <label>Tutar (TL)</label>
                    <input type="text" id="bol-kart" class="hk-input hk-currency-mask" placeholder="0,00"
                        inputmode="decimal">
                </div>
            </div>
            <div class="bol-satir">
                <span>🏦 IBAN</span>
                <div class="bol-input-grup">
                    <label>Tutar (TL)</label>
                    <input type="text" id="bol-iban" class="hk-input hk-currency-mask" placeholder="0,00"
                        inputmode="decimal">
                </div>
            </div>

            <div id="bol-kalan-uyari" class="kalan-eksik">Kalan: <span id="bol-kalan-tutar">0.00</span> TL</div>

            <div class="modal-butonlar" style="margin-top:25px;">
                <button id="bol-vazgec"
                    style="background:var(--hk-bg-hover); color:var(--hk-text-main); border:1px solid var(--hk-border);">Vazgeç</button>
                <button id="bol-onayla" class="hk-btn-primary" style="padding:12px;">Ödemeyi Böl</button>
            </div>
        </div>
    </div>

    <!-- Ürün Arama Modalı -->
    <div id="urun-arama-modal" class="modal-cerceve"
        style="display:none; position:fixed; z-index:10000; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.8); align-items:center; justify-content:center;">
        <div class="modal-icerik" style="width: 90% !important; max-width: 800px !important;">
            <h3>🔍 Ürün Ara</h3>
            <div style="position:relative; margin-bottom:15px;">
                <input type="text" id="urun-arama-input" class="hk-input" placeholder="Ürün adı veya SKU yazın..."
                    autocomplete="off" style="padding-left:45px;">
                <span style="position:absolute; left:15px; top:50%; transform:translateY(-50%); opacity:0.5;">🔍</span>
            </div>
            <ul id="arama-sonuclari">
                <!-- Aramalar buraya gelecek -->
            </ul>
            <div class="modal-butonlar" style="margin-top:20px;">
                <button id="urun-arama-kapat"
                    style="background:var(--hk-bg-hover); color:var(--hk-text-main); border:1px solid var(--hk-border);">Kapat</button>
            </div>
        </div>
    </div>

    <!-- Başarı ve Fiş Modalı -->
    <div id="fis-onay-modal" class="modal-cerceve"
        style="display:none; position:fixed; z-index:10001; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.9); align-items:center; justify-content:center;">
        <div class="modal-icerik" style="text-align:center; padding:50px;">
            <div class="basari-ikon" style="font-size:80px; color:var(--hk-success); margin-bottom:30px;">✓</div>
            <h2 style="color:var(--hk-text-main); margin-bottom:15px; font-weight:800;">Sipariş Oluşturuldu!</h2>
            <p style="font-size:20px; color:var(--hk-text-muted);">Sipariş No: <strong id="fis-order-no"
                    style="color:var(--hk-accent);">#----</strong></p>
            <div class="fis-butonlar" style="margin-top:40px;">
                <button class="hk-btn-primary" id="fis-yazdir-tetik" style="margin-bottom:10px; padding:18px;">🖨️ Fiş
                    Yazdır (Enter)</button>
                <button id="fis-yazdir-kapat"
                    style="width:100%; padding:15px; background:var(--hk-bg-hover); color:var(--hk-text-main); border:1px solid var(--hk-border); border-radius:10px; cursor:pointer; font-weight:700;">Yeni
                    Satışa Geç (Esc)</button>
            </div>
        </div>
    </div>

    <!-- Gizli Fiş Şablonu (Sadece Yazdırma İçin) -->
    <div id="fis-sablon" style="color:#000;">
        <div style="text-align:center; margin-bottom:10px; border-bottom:1px solid #000; padding-bottom:10px;">
            <h2 style="margin:0; font-size:18px;"><?php echo get_bloginfo('name'); ?></h2>
            <p style="margin:5px 0; font-size:12px;">HIZLI KASA SATIŞ FİŞİ</p>
            <p id="fis-tarih" style="margin:0; font-size:11px;"></p>
            <p id="fis-no-text" style="font-weight:bold; margin:5px 0; font-size:14px;"></p>
            <div style="text-align:center; margin-bottom:10px;">
                <svg id="fis-barkod" style="max-width:100%; height:auto;"></svg>
            </div>
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

        <div style="border-top:1px solid #000; padding-top:10px; font-size:13px;">
            <div style="display:flex; justify-content:space-between; margin-bottom:3px;" id="fis-liste-toplami-satiri">
                <span>Etiket Toplamı:</span>
                <span id="fis-liste-toplami-tutar"></span>
            </div>
            <div style="display:flex; justify-content:space-between; margin-bottom:3px; display:none;"
                id="fis-nakit-indirim-satiri">
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
    <div id="gun-sonu-modal" class="modal-cerceve"
        style="display:none; position:fixed; z-index:10002; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.8); align-items:center; justify-content:center;">
        <div class="modal-icerik" style="width:900px !important; max-height:85vh; display:flex; flex-direction:column;">
            <!-- Yükleniyor Durumu -->
            <div id="gun-sonu-yukleniyor" style="text-align:center; padding:40px;">
                <div style="font-size:36px; margin-bottom:15px; animation: gs-spin 1s linear infinite;">⏳</div>
                <p style="margin:0; color:var(--hk-text-muted);">Günün raporu hazırlanıyor...</p>
            </div>

            <!-- Rapor İçeriği -->
            <div id="gun-sonu-icerik" style="display:none; overflow-y:auto; flex:1; color:var(--hk-text-main);"></div>

            <!-- Butonlar -->
            <div class="modal-butonlar" style="margin-top:20px; flex-shrink:0;">
                <button id="gun-sonu-kapat"
                    style="background:var(--hk-bg-hover); color:var(--hk-text-main); border:1px solid var(--hk-border);">Kapat</button>
                <button id="gun-sonu-yazdir-ozet" class="hk-btn-primary"
                    style="display:none; padding:12px; width:auto; min-width:150px;">🖨️ Yazdır</button>
                <button id="gun-sonu-yazdir"
                    style="background:var(--hk-border); color:var(--hk-text-main); display:none; border:none; padding:12px; border-radius:8px; cursor:pointer; font-weight:bold;">🖨️
                    Detaylı Yazdır</button>
            </div>
        </div>
    </div>

    <!-- Gün Sonu Fiş Şablonu (Sadece Yazdırma İçin) -->
    <div id="gun-sonu-sablon">
        <!-- JS tarafından doldurulacak -->
    </div>

    <!-- Sipariş Düzenleme Modalı -->
    <div id="order-edit-modal" class="modal-cerceve"
        style="display:none; position:fixed; z-index:10008; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.8); align-items:center; justify-content:center;">
        <div class="modal-icerik" style="width: 800px !important; max-height: 90vh; overflow-y: auto;">
            <div
                style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; border-bottom:1px solid var(--hk-border); padding-bottom:10px;">
                <h3 style="margin:0;">✏️ Son Siparişleri Düzenle</h3>
                <button class="modal-kapat" id="order-edit-kapat"
                    style="background:none; border:none; font-size:24px; cursor:pointer; color:var(--hk-text-muted);">×</button>
            </div>

            <div id="order-edit-list-view">
                <div id="recent-orders-loading" style="text-align:center; padding:20px;">Siparişler yükleniyor...</div>
                <div id="recent-orders-container" class="recent-orders-list"></div>
            </div>

            <div id="order-edit-detail-view" style="display:none;">
                <button id="order-edit-back" class="hk-btn-secondary" style="margin-bottom:15px;">← Geri Dön</button>
                <div id="order-edit-items-container"></div>

                <div style="margin-top:20px; padding-top:15px; border-top:1px solid var(--hk-border);">
                    <label><strong>Ödeme Yöntemi:</strong></label>
                    <select id="edit-order-payment" class="hk-input" style="margin-top:5px;">
                        <option value="other">Kredi Kartı</option>
                        <option value="cod">Nakit</option>
                        <option value="bacs">IBAN / Havale</option>
                        <option value="split">Bölünmüş Ödeme</option>
                    </select>
                </div>

                <div style="margin-top:15px;">
                    <label><strong>İskonto (TL):</strong></label>
                    <input type="text" id="edit-order-discount" class="hk-input hk-currency-mask" placeholder="0,00"
                        inputmode="decimal" style="margin-top:5px;">
                    <small style="color:var(--hk-text-muted);">Mevcut iskonto otomatik yüklenir.</small>
                </div>

                <div class="modal-butonlar" style="margin-top:30px;">
                    <button id="order-edit-save" class="hk-btn-primary" style="width:100%; padding:15px;">Değişiklikleri
                        Kaydet</button>
                </div>
            </div>
        </div>
    </div>
</div>
