/**
 * Hızlı Kasa - Mal Kabul & Tedarik Yönetimi
 */

const MalkabulManager = (function () {
    const API_URL = kasaAyar.rootApiUrl + 'hizli-kasa/v2';
    
    let suppliers = [];
    let activeDepoId = null;

    function init() {
        // Tab Yüklendiğinde
        document.addEventListener('hkTabLoaded', (e) => {
            if (e.detail.tab === 'sevk') {
                bindEvents();
                activeDepoId = window.HizliKasa && window.HizliKasa.DepoManager ? window.HizliKasa.DepoManager.getActiveDepo() : kasaAyar.activeDepoId;
                loadSuppliers();
                loadPurchaseOrders();
            }
        });
    }

    function bindEvents() {
        const btnSaveSupplier = document.getElementById('tedarikci-yeni-kaydet');
        if(btnSaveSupplier) {
            btnSaveSupplier.addEventListener('click', saveSupplier);
        }

        const btnRefreshSuppliers = document.getElementById('malkabul-tedarikciler-yenile');
        if(btnRefreshSuppliers) {
            btnRefreshSuppliers.addEventListener('click', loadSuppliers);
        }

        const btnRefreshPOs = document.getElementById('malkabul-siparisler-yenile');
        if(btnRefreshPOs) {
            btnRefreshPOs.addEventListener('click', loadPurchaseOrders);
        }

        const inputBarcode = document.getElementById('malkabul-yeni-barkod');
        if (inputBarcode) {
            inputBarcode.addEventListener('keypress', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    searchProductForPO(this.value);
                }
            });
        }

        const btnAddCustom = document.getElementById('malkabul-yeni-bagimsiz-btn');
        if (btnAddCustom) {
            btnAddCustom.addEventListener('click', addCustomProductToPO);
        }

        const btnSavePO = document.getElementById('malkabul-yeni-kaydet');
        if (btnSavePO) {
            btnSavePO.addEventListener('click', savePurchaseOrder);
        }

        const btnCloseModal = document.getElementById('malkabul-modal-kapat');
        if (btnCloseModal) {
            btnCloseModal.addEventListener('click', () => {
                document.getElementById('malkabul-detay-modal').style.display = 'none';
            });
        }

        const btnReceive = document.getElementById('malkabul-teslim-al-btn');
        if (btnReceive) {
            btnReceive.addEventListener('click', receivePurchaseOrder);
        }
    }

    // --- TEDARİKÇİLER ---

    async function loadSuppliers() {
        try {
            const res = await fetch(`${API_URL}/suppliers?_=${Date.now()}`, {
                headers: { 'X-WP-Nonce': kasaAyar.nonce }
            });
            const data = await res.json();
            if (data.success && data.data.suppliers) {
                suppliers = data.data.suppliers;
                renderSuppliersList();
                populateSupplierSelect();
            }
        } catch (error) {
            console.error(error);
        }
    }

    function renderSuppliersList() {
        const list = document.getElementById('malkabul-tedarikci-listesi');
        if(!list) return;
        
        if (suppliers.length === 0) {
            list.innerHTML = '<div class="sevk-empty">Kayıtlı tedarikçi yok.</div>';
            return;
        }

        list.innerHTML = suppliers.map(s => `
            <div class="sevk-list-card">
                <div class="sevk-card-header">
                    <strong>${s.name}</strong>
                </div>
                <div class="sevk-card-body">
                    ${s.phone ? `<div>📞 ${s.phone}</div>` : ''}
                    ${s.email ? `<div>✉️ ${s.email}</div>` : ''}
                </div>
            </div>
        `).join('');
    }

    function populateSupplierSelect() {
        const select = document.getElementById('malkabul-yeni-tedarikci');
        if(!select) return;
        
        let html = '<option value="">Seçiniz...</option>';
        suppliers.forEach(s => {
            html += `<option value="${s.id}">${s.name}</option>`;
        });
        select.innerHTML = html;
    }

    async function saveSupplier() {
        const name = document.getElementById('tedarikci-yeni-ad').value.trim();
        const phone = document.getElementById('tedarikci-yeni-tel').value.trim();
        const email = document.getElementById('tedarikci-yeni-email').value.trim();
        const tax_id = document.getElementById('tedarikci-yeni-vergi').value.trim();
        const address = document.getElementById('tedarikci-yeni-adres').value.trim();

        if (!name) {
            if(window.Toast) Toast.show('Tedarikçi adı zorunludur.', 'error');
            return;
        }

        try {
            const res = await fetch(`${API_URL}/suppliers`, {
                method: 'POST',
                headers: {
                    'X-WP-Nonce': kasaAyar.nonce,
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ name, phone, email, tax_id, address })
            });
            const data = await res.json();
            if (data.success) {
                if(window.Toast) Toast.show('Tedarikçi eklendi.', 'success');
                // Formu temizle
                document.getElementById('tedarikci-yeni-ad').value = '';
                document.getElementById('tedarikci-yeni-tel').value = '';
                document.getElementById('tedarikci-yeni-email').value = '';
                document.getElementById('tedarikci-yeni-vergi').value = '';
                document.getElementById('tedarikci-yeni-adres').value = '';
                loadSuppliers();
            } else {
                if(window.Toast) Toast.show(data.data.message || 'Hata oluştu.', 'error');
            }
        } catch (error) {
            console.error(error);
        }
    }

    // --- SİPARİŞ OLUŞTURMA ---

    let poItems = [];

    async function searchProductForPO(query) {
        if(!query) return;
        const input = document.getElementById('malkabul-yeni-barkod');
        input.disabled = true;

        try {
            // Barkod modülü varsa onu kullanalım, yoksa wc/v3/products
            let apiUrl = `${kasaAyar.apiUrl}products?search=${encodeURIComponent(query)}&per_page=5`;
            
            // Eğer barkod API'si varsa:
            if (window.BarcodeScanner) {
                // ... Özel bir arama yapılabilir, şimdilik WC API kullanalım
            }

            const res = await fetch(apiUrl, {
                headers: { 'X-WP-Nonce': kasaAyar.nonce }
            });
            const products = await res.json();

            if (products && products.length > 0) {
                const p = products[0]; // İlk ürünü al
                
                // Zaten listede var mı?
                const existing = poItems.find(i => i.product_id === p.id);
                if (existing) {
                    existing.expected_qty += 1;
                } else {
                    poItems.push({
                        product_id: p.id,
                        variation_id: 0,
                        name: p.name,
                        expected_qty: 1,
                        unit_cost: p.regular_price || 0
                    });
                }
                renderPOItems();
                input.value = '';
            } else {
                if(window.Toast) Toast.show('Ürün bulunamadı', 'warning');
            }
        } catch (error) {
            console.error(error);
        } finally {
            input.disabled = false;
            input.focus();
        }
    }

    async function addCustomProductToPO() {
        const customName = prompt("Sitede olmayan bağımsız ürünün adını girin:");
        if (!customName || customName.trim() === '') return;

        poItems.push({
            product_id: 0,
            variation_id: 0,
            name: customName.trim(),
            custom_product_name: customName.trim(),
            expected_qty: 1,
            unit_cost: 0,
            is_custom: true
        });
        renderPOItems();
    }

    function renderPOItems() {
        const tbody = document.getElementById('malkabul-yeni-kalemler');
        if (!tbody) return;

        if (poItems.length === 0) {
            tbody.innerHTML = '<tr id="malkabul-yeni-kalemler-empty"><td colspan="4" class="sevk-empty">Henüz ürün eklenmedi.</td></tr>';
            return;
        }

        let html = '';
        poItems.forEach((item, index) => {
            const displayName = item.is_custom 
                ? `${item.name} <span class="sevk-tab-badge" style="background:var(--secondary); font-size:10px;">Bağımsız</span>`
                : item.name;

            html += `
                <tr>
                    <td>${displayName}</td>
                    <td><input type="number" class="hk-input po-item-qty" data-index="${index}" value="${item.expected_qty}" min="1" style="width:80px;"></td>
                    <td><input type="number" class="hk-input po-item-cost" data-index="${index}" value="${item.unit_cost}" min="0" step="0.01" style="width:100px;"></td>
                    <td><button type="button" class="sevk-icon-btn po-item-remove" data-index="${index}" style="color:var(--danger)">🗑️</button></td>
                </tr>
            `;
        });
        tbody.innerHTML = html;

        // Event listeners
        tbody.querySelectorAll('.po-item-qty').forEach(input => {
            input.addEventListener('change', (e) => {
                const idx = e.target.getAttribute('data-index');
                poItems[idx].expected_qty = parseFloat(e.target.value) || 1;
            });
        });
        tbody.querySelectorAll('.po-item-cost').forEach(input => {
            input.addEventListener('change', (e) => {
                const idx = e.target.getAttribute('data-index');
                poItems[idx].unit_cost = parseFloat(e.target.value) || 0;
            });
        });
        tbody.querySelectorAll('.po-item-remove').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const idx = e.target.closest('button').getAttribute('data-index');
                poItems.splice(idx, 1);
                renderPOItems();
            });
        });
    }

    async function savePurchaseOrder() {
        const supplier_id = document.getElementById('malkabul-yeni-tedarikci').value;
        const reference_no = document.getElementById('malkabul-yeni-referans').value;
        const notes = document.getElementById('malkabul-yeni-not').value;

        if (!supplier_id) {
            if(window.Toast) Toast.show('Tedarikçi seçmelisiniz.', 'error');
            return;
        }
        if (poItems.length === 0) {
            if(window.Toast) Toast.show('Siparişte ürün yok.', 'error');
            return;
        }

        const btn = document.getElementById('malkabul-yeni-kaydet');
        btn.disabled = true;
        btn.textContent = 'Kaydediliyor...';

        try {
            const res = await fetch(`${API_URL}/purchase-orders`, {
                method: 'POST',
                headers: {
                    'X-WP-Nonce': kasaAyar.nonce,
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ supplier_id, reference_no, notes, items: poItems })
            });
            const data = await res.json();
            if (data.success) {
                if(window.Toast) Toast.show('Sipariş oluşturuldu.', 'success');
                // Temizle ve listeye dön
                poItems = [];
                renderPOItems();
                document.getElementById('malkabul-yeni-referans').value = '';
                document.getElementById('malkabul-yeni-not').value = '';
                document.querySelector('[data-target="malkabul-siparisler"]').click();
                loadPurchaseOrders();
            } else {
                if(window.Toast) Toast.show(data.data.message || 'Hata', 'error');
            }
        } catch (error) {
            console.error(error);
        } finally {
            btn.disabled = false;
            btn.textContent = 'Siparişi Oluştur';
        }
    }

    // --- SİPARİŞ LİSTESİ VE TESLİM ALMA ---

    async function loadPurchaseOrders() {
        try {
            const res = await fetch(`${API_URL}/purchase-orders?_=${Date.now()}`, {
                headers: { 'X-WP-Nonce': kasaAyar.nonce }
            });
            const data = await res.json();
            if (data.success && data.data.purchase_orders) {
                renderPurchaseOrders(data.data.purchase_orders);
            }
        } catch (error) {
            console.error(error);
        }
    }

    function renderPurchaseOrders(orders) {
        const tbody = document.getElementById('malkabul-siparisler-listesi');
        if (!tbody) return;

        if (orders.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" class="sevk-empty">Sipariş bulunamadı.</td></tr>';
            return;
        }

        const statusMap = {
            'pending': '<span style="color:var(--warning)">Bekliyor</span>',
            'partial': '<span style="color:var(--primary)">Kısmi Gelen</span>',
            'completed': '<span style="color:var(--success)">Tamamlandı</span>',
            'cancelled': '<span style="color:var(--danger)">İptal</span>'
        };

        tbody.innerHTML = orders.map(o => `
            <tr>
                <td><strong>#${o.id}</strong></td>
                <td>${o.supplier_name || '-'}</td>
                <td>${o.reference_no || '-'}</td>
                <td>${new Date(o.order_date).toLocaleDateString('tr-TR')}</td>
                <td>${statusMap[o.status]}</td>
                <td>
                    <button class="sevk-btn secondary malkabul-detay-btn" data-id="${o.id}">
                        ${o.status === 'completed' ? 'İncele' : 'Teslim Al'}
                    </button>
                </td>
            </tr>
        `).join('');

        tbody.querySelectorAll('.malkabul-detay-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const id = e.target.getAttribute('data-id');
                openPurchaseOrderDetail(id);
            });
        });
    }

    let currentPO = null;

    async function openPurchaseOrderDetail(id) {
        try {
            const res = await fetch(`${API_URL}/purchase-orders/${id}?_=${Date.now()}`, {
                headers: { 'X-WP-Nonce': kasaAyar.nonce }
            });
            const data = await res.json();
            
            if (data.success && data.data.purchase_order) {
                currentPO = data.data.purchase_order;
                
                document.getElementById('malkabul-modal-info').innerHTML = `
                    Sipariş: #${currentPO.id} | Tedarikçi: ${currentPO.supplier_name} <br>
                    <small>Depoya Eklenecek Konum: <span style="color:var(--primary)">${document.getElementById('ust-aktif-depo-adi').textContent}</span></small>
                `;

                const tbody = document.getElementById('malkabul-teslim-kalemleri');
                
                let html = '';
                const isCompleted = currentPO.status === 'completed';

                currentPO.items.forEach(item => {
                    const remaining = Math.max(0, parseFloat(item.expected_qty) - parseFloat(item.received_qty));
                    const badge = item.is_custom ? ' <span class="sevk-tab-badge" style="background:var(--secondary); font-size:10px;">Bağımsız</span>' : '';
                    html += `
                        <tr>
                            <td>${item.product_name}${badge}<br><small>${item.sku}</small></td>
                            <td>${item.expected_qty}</td>
                            <td>${item.received_qty}</td>
                            <td>
                                ${isCompleted ? '-' : `<input type="number" class="hk-input po-receive-qty" data-id="${item.id}" value="${remaining}" min="0" style="width:100px;">`}
                            </td>
                        </tr>
                    `;
                });
                tbody.innerHTML = html;

                document.getElementById('malkabul-teslim-al-btn').style.display = isCompleted ? 'none' : 'inline-block';
                document.getElementById('malkabul-detay-modal').style.display = 'flex';
            }
        } catch (error) {
            console.error(error);
        }
    }

    async function receivePurchaseOrder() {
        if (!currentPO) return;
        
        activeDepoId = window.HizliKasa && window.HizliKasa.DepoManager ? window.HizliKasa.DepoManager.getActiveDepo() : kasaAyar.activeDepoId;

        const itemsToReceive = [];
        document.querySelectorAll('.po-receive-qty').forEach(input => {
            const val = parseFloat(input.value);
            if (val > 0) {
                itemsToReceive.push({
                    id: input.getAttribute('data-id'),
                    received_qty: val
                });
            }
        });

        if (itemsToReceive.length === 0) {
            if(window.Toast) Toast.show('Teslim alınacak miktar girilmedi.', 'warning');
            return;
        }

        const btn = document.getElementById('malkabul-teslim-al-btn');
        btn.disabled = true;
        btn.textContent = 'İşleniyor...';

        try {
            const res = await fetch(`${API_URL}/purchase-orders/${currentPO.id}/receive`, {
                method: 'POST',
                headers: {
                    'X-WP-Nonce': kasaAyar.nonce,
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ 
                    depo_id: activeDepoId,
                    items: itemsToReceive 
                })
            });
            
            const data = await res.json();
            if (data.success) {
                if(window.Toast) Toast.show('Ürünler stoklara başarıyla eklendi.', 'success');
                document.getElementById('malkabul-detay-modal').style.display = 'none';
                loadPurchaseOrders();
            } else {
                if(window.Toast) Toast.show(data.data.message || 'Hata', 'error');
            }
        } catch (error) {
            console.error(error);
        } finally {
            btn.disabled = false;
            btn.textContent = 'Seçili Miktarları Teslim Al (Stoğa Ekle)';
        }
    }

    return {
        init: init
    };

})();

document.addEventListener('DOMContentLoaded', () => {
    MalkabulManager.init();
});
