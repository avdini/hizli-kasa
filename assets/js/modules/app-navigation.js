/**
 * Hızlı Kasa - Uygulama Navigasyon Modülü
 * 
 * Sekmeler arası geçişi ve lazy loading (dinamik yükleme) mantığını yönetir.
 */

const AppNavigation = (function () {
    let tabs = null;
    let contents = null;
    let loadingOverlay = null;
    let mobileToggle = null;
    let menuList = null;

    // Aktif sekmeleri ve içerikleri tutar
    let loadedTabs = ['kasa']; // Kasa varsayılan olarak yüklüdür

    function init() {
        tabs = document.querySelectorAll('.ust-sekme');
        contents = document.querySelectorAll('.tab-content');
        loadingOverlay = document.getElementById('app-loading');
        mobileToggle = document.getElementById('mobile-menu-toggle');
        menuList = document.getElementById('ust-sekme-listesi');

        if (tabs) {
            tabs.forEach(tab => {
                tab.addEventListener('click', () => {
                    const targetTab = tab.getAttribute('data-tab');
                    handleTabSwitch(targetTab);

                    // Mobilde menü açıksa kapat
                    if (menuList && menuList.classList.contains('open')) {
                        toggleMobileMenu();
                    }
                });
            });
        }

        if (mobileToggle) {
            mobileToggle.addEventListener('click', () => {
                toggleMobileMenu();
            });
        }

        initGlobalActions();
    }

    function initGlobalActions() {
        const fsToggle = document.getElementById('tam-ekran-toggle');
        if (fsToggle) {
            fsToggle.addEventListener('click', () => {
                toggleFullscreen();
            });
        }
    }

    function toggleFullscreen() {
        if (!document.fullscreenElement) {
            document.documentElement.requestFullscreen().catch(err => {
                console.warn('Tam ekran modu başlatılamadı:', err.message);
            });
        } else {
            if (document.exitFullscreen) {
                document.exitFullscreen();
            }
        }
    }

    function toggleMobileMenu() {
        if (mobileToggle && menuList) {
            mobileToggle.classList.toggle('open');
            menuList.classList.toggle('open');
        }
    }

    /**
     * Sekme değiştirme mantığı
     * @param {string} tabName - Hedef sekme adı
     */
    async function handleTabSwitch(tabName) {
        if (isAlreadyActive(tabName)) return;

        // Eğer sekme daha önce yüklenmemişse, AJAX ile çek
        if (!loadedTabs.includes(tabName)) {
            await loadTabContent(tabName);
        }

        // Görünürlüğü güncelle
        updateUI(tabName);

        // Sekme her aktif olduğunda olayı tetikle (modüllerin odaklanma vb. işlemleri için)
        dispatchTabLoadedEvent(tabName);
    }

    function isAlreadyActive(tabName) {
        const activeTab = document.querySelector('.ust-sekme.aktif');
        return activeTab && activeTab.getAttribute('data-tab') === tabName;
    }

    /**
     * Sekme içeriğini REST API üzerinden yükler
     */
    async function loadTabContent(tabName) {
        showLoading();

        try {
            const apiBase = kasaAyar.rootApiUrl || (window.location.origin + '/wp-json/');
            // Cache busting için timestamp ekleyelim
            const response = await fetch(`${apiBase}hizli-kasa/v1/load-tab?tab=${tabName}&_=${Date.now()}`, {
                headers: {
                    'X-WP-Nonce': kasaAyar.nonce
                }
            });

            if (!response.ok) throw new Error('Sekme yüklenemedi');

            const data = await response.json();

            const contentDiv = document.getElementById(`tab-content-${tabName}`);
            if (contentDiv) {
                contentDiv.innerHTML = data.html;
                loadedTabs.push(tabName);
            }

        } catch (error) {
            console.error('Hata:', error);
            alert('Sayfa yüklenirken bir sorun oluştu.');
        } finally {
            hideLoading();
        }
    }

    function updateUI(tabName) {
        // Sekme butonlarını güncelle
        if (tabs) {
            tabs.forEach(t => {
                if (!t) return;
                if (t.getAttribute('data-tab') === tabName) {
                    t.classList.add('aktif');
                } else {
                    t.classList.remove('aktif');
                }
            });
        }

        // İçerik alanlarını güncelle
        if (contents) {
            contents.forEach(c => {
                if (!c) return;
                if (c.id === `tab-content-${tabName}`) {
                    c.classList.add('aktif');
                } else {
                    c.classList.remove('aktif');
                }
            });
        }
    }

    function showLoading() {
        if (loadingOverlay) loadingOverlay.style.display = 'flex';
    }

    function hideLoading() {
        if (loadingOverlay) loadingOverlay.style.display = 'none';
    }

    function dispatchTabLoadedEvent(tabName) {
        const event = new CustomEvent('hkTabLoaded', { detail: { tab: tabName } });
        document.dispatchEvent(event);
    }

    return {
        init: init
    };
})();

// DOM hazır olduğunda başlat
document.addEventListener('DOMContentLoaded', () => {
    AppNavigation.init();
});

// Sevk alt sekmeleri için event delegation (Dinamik yüklendiği için body'ye bağlıyoruz)
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('sevk-alt-btn')) {
        const btn = e.target;
        const container = btn.closest('.hk-tab-container');
        
        if (container) {
            // Aktif butonu güncelle
            container.querySelectorAll('.sevk-alt-btn').forEach(b => b.classList.remove('aktif'));
            btn.classList.add('aktif');

            // Hedef paneli göster
            const targetId = btn.getAttribute('data-target');
            container.querySelectorAll('.sevk-icerik-paneli').forEach(panel => {
                panel.style.display = 'none';
                panel.classList.remove('aktif');
            });
            
            const targetPanel = container.querySelector('#' + targetId);
            if(targetPanel) {
                targetPanel.style.display = 'block';
                targetPanel.classList.add('aktif');
                document.dispatchEvent(new CustomEvent('hkSevkSubTabLoaded', {
                    detail: { subTab: targetId }
                }));
            }
        }
    }
});

// Sevk ana grup seçici için event delegation
document.addEventListener('click', function(e) {
    const groupBtn = e.target.closest('.sevk-grup-btn');
    if (groupBtn) {
        const container = groupBtn.closest('.sevk-shell');
        if (container) {
            const group = groupBtn.getAttribute('data-group');
            
            container.querySelectorAll('.sevk-grup-btn').forEach(btn => btn.classList.remove('aktif'));
            groupBtn.classList.add('aktif');
            
            container.setAttribute('data-active-group', group);
            
            const firstSubTab = container.querySelector(`.sevk-alt-btn[data-group="${group}"]`);
            if (firstSubTab) {
                firstSubTab.click();
            }
        }
    }
});
