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
                bindIadeEvents();
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
        const selectIade = document.getElementById('t-iade-yeni-tedarikci');
        
        let html = '<option value="">Seçiniz...</option>';
        suppliers.forEach(s => {
            html += `<option value="${s.id}">${s.name}</option>`;
        });
        
        if (select) select.innerHTML = html;
        if (selectIade) selectIade.innerHTML = html;
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

    // --- TEDARİKÇİ İADE ---
    let activeIade = null;
    let iadeList = [];

    function bindIadeEvents() {
        document.querySelectorAll('.sevk-alt-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                if (btn.dataset.target === 'tedarikci-iade') {
                    activeDepoId = window.HizliKasa && window.HizliKasa.DepoManager ? window.HizliKasa.DepoManager.getActiveDepo() : kasaAyar.activeDepoId;
                    const depoLabel = document.getElementById('t-iade-yeni-depo-label');
                    if (depoLabel) {
                        const activeDepoName = window.HizliKasa && window.HizliKasa.DepoManager ? window.HizliKasa.DepoManager.getActiveDepoName() : 'Depo';
                        depoLabel.value = activeDepoName;
                    }
                    loadIadeList();
                    populateIadeSupplierSelect();
                }
            });
        });

        document.addEventListener('hkActiveDepoChanged', () => {
            activeDepoId = window.HizliKasa && window.HizliKasa.DepoManager ? window.HizliKasa.DepoManager.getActiveDepo() : kasaAyar.activeDepoId;
            const depoLabel = document.getElementById('t-iade-yeni-depo-label');
            if (depoLabel && document.getElementById('tedarikci-iade').style.display !== 'none') {
                const activeDepoName = window.HizliKasa && window.HizliKasa.DepoManager ? window.HizliKasa.DepoManager.getActiveDepoName() : 'Depo';
                depoLabel.value = activeDepoName;
                loadIadeList();
            }
        });

        const btnYenile = document.getElementById('tedarikci-iade-yenile');
        if (btnYenile) btnYenile.addEventListener('click', loadIadeList);

        const btnOlustur = document.getElementById('t-iade-olustur-btn');
        if (btnOlustur) btnOlustur.addEventListener('click', createSupplierReturn);

        const inputBarkod = document.getElementById('t-iade-barkod');
        if (inputBarkod) {
            inputBarkod.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    const sku = this.value.trim();
                    this.value = '';
                    if (sku) addIadeItem(sku);
                }
            });
        }

        const btnIptal = document.getElementById('t-iade-iptal-btn');
        if (btnIptal) btnIptal.addEventListener('click', deleteDraftIade);

        const btnOnayla = document.getElementById('t-iade-onayla-btn');
        if (btnOnayla) btnOnayla.addEventListener('click', confirmIade);

        const btnDetayKapat = document.getElementById('t-iade-detay-kapat');
        if (btnDetayKapat) btnDetayKapat.addEventListener('click', closeIadeDetay);

        const btnDetayYazdir = document.getElementById('t-iade-detay-yazdir');
        if (btnDetayYazdir) {
            btnDetayYazdir.addEventListener('click', () => {
                if (activeIade) printIadeFis(activeIade);
            });
        }
    }

    function populateIadeSupplierSelect() {
        const select = document.getElementById('t-iade-yeni-tedarikci');
        if (!select) return;
        let html = '<option value="">Seçiniz...</option>';
        suppliers.forEach(s => {
            html += `<option value="${s.id}">${s.name}</option>`;
        });
        select.innerHTML = html;
    }

    async function loadIadeList() {
        const tbody = document.getElementById('tedarikci-iade-listesi');
        if (!tbody) return;

        tbody.innerHTML = '<tr><td colspan="7" class="sevk-empty">İadeler yükleniyor...</td></tr>';

        try {
            const res = await fetch(`${API_URL}/tedarikci-iade/liste?_=${Date.now()}`, {
                headers: { 'X-WP-Nonce': kasaAyar.nonce }
            });
            const data = await res.json();
            if (data.success && data.data.items) {
                iadeList = data.data.items;
                renderIadeList();
            } else {
                tbody.innerHTML = '<tr><td colspan="7" class="sevk-empty">İade bulunamadı.</td></tr>';
            }
        } catch (error) {
            console.error(error);
            tbody.innerHTML = '<tr><td colspan="7" class="sevk-empty" style="color:var(--hk-accent);">Yüklenirken hata oluştu.</td></tr>';
        }
    }

    function renderIadeList() {
        const tbody = document.getElementById('tedarikci-iade-listesi');
        if (!tbody) return;

        if (iadeList.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" class="sevk-empty">Geçmiş iade kaydı bulunmuyor.</td></tr>';
            return;
        }

        tbody.innerHTML = iadeList.map(item => {
            const dateStr = item.created_at ? new Date(item.created_at.replace(' ', 'T')).toLocaleString('tr-TR') : '-';
            const durumClass = item.durum === 'tamamlandi' ? 'tamamlandi' : 'taslak';
            const durumLabel = item.durum === 'tamamlandi' ? 'Tamamlandı' : 'Taslak';

            return `
                <tr>
                    <td><strong>${item.iade_no}</strong></td>
                    <td>${item.supplier_name || 'Bilinmeyen'}</td>
                    <td>${item.location_name || 'Bilinmeyen'}</td>
                    <td>${dateStr}</td>
                    <td>${item.toplam_cesit} / ${parseFloat(item.toplam_adet)}</td>
                    <td><span class="sevk-status ${durumClass}">${durumLabel}</span></td>
                    <td style="text-align:right;">
                        <button type="button" class="sevk-btn secondary small t-iade-git-btn" data-id="${item.id}">Git</button>
                    </td>
                </tr>
            `;
        }).join('');

        tbody.querySelectorAll('.t-iade-git-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                showIade(id);
            });
        });
    }

    async function showIade(id) {
        try {
            const res = await fetch(`${API_URL}/tedarikci-iade/detay/${id}?_=${Date.now()}`, {
                headers: { 'X-WP-Nonce': kasaAyar.nonce }
            });
            const data = await res.json();
            if (data.success && data.data.iade) {
                activeIade = data.data.iade;
                
                document.getElementById('t-iade-adim-1').style.display = 'none';
                document.getElementById('t-iade-adim-2').style.display = 'none';
                document.getElementById('t-iade-detay-panel').style.display = 'none';

                if (activeIade.durum === 'taslak') {
                    document.getElementById('t-iade-no-label').innerText = activeIade.iade_no;
                    document.getElementById('t-iade-supplier-label').innerText = `Tedarikçi: ${activeIade.supplier_name}`;
                    document.getElementById('t-iade-sebep').value = activeIade.iade_sebep || '';
                    document.getElementById('t-iade-not').value = activeIade.not || '';
                    
                    renderIadeItemsTable();
                    document.getElementById('t-iade-adim-2').style.display = 'block';
                    setTimeout(() => {
                        const input = document.getElementById('t-iade-barkod');
                        if (input) input.focus();
                    }, 50);
                } else {
                    document.getElementById('t-iade-detay-no').innerText = activeIade.iade_no;
                    document.getElementById('t-iade-detay-supplier').innerText = `Tedarikçi: ${activeIade.supplier_name}`;
                    document.getElementById('t-iade-detay-depo').innerText = activeIade.location_name || 'Bilinmeyen';
                    document.getElementById('t-iade-detay-tarih').innerText = activeIade.created_at ? new Date(activeIade.created_at.replace(' ', 'T')).toLocaleString('tr-TR') : '-';
                    document.getElementById('t-iade-detay-sebep').innerText = activeIade.iade_sebep || '-';
                    document.getElementById('t-iade-detay-not').innerText = activeIade.not || '-';

                    const tbody = document.getElementById('t-iade-detay-kalemler');
                    tbody.innerHTML = activeIade.kalemler.map(item => `
                        <tr>
                            <td><strong>${item.urun_adi}</strong></td>
                            <td>${item.sku}</td>
                            <td style="text-align:center;">${parseFloat(item.adet)}</td>
                        </tr>
                    `).join('');

                    document.getElementById('t-iade-detay-panel').style.display = 'block';
                }
            }
        } catch (error) {
            console.error(error);
        }
    }

    async function createSupplierReturn() {
        const supplierSelect = document.getElementById('t-iade-yeni-tedarikci');
        const supplierId = supplierSelect.value;
        if (!supplierId) {
            if (window.Toast) Toast.show('Lütfen bir tedarikçi seçin.', 'error');
            return;
        }

        activeDepoId = window.HizliKasa && window.HizliKasa.DepoManager ? window.HizliKasa.DepoManager.getActiveDepo() : kasaAyar.activeDepoId;

        try {
            const res = await fetch(`${API_URL}/tedarikci-iade/olustur`, {
                method: 'POST',
                headers: {
                    'X-WP-Nonce': kasaAyar.nonce,
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    supplier_id: supplierId,
                    location_id: activeDepoId
                })
            });
            const data = await res.json();
            if (data.success && data.data.iade) {
                if (window.Toast) Toast.show('İade taslağı başlatıldı.', 'success');
                showIade(data.data.iade.id);
                loadIadeList();
            } else {
                if (window.Toast) Toast.show(data.data.message || 'Hata oluştu.', 'error');
            }
        } catch (error) {
            console.error(error);
        }
    }

    async function addIadeItem(sku) {
        if (!activeIade) return;

        try {
            const res = await fetch(`${API_URL}/tedarikci-iade/kalem-ekle`, {
                method: 'POST',
                headers: {
                    'X-WP-Nonce': kasaAyar.nonce,
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    iade_id: activeIade.id,
                    sku: sku,
                    qty: 1
                })
            });
            const data = await res.json();
            if (data.success) {
                if (window.Toast) Toast.show('Ürün eklendi.', 'success');
                
                const index = activeIade.kalemler.findIndex(k => k.product_id === data.data.kalem.product_id && k.variation_id === data.data.kalem.variation_id);
                if (index > -1) {
                    activeIade.kalemler[index] = data.data.kalem;
                } else {
                    activeIade.kalemler.push(data.data.kalem);
                }

                activeIade.toplam_cesit = data.data.toplam_cesit;
                activeIade.toplam_adet = data.data.toplam_adet;

                renderIadeItemsTable();
                loadIadeList();
            } else {
                if (window.Toast) Toast.show(data.data.message || 'Ürün bulunamadı.', 'error');
            }
        } catch (error) {
            console.error(error);
        }
    }

    async function removeIadeItem(kalemId) {
        if (!activeIade) return;

        try {
            const res = await fetch(`${API_URL}/tedarikci-iade/kalem-sil`, {
                method: 'POST',
                headers: {
                    'X-WP-Nonce': kasaAyar.nonce,
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    iade_id: activeIade.id,
                    kalem_id: kalemId
                })
            });
            const data = await res.json();
            if (data.success) {
                activeIade.kalemler = activeIade.kalemler.filter(k => k.id !== kalemId);
                activeIade.toplam_cesit = data.data.toplam_cesit;
                activeIade.toplam_adet = data.data.toplam_adet;

                renderIadeItemsTable();
                loadIadeList();
            }
        } catch (error) {
            console.error(error);
        }
    }

    async function updateIadeItemQty(kalemId, qty) {
        if (!activeIade) return;

        try {
            const res = await fetch(`${API_URL}/tedarikci-iade/kalem-miktar-guncelle`, {
                method: 'POST',
                headers: {
                    'X-WP-Nonce': kasaAyar.nonce,
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    iade_id: activeIade.id,
                    kalem_id: kalemId,
                    qty: qty
                })
            });
            const data = await res.json();
            if (data.success) {
                const index = activeIade.kalemler.findIndex(k => k.id === kalemId);
                if (index > -1) {
                    activeIade.kalemler[index] = data.data.kalem;
                }
                activeIade.toplam_cesit = data.data.toplam_cesit;
                activeIade.toplam_adet = data.data.toplam_adet;

                renderIadeItemsTable();
                loadIadeList();
            }
        } catch (error) {
            console.error(error);
        }
    }

    function renderIadeItemsTable() {
        const tbody = document.getElementById('t-iade-kalemler');
        const ozet = document.getElementById('t-iade-ozet-label');
        if (!tbody || !activeIade) return;

        if (activeIade.kalemler.length === 0) {
            tbody.innerHTML = '<tr><td colspan="4" class="sevk-empty">Henüz ürün eklenmedi. Barkod okutun.</td></tr>';
            ozet.innerText = '0 çeşit ürün, 0 adet toplam';
            return;
        }

        tbody.innerHTML = activeIade.kalemler.map(item => `
            <tr>
                <td><strong>${item.urun_adi}</strong></td>
                <td>${item.sku}</td>
                <td>
                    <input type="number" class="hk-input t-iade-qty-input" data-id="${item.id}" value="${parseFloat(item.adet)}" min="0.0001" step="any" style="width:70px; text-align:center; padding: 4px;">
                </td>
                <td style="text-align:center;">
                    <button type="button" class="sevk-btn secondary small t-iade-sil-btn" data-id="${item.id}" style="padding: 2px 6px;">✕</button>
                </td>
            </tr>
        `).join('');

        ozet.innerText = `${activeIade.toplam_cesit} çeşit ürün, ${parseFloat(activeIade.toplam_adet)} adet toplam`;

        tbody.querySelectorAll('.t-iade-qty-input').forEach(input => {
            input.addEventListener('change', function() {
                const kalemId = parseInt(this.getAttribute('data-id'));
                const val = parseFloat(this.value);
                if (val > 0) {
                    updateIadeItemQty(kalemId, val);
                }
            });
        });

        tbody.querySelectorAll('.t-iade-sil-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const kalemId = parseInt(this.getAttribute('data-id'));
                removeIadeItem(kalemId);
            });
        });
    }

    async function deleteDraftIade() {
        if (!activeIade) return;
        if (!confirm('Bu iade taslağını silmek istediğinize emin misiniz?')) return;

        try {
            const res = await fetch(`${API_URL}/tedarikci-iade/sil`, {
                method: 'POST',
                headers: {
                    'X-WP-Nonce': kasaAyar.nonce,
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    iade_id: activeIade.id
                })
            });
            const data = await res.json();
            if (data.success) {
                if (window.Toast) Toast.show('İade taslağı silindi.', 'success');
                closeIadeDetay();
                loadIadeList();
            }
        } catch (error) {
            console.error(error);
        }
    }

    async function confirmIade() {
        if (!activeIade) return;

        const sebep = document.getElementById('t-iade-sebep').value.trim();
        const not = document.getElementById('t-iade-not').value.trim();

        if (!sebep) {
            if (window.Toast) Toast.show('Lütfen bir iade sebebi belirtin.', 'error');
            return;
        }

        if (activeIade.kalemler.length === 0) {
            if (window.Toast) Toast.show('Boş iade onaylanamaz.', 'error');
            return;
        }

        const btn = document.getElementById('t-iade-onayla-btn');
        btn.disabled = true;
        btn.textContent = 'İşleniyor...';

        try {
            const res = await fetch(`${API_URL}/tedarikci-iade/onayla`, {
                method: 'POST',
                headers: {
                    'X-WP-Nonce': kasaAyar.nonce,
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    iade_id: activeIade.id,
                    iade_sebep: sebep,
                    not: not
                })
            });
            const data = await res.json();
            if (data.success) {
                if (window.Toast) Toast.show('İade başarıyla tamamlandı ve stoktan düşüldü.', 'success');
                const iadeId = activeIade.id;
                
                await loadIadeList();
                await showIade(iadeId);

                if (activeIade) {
                    printIadeFis(activeIade);
                }
            } else {
                if (window.Toast) Toast.show(data.data.message || 'Onaylanırken hata oluştu.', 'error');
            }
        } catch (error) {
            console.error(error);
        } finally {
            btn.disabled = false;
            btn.textContent = 'Onayla ve Stoktan Düş';
        }
    }

    function printIadeFis(iade) {
        if (!iade) return;

        document.getElementById('iade-paket-no').innerText = iade.iade_no;
        document.getElementById('iade-paket-tarih').innerText = iade.created_at ? new Date(iade.created_at.replace(' ', 'T')).toLocaleString('tr-TR') : new Date().toLocaleString('tr-TR');
        document.getElementById('iade-paket-supplier').innerText = iade.supplier_name;
        document.getElementById('iade-paket-cesit').innerText = iade.toplam_cesit;
        document.getElementById('iade-paket-adet').innerText = parseFloat(iade.toplam_adet);
        
        const sebepEl = document.getElementById('iade-paket-sebep');
        const sebepRow = document.getElementById('iade-paket-sebep-satiri');
        if (iade.iade_sebep) {
            sebepEl.innerText = iade.iade_sebep;
            sebepRow.style.display = 'block';
        } else {
            sebepRow.style.display = 'none';
        }

        const notEl = document.getElementById('iade-paket-not');
        const notRow = document.getElementById('iade-paket-not-satiri');
        if (iade.not) {
            notEl.innerText = iade.not;
            notRow.style.display = 'block';
        } else {
            notRow.style.display = 'none';
        }

        const tbody = document.getElementById('iade-paket-urunler-body');
        tbody.innerHTML = iade.kalemler.map(item => `
            <tr style="border-bottom:1px dashed #ccc;">
                <td style="padding:4px 0; line-height: 1.2;">
                    <strong>${item.urun_adi}</strong><br>
                    <span style="font-size: 9px; color: #444;">${item.sku}</span>
                </td>
                <td style="text-align:right; padding:4px 0; font-weight: bold; vertical-align: middle;">x ${parseFloat(item.adet)}</td>
            </tr>
        `).join('');

        if (typeof JsBarcode === "function") {
            try {
                JsBarcode("#iade-paket-barkod", iade.iade_no, {
                    format: "CODE128",
                    width: 2,
                    height: 40,
                    displayValue: false,
                    margin: 0,
                    background: "#ffffff",
                    lineColor: "#000000"
                });
                document.getElementById('iade-paket-barkod-text').innerText = iade.iade_no;
            } catch (e) {
                console.error("Barkod üretilemedi:", e);
            }
        }

        if (window.HizliKasa && window.HizliKasa.PrintManager) {
            window.HizliKasa.PrintManager.print('iade-paket-fis');
        } else {
            console.error("PrintManager bulunamadı!");
        }
    }

    function closeIadeDetay() {
        activeIade = null;
        document.getElementById('t-iade-adim-1').style.display = 'block';
        document.getElementById('t-iade-adim-2').style.display = 'none';
        document.getElementById('t-iade-detay-panel').style.display = 'none';
        
        const select = document.getElementById('t-iade-yeni-tedarikci');
        if (select) select.value = '';
    }

    return {
        init: init
    };

})();

document.addEventListener('DOMContentLoaded', () => {
    MalkabulManager.init();
});
