<?php
if (!defined('ABSPATH')) exit;

global $wpdb;
$depo_table = $wpdb->prefix . 'hizli_kasa_depolar';
$depolar = $wpdb->get_results("SELECT id, name FROM $depo_table ORDER BY priority DESC");
?>

<div id="hk-pending-bar" style="display:none; position:sticky; top:32px; z-index:9998; background:#fef3c7; border:1px solid #fcd34d; border-radius:8px; padding:10px 18px; margin-bottom:12px; align-items:center; justify-content:space-between; box-shadow:0 2px 8px rgba(251,191,36,0.3);">
  <span style="font-size:13px; font-weight:500;">⏳ <strong id="hk-pending-count">0</strong> değişiklik kaydedilmeyi bekliyor</span>
  <div style="display:flex; gap:8px;">
    <button type="button" class="button" onclick="cancelPendingChanges()">✕ İptal</button>
    <button type="button" id="btn-save-changes" class="button button-primary" onclick="savePendingChanges()" disabled>💾 Değişiklikleri Kaydet</button>
  </div>
</div>
<div id="hk-save-notice" style="display:none; margin-bottom:12px; padding:10px 16px; border-radius:8px; font-weight:500; font-size:13px;"></div>

<div class="hizli-kasa-admin-stock-wrap">
    <div class="stock-header" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; background:#fff; padding:15px; border-radius:8px; box-shadow:0 1px 3px rgba(0,0,0,0.1);">
        <div class="search-box" style="flex:1; display:flex; align-items:center; gap:15px;">
            <input type="text" id="admin-product-search" placeholder="Ürün adı veya SKU ile arayın..." style="width:100%; max-width:400px; padding:8px 12px; border-radius:4px;">
            <label style="display:inline-flex; align-items:center; gap:8px; font-size:13px; color:#d63638; font-weight:600; cursor:pointer; background:#fff5f5; padding:5px 12px; border-radius:6px; border:1px solid #fecaca;">
                <input type="checkbox" id="filter-mismatch" onchange="currentPage=1; loadStockList()"> 
                <span class="dashicons dashicons-warning" style="font-size:18px; width:18px; height:18px; color:#d63638;"></span>
                Stok Uyuşmazlığı Olanlar
            </label>
            <label style="display:inline-flex; align-items:center; gap:8px; font-size:13px; color:#64748b; font-weight:600; cursor:pointer; background:#f1f5f9; padding:5px 12px; border-radius:6px; border:1px solid #cbd5e1;">
                <input type="checkbox" id="filter-zero-stock" onchange="currentPage=1; loadStockList()"> 
                <span class="dashicons dashicons-minus" style="font-size:18px; width:18px; height:18px; color:#64748b;"></span>
                Stoku Sıfır Olanlar
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
                    <th style="width:36px; padding:12px 8px;"><input type="checkbox" id="select-all-rows"></th>
                    <th style="width:56px;">Görsel</th>
                    <th>Ürün Bilgisi</th>
                    <th style="width:130px; text-align:center;">Site Stoğu</th>
                    <?php foreach($depolar as $d): ?>
                        <th style="text-align:center; background: #f0f6fb; border-left:1px solid #ccd0d4;">
                            <?php echo esc_html($d->name); ?>
                        </th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody id="admin-stock-list-body">
                <tr>
                    <td colspan="<?php echo count($depolar) + 4; ?>" style="text-align:center; padding:50px;">
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

<div id="hk-bulk-toolbar" style="display:none; position:fixed; bottom:24px; left:50%; transform:translateX(-50%); z-index:9999; background:#1e293b; color:#fff; border-radius:12px; padding:12px 20px; box-shadow:0 8px 32px rgba(0,0,0,0.35); align-items:center; gap:10px; white-space:nowrap;">
  <span style="font-size:13px; opacity:0.8;"><strong id="hk-selected-count">0</strong> satır seçili</span>
  <div style="width:1px; height:24px; background:rgba(255,255,255,0.2);"></div>
  <select id="bulk-col-select" style="background:#334155; color:#fff; border:1px solid #475569; border-radius:6px; padding:5px 8px; font-size:12px;">
    <option value="wc_stock">Site Stoğu</option>
    <?php foreach($depolar as $d): ?>
      <option value="did_<?php echo $d->id; ?>"><?php echo esc_html($d->name); ?></option>
    <?php endforeach; ?>
  </select>
  <input type="number" id="bulk-val-input" placeholder="Değer" min="0" style="width:80px; background:#334155; color:#fff; border:1px solid #475569; border-radius:6px; padding:5px 8px; font-size:12px;">
  <button type="button" onclick="broadcastToSelected()" style="background:#3b82f6; color:#fff; border:none; border-radius:6px; padding:6px 12px; font-size:12px; cursor:pointer;">📢 Seçilenlere Uygula</button>
  <button type="button" onclick="fillDown()" style="background:#10b981; color:#fff; border:none; border-radius:6px; padding:6px 12px; font-size:12px; cursor:pointer;">↓ Aşağı Doldur</button>
  <button type="button" onclick="fillUp()" style="background:#8b5cf6; color:#fff; border:none; border-radius:6px; padding:6px 12px; font-size:12px; cursor:pointer;">↑ Yukarı Doldur</button>
  <button type="button" onclick="clearSelection()" style="background:transparent; color:#94a3b8; border:1px solid #475569; border-radius:6px; padding:6px 10px; font-size:12px; cursor:pointer;">✕</button>
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
/* Custom Checkbox */
.hk-row-cb { cursor:pointer; width:16px; height:16px; margin:0 !important; }

/* Bulky Rows */
#admin-stock-table-container td { padding: 12px 10px; font-size:14px; }

.stock-qty-control {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}
.btn-qty {
    width: 28px;
    height: 28px;
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
#admin-stock-list-body tr.hk-selected { background-color: #e0e7ff !important; }
#admin-stock-list-body tr.hk-pending { background-color: #fef3c7 !important; }

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
    let pendingChanges = {};

    function updateSaveButtonState() {
        const count = Object.keys(pendingChanges).length;
        $('#hk-pending-count').text(count);
        if (count > 0) {
            $('#hk-pending-bar').css('display', 'flex');
            $('#btn-save-changes').prop('disabled', false);
        } else {
            $('#hk-pending-bar').hide();
            $('#btn-save-changes').prop('disabled', true);
        }
    }

    window.openImagePreview = function(src) {
        if (!src || src.includes('placeholder')) return;

        let modal = document.getElementById('terminal-image-preview-modal');
        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'terminal-image-preview-modal';
            modal.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.85);z-index:999999;display:flex;align-items:center;justify-content:center;backdrop-filter:blur(5px);cursor:zoom-out;';
            
            const loader = document.createElement('div');
            loader.id = 'terminal-preview-loader';
            loader.style.cssText = 'position:absolute;width:40px;height:40px;border:4px solid #fff;border-top:4px solid transparent;border-radius:50%;animation:hk-spin 1s linear infinite;';
            
            if (!document.getElementById('hk-spin-keyframes')) {
                const style = document.createElement('style');
                style.id = 'hk-spin-keyframes';
                style.innerHTML = '@keyframes hk-spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }';
                document.head.appendChild(style);
            }

            const img = document.createElement('img');
            img.id = 'terminal-preview-img';
            img.style.cssText = 'max-width:90%;max-height:90%;object-fit:contain;border-radius:12px;opacity:0;transition:opacity 0.3s;box-shadow:0 10px 40px rgba(0,0,0,0.5);';
            
            modal.appendChild(loader);
            modal.appendChild(img);
            document.body.appendChild(modal);

            modal.addEventListener('click', () => {
                modal.style.display = 'none';
            });
        }

        const img = document.getElementById('terminal-preview-img');
        const loader = document.getElementById('terminal-preview-loader');
        
        img.style.opacity = '0';
        img.src = '';
        loader.style.display = 'block';
        modal.style.display = 'flex';
        
        const fullSrc = src.replace(/-\d+x\d+(\.[a-zA-Z]+)$/i, '$1');
        
        img.onload = function() {
            loader.style.display = 'none';
            img.style.opacity = '1';
        };
        img.onerror = function() {
            loader.style.display = 'none';
            img.style.opacity = '1';
        };
        img.src = fullSrc;
    };

    // Select All
    $('#select-all-rows').on('change', function() {
        const isChecked = $(this).is(':checked');
        $('.hk-row-cb, .hk-parent-cb').filter(':visible').prop('checked', isChecked);
        updateBulkToolbar();
    });

    $(document).on('change', '.hk-parent-cb', function(e) {
        e.stopPropagation();
        const isChecked = $(this).is(':checked');
        const pid = $(this).closest('tr').data('id');
        $(`.child-of-${pid}`).find('.hk-row-cb').prop('checked', isChecked);
        updateBulkToolbar();
    });

    $(document).on('change', '.hk-row-cb', function(e) {
        e.stopPropagation(); // Satır aç/kapatı tetiklemesin
        updateBulkToolbar();
    });

    function updateBulkToolbar() {
        const selected = $('.hk-row-cb:checked').length;
        if (selected > 0) {
            $('#hk-selected-count').text(selected);
            $('#hk-bulk-toolbar').css('display', 'flex');
        } else {
            $('#hk-bulk-toolbar').hide();
        }
        
        // Update selection styling for all rows based on their respective checkboxes
        $('.hk-row-cb, .hk-parent-cb').each(function() {
            $(this).closest('tr').toggleClass('hk-selected', $(this).is(':checked'));
        });
    }

    window.clearSelection = function() {
        $('.hk-row-cb, .hk-parent-cb').prop('checked', false);
        $('#select-all-rows').prop('checked', false);
        updateBulkToolbar();
    };

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
        const filterZeroStock = $('#filter-zero-stock').is(':checked');
        const $body = $('#admin-stock-list-body');
        const ajax_url = (typeof ajaxurl !== 'undefined') ? ajaxurl : '/wp-admin/admin-ajax.php';
        
        console.log('HK Debug: Sending AJAX request to:', ajax_url, {
            action: 'hizli_kasa_get_admin_stock_list',
            s: query,
            paged: page,
            filter_mismatch: filterMismatch,
            filter_zero_stock: filterZeroStock
        });

        $.post(ajax_url, {
            action: 'hizli_kasa_get_admin_stock_list',
            s: query,
            paged: page,
            filter_mismatch: filterMismatch,
            filter_zero_stock: filterZeroStock
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
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('filter_mismatch') === 'true') {
        $('#filter-mismatch').prop('checked', true);
    }
    loadStockList();

    // Sayfa değiştirince veya arama yapınca delegasyonlu event listener'ı bir kez kur
    $(document).off('click', '.row-variable').on('click', '.row-variable', function(e) {
        if ($(e.target).is('input[type="checkbox"]')) return;
        
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

        const queryStr = $('#admin-product-search').val().trim();
        const autoExpand = (products.length === 1 && queryStr !== '');

        let mainRowCounter = 0;

        products.forEach(p => {
            mainRowCounter++;
            const isVariable = p.type === 'variable';
            const badgeClass = isVariable ? 'badge-variable' : 'badge-simple';
            const badgeText = isVariable ? 'Varyantlı' : 'Basit';
            const stripeClass = (mainRowCounter % 2 === 0) ? 'stripe-even' : 'stripe-odd';
            const mismatchIcon = p.has_mismatch ? '<span class="dashicons dashicons-warning" style="color:#d63638; font-size:18px; margin-left:8px;" title="Depo stok toplamı site stoğu ile uyuşmuyor!"></span>' : '';
            
            // Eğer arama yapılmış ve tek sonuç dönmüşse parent row 'expanded' gelsin
            const isExpanded = isVariable && autoExpand ? 'expanded' : '';

            let row = `<tr class="${isVariable ? 'row-variable' : ''} ${stripeClass} ${isExpanded}" data-id="${p.id}">
                <td style="text-align:center;"><input type="checkbox" class="${isVariable ? 'hk-parent-cb' : 'hk-row-cb'}" value="${p.id}"></td>
                <td><img src="${p.thumbnail}" style="width:40px; height:40px; border-radius:4px; object-fit:cover; cursor:pointer;" onclick="openImagePreview('${p.thumbnail}')"></td>
                <td style="vertical-align:middle;">
                    <div style="display:flex; align-items:center;">
                        ${isVariable ? '<span class="toggle-icon">▶</span>' : ''}
                        <strong>${p.name}</strong>
                        <span class="hk-badge ${badgeClass}">${badgeText}</span>
                        ${mismatchIcon}
                    </div>
                    <code style="font-size:10px; color:#64748b; margin-left:${isVariable ? '26px' : '0'};">SKU: ${p.sku || 'N/A'}</code>
                </td>
                <td style="font-weight:bold; color:#64748b; vertical-align:middle; text-align:center; ${p.has_mismatch ? 'color:#d63638;' : ''}">
                    ${isVariable ? '-' : `
                    <div class="stock-qty-control" data-pid="${p.id}" data-vid="0" data-did="0" data-type="wc_stock">
                        <span class="qty-value">${p.wc_stock}</span>
                    </div>
                    `}
                    ${p.has_mismatch && !isVariable ? `<div style="font-size:9px; font-weight:normal; opacity:0.8;">Depo: ${p.total_warehouse_stock}</div>` : ''}
                </td>`;
            
            p.warehouse_stocks.forEach(ws => {
                row += `<td style="text-align:center; border-left:1px solid #eee; vertical-align:middle;">
                    ${isVariable ? '<span style="color:#cbd5e1">—</span>' : `
                    <div class="stock-qty-control" data-pid="${p.id}" data-vid="${p.variation_id}" data-did="${ws.depo_id}" data-type="warehouse">
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
                    const hiddenClass = autoExpand ? '' : 'hidden-variation';
                    let vRow = `<tr class="row-variation child-of-${p.id} ${hiddenClass}" data-id="${p.id}" data-vid="${v.variation_id}">
                        <td style="text-align:center;"><input type="checkbox" class="hk-row-cb" value="${v.variation_id}"></td>
                        <td style="text-align:right;"><img src="${v.thumbnail}" style="width:30px; height:30px; border-radius:4px; object-fit:cover; cursor:pointer;" onclick="openImagePreview('${v.thumbnail}')"></td>
                        <td class="variation-indent" style="vertical-align:middle;">
                            <div style="display:flex; align-items:center;">
                                <span style="font-size:13px; color:#334155;">${v.name}</span>
                                <span class="hk-badge badge-variation">Varyasyon</span>
                                ${v.has_mismatch ? '<span class="dashicons dashicons-warning" style="color:#d63638; font-size:16px; margin-left:5px;" title="Depo stok toplamı site stoğu ile uyuşmuyor!"></span>' : ''}
                            </div>
                            <code style="font-size:10px; color:#94a3b8;">SKU: ${v.sku || 'N/A'}</code>
                        </td>
                        <td style="font-weight:600; color:#64748b; vertical-align:middle; text-align:center; ${v.has_mismatch ? 'color:#d63638;' : ''}">
                            <div class="stock-qty-control" data-pid="${p.id}" data-vid="${v.variation_id}" data-did="0" data-type="wc_stock">
                                <span class="qty-value">${v.wc_stock}</span>
                            </div>
                            ${v.has_mismatch ? `<div style="font-size:9px; font-weight:normal; opacity:0.8;">Depo: ${v.total_warehouse_stock}</div>` : ''}
                        </td>`;

                    v.warehouse_stocks.forEach(vws => {
                        vRow += `<td style="text-align:center; border-left:1px solid #eee; vertical-align:middle;">
                            <div class="stock-qty-control" data-pid="${p.id}" data-vid="${v.variation_id}" data-did="${vws.depo_id}" data-type="warehouse">
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
        const currentQty = parseFloat($val.text()) || 0;
        let newQty = 0;
        
        if (inputVal.startsWith('+') || inputVal.startsWith('-')) {
            newQty = currentQty + parseFloat(inputVal);
        } else {
            newQty = parseFloat(inputVal);
        }
        if (newQty < 0) newQty = 0;
        
        $val.text(newQty).addClass('qty-changed');
        setTimeout(() => $val.removeClass('qty-changed'), 1000);
        
        const pid = $parent.data('pid');
        const vid = $parent.data('vid');
        const did = $parent.data('did');
        const type = $parent.data('type');
        
        const key = type === 'wc_stock' ? `wc_${pid}_${vid}` : `w_${pid}_${vid}_${did}`;
        pendingChanges[key] = { pid, vid, did, new_qty: newQty, type };
        
        $parent.closest('tr').addClass('hk-pending');
        updateSaveButtonState();
    }

    window.savePendingChanges = function() {
        if (Object.keys(pendingChanges).length === 0) return;
        
        $('#btn-save-changes').prop('disabled', true).text('Kaydediliyor...');
        
        $.post(ajaxurl, {
            action: 'hizli_kasa_batch_update_stock',
            changes: JSON.stringify(Object.values(pendingChanges))
        }, function(res) {
            if (res.success) {
                pendingChanges = {};
                updateSaveButtonState();
                $('#admin-stock-list-body tr').removeClass('hk-pending');
                $('#btn-save-changes').text('💾 Değişiklikleri Kaydet');
                
                $('#hk-save-notice').text(res.data.updated + ' ürün stok bilgisi başarıyla güncellendi.').css({'display': 'block', 'background': '#ecfdf5', 'color': '#065f46', 'border': '1px solid #d1fae5'});
                setTimeout(() => $('#hk-save-notice').fadeOut(), 3000);
                
                clearSelection();
                loadStockList(currentPage);
            } else {
                alert('Kaydetme hatası: ' + (res.data ? res.data.message : 'Bilinmeyen hata'));
                $('#btn-save-changes').prop('disabled', false).text('💾 Değişiklikleri Kaydet');
            }
        }).fail(function() {
            alert('Sunucu hatası oluştu!');
            $('#btn-save-changes').prop('disabled', false).text('💾 Değişiklikleri Kaydet');
        });
    };

    window.cancelPendingChanges = function() {
        pendingChanges = {};
        updateSaveButtonState();
        $('#admin-stock-list-body tr').removeClass('hk-pending');
        loadStockList(currentPage);
    };

    // Bulk Mod A: Yayma
    window.broadcastToSelected = function() {
        const targetCol = $('#bulk-col-select').val(); // 'wc_stock' veya 'did_1' vb.
        const val = $('#bulk-val-input').val();
        if (val === '') { alert('Lütfen bir değer girin.'); return; }
        
        $('.hk-row-cb:checked').each(function() {
            const $tr = $(this).closest('tr');
            let $control;
            if (targetCol === 'wc_stock') {
                $control = $tr.find('.stock-qty-control[data-type="wc_stock"]');
            } else {
                const did = targetCol.replace('did_', '');
                $control = $tr.find(`.stock-qty-control[data-type="warehouse"][data-did="${did}"]`);
            }
            if ($control.length) {
                saveStock($control, val);
            }
        });
    };

    // Bulk Mod C: Aşağı Doldur (Fill-Down)
    window.fillDown = function() {
        const targetCol = $('#bulk-col-select').val();
        const selectedRows = $('.hk-row-cb:checked').closest('tr');
        if (selectedRows.length === 0) return;
        
        selectedRows.each(function() {
            const $tr = $(this);
            // Sadece görünür satırları dikkate al (Açık olan varyasyonlar vb.)
            const $nextTr = $tr.nextAll('tr:visible').first();
            if ($nextTr.length === 0) return;
            
            let $sourceControl, $targetControl;
            if (targetCol === 'wc_stock') {
                $sourceControl = $tr.find('.stock-qty-control[data-type="wc_stock"]');
                $targetControl = $nextTr.find('.stock-qty-control[data-type="wc_stock"]');
            } else {
                const did = targetCol.replace('did_', '');
                $sourceControl = $tr.find(`.stock-qty-control[data-type="warehouse"][data-did="${did}"]`);
                $targetControl = $nextTr.find(`.stock-qty-control[data-type="warehouse"][data-did="${did}"]`);
            }
            
            if ($sourceControl.length && $targetControl.length) {
                const val = $sourceControl.find('.qty-value').text();
                saveStock($targetControl, val);
            }
        });
    };

    // Bulk Mod C: Yukarı Doldur (Fill-Up)
    window.fillUp = function() {
        const targetCol = $('#bulk-col-select').val();
        const selectedRows = $('.hk-row-cb:checked').closest('tr');
        if (selectedRows.length === 0) return;
        
        $($('.hk-row-cb:checked').get().reverse()).each(function() {
            const $tr = $(this).closest('tr');
            const $prevTr = $tr.prevAll('tr:visible').first();
            if ($prevTr.length === 0) return;
            
            let $sourceControl, $targetControl;
            if (targetCol === 'wc_stock') {
                $sourceControl = $tr.find('.stock-qty-control[data-type="wc_stock"]');
                $targetControl = $prevTr.find('.stock-qty-control[data-type="wc_stock"]');
            } else {
                const did = targetCol.replace('did_', '');
                $sourceControl = $tr.find(`.stock-qty-control[data-type="warehouse"][data-did="${did}"]`);
                $targetControl = $prevTr.find(`.stock-qty-control[data-type="warehouse"][data-did="${did}"]`);
            }
            
            if ($sourceControl.length && $targetControl.length) {
                const val = $sourceControl.find('.qty-value').text();
                saveStock($targetControl, val);
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
