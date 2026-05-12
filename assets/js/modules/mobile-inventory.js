/**
 * Hızlı Kasa - Mobil Envanter Modülü
 */

(function() {
    'use strict';

    const MobileApp = {
        state: {
            html5QrCode: null,
            isScanning: false,
            isStartingScanner: false,
            cameraDevices: [],
            selectedCameraId: null,
            torchOn: false,
            canTorch: false,
            preferredZoom: null,
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
            const switchCameraBtn = document.getElementById('switch-camera-btn');
            const refocusCameraBtn = document.getElementById('refocus-camera-btn');
            const torchCameraBtn = document.getElementById('torch-camera-btn');
            const exitLogo = document.getElementById('app-exit-logo');
            
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

            toggleScannerBtn.addEventListener('click', async () => {
                await this.toggleScanner();
            });

            switchCameraBtn?.addEventListener('click', async () => this.switchCamera());
            refocusCameraBtn?.addEventListener('click', async () => this.refocusCamera());
            torchCameraBtn?.addEventListener('click', async () => this.toggleTorch());

            exitLogo.addEventListener('click', () => {
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

        toggleScanner: async function() {
            const wrapper = document.getElementById('scanner-wrapper');
            const btn = document.getElementById('toggle-scanner-btn');

            if (this.state.isStartingScanner) return;

            if (this.state.isScanning) {
                await this.stopScanner();
                wrapper.classList.add('collapsed');
                btn.innerHTML = '<span class="icon">📷</span> Barkod Tara';
            } else {
                wrapper.classList.remove('collapsed');
                btn.innerHTML = '<span class="icon">⌛</span> Kamera Açılıyor';
                const started = await this.startScanner();
                if (started) {
                    btn.innerHTML = '<span class="icon">✕</span> Taramayı Kapat';
                } else {
                    wrapper.classList.add('collapsed');
                    btn.innerHTML = '<span class="icon">📷</span> Barkod Tara';
                }
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

        startScanner: async function() {
            this.state.isStartingScanner = true;

            if (!this.state.html5QrCode) {
                this.state.html5QrCode = new Html5Qrcode("reader");
            }

            const config = {
                fps: 12,
                qrbox: function(viewfinderWidth, viewfinderHeight) {
                    const width = Math.min(420, Math.floor(viewfinderWidth * 0.88));
                    const height = Math.min(190, Math.max(120, Math.floor(width * 0.45)));
                    return {
                        width: Math.min(width, viewfinderWidth - 20),
                        height: Math.min(height, viewfinderHeight - 20)
                    };
                },
                aspectRatio: 1.7777778,
                disableFlip: true,
                experimentalFeatures: {
                    useBarCodeDetectorIfSupported: true
                }
            };

            try {
                const cameraConfig = await this.getPreferredCameraConfig();
                this.state.preferredZoom = null;
                this.state.canTorch = false;
                this.state.torchOn = false;
                await this.state.html5QrCode.start(
                    cameraConfig,
                    config,
                    (decodedText) => {
                    console.log(`Code scanned: ${decodedText}`);
                    const input = document.getElementById('mobile-search-input');
                    input.value = decodedText;
                    document.getElementById('clear-search').style.display = 'block';
                    this.searchProducts(decodedText);

                    this.closeScannerAfterScan();

                    if (navigator.vibrate) navigator.vibrate(100);
                    },
                    (errorMessage) => {
                        // console.log(errorMessage);
                    }
                );

                this.state.isScanning = true;
                await this.applyCameraTuning();
                this.updateCameraStatus();
                return true;
            } catch (err) {
                console.error("Camera error", err);
                alert("Kamera başlatılamadı. İzinleri kontrol edin.");
                this.state.isScanning = false;
                return false;
            } finally {
                this.state.isStartingScanner = false;
            }
        },

        stopScanner: async function() {
            if (this.state.html5QrCode && this.state.isScanning) {
                this.state.isScanning = false;
                this.state.torchOn = false;
                this.state.canTorch = false;
                this.state.preferredZoom = null;
                this.updateCameraStatus();
                await this.state.html5QrCode.stop().catch(err => console.error("Stop error", err));
            }
        },

        closeScannerAfterScan: async function() {
            if (!this.state.isScanning) return;

            await this.stopScanner();
            document.getElementById('scanner-wrapper')?.classList.add('collapsed');
            const btn = document.getElementById('toggle-scanner-btn');
            if (btn) {
                btn.innerHTML = '<span class="icon">📷</span> Barkod Tara';
            }
        },

        getPreferredCameraConfig: async function() {
            const fallbackConfig = {
                facingMode: { ideal: "environment" },
                width: { ideal: 1280 },
                height: { ideal: 720 }
            };

            try {
                const cameras = await Html5Qrcode.getCameras();
                this.state.cameraDevices = Array.isArray(cameras) ? cameras : [];

                if (!this.state.cameraDevices.length) {
                    return fallbackConfig;
                }

                const selectedStillExists = this.state.selectedCameraId &&
                    this.state.cameraDevices.some(camera => camera.id === this.state.selectedCameraId);

                if (!selectedStillExists) {
                    this.state.selectedCameraId = this.getRankedCameras()[0]?.id || null;
                }

                if (!this.state.selectedCameraId) {
                    return fallbackConfig;
                }

                return {
                    deviceId: { exact: this.state.selectedCameraId },
                    facingMode: { ideal: "environment" },
                    width: { ideal: 1280 },
                    height: { ideal: 720 }
                };
            } catch (err) {
                console.warn("Camera list unavailable, using environment camera.", err);
                return fallbackConfig;
            }
        },

        getRankedCameras: function() {
            return [...this.state.cameraDevices].sort((a, b) => {
                return this.scoreCamera(b) - this.scoreCamera(a);
            });
        },

        scoreCamera: function(camera) {
            const label = (camera.label || '').toLocaleLowerCase('tr-TR');
            let score = 0;

            if (/back|rear|environment|arka|dış/.test(label)) score += 100;
            if (/front|user|ön|selfie/.test(label)) score -= 120;
            if (/ultra|0\.5|0,5|wide|geniş|genis/.test(label)) score -= 80;
            if (/main|standard|normal|1x|1\.0|1,0/.test(label)) score += 45;
            if (/tele|macro/.test(label)) score -= 20;

            return score;
        },

        getRunningVideoTrack: function() {
            if (this.state.html5QrCode?.getRunningTrack) {
                return this.state.html5QrCode.getRunningTrack();
            }

            const video = document.querySelector('#reader video');
            return video?.srcObject?.getVideoTracks?.()[0] || null;
        },

        applyCameraTuning: async function(showToastOnSuccess = false) {
            const track = this.getRunningVideoTrack();
            if (!track || !track.getCapabilities || !track.applyConstraints) return;

            const capabilities = track.getCapabilities();
            const advanced = [];
            this.state.preferredZoom = null;

            if (Array.isArray(capabilities.focusMode) && capabilities.focusMode.includes('continuous')) {
                advanced.push({ focusMode: 'continuous' });
            }

            if (capabilities.zoom && Number.isFinite(capabilities.zoom.min) && Number.isFinite(capabilities.zoom.max)) {
                const targetZoom = Math.min(capabilities.zoom.max, Math.max(capabilities.zoom.min, 1.5));
                this.state.preferredZoom = targetZoom;
                advanced.push({ zoom: targetZoom });
            }

            this.state.canTorch = !!capabilities.torch;
            if (capabilities.torch) {
                advanced.push({ torch: this.state.torchOn });
            }

            if (advanced.length) {
                try {
                    await track.applyConstraints({ advanced });
                    if (showToastOnSuccess) {
                        this.showToast("Kamera netlik ayarı yenilendi.");
                    }
                } catch (err) {
                    console.warn("Camera tuning not supported on this device.", err);
                }
            }

            this.updateCameraStatus();
        },

        refocusCamera: async function() {
            if (!this.state.isScanning) return;
            await this.applyCameraTuning(true);
        },

        toggleTorch: async function() {
            if (!this.state.isScanning || !this.state.canTorch) {
                this.showToast("Bu cihazda flaş kontrolü desteklenmiyor.", "error");
                return;
            }

            this.state.torchOn = !this.state.torchOn;
            await this.applyCameraTuning();
            this.updateCameraStatus();
        },

        switchCamera: async function() {
            const rankedCameras = this.getRankedCameras();
            if (rankedCameras.length < 2 || !this.state.isScanning) {
                this.showToast("Başka kamera bulunamadı.", "error");
                return;
            }

            const currentIndex = rankedCameras.findIndex(camera => camera.id === this.state.selectedCameraId);
            const nextIndex = currentIndex >= 0 ? (currentIndex + 1) % rankedCameras.length : 0;
            this.state.selectedCameraId = rankedCameras[nextIndex].id;

            await this.stopScanner();
            await this.startScanner();
        },

        updateCameraStatus: function() {
            const statusEl = document.getElementById('camera-status');
            const torchBtn = document.getElementById('torch-camera-btn');
            const switchBtn = document.getElementById('switch-camera-btn');

            if (torchBtn) {
                torchBtn.disabled = !this.state.canTorch || !this.state.isScanning;
                torchBtn.classList.toggle('active', this.state.torchOn);
            }

            if (switchBtn) {
                switchBtn.disabled = this.state.cameraDevices.length < 2 || !this.state.isScanning;
            }

            if (!statusEl) return;

            if (!this.state.isScanning) {
                statusEl.innerText = "Kamera kapalı";
                return;
            }

            const selected = this.state.cameraDevices.find(camera => camera.id === this.state.selectedCameraId);
            const label = selected?.label ? selected.label.replace(/\s*\([^)]+\)\s*$/, '') : 'Arka kamera';
            const zoomLabel = this.state.preferredZoom ? ` · ${this.state.preferredZoom.toFixed(1)}x` : '';
            statusEl.innerText = `${label}${zoomLabel}`;
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
            const loader = document.getElementById('preview-loader');
            
            if (modal && img) {
                // Hazırlık: Eski resmi ve durumları tamamen sıfırla
                img.style.opacity = '0';
                img.src = ''; // Önceki resmin hayaletini yok et
                loader.style.display = 'block';
                
                // Yüksek çözünürlüklü URL
                const fullSrc = src.replace(/-(\d+)x(\d+)\.(jpg|jpeg|png|webp|gif)$/, '.$3');
                
                img.onload = function() {
                    loader.style.display = 'none';
                    img.style.opacity = '1';
                };

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
