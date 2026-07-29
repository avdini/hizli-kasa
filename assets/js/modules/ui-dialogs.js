/**
 * Hızlı Kasa - Bütünleşik UI, Asenkron Dialog & Toast Bildirim Motoru (HK.UI)
 *
 * @package HizliKasa
 * @version 2.0
 */

(function(window) {
    'use strict';

    // Global HK Namespace
    window.HK = window.HK || window.HizliKasa || {};
    window.HizliKasa = window.HK;

    var HKUI = {
        _activeResolver: null,
        _keyListenerBound: false,

        /**
         * DOM Eleman Referanslarını Al
         */
        _getElements: function() {
            var modal = document.getElementById('hk-global-dialog-modal');
            var toastContainer = document.getElementById('hk-toast-container');

            if (!modal && document.body) {
                var modalWrapper = document.createElement('div');
                modalWrapper.id = 'hk-global-dialog-modal';
                modalWrapper.className = 'modal-cerceve';
                modalWrapper.style.display = 'none';
                modalWrapper.style.zIndex = '12000';
                modalWrapper.innerHTML =
                    '<div class="modal-icerik modal-icerik-sm">' +
                    '    <h3 id="hk-global-dialog-title">📌 Uyarı</h3>' +
                    '    <p id="hk-global-dialog-message"></p>' +
                    '    <div id="hk-global-dialog-input-wrapper" style="display:none;">' +
                    '        <input type="text" id="hk-global-dialog-input" class="hk-input" autocomplete="off" />' +
                    '        <textarea id="hk-global-dialog-textarea" class="hk-input" rows="3" style="display:none;"></textarea>' +
                    '        <small id="hk-global-dialog-error" class="hk-input-error" style="display:none;"></small>' +
                    '    </div>' +
                    '    <div class="modal-butonlar">' +
                    '        <button id="hk-global-dialog-cancel" class="modal-btn-cancel">Vazgeç</button>' +
                    '        <button id="hk-global-dialog-confirm" class="hk-btn-primary">Onayla</button>' +
                    '    </div>' +
                    '</div>';
                document.body.appendChild(modalWrapper);
                modal = modalWrapper;
            }

            if (!toastContainer && document.body) {
                toastContainer = document.createElement('div');
                toastContainer.id = 'hk-toast-container';
                toastContainer.className = 'hk-toast-stack-container';
                document.body.appendChild(toastContainer);
            }

            return {
                modal: modal,
                title: document.getElementById('hk-global-dialog-title'),
                message: document.getElementById('hk-global-dialog-message'),
                inputWrapper: document.getElementById('hk-global-dialog-input-wrapper'),
                input: document.getElementById('hk-global-dialog-input'),
                textarea: document.getElementById('hk-global-dialog-textarea'),
                error: document.getElementById('hk-global-dialog-error'),
                confirmBtn: document.getElementById('hk-global-dialog-confirm'),
                cancelBtn: document.getElementById('hk-global-dialog-cancel'),
                toastContainer: toastContainer
            };
        },

        /**
         * Sound Manager Entegrasyonu ile ses çal
         */
        _playSound: function(soundType) {
            if (window.HK && window.HK.SoundManager && typeof window.HK.SoundManager.play === 'function') {
                try {
                    window.HK.SoundManager.play(soundType);
                } catch (e) {
                    console.warn('[HK.UI] Sound error:', e);
                }
            }
        },

        /**
         * Modal Sıfırla ve Temizle
         */
        _resetModal: function(els) {
            if (!els.modal) return;
            els.modal.classList.remove('hk-modal-show');
            els.modal.style.display = 'none';
            els.inputWrapper.style.display = 'none';
            els.input.style.display = 'none';
            els.textarea.style.display = 'none';
            els.error.style.display = 'none';
            els.input.value = '';
            els.textarea.value = '';
            els.error.innerText = '';
            els.cancelBtn.style.display = 'inline-block';
            els.cancelBtn.innerText = 'Vazgeç';
            els.confirmBtn.innerText = 'Onayla';
            els.confirmBtn.className = 'hk-btn-primary';

            if (this._activeResolver) {
                this._activeResolver = null;
            }
        },

        /**
         * Keydown (Enter/Escape) Kısayol Dinleyicisi
         */
        _bindKeyboardEvents: function(els, onConfirm, onCancel) {
            var self = this;
            var handler = function(e) {
                if (els.modal.style.display === 'none' || !els.modal.classList.contains('hk-modal-show')) {
                    document.removeEventListener('keydown', handler);
                    return;
                }

                if (e.key === 'Escape') {
                    e.preventDefault();
                    document.removeEventListener('keydown', handler);
                    onCancel();
                } else if (e.key === 'Enter' && e.target.tagName !== 'TEXTAREA') {
                    e.preventDefault();
                    document.removeEventListener('keydown', handler);
                    onConfirm();
                }
            };
            document.addEventListener('keydown', handler);
        },

        // ==========================================================================
        // 1. INTERACTIVE DIALOG MODALS (alert, confirm, prompt)
        // ==========================================================================

        /**
         * Bilgilendirme / Hata Modalı (alert replacement)
         *
         * @param {Object|String} options
         * @returns {Promise<void>}
         */
        alert: function(options, titleParam) {
            var self = this;
            if (typeof options === 'string') {
                options = { message: options, title: titleParam || '📌 Bilgilendirme' };
            }
            options = options || {};
            var title = options.title || '📌 Bilgilendirme';
            var message = options.message || '';
            var type = options.type || 'info';
            var confirmText = options.confirmText || 'Tamam';

            return new Promise(function(resolve) {
                var els = self._getElements();
                if (!els.modal) {
                    console.error('[HK.UI] #hk-global-dialog-modal DOM element not found');
                    resolve();
                    return;
                }

                self._resetModal(els);

                els.title.innerText = title;
                els.message.innerHTML = message;
                els.cancelBtn.style.display = 'none';
                els.confirmBtn.innerText = confirmText;

                if (type === 'error') {
                    els.confirmBtn.className = 'hk-btn-danger';
                    self._playSound('high_alert');
                } else if (type === 'warning') {
                    els.confirmBtn.className = 'hk-btn-warning';
                    self._playSound('high_alert');
                } else {
                    els.confirmBtn.className = 'hk-btn-primary';
                    self._playSound('soft');
                }

                var cleanup = function() {
                    els.confirmBtn.onclick = null;
                    self._resetModal(els);
                    resolve();
                };

                els.confirmBtn.onclick = cleanup;
                self._bindKeyboardEvents(els, cleanup, cleanup);

                els.modal.style.display = 'flex';
                setTimeout(function() {
                    els.modal.classList.add('hk-modal-show');
                    els.confirmBtn.focus();
                }, 20);
            });
        },

        /**
         * İşlem Onay Modalı (confirm replacement)
         *
         * @param {Object|String} options
         * @returns {Promise<Boolean>}
         */
        confirm: function(options) {
            var self = this;
            if (typeof options === 'string') {
                options = { message: options };
            }
            options = options || {};
            var title = options.title || '❓ İşlem Onayı';
            var message = options.message || 'Bu işlemi onaylıyor musunuz?';
            var confirmText = options.confirmText || 'Evet, Onayla';
            var cancelText = options.cancelText || 'Vazgeç';
            var type = options.type || 'warning';

            return new Promise(function(resolve) {
                var els = self._getElements();
                if (!els.modal) {
                    console.error('[HK.UI] #hk-global-dialog-modal DOM element not found');
                    resolve(false);
                    return;
                }

                self._resetModal(els);

                els.title.innerText = title;
                els.message.innerHTML = message;
                els.cancelBtn.innerText = cancelText;
                els.confirmBtn.innerText = confirmText;

                if (type === 'danger' || type === 'error') {
                    els.confirmBtn.className = 'hk-btn-danger';
                    self._playSound('high_alert');
                } else {
                    els.confirmBtn.className = 'hk-btn-primary';
                    self._playSound('soft');
                }

                var handleConfirm = function() {
                    self._playSound('sharp_click');
                    els.confirmBtn.onclick = null;
                    els.cancelBtn.onclick = null;
                    self._resetModal(els);
                    resolve(true);
                };

                var handleCancel = function() {
                    els.confirmBtn.onclick = null;
                    els.cancelBtn.onclick = null;
                    self._resetModal(els);
                    resolve(false);
                };

                els.confirmBtn.onclick = handleConfirm;
                els.cancelBtn.onclick = handleCancel;
                self._bindKeyboardEvents(els, handleConfirm, handleCancel);

                els.modal.style.display = 'flex';
                setTimeout(function() {
                    els.modal.classList.add('hk-modal-show');
                    els.confirmBtn.focus();
                }, 20);
            });
        },

        /**
         * Girdi / Metin Alma Modalı (prompt replacement)
         *
         * @param {Object|String} options
         * @returns {Promise<String|null>}
         */
        prompt: function(options, defaultValueParam) {
            var self = this;
            if (typeof options === 'string') {
                options = { message: options, defaultValue: defaultValueParam || '' };
            }
            options = options || {};
            var title = options.title || '✏️ Metin Girişi';
            var message = options.message || 'Lütfen bilgileri giriniz:';
            var placeholder = options.placeholder || '';
            var defaultValue = options.defaultValue || '';
            var inputType = options.inputType || 'text'; // 'text' | 'number' | 'textarea'
            var required = options.required !== false;
            var confirmText = options.confirmText || 'Tamam';
            var cancelText = options.cancelText || 'Vazgeç';

            return new Promise(function(resolve) {
                var els = self._getElements();
                if (!els.modal) {
                    console.error('[HK.UI] #hk-global-dialog-modal DOM element not found');
                    resolve(null);
                    return;
                }

                self._resetModal(els);

                els.title.innerText = title;
                els.message.innerHTML = message;
                els.cancelBtn.innerText = cancelText;
                els.confirmBtn.innerText = confirmText;
                els.inputWrapper.style.display = 'block';

                var activeInput = (inputType === 'textarea') ? els.textarea : els.input;
                activeInput.style.display = 'block';
                if (inputType === 'number') {
                    activeInput.type = 'number';
                } else if (inputType === 'text') {
                    activeInput.type = 'text';
                }
                activeInput.placeholder = placeholder;
                activeInput.value = defaultValue;

                self._playSound('soft');

                var handleConfirm = function() {
                    var val = activeInput.value.trim();
                    if (required && val === '') {
                        els.error.innerText = 'Bu alan zorunludur.';
                        els.error.style.display = 'block';
                        activeInput.focus();
                        return;
                    }
                    self._playSound('sharp_click');
                    els.confirmBtn.onclick = null;
                    els.cancelBtn.onclick = null;
                    self._resetModal(els);
                    resolve(val);
                };

                var handleCancel = function() {
                    els.confirmBtn.onclick = null;
                    els.cancelBtn.onclick = null;
                    self._resetModal(els);
                    resolve(null);
                };

                els.confirmBtn.onclick = handleConfirm;
                els.cancelBtn.onclick = handleCancel;
                self._bindKeyboardEvents(els, handleConfirm, handleCancel);

                els.modal.style.display = 'flex';
                setTimeout(function() {
                    els.modal.classList.add('hk-modal-show');
                    activeInput.focus();
                }, 20);
            });
        },

        // ==========================================================================
        // 2. NON-BLOCKING FLOATING TOAST NOTIFICATIONS
        // ==========================================================================

        /**
         * Yüzen Baloncuk Bildirimi (Toast)
         *
         * @param {Object|String} options
         * @returns {Object} { close: Function }
         */
        toast: function(options) {
            var self = this;
            if (typeof options === 'string') {
                options = { message: options };
            }
            options = options || {};

            var message = options.message || '';
            var title = options.title || '';
            var type = options.type || 'info'; // 'success' | 'error' | 'warning' | 'info'
            var autoClose = options.autoClose !== false;
            var duration = options.duration || 4000;
            var actions = options.actions || null;

            var els = this._getElements();
            var container = els.toastContainer;

            if (!container) {
                container = document.createElement('div');
                container.id = 'hk-toast-container';
                document.body.appendChild(container);
            }

            var iconMap = {
                success: '✅',
                error: '❌',
                warning: '⚠️',
                info: 'ℹ️'
            };

            var toastEl = document.createElement('div');
            toastEl.className = 'hk-toast-item hk-toast-' + type;

            var iconEl = document.createElement('span');
            iconEl.className = 'hk-toast-icon';
            iconEl.innerText = iconMap[type] || 'ℹ️';
            toastEl.appendChild(iconEl);

            var contentEl = document.createElement('div');
            contentEl.className = 'hk-toast-content';

            if (title) {
                var titleEl = document.createElement('div');
                titleEl.className = 'hk-toast-title';
                titleEl.innerText = title;
                contentEl.appendChild(titleEl);
            }

            var msgEl = document.createElement('div');
            msgEl.className = 'hk-toast-message';
            msgEl.innerHTML = message;
            contentEl.appendChild(msgEl);

            // Action Buttons (if any)
            if (actions && Array.isArray(actions)) {
                var actionsEl = document.createElement('div');
                actionsEl.className = 'hk-toast-actions';
                actions.forEach(function(act) {
                    var btn = document.createElement('button');
                    btn.className = act.class || 'hk-btn-sm-primary';
                    btn.innerText = act.label || 'Tamam';
                    btn.onclick = function() {
                        if (typeof act.onClick === 'function') {
                            act.onClick({ close: closeToast });
                        } else {
                            closeToast();
                        }
                    };
                    actionsEl.appendChild(btn);
                });
                contentEl.appendChild(actionsEl);
            }

            toastEl.appendChild(contentEl);

            // Close button
            var closeBtn = document.createElement('button');
            closeBtn.className = 'hk-toast-close';
            closeBtn.innerHTML = '&times;';
            closeBtn.onclick = function() { closeToast(); };
            toastEl.appendChild(closeBtn);

            container.appendChild(toastEl);

            // Sound feedback
            if (type === 'success') self._playSound('soft');
            else if (type === 'error' || type === 'warning') self._playSound('high_alert');

            var timer = null;
            var closeToast = function() {
                if (timer) clearTimeout(timer);
                toastEl.classList.add('hk-toast-hiding');
                setTimeout(function() {
                    if (toastEl.parentNode) {
                        toastEl.parentNode.removeChild(toastEl);
                    }
                }, 300);
            };

            if (autoClose) {
                timer = setTimeout(closeToast, duration);
            }

            return { close: closeToast };
        },

        /**
         * Aksiyon Kartı Toast Kısayolu
         */
        toastCard: function(options) {
            options = options || {};
            options.variant = 'card';
            options.autoClose = options.autoClose === true; // Default false for action cards
            return this.toast(options);
        },

        // ==========================================================================
        // 3. NOTIFICATION BADGE STATE MANAGEMENT
        // ==========================================================================

        badge: {
            set: function(count) {
                var badges = document.querySelectorAll('.hk-notification-badge, .update-plugins.count-unmatched');
                badges.forEach(function(el) {
                    el.innerText = count;
                    el.style.display = count > 0 ? 'inline-block' : 'none';
                });
            },
            increment: function(step) {
                step = step || 1;
                var el = document.querySelector('.hk-notification-badge');
                if (el) {
                    var current = parseInt(el.innerText, 10) || 0;
                    this.set(current + step);
                }
            },
            decrement: function(step) {
                step = step || 1;
                var el = document.querySelector('.hk-notification-badge');
                if (el) {
                    var current = parseInt(el.innerText, 10) || 0;
                    this.set(Math.max(0, current - step));
                }
            }
        }
    };

    // Expose HK.UI and HizliKasa.UI
    window.HK = window.HK || {};
    window.HizliKasa = window.HizliKasa || window.HK;
    window.HK.UI = HKUI;
    window.HizliKasa.UI = HKUI;

})(window);
