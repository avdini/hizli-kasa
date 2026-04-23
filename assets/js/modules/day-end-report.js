/**
 * Hızlı Kasa - Gün Sonu Raporu (Day End Report)
 *
 * Kasanın günlük satış özetini API'den çeker,
 * termal fiş şablonuna doldurur ve yazdırır.
 *
 * @package HizliKasa
 */

(function(HK) {
    'use strict';

    HK.DayEndReport = {

        els: {},
        data: null,

        /**
         * Gün Sonu Raporu event listener'larını bağla
         */
        init: function() {
            var self = this;

            this.els = {
                gunSonuModal: document.getElementById("gun-sonu-modal"),
                gunSonuKapat: document.getElementById("gun-sonu-kapat"),
                gunSonuYazdir: document.getElementById("gun-sonu-yazdir"),
                gunSonuYazdirOzet: document.getElementById("gun-sonu-yazdir-ozet"),
                gunSonuIcerik: document.getElementById("gun-sonu-icerik"),
                gunSonuYukleniyor: document.getElementById("gun-sonu-yukleniyor"),
                gunSonuSablon: document.getElementById("gun-sonu-sablon"),
                gunSonuButon: document.getElementById("gun-sonu-buton"),
                genelRaporButon: document.getElementById("genel-rapor-buton")
            };

            if (this.els.gunSonuButon) {
                this.els.gunSonuButon.addEventListener("click", function() {
                    self.raporuGetir(); // Varsayılan: Aktif kasa
                });
            }

            if (this.els.genelRaporButon) {
                this.els.genelRaporButon.addEventListener("click", function() {
                    self.raporuGetir('all'); // Tüm kasalar
                });
            }

            if (this.els.gunSonuKapat) {
                this.els.gunSonuKapat.addEventListener("click", function() {
                    self.els.gunSonuModal.style.display = "none";
                });
            }

            if (this.els.gunSonuYazdir) {
                this.els.gunSonuYazdir.addEventListener("click", function() {
                    self._yazdir(true);
                });
            }

            if (this.els.gunSonuYazdirOzet) {
                this.els.gunSonuYazdirOzet.addEventListener("click", function() {
                    self._yazdir(false);
                });
            }

            // Modal dış tıklama ile kapama
            if (this.els.gunSonuModal) {
                this.els.gunSonuModal.addEventListener("click", function(e) {
                    if (e.target === self.els.gunSonuModal) {
                        self.els.gunSonuModal.style.display = "none";
                    }
                });
            }
        },

        /**
         * API'den gün sonu rapor verisini çek
         * @param {String|Number|null} kasaId Belirli bir kasa ID veya 'all'
         */
        raporuGetir: async function(kasaId) {
            var self = this;
            var state = HK.State;
            var finalKasaNo = kasaId || state.aktifKasaId;

            // Modalı aç, yükleniyor göster
            this.els.gunSonuModal.style.display = "flex";
            this.els.gunSonuYukleniyor.style.display = "block";
            this.els.gunSonuIcerik.style.display = "none";

            try {
                var url = window.location.origin + '/wp-json/hizli-kasa/v1/gun-sonu-raporu?kasa_no=' + finalKasaNo;
                var response = await fetch(url, {
                    headers: { 'X-WP-Nonce': kasaAyar.nonce }
                });
                var rapor = await response.json();

                self.els.gunSonuYukleniyor.style.display = "none";
                self.els.gunSonuIcerik.style.display = "block";

                self.data = rapor;
                self._raporuGoster(rapor);
                self._fisSablonuDoldur(rapor, true);
            } catch (error) {
                console.error("Gün sonu raporu hatası:", error);
                self.els.gunSonuYukleniyor.innerHTML = '<p style="color: #e74c3c; text-align:center;">Rapor yüklenirken hata oluştu!</p>';
            }
        },

        /**
         * Rapor verisini 'önizleme' alanına render et
         * @param {Object} rapor API'den dönen rapor verisi
         */
        _raporuGoster: function(rapor) {
            var icerik = this.els.gunSonuIcerik;
            var ozet = rapor.ozet;

            if (rapor.siparis_sayisi === 0) {
                icerik.innerHTML =
                    '<div style="text-align:center; padding:30px; color:#95a5a6;">' +
                        '<div style="font-size:48px; margin-bottom:15px;">📋</div>' +
                        '<h3 style="margin:0 0 5px;">Bugün İşlem Yok</h3>' +
                        '<p style="margin:0;">Kasa ' + rapor.kasa_no + ' için bugün henüz sipariş oluşturulmamış.</p>' +
                    '</div>';
                this.els.gunSonuYazdir.style.display = "none";
                if (this.els.gunSonuYazdirOzet) this.els.gunSonuYazdirOzet.style.display = "none";
                return;
            }

            this.els.gunSonuYazdir.style.display = "inline-block";
            if (this.els.gunSonuYazdirOzet) this.els.gunSonuYazdirOzet.style.display = "inline-block";

            var html = '';

            // ▧ Genel Bilgiler
            html += '<div class="gs-ozet-baslik">' +
                '<h3 style="margin:0;">📊 Kasa ' + rapor.kasa_no + ' — Gün Sonu Raporu</h3>' +
                '<p style="margin:3px 0 0; color:#999; font-size:13px;">' + rapor.tarih_okunabilir + ' • Rapor: ' + rapor.rapor_zamani + '</p>' +
            '</div>';

            // ▧ Özet Kartlar
            html += '<div class="gs-kart-grid">' +
                this._kartHTML('💰', 'Toplam Ciro', ozet.toplam_ciro.toFixed(2) + ' TL', '#27ae60') +
                this._kartHTML('🧾', 'Sipariş Sayısı', rapor.siparis_sayisi, '#2c3e50') +
                this._kartHTML('📦', 'Satılan Ürün', ozet.urun_adet_toplam + ' Adet', '#3498db') +
                this._kartHTML('🏷️', 'Toplam İskonto', ozet.toplam_iskonto.toFixed(2) + ' TL', '#e74c3c') +
            '</div>';

            // ▧ Ödeme Dağılımı
            html += '<div class="gs-bolum">' +
                '<h4>Ödeme Dağılımı</h4>' +
                '<table class="gs-tablo">' +
                '<tr><td>💵 Nakit</td><td class="gs-sag">' + ozet.nakit_toplam.toFixed(2) + ' TL</td></tr>' +
                '<tr><td>💳 Kredi Kartı</td><td class="gs-sag">' + ozet.kart_toplam.toFixed(2) + ' TL</td></tr>' +
                '<tr><td>🏦 IBAN / Havale</td><td class="gs-sag">' + ozet.iban_toplam.toFixed(2) + ' TL</td></tr>' +
                '<tr class="gs-toplam-satir"><td><strong>TOPLAM</strong></td><td class="gs-sag"><strong>' + ozet.toplam_ciro.toFixed(2) + ' TL</strong></td></tr>' +
                '</table>' +
            '</div>';

            // ▧ Ürün Dağılımı (İlk 15)
            if (rapor.urun_dagilimi && rapor.urun_dagilimi.length > 0) {
                html += '<div class="gs-bolum">' +
                    '<h4>En Çok Satılan Ürünler</h4>' +
                    '<table class="gs-tablo">' +
                    '<tr style="font-weight:bold; border-bottom:2px solid #ddd;"><td>Ürün</td><td class="gs-sag">Adet</td><td class="gs-sag">Tutar</td></tr>';
                
                rapor.urun_dagilimi.slice(0, 15).forEach(function(u) {
                    html += '<tr><td>' + u.name + (u.sku ? ' <small style="color:#999;">' + u.sku + '</small>' : '') + '</td>' +
                        '<td class="gs-sag">' + u.qty + '</td>' +
                        '<td class="gs-sag">' + u.total.toFixed(2) + ' TL</td></tr>';
                });
                html += '</table></div>';
            }

            // ▧ Sipariş Listesi
            html += '<div class="gs-bolum">' +
                '<h4>Siparişler (' + rapor.siparis_sayisi + ')</h4>' +
                '<table class="gs-tablo">' +
                '<tr style="font-weight:bold; border-bottom:2px solid #ddd;"><td>Saat</td><td>Sipariş No</td><td>Ödeme</td><td class="gs-sag">Tutar</td></tr>';
            
            rapor.siparisler.forEach(function(s) {
                html += '<tr>' +
                    '<td>' + s.saat + '</td>' +
                    '<td>#' + s.id + '</td>' +
                    '<td>' + s.odeme_tipi + '</td>' +
                    '<td class="gs-sag">' + s.toplam.toFixed(2) + ' TL</td>' +
                '</tr>';
            });
            html += '</table></div>';

            // ▧ Kasiyer Dağılımı
            if (rapor.kasiyerler && Object.keys(rapor.kasiyerler).length > 0) {
                html += '<div class="gs-bolum">' +
                    '<h4>Kasiyerler</h4>' +
                    '<table class="gs-tablo">';
                for (var kasiyer in rapor.kasiyerler) {
                    html += '<tr><td>' + kasiyer + '</td><td class="gs-sag">' + rapor.kasiyerler[kasiyer] + ' sipariş</td></tr>';
                }
                html += '</table></div>';
            }

            icerik.innerHTML = html;
        },

        /**
         * Küçük bilgi kartı HTML'i oluştur
         */
        _kartHTML: function(icon, label, value, color) {
            return '<div class="gs-kart">' +
                '<span class="gs-kart-ikon">' + icon + '</span>' +
                '<span class="gs-kart-etiket">' + label + '</span>' +
                '<span class="gs-kart-deger" style="color:' + color + ';">' + value + '</span>' +
            '</div>';
        },

        /**
         * Termal fiş şablonunu rapor verisiyle doldur
         * @param {Object} rapor API'den dönen rapor verisi
         * @param {Boolean} includeDetails Detaylı ürün ve sipariş listesi eklensin mi?
         */
        _fisSablonuDoldur: function(rapor, includeDetails) {
            if (includeDetails === undefined) includeDetails = true;
            var sablon = this.els.gunSonuSablon;
            if (!sablon) return;

            var ozet = rapor.ozet;
            var html = '';

            // ─── BAŞLIK ───
            var baslik = (rapor.kasa_no === 'Genel') ? 'GENEL GÜN SONU RAPORU' : 'GÜN SONU RAPORU';
            
            html += '<div style="text-align:center; margin-bottom:8px; border-bottom:2px double #000; padding-bottom:8px;">';
            html += '<h2 style="margin:0; font-size:16px;">' + (document.querySelector('#fis-sablon h2') ? document.querySelector('#fis-sablon h2').innerText : 'MAĞAZA') + '</h2>';
            html += '<p style="margin:3px 0; font-size:13px; font-weight:bold;">═══ ' + baslik + ' ═══</p>';
            html += '<p style="margin:2px 0; font-size:11px;">Kasa: ' + rapor.kasa_no + '</p>';
            html += '<p style="margin:2px 0; font-size:11px;">' + rapor.tarih_okunabilir + '</p>';
            html += '<p style="margin:2px 0; font-size:10px;">Rapor: ' + rapor.rapor_zamani + '</p>';
            html += '</div>';

            if (rapor.siparis_sayisi === 0) {
                html += '<p style="text-align:center; font-size:14px; margin:20px 0;">Bugün işlem yapılmamıştır.</p>';
                sablon.innerHTML = html;
                return;
            }

            // ─── GENEL ÖZET ───
            html += '<div style="margin-bottom:8px;">';
            html += '<p style="font-weight:bold; margin:0 0 4px; font-size:12px; border-bottom:1px dashed #000;">GENEL ÖZET</p>';
            html += '<table style="width:100%; font-size:12px; border-collapse:collapse;">';
            html += '<tr><td>Sipariş Sayısı</td><td style="text-align:right; font-weight:bold;">' + rapor.siparis_sayisi + '</td></tr>';
            html += '<tr><td>Satılan Ürün Adedi</td><td style="text-align:right; font-weight:bold;">' + ozet.urun_adet_toplam + '</td></tr>';
            html += '<tr><td>Toplam İskonto</td><td style="text-align:right; font-weight:bold;">-' + ozet.toplam_iskonto.toFixed(2) + ' TL</td></tr>';
            html += '</table>';
            html += '</div>';

            // ─── ÖDEME DAĞILIMI ───
            html += '<div style="margin-bottom:8px;">';
            html += '<p style="font-weight:bold; margin:0 0 4px; font-size:12px; border-bottom:1px dashed #000;">ÖDEME DAĞILIMI</p>';
            html += '<table style="width:100%; font-size:12px; border-collapse:collapse;">';
            html += '<tr><td>Nakit</td><td style="text-align:right;">' + ozet.nakit_toplam.toFixed(2) + ' TL</td></tr>';
            html += '<tr><td>Kredi Kartı</td><td style="text-align:right;">' + ozet.kart_toplam.toFixed(2) + ' TL</td></tr>';
            html += '<tr><td>IBAN / Havale</td><td style="text-align:right;">' + ozet.iban_toplam.toFixed(2) + ' TL</td></tr>';
            html += '<tr style="border-top:1px solid #000;"><td style="font-weight:bold; font-size:14px;">TOPLAM CİRO</td><td style="text-align:right; font-weight:bold; font-size:14px;">' + ozet.toplam_ciro.toFixed(2) + ' TL</td></tr>';
            html += '</table>';
            html += '</div>';

            // ─── ÜRÜN DAĞILIMI ───
            if (includeDetails && rapor.urun_dagilimi && rapor.urun_dagilimi.length > 0) {
                html += '<div style="margin-bottom:8px;">';
                html += '<p style="font-weight:bold; margin:0 0 4px; font-size:12px; border-bottom:1px dashed #000;">ÜRÜN DAĞILIMI</p>';
                html += '<table style="width:100%; font-size:11px; border-collapse:collapse;">';
                html += '<tr style="border-bottom:1px solid #000;"><th style="text-align:left;">Ürün</th><th style="text-align:right;">Ad.</th><th style="text-align:right;">Tutar</th></tr>';

                rapor.urun_dagilimi.forEach(function(u) {
                    html += '<tr>' +
                        '<td style="padding:2px 0; max-width:100px; overflow:hidden; text-overflow:ellipsis;">' + u.name + '</td>' +
                        '<td style="text-align:right; padding:2px 0;">' + u.qty + '</td>' +
                        '<td style="text-align:right; padding:2px 0;">' + u.total.toFixed(2) + '</td>' +
                    '</tr>';
                });
                html += '</table>';
                html += '</div>';
            }

            // ─── SİPARİŞ LİSTESİ ───
            if (includeDetails) {
                html += '<div style="margin-bottom:8px;">';
            html += '<p style="font-weight:bold; margin:0 0 4px; font-size:12px; border-bottom:1px dashed #000;">SİPARİŞLER (' + rapor.siparis_sayisi + ')</p>';
            html += '<table style="width:100%; font-size:11px; border-collapse:collapse;">';
            html += '<tr style="border-bottom:1px solid #000;"><th style="text-align:left;">Saat</th><th style="text-align:left;">No</th><th style="text-align:left;">Ödm.</th><th style="text-align:right;">Tutar</th></tr>';

            rapor.siparisler.forEach(function(s) {
                html += '<tr>' +
                    '<td style="padding:1px 0;">' + s.saat + '</td>' +
                    '<td style="padding:1px 0;">#' + s.id + '</td>' +
                    '<td style="padding:1px 0; max-width:50px; overflow:hidden;">' + s.odeme_tipi.substring(0, 6) + '</td>' +
                    '<td style="text-align:right; padding:1px 0;">' + s.toplam.toFixed(2) + '</td>' +
                '</tr>';
            });
            html += '</table>';
            html += '</div>';
            }

            // ─── KASİYERLER ───
            if (rapor.kasiyerler && Object.keys(rapor.kasiyerler).length > 0) {
                html += '<div style="margin-bottom:8px;">';
                html += '<p style="font-weight:bold; margin:0 0 4px; font-size:12px; border-bottom:1px dashed #000;">KASİYERLER</p>';
                html += '<table style="width:100%; font-size:11px; border-collapse:collapse;">';
                for (var kasiyer in rapor.kasiyerler) {
                    html += '<tr><td>' + kasiyer + '</td><td style="text-align:right;">' + rapor.kasiyerler[kasiyer] + ' sipariş</td></tr>';
                }
                html += '</table>';
                html += '</div>';
            }

            // ─── ALT BİLGİ ───
            html += '<div style="text-align:center; margin-top:10px; padding-top:8px; border-top:2px double #000; font-size:10px;">';
            html += '<p style="margin:0;">Bu rapor Hızlı Kasa POS<br>sistemi tarafından üretilmiştir.</p>';
            html += '</div>';

            sablon.innerHTML = html;
        },

        /**
         * Fiş şablonunu yazdır
         * @param {Boolean} includeDetails Detaylı rapor mu?
         */
        _yazdir: function(includeDetails) {
            if (!this.data) return;

            // Yazdırmadan önce şablonu güncelleyelim (detaylı veya özet)
            this._fisSablonuDoldur(this.data, includeDetails);

            // Termal fiş şablonunu göster, yazdır, gizle
            var sablon = this.els.gunSonuSablon;
            if (!sablon) return;

            // Normal fiş şablonunu gizle (çakışma önleme)
            var normalFis = document.getElementById("fis-sablon");
            if (normalFis) normalFis.style.display = "none";

            sablon.style.display = "block";
            if (HK.PrintHelper) {
                HK.PrintHelper.setPageStyle('size: auto; margin: 0;');
            }
            window.print();
            sablon.style.display = "none";
        }
    };

})(window.HizliKasa);
