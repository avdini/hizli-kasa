/**
 * Hızlı Kasa - Sipariş İşlemci (Order Processor)
 *
 * Sipariş oluşturma, stok kontrolü ve WooCommerce API iletişimi.
 *
 * @package HizliKasa
 */

(function(HK) {
    'use strict';

    HK.OrderProcessor = {

        /**
         * Sipariş onay butonunu bağla
         */
        init: function() {
            var self = this;

            document.getElementById("onayla-buton").addEventListener("click", async function() {
                var state = HK.State;
                if (state.sepet.length === 0) return;

                var sorunlar = await self.sonStokKontrolu();

                if (sorunlar.length > 0) {
                    self._stokUyarisiGoster(sorunlar);
                } else {
                    self.siparisIsleminiGerceklestir();
                }
            });

            // Stok Modalı Butonları
            document.getElementById("stok-vazgec").addEventListener("click", function() {
                document.getElementById("stok-uyari-modal").style.display = "none";
                document.getElementById("durum").innerText = "İşlem iptal edildi. Sepeti düzenleyebilirsiniz.";
            });

            document.getElementById("stok-devam").addEventListener("click", function() {
                document.getElementById("stok-uyari-modal").style.display = "none";
                self.siparisIsleminiGerceklestir();
            });
        },

        /**
         * Son stok kontrolü — sipariş öncesi güncel stokları doğrula
         * @returns {Promise<Array>} Sorunlu ürünler listesi
         */
        sonStokKontrolu: async function() {
            var state = HK.State;
            var durumMetni = document.getElementById("durum");
            var stokUyariListe = document.getElementById("stok-uyari-liste");

            durumMetni.innerText = "Güncel stoklar kontrol ediliyor...";
            stokUyariListe.innerHTML = "";
            var sorunluUrunler = [];

            try {
                var checkPromises = state.sepet.map(async function(item) {
                    var id = item.variation_id || item.product_id;
                    var parentId = item.variation_id ? item.product_id : "";

                    var url = item.variation_id
                        ? kasaAyar.apiUrl + 'products/' + parentId + '/variations/' + id
                        : kasaAyar.apiUrl + 'products/' + id;

                    var response = await fetch(url, { headers: { 'X-WP-Nonce': kasaAyar.nonce } });
                    var serverUrun = await response.json();

                    if (serverUrun.manage_stock && serverUrun.stock_quantity !== null) {
                        if (item.quantity > serverUrun.stock_quantity) {
                            sorunluUrunler.push({
                                name: item.name,
                                cartQty: item.quantity,
                                serverQty: serverUrun.stock_quantity
                            });
                        }
                    } else if (serverUrun.stock_status === 'outofstock') {
                        sorunluUrunler.push({
                            name: item.name,
                            cartQty: item.quantity,
                            serverQty: 0
                        });
                    }
                });

                await Promise.all(checkPromises);
            } catch (e) {
                console.error("Stok kontrol hatası", e);
            }

            return sorunluUrunler;
        },

        /**
         * Sipariş oluşturma ana fonksiyonu
         * @param {Object|null} splitData Bölünmüş ödeme verisi (opsiyonel)
         */
        siparisIsleminiGerceklestir: async function(splitData) {
            splitData = splitData || null;
            var state = HK.State;
            var durumMetni = document.getElementById("durum");

            // Sipariş öncesi sekmeler arası çakışmayı önle
            HK.CartManager.sepetiYukle(state.aktifKasaId);

            if (state.sepet.length === 0) return;
            durumMetni.innerText = "İşlem onaylanıyor...";

            // Eğer ödeme bölünmüşse OTOMATİK %5 indirimleri IPTAL et
            var isAutoDiscount = splitData ? false : (state.odemeTipi === "cash" || state.odemeTipi === "iban");

            var temizSepet = state.sepet.map(function(item) {
                var lineSubtotal = item.price * item.quantity;
                var lineTotal = isAutoDiscount ? (lineSubtotal * 0.95) : lineSubtotal;

                var p = {
                    product_id: item.product_id,
                    quantity: item.quantity,
                    subtotal: lineSubtotal.toFixed(2),
                    total: lineTotal.toFixed(2),
                    meta_data: [
                        { key: "Kasiyer", value: kasaAyar.userName || "Kasa Personeli" },
                        { key: "Kasa No", value: state.aktifKasaId.toString() }
                    ]
                };
                if (item.variation_id) p.variation_id = item.variation_id;
                return p;
            });

            var feeLines = [];
            if (state.iskontoTutar > 0) {
                feeLines.push({ name: "İskonto", total: "-" + state.iskontoTutar.toFixed(2), tax_status: "none" });
            }

            var sepetAraToplam = 0;
            state.sepet.forEach(function(item) { sepetAraToplam += (item.price * item.quantity); });
            var netToplam = sepetAraToplam - state.iskontoTutar - (isAutoDiscount ? (sepetAraToplam * 0.05) : 0);

            // Ödeme Yöntemleri (Raporlama İçin)
            var oNakit = 0, oKart = 0, oIban = 0;
            if (splitData) {
                oNakit = splitData.nakit;
                oKart = splitData.kart;
                oIban = splitData.iban;
            } else {
                if (state.odemeTipi === "cash") oNakit = netToplam;
                else if (state.odemeTipi === "iban") oIban = netToplam;
                else oKart = netToplam;
            }

            var paymentMethod = splitData ? "split" : (state.odemeTipi === "card" ? "other" : (state.odemeTipi === "cash" ? "cod" : "bacs"));
            var paymentTitle = splitData ? "Bölünmüş Ödeme" : (state.odemeTipi === "card" ? "Kredi Kartı" : (state.odemeTipi === "cash" ? "Nakit" : "IBAN / Havale"));

            var customerNote = "Kasiyer: " + (kasaAyar.userName || "Kasa Personeli") + ", Kasa " + state.aktifKasaId + " | " +
                (splitData
                    ? "Ödeme Bölündü: Nakit: " + oNakit.toFixed(2) + " TL, Kart: " + oKart.toFixed(2) + " TL, IBAN: " + oIban.toFixed(2) + " TL"
                    : "Ödeme: " + paymentTitle);

            var siparisVerisi = {
                status: kasaAyar.siparisDurumu,
                line_items: temizSepet,
                payment_method: paymentMethod,
                payment_method_title: paymentTitle,
                customer_note: customerNote,
                billing: {
                    first_name: kasaAyar.userName || "Kasa",
                    last_name: "Kasa " + state.aktifKasaId,
                    address_1: "POS Satış",
                    city: "Mağaza",
                    country: "TR"
                },
                shipping: {
                    first_name: kasaAyar.userName || "Kasa",
                    last_name: "Kasa " + state.aktifKasaId
                },
                fee_lines: feeLines,
                meta_data: [
                    { key: "_hizli_kasa_kasiyer", value: kasaAyar.userName || "Kasa Personeli" },
                    { key: "_hizli_kasa_kasa_no", value: state.aktifKasaId.toString() },
                    { key: "_odeme_nakit", value: oNakit.toFixed(2) },
                    { key: "_odeme_kart", value: oKart.toFixed(2) },
                    { key: "_odeme_iban", value: oIban.toFixed(2) },
                    { key: "Ödeme (Nakit)", value: oNakit.toFixed(2) + " TL" },
                    { key: "Ödeme (Kart)", value: oKart.toFixed(2) + " TL" },
                    { key: "Ödeme (IBAN)", value: oIban.toFixed(2) + " TL" }
                ]
            };

            try {
                var response = await fetch(kasaAyar.apiUrl + 'orders', {
                    method: "POST",
                    headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': kasaAyar.nonce },
                    body: JSON.stringify(siparisVerisi)
                });
                var orderResult = await response.json();

                if (response.ok) {
                    if (HK.ReceiptPrinter) {
                        HK.ReceiptPrinter.fisHazirla(orderResult);
                    }
                    durumMetni.innerText = "Sipariş oluşturuldu.";
                    durumMetni.style.color = "#27ae60";
                    HK.CartManager.sepetiTemizle();
                    document.getElementById("fis-onay-modal").style.display = "flex";
                } else {
                    durumMetni.innerText = "HATA: " + (orderResult.message || "API sorunu!");
                    durumMetni.style.color = "red";
                }
            } catch (error) {
                console.error("Sipariş hatası", error);
            }
        },

        /**
         * Stok uyarı modalını göster
         * @param {Array} sorunlar Sorunlu ürün listesi
         */
        _stokUyarisiGoster: function(sorunlar) {
            var stokUyariListe = document.getElementById("stok-uyari-liste");
            var stokUyariModal = document.getElementById("stok-uyari-modal");
            var durumMetni = document.getElementById("durum");

            stokUyariListe.innerHTML = "";
            sorunlar.forEach(function(u) {
                var li = document.createElement("li");
                li.innerHTML = '<span>' + u.name + '</span> <span>İhtiyaç: ' + u.cartQty + ' / Stok: ' + u.serverQty + '</span>';
                stokUyariListe.appendChild(li);
            });

            stokUyariModal.style.display = "flex";
            durumMetni.innerText = "Stok uyarısı verildi!";
        }
    };

})(window.HizliKasa);
