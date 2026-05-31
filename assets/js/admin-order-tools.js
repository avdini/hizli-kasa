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
            })
        };
    }

    function setMessage(root, text, type) {
        var message = qs('#hk-aot-message', root);
        message.className = type ? 'is-' + type : '';
        message.textContent = text || '';
    }

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
})();
