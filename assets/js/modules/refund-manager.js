/**
 * Hızlı Kasa - İade (Refund) Yönetim Modülü
 */

const RefundManager = (function () {
    let originalOrder = null;
    let refundCart = [];

    function init() {
        // İade sekmesi her yüklendiğinde (lazy load sonrası) elementleri tekrar yakala
        document.addEventListener('hkTabLoaded', function(e) {
            if (e.detail.tab === 'iade') {
                bindEvents();
            }
        });
    }

    function bindEvents() {
        const bulBtn = document.getElementById('iade-siparis-bul-btn');
        const siparisInput = document.getElementById('iade-siparis-no');
        const onaylaBtn = document.getElementById('iade-onayla-btn');

        if (bulBtn) {
            bulBtn.onclick = () => fetchOrder(siparisInput.value);
        }

        if (siparisInput) {
            siparisInput.onkeydown = (e) => {
                if (e.key === 'Enter') {
                    fetchOrder(siparisInput.value);
                    siparisInput.value = ''; // Barkod okunduktan sonra temizle
                }
            };
            siparisInput.focus();
        }

        if (onaylaBtn) {
            onaylaBtn.onclick = processRefund;
        }

        // Modül konteynerine tıklandığında input'u tekrar odakla (Hızlı barkod için)
        const container = document.getElementById('iade-modul-konteyner');
        if (container) {
            container.onclick = (e) => {
                if (e.target.tagName !== 'BUTTON' && e.target.tagName !== 'INPUT') {
                    siparisInput.focus();
                }
            };
        }
    }

    async function fetchOrder(id) {
        if (!id) return;
        id = id.replace('#', '').trim();

        showLoading();
        try {
            const apiBase = kasaAyar.rootApiUrl || (window.location.origin + '/wp-json/');
            const response = await fetch(`${apiBase}hizli-kasa/v1/get-order?id=${id}`, {
                headers: { 'X-WP-Nonce': kasaAyar.nonce }
            });

            if (!response.ok) throw new Error('Sipariş bulunamadı');

            originalOrder = await response.json();
            renderOrderDetails();

        } catch (error) {
            alert('Hata: ' + error.message);
            originalOrder = null;
        } finally {
            hideLoading();
        }
    }

    function renderOrderDetails() {
        const container = document.getElementById('iade-siparis-detay');
        if (!container) return;

        let html = `
            <div class="siparis-ozet">
                <strong>Sipariş: #${originalOrder.id}</strong> | Tarih: ${originalOrder.date} <br>
                <span>Ödeme: ${originalOrder.payment}</span> | <span>Toplam: ${originalOrder.total} TL</span>
            </div>
            <div class="urun-listesi-baslik">Siparişteki Ürünler</div>
            <div class="iade-kaydirilabilir-liste">
        `;

        originalOrder.items.forEach(item => {
            html += `
                <div class="iade-urun-satir">
                    <div class="urun-bilgi">
                        <span class="urun-ad">${item.name}</span>
                        <span class="urun-sku">SKU: ${item.sku}</span>
                    </div>
                    <div class="urun-fiyat-adet">
                        <span class="birim-fiyat">${item.price} TL</span> x <span class="mevcut-adet">${item.qty}</span>
                    </div>
                    <button class="iade-ekle-btn" onclick="RefundManager.addToRefundCart('${item.item_id}')">İade Et</button>
                </div>
            `;
        });

        html += `</div>`;
        container.innerHTML = html;
        
        // Sepeti sıfırla
        refundCart = [];
        renderRefundCart();
    }

    function addToRefundCart(itemId) {
        const item = originalOrder.items.find(i => i.item_id == itemId);
        if (!item) return;

        const cartItem = refundCart.find(i => i.item_id == itemId);
        
        if (cartItem) {
            if (cartItem.qty < item.qty) {
                cartItem.qty++;
            } else {
                alert('Siparişteki adetten fazla iade edilemez.');
            }
        } else {
            refundCart.push({ ...item, qty: 1 });
        }

        renderRefundCart();
    }

    function removeFromRefundCart(itemId) {
        refundCart = refundCart.filter(i => i.item_id != itemId);
        renderRefundCart();
    }

    function renderRefundCart() {
        const list = document.getElementById('iade-sepet-listesi');
        const totalSpan = document.getElementById('iade-toplam-tutar');
        const onaylaBtn = document.getElementById('iade-onayla-btn');
        
        if (!list) return;

        let total = 0;
        let html = '';

        refundCart.forEach(item => {
            const lineTotal = item.price * item.qty;
            total += lineTotal;
            html += `
                <li>
                    <div class="sepet-item-bilgi">
                        <strong>${item.name}</strong> <br>
                        <span>${item.qty} adet x ${item.price} TL</span>
                    </div>
                    <div class="sepet-item-fiyat">-${lineTotal.toFixed(2)} TL</div>
                    <button class="sepet-sil" onclick="RefundManager.removeFromRefundCart('${item.item_id}')">✕</button>
                </li>
            `;
        });

        list.innerHTML = html || '<p class="iade-bos-sepet">İade edilecek ürün seçilmedi.</p>';
        totalSpan.innerText = `-${total.toFixed(2)} TL`;
        onaylaBtn.disabled = refundCart.length === 0;
    }

    async function processRefund() {
        if (!confirm('İade faturası oluşturulacak. Onaylıyor musunuz?')) return;

        showLoading();
        try {
            const apiBase = kasaAyar.rootApiUrl || (window.location.origin + '/wp-json/');
            const response = await fetch(`${apiBase}hizli-kasa/v1/process-refund`, {
                method: 'POST',
                headers: { 
                    'X-WP-Nonce': kasaAyar.nonce,
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    original_order_id: originalOrder.id,
                    items: refundCart
                })
            });

            const data = await response.json();
            if (data.success) {
                alert('İade başarıyla tamamlandı. Sipariş No: #' + data.order_id);
                // Ekranı temizle
                document.getElementById('iade-siparis-no').value = '';
                document.getElementById('iade-siparis-detay').innerHTML = `
                    <div class="iade-basari-mesaj">
                        <span>✅</span>
                        <p>#${data.order_id} nolu iade faturası oluşturuldu.</p>
                        <button onclick="location.reload()">Yeni İşlem</button>
                    </div>
                `;
                refundCart = [];
                renderRefundCart();
            } else {
                throw new Error(data.message || 'İşlem başarısız');
            }

        } catch (error) {
            alert('Hata: ' + error.message);
        } finally {
            hideLoading();
        }
    }

    function showLoading() {
        const overlay = document.getElementById('app-loading');
        if (overlay) overlay.style.display = 'flex';
    }

    function hideLoading() {
        const overlay = document.getElementById('app-loading');
        if (overlay) overlay.style.display = 'none';
    }

    return {
        init,
        addToRefundCart,
        removeFromRefundCart
    };
})();

RefundManager.init();
