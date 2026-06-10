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
            cameraQualityProfile: null,
            isClosingScanner: false,
            isTransitioning: false,
            decodedInProgress: false,
            lastSearchQuery: '',
            searchTimer: null,
            isLoading: false,
            aktifDepoId: kasaAyar.aktifDepo || 0,
            depolar: kasaAyar.depolar || []
        },

        init: function() {
            this.loadSettings();
            this.restoreActiveDepo();
            this.updateDepoDisplay();
            this.bindEvents();
            console.log('Mobile Inventory initialized');
        },

        loadSettings: function() {
            try {
                const saved = localStorage.getItem('hk_mobile_camera_settings');
                if (saved) {
                    const settings = JSON.parse(saved);
                    this.state.selectedCameraId = settings.cameraId || null;
                    this.state.preferredZoom = settings.zoom || null;
                    
                    if (settings.quality) {
                        const profiles = {
                            high: { label: 'Hızlı', width: 1920, height: 1080, fps: 18, qrboxScale: 0.9, qrboxMaxWidth: 460, qrboxMinHeight: 130, zoom: 1.35 },
                            medium: { label: 'Dengeli', width: 1280, height: 720, fps: 12, qrboxScale: 0.88, qrboxMaxWidth: 420, qrboxMinHeight: 120, zoom: 1.5 },
                            low: { label: 'Hafif', width: 960, height: 540, fps: 8, qrboxScale: 0.84, qrboxMaxWidth: 360, qrboxMinHeight: 110, zoom: 1.65 }
                        };
                        this.state.cameraQualityProfile = { ...profiles[settings.quality], level: settings.quality };
                    }
                    console.log("Loaded camera settings:", settings);
                }
            } catch (err) {
                console.warn("Failed to load settings", err);
            }
        },

        restoreActiveDepo: function() {
            try {
                const savedId = localStorage.getItem('hk_mobile_active_depo');
                if (savedId) {
                    const id = parseInt(savedId, 10);
                    // Sadece yetkili listesinde varsa ve geçerli bir sayıysa geri yükle
                    if (id > 0 && this.state.depolar.some(d => d.id == id)) {
                        this.state.aktifDepoId = id;
                        console.log("Restored active depo from localStorage:", id);
                    }
                }
            } catch (e) {
                console.warn("Failed to restore active depo", e);
            }
        },

        saveSettings: function() {
            try {
                const settings = {
                    cameraId: this.state.selectedCameraId,
                    zoom: this.state.preferredZoom,
                    quality: this.state.cameraQualityProfile?.level || 'medium'
                };
                localStorage.setItem('hk_mobile_camera_settings', JSON.stringify(settings));
            } catch (err) {
                console.warn("Failed to save settings", err);
            }
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
            const refocusCameraBtn = document.getElementById('refocus-camera-btn');
            const switchCameraBtn = document.getElementById('switch-camera-btn');
            const zoomSlider = document.getElementById('zoom-slider');
            const qualityMenuBtn = document.getElementById('quality-menu-btn');
            const torchCameraBtn = document.getElementById('torch-camera-btn');
            const exitLogo = document.getElementById('app-exit-logo');
            
            const changeDepoBtn = document.getElementById('header-depo-selector');
            const closeDepoModal = document.getElementById('close-depo-modal');

            changeDepoBtn.addEventListener('click', () => this.openDepoModal());
            closeDepoModal.addEventListener('click', () => document.getElementById('depo-select-modal').style.display = 'none');

            searchInput.addEventListener('input', (e) => {
                const val = e.target.value.trim();
                clearBtn.style.display = val.length > 0 ? 'block' : 'none';

                if (val.length === 0) {
                    this.state.lastSearchQuery = '';
                    this.renderInitialState();
                }
            });

            searchInput.addEventListener('keydown', (e) => {
                if (e.key !== 'Enter') return;

                e.preventDefault();
                const val = e.target.value.trim();
                if (val.length < 2) {
                    this.showToast("Aramak için en az 2 karakter girin.", "error");
                    return;
                }

                this.searchProducts(val);
            });

            clearBtn.addEventListener('click', () => {
                searchInput.value = '';
                clearBtn.style.display = 'none';
                this.state.lastSearchQuery = '';
                this.renderInitialState();
                searchInput.focus();
            });

            toggleScannerBtn.addEventListener('click', async () => {
                await this.toggleScanner();
            });

            refocusCameraBtn?.addEventListener('click', async () => this.refocusCamera());
            switchCameraBtn?.addEventListener('click', async () => this.switchCamera());
            
            zoomSlider?.addEventListener('input', (e) => {
                const val = parseFloat(e.target.value);
                this.adjustZoomTo(val);
            });

            qualityMenuBtn?.addEventListener('click', (e) => {
                e.stopPropagation();
                document.getElementById('quality-menu').classList.toggle('show');
            });

            document.querySelectorAll('.quality-option').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    const quality = e.target.dataset.quality;
                    this.changeQualityTo(quality);
                    document.getElementById('quality-menu').classList.remove('show');
                });
            });

            document.addEventListener('click', () => {
                document.getElementById('quality-menu')?.classList.remove('show');
            });

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

            if (this.state.isTransitioning) {
                console.log("Scanner is busy, ignoring toggle request");
                return;
            }

            if (this.state.isScanning) {
                this.state.isTransitioning = true;
                try {
                    await this.stopScanner();
                    wrapper.classList.add('collapsed');
                    btn.innerHTML = '<span class="icon">📷</span> Barkod Tara';
                } finally {
                    this.state.isTransitioning = false;
                }
            } else {
                this.state.isTransitioning = true;
                wrapper.classList.remove('collapsed');
                btn.innerHTML = '<span class="icon">⌛</span> Kamera Açılıyor';
                
                try {
                    // Wait for DOM transition (collapsed -> expanded)
                    await new Promise(r => setTimeout(r, 450));
                    const started = await this.startScanner();
                    if (started) {
                        btn.innerHTML = '<span class="icon">✕</span> Taramayı Kapat';
                    } else {
                        wrapper.classList.add('collapsed');
                        btn.innerHTML = '<span class="icon">📷</span> Barkod Tara';
                    }
                } catch (err) {
                    console.error("Toggle Scanner Error:", err);
                    wrapper.classList.add('collapsed');
                    btn.innerHTML = '<span class="icon">📷</span> Barkod Tara';
                } finally {
                    this.state.isTransitioning = false;
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
            const newId = parseInt(id, 10) || id;
            this.state.aktifDepoId = newId;
            this.updateDepoDisplay();
            
            // Yerel sakla
            try {
                localStorage.setItem('hk_mobile_active_depo', newId);
            } catch(e) {}

            // Sunucuya gönder
            this.saveActiveDepoToServer(newId);

            // Eğer arama yapılmışsa sonuçları yenile
            const searchVal = this.state.lastSearchQuery;
            if (searchVal.length >= 2) {
                this.searchProducts(searchVal);
            }
            
            this.showToast("Depo değiştirildi.");
        },

        saveActiveDepoToServer: async function(depoId) {
            try {
                const response = await fetch(kasaAyar.rootApiUrl + 'hizli-kasa/v1/user/set-active-depo', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': kasaAyar.nonce
                    },
                    body: JSON.stringify({ depo_id: depoId })
                });

                if (response.status === 401 || response.status === 403) {
                    this.handleSessionExpired();
                }
            } catch(e) {
                console.warn("Could not save active depo to server", e);
            }
        },

        handleSessionExpired: function() {
            this.showToast("Oturum süresi doldu, sayfa yenileniyor...", "error");
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        },

        renderErrorState: function(message = "Bağlantı Hatası") {
            const container = document.getElementById('results-container');
            container.innerHTML = `
                <div class="initial-state error-state" style="padding: 40px 20px; text-align: center;">
                    <div class="welcome-icon" style="color: #ef4444; font-size: 64px; margin-bottom: 20px;">⚠️</div>
                    <h2 style="color: #ef4444; margin-bottom: 10px;">${message}</h2>
                    <p style="color: #94a3b8; margin-bottom: 20px;">Sunucuyla iletişim kurulamadı. Lütfen internet bağlantınızı kontrol edip tekrar deneyin.</p>
                    <button onclick="location.reload()" class="scanner-btn" style="background:#ef4444; border:none; color:white; padding:12px 24px; border-radius:8px; font-weight:bold; cursor:pointer;">Sayfayı Yenile</button>
                </div>
            `;
            const countEl = document.getElementById('result-count');
            if (countEl) countEl.innerText = 'Bağlantı hatası';
        },

        startScanner: async function() {
            console.log("Starting scanner...");
            try {
                // 1. Her şeyi durdur ve temizle
                await this.cleanupExistingScanner();
                
                // 2. Cihazları tara
                const cameraConfigs = await this.getCameraStartCandidates();
                
                this.state.preferredZoom = null;
                this.state.canTorch = false;
                this.state.torchOn = false;
                this.state.decodedInProgress = false;

                const config = this.getScannerConfig();
                
                // 3. Başlatmayı dene (Fallbacks içinde instance oluşturulacak)
                await this.startWithFallbacks(cameraConfigs, config);

                this.state.isScanning = true;
                await this.applyCameraTuning();
                this.updateCameraStatus();
                return true;
            } catch (err) {
                console.error("Camera start failed definitively:", err);
                const errorMsg = this.getCameraErrorMessage(err);
                alert(errorMsg);
                this.state.isScanning = false;
                return false;
            }
        },

        cleanupExistingScanner: async function() {
            console.log("Cleaning up existing scanner instance...");
            if (this.state.html5QrCode) {
                try {
                    const state = this.state.html5QrCode.getState();
                    if (state > 1) { // NOT_STARTED, SCANNING or PAUSED
                        await this.state.html5QrCode.stop().catch(() => {});
                    }
                    this.state.html5QrCode.clear();
                } catch (err) {
                    console.warn("Scanner cleanup warning:", err);
                }
                this.state.html5QrCode = null;
            }

            this.stopVideoTracks();
            
            const reader = document.getElementById('reader');
            if (reader) {
                reader.innerHTML = ''; 
            }
            
            await new Promise(r => setTimeout(r, 200));
        },

        stopScanner: async function() {
            if (!this.state.html5QrCode) {
                this.state.isScanning = false;
                this.stopVideoTracks();
                return;
            }

            console.log("Stopping scanner...");
            const runningTrack = this.getRunningVideoTrack();

            try {
                const state = this.state.html5QrCode.getState();
                if (state > 1) { // State 2, 3, 4 implies it might be running or attached
                    await this.state.html5QrCode.stop().catch(err => console.warn("Stop promise rejected:", err));
                }
                this.state.html5QrCode.clear();
            } catch (err) {
                console.error("Error during stopScanner:", err);
            } finally {
                this.state.html5QrCode = null;
                this.stopVideoTracks(runningTrack);
                this.state.isScanning = false;
                this.state.torchOn = false;
                this.state.canTorch = false;
                this.state.preferredZoom = null;
                this.state.decodedInProgress = false;
                this.updateCameraStatus();
                console.log("Scanner stopped and cleared.");
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

        getDeviceCameraProfile: function() {
            if (this.state.cameraQualityProfile) {
                return this.state.cameraQualityProfile;
            }

            const memory = navigator.deviceMemory || 4;
            const cores = navigator.hardwareConcurrency || 4;
            const screenMax = Math.max(window.screen?.width || 0, window.screen?.height || 0) * (window.devicePixelRatio || 1);
            let level = 'medium';

            if (memory >= 6 && cores >= 6 && screenMax >= 1600) {
                level = 'high';
            } else if (memory <= 2 || cores <= 2 || screenMax < 900) {
                level = 'low';
            }

            const profiles = {
                high: {
                    label: 'Hızlı',
                    width: 1920,
                    height: 1080,
                    fps: 18,
                    qrboxScale: 0.9,
                    qrboxMaxWidth: 460,
                    qrboxMinHeight: 130,
                    zoom: 1.35
                },
                medium: {
                    label: 'Dengeli',
                    width: 1280,
                    height: 720,
                    fps: 12,
                    qrboxScale: 0.88,
                    qrboxMaxWidth: 420,
                    qrboxMinHeight: 120,
                    zoom: 1.5
                },
                low: {
                    label: 'Hafif',
                    width: 960,
                    height: 540,
                    fps: 8,
                    qrboxScale: 0.84,
                    qrboxMaxWidth: 360,
                    qrboxMinHeight: 110,
                    zoom: 1.65
                }
            };

            this.state.cameraQualityProfile = { ...profiles[level], level };
            return this.state.cameraQualityProfile;
        },

        getScannerConfig: function() {
            const profile = this.getDeviceCameraProfile();

            return {
                fps: profile.fps,
                qrbox: function(viewfinderWidth, viewfinderHeight) {
                    const width = Math.min(profile.qrboxMaxWidth, Math.floor(viewfinderWidth * profile.qrboxScale));
                    const height = Math.min(190, Math.max(profile.qrboxMinHeight, Math.floor(width * 0.45)));
                    return {
                        width: Math.min(width, viewfinderWidth - 20),
                        height: Math.min(height, viewfinderHeight - 20)
                    };
                },
                aspectRatio: 1.7777778,
                disableFlip: true,
                experimentalFeatures: {
                    useBarCodeDetectorIfSupported: false // Bazı cihazlarda çakışmaya neden olabiliyor
                }
            };
        },

        getVideoConstraints: function(deviceId = null) {
            const profile = this.getDeviceCameraProfile();
            
            // Temel kısıtlar (Ideal değerler tarayıcının esnek davranmasını sağlar)
            const constraints = {
                facingMode: { ideal: "environment" },
                width: { ideal: profile.width },
                height: { ideal: profile.height },
                frameRate: { ideal: profile.fps }
            };

            if (deviceId) {
                // DeviceId varsa facingMode'u kaldırıp tam eşleşme deneyelim
                delete constraints.facingMode;
                constraints.deviceId = deviceId; 
            }

            return constraints;
        },

        getCameraStartCandidates: async function() {
            try {
                const cameras = await Html5Qrcode.getCameras();
                this.state.cameraDevices = Array.isArray(cameras) ? cameras : [];

                if (!this.state.cameraDevices.length) {
                    return [
                        this.getVideoConstraints(),
                        { facingMode: "environment" },
                        { facingMode: { ideal: "environment" } }
                    ];
                }

                const selectedStillExists = this.state.selectedCameraId &&
                    this.state.cameraDevices.some(camera => camera.id === this.state.selectedCameraId);

                if (!selectedStillExists) {
                    this.state.selectedCameraId = this.getRankedCameras()[0]?.id || null;
                }

                if (!this.state.selectedCameraId) {
                    return [
                        this.getVideoConstraints(),
                        { facingMode: "environment" },
                        { facingMode: { ideal: "environment" } }
                    ];
                }

                return [
                    this.getVideoConstraints(this.state.selectedCameraId),
                    this.state.selectedCameraId,
                    { deviceId: { exact: this.state.selectedCameraId } },
                    this.getVideoConstraints(),
                    { facingMode: "environment" },
                    { facingMode: { ideal: "environment" } }
                ];
            } catch (err) {
                console.warn("Camera list unavailable, using environment camera.", err);
                return [
                    this.getVideoConstraints(),
                    { facingMode: "environment" },
                    { facingMode: { ideal: "environment" } }
                ];
            }
        },

        startWithFallbacks: async function(cameraConfigs, scannerConfig) {
            let lastError = null;

            // 1. Aşamada aday konfigürasyonları dene
            for (const cameraConfig of cameraConfigs) {
                try {
                    // Her denemede yeni instance oluşturmak 'already under transition' hatasını en aza indirir
                    if (this.state.html5QrCode) {
                        try { this.state.html5QrCode.clear(); } catch(e){}
                    }
                    this.state.html5QrCode = new Html5Qrcode("reader");

                    console.log("Attempting camera start with config:", cameraConfig);
                    await this.state.html5QrCode.start(
                        cameraConfig,
                        scannerConfig,
                        (decodedText) => this.handleDecodedBarcode(decodedText),
                        () => {} 
                    );
                    console.log("Camera started successfully.");
                    return;
                } catch (err) {
                    lastError = err;
                    const errorStr = String(err);
                    console.warn("Camera start attempt failed:", errorStr);
                    
                    if (this.isConstraintError(err)) {
                        this.downgradeCameraProfile();
                    }
                    
                    // Eğer 'already under transition' hatası alırsak, instance'ı öldürüp daha uzun bekle
                    if (errorStr.includes("transition")) {
                        console.warn("Transition error detected, performing hard reset...");
                        if (this.state.html5QrCode) {
                            try { this.state.html5QrCode.clear(); } catch(e){}
                            this.state.html5QrCode = null;
                        }
                        await new Promise(r => setTimeout(r, 800));
                    } else {
                        await new Promise(r => setTimeout(r, 300));
                    }
                }
            }

            // 2. Aşamada (Hepsi başarısız olursa) en temel konfigürasyonu dene
            try {
                console.log("Attempting ultimate fallback...");
                if (this.state.html5QrCode) {
                    try { this.state.html5QrCode.clear(); } catch(e){}
                }
                this.state.html5QrCode = new Html5Qrcode("reader");

                await new Promise(r => setTimeout(r, 500)); 
                await this.state.html5QrCode.start(
                    { facingMode: "environment" },
                    { fps: 10, qrbox: scannerConfig.qrbox },
                    (decodedText) => this.handleDecodedBarcode(decodedText),
                    () => {}
                );
                return;
            } catch (err) {
                lastError = err;
            }

            throw lastError || new Error("Camera start failed after fallbacks");
        },

        handleDecodedBarcode: function(decodedText) {
            if (this.state.decodedInProgress) return;
            this.state.decodedInProgress = true;

            console.log(`Code scanned: ${decodedText}`);
            const input = document.getElementById('mobile-search-input');
            input.value = decodedText;
            this.state.lastSearchQuery = decodedText;
            document.getElementById('clear-search').style.display = 'block';
            this.searchProducts(decodedText);

            this.closeScannerAfterScan();

            if (navigator.vibrate) navigator.vibrate(100);
        },

        getCameraErrorMessage: function(err) {
            const name = err?.name || '';
            const message = String(err?.message || err || '');
            const combined = `${name} ${message}`.toLowerCase();

            if (combined.includes('notallowed') || combined.includes('permission') || combined.includes('denied')) {
                return "Kamera izni verilmemiş görünüyor. Tarayıcı izinlerini kontrol edin.";
            }

            if (combined.includes('notfound') || combined.includes('devicesnotfound')) {
                return "Bu cihazda kullanılabilir kamera bulunamadı.";
            }

            if (combined.includes('notreadable') || combined.includes('trackstarterror')) {
                return "Kamera başka bir uygulama veya sekme tarafından kullanılıyor olabilir.";
            }

            if (combined.includes('overconstrained') || combined.includes('constraint')) {
                return "Kamera bu ayarları desteklemedi. Daha uyumlu ayarlarla tekrar deneyin.";
            }

            if (combined.includes('abort') || combined.includes('interrupted')) {
                return "Kamera başlatma işlemi kesildi. Lütfen tekrar deneyin.";
            }

            if (combined.includes('typeerror')) {
                return "Kamera sürücüsü veya tarayıcı hatası (TypeError). Cihazınızı yeniden başlatmayı deneyebilirsiniz.";
            }

            return `Kamera başlatılamadı (${name}: ${message}). Sayfayı yenileyip tekrar deneyin.`;
        },

        isConstraintError: function(err) {
            const name = err?.name || '';
            const message = String(err?.message || err || '');
            const combined = `${name} ${message}`.toLowerCase();
            return combined.includes('overconstrained') || combined.includes('constraint');
        },

        downgradeCameraProfile: function() {
            const current = this.getDeviceCameraProfile();
            if (current.level === 'low') return;

            this.state.cameraQualityProfile = {
                level: 'low',
                label: 'Hafif',
                width: 960,
                height: 540,
                fps: 8,
                qrboxScale: 0.84,
                qrboxMaxWidth: 360,
                qrboxMinHeight: 110,
                zoom: 1.65
            };
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

        stopVideoTracks: function(extraTrack = null) {
            const tracks = [];
            if (extraTrack) tracks.push(extraTrack);

            document.querySelectorAll('#reader video').forEach(video => {
                const stream = video.srcObject;
                stream?.getTracks?.().forEach(track => tracks.push(track));
                video.pause?.();
                video.srcObject = null;
                video.removeAttribute('src');
            });

            tracks.forEach(track => {
                try {
                    if (track.readyState !== 'ended') {
                        track.stop();
                    }
                } catch (err) {
                    console.warn("Camera track could not be stopped.", err);
                }
            });
        },

        applyCameraTuning: async function(showToastOnSuccess = false) {
            const track = this.getRunningVideoTrack();
            if (!track || !track.getCapabilities || !track.applyConstraints) return;

            const profile = this.getDeviceCameraProfile();
            const capabilities = track.getCapabilities();
            const advanced = [];
            this.state.preferredZoom = null;

            if (Array.isArray(capabilities.focusMode) && capabilities.focusMode.includes('continuous')) {
                advanced.push({ focusMode: 'continuous' });
            }

            if (capabilities.zoom && Number.isFinite(capabilities.zoom.min) && Number.isFinite(capabilities.zoom.max)) {
                const targetZoom = Math.min(capabilities.zoom.max, Math.max(capabilities.zoom.min, profile.zoom));
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
            if (this.state.isTransitioning) return;
            
            const rankedCameras = this.getRankedCameras();
            if (rankedCameras.length < 2 || !this.state.isScanning) {
                this.showToast("Başka kamera bulunamadı.", "error");
                return;
            }

            const currentIndex = rankedCameras.findIndex(camera => camera.id === this.state.selectedCameraId);
            const nextIndex = currentIndex >= 0 ? (currentIndex + 1) % rankedCameras.length : 0;
            this.state.selectedCameraId = rankedCameras[nextIndex].id;
            this.saveSettings();

            this.state.isTransitioning = true;
            try {
                await this.stopScanner();
                await new Promise(r => setTimeout(r, 300));
                await this.startScanner();
            } finally {
                this.state.isTransitioning = false;
            }
        },

        adjustZoomTo: async function(value) {
            if (!this.state.isScanning) return;
            
            const track = this.getRunningVideoTrack();
            if (!track || !track.applyConstraints) return;
            
            this.state.preferredZoom = value;
            try {
                await track.applyConstraints({ advanced: [{ zoom: value }] });
                this.saveSettings();
                this.updateCameraStatus();
            } catch (err) {
                console.warn("Zoom adjustment failed", err);
            }
        },

        changeQualityTo: async function(level) {
            if (this.state.isTransitioning) return;
            if (this.state.cameraQualityProfile?.level === level) return;

            const profiles = {
                high: { label: 'Hızlı', width: 1920, height: 1080, fps: 18, qrboxScale: 0.9, qrboxMaxWidth: 460, qrboxMinHeight: 130, zoom: 1.35 },
                medium: { label: 'Dengeli', width: 1280, height: 720, fps: 12, qrboxScale: 0.88, qrboxMaxWidth: 420, qrboxMinHeight: 120, zoom: 1.5 },
                low: { label: 'Hafif', width: 960, height: 540, fps: 8, qrboxScale: 0.84, qrboxMaxWidth: 360, qrboxMinHeight: 110, zoom: 1.65 }
            };

            this.state.cameraQualityProfile = { ...profiles[level], level: level };
            this.saveSettings();
            this.showToast(`Kalite Değişiyor: ${level.toUpperCase()}`);

            this.state.isTransitioning = true;
            try {
                await this.stopScanner();
                await new Promise(r => setTimeout(r, 300));
                await this.startScanner();
            } finally {
                this.state.isTransitioning = false;
            }
        },

        updateCameraStatus: function() {
            const statusEl = document.getElementById('camera-status');
            const torchBtn = document.getElementById('torch-camera-btn');
            const zoomSlider = document.getElementById('zoom-slider');
            const qualityMenuBtn = document.getElementById('quality-menu-btn');

            if (torchBtn) {
                torchBtn.disabled = !this.state.canTorch || !this.state.isScanning;
                torchBtn.classList.toggle('active', this.state.torchOn);
            }

            // Sliderları güncelle
            if (zoomSlider && this.state.isScanning) {
                const track = this.getRunningVideoTrack();
                if (track?.getCapabilities) {
                    const cap = track.getCapabilities();
                    if (cap.zoom) {
                        zoomSlider.disabled = false;
                        zoomSlider.min = cap.zoom.min || 1;
                        zoomSlider.max = cap.zoom.max || 5;
                        zoomSlider.value = this.state.preferredZoom || cap.zoom.min || 1;
                    } else {
                        zoomSlider.disabled = true;
                    }
                }
            } else if (zoomSlider) {
                zoomSlider.disabled = true;
            }

            if (qualityMenuBtn) {
                const current = this.state.cameraQualityProfile?.level || 'medium';
                document.querySelectorAll('.quality-option').forEach(opt => {
                    opt.classList.toggle('active', opt.dataset.quality === current);
                });
            }

            if (!statusEl) return;

            if (!this.state.isScanning) {
                statusEl.innerText = "Kamera kapalı";
                return;
            }

            const selected = this.state.cameraDevices.find(camera => camera.id === this.state.selectedCameraId);
            const profile = this.getDeviceCameraProfile();
            const label = selected?.label ? selected.label.replace(/\s*\([^)]+\)\s*$/, '') : 'Arka kamera';
            const zoomLabel = this.state.preferredZoom ? ` · ${this.state.preferredZoom.toFixed(1)}x` : '';
            statusEl.innerText = `${label} · ${profile.label}${zoomLabel}`;
        },

        searchProducts: async function(query) {
            const cleanQuery = String(query || '').trim();
            if (cleanQuery.length < 2) return;

            this.state.lastSearchQuery = cleanQuery;
            const container = document.getElementById('results-container');
            const loader = document.getElementById('app-loader');
            
            loader.style.display = 'flex';

            try {
                // Mevcut terminal API'sini kullanıyoruz + aktif depo
                const url = `${kasaAyar.rootApiUrl}hizli-kasa/v1/terminal/products?s=${encodeURIComponent(cleanQuery)}&limit=15&depo_id=${this.state.aktifDepoId}&_=${Date.now()}`;
                const response = await fetch(url, {
                    headers: { 'X-WP-Nonce': kasaAyar.nonce }
                });

                if (response.status === 401 || response.status === 403) {
                    this.showToast("Oturum süresi doldu, sayfa yenileniyor...", "error");
                    setTimeout(() => window.location.reload(), 1500);
                    return;
                }

                if (!response.ok) throw new Error("Sunucu hatası");

                const data = await response.json();
                this.renderResults(data.products || []);
                document.getElementById('result-count').innerText = `${data.products.length} Ürün bulundu`;

            } catch (err) {
                console.error(err);
                this.showToast("Ürünler yüklenirken hata oluştu", "error");
                container.innerHTML = `
                    <div class="initial-state" style="color: #e74c3c;">
                        <div class="welcome-icon">⚠️</div>
                        <h2>Bağlantı Hatası</h2>
                        <p>Sunucuya ulaşılamadı veya oturumunuz sonlandı. Lütfen sayfayı yenileyin.</p>
                    </div>
                `;
                document.getElementById('result-count').innerText = '0 Ürün bulundu';
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
            const depoKodu = p.all_codes && p.all_codes[String(this.state.aktifDepoId)] ? p.all_codes[String(this.state.aktifDepoId)] : '';

            return `
                <div class="mobile-urun-kart parent-card ${isOpen ? 'is-open' : ''}" data-target="vars-of-${p.id}">
                    <div class="card-main">
                        <img src="${img}" class="card-img" alt="">
                        <div class="card-info">
                            <div class="card-name">${p.name}</div>
                            <div class="card-identifiers">
                                <span class="card-sku">SKU: <strong>${p.sku || 'KARIŞIK SKU'}</strong></span>
                                ${depoKodu ? `<span class="card-separator">/</span><span class="card-shelf">RAF: <strong>${depoKodu}</strong></span>` : ''}
                            </div>
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
            const allCodes = p.all_codes || {};
            const aktifDepoIdStr = String(this.state.aktifDepoId);
            
            const depoKodu = allCodes[aktifDepoIdStr] || '';
            
            // API'den gelen warehouse_stock direkt o depo için hesaplanmıştır, en güvenilir kaynak odur.
            // all_stocks içinden de doğrulanabilir ama tip uyumsuzluklarına karşı warehouse_stock daha sağlamdır.
            const currentQty = (p.warehouse_stock != null) ? parseFloat(p.warehouse_stock) : (parseFloat(allStocks[aktifDepoIdStr]) || 0);
            
            const aktifDepoName = this.state.depolar.find(d => d.id == this.state.aktifDepoId)?.name || "Depo";
            
            let otherTotal = 0;
            if (allStocks && typeof allStocks === 'object') {
                Object.keys(allStocks).forEach(depoId => {
                    if (String(depoId) === aktifDepoIdStr) return;
                    const val = parseFloat(allStocks[depoId]);
                    if (!isNaN(val)) otherTotal += val;
                });
            }

            // Fiyat Hesaplamaları
            const currentPrice = parseFloat(p.price || 0);
            const regularPrice = parseFloat(p.regular_price || 0);
            const hasSale = regularPrice > 0 && regularPrice > currentPrice;
            const cashPrice = currentPrice * 0.95;
            const priceFormatter = (window.HizliKasa && HizliKasa.CurrencyMask) ? HizliKasa.CurrencyMask.format : (n) => parseFloat(n).toFixed(2);

            const priceHtml = `
                <div class="card-price-info">
                    <div class="price-row">
                        ${hasSale ? `<span class="old-price">${priceFormatter(regularPrice)}</span>` : ''}
                        <span class="main-price">${priceFormatter(currentPrice)}</span>
                    </div>
                    <div class="cash-price-row">
                        <span class="cp-label">%5 Nakit:</span>
                        <span class="cp-val">${priceFormatter(cashPrice)}</span>
                    </div>
                </div>
            `;

            if (isVariation) {
                // VARYASYON KARTI (Sağdan Stoklu)
                return `
                    <div class="mobile-urun-kart variation">
                        <div class="card-flex-wrapper">
                            <img src="${img}" class="card-img-mini" alt="">
                            <div class="card-info">
                                <div class="card-name">${p.name.replace(/.* - /, '')}</div>
                                <div class="card-identifiers">
                                    <span class="card-sku">SKU: <strong>${p.sku || 'YOK'}</strong></span>
                                    ${depoKodu ? `<span class="card-separator">/</span><span class="card-shelf">RAF: <strong>${depoKodu}</strong></span>` : ''}
                                </div>
                                ${priceHtml}
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
                            <div class="card-identifiers">
                                <span class="card-sku">SKU: <strong>${p.sku || 'SKU YOK'}</strong></span>
                                ${depoKodu ? `<span class="card-separator">/</span><span class="card-shelf">RAF: <strong>${depoKodu}</strong></span>` : ''}
                            </div>
                            ${priceHtml}
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

        // Safari BFCache koruması — geri/ileri navigasyonda donmuş (eski) stok verisini engelle
        window.addEventListener('pageshow', (event) => {
            if (event.persisted) {
                const query = MobileApp.state.lastSearchQuery;
                if (query && query.length >= 2) {
                    MobileApp.searchProducts(query);
                }
            }
        });

        // Tab'a geri dönüş koruması — kullanıcı başka sekmeye/uygulamaya geçip döndüğünde
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible') {
                const query = MobileApp.state.lastSearchQuery;
                if (query && query.length >= 2) {
                    MobileApp.searchProducts(query);
                }
            }
        });
    });

})();
