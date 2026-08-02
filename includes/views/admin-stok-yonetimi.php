<?php
if (!defined('ABSPATH')) exit;

global $wpdb;
$depo_table = Hizli_Kasa_Database::get_tables()['depolar'];
$stok_table = Hizli_Kasa_Database::get_tables()['stok_konumlari'];
$depolar = $wpdb->get_results("SELECT id, name FROM $depo_table ORDER BY priority DESC");
$depo_count = count($depolar);

$stats = class_exists('Hizli_Kasa_Ajax_Stock') ? Hizli_Kasa_Ajax_Stock::get_stock_stats() : ['total' => 0, 'mismatch' => 0, 'zero' => 0];
?>

<div class="hizli-kasa-admin-stock-wrap">
    <!-- Live Quick Stats Cards -->
    <div class="hk-stock-stats-grid">
        <div class="hk-stat-card active" id="card-stat-all" onclick="filterByStat('all')" style="--card-accent: #3b82f6;">
            <div class="hk-stat-icon" style="background:#eff6ff; color:#2563eb;">
                <span class="dashicons dashicons-archive"></span>
            </div>
            <div class="hk-stat-info">
                <div class="hk-stat-val" id="stat-val-total"><?php echo esc_html($stats['total']); ?></div>
                <div class="hk-stat-lbl">Toplam SKU / Ürün</div>
            </div>
        </div>

        <div class="hk-stat-card" id="card-stat-mismatch" onclick="filterByStat('mismatch')" style="--card-accent: #ea580c;">
            <div class="hk-stat-icon" style="background:#fff7ed; color:#ea580c;">
                <span class="dashicons dashicons-warning"></span>
            </div>
            <div class="hk-stat-info">
                <div class="hk-stat-val" id="stat-val-mismatch"><?php echo esc_html($stats['mismatch']); ?></div>
                <div class="hk-stat-lbl">Stok Uyuşmazlığı</div>
            </div>
        </div>

        <div class="hk-stat-card" id="card-stat-reserved" onclick="filterByStat('reserved')" style="--card-accent: #d97706;">
            <div class="hk-stat-icon" style="background:#fffbe8; color:#d97706;">
                <span class="dashicons dashicons-lock"></span>
            </div>
            <div class="hk-stat-info">
                <div class="hk-stat-val"><span id="stat-val-reserved-sku"><?php echo esc_html($stats['reserved_sku'] ?? 0); ?></span> <span style="font-size:12px; font-weight:600; opacity:0.75;">SKU</span></div>
                <div class="hk-stat-lbl">Rezerve Stok (<span id="stat-val-reserved-qty"><?php echo esc_html($stats['reserved_qty'] ?? 0); ?></span> Adet)</div>
            </div>
        </div>

        <div class="hk-stat-card" id="card-stat-warehouses" style="--card-accent: #f58220; cursor:default;">
            <div class="hk-stat-icon" style="background:#fff7ed; color:#f58220;">
                <span class="dashicons dashicons-building"></span>
            </div>
            <div class="hk-stat-info">
                <div class="hk-stat-val"><?php echo esc_html($depo_count); ?></div>
                <div class="hk-stat-lbl">Aktif Depo Sayısı</div>
            </div>
        </div>
    </div>

    <!-- Pending Save Bar -->
    <div id="hk-pending-bar" style="display:none; position:sticky; top:32px; z-index:9998; background:#fef3c7; border:1px solid #fcd34d; border-radius:10px; padding:12px 20px; margin-bottom:16px; align-items:center; justify-content:space-between; box-shadow:0 4px 12px rgba(251,191,36,0.25);">
      <span style="font-size:13.5px; font-weight:600; color:#78350f;">⏳ <strong id="hk-pending-count">0</strong> değişiklik kaydedilmeyi bekliyor</span>
      <div style="display:flex; gap:10px;">
        <button type="button" class="button" onclick="cancelPendingChanges()">✕ İptal</button>
        <button type="button" id="btn-save-changes" class="button button-primary" onclick="savePendingChanges()" disabled>💾 Değişiklikleri Kaydet (Ctrl+S)</button>
      </div>
    </div>
    <div id="hk-save-notice" style="display:none; margin-bottom:16px; padding:12px 18px; border-radius:10px; font-weight:600; font-size:13px;"></div>

    <!-- Main Toolbar -->
    <div class="hk-stock-toolbar">
        <div class="hk-stock-search-group">
            <div class="hk-search-input-wrap">
                <span class="dashicons dashicons-search"></span>
                <input type="text" id="admin-product-search" placeholder="Ürün adı veya SKU ile arayın...">
            </div>
            <div class="hk-filter-btn-wrap" style="display:flex; align-items:center; gap:8px;">
                <button type="button" class="button button-secondary" id="btn-open-stock-filter" onclick="openStockFilterModal()" style="display:flex; align-items:center; gap:6px; font-weight:600; height:36px; padding:0 14px; border-radius:8px;">
                    <span class="dashicons dashicons-filter" style="font-size:16px; margin-top:2px;"></span> Filtrele
                    <span id="hk-active-filter-badge" class="hk-filter-badge" style="display:none; background:#2563eb; color:#ffffff; font-size:11px; font-weight:700; padding:1px 7px; border-radius:10px; margin-left:2px;">0</span>
                </button>
                <div id="hk-active-filters-summary" style="display:none; align-items:center; gap:6px; background:#eff6ff; border:1px solid #bfdbfe; color:#1e40af; padding:4px 10px; border-radius:8px; font-size:12px; font-weight:600;">
                    <span id="hk-active-filters-text"></span>
                    <button type="button" onclick="resetStockFilters(true)" style="background:none; border:none; color:#ef4444; font-size:12px; cursor:pointer; padding:0 2px; line-height:1; font-weight:700;" title="Filtreleri Temizle">✕</button>
                </div>
            </div>
        </div>
        <div class="actions" style="display:flex; gap:10px; align-items:center;">
            <div class="hk-import-export-group" style="padding-right:15px; border-right:1px solid var(--hk-border-color); display:flex; gap:8px;">
                <button type="button" class="button button-secondary" onclick="openImportModal()"><span class="dashicons dashicons-upload" style="margin-top:4px;"></span> İçe Aktar</button>
                <button type="button" class="button button-secondary" onclick="openExportModal()"><span class="dashicons dashicons-download" style="margin-top:4px;"></span> Dışa Aktar</button>
            </div>
            <button type="button" class="button button-primary" onclick="loadStockList()"><span class="dashicons dashicons-update" style="margin-top:4px;"></span> Yenile</button>
        </div>
    </div>

    <!-- Data Table Container -->
    <div class="hk-stock-table-wrap">
        <table class="hk-stock-table-modern">
            <thead class="hk-stock-table-thead">
                <tr class="hk-stock-table-header-row">
                    <th class="hk-th-select" style="width:38px; text-align:center;"><input type="checkbox" id="select-all-rows"></th>
                    <th class="hk-th-thumb" style="width:54px; text-align:center;">Görsel</th>
                    <th class="hk-th-info">Ürün Bilgisi / SKU</th>
                    <th class="hk-th-wc-stock" style="width:140px; text-align:center;">Site Stoğu (WC)</th>
                    <?php foreach($depolar as $d): ?>
                        <th class="hk-th-depo-stock" style="text-align:center; background: #f8fafc; border-left:1px solid var(--hk-border-color);">
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

    <!-- Sayfalama -->
    <div class="pagination-wrap" id="admin-stock-pagination" style="margin-top:20px; text-align:right;"></div>
</div>

<!-- Floating Bulk Action Dock -->
<div id="hk-bulk-toolbar">
  <span style="font-size:13px; opacity:0.9;"><strong id="hk-selected-count">0</strong> satır seçili</span>
  <div style="width:1px; height:22px; background:rgba(255,255,255,0.2);"></div>
  
  <select id="bulk-col-select">
    <option value="wc_stock">Site Stoğu (WC)</option>
    <?php foreach($depolar as $d): ?>
      <option value="did_<?php echo $d->id; ?>"><?php echo esc_html($d->name); ?></option>
    <?php endforeach; ?>
  </select>

  <select id="bulk-mode-select" style="background:#1e293b; color:#fff; border:1px solid #334155; border-radius:8px; padding:6px 10px; font-size:12.5px;">
    <option value="set">Sabit Değer</option>
    <option value="relative">Nispi Ekle/Çıkar (+5 / -2)</option>
    <option value="percent_plus">Yüzdesel Artır (%+)</option>
    <option value="percent_minus">Yüzdesel Azalt (%-)</option>
  </select>

  <input type="text" id="bulk-val-input" placeholder="Değer (ör: 10 veya %20)" style="width:100px;">
  
  <button type="button" onclick="broadcastToSelected()" style="background:#3b82f6; color:#fff; border:none; border-radius:8px; padding:7px 14px; font-size:12.5px; font-weight:600; cursor:pointer;">📢 Uygula</button>
  <button type="button" onclick="fillDown()" style="background:#10b981; color:#fff; border:none; border-radius:8px; padding:7px 12px; font-size:12.5px; font-weight:600; cursor:pointer;">↓ Aşağı Doldur</button>
  <button type="button" onclick="fillUp()" style="background:#8b5cf6; color:#fff; border:none; border-radius:8px; padding:7px 12px; font-size:12.5px; font-weight:600; cursor:pointer;">↑ Yukarı Doldur</button>
  <button type="button" onclick="clearSelection()" style="background:transparent; color:#94a3b8; border:1px solid #475569; border-radius:8px; padding:6px 12px; font-size:12.5px; cursor:pointer;">✕</button>
</div>

<!-- İçe Aktar Modalı -->
<div id="hk-import-modal" class="hk-modal-overlay">
    <div class="hk-modal-box">
        <div class="hk-modal-header">
            <h3><span class="dashicons dashicons-upload"></span> Stok İçe Aktar</h3>
            <button type="button" class="hk-modal-close" onclick="closeImportModal()">✕</button>
        </div>
        <div class="hk-modal-body">
            <p style="color:var(--hk-text-muted); font-size:13px; margin-top:0;">CSV veya JSON formatındaki stok dosyanızı yükleyin. SKU eşleşen ürünlerin stokları otomatik güncellenecektir.</p>
            
            <div class="hk-import-upload-area" id="import-drop-zone" style="border:2px dashed var(--hk-border-color); border-radius:12px; padding:30px 20px; text-align:center; margin:16px 0; cursor:pointer; background:#f8fafc; transition:all 0.2s;">
                <span class="dashicons dashicons-upload" style="font-size:42px; width:42px; height:42px; color:var(--hk-primary);"></span>
                <p style="margin:10px 0 0; font-weight:600; color:var(--hk-text-main);">Dosyayı buraya sürükleyin veya <span style="color:var(--hk-primary); text-decoration:underline;">tıklayıp seçin</span></p>
                <input type="file" id="import-file-input" style="display:none;" accept=".csv,.json">
                <div id="selected-file-info" style="display:none; margin-top:12px; font-weight:700; color:var(--hk-primary);"></div>
            </div>

            <div id="import-progress-container" style="display:none; margin:16px 0;">
                <div style="background:#e2e8f0; height:8px; border-radius:4px; overflow:hidden;">
                    <div id="import-progress-bar" style="width:0%; height:100%; background:var(--hk-primary); transition:width 0.3s;"></div>
                </div>
                <p id="import-progress-text" style="font-size:12px; text-align:center; margin-top:6px; color:var(--hk-text-muted);">Dosya işleniyor...</p>
            </div>

            <div id="import-result-summary" style="display:none; background:#eff6ff; padding:15px; border-radius:10px; margin:16px 0; border-left:4px solid #3b82f6;">
                <h4 style="margin:0 0 8px; font-size:14px;">İşlem Tamamlandı:</h4>
                <ul style="margin:0; padding-left:18px; font-size:13px; color:var(--hk-text-main);">
                    <li>Güncellenen Ürün: <strong id="res-updated">0</strong></li>
                    <li>Hatalı/Eşleşmeyen: <strong id="res-unmatched" style="color:#dc2626;">0</strong></li>
                    <li>Yeni Oluşturulan Depo: <strong id="res-warehouses">0</strong></li>
                </ul>
            </div>

            <div id="hk-import-message" style="display:none; margin-top:12px; padding:12px; border-radius:8px; font-weight:600; font-size:13px;"></div>
        </div>
        <div class="hk-modal-footer">
            <button type="button" class="button" id="hk-close-import-btn" onclick="closeImportModal()">Kapat</button>
            <button type="button" class="button button-primary" id="start-import-btn" disabled>İşlemi Başlat</button>
        </div>
    </div>
</div>

<!-- Dışa Aktar Modalı -->
<div id="hk-export-modal" class="hk-modal-overlay">
    <div class="hk-modal-box">
        <div class="hk-modal-header">
            <h3><span class="dashicons dashicons-download"></span> Stok Dışa Aktar</h3>
            <button type="button" class="hk-modal-close" onclick="closeExportModal()">✕</button>
        </div>
        <div class="hk-modal-body">
            <p style="color:var(--hk-text-muted); font-size:13px; margin-top:0;">Dışa aktarılacak depoyu ve dosya formatını seçin.</p>
            
            <div style="margin:16px 0;">
                <label style="display:block; margin-bottom:8px; font-weight:600; font-size:13px;">Hangi Depo?</label>
                <select id="export-depo-select" style="width:100%; padding:9px 12px; border-radius:8px; border:1px solid var(--hk-border-color); background:#f8fafc; font-size:13.5px;">
                    <option value="0">Tüm Depolar (Genel Liste)</option>
                    <?php foreach($depolar as $d): ?>
                        <option value="<?php echo $d->id; ?>"><?php echo esc_html($d->name); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="margin:16px 0;">
                <label style="display:block; margin-bottom:8px; font-weight:600; font-size:13px;">Dosya Formatı</label>
                <div style="display:flex; gap:20px;">
                    <label style="cursor:pointer; font-weight:500; font-size:13.5px;"><input type="radio" name="export_format" value="csv" checked> Excel (CSV)</label>
                    <label style="cursor:pointer; font-weight:500; font-size:13.5px;"><input type="radio" name="export_format" value="json"> JSON</label>
                </div>
            </div>
        </div>
        </div>
        <div class="hk-modal-footer">
            <button type="button" class="button" onclick="closeExportModal()">İptal</button>
            <button type="button" class="button button-primary" onclick="startExport()">
                <span class="dashicons dashicons-download" style="font-size:16px; margin-top:2px;"></span> İndir
            </button>
        </div>
    </div>
</div>

<!-- Rezervasyon Temizleme Modalı -->
<div id="hk-clear-res-modal" class="hk-modal-overlay">
    <div class="hk-modal-box" style="max-width:460px;">
        <div class="hk-modal-header">
            <h3><span class="dashicons dashicons-lock" style="color:#ea580c;"></span> Rezervasyonu Temizle</h3>
            <button type="button" class="hk-modal-close" onclick="closeClearReservationModal()">✕</button>
        </div>
        <div class="hk-modal-body">
            <p style="color:var(--hk-text-main); font-size:13.5px; margin-top:0;" id="clear-res-modal-text">Bu ürünün kilitli stok rezervasyonunu temizlemek istediğinize emin misiniz?</p>
            <div style="background:#fff7ed; border:1px solid #ffedd5; border-radius:8px; padding:10px 12px; margin-top:12px; font-size:12px; color:#c2410c;">
                <strong>⚠️ Dikkat:</strong> Bu işlem kilitli stoğu 0 yapar ve net kullanılabilir stoğu artırır. Siparişi iptal olmuş veya askıda kalmış durumlar hariç dikkatle uygulanmalıdır.
            </div>
            <div id="hk-clear-res-message" style="display:none; margin-top:12px; padding:10px; border-radius:8px; font-weight:600; font-size:12.5px;"></div>
        </div>
        <div class="hk-modal-footer">
            <button type="button" class="button" onclick="closeClearReservationModal()">İptal</button>
            <button type="button" class="button button-primary" id="confirm-clear-res-btn" style="background:#dc2626; border-color:#dc2626;">
                <span class="dashicons dashicons-trash" style="font-size:16px; margin-top:3px;"></span> Evet, Temizle
            </button>
        </div>
    </div>
</div>

<!-- Stok Filtreleme Modalı -->
<div id="hk-stock-filter-modal" class="hk-modal-overlay">
    <div class="hk-modal-box" style="max-width:540px;">
        <div class="hk-modal-header">
            <h3><span class="dashicons dashicons-filter" style="color:var(--hk-primary);"></span> Stok Filtreleme Seçenekleri</h3>
            <button type="button" class="hk-modal-close" onclick="closeStockFilterModal()">✕</button>
        </div>
        <div class="hk-modal-body" style="padding:20px 24px;">
            <!-- Depo Seçimi -->
            <div style="margin-bottom:18px;">
                <label style="display:block; margin-bottom:6px; font-weight:600; font-size:13px; color:var(--hk-text-main);">🏢 Depo Seçimi</label>
                <select id="modal-filter-depo-id" class="hk-modal-input" style="width:100%; padding:9px 12px; border-radius:8px; border:1px solid var(--hk-border-color); background:#f8fafc; font-size:13.5px;">
                    <option value="0">Tüm Depolar (Genel Toplam)</option>
                    <?php foreach($depolar as $d): ?>
                        <option value="<?php echo $d->id; ?>"><?php echo esc_html($d->name); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Depodaki Stok Durumu -->
            <div style="margin-bottom:18px;">
                <label style="display:block; margin-bottom:6px; font-weight:600; font-size:13px; color:var(--hk-text-main);">📊 Depo Stok Durumu</label>
                <select id="modal-filter-depo-status" class="hk-modal-input" style="width:100%; padding:9px 12px; border-radius:8px; border:1px solid var(--hk-border-color); background:#f8fafc; font-size:13.5px;">
                    <option value="in_stock" selected>Stoğu Var (> 0)</option>
                    <option value="all">Tüm Durumlar (0 Dahil)</option>
                    <option value="out_of_stock">Stoğu Tükenmiş (≤ 0)</option>
                    <option value="negative">Eksi Stoğa Düşmüş (< 0)</option>
                </select>
            </div>

            <!-- Özel Durum Filtreleri -->
            <div style="margin-bottom:18px;">
                <label style="display:block; margin-bottom:8px; font-weight:600; font-size:13px; color:var(--hk-text-main);">⚡ Özel Durum Filtreleri</label>
                <div style="display:flex; flex-direction:column; gap:10px; background:#f8fafc; border:1px solid var(--hk-border-color); border-radius:10px; padding:12px 14px;">
                    <label style="display:flex; align-items:center; gap:8px; font-size:13px; cursor:pointer; font-weight:500;">
                        <input type="checkbox" id="filter-mismatch">
                        <span class="dashicons dashicons-warning" style="color:#ea580c; font-size:16px;"></span>
                        <strong>Uyuşmazlığı Olanlar</strong> (Site Stoğu ile Depo Stoğu Farklı / Eksi)
                    </label>
                    <label style="display:flex; align-items:center; gap:8px; font-size:13px; cursor:pointer; font-weight:500;">
                        <input type="checkbox" id="filter-zero-stock">
                        <span class="dashicons dashicons-minus" style="color:#64748b; font-size:16px;"></span>
                        <strong>Sıfır Stoklular</strong> (Site Stok Miktarı ≤ 0 Olanlar)
                    </label>
                    <label style="display:flex; align-items:center; gap:8px; font-size:13px; cursor:pointer; font-weight:500;">
                        <input type="checkbox" id="filter-reserved">
                        <span class="dashicons dashicons-lock" style="color:#ea580c; font-size:16px;"></span>
                        <strong>Rezerve Edilmiş Olanlar</strong> (Kilitli Sipariş Stoğu > 0 Olanlar)
                    </label>
                </div>
            </div>

            <!-- Stok Miktar Aralığı -->
            <div style="margin-bottom:18px;">
                <label style="display:block; margin-bottom:6px; font-weight:600; font-size:13px; color:var(--hk-text-main);">🔢 Stok Miktar Aralığı</label>
                <div style="display:flex; gap:12px; align-items:center;">
                    <input type="number" id="modal-filter-min-stock" placeholder="Min Stok" style="flex:1; padding:8px 12px; border-radius:8px; border:1px solid var(--hk-border-color); font-size:13px; background:#f8fafc;">
                    <span style="color:var(--hk-text-muted); font-weight:600;">-</span>
                    <input type="number" id="modal-filter-max-stock" placeholder="Max Stok" style="flex:1; padding:8px 12px; border-radius:8px; border:1px solid var(--hk-border-color); font-size:13px; background:#f8fafc;">
                </div>
            </div>

            <!-- Ürün Türü Filtresi -->
            <div style="margin-bottom:6px;">
                <label style="display:block; margin-bottom:6px; font-weight:600; font-size:13px; color:var(--hk-text-main);">📦 Ürün Türü</label>
                <select id="modal-filter-product-type" class="hk-modal-input" style="width:100%; padding:9px 12px; border-radius:8px; border:1px solid var(--hk-border-color); background:#f8fafc; font-size:13.5px;">
                    <option value="all">Tüm Ürünler ve Varyasyonlar</option>
                    <option value="simple">Sadece Ana / Basit Ürünler</option>
                    <option value="variation">Sadece Varyasyonlar</option>
                </select>
            </div>
        </div>
        <div class="hk-modal-footer" style="display:flex; justify-content:space-between; align-items:center;">
            <button type="button" class="button" onclick="resetStockFilters()" style="color:#ef4444; border-color:#fca5a5;">
                <span class="dashicons dashicons-trash" style="font-size:15px; margin-top:3px;"></span> Sıfırla
            </button>
            <div style="display:flex; gap:10px;">
                <button type="button" class="button" onclick="closeStockFilterModal()">İptal</button>
                <button type="button" class="button button-primary" onclick="applyStockFilters()">
                    <span class="dashicons dashicons-filter" style="font-size:15px; margin-top:3px;"></span> Filtreleri Uygula
                </button>
            </div>
        </div>
    </div>
</div>

<script>
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

    window.openStockFilterModal = function() {
        $('#hk-stock-filter-modal').css('display', 'flex');
    };

    window.closeStockFilterModal = function() {
        $('#hk-stock-filter-modal').hide();
    };

    window.resetStockFilters = function(triggerLoad = false) {
        $('#filter-mismatch').prop('checked', false);
        $('#filter-zero-stock').prop('checked', false);
        $('#filter-reserved').prop('checked', false);
        $('#modal-filter-depo-id').val('0');
        $('#modal-filter-depo-status').val('all');
        $('#modal-filter-min-stock').val('');
        $('#modal-filter-max-stock').val('');
        $('#modal-filter-product-type').val('all');

        $('.hk-stat-card').removeClass('active');
        $('#card-stat-all').addClass('active');

        updateFilterBadge();

        if (triggerLoad) {
            currentPage = 1;
            loadStockList();
        }
    };

    window.applyStockFilters = function() {
        closeStockFilterModal();
        updateFilterBadge();
        currentPage = 1;
        loadStockList();
    };

    function updateFilterBadge() {
        let count = 0;
        let labels = [];

        if ($('#filter-mismatch').is(':checked')) { count++; labels.push('Uyuşmazlık'); }
        if ($('#filter-zero-stock').is(':checked')) { count++; labels.push('Sıfır Stok'); }
        if ($('#filter-reserved').is(':checked')) { count++; labels.push('Rezerve Stok'); }

        const depoId = $('#modal-filter-depo-id').val();
        if (depoId && depoId !== '0') {
            count++;
            const depoName = $('#modal-filter-depo-id option:selected').text();
            labels.push(depoName);
        }

        const depoStatus = $('#modal-filter-depo-status').val();
        if (depoStatus && depoStatus !== 'all') {
            count++;
            labels.push($('#modal-filter-depo-status option:selected').text());
        }

        const minStock = $('#modal-filter-min-stock').val();
        const maxStock = $('#modal-filter-max-stock').val();
        if (minStock !== '' || maxStock !== '') {
            count++;
            if (minStock !== '' && maxStock !== '') labels.push(`Stok: ${minStock}-${maxStock}`);
            else if (minStock !== '') labels.push(`Stok ≥ ${minStock}`);
            else if (maxStock !== '') labels.push(`Stok ≤ ${maxStock}`);
        }

        const pType = $('#modal-filter-product-type').val();
        if (pType && pType !== 'all') {
            count++;
            labels.push(pType === 'simple' ? 'Ana Ürünler' : 'Varyasyonlar');
        }

        if (count > 0) {
            $('#hk-active-filter-badge').text(count).show();
            $('#btn-open-stock-filter').addClass('button-primary').removeClass('button-secondary');
            $('#hk-active-filters-text').text('Aktif: ' + labels.join(', '));
            $('#hk-active-filters-summary').css('display', 'inline-flex');
        } else {
            $('#hk-active-filter-badge').hide();
            $('#btn-open-stock-filter').removeClass('button-primary').addClass('button-secondary');
            $('#hk-active-filters-summary').hide();
        }
    }

    window.filterByStat = function(statType) {
        $('.hk-stat-card').removeClass('active');
        if (statType === 'mismatch') {
            $('#card-stat-mismatch').addClass('active');
            $('#filter-mismatch').prop('checked', true);
            $('#filter-zero-stock').prop('checked', false);
            $('#filter-reserved').prop('checked', false);
        } else if (statType === 'reserved') {
            $('#card-stat-reserved').addClass('active');
            $('#filter-mismatch').prop('checked', false);
            $('#filter-zero-stock').prop('checked', false);
            $('#filter-reserved').prop('checked', true);
        } else {
            $('#card-stat-all').addClass('active');
            $('#filter-mismatch').prop('checked', false);
            $('#filter-zero-stock').prop('checked', false);
            $('#filter-reserved').prop('checked', false);
        }
        updateFilterBadge();
        currentPage = 1;
        loadStockList();
    };

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

    function updateSelectAllState() {
        const totalRowCbs = $('.hk-row-cb').length;
        const checkedRowCbs = $('.hk-row-cb:checked').length;
        $('#select-all-rows').prop('checked', totalRowCbs > 0 && totalRowCbs === checkedRowCbs);
    }

    // Select All
    $('#select-all-rows').on('change', function() {
        const isChecked = $(this).is(':checked');
        $('.hk-row-cb, .hk-parent-cb').prop('checked', isChecked);
        updateBulkToolbar();
    });

    $(document).on('change', '.hk-parent-cb', function(e) {
        e.stopPropagation();
        const isChecked = $(this).is(':checked');
        const pid = $(this).closest('tr').data('id');
        $(`.child-of-${pid}`).find('.hk-row-cb').prop('checked', isChecked);
        updateSelectAllState();
        updateBulkToolbar();
    });

    $(document).on('change', '.hk-row-cb', function(e) {
        e.stopPropagation();
        const $tr = $(this).closest('tr');
        if ($tr.hasClass('row-variation')) {
            const pid = $tr.data('id');
            const $childCbs = $(`.child-of-${pid}`).find('.hk-row-cb');
            const totalChildren = $childCbs.length;
            const checkedChildren = $childCbs.filter(':checked').length;
            $(`tr.row-variable[data-id="${pid}"]`).find('.hk-parent-cb').prop('checked', totalChildren > 0 && totalChildren === checkedChildren);
        }
        updateSelectAllState();
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
        
        $('.hk-row-cb, .hk-parent-cb').each(function() {
            $(this).closest('tr').toggleClass('hk-selected', $(this).is(':checked'));
        });
    }

    window.clearSelection = function() {
        $('.hk-row-cb, .hk-parent-cb').prop('checked', false);
        $('#select-all-rows').prop('checked', false);
        updateBulkToolbar();
    };

    // Arama Tetikleyici (Debounced)
    $('#admin-product-search').on('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            currentPage = 1;
            loadStockList();
        }, 300);
    });

    // --- İçe / Dışa Aktar Kontrolleri ---
    window.openExportModal = function() {
        $('#hk-export-modal').css('display', 'flex');
    };

    window.closeExportModal = function() {
        $('#hk-export-modal').hide();
    };

    let pendingClearResData = null;

    window.closeClearReservationModal = function() {
        jQuery('#hk-clear-res-modal').hide();
    };

    window.openClearReservationModal = function(e, pid, vid, did, name) {
        if (e) e.stopPropagation();
        pendingClearResData = { pid: pid, vid: vid, did: did };
        jQuery('#clear-res-modal-text').html(`<strong>${name}</strong> ürünü için bu depodaki kilitli stok rezervasyonunu sıfırlamak istediğinize emin misiniz?`);
        jQuery('#hk-clear-res-message').hide().text('');
        jQuery('#confirm-clear-res-btn').prop('disabled', false).html('<span class="dashicons dashicons-trash" style="font-size:16px; margin-top:3px;"></span> Evet, Temizle');
        jQuery('#hk-clear-res-modal').css('display', 'flex');
    };

    $('#confirm-clear-res-btn').on('click', function() {
        if (!pendingClearResData) return;
        const $btn = $(this);
        $btn.prop('disabled', true).text('İşleniyor...');

        const ajax_url = (typeof ajaxurl !== 'undefined') ? ajaxurl : '/wp-admin/admin-ajax.php';
        $.post(ajax_url, {
            action: 'hizli_kasa_clear_stock_reservation',
            product_id: pendingClearResData.pid,
            variation_id: pendingClearResData.vid,
            location_id: pendingClearResData.did
        }, function(res) {
            if (res.success) {
                closeClearReservationModal();
                loadStockList(currentPage);
            } else {
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-trash" style="font-size:16px; margin-top:3px;"></span> Evet, Temizle');
                $('#hk-clear-res-message').css({ background: '#fef2f2', color: '#991b1b', border: '1px solid #fecaca' }).text(res.data ? res.data.message : 'Hata oluştu').show();
            }
        }).fail(function() {
            $btn.prop('disabled', false).html('<span class="dashicons dashicons-trash" style="font-size:16px; margin-top:3px;"></span> Evet, Temizle');
            $('#hk-clear-res-message').css({ background: '#fef2f2', color: '#991b1b', border: '1px solid #fecaca' }).text('Bağlantı hatası oluştu').show();
        });
    });

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
        $('#import-drop-zone').show().css('border-color', 'var(--hk-border-color)');
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
        dropZone.ondragover = (e) => { e.preventDefault(); dropZone.style.borderColor = 'var(--hk-primary)'; };
        dropZone.ondragleave = () => { dropZone.style.borderColor = 'var(--hk-border-color)'; };
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
            HK.UI.alert('Lütfen geçerli bir CSV veya JSON dosyası seçin.');
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

    window.loadStockList = function(page = 1) {
        const query = $('#admin-product-search').val();
        const filterMismatch = $('#filter-mismatch').is(':checked');
        const filterZeroStock = $('#filter-zero-stock').is(':checked');
        const filterReserved = $('#filter-reserved').is(':checked');
        const filterDepoId = $('#modal-filter-depo-id').val();
        const filterDepoStatus = $('#modal-filter-depo-status').val();
        const filterMinStock = $('#modal-filter-min-stock').val();
        const filterMaxStock = $('#modal-filter-max-stock').val();
        const filterProductType = $('#modal-filter-product-type').val();
        const $body = $('#admin-stock-list-body');
        const ajax_url = (typeof ajaxurl !== 'undefined') ? ajaxurl : '/wp-admin/admin-ajax.php';

        $.post(ajax_url, {
            action: 'hizli_kasa_get_admin_stock_list',
            s: query,
            paged: page,
            filter_mismatch: filterMismatch,
            filter_zero_stock: filterZeroStock,
            filter_reserved: filterReserved,
            depo_id: filterDepoId,
            depo_stock_status: filterDepoStatus,
            min_stock: filterMinStock,
            max_stock: filterMaxStock,
            product_type: filterProductType
        }, function(res) {
            $body.css('opacity', '1');
            if(res.success) {
                renderTable(res.data.products);
                renderPagination(res.data.total_pages, page);
                if (res.data.stats) {
                    $('#stat-val-total').text(res.data.stats.total);
                    $('#stat-val-mismatch').text(res.data.stats.mismatch);
                    $('#stat-val-reserved-sku').text(res.data.stats.reserved_sku || 0);
                    $('#stat-val-reserved-qty').text(res.data.stats.reserved_qty || 0);
                }
                if (res.data.perf) {
                    const p = res.data.perf;
                    console.groupCollapsed(`%c⚡ [Hızlı Kasa Vakit Kaybı Analizi] Toplam Süre: ${p.total_server_time_ms} ms (${p.db_queries} SQL Sorgusu)`, 'color:#2563eb; font-weight:bold; font-size:12px;');
                    const tableData = [
                        { 'İşlem Adımı': '1. 🌐 WordPress Boot & Eklentiler (Açılış)', 'Süre (ms)': p.wp_bootup_ms, 'Pay (%)': Math.round((p.wp_bootup_ms / p.total_server_time_ms) * 100) + '%' },
                        { 'İşlem Adımı': '2. 🗄️ SQL Ürün ID Filtreleme (Sorgu 1)', 'Süre (ms)': p.db_product_ids_ms, 'Pay (%)': Math.round((p.db_product_ids_ms / p.total_server_time_ms) * 100) + '%' },
                        { 'İşlem Adımı': '3. 📋 Postmeta & Nitelik Çekimi (Sorgu 2)', 'Süre (ms)': p.db_postmeta_ms, 'Pay (%)': Math.round((p.db_postmeta_ms / p.total_server_time_ms) * 100) + '%' },
                        { 'İşlem Adımı': '4. 🏢 Depo Stokları & Term Çözümleme (Sorgu 3-4)', 'Süre (ms)': p.db_stocks_terms_ms, 'Pay (%)': Math.round((p.db_stocks_terms_ms / p.total_server_time_ms) * 100) + '%' },
                        { 'İşlem Adımı': '5. 🌳 Varyasyon Ağacı & Şablon Hazırlığı', 'Süre (ms)': p.php_tree_assembly_ms, 'Pay (%)': Math.round((p.php_tree_assembly_ms / p.total_server_time_ms) * 100) + '%' }
                    ];
                    if (p.db_stats_ms !== undefined) {
                        tableData.push({ 'İşlem Adımı': '6. 📊 Stok İstatistikleri (Metrik Çekimi)', 'Süre (ms)': p.db_stats_ms, 'Pay (%)': Math.round((p.db_stats_ms / p.total_server_time_ms) * 100) + '%' });
                    }
                    console.table(tableData);
                    console.groupEnd();
                }
            } else {
                let errorMsg = res.data ? res.data.message : 'Bilinmeyen hata';
                $body.html(`<tr><td colspan="100%" style="text-align:center; padding:40px;">
                    <div style="color:#dc2626; font-weight:700; margin-bottom:10px;">⚠️ Veri Alınamadı</div>
                    <div style="font-size:13px; color:var(--hk-text-muted); margin-bottom:15px;">${errorMsg}</div>
                    <button class="button" onclick="loadStockList(${page})">Tekrar Dene</button>
                </td></tr>`);
            }
        }).fail(function(xhr) {
            $body.css('opacity', '1');
            let detail = (xhr.status === 504) ? 'Sunucu yanıt süresi aşıldı (Timeout). Lütfen sayfayı yenileyip tekrar deneyin.' : 'Bağlantı hatası oluştu.';
            $body.html(`<tr><td colspan="100%" style="text-align:center; padding:40px;">
                <div style="color:#dc2626; font-weight:700; margin-bottom:10px;">⚠️ Sunucu Hatası (Kod: ${xhr.status})</div>
                <div style="font-size:13px; color:var(--hk-text-muted); margin-bottom:15px;">${detail}</div>
                <button class="button" onclick="loadStockList(${page})">Tekrar Dene</button>
            </td></tr>`);
        });
    };

    // İlk Yükleme
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('filter_mismatch') === 'true') {
        $('#filter-mismatch').prop('checked', true);
        updateFilterChipStyles();
    }
    loadStockList();

    $(document).off('click', '.row-variable').on('click', '.row-variable', function(e) {
        if ($(e.target).is('input[type="checkbox"]')) return;
        
        const productID = $(this).data('id');
        $(this).toggleClass('expanded');
        $(`.child-of-${productID}`).toggleClass('hidden-variation');
    });

    function renderTable(products) {
        clearSelection();
        const $body = $('#admin-stock-list-body');
        $body.empty();

        if(products.length === 0) {
            $body.append('<tr><td colspan="100%" style="text-align:center; padding:40px; color:var(--hk-text-muted);">Aramanıza uygun ürün bulunamadı.</td></tr>');
            return;
        }

        const queryStr = $('#admin-product-search').val().trim().toLowerCase();
        const autoExpand = (queryStr !== '');
        let mainRowCounter = 0;

        products.forEach(p => {
            mainRowCounter++;
            const isVariable = p.type === 'variable';
            const badgeClass = isVariable ? 'badge-variable' : 'badge-simple';
            const badgeText = isVariable ? 'Varyantlı' : 'Basit';
            const stripeClass = (mainRowCounter % 2 === 0) ? 'stripe-even' : 'stripe-odd';
            
            const netWhStock = (p.total_warehouse_stock !== undefined) ? (p.total_warehouse_stock - (p.total_reserved_stock || 0)) : 0;
            const diffVal = netWhStock - p.wc_stock;
            const diffDisplay = diffVal > 0 ? `+${diffVal}` : `${diffVal}`;
            const mismatchBadge = p.has_mismatch ? `<span class="hk-delta-badge delta-error" title="Depo net stokları toplamı site stoğu ile uyuşmuyor!">Δ ${diffDisplay}</span>` : '';
            
            const isExpanded = isVariable && autoExpand ? 'expanded' : '';

            let row = `<tr class="${isVariable ? 'row-variable' : ''} ${stripeClass} ${isExpanded}" data-id="${p.id}">
                <td style="text-align:center;"><input type="checkbox" class="${isVariable ? 'hk-parent-cb' : 'hk-row-cb'}" value="${p.id}"></td>
                <td style="text-align:center;"><img src="${p.thumbnail}" style="width:38px; height:38px; border-radius:6px; object-fit:cover; cursor:pointer;" onclick="openImagePreview('${p.thumbnail}')"></td>
                <td style="vertical-align:middle;">
                    <div style="display:flex; align-items:center; gap:6px;">
                        ${isVariable ? '<span class="toggle-icon">▶</span>' : ''}
                        <strong style="color:var(--hk-text-main);">${p.name}</strong>
                        <span class="hk-stock-badge ${badgeClass}">${badgeText}</span>
                    </div>
                    <code style="font-size:10.5px; color:var(--hk-text-muted); margin-left:${isVariable ? '24px' : '0'}; font-weight:600;">SKU: ${p.sku || 'N/A'}</code>
                </td>
                <td style="font-weight:700; color:var(--hk-text-main); vertical-align:middle; text-align:center; ${p.has_mismatch ? 'color:#dc2626;' : ''}">
                    ${isVariable ? '—' : `
                    <div class="stock-qty-control" data-pid="${p.id}" data-vid="0" data-did="0" data-type="wc_stock">
                        <span class="qty-value">${p.wc_stock}</span>
                    </div>
                    ${mismatchBadge}
                    `}
                </td>`;
            
            p.warehouse_stocks.forEach(ws => {
                const safeName = (p.name || '').replace(/'/g, "\\'");
                const reservedInfo = (ws.reserved && ws.reserved > 0) ? `<div style="font-size:10px; color:#d97706; font-weight:700; margin-top:3px; background:#fffbe8; border:1px solid #fef3c7; border-radius:4px; padding:2px 6px; display:inline-flex; align-items:center; gap:4px;" title="Online Sipariş Kilitli Stok: ${ws.reserved} Adet (Net Stok: ${ws.qty - ws.reserved})">🔒 ${ws.reserved} Rezerve <button type="button" style="background:none; border:none; color:#dc2626; cursor:pointer; font-weight:bold; font-size:11px; padding:0; line-height:1;" title="Rezervasyonu Temizle" onclick="openClearReservationModal(event, ${p.id}, 0, ${ws.depo_id}, '${safeName}')">✕</button></div>` : '';
                row += `<td style="text-align:center; border-left:1px solid #f1f5f9; vertical-align:middle;">
                    ${isVariable ? '<span style="color:#cbd5e1">—</span>' : `
                    <div class="stock-qty-control" data-pid="${p.id}" data-vid="${p.variation_id}" data-did="${ws.depo_id}" data-type="warehouse">
                        <button class="btn-qty minus" onclick="updateStock(this, -1)">-</button>
                        <span class="qty-value">${ws.qty}</span>
                        <button class="btn-qty plus" onclick="updateStock(this, 1)">+</button>
                    </div>
                    ${reservedInfo}`}
                </td>`;
            });

            row += `</tr>`;
            $body.append(row);

            // Varyasyonlar (Parent'a Bağlı, Sütunları Hizalı ve Kapalı Gelen Düz Satırlar)
            if(isVariable && p.variations && p.variations.length > 0) {
                p.variations.forEach(v => {
                    const hiddenClass = autoExpand ? '' : 'hidden-variation';
                    const vNetWhStock = (v.total_warehouse_stock !== undefined) ? (v.total_warehouse_stock - (v.total_reserved_stock || 0)) : 0;
                    const vDiffVal = vNetWhStock - v.wc_stock;
                    const vDiffDisplay = vDiffVal > 0 ? `+${vDiffVal}` : `${vDiffVal}`;
                    const vMismatchBadge = v.has_mismatch ? `<span class="hk-delta-badge delta-error" title="Depo net stokları toplamı site stoğu ile uyuşmuyor!">Δ ${vDiffDisplay}</span>` : '';
                    
                    const vSkuLow = (v.sku || '').toLowerCase().trim();
                    const isExactSkuMatch = (queryStr !== '' && vSkuLow === queryStr);
                    const exactMatchBadge = isExactSkuMatch ? `<span class="hk-stock-badge" style="background:#dcfce7; color:#15803d; border:1px solid #86efac; margin-left:4px;">🎯 Tam Eşleşen SKU</span>` : '';
                    const highlightClass = isExactSkuMatch ? 'style="background-color:#f0fdf4 !important;"' : '';

                    let vRow = `<tr class="row-variation child-of-${p.id} ${hiddenClass}" data-id="${p.id}" data-vid="${v.variation_id}" ${highlightClass}>
                        <td style="text-align:center;"><input type="checkbox" class="hk-row-cb" value="${v.variation_id}"></td>
                        <td style="text-align:center;"><img src="${v.thumbnail}" style="width:30px; height:30px; border-radius:6px; object-fit:cover; cursor:pointer;" onclick="openImagePreview('${v.thumbnail}')"></td>
                        <td class="variation-indent" style="vertical-align:middle;">
                            <div style="display:flex; align-items:center; gap:6px;">
                                <span style="font-size:13px; color:var(--hk-text-main); font-weight:600;">${v.name}</span>
                                <span class="hk-stock-badge badge-variation">Varyasyon</span>
                                ${exactMatchBadge}
                            </div>
                            <code style="font-size:10px; color:var(--hk-text-muted); font-weight:600;">SKU: ${v.sku || 'N/A'}</code>
                        </td>
                        <td style="font-weight:700; color:var(--hk-text-main); vertical-align:middle; text-align:center; ${v.has_mismatch ? 'color:#dc2626;' : ''}">
                            <div class="stock-qty-control" data-pid="${p.id}" data-vid="${v.variation_id}" data-did="0" data-type="wc_stock">
                                <span class="qty-value">${v.wc_stock}</span>
                            </div>
                            ${vMismatchBadge}
                        </td>`;

                    v.warehouse_stocks.forEach(vws => {
                        const safeVName = (v.name || '').replace(/'/g, "\\'");
                        const vReservedInfo = (vws.reserved && vws.reserved > 0) ? `<div style="font-size:10px; color:#d97706; font-weight:700; margin-top:3px; background:#fffbe8; border:1px solid #fef3c7; border-radius:4px; padding:2px 6px; display:inline-flex; align-items:center; gap:4px;" title="Online Sipariş Kilitli Stok: ${vws.reserved} Adet (Net Stok: ${vws.qty - vws.reserved})">🔒 ${vws.reserved} Rezerve <button type="button" style="background:none; border:none; color:#dc2626; cursor:pointer; font-weight:bold; font-size:11px; padding:0; line-height:1;" title="Rezervasyonu Temizle" onclick="openClearReservationModal(event, ${p.id}, ${v.variation_id}, ${vws.depo_id}, '${safeVName}')">✕</button></div>` : '';
                        vRow += `<td style="text-align:center; border-left:1px solid #f1f5f9; vertical-align:middle;">
                            <div class="stock-qty-control" data-pid="${p.id}" data-vid="${v.variation_id}" data-did="${vws.depo_id}" data-type="warehouse">
                                <button class="btn-qty minus" onclick="updateStock(this, -1)">-</button>
                                <span class="qty-value">${vws.qty}</span>
                                <button class="btn-qty plus" onclick="updateStock(this, 1)">+</button>
                            </div>
                            ${vReservedInfo}
                        </td>`;
                    });

                    vRow += `</tr>`;
                    $body.append(vRow);
                });
            }
        });
    }

    // Akıllı Stok Düzenleme (Spreadsheet Quick Edit & Focus)
    $(document).on('click', '.qty-value', function(e) {
        if ($(this).find('input').length > 0) return;
        
        const $val = $(this);
        const currentQty = $val.text().trim();
        const $input = $('<input type="text" class="qty-input hk-grid-cell-input">').val(currentQty);
        
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

    // Spreadsheet Klavye Gezinmesi (Arrow keys, Tab, Enter)
    $(document).on('keydown', '.hk-grid-cell-input', function(e) {
        const $currentInput = $(this);
        const $td = $currentInput.closest('td');
        const $tr = $td.closest('tr');
        const colIndex = $td.index();

        switch (e.which) {
            case 39: // Arrow Right
                e.preventDefault();
                const $nextTd = $td.nextAll('td').find('.qty-value').first();
                if ($nextTd.length) $nextTd.trigger('click');
                break;

            case 37: // Arrow Left
                e.preventDefault();
                const $prevTd = $td.prevAll('td').find('.qty-value').last();
                if ($prevTd.length) $prevTd.trigger('click');
                break;

            case 40: // Arrow Down
                e.preventDefault();
                const $nextTr = $tr.nextAll('tr:visible').first();
                if ($nextTr.length) {
                    const $downVal = $nextTr.children().eq(colIndex).find('.qty-value');
                    if ($downVal.length) $downVal.trigger('click');
                }
                break;

            case 38: // Arrow Up
                e.preventDefault();
                const $prevTr = $tr.prevAll('tr:visible').first();
                if ($prevTr.length) {
                    const $upVal = $prevTr.children().eq(colIndex).find('.qty-value');
                    if ($upVal.length) $upVal.trigger('click');
                }
                break;
        }
    });

    // Global Kısayol: Ctrl+S / Cmd+S ile kaydetme
    $(document).on('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && e.which === 83) { // Ctrl + S
            e.preventDefault();
            if (Object.keys(pendingChanges).length > 0) {
                savePendingChanges();
            }
        }
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
                $('#btn-save-changes').text('💾 Değişiklikleri Kaydet (Ctrl+S)');
                
                $('#hk-save-notice').text(res.data.updated + ' ürün stok bilgisi başarıyla güncellendi.').css({'display': 'block', 'background': '#ecfdf5', 'color': '#065f46', 'border': '1px solid #d1fae5'});
                setTimeout(() => $('#hk-save-notice').fadeOut(), 3000);
                
                clearSelection();
                loadStockList(currentPage);
            } else {
                HK.UI.alert('Kaydetme hatası: ' + (res.data ? res.data.message : 'Bilinmeyen hata'));
                $('#btn-save-changes').prop('disabled', false).text('💾 Değişiklikleri Kaydet (Ctrl+S)');
            }
        }).fail(function() {
            HK.UI.alert('Sunucu hatası oluştu!');
            $('#btn-save-changes').prop('disabled', false).text('💾 Değişiklikleri Kaydet (Ctrl+S)');
        });
    };

    window.cancelPendingChanges = function() {
        pendingChanges = {};
        updateSaveButtonState();
        $('#admin-stock-list-body tr').removeClass('hk-pending');
        loadStockList(currentPage);
    };

    // Bulk Mod İşlemleri (Yayma, Yüzdesel, Relative)
    window.broadcastToSelected = function() {
        const targetCol = $('#bulk-col-select').val();
        const mode = $('#bulk-mode-select').val();
        const valStr = $('#bulk-val-input').val().trim();
        if (valStr === '') { HK.UI.alert('Lütfen geçerli bir değer veya oran girin.'); return; }
        
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
                const currentVal = parseFloat($control.find('.qty-value').text()) || 0;
                let finalVal = valStr;

                if (mode === 'percent_plus') {
                    const pct = parseFloat(valStr.replace('%', '')) || 0;
                    finalVal = Math.round(currentVal * (1 + pct / 100));
                } else if (mode === 'percent_minus') {
                    const pct = parseFloat(valStr.replace('%', '')) || 0;
                    finalVal = Math.max(0, Math.round(currentVal * (1 - pct / 100)));
                } else if (mode === 'relative') {
                    finalVal = (valStr.startsWith('+') || valStr.startsWith('-')) ? valStr : '+' + valStr;
                }

                saveStock($control, String(finalVal));
            }
        });
    };

    window.fillDown = function() {
        const targetCol = $('#bulk-col-select').val();
        const selectedRows = $('.hk-row-cb:checked').closest('tr');
        if (selectedRows.length === 0) return;
        
        selectedRows.each(function() {
            const $tr = $(this);
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
        const range = 2;

        items.push(`<a class="hk-page-link hk-page-nav ${activePage === 1 ? 'disabled' : ''}" href="#" onclick="loadStockList(${activePage - 1}); return false;">«</a>`);

        if (activePage > range + 1) {
            items.push(`<a class="hk-page-link" href="#" onclick="loadStockList(1); return false;">1</a>`);
            if (activePage > range + 2) items.push(`<span class="hk-page-dots">...</span>`);
        }

        for (let i = Math.max(1, activePage - range); i <= Math.min(totalPages, activePage + range); i++) {
            items.push(`<a class="hk-page-link ${i === activePage ? 'active' : ''}" href="#" onclick="loadStockList(${i}); return false;">${i}</a>`);
        }

        if (activePage < totalPages - range) {
            if (activePage < totalPages - range - 1) items.push(`<span class="hk-page-dots">...</span>`);
            items.push(`<a class="hk-page-link" href="#" onclick="loadStockList(${totalPages}); return false;">${totalPages}</a>`);
        }

        items.push(`<a class="hk-page-link hk-page-nav ${activePage === totalPages ? 'disabled' : ''}" href="#" onclick="loadStockList(${activePage + 1}); return false;">»</a>`);

        const gotoHtml = `<div class="hk-page-goto-wrap" style="display:inline-flex; align-items:center; gap:6px; margin-left:12px; background:#ffffff; border:1px solid var(--hk-border-color, #cbd5e1); padding:3px 8px; border-radius:8px; box-shadow:0 1px 2px rgba(0,0,0,0.05);">
            <span style="font-size:12px; font-weight:600; color:var(--hk-text-muted, #64748b);">Sayfaya Git:</span>
            <input type="number" id="hk-page-goto-input" min="1" max="${totalPages}" value="${activePage}" style="width:48px; height:28px; text-align:center; padding:2px 4px; border-radius:6px; border:1px solid #cbd5e1; font-size:12.5px; font-weight:600;">
            <span style="font-size:12px; color:var(--hk-text-muted, #64748b); font-weight:600;">/ ${totalPages}</span>
            <button type="button" class="button button-small" id="hk-btn-page-goto" style="height:28px; line-height:26px; padding:0 10px; border-radius:6px; font-size:12px; font-weight:600;">Git</button>
        </div>`;

        $pag.append(`<div class="hk-pagination" style="display:inline-flex; align-items:center; flex-wrap:wrap; gap:8px;">${items.join('')} ${gotoHtml}</div>`);
    }

    $(document).on('click', '#hk-btn-page-goto', function() {
        submitGotoPage();
    });

    $(document).on('keydown', '#hk-page-goto-input', function(e) {
        if (e.which === 13) {
            e.preventDefault();
            submitGotoPage();
        }
    });

    function submitGotoPage() {
        const totalPages = parseInt($('#hk-page-goto-input').attr('max')) || 1;
        let page = parseInt($('#hk-page-goto-input').val());
        if (isNaN(page) || page < 1) page = 1;
        if (page > totalPages) page = totalPages;
        loadStockList(page);
    }
});
</script>
