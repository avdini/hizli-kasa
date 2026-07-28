<?php
/**
 * Hızlı Kasa - Sistem & Denetim Günlüğü (Log UI Dashboard)
 *
 * Modern Hub estetiği ile uyumlu, canlı filtreli, çift katmanlı log takip paneli.
 *
 * @package HizliKasa
 */

if (!defined('ABSPATH')) {
    exit;
}

$nonce = wp_create_nonce('wp_rest');
?>

<div class="hk-log-dashboard">
    <!-- Stil Tanımlamaları -->
    <style>
        .hk-log-dashboard {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
            color: #1e293b;
            margin-top: 10px;
        }

        /* KPI Metrik Kartları */
        .hk-log-kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        .hk-kpi-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 16px 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .hk-kpi-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }
        .hk-kpi-info p {
            margin: 0;
            font-size: 13px;
            font-weight: 500;
            color: #64748b;
        }
        .hk-kpi-info h3 {
            margin: 4px 0 0 0;
            font-size: 26px;
            font-weight: 700;
            color: #0f172a;
        }
        .hk-kpi-icon {
            width: 44px;
            height: 44px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }
        .hk-kpi-icon.total { background: #e0f2fe; color: #0284c7; }
        .hk-kpi-icon.info { background: #dcfce7; color: #16a34a; }
        .hk-kpi-icon.warning { background: #fef3c7; color: #d97706; }
        .hk-kpi-icon.error { background: #fee2e2; color: #dc2626; }

        /* Filtre Paneli */
        .hk-log-filter-box {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 18px 20px;
            margin-bottom: 24px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        .hk-filter-row {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 14px;
        }
        .hk-filter-search {
            flex: 1;
            min-width: 260px;
            position: relative;
        }
        .hk-filter-search input {
            width: 100%;
            padding: 9px 12px 9px 36px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 14px;
            outline: none;
            transition: border-color 0.2s;
        }
        .hk-filter-search input:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
        }
        .hk-filter-search .dashicons {
            position: absolute;
            left: 10px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
        }
        .hk-filter-controls {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
        }
        .hk-filter-controls select, .hk-filter-controls input[type="date"] {
            padding: 8px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 13px;
            background: #f8fafc;
            color: #334155;
        }

        /* Channel Pills / Tabs */
        .hk-channel-pills {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }
        .hk-pill {
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 500;
            background: #f1f5f9;
            color: #475569;
            border: 1px solid transparent;
            cursor: pointer;
            transition: all 0.2s;
        }
        .hk-pill:hover {
            background: #e2e8f0;
            color: #0f172a;
        }
        .hk-pill.active {
            background: #2563eb;
            color: #ffffff;
            border-color: #2563eb;
            box-shadow: 0 2px 6px rgba(37, 99, 235, 0.25);
        }

        /* Log Akış Listesi */
        .hk-log-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .hk-log-item {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-left: 5px solid #94a3b8;
            border-radius: 10px;
            padding: 14px 18px;
            box-shadow: 0 1px 2px rgba(0,0,0,0.03);
            transition: background 0.15s;
        }
        .hk-log-item:hover {
            background: #f8fafc;
        }
        .hk-log-item.level-info { border-left-color: #22c55e; }
        .hk-log-item.level-warning { border-left-color: #f59e0b; }
        .hk-log-item.level-error { border-left-color: #ef4444; }
        .hk-log-item.level-debug { border-left-color: #64748b; }

        .hk-log-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 6px;
        }
        .hk-log-meta {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 12px;
            color: #64748b;
        }
        .hk-badge {
            padding: 2px 8px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .hk-badge.channel-pos { background: #dbeafe; color: #1e40af; }
        .hk-badge.channel-stock { background: #dcfce7; color: #166534; }
        .hk-badge.channel-sku { background: #fef3c7; color: #92400e; }
        .hk-badge.channel-payment { background: #fae8ff; color: #86198f; }
        .hk-badge.channel-sync { background: #e0e7ff; color: #3730a3; }
        .hk-badge.channel-system { background: #f1f5f9; color: #334155; }

        .hk-badge.level-info { background: #dcfce7; color: #15803d; }
        .hk-badge.level-warning { background: #fef3c7; color: #b45309; }
        .hk-badge.level-error { background: #fee2e2; color: #b91c1c; }
        .hk-badge.level-debug { background: #f1f5f9; color: #475569; }

        .hk-log-req {
            font-family: monospace;
            background: #f1f5f9;
            padding: 2px 6px;
            border-radius: 4px;
            color: #475569;
            cursor: pointer;
        }
        .hk-log-req:hover {
            background: #cbd5e1;
            color: #0f172a;
        }

        .hk-log-body {
            font-size: 14px;
            font-weight: 500;
            color: #1e293b;
            line-height: 1.4;
        }

        /* Accordion / Context Detail */
        .hk-log-actions {
            margin-top: 8px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .hk-btn-toggle-context {
            background: none;
            border: none;
            color: #2563eb;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            padding: 0;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        .hk-btn-toggle-context:hover {
            text-decoration: underline;
        }
        .hk-context-panel {
            display: none;
            margin-top: 10px;
            background: #0f172a;
            color: #f8fafc;
            padding: 12px 14px;
            border-radius: 8px;
            font-family: monospace;
            font-size: 12px;
            overflow-x: auto;
            white-space: pre-wrap;
            word-break: break-all;
        }

        /* Modal Trace */
        .hk-modal-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(4px);
            z-index: 99999;
            align-items: center;
            justify-content: center;
        }
        .hk-modal-overlay.active { display: flex; }
        .hk-modal-content {
            background: #ffffff;
            width: 90%;
            max-width: 800px;
            max-height: 85vh;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1);
            overflow-y: auto;
            position: relative;
        }
        .hk-modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 16px;
            padding-bottom: 12px;
            border-bottom: 1px solid #e2e8f0;
        }
        .hk-modal-close {
            background: none;
            border: none;
            font-size: 24px;
            color: #94a3b8;
            cursor: pointer;
        }
        .hk-modal-close:hover { color: #0f172a; }

        /* Sayfalama */
        .hk-pagination {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: 20px;
            padding: 12px 0;
        }
        .hk-btn-page {
            padding: 6px 14px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            background: #ffffff;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
        }
        .hk-btn-page:disabled { opacity: 0.5; cursor: not-allowed; }
    </style>

    <!-- Header & Action Bar -->
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px;">
        <div>
            <h2 style="margin: 0; font-size: 20px; font-weight: 700; color: #0f172a;">Sistem & Denetim Günlüğü</h2>
            <p style="margin: 4px 0 0 0; font-size: 13px; color: #64748b;">Hızlı Kasa eklentisinde gerçekleşen tüm operasyonel hareketleri ve durum loglarını canlı takip edin.</p>
        </div>
        <div style="display: flex; gap: 10px; align-items: center;">
            <label style="font-size: 13px; font-weight: 500; color: #475569; display: flex; align-items: center; gap: 6px; cursor: pointer;">
                <input type="checkbox" id="hk-auto-refresh"> Canlı Takip Et (5sn)
            </label>
            <button id="hk-btn-clear-logs" class="button button-secondary" style="color: #dc2626; border-color: #fca5a5;">
                <span class="dashicons dashicons-trash" style="vertical-align: middle;"></span> Logları Temizle
            </button>
        </div>
    </div>

    <!-- Metrik Kartları -->
    <div class="hk-log-kpi-grid">
        <div class="hk-kpi-card">
            <div class="hk-kpi-info">
                <p>Toplam Kayıt</p>
                <h3 id="stat-total-logs">-</h3>
            </div>
            <div class="hk-kpi-icon total"><span class="dashicons dashicons-list-view"></span></div>
        </div>
        <div class="hk-kpi-card">
            <div class="hk-kpi-info">
                <p>Bilgi / Normal</p>
                <h3 id="stat-info-logs">-</h3>
            </div>
            <div class="hk-kpi-icon info"><span class="dashicons dashicons-yes-alt"></span></div>
        </div>
        <div class="hk-kpi-card">
            <div class="hk-kpi-info">
                <p>Uyarılar</p>
                <h3 id="stat-warning-logs">-</h3>
            </div>
            <div class="hk-kpi-icon warning"><span class="dashicons dashicons-warning"></span></div>
        </div>
        <div class="hk-kpi-card">
            <div class="hk-kpi-info">
                <p>Bugünkü Hatalar</p>
                <h3 id="stat-today-errors">-</h3>
            </div>
            <div class="hk-kpi-icon error"><span class="dashicons dashicons-dismiss"></span></div>
        </div>
    </div>

    <!-- Filtre Kutusu -->
    <div class="hk-log-filter-box">
        <div class="hk-filter-row">
            <div class="hk-filter-search">
                <span class="dashicons dashicons-search"></span>
                <input type="text" id="hk-log-search-input" placeholder="Sipariş No, Ürün ID, Kasiyer veya İstek Kimliği (req_xxx) ara...">
            </div>
            <div class="hk-filter-controls">
                <select id="hk-log-level-select">
                    <option value="">Tüm Seviyeler</option>
                    <option value="info">🟢 Bilgi (Info)</option>
                    <option value="warning">🟡 Uyarı (Warning)</option>
                    <option value="error">🔴 Hata (Error)</option>
                    <option value="debug">⚙️ Debug</option>
                </select>
                <input type="date" id="hk-log-date-from" title="Başlangıç Tarihi">
                <input type="date" id="hk-log-date-to" title="Bitiş Tarihi">
            </div>
        </div>
        
        <!-- Channel Pills -->
        <div class="hk-channel-pills" id="hk-channel-pills">
            <button class="hk-pill active" data-channel="">Tümü</button>
            <button class="hk-pill" data-channel="pos">🛒 POS & Kasa</button>
            <button class="hk-pill" data-channel="stock">📦 Stok & Depo</button>
            <button class="hk-pill" data-channel="sku">🏷️ Otomatik SKU</button>
            <button class="hk-pill" data-channel="payment">💳 Ödeme</button>
            <button class="hk-pill" data-channel="sync">🔄 Senkronizasyon</button>
            <button class="hk-pill" data-channel="system">⚙️ Sistem</button>
        </div>
    </div>

    <!-- Akış Listesi -->
    <div id="hk-log-list-container" class="hk-log-list">
        <div style="text-align: center; padding: 40px; color: #64748b;">
            <span class="dashicons dashicons-update spin" style="font-size: 28px; width: 28px; height: 28px;"></span>
            <p style="margin-top: 10px;">Log kayıtları yükleniyor...</p>
        </div>
    </div>

    <!-- Sayfalama Alt Bar -->
    <div class="hk-pagination">
        <span id="hk-page-info" style="font-size: 13px; color: #64748b;">Sayfa 1 / 1 (Toplam 0 kayıt)</span>
        <div style="display: flex; gap: 8px;">
            <button id="hk-btn-prev" class="hk-btn-page" disabled>Önceki</button>
            <button id="hk-btn-next" class="hk-btn-page" disabled>Sonraki</button>
        </div>
    </div>

    <!-- Trace Modal -->
    <div id="hk-trace-modal" class="hk-modal-overlay">
        <div class="hk-modal-content">
            <div class="hk-modal-header">
                <div>
                    <h3 style="margin: 0; font-size: 18px; color: #0f172a;">İşlem Adım Zinciri (Trace Timeline)</h3>
                    <p style="margin: 4px 0 0 0; font-size: 12px; color: #64748b;" id="hk-trace-req-title">Request ID: -</p>
                </div>
                <button class="hk-modal-close" onclick="closeTraceModal()">&times;</button>
            </div>
            <div id="hk-trace-body" class="hk-log-list">
                <!-- Trace adımları yüklenecek -->
            </div>
        </div>
    </div>
</div>

<!-- Dynamic JS Controller -->
<script>
(function() {
    const API_BASE = '<?php echo esc_url_raw(rest_url('hizli-kasa/v2/logs')); ?>';
    const NONCE = '<?php echo esc_js($nonce); ?>';

    let currentPage = 1;
    let currentChannel = '';
    let autoRefreshInterval = null;

    document.addEventListener('DOMContentLoaded', () => {
        fetchStats();
        fetchLogs();

        // Search input debounce
        let searchTimer;
        document.getElementById('hk-log-search-input').addEventListener('input', () => {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(() => { currentPage = 1; fetchLogs(); }, 400);
        });

        document.getElementById('hk-log-level-select').addEventListener('change', () => { currentPage = 1; fetchLogs(); });
        document.getElementById('hk-log-date-from').addEventListener('change', () => { currentPage = 1; fetchLogs(); });
        document.getElementById('hk-log-date-to').addEventListener('change', () => { currentPage = 1; fetchLogs(); });

        // Channel Pills Click
        document.getElementById('hk-channel-pills').addEventListener('click', (e) => {
            if (e.target.classList.contains('hk-pill')) {
                document.querySelectorAll('.hk-pill').forEach(p => p.classList.remove('active'));
                e.target.classList.add('active');
                currentChannel = e.target.dataset.channel;
                currentPage = 1;
                fetchLogs();
            }
        });

        // Pagination
        document.getElementById('hk-btn-prev').addEventListener('click', () => { if (currentPage > 1) { currentPage--; fetchLogs(); } });
        document.getElementById('hk-btn-next').addEventListener('click', () => { currentPage++; fetchLogs(); });

        // Auto Refresh Toggle
        document.getElementById('hk-auto-refresh').addEventListener('change', (e) => {
            if (e.target.checked) {
                autoRefreshInterval = setInterval(() => { fetchLogs(true); fetchStats(); }, 5000);
            } else {
                clearInterval(autoRefreshInterval);
            }
        });

        // Clear Logs
        document.getElementById('hk-btn-clear-logs').addEventListener('click', async () => {
            if (!(await HK.UI.confirm('Tüm log kayıtları temizlenecek. Emin misiniz?'))) return;
            try {
                const res = await fetch(API_BASE + '/clear', {
                    method: 'DELETE',
                    headers: { 'X-WP-Nonce': NONCE }
                });
                const data = await res.json();
                HK.UI.alert(data.data.message || 'Loglar temizlendi.');
                fetchStats();
                fetchLogs();
            } catch (err) {
                HK.UI.alert('Loglar temizlenirken bir hata oluştu.');
            }
        });
    });

    async function fetchStats() {
        try {
            const res = await fetch(API_BASE + '/stats', { headers: { 'X-WP-Nonce': NONCE } });
            const json = await res.json();
            if (json.success && json.data) {
                document.getElementById('stat-total-logs').textContent = json.data.total_logs || 0;
                document.getElementById('stat-info-logs').textContent = (json.data.levels && json.data.levels.info) ? json.data.levels.info : 0;
                document.getElementById('stat-warning-logs').textContent = (json.data.levels && json.data.levels.warning) ? json.data.levels.warning : 0;
                document.getElementById('stat-today-errors').textContent = json.data.today_errors || 0;
            }
        } catch (err) {
            console.error('Stats fetch error:', err);
        }
    }

    async function fetchLogs(isSilent = false) {
        const container = document.getElementById('hk-log-list-container');
        if (!isSilent) {
            container.innerHTML = '<div style="text-align: center; padding: 40px; color: #64748b;"><span class="dashicons dashicons-update spin" style="font-size: 28px;"></span><p style="margin-top: 10px;">Loglar yükleniyor...</p></div>';
        }

        const search = document.getElementById('hk-log-search-input').value;
        const level = document.getElementById('hk-log-level-select').value;
        const dateFrom = document.getElementById('hk-log-date-from').value;
        const dateTo = document.getElementById('hk-log-date-to').value;

        let url = `${API_BASE}?page=${currentPage}&limit=20`;
        if (currentChannel) url += `&channel=${encodeURIComponent(currentChannel)}`;
        if (level) url += `&level=${encodeURIComponent(level)}`;
        if (search) url += `&search=${encodeURIComponent(search)}`;
        if (dateFrom) url += `&date_from=${encodeURIComponent(dateFrom)}`;
        if (dateTo) url += `&date_to=${encodeURIComponent(dateTo)}`;

        try {
            const res = await fetch(url, { headers: { 'X-WP-Nonce': NONCE } });
            const json = await res.json();

            if (!json.success || !json.data || !json.data.items) {
                container.innerHTML = '<div style="text-align: center; padding: 40px; color: #94a3b8;">Log kaydı bulunamadı.</div>';
                return;
            }

            const items = json.data.items;
            const pagination = json.data.pagination;

            if (items.length === 0) {
                container.innerHTML = '<div style="text-align: center; padding: 40px; color: #94a3b8;">Seçilen filtrelere uygun log bulunamadı.</div>';
            } else {
                container.innerHTML = items.map(renderLogItem).join('');
            }

            // Pagination Update
            document.getElementById('hk-page-info').textContent = `Sayfa ${pagination.current_page} / ${pagination.total_pages || 1} (Toplam ${pagination.total_items} kayıt)`;
            document.getElementById('hk-btn-prev').disabled = (pagination.current_page <= 1);
            document.getElementById('hk-btn-next').disabled = (pagination.current_page >= pagination.total_pages);

        } catch (err) {
            container.innerHTML = '<div style="text-align: center; padding: 40px; color: #ef4444;">Loglar yüklenirken bir hata oluştu.</div>';
        }
    }

    function renderLogItem(item) {
        const hasContext = item.context && Object.keys(item.context).length > 0;
        const contextJson = hasContext ? JSON.stringify(item.context, null, 2) : '';

        return `
            <div class="hk-log-item level-${item.level}">
                <div class="hk-log-header">
                    <div class="hk-log-meta">
                        <span class="hk-badge channel-${item.channel}">${item.channel}</span>
                        <span class="hk-badge level-${item.level}">${item.level}</span>
                        <span>${item.created_at}</span>
                        ${item.user_id ? `<span>• Kasiyer #${item.user_id}</span>` : ''}
                    </div>
                    <span class="hk-log-req" onclick="showTrace('${item.request_id}')" title="İşlem Adımlarını Gör">${item.request_id}</span>
                </div>
                <div class="hk-log-body">${escapeHtml(item.message)}</div>
                ${hasContext ? `
                    <div class="hk-log-actions">
                        <button class="hk-btn-toggle-context" onclick="toggleContext(this)">
                            <span class="dashicons dashicons-arrow-down-alt2" style="font-size: 14px;"></span> Teknik Detaylar (JSON)
                        </button>
                    </div>
                    <div class="hk-context-panel">${escapeHtml(contextJson)}</div>
                ` : ''}
            </div>
        `;
    }

    window.toggleContext = function(btn) {
        const panel = btn.parentElement.nextElementSibling;
        if (panel.style.display === 'block') {
            panel.style.display = 'none';
            btn.querySelector('.dashicons').className = 'dashicons dashicons-arrow-down-alt2';
        } else {
            panel.style.display = 'block';
            btn.querySelector('.dashicons').className = 'dashicons dashicons-arrow-up-alt2';
        }
    };

    window.showTrace = async function(reqId) {
        const modal = document.getElementById('hk-trace-modal');
        const body = document.getElementById('hk-trace-body');
        document.getElementById('hk-trace-req-title').textContent = `Request ID: ${reqId}`;
        body.innerHTML = '<div style="text-align: center; padding: 20px;"><span class="dashicons dashicons-update spin"></span> Yükleniyor...</div>';
        modal.classList.add('active');

        try {
            const res = await fetch(`${API_BASE}/trace/${reqId}`, { headers: { 'X-WP-Nonce': NONCE } });
            const json = await res.json();
            if (json.success && json.data && json.data.steps) {
                body.innerHTML = json.data.steps.map(renderLogItem).join('');
            } else {
                body.innerHTML = '<p>Adım kaydı bulunamadı.</p>';
            }
        } catch (err) {
            body.innerHTML = '<p style="color: red;">Trace bilgisi yüklenemedi.</p>';
        }
    };

    window.closeTraceModal = function() {
        document.getElementById('hk-trace-modal').classList.remove('active');
    };

    function escapeHtml(str) {
        return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }
})();
</script>
