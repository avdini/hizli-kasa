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
                    <th>Ürün Bilgisi</th>
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

/* Modern Pagination Styling */
.hk-pagination {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    background: #fff;
    padding: 6px;
    border-radius: 10px;
    border: 1px solid #e2e8f0;
}
.hk-page-link {
    display: flex;
    align-items: center;
    justify-content: center;
    min-width: 34px;
    height: 34px;
    padding: 0 10px;
    border-radius: 6px;
    border: 1px solid transparent;
    color: #64748b;
    text-decoration: none;
    font-size: 13px;
    font-weight: 600;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    cursor: pointer;
    background: transparent;
}
.hk-page-link:hover:not(.disabled):not(.active) {
    background: #f1f5f9;
    color: #0f172a;
    border-color: #cbd5e1;
}
.hk-page-link.active {
    background: #2271b1;
    color: #fff;
    box-shadow: 0 4px 6px -1px rgba(34, 113, 177, 0.2);
    cursor: default;
}
.hk-page-link.disabled {
    opacity: 0.3;
    pointer-events: none;
}
.hk-page-dots {
    color: #94a3b8;
    padding: 0 4px;
    font-weight: bold;
}
.hk-page-nav {
    font-size: 18px;
    line-height: 1;
}

/* Hierarchical Rows & Accordion */
.row-variable { background: #f8fafc !important; cursor: pointer; user-select: none; }
.row-variable:hover { background: #f1f5f9 !important; }
.row-variation { background: #ffffff !important; transition: all 0.2s; }
.variation-indent { padding-left: 45px !important; position: relative; }
.variation-indent::before {
    content: '';
    position: absolute;
    left: 20px;
    top: -10px;
    bottom: 50%;
    width: 20px;
    border-left: 2px solid #cbd5e1;
    border-bottom: 2px solid #cbd5e1;
    border-bottom-left-radius: 6px;
}

.toggle-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 18px;
    height: 18px;
    border-radius: 4px;
    background: #e2e8f0;
    color: #64748b;
    font-size: 10px;
    margin-right: 8px;
    transition: all 0.2s;
}
.row-variable.expanded .toggle-icon {
    background: #2271b1;
    color: #fff;
    transform: rotate(90deg);
}

.hidden-variation { display: none !important; }

/* Badges */
.hk-badge {
    display: inline-block;
    padding: 2px 6px;
    border-radius: 4px;
    font-size: 9px;
    font-weight: 700;
    text-transform: uppercase;
    vertical-align: middle;
    margin-left: 4px;
}
.badge-simple { background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; }
.badge-variable { background: #ecfdf5; color: #065f46; border: 1px solid #d1fae5; }
.badge-variation { background: #fffcf0; color: #854d0e; border: 1px solid #fef3c7; }
 </style>

<script>
console.log('Hızlı Kasa JS Başlatıldı.');
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

    // Define reload function early
    window.loadStockList = function(page = 1) {
        const query = $('#admin-product-search').val();
        const $body = $('#admin-stock-list-body');
        const ajax_url = (typeof ajaxurl !== 'undefined') ? ajaxurl : '/wp-admin/admin-ajax.php';
        
        $body.css('opacity', '0.5');
        
        $.post(ajax_url, {
            action: 'hizli_kasa_get_admin_stock_list',
            s: query,
            paged: page
        }, function(res) {
            $body.css('opacity', '1');
            if(res.success) {
                renderTable(res.data.products);
                renderPagination(res.data.total_pages, page);
            } else {
                $body.html('<tr><td colspan="100%" style="text-align:center; color:red; padding:20px;">Hata: ' + (res.data ? res.data.message : 'Bilinmeyen hata') + '</td></tr>');
            }
        }).fail(function(xhr) {
            $body.css('opacity', '1');
            $body.html('<tr><td colspan="100%" style="text-align:center; color:red; padding:20px;">Sunucu hatası (Kod: ' + xhr.status + '). Detaylar loglara yazılmış olabilir.</td></tr>');
        });
    };

    // İlk Yükleme (Artık güvenli)
    loadStockList();

    // Sayfa değiştirince veya arama yapınca delegasyonlu event listener'ı bir kez kur
    $(document).off('click', '.row-variable').on('click', '.row-variable', function() {
        const productID = $(this).data('id');
        $(this).toggleClass('expanded');
        $(`.child-of-${productID}`).toggleClass('hidden-variation');
    });

    function renderTable(products) {
        const $body = $('#admin-stock-list-body');
        $body.empty();

        if(products.length === 0) {
            $body.append('<tr><td colspan="100%" style="text-align:center; padding:20px;">Ürün bulunamadı.</td></tr>');
            return;
        }

        products.forEach(p => {
            const isVariable = p.type === 'variable';
            const badgeClass = isVariable ? 'badge-variable' : 'badge-simple';
            const badgeText = isVariable ? 'Varyantlı' : 'Basit';

            let row = `<tr class="${isVariable ? 'row-variable' : ''}" data-id="${p.id}">
                <td><img src="${p.thumbnail}" style="width:40px; height:40px; border-radius:4px; object-fit:cover;"></td>
                <td style="vertical-align:middle;">
                    <div style="display:flex; align-items:center;">
                        ${isVariable ? '<span class="toggle-icon">▶</span>' : ''}
                        <strong>${p.name}</strong>
                        <span class="hk-badge ${badgeClass}">${badgeText}</span>
                    </div>
                    <code style="font-size:10px; color:#64748b; margin-left:${isVariable ? '26px' : '0'};">SKU: ${p.sku || 'N/A'}</code>
                </td>
                <td style="font-weight:bold; color:#64748b; vertical-align:middle;">${isVariable ? '-' : p.wc_stock}</td>`;
            
            p.warehouse_stocks.forEach(ws => {
                row += `<td style="text-align:center; border-left:1px solid #eee; vertical-align:middle;">
                    ${isVariable ? '<span style="color:#cbd5e1">—</span>' : `
                    <div class="stock-qty-control" data-pid="${p.id}" data-vid="${p.variation_id}" data-did="${ws.depo_id}">
                        <button class="btn-qty minus" onclick="updateStock(this, -1)">-</button>
                        <span class="qty-value">${ws.qty}</span>
                        <button class="btn-qty plus" onclick="updateStock(this, 1)">+</button>
                    </div>`}
                </td>`;
            });

            row += `</tr>`;
            $body.append(row);

            // Varyasyonları Ekle
            if(isVariable && p.variations) {
                p.variations.forEach(v => {
                    let vRow = `<tr class="row-variation child-of-${p.id} hidden-variation">
                        <td style="text-align:right;"><img src="${v.thumbnail}" style="width:30px; height:30px; border-radius:4px; object-fit:cover;"></td>
                        <td class="variation-indent" style="vertical-align:middle;">
                            <div style="display:flex; align-items:center;">
                                <span style="font-size:13px; color:#334155;">${v.name}</span>
                                <span class="hk-badge badge-variation">Varyasyon</span>
                            </div>
                            <code style="font-size:10px; color:#94a3b8;">SKU: ${v.sku || 'N/A'}</code>
                        </td>
                        <td style="font-weight:600; color:#64748b; vertical-align:middle;">${v.wc_stock}</td>`;

                    v.warehouse_stocks.forEach(vws => {
                        vRow += `<td style="text-align:center; border-left:1px solid #eee; vertical-align:middle;">
                            <div class="stock-qty-control" data-pid="${v.id}" data-vid="${v.variation_id}" data-did="${vws.depo_id}">
                                <button class="btn-qty minus" onclick="updateStock(this, -1)">-</button>
                                <span class="qty-value">${vws.qty}</span>
                                <button class="btn-qty plus" onclick="updateStock(this, 1)">+</button>
                            </div>
                        </td>`;
                    });

                    vRow += `</tr>`;
                    $body.append(vRow);
                });
            }
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

        let items = [];
        const range = 2; // Aktif sayfanın sağında ve solunda kaç sayı görünecek

        // Önceki Butonu
        items.push(`<a class="hk-page-link hk-page-nav ${activePage === 1 ? 'disabled' : ''}" href="#" onclick="loadStockList(${activePage - 1}); return false;">«</a>`);

        // İlk sayfa
        if (activePage > range + 1) {
            items.push(`<a class="hk-page-link" href="#" onclick="loadStockList(1); return false;">1</a>`);
            if (activePage > range + 2) items.push(`<span class="hk-page-dots">...</span>`);
        }

        // Sayı Aralığı
        for (let i = Math.max(1, activePage - range); i <= Math.min(totalPages, activePage + range); i++) {
            items.push(`<a class="hk-page-link ${i === activePage ? 'active' : ''}" href="#" onclick="loadStockList(${i}); return false;">${i}</a>`);
        }

        // Son sayfa
        if (activePage < totalPages - range) {
            if (activePage < totalPages - range - 1) items.push(`<span class="hk-page-dots">...</span>`);
            items.push(`<a class="hk-page-link" href="#" onclick="loadStockList(${totalPages}); return false;">${totalPages}</a>`);
        }

        // Sonraki Butonu
        items.push(`<a class="hk-page-link hk-page-nav ${activePage === totalPages ? 'disabled' : ''}" href="#" onclick="loadStockList(${activePage + 1}); return false;">»</a>`);

        $pag.append(`<div class="hk-pagination">${items.join('')}</div>`);
    }
});
</script>
