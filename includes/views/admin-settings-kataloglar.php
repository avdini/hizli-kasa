<?php
/**
 * Admin Paylaşılan Kataloglar Görünümü
 */
if (!defined('ABSPATH')) exit;

$shares = Hizli_Kasa_Catalog_Share_Manager::get_all_shares();
?>

<div class="katalog-yonetimi-konteyner">
    <div class="card" style="max-width: 100%; margin-top: 0; padding: 24px; border-radius: 12px; border: 1px solid var(--hk-border-color); box-shadow: var(--hk-shadow-sm);">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <h3 style="margin:0; font-size:18px; font-weight:700; color:var(--hk-text-main);">Paylaşılan Katalog Bağlantıları</h3>
            <span style="font-size:13px; color:var(--hk-text-muted); font-weight:500;"><?php echo count($shares); ?> aktif katalog</span>
        </div>

        <table class="wp-list-table widefat fixed striped" style="border:none; box-shadow:none;">
            <thead>
                <tr>
                    <th style="font-weight:600; color:var(--hk-text-muted);">Katalog Başlığı</th>
                    <th style="font-weight:600; color:var(--hk-text-muted); width: 100px; text-align:center;">Ürün Sayısı</th>
                    <th style="font-weight:600; color:var(--hk-text-muted); width: 150px;">Oluşturulma Tarihi</th>
                    <th style="font-weight:600; color:var(--hk-text-muted); width: 150px;">Geçerlilik Süresi</th>
                    <th style="font-weight:600; color:var(--hk-text-muted); width: 120px; text-align:center;">Durum</th>
                    <th style="font-weight:600; color:var(--hk-text-muted); width: 180px; text-align:center;">İşlemler</th>
                </tr>
            </thead>
            <tbody id="hk-katalog-table-body">
                <?php if (empty($shares)): ?>
                    <tr>
                        <td colspan="6" style="text-align:center; padding: 40px 10px; color: var(--hk-text-muted);">
                            <span class="dashicons dashicons-share" style="font-size:48px; width:48px; height:48px; margin-bottom:10px; opacity:0.3; display:inline-block;"></span>
                            <p style="margin:0; font-size:14px; font-weight:500;">Henüz paylaşılan bir katalog bulunmuyor.</p>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($shares as $share):
                        $options = json_decode($share->options, true) ?: [];
                        $title = !empty($options['title']) ? esc_html($options['title']) : 'Ürün Bilgi & Fiyat Kataloğu';
                        
                        $product_ids = json_decode($share->product_ids, true) ?: [];
                        $prod_count = count($product_ids);
                        
                        $created_at = date('d.m.Y H:i', strtotime($share->created_at));
                        $expires_at = date('d.m.Y H:i', strtotime($share->expires_at));
                        
                        $is_expired = strtotime($share->expires_at) <= current_time('timestamp');
                        $status_label = $is_expired ? 'Süresi Dolmuş' : 'Aktif';
                        $status_class = $is_expired ? 'hk-status-expired' : 'hk-status-active';
                        
                        $public_url = Hizli_Kasa_Catalog_Share_Manager::get_public_url($share->token);
                    ?>
                        <tr>
                            <td style="vertical-align: middle;">
                                <strong><?php echo $title; ?></strong>
                                <br>
                                <span style="font-size: 11px; color: var(--hk-text-muted); font-family:monospace;"><?php echo esc_html($share->token); ?></span>
                            </td>
                            <td style="vertical-align: middle; text-align:center; font-weight:600;">
                                <?php echo $prod_count; ?>
                            </td>
                            <td style="vertical-align: middle;">
                                <?php echo $created_at; ?>
                            </td>
                            <td style="vertical-align: middle;">
                                <?php echo $expires_at; ?>
                            </td>
                            <td style="vertical-align: middle; text-align:center;">
                                <span class="hk-status-badge <?php echo $status_class; ?>">
                                    <?php echo $status_label; ?>
                                </span>
                            </td>
                            <td style="vertical-align: middle; text-align:center;">
                                <button type="button" class="button button-secondary" onclick="copyToClipboard('<?php echo esc_url($public_url); ?>', this)">
                                    <span class="dashicons dashicons-admin-links"></span> Bağlantı
                                </button>
                                <button type="button" class="button button-link-delete" style="margin-left: 6px; font-weight: 500;" onclick="deleteCatalogShare('<?php echo esc_attr($share->token); ?>', this)">
                                    Sil
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<style>
    .hk-status-badge {
        display: inline-block;
        padding: 4px 10px;
        border-radius: 9999px;
        font-size: 11px;
        font-weight: 600;
    }
    .hk-status-active {
        background: #ecfdf5;
        color: #059669;
    }
    .hk-status-expired {
        background: #fef2f2;
        color: #dc2626;
    }
    .wp-list-table tr:hover td {
        background: #f8fafc;
    }
    .katalog-yonetimi-konteyner .button .dashicons {
        font-size: 15px;
        width: 15px;
        height: 15px;
        line-height: 15px;
        vertical-align: middle;
        margin-right: 2px;
    }
</style>

<script>
    function copyToClipboard(text, button) {
        navigator.clipboard.writeText(text)
            .then(() => {
                const originalContent = button.innerHTML;
                button.innerHTML = '<span class="dashicons dashicons-yes"></span> Kopyalandı';
                button.style.backgroundColor = '#ecfdf5';
                button.style.borderColor = '#a7f3d0';
                button.style.color = '#059669';
                setTimeout(() => {
                    button.innerHTML = originalContent;
                    button.style.backgroundColor = '';
                    button.style.borderColor = '';
                    button.style.color = '';
                }, 2000);
            })
            .catch(err => {
                console.error('Kopyalama başarısız:', err);
                alert('Bağlantı kopyalanamadı, lütfen manuel kopyalayın: ' + text);
            });
    }

    function deleteCatalogShare(token, button) {
        if (!confirm('Bu paylaşılan katalog bağlantısını silmek istediğinize emin misiniz?')) {
            return;
        }
        
        button.disabled = true;
        button.textContent = 'Siliniyor...';

        const data = new URLSearchParams();
        data.append('action', 'hk_delete_catalog_share');
        data.append('token', token);
        data.append('nonce', '<?php echo wp_create_nonce("hk_catalog_share_nonce"); ?>');

        fetch(ajaxurl, {
            method: 'POST',
            body: data,
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            }
        })
        .then(response => response.json())
        .then(res => {
            if (res.success) {
                const row = button.closest('tr');
                if (row) {
                    row.style.transition = 'all 0.3s ease';
                    row.style.opacity = '0';
                    row.style.transform = 'translateX(20px)';
                    setTimeout(() => {
                        row.remove();
                        const tbody = document.querySelector('#hk-katalog-table-body');
                        if (tbody && tbody.querySelectorAll('tr').length === 0) {
                            tbody.innerHTML = `
                                <tr>
                                    <td colspan="6" style="text-align:center; padding: 40px 10px; color: var(--hk-text-muted);">
                                        <span class="dashicons dashicons-share" style="font-size:48px; width:48px; height:48px; margin-bottom:10px; opacity:0.3; display:inline-block;"></span>
                                        <p style="margin:0; font-size:14px; font-weight:500;">Henüz paylaşılan bir katalog bulunmuyor.</p>
                                    </td>
                                </tr>
                            `;
                        }
                    }, 300);
                }
            } else {
                alert('Hata: ' + (res.data ? res.data.message : 'Bilinmeyen bir hata oluştu.'));
                button.disabled = false;
                button.textContent = 'Sil';
            }
        })
        .catch(err => {
            console.error(err);
            alert('İstek gönderilirken bir hata oluştu.');
            button.disabled = false;
            button.textContent = 'Sil';
        });
    }
</script>
