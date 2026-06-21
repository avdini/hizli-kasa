/**
 * Hızlı Kasa - Depo Yöneticisi Modülü
 *
 * Çift katman cache (localStorage + WP user_meta) ile aktif depo yönetimi.
 * Görüntüleme / yönetim yetki kontrolü.
 * Depo switcher dropdown UI yönetimi.
 *
 * @package HizliKasa
 */

(function(HK) {
    'use strict';

    HK.DepoManager = {

        // --- State ---
        state: {
            view: [],          // [{id, name}] — görüntüleyebildiği depolar
            manageIds: [],     // [id, ...] — yönetebileceği depo ID'leri
            activeDepoId: null, // Yönetim/Kasa deposu
            viewDepoId: null,   // Ürünler sekmesi görüntüleme deposu
            isLoaded: false,
        },

        // localStorage key (per-user çakışma olmasın)
        _cacheKey: function() {
            var uid = (typeof kasaAyar !== 'undefined' && kasaAyar.userId) ? kasaAyar.userId : 'anon';
            return 'hk_active_depo_' + uid;
        },

        // --- Temel Okuma / Yazma ---

        getActiveDepo: function() {
            return this.state.activeDepoId;
        },

        getViewDepo: function() {
            return this.state.viewDepoId;
        },

        /**
         * Aktif depoyu (Yönetim/Kasa) günceller:
         * 1. state'e yazar
         * 2. localStorage'a yazar
         * 3. Sunucuya (user_meta) async olarak yazar
         * 4. Görüntüleme deposunu da buna eşitler (Yönetim yetkisi görüntülemeyi kapsar)
         * 5. hkActiveDepoChanged event'ini tetikler
         */
        setActiveDepo: function(depoId, silent) {
            var self = this;
            depoId = parseInt(depoId);
            if (!depoId || !this.canManageDepo(depoId)) {
                console.warn('HK DepoManager: Yönetim yetkisi olmayan veya geçersiz depo ID:', depoId);
                return;
            }

            var prev = this.state.activeDepoId;
            this.state.activeDepoId = depoId;

            // Yönetim deposu değişince görüntüleme deposunu da güncelle
            this.setViewDepo(depoId);

            // 1. localStorage
            try {
                localStorage.setItem(this._cacheKey(), depoId);
            } catch(e) {
                console.warn('HK DepoManager: localStorage yazılamadı:', e);
            }

            // 2. Sunucuya kaydet (async, hata yutulur)
            this._saveToServer(depoId);

            // 3. Dropdown UI'ları güncelle
            this._updateDropdownUI();
            this._updateTopDropdownUI();

            // 4. Event tetikle (sayfa yenilemeden ürünleri günceller)
            if (!silent && prev !== depoId) {
                document.dispatchEvent(new CustomEvent('hkActiveDepoChanged', {
                    detail: { depoId: depoId, prevDepoId: prev }
                }));
            }
        },

        /**
         * Görüntüleme deposunu günceller (Sadece ürünler sekmesi için)
         */
        setViewDepo: function(depoId, silent) {
            depoId = parseInt(depoId);
            if (!depoId || !this.canViewDepo(depoId)) {
                console.warn('HK DepoManager: Görüntüleme yetkisi olmayan veya geçersiz depo ID:', depoId);
                return;
            }

            var prev = this.state.viewDepoId;
            this.state.viewDepoId = depoId;

            // UI güncelle
            this._updateDropdownUI();

            if (!silent && prev !== depoId) {
                document.dispatchEvent(new CustomEvent('hkViewDepoChanged', {
                    detail: { depoId: depoId, prevDepoId: prev }
                }));
            }
        },

        canViewDepo: function(depoId) {
            if (!depoId) return false;
            // Admin tüm depoları görebilir (view listesi boş değilse)
            return this.state.view.some(function(d) { return d.id === parseInt(depoId); });
        },

        canManageDepo: function(depoId) {
            if (!depoId) return false;
            return this.state.manageIds.includes(parseInt(depoId));
        },

        getActiveDepoName: function() {
            var id = this.state.activeDepoId;
            var found = this.state.view.find(function(d) { return d.id === id; });
            return found ? found.name : '---';
        },

        getViewDepoName: function() {
            var id = this.state.viewDepoId;
            var found = this.state.view.find(function(d) { return d.id === id; });
            return found ? found.name : '---';
        },

        // --- Sunucu ile Senkronizasyon ---

        /**
         * Sunucudan depo listesini ve aktif depoyu yükler.
         * Önce localStorage'ı kontrol eder (hızlı yükleme), sonra sunucu cevabıyla doğrular.
         */
        load: async function() {
            var self = this;

            // localStorage'dan geçici aktif depo (hızlı yükleme)
            var cachedId = null;
            try {
                cachedId = parseInt(localStorage.getItem(this._cacheKey())) || null;
            } catch(e) {}

            try {
                var response = await fetch(kasaAyar.rootApiUrl + 'hizli-kasa/v1/user/depolar', {
                    headers: { 'X-WP-Nonce': kasaAyar.nonce }
                });

                if (!response.ok) {
                    throw new Error('HTTP ' + response.status);
                }

                var data = await response.json();

                self.state.view       = data.view       || [];
                self.state.manageIds  = data.manage_ids || [];

                // Aktif depo önceliği: localStorage > sunucu > ilk görüntüleme deposu
                var serverActive = data.active_depo_id ? parseInt(data.active_depo_id) : null;
                var resolved     = null;

                if (cachedId && self.canViewDepo(cachedId)) {
                    resolved = cachedId;
                } else if (serverActive && self.canViewDepo(serverActive)) {
                    resolved = serverActive;
                } else if (self.state.view.length > 0) {
                    resolved = self.state.view[0].id;
                }

                self.state.activeDepoId = resolved;
                self.state.viewDepoId = resolved; // Başlangıçta ikisi aynı
                self.state.isLoaded = true;

                // Sunucu cache'i ile localStorage'ı uyumlu tut
                if (resolved) {
                    try { localStorage.setItem(self._cacheKey(), resolved); } catch(e) {}
                    if (resolved !== serverActive) {
                        self._saveToServer(resolved);
                    }

                    // Aktif depo belirlendiğinde diğer modülleri haberdar et (sepet yüklemesi vb için)
                    document.dispatchEvent(new CustomEvent('hkActiveDepoChanged', {
                        detail: { depoId: resolved, prevDepoId: null }
                    }));
                }

            } catch(e) {
                console.error('HK DepoManager: Depo listesi yüklenemedi:', e);
                // Fallback: localStorage varsa kullan
                if (cachedId) {
                    self.state.activeDepoId = cachedId;
                    self.state.isLoaded = true;
                }
            }
        },

        /**
         * Aktif depoyu sunucu user_meta'ya kaydeder (arka planda).
         */
        _saveToServer: async function(depoId) {
            try {
                await fetch(kasaAyar.rootApiUrl + 'hizli-kasa/v1/user/set-active-depo', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': kasaAyar.nonce
                    },
                    body: JSON.stringify({ depo_id: depoId })
                });
            } catch(e) {
                console.warn('HK DepoManager: Sunucuya kaydedilemedi:', e);
            }
        },

        // --- Dropdown UI ---

        /**
         * Ürünler sekmesindeki .depo-switcher bileşenini başlatır.
         * Tab yüklendiğinde çağrılmalıdır.
         */
        initSwitcherUI: function() {
            var self = this;

            var trigger = document.getElementById('depo-switcher-trigger');
            var dropdown = document.getElementById('depo-dropdown');
            var readonlyBadge = document.getElementById('depo-readonly-badge');

            if (!trigger || !dropdown) return;

            // Zaten başlatılmışsa eventleri tekrar bağlama, sadece render et
            if (trigger.dataset.initialized) {
                this._renderDropdownItems();
                this._updateDropdownUI();
                return;
            }
            trigger.dataset.initialized = "true";

            // Dropdown listesini doldur
            this._renderDropdownItems();
            this._updateDropdownUI();

            // Tıklama: Dropdown aç/kapat
            trigger.addEventListener('click', function(e) {
                e.stopPropagation();
                var isOpen = dropdown.style.display !== 'none';
                dropdown.style.display = isOpen ? 'none' : 'block';
                trigger.classList.toggle('open', !isOpen);
            });

            // Dışarı tıklanınca kapat
            document.addEventListener('click', function() {
                dropdown.style.display = 'none';
                trigger.classList.remove('open');
            });

            // Yönetim rozeti
            if (readonlyBadge) {
                readonlyBadge.style.display = self.canManageDepo(self.state.viewDepoId) ? 'none' : 'flex';
            }
        },

        _renderDropdownItems: function() {
            var self = this;
            var dropdown = document.getElementById('depo-dropdown');
            if (!dropdown) return;

            dropdown.innerHTML = '';

            if (this.state.view.length === 0) {
                dropdown.innerHTML = '<div class="depo-dropdown-empty">Yetkili depo yok</div>';
                return;
            }

            this.state.view.forEach(function(d) {
                var canManage = self.canManageDepo(d.id);
                var item = document.createElement('div');
                item.className = 'depo-dropdown-item' + (d.id === self.state.activeDepoId ? ' active' : '');
                item.dataset.depoId = d.id;
                item.innerHTML =
                    '<span class="depo-item-name">' + d.name + '</span>' +
                    (canManage
                        ? '<span class="depo-manage-badge" title="Yönetim yetkisi var">⚙️</span>'
                        : '<span class="depo-view-badge" title="Sadece görüntüleme">👁</span>');
                item.addEventListener('click', function(e) {
                    e.stopPropagation();
                    self.setViewDepo(d.id);
                    var dropdown2 = document.getElementById('depo-dropdown');
                    var trigger2  = document.getElementById('depo-switcher-trigger');
                    if (dropdown2) dropdown2.style.display = 'none';
                    if (trigger2) trigger2.classList.remove('open');
                });
                dropdown.appendChild(item);
            });
        },

        _updateDropdownUI: function() {
            var self = this;
            var nameEl = document.getElementById('aktif-depo-adi');
            var readonlyBadge = document.getElementById('depo-readonly-badge');

            if (nameEl) {
                nameEl.textContent = this.getViewDepoName();
            }

            // Dropdown aktif item'ı işaretle
            document.querySelectorAll('#depo-dropdown .depo-dropdown-item').forEach(function(el) {
                el.classList.toggle('active', parseInt(el.dataset.depoId) === self.state.viewDepoId);
            });

            // Yönetim rozeti
            var isManage = this.canManageDepo(this.state.viewDepoId);
            if (readonlyBadge) {
                readonlyBadge.style.display = isManage ? 'none' : 'flex';
            }
        },

        // --- Üst Menü Switcher (Global) ---

        /**
         * Üst menüdeki depo switcher'ı başlatır.
         */
        initTopSwitcherUI: function() {
            var self = this;
            var trigger = document.getElementById('ust-depo-switcher-trigger');
            var dropdown = document.getElementById('ust-depo-dropdown');

            if (!trigger || !dropdown) return;

            // Dropdown listesini doldur
            this._renderTopDropdownItems();
            this._updateTopDropdownUI();

            // Tıklama: Dropdown aç/kapat
            trigger.addEventListener('click', function(e) {
                e.stopPropagation();
                var isOpen = dropdown.style.display !== 'none';
                dropdown.style.display = isOpen ? 'none' : 'block';
                trigger.classList.toggle('open', !isOpen);
            });

            // Dışarı tıklanınca kapat
            document.addEventListener('click', function() {
                dropdown.style.display = 'none';
                trigger.classList.remove('open');
            });
        },

        _renderTopDropdownItems: function() {
            var self = this;
            var dropdown = document.getElementById('ust-depo-dropdown');
            var trigger = document.getElementById('ust-depo-switcher-trigger');
            if (!dropdown) return;

            dropdown.innerHTML = '';

            // Sadece yönetebildiği depoları listele (Kullanıcı talebi)
            var manageable = this.state.view.filter(function(d) {
                return self.canManageDepo(d.id);
            });

            if (manageable.length === 0) {
                dropdown.innerHTML = '<div style="padding:15px; font-size:12px; color:var(--hk-text-muted); text-align:center;">Değiştirme yetkisi yok</div>';
                return;
            }

            manageable.forEach(function(d) {
                var item = document.createElement('div');
                item.className = 'depo-dropdown-item' + (d.id === self.state.activeDepoId ? ' active' : '');
                item.dataset.depoId = d.id;
                item.innerHTML = '<span class="depo-item-name">' + d.name + '</span>';
                
                item.addEventListener('click', function(e) {
                    e.stopPropagation();
                    self.setActiveDepo(d.id);
                    dropdown.style.display = 'none';
                    if (trigger) trigger.classList.remove('open');
                });
                dropdown.appendChild(item);
            });
        },

        _updateTopDropdownUI: function() {
            var nameEl = document.getElementById('ust-aktif-depo-adi');
            if (nameEl) {
                nameEl.textContent = this.getActiveDepoName();
            }
            // Dropdown aktif item'ı işaretle
            document.querySelectorAll('#ust-depo-dropdown .depo-dropdown-item').forEach(function(el) {
                el.classList.toggle('active', parseInt(el.dataset.depoId) === HK.DepoManager.state.activeDepoId);
            });
        },

        // --- Init ---

        /**
         * Ana başlatıcı. Shortcode yüklendiğinde çağrılır.
         */
        init: async function() {
            await this.load();

            // Üst menü switcher'ı başlat
            this.initTopSwitcherUI();

            // Ürünler sekmesi açık olduğunda switcher'ı başlat
            document.addEventListener('hkTabLoaded', function(e) {
                if (e.detail.tab === 'urunler') {
                    HK.DepoManager.initSwitcherUI();
                }
            });

            // Eğer ürünler sekmesi zaten açıksa hemen başlat
            if (document.getElementById('depo-switcher-trigger')) {
                this.initSwitcherUI();
            }
        }
    };

})(window.HizliKasa = window.HizliKasa || {});
