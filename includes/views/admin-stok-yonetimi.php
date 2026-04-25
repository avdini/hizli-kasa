<?php
if (!defined('ABSPATH')) exit;

global $wpdb;
$depo_table = $wpdb->prefix . 'hizli_kasa_depolar';
$depolar = $wpdb->get_results("SELECT id, name FROM $depo_table ORDER BY priority DESC");
?>

<div class="hizli-kasa-admin-stock-wrap">
    <div class="stock-header" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; background:#fff; padding:15px; border-radius:8px; box-shadow:0 1px 3px rgba(0,0,0,0.1);">
        <div class="search-box" style="flex:1; display:flex; align-items:center; gap:15px;">
            <input type="text" id="admin-product-search" placeholder="Ürün adı veya SKU ile arayın..." style="width:100%; max-width:400px; padding:8px 12px; border-radius:4px;">
            <label style="display:inline-flex; align-items:center; gap:8px; font-size:13px; color:#d63638; font-weight:600; cursor:pointer; background:#fff5f5; padding:5px 12px; border-radius:6px; border:1px solid #fecaca;">
                <input type="checkbox" id="filter-mismatch" onchange="loadStockList()"> 
                <span class="dashicons dashicons-warning" style="font-size:18px; width:18px; height:18px; color:#d63638;"></span>
                Stok Uyuşmazlığı Olanlar
            </label>
        </div>
        <div class="actions" style="display:flex; gap:10px; align-items:center;">
            <div class="hk-import-export-group" style="padding-right:15px; border-right:1px solid #eee; margin-right:5px;">
                <button type="button" class="button button-secondary" onclick="openImportModal()"><span class="dashicons dashicons-upload" style="margin-top:4px;"></span> İçe Aktar</button>
                <button type="button" class="button button-secondary" onclick="openExportModal()"><span class="dashicons dashicons-download" style="margin-top:4px;"></span> Dışa Aktar</button>
            </div>
            <span id="stock-sync-status" style="margin-right:15px; font-size:12px; color:#666;"></span>
            <button type="button" class="button button-primary" onclick="loadStockList()"><span class="dashicons dashicons-update" style="margin-top:4px;"></span> Yenile</button>
        </div>
    </div>

    <!-- Stok Tablosu -->

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

<!-- İçe Aktar Modalı -->
<div id="hk-import-modal" class="hk-modal" style="display:none; position:fixed; z-index:100000; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.5); align-items:center; justify-content:center;">
    <div class="hk-modal-content" style="background:#fff; padding:30px; border-radius:12px; width:450px; box-shadow:0 20px 40px rgba(0,0,0,0.2);">
        <h2 style="margin-top:0;">Stok İçe Aktar</h2>
        <p style="color:#666;">CSV veya JSON formatındaki stok dosyanızı yükleyin. SKU eşleşen ürünlerin stokları otomatik güncellenecektir.</p>
        
        <div class="hk-import-upload-area" id="import-drop-zone" style="border:2px dashed #ddd; border-radius:8px; padding:30px; text-align:center; margin:20px 0; cursor:pointer; transition:all 0.2s;">
            <span class="dashicons dashicons-upload" style="font-size:40px; width:40px; height:40px; color:#bbb;"></span>
            <p style="margin:10px 0 0;">Dosyayı buraya sürükleyin veya <span style="color:#2271b1; text-decoration:underline;">tıklayıp seçin</span></p>
            <input type="file" id="import-file-input" style="display:none;" accept=".csv,.json">
            <div id="selected-file-info" style="display:none; margin-top:10px; font-weight:bold; color:#2271b1;"></div>
        </div>

        <div id="import-progress-container" style="display:none; margin:20px 0;">
            <div style="background:#eee; height:8px; border-radius:4px; overflow:hidden;">
                <div id="import-progress-bar" style="width:0%; height:100%; background:#2271b1; transition:width 0.3s;"></div>
            </div>
            <p id="import-progress-text" style="font-size:12px; text-align:center; margin-top:5px; color:#666;">Dosya işleniyor...</p>
        </div>

        <div id="import-result-summary" style="display:none; background:#f0f7ff; padding:15px; border-radius:8px; margin:20px 0; border-left:4px solid #2271b1;">
            <h4 style="margin:0 0 10px;">İşlem Tamamlandı:</h4>
            <ul style="margin:0; padding-left:20px; font-size:13px;">
                <li>Güncellenen Ürün: <strong id="res-updated">0</strong></li>
                <li>Hatalı/Eşleşmeyen: <strong id="res-unmatched" style="color:#d63638;">0</strong></li>
                <li>Yeni Oluşturulan Depo: <strong id="res-warehouses">0</strong></li>
            </ul>
        </div>

        <div id="hk-import-message" style="display:none; margin-top:15px; padding:12px; border-radius:6px; font-weight:500;"></div>

        <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:20px;">
            <button type="button" class="button button-secondary" id="hk-close-import-btn" onclick="closeImportModal()">Kapat</button>
            <button type="button" class="button button-primary" id="start-import-btn" disabled>İşlemi Başlat</button>
        </div>
    </div>
</div>

<!-- Dışa Aktar Modalı -->
<div id="hk-export-modal" class="hk-modal" style="display:none; position:fixed; z-index:100000; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.5); align-items:center; justify-content:center;">
    <div class="hk-modal-content" style="background:#fff; padding:30px; border-radius:12px; width:450px; box-shadow:0 20px 40px rgba(0,0,0,0.2);">
        <h2 style="margin-top:0;">Stok Dışa Aktar</h2>
        <p style="color:#666;">Dışa aktarılacak depoyu ve dosya formatını seçin.</p>
        
        <div style="margin:20px 0;">
            <label style="display:block; margin-bottom:8px; font-weight:600;">Hangi Depo?</label>
            <select id="export-depo-select" style="width:100%; padding:8px; border-radius:4px; border:1px solid #ddd;">
                <option value="0">Tüm Depolar (Genel Liste)</option>
                <?php foreach($depolar as $d): ?>
                    <option value="<?php echo $d->id; ?>"><?php echo esc_html($d->name); ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div style="margin:20px 0;">
            <label style="display:block; margin-bottom:8px; font-weight:600;">Dosya Formatı</label>
            <div style="display:flex; gap:20px;">
                <label style="cursor:pointer;"><input type="radio" name="export_format" value="csv" checked> Excel (CSV)</label>
                <label style="cursor:pointer;"><input type="radio" name="export_format" value="json"> JSON</label>
            </div>
        </div>

        <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:30px;">
            <button type="button" class="button button-secondary" onclick="closeExportModal()">İptal</button>
            <button type="button" class="button button-primary" onclick="startExport()">
                <span class="dashicons dashicons-download" style="font-size:16px; margin-top:2px;"></span> İndir
            </button>
        </div>
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
/* Quick Edit Styling */
.qty-value {
    min-width: 30px;
    text-align: center;
    font-weight: 600;
    cursor: text;
    padding: 2px 4px;
    border-bottom: 1px dashed #cbd5e1;
    transition: all 0.2s;
}
.qty-value:hover { background: #f1f5f9; border-bottom-color: #2271b1; color: #2271b1; }
.qty-input {
    width: 60px;
    height: 24px;
    text-align: center;
    font-weight: 600;
    border: 1px solid #2271b1;
    border-radius: 4px;
    background: #fff;
    box-shadow: 0 0 0 2px rgba(34, 113, 177, 0.1);
}
.updating { opacity: 0.5; pointer-events: none; }
.qty-changed {
    animation: hk-pulse-success 1s ease;
}
@keyframes hk-pulse-success {
    0% { color: #166534; transform: scale(1.1); }
    100% { color: inherit; transform: scale(1); }
}

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
.row-variable { cursor: pointer; user-select: none; font-weight: 500; }
.row-variation { transition: all 0.2s; border-top: 1px solid #f8fafc; }
.variation-indent { padding-left: 45px !important; position: relative; }

/* Smart Group Striping */
.stripe-even { background-color: #f0f7ff !important; } /* Mavimsi ton */
.stripe-odd { background-color: #ffffff !important; }
.row-variation { background-color: #ffffff !important; } /* Varyasyonlar temiz beyaz kalsın */

/* Hover Effect */
#admin-stock-list-body tr:hover { background-color: #e0e7ff !important; } /* Daha belirgin hover */

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

    // --- İçe / Dışa Aktar Kontrolleri ---
    window.openExportModal = function() {
        $('#hk-export-modal').css('display', 'flex');
    };

    window.closeExportModal = function() {
        $('#hk-export-modal').hide();
    };

    window.startExport = function() {
        const depoId = $('#export-depo-select').val();
        const format = $('input[name="export_format"]:checked').val();
        const exportUrl = `${ajaxurl}?action=hizli_kasa_export_stocks&format=${format}&depo_id=${depoId}`;
        
        window.location.href = exportUrl;
        closeExportModal();
    };

    window.openImportModal = function() {
        $('#hk-import-modal').css('display', 'flex');
        resetImportUI();
    };

    let importOccurred = false;

    window.closeImportModal = function() {
        $('#hk-import-modal').hide();
        if (importOccurred) {
            location.reload();
        } else {
            loadStockList(); 
        }
    };

    function resetImportUI() {
        $('#import-drop-zone').show().css('border-color', '#ddd');
        $('#selected-file-info').hide().text('');
        $('#import-result-summary').hide();
        $('#import-progress-container').hide();
        $('#hk-import-message').hide().text('').removeClass('updated error');
        $('#start-import-btn').prop('disabled', true).text('İşlemi Başlat');
        $('#hk-close-import-btn').prop('disabled', false);
        $('#import-file-input').val('');
    }

    const dropZone = document.getElementById('import-drop-zone');
    const fileInput = document.getElementById('import-file-input');

    if (dropZone) {
        dropZone.onclick = () => fileInput.click();
        dropZone.ondragover = (e) => { e.preventDefault(); dropZone.style.border_color = '#2271b1'; };
        dropZone.ondragleave = () => { dropZone.style.border_color = '#ddd'; };
        dropZone.ondrop = (e) => {
            e.preventDefault();
            const files = e.dataTransfer.files;
            if (files.length) handleFileSelect(files[0]);
        };
    }

    if (fileInput) {
        fileInput.onchange = (e) => {
            if (e.target.files.length) handleFileSelect(e.target.files[0]);
        };
    }

    function handleFileSelect(file) {
        const ext = file.name.split('.').pop().toLowerCase();
        if (ext !== 'csv' && ext !== 'json') {
            alert('Lütfen geçerli bir CSV veya JSON dosyası seçin.');
            return;
        }
        $('#selected-file-info').text('Seçilen Dosya: ' + file.name).show();
        $('#start-import-btn').prop('disabled', false).data('file', file);
    }

    $('#start-import-btn').on('click', function() {
        const file = $(this).data('file');
        if (!file) return;

        const formData = new FormData();
        formData.append('action', 'hizli_kasa_import_stocks');
        formData.append('import_file', file);

        $(this).prop('disabled', true).text('İşleniyor...');
        $('#hk-close-import-btn').prop('disabled', true);
        $('#import-drop-zone').hide();
        $('#hk-import-message').hide().removeClass('updated error');
        $('#import-progress-container').show();
        $('#import-progress-bar').css('width', '50%');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(res) {
                $('#import-progress-bar').css('width', '100%');
                $('#hk-close-import-btn').prop('disabled', false);
                
                if (res.success) {
                    $('#start-import-btn').text('İşlem Tamamlandı');
                    let msg = '<strong>Harika!</strong> Stoklar başarıyla güncellendi.';
                    if (res.data.stats && res.data.stats.unmatched > 0) {
                        msg += `<div style="font-size:12px; margin-top:5px; font-weight:normal;">${res.data.stats.unmatched} ürün eşleşmediği için ayrı bir listeye eklendi. "Eşleşmeyen Ürünler" sekmesinden kontrol edebilirsiniz.</div>`;
                    }
                    $('#hk-import-message').html(msg).css({ 'background': '#ecfdf5', 'color': '#065f46', 'border': '1px solid #d1fae5' }).fadeIn();
                    
                    importOccurred = true;
                    $('#start-import-btn').text('Tamam (Sayfayı Yenile)').off('click').on('click', function() {
                        closeImportModal();
                    });
                } else {
                    const errorMsg = res.data.message || 'Bilinmeyen bir hata oluştu.';
                    $('#hk-import-message').html('<strong>Hata!</strong> ' + errorMsg).css({ 'background': '#fef2f2', 'color': '#991b1b', 'border': '1px solid #fecaca' }).fadeIn();
                    $('#start-import-btn').prop('disabled', false).text('İşlemi Tekrar Başlat');
                }
            },
            error: function() {
                $('#hk-close-import-btn').prop('disabled', false);
                $('#start-import-btn').prop('disabled', false).text('İşlemi Tekrar Başlat');
                $('#hk-import-message').html('<strong>Sunucu Hatası!</strong> İşlem sırasında bir hata oluştu.').css({ 'background': '#fef2f2', 'color': '#991b1b', 'border': '1px solid #fecaca' }).fadeIn();
            }
        });
    });

    // İlk yüklemede eşleşmeyenleri kontrol etmeyi artık yapmıyoruz (Ayrı sekmede)

    // Define reload function early
    window.loadStockList = function(page = 1) {
        console.log('HK Debug: loadStockList called, page:', page);
        const query = $('#admin-product-search').val();
        const filterMismatch = $('#filter-mismatch').is(':checked');
        const $body = $('#admin-stock-list-body');
        const ajax_url = (typeof ajaxurl !== 'undefined') ? ajaxurl : '/wp-admin/admin-ajax.php';
        
        console.log('HK Debug: Sending AJAX request to:', ajax_url, {
            action: 'hizli_kasa_get_admin_stock_list',
            s: query,
            paged: page,
            filter_mismatch: filterMismatch
        });

        $.post(ajax_url, {
            action: 'hizli_kasa_get_admin_stock_list',
            s: query,
            paged: page,
            filter_mismatch: filterMismatch
        }, function(res) {
            console.log('HK Debug: AJAX Response received:', res);
            $body.css('opacity', '1');
            if(res.success) {
                console.log('HK Debug: Rendering table with', res.data.products.length, 'products');
                renderTable(res.data.products);
                renderPagination(res.data.total_pages, page);
            } else {
                let errorMsg = res.data ? res.data.message : 'Bilinmeyen hata';
                console.error('HK Debug: AJAX reported failure:', errorMsg);
                $body.html(`<tr><td colspan="100%" style="text-align:center; padding:40px;">
                    <div style="color:#d63638; font-weight:600; margin-bottom:10px;">⚠️ Veri Alınamadı</div>
                    <div style="font-size:13px; color:#666; margin-bottom:15px;">${errorMsg}</div>
                    <button class="button" onclick="loadStockList(${page})">Tekrar Dene</button>
                </td></tr>`);
            }
        }).fail(function(xhr) {
            console.error('HK Debug: AJAX Connection failed (Status:', xhr.status, ')', xhr.responseText);
            $body.css('opacity', '1');
            let detail = (xhr.status === 504) ? 'Sunucu yanıt süresi aşıldı (Timeout). Lütfen sayfayı yenileyip tekrar deneyin.' : 'Bağlantı hatası oluştu.';
            $body.html(`<tr><td colspan="100%" style="text-align:center; padding:40px;">
                <div style="color:#d63638; font-weight:600; margin-bottom:10px;">⚠️ Sunucu Hatası (Kod: ${xhr.status})</div>
                <div style="font-size:13px; color:#666; margin-bottom:15px;">${detail}</div>
                <button class="button" onclick="loadStockList(${page})">Tekrar Dene</button>
            </td></tr>`);
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

        let mainRowCounter = 0;

        products.forEach(p => {
            mainRowCounter++;
            const isVariable = p.type === 'variable';
            const badgeClass = isVariable ? 'badge-variable' : 'badge-simple';
            const badgeText = isVariable ? 'Varyantlı' : 'Basit';
            const stripeClass = (mainRowCounter % 2 === 0) ? 'stripe-even' : 'stripe-odd';
            const mismatchIcon = p.has_mismatch ? '<span class="dashicons dashicons-warning" style="color:#d63638; font-size:18px; margin-left:8px;" title="Depo stok toplamı site stoğu ile uyuşmuyor!"></span>' : '';

            let row = `<tr class="${isVariable ? 'row-variable' : ''} ${stripeClass}" data-id="${p.id}">
                <td><img src="${p.thumbnail}" style="width:40px; height:40px; border-radius:4px; object-fit:cover;"></td>
                <td style="vertical-align:middle;">
                    <div style="display:flex; align-items:center;">
                        ${isVariable ? '<span class="toggle-icon">▶</span>' : ''}
                        <strong>${p.name}</strong>
                        <span class="hk-badge ${badgeClass}">${badgeText}</span>
                        ${mismatchIcon}
                    </div>
                    <code style="font-size:10px; color:#64748b; margin-left:${isVariable ? '26px' : '0'};">SKU: ${p.sku || 'N/A'}</code>
                </td>
                <td style="font-weight:bold; color:#64748b; vertical-align:middle; ${p.has_mismatch ? 'color:#d63638;' : ''}">
                    ${isVariable ? '-' : p.wc_stock}
                    ${p.has_mismatch && !isVariable ? `<div style="font-size:9px; font-weight:normal; opacity:0.8;">Depo: ${p.total_warehouse_stock}</div>` : ''}
                </td>`;
            
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
                                ${v.has_mismatch ? '<span class="dashicons dashicons-warning" style="color:#d63638; font-size:16px; margin-left:5px;" title="Depo stok toplamı site stoğu ile uyuşmuyor!"></span>' : ''}
                            </div>
                            <code style="font-size:10px; color:#94a3b8;">SKU: ${v.sku || 'N/A'}</code>
                        </td>
                        <td style="font-weight:600; color:#64748b; vertical-align:middle; ${v.has_mismatch ? 'color:#d63638;' : ''}">
                            ${v.wc_stock}
                            ${v.has_mismatch ? `<div style="font-size:9px; font-weight:normal; opacity:0.8;">Depo: ${v.total_warehouse_stock}</div>` : ''}
                        </td>`;

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

    // Akıllı Stok Güncelleme (Quick Edit)
    $(document).on('click', '.qty-value', function(e) {
        if ($(this).find('input').length > 0) return;
        
        const $val = $(this);
        const currentQty = $val.text();
        const $input = $('<input type="text" class="qty-input">').val(currentQty);
        
        $val.html($input);
        $input.focus().select();
        
        $input.on('blur keyup', function(e) {
            if (e.type === 'keyup' && e.keyCode !== 13 && e.keyCode !== 27) return;
            if (e.keyCode === 27) { $val.text(currentQty); return; }
            
            const newVal = $input.val().trim();
            if (newVal === currentQty) { $val.text(currentQty); return; }
            
            saveStock($val.closest('.stock-qty-control'), newVal);
        });
    });

    window.updateStock = function(btn, change) {
        saveStock(jQuery(btn).closest('.stock-qty-control'), (change > 0 ? '+' : '') + change);
    };

    function saveStock($parent, inputVal) {
        const $val = $parent.find('.qty-value');
        let data = {
            action: 'hizli_kasa_admin_update_stock',
            product_id: $parent.data('pid'),
            variation_id: $parent.data('vid'),
            depo_id: $parent.data('did')
        };

        // Smart Syntax Kontrolü
        if (inputVal.startsWith('+') || inputVal.startsWith('-')) {
            data.change = inputVal;
        } else {
            data.set_qty = inputVal;
        }

        $parent.addClass('updating');
        
        jQuery.post(ajaxurl, data, function(res) {
            $parent.removeClass('updating');
            if(res.success) {
                $val.text(res.data.new_qty).addClass('qty-changed');
                setTimeout(() => $val.removeClass('qty-changed'), 1000);
            } else {
                alert(res.data.message);
                loadStockList(); // Hata varsa tabloyu eski haline getir
            }
        });
    }

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
