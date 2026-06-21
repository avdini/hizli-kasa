/**
 * Hızlı Kasa - Gün Sonu Raporu (Day End Report)
 *
 * Kasanın günlük satış özetini API'den çeker,
 * termal fiş şablonuna doldurur ve yazdırır.
 *
 * @package HizliKasa
 */

(function (HK) {
    'use strict';

    HK.DayEndReport = {

        els: {},
        data: null,

        /**
         * Gün Sonu Raporu event listener'larını bağla
         */
        init: function () {
            var self = this;

            this.els = {
                gunSonuModal: document.getElementById("gun-sonu-modal"),
                gunSonuKapat: document.getElementById("gun-sonu-kapat"),
                gunSonuYazdir: document.getElementById("gun-sonu-yazdir"),
                gunSonuYazdirOzet: document.getElementById("gun-sonu-yazdir-ozet"),
                gunSonuYazdirBasit: document.getElementById("gun-sonu-yazdir-basit"),
                gunSonuIcerik: document.getElementById("gun-sonu-icerik"),
                gunSonuYukleniyor: document.getElementById("gun-sonu-yukleniyor"),
                gunSonuSablon: document.getElementById("gun-sonu-sablon"),
                gunSonuButon: document.getElementById("gun-sonu-buton"),
                genelRaporButon: document.getElementById("genel-rapor-buton")
            };

            if (this.els.gunSonuButon && !this.els.gunSonuButon.dataset.bound) {
                this.els.gunSonuButon.addEventListener("click", function () {
                    self.raporuGetir();
                });
                this.els.gunSonuButon.dataset.bound = "true";
            }

            if (this.els.genelRaporButon && !this.els.genelRaporButon.dataset.bound) {
                this.els.genelRaporButon.addEventListener("click", function () {
                    self.raporuGetir('all');
                });
                this.els.genelRaporButon.dataset.bound = "true";
            }

            if (this.els.gunSonuKapat && !this.els.gunSonuKapat.dataset.bound) {
                this.els.gunSonuKapat.addEventListener("click", function () {
                    self.els.gunSonuModal.style.display = "none";
                });
                this.els.gunSonuKapat.dataset.bound = "true";
            }

            if (this.els.gunSonuYazdir && !this.els.gunSonuYazdir.dataset.bound) {
                this.els.gunSonuYazdir.addEventListener("click", function () {
                    self._yazdir(true);
                });
                this.els.gunSonuYazdir.dataset.bound = "true";
            }

            if (this.els.gunSonuYazdirOzet && !this.els.gunSonuYazdirOzet.dataset.bound) {
                this.els.gunSonuYazdirOzet.addEventListener("click", function () {
                    self._yazdir(false);
                });
                this.els.gunSonuYazdirOzet.dataset.bound = "true";
            }

            if (this.els.gunSonuYazdirBasit && !this.els.gunSonuYazdirBasit.dataset.bound) {
                this.els.gunSonuYazdirBasit.addEventListener("click", function () {
                    self._yazdirBasit();
                });
                this.els.gunSonuYazdirBasit.dataset.bound = "true";
            }

            // Modal dış tıklama ile kapama
            if (this.els.gunSonuModal && !this.els.gunSonuModal.dataset.bound) {
                this.els.gunSonuModal.addEventListener("click", function (e) {
                    if (e.target === self.els.gunSonuModal) {
                        self.els.gunSonuModal.style.display = "none";
                    }
                });
                this.els.gunSonuModal.dataset.bound = "true";
            }
        },

        /**
         * API'den gün sonu rapor verisini çek
         * @param {String|Number|null} kasaId Belirli bir kasa ID veya 'all'
         * @param {String|null} tarih Belirli bir tarih (YYYY-MM-DD)
         */
        raporuGetir: async function (kasaId, tarih) {
            var self = this;
            var state = HK.State;
            var finalKasaNo = kasaId || state.aktifKasaId;

            console.log("HK.DayEndReport: Initializing elements...");
            this.init(); // Her seferinde tazele (Elementler DOM'da yer değiştirmiş olabilir)

            if (!this.els || !this.els.gunSonuModal) {
                console.error("HK.DayEndReport: Modal element found:", !!document.getElementById("gun-sonu-modal"));
                if (HK.UIRenderer) HK.UIRenderer.showToast("Kritik Hata: Rapor penceresi bulunamadı!", "error");
                return;
            }

            // Modalı aç, yükleniyor göster
            this.els.gunSonuModal.style.setProperty("display", "flex", "important");
            this.els.gunSonuYukleniyor.style.display = "block";
            this.els.gunSonuIcerik.style.display = "none";

            try {
                var depoId = HK.DepoManager ? HK.DepoManager.getActiveDepo() : 0;
                var url = `${kasaAyar.rootApiUrl}hizli-kasa/v1/gun-sonu-raporu?kasa_no=${finalKasaNo}&depo_id=${depoId}`;
                if (tarih) {
                    url += '&tarih=' + tarih;
                }

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
                if (HK.UIRenderer) HK.UIRenderer.showToast("Rapor verisi alınamadı!", "error");
            }
        },

        /**
         * Rapor verisini 'önizleme' alanına render et
         * @param {Object} rapor API'den dönen rapor verisi
         */
        _raporuGoster: function (rapor) {
            var icerik = this.els.gunSonuIcerik;
            var ozet = rapor.ozet;

            var hasSales = (rapor.siparis_sayisi > 0);
            var hasRefunds = (ozet.toplam_iade > 0);
            var hasExpenses = (ozet.toplam_masraf > 0);
            var isGenel = (rapor.kasa_no === 'Genel');

            if (!hasSales && !hasRefunds && !hasExpenses) {
                icerik.innerHTML =
                    '<div style="text-align:center; padding:30px; color:#95a5a6;">' +
                    '<div style="font-size:48px; margin-bottom:15px;">📋</div>' +
                    '<h3 style="margin:0 0 5px;">Bugün İşlem Yok</h3>' +
                    '<p style="margin:0;">Kasa ' + rapor.kasa_no + ' için bugün herhangi bir işlem bulunmuyor.</p>' +
                    '</div>';
                this.els.gunSonuYazdir.style.display = "none";
                if (this.els.gunSonuYazdirOzet) this.els.gunSonuYazdirOzet.style.display = "none";
                return;
            }

            this.els.gunSonuYazdir.style.display = "inline-block";
            if (this.els.gunSonuYazdirOzet) this.els.gunSonuYazdirOzet.style.display = "inline-block";
            if (this.els.gunSonuYazdirBasit) {
                this.els.gunSonuYazdirBasit.style.display = (rapor.kasa_no === 'Genel') ? "inline-block" : "none";
            }

            var html = '';

            // ▧ Genel Bilgiler
            var depoAdi = HK.DepoManager ? HK.DepoManager.getActiveDepoName() : '';
            html += '<div class="gs-ozet-baslik">' +
                '<h3 style="margin:0;">📊 Kasa ' + rapor.kasa_no + ' ' + (depoAdi ? '— ' + depoAdi : '') + ' — Gün Sonu Raporu</h3>' +
                '<p style="margin:3px 0 0; color:#999; font-size:13px;">' + rapor.tarih_okunabilir + ' • Rapor: ' + rapor.rapor_zamani + '</p>' +
                '</div>';

            // ▧ Özet Kartlar
            html += '<div class="gs-kart-grid">' +
                this._kartHTML('💰', 'Satış Cirosu', ozet.toplam_ciro.toFixed(2) + ' TL', '#27ae60') +
                this._kartHTML('🧾', 'Sipariş Sayısı', rapor.siparis_sayisi, '#2c3e50') +
                this._kartHTML('📦', 'Satılan Ürün', ozet.urun_adet_toplam + ' Adet', '#3498db') +
                this._kartHTML('🔄', 'İade Tutarı', ozet.toplam_iade.toFixed(2) + ' TL', '#e67e22') +
                '</div>';

            // ▧ Ödeme Dağılımı
            var netCiro = ozet.toplam_ciro - ozet.toplam_iade;

            html += '<div class="gs-bolum">' +
                '<h4>Ödeme Dağılımı</h4>' +
                '<table class="gs-tablo">' +
                '<tr><td>💵 Nakit Satış</td><td class="gs-sag">' + ozet.nakit_toplam.toFixed(2) + ' TL</td></tr>' +
                '<tr><td>💳 Kart Satış</td><td class="gs-sag">' + ozet.kart_toplam.toFixed(2) + ' TL</td></tr>' +
                '<tr><td>🏦 IBAN Satış</td><td class="gs-sag">' + ozet.iban_toplam.toFixed(2) + ' TL</td></tr>' +
                (ozet.kupon_toplam > 0 ? '<tr><td>🎟️ Kupon Satış</td><td class="gs-sag">' + ozet.kupon_toplam.toFixed(2) + ' TL</td></tr>' : '') +
                '<tr class="gs-toplam-satir" style="color:#27ae60;"><td><strong>TOPLAMCİRO</strong></td><td class="gs-sag"><strong>' + ozet.toplam_ciro.toFixed(2) + ' TL</strong></td></tr>';

            if (ozet.toplam_iade > 0) {
                html += '<tr><td colspan="2" style="height:10px;"></td></tr>' +
                    '<tr><td style="color:#e67e22;">🔄 Nakit İade</td><td class="gs-sag" style="color:#e67e22;">-' + ozet.iade_nakit.toFixed(2) + ' TL</td></tr>' +
                    '<tr><td style="color:#e67e22;">🔄 Kart İade</td><td class="gs-sag" style="color:#e67e22;">-' + ozet.iade_kart.toFixed(2) + ' TL</td></tr>' +
                    '<tr><td style="color:#e67e22;">🔄 IBAN İade</td><td class="gs-sag" style="color:#e67e22;">-' + ozet.iade_iban.toFixed(2) + ' TL</td></tr>' +
                    (ozet.iade_kupon > 0 ? '<tr><td style="color:#e67e22;">🔄 Kupon İade</td><td class="gs-sag" style="color:#e67e22;">-' + ozet.iade_kupon.toFixed(2) + ' TL</td></tr>' : '');
            }

            if (isGenel && ozet.toplam_masraf > 0) {
                html += '<tr><td colspan="2" style="height:10px;"></td></tr>';
                if (ozet.nakit_masraf > 0) {
                    html += '<tr><td style="color:#c0392b;">💸 Nakit Masraf</td><td class="gs-sag" style="color:#c0392b;">-' + ozet.nakit_masraf.toFixed(2) + ' TL</td></tr>';
                }
                if (ozet.kart_masraf > 0) {
                    html += '<tr><td style="color:#c0392b;">💸 Kart Masraf</td><td class="gs-sag" style="color:#c0392b;">-' + ozet.kart_masraf.toFixed(2) + ' TL</td></tr>';
                }
                if (ozet.iban_masraf > 0) {
                    html += '<tr><td style="color:#c0392b;">💸 IBAN Masraf</td><td class="gs-sag" style="color:#c0392b;">-' + ozet.iban_masraf.toFixed(2) + ' TL</td></tr>';
                }
                netCiro -= ozet.toplam_masraf;
            }

            html += '<tr><td colspan="2" style="height:15px;"></td></tr>' +
                '<tr><td colspan="2" style="background:#f1f2f6; padding:8px; font-weight:bold; text-align:center; border-radius:4px; color:#2f3542;">📊 NET DURUM</td></tr>' +
                '<tr><td colspan="2" style="height:5px;"></td></tr>' +
                '<tr><td><strong>NET NAKİT (Kasadaki)</strong></td><td class="gs-sag"><strong>' + ozet.net_nakit.toFixed(2) + ' TL</strong></td></tr>' +
                '<tr><td><strong>NET KART</strong></td><td class="gs-sag"><strong>' + ozet.net_kart.toFixed(2) + ' TL</strong></td></tr>' +
                '<tr><td><strong>NET IBAN</strong></td><td class="gs-sag"><strong>' + ozet.net_iban.toFixed(2) + ' TL</strong></td></tr>' +
                '<tr class="gs-toplam-satir" style="background:#f8f9fa;"><td><strong>NET KASA TOPLAMI</strong></td><td class="gs-sag"><strong>' + netCiro.toFixed(2) + ' TL</strong></td></tr>' +
                '</table>' +
                '</div>';

            // ▧ Ürün Dağılımı (İlk 15)
            if (rapor.urun_dagilimi && rapor.urun_dagilimi.length > 0) {
                html += '<div class="gs-bolum">' +
                    '<h4>En Çok Satılan Ürünler</h4>' +
                    '<table class="gs-tablo">' +
                    '<tr style="font-weight:bold; border-bottom:2px solid #ddd;"><td>Ürün</td><td class="gs-sag">Adet</td><td class="gs-sag">Tutar</td></tr>';

                rapor.urun_dagilimi.slice(0, 15).forEach(function (u) {
                    html += '<tr><td>' + u.name + (u.sku ? ' <small style="color:#999;">' + u.sku + '</small>' : '') + '</td>' +
                        '<td class="gs-sag">' + u.qty + '</td>' +
                        '<td class="gs-sag">' + u.total.toFixed(2) + ' TL</td></tr>';
                });
                html += '</table></div>';
            }

            // ▧ Sipariş Listesi
            if (rapor.siparis_sayisi > 0) {
                html += '<div class="gs-bolum">' +
                    '<h4>Siparişler (' + rapor.siparis_sayisi + ')</h4>' +
                    '<table class="gs-tablo">' +
                    '<tr style="font-weight:bold; border-bottom:2px solid #ddd;"><td>Saat</td><td>Sipariş No</td><td>Ödeme</td><td class="gs-sag">Tutar</td></tr>';

                rapor.siparisler.forEach(function (s) {
                    html += '<tr>' +
                        '<td>' + s.saat + '</td>' +
                        '<td>#' + s.id + '</td>' +
                        '<td>' + s.odeme_tipi + '</td>' +
                        '<td class="gs-sag">' + s.toplam.toFixed(2) + ' TL</td>' +
                        '</tr>';
                });
                html += '</table></div>';
            }

            // ▧ İade Listesi
            if (rapor.iade_siparisler && rapor.iade_siparisler.length > 0) {
                html += '<div class="gs-bolum">' +
                    '<h4>İade İşlemleri (' + rapor.ozet.iade_adet + ')</h4>' +
                    '<table class="gs-tablo">' +
                    '<tr style="font-weight:bold; border-bottom:2px solid #ddd;"><td>Saat</td><td>Sipariş No</td><td>Ödeme</td><td class="gs-sag">Tutar</td></tr>';

                rapor.iade_siparisler.forEach(function (s) {
                    html += '<tr>' +
                        '<td>' + s.saat + '</td>' +
                        '<td>#' + s.id + '</td>' +
                        '<td>' + s.odeme_tipi + '</td>' +
                        '<td class="gs-sag" style="color:#e67e22;">-' + s.toplam.toFixed(2) + ' TL</td>' +
                        '</tr>';
                });
                html += '</table></div>';
            }

            // ▧ Masraf Listesi
            if (isGenel && rapor.masraf_detay && rapor.masraf_detay.length > 0) {
                html += '<div class="gs-bolum">' +
                    '<h4>Masraf Giderleri (' + rapor.masraf_detay.length + ')</h4>' +
                    '<table class="gs-tablo">' +
                    '<tr style="font-weight:bold; border-bottom:2px solid #ddd;"><td>Kategori</td><td>Açıklama</td><td>Yöntem</td><td class="gs-sag">Tutar</td></tr>';

                rapor.masraf_detay.forEach(function (m) {
                    html += '<tr>' +
                        '<td>' + m.kategori + '</td>' +
                        '<td>' + (m.aciklama || '-') + '</td>' +
                        '<td>' + m.yontem.toUpperCase() + '</td>' +
                        '<td class="gs-sag" style="color:#c0392b;">-' + m.tutar.toFixed(2) + ' TL</td>' +
                        '</tr>';
                });
                html += '</table></div>';
            }

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
        _kartHTML: function (icon, label, value, color) {
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
        _fisSablonuDoldur: function (rapor, includeDetails) {
            if (includeDetails === undefined) includeDetails = true;
            var sablon = this.els.gunSonuSablon;
            if (!sablon) return;

            var ozet = rapor.ozet;
            var html = '';

            // ─── BAŞLIK ───
            var baslik = (rapor.kasa_no === 'Genel') ? 'GENEL GÜN SONU RAPORU' : 'GÜN SONU RAPORU';
            var depoAdi = HK.DepoManager ? HK.DepoManager.getActiveDepoName() : '';

            html += '<div style="text-align:center; margin-bottom:8px; border-bottom:1px solid #000; padding-bottom:8px;">';
            html += '<h2 style="margin:0; font-size:16px;">' + (document.querySelector('#fis-sablon h2') ? document.querySelector('#fis-sablon h2').innerText : 'MAĞAZA') + '</h2>';
            html += '<p style="margin:3px 0; font-size:13px; font-weight:bold;">' + baslik + '</p>';
            html += '<p style="margin:2px 0; font-size:11px;">Kasa: ' + rapor.kasa_no + (depoAdi ? ' / ' + depoAdi : '') + '</p>';
            html += '<p style="margin:2px 0; font-size:11px;">' + rapor.tarih_okunabilir + '</p>';
            html += '<p style="margin:2px 0; font-size:10px;">Rapor: ' + rapor.rapor_zamani + '</p>';
            html += '</div>';

            var hasSales = (rapor.siparis_sayisi > 0);
            var hasRefunds = (ozet.toplam_iade > 0);
            var hasExpenses = (ozet.toplam_masraf > 0);
            var isGenel = (rapor.kasa_no === 'Genel');

            if (!hasSales && !hasRefunds && !hasExpenses) {
                html += '<p style="text-align:center; font-size:14px; margin:20px 0;">Bugün işlem yapılmamıştır.</p>';
                sablon.innerHTML = html;
                return;
            }

            // ─── GENEL ÖZET ───
            html += '<div style="margin-bottom:8px;">';
            html += '<p style="font-weight:bold; margin:0 0 4px; font-size:12px; border-bottom:1px solid #000;">GENEL ÖZET</p>';
            html += '<table style="width:100%; font-size:12px; border-collapse:collapse;">';
            html += '<tr><td>Sipariş Sayısı</td><td style="text-align:right; font-weight:bold;">' + rapor.siparis_sayisi + '</td></tr>';
            html += '<tr><td>Satılan Ürün</td><td style="text-align:right; font-weight:bold;">' + ozet.urun_adet_toplam + '</td></tr>';
            html += '<tr><td>İade Tutarı</td><td style="text-align:right; font-weight:bold;">' + ozet.toplam_iade.toFixed(2) + ' TL</td></tr>';
            html += '<tr><td>İskonto</td><td style="text-align:right; font-weight:bold;">-' + ozet.toplam_iskonto.toFixed(2) + ' TL</td></tr>';
            html += '</table>';
            html += '</div>';

            // ─── ÖDEME DAĞILIMI ───
            var netCiro = ozet.toplam_ciro - ozet.toplam_iade;
            html += '<div style="margin-bottom:8px;">';
            html += '<p style="font-weight:bold; margin:0 0 4px; font-size:12px; border-bottom:1px solid #000;">ÖDEME DAĞILIMI</p>';
            html += '<table style="width:100%; font-size:12px; border-collapse:collapse;">';
            html += '<tr><td>Kredi Kartı</td><td style="text-align:right;">' + ozet.kart_toplam.toFixed(2) + ' TL</td></tr>';
            html += '<tr><td>IBAN / Havale</td><td style="text-align:right;">' + ozet.iban_toplam.toFixed(2) + ' TL</td></tr>';
            html += '<tr><td>Nakit Satış</td><td style="text-align:right;">' + ozet.nakit_toplam.toFixed(2) + ' TL</td></tr>';
            html += '<tr style="border-top:1px dashed #000;"><td style="font-weight:bold; font-size:14px; padding-top:2px;">TOPLAM CİRO</td><td style="text-align:right; font-weight:bold; font-size:14px; padding-top:2px;">' + ozet.toplam_ciro.toFixed(2) + ' TL</td></tr>';
            html += '</table>';
            html += '</div>';

            // GİDERLER
            var toplamGider = (ozet.toplam_iade || 0);
            if (isGenel) {
                toplamGider += (ozet.toplam_masraf || 0);
            }

            if (toplamGider > 0) {
                html += '<div style="margin-bottom:8px;">';
                html += '<p style="font-weight:bold; margin:0 0 4px; font-size:12px; border-bottom:1px solid #000;">GİDERLER</p>';
                html += '<table style="width:100%; font-size:12px; border-collapse:collapse;">';
                
                // İadeler
                if (ozet.iade_kart > 0) html += '<tr><td>Kart İade</td><td style="text-align:right;">-' + ozet.iade_kart.toFixed(2) + ' TL</td></tr>';
                if (ozet.iade_iban > 0) html += '<tr><td>IBAN İade</td><td style="text-align:right;">-' + ozet.iade_iban.toFixed(2) + ' TL</td></tr>';
                if (ozet.iade_nakit > 0) html += '<tr><td>Nakit İade</td><td style="text-align:right;">-' + ozet.iade_nakit.toFixed(2) + ' TL</td></tr>';
                if (ozet.iade_kupon > 0) html += '<tr><td>Kupon İade</td><td style="text-align:right;">-' + ozet.iade_kupon.toFixed(2) + ' TL</td></tr>';
                
                // Masraflar (Sadece Genel raporda)
                if (isGenel) {
                    if (ozet.kart_masraf > 0) html += '<tr><td>Kart Masraf</td><td style="text-align:right;">-' + ozet.kart_masraf.toFixed(2) + ' TL</td></tr>';
                    if (ozet.iban_masraf > 0) html += '<tr><td>IBAN Masraf</td><td style="text-align:right;">-' + ozet.iban_masraf.toFixed(2) + ' TL</td></tr>';
                    if (ozet.nakit_masraf > 0) html += '<tr><td>Nakit Masraf</td><td style="text-align:right;">-' + ozet.nakit_masraf.toFixed(2) + ' TL</td></tr>';
                    netCiro -= ozet.toplam_masraf;
                }

                html += '<tr style="border-top:1px dashed #000;"><td style="font-weight:bold; font-size:13px; padding-top:2px;">TOPLAM GİDER</td><td style="text-align:right; font-weight:bold; font-size:13px; padding-top:2px;">-' + toplamGider.toFixed(2) + ' TL</td></tr>';
                html += '</table>';
                html += '</div>';
            }


            // ─── NET KASA DURUMU ───
            html += '<div style="margin-bottom:8px;">';
            html += '<p style="font-weight:bold; font-size:13px; text-align:center; margin:0 0 5px;">--- NET KASA DURUMU ---</p>';
            html += '<table style="width:100%; font-size:12px; border-collapse:collapse;">';
            html += '<tr style="border-bottom:1px dashed #eee;"><td>Net Kart Toplamı</td><td style="text-align:right; font-weight:bold;">' + ozet.net_kart.toFixed(2) + ' TL</td></tr>';
            html += '<tr style="border-bottom:1px dashed #eee;"><td>Net IBAN Toplamı</td><td style="text-align:right; font-weight:bold;">' + ozet.net_iban.toFixed(2) + ' TL</td></tr>';
            html += '<tr style="border-bottom:1px dashed #eee;"><td>Net Nakit Toplamı</td><td style="text-align:right; font-weight:bold;">' + ozet.net_nakit.toFixed(2) + ' TL</td></tr>';
            html += '<tr style="border-top:1px solid #000;"><td style="font-weight:bold; font-size:14px; padding-top:4px;">NET KASA TOPLAMI</td><td style="text-align:right; font-weight:bold; font-size:14px; padding-top:4px;">' + netCiro.toFixed(2) + ' TL</td></tr>';
            html += '</table>';
            html += '</div>';

            // ─── ÜRÜN DAĞILIMI ───
            if (includeDetails && rapor.urun_dagilimi && rapor.urun_dagilimi.length > 0) {
                html += '<div style="margin-bottom:8px;">';
                html += '<p style="font-weight:bold; margin:0 0 4px; font-size:12px; border-bottom:1px solid #000;">ÜRÜN DAĞILIMI</p>';
                html += '<table style="width:100%; font-size:11px; border-collapse:collapse;">';
                html += '<tr style="border-bottom:1px solid #000;"><th style="text-align:left;">Ürün</th><th style="text-align:right;">Ad.</th><th style="text-align:right;">Tutar</th></tr>';

                rapor.urun_dagilimi.forEach(function (u) {
                    html += '<tr>' +
                        '<td style="padding:1px 0; max-width:140px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">' + u.name + '</td>' +
                        '<td style="text-align:right; padding:1px 0;">' + u.qty + '</td>' +
                        '<td style="text-align:right; padding:1px 0;">' + u.total.toFixed(2) + '</td>' +
                        '</tr>';
                });
                html += '</table>';
                html += '</div>';
            }

            // ─── SİPARİŞ LİSTESİ ───
            if (includeDetails && rapor.siparis_sayisi > 0) {
                html += '<div style="margin-bottom:8px;">';
                html += '<p style="font-weight:bold; margin:0 0 4px; font-size:12px; border-bottom:1px solid #000;">SİPARİŞLER (' + rapor.siparis_sayisi + ')</p>';
                html += '<table style="width:100%; font-size:11px; border-collapse:collapse;">';
                html += '<tr style="border-bottom:1px solid #000;"><th style="text-align:left;">Saat</th><th style="text-align:left;">No</th><th style="text-align:left;">Ödm.</th><th style="text-align:right;">Tutar</th></tr>';

                rapor.siparisler.forEach(function (s) {
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

            // ─── İADE İŞLEMLERİ ───
            if (includeDetails && rapor.iade_siparisler && rapor.iade_siparisler.length > 0) {
                html += '<div style="margin-bottom:8px;">';
                html += '<p style="font-weight:bold; margin:0 0 4px; font-size:12px; border-bottom:1px solid #000;">İADE İŞLEMLERİ (' + rapor.ozet.iade_adet + ')</p>';
                html += '<table style="width:100%; font-size:11px; border-collapse:collapse;">';
                html += '<tr style="border-bottom:1px solid #000;"><th style="text-align:left;">Saat</th><th style="text-align:left;">No</th><th style="text-align:left;">Ödm.</th><th style="text-align:right;">Tutar</th></tr>';

                rapor.iade_siparisler.forEach(function (s) {
                    html += '<tr>' +
                        '<td style="padding:1px 0;">' + s.saat + '</td>' +
                        '<td style="padding:1px 0;">#' + s.id + '</td>' +
                        '<td style="padding:1px 0; max-width:50px; overflow:hidden;">' + s.odeme_tipi.substring(0, 6) + '</td>' +
                        '<td style="text-align:right; padding:1px 0;">-' + s.toplam.toFixed(2) + '</td>' +
                        '</tr>';
                });
                html += '</table>';
                html += '</div>';
            }

            // ─── MASRAF GİDERLERİ ───
            if (includeDetails && isGenel && rapor.masraf_detay && rapor.masraf_detay.length > 0) {
                html += '<div style="margin-bottom:8px;">';
                html += '<p style="font-weight:bold; margin:0 0 4px; font-size:12px; border-bottom:1px solid #000;">MASRAFLAR (' + rapor.masraf_detay.length + ')</p>';
                html += '<table style="width:100%; font-size:11px; border-collapse:collapse;">';
                html += '<tr style="border-bottom:1px solid #000;"><th style="text-align:left;">Kategori</th><th style="text-align:right;">Tutar</th></tr>';

                rapor.masraf_detay.forEach(function (m) {
                    html += '<tr>' +
                        '<td style="padding:1px 0; max-width:140px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">' + m.kategori + '</td>' +
                        '<td style="text-align:right; padding:1px 0;">-' + m.tutar.toFixed(2) + '</td>' +
                        '</tr>';
                });
                html += '</table>';
                html += '</div>';
            }

            // ─── KASİYERLER ───
            if (rapor.kasiyerler && Object.keys(rapor.kasiyerler).length > 0) {
                html += '<div style="margin-bottom:8px;">';
                html += '<p style="font-weight:bold; margin:0 0 4px; font-size:12px; border-bottom:1px solid #000;">KASİYERLER</p>';
                html += '<table style="width:100%; font-size:11px; border-collapse:collapse;">';
                for (var kasiyer in rapor.kasiyerler) {
                    html += '<tr><td>' + kasiyer + '</td><td style="text-align:right;">' + rapor.kasiyerler[kasiyer] + ' sipariş</td></tr>';
                }
                html += '</table>';
                html += '</div>';
            }

            // ─── ALT BİLGİ ───
            html += '<div style="text-align:center; margin-top:10px; padding-top:8px; border-top:1px solid #000; font-size:10px;">';
            html += '<p style="margin:0;">Bu rapor Hızlı Kasa POS<br>sistemi tarafından üretilmiştir.</p>';
            html += '</div>';

            html += '<div style="height:120px;"></div>'; // Alt kısma boşluk ekle (Yazıcıda kesilmemesi için)

            sablon.innerHTML = html;
        },

        /**
         * Fiş şablonunu yazdır
         * @param {Boolean} includeDetails Detaylı rapor mu?
         */
        _yazdir: function (includeDetails) {
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
            HK.PrintManager.print('report');
            sablon.style.display = "none";
        },

        /**
         * Basit fiş şablonunu yazdır
         */
        _yazdirBasit: function () {
            if (!this.data) return;

            this._fisSablonuDoldurBasit(this.data);

            var sablon = this.els.gunSonuSablon;
            if (!sablon) return;

            var normalFis = document.getElementById("fis-sablon");
            if (normalFis) normalFis.style.display = "none";

            sablon.style.display = "block";
            HK.PrintManager.print('report');
            sablon.style.display = "none";
        },

        /**
         * Basit fiş şablonunu doldur (iadeler düşülmüş rakamlar)
         */
        _fisSablonuDoldurBasit: function (rapor) {
            var sablon = this.els.gunSonuSablon;
            if (!sablon) return;

            var ozet = rapor.ozet;
            var html = '';

            var netKart = (ozet.kart_toplam || 0) - (ozet.iade_kart || 0);
            var netIban = (ozet.iban_toplam || 0) - (ozet.iade_iban || 0);
            var netNakit = (ozet.nakit_toplam || 0) - (ozet.iade_nakit || 0);
            var genelToplam = (ozet.toplam_ciro || 0) - (ozet.toplam_iade || 0);
            
            var masrafKart = (ozet.kart_masraf || 0);
            var masrafIban = (ozet.iban_masraf || 0);
            var masrafNakit = (ozet.nakit_masraf || 0);
            var toplamMasraf = (ozet.toplam_masraf || 0);
            
            var netKasa = genelToplam - toplamMasraf;

            // ─── TARİH BAŞLIĞI ───
            var now = new Date();
            var year = now.getFullYear();
            var month = String(now.getMonth() + 1).padStart(2, '0');
            var day = String(now.getDate()).padStart(2, '0');
            var hour = String(now.getHours()).padStart(2, '0');
            var minute = String(now.getMinutes()).padStart(2, '0');
            var second = String(now.getSeconds()).padStart(2, '0');
            var fullDate = year + ' ' + month + ' ' + day + ' ' + hour + ':' + minute + ':' + second;

            html += '<div style="text-align:center; font-family:monospace; font-size:14px; border-bottom:1px solid #000; padding-bottom:5px; margin-bottom:10px; font-weight:bold;">' + fullDate + '</div>';

            html += '<div style="margin-bottom:10px;">';
            html += '<table style="width:100%; font-size:14px; border-collapse:collapse; font-family:monospace;">';
            
            html += '<tr style="border-bottom:1px dashed #000;"><td style="padding:4px 0;">GENEL TOPLAM</td><td style="text-align:right; font-weight:bold;">' + genelToplam.toFixed(2) + ' TL</td></tr>';
            html += '<tr><td style="padding:4px 0;">KART TOPLAM</td><td style="text-align:right;">' + netKart.toFixed(2) + ' TL</td></tr>';
            html += '<tr><td style="padding:4px 0;">IBAN TOPLAM</td><td style="text-align:right;">' + netIban.toFixed(2) + ' TL</td></tr>';
            html += '<tr><td style="padding:4px 0;">NAKİT TOPLAM</td><td style="text-align:right;">' + netNakit.toFixed(2) + ' TL</td></tr>';
            
            if (toplamMasraf > 0) {
                html += '<tr><td colspan="2" style="height:10px;"></td></tr>';
                
                if (masrafKart > 0) {
                    html += '<tr><td style="padding:4px 0;">KART MASRAF</td><td style="text-align:right;">-' + masrafKart.toFixed(2) + ' TL</td></tr>';
                }
                if (masrafIban > 0) {
                    html += '<tr><td style="padding:4px 0;">IBAN MASRAF</td><td style="text-align:right;">-' + masrafIban.toFixed(2) + ' TL</td></tr>';
                }
                if (masrafNakit > 0) {
                    html += '<tr><td style="padding:4px 0;">NAKİT MASRAF</td><td style="text-align:right;">-' + masrafNakit.toFixed(2) + ' TL</td></tr>';
                }

                // DEĞİŞEN NETLERİ GÖSTER
                html += '<tr><td colspan="2" style="height:10px;"></td></tr>';
                html += '<tr><td colspan="2" style="border-top:1px dashed #000;"></td></tr>';
                if (masrafKart > 0) {
                    html += '<tr style="font-size:14px;"><td style="padding:6px 0; font-weight:bold;">NET KART</td><td style="text-align:right; font-weight:bold;">' + (netKart - masrafKart).toFixed(2) + ' TL</td></tr>';
                }
                if (masrafIban > 0) {
                    html += '<tr style="font-size:14px;"><td style="padding:6px 0; font-weight:bold;">NET IBAN</td><td style="text-align:right; font-weight:bold;">' + (netIban - masrafIban).toFixed(2) + ' TL</td></tr>';
                }
                if (masrafNakit > 0) {
                    html += '<tr style="font-size:14px;"><td style="padding:6px 0; font-weight:bold;">NET NAKİT</td><td style="text-align:right; font-weight:bold;">' + (netNakit - masrafNakit).toFixed(2) + ' TL</td></tr>';
                }
            }
            
            // html += '<tr style="border-top:1px solid #000; font-size:16px;"><td style="padding:6px 0; font-weight:bold;">NET KASA TOPLAMI</td><td style="text-align:right; font-weight:bold;">' + netKasa.toFixed(2) + ' TL</td></tr>';
            
            html += '</table>';
            html += '<div style="height:120px;"></div>'; // Alt kısma boşluk ekle (Yazıcıda kesilmemesi için)
            html += '</div>';

            sablon.innerHTML = html;
        }
    };

})(window.HizliKasa);
