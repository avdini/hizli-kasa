/**
 * Hızlı Kasa - QR Payment Manager
 *
 * Kasadan Sanal POS ile QR Taksitli Ödeme işlemlerini,
 * modal görünümlerini, arka plan polling durum takibini ve
 * üst bar rozet (badge) bildirimlerini yönetir.
 *
 * @package HizliKasa
 */

(function (HK) {
    'use strict';

    HK.QRPaymentManager = {

        pendingPayments: [], // [{ order_id, order_number, total, pay_url, created_at, interval_id }]
        pollIntervalMs: 5000,
        timeoutMinutes: 15,
        currentViewingOrderId: null,

        /**
         * Modül Başlatıcı
         */
        init: function () {
            var self = this;
            if (typeof kasaAyar !== 'undefined' && kasaAyar.qrPaymentTimeout) {
                self.timeoutMinutes = parseInt(kasaAyar.qrPaymentTimeout, 10) || 15;
            }

            // QR Modal İçi Butonlar
            var btnArkaPlan = document.getElementById("qr-modal-arka-plan");
            if (btnArkaPlan) {
                btnArkaPlan.addEventListener("click", function () {
                    self.minimizeToBackground();
                });
            }

            var btnKapat = document.getElementById("qr-modal-kapat");
            if (btnKapat) {
                btnKapat.addEventListener("click", function () {
                    self.minimizeToBackground();
                });
            }

            var btnIptal = document.getElementById("qr-modal-iptal");
            if (btnIptal) {
                btnIptal.addEventListener("click", function () {
                    if (self.currentViewingOrderId) {
                        self.cancelPayment(self.currentViewingOrderId);
                    }
                });
            }
        },

        /**
         * QR Taksitli Ödeme Başlatma
         */
        startQRPayment: async function (siparisVerisi, kasaId) {
            var self = this;
            var durumMetni = document.getElementById("durum");
            var apiBase = (typeof kasaAyar !== 'undefined' && kasaAyar.rootApiUrl) ? kasaAyar.rootApiUrl : (window.location.origin + '/wp-json/');

            if (HK.OrderProcessor && typeof HK.OrderProcessor.toggleLoading === 'function') {
                HK.OrderProcessor.toggleLoading(true);
            }
            if (durumMetni) durumMetni.innerText = "QR Taksitli ödeme hazırlanıyor...";

            try {
                var response = await fetch(apiBase + 'hizli-kasa/v2/qr-payment/create', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': kasaAyar.nonce
                    },
                    body: JSON.stringify(siparisVerisi)
                });

                var result = await response.json();

                if (HK.OrderProcessor && typeof HK.OrderProcessor.toggleLoading === 'function') {
                    HK.OrderProcessor.toggleLoading(false);
                }

                if (result.success && result.data) {
                    var data = result.data;
                    var state = HK.State;
                    var lockedKasaId = kasaId || state.aktifKasaId;

                    if (HK.CartManager) {
                        HK.CartManager.kasayiKilitle(lockedKasaId, {
                            orderId: data.order_id,
                            orderNumber: data.order_number,
                            total: data.total,
                            payUrl: data.pay_url,
                            createdAt: Date.now()
                        });
                    }

                    // Bekleyen listeye ekle
                    var paymentObj = {
                        order_id: data.order_id,
                        order_number: data.order_number,
                        total: data.total,
                        pay_url: data.pay_url,
                        created_at: Date.now(),
                        interval_id: null,
                        kasaId: lockedKasaId
                    };

                    self.pendingPayments.push(paymentObj);
                    self.currentViewingOrderId = data.order_id;

                    // QR Kod Göster
                    self.showQRModal(paymentObj);

                    // Polling Başlat
                    self.startPollingForOrder(paymentObj);

                    if (durumMetni) {
                        durumMetni.innerText = "QR Ödeme Bekleniyor: " + data.order_number;
                        durumMetni.style.color = "#f39c12";
                    }
                } else {
                    var errorMsg = (result.errors && result.errors[0]) ? result.errors[0] : "QR ödeme siparişi oluşturulamadı.";
                    if (HK.UIRenderer) HK.UIRenderer.showToast("HATA: " + errorMsg, "error", true);
                    if (durumMetni) {
                        durumMetni.innerText = "HATA: " + errorMsg;
                        durumMetni.style.color = "red";
                    }
                }
            } catch (err) {
                if (HK.OrderProcessor && typeof HK.OrderProcessor.toggleLoading === 'function') {
                    HK.OrderProcessor.toggleLoading(false);
                }
                console.error("QR payment error", err);
                if (HK.UIRenderer) HK.UIRenderer.showToast("QR ödeme oluşturulurken sistem hatası oluştu.", "error", true);
            }
        },

        /**
         * QR Gösterim Modalı
         */
        showQRModal: function (paymentObj) {
            var self = this;
            self.currentViewingOrderId = paymentObj.order_id;

            var modal = document.getElementById("qr-odeme-modal");
            var orderInfo = document.getElementById("qr-modal-siparis-info");
            var qrBox = document.getElementById("qr-code-container");
            var linkText = document.getElementById("qr-modal-link-display");

            if (orderInfo) {
                orderInfo.innerHTML = "Sipariş <strong>" + paymentObj.order_number + "</strong> — Tutar: <strong style='color:#00B894;'>" + paymentObj.total + " TL</strong>";
            }

            if (linkText) {
                linkText.href = paymentObj.pay_url;
                linkText.innerText = paymentObj.pay_url;
            }

            if (qrBox) {
                qrBox.innerHTML = "";
                if (typeof QRCode !== 'undefined') {
                    new QRCode(qrBox, {
                        text: paymentObj.pay_url,
                        width: 220,
                        height: 220,
                        colorDark: "#000000",
                        colorLight: "#ffffff"
                    });
                }
            }

            if (modal) {
                modal.style.display = "flex";
            }
        },

        /**
         * Modalı kapatıp arka plana at (kasa devam etsin)
         */
        minimizeToBackground: function () {
            var modal = document.getElementById("qr-odeme-modal");
            if (modal) modal.style.display = "none";
            this.currentViewingOrderId = null;
            if (HK.UIRenderer) {
                HK.UIRenderer.showToast("QR ödeme arka planda takip ediliyor. Ödeme alındığında bildirilecek.", "info", false);
            }
        },

        /**
         * Belirli Bir Sipariş İçin Polling Döngüsü Başlatır
         */
        startPollingForOrder: function (paymentObj) {
            var self = this;
            var apiBase = (typeof kasaAyar !== 'undefined' && kasaAyar.rootApiUrl) ? kasaAyar.rootApiUrl : (window.location.origin + '/wp-json/');
            paymentObj.isStopped = false;

            var checkStatus = async function () {
                if (paymentObj.isStopped) return;
                try {
                    var res = await fetch(apiBase + 'hizli-kasa/v2/qr-payment/status/' + paymentObj.order_id, {
                        headers: { 'X-WP-Nonce': kasaAyar.nonce }
                    });
                    var data = await res.json();

                    if (paymentObj.isStopped) return;

                    if (data.success && data.data) {
                        var statusData = data.data;

                        if (statusData.status === 'paid') {
                            self.stopPollingForOrder(paymentObj);
                            self.onPaymentComplete(paymentObj, statusData);
                        } else if (statusData.status === 'failed') {
                            self.stopPollingForOrder(paymentObj);
                            self.onPaymentFailed(paymentObj, statusData);
                        } else if (statusData.status === 'waiting') {
                            // Geri sayım sayacını modalda güncelle
                            if (self.currentViewingOrderId === paymentObj.order_id) {
                                var timerEl = document.getElementById("qr-modal-timer");
                                if (timerEl) {
                                    var rem = statusData.remaining_seconds || 0;
                                    var mins = Math.floor(rem / 60);
                                    var secs = rem % 60;
                                    timerEl.innerText = (mins < 10 ? '0' : '') + mins + ':' + (secs < 10 ? '0' : '') + secs;
                                }
                            }
                        }
                    }
                } catch (e) {
                    console.warn("QR status check error", e);
                }
            };

            // İlk kontrol 2 saniye sonra, ardından 5 sn aralıkla
            paymentObj.timeout_id = setTimeout(checkStatus, 2000);
            paymentObj.interval_id = setInterval(checkStatus, self.pollIntervalMs);
        },

        /**
         * Polling Döngüsünü Durdurur
         */
        stopPollingForOrder: function (orderId) {
            var payment = (typeof orderId === 'object' && orderId !== null) ? orderId : this.pendingPayments.find(function (p) { return p.order_id === orderId; });
            if (payment) {
                payment.isStopped = true;
                if (payment.interval_id) {
                    clearInterval(payment.interval_id);
                    payment.interval_id = null;
                }
                if (payment.timeout_id) {
                    clearTimeout(payment.timeout_id);
                    payment.timeout_id = null;
                }
            }
        },

        /**
         * Ödeme Başarıyla Alındığında
         */
        onPaymentComplete: function (paymentObj, statusData) {
            var self = this;
            self.stopPollingForOrder(paymentObj);

            var lockedKasaId = paymentObj.kasaId;
            
            // Bekleyen listeden çıkar
            self.pendingPayments = self.pendingPayments.filter(function (p) { return p.order_id !== paymentObj.order_id; });

            // Eğer modal açık ve bu sipariş izleniyorsa kapat
            if (self.currentViewingOrderId === paymentObj.order_id) {
                var modal = document.getElementById("qr-odeme-modal");
                if (modal) modal.style.display = "none";
                self.currentViewingOrderId = null;
            }

            // Ses Çal
            if (HK.Sound && typeof HK.Sound.play === 'function') {
                HK.Sound.play('success');
            }

            if (HK.CartManager) {
                HK.CartManager.kasayiKilitle(lockedKasaId, {
                    orderId: paymentObj.order_id,
                    orderNumber: paymentObj.order_number,
                    total: paymentObj.total,
                    payUrl: paymentObj.pay_url,
                    createdAt: paymentObj.created_at || Date.now(),
                    status: 'paid'
                });
            }

            if (HK.UIRenderer) {
                HK.UIRenderer.kasaQRDurumGuncelle(lockedKasaId, 'tamamlandi', paymentObj);
            }

            // Komisyon / Taksit Detay Metni Oluştur
            var gw = statusData.gateway_data || {};
            var detailText = paymentObj.total + " TL (" + (gw.installment_label || "Taksitli Sanal POS") + ")";
            if (gw.merchant_payout) {
                detailText += " | Net Ele Geçen: " + gw.merchant_payout;
            }

            var toastMsg = "🟢 Kasa " + lockedKasaId + "'te " + paymentObj.order_number + " Ödemesi Alındı! Fişi yazdırıp tamamlamak için kasanıza geçin. (" + detailText + ")";

            if (HK.UIRenderer && typeof HK.UIRenderer.showToast === 'function') {
                HK.UIRenderer.showToast(toastMsg, "success", true);
            }

            var durumMetni = document.getElementById("durum");
            if (durumMetni && HK.State.aktifKasaId === lockedKasaId) {
                durumMetni.innerText = "QR Ödeme Alındı: " + paymentObj.order_number + " (Fiş Bekliyor)";
                durumMetni.style.color = "#00B894";
            }
        },

        /**
         * Ödeme Başarısız/İptal Olduğunda
         */
        onPaymentFailed: function (paymentObj, statusData) {
            var self = this;
            self.stopPollingForOrder(paymentObj);

            var lockedKasaId = paymentObj.kasaId;

            self.pendingPayments = self.pendingPayments.filter(function (p) { return p.order_id !== paymentObj.order_id; });

            if (self.currentViewingOrderId === paymentObj.order_id) {
                var modal = document.getElementById("qr-odeme-modal");
                if (modal) modal.style.display = "none";
                self.currentViewingOrderId = null;
            }

            if (HK.CartManager) {
                HK.CartManager.kasaKilidiniAc(lockedKasaId);
            }

            if (HK.UIRenderer) {
                HK.UIRenderer.kasaQRDurumGuncelle(lockedKasaId, 'suresi-doldu');
            }

            var msg = (statusData && statusData.message) ? statusData.message : "Ödeme alınamadı / zaman aşımına uğradı.";
            if (HK.UIRenderer && typeof HK.UIRenderer.showToast === 'function') {
                HK.UIRenderer.showToast("❌ " + paymentObj.order_number + " Ödeme İptal: " + msg, "error", true);
            }
        },

        /**
         * Kasiyer Tarafından QR Ödemenin İptali
         */
        cancelPayment: async function (orderId) {
            var self = this;
            if (!confirm("Bu QR ödeme siparişini iptal etmek istediğinizden emin misiniz?")) {
                return;
            }

            var apiBase = (typeof kasaAyar !== 'undefined' && kasaAyar.rootApiUrl) ? kasaAyar.rootApiUrl : (window.location.origin + '/wp-json/');

            try {
                var res = await fetch(apiBase + 'hizli-kasa/v2/qr-payment/cancel/' + orderId, {
                    method: 'POST',
                    headers: { 'X-WP-Nonce': kasaAyar.nonce }
                });
                var data = await res.json();

                self.stopPollingForOrder(orderId);
                var paymentObj = self.pendingPayments.find(function (p) { return p.order_id === orderId; }) || { order_id: orderId, order_number: '#' + orderId, kasaId: HK.State.aktifKasaId };
                self.onPaymentFailed(paymentObj, { message: "Kasiyer tarafından iptal edildi." });
            } catch (e) {
                console.error("Cancel payment error", e);
            }
        },

        changePaymentMethod: function (kasaId) {
            var self = this;
            var payment = self.pendingPayments.find(function (p) { return p.kasaId === parseInt(kasaId); });
            if (!payment) {
                // If not found in memory array, attempt unlock directly
                if (HK.CartManager) HK.CartManager.kasaKilidiniAc(kasaId);
                return;
            }
            self.cancelPayment(payment.order_id);
        },

        /**
         * Top Bar QR Badge Güncelleme
         */
        updateBadge: function () {
            // Badge artık bildirim merkezi, QR bağlantısı kaldırıldı
        },

        /**
         * Bekleyen QR Ödemeler Listesi Modalı
         */
        showPendingListModal: function () {
            // Modal artık bildirim merkezi placeholder'ı
        },

        /**
         * Tamamlanan Sipariş Fişini Çek ve Hazırla
         */
        fetchAndShowReceipt: async function (orderId) {
            var apiBase = (typeof kasaAyar !== 'undefined' && kasaAyar.rootApiUrl) ? kasaAyar.rootApiUrl : (window.location.origin + '/wp-json/');
            try {
                var res = await fetch(apiBase + 'wc/v3/orders/' + orderId, {
                    headers: { 'X-WP-Nonce': kasaAyar.nonce }
                });
                if (res.ok) {
                    var orderResult = await res.json();
                    if (HK.ReceiptPrinter && typeof HK.ReceiptPrinter.fisHazirla === 'function') {
                        HK.ReceiptPrinter.fisHazirla(orderResult);
                    }
                    var fisModal = document.getElementById("fis-onay-modal");
                    if (fisModal) fisModal.style.display = "flex";
                }
            } catch (e) {
                console.warn("Receipt fetch error", e);
            }
        }
    };

})(window.HizliKasa || (window.HizliKasa = {}));
