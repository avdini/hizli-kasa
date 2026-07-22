<?php
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$table = $wpdb->prefix . 'hizli_kasa_unmatched_items';
$results = $wpdb->get_results("SELECT * FROM $table ORDER BY created_at DESC");

$total_count     = count($results);
$depo_names      = array_unique(array_column($results, 'warehouse_name'));
$unique_depo_cnt = count($depo_names);
$total_qty       = array_sum(array_column($results, 'stock_qty'));
?>

<div class="hk-settings-container">
    <!-- Header -->
    <div class="hk-settings-header">
        <div class="hk-settings-header-title">
            <h1>
                <span class="dashicons dashicons-warning" style="color:#ea580c; font-size:26px; width:26px; height:26px;"></span>
                Eşleşmeyen Ürünler
            </h1>
            <p>Excel ve stok aktarımı sırasında SKU/Barkod uyuşmazlığı nedeniyle eşleşemeyen ürünlerin listesi.</p>
        </div>
        <div>
            <button type="button" class="hk-btn-action hk-btn-action-danger" id="btn-clear-all-unmatched" style="<?php echo empty($results) ? 'display:none;' : ''; ?>" onclick="deleteAllUnmatched(true)">
                <span class="dashicons dashicons-trash"></span> Tüm Listeyi Temizle
            </button>
        </div>
    </div>

    <!-- Summary Metrics -->
    <div class="hk-metrics-grid">
        <div class="hk-metric-card">
            <div class="hk-metric-icon" style="background:#fff7ed; color:#ea580c;">
                <span class="dashicons dashicons-warning"></span>
            </div>
            <div>
                <div class="hk-metric-val" id="metric-total-count"><?php echo $total_count; ?></div>
                <div class="hk-metric-lbl">Eşleşmeyen Kayıt</div>
            </div>
        </div>

        <div class="hk-metric-card">
            <div class="hk-metric-icon" style="background:#eff6ff; color:#2563eb;">
                <span class="dashicons dashicons-building"></span>
            </div>
            <div>
                <div class="hk-metric-val" id="metric-depo-count"><?php echo $unique_depo_cnt; ?></div>
                <div class="hk-metric-lbl">Etkilenen Depo</div>
            </div>
        </div>

        <div class="hk-metric-card">
            <div class="hk-metric-icon" style="background:#f0fdf4; color:#16a34a;">
                <span class="dashicons dashicons-archive"></span>
            </div>
            <div>
                <div class="hk-metric-val" id="metric-total-qty"><?php echo $total_qty; ?></div>
                <div class="hk-metric-lbl">Toplam Hatalı Stok</div>
            </div>
        </div>

        <div class="hk-metric-card">
            <div class="hk-metric-icon" style="background:<?php echo $total_count > 0 ? '#fef2f2' : '#f0fdf4'; ?>; color:<?php echo $total_count > 0 ? '#dc2626' : '#16a34a'; ?>;">
                <span class="dashicons <?php echo $total_count > 0 ? 'dashicons-shield-warning' : 'dashicons-yes-alt'; ?>"></span>
            </div>
            <div>
                <div class="hk-metric-val" id="metric-status-val" style="font-size:15px; color:<?php echo $total_count > 0 ? '#dc2626' : '#16a34a'; ?>;">
                    <?php echo $total_count > 0 ? 'İnceleme Bekliyor' : 'Temiz Liste'; ?>
                </div>
                <div class="hk-metric-lbl">Liste Durumu</div>
            </div>
        </div>
    </div>

    <!-- Table & Search Container -->
    <div class="hk-table-container">
        <!-- Toolbar -->
        <?php if (!empty($results)): ?>
        <div class="hk-table-toolbar">
            <div class="hk-table-search">
                <span class="dashicons dashicons-search" style="color:var(--hk-text-muted);"></span>
                <input type="text" id="hk-unmatched-search" class="hk-input" placeholder="Ürün adı, SKU veya Depo ara..." style="width:100%;">
            </div>
            <div>
                <select id="hk-unmatched-depo-filter" class="hk-select">
                    <option value="">-- Tüm Depolar --</option>
                    <?php foreach ($depo_names as $depo_name): ?>
                        <option value="<?php echo esc_attr($depo_name); ?>"><?php echo esc_html($depo_name); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <?php endif; ?>

        <!-- Table -->
        <table class="hk-table-modern">
            <thead>
                <tr>
                    <th>Dosyadaki Depo</th>
                    <th>Dosyadaki Ürün Adı</th>
                    <th>SKU (Hatalı / Eksik)</th>
                    <th>Miktar</th>
                    <th>Hata Nedeni</th>
                    <th style="width:110px; text-align:center;">İşlem</th>
                </tr>
            </thead>
            <tbody id="unmatched-items-body-main">
                <?php if (empty($results)): ?>
                    <tr id="empty-state-row">
                        <td colspan="6">
                            <div class="hk-empty-state">
                                <div class="hk-empty-icon">
                                    <span class="dashicons dashicons-yes-alt"></span>
                                </div>
                                <h3 style="margin:0 0 6px 0; color:var(--hk-text-main); font-size:18px; font-weight:700;">Harika! Her Şey Yolunda</h3>
                                <p style="margin:0; color:var(--hk-text-muted); font-size:13.5px;">Sistemde şu an herhangi bir eşleşmeyen stok uyuşmazlığı bulunmuyor.</p>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($results as $item): ?>
                        <tr id="unmatched-row-<?php echo $item->id; ?>" class="unmatched-item-row" data-depo="<?php echo esc_attr($item->warehouse_name); ?>">
                            <td>
                                <span class="hk-badge-depo">
                                    <span class="dashicons dashicons-building"></span>
                                    <?php echo esc_html($item->warehouse_name); ?>
                                </span>
                            </td>
                            <td><strong style="color:var(--hk-text-main);"><?php echo esc_html($item->product_name); ?></strong></td>
                            <td>
                                <code style="background:#f1f5f9; color:#0f172a; padding:3px 8px; border-radius:6px; font-weight:700; font-size:12.5px; border:1px solid #e2e8f0;">
                                    <?php echo esc_html($item->sku ?: '— Yok —'); ?>
                                </code>
                            </td>
                            <td><strong><?php echo esc_html($item->stock_qty); ?></strong> <span style="font-size:12px; color:var(--hk-text-muted);">Adet</span></td>
                            <td>
                                <span class="hk-badge-error">
                                    <span class="dashicons dashicons-info"></span>
                                    <?php echo esc_html($item->error_msg); ?>
                                </span>
                            </td>
                            <td style="text-align:center;">
                                <button type="button" class="hk-btn-action hk-btn-action-danger" style="padding:4px 10px; font-size:12px;" onclick="deleteUnmatched(<?php echo $item->id; ?>, <?php echo (int)$item->stock_qty; ?>)">
                                    <span class="dashicons dashicons-trash" style="font-size:14px; width:14px; height:14px;"></span> Kaldır
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Live Search Filter
    $('#hk-unmatched-search').on('keyup', function() {
        filterRows();
    });

    // Depo Filter
    $('#hk-unmatched-depo-filter').on('change', function() {
        filterRows();
    });

    function filterRows() {
        const query = $('#hk-unmatched-search').val().toLowerCase();
        const selectedDepo = $('#hk-unmatched-depo-filter').val();

        $('.unmatched-item-row').each(function() {
            const rowText = $(this).text().toLowerCase();
            const rowDepo = $(this).data('depo');

            const matchesSearch = !query || rowText.indexOf(query) !== -1;
            const matchesDepo = !selectedDepo || rowDepo === selectedDepo;

            if (matchesSearch && matchesDepo) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
    }
});

function deleteUnmatched(id, qty) {
    if(!confirm('Bu kaydı listeden kaldırmak istediğinize emin misiniz?')) return;
    
    jQuery.post(ajaxurl, {
        action: 'hizli_kasa_delete_unmatched',
        id: id
    }, function(res) {
        if(res.success) {
            jQuery('#unmatched-row-' + id).fadeOut(300, function() {
                jQuery(this).remove();
                
                // Update metrics dynamically
                const countElem = jQuery('#metric-total-count');
                const qtyElem   = jQuery('#metric-total-qty');
                
                let currentCount = parseInt(countElem.text()) || 0;
                let currentQty   = parseInt(qtyElem.text()) || 0;
                
                currentCount = Math.max(0, currentCount - 1);
                currentQty   = Math.max(0, currentQty - qty);
                
                countElem.text(currentCount);
                qtyElem.text(currentQty);

                // Update Red Badge on Left Admin Menu if present
                const menuBadge = jQuery('#toplevel_page_hizli-kasa .update-plugins');
                if (menuBadge.length) {
                    if (currentCount > 0) {
                        menuBadge.text(currentCount);
                    } else {
                        menuBadge.remove();
                    }
                }
                
                if (currentCount === 0) {
                    location.reload();
                }
            });
        }
    });
}

function deleteAllUnmatched(force = false) {
    if(!force) return;
    if(!confirm('TÜM uyuşmazlık listesini temizlemek istediğinize emin misiniz? Bu işlem geri alınamaz.')) return;
    
    jQuery.post(ajaxurl, {
        action: 'hizli_kasa_clear_all_unmatched'
    }, function(res) {
        if(res.success) {
            alert(res.data.message);
            location.reload();
        }
    });
}
</script>
