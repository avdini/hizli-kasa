/**
 * Hızlı Kasa - Sepet Yöneticisi (Cart Manager)
 *
 * Sepet CRUD işlemleri, localStorage yönetimi,
 * çapraz kasa kontrolü ve ürün ekleme mantığı.
 *
 * @package HizliKasa
 */

window.HizliKasa = window.HizliKasa || {};

(function(HK) {
    'use strict';

    // Merkezi State (Tüm modüller bu state'e erişir)
    HK.State = {
        aktifKasaId: 1,
        sepet: [],
        iskontoTutar: 0,
        odemeTipi: "card",
        splitData: null,
        lastUpdatedId: null,
        CURRENT_VERSION: typeof kasaAyar !== 'undefined' && kasaAyar.version ? kasaAyar.version : "2.8.8",
        MAX_KASA: 4
    };

    HK.CartManager = {

        /**
         * localStorage anahtarını depo bazlı oluşturur
         * @param {number|string} kasaId
         */
        _slotKey: function(kasaId) {
            var depoId = HK.DepoManager ? HK.DepoManager.getActiveDepo() : 0;
            return 'hizli_kasa_hafiza_slot_' + kasaId + '_depo_' + depoId;
        },

        /**
         * Mevcut sepeti localStorage'a kaydet
         */
        sepetiKaydet: function() {
            var state = HK.State;
            localStorage.setItem('hizli_kasa_aktif_id', state.aktifKasaId);

            var veri = {
                sepet: state.sepet,
                iskontoTutar: state.iskontoTutar,
                odemeTipi: state.odemeTipi,
                splitData: state.splitData
            };
            localStorage.setItem(this._slotKey(state.aktifKasaId), JSON.stringify(veri));

            if (HK.UIRenderer) {
                HK.UIRenderer.sidebarGuncelle();
            }
        },

        /**
         * Belirtilen kasa slotundan sepeti yükle
         * @param {number|string} kasaId Kasa numarası
         */
        sepetiYukle: function(kasaId) {
            var state = HK.State;
            if (kasaId) state.aktifKasaId = parseInt(kasaId);

            var kaydedilen = localStorage.getItem(this._slotKey(state.aktifKasaId));
            if (kaydedilen) {
                try {
                    var veri = JSON.parse(kaydedilen);
                    state.sepet = veri.sepet || [];
                    state.iskontoTutar = veri.iskontoTutar || 0;
                    state.odemeTipi = veri.odemeTipi || "card";
                    state.splitData = veri.splitData || null;

                    // Geriye dönük uyumluluk: discounted_price'ı line_discount'a çevir
                    state.sepet.forEach(function(item) {
                        if (typeof item.line_discount !== 'number') {
                            if (item.discounted_price !== null && item.discounted_price !== undefined) {
                                item.line_discount = parseFloat(((item.price - item.discounted_price) * item.quantity).toFixed(2));
                            } else {
                                item.line_discount = 0;
                            }
                            delete item.discounted_price;
                        }
                    });
                } catch (e) {
                    console.error("Hafıza yükleme hatası", e);
                }
            } else {
                state.sepet = [];
                state.iskontoTutar = 0;
                state.odemeTipi = "card";
                state.splitData = null;
            }

            state.lastUpdatedId = null;

            if (HK.UIRenderer) {
                HK.UIRenderer.sidebarGuncelle();
                HK.UIRenderer.arayuzuGuncelle();
            }
        },

        /**
         * Mevcut kasanın sepetini temizle
         */
        sepetiTemizle: function() {
            var state = HK.State;
            localStorage.removeItem(this._slotKey(state.aktifKasaId));
            state.sepet = [];
            state.iskontoTutar = 0;
            state.odemeTipi = "card";
            state.splitData = null;

            if (HK.UIRenderer) {
                HK.UIRenderer.arayuzuGuncelle();
            }
        },

        /**
         * Diğer kasalardaki aynı ürünün bilgisini getir
         * @param {number} productId Ürün ID
         * @param {number} variationId Varyant ID
         * @returns {Object} { adet: number, kasalar: number[] }
         */
        digerKasalardakiBilgi: function(productId, variationId) {
            var state = HK.State;
            var toplam = 0;
            var hangiKasalar = [];

            for (var i = 1; i <= state.MAX_KASA; i++) {
                if (i === state.aktifKasaId) continue;

                var slotVeri = localStorage.getItem(this._slotKey(i));
                if (slotVeri) {
                    try {
                        var veri = JSON.parse(slotVeri);
                        var urun = veri.sepet.find(function(item) {
                            return parseInt(item.product_id) === parseInt(productId) &&
                                   parseInt(item.variation_id || 0) === parseInt(variationId || 0);
                        });
                        if (urun) {
                            toplam += urun.quantity;
                            hangiKasalar.push(i);
                        }
                    } catch (e) {
                        console.error("Slot okuma hatası", e);
                    }
                }
            }

            return { adet: toplam, kasalar: hangiKasalar };
        },

        /**
         * Ürün objesini sepete ekle (stok kontrolü dahil)
         * @param {Object} urun API'den gelen ürün objesi
         */
        ekleUrunObjesiyle: function(urun) {
            var state = HK.State;
            var durumMetni = document.getElementById("durum");

            if (urun.is_variable) {
                durumMetni.innerText = "HATA: Ana Ürün (" + urun.name + ")! Lütfen varyant kodu okutun.";
                durumMetni.style.color = "red";
                return;
            }

            var isVariation = urun.type === 'variation' || (urun.parent_id && urun.parent_id !== 0);
            var eklenecekUrun = {
                product_id: isVariation ? urun.parent_id : urun.id,
                quantity: 1,
                name: urun.name,
                sku: urun.sku || "",
                price: parseFloat(urun.price) || 0,
                regular_price: parseFloat(urun.regular_price) || 0,
                line_discount: 0,
                image: urun.images.length > 0 ? urun.images[0].src : ''
            };

            if (isVariation) eklenecekUrun.variation_id = urun.id;

            var mevcutUrunIndex = state.sepet.findIndex(function(item) {
                return item.product_id === eklenecekUrun.product_id &&
                       item.variation_id === eklenecekUrun.variation_id;
            });

            // Stok Kontrolü
            var urunStok = parseInt(urun.stock_quantity);
            var sepettekiMevcutAdet = mevcutUrunIndex !== -1 ? parseInt(state.sepet[mevcutUrunIndex].quantity) : 0;

            // Diğer kasalarda bu üründen ne kadar var?
            var digerBilgi = this.digerKasalardakiBilgi(eklenecekUrun.product_id, eklenecekUrun.variation_id);
            var digerKasalardakiAdet = parseInt(digerBilgi.adet);
            var toplamBekleyenAdet = sepettekiMevcutAdet + digerKasalardakiAdet + 1;

            if (urun.manage_stock && urun.stock_quantity !== null) {
                if (toplamBekleyenAdet > urunStok) {
                    var mesaj = "";
                    if (digerKasalardakiAdet > 0) {
                        mesaj = "DİKKAT: Ürün Kasa " + digerBilgi.kasalar.join(", ") + " üzerinde işlemde! Stok yetersiz.";
                    } else {
                        mesaj = "HATA: Yetersiz Stok! (Maksimum: " + urun.stock_quantity + ")";
                    }
                    
                    durumMetni.innerText = mesaj;
                    durumMetni.style.color = "#e74c3c";

                    if (HK.UIRenderer) {
                        HK.UIRenderer.showToast("Stok Yetersiz: " + urun.name, 'error', true);
                    }
                    return;
                }
            } else if (urun.stock_status === 'outofstock') {
                if (HK.UIRenderer) {
                    HK.UIRenderer.showToast("Stok Yok: " + urun.name, 'error', true);
                }
                durumMetni.innerText = "HATA: Ürün stokta yok!";
                durumMetni.style.color = "red";
                return;
            }

            // Depo Stok Kontrolü (Uyarı — engellemez)
            var depoStok = urun.warehouse_stock;
            if (depoStok !== undefined && depoStok !== null && toplamBekleyenAdet > depoStok) {
                durumMetni.innerText = "⚠️ DİKKAT: Depoda yeterli stok yok! (Depo: " + depoStok + ", İhtiyaç: " + toplamBekleyenAdet + ")";
                durumMetni.style.color = "#e67e22";
                // Engellemiyoruz — kasiyere bırakıyoruz (fiziksel ürün kasada olabilir)
            }

            // Eğer ürün başka kasada varsa ama stok yetiyorsa bilgi ver
            if (digerKasalardakiAdet > 0) {
                console.log("Bu ürün Kasa " + digerBilgi.kasalar.join(", ") + " içerisinde de mevcut.");
            }

            if (mevcutUrunIndex !== -1) {
                // Ürün zaten varsa: Adedi artır ve en üste taşı
                var mevcutUrun = state.sepet[mevcutUrunIndex];
                state.sepet.splice(mevcutUrunIndex, 1); // Mevcut konumundan çıkar
                mevcutUrun.quantity += 1;
                state.sepet.unshift(mevcutUrun); // En üste ekle
            } else {
                // Yeni ürün: Doğrudan en üste ekle
                state.sepet.unshift(eklenecekUrun);
            }

            // Sepet değişti: Mevcut iskonto varsa toplamı güncelle (yeniden dağıtmadan)
            if (state.iskontoTutar > 0) {
                this.iskontoTutariniGuncelle();
            }

            if (HK.UIRenderer) {
                state.lastUpdatedId = eklenecekUrun.product_id + '-' + (eklenecekUrun.variation_id || 0);
                HK.UIRenderer.arayuzuGuncelle();
            }
            durumMetni.innerText = urun.name + " eklendi.";
            durumMetni.style.color = "#27ae60";
        },

        /**
         * İskontoyu fiyat ağırlığına göre ürünlere dağıtır (Largest Remainder Method - Kuruş Bazlı)
         * @param {number} toplamIskonto Toplam iskonto tutarı (TL)
         */
        dagitimiHesapla: function(toplamIskonto) {
            var state = HK.State;
            toplamIskonto = parseFloat(toplamIskonto) || 0;

            if (toplamIskonto <= 0 || state.sepet.length === 0) {
                this.iskontoTemizle();
                return;
            }

            var toplamIskontoKurus = Math.round(toplamIskonto * 100);
            var toplamKurus = 0;

            state.sepet.forEach(function(item) {
                toplamKurus += Math.round(item.price * item.quantity * 100);
            });

            if (toplamIskontoKurus > toplamKurus) {
                toplamIskontoKurus = toplamKurus;
            }

            state.iskontoTutar = parseFloat((toplamIskontoKurus / 100).toFixed(2));

            var dagitimlar = [];
            var dagitilmisKurus = 0;

            state.sepet.forEach(function(item, index) {
                var satirKurus = Math.round(item.price * item.quantity * 100);
                var hamPay = toplamKurus > 0 ? (toplamIskontoKurus * satirKurus / toplamKurus) : 0;
                var pay = Math.floor(hamPay); // Tam kuruşa yuvarla
                dagitilmisKurus += pay;

                dagitimlar.push({
                    index: index,
                    pay: pay,
                    kalan: hamPay - pay
                });
            });

            // Kalan kuruşları dağıt (her birine 1 kuruş)
            var kalanKurus = toplamIskontoKurus - dagitilmisKurus;

            if (kalanKurus > 0) {
                var sirali = dagitimlar.slice().sort(function(a, b) { return b.kalan - a.kalan; });
                for (var i = 0; i < kalanKurus && i < sirali.length; i++) {
                    sirali[i].pay += 1;
                }
            }

            // Dağıtılmış iskonto kuruşlarını satıra yaz
            dagitimlar.forEach(function(d) {
                var item = state.sepet[d.index];
                if (d.pay > 0 && item.quantity > 0) {
                    item.line_discount = parseFloat((d.pay / 100).toFixed(2));
                } else {
                    item.line_discount = 0;
                }
            });
        },

        /**
         * Tekil ürünün iskontolu fiyatını günceller, diğer ürünlerin iskontolarıyla toplayıp state'i günceller.
         * @param {number} index Sepet indeksi
         * @param {number} yeniDeger Yeni girilen tutar (birim veya toplam)
         * @param {string} tip 'birim' veya 'toplam'
         */
        urunIskontoGuncelle: function(index, yeniDeger, tip) {
            var state = HK.State;
            var item = state.sepet[index];
            if (!item) return;

            yeniDeger = parseFloat(yeniDeger) || 0;
            var satirToplamFiyat = item.price * item.quantity;
            var hedeflenenSatirNet;

            if (tip === 'toplam') {
                hedeflenenSatirNet = yeniDeger;
            } else {
                hedeflenenSatirNet = yeniDeger * item.quantity;
            }

            var hasAutoDiscount = !state.splitData && (state.odemeTipi === "cash" || state.odemeTipi === "iban");
            
            // Eğer %5 indirimi varsa kullanıcının yazdığı değer net(indirimli) ise, 
            // iskontonun manuel kısmını bulmak için %5'i tersine katıp iskonto miktarını buluyoruz.
            // Formül: Satır İskontosu = Satır Brüt - Satır %5 - Hedef Net
            var satirNakitIndirim = hasAutoDiscount ? (satirToplamFiyat * 0.05) : 0;
            
            var buSatirIskonto = satirToplamFiyat - satirNakitIndirim - hedeflenenSatirNet;

            if (buSatirIskonto < 0) buSatirIskonto = 0;
            if (buSatirIskonto > satirToplamFiyat) buSatirIskonto = satirToplamFiyat;

            // Kuruş bazlı kaydet (sadece 2 ondalık)
            item.line_discount = parseFloat(buSatirIskonto.toFixed(2));

            // Diğer satır iskontolarını topla
            this.iskontoTutariniGuncelle();

            if (HK.UIRenderer) {
                HK.UIRenderer.arayuzuGuncelle();
            }
        },

        /**
         * Tüm ürünlerdeki iskonto dağıtımını temizler
         */
        iskontoTemizle: function() {
            var state = HK.State;
            state.iskontoTutar = 0;
            state.sepet.forEach(function(item) {
                item.line_discount = 0;
            });
        },

        /**
         * Sepetteki ürünlerin mevcut indirimlerinden toplam iskonto tutarını hesaplar ve günceller
         */
        iskontoTutariniGuncelle: function() {
            var state = HK.State;
            var toplam = 0;
            state.sepet.forEach(function(item) {
                if (item.line_discount > 0) {
                    toplam += item.line_discount;
                } else {
                    item.line_discount = 0;
                }
            });
            state.iskontoTutar = parseFloat(toplam.toFixed(2));
        }
    };

    // Depo değiştiğinde sepeti o deponun hafızasından yükle
    document.addEventListener('hkActiveDepoChanged', function() {
        HK.CartManager.sepetiYukle();
    });

})(window.HizliKasa);
