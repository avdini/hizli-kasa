/**
 * Hızlı Kasa - Ana Giriş Noktası (Orchestrator)
 *
 * Tüm modülleri başlatır ve sekmeler arası
 * senkronizasyonu yönetir.
 *
 * @package HizliKasa
 */

document.addEventListener("DOMContentLoaded", function() {
    'use strict';

    var HK = window.HizliKasa;
    if (!HK) {
        console.error("HizliKasa namespace bulunamadı!");
        return;
    }

    // 1. UI Renderer'ı başlat (DOM elementlerini cache'le)
    HK.UIRenderer.init();

    // 2. Barkod okuyucuyu başlat
    HK.BarcodeScanner.init();

    // 3. Modal yöneticisini başlat
    HK.ModalManager.init();

    // 4. Sipariş işlemcisini başlat
    HK.OrderProcessor.init();

    // 5. Fiş yazıcıyı başlat
    HK.ReceiptPrinter.init();

    // 6. Gün sonu raporu modülünü başlat
    HK.DayEndReport.init();

    // 6.1 Anlık kasa durumu modülünü başlat
    if (HK.AnlikKasa) {
        HK.AnlikKasa.init();
    }

    // 7. Tema Yöneticisini başlat
    if (HK.ThemeManager) {
        HK.ThemeManager.init();
    }

    // 7.5 Barkod Render Motorunu başlat
    if (HK.BarcodeRenderer) {
        HK.BarcodeRenderer.init();
    }

    // 7.6 Sipariş Düzenleyiciyi başlat
    if (HK.OrderEditor) {
        HK.OrderEditor.init();
    }

    // 7.7 Rapor modüllerini başlat
    if (HK.OrderEditReports) {
        HK.OrderEditReports.init();
    }

    // 7.8 Raporlar izole fiş yazdırıcıyı başlat
    if (HK.ReportReceiptPrinter) {
        HK.ReportReceiptPrinter.init();
    }

    // 8. Depo Yöneticisini başlat (async — aktif depoyu server'dan/localStorage'dan yükler)
    if (HK.DepoManager) {
        HK.DepoManager.init().then(function() {
            // DepoManager yüklendikten sonra stok terminalini başlat
            if (HK.StockTerminal) {
                HK.StockTerminal.init();
            }
            if (HK.SevkManager) {
                HK.SevkManager.init();
            }
        });
    } else if (HK.StockTerminal) {
        HK.StockTerminal.init();
        if (HK.SevkManager) {
            HK.SevkManager.init();
        }
    }

    // 7. Sekmeler arası canlı senkronizasyon
    window.addEventListener('storage', function(e) {
        var state = HK.State;
        var currentSlotKey = HK.CartManager ? HK.CartManager._slotKey(state.aktifKasaId) : ('hizli_kasa_hafiza_slot_' + state.aktifKasaId);
        
        // Eğer değişiklik bizim aktif kasa slotumuzdaysa sepeti yenile
        if (e.key === currentSlotKey) {
            HK.CartManager.sepetiYukle(state.aktifKasaId);
        }
        // Slotlar arası doluluk bilgisini güncellemek için sidebar'ı yenile
        HK.UIRenderer.sidebarGuncelle();
    });

    // 8. Başlangıç yüklemesi — kayıtlı sepeti yükle
    // Not: DepoManager.init() içinde hkActiveDepoChanged tetiklendiği için
    // CartManager zaten sepeti yükleyecektir. Yine de garantiye alalım.
    var ilkKasaId = localStorage.getItem('hizli_kasa_aktif_id') || 1;
    if (HK.DepoManager && HK.DepoManager.state.isLoaded) {
        HK.CartManager.sepetiYukle(ilkKasaId);
    } else {
        // DepoManager henüz yüklenmediyse bekle (zaten hkActiveDepoChanged ile yüklenecek)
        HK.State.aktifKasaId = parseInt(ilkKasaId);
    }

    console.log("Hızlı Kasa v" + HK.State.CURRENT_VERSION + " başlatıldı.");
});
