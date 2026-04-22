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
        const detayliAraBtn = document.getElementById('iade-detayli-ara-btn');
        const toggleBtn = document.getElementById('iade-detayli-toggle-btn');
        const detayliAlanlar = document.getElementById('iade-detayli-alanlar');
        const siparisInput = document.getElementById('iade-siparis-no');
        const onaylaBtn = document.getElementById('iade-onayla-btn');

        if (bulBtn) {
            bulBtn.onclick = () => fetchOrder(siparisInput.value);
        }

        if (toggleBtn && detayliAlanlar) {
            toggleBtn.onclick = () => {
                const isHidden = detayliAlanlar.style.display === 'none';
                detayliAlanlar.style.display = isHidden ? 'block' : 'none';
                toggleBtn.innerText = isHidden ? '✕ Aramayı Kapat' : '🔍 Detaylı Arama';
                toggleBtn.classList.toggle('aktif', isHidden);
            };
        }

        if (detayliAraBtn) {
            detayliAraBtn.onclick = advancedSearchOrders;
        }

        if (siparisInput) {
            siparisInput.onkeydown = (e) => {
                if (e.key === 'Enter') {
                    fetchOrder(siparisInput.value);
                    // Artık temizlemiyoruz ki ne okuttuğunu görsün veya hemen bulsun
                }
            };
            siparisInput.focus();
        }

        // Telefon maskeleme (İade arama alanı için)
        const telInput = document.getElementById('iade-arama-telefon');
        if (telInput) {
            telInput.addEventListener('input', function(e) {
                var x = e.target.value.replace(/\D/g, '').match(/(\d{0,1})(\d{0,3})(\d{0,3})(\d{0,2})(\d{0,2})/);
                if (!x[1]) { e.target.value = ''; return; }
                e.target.value = !x[2] ? x[1] : x[1] + ' (' + x[2] + (x[3] ? ') ' + x[3] : '') + (x[4] ? ' ' + x[4] : '') + (x[5] ? ' ' + x[5] : '');
            });
        }

        if (onaylaBtn) {
            onaylaBtn.onclick = processRefund;
        }

        const iskontoInput = document.getElementById('iade-iskonto-input');
        if (iskontoInput) {
            iskontoInput.oninput = () => {
                const kalan = (originalOrder.total_discount || 0) - (originalOrder.refunded_discount || 0);
                const sepetToplami = refundCart.reduce((sum, item) => sum + (item.price * item.qty), 0);
                const maxDusebilir = Math.min(kalan, sepetToplami);
                
                if (parseFloat(iskontoInput.value) > maxDusebilir) {
                    iskontoInput.value = maxDusebilir.toFixed(2);
                }
                renderRefundCart();
            };
        }

        // Modül konteynerine tıklandığında input'u tekrar odakla (Hızlı barkod için)
        const container = document.getElementById('iade-modul-konteyner');
        if (container) {
            container.onclick = (e) => {
                if (e.target.tagName !== 'BUTTON' && e.target.tagName !== 'INPUT' && e.target.tagName !== 'SELECT') {
                    siparisInput.focus();
                }
            };
        }
    }

    async function advancedSearchOrders() {
        document.querySelector('.iade-sol-panel').classList.add('searching-mode');

        const params = new URLSearchParams({
            phone: document.getElementById('iade-arama-telefon').value,
            barcode: document.getElementById('iade-arama-urun').value,
            price_min: document.getElementById('iade-arama-fiyat-min').value,
            price_max: document.getElementById('iade-arama-fiyat-max').value,
            date_start: document.getElementById('iade-arama-tarih-bas').value,
            date_end: document.getElementById('iade-arama-tarih-bit').value
        });

        showLoading();
        try {
            const apiBase = kasaAyar.rootApiUrl || (window.location.origin + '/wp-json/');
            const response = await fetch(`${apiBase}hizli-kasa/v1/search-orders?${params.toString()}`, {
                headers: { 'X-WP-Nonce': kasaAyar.nonce }
            });

            const results = await response.json();
            renderSearchResults(results);

        } catch (error) {
            alert('Arama hatası: ' + error.message);
        } finally {
            hideLoading();
        }
    }

    function renderSearchResults(results) {
        const container = document.getElementById('iade-arama-sonuclari');
        const list = document.getElementById('iade-sonuc-listesi');
        
        if (!list) return;
        if (!results || results.length === 0) {
            list.innerHTML = '<li class="sonuc-yok">Eşleşen sipariş bulunamadı.</li>';
        } else {
            list.innerHTML = results.map(order => {
                const isFull = order.is_fully_refunded;
                const clickAction = isFull ? '' : `RefundManager.selectOrder('${order.id}')`;
                const fullClass = isFull ? 'fully-refunded-row' : '';
                const badge = isFull ? '<span class="iade-badge">Tamamı İade Edildi</span>' : '';

                return `
                    <li onclick="${clickAction}" class="${fullClass}">
                        <div class="sonuc-ana">
                            <strong>#${order.id} ${badge}</strong>
                            <span>${order.date}</span>
                        </div>
                        <div class="sonuc-detay">
                            <span>💰 ${order.total} TL</span>
                            <span>👤 ${order.telefon}</span>
                            <span>👤 Kasiyer: ${order.kasiyer}</span>
                        </div>
                    </li>
                `;
            }).join('');
        }
        
        container.style.display = 'block';

        // Kapatma butonu ekle (Eğer yoksa)
        const baslik = container.querySelector('h3');
        if (baslik && !baslik.querySelector('.iade-sonuc-kapat')) {
            const kapatBtn = document.createElement('button');
            kapatBtn.className = 'iade-sonuc-kapat iade-kucuk-btn';
            kapatBtn.innerHTML = '✕ Kapat';
            kapatBtn.onclick = closeSearchResults;
            baslik.appendChild(kapatBtn);
        }
    }

    function closeSearchResults() {
        document.querySelector('.iade-sol-panel').classList.remove('searching-mode');
        document.getElementById('iade-arama-sonuclari').style.display = 'none';
    }

    function selectOrder(id) {
        document.getElementById('iade-siparis-no').value = id;
        closeSearchResults();

        // Detaylı arama formunu kapat
        const detayliAlanlar = document.getElementById('iade-detayli-alanlar');
        const toggleBtn = document.getElementById('iade-detayli-toggle-btn');
        if (detayliAlanlar && detayliAlanlar.style.display !== 'none') {
            detayliAlanlar.style.display = 'none';
            if (toggleBtn) {
                toggleBtn.innerText = '🔍 Detaylı Arama';
                toggleBtn.classList.remove('aktif');
            }
        }

        fetchOrder(id);
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

            const data = await response.json();
            if (!response.ok) {
                throw new Error(data.message || 'Sipariş bulunamadı');
            }

            originalOrder = data;
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
                <span>Ödeme: ${originalOrder.payment}</span> | <span>Toplam: ${originalOrder.total} TL</span> <br>
                <span class="original-kasa-bilgi">📢 Bu sipariş <strong>Kasa ${originalOrder.kasa_no || 'Bilinmiyor'}</strong> üzerinden satılmış. (Kasiyer: ${originalOrder.kasiyer || '-'})</span>
            </div>
            
            <div class="iade-islem-ayarlari" style="margin: 10px 0; padding: 10px; background: rgba(0,0,0,0.05); border-radius: 8px;">
                <label style="display: block; margin-bottom: 5px; font-weight: bold; font-size: 13px;">İadenin İşleneceği Kasa:</label>
                <select id="iade-hedef-kasa" class="terminal-input" style="width: 100%;" onmousedown="event.stopPropagation()" onclick="event.stopPropagation()">
                    ${(() => {
                        let options = '';
                        const total = kasaAyar.toplamKasa || 3;
                        const originalKasaNo = parseInt(originalOrder.kasa_no) || 1;
                        // Eğer orijinal kasa artık mevcut değilse (toplam sayı düşürüldüyse) 1'e çek
                        const defaultKasa = (originalKasaNo > 0 && originalKasaNo <= total) ? originalKasaNo : 1;
                        
                        for (let i = 1; i <= total; i++) {
                            options += `<option value="${i}" ${defaultKasa == i ? 'selected' : ''}>Kasa ${i}</option>`;
                        }
                        return options;
                    })()}
                </select>
            </div>

            <div class="urun-listesi-baslik">İade Edilecek Ürünleri Seçin</div>
            <div class="iade-kaydirilabilir-liste">
        `;

        originalOrder.items.forEach(item => {
            var depoBadge = item.depo_adi 
                ? '<span class="depo-badge" title="Çıkış Deposu">📦 ' + item.depo_adi + '</span>'
                : '<span class="depo-badge depo-bilinmeyen" title="Depo bilgisi yok">📦 Bilinmeyen</span>';

            html += `
                <div class="iade-urun-satir">
                    <div class="urun-bilgi">
                        <span class="urun-ad">${item.name}</span>
                        <span class="urun-sku">SKU: ${item.sku} ${depoBadge}</span>
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
        const iskontoInput = document.getElementById('iade-iskonto-input');
        if (iskontoInput) iskontoInput.value = 0;
        
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
            refundCart.push({ 
                ...item, 
                qty: 1,
                variation_id: item.variation_id || 0,
                depo_id: item.depo_id || 0  // Orijinal çıkış deposu
            });
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
        
        // İskonto Yönetimi
        const iskontoKonteyner = document.getElementById('iade-iskonto-konteyner');
        const iskontoInput = document.getElementById('iade-iskonto-input');
        const kalanIskontoSpan = document.getElementById('iade-kalan-iskonto');
        
        const kalanIskonto = (originalOrder.total_discount || 0) - (originalOrder.refunded_discount || 0);
        console.log('İskonto Bilgisi:', { 
            total: originalOrder.total_discount, 
            refunded: originalOrder.refunded_discount, 
            kalan: kalanIskonto 
        });
        
        if (kalanIskonto > 0) {
            iskontoKonteyner.style.display = 'block';
            kalanIskontoSpan.innerText = `${kalanIskonto.toFixed(2)} TL`;
            iskontoInput.max = kalanIskonto;
        } else {
            iskontoKonteyner.style.display = 'none';
            if (iskontoInput) iskontoInput.value = 0;
        }

        const currentDiscount = parseFloat(iskontoInput ? iskontoInput.value : 0) || 0;
        const finalTotal = total - currentDiscount;

        totalSpan.innerText = `-${Math.abs(finalTotal).toFixed(2)} TL`;
        onaylaBtn.disabled = refundCart.length === 0;
    }

    async function processRefund() {
        if (!confirm('İade faturası oluşturulacak. Onaylıyor musunuz?')) return;

        showLoading();
        try {
            const apiBase = kasaAyar.rootApiUrl || (window.location.origin + '/wp-json/');
            const selectedKasa = document.getElementById('iade-hedef-kasa') ? document.getElementById('iade-hedef-kasa').value : (kasaAyar.kasaNo || "1");

            const response = await fetch(`${apiBase}hizli-kasa/v1/process-refund`, {
                method: 'POST',
                headers: { 
                    'X-WP-Nonce': kasaAyar.nonce,
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    original_order_id: originalOrder.id,
                    kasa_no: selectedKasa,
                    refund_discount: parseFloat(document.getElementById('iade-iskonto-input')?.value || 0),
                    items: refundCart.map(item => ({
                        id: item.id,
                        item_id: item.item_id,
                        variation_id: item.variation_id || 0,
                        qty: item.qty,
                        price: item.price,
                        depo_id: item.depo_id || 0  // Orijinal çıkış deposu (backend bu depoya iade eder)
                    }))
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
        removeFromRefundCart,
        selectOrder,
        closeSearchResults
    };
})();

RefundManager.init();
