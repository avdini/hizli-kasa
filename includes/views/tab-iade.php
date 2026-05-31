<?php
/**
 * Hızlı Kasa - İade (Refund) Sekmesi HTML Şablonu
 */
if (!defined('ABSPATH')) exit;
?>

<div id="iade-modul-konteyner">
    <div class="iade-sol-panel">
        <div class="iade-ust-cubuk">
            <div class="iade-baslik-satiri">
                <h2 id="iade-sol-baslik">Sipariş Sorgula</h2>
                <div class="iade-baslik-butonlar">
                    <button id="iade-manuel-toggle-btn" class="iade-kucuk-btn iade-btn-accent">➕ Sıfırdan İade</button>
                    <button id="iade-detayli-toggle-btn" class="iade-kucuk-btn">🔍 Detaylı Arama</button>
                </div>
            </div>
            
            <div class="iade-arama-formu">
                <!-- Basit Arama (Varsayılan) -->
                <div id="iade-basit-arama-konteyner" class="arama-satiri basit-arama">
                    <div class="input-grup">
                        <label>Sipariş No / Barkod</label>
                        <div class="input-buton-grup">
                            <input type="text" id="iade-siparis-no" placeholder="Barkod okutun veya No yazın..." autocomplete="off">
                            <button id="iade-siparis-bul-btn" class="iade-arama-btn">Getir</button>
                        </div>
                    </div>
                </div>

                <!-- Manuel Ürün Arama (Gizli) -->
                <div id="iade-manuel-arama-konteyner" class="arama-satiri basit-arama" style="display:none;">
                    <div class="input-grup">
                        <label>Ürün Ara / Barkod Okut</label>
                        <div class="input-buton-grup">
                            <input type="text" id="iade-manuel-urun-ara" placeholder="Ürün adı veya barkod..." autocomplete="off">
                            <button id="iade-manuel-temizle-btn" class="iade-kucuk-btn">Temizle</button>
                        </div>
                    </div>
                </div>

                <!-- Detaylı Arama Alanları (Gizli) -->
                <div id="iade-detayli-alanlar" style="display:none;">
                    <div class="arama-satiri">
                        <div class="input-grup">
                            <label>Müşteri Telefonu</label>
                            <input type="text" id="iade-arama-telefon" placeholder="0 (5xx) xxx xx xx" autocomplete="off">
                        </div>
                        <div class="input-grup">
                            <label>Ürün Barkodu / SKU</label>
                            <input type="text" id="iade-arama-urun" placeholder="Ürün barkodu okutun..." autocomplete="off">
                        </div>
                    </div>
                    <div class="arama-satiri">
                        <div class="input-grup">
                            <label>Tutar Aralığı (Min - Max)</label>
                            <div class="cift-input">
                                <input type="number" id="iade-arama-fiyat-min" placeholder="Min" step="0.01">
                                <input type="number" id="iade-arama-fiyat-max" placeholder="Max" step="0.01">
                            </div>
                        </div>
                        <div class="input-grup">
                            <label>Tarih Aralığı (Başlangıç - Bitiş)</label>
                            <div class="cift-input">
                                <input type="date" id="iade-arama-tarih-bas">
                                <input type="date" id="iade-arama-tarih-bit">
                            </div>
                        </div>
                    </div>
                    <div class="arama-satiri iade-arama-submit-row">
                        <button id="iade-detayli-ara-btn" class="iade-arama-btn tam-genislik">🔍 Seçilen Kriterlerle Siparişleri Bul</button>
                    </div>
                </div>
            </div>
        </div>

        <div id="iade-arama-sonuclari" class="arama-sonuclari-konteyner" style="display:none;">
            <h3>Arama Sonuçları</h3>
            <div class="sonuc-listesi-wrapper">
                <ul id="iade-sonuc-listesi"></ul>
            </div>
        </div>

        <div id="iade-siparis-detay" class="iade-panel-icerik">
            <div class="iade-bos-durum">
                <span class="bos-ikon">🔍</span>
                <p>İşleme başlamak için bir sipariş barkodu okutun.</p>
            </div>
            <!-- Sipariş içeriği buraya dinamik gelecek -->
        </div>
    </div>

    <div class="iade-sag-panel">
        <div class="iade-ust-cubuk">
            <h2>İade Edilecekler</h2>
        </div>
        
        <div id="iade-sepet-icerik" class="iade-panel-icerik">
            <ul id="iade-sepet-listesi">
                <!-- İade ürünleri buraya gelecek -->
            </ul>
        </div>

        <div class="iade-ozet-alani">
            <div class="iade-toplam-satir">
                <span>İADE EDİLECEK TOPLAM:</span>
                <span id="iade-toplam-tutar">0.00 TL</span>
            </div>
            <div class="iade-buton-grubu">
                <button id="degisim-kasaya-gonder-btn" class="iade-islem-btn degisim-btn" disabled>🔄 Değişim İçin Kasaya Gönder</button>
                <button id="iade-onayla-btn" class="iade-islem-btn" disabled>İade Sepetini Onayla</button>
            </div>
        </div>
    </div>
</div>

<!-- İade Onay Modalı -->
<div id="iade-onay-modal" class="hk-modal-overlay">
    <div class="modal-icerik iade-onay-modal-icerik">
        <h3>İadeyi Onayla</h3>
        
        <div class="iade-modal-layout">
            <div class="iade-modal-sol">
                <div id="iade-modal-siparis-ozet" class="siparis-ozet-v2">
                    <!-- Orijinal sipariş özeti buraya kopyalanacak -->
                </div>
            </div>
            
            <div class="iade-modal-sag">
                <div id="iade-modal-ozet" class="iade-modal-ozet-box">
                    <div class="iade-modal-ozet-row">
                        <span class="iade-modal-ozet-label">İade Edilecek Toplam:</span>
                        <div class="iade-modal-toplam-wrap">
                            <input type="text" id="iade-modal-toplam-input" class="hk-currency-mask iade-modal-toplam-input" value="0,00">
                            <span class="iade-modal-toplam-currency">TL</span>
                        </div>
                    </div>
                </div>

                <div id="iade-iskonto-konteyner-alani" class="iade-modal-section">
                    <div id="iade-iskonto-konteyner" style="display:none;">
                        <div class="iskonto-girdi-satiri">
                            <span>Düşülecek İskonto (TL):</span>
                            <input type="text" id="iade-iskonto-input" class="hk-currency-mask" placeholder="0,00" inputmode="decimal" value="0">
                        </div>
                        <div class="iskonto-limit-satiri">
                            <span>Kalan İskonto Limiti:</span>
                            <span id="iade-kalan-iskonto">0.00 TL</span>
                        </div>
                    </div>
                </div>

                <div id="iade-odeme-yontemi-alani" class="iade-modal-section"></div>

                <div class="modal-butonlar">
                    <button id="iade-modal-vazgec" class="iade-btn-cancel">Vazgeç</button>
                    <button id="iade-modal-tamamla" class="iade-btn-confirm">İadeyi Onayla ve Bitir</button>
                </div>
            </div>
        </div>
    </div>
</div>

<template id="iade-urun-satir-template">
    <div class="iade-urun-satir">
        <div class="urun-bilgi">
            <span class="urun-ad"></span>
            <span class="urun-sku"></span>
        </div>
        <div class="urun-fiyat-adet">
            <span class="birim-fiyat"></span> x <span class="mevcut-adet"></span>
        </div>
        <button class="iade-ekle-btn">İade Et</button>
    </div>
</template>
