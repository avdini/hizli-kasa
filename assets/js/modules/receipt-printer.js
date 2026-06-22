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
                fisDegisimFarkiSatiri: document.getElementById("fis-degisim-farki-satiri"),
                fisDegisimFarkiTutar: document.getElementById("fis-degisim-farki-tutar"),
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
                var urunIskonto = getMeta(item.meta_data, "_hk_item_discount");
                
                var satirEtiketToplam = etiketFiyat * item.quantity;
                var satirKampanyaToplam = kampanyaFiyat * item.quantity;
                var satirNetToplam = parseFloat(item.total);

                var tr = document.createElement("tr");
                var fiyatHTML = "";
                
                // Üç katmanlı fiyat gösterimi - SADECE SİYAH
                if (satirEtiketToplam > satirKampanyaToplam) {
                    fiyatHTML += '<div style="font-size: 10px; text-decoration: line-through;">' + satirEtiketToplam.toFixed(2) + '</div>';
                }
                if (satirKampanyaToplam > satirNetToplam + 0.01) {
                    fiyatHTML += '<div style="font-size: 10px; text-decoration: line-through;">' + satirKampanyaToplam.toFixed(2) + '</div>';
                }
                fiyatHTML += '<div style="font-weight: bold; font-size: 13px;">' + satirNetToplam.toFixed(2) + '</div>';

                // Ürüne düşen iskonto bilgisi
                var iskontoInfo = '';
                if (urunIskonto && parseFloat(urunIskonto) > 0) {
                    iskontoInfo = '<div style="font-size:9px; color:#666;">(İsk: -' + parseFloat(urunIskonto).toFixed(2) + ')</div>';
                }

                tr.innerHTML = '<td style="padding:1px 0; line-height: 1.1;">' + 
                        '<div style="font-weight:bold; font-size:12px; text-transform:uppercase;">' + item.name + '</div>' +
                        '<div style="font-size:10px;">' + (item.sku ? item.sku + ' | ' : '') + item.quantity + ' Adet' + '</div>' +
                        iskontoInfo +
                    '</td>' +
                    '<td style="text-align:right; padding:1px 0; vertical-align: middle; white-space:nowrap; padding-left:10px;">' + fiyatHTML + '</td>';
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
                yeniSatir.style = "display:flex; justify-content:space-between; margin-bottom:2px; font-size:12px;";
                yeniSatir.innerHTML = '<span>ARA TOPLAM:</span> <span id="fis-ara-toplam-tutar"></span>';
                els.fisListeToplamiSatiri.parentNode.insertBefore(yeniSatir, els.fisNakitIndirimSatiri);
                araToplamElemen = yeniSatir;
            }
            document.getElementById("fis-ara-toplam-tutar").innerText = araToplam.toFixed(2) + " TL";

            var autoDiscountLabel = order.payment_method === "cod" ? "Nakit İndirimi (%5):" : (order.payment_method === "bacs" ? "Havale İndirimi (%5):" : "İndirim (%5):");
            
            var indirimFarki = araToplam - parseFloat(order.total);
            var iskontoTutar = parseFloat(getMeta(order.meta_data, "_hk_toplam_iskonto") || 0);
            if (iskontoTutar === 0) {
                var iskontoFee = order.fee_lines.find(function(f) { return f.name === "İskonto"; });
                iskontoTutar = iskontoFee ? Math.abs(parseFloat(iskontoFee.total)) : 0;
            }
            var otomatikIndirimMeta = getMeta(order.meta_data, "_hk_otomatik_indirim");
            var nakitIndirimTutar = otomatikIndirimMeta !== null ? parseFloat(otomatikIndirimMeta) : (indirimFarki - iskontoTutar);

            if (nakitIndirimTutar > 0.01) {
                els.fisNakitIndirimSatiri.style.display = "flex";
                els.fisNakitIndirimSatiri.style.fontSize = "12px";
                document.getElementById("fis-nakit-indirim-etiket").innerText = autoDiscountLabel;
                els.fisNakitIndirimTutar.innerText = "-" + nakitIndirimTutar.toFixed(2) + " TL";
            } else {
                els.fisNakitIndirimSatiri.style.display = "none";
            }

            if (iskontoTutar > 0) {
                els.fisIskontoSatiri.style.display = "flex";
                els.fisIskontoSatiri.style.fontSize = "12px";
                els.fisIskontoTutar.innerText = "-" + iskontoTutar.toFixed(2) + " TL";
            } else {
                els.fisIskontoSatiri.style.display = "none";
            }

            // Kupon satırını ekle/güncelle
            var couponFee = (order.fee_lines || []).find(function(f) { return f.name.indexOf("İade Çeki") !== -1; });
            var kuponSatirEleman = document.getElementById("fis-kupon-satiri");
            if (couponFee) {
                if (!kuponSatirEleman) {
                    kuponSatirEleman = document.createElement("div");
                    kuponSatirEleman.id = "fis-kupon-satiri";
                    kuponSatirEleman.style = "display:flex; justify-content:space-between; margin-bottom:3px; font-size:12px;";
                    var parent = els.fisIskontoSatiri.parentNode;
                    parent.insertBefore(kuponSatirEleman, els.fisDegisimFarkiSatiri || els.fisGenelToplam.parentNode);
                }
                kuponSatirEleman.style.display = "flex";
                kuponSatirEleman.innerHTML = '<span>' + couponFee.name + ':</span> <span>' + parseFloat(couponFee.total).toFixed(2) + ' TL</span>';
            } else {
                if (kuponSatirEleman) {
                    kuponSatirEleman.style.display = "none";
                }
            }

            var exchangeRefundTotalMeta = getMeta(order.meta_data, "_hk_exchange_refund_total");
            var customerPaidTotalMeta = getMeta(order.meta_data, "_hk_customer_paid_total");
            var exchangeRefundTotal = exchangeRefundTotalMeta !== null ? parseFloat(exchangeRefundTotalMeta) : 0;
            var customerPaidTotal = customerPaidTotalMeta !== null ? parseFloat(customerPaidTotalMeta) : parseFloat(order.total);
            var degisimFarkiFee = (order.fee_lines || []).find(function(f) { return f.name === "Ekstra Değişim Farkı"; });
            if (exchangeRefundTotal > 0.01) {
                if (els.fisDegisimFarkiSatiri) {
                    els.fisDegisimFarkiSatiri.style.display = "flex";
                    els.fisDegisimFarkiSatiri.style.fontSize = "12px";
                    els.fisDegisimFarkiSatiri.querySelector("span:first-child").innerText = "Değişim Mahsubu:";
                    els.fisDegisimFarkiTutar.innerText = "-" + exchangeRefundTotal.toFixed(2) + " TL";
                }
            } else if (degisimFarkiFee && parseFloat(degisimFarkiFee.total) > 0) {
                if (els.fisDegisimFarkiSatiri) {
                    els.fisDegisimFarkiSatiri.style.display = "flex";
                    els.fisDegisimFarkiSatiri.style.fontSize = "12px";
                    els.fisDegisimFarkiSatiri.querySelector("span:first-child").innerText = "Ekstra Değişim Farkı:";
                    els.fisDegisimFarkiTutar.innerText = parseFloat(degisimFarkiFee.total).toFixed(2) + " TL";
                }
            } else if (els.fisDegisimFarkiSatiri) {
                els.fisDegisimFarkiSatiri.style.display = "none";
            }

            els.fisGenelToplam.style.borderTop = "1px solid #000";
            els.fisGenelToplam.style.paddingTop = "5px";
            els.fisGenelToplam.innerText = customerPaidTotal.toFixed(2) + " TL";

            // Barkod Üret (CODE128)
            if (typeof JsBarcode === "function") {
                try {
                    JsBarcode("#fis-barkod", (order.id || order.number).toString(), {
                        format: "CODE128",
                        width: 2,
                        height: 50,
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
         * Kupon fişini doldur ve yazdır
         * @param {Object} couponData Kupon bilgileri (code, amount, phone, date)
         */
        printCouponReceipt: function(couponData) {
            var els = {
                tarih: document.getElementById('fis-coupon-tarih'),
                kodu: document.getElementById('fis-coupon-kodu'),
                tutar: document.getElementById('fis-coupon-tutar'),
                telefon: document.getElementById('fis-coupon-telefon')
            };

            if (!els.tarih || !els.kodu) return;

            els.tarih.innerText = couponData.date || new Date().toLocaleString('tr-TR');
            els.kodu.innerText = couponData.code;
            els.tutar.innerText = parseFloat(couponData.amount).toFixed(2) + " TL";
            els.telefon.innerText = couponData.phone;

            // Barkod Üret (CODE128)
            if (typeof JsBarcode === "function") {
                try {
                    JsBarcode("#fis-coupon-barkod", couponData.code, {
                        format: "CODE128",
                        width: 2,
                        height: 50,
                        displayValue: false,
                        margin: 0,
                        background: "#ffffff",
                        lineColor: "#000000"
                    });
                } catch (e) {
                    console.error("Kupon barkodu oluşturulamadı:", e);
                }
            }

            // Doğrudan kupon yazdırma modunu tetikle
            HK.PrintManager.print('coupon');
        },

        /**
         * Yazdır/Kapat butonları ve klavye kısayollarını bağla
         */
        _bindEvents: function() {
            var els = this.els;

            els.fisYazdirTetik.addEventListener("click", function() {
                HK.PrintManager.print('receipt');
            });

            els.fisYazdirKapat.addEventListener("click", function() {
                els.fisOnayModal.style.display = "none";
            });

            document.addEventListener("keydown", function(e) {
                if (els.fisOnayModal.style.display === "flex") {
                    if (e.key === "Enter") {
                        HK.PrintManager.print('receipt');
                    } else if (e.key === "Escape") {
                        els.fisOnayModal.style.display = "none";
                    }
                }
            });
        }
    };

})(window.HizliKasa);
