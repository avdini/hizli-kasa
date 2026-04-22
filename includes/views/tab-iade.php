<?php
/**
 * Hızlı Kasa - İade (Refund) Sekmesi HTML Şablonu
 */
if (!defined('ABSPATH')) exit;
?>

<div id="iade-modul-konteyner">
    <div class="iade-sol-panel">
        <div class="iade-ust-cubuk">
            <h2>Sipariş Sorgula</h2>
            <div class="iade-arama-formu">
                <div class="arama-satiri">
                    <div class="input-grup">
                        <label>Sipariş No / Barkod</label>
                        <input type="text" id="iade-siparis-no" placeholder="Barkod okutun veya No yazın..." autocomplete="off">
                    </div>
                    <div class="input-grup">
                        <label>Müşteri Telefonu</label>
                        <input type="text" id="iade-arama-telefon" placeholder="0 (5xx) xxx xx xx" autocomplete="off">
                    </div>
                </div>
                <div class="arama-satiri">
                    <div class="input-grup">
                        <label>Ürün Barkodu / SKU</label>
                        <input type="text" id="iade-arama-urun" placeholder="Ürün barkodu okutun..." autocomplete="off">
                    </div>
                    <div class="input-grup">
                        <label>Tutar Aralığı (Min - Max)</label>
                        <div class="cift-input">
                            <input type="number" id="iade-arama-fiyat-min" placeholder="Min" step="0.01">
                            <input type="number" id="iade-arama-fiyat-max" placeholder="Max" step="0.01">
                        </div>
                    </div>
                </div>
                <div class="arama-satiri">
                    <div class="input-grup">
                        <label>Tarih Aralığı (Başlangıç - Bitiş)</label>
                        <div class="cift-input">
                            <input type="date" id="iade-arama-tarih-bas">
                            <input type="date" id="iade-arama-tarih-bit">
                        </div>
                    </div>
                    <button id="iade-detayli-ara-btn" class="iade-arama-btn">🔍 Siparişleri Bul</button>
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
            <button id="iade-onayla-btn" class="iade-islem-btn" disabled>İadeyi Onayla ve Faturayı Kes</button>
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
