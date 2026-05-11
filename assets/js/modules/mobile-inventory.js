/**
 * Hızlı Kasa - Mobil Envanter Modülü
 */

(function() {
    'use strict';

    const MobileApp = {
        state: {
            html5QrCode: null,
            isScanning: false,
            searchTimer: null,
            isLoading: false,
            aktifDepoId: kasaAyar.aktifDepo || 0,
            depolar: kasaAyar.depolar || []
        },

        init: function() {
            this.updateDepoDisplay();
            this.bindEvents();
            console.log('Mobile Inventory initialized');
        },

        updateDepoDisplay: function() {
            const currentDepo = this.state.depolar.find(d => d.id == this.state.aktifDepoId);
            const nameEl = document.getElementById('current-depo-name');
            if (nameEl) {
                nameEl.innerText = currentDepo ? currentDepo.name : 'Depo Seçin';
            }
        },

        bindEvents: function() {
            const searchInput = document.getElementById('mobile-search-input');
            const clearBtn = document.getElementById('clear-search');
            const toggleScannerBtn = document.getElementById('toggle-scanner-btn');
            const backBtn = document.getElementById('back-to-pos');
            
            const changeDepoBtn = document.getElementById('header-depo-selector');
            const closeDepoModal = document.getElementById('close-depo-modal');

            changeDepoBtn.addEventListener('click', () => this.openDepoModal());
            closeDepoModal.addEventListener('click', () => document.getElementById('depo-select-modal').style.display = 'none');

            searchInput.addEventListener('input', (e) => {
                const val = e.target.value.trim();
                clearBtn.style.display = val.length > 0 ? 'block' : 'none';
                
                clearTimeout(this.state.searchTimer);
                this.state.searchTimer = setTimeout(() => {
                    if (val.length >= 2) {
                        this.searchProducts(val);
                    } else if (val.length === 0) {
                        this.renderInitialState();
                    }
                }, 400);
            });

            clearBtn.addEventListener('click', () => {
                searchInput.value = '';
                clearBtn.style.display = 'none';
                this.renderInitialState();
                searchInput.focus();
            });

            toggleScannerBtn.addEventListener('click', () => {
                this.toggleScanner();
            });

            backBtn.addEventListener('click', () => {
                window.location.href = window.location.pathname;
            });

            // Resim Önizleme Olayları (Event Delegation)
            document.getElementById('results-container').addEventListener('click', (e) => {
                if (e.target.classList.contains('card-img') || e.target.classList.contains('card-img-mini')) {
                    e.stopPropagation(); // Parent accordion tetiklenmesin
                    this.openImagePreview(e.target.src);
                }
            });

            document.getElementById('image-preview-modal').addEventListener('click', () => {
                this.closeImagePreview();
            });
        },

        toggleScanner: function() {
            const wrapper = document.getElementById('scanner-wrapper');
            const btn = document.getElementById('toggle-scanner-btn');

            if (this.state.isScanning) {
                this.stopScanner();
                wrapper.classList.add('collapsed');
                btn.innerHTML = '<span class="icon">📷</span> Barkod Tara';
            } else {
                this.startScanner();
                wrapper.classList.remove('collapsed');
                btn.innerHTML = '<span class="icon">✕</span> Taramayı Kapat';
            }
        },

        openDepoModal: function() {
            const modal = document.getElementById('depo-select-modal');
            const listWrapper = document.getElementById('depo-list-wrapper');
            
            let html = '';
            this.state.depolar.forEach(d => {
                html += `<div class="depo-option ${d.id == this.state.aktifDepoId ? 'active' : ''}" data-id="${d.id}">${d.name}</div>`;
            });
            
            listWrapper.innerHTML = html;
            modal.style.display = 'flex';

            // Click listener for options
            listWrapper.querySelectorAll('.depo-option').forEach(opt => {
                opt.addEventListener('click', () => {
                    const id = opt.dataset.id;
                    this.switchDepo(id);
                    modal.style.display = 'none';
                });
            });
        },

        switchDepo: function(id) {
            this.state.aktifDepoId = id;
            this.updateDepoDisplay();
            
            // Eğer arama yapılmışsa sonuçları yenile
            const searchVal = document.getElementById('mobile-search-input').value.trim();
            if (searchVal.length >= 2) {
                this.searchProducts(searchVal);
            }
            
            this.showToast("Depo değiştirildi.");
        },

        startScanner: function() {
            if (!this.state.html5QrCode) {
                this.state.html5QrCode = new Html5Qrcode("reader");
            }

            const config = { fps: 15, qrbox: { width: 250, height: 180 } };

            this.state.html5QrCode.start(
                { facingMode: "environment" }, 
                config, 
                (decodedText) => {
                    console.log(`Code scanned: ${decodedText}`);
                    document.getElementById('mobile-search-input').value = decodedText;
                    document.getElementById('clear-search').style.display = 'block';
                    this.searchProducts(decodedText);
                    this.toggleScanner(); // Başarılı tarama sonrası kapat
                    
                    // Vibrate feedback if supported
                    if (navigator.vibrate) navigator.vibrate(100);
                },
                (errorMessage) => {
                    // console.log(errorMessage);
                }
            ).catch((err) => {
                console.error("Camera error", err);
                alert("Kamera başlatılamadı. İzinleri kontrol edin.");
            });

            this.state.isScanning = true;
        },

        stopScanner: function() {
            if (this.state.html5QrCode) {
                this.state.html5QrCode.stop().then(() => {
                    this.state.isScanning = false;
                });
            }
        },

        searchProducts: async function(query) {
            const container = document.getElementById('results-container');
            const loader = document.getElementById('app-loader');
            
            loader.style.display = 'flex';

            try {
                // Mevcut terminal API'sini kullanıyoruz + aktif depo
                const url = `${kasaAyar.rootApiUrl}hizli-kasa/v1/terminal/products?s=${encodeURIComponent(query)}&limit=15&depo_id=${this.state.aktifDepoId}`;
                const response = await fetch(url, {
                    headers: { 'X-WP-Nonce': kasaAyar.nonce }
                });

                if (!response.ok) throw new Error("Sunucu hatası");

                const data = await response.json();
                this.renderResults(data.products || []);
                document.getElementById('result-count').innerText = `${data.products.length} Ürün bulundu`;

            } catch (err) {
                console.error(err);
                this.showToast("Ürünler yüklenirken hata oluştu", "error");
            } finally {
                loader.style.display = 'none';
            }
        },

        renderResults: function(products) {
            const container = document.getElementById('results-container');
            
            if (products.length === 0) {
                container.innerHTML = `
                    <div class="initial-state">
                        <div class="welcome-icon">🔍</div>
                        <h2>Sonuç Bulunamadı</h2>
                        <p>Farklı bir arama terimi deneyin.</p>
                    </div>
                `;
                return;
            }

            // Tek bir ürün mü bulundu? (Auto-expand için)
            const isSingleResult = products.length === 1;

            let html = '';
            products.forEach(p => {
                if (p.is_variable) {
                    // Parent Kartı
                    html += this.createParentCardHtml(p, isSingleResult);
                    
                    // Varyasyon Grubu
                    if (p.variations && p.variations.length > 0) {
                        html += `<div id="vars-of-${p.id}" class="variation-group" style="display: ${isSingleResult ? 'block' : 'none'}">`;
                        p.variations.forEach(v => {
                            html += this.createProductCardHtml(v, true);
                        });
                        html += `</div>`;
                    }
                } else {
                    // Basit Ürün
                    html += this.createProductCardHtml(p);
                }
            });

            container.innerHTML = html;
            this.bindVariationToggles();
        },

        createParentCardHtml: function(p, isOpen) {
            const img = (p.images && p.images[0]) ? p.images[0].src : '';
            const varCount = p.variations ? p.variations.length : 0;

            return `
                <div class="mobile-urun-kart parent-card ${isOpen ? 'is-open' : ''}" data-target="vars-of-${p.id}">
                    <div class="card-main">
                        <img src="${img}" class="card-img" alt="">
                        <div class="card-info">
                            <div class="card-name">${p.name}</div>
                            <div class="card-sku">${p.sku || 'KARIŞIK SKU'}</div>
                            <div class="var-summary">${varCount} Varyasyon Mevcut</div>
                        </div>
                        <div class="expand-chevron">▼</div>
                    </div>
                </div>
            `;
        },

        createProductCardHtml: function(p, isVariation = false) {
            const img = (p.images && p.images[0]) ? p.images[0].src : '';
            const allStocks = p.all_stocks || {};
            
            const currentQty = allStocks[this.state.aktifDepoId] || 0;
            const aktifDepoName = this.state.depolar.find(d => d.id == this.state.aktifDepoId)?.name || "Depo";
            
            let otherTotal = 0;
            Object.keys(allStocks).forEach(depoId => {
                if (depoId == this.state.aktifDepoId) return;
                otherTotal += parseFloat(allStocks[depoId] || 0);
            });

            if (isVariation) {
                // VARYASYON KARTI (Sağdan Stoklu)
                return `
                    <div class="mobile-urun-kart variation">
                        <div class="card-flex-wrapper">
                            <img src="${img}" class="card-img-mini" alt="">
                            <div class="card-info">
                                <div class="card-name">${p.name.replace(/.* - /, '')}</div>
                                <div class="card-sku">${p.sku || ''}</div>
                            </div>
                            <div class="card-side-stocks">
                                <div class="side-stock-item highlight" title="${aktifDepoName}">
                                    <span class="ss-val">${currentQty}</span>
                                    <span class="ss-label">DEP</span>
                                </div>
                                <div class="side-stock-item" title="Diğer Depolar">
                                    <span class="ss-val">${otherTotal}</span>
                                    <span class="ss-label">DİĞ</span>
                                </div>
                                <div class="side-stock-item site" title="Site Stoğu">
                                    <span class="ss-val">${p.stock_quantity || 0}</span>
                                    <span class="ss-label">WEB</span>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            }

            // BASİT ÜRÜN KARTI (Klasik Görünüm)
            return `
                <div class="mobile-urun-kart">
                    <div class="card-main">
                        <img src="${img}" class="card-img" alt="">
                        <div class="card-info">
                            <div class="card-name">${p.name} ${p.is_variable ? '<span class="var-badge">VARYASYONLU</span>' : ''}</div>
                            <div class="card-sku">${p.sku || 'SKU YOK'}</div>
                        </div>
                    </div>
                    <div class="stock-grid">
                        <div class="stock-box highlight full-width">
                            <span class="sb-label">${aktifDepoName}</span>
                            <span class="sb-val">${currentQty}</span>
                        </div>
                        <div class="stock-box">
                            <span class="sb-label">Diğer</span>
                            <span class="sb-val">${otherTotal}</span>
                        </div>
                        <div class="stock-box">
                            <span class="sb-label">Site</span>
                            <span class="sb-val">${p.stock_quantity || 0}</span>
                        </div>
                    </div>
                </div>
            `;
        },

        bindVariationToggles: function() {
            document.querySelectorAll('.parent-card').forEach(card => {
                card.addEventListener('click', () => {
                    const targetId = card.dataset.target;
                    const group = document.getElementById(targetId);
                    if (group) {
                        const isHidden = group.style.display === 'none';
                        group.style.display = isHidden ? 'block' : 'none';
                        card.classList.toggle('is-open', isHidden);
                    }
                });
            });
        },

        renderInitialState: function() {
            const container = document.getElementById('results-container');
            container.innerHTML = `
                <div class="initial-state">
                    <div class="welcome-icon">📦</div>
                    <h2>Envanter Aracı</h2>
                    <p>Ürün aramak için yukarıdaki kutuyu kullanın veya barkod taratın.</p>
                </div>
            `;
            document.getElementById('result-count').innerText = '0 Ürün bulundu';
        },

        showToast: function(msg, type = 'info') {
            const container = document.getElementById('mobile-toast-container');
            const toast = document.createElement('div');
            toast.className = `mobile-toast ${type}`;
            toast.innerText = msg;
            container.appendChild(toast);

            setTimeout(() => {
                toast.classList.add('fade-out');
                setTimeout(() => toast.remove(), 500);
            }, 3000);
        },

        openImagePreview: function(src) {
            const modal = document.getElementById('image-preview-modal');
            const img = document.getElementById('preview-img');
            if (modal && img) {
                // WordPress'in thumbnail eklerini temizleyip orijinal resme ulaşmaya çalışalım
                // Örn: image-150x150.jpg -> image.jpg
                const fullSrc = src.replace(/-(\d+)x(\d+)\.(jpg|jpeg|png|webp|gif)$/, '.$3');
                
                img.src = fullSrc;
                modal.style.display = 'flex';
            }
        },

        closeImagePreview: function() {
            const modal = document.getElementById('image-preview-modal');
            if (modal) {
                modal.style.display = 'none';
            }
        }
    };

    document.addEventListener('DOMContentLoaded', () => {
        MobileApp.init();
    });

})();
