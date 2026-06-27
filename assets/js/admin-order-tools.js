(function() {
    'use strict';

    function qs(selector, root) {
        return (root || document).querySelector(selector);
    }

    function qsa(selector, root) {
        return Array.prototype.slice.call((root || document).querySelectorAll(selector));
    }

    function makeRow(className, html) {
        var div = document.createElement('div');
        div.className = className;
        div.innerHTML = html;
        return div;
    }

    function escapeHtml(value) {
        return String(value || '').replace(/[&<>"']/g, function(char) {
            return {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            }[char];
        });
    }

    function makeMetaRow(key, value) {
        return makeRow('hk-aot-meta-row hk-aot-new-meta', [
            '<input type="text" class="hk-aot-meta-key" value="' + escapeHtml(key) + '" placeholder="meta_key">',
            '<textarea class="hk-aot-meta-value" rows="2">' + escapeHtml(value) + '</textarea>',
            '<button type="button" class="button hk-aot-remove-row">Kaldir</button>'
        ].join(''));
    }

    // ===== Siparis Bilgileri: Odeme yontemi degisikligi =====
    function handlePaymentMethodChange(root) {
        var select = qs('#hk-oi-payment-method', root);
        if (!select) return;

        var method = select.value;
        var orderTotal = parseFloat(qs('#hk-oi-order-total', root).value) || 0;
        var nakitInput = qs('#hk-oi-nakit', root);
        var kartInput = qs('#hk-oi-kart', root);
        var ibanInput = qs('#hk-oi-iban', root);
        var hint = qs('#hk-oi-payment-hint', root);

        if (method === 'split') {
            // Bolunmus odeme: tum alanlar duzenlenebilir
            nakitInput.removeAttribute('readonly');
            kartInput.removeAttribute('readonly');
            ibanInput.removeAttribute('readonly');
            if (hint) hint.textContent = 'Bolunmus odeme — tutarlari asagidan duzenleyebilirsiniz.';
        } else {
            // Tek kanal: otomatik hesapla ve kilitle
            nakitInput.setAttribute('readonly', 'readonly');
            kartInput.setAttribute('readonly', 'readonly');
            ibanInput.setAttribute('readonly', 'readonly');

            nakitInput.value = '0.00';
            kartInput.value = '0.00';
            ibanInput.value = '0.00';

            if (method === 'cod') {
                nakitInput.value = orderTotal.toFixed(2);
            } else if (method === 'other') {
                kartInput.value = orderTotal.toFixed(2);
            } else if (method === 'bacs') {
                ibanInput.value = orderTotal.toFixed(2);
            }

            if (hint) hint.textContent = 'Yontem degistirildiginde tutarlar otomatik guncellenir.';
        }
    }

    // ===== Siparis Bilgileri: Depo degisikligi =====
    function handleDepoChange(root) {
        var select = qs('#hk-oi-depo', root);
        var depoAdiInput = qs('#hk-oi-depo-adi', root);
        if (!select || !depoAdiInput) return;

        var selectedOption = select.options[select.selectedIndex];
        var depoAdi = selectedOption && selectedOption.value ? selectedOption.getAttribute('data-name') || selectedOption.text : '';
        depoAdiInput.value = depoAdi;
    }

    // ===== Siparis Bilgileri: order_info payload =====
    function collectOrderInfo(root) {
        var depoSelect = qs('#hk-oi-depo', root);
        var depoOption = depoSelect ? depoSelect.options[depoSelect.selectedIndex] : null;

        return {
            kasiyer: (qs('#hk-oi-kasiyer', root) || {}).value || '',
            kasa_no: (qs('#hk-oi-kasa-no', root) || {}).value || '',
            kaynak: (qs('#hk-oi-kaynak', root) || {}).value || '',
            rapor_kaynak: (qs('#hk-oi-rapor-kaynak', root) || {}).value || '',
            telefon: (qs('#hk-oi-telefon', root) || {}).value || '',
            note: (qs('#hk-oi-note', root) || {}).value || '',
            payment_method: (qs('#hk-oi-payment-method', root) || {}).value || '',
            odeme_nakit: (qs('#hk-oi-nakit', root) || {}).value || '0',
            odeme_kart: (qs('#hk-oi-kart', root) || {}).value || '0',
            odeme_iban: (qs('#hk-oi-iban', root) || {}).value || '0',
            depo_id: depoSelect ? depoSelect.value : '',
            depo_adi: depoOption && depoOption.value ? (depoOption.getAttribute('data-name') || depoOption.text) : '',
            original_order: (qs('#hk-oi-orijinal-siparis', root) || {}).value || '',
            toplam_iskonto: (qs('#hk-oi-toplam-iskonto', root) || {}).value || '0',
            date_created: (qs('#hk-oi-date-created', root) || {}).value || ''
        };
    }

    function collectPayload(root) {
        return {
            order_id: root.getAttribute('data-order-id'),
            recalculate: qs('#hk-aot-recalculate', root).checked ? 1 : 0,
            items: qsa('#hk-aot-items tr', root).map(function(row) {
                return {
                    id: row.getAttribute('data-item-id'),
                    qty: qs('.hk-aot-qty', row).value,
                    subtotal: qs('.hk-aot-subtotal', row).value,
                    total: qs('.hk-aot-total', row).value,
                    item_meta: qs('.hk-aot-item-meta', row).value,
                    remove: qs('.hk-aot-remove', row).checked ? 1 : 0
                };
            }),
            new_products: qsa('.hk-aot-new-product', root).map(function(row) {
                return {
                    product_id: qs('.hk-aot-new-product-id', row).value,
                    qty: qs('.hk-aot-new-product-qty', row).value,
                    total: qs('.hk-aot-new-product-total', row).value
                };
            }),
            fees: qsa('[data-fee-id]', root).map(function(row) {
                return {
                    id: row.getAttribute('data-fee-id'),
                    name: qs('.hk-aot-fee-name', row).value,
                    total: qs('.hk-aot-fee-total', row).value,
                    remove: qs('.hk-aot-fee-remove', row).checked ? 1 : 0
                };
            }),
            new_fees: qsa('.hk-aot-new-fee', root).map(function(row) {
                return {
                    name: qs('.hk-aot-fee-name', row).value,
                    total: qs('.hk-aot-fee-total', row).value
                };
            }),
            shipping: qsa('[data-shipping-id]', root).map(function(row) {
                return {
                    id: row.getAttribute('data-shipping-id'),
                    title: qs('.hk-aot-shipping-title', row).value,
                    total: qs('.hk-aot-shipping-total', row).value,
                    remove: qs('.hk-aot-shipping-remove', row).checked ? 1 : 0
                };
            }),
            meta: qsa('[data-meta-id]', root).map(function(row) {
                return {
                    id: row.getAttribute('data-meta-id'),
                    key: qs('.hk-aot-meta-key', row).value,
                    value: qs('.hk-aot-meta-value', row).value,
                    remove: qs('.hk-aot-meta-remove', row).checked ? 1 : 0
                };
            }),
            new_meta: qsa('.hk-aot-new-meta', root).map(function(row) {
                return {
                    key: qs('.hk-aot-meta-key', row).value,
                    value: qs('.hk-aot-meta-value', row).value
                };
            }),
            order_info: collectOrderInfo(root)
        };
    }

    function setMessage(root, text, type) {
        var message = qs('#hk-aot-message', root);
        message.className = type ? 'is-' + type : '';
        message.textContent = text || '';
    }

    // ===== Change event handler (odeme yontemi + depo) =====
    document.addEventListener('change', function(event) {
        var root = event.target.closest('.hk-admin-order-tools');
        if (!root) return;

        if (event.target.id === 'hk-oi-payment-method') {
            handlePaymentMethodChange(root);
            return;
        }

        if (event.target.id === 'hk-oi-depo') {
            handleDepoChange(root);
            return;
        }
    });

    // ===== Click event handler =====
    document.addEventListener('click', function(event) {
        var root = event.target.closest('.hk-admin-order-tools');
        if (!root) {
            return;
        }

        var tab = event.target.closest('[data-hk-aot-tab]');
        if (tab) {
            qsa('[data-hk-aot-tab]', root).forEach(function(btn) {
                btn.classList.toggle('is-active', btn === tab);
            });
            qsa('[data-hk-aot-panel]', root).forEach(function(panel) {
                panel.classList.toggle('is-active', panel.getAttribute('data-hk-aot-panel') === tab.getAttribute('data-hk-aot-tab'));
            });
            return;
        }

        if (event.target.id === 'hk-aot-add-product') {
            var productId = qs('#hk-aot-add-product-id', root).value;
            if (!productId) {
                return;
            }
            qs('#hk-aot-new-products', root).appendChild(makeRow('hk-aot-grid-row hk-aot-new-product', [
                '<input type="number" class="hk-aot-new-product-id" value="' + productId + '" min="1">',
                '<input type="number" class="hk-aot-new-product-qty" value="' + (qs('#hk-aot-add-product-qty', root).value || 1) + '" min="1" step="1">',
                '<input type="number" class="hk-aot-new-product-total" value="' + qs('#hk-aot-add-product-total', root).value + '" step="0.01" placeholder="Toplam">',
                '<button type="button" class="button hk-aot-remove-row">Kaldir</button>'
            ].join('')));
            qs('#hk-aot-add-product-id', root).value = '';
            qs('#hk-aot-add-product-total', root).value = '';
            return;
        }

        if (event.target.id === 'hk-aot-add-fee') {
            qs('#hk-aot-fees', root).appendChild(makeRow('hk-aot-grid-row hk-aot-new-fee', [
                '<input type="text" class="hk-aot-fee-name" placeholder="Ad">',
                '<input type="number" step="0.01" class="hk-aot-fee-total" placeholder="Toplam">',
                '<button type="button" class="button hk-aot-remove-row">Kaldir</button>'
            ].join('')));
            return;
        }

        if (event.target.id === 'hk-aot-add-meta') {
            qs('#hk-aot-meta-list', root).appendChild(makeMetaRow('', ''));
            return;
        }

        if (event.target.id === 'hk-aot-add-selected-meta') {
            var select = qs('#hk-aot-meta-template', root);
            var option = select.options[select.selectedIndex];
            if (!select.value || !option) {
                return;
            }
            qs('#hk-aot-meta-list', root).appendChild(makeMetaRow(select.value, option.getAttribute('data-default') || ''));
            select.value = '';
            return;
        }

        if (event.target.classList.contains('hk-aot-remove-row')) {
            event.target.closest('.hk-aot-grid-row, .hk-aot-meta-row').remove();
            return;
        }

        if (event.target.id === 'hk-aot-save') {
            if (!window.confirm(hkAdminOrderTools.labels.confirm)) {
                return;
            }

            var button = event.target;
            var oldText = button.textContent;
            button.disabled = true;
            button.textContent = hkAdminOrderTools.labels.saving;
            setMessage(root, '', '');

            var body = new URLSearchParams();
            body.set('action', 'hk_admin_order_tools_save');
            body.set('nonce', hkAdminOrderTools.nonce);
            body.set('payload', JSON.stringify(collectPayload(root)));

            fetch(hkAdminOrderTools.ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                },
                body: body.toString()
            })
                .then(function(response) {
                    return response.json();
                })
                .then(function(result) {
                    if (!result || !result.success) {
                        throw new Error(result && result.data && result.data.message ? result.data.message : hkAdminOrderTools.labels.error);
                    }
                    setMessage(root, result.data.message || hkAdminOrderTools.labels.saved, 'success');
                    setTimeout(function() {
                        window.location.reload();
                    }, 1000);
                })
                .catch(function(error) {
                    setMessage(root, error.message || hkAdminOrderTools.labels.error, 'error');
                })
                .finally(function() {
                    button.disabled = false;
                    button.textContent = oldText;
                });
        }
    });

    function initializeItemMetaEditors(root) {
        var rows = qsa('#hk-aot-items tr', root);
        rows.forEach(function(row) {
            var textarea = qs('.hk-aot-item-meta', row);
            if (!textarea || textarea.dataset.initialized) return;
            textarea.dataset.initialized = '1';

            // Hide the raw JSON textarea
            textarea.style.display = 'none';

            // Create visual editor container
            var editor = document.createElement('div');
            editor.className = 'hk-aot-item-meta-editor';

            // Parse initial JSON
            var metaArray = [];
            try {
                metaArray = JSON.parse(textarea.value || '[]');
            } catch (e) {
                metaArray = [];
            }
            if (!Array.isArray(metaArray)) {
                metaArray = [];
            }

            var depoId = '';
            var discount = '';
            metaArray.forEach(function(m) {
                if (m.key === '_hk_cikis_depo_id') depoId = String(m.value || '');
                if (m.key === '_hk_item_discount') discount = String(m.value || '');
            });

            // Build warehouse options
            var depoHtml = '<option value="">Depo Seçiniz</option>';
            if (hkAdminOrderTools.depolar && Array.isArray(hkAdminOrderTools.depolar)) {
                hkAdminOrderTools.depolar.forEach(function(depo) {
                    var selected = (String(depo.id) === depoId) ? 'selected' : '';
                    depoHtml += '<option value="' + depo.id + '" data-name="' + escapeHtml(depo.name) + '" ' + selected + '>' + escapeHtml(depo.name) + '</option>';
                });
            }

            editor.innerHTML = [
                '<div class="hk-aot-ime-row">',
                '  <div class="hk-aot-ime-group">',
                '    <label>Depo</label>',
                '    <select class="hk-aot-ime-depo">' + depoHtml + '</select>',
                '  </div>',
                '  <div class="hk-aot-ime-group">',
                '    <label>İskonto (₺)</label>',
                '    <input type="number" step="0.01" min="0" class="hk-aot-ime-discount" value="' + escapeHtml(discount) + '" placeholder="0.00">',
                '  </div>',
                '  <div class="hk-aot-ime-group inline-btn">',
                '    <button type="button" class="button button-small hk-aot-ime-toggle-advanced" title="Gelişmiş JSON Metaları Düzenle">⚙️ Gelişmiş</button>',
                '  </div>',
                '</div>'
            ].join('');

            textarea.parentNode.appendChild(editor);

            var depoSelect = qs('.hk-aot-ime-depo', editor);
            var discountInput = qs('.hk-aot-ime-discount', editor);
            var advancedBtn = qs('.hk-aot-ime-toggle-advanced', editor);

            function syncToTextarea() {
                var currentDepoId = depoSelect.value;
                var currentDepoOption = depoSelect.options[depoSelect.selectedIndex];
                var currentDepoName = currentDepoOption && currentDepoOption.value ? currentDepoOption.getAttribute('data-name') : '';
                var currentDiscount = discountInput.value;

                var textArray = [];
                try {
                    textArray = JSON.parse(textarea.value || '[]');
                } catch(e) {
                    textArray = [];
                }
                if (!Array.isArray(textArray)) {
                    textArray = [];
                }

                // Filter out standard keys
                textArray = textArray.filter(function(item) {
                    return item.key !== '_hk_cikis_depo_id' && item.key !== '_hk_cikis_depo_adi' && item.key !== '_hk_item_discount';
                });

                // Add values back if they exist
                if (currentDepoId) {
                    textArray.push({ key: '_hk_cikis_depo_id', value: currentDepoId });
                    if (currentDepoName) {
                        textArray.push({ key: '_hk_cikis_depo_adi', value: currentDepoName });
                    }
                }
                if (currentDiscount && parseFloat(currentDiscount) > 0) {
                    textArray.push({ key: '_hk_item_discount', value: parseFloat(currentDiscount).toFixed(2) });
                }

                textarea.value = JSON.stringify(textArray, null, 4);
            }

            function syncToUI() {
                var textArray = [];
                try {
                    textArray = JSON.parse(textarea.value || '[]');
                } catch(e) {
                    textArray = [];
                }
                if (!Array.isArray(textArray)) {
                    textArray = [];
                }

                var dId = '';
                var disc = '';
                textArray.forEach(function(item) {
                    if (item.key === '_hk_cikis_depo_id') dId = String(item.value || '');
                    if (item.key === '_hk_item_discount') disc = String(item.value || '');
                });

                depoSelect.value = dId;
                discountInput.value = disc;
            }

            depoSelect.addEventListener('change', syncToTextarea);
            discountInput.addEventListener('input', syncToTextarea);
            textarea.addEventListener('input', syncToUI);

            advancedBtn.addEventListener('click', function() {
                if (textarea.style.display === 'none') {
                    textarea.style.display = 'block';
                    advancedBtn.textContent = 'Gizle';
                } else {
                    textarea.style.display = 'none';
                    advancedBtn.textContent = '⚙️ Gelişmiş';
                }
            });
        });
    }

    function init() {
        var root = qs('.hk-admin-order-tools');
        if (root) {
            initializeItemMetaEditors(root);
            handlePaymentMethodChange(root);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
