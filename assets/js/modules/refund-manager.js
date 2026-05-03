/**
 * Hızlı Kasa - İade (Refund) Yönetim Modülü
 */

const RefundManager = (function () {
    const HK = window.HizliKasa;
    let originalOrder = null;
    let refundCart = [];
    let refundSplitData = null;

    function init() {
        // İade sekmesi her yüklendiğinde (lazy load sonrası) elementleri tekrar yakala
        document.addEventListener('hkTabLoaded', function (e) {
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
            telInput.addEventListener('input', function (e) {
                var x = e.target.value.replace(/\D/g, '').match(/(\d{0,1})(\d{0,3})(\d{0,3})(\d{0,2})(\d{0,2})/);
                if (!x[1]) { e.target.value = ''; return; }
                e.target.value = !x[2] ? x[1] : x[1] + ' (' + x[2] + (x[3] ? ') ' + x[3] : '') + (x[4] ? ' ' + x[4] : '') + (x[5] ? ' ' + x[5] : '');
            });
        }

        if (onaylaBtn) {
            onaylaBtn.onclick = openRefundModal;
        }

        const modalVazgec = document.getElementById('iade-modal-vazgec');
        if (modalVazgec) {
            modalVazgec.onclick = closeRefundModal;
        }

        const modalTamamla = document.getElementById('iade-modal-tamamla');
        if (modalTamamla) {
            modalTamamla.onclick = processRefund;
        }

        const iskontoInput = document.getElementById('iade-iskonto-input');
        if (iskontoInput) {
            iskontoInput.oninput = () => {
                if (!originalOrder) return;
                const kalan = (originalOrder.manual_discount || 0) - (originalOrder.refunded_manual_discount || 0);
                const sepetToplami = refundCart.reduce((sum, item) => sum + (item.price * item.qty), 0);
                const maxDusebilir = Math.min(kalan, sepetToplami);

                if (HK.CurrencyMask.parse(iskontoInput.value) > maxDusebilir) {
                    iskontoInput.value = HK.CurrencyMask.format(maxDusebilir);
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

        // Modal dışına tıklandığında kapat
        window.addEventListener('click', (e) => {
            const modal = document.getElementById('iade-onay-modal');
            if (e.target === modal) {
                closeRefundModal();
            }
        });
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

        let odemeDetayHtml = '';
        if (originalOrder.payment_details) {
            const p = originalOrder.payment_details;
            const detaylar = [];
            if (p.nakit > 0) detaylar.push(`<span class="odeme-turu nakit">💵 ${p.nakit.toFixed(2)} TL</span>`);
            if (p.kart > 0) detaylar.push(`<span class="odeme-turu kart">💳 ${p.kart.toFixed(2)} TL</span>`);
            if (p.iban > 0) detaylar.push(`<span class="odeme-turu iban">🏦 ${p.iban.toFixed(2)} TL</span>`);
            if (detaylar.length > 0) {
                odemeDetayHtml = `<div class="ozet-odeme-detay">${detaylar.join('')}</div>`;
            }
        }

        let html = `
            <div class="siparis-ozet-v2">
                <div class="ozet-ust">
                    <div class="ozet-sol">
                        <span class="ozet-no">#${originalOrder.id}</span>
                        <span class="ozet-tarih">${originalOrder.date}</span>
                    </div>
                    <div class="ozet-sag">
                        <span class="ozet-toplam-label">SİPARİŞ TOPLAMI</span>
                        <span class="ozet-toplam-deger">${originalOrder.total} TL</span>
                    </div>
                </div>
                
                <div class="ozet-govde">
                    <div class="ozet-kart">
                        <div class="kart-baslik">💳 ÖDEME BİLGİLERİ</div>
                        <div class="kart-icerik">
                            <div class="ozet-satir">
                                <span class="label">Yöntem:</span>
                                <span class="deger">${originalOrder.payment}</span>
                            </div>
                            ${odemeDetayHtml}
                        </div>
                    </div>

                    <div class="ozet-kart">
                        <div class="kart-baslik">🏪 İŞLEM DETAYI</div>
                        <div class="kart-icerik">
                            <div class="ozet-satir">
                                <span class="label">Kasa:</span>
                                <span class="deger highlight">Kasa ${originalOrder.kasa_no || 'Bilinmiyor'}</span>
                            </div>
                            <div class="ozet-satir">
                                <span class="label">Kasiyer:</span>
                                <span class="deger">${originalOrder.kasiyer || '-'}</span>
                            </div>
                        </div>
                    </div>
                </div>
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
        if (iskontoInput) iskontoInput.value = "";

        renderRefundCart();
    }

    function openRefundModal() {
        const modal = document.getElementById('iade-onay-modal');
        if (!modal) return;

        modal.style.display = 'flex';

        // Sipariş özetini modalın soluna kopyala
        const sidebarOzet = document.querySelector('.siparis-ozet-v2');
        const modalOzetKonteynir = document.getElementById('iade-modal-siparis-ozet');
        if (sidebarOzet && modalOzetKonteynir) {
            modalOzetKonteynir.innerHTML = sidebarOzet.innerHTML;
        }

        // Modal içindeki toplamı güncelle
        const cartTotal = refundCart.reduce((sum, item) => sum + (item.price * item.qty), 0);
        document.getElementById('iade-modal-toplam').innerText = cartTotal.toFixed(2) + ' TL';

        // Ayarları render et (Ödeme yöntemleri vs)
        renderRefundSettings();

        // İskonto maskesini uygula (Eğer yeni elementler geldiyse)
        if (HK.CurrencyMask) {
            HK.CurrencyMask.init(modal);
        }
    }

    function closeRefundModal() {
        const modal = document.getElementById('iade-onay-modal');
        if (modal) modal.style.display = 'none';
    }

    function renderRefundSettings() {
        const container = document.getElementById('iade-odeme-yontemi-alani');
        if (!container || !originalOrder) {
            if (container) container.style.display = 'none';
            return;
        }

        const origMethod = originalOrder.payment_method;
        let defaultMethod = 'nakit';
        if (origMethod === 'other') defaultMethod = 'kart';
        else if (origMethod === 'bacs') defaultMethod = 'iban';
        else if (origMethod === 'split') defaultMethod = 'split';

        container.innerHTML = `
            <div class="iade-ayarlar-panel">
                <div class="iade-ayar-satir">
                    <label>İade Ödeme Yöntemi:</label>
                    <div class="iade-odeme-secenekleri">
                        <label class="iade-odeme-btn-label">
                            <input type="radio" name="iade_payment_method" value="split" ${defaultMethod === 'split' ? 'checked' : ''}>
                            <span>➗ Böl</span>
                        </label>
                        <label class="iade-odeme-btn-label">
                            <input type="radio" name="iade_payment_method" value="nakit" ${defaultMethod === 'nakit' ? 'checked' : ''}>
                            <span>💵 Nakit</span>
                        </label>
                        <label class="iade-odeme-btn-label">
                            <input type="radio" name="iade_payment_method" value="kart" ${defaultMethod === 'kart' ? 'checked' : ''}>
                            <span>💳 Kart</span>
                        </label>
                        <label class="iade-odeme-btn-label">
                            <input type="radio" name="iade_payment_method" value="iban" ${defaultMethod === 'iban' ? 'checked' : ''}>
                            <span>🏦 IBAN</span>
                        </label>
                    </div>
                </div>

                <div id="iade-bol-detay" style="${defaultMethod === 'split' ? 'display:block;' : 'display:none;'} margin-top: 15px; padding: 12px; background: var(--hk-bg-body); border-radius: 8px; border: 1px dashed var(--hk-accent);">
                    <div class="iade-bol-input-grup" style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap: 8px;">
                        <div>
                            <label style="font-size:11px; color:var(--hk-text-muted);">💵 Nakit</label>
                            <input type="text" id="iade-bol-nakit" class="hk-input hk-currency-mask" placeholder="0,00" style="padding: 6px; font-size:13px;">
                        </div>
                        <div>
                            <label style="font-size:11px; color:var(--hk-text-muted);">💳 Kart</label>
                            <input type="text" id="iade-bol-kart" class="hk-input hk-currency-mask" placeholder="0,00" style="padding: 6px; font-size:13px;">
                        </div>
                        <div>
                            <label style="font-size:11px; color:var(--hk-text-muted);">🏦 IBAN</label>
                            <input type="text" id="iade-bol-iban" class="hk-input hk-currency-mask" placeholder="0,00" style="padding: 6px; font-size:13px;">
                        </div>
                    </div>
                    <div id="iade-bol-kalan-uyari" style="margin-top: 10px; font-size: 12px; font-weight: bold; text-align: center;"></div>
                </div>

                <div class="iade-ayar-satir" style="margin-top: 15px;">
                    <label>İadenin İşleneceği Kasa:</label>
                    <select id="iade-hedef-kasa" class="hk-input">
                        ${(() => {
                let options = '';
                const total = kasaAyar.toplamKasa || 3;
                const originalKasaNo = parseInt(originalOrder.kasa_no) || 1;
                const defaultKasa = (originalKasaNo > 0 && originalKasaNo <= total) ? originalKasaNo : 1;

                for (let i = 1; i <= total; i++) {
                    options += `<option value="${i}" ${defaultKasa == i ? 'selected' : ''}>Kasa ${i}</option>`;
                }
                return options;
            })()}
                    </select>
                </div>
            </div>
        `;

        // Eğer split ise değerleri sıfırla (Kasiyer manuel girecek)
        if (defaultMethod === 'split') {
            document.getElementById('iade-bol-nakit').value = '0,00';
            document.getElementById('iade-bol-kart').value = '0,00';
            document.getElementById('iade-bol-iban').value = '0,00';

            calculateRefundSplit();
        }

        // Event Listeners for Split UI
        const radios = container.querySelectorAll('input[name="iade_payment_method"]');
        const bolDetay = container.querySelector('#iade-bol-detay');

        radios.forEach(r => {
            r.addEventListener('change', () => {
                bolDetay.style.display = r.value === 'split' ? 'block' : 'none';
                if (r.value === 'split') {
                    // Varsayılan olarak hepsini sıfırla (Kasiyer manuel girecek)
                    document.getElementById('iade-bol-nakit').value = '0,00';
                    document.getElementById('iade-bol-kart').value = '0,00';
                    document.getElementById('iade-bol-iban').value = '0,00';

                    calculateRefundSplit();
                }
            });
        });

        const bolInputs = container.querySelectorAll('.iade-bol-input-grup input');
        bolInputs.forEach(inp => {
            inp.addEventListener('input', calculateRefundSplit);
        });

        container.style.display = 'block';
    }

    function calculateRefundSplit() {
        const total = getRefundFinalTotal();
        const nakit = HK.CurrencyMask.parse(document.getElementById('iade-bol-nakit')?.value || "0");
        const kart = HK.CurrencyMask.parse(document.getElementById('iade-bol-kart')?.value || "0");
        const iban = HK.CurrencyMask.parse(document.getElementById('iade-bol-iban')?.value || "0");

        const girenToplam = nakit + kart + iban;
        const kalan = total - girenToplam;
        const uyariArea = document.getElementById('iade-bol-kalan-uyari');

        if (!uyariArea) return;

        if (total <= 0) {
            uyariArea.innerHTML = '<span style="color:var(--hk-text-muted);">İade edilecek ürün seçin</span>';
            refundSplitData = null;
            return;
        }

        if (Math.abs(kalan) < 0.01) {
            uyariArea.innerHTML = '<span style="color:var(--hk-success);">✅ Tutar Tamamlandı</span>';
            refundSplitData = { nakit, kart, iban };
        } else {
            const farkMetni = kalan > 0 ? `Kalan: ${kalan.toFixed(2)} TL` : `Fazla: ${Math.abs(kalan).toFixed(2)} TL`;
            uyariArea.innerHTML = `<span style="color:var(--hk-danger);">${farkMetni}</span>`;
            refundSplitData = null;
        }
    }

    function getRefundFinalTotal() {
        const cartTotal = refundCart.reduce((sum, item) => sum + (item.price * item.qty), 0);
        const discount = HK.CurrencyMask.parse(document.getElementById('iade-iskonto-input')?.value || "0");
        return Math.abs(cartTotal - discount);
    }

    function addToRefundCart(itemId) {
        if (!originalOrder) return;
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

        // Yan paneldeki toplamı güncelle (iskonto düşülmeden önceki sepet toplamı)
        totalSpan.innerText = `-${total.toFixed(2)} TL`;
        onaylaBtn.disabled = refundCart.length === 0;

        // İskonto Yönetimi (Sadece modal açıkken veya ayarlar render edildiğinde anlamlı ama burada da kalabilir)
        updateDiscountVisibility();

        if (document.querySelector('input[name="iade_payment_method"]:checked')?.value === 'split') {
            calculateRefundSplit();
        }
    }

    function updateDiscountVisibility() {
        const iskontoKonteyner = document.getElementById('iade-iskonto-konteyner');
        const iskontoInput = document.getElementById('iade-iskonto-input');
        const kalanIskontoSpan = document.getElementById('iade-kalan-iskonto');

        if (!originalOrder || !iskontoKonteyner) return;

        const kalanIskonto = (originalOrder.manual_discount || 0) - (originalOrder.refunded_manual_discount || 0);

        if (kalanIskonto > 0) {
            iskontoKonteyner.style.display = 'block';
            kalanIskontoSpan.innerText = `${kalanIskonto.toFixed(2)} TL`;
            iskontoInput.max = kalanIskonto;
        } else {
            iskontoKonteyner.style.display = 'none';
            if (iskontoInput) iskontoInput.value = 0;
        }

        // Modal toplamını güncelle (Eğer modal açıksa)
        const modalToplam = document.getElementById('iade-modal-toplam');
        if (modalToplam) {
            const finalTotal = getRefundFinalTotal();
            modalToplam.innerText = `${finalTotal.toFixed(2)} TL`;
        }
    }

    async function processRefund() {
        if (!confirm('İade faturası oluşturulacak. Onaylıyor musunuz?')) return;

        showLoading();
        try {
            const apiBase = kasaAyar.rootApiUrl || (window.location.origin + '/wp-json/');
            const selectedKasa = document.getElementById('iade-hedef-kasa') ? document.getElementById('iade-hedef-kasa').value : (kasaAyar.kasaNo || "1");
            const paymentMethod = document.querySelector('input[name="iade_payment_method"]:checked')?.value || "nakit";

            if (paymentMethod === 'split' && !refundSplitData) {
                alert('Lütfen bölünmüş ödeme tutarlarını kontrol edin! Toplam eşleşmiyor.');
                return;
            }

            const response = await fetch(`${apiBase}hizli-kasa/v1/process-refund`, {
                method: 'POST',
                headers: {
                    'X-WP-Nonce': kasaAyar.nonce,
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    original_order_id: originalOrder.id,
                    kasa_no: selectedKasa,
                    payment_method: paymentMethod,
                    split_data: refundSplitData,
                    refund_discount: HK.CurrencyMask.parse(document.getElementById('iade-iskonto-input')?.value || "0"),
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

                closeRefundModal();

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
                renderRefundSettings();
                jQuery(document).trigger('hk:iade-tamamlandi');
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
