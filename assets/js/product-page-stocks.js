jQuery(document).ready(function ($) {
    var $container = $('#hk-product-warehouse-stocks-container');
    if (!$container.length || typeof hk_product_stocks_obj === 'undefined') {
        return;
    }

    var i18n = hk_product_stocks_obj.i18n;

    $container.html(
        '<div class="hk-product-stocks-wrapper">' +
            '<div class="hk-product-stocks-loading">' +
                '<div class="hk-product-stocks-spinner"></div>' +
                '<span>' + i18n.loading + '</span>' +
            '</div>' +
        '</div>'
    );

    $.ajax({
        url: hk_product_stocks_obj.ajax_url,
        type: 'POST',
        data: {
            action: 'hk_get_product_warehouse_stocks',
            nonce: hk_product_stocks_obj.nonce,
            product_id: $container.data('product-id') || hk_product_stocks_obj.product_id
        },
        success: function (response) {
            if (!response.success) {
                $container.html('<div class="hk-product-stocks-wrapper"><div class="hk-product-stocks-no-data">' + response.data + '</div></div>');
                return;
            }

            var data = response.data;
            if (!data.warehouses || data.warehouses.length === 0) {
                $container.html('');
                return;
            }

            renderStocksTable(data);
        },
        error: function () {
            $container.html('<div class="hk-product-stocks-wrapper"><div class="hk-product-stocks-no-data">' + i18n.no_permission + '</div></div>');
        }
    });

    function getStockPillClass(avail) {
        if (avail <= 0) {
            return 'out-of-stock';
        } else if (avail <= 5) {
            return 'low-stock';
        } else {
            return 'in-stock';
        }
    }

    function renderStocksTable(data) {
        var html = '<div class="hk-product-stocks-wrapper">';
        
        // Header
        html += '<div class="hk-product-stocks-header">';
        html += '<h4 class="hk-product-stocks-title">📦 ' + i18n.title + '</h4>';
        html += '<span class="hk-product-stocks-badge">🛡️ ' + i18n.badge + '</span>';
        html += '</div>';

        // Search box for variable products
        if (data.is_variable && data.variations.length > 1) {
            html += '<div class="hk-product-stocks-search-wrapper">';
            html += '<input type="text" class="hk-product-stocks-search" placeholder="' + i18n.search_placeholder + '">';
            html += '</div>';
        }

        html += '<div class="hk-product-stocks-table-wrapper">';
        html += '<table class="hk-product-stocks-table">';

        if (data.is_variable) {
            // Matrix layout
            html += '<thead><tr>';
            html += '<th>' + i18n.th_variation + '</th>';
            $.each(data.warehouses, function (i, wh) {
                html += '<th>' + escHtml(wh.name) + '</th>';
            });
            html += '</tr></thead>';
            html += '<tbody>';

            $.each(data.variations, function (i, v) {
                html += '<tr class="hk-variation-row" data-search-name="' + escHtml(v.name.toLowerCase()) + '">';
                html += '<td><strong>' + escHtml(v.name) + '</strong></td>';

                $.each(data.warehouses, function (j, wh) {
                    var st = v.stocks[wh.id] || { qty: 0.0, res: 0.0, avail: 0.0 };
                    var avail = parseFloat(st.avail);
                    var qty = parseFloat(st.qty);
                    var res = parseFloat(st.res);
                    var pillClass = getStockPillClass(avail);

                    html += '<td>';
                    html += '<span class="hk-stock-pill ' + pillClass + '">' + avail.toFixed(0) + '</span>';
                    if (res > 0) {
                        html += ' <span class="hk-stock-pill reserved" title="Rezerve: ' + res.toFixed(0) + ' (Fiziksel: ' + qty.toFixed(0) + ')">🔒' + res.toFixed(0) + '</span>';
                    }
                    html += '</td>';
                });

                html += '</tr>';
            });

            html += '</tbody>';
        } else {
            // Simple product layout (Vertical list)
            html += '<thead><tr>';
            html += '<th>' + i18n.th_variation + ' / ' + i18n.th_variation.replace('Varyasyon', 'Depo Adı') + '</th>';
            html += '<th>' + i18n.th_physical + '</th>';
            html += '<th>' + i18n.th_reserved + '</th>';
            html += '<th>' + i18n.th_net + '</th>';
            html += '</tr></thead>';
            html += '<tbody>';

            var v = data.variations[0];
            $.each(data.warehouses, function (i, wh) {
                var st = v.stocks[wh.id] || { qty: 0.0, res: 0.0, avail: 0.0 };
                var avail = parseFloat(st.avail);
                var qty = parseFloat(st.qty);
                var res = parseFloat(st.res);
                var pillClass = getStockPillClass(avail);

                html += '<tr>';
                html += '<td><strong>' + escHtml(wh.name) + '</strong></td>';
                html += '<td>' + qty.toFixed(0) + '</td>';
                html += '<td>' + res.toFixed(0) + '</td>';
                html += '<td><span class="hk-stock-pill ' + pillClass + '">' + avail.toFixed(0) + '</span></td>';
                html += '</tr>';
            });

            html += '</tbody>';
        }

        html += '</table>';
        html += '</div>'; // table-wrapper
        html += '</div>'; // wrapper

        $container.html(html);

        // Instant Filter keyup handler
        $container.on('keyup input', '.hk-product-stocks-search', function () {
            var val = $(this).val().toLowerCase().trim();
            var $rows = $container.find('.hk-variation-row');

            if (val === '') {
                $rows.show();
                return;
            }

            $rows.each(function () {
                var $row = $(this);
                var name = $row.data('search-name') || '';
                if (name.indexOf(val) > -1) {
                    $row.show();
                } else {
                    $row.hide();
                }
            });
        });
    }

    function escHtml(str) {
        if (!str) return '';
        return str
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }
});
