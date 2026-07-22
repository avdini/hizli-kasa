/**
 * Hızlı Kasa - İade (Refund) Yönetim Modülü
 */

const RefundManager = (function () {
    const HK = window.HizliKasa;
    let originalOrder = null;
    let refundCart = [];
    let refundSplitData = null;
    let isManualMode = false;
    let manualSearchTimeout = null;
    let manualSearchController = null;
    let currentSearchPage = 1;
    let activeSearchParams = null;

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
        const manuelToggleBtn = document.getElementById('iade-manuel-toggle-btn');
        const detayliAlanlar = document.getElementById('iade-detayli-alanlar');
        const siparisInput = document.getElementById('iade-siparis-no');
        const manuelInput = document.getElementById('iade-manuel-urun-ara');
        const manuelTemizleBtn = document.getElementById('iade-manuel-temizle-btn');
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

        if (manuelToggleBtn) {
            manuelToggleBtn.onclick = toggleManualMode;
        }

        if (manuelInput) {
            manuelInput.oninput = (e) => {
                clearTimeout(manualSearchTimeout);
                manualSearchTimeout = setTimeout(() => {
                    searchManualProducts(e.target.value);
                }, 300);
            };
            manuelInput.onkeydown = (e) => {
                if (e.key === 'Enter') {
                    clearTimeout(manualSearchTimeout);
                    searchManualProducts(manuelInput.value, true);
                }
            };
        }

        if (manuelTemizleBtn) {
            manuelTemizleBtn.onclick = () => {
                if (manuelInput) {
                    manuelInput.value = '';
                    manuelInput.focus();
                    searchManualProducts('');
                }
            };
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

        const degisimBtn = document.getElementById('degisim-kasaya-gonder-btn');
        if (degisimBtn) {
            degisimBtn.onclick = sendToRegisterForExchange;
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

        const modalToplamInput = document.getElementById('iade-modal-toplam-input');
        if (modalToplamInput) {
            modalToplamInput.onchange = () => {
                const cartTotal = refundCart.reduce((sum, item) => sum + (item.price * item.qty), 0);
                
                if (originalOrder && originalOrder.has_item_discount) {
                    modalToplamInput.value = HK.CurrencyMask.format(cartTotal);
                    alert('Bu siparişte iskonto ürün fiyatlarına yedirilmiştir. Ekstra iskonto düşülemez.');
                    if (document.querySelector('input[name="iade_payment_method"]:checked')?.value === 'split') {
                        calculateRefundSplit();
                    }
                    return;
                }

                const enteredTotal = HK.CurrencyMask.parse(modalToplamInput.value);
                
                let requiredDiscount = cartTotal - enteredTotal;
                let finalTotal = enteredTotal;
                let hasCorrection = false;
                
                // Doğrulama 1: Negatif iskonto engeli (Toplam > Sepet)
                if (requiredDiscount < 0) {
                    requiredDiscount = 0;
                    finalTotal = cartTotal;
                    hasCorrection = true;
                }
                
                // Doğrulama 2: Maksimum iskonto engeli (Sadece orijinal sipariş varsa)
                if (originalOrder) {
                    const maxDusebilir = (originalOrder.manual_discount || 0) - (originalOrder.refunded_manual_discount || 0);
                    if (requiredDiscount > maxDusebilir) {
                        requiredDiscount = maxDusebilir;
                        finalTotal = cartTotal - maxDusebilir;
                        hasCorrection = true;
                        alert('⚠️ Maksimum iskonto limitini aştınız!\nTutar izin verilen limite (' + finalTotal.toFixed(2) + ' TL) göre düzeltildi.');
                    }
                }
                
                // İskonto alanını güncelle
                const iskontoInput = document.getElementById('iade-iskonto-input');
                if (iskontoInput) {
                    iskontoInput.value = HK.CurrencyMask.format(requiredDiscount);
                }
                
                // Kendi değerini de (eğer düzeltme varsa) güncelle
                if (hasCorrection) {
                    modalToplamInput.value = HK.CurrencyMask.format(finalTotal);
                }
                
                // Bölünmüş ödeme hesaplamasını tetikle
                if (document.querySelector('input[name="iade_payment_method"]:checked')?.value === 'split') {
                    calculateRefundSplit();
                }
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

        // Depo değiştiğinde ekranı temizle (başka deponun siparişi kalmasın)
        document.addEventListener('hkActiveDepoChanged', function() {
            originalOrder = null;
            refundCart = [];
            const container = document.getElementById('iade-siparis-detay');
            if (container) {
                if (isManualMode) {
                    container.innerHTML = '<div class="iade-bos-state">Ürün arayarak iadeye başlayın.</div>';
                } else {
                    container.innerHTML = '<div class="iade-bos-state">Lütfen iade edilecek siparişi seçin veya okutun.</div>';
                }
            }
            renderRefundCart();
            closeSearchResults();
        });

        const detayContainer = document.getElementById('iade-siparis-detay');
        if (detayContainer) {
            detayContainer.addEventListener('click', function(e) {
                const img = e.target.closest('.iade-item-image');
                if (img && img.src) {
                    e.stopPropagation();
                    e.preventDefault();
                    openImagePreview(img.src);
                }
            });
        }
    }

    function toggleManualMode() {
        isManualMode = !isManualMode;
        
        const manuelToggleBtn = document.getElementById('iade-manuel-toggle-btn');
        const detayliToggleBtn = document.getElementById('iade-detayli-toggle-btn');
        const basitAramaKonteyner = document.getElementById('iade-basit-arama-konteyner');
        const manuelAramaKonteyner = document.getElementById('iade-manuel-arama-konteyner');
        const detayliAlanlar = document.getElementById('iade-detayli-alanlar');
        const baslik = document.getElementById('iade-sol-baslik');
        const detayPanel = document.getElementById('iade-siparis-detay');

        if (isManualMode) {
            manuelToggleBtn.innerHTML = '✕ Sipariş Sorgula';
            manuelToggleBtn.style.background = 'var(--hk-danger)';
            detayliToggleBtn.style.display = 'none';
            basitAramaKonteyner.style.display = 'none';
            manuelAramaKonteyner.style.display = 'block';
            detayliAlanlar.style.display = 'none';
            baslik.innerText = 'Ürün İade Et (Manuel)';
            detayPanel.innerHTML = '<div class="iade-bos-state">Ürün arayarak iadeye başlayın.</div>';
            
            document.getElementById('iade-manuel-urun-ara').focus();
        } else {
            manuelToggleBtn.innerHTML = '➕ Sıfırdan İade';
            manuelToggleBtn.style.background = 'var(--hk-accent)';
            detayliToggleBtn.style.display = 'inline-block';
            basitAramaKonteyner.style.display = 'block';
            manuelAramaKonteyner.style.display = 'none';
            baslik.innerText = 'Sipariş Sorgula';
            detayPanel.innerHTML = '<div class="iade-bos-durum"><span class="bos-ikon">🔍</span><p>İşleme başlamak için bir sipariş barkodu okutun.</p></div>';
            
            document.getElementById('iade-siparis-no').focus();
        }

        // Temizle
        originalOrder = null;
        refundCart = [];
        renderRefundCart();
    }

    async function searchManualProducts(query, isExact = false) {
        if (!query || query.length < 2) {
            const container = document.getElementById('iade-siparis-detay');
            if (container) container.innerHTML = '<div class="iade-bos-state">Ürün arayarak iadeye başlayın.</div>';
            return;
        }

        if (manualSearchController) {
            manualSearchController.abort();
        }
        manualSearchController = new AbortController();

        const depo_id = HK.DepoManager ? HK.DepoManager.getActiveDepo() : 0;
        const apiBase = kasaAyar.rootApiUrl || (window.location.origin + '/wp-json/');
        
        try {
            const response = await fetch(`${apiBase}hizli-kasa/v1/search?s=${encodeURIComponent(query)}&depo_id=${depo_id}${isExact ? '&exact=1' : ''}`, {
                headers: { 'X-WP-Nonce': kasaAyar.nonce },
                signal: manualSearchController.signal
            });

            const results = await response.json();
            renderManualSearchResults(results);

            // Eğer tam eşleşme ise ve tek ürün geldiyse doğrudan ekle
            if (isExact && results.length === 1) {
                addManualToRefundCart(results[0]);
                document.getElementById('iade-manuel-urun-ara').value = '';
                document.getElementById('iade-manuel-urun-ara').focus();
            }

        } catch (error) {
            if (error.name === 'AbortError') return;
            console.error('Manuel arama hatası:', error);
        }
    }

    function renderAttributeChipsHtml(product) {
        if (product.attributes) {
            let chips = '';
            if (typeof product.attributes === 'object' && !Array.isArray(product.attributes)) {
                const keys = Object.keys(product.attributes);
                keys.forEach(key => {
                    const val = product.attributes[key];
                    if (!val) return;
                    const lowerKey = key.toLowerCase();
                    const isStandard = ['beden', 'renk', 'numara', 'size', 'color', 'ebat'].includes(lowerKey);
                    const labelText = isStandard ? val : `${key}: ${val}`;
                    chips += `<span class="attr-chip" title="${key}: ${val}">${labelText}</span>`;
                });
            } else if (typeof product.attributes === 'string' && product.attributes.trim() !== '') {
                chips = `<span class="attr-chip">${product.attributes}</span>`;
            }
            if (chips) {
                return `<div class="attr-chip-container">${chips}</div>`;
            }
        }
        if (product.name && product.name.includes(' - ')) {
            const parts = product.name.split(' - ').slice(1).join(' - ').split(',');
            const chips = parts.map(p => `<span class="attr-chip">${p.trim()}</span>`).join('');
            return `<div class="attr-chip-container">${chips}</div>`;
        }
        return `<span class="urun-ad">${product.name}</span>`;
    }

    function renderManualSearchResults(results) {
        const container = document.getElementById('iade-siparis-detay');
        if (!container) return;

        if (!results || results.length === 0) {
            container.innerHTML = '<div class="iade-bos-state">Sonuç bulunamadı.</div>';
            return;
        }

        let html = `<div class="urun-listesi-baslik">Arama Sonuçları (${results.length})</div><div class="iade-kaydirilabilir-liste">`;
        
        results.forEach(product => {
            const isVariable = product.type === 'variable' || product.is_variable;
            const isVariation = product.type === 'variation' || product.parent_id > 0;
            const btnHtml = isVariable 
                ? `<span class="iade-uyari-text">Varyant Seçin</span>`
                : `<button class="iade-ekle-btn" onclick="RefundManager.addManualToRefundCart(${JSON.stringify(product).replace(/"/g, '&quot;')})">İadeye Ekle</button>`;

            const imageSrc = (product.images && product.images.length > 0)
                ? product.images[0].src
                : (product.image ? product.image : null);

            const imgHtml = imageSrc
                ? `<img src="${imageSrc}" class="iade-item-image" alt="">`
                : `<div class="iade-item-image-placeholder"></div>`;

            const rowClass = isVariation ? 'iade-urun-satir iade-varyasyon-satir' : 'iade-urun-satir';
            const nameHtml = isVariation
                ? `<div class="urun-ad-wrapper"><span class="var-icon">↳</span>${renderAttributeChipsHtml(product)}</div>`
                : `<span class="urun-ad">${product.name}</span>`;

            html += `
                <div class="${rowClass}">
                    ${imgHtml}
                    <div class="urun-bilgi">
                        ${nameHtml}
                        <span class="urun-sku">SKU: ${product.sku || '-'} | Stok: ${product.stock_quantity || 0}</span>
                    </div>
                    <div class="urun-fiyat-adet">
                        <span class="birim-fiyat">${product.price} TL</span>
                    </div>
                    ${btnHtml}
                </div>
            `;
        });

        html += `</div>`;
        container.innerHTML = html;
    }

    function addManualToRefundCart(product) {
        const itemId = 'manual_' + product.id;
        const cartItem = refundCart.find(i => i.item_id === itemId);

        if (cartItem) {
            cartItem.qty++;
        } else {
            const isVariation = (product.type === 'variation' || product.parent_id > 0);
            refundCart.push({
                item_id: itemId,
                id: isVariation ? product.parent_id : product.id,
                name: product.name,
                sku: product.sku,
                price: parseFloat(product.price),
                qty: 1,
                variation_id: isVariation ? product.id : 0,
                depo_id: HK.DepoManager ? HK.DepoManager.getActiveDepo() : 0
            });
        }

        renderRefundCart();
    }

    async function advancedSearchOrders() {
        document.querySelector('.iade-sol-panel').classList.add('searching-mode');

        const depo_id = HK.DepoManager ? HK.DepoManager.getActiveDepo() : 0;
        activeSearchParams = {
            phone: document.getElementById('iade-arama-telefon').value,
            barcode: document.getElementById('iade-arama-urun').value,
            price_min: document.getElementById('iade-arama-fiyat-min').value,
            price_max: document.getElementById('iade-arama-fiyat-max').value,
            date_start: document.getElementById('iade-arama-tarih-bas').value,
            date_end: document.getElementById('iade-arama-tarih-bit').value,
            depo_id: depo_id
        };

        currentSearchPage = 1;
        await fetchSearchOrdersPage(currentSearchPage, false);
    }

    async function fetchSearchOrdersPage(page, append = false) {
        if (!activeSearchParams) return;

        const params = new URLSearchParams({
            ...activeSearchParams,
            page: page
        });

        showLoading();
        try {
            const apiBase = kasaAyar.rootApiUrl || (window.location.origin + '/wp-json/');
            const response = await fetch(`${apiBase}hizli-kasa/v1/search-orders?${params.toString()}`, {
                headers: { 'X-WP-Nonce': kasaAyar.nonce }
            });

            const data = await response.json();
            renderSearchResults(data, append);

        } catch (error) {
            alert('Arama hatası: ' + error.message);
        } finally {
            hideLoading();
        }
    }

    function loadMoreOrders() {
        currentSearchPage++;
        fetchSearchOrdersPage(currentSearchPage, true);
    }

    function renderSearchResults(data, append = false) {
        const container = document.getElementById('iade-arama-sonuclari');
        const list = document.getElementById('iade-sonuc-listesi');

        if (!list) return;

        const results = data.results || [];
        const has_more = data.has_more || false;

        const itemsHtml = results.map(order => {
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

        if (append) {
            list.innerHTML += itemsHtml;
        } else {
            if (results.length === 0) {
                list.innerHTML = '<li class="sonuc-yok">Eşleşen sipariş bulunamadı.</li>';
            } else {
                list.innerHTML = itemsHtml;
            }
        }

        // Manage Load More button
        let loadMoreBtn = document.getElementById('iade-arama-daha-fazla-btn');
        if (has_more) {
            if (!loadMoreBtn) {
                loadMoreBtn = document.createElement('button');
                loadMoreBtn.id = 'iade-arama-daha-fazla-btn';
                loadMoreBtn.className = 'iade-arama-btn tam-genislik';
                loadMoreBtn.style.marginTop = '15px';
                loadMoreBtn.innerText = 'Daha Fazla Yükle';
                loadMoreBtn.onclick = loadMoreOrders;
                list.parentNode.appendChild(loadMoreBtn);
            }
        } else {
            if (loadMoreBtn) {
                loadMoreBtn.remove();
            }
        }

        if (document.querySelector('.iade-sol-panel').classList.contains('searching-mode')) {
            container.style.display = 'flex';
        } else {
            container.style.display = 'block';
        }

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
        const loadMoreBtn = document.getElementById('iade-arama-daha-fazla-btn');
        if (loadMoreBtn) {
            loadMoreBtn.remove();
        }
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
            const depo_id = HK.DepoManager ? HK.DepoManager.getActiveDepo() : 0;
            const apiBase = kasaAyar.rootApiUrl || (window.location.origin + '/wp-json/');
            const response = await fetch(`${apiBase}hizli-kasa/v1/get-order?id=${id}&depo_id=${depo_id}`, {
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

        const itemsList = Array.isArray(originalOrder.items)
            ? originalOrder.items
            : Object.values(originalOrder.items || {});

        itemsList.forEach(item => {
            var depoBadge = item.depo_adi
                ? `<span class="depo-badge" title="Çıkış Deposu: ${item.depo_adi}">📦 ${item.depo_adi}</span>`
                : `<span class="depo-badge depo-bilinmeyen" title="Depo bilgisi bulunamadı">📦 Bilinmeyen</span>`;

            var itemDiscount = parseFloat(item.item_discount) || 0;
            var iskontoBadge = (originalOrder.has_item_discount && itemDiscount > 0)
                ? `<span class="urun-iskonto-badge" title="Ürüne özel -${itemDiscount.toFixed(2)} ₺ iskonto uygulandı">İsk: -${itemDiscount.toFixed(2)} ₺</span>`
                : '';

            var imgHtml = item.image
                ? `<img src="${item.image}" class="iade-item-image" alt="" title="${item.name}">`
                : `<div class="iade-item-image-placeholder"></div>`;

            var nameHtml = `<span class="urun-ad" title="${item.name}">${item.name}</span>`;
            if (item.name && item.name.includes(' - ')) {
                var nameParts = item.name.split(' - ');
                var parentTitle = nameParts[0];
                var attrText = nameParts.slice(1).join(' - ');
                var attrChips = attrText.split(',').map(a => `<span class="attr-chip" title="${a.trim()}">${a.trim()}</span>`).join('');
                nameHtml = `<span class="urun-ad" title="${item.name}">${parentTitle}</span><div class="attr-chip-container">${attrChips}</div>`;
            }

            html += `
                <div class="iade-urun-satir" title="${item.name}">
                    ${imgHtml}
                    <div class="urun-bilgi">
                        ${nameHtml}
                        <div class="urun-meta-satir">
                            <span class="urun-sku">SKU: ${item.sku || '-'}</span>
                            ${depoBadge}
                        </div>
                    </div>
                    <div class="urun-fiyat-adet">
                        <span class="birim-fiyat">${item.price} TL</span> ${iskontoBadge}
                        <span class="adet-vurgu">x <span class="mevcut-adet">${item.qty}</span></span>
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
        if (modalOzetKonteynir) {
            if (isManualMode) {
                modalOzetKonteynir.innerHTML = `
                    <div class="siparis-ozet-v2">
                        <div class="ozet-ust">
                            <div class="ozet-sol">
                                <span class="ozet-no">MANUEL İADE</span>
                                <span class="ozet-tarih">${new Date().toLocaleDateString('tr-TR')}</span>
                            </div>
                        </div>
                        <div class="ozet-govde">
                            <div class="ozet-kart" style="width:100%">
                                <div class="kart-baslik">ℹ️ BİLGİ</div>
                                <div class="kart-icerik">
                                    <p>Sistem dışı veya eski satışlar için sıfırdan iade oluşturuluyor.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            } else if (sidebarOzet) {
                modalOzetKonteynir.innerHTML = sidebarOzet.innerHTML;
            }
        }

        // Modal içindeki toplamı güncelle
        const cartTotal = refundCart.reduce((sum, item) => sum + (item.price * item.qty), 0);
        const modalToplamInput = document.getElementById('iade-modal-toplam-input');
        if (modalToplamInput) {
            modalToplamInput.value = HK.CurrencyMask.format(cartTotal);
        }

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
        if (!container || (!originalOrder && !isManualMode)) {
            if (container) container.style.display = 'none';
            return;
        }

        const origMethod = originalOrder ? originalOrder.payment_method : 'cod';
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
                        <label class="iade-odeme-btn-label">
                            <input type="radio" name="iade_payment_method" value="coupon" ${defaultMethod === 'coupon' ? 'checked' : ''}>
                            <span>🎟️ Kupon</span>
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

                <div id="iade-coupon-detay" style="display:none; margin-top: 15px; padding: 12px; background: var(--hk-bg-body); border-radius: 8px; border: 1px dashed var(--hk-accent);">
                    <div class="iade-ayar-satir">
                        <label style="font-weight:bold; color:var(--hk-danger);">Kupon Telefon Numarası (Zorunlu):</label>
                        <input type="text" id="iade-coupon-phone" class="hk-input" placeholder="05XX XXX XX XX" maxlength="15">
                        <small style="color:var(--hk-text-muted); display:block; margin-top:5px;">Müşteri fişi kaybederse veya tekrar kullanmak isterse doğrulamak için zorunludur.</small>
                    </div>
                </div>

                <div class="iade-ayar-satir" style="margin-top: 15px;">
                    <label>İadenin İşleneceği Kasa:</label>
                    <select id="iade-hedef-kasa" class="hk-input">
                        ${(() => {
                let options = '';
                const total = kasaAyar.toplamKasa || 3;
                const originalKasaNo = originalOrder ? parseInt(originalOrder.kasa_no) : 1;
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
                const couponDetay = container.querySelector('#iade-coupon-detay');
                if (couponDetay) couponDetay.style.display = r.value === 'coupon' ? 'block' : 'none';

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
        
        const couponPhoneInput = document.getElementById('iade-coupon-phone');
        if (couponPhoneInput && window.intlTelInput) {
            if (window.refundIti) {
                window.refundIti.destroy();
            }
            window.refundIti = window.intlTelInput(couponPhoneInput, {
                initialCountry: "tr",
                separateDialCode: true,
                strictMode: true,
                countryOrder: ["tr", "de", "nl", "be", "at", "fr", "gb", "us"],
                loadUtils: function () {
                    return import("https://cdn.jsdelivr.net/npm/intl-tel-input@29.0.3/dist/js/utils.js");
                },
                placeholderNumberPolicy: "AGGRESSIVE",
                formatAsYouType: true,
                countrySearch: true,
                countryNameLocale: "tr",
                uiTranslations: {
                    searchPlaceholder: "Ülke ara...",
                    searchEmptyState: "Sonuç bulunamadı",
                }
            });
        }

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
        const discount = (originalOrder && originalOrder.has_item_discount) ? 0 : HK.CurrencyMask.parse(document.getElementById('iade-iskonto-input')?.value || "0");
        return Math.abs(cartTotal - discount);
    }

    function addToRefundCart(itemId) {
        if (!originalOrder && !isManualMode) return;
        
        // Manuel modda bu fonksiyon yerine addManualToRefundCart kullanılıyor
        if (isManualMode) return;

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
                price: item.discounted_price !== undefined ? item.discounted_price : item.price,
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
        const degisimBtn = document.getElementById('degisim-kasaya-gonder-btn');

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
        if (degisimBtn) degisimBtn.disabled = refundCart.length === 0;

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

        if (originalOrder && originalOrder.has_item_discount) {
            if (iskontoKonteyner) iskontoKonteyner.style.display = 'none';
            if (iskontoInput) iskontoInput.value = 0;
            
            const modalToplamInput = document.getElementById('iade-modal-toplam-input');
            if (modalToplamInput) {
                const finalTotal = getRefundFinalTotal();
                modalToplamInput.value = HK.CurrencyMask.format(finalTotal);
            }
            return;
        }

        if (isManualMode && HK.CurrencyMask.parse(iskontoInput.value || "0") <= 0) {
            if (iskontoKonteyner) iskontoKonteyner.style.display = 'none';
            return;
        }

        if (!iskontoKonteyner) return;
        if (!originalOrder && !isManualMode) return;

        const kalanIskonto = originalOrder ? ((originalOrder.manual_discount || 0) - (originalOrder.refunded_manual_discount || 0)) : 999999;
        const currentDiscount = HK.CurrencyMask.parse(iskontoInput.value || "0");

        if (kalanIskonto > 0 || currentDiscount > 0) {
            iskontoKonteyner.style.display = 'block';
            if (originalOrder) {
                kalanIskontoSpan.innerText = `${kalanIskonto.toFixed(2)} TL`;
                iskontoInput.max = kalanIskonto;
            } else {
                kalanIskontoSpan.innerText = 'Sınırsız (Manuel)';
            }
        } else {
            iskontoKonteyner.style.display = 'none';
            if (iskontoInput) iskontoInput.value = 0;
        }

        // Modal toplamını güncelle (Eğer modal açıksa)
        const modalToplamInput = document.getElementById('iade-modal-toplam-input');
        if (modalToplamInput) {
            const finalTotal = getRefundFinalTotal();
            modalToplamInput.value = HK.CurrencyMask.format(finalTotal);
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

            let couponPhone = '';
            if (paymentMethod === 'coupon') {
                if (window.refundIti) {
                    if (!window.refundIti.isValidNumber()) {
                        alert('Lütfen kupon için geçerli bir telefon numarası girin!');
                        return;
                    }
                    couponPhone = window.refundIti.getNumber();
                } else {
                    couponPhone = document.getElementById('iade-coupon-phone') ? document.getElementById('iade-coupon-phone').value.trim() : '';
                    if (couponPhone.replace(/\D/g, '').length < 10) {
                        alert('Lütfen kupon için geçerli bir telefon numarası girin!');
                        return;
                    }
                }
            }

            const response = await fetch(`${apiBase}hizli-kasa/v1/process-refund`, {
                method: 'POST',
                headers: {
                    'X-WP-Nonce': kasaAyar.nonce,
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    original_order_id: originalOrder ? originalOrder.id : '',
                    is_manual: isManualMode,
                    active_depo_id: HK.DepoManager ? HK.DepoManager.getActiveDepo() : 0,
                    kasa_no: selectedKasa,
                    payment_method: paymentMethod,
                    split_data: refundSplitData,
                    coupon_phone: couponPhone,
                    refund_discount: (originalOrder && originalOrder.has_item_discount) ? 0 : HK.CurrencyMask.parse(document.getElementById('iade-iskonto-input')?.value || "0"),
                    items: refundCart.map(item => ({
                        id: item.id,
                        item_id: item.item_id,
                        variation_id: item.variation_id || 0,
                        qty: item.qty,
                        price: item.price,
                        depo_id: item.depo_id || 0
                    }))
                })
            });

            const data = await response.json();
            if (data.success) {
                alert('İade başarıyla tamamlandı. Sipariş No: #' + data.order_id);
                
                if (paymentMethod === 'coupon' && data.coupon) {
                    if (window.HizliKasa && window.HizliKasa.ReceiptPrinter && typeof window.HizliKasa.ReceiptPrinter.printCouponReceipt === 'function') {
                        window.HizliKasa.ReceiptPrinter.printCouponReceipt(data.coupon);
                    }
                }

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

    function openImagePreview(src) {
        if (!src || src.includes('placeholder')) return;

        if (window.HizliKasa && window.HizliKasa.StockTerminal && typeof window.HizliKasa.StockTerminal.openImagePreview === 'function') {
            window.HizliKasa.StockTerminal.openImagePreview(src);
            return;
        }
        if (window.HizliKasa && window.HizliKasa.UIRenderer && typeof window.HizliKasa.UIRenderer.openImagePreview === 'function') {
            window.HizliKasa.UIRenderer.openImagePreview(src);
            return;
        }

        var modal = document.getElementById('terminal-image-preview-modal');
        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'terminal-image-preview-modal';
            modal.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.85);z-index:999999;display:flex;align-items:center;justify-content:center;backdrop-filter:blur(5px);cursor:zoom-out;';
            
            var loader = document.createElement('div');
            loader.id = 'terminal-preview-loader';
            loader.style.cssText = 'position:absolute;width:40px;height:40px;border:4px solid #fff;border-top:4px solid transparent;border-radius:50%;animation:hk-spin 1s linear infinite;';
            
            if (!document.getElementById('hk-spin-keyframes')) {
                var style = document.createElement('style');
                style.id = 'hk-spin-keyframes';
                style.innerHTML = '@keyframes hk-spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }';
                document.head.appendChild(style);
            }

            var img = document.createElement('img');
            img.id = 'terminal-preview-img';
            img.style.cssText = 'max-width:90%;max-height:90%;object-fit:contain;border-radius:12px;opacity:0;transition:opacity 0.3s;box-shadow:0 10px 40px rgba(0,0,0,0.5);';
            
            modal.appendChild(loader);
            modal.appendChild(img);
            document.body.appendChild(modal);

            modal.addEventListener('click', function() {
                modal.style.display = 'none';
            });
        }

        var imgEl = document.getElementById('terminal-preview-img');
        var loaderEl = document.getElementById('terminal-preview-loader');
        
        imgEl.style.opacity = '0';
        imgEl.src = '';
        loaderEl.style.display = 'block';
        modal.style.display = 'flex';
        
        var fullSrc = src.replace(/-\d+x\d+(\.[a-zA-Z]+)$/i, '$1');
        
        imgEl.onload = function() {
            loaderEl.style.display = 'none';
            imgEl.style.opacity = '1';
        };
        imgEl.onerror = function() {
            loaderEl.style.display = 'none';
            imgEl.style.opacity = '1';
            if (imgEl.src !== src) {
                imgEl.src = src;
            }
        };
        
        imgEl.src = fullSrc;
    }

    /**
     * İade sepetindeki ürünleri kasa sepetine negatif satır olarak gönderir.
     * Müşteri yeni ürünleri de ekleyip tek sipariş olarak kapatabilir (değişim akışı).
     */
    function sendToRegisterForExchange() {
        if (refundCart.length === 0) return;

        const HK = window.HizliKasa;
        if (!HK || !HK.CartManager || !HK.State) {
            alert('Kasa modülü yüklenemedi. Lütfen sayfayı yenileyin.');
            return;
        }

        if (HK.State.editingOrderId) {
            alert("Kasada şu anda aktif bir sipariş düzenleme işlemi var! Lütfen önce düzenlemeyi tamamlayın veya iptal edin.");
            return;
        }

        if (!confirm(`${refundCart.length} ürün değişim için kasaya gönderilecek. Devam edilsin mi?`)) return;

        // İade sepetindeki ürünleri kasa sepetine negatif satır olarak ekle
        refundCart.forEach(item => {
            const isVariation = (item.variation_id && item.variation_id > 0);

            const exchangeItem = {
                product_id: item.id,
                variation_id: item.variation_id || 0,
                quantity: -(Math.abs(item.qty)),  // Negatif adet
                name: item.name,
                sku: item.sku || '',
                price: parseFloat(item.price),
                regular_price: parseFloat(item.price),
                line_discount: 0,
                image: '',
                _is_exchange_return: true,
                _exchange_depo_id: item.depo_id || (HK.DepoManager ? HK.DepoManager.getActiveDepo() : 0),
                _exchange_original_order: originalOrder ? originalOrder.id : null,
                _exchange_item_id: item.item_id || null
            };

            // Sepete ekle (aynı ürün varsa bile yeni satır olarak — negatif satır birleştirilmemeli)
            HK.State.sepet.unshift(exchangeItem);
        });

        // İskonto ve ödeme bilgilerini sıfırla (değişim sepetinde temiz başla)
        HK.State.iskontoTutar = 0;
        HK.State.splitData = null;
        HK.State.odemeTipi = 'card';
        HK.State.sepet.forEach(item => { item.line_discount = 0; });

        // Kaydet ve UI güncelle
        HK.CartManager.sepetiKaydet();
        if (HK.UIRenderer) {
            HK.UIRenderer.arayuzuGuncelle();
        }

        // İade sekmesini temizle
        const addedCount = refundCart.length;
        refundCart = [];
        originalOrder = null;
        renderRefundCart();

        const detayPanel = document.getElementById('iade-siparis-detay');
        if (detayPanel) {
            detayPanel.innerHTML = `
                <div class="iade-basari-mesaj degisim-mesaj">
                    <span>🔄</span>
                    <p>${addedCount} ürün değişim için kasaya gönderildi.</p>
                    <p style="font-size:13px; color:var(--hk-text-muted);">Kasa sekmesine geçip yeni ürünleri ekleyin.</p>
                </div>
            `;
        }

        // Kasa sekmesine geçiş
        const kasaTab = document.querySelector('.ust-sekme[data-tab="kasa"]');
        if (kasaTab) {
            kasaTab.click();
        }

        // Toast bildirim
        if (HK.UIRenderer && HK.UIRenderer.showToast) {
            HK.UIRenderer.showToast(`🔄 ${addedCount} ürün değişim için kasaya eklendi. Yeni ürünleri okutun.`, 'success');
        }
    }

    return {
        init,
        addToRefundCart,
        removeFromRefundCart,
        selectOrder,
        closeSearchResults,
        addManualToRefundCart,
        sendToRegisterForExchange
    };
})();

RefundManager.init();
