<?php
/**
 * Hızlı Kasa - Global Modallar
 * 
 * Bu dosya sekmelerden bağımsız olarak tüm modalları içerir.
 */
if (!defined('ABSPATH')) exit;
?>

<!-- Stok Uyarı Modalı -->
<div id="stok-uyari-modal" class="modal-cerceve" style="display:none;">
    <div class="modal-icerik stok-uyari-icerik">
        <h3>⚠️ Kritik Stok Uyarısı!</h3>
        <p>Sepetteki şu ürünler siz işlem yaparken internetten satılmış veya tükenmiş görünüyor:</p>
        <ul id="stok-uyari-liste"></ul>
        <p class="stok-uyari-not">
            <strong>Not:</strong> "Yine de Satış Yap" derseniz işlem tamamlanır ancak ilgili ürünlerin stoğu eksiye düşebilir.
        </p>
        <div class="masraf-payment-methods" style="margin-top:20px;">
            <button id="stok-vazgec" class="stok-uyari-btn-cancel">❌ Geri Dön</button>
            <button id="stok-devam" class="stok-uyari-btn-confirm">✅ Yine de Satış Yap</button>
        </div>
    </div>
</div>

<!-- İskonto Modalı -->
<div id="iskonto-modal" class="modal-cerceve" style="display:none;">
    <div class="modal-icerik">
        <h3>İskonto Tutarı (TL)</h3>
        <div class="iskonto-modal-alan">
            <label for="iskonto-hedef-input">Ödenecek Tutar (TL)</label>
            <input type="text" id="iskonto-hedef-input" class="hk-input hk-currency-mask iskonto-input-lg" placeholder="0,00" inputmode="decimal">
        </div>
        <div class="iskonto-modal-alan" style="margin-top:14px;">
            <label for="iskonto-input">İskonto Tutarı (TL)</label>
            <input type="text" id="iskonto-input" class="hk-input hk-currency-mask iskonto-input-danger" placeholder="0,00" inputmode="decimal">
        </div>
        <small id="iskonto-limit-bilgi" class="iskonto-modal-yardimci-metin"></small>
        <div class="modal-butonlar" style="margin-top:20px;">
            <button id="iskonto-iptal" class="modal-btn-cancel">İptal</button>
            <button id="iskonto-onay" class="hk-btn-primary" style="padding:12px;">Uygula</button>
        </div>
    </div>
</div>

<!-- Sipariş Notu Modalı -->
<div id="siparis-notu-modal" class="modal-cerceve" style="display:none;">
    <div class="modal-icerik modal-icerik-sm siparis-notu-icerik">
        <h3>📝 Sipariş Notu</h3>
        <textarea id="siparis-notu-input" class="hk-input siparis-notu-textarea" rows="6" maxlength="500" placeholder="Siparişe eklenecek not..."></textarea>
        <div class="siparis-notu-alt">
            <span id="siparis-notu-sayac">0/500</span>
            <button id="siparis-notu-temizle" type="button">Temizle</button>
        </div>
        <div class="modal-butonlar" style="margin-top:18px;">
            <button id="siparis-notu-iptal" class="modal-btn-cancel">Vazgeç</button>
            <button id="siparis-notu-kaydet" class="hk-btn-primary" style="padding:12px;">Kaydet</button>
        </div>
    </div>
</div>

<!-- El ile Ürün Ekleme Modalı -->
<div id="manuel-urun-modal" class="modal-cerceve" style="display:none;">
    <div class="modal-icerik modal-icerik-sm">
        <h3>📦 El ile Ürün Ekle</h3>
        <div class="masraf-form-group">
            <label>Ürün Adı</label>
            <input type="text" id="manuel-urun-ad" class="hk-input" placeholder="Örn: Poşet, Özel İndirimli Ürün">
        </div>
        <div class="masraf-form-group">
            <label>Fiyat (TL)</label>
            <input type="number" id="manuel-urun-fiyat" class="hk-input manuel-fiyat-input" placeholder="0.00" step="0.01">
        </div>
        <div class="modal-butonlar">
            <button id="manuel-vazgec" class="modal-btn-cancel">İptal</button>
            <button id="manuel-onayla" class="hk-btn-primary" style="padding:12px;">Sepete Ekle</button>
        </div>
    </div>
</div>

<!-- Ödemeyi Böl Modalı -->
<div id="odeme-bol-modal" class="modal-cerceve" style="display:none;">
    <div class="modal-icerik modal-icerik-md">
        <h3>Ödemeyi Böl</h3>
        <p class="modal-not"><span id="bol-uyari-metni">Bölme işlemi arka plandaki aktif ödeme kanalının kurallarını baz alır.</span> <br><strong>Ödenecek Net Tutar:
                <span id="bol-net-toplam">0.00</span> TL</strong></p>

        <div class="bol-satir">
            <span>💵 Nakit</span>
            <div class="bol-input-grup">
                <label>Tutar (TL)</label>
                <input type="text" id="bol-nakit" class="hk-input hk-currency-mask" placeholder="0,00" inputmode="decimal">
            </div>
        </div>
        <div class="bol-satir">
            <span>💳 Kredi Kartı</span>
            <div class="bol-input-grup">
                <label>Tutar (TL)</label>
                <input type="text" id="bol-kart" class="hk-input hk-currency-mask" placeholder="0,00" inputmode="decimal">
            </div>
        </div>
        <div class="bol-satir">
            <span>🏦 IBAN</span>
            <div class="bol-input-grup">
                <label>Tutar (TL)</label>
                <input type="text" id="bol-iban" class="hk-input hk-currency-mask" placeholder="0,00" inputmode="decimal">
            </div>
        </div>

        <div id="bol-kalan-uyari" class="kalan-eksik">Kalan: <span id="bol-kalan-tutar">0.00</span> TL</div>

        <div class="modal-butonlar" style="margin-top:25px;">
            <button id="bol-vazgec" class="modal-btn-cancel">Vazgeç</button>
            <button id="bol-onayla" class="hk-btn-primary" style="padding:12px;">Ödemeyi Böl</button>
        </div>
    </div>
</div>

<!-- Ürün Arama Modalı -->
<div id="urun-arama-modal" class="modal-cerceve" style="display:none;">
    <div class="modal-icerik modal-icerik-lg">
        <h3>🔍 Ürün Ara</h3>
        <input type="text" id="urun-arama-input" class="hk-input urun-arama-input" placeholder="Ürün adı veya SKU yazın..." autocomplete="off">
        <ul id="arama-sonuclari">
            <!-- Aramalar buraya gelecek -->
        </ul>
        <div class="modal-butonlar" style="margin-top:20px;">
            <button id="urun-arama-kapat" class="modal-btn-cancel">Kapat</button>
        </div>
    </div>
</div>

<!-- Başarı ve Fiş Modalı -->
<div id="fis-onay-modal" class="modal-cerceve" style="display:none;">
    <div class="modal-icerik fis-onay-icerik">
        <div class="basari-ikon fis-onay-ikon">✓</div>
        <h2 class="fis-onay-baslik">Sipariş Oluşturuldu!</h2>
        <p class="fis-onay-sub">Sipariş No: <strong id="fis-order-no" style="color:var(--hk-accent);">#----</strong></p>
        <div class="fis-butonlar" style="margin-top:40px;">
            <button class="hk-btn-primary fis-yazdir-btn" id="fis-yazdir-tetik">🖨️ Fiş Yazdır (Enter)</button>
            <button id="fis-yazdir-kapat" class="fis-kapat-btn">Yeni Satışa Geç (Esc)</button>
        </div>
    </div>
</div>

<!-- Kupon Doğrulama Modalı -->
<div id="kupon-dogrulama-modal" class="modal-cerceve" style="display:none;">
    <div class="modal-icerik modal-icerik-sm">
        <h3>🎟️ İade Çeki Doğrulama</h3>
        <p style="font-size: 13px; color: var(--hk-text-muted); margin-bottom: 15px;">Bu kuponu kullanabilmek için iade esnasında verilen telefon numarasını doğrulamanız gerekmektedir.</p>
        
        <div class="masraf-form-group">
            <label>Kupon Kodu</label>
            <input type="text" id="dogrulama-kupon-kodu" class="hk-input" readonly disabled style="background-color: var(--hk-bg-hover); font-weight: bold;">
        </div>

        <div class="masraf-form-group" style="margin-top: 15px;">
            <label>Müşteri Telefon Numarası</label>
            <input type="tel" id="dogrulama-kupon-telefon" class="hk-input" placeholder="0 (5xx) xxx xx xx" autocomplete="off">
        </div>

        <div id="kupon-dogrulama-hata" style="color: #e74c3c; font-size: 12px; margin-top: 10px; display: none;"></div>

        <div class="modal-butonlar" style="margin-top: 20px;">
            <button id="kupon-dogrulama-iptal" class="modal-btn-cancel">İptal</button>
            <button id="kupon-dogrulama-onay" class="hk-btn-primary" style="padding:12px;">Doğrula ve Kullan</button>
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
            <img id="fis-barkod" style="width: 100%; max-width: 240px; height: auto; margin: 0 auto; display: block;" />
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
        <div style="display:flex; justify-content:space-between; margin-bottom:3px; display:none;" id="fis-nakit-indirim-satiri">
            <span id="fis-nakit-indirim-etiket">İndirim (%5):</span>
            <span id="fis-nakit-indirim-tutar"></span>
        </div>
        <div style="display:flex; justify-content:space-between; margin-bottom:3px;" id="fis-iskonto-satiri">
            <span>İskonto:</span>
            <span id="fis-iskonto-tutar"></span>
        </div>
        <div style="display:flex; justify-content:space-between; margin-bottom:3px; display:none;" id="fis-degisim-farki-satiri">
            <span>Ekstra Değişim Farkı:</span>
            <span id="fis-degisim-farki-tutar"></span>
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

<!-- İade Kuponu Fiş Şablonu (Sadece Yazdırma İçin) -->
<div id="fis-coupon-sablon" style="color:#000; font-family: 'Courier New', Courier, monospace; padding: 10px 0; box-sizing: border-box; text-align: center;">
    <?php
    $post_date = '';
    $amount = '';
    $coupon_code = '';
    $saved_phone = '';
    include HIZLI_KASA_PATH . 'includes/views/receipt-coupon-template.php';
    ?>
</div>

<!-- Tedarikçi İade Paket Fiş Şablonu (Sadece Yazdırma İçin) -->
<div id="iade-paket-fis-sablon" style="color:#000; font-family: 'Courier New', Courier, monospace; width: 100%; max-width: 300px; margin: 0 auto; padding: 10px; box-sizing: border-box;">
    <div style="text-align:center; margin-bottom:10px; border-bottom:1px dashed #000; padding-bottom:10px;">
        <h2 style="margin:0; font-size:16px; text-transform: uppercase; font-weight: bold;"><?php echo get_bloginfo('name'); ?></h2>
        <p style="margin:4px 0 0; font-size:12px; font-weight: bold; letter-spacing: 1px;">TEDARİKÇİ İADE FİŞİ</p>
    </div>
    
    <div style="font-size:11px; margin-bottom:10px; line-height: 1.3;">
        <div><strong>İADE NO:</strong> <span id="iade-paket-no">TIA-XXXX</span></div>
        <div><strong>TARİH:</strong> <span id="iade-paket-tarih"></span></div>
        <div><strong>TEDARİKÇİ:</strong> <span id="iade-paket-supplier"></span></div>
    </div>

    <table style="width:100%; border-collapse:collapse; font-size:11px; margin-bottom:10px;">
        <thead>
            <tr style="border-bottom:1px dashed #000; font-weight: bold;">
                <th style="text-align:left; padding:4px 0;">Ürün</th>
                <th style="text-align:right; padding:4px 0; width: 50px;">Adet</th>
            </tr>
        </thead>
        <tbody id="iade-paket-urunler-body">
            <!-- Dinamik doldurulacak -->
        </tbody>
    </table>

    <div style="border-top:1px dashed #000; padding-top:6px; font-size:11px; line-height: 1.3; margin-bottom: 10px;">
        <div style="display:flex; justify-content:space-between; font-weight:bold;">
            <span>TOPLAM ÇEŞİT:</span>
            <span id="iade-paket-cesit">0</span>
        </div>
        <div style="display:flex; justify-content:space-between; font-weight:bold;">
            <span>TOPLAM ADET:</span>
            <span id="iade-paket-adet">0</span>
        </div>
    </div>

    <div style="font-size:10px; margin-bottom:15px; border-top:1px dashed #000; padding-top:6px; line-height: 1.2;">
        <div id="iade-paket-sebep-satiri"><strong>SEBEP:</strong> <span id="iade-paket-sebep"></span></div>
        <div id="iade-paket-not-satiri" style="margin-top: 3px;"><strong>NOT:</strong> <span id="iade-paket-not"></span></div>
        <div style="margin-top: 3px;"><strong>HAZIRLAYAN:</strong> <span id="iade-paket-hazirlayan"><?php 
            $current_user = wp_get_current_user(); 
            echo esc_html(trim($current_user->first_name . ' ' . $current_user->last_name) ?: $current_user->display_name); 
        ?></span></div>
    </div>

    <div style="text-align:center;">
        <img id="iade-paket-barkod" style="width: 100%; max-width: 200px; height: auto; margin: 0 auto; display: block;" />
        <span id="iade-paket-barkod-text" style="font-size: 9px; display: block; margin-top: 2px;"></span>
    </div>
</div>

<!-- ==================== GÜN SONU RAPORU ==================== -->

<!-- Gün Sonu Raporu Modalı -->
<div id="gun-sonu-modal" class="modal-cerceve" style="display:none;">
    <div class="modal-icerik modal-icerik-xl">
        <!-- Yükleniyor Durumu -->
        <div id="gun-sonu-yukleniyor" class="gun-sonu-yukleniyor">
            <div style="font-size:36px; margin-bottom:15px; animation: gs-spin 1s linear infinite;">⏳</div>
            <p style="margin:0; color:var(--hk-text-muted);">Günün raporu hazırlanıyor...</p>
        </div>

        <!-- Rapor İçeriği -->
        <div id="gun-sonu-icerik" style="display:none;"></div>

        <!-- Butonlar -->
        <div class="modal-butonlar" style="margin-top:20px; flex-shrink:0;">
            <button id="gun-sonu-kapat" class="modal-btn-cancel">Kapat</button>
            <button id="gun-sonu-yazdir-basit" class="hk-btn-primary gun-sonu-btn-yazdir" style="display:none;">🖨️ Basit Yazdır</button>
            <button id="gun-sonu-yazdir-ozet" class="hk-btn-primary" style="display:none; padding:12px; width:auto; min-width:150px;">🖨️ Yazdır</button>
            <button id="gun-sonu-yazdir" style="background:var(--hk-border); color:var(--hk-text-main); display:none; border:none; padding:12px; border-radius:8px; cursor:pointer; font-weight:bold;">🖨️ Detaylı Yazdır</button>
        </div>
    </div>
</div>

<!-- Gün Sonu Fiş Şablonu (Sadece Yazdırma İçin) -->
<div id="gun-sonu-sablon">
    <!-- JS tarafından doldurulacak -->
</div>

<!-- Sipariş Düzenleme Modalı -->
<div id="order-edit-modal" class="modal-cerceve" style="display:none;">
    <div class="modal-icerik modal-icerik-flex">
        <div class="modal-icerik-header">
            <h3 style="margin:0;">✏️ Son Siparişleri Düzenle</h3>
            <button class="modal-kapat" id="order-edit-kapat" style="background:none; border:none; font-size:24px; cursor:pointer; color:var(--hk-text-muted);">×</button>
        </div>

        <div id="order-edit-list-view">
            <div id="recent-orders-loading" class="loading-text">Siparişler yükleniyor...</div>
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
                <label><strong>Müşteri Telefonu:</strong></label>
                <input type="text" id="edit-order-phone" class="hk-input" placeholder="0 (5xx) xxx xx xx" autocomplete="off" style="margin-top:5px;">
            </div>

            <div style="margin-top:15px;">
                <label><strong>İskonto (TL):</strong></label>
                <input type="text" id="edit-order-discount" class="hk-input hk-currency-mask" placeholder="0,00" inputmode="decimal" style="margin-top:5px;">
                <small style="color:var(--hk-text-muted);">Mevcut iskonto otomatik yüklenir.</small>
            </div>

            <div class="modal-butonlar" style="margin-top:30px;">
                <button id="order-edit-save" class="hk-btn-primary" style="width:100%; padding:15px;">Değişiklikleri Kaydet</button>
            </div>
        </div>
    </div>
</div>

<!-- Anlık Kasa Durumu Modalı -->
<div id="anlik-kasa-modal" class="modal-cerceve" style="display:none;">
    <div class="modal-icerik modal-icerik-mini">
        <h3 class="anlik-kasa-header">
            <span>📊</span> <span id="anlik-kasa-baslik">Anlık Kasa Durumu</span>
        </h3>
        
        <div class="anlik-kasa-row">
            <span>💳 Kart:</span>
            <strong id="anlik-net-kart">0.00 TL</strong>
        </div>
        <div class="anlik-kasa-row">
            <span>🏦 IBAN:</span>
            <strong id="anlik-net-iban">0.00 TL</strong>
        </div>
        <div class="anlik-kasa-row">
            <span>💵 Nakit:</span>
            <strong id="anlik-net-nakit">0.00 TL</strong>
        </div>
        
        <div class="anlik-kasa-total">
            <span>Genel Kasa Durumu:</span>
            <span id="anlik-genel-net">0.00 TL</span>
        </div>
        
        <p class="anlik-kasa-note">
            * Sadece iadeler düşülmüştür, masraflar dahil değildir.
        </p>

        <div class="modal-butonlar" style="margin-top:25px;">
            <button id="anlik-kasa-kapat" class="hk-btn-primary" style="width:100%; padding:12px;">Kapat (Esc)</button>
        </div>
    </div>
</div>
