/**
 * Hızlı Kasa - Sipariş Düzenleyici (Order Editor)
 *
 * Kasiyerin son siparişleri düzenlemesini sağlar.
 * 
 * @package HizliKasa
 */

(function(HK) {
    'use strict';

    HK.OrderEditor = {
        activeOrder: null,
        editedItems: {}, // item_id -> new_qty

        init: function() {
            var self = this;
            var editBtn = document.getElementById("siparis-duzenle-buton");
            var closeBtn = document.getElementById("order-edit-kapat");
            var backBtn = document.getElementById("order-edit-back");
            var saveBtn = document.getElementById("order-edit-save");

            if (editBtn) {
                editBtn.addEventListener("click", function() {
                    self.openModal();
                });
            }

            if (closeBtn) {
                closeBtn.addEventListener("click", function() {
                    document.getElementById("order-edit-modal").style.display = "none";
                });
            }

            if (backBtn) {
                backBtn.addEventListener("click", function() {
                    document.getElementById("order-edit-detail-view").style.display = "none";
                    document.getElementById("order-edit-list-view").style.display = "block";
                });
            }

            if (saveBtn) {
                saveBtn.addEventListener("click", function() {
                    self.saveChanges();
                });
            }

            // Telefon Maskeleme
            var phoneInput = document.getElementById("edit-order-phone");
            if (phoneInput) {
                phoneInput.addEventListener("input", function(e) {
                    var val = e.target.value.replace(/\D/g, '');
                    
                    // Eğer kullanıcı doğrudan 5 ile başlıyorsa başına 0 ekle (Türkiye için kolaylık)
                    if (val.length > 0 && val[0] === '5' && val.length <= 10) {
                        val = '0' + val;
                    }
                    
                    // Max 11 hane
                    if (val.length > 11) val = val.substring(0, 11);

                    var x = val.match(/(\d{0,1})(\d{0,3})(\d{0,3})(\d{0,2})(\d{0,2})/);
                    if (!x[1]) {
                        e.target.value = '';
                    } else {
                        e.target.value = !x[2] ? x[1] : x[1] + ' (' + x[2] + (x[3] ? ') ' + x[3] : '') + (x[4] ? ' ' + x[4] : '') + (x[5] ? ' ' + x[5] : '');
                    }
                });
            }

            // Depo değiştiğinde listeyi yenile
            document.addEventListener('hkActiveDepoChanged', function() {
                var modal = document.getElementById("order-edit-modal");
                if (modal && modal.style.display === "flex") {
                    self.loadRecentOrders();
                }
            });
        },

        openModal: function() {
            document.getElementById("order-edit-modal").style.display = "flex";
            document.getElementById("order-edit-detail-view").style.display = "none";
            document.getElementById("order-edit-list-view").style.display = "block";
            this.loadRecentOrders();
        },

        loadRecentOrders: async function() {
            var container = document.getElementById("recent-orders-container");
            var loading = document.getElementById("recent-orders-loading");
            var self = this;

            loading.style.display = "block";
            container.innerHTML = "";

            try {
                var depoId = HK.DepoManager ? HK.DepoManager.getActiveDepo() : 0;
                var response = await fetch(kasaAyar.rootApiUrl + 'hizli-kasa/v1/recent-orders?kasa_no=' + HK.State.aktifKasaId + '&depo_id=' + depoId, {
                    headers: { 'X-WP-Nonce': kasaAyar.nonce }
                });
                var orders = await response.json();

                loading.style.display = "none";

                if (orders.length === 0) {
                    container.innerHTML = '<div style="text-align:center; padding:20px; color:var(--hk-text-muted);">Bugün yapılmış düzenlenebilir sipariş bulunamadı.</div>';
                    return;
                }

                orders.forEach(function(order) {
                    var div = document.createElement("div");
                    var isLocked = order.has_refund;
                    var lockReason = "";
                    if (order.has_refund) lockReason = "(İade İşlemi Gördü)";

                    div.className = "recent-order-item" + (isLocked ? " is-locked" : "");
                    div.innerHTML = `
                        <div class="recent-order-info">
                            <span class="recent-order-id">#${order.id} ${lockReason ? '<small style="color:#d63031;">' + lockReason + '</small>' : ''}</span>
                            <span class="recent-order-meta">${order.date} | ${order.payment_title}</span>
                        </div>
                        <div class="recent-order-total">${parseFloat(order.total).toFixed(2)} TL</div>
                    `;
                    
                    if (!isLocked) {
                        div.addEventListener("click", function() {
                            self.selectOrder(order);
                        });
                    } else {
                        div.style.opacity = "0.6";
                        div.style.cursor = "not-allowed";
                    }
                    
                    container.appendChild(div);
                });

            } catch (e) {
                console.error("Recent orders error", e);
                loading.innerText = "Hata oluştu!";
            }
        },

        selectOrder: async function(order) {
            var self = this;
            var modal = document.getElementById("order-edit-modal");

            if (HK.UIRenderer && typeof HK.UIRenderer.showToast === 'function') {
                HK.UIRenderer.showToast("Sipariş verileri yükleniyor...", "info");
            }

            try {
                // Sipariş detaylarını API'den çek
                var response = await fetch(kasaAyar.rootApiUrl + 'hizli-kasa/v1/get-order?id=' + order.id, {
                    headers: { 'X-WP-Nonce': kasaAyar.nonce }
                });
                var orderDetails = await response.json();

                if (!orderDetails || orderDetails.code === 'rest_no_route' || orderDetails.message) {
                    throw new Error(orderDetails.message || "Sipariş detayları alınamadı.");
                }

                // Sepet formatına dönüştür
                var cartItems = orderDetails.items.map(function(item) {
                    return {
                        product_id: item.id, // get_order_details returns product ID in 'id' field
                        variation_id: item.variation_id || 0,
                        quantity: item.qty,
                        name: item.name,
                        sku: item.sku || "",
                        price: parseFloat(item.price) || 0,
                        regular_price: parseFloat(item.price) || 0,
                        line_discount: parseFloat(item.item_discount) || 0,
                        image: item.image || ""
                    };
                });

                // Global State'e yükle
                HK.State.sepet = cartItems;
                HK.State.editingOrderId = orderDetails.id;
                HK.State.iskontoTutar = parseFloat(orderDetails.manual_discount) || 0;
                HK.State.musteriTelefon = orderDetails.telefon || "";
                HK.State.odemeTipi = orderDetails.payment_method || "card";
                HK.State.siparisNotu = orderDetails.siparis_notu || "";

                if (orderDetails.payment_method === 'split' && orderDetails.payment_details) {
                    HK.State.splitData = {
                        nakit: parseFloat(orderDetails.payment_details.nakit) || 0,
                        kart: parseFloat(orderDetails.payment_details.kart) || 0,
                        iban: parseFloat(orderDetails.payment_details.iban) || 0
                    };
                } else {
                    HK.State.splitData = null;
                }

                // Hafızaya kaydet ve arayüzü güncelle
                if (HK.CartManager) {
                    HK.CartManager.sepetiKaydet();
                }

                if (HK.UIRenderer) {
                    HK.UIRenderer.arayuzuGuncelle();
                }

                // Kasa sekmesine yönlendir
                var tabBtn = document.querySelector('.ust-sekme[data-tab="kasa"]');
                if (tabBtn) tabBtn.click();

                // Modalı kapat
                if (modal) modal.style.display = "none";

                if (HK.UIRenderer && typeof HK.UIRenderer.showToast === 'function') {
                    HK.UIRenderer.showToast("Sipariş #" + orderDetails.id + " düzenleme modunda yüklendi.", "success");
                }

            } catch (e) {
                console.error("Order load error", e);
                if (HK.UIRenderer && typeof HK.UIRenderer.showToast === 'function') {
                    HK.UIRenderer.showToast("Sipariş yüklenirken hata oluştu: " + e.message, "error", true);
                }
            }
        }
    };

})(window.HizliKasa);
