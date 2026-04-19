<?php
if (!defined('ABSPATH')) exit;

global $wpdb;
$depo_table = $wpdb->prefix . 'hizli_kasa_depolar';
$depolar = $wpdb->get_results("SELECT id, name FROM $depo_table ORDER BY priority DESC");
?>

<div class="hizli-kasa-admin-stock-wrap">
    <div class="stock-header" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; background:#fff; padding:15px; border-radius:8px; box-shadow:0 1px 3px rgba(0,0,0,0.1);">
        <div class="search-box" style="flex:1;">
            <input type="text" id="admin-product-search" placeholder="Ürün adı veya SKU ile arayın..." style="width:100%; max-width:400px; padding:8px 12px; border-radius:4px;">
        </div>
        <div class="actions">
            <span id="stock-sync-status" style="margin-right:15px; font-size:12px; color:#666;"></span>
            <button type="button" class="button button-secondary" onclick="loadStockList()">Yazdır / Listeyi Yenile</button>
        </div>
    </div>

    <div id="admin-stock-table-container">
        <table class="wp-list-table widefat fixed striped table-view-list products">
            <thead>
                <tr>
                    <th style="width:50px;">Görsel</th>
                    <th>Ürün / SKU</th>
                    <th style="width:100px;">Site Stoğu</th>
                    <?php foreach($depolar as $d): ?>
                        <th style="text-align:center; background: #f0f6fb; border-left:1px solid #ccd0d4;">
                            <?php echo esc_html($d->name); ?>
                        </th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody id="admin-stock-list-body">
                <tr>
                    <td colspan="<?php echo count($depolar) + 3; ?>" style="text-align:center; padding:50px;">
                        <span class="spinner is-active" style="float:none;"></span> Ürünler yükleniyor...
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="pagination-wrap" id="admin-stock-pagination" style="margin-top:20px; text-align:right;">
        <!-- Sayfalama buraya gelecek -->
    </div>
</div>

<style>
.stock-qty-control {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}
.btn-qty {
    width: 24px;
    height: 24px;
    border-radius: 4px;
    border: 1px solid #ddd;
    background: #fff;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    transition: all 0.2s;
}
.btn-qty:hover { background: #f0f0f0; border-color: #999; }
.btn-qty.plus:hover { color: #2271b1; border-color: #2271b1; }
.btn-qty.minus:hover { color: #d63638; border-color: #d63638; }
.qty-value {
    min-width: 30px;
    text-align: center;
    font-weight: 500;
}
.updating { opacity: 0.5; pointer-events: none; }
</style>

<script>
jQuery(document).ready(function($) {
    let currentPage = 1;
    let searchTimeout = null;

    // Arama Tetikleyici
    $('#admin-product-search').on('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            currentPage = 1;
            loadStockList();
        }, 500);
    });

    // İlk Yükleme
    loadStockList();

    window.loadStockList = function(page = 1) {
        const query = $('#admin-product-search').val();
        const $body = $('#admin-stock-list-body');
        
        $body.css('opacity', '0.5');
        
        $.post(ajaxurl, {
            action: 'hizli_kasa_get_admin_stock_list',
            s: query,
            paged: page
        }, function(res) {
            $body.css('opacity', '1');
            if(res.success) {
                renderTable(res.data.products);
                renderPagination(res.data.total_pages, page);
            }
        });
    };

    function renderTable(products) {
        const $body = $('#admin-stock-list-body');
        $body.empty();

        if(products.length === 0) {
            $body.append('<tr><td colspan="100%" style="text-align:center; padding:20px;">Ürün bulunamadı.</td></tr>');
            return;
        }

        products.forEach(p => {
            let rows = `<tr>
                <td><img src="${p.thumbnail}" style="width:40px; height:40px; border-radius:4px; object-fit:cover;"></td>
                <td>
                    <strong>${p.name}</strong><br>
                    <code style="font-size:10px;">SKU: ${p.sku || 'N/A'}</code>
                </td>
                <td style="font-weight:bold; color:#666;">${p.wc_stock}</td>`;
            
            p.warehouse_stocks.forEach(ws => {
                rows += `<td style="text-align:center; border-left:1px solid #eee;">
                    <div class="stock-qty-control" data-pid="${p.id}" data-vid="${p.variation_id}" data-did="${ws.depo_id}">
                        <button class="btn-qty minus" onclick="updateStock(this, -1)">-</button>
                        <span class="qty-value">${ws.qty}</span>
                        <button class="btn-qty plus" onclick="updateStock(this, 1)">+</button>
                    </div>
                </td>`;
            });

            rows += `</tr>`;
            $body.append(rows);
        });
    }

    window.updateStock = function(btn, change) {
        const $parent = jQuery(btn).closest('.stock-qty-control');
        const $val = $parent.find('.qty-value');
        const data = {
            action: 'hizli_kasa_admin_update_stock',
            product_id: $parent.data('pid'),
            variation_id: $parent.data('vid'),
            depo_id: $parent.data('did'),
            change: change
        };

        $parent.addClass('updating');
        
        jQuery.post(ajaxurl, data, function(res) {
            $parent.removeClass('updating');
            if(res.success) {
                $val.text(res.data.new_qty);
                // Başarı efekti
                $val.css('color', change > 0 ? 'green' : 'red');
                setTimeout(() => $val.css('color', ''), 500);
            } else {
                alert(res.data.message);
            }
        });
    };

    function renderPagination(totalPages, activePage) {
        const $pag = $('#admin-stock-pagination');
        $pag.empty();
        if(totalPages <= 1) return;

        let html = `<div class="tablenav-pages"><span class="displaying-num">${totalPages} sayfa</span>`;
        for(let i=1; i<=totalPages; i++) {
            if(i === activePage) {
                html += `<span class="current-page">${i}</span> `;
            } else {
                html += `<a class="page-numbers" href="#" onclick="loadStockList(${i}); return false;">${i}</a> `;
            }
        }
        html += `</div>`;
        $pag.append(html);
    }
});
</script>
