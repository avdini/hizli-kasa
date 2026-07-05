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
        musteriTelefon: "",
        musteriTelefonUlkeKodu: "+90",
        musteriTelefonUlkeIso: "tr",
        siparisNotu: "",
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

        _telefonAlaniniGuncelle: function() {
            var state = HK.State;
            var phoneInput = document.getElementById("musteri-telefon");
            var musteriPanel = document.getElementById("musteri-telefon-panel");

            if (phoneInput) {
                HK._telefonProgramatikGuncelleniyor = true;
                if (HK.iti && state.musteriTelefonUlkeIso && typeof HK.iti.setCountry === 'function') {
                    HK.iti.setCountry(state.musteriTelefonUlkeIso);
                }
                phoneInput.value = state.musteriTelefon || "";
                // Flag'i sıfırla — sepetiYukle bunu clearTimeout ile iptal eder ve kendi timer'ını koyar
                clearTimeout(HK._telefonProgramatikTimer);
                HK._telefonProgramatikTimer = setTimeout(function() {
                    HK._telefonProgramatikGuncelleniyor = false;
                }, 0);
            }

            if (musteriPanel) {
                musteriPanel.style.display = state.musteriTelefon ? "block" : "none";
            }
        },


        _telefonStateiniInputtanGuncelle: function() {
            var state = HK.State;
            var phoneInput = document.getElementById("musteri-telefon");

            if (phoneInput) {
                state.musteriTelefon = phoneInput.value || "";
            }

            if (HK.iti && typeof HK.iti.getSelectedCountryData === 'function') {
                var countryData = HK.iti.getSelectedCountryData();
                if (countryData && countryData.dialCode) {
                    state.musteriTelefonUlkeKodu = "+" + countryData.dialCode;
                    state.musteriTelefonUlkeIso = countryData.iso2 || "tr";
                }
            }
        },

        /**
         * Mevcut sepeti localStorage'a kaydet
         */
        sepetiKaydet: function() {
            var state = HK.State;
            if (!HK._telefonProgramatikGuncelleniyor) {
                this._telefonStateiniInputtanGuncelle();
            }
            localStorage.setItem('hizli_kasa_aktif_id', state.aktifKasaId);

            var veri = {
                sepet: state.sepet,
                iskontoTutar: state.iskontoTutar,
                odemeTipi: state.odemeTipi,
                musteriTelefon: state.musteriTelefon,
                musteriTelefonUlkeKodu: state.musteriTelefonUlkeKodu,
                musteriTelefonUlkeIso: state.musteriTelefonUlkeIso,
                siparisNotu: state.siparisNotu,
                splitData: state.splitData,
                editingOrderId: state.editingOrderId
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
            localStorage.setItem('hizli_kasa_aktif_id', state.aktifKasaId);

            var kaydedilen = localStorage.getItem(this._slotKey(state.aktifKasaId));
            if (kaydedilen) {
                try {
                    var veri = JSON.parse(kaydedilen);
                    state.sepet = veri.sepet || [];
                    state.iskontoTutar = veri.iskontoTutar || 0;
                    state.odemeTipi = veri.odemeTipi || "card";
                    state.musteriTelefon = veri.musteriTelefon || "";
                    state.musteriTelefonUlkeKodu = veri.musteriTelefonUlkeKodu || "+90";
                    state.musteriTelefonUlkeIso = veri.musteriTelefonUlkeIso || "tr";
                    state.siparisNotu = veri.siparisNotu || "";
                    state.splitData = veri.splitData || null;
                    state.editingOrderId = veri.editingOrderId || null;

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
                state.musteriTelefon = "";
                state.musteriTelefonUlkeKodu = "+90";
                state.musteriTelefonUlkeIso = "tr";
                state.siparisNotu = "";
                state.splitData = null;
            }

            state.lastUpdatedId = null;

            // Yükleme + UI güncellemesi boyunca ITI'den okuma yapılmasın.
            // arayuzuGuncelle → sepetiKaydet → _telefonStateiniInputtanGuncelle zinciri
            // ITI'nin async utils yüklenmemiş olabileceğinden yanlış ülke döndürebilir.
            clearTimeout(HK._telefonProgramatikTimer);
            HK._telefonProgramatikGuncelleniyor = true;

            this._telefonAlaniniGuncelle();

            if (HK.UIRenderer) {
                HK.UIRenderer.sidebarGuncelle();
                HK.UIRenderer.arayuzuGuncelle();
            }

            // UI akışı bittikten sonra bayrağı kaldır (100ms: async event loop için yeterli)
            HK._telefonProgramatikTimer = setTimeout(function() {
                HK._telefonProgramatikGuncelleniyor = false;
            }, 100);
        },

        /**
         * Mevcut kasanın sepetini temizle
         */
        sepetiTemizle: function(kasaId) {
            var state = HK.State;
            var temizlenecekKasaId = kasaId ? parseInt(kasaId) : state.aktifKasaId;
            localStorage.removeItem(this._slotKey(temizlenecekKasaId));

            if (temizlenecekKasaId !== state.aktifKasaId) {
                return;
            }

            state.sepet = [];
            state.iskontoTutar = 0;
            state.odemeTipi = "card";
            state.musteriTelefon = "";
            state.musteriTelefonUlkeKodu = "+90";
            state.musteriTelefonUlkeIso = "tr";
            state.siparisNotu = "";
            state.splitData = null;

            // UI Güncelle
            var phoneInput = document.getElementById("musteri-telefon");
            if (phoneInput) {
                HK._telefonProgramatikGuncelleniyor = true;
                try {
                    phoneInput.value = "";
                    if (HK.iti && typeof HK.iti.setCountry === 'function') HK.iti.setCountry("tr");
                } finally {
                    clearTimeout(HK._telefonProgramatikTimer);
                    HK._telefonProgramatikTimer = setTimeout(function() {
                        HK._telefonProgramatikGuncelleniyor = false;
                    }, 0);
                }
            }
            var musteriPanel = document.getElementById("musteri-telefon-panel");
            if (musteriPanel) {
                musteriPanel.style.display = "none";
            }
            var phoneGroup = phoneInput ? phoneInput.closest('.musteri-input-grup') : null;
            if (phoneGroup) {
                phoneGroup.classList.remove('gecerli', 'gecersiz');
            }

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
            if (urun.verified_phone) eklenecekUrun.verified_phone = urun.verified_phone;

            var mevcutUrunIndex = state.sepet.findIndex(function(item) {
                // Değişim iade satırlarını (negatif) normal ürünlerle birleştirme
                if (item._is_exchange_return) return false;
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
         * İskontoyu fiyat ağırlığına göre ürünlere dağıtır (1 TL Adımlı, Küsüratları Temizleme ve En Büyüğe Yığma)
         * Nakit indirimi gibi dış etkenlerden doğan küsüratları hesaplayıp yok eder.
         * @param {number} toplamIskonto Toplam iskonto tutarı (TL)
         */
        dagitimiHesapla: function(toplamIskonto) {
            var state = HK.State;
            toplamIskonto = parseFloat(toplamIskonto) || 0;

            if (toplamIskonto <= 0 || state.sepet.length === 0) {
                this.iskontoTemizle();
                return;
            }

            var hasAutoDiscount = (state.odemeTipi === "cash" || state.odemeTipi === "iban");
            var carpan = hasAutoDiscount ? 0.95 : 1;

            var toplamIskontoKurus = Math.round(toplamIskonto * 100);
            var toplamKurus = 0; // Brüt
            var enPahaliIndex = 0;
            var enPahaliSatirKurus = 0;

            state.sepet.forEach(function(item, index) {
                if (item.product_id === "COUPON" || item._is_exchange_return || item.quantity <= 0) return;
                var satirBrutKurus = Math.round(item.price * item.quantity * carpan * 100);
                toplamKurus += satirBrutKurus;
                if (satirBrutKurus > enPahaliSatirKurus) {
                    enPahaliSatirKurus = satirBrutKurus;
                    enPahaliIndex = index;
                }
            });

            if (toplamKurus <= 0) {
                this.iskontoTemizle();
                return;
            }

            if (toplamIskontoKurus > toplamKurus) {
                toplamIskontoKurus = toplamKurus;
            }

            state.iskontoTutar = parseFloat((toplamIskontoKurus / 100).toFixed(2));

            var iskontolar = [];
            var dagitilmisKurus = 0;
            var kalanD = toplamIskontoKurus;

            // Aşama 1: Her satırın küsüratını (F_i) temizlemeye çalış (En pahalı hariç)
            // Böylece net fiyatları .00 ile biter.
            var minGerekenler = [];
            var toplamMinGereken = 0;
            state.sepet.forEach(function(item, index) {
                iskontolar[index] = 0;
                if (item.product_id === "COUPON" || item._is_exchange_return || item.quantity <= 0) {
                    minGerekenler[index] = 0;
                    return;
                }
                if (index === enPahaliIndex) {
                    minGerekenler[index] = 0;
                    return;
                }
                var satirBrutKurus = Math.round(item.price * item.quantity * carpan * 100);
                var fi = satirBrutKurus % 100;
                minGerekenler[index] = fi;
                toplamMinGereken += fi;
            });

            if (kalanD >= toplamMinGereken) {
                // Hepsini temizlemeye yetecek kadar paramız var
                state.sepet.forEach(function(item, index) {
                    if (item.product_id === "COUPON" || item._is_exchange_return || item.quantity <= 0) return;
                    if (index === enPahaliIndex) return;
                    iskontolar[index] = minGerekenler[index];
                    kalanD -= minGerekenler[index];
                    dagitilmisKurus += minGerekenler[index];
                });
            } else {
                // Hepsini temizlemeye yetmiyor. Gücümüzün yettiği kadarını temizleyelim.
                state.sepet.forEach(function(item, index) {
                    if (item.product_id === "COUPON" || item._is_exchange_return || item.quantity <= 0) return;
                    if (index === enPahaliIndex) return;
                    if (kalanD >= minGerekenler[index]) {
                        iskontolar[index] = minGerekenler[index];
                        kalanD -= minGerekenler[index];
                        dagitilmisKurus += minGerekenler[index];
                    }
                });
            }

            // Aşama 2: Kalan iskontoyu oransal olarak 1 TL adımlarla dağıt (En pahalı hariç)
            var adimKurus = 100;
            state.sepet.forEach(function(item, index) {
                if (item.product_id === "COUPON" || item._is_exchange_return || item.quantity <= 0) return;
                if (index === enPahaliIndex) return;

                var satirBrutKurus = Math.round(item.price * item.quantity * carpan * 100);
                var hamPayKurus = toplamKurus > 0 ? (toplamIskontoKurus * satirBrutKurus / toplamKurus) : 0;
                
                // Zaten temizlik için bir miktar verdik, üstüne ne kadar 100 kuruşluk dilim verebiliriz?
                var hedefEkstra = hamPayKurus - iskontolar[index];
                if (hedefEkstra < 0) hedefEkstra = 0;

                var eklenecekAdim = Math.round(hedefEkstra / adimKurus) * adimKurus;

                if (dagitilmisKurus + eklenecekAdim > toplamIskontoKurus) {
                    eklenecekAdim = Math.floor(hedefEkstra / adimKurus) * adimKurus;
                }
                if (dagitilmisKurus + eklenecekAdim > toplamIskontoKurus) {
                    eklenecekAdim = Math.max(0, toplamIskontoKurus - dagitilmisKurus);
                    // Temizlik bozulmasın diye 100'ün katlarına zorlayalım
                    eklenecekAdim = Math.floor(eklenecekAdim / adimKurus) * adimKurus;
                }

                // Ürün fiyatını aşmasın
                var maxVerebilecegim = satirBrutKurus - iskontolar[index];
                if (eklenecekAdim > maxVerebilecegim) {
                    eklenecekAdim = Math.floor(maxVerebilecegim / adimKurus) * adimKurus;
                }

                iskontolar[index] += eklenecekAdim;
                dagitilmisKurus += eklenecekAdim;
            });

            // Aşama 3: Geriye ne kalıyorsa (iyi/kötü) hepsini en pahalıya bas
            var sonKalan = toplamIskontoKurus - dagitilmisKurus;
            
            if (sonKalan > enPahaliSatirKurus) {
                iskontolar[enPahaliIndex] = enPahaliSatirKurus;
                sonKalan -= enPahaliSatirKurus;

                // Taşan kısmı mecburen diğerlerine yedir (bu aşamada temizlik bozulabilir ama ekstrem durumdur)
                for (var i = 0; i < state.sepet.length && sonKalan > 0; i++) {
                    if (state.sepet[i].product_id === "COUPON" || state.sepet[i]._is_exchange_return || state.sepet[i].quantity <= 0) continue;
                    if (i === enPahaliIndex) continue;
                    var itKurus = Math.round(state.sepet[i].price * state.sepet[i].quantity * carpan * 100);
                    var bosluk = itKurus - (iskontolar[i] || 0);
                    if (bosluk > 0) {
                        var ek = Math.min(bosluk, sonKalan);
                        iskontolar[i] += ek;
                        sonKalan -= ek;
                    }
                }
            } else {
                iskontolar[enPahaliIndex] += sonKalan;
            }

            // Hesaplanan değerleri ürünlere işle
            state.sepet.forEach(function(item, index) {
                var isko = iskontolar[index] || 0;
                if (isko > 0 && item.quantity > 0) {
                    item.line_discount = parseFloat((isko / 100).toFixed(2));
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

            var hasAutoDiscount = (state.odemeTipi === "cash" || state.odemeTipi === "iban");
            
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
        },

        /**
         * Kupon barkodu okutulduğunda doğrulama modalını açar
         */
        verifyCoupon: function(sku) {
            var modal = document.getElementById("kupon-dogrulama-modal");
            var koduInput = document.getElementById("dogrulama-kupon-kodu");
            var telInput = document.getElementById("dogrulama-kupon-telefon");
            var hataDiv = document.getElementById("kupon-dogrulama-hata");

            if (modal && koduInput && telInput) {
                koduInput.value = sku;
                telInput.value = "";
                if (hataDiv) hataDiv.style.display = "none";
                modal.style.display = "flex";
                setTimeout(function() { telInput.focus(); }, 100);
            } else {
                console.error("Kupon doğrulama modal öğeleri bulunamadı!");
            }
        }
    };

    // Depo değiştiğinde sepeti o deponun hafızasından yükle
    document.addEventListener('hkActiveDepoChanged', function() {
        HK.CartManager.sepetiYukle();
    });

})(window.HizliKasa);
