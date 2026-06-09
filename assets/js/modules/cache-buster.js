/**
 * Hızlı Kasa - Cache Buster (Önbellek Yıkıcı)
 *
 * Tüm hizli-kasa/v1/ REST API'ye yapılan GET fetch isteklerine
 * otomatik olarak ?_=timestamp parametresi ekler.
 *
 * Bu sayede tarayıcı veya proxy, eski bir yanıtı cache'den
 * servis edemez. Her istek sunucuya taze olarak gider.
 *
 * Yöntem: Native window.fetch'i wrap ederek global olarak çalışır.
 * Tüm modüllerde (modal-manager, stock-terminal, detailed-reports vb.)
 * tek tek değişiklik yapmaya gerek kalmaz.
 */
(function () {
    'use strict';

    // Orijinal fetch'i sakla
    var _originalFetch = window.fetch;

    window.fetch = function (url, options) {
        // Sadece string URL'leri ve hizli-kasa/v1/ namespace'ini hedef al
        if (typeof url === 'string' && url.indexOf('hizli-kasa/v1/') !== -1) {
            // Safari memory cache koruması — tarayıcının eski response'u servis etmesini engelle
            options = Object.assign({}, options || {}, { cache: 'no-store' });

            var method = (options.method) ? options.method.toUpperCase() : 'GET';

            // Yalnızca GET isteklerine cache-busting uygula
            // (POST/PUT/DELETE zaten önbelleğe alınmaz)
            if (method === 'GET') {
                var separator = url.indexOf('?') !== -1 ? '&' : '?';
                url = url + separator + '_=' + Date.now();
            }
        }

        return _originalFetch.call(this, url, options);
    };
})();
