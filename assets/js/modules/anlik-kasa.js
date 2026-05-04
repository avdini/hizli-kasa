/**
 * Hızlı Kasa - Anlık Kasa Durumu Modülü
 */
(function($) {
    'use strict';

    window.HizliKasa = window.HizliKasa || {};

    var HK = window.HizliKasa;

    HK.AnlikKasa = {
        init: function() {
            this.cacheElements();
            this.bindEvents();
            this.guncelle(); // İlk açılışta güncelle
        },

        cacheElements: function() {
            this.$buton = $('#anlik-kasa-buton');
            this.$toplamText = $('#anlik-kasa-toplam-text');
            this.$modal = $('#anlik-kasa-modal');
            
            // Modal içindeki değerler
            this.$netKart = $('#anlik-net-kart');
            this.$netIban = $('#anlik-net-iban');
            this.$netNakit = $('#anlik-net-nakit');
            this.$genelNet = $('#anlik-genel-net');
            this.$etiket = $('#anlik-kasa-etiket');
            this.$baslik = $('#anlik-kasa-baslik');
        },

        bindEvents: function() {
            var self = this;

            // Butona tıklandığında modalı aç
            this.$buton.on('click', function() {
                self.guncelle(function() {
                    self.$modal.fadeIn(200).css('display', 'flex');
                });
            });

            // Modal kapatma
            $('#anlik-kasa-kapat').on('click', function() {
                self.$modal.fadeOut(200);
            });

            // Esc ile kapatma
            $(document).on('keydown', function(e) {
                if (e.keyCode === 27 && self.$modal.is(':visible')) {
                    self.$modal.fadeOut(200);
                }
            });

            // Global olay dinleyicileri (Diğer modüllerden tetiklenebilir)
            $(document).on('hk:siparis-tamamlandi hk:iade-tamamlandi hk:masraf-guncellendi', function() {
                self.guncelle();
            });

            // Kasa sekmesi değiştiğinde güncelle
            $(document).on('hk:kasa-degisti', function() {
                self.guncelle();
            });

            // Depo değiştiğinde güncelle
            document.addEventListener('hkActiveDepoChanged', function() {
                self.guncelle();
            });
        },

        guncelle: function(callback) {
            var self = this;
            var kapsam = kasaAyar.anlikKasaKapsam || 'secili';
            var kasaNo = kapsam === 'tum' ? 'all' : (HK.State ? HK.State.aktifKasaId : 1);
            
            // UI'da yükleniyor hissi ver (opsiyonel)
            this.$toplamText.css('opacity', '0.5');

            var depoId = HK.DepoManager ? HK.DepoManager.getActiveDepo() : 0;

            $.ajax({
                url: kasaAyar.rootApiUrl + 'hizli-kasa/v1/gun-sonu-raporu',
                method: 'GET',
                data: {
                    kasa_no: kasaNo,
                    tarih: new Date().toISOString().split('T')[0],
                    depo_id: depoId
                },
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', kasaAyar.nonce);
                },
                success: function(response) {
                    if (response && response.ozet) {
                        var ozet = response.ozet;
                        
                        // Yeni hesaplama: Toplam - Sadece İade (Masraflar düşülmüyor)
                        var nakitGosterilecek = ozet.nakit_toplam - ozet.iade_nakit;
                        var kartGosterilecek = ozet.kart_toplam - ozet.iade_kart;
                        var ibanGosterilecek = ozet.iban_toplam - ozet.iade_iban;
                        var genelGosterilecek = nakitGosterilecek + kartGosterilecek + ibanGosterilecek;

                        // Etiketleri Güncelle
                        var depoAdi = HK.DepoManager ? HK.DepoManager.getActiveDepoName() : '';
                        var depoEtiketi = depoAdi ? ' (' + depoAdi + ')' : '';

                        if (kapsam === 'tum') {
                            self.$etiket.text('(Genel)' + depoEtiketi);
                            self.$baslik.text('Anlık Kasa Durumu (Tüm Kasalar' + depoEtiketi + ')');
                        } else {
                            self.$etiket.text('(Kasa ' + kasaNo + ')' + depoEtiketi);
                            self.$baslik.text('Anlık Kasa Durumu (Kasa ' + kasaNo + depoEtiketi + ')');
                        }

                        // UI Güncelle
                        self.$toplamText.text(HK.UIRenderer ? HK.UIRenderer.formatPara(genelGosterilecek) + ' TL' : genelGosterilecek.toFixed(2) + ' TL');
                        
                        // Modal Güncelle
                        self.$netKart.text(HK.UIRenderer ? HK.UIRenderer.formatPara(kartGosterilecek) + ' TL' : kartGosterilecek.toFixed(2) + ' TL');
                        self.$netIban.text(HK.UIRenderer ? HK.UIRenderer.formatPara(ibanGosterilecek) + ' TL' : ibanGosterilecek.toFixed(2) + ' TL');
                        self.$netNakit.text(HK.UIRenderer ? HK.UIRenderer.formatPara(nakitGosterilecek) + ' TL' : nakitGosterilecek.toFixed(2) + ' TL');
                        self.$genelNet.text(HK.UIRenderer ? HK.UIRenderer.formatPara(genelGosterilecek) + ' TL' : genelGosterilecek.toFixed(2) + ' TL');
                    }
                },
                complete: function() {
                    self.$toplamText.css('opacity', '1');
                    if (typeof callback === 'function') callback();
                }
            });
        }
    };

})(jQuery);
