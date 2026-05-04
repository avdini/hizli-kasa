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
                    var isLocked = order.has_refund || order.is_split;
                    var lockReason = "";
                    if (order.has_refund) lockReason = "(İade İşlemi Gördü)";
                    else if (order.is_split) lockReason = "(Bölünmüş Ödeme)";

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

        selectOrder: function(order) {
            this.activeOrder = order;
            this.editedItems = {};
            
            document.getElementById("order-edit-list-view").style.display = "none";
            document.getElementById("order-edit-detail-view").style.display = "block";
            
            document.getElementById("edit-order-payment").value = order.payment_method;
            document.getElementById("edit-order-phone").value = order.phone || "";
            var discountVal = parseFloat(order.manual_discount || order.discount || 0);
            document.getElementById("edit-order-discount").value = discountVal > 0 ? HK.CurrencyMask.format(discountVal) : "";
            this.renderItems();
        },

        renderItems: function() {
            var self = this;
            var container = document.getElementById("order-edit-items-container");
            container.innerHTML = "";

            this.activeOrder.items.forEach(function(item) {
                var currentQty = self.editedItems[item.item_id] !== undefined ? self.editedItems[item.item_id] : item.qty;
                var isRemoved = currentQty === 0;
                var maxQty = item.max_qty || item.qty;

                var div = document.createElement("div");
                div.className = "edit-item-row" + (isRemoved ? " removed-item" : "");
                div.innerHTML = `
                    <div class="edit-item-info">
                        <span class="edit-item-name">${item.name}</span>
                        <span class="edit-item-price">${parseFloat(item.total / item.qty).toFixed(2)} TL / Adet</span>
                        ${maxQty > item.qty ? '<small style="color:var(--hk-success); display:block;">Stokta var: Max ' + maxQty + '</small>' : ''}
                    </div>
                    <div class="edit-item-actions">
                        <div class="edit-qty-control">
                            <button class="edit-qty-btn minus" data-id="${item.item_id}">-</button>
                            <span class="edit-qty-val">${currentQty}</span>
                            <button class="edit-qty-btn plus" data-id="${item.item_id}" ${currentQty >= maxQty ? 'disabled style="opacity:0.3; cursor:not-allowed;"' : ''}>+</button>
                        </div>
                        <button class="remove-item-btn" data-id="${item.item_id}">${isRemoved ? 'Geri Al' : 'Kaldır'}</button>
                    </div>
                `;

                // Azaltma butonu
                div.querySelector(".minus").addEventListener("click", function() {
                    if (currentQty > 0) self.updateItemQty(item.item_id, currentQty - 1);
                });

                // Arttırma butonu
                div.querySelector(".plus").addEventListener("click", function() {
                    if (currentQty < maxQty) self.updateItemQty(item.item_id, currentQty + 1);
                });

                // Kaldırma/Geri Al butonu
                div.querySelector(".remove-item-btn").addEventListener("click", function() {
                    if (isRemoved) {
                        self.updateItemQty(item.item_id, item.qty);
                    } else {
                        self.updateItemQty(item.item_id, 0);
                    }
                });

                container.appendChild(div);
            });
        },

        updateItemQty: function(itemId, newQty) {
            this.editedItems[itemId] = newQty;
            this.renderItems();
        },

        saveChanges: async function() {
            var self = this;
            var paymentMethod = document.getElementById("edit-order-payment").value;
            var phone = document.getElementById("edit-order-phone").value;
            var discount = HK.CurrencyMask.parse(document.getElementById("edit-order-discount").value || "0");
            var changes = [];

            for (var itemId in this.editedItems) {
                changes.push({
                    item_id: itemId,
                    qty: this.editedItems[itemId]
                });
            }

            var hasItemChanges = changes.length > 0;
            var hasPaymentChanges = paymentMethod !== this.activeOrder.payment_method;
            var hasPhoneChanges = phone !== (this.activeOrder.phone || "");
            var hasDiscountChanges = discount !== parseFloat(this.activeOrder.manual_discount || this.activeOrder.discount || 0);

            if (!hasItemChanges && !hasPaymentChanges && !hasPhoneChanges && !hasDiscountChanges) {
                HK.UIRenderer.showToast("Herhangi bir değişiklik yapılmadı.", 'info');
                return;
            }

            // Telefon doğrulaması (boş değilse)
            if (phone.trim() !== "") {
                var rawPhone = phone.replace(/\D/g, '');
                if (rawPhone.length !== 11 || rawPhone[0] !== '0') {
                    HK.UIRenderer.showToast("Lütfen geçerli bir telefon numarası giriniz (05xx...)", "error", true);
                    document.getElementById("edit-order-phone").focus();
                    return;
                }
            }

            if (!confirm("Sipariş düzenlenecek ve stoklar güncellenecek. Emin misiniz?")) return;

            var saveBtn = document.getElementById("order-edit-save");
            saveBtn.disabled = true;
            saveBtn.innerText = "Kaydediliyor...";

            try {
                var response = await fetch(kasaAyar.rootApiUrl + 'hizli-kasa/v1/update-order', {
                    method: 'POST',
                    headers: { 
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': kasaAyar.nonce 
                    },
                    body: JSON.stringify({
                        order_id: this.activeOrder.id,
                        payment_method: paymentMethod,
                        phone: phone,
                        discount: discount,
                        items: changes
                    })
                });
                var result = await response.json();

                if (result.success) {
                    HK.UIRenderer.showToast("Sipariş başarıyla güncellendi.", 'success');
                    document.getElementById("order-edit-modal").style.display = "none";
                } else {
                    HK.UIRenderer.showToast("Hata: " + (result.message || "Bilinmeyen bir hata"), 'error');
                }
            } catch (e) {
                console.error("Save edit error", e);
                HK.UIRenderer.showToast("İşlem sırasında bir hata oluştu.", 'error');
            } finally {
                saveBtn.disabled = false;
                saveBtn.innerText = "Değişiklikleri Kaydet";
            }
        }
    };

})(window.HizliKasa);
