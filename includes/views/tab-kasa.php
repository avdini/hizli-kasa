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
                <div class="kasa-baslik-sag">
                    <button id="anlik-kasa-buton" class="ust-buton-kucuk">
                        <span>📊</span> <span id="anlik-kasa-etiket"></span> Kasa: <strong id="anlik-kasa-toplam-text">...</strong>
                    </button>
                    <button id="manuel-urun-buton" class="ust-buton-kucuk">El ile Ürün Ekle</button>
                </div>
            </div>

            <!-- Barkod İzleme Alanı -->
            <div id="barkod-izleme-konteyner">
                <span id="barkod-izleme-etiket">Giriş:</span>
                <div id="barkod-izleme">...</div>
                <div id="sepet-istatistik-watermark">0 Kalem / 0 Adet</div>
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
                    <span id="liste-toplami-deger">0.00 TL</span>
                </div>
                <div class="toplam-satir" id="ara-toplam-satiri">
                    <span class="toplam-etiket">ARA TOPLAM:</span>
                    <span id="ara-toplam-deger">0.00 TL</span>
                </div>
                <div class="toplam-satir" id="nakit-indirim-satiri" style="display:none !important;">
                    <span class="toplam-etiket" id="nakit-indirim-etiket">İNDİRİM (%5):</span>
                    <span id="nakit-indirim-deger" class="indirim-deger">-0.00 TL</span>
                </div>
                <div class="toplam-satir" id="indirim-satiri" style="display:none !important;">
                    <span class="toplam-etiket"><button id="iskonto-temizle-btn" title="İskontoyu Sıfırla">✕</button> İSKONTO:</span>
                    <span id="indirim-deger" class="indirim-deger">-0.00 TL</span>
                </div>
                <div class="toplam-satir" id="kupon-satiri" style="display:none !important;">
                    <span class="toplam-etiket">İADE ÇEKİ:</span>
                    <span id="kupon-deger" class="indirim-deger">-0.00 TL</span>
                </div>
                <div class="toplam-satir">
                    <span class="toplam-etiket">GENEL TOPLAM:</span>
                    <span id="genel-toplam" class="toplam-deger">0.00 TL</span>
                </div>
            </div>
        </div>

            <!-- Ödeme Tipi Seçici -->
            <div class="odeme-secici">
                <div class="odeme-btn" id="bol-buton" data-tip="split">➗ Ödemeyi Böl</div>
                <div class="odeme-btn aktif" data-tip="card">💳 Kredi Kartı</div>
                <div class="odeme-btn" data-tip="cash">💵 Nakit (-%5)</div>
                <div class="odeme-btn" data-tip="iban">🏦 IBAN (-%5)</div>
            </div>

            <!-- Müşteri Bilgisi (Gizli - Butonla açılır) -->
            <div id="musteri-telefon-panel" class="musteri-bilgi-alani" style="display:none;">
                <div class="musteri-input-grup">
                    <span class="musteri-ikon">👤</span>
                    <input type="tel" id="musteri-telefon" class="musteri-telefon-input"
                        autocomplete="off">
                    <button id="musteri-telefon-kapat" class="input-temizle-btn">✕</button>
                </div>
            </div>

            <!-- İşlem Butonları -->
            <div class="islem-grubu">
                <div class="islem-sol-grup">
                    <button id="musteri-ekle-btn" title="Müşteri Telefonu Ekle">👤 Müşteri</button>
                    <button id="siparis-notu-btn" title="Sipariş Notu Ekle">📝 Not</button>

                    <button id="yuvarla-buton">Küsürat Yuvarla</button>
                    <button id="iskonto-buton">İskonto</button>
                </div>
                <button id="onayla-buton">Sipariş Oluştur</button>
            </div>

        </div>
    </div>

</div>
