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
            console.log("BarcodeRenderer init called");

            // Modal Kapatma
            var iptalBtn = document.getElementById('barkod-iptal');
            if (iptalBtn) {
                iptalBtn.onclick = function() {
                    var modal = document.getElementById('barkod-yazdir-modal');
                    if (modal) modal.style.display = 'none';
                };
            }

            // Yazdır Butonu
            var yazdirBtn = document.getElementById('barkod-onay-yazdir');
            if (yazdirBtn) {
                yazdirBtn.onclick = function() {
                    self.processAndPrint();
                };
            }

            this.initialized = true;
        },

        /**
         * Tek bir ürün/varyant için modalı açar.
         */
        openSingleModal: function(product) {
            this.init(); // Listener'ları tazele
            console.log("Opening Single Modal", product);
            this.state.currentProduct = product;
            var container = document.getElementById('barkod-urun-listesi-konteynir');
            var modal = document.getElementById('barkod-yazdir-modal');
            
            if (!container || !modal) {
                console.error("Barkod modalı bulunamadı!");
                return;
            }

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
         * Parent ürün ve tüm (stoktaki) varyasyonları için modalı açar.
         */
        openBulkModal: function(parentProduct) {
            this.init(); // Listener'ları tazele
            console.log("Opening Bulk Modal", parentProduct);
            this.state.currentProduct = parentProduct;
            var container = document.getElementById('barkod-urun-listesi-konteynir');
            var modal = document.getElementById('barkod-yazdir-modal');

            if (!container || !modal) {
                console.error("Barkod modalı bulunamadı!");
                return;
            }

            document.getElementById('barkod-modal-baslik').innerText = 'Toplu Barkod Çıkart';
            
            var html = '';
            // Sadece stoğu 0'dan büyük olanları ekle
            parentProduct.variations.forEach(v => {
                if (parseFloat(v.warehouse_stock) > 0) {
                    html += `
                        <div class="barkod-item-row" data-id="${parentProduct.id}" data-vid="${v.id}">
                            <div class="item-info">
                                <span class="item-name">${v.name}</span>
                                <span class="item-sku">${v.sku || 'SKU YOK'} | Stok: ${v.warehouse_stock}</span>
                            </div>
                            <div class="item-qty-input">
                                <label>Adet:</label>
                                <input type="number" class="print-qty" value="${Math.ceil(v.warehouse_stock)}" min="0">
                            </div>
                        </div>
                    `;
                }
            });

            if (html === '') {
                html = '<p style="text-align:center; padding:20px; color:var(--hk-text-muted);">Yazdırılacak stoklu varyasyon bulunamadı.</p>';
            }

            container.innerHTML = html;
            document.getElementById('barkod-yazdir-modal').style.display = 'flex';
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
                    
                    // EAN-8 mi yoksa CODE128 mi?
                    var format = "CODE128";
                    if (/^\d+$/.test(code) && (code.length === 7 || code.length === 8)) {
                        format = "EAN8";
                    }

                    JsBarcode(svg, code, {
                        format: format,
                        width: 1.5,
                        height: 40,
                        displayValue: false,
                        margin: 0
                    });
                }
            });

            // Yazdırma işlemini başlat
            setTimeout(() => {
                if (HK.PrintHelper) {
                    HK.PrintHelper.setPageStyle(
                        'size: 50mm 35mm; margin: 0;',
                        '#hk-barcode-print-area { display: block !important; visibility: visible !important; position: fixed !important; top: 0; left: 0; }'
                    );
                }
                window.print();
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

    // Sekme yüklendiğinde başlat (özellikle urunler sekmesi AJAX ile gelirse)
    document.addEventListener('hkTabLoaded', function(e) {
        if (e.detail.tab === 'urunler') {
            HK.BarcodeRenderer.init();
        }
    });

})(window.HizliKasa = window.HizliKasa || {});
