/**
 * Hızlı Kasa - Uygulama Navigasyon Modülü
 * 
 * Sekmeler arası geçişi ve lazy loading (dinamik yükleme) mantığını yönetir.
 */

const AppNavigation = (function () {
    const tabs = document.querySelectorAll('.ust-sekme');
    const contents = document.querySelectorAll('.tab-content');
    const loadingOverlay = document.getElementById('app-loading');

    // Aktif sekmeleri ve içerikleri tutar
    let loadedTabs = ['kasa']; // Kasa varsayılan olarak yüklüdür

    function init() {
        tabs.forEach(tab => {
            tab.addEventListener('click', () => {
                const targetTab = tab.getAttribute('data-tab');
                handleTabSwitch(targetTab);
            });
        });
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
            const response = await fetch(`${kasaAyar.apiUrl}hizli-kasa/v1/load-tab?tab=${tabName}`, {
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
                
                // Eğer yeni sayfada özel init fonksiyonları gerekiyorsa burada tetiklenebilir
                dispatchTabLoadedEvent(tabName);
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
        tabs.forEach(t => {
            if (t.getAttribute('data-tab') === tabName) {
                t.classList.add('aktif');
            } else {
                t.classList.remove('aktif');
            }
        });

        // İçerik alanlarını güncelle
        contents.forEach(c => {
            if (c.id === `tab-content-${tabName}`) {
                c.classList.add('aktif');
            } else {
                c.classList.remove('aktif');
            }
        });
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
