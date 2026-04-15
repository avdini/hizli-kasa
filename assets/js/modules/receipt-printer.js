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

            els.fisOrderNo.innerText = "#" + (order.id || order.number);
            els.fisNoText.innerText = "SİPARİŞ NO: " + (order.id || order.number);
            els.fisTarih.innerText = new Date().toLocaleString('tr-TR');

            els.fisUrunlerBody.innerHTML = "";
            order.line_items.forEach(function(item) {
                var tr = document.createElement("tr");
                tr.innerHTML = '<td style="padding:5px 0;">' + item.name + ' <br><small>' + (item.sku ? item.sku : '') + ' | ' + item.quantity + ' Adet</small></td>' +
                    '<td style="text-align:right; padding:5px 0;">' + parseFloat(item.total).toFixed(2) + ' TL</td>';
                els.fisUrunlerBody.appendChild(tr);
            });

            var autoDiscountLabel = state.odemeTipi === "cash" ? "Nakit İndirimi (%5):" : "Havale İndirimi (%5):";
            var isAutoDiscount = (state.odemeTipi === "cash" || state.odemeTipi === "iban");

            var orderSubtotal = 0;
            var orderTotalBeforeFees = 0;
            order.line_items.forEach(function(li) { 
                orderSubtotal += parseFloat(li.subtotal);
                orderTotalBeforeFees += parseFloat(li.total);
            });

            els.fisListeToplamiSatiri.style.display = "flex";
            els.fisListeToplamiTutar.innerText = orderSubtotal.toFixed(2) + " TL";

            if (isAutoDiscount) {
                var totalBeforeAuto = 0;
                order.line_items.forEach(function(li) { totalBeforeAuto += parseFloat(li.subtotal); });
                var autoDiscountTotal = totalBeforeAuto * 0.05;

                els.fisNakitIndirimSatiri.style.display = "flex";
                document.getElementById("fis-nakit-indirim-etiket").innerText = autoDiscountLabel;
                els.fisNakitIndirimTutar.innerText = "-" + autoDiscountTotal.toFixed(2) + " TL";
            } else {
                els.fisNakitIndirimSatiri.style.display = "none";
            }

            var iskontoFee = order.fee_lines.find(function(f) { return f.name === "İskonto"; });
            if (iskontoFee) {
                els.fisIskontoSatiri.style.display = "flex";
                els.fisIskontoTutar.innerText = iskontoFee.total + " TL";
            } else {
                els.fisIskontoSatiri.style.display = "none";
            }

            els.fisGenelToplam.innerText = parseFloat(order.total).toFixed(2) + " TL";
        },

        /**
         * Yazdır/Kapat butonları ve klavye kısayollarını bağla
         */
        _bindEvents: function() {
            var els = this.els;

            els.fisYazdirTetik.addEventListener("click", function() {
                window.print();
            });

            els.fisYazdirKapat.addEventListener("click", function() {
                els.fisOnayModal.style.display = "none";
            });

            document.addEventListener("keydown", function(e) {
                if (els.fisOnayModal.style.display === "flex") {
                    if (e.key === "Enter") {
                        window.print();
                    } else if (e.key === "Escape") {
                        els.fisOnayModal.style.display = "none";
                    }
                }
            });
        }
    };

})(window.HizliKasa);
