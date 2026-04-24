/**
 * Hızlı Kasa - Para Birimi Maskeleme (Currency Mask)
 * 
 * xxx.xxx.xxx,xx formatında giriş yapılmasını sağlar.
 * 
 * @package HizliKasa
 */

(function(HK) {
    'use strict';

    HK.CurrencyMask = {

        /**
         * Başlatıcı: Sayfadaki tüm .hk-currency-mask elementlerine uygula
         */
        init: function() {
            var self = this;
            var inputs = document.querySelectorAll('.hk-currency-mask');
            inputs.forEach(function(input) {
                self.apply(input);
            });

            // Dinamik olarak eklenen elementler için gözlemci (isteğe bağlı)
            // Şimdilik sadece mevcutlara uygula.
        },

        /**
         * Belirli bir elemente maskeleme uygula
         * @param {HTMLInputElement} el 
         */
        apply: function(el) {
            var self = this;

            el.addEventListener('input', function(e) {
                var val = el.value;
                
                // Sadece rakamları al
                var cleanValue = val.replace(/\D/g, '');
                
                if (cleanValue === '') {
                    el.value = '';
                    return;
                }

                // Sayıya çevir (son iki hane kuruş)
                var numberValue = parseFloat(cleanValue) / 100;
                
                // TR formatında yazdır
                el.value = self.format(numberValue);
            });

            // Odaklandığında boşsa 0,00 yapma (kullanıcıyı yormasın)
            // Ama değer varsa formatlı kalsın.
        },

        /**
         * Sayıyı TR para formatına çevir (1234.56 -> 1.234,56)
         * @param {number} num 
         * @returns {string}
         */
        format: function(num) {
            if (isNaN(num)) return "0,00";
            return num.toLocaleString('tr-TR', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        },

        /**
         * Formatlı string'i sayıya çevir (1.234,56 -> 1234.56)
         * @param {string} str 
         * @returns {number}
         */
        parse: function(str) {
            if (!str) return 0;
            // Noktaları (binlik) kaldır, virgülü (ondalık) noktaya çevir
            var clean = str.toString().replace(/\./g, '').replace(',', '.');
            return parseFloat(clean) || 0;
        }
    };

    // DOM yüklendiğinde otomatik çalıştır
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            HK.CurrencyMask.init();
        });
    } else {
        HK.CurrencyMask.init();
    }
    
    // Lazy loaded sekmeler için tekrar çalıştır
    document.addEventListener('hkTabLoaded', function() {
        HK.CurrencyMask.init();
    });

})(window.HizliKasa = window.HizliKasa || {});
