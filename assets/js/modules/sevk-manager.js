(function(HK) {
    'use strict';

    var apiBase = function() {
        return (typeof kasaAyar !== 'undefined' && kasaAyar.rootApiUrl)
            ? kasaAyar.rootApiUrl + 'hizli-kasa/v1/'
            : window.location.origin + '/wp-json/hizli-kasa/v1/';
    };

    var api = async function(path, options) {
        options = options || {};
        options.headers = Object.assign({
            'Content-Type': 'application/json',
            'X-WP-Nonce': (typeof kasaAyar !== 'undefined' ? kasaAyar.nonce : '')
        }, options.headers || {});

        var response = await fetch(apiBase() + path, options);
        var data = await response.json().catch(function() { return {}; });
        if (!response.ok) {
            throw new Error(data.message || 'İşlem tamamlanamadı');
        }
        return data;
    };

    var escapeHtml = function(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    };

    var toast = function(message, type) {
        if (HK.UIRenderer && HK.UIRenderer.showToast) {
            HK.UIRenderer.showToast(message, type || 'success', type === 'error');
        } else {
            alert(message);
        }
    };

    var formatDate = function(value) {
        if (!value) return '-';
        var date = new Date(String(value).replace(' ', 'T'));
        return isNaN(date.getTime()) ? value : date.toLocaleString('tr-TR');
    };

    var statusBadge = function(item) {
        return '<span class="sevk-status ' + escapeHtml(item.durum) + '">' + escapeHtml(item.durum_label || item.durum) + '</span>';
    };

    HK.SevkManager = {
        initialized: false,
        activeSevk: null,
        incoming: [],
        allItems: [],
        pollTimer: null,

        init: function() {
            var self = this;
            if (this.initialized) return;

            if (!document.querySelector('.sevk-shell')) {
                document.addEventListener('hkTabLoaded', function(e) {
                    if (e.detail.tab === 'sevk') self.init();
                });
                return;
            }

            this.initialized = true;
            this.bindTabs();
            this.bindCikis();
            this.bindKabul();
            this.bindGenel();
            this.bindModal();
            this.bindImagePreview();
            this.refreshDepoUi();
            this.loadAll();
            this.startPolling();

            document.addEventListener('hkActiveDepoChanged', function() {
                self.refreshDepoUi();
                self.resetCikis();
            });
        },

        bindTabs: function() {
            var self = this;
            document.querySelectorAll('.sevk-alt-btn').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var targetId = btn.dataset.target;
                    document.querySelectorAll('.sevk-alt-btn').forEach(function(b) { b.classList.remove('aktif'); });
                    btn.classList.add('aktif');
                    document.querySelectorAll('.sevk-icerik-paneli').forEach(function(panel) {
                        panel.style.display = 'none';
                        panel.classList.remove('aktif');
                    });
                    var target = document.getElementById(targetId);
                    if (target) {
                        target.style.display = 'block';
                        target.classList.add('aktif');
                    }
                    if (targetId === 'sevk-kabul') self.loadIncoming();
                    if (targetId === 'sevk-genel') self.loadAll();
                    if (targetId === 'sevk-cikis') setTimeout(function() {
                        var input = document.getElementById('sevk-cikis-barkod');
                        if (input && self.activeSevk) input.focus();
                    }, 50);
                });
            });
        },

        bindCikis: function() {
            var self = this;
            var createBtn = document.getElementById('sevk-cikis-olustur');
            var input = document.getElementById('sevk-cikis-barkod');
            var approveBtn = document.getElementById('sevk-cikis-onayla');
            var newBtn = document.getElementById('sevk-cikis-yeni');

            if (createBtn) createBtn.addEventListener('click', function() { self.createSevk(); });
            if (approveBtn) approveBtn.addEventListener('click', function() { self.submitForApproval(); });
            if (newBtn) newBtn.addEventListener('click', function() { self.resetCikis(); });
            if (input) {
                input.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        var sku = input.value.trim();
                        input.value = '';
                        if (sku) self.addItem(sku);
                    }
                });
            }
        },

        bindKabul: function() {
            var self = this;
            var refresh = document.getElementById('sevk-kabul-yenile');
            if (refresh) refresh.addEventListener('click', function() { self.loadIncoming(); });
        },

        bindGenel: function() {
            var self = this;
            var refresh = document.getElementById('sevk-genel-yenile');
            var filter = document.getElementById('sevk-genel-durum');
            var dateStart = document.getElementById('sevk-genel-date-start');
            var dateEnd = document.getElementById('sevk-genel-date-end');
            if (refresh) refresh.addEventListener('click', function() { self.loadAll(); });
            if (filter) filter.addEventListener('change', function() { self.loadAll(); });
            if (dateStart) dateStart.addEventListener('change', function() { self.loadAll(); });
            if (dateEnd) dateEnd.addEventListener('change', function() { self.loadAll(); });
        },

        bindModal: function() {
            var close = document.getElementById('sevk-modal-kapat');
            var modal = document.getElementById('sevk-detay-modal');
            if (close) close.addEventListener('click', function() { modal.style.display = 'none'; });
            if (modal) modal.addEventListener('click', function(e) {
                if (e.target === modal) modal.style.display = 'none';
            });
        },

        refreshDepoUi: function() {
            var label = document.getElementById('sevk-active-depo-label');
            var source = document.getElementById('sevk-cikis-kaynak-label');
            var target = document.getElementById('sevk-cikis-hedef');
            var depo = HK.DepoManager;

            if (!depo || !depo.state.isLoaded) return;
            if (label) label.textContent = 'Aktif depo: ' + depo.getActiveDepoName();
            if (source) source.value = depo.getActiveDepoName();

            if (target) {
                var active = depo.getActiveDepo();
                target.innerHTML = '';
                depo.state.view.filter(function(d) { return d.id !== active; }).forEach(function(d) {
                    var option = document.createElement('option');
                    option.value = d.id;
                    option.textContent = d.name;
                    target.appendChild(option);
                });
                if (!target.children.length) {
                    target.innerHTML = '<option value="">Hedef depo yok</option>';
                }
            }
        },

        setStep: function(step) {
            document.querySelectorAll('[data-step]').forEach(function(panel) {
                panel.style.display = panel.dataset.step === String(step) ? 'block' : 'none';
            });
            document.querySelectorAll('[data-step-indicator]').forEach(function(item) {
                item.classList.toggle('active', item.dataset.stepIndicator === String(step));
            });
        },

        createSevk: async function() {
            var depo = HK.DepoManager;
            var target = document.getElementById('sevk-cikis-hedef');
            if (!depo || !depo.getActiveDepo()) return toast('Aktif depo bulunamadı.', 'error');
            if (!depo.canManageDepo(depo.getActiveDepo())) return toast('Aktif depoda yönetim yetkiniz yok.', 'error');
            if (!target || !target.value) return toast('Hedef depo seçin.', 'error');

            try {
                var data = await api('sevk/olustur', {
                    method: 'POST',
                    body: JSON.stringify({ kaynak_depo_id: depo.getActiveDepo(), hedef_depo_id: parseInt(target.value) })
                });
                this.activeSevk = data.sevk;
                this.renderCikis();
                this.setStep(2);
                setTimeout(function() {
                    var input = document.getElementById('sevk-cikis-barkod');
                    if (input) input.focus();
                }, 50);
            } catch (e) {
                toast(e.message, 'error');
            }
        },

        addItem: async function(sku) {
            if (!this.activeSevk) return;
            try {
                var data = await api('sevk/kalem-ekle', {
                    method: 'POST',
                    body: JSON.stringify({ sevk_id: this.activeSevk.id, sku: sku, qty: 1 })
                });
                this.activeSevk = data.sevk;
                this.renderCikis();
            } catch (e) {
                toast(e.message, 'error');
            }
        },

        deleteItem: async function(id) {
            try {
                var data = await api('sevk/kalem-sil', {
                    method: 'POST',
                    body: JSON.stringify({ sevk_id: this.activeSevk.id, kalem_id: id })
                });
                this.activeSevk = data.sevk;
                this.renderCikis();
            } catch (e) {
                toast(e.message, 'error');
            }
        },

        renderCikis: function() {
            var sevk = this.activeSevk;
            if (!sevk) return;
            var no = document.getElementById('sevk-cikis-no');
            var route = document.getElementById('sevk-cikis-route');
            var list = document.getElementById('sevk-cikis-kalemler');
            var summary = document.getElementById('sevk-cikis-ozet');

            if (no) no.textContent = sevk.sevk_no;
            if (route) route.textContent = sevk.kaynak_depo_adi + ' → ' + sevk.hedef_depo_adi;
            if (summary) summary.textContent = sevk.toplam_cesit + ' çeşit ürün, ' + sevk.toplam_adet + ' adet toplam';
            if (!list) return;

            if (!sevk.kalemler || !sevk.kalemler.length) {
                list.innerHTML = '<tr><td colspan="4" class="sevk-empty">Barkod okutulunca ürünler burada görünecek.</td></tr>';
                return;
            }

            var self = this;
            list.innerHTML = sevk.kalemler.map(function(item) {
                var img = item.image ? '<img src="' + escapeHtml(item.image) + '" alt="">' : '<span></span>';
                return '<tr>' +
                    '<td><div class="sevk-product-cell">' + img + '<strong>' + escapeHtml(item.urun_adi) + '</strong></div></td>' +
                    '<td>' + escapeHtml(item.sku) + '</td>' +
                    '<td>' + item.gonderilen_adet + '</td>' +
                    '<td><button type="button" class="sevk-btn secondary" data-delete-item="' + item.id + '">Sil</button></td>' +
                    '</tr>';
            }).join('');
            list.querySelectorAll('[data-delete-item]').forEach(function(btn) {
                btn.addEventListener('click', function() { self.deleteItem(parseInt(btn.dataset.deleteItem)); });
            });
        },

        submitForApproval: async function() {
            if (!this.activeSevk) return;
            var note = document.getElementById('sevk-cikis-not');
            try {
                var data = await api('sevk/gonder-onayla', {
                    method: 'POST',
                    body: JSON.stringify({ sevk_id: this.activeSevk.id, not_gonderici: note ? note.value : '' })
                });
                this.activeSevk = data.sevk;
                var result = document.getElementById('sevk-cikis-sonuc');
                if (result) result.textContent = data.sevk.sevk_no + ' alıcı depo onayına gönderildi.';
                this.setStep(3);
                this.loadAll();
            } catch (e) {
                toast(e.message, 'error');
            }
        },

        resetCikis: function() {
            this.activeSevk = null;
            this.refreshDepoUi();
            this.setStep(1);
            var note = document.getElementById('sevk-cikis-not');
            var input = document.getElementById('sevk-cikis-barkod');
            var list = document.getElementById('sevk-cikis-kalemler');
            if (note) note.value = '';
            if (input) input.value = '';
            if (list) list.innerHTML = '';
        },

        loadAll: async function() {
            var filter = document.getElementById('sevk-genel-durum');
            var durum = filter ? filter.value : 'all';
            var dateStart = document.getElementById('sevk-genel-date-start');
            var dateEnd = document.getElementById('sevk-genel-date-end');
            var query = 'durum=' + encodeURIComponent(durum);
            if (dateStart && dateStart.value) query += '&date_start=' + encodeURIComponent(dateStart.value);
            if (dateEnd && dateEnd.value) query += '&date_end=' + encodeURIComponent(dateEnd.value);
            try {
                var data = await api('sevk/liste?' + query, { method: 'GET', headers: { 'X-WP-Nonce': kasaAyar.nonce } });
                this.allItems = data.items || [];
                this.renderStats(data.stats || {});
                this.renderGeneral();
            } catch (e) {
                var list = document.getElementById('sevk-genel-listesi');
                if (list) list.innerHTML = '<tr><td colspan="6" class="sevk-empty">' + escapeHtml(e.message) + '</td></tr>';
            }
        },

        renderStats: function(stats) {
            var map = { total: 'sevk-stat-total', yolda: 'sevk-stat-yolda', bekleyen: 'sevk-stat-bekleyen', tamamlanan: 'sevk-stat-tamamlanan' };
            Object.keys(map).forEach(function(key) {
                var el = document.getElementById(map[key]);
                if (el) el.textContent = stats[key] || 0;
            });
        },

        renderGeneral: function() {
            var body = document.getElementById('sevk-genel-listesi');
            if (!body) return;
            if (!this.allItems.length) {
                body.innerHTML = '<tr><td colspan="6" class="sevk-empty">Kayıtlı sevk bulunamadı.</td></tr>';
                return;
            }
            var self = this;
            body.innerHTML = this.allItems.map(function(item) {
                return '<tr>' +
                    '<td><strong>' + escapeHtml(item.sevk_no) + '</strong></td>' +
                    '<td>' + escapeHtml(item.kaynak_depo_adi) + ' → ' + escapeHtml(item.hedef_depo_adi) + '</td>' +
                    '<td>' + statusBadge(item) + '</td>' +
                    '<td>' + escapeHtml(formatDate(item.created_at)) + '</td>' +
                    '<td>' + item.toplam_cesit + ' / ' + item.toplam_adet + '</td>' +
                    '<td><button type="button" class="sevk-btn secondary" data-open-detail="' + item.id + '">Detay</button></td>' +
                    '</tr>';
            }).join('');
            body.querySelectorAll('[data-open-detail]').forEach(function(btn) {
                btn.addEventListener('click', function() { self.openDetail(parseInt(btn.dataset.openDetail)); });
            });
        },

        loadIncoming: async function() {
            try {
                var data = await api('sevk/liste?scope=incoming', { method: 'GET', headers: { 'X-WP-Nonce': kasaAyar.nonce } });
                this.incoming = data.items || [];
                this.renderIncoming();
            } catch (e) {
                var list = document.getElementById('sevk-kabul-listesi');
                if (list) list.innerHTML = '<div class="sevk-empty-state">' + escapeHtml(e.message) + '</div>';
            }
        },

        renderIncoming: function() {
            var list = document.getElementById('sevk-kabul-listesi');
            if (!list) return;
            if (!this.incoming.length) {
                list.innerHTML = '<div class="sevk-empty-state"><p>Gelen sevk bulunamadı.</p></div>';
                return;
            }
            var self = this;
            list.innerHTML = this.incoming.map(function(item) {
                return '<div class="sevk-card-item" data-incoming-id="' + item.id + '">' +
                    '<span>' + escapeHtml(item.sevk_no) + '</span>' +
                    '<strong>' + escapeHtml(item.kaynak_depo_adi) + ' → ' + escapeHtml(item.hedef_depo_adi) + '</strong>' +
                    '<div class="sevk-card-meta"><span>' + statusBadge(item) + '</span><span>' + item.toplam_adet + ' adet</span></div>' +
                    '</div>';
            }).join('');
            list.querySelectorAll('[data-incoming-id]').forEach(function(card) {
                card.addEventListener('click', function() { self.loadIncomingDetail(parseInt(card.dataset.incomingId)); });
            });
        },

        loadIncomingDetail: async function(id) {
            try {
                var data = await api('sevk/detay/' + id, { method: 'GET', headers: { 'X-WP-Nonce': kasaAyar.nonce } });
                this.renderIncomingDetail(data.sevk);
            } catch (e) {
                toast(e.message, 'error');
            }
        },

        renderIncomingDetail: function(sevk) {
            var panel = document.getElementById('sevk-kabul-detay');
            if (!panel) return;
            var actions = '';
            if (sevk.durum === 'onay_bekliyor') {
                actions = '<textarea id="sevk-alici-not" class="hk-input" rows="2" placeholder="Alıcı notu / red sebebi"></textarea>' +
                    '<div class="sevk-summary-row"><button type="button" class="sevk-btn primary" data-action="accept">Onayla</button><button type="button" class="sevk-btn secondary" data-action="reject">Reddet</button></div>';
            } else if (['gonderildi', 'teslim_kontrol', 'uyusmazlik'].includes(sevk.durum)) {
                actions = '<input type="text" id="sevk-teslim-barkod" class="hk-input sevk-barcode-input" placeholder="Teslim barkodu okutun">' +
                    (sevk.durum === 'uyusmazlik' ? '<label style="display:flex; gap:8px; align-items:center; margin:10px 0;"><input type="checkbox" id="sevk-force-approve"> Uyuşmazlığa rağmen onayla</label>' : '') +
                    '<button type="button" class="sevk-btn primary" data-action="deliver">Teslim Onayla</button>';
            } else if (sevk.durum === 'onaylandi') {
                actions = '<p class="sevk-empty">Göndericinin yola çıkarma işlemi bekleniyor.</p>';
            }

            panel.innerHTML = '<div class="sevk-panel-title"><div><h3>' + escapeHtml(sevk.sevk_no) + '</h3><p>' + escapeHtml(sevk.kaynak_depo_adi) + ' → ' + escapeHtml(sevk.hedef_depo_adi) + '</p></div>' + statusBadge(sevk) + '</div>' +
                '<div class="sevk-table-wrap compact" style="margin:14px 0;">' + this.renderCompareTable(sevk.kalemler || []) + '</div>' +
                actions;

            var self = this;
            panel.querySelectorAll('[data-action]').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    self.handleIncomingAction(sevk, btn.dataset.action);
                });
            });
            var barcode = document.getElementById('sevk-teslim-barkod');
            if (barcode) {
                barcode.focus();
                barcode.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        var sku = barcode.value.trim();
                        barcode.value = '';
                        if (sku) self.scanDelivery(sevk.id, sku);
                    }
                });
            }
        },

        renderCompareTable: function(items) {
            if (!items.length) return '<div class="sevk-empty">Kalem yok.</div>';
            return '<table class="sevk-table"><thead><tr><th>Ürün</th><th>SKU</th><th>Gönderilen</th><th>Teslim</th></tr></thead><tbody>' +
                items.map(function(item) {
                    var received = item.teslim_alinan_adet == null ? 0 : item.teslim_alinan_adet;
                    var cls = received === item.gonderilen_adet ? 'sevk-compare-ok' : (received < item.gonderilen_adet ? 'sevk-compare-missing' : 'sevk-compare-extra');
                    var img = item.image ? '<img src="' + escapeHtml(item.image) + '" alt="">' : '<span></span>';
                    return '<tr class="' + cls + '"><td><div class="sevk-product-cell">' + img + '<strong>' + escapeHtml(item.urun_adi) + '</strong></div></td><td>' + escapeHtml(item.sku) + '</td><td>' + item.gonderilen_adet + '</td><td>' + received + '</td></tr>';
                }).join('') + '</tbody></table>';
        },

        handleIncomingAction: async function(sevk, action) {
            var note = document.getElementById('sevk-alici-not');
            var endpoint = action === 'accept' ? 'sevk/alici-onayla' : (action === 'reject' ? 'sevk/alici-reddet' : 'sevk/teslim-onayla');
            var payload = { sevk_id: sevk.id, not_alici: note ? note.value : '' };
            if (action === 'deliver') {
                var force = document.getElementById('sevk-force-approve');
                payload.force = force ? force.checked : false;
            }
            try {
                var data = await api(endpoint, { method: 'POST', body: JSON.stringify(payload) });
                toast('Sevk güncellendi.');
                this.renderIncomingDetail(data.sevk);
                this.loadIncoming();
                this.loadAll();
                this.updatePendingBadge();
            } catch (e) {
                toast(e.message, 'error');
            }
        },

        scanDelivery: async function(sevkId, sku) {
            try {
                var data = await api('sevk/teslim-barkod', { method: 'POST', body: JSON.stringify({ sevk_id: sevkId, sku: sku, qty: 1 }) });
                this.renderIncomingDetail(data.sevk);
            } catch (e) {
                toast(e.message, 'error');
            }
        },

        openDetail: async function(id) {
            try {
                var data = await api('sevk/detay/' + id, { method: 'GET', headers: { 'X-WP-Nonce': kasaAyar.nonce } });
                var modal = document.getElementById('sevk-detay-modal');
                var body = document.getElementById('sevk-modal-body');
                var sevk = data.sevk;
                var shipAction = sevk.durum === 'onaylandi'
                    ? '<div style="margin-top:14px;"><button type="button" class="sevk-btn primary" id="sevk-yola-cikart-btn">Gönder / Yola Çıkart</button></div>'
                    : '';
                body.innerHTML = '<h3>' + escapeHtml(sevk.sevk_no) + '</h3><p>' + escapeHtml(sevk.kaynak_depo_adi) + ' → ' + escapeHtml(sevk.hedef_depo_adi) + ' ' + statusBadge(sevk) + '</p><div class="sevk-table-wrap">' + this.renderCompareTable(sevk.kalemler || []) + '</div>' + shipAction;
                modal.style.display = 'flex';
                var shipBtn = document.getElementById('sevk-yola-cikart-btn');
                if (shipBtn) {
                    var self = this;
                    shipBtn.addEventListener('click', function() { self.shipSevk(sevk.id); });
                }
            } catch (e) {
                toast(e.message, 'error');
            }
        },

        shipSevk: async function(id) {
            try {
                await api('sevk/yola-cikart', { method: 'POST', body: JSON.stringify({ sevk_id: id }) });
                toast('Sevk yola çıkarıldı.');
                var modal = document.getElementById('sevk-detay-modal');
                if (modal) modal.style.display = 'none';
                this.loadAll();
                this.loadIncoming();
            } catch (e) {
                toast(e.message, 'error');
            }
        },

        updatePendingBadge: async function() {
            try {
                var data = await api('sevk/bekleyen-sayisi', { method: 'GET', headers: { 'X-WP-Nonce': kasaAyar.nonce } });
                var badge = document.getElementById('sevk-kabul-badge');
                if (badge) {
                    badge.textContent = data.count || 0;
                    badge.style.display = data.count > 0 ? 'inline-flex' : 'none';
                }
            } catch (e) {}
        },

        startPolling: function() {
            var self = this;
            this.updatePendingBadge();
            if (this.pollTimer) clearInterval(this.pollTimer);
            this.pollTimer = setInterval(function() { self.updatePendingBadge(); }, 30000);
        },

        bindImagePreview: function() {
            var self = this;
            var shell = document.querySelector('.sevk-shell');
            if (shell) {
                shell.addEventListener('click', function(e) {
                    var img = e.target.closest('.sevk-product-cell img');
                    if (img && img.src) {
                        e.stopPropagation();
                        e.preventDefault();
                        self.openImagePreview(img.src);
                    }
                });
            }
            var modal = document.getElementById('sevk-detay-modal');
            if (modal) {
                modal.addEventListener('click', function(e) {
                    var img = e.target.closest('.sevk-product-cell img');
                    if (img && img.src) {
                        e.stopPropagation();
                        e.preventDefault();
                        self.openImagePreview(img.src);
                    }
                });
            }
        },

        openImagePreview: function(src) {
            if (!src || src.includes('placeholder')) return;

            if (window.HizliKasa && window.HizliKasa.StockTerminal && typeof window.HizliKasa.StockTerminal.openImagePreview === 'function') {
                window.HizliKasa.StockTerminal.openImagePreview(src);
                return;
            }
            if (window.HizliKasa && window.HizliKasa.UIRenderer && typeof window.HizliKasa.UIRenderer.openImagePreview === 'function') {
                window.HizliKasa.UIRenderer.openImagePreview(src);
                return;
            }

            var modal = document.getElementById('terminal-image-preview-modal');
            if (!modal) {
                modal = document.createElement('div');
                modal.id = 'terminal-image-preview-modal';
                modal.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.85);z-index:999999;display:flex;align-items:center;justify-content:center;backdrop-filter:blur(5px);cursor:zoom-out;';
                
                var loader = document.createElement('div');
                loader.id = 'terminal-preview-loader';
                loader.style.cssText = 'position:absolute;width:40px;height:40px;border:4px solid #fff;border-top:4px solid transparent;border-radius:50%;animation:hk-spin 1s linear infinite;';
                
                if (!document.getElementById('hk-spin-keyframes')) {
                    var style = document.createElement('style');
                    style.id = 'hk-spin-keyframes';
                    style.innerHTML = '@keyframes hk-spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }';
                    document.head.appendChild(style);
                }

                var img = document.createElement('img');
                img.id = 'terminal-preview-img';
                img.style.cssText = 'max-width:90%;max-height:90%;object-fit:contain;border-radius:12px;opacity:0;transition:opacity 0.3s;box-shadow:0 10px 40px rgba(0,0,0,0.5);';
                
                modal.appendChild(loader);
                modal.appendChild(img);
                document.body.appendChild(modal);

                modal.addEventListener('click', function() {
                    modal.style.display = 'none';
                });
            }

            var imgEl = document.getElementById('terminal-preview-img');
            var loaderEl = document.getElementById('terminal-preview-loader');
            
            imgEl.style.opacity = '0';
            imgEl.src = '';
            loaderEl.style.display = 'block';
            modal.style.display = 'flex';
            
            var fullSrc = src.replace(/-\d+x\d+(\.[a-zA-Z]+)$/i, '$1');
            
            imgEl.onload = function() {
                loaderEl.style.display = 'none';
                imgEl.style.opacity = '1';
            };
            imgEl.onerror = function() {
                loaderEl.style.display = 'none';
                imgEl.style.opacity = '1';
                if (imgEl.src !== src) {
                    imgEl.src = src;
                }
            };
            
            imgEl.src = fullSrc;
        }
    };

})(window.HizliKasa = window.HizliKasa || {});
