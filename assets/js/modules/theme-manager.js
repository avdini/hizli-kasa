/**
 * Hızlı Kasa - Tema Yöneticisi (Theme Manager)
 * 
 * Kullanıcı bazlı tema değişimini ve REST API senkronizasyonunu yönetir.
 */

(function(HK) {
    'use strict';

    HK.ThemeManager = {

        init: function() {
            var self = this;
            
            // Sekme yüklendiğinde butonları bağlayalım
            document.addEventListener('hkTabLoaded', function(e) {
                if (e.detail.tab === 'ayarlar') {
                    self.bindThemeButtons();
                }
            });

            // Eğer ayarlar sekmesi şu an aktifse (veya doğrudan yüklendiyse) bağla
            if (document.querySelector('.terminal-ayarlar-konteyner')) {
                self.bindThemeButtons();
            }
        },

         /**
         * Ayarlar sekmesindeki tema butonlarını bağla
         */
        bindThemeButtons: function() {
            var self = this;
            var buttons = document.querySelectorAll('.btn-tema');
            var app = document.getElementById('hizli-kasa-app');
            var currentTheme = 'light';
            
            if (app) {
                if (app.classList.contains('theme-dark')) currentTheme = 'dark';
            }

            buttons.forEach(function(btn) {
                // İlk yüklemede aktif temayı vurgula
                if (btn.dataset.tema === currentTheme) {
                    btn.classList.add('aktif');
                    btn.style.background = 'var(--hk-accent)';
                    btn.style.color = 'white';
                } else {
                    btn.classList.remove('aktif');
                    btn.style.background = 'var(--hk-bg-body)';
                    btn.style.color = 'var(--hk-text-main)';
                }

                if (!btn.dataset.bound) {
                    btn.addEventListener('click', function() {
                        var theme = this.dataset.tema;
                        self.setTheme(theme);
                    });
                    btn.dataset.bound = 'true';
                }
            });
        },

        /**
         * Temayı uygular ve sunucuya kaydeder
         * @param {string} theme 'light' | 'dark'
         */
        setTheme: function(theme) {
            var app = document.getElementById('hizli-kasa-app');
            if (!app) return;

            // 1. Görünümü güncelle
            app.classList.remove('theme-light', 'theme-dark');
            app.classList.add('theme-' + theme);

            // 2. Buton stillerini güncelle (Ayarlar sekmesindeysek)
            var buttons = document.querySelectorAll('.btn-tema');
            buttons.forEach(function(btn) {
                if (btn.dataset.tema === theme) {
                    btn.classList.add('aktif');
                    btn.style.background = 'var(--hk-accent)';
                    btn.style.color = 'white';
                } else {
                    btn.classList.remove('aktif');
                    btn.style.background = 'var(--hk-bg-body)';
                    btn.style.color = 'var(--hk-text-main)';
                }
            });

            // 3. API ile sunucuya kaydet
            this.saveThemeToServer(theme);

            // 4. Bildirim göster
            if (HK.UIRenderer && HK.UIRenderer.showToast) {
                HK.UIRenderer.showToast('Görünüm teması ' + (theme === 'dark' ? 'karanlık' : 'aydınlık') + ' olarak güncellendi.', 'success');
            }

            // 5. Diğer modülleri bilgilendir (grafik yenileme vs.)
            document.dispatchEvent(new CustomEvent('hkThemeChanged', { detail: { theme: theme } }));
        },

        /**
         * Tema tercihini REST API üzerinden kaydeder
         */
        saveThemeToServer: function(theme) {
            var apiBase = kasaAyar.rootApiUrl || (window.location.origin + '/wp-json/');
            
            fetch(apiBase + 'hizli-kasa/v1/user/set-theme', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': kasaAyar.nonce
                },
                body: JSON.stringify({ theme: theme })
            })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                if (!data.success) {
                    console.error('Tema kaydedilemedi:', data.message);
                }
            })
            .catch(function(err) {
                console.error('Tema API hatası:', err);
            });
        }
    };

})(window.HizliKasa);
