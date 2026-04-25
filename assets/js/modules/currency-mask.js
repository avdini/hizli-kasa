/**
 * Hızlı Kasa - Para Birimi Maskeleme (Currency Mask)
 * 
 * xxx.xxx.xxx,xx formatında giriş yapılmasını sağlar.
 * Kullanıcı virgül koyana kadar tam sayı olarak kalır.
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
                if (!input.dataset.masked) {
                    self.apply(input);
                    input.dataset.masked = "true";
                }
            });
        },

        /**
         * Belirli bir elemente maskeleme uygula
         * @param {HTMLInputElement} el 
         */
        apply: function(el) {
            var self = this;

            el.addEventListener('input', function(e) {
                var cursorPosition = el.selectionStart;
                var originalValue = el.value;
                
                // Virgül veya nokta (ondalık ayraç olarak kabul et)
                var hasComma = originalValue.includes(',') || originalValue.includes('.');
                
                // Tüm karakterleri temizle, sadece rakam ve tek bir virgül kalsın
                var cleanValue = originalValue.replace(/\./g, '').replace(',', '.');
                
                // Sadece rakamlar ve nokta kalsın
                cleanValue = cleanValue.replace(/[^0-9.]/g, '');
                
                // Birden fazla nokta varsa sadece ilkini tut
                var parts = cleanValue.split('.');
                var integerPart = parts[0];
                var decimalPart = parts.length > 1 ? parts[1].substring(0, 2) : null;

                // Tam sayı kısmını formatla (binlik ayraçlar)
                var formattedInteger = "";
                if (integerPart !== "") {
                    formattedInteger = parseInt(integerPart).toLocaleString('tr-TR');
                } else if (decimalPart !== null) {
                    // Eğer .50 gibi girildiyse 0,50 yap
                    formattedInteger = "0";
                }

                // Nihai değeri oluştur
                var newValue = formattedInteger;
                if (decimalPart !== null || (hasComma && parts.length > 1)) {
                    newValue += "," + (decimalPart || "");
                }

                // Değeri güncelle (Sadece değiştiyse, imlecin sona kaçmasını engellemek için)
                if (el.value !== newValue) {
                    el.value = newValue;

                    // İmleç konumunu ayarla
                    var diff = newValue.length - originalValue.length;
                    el.setSelectionRange(cursorPosition + diff, cursorPosition + diff);
                }
            });

            // Odaklandığında içeriği seç (kolay silme/değiştirme için)
            el.addEventListener('focus', function() {
                setTimeout(function() {
                    el.select();
                }, 10);
            });

            // Virgül tuşuna basıldığında (nokta tuşuna basılsa bile virgül yap)
            el.addEventListener('keydown', function(e) {
                if (e.key === '.') {
                    e.preventDefault();
                    var val = el.value;
                    if (!val.includes(',')) {
                        el.value = val + ',';
                        el.dispatchEvent(new Event('input'));
                    }
                }
            });
        },

        /**
         * Sayıyı TR para formatına çevir (1234.56 -> 1.234,56)
         * @param {number} num 
         * @returns {string}
         */
        format: function(num) {
            if (isNaN(num) || num === null) return "0,00";
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
