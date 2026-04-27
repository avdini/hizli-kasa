/**
 * Hızlı Kasa - Barkod Çizim ve Yazdırma Motoru
 * 
 * 50x35mm termal etiket tasarımı ve JsBarcode entegrasyonu.
 */

(function(HK) {
    'use strict';

    HK.BarcodeRenderer = {
        
        state: {
            currentProduct: null,
            itemsToPrint: [] // {data: {}, qty: 0}
        },

        init: function() {
            var self = this;
            
            // Modal Kapatma (Vazgeç)
            var iptalBtn = document.getElementById('barkod-iptal');
            if (iptalBtn) {
                iptalBtn.onclick = function() {
                    document.getElementById('barkod-yazdir-modal').style.display = 'none';
                };
            }

            // Yazdır Butonu (Yazıcıya Gönder)
            var yazdirBtn = document.getElementById('barkod-onay-yazdir');
            if (yazdirBtn) {
                yazdirBtn.onclick = function() {
                    self.processAndPrint();
                };
            }

            this.initialized = true;
            console.log("BarcodeRenderer (re)initialized");
        },

        /**
         * Tek bir ürün/varyant için modalı açar.
         */
        openSingleModal: function(product) {
            console.log("Opening Single Modal", product);
            this.state.currentProduct = product;
            var container = document.getElementById('barkod-urun-listesi-konteynir');
            var modal = document.getElementById('barkod-yazdir-modal');
            var filterContainer = document.getElementById('barkod-modal-filtreler');
            
            if (!container || !modal) {
                console.error("Barkod modalı bulunamadı!");
                return;
            }

            // Tekli barkodda filtreleri gizle ve modalı küçült
            if (filterContainer) filterContainer.style.display = 'none';
            modal.querySelector('.modal-icerik').classList.remove('barkod-modal-genis');

            document.getElementById('barkod-modal-baslik').innerText = 'Barkod Yazdır';
            
            container.innerHTML = `
                <div class="barkod-item-row" data-id="${product.id}" data-vid="${product.variation_id || 0}">
                    <div class="item-info">
                        <span class="item-name">${product.name}</span>
                        <span class="item-sku">${product.sku || 'SKU YOK'}</span>
                    </div>
                    <div class="item-qty-input">
                        <label>Adet:</label>
                        <input type="number" class="print-qty" value="1" min="1">
                    </div>
                </div>
            `;

            document.getElementById('barkod-yazdir-modal').style.display = 'flex';
        },

        /**
         * Parent ürün ve tüm varyasyonları için modalı açar.
         */
        openBulkModal: function(parentProduct) {
            console.log("Opening Bulk Modal", parentProduct);
            this.state.currentProduct = parentProduct;
            this.state.filters = {
                hideEmptyStock: true
            };

            var container = document.getElementById('barkod-urun-listesi-konteynir');
            var modal = document.getElementById('barkod-yazdir-modal');
            if (!container || !modal) return;

            // Toplu barkodda modalı genişlet
            modal.querySelector('.modal-icerik').classList.add('barkod-modal-genis');

            document.getElementById('barkod-modal-baslik').innerText = 'Toplu Barkod Çıkart';
            
            // Filtreleri hazırla ve göster
            this.renderBulkFilters();
            
            // Listeyi ilk kez bas
            this.applyFilters();

            document.getElementById('barkod-yazdir-modal').style.display = 'flex';
        },

        /**
         * Dinamik filtreleri oluşturur.
         */
        renderBulkFilters: function() {
            var self = this;
            var variations = this.state.currentProduct.variations || [];
            var filterContainer = document.getElementById('barkod-modal-filtreler');
            
            if (!filterContainer) return;
            filterContainer.style.display = 'flex';
            
            // Mevcut filtreleri sıfırla veya koru (Multi-select için [] kullanacağız)
            if (!this.state.filters.attributes) this.state.filters.attributes = {};

            // 1. Stoğu Olmayanlar Filtresi (Sabit)
            var html = `
                <div class="filtre-grup">
                    <label>Stok Durumu</label>
                    <label class="stok-filtre-toggle">
                        <input type="checkbox" id="filter-stock-toggle" ${this.state.filters.hideEmptyStock ? 'checked' : ''}>
                        Stoğu Olmayanları Gizle
                    </label>
                </div>
            `;
            
            // 2. Dinamik Öznitelik Filtreleri (Renk, Beden vb.)
            var attrKeys = {}; // {color: Set(['Mavi', 'Yeşil']), size: Set(['42', '48'])}
            
            variations.forEach(v => {
                if (v.attributes) {
                    Object.keys(v.attributes).forEach(key => {
                        if (!attrKeys[key]) attrKeys[key] = new Set();
                        if (v.attributes[key]) {
                            attrKeys[key].add(v.attributes[key]);
                        }
                    });
                }
            });
            
            Object.keys(attrKeys).forEach(key => {
                var values = Array.from(attrKeys[key]).sort();
                if (values.length > 1) {
                    var label = key.charAt(0).toUpperCase() + key.slice(1);
                    if (key.includes('renk') || key.includes('color')) label = 'Renk';
                    if (key.includes('beden') || key.includes('size')) label = 'Beden';
                    if (key.includes('numara')) label = 'Numara';

                    if (!this.state.filters.attributes[key]) this.state.filters.attributes[key] = [];
                    
                    var selected = this.state.filters.attributes[key];
                    var summary = selected.length > 0 ? selected.join(', ') : 'Tümü';

                    html += `
                        <div class="filtre-grup" data-attr-group="${key}">
                            <label>${label}</label>
                            <div class="multi-select-trigger" onclick="event.stopPropagation(); this.parentElement.classList.toggle('is-open')">
                                <span>${summary}</span>
                            </div>
                            <div class="attr-checkbox-list" data-attr="${key}" onclick="event.stopPropagation()">
                                ${values.map(val => `
                                    <label>
                                        <input type="checkbox" value="${val}" ${this.state.filters.attributes[key].includes(val) ? 'checked' : ''}>
                                        ${val}
                                    </label>
                                `).join('')}
                            </div>
                        </div>
                    `;
                }
            });
            
            filterContainer.innerHTML = html;
            
            // Dışarı tıklayınca kapat
            document.onclick = function() {
                document.querySelectorAll('.filtre-grup.is-open').forEach(el => el.classList.remove('is-open'));
            };

            // Event Listeners
            var stockToggle = document.getElementById('filter-stock-toggle');
            if (stockToggle) {
                stockToggle.onchange = function() {
                    self.state.filters.hideEmptyStock = this.checked;
                    self.applyFilters();
                };
            }
            
            filterContainer.querySelectorAll('.attr-checkbox-list').forEach(list => {
                var attr = list.dataset.attr;
                var triggerSpan = list.parentElement.querySelector('.multi-select-trigger span');

                list.querySelectorAll('input').forEach(input => {
                    input.onchange = function() {
                        var val = this.value;
                        var currentFilters = self.state.filters.attributes[attr] || [];
                        
                        if (this.checked) {
                            if (!currentFilters.includes(val)) currentFilters.push(val);
                        } else {
                            self.state.filters.attributes[attr] = currentFilters.filter(v => v !== val);
                        }
                        
                        // Özeti güncelle
                        var newSelected = self.state.filters.attributes[attr];
                        triggerSpan.innerText = newSelected.length > 0 ? newSelected.join(', ') : 'Tümü';

                        self.applyFilters();
                    };
                });
            });
        },

        /**
         * Filtreleri uygular ve listeyi günceller.
         */
        applyFilters: function() {
            var variations = this.state.currentProduct.variations || [];
            var filters = this.state.filters;
            
            var filtered = variations.filter(v => {
                // 1. Stok filtresi
                if (filters.hideEmptyStock && parseFloat(v.warehouse_stock) <= 0) {
                    return false;
                }
                
                // 2. Öznitelik filtreleri (Multi-select)
                var attrMatch = true;
                if (filters.attributes) {
                    Object.keys(filters.attributes).forEach(fKey => {
                        var selectedValues = filters.attributes[fKey];
                        if (selectedValues && selectedValues.length > 0) {
                            // Eğer bu öznitelik için seçim yapılmışsa, varyasyonun değeri bunlardan biri olmalı
                            if (!v.attributes || !selectedValues.includes(v.attributes[fKey])) {
                                attrMatch = false;
                            }
                        }
                    });
                }
                
                return attrMatch;
            });
            
            this.renderBulkList(filtered);
        },

        /**
         * Filtrelenmiş listeyi HTML olarak basar.
         */
        renderBulkList: function(variations) {
            var container = document.getElementById('barkod-urun-listesi-konteynir');
            var parentProduct = this.state.currentProduct;
            
            if (variations.length === 0) {
                container.innerHTML = '<p style="text-align:center; padding:20px; color:var(--hk-text-muted);">Filtrelere uygun varyasyon bulunamadı.</p>';
                return;
            }
            
            var html = '';
            variations.forEach(v => {
                html += `
                    <div class="barkod-item-row" data-id="${parentProduct.id}" data-vid="${v.id}">
                        <div class="item-info">
                            <span class="item-name">${v.name}</span>
                            <span class="item-sku">${v.sku || 'SKU YOK'} | Stok: ${v.warehouse_stock}</span>
                        </div>
                        <div class="item-qty-input">
                            <label>Adet:</label>
                            <input type="number" class="print-qty" value="${Math.max(0, Math.ceil(v.warehouse_stock))}" min="0">
                        </div>
                    </div>
                `;
            });
            
            container.innerHTML = html;
        },

        /**
         * Seçilen adetleri toplayıp API'den detaylı verileri çeker ve yazdırır.
         */
        processAndPrint: async function() {
            var self = this;
            var rows = document.querySelectorAll('.barkod-item-row');
            var btn = document.getElementById('barkod-onay-yazdir');
            
            var requests = [];
            rows.forEach(row => {
                var qty = parseInt(row.querySelector('.print-qty').value);
                if (qty > 0) {
                    requests.push({
                        product_id: row.dataset.id,
                        variation_id: row.dataset.vid,
                        qty: qty
                    });
                }
            });

            if (requests.length === 0) return alert("Lütfen en az 1 adet seçin.");

            btn.disabled = true;
            btn.innerText = 'Hazırlanıyor...';

            try {
                var printData = [];
                
                // Her bir kalem için detaylı etiket verisini çek
                for (var req of requests) {
                    var url = kasaAyar.rootApiUrl + 'hizli-kasa/v1/barcode/label-data?product_id=' + req.product_id + '&variation_id=' + req.variation_id;
                    var response = await fetch(url, { headers: { 'X-WP-Nonce': kasaAyar.nonce } });
                    var data = await response.json();
                    
                    if (data && !data.code) {
                        printData.push({
                            label: data,
                            qty: req.qty
                        });
                    }
                }

                this.renderPrintOutput(printData);

            } catch (e) {
                console.error(e);
                alert("Barkod verileri alınırken bir hata oluştu.");
            } finally {
                btn.disabled = false;
                btn.innerText = 'Yazıcıya Gönder';
                document.getElementById('barkod-yazdir-modal').style.display = 'none';
            }
        },

        /**
         * HTML şablonlarını oluşturur, JsBarcode ile barkodları çizer ve yazdırır.
         */
        renderPrintOutput: function(items) {
            // Gizli print alanı oluştur veya temizle
            var printArea = document.getElementById('hk-barcode-print-area');
            if (!printArea) {
                printArea = document.createElement('div');
                printArea.id = 'hk-barcode-print-area';
                document.body.appendChild(printArea);
            }
            printArea.innerHTML = '';

            items.forEach(item => {
                for (var i = 0; i < item.qty; i++) {
                    var labelHtml = this.getLabelTemplate(item.label);
                    printArea.insertAdjacentHTML('beforeend', labelHtml);
                    
                    // Son eklenen barkodu çiz
                    var lastLabel = printArea.lastElementChild;
                    var svg = lastLabel.querySelector('.barcode-svg');
                    var code = item.label.barcode_no;
                    
                    // Her zaman CODE128 kullan (Görsel bütünlük ve esneklik için)
                    var format = "CODE128";

                    // İçeriğe göre dinamik genişlik (Kutuya tam yayılması için 2.0 kullanıyoruz)
                    var barWidth = 2.0;

                    JsBarcode(svg, code, {
                        format: format,
                        width: barWidth,
                        height: 80,
                        displayValue: false,
                        margin: 0
                    });

                    // Barkodu kutuya tam sığdır (CSS ile birlikte çalışır)
                    svg.setAttribute("preserveAspectRatio", "none");
                }
            });

            // Yazdırma işlemini başlat
            setTimeout(() => {
                HK.PrintManager.print('barcode');
            }, 500);
        },

        /**
         * 50x35mm Etiket HTML Şablonu
         */
        getLabelTemplate: function(data) {
            var priceHtml = '';
            if (data.price_data.on_sale) {
                priceHtml = `
                    <div class="price-old">${data.price_data.regular_price}</div>
                    <div class="price-new">${data.price_data.sale_price}</div>
                `;
            } else {
                priceHtml = `<div class="price-single">${data.price_data.price}</div>`;
            }

            return `
                <div class="barcode-label">
                    <div class="label-header">
                        <div class="product-name">${data.product_name}</div>
                    </div>
                    <div class="label-body">
                        <div class="col-left">
                            <div class="model-no">Model: ${data.model_no}</div>
                            <div class="barcode-container">
                                <svg class="barcode-svg"></svg>
                            </div>
                            <div class="sku-text">SKU: ${data.barcode_no}</div>
                        </div>
                        <div class="col-right">
                            <div class="attributes">
                                <div class="attr-color">Renk: ${data.attributes.color || ''}</div>
                                <div class="attr-size">
                                    <span class="size-label">${data.attributes.label}:</span>
                                    <span class="size-val">${data.attributes.size || ''}</span>
                                </div>
                            </div>
                            <div class="price-section ${data.price_data.on_sale ? 'has-sale' : ''}">
                                ${priceHtml}
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }
    };

    // Sekme yüklendiğinde başlat
    document.addEventListener('hkTabLoaded', function(e) {
        if (e.detail.tab === 'urunler') {
            HK.BarcodeRenderer.init();
        }
    });

    // Sayfa ilk yüklendiğinde eğer elementler varsa başlat
    if (document.getElementById('barkod-yazdir-modal')) {
        HK.BarcodeRenderer.init();
    }

})(window.HizliKasa = window.HizliKasa || {});
