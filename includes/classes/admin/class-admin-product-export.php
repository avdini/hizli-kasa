<?php
if (!defined('ABSPATH')) {
    exit;
}

class Hizli_Kasa_Admin_Product_Export {

    public static function init() {
        add_filter('bulk_actions-edit-product', [self::class, 'add_bulk_action']);
        add_filter('handle_bulk_actions-edit-product', [self::class, 'handle_bulk_action'], 10, 3);
        add_action('admin_menu', [self::class, 'register_hidden_page']);
        add_action('admin_footer-edit.php', [self::class, 'maybe_print_share_modal']);
    }

    public static function add_bulk_action($bulk_actions) {
        $bulk_actions['hk_export_products'] = __('Hızlı Kasa - Müşteri Bilgi Tablosu Dışa Aktar', 'hizli-kasa');
        return $bulk_actions;
    }

    public static function handle_bulk_action($redirect_to, $action, $post_ids) {
        if ($action !== 'hk_export_products') {
            return $redirect_to;
        }
        $redirect_to = admin_url('admin.php?page=hk-product-export&product_ids=' . implode(',', array_map('intval', $post_ids)));
        return $redirect_to;
    }

    public static function register_hidden_page() {
        add_submenu_page(
            null,
            __('Ürün Dışa Aktarma', 'hizli-kasa'),
            __('Ürün Dışa Aktarma', 'hizli-kasa'),
            'manage_options',
            'hk-product-export',
            [self::class, 'render_export_page']
        );
    }

    public static function render_export_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Yetkiniz yetersiz.', 'hizli-kasa'));
        }

        $product_ids_raw = isset($_GET['product_ids']) ? sanitize_text_field($_GET['product_ids']) : '';
        if (empty($product_ids_raw)) {
            wp_die(__('Lütfen en az bir ürün seçin.', 'hizli-kasa'));
        }

        $product_ids = array_filter(array_map('intval', explode(',', $product_ids_raw)));
        if (empty($product_ids)) {
            wp_die(__('Geçersiz Ürün ID\'leri.', 'hizli-kasa'));
        }

        global $wpdb;
        $tables       = Hizli_Kasa_Database::get_tables();
        $stok_table   = $tables['stok_konumlari'];
        $depolar_table = $tables['depolar'];

        $warehouses = $wpdb->get_results("SELECT id, name FROM {$depolar_table} ORDER BY priority DESC, id ASC");

        $placeholders = implode(',', array_fill(0, count($product_ids), '%d'));
        $stock_rows   = $wpdb->get_results($wpdb->prepare(
            "SELECT product_id, variation_id, location_id, quantity FROM {$stok_table} WHERE product_id IN ($placeholders)",
            ...$product_ids
        ));

        $stock_map = [];
        foreach ($stock_rows as $row) {
            $p_id = (int) $row->product_id;
            $v_id = (int) $row->variation_id;
            $l_id = (int) $row->location_id;
            $stock_map[$p_id][$v_id][$l_id] = floatval($row->quantity);
        }

        $options     = [];
        $is_public   = false;

        self::print_share_panel($product_ids);
        include HIZLI_KASA_PATH . 'includes/views/product-export-template.php';
    }

    private static function print_share_panel($product_ids) {
        $nonce          = wp_create_nonce('hk_catalog_share_nonce');
        $product_ids_js = implode(',', $product_ids);
        ?>
        <div id="hkSharePanel" style="
            font-family:'Inter',system-ui,sans-serif;
            background:#fff;
            border:1px solid #e2e8f0;
            border-radius:12px;
            padding:16px 20px;
            margin:16px 0;
            max-width:700px;
            box-shadow:0 2px 8px rgb(0 0 0/.06);
            display:flex;
            align-items:center;
            gap:16px;
            flex-wrap:wrap;
        ">
            <div style="margin-right:auto;">
                <div style="font-weight:700;font-size:14px;color:#0f172a;">🔗 Müşteri Paylaşım Linki</div>
                <div style="font-size:12px;color:#64748b;margin-top:2px;">WordPress girişi gerektirmeden açılabilen güvenli, süreli link oluşturun.</div>
            </div>

            <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                <select id="hkTtlDays" style="font-family:inherit;font-size:13px;padding:7px 10px;border:1px solid #e2e8f0;border-radius:8px;background:#f8fafc;color:#0f172a;cursor:pointer;">
                    <option value="1">1 Gün</option>
                    <option value="7" selected>7 Gün</option>
                    <option value="30">30 Gün</option>
                </select>
                <input id="hkCatalogTitle" type="text" placeholder="Başlık (opsiyonel)" style="font-family:inherit;font-size:13px;padding:7px 12px;border:1px solid #e2e8f0;border-radius:8px;width:180px;color:#0f172a;">
                <button id="hkCreateShareBtn" onclick="hkCreateShare()" style="
                    font-family:inherit;font-size:13px;font-weight:600;
                    padding:7px 16px;border-radius:8px;
                    background:#4f46e5;color:#fff;border:none;cursor:pointer;
                    transition:background .18s;
                " onmouseover="this.style.background='#4338ca'" onmouseout="this.style.background='#4f46e5'">
                    Link Oluştur
                </button>
            </div>

            <div id="hkShareResult" style="width:100%;display:none;">
                <div style="display:flex;align-items:center;gap:8px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:8px 12px;">
                    <input id="hkShareUrl" type="text" readonly style="flex:1;border:none;background:transparent;font-family:monospace;font-size:12px;color:#4f46e5;outline:none;">
                    <button onclick="hkCopyShareUrl()" style="
                        font-family:inherit;font-size:12px;font-weight:600;
                        padding:5px 12px;border-radius:6px;
                        background:#4f46e5;color:#fff;border:none;cursor:pointer;
                        white-space:nowrap;
                    ">📋 Kopyala</button>
                    <button onclick="hkDeleteShare()" id="hkDeleteBtn" style="
                        font-family:inherit;font-size:12px;
                        padding:5px 10px;border-radius:6px;
                        background:#fef2f2;color:#dc2626;border:1px solid #fecaca;cursor:pointer;
                    ">Sil</button>
                </div>
                <div id="hkExpireInfo" style="font-size:11px;color:#64748b;margin-top:4px;"></div>
            </div>
        </div>

        <script>
        var _hkNonce     = '<?php echo esc_js($nonce); ?>';
        var _hkPids      = '<?php echo esc_js($product_ids_js); ?>';
        var _hkToken     = '';
        var _hkAjaxUrl   = '<?php echo esc_url(admin_url('admin-ajax.php')); ?>';

        function hkCreateShare() {
            var btn   = document.getElementById('hkCreateShareBtn');
            var ttl   = document.getElementById('hkTtlDays').value;
            var title = document.getElementById('hkCatalogTitle').value;
            btn.textContent = '⏳ Oluşturuluyor...';
            btn.disabled    = true;

            var fd = new FormData();
            fd.append('action', 'hk_create_catalog_share');
            fd.append('nonce', _hkNonce);
            fd.append('product_ids', _hkPids);
            fd.append('ttl_days', ttl);
            fd.append('catalog_title', title);

            fetch(_hkAjaxUrl, { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    btn.textContent = 'Yenile';
                    btn.disabled    = false;
                    if (data.success) {
                        _hkToken = data.data.token;
                        document.getElementById('hkShareUrl').value   = data.data.url;
                        document.getElementById('hkShareResult').style.display = 'block';
                        document.getElementById('hkExpireInfo').textContent    = '⏱ Geçerlilik: ' + data.data.expires_in;
                        document.getElementById('hkDeleteBtn').dataset.token   = _hkToken;
                    } else {
                        alert('Hata: ' + (data.data && data.data.message ? data.data.message : 'Bilinmeyen hata.'));
                    }
                })
                .catch(() => {
                    btn.textContent = 'Link Oluştur';
                    btn.disabled    = false;
                    alert('Bağlantı hatası.');
                });
        }

        function hkCopyShareUrl() {
            var urlEl = document.getElementById('hkShareUrl');
            urlEl.select();
            navigator.clipboard.writeText(urlEl.value).then(() => {
                var btn = event.target;
                btn.textContent = '✓ Kopyalandı!';
                setTimeout(() => btn.textContent = '📋 Kopyala', 2000);
            });
        }

        function hkDeleteShare() {
            if (!_hkToken || !confirm('Bu paylaşım linkini silmek istediğinize emin misiniz?')) return;
            var fd = new FormData();
            fd.append('action', 'hk_delete_catalog_share');
            fd.append('nonce', _hkNonce);
            fd.append('token', _hkToken);
            fetch(_hkAjaxUrl, { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('hkShareResult').style.display = 'none';
                        document.getElementById('hkShareUrl').value = '';
                        document.getElementById('hkCreateShareBtn').textContent = 'Link Oluştur';
                        _hkToken = '';
                    }
                });
        }
        </script>
        <?php
    }

    public static function maybe_print_share_modal() {
        // Placeholder — share UI is printed inline on the export page.
    }
}
