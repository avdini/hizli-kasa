/**
 * Hızlı Kasa - Fiş Yazıcı (Receipt Printer)
 *
 * Fiş şablonunu doldurma, yazdırma tetikleme
 * ve fiş onay modalı kısayolları.
 *
 * @package HizliKasa
 */

(function(HK) {
    'use strict';

    HK.ReceiptPrinter = {

        // DOM Referansları
        els: {},

        /**
         * Fiş yazdırma event listener'larını bağla
         */
        init: function() {
            this.els = {
                fisOnayModal: document.getElementById("fis-onay-modal"),
                fisYazdirTetik: document.getElementById("fis-yazdir-tetik"),
                fisYazdirKapat: document.getElementById("fis-yazdir-kapat"),
                fisOrderNo: document.getElementById("fis-order-no"),
                fisUrunlerBody: document.getElementById("fis-urunler-body"),
                fisIskontoSatiri: document.getElementById("fis-iskonto-satiri"),
                fisIskontoTutar: document.getElementById("fis-iskonto-tutar"),
                fisNakitIndirimSatiri: document.getElementById("fis-nakit-indirim-satiri"),
                fisNakitIndirimTutar: document.getElementById("fis-nakit-indirim-tutar"),
                fisListeToplamiSatiri: document.getElementById("fis-liste-toplami-satiri"),
                fisListeToplamiTutar: document.getElementById("fis-liste-toplami-tutar"),
                fisGenelToplam: document.getElementById("fis-genel-toplam"),
                fisTarih: document.getElementById("fis-tarih"),
                fisNoText: document.getElementById("fis-no-text")
            };

            this._bindEvents();
        },

        /**
         * Fiş şablonunu sipariş verileriyle doldur
         * @param {Object} order WooCommerce API'den dönen sipariş objesi
         */
        fisHazirla: function(order) {
            var els = this.els;
            var state = HK.State;
            var self = this;

            // Yardımcı: Meta verisinden değer çek
            var getMeta = function(metaArray, key) {
                var found = metaArray.find(function(m) { return m.key === key; });
                return found ? found.value : null;
            };

            els.fisOrderNo.innerText = "#" + (order.id || order.number);
            els.fisNoText.innerText = "SİPARİŞ NO: " + (order.id || order.number);
            els.fisTarih.innerText = new Date().toLocaleString('tr-TR');

            els.fisUrunlerBody.innerHTML = "";
            order.line_items.forEach(function(item) {
                var etiketFiyat = parseFloat(getMeta(item.meta_data, "_etiket_fiyat") || item.subtotal / item.quantity);
                var kampanyaFiyat = parseFloat(getMeta(item.meta_data, "_kampanya_fiyat") || item.subtotal / item.quantity);
                var netFiyat = parseFloat(item.total) / item.quantity;
                
                var satirEtiketToplam = etiketFiyat * item.quantity;
                var satirKampanyaToplam = kampanyaFiyat * item.quantity;
                var satirNetToplam = parseFloat(item.total);

                var tr = document.createElement("tr");
                var fiyatHTML = "";
                
                // Üç katmanlı fiyat gösterimi
                if (satirEtiketToplam > satirKampanyaToplam) {
                    fiyatHTML += '<div style="font-size: 11px; color: #000; text-decoration: line-through; font-weight: normal;">' + satirEtiketToplam.toFixed(2) + ' TL</div>';
                }
                if (satirKampanyaToplam > satirNetToplam) {
                    fiyatHTML += '<div style="font-size: 11px; color: #000; text-decoration: line-through; font-weight: normal;">' + satirKampanyaToplam.toFixed(2) + ' TL</div>';
                }
                fiyatHTML += '<div style="font-weight: bold; font-size: 14px; color: #000;">' + satirNetToplam.toFixed(2) + ' TL</div>';

                tr.innerHTML = '<td style="padding:1px 0; line-height: 1.1; color: #000;">' + 
                        '<span style="font-weight: 600; font-size: 11px;">' + item.name + '</span><br>' +
                        '<span style="font-size: 10px; color: #000;">' + (item.sku ? item.sku : '') + ' | ' + item.quantity + ' Adet</span>' +
                    '</td>' +
                    '<td style="text-align:right; padding:1px 0; vertical-align: middle;">' + fiyatHTML + '</td>';
                els.fisUrunlerBody.appendChild(tr);
            });

            // Alt Toplamlar
            var etiketToplam = parseFloat(getMeta(order.meta_data, "_etiket_toplami") || order.subtotal);
            var araToplam = parseFloat(getMeta(order.meta_data, "_ara_toplam") || order.total);

            els.fisListeToplamiSatiri.style.display = "flex";
            document.querySelector("#fis-liste-toplami-satiri span:first-child").innerText = "ETİKET TOPLAMI:";
            els.fisListeToplamiTutar.innerText = etiketToplam.toFixed(2) + " TL";

            // Ara Toplam Satırı (Kampanyalı Toplam)
            var araToplamElemen = document.getElementById("fis-ara-toplam-satiri");
            if (!araToplamElemen) {
                // Eğer yoksa Etiket Toplamı'ndan sonra ekle
                var yeniSatir = document.createElement("div");
                yeniSatir.id = "fis-ara-toplam-satiri";
                yeniSatir.style = "display:flex; justify-content:space-between; margin-bottom:3px;";
                yeniSatir.innerHTML = '<span>ARA TOPLAM:</span> <span id="fis-ara-toplam-tutar"></span>';
                els.fisListeToplamiSatiri.parentNode.insertBefore(yeniSatir, els.fisNakitIndirimSatiri);
                araToplamElemen = yeniSatir;
            }
            document.getElementById("fis-ara-toplam-tutar").innerText = araToplam.toFixed(2) + " TL";

            var autoDiscountLabel = order.payment_method === "cod" ? "Nakit İndirimi (%5):" : (order.payment_method === "bacs" ? "Havale İndirimi (%5):" : "İndirim (%5):");
            var isAutoDiscount = (order.payment_method === "cod" || order.payment_method === "bacs") && (order.payment_method !== "split");

            // Otomatik indirim kontrolü (Sipariş notlarından veya fee lines'dan da bakılabilir ama metadan bakmak daha sağlıklı)
            // Ancak şu anki mantıkta net toplam zaten hesaplanmış durumda.
            // Sadece görsel olarak indirimi gösterelim.
            var indirimFarki = araToplam - parseFloat(order.total);
            var iskontoFee = order.fee_lines.find(function(f) { return f.name === "İskonto"; });
            var iskontoTutar = iskontoFee ? Math.abs(parseFloat(iskontoFee.total)) : 0;
            var nakitIndirimTutar = indirimFarki - iskontoTutar;

            if (nakitIndirimTutar > 0.01) {
                els.fisNakitIndirimSatiri.style.display = "flex";
                document.getElementById("fis-nakit-indirim-etiket").innerText = autoDiscountLabel;
                els.fisNakitIndirimTutar.innerText = "-" + nakitIndirimTutar.toFixed(2) + " TL";
            } else {
                els.fisNakitIndirimSatiri.style.display = "none";
            }

            if (iskontoTutar > 0) {
                els.fisIskontoSatiri.style.display = "flex";
                els.fisIskontoTutar.innerText = "-" + iskontoTutar.toFixed(2) + " TL";
            } else {
                els.fisIskontoSatiri.style.display = "none";
            }

            els.fisGenelToplam.innerText = parseFloat(order.total).toFixed(2) + " TL";

            // Barkod Üret (CODE128)
            if (typeof JsBarcode === "function") {
                try {
                    JsBarcode("#fis-barkod", (order.id || order.number).toString(), {
                        format: "CODE128",
                        width: 2,
                        height: 40,
                        displayValue: false,
                        margin: 0,
                        background: "#ffffff",
                        lineColor: "#000000"
                    });
                } catch (e) {
                    console.error("Barkod oluşturulamadı:", e);
                }
            }
        },

        /**
         * Yazdır/Kapat butonları ve klavye kısayollarını bağla
         */
        _bindEvents: function() {
            var els = this.els;

            els.fisYazdirTetik.addEventListener("click", function() {
                // Gün sonu şablonunu gizle (çakışma önleme)
                var gsSablon = document.getElementById("gun-sonu-sablon");
                if (gsSablon) gsSablon.style.display = "none";

                if (HK.PrintHelper) {
                    HK.PrintHelper.setPageStyle('size: auto; margin: 0;');
                }
                window.print();
            });

            els.fisYazdirKapat.addEventListener("click", function() {
                els.fisOnayModal.style.display = "none";
            });

            document.addEventListener("keydown", function(e) {
                if (els.fisOnayModal.style.display === "flex") {
                    if (e.key === "Enter") {
                        if (HK.PrintHelper) {
                            HK.PrintHelper.setPageStyle('size: auto; margin: 0;');
                        }
                        window.print();
                    } else if (e.key === "Escape") {
                        els.fisOnayModal.style.display = "none";
                    }
                }
            });
        }
    };

})(window.HizliKasa);
