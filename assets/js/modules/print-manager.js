/**
 * Hızlı Kasa - Yazdırma Yöneticisi (Print Manager)
 * 
 * Farklı yazdırma modları (fiş, barkod, rapor) için dinamik CSS ve @page kurallarını yönetir.
 */

(function(HK) {
    'use strict';

    HK.PrintManager = {
        
        /**
         * Yazdırma işlemini başlatır
         * @param {'receipt'|'barcode'|'report'} mode - Yazdırma modu
         */
        print: function(mode) {
            var body = document.body;
            var modeClass = 'print-mode-' + mode;
            
            // 1. Body'ye mod sınıfı ekle
            body.classList.add(modeClass);
            
            // 2. Dinamik @page stili enjekte et
            var pageStyle = document.getElementById('hk-dynamic-page-style');
            if (!pageStyle) {
                pageStyle = document.createElement('style');
                pageStyle.id = 'hk-dynamic-page-style';
                document.head.appendChild(pageStyle);
            }
            
            if (mode === 'barcode') {
                // 50x35mm barkod etiketi
                pageStyle.textContent = '@media print { @page { size: 50mm 35mm; margin: 0; } }';
            } else {
                // Termal fiş ve rapor (genişlik otomatik, margin yok)
                pageStyle.textContent = '@media print { @page { size: auto; margin: 0; } }';
            }
            
            // 3. Yazdırma sonrası temizlik
            var cleanup = function() {
                body.classList.remove(modeClass);
                var style = document.getElementById('hk-dynamic-page-style');
                if (style) {
                    style.textContent = ''; // Temizle ama elementi bırakabiliriz veya silebiliriz
                }
                window.removeEventListener('afterprint', cleanup);
                console.log('Print cleanup done for mode:', mode);
            };
            
            window.addEventListener('afterprint', cleanup);
            
            // 4. Yazdır (Tarayıcıya zaman tanımak gerekebilir)
            setTimeout(function() {
                window.print();
            }, 50);
        }
    };

})(window.HizliKasa = window.HizliKasa || {});
