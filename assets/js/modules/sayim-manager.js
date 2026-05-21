/**
 * Hızlı Kasa - Depo Sayım Yöneticisi Modülü
 *
 * @package HizliKasa
 */

(function(HK) {
    'use strict';

    HK.SayimManager = {
        state: {
            activeSession: null,
            items: [],
            isCountingMode: false,
            searchTimer: null
        },

        init: function() {
            var self = this;
            if (this.initialized) return;
            
            // Cache DOM elements
            this.cacheElements();
            if (!this.elements.toggleBtn) return; // Tab-urunler view loading wait
            
            this.initialized = true;
            this.bindEvents();
            
            // If the view warehouse changes, reload the active session if we are in counting mode
            document.addEventListener('hkViewDepoChanged', function() {
                if (self.state.isCountingMode) {
                    self.loadActiveSession();
                }
            });
        },

        cacheElements: function() {
            this.elements = {
                toggleBtn: document.getElementById('btn-stok-sayimi-toggle'),
                listePaneli: document.getElementById('terminal-liste-paneli'),
                sayimPaneli: document.getElementById('terminal-sayim-paneli'),
                baslangicEkrani: document.getElementById('sayim-baslangic-ekrani'),
                aktifEkrani: document.getElementById('sayim-aktif-ekrani'),
                btnSayimBaslat: document.getElementById('btn-sayim-baslat'),
                barkodInput: document.getElementById('sayim-barkod-input'),
                personelAdi: document.getElementById('sayim-personel-adi'),
                tarihLabel: document.getElementById('sayim-tarihi'),
                soundCheckbox: document.getElementById('chk-sayim-ses'),
                kalemSayisiLbl: document.getElementById('sayim-kalem-sayisi-lbl'),
                itemsBody: document.getElementById('sayim-items-body'),
                btnSayimIptal: document.getElementById('btn-sayim-iptal'),
                btnSayimBitir: document.getElementById('btn-sayim-bitir'),
                urunEkleInput: document.getElementById('sayim-urun-ekle-input'),
                urunEkleResults: document.getElementById('sayim-urun-ekle-results'),
                bitirModal: document.getElementById('sayim-bitir-modal'),
                bitirVazgec: document.getElementById('sayim-bitir-vazgec'),
                bitirOnayla: document.getElementById('sayim-bitir-onayla')
            };
        },

        bindEvents: function() {
            var self = this;

            // Toggle Counting Mode button
            this.elements.toggleBtn.addEventListener('click', function() {
                self.toggleCountingMode();
            });

            // Start new counting session
            this.elements.btnSayimBaslat.addEventListener('click', function() {
                self.startSession();
            });

            // Cancel session
            this.elements.btnSayimIptal.addEventListener('click', function() {
                self.discardSession();
            });

            // Complete session modal triggers
            this.elements.btnSayimBitir.addEventListener('click', function() {
                self.elements.bitirModal.style.display = 'flex';
            });

            this.elements.bitirVazgec.addEventListener('click', function() {
                self.elements.bitirModal.style.display = 'none';
            });

            this.elements.bitirOnayla.addEventListener('click', function() {
                self.completeSession();
            });

            // Barcode scanning keypress (Enter)
            this.elements.barkodInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    self.scanBarcode();
                }
            });

            // Automatically focus barcode input when clicking anywhere inside sol-kolon/aktif-ekran to keep scanner focused,
            // except when other input is active.
            this.elements.aktifEkrani.addEventListener('click', function(e) {
                if (e.target !== self.elements.urunEkleInput && 
                    e.target.tagName !== 'INPUT' && 
                    e.target.tagName !== 'BUTTON' &&
                    !e.target.classList.contains('btn-sayim-adet') &&
                    !e.target.classList.contains('sayim-adet-input')) {
                    self.elements.barkodInput.focus();
                }
            });

            // Manual item search & add input
            this.elements.urunEkleInput.addEventListener('input', function() {
                clearTimeout(self.state.searchTimer);
                var query = this.value.trim();
                if (query.length < 2) {
                    self.elements.urunEkleResults.style.display = 'none';
                    return;
                }
                self.state.searchTimer = setTimeout(function() {
                    self.searchProducts(query);
                }, 300);
            });

            // Hide results when clicking outside
            document.addEventListener('click', function(e) {
                if (e.target !== self.elements.urunEkleInput && e.target !== self.elements.urunEkleResults) {
                    self.elements.urunEkleResults.style.display = 'none';
                }
            });

            // Table quantity adjustments and delete delegation
            this.elements.itemsBody.addEventListener('click', function(e) {
                var target = e.target;
                var row = target.closest('tr');
                if (!row) return;

                var id = parseInt(row.dataset.id);
                var item = self.state.items.find(function(it) { return it.id === id; });
                if (!item) return;

                if (target.classList.contains('btn-qty-plus')) {
                    self.updateItemQty(item, parseFloat(item.counted_qty) + 1);
                } else if (target.classList.contains('btn-qty-minus')) {
                    var newQty = Math.max(0, parseFloat(item.counted_qty) - 1);
                    self.updateItemQty(item, newQty);
                } else if (target.classList.contains('btn-sayim-kalem-sil') || target.parentNode.classList.contains('btn-sayim-kalem-sil')) {
                    if (confirm('Bu ürünü sayım listesinden çıkartmak istediğinize emin misiniz?')) {
                        self.deleteItem(item);
                    }
                }
            });

            // Handle manually entered input change
            this.elements.itemsBody.addEventListener('change', function(e) {
                var target = e.target;
                if (target.classList.contains('sayim-adet-input')) {
                    var row = target.closest('tr');
                    if (!row) return;

                    var id = parseInt(row.dataset.id);
                    var item = self.state.items.find(function(it) { return it.id === id; });
                    if (!item) return;

                    var newQty = parseFloat(target.value);
                    if (isNaN(newQty) || newQty < 0) {
                        newQty = 0;
                    }
                    self.updateItemQty(item, newQty);
                }
            });
            
            // Also let's listen to Enter key inside quantity inputs to trigger blur/change immediately
            this.elements.itemsBody.addEventListener('keypress', function(e) {
                if (e.target.classList.contains('sayim-adet-input') && e.key === 'Enter') {
                    e.target.blur();
                }
            });
        },

        toggleCountingMode: function() {
            var self = this;
            this.state.isCountingMode = !this.state.isCountingMode;

            if (this.state.isCountingMode) {
                // Show sayim panel, hide products list panel
                this.elements.listePaneli.style.display = 'none';
                this.elements.sayimPaneli.style.display = 'block';

                // Change toggle button text
                var iconSpan = this.elements.toggleBtn.querySelector('.ikon');
                var textSpan = this.elements.toggleBtn.querySelector('.btn-text');
                if (iconSpan) iconSpan.textContent = '📦';
                if (textSpan) textSpan.textContent = 'Ürün Listesi';
                
                // Hide header's sorting and product search elements
                var sortingBox = document.querySelector('.siralama-kutusu');
                var searchBox = document.querySelector('.arama-kutusu');
                if (sortingBox) sortingBox.style.display = 'none';
                if (searchBox) searchBox.style.display = 'none';

                // Load active session
                this.loadActiveSession();
            } else {
                // Hide sayim panel, show products list panel
                this.elements.listePaneli.style.display = 'block';
                this.elements.sayimPaneli.style.display = 'none';

                // Change toggle button text
                var iconSpan = this.elements.toggleBtn.querySelector('.ikon');
                var textSpan = this.elements.toggleBtn.querySelector('.btn-text');
                if (iconSpan) iconSpan.textContent = '📋';
                if (textSpan) textSpan.textContent = 'Depo Sayımı';
                
                // Show header's sorting and product search elements
                var sortingBox = document.querySelector('.siralama-kutusu');
                var searchBox = document.querySelector('.arama-kutusu');
                if (sortingBox) sortingBox.style.display = '';
                if (searchBox) searchBox.style.display = '';

                // Reload product list to show any stock changes
                if (HK.StockTerminal) {
                    HK.StockTerminal.loadProducts();
                }
            }
        },

        loadActiveSession: async function() {
            var self = this;
            var depoId = HK.DepoManager ? HK.DepoManager.getViewDepo() : null;

            if (!depoId) {
                if (HK.UIRenderer) HK.UIRenderer.showToast('Lütfen önce bir depo seçin.', 'error');
                return;
            }

            try {
                var response = await fetch(`${kasaAyar.rootApiUrl}hizli-kasa/v1/sayim/active?depo_id=${depoId}&_=${Date.now()}`, {
                    headers: { 'X-WP-Nonce': kasaAyar.nonce }
                });

                if (!response.ok) throw new Error('API Error');
                var data = await response.json();

                if (data.active) {
                    self.state.activeSession = data.session;
                    self.state.items = data.items || [];
                    
                    self.elements.baslangicEkrani.style.display = 'none';
                    self.elements.aktifEkrani.style.display = 'grid';

                    // Update UI labels
                    self.elements.personelAdi.textContent = data.session.created_by;
                    
                    // Format date beautifully
                    var dateObj = new Date(data.session.created_at);
                    var formattedDate = dateObj.toLocaleDateString('tr-TR') + ' ' + dateObj.toLocaleTimeString('tr-TR', {hour: '2-digit', minute:'2-digit'});
                    self.elements.tarihLabel.textContent = formattedDate;

                    self.renderItems();
                    self.elements.barkodInput.focus();
                } else {
                    self.state.activeSession = null;
                    self.state.items = [];
                    self.elements.baslangicEkrani.style.display = 'flex';
                    self.elements.aktifEkrani.style.display = 'none';
                }
            } catch(e) {
                console.error("loadActiveSession error", e);
                if (HK.UIRenderer) HK.UIRenderer.showToast('Aktif sayım seansı yüklenemedi!', 'error');
            }
        },

        startSession: async function() {
            var self = this;
            var depoId = HK.DepoManager ? HK.DepoManager.getViewDepo() : null;

            if (!depoId) {
                if (HK.UIRenderer) HK.UIRenderer.showToast('Depo seçilmedi!', 'error');
                return;
            }

            try {
                var response = await fetch(`${kasaAyar.rootApiUrl}hizli-kasa/v1/sayim/start`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': kasaAyar.nonce
                    },
                    body: JSON.stringify({ depo_id: depoId })
                });

                var data = await response.json();

                if (response.ok && data.success) {
                    if (HK.UIRenderer) HK.UIRenderer.showToast('Sayım seansı başlatıldı.', 'success');
                    self.loadActiveSession();
                } else {
                    var msg = data.message || 'Sayım başlatılamadı!';
                    if (HK.UIRenderer) HK.UIRenderer.showToast(msg, 'error');
                }
            } catch(e) {
                console.error("startSession error", e);
                if (HK.UIRenderer) HK.UIRenderer.showToast('Sayım başlatılırken bağlantı hatası oluştu.', 'error');
            }
        },

        scanBarcode: async function() {
            var self = this;
            var barcode = this.elements.barkodInput.value.trim();
            this.elements.barkodInput.value = ''; // Reset immediately for fast subsequent scans

            if (!barcode || !this.state.activeSession) return;

            try {
                var response = await fetch(`${kasaAyar.rootApiUrl}hizli-kasa/v1/sayim/scan-item`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': kasaAyar.nonce
                    },
                    body: JSON.stringify({
                        session_id: self.state.activeSession.id,
                        barcode: barcode
                    })
                });

                var data = await response.json();

                if (response.ok && data.success) {
                    self.playFeedbackSound('success');
                    
                    // Add or update the item in the list
                    var index = self.state.items.findIndex(function(it) {
                        return it.product_id === data.item.product_id && it.variation_id === data.item.variation_id;
                    });

                    if (index > -1) {
                        self.state.items[index] = data.item;
                    } else {
                        self.state.items.push(data.item);
                    }

                    // Render with flash effect on the scanned item
                    self.renderItems(data.item.id);
                } else {
                    self.playFeedbackSound('error');
                    var msg = data.message || 'Barkod bulunamadı!';
                    if (HK.UIRenderer) HK.UIRenderer.showToast(msg, 'error');
                }
            } catch(e) {
                console.error("scanBarcode error", e);
                self.playFeedbackSound('error');
                if (HK.UIRenderer) HK.UIRenderer.showToast('Barkod sorgulanırken hata oluştu.', 'error');
            }

            self.elements.barkodInput.focus();
        },

        updateItemQty: async function(item, newQty) {
            var self = this;
            if (!this.state.activeSession) return;

            try {
                var response = await fetch(`${kasaAyar.rootApiUrl}hizli-kasa/v1/sayim/update-item-qty`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': kasaAyar.nonce
                    },
                    body: JSON.stringify({
                        session_id: self.state.activeSession.id,
                        product_id: item.product_id,
                        variation_id: item.variation_id,
                        qty: newQty
                    })
                });

                var data = await response.json();

                if (response.ok && data.success) {
                    var index = self.state.items.findIndex(function(it) { return it.id === item.id; });
                    if (index > -1) {
                        self.state.items[index] = data.item;
                    }
                    self.renderItems(data.item.id);
                } else {
                    var msg = data.message || 'Miktar güncellenemedi!';
                    if (HK.UIRenderer) HK.UIRenderer.showToast(msg, 'error');
                    self.renderItems(); // Reset input state back
                }
            } catch(e) {
                console.error("updateItemQty error", e);
                if (HK.UIRenderer) HK.UIRenderer.showToast('Bağlantı hatası.', 'error');
                self.renderItems(); // Reset input state back
            }
        },

        deleteItem: async function(item) {
            var self = this;
            if (!this.state.activeSession) return;

            try {
                var response = await fetch(`${kasaAyar.rootApiUrl}hizli-kasa/v1/sayim/delete-item`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': kasaAyar.nonce
                    },
                    body: JSON.stringify({
                        session_id: self.state.activeSession.id,
                        product_id: item.product_id,
                        variation_id: item.variation_id
                    })
                });

                var data = await response.json();

                if (response.ok && data.success) {
                    if (HK.UIRenderer) HK.UIRenderer.showToast('Ürün sayımdan kaldırıldı.', 'success');
                    self.state.items = self.state.items.filter(function(it) { return it.id !== item.id; });
                    self.renderItems();
                } else {
                    var msg = data.message || 'Ürün silinemedi!';
                    if (HK.UIRenderer) HK.UIRenderer.showToast(msg, 'error');
                }
            } catch(e) {
                console.error("deleteItem error", e);
                if (HK.UIRenderer) HK.UIRenderer.showToast('Bağlantı hatası.', 'error');
            }
        },

        discardSession: async function() {
            var self = this;
            if (!this.state.activeSession) return;

            if (!confirm('Bu sayım seansını İPTAL etmek istediğinize emin misiniz? Sayılmış tüm veriler arşivlenecek ancak stoklar güncellenmeyecektir!')) {
                return;
            }

            try {
                var response = await fetch(`${kasaAyar.rootApiUrl}hizli-kasa/v1/sayim/discard`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': kasaAyar.nonce
                    },
                    body: JSON.stringify({
                        session_id: self.state.activeSession.id
                    })
                });

                var data = await response.json();

                if (response.ok && data.success) {
                    if (HK.UIRenderer) HK.UIRenderer.showToast('Sayım oturumu iptal edildi.', 'success');
                    self.loadActiveSession();
                } else {
                    var msg = data.message || 'Sayım iptal edilemedi!';
                    if (HK.UIRenderer) HK.UIRenderer.showToast(msg, 'error');
                }
            } catch(e) {
                console.error("discardSession error", e);
                if (HK.UIRenderer) HK.UIRenderer.showToast('Bağlantı hatası.', 'error');
            }
        },

        completeSession: async function() {
            var self = this;
            if (!this.state.activeSession) return;

            var updateTypeEl = document.querySelector('input[name="sayim_update_type"]:checked');
            var updateType = updateTypeEl ? updateTypeEl.value : 'partial';

            self.elements.bitirModal.style.display = 'none';

            if (updateType === 'full' && !confirm('DİKKAT! Tam Eşitleme yöntemini seçtiniz. Bu depodaki listede yer almayan TÜM diğer ürünlerin stokları 0 yapılacaktır. Devam etmek istiyor musunuz?')) {
                return;
            }

            try {
                var response = await fetch(`${kasaAyar.rootApiUrl}hizli-kasa/v1/sayim/complete`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': kasaAyar.nonce
                    },
                    body: JSON.stringify({
                        session_id: self.state.activeSession.id,
                        update_type: updateType
                    })
                });

                var data = await response.json();

                if (response.ok && data.success) {
                    if (HK.UIRenderer) HK.UIRenderer.showToast('Sayım seansı başarıyla tamamlandı ve envanter eşitlendi.', 'success');
                    self.loadActiveSession();
                } else {
                    var msg = data.message || 'Sayım tamamlanamadı!';
                    if (HK.UIRenderer) HK.UIRenderer.showToast(msg, 'error');
                }
            } catch(e) {
                console.error("completeSession error", e);
                if (HK.UIRenderer) HK.UIRenderer.showToast('Bağlantı hatası.', 'error');
            }
        },

        searchProducts: async function(query) {
            var self = this;
            var depoId = HK.DepoManager ? HK.DepoManager.getViewDepo() : null;

            if (!depoId) return;

            try {
                var url = `${kasaAyar.rootApiUrl}hizli-kasa/v1/terminal/products?limit=10&depo_id=${depoId}&s=${encodeURIComponent(query)}&_=${Date.now()}`;
                var response = await fetch(url, {
                    headers: { 'X-WP-Nonce': kasaAyar.nonce }
                });

                if (!response.ok) throw new Error('API Error');
                var data = await response.json();

                var products = data.products || [];

                if (products.length === 0) {
                    self.elements.urunEkleResults.innerHTML = '<div style="padding:10px; color:var(--hk-text-muted); font-size:12px; text-align:center;">Sonuç bulunamadı</div>';
                    self.elements.urunEkleResults.style.display = 'block';
                    return;
                }

                var html = '';
                products.forEach(function(p) {
                    var sku = p.sku ? `(SKU: ${p.sku})` : '';
                    
                    // Simple product
                    if (p.type !== 'variable') {
                        html += `
                            <div class="sayim-arama-row" data-barcode="${p.sku || p.id}">
                                <span class="arama-urun-ad">${self.escapeHtml(p.title)}</span>
                                <span class="arama-urun-detay">${sku} - Mevcut Stok: ${p.stock_quantity || 0}</span>
                            </div>
                        `;
                    } else if (p.variations) {
                        // Display variations inline
                        p.variations.forEach(function(v) {
                            var vSku = v.sku ? `(SKU: ${v.sku})` : '';
                            var attrDesc = [];
                            if (v.attributes) {
                                for (var key in v.attributes) {
                                    attrDesc.push(v.attributes[key]);
                                }
                            }
                            var attrText = attrDesc.join(', ');
                            html += `
                                <div class="sayim-arama-row" data-barcode="${v.sku || v.id}">
                                    <span class="arama-urun-ad">${self.escapeHtml(p.title)} <span class="var-badge">${self.escapeHtml(attrText)}</span></span>
                                    <span class="arama-urun-detay">${vSku} - Mevcut Stok: ${v.stock_quantity || 0}</span>
                                </div>
                            `;
                        });
                    }
                });

                self.elements.urunEkleResults.innerHTML = html;
                self.elements.urunEkleResults.style.display = 'block';

                // Tıklama event'i
                var rows = self.elements.urunEkleResults.querySelectorAll('.sayim-arama-row');
                rows.forEach(function(row) {
                    row.addEventListener('click', function() {
                        var barcode = this.dataset.barcode;
                        self.elements.barkodInput.value = barcode;
                        self.elements.urunEkleResults.style.display = 'none';
                        self.elements.urunEkleInput.value = '';
                        self.scanBarcode();
                    });
                });

            } catch(e) {
                console.error("searchProducts error", e);
            }
        },

        playFeedbackSound: function(type) {
            if (this.elements.soundCheckbox && !this.elements.soundCheckbox.checked) return;

            try {
                var AudioContextClass = window.AudioContext || window.webkitAudioContext;
                if (!AudioContextClass) return;
                
                var ctx = new AudioContextClass();
                var osc = ctx.createOscillator();
                var gain = ctx.createGain();
                
                osc.connect(gain);
                gain.connect(ctx.destination);
                
                if (type === 'success') {
                    osc.frequency.setValueAtTime(800, ctx.currentTime);
                    gain.gain.setValueAtTime(0.1, ctx.currentTime);
                    gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.1);
                    osc.start(ctx.currentTime);
                    osc.stop(ctx.currentTime + 0.1);
                } else {
                    osc.type = 'sawtooth';
                    osc.frequency.setValueAtTime(220, ctx.currentTime);
                    gain.gain.setValueAtTime(0.15, ctx.currentTime);
                    gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.3);
                    osc.start(ctx.currentTime);
                    osc.stop(ctx.currentTime + 0.3);
                }
            } catch(e) {
                console.warn("AudioContext error", e);
            }
        },

        renderItems: function(flashItemId) {
            var self = this;
            var tbody = this.elements.itemsBody;
            var label = this.elements.kalemSayisiLbl;
            
            if (!tbody) return;
            
            if (this.state.items.length === 0) {
                tbody.innerHTML = '<tr><td colspan="7" class="rapor-empty-td">Henüz ürün sayılmadı. Barkod okutun veya manuel ekleyin.</td></tr>';
                if (label) label.textContent = '0';
                return;
            }
            
            if (label) label.textContent = this.state.items.length.toString();
            
            // Sort items by updated_at descending
            this.state.items.sort(function(a, b) {
                return new Date(b.updated_at) - new Date(a.updated_at);
            });
            
            var html = '';
            this.state.items.forEach(function(item) {
                var isFlash = flashItemId && item.id === flashItemId ? 'row-flash' : '';
                var imgHtml = item.image ? `<img src="${item.image}" class="variation-img" style="margin: 0 auto; display: block;">` : `<span class="ikon" style="font-size:24px; display:block; text-align:center;">📦</span>`;
                
                var nameHtml = self.escapeHtml(item.name);
                if (item.attributes) {
                    nameHtml += ` <span class="var-badge">${self.escapeHtml(item.attributes)}</span>`;
                }
                
                var diffVal = parseFloat(item.diff);
                var diffClass = diffVal > 0 ? 'diff-plus' : (diffVal < 0 ? 'diff-minus' : 'diff-zero');
                var diffSign = diffVal > 0 ? '+' : '';
                
                html += `
                    <tr class="${isFlash}" data-id="${item.id}" data-prod-id="${item.product_id}" data-var-id="${item.variation_id}">
                        <td style="text-align: center; padding: 5px;">${imgHtml}</td>
                        <td>${nameHtml}</td>
                        <td>${self.escapeHtml(item.sku)}</td>
                        <td style="text-align: center; font-weight: 500;">${parseFloat(item.system_qty)}</td>
                        <td>
                            <div class="sayim-adet-wrapper">
                                <button class="btn-sayim-adet btn-qty-minus">-</button>
                                <input type="number" class="sayim-adet-input" value="${parseFloat(item.counted_qty)}" step="1" min="0">
                                <button class="btn-sayim-adet btn-qty-plus">+</button>
                            </div>
                        </td>
                        <td style="text-align: center;" class="item-diff-cell ${diffClass}">${diffSign}${diffVal}</td>
                        <td style="text-align: center;">
                            <button class="btn-sayim-kalem-sil" title="Sil">🗑️</button>
                        </td>
                    </tr>
                `;
            });
            
            tbody.innerHTML = html;
        },

        escapeHtml: function(text) {
            if (!text) return '';
            return text
                .toString()
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }
    };

    // Auto init when tab is loaded or ready
    document.addEventListener('hkTabLoaded', function(e) {
        if (e.detail.tab === 'urunler') {
            HK.SayimManager.init();
        }
    });

    if (document.getElementById('btn-stok-sayimi-toggle')) {
        HK.SayimManager.init();
    }

})(window.HizliKasa = window.HizliKasa || {});
