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
            <div class="iade-arama-alani">
                <input type="text" id="iade-siparis-no" placeholder="Sipariş Barkodu veya No Okutun..." autocomplete="off">
                <button id="iade-siparis-bul-btn">Siparişi Getir</button>
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
