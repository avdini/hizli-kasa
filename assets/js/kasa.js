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

    // 7. Sekmeler arası canlı senkronizasyon
    window.addEventListener('storage', function(e) {
        var state = HK.State;
        // Eğer değişiklik bizim aktif kasa slotumuzdaysa sepeti yenile
        if (e.key === 'hizli_kasa_hafiza_slot_' + state.aktifKasaId) {
            HK.CartManager.sepetiYukle(state.aktifKasaId);
        }
        // Slotlar arası doluluk bilgisini güncellemek için sidebar'ı yenile
        HK.UIRenderer.sidebarGuncelle();
    });

    // 8. Başlangıç yüklemesi — kayıtlı sepeti yükle
    HK.CartManager.sepetiYukle(localStorage.getItem('hizli_kasa_aktif_id') || 1);

    console.log("Hızlı Kasa v" + HK.State.CURRENT_VERSION + " başlatıldı.");
});
