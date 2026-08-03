# Masraf KPI Kartı Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a mini Masraf (expenses) info card to the statistics dashboard in the reports tab, displaying total expenses and expense count, with a click action that redirects to the main Masraf tab.

**Architecture:** Update the `/hizli-kasa/v1/statistics/summary` API in PHP to return `masraf_sayisi` alongside `toplam_masraf`. In `statistics-dashboard.js`, render a new compact KPI card (`kpi-masraf`) in `stat-kpi-compact-grid` and bind its click event to activate `.ust-sekme[data-tab="masraf"]`. In `statistics.css`, add styling and update grid layout for 5 cards.

**Tech Stack:** PHP (WooCommerce REST API), Vanilla JS (ES6/jQuery/Event Handlers), CSS (CSS variables, CSS Grid).

## Global Constraints
- No native browser dialogs (`alert`, `confirm`, `prompt`).
- No verbose inline code comments (per AGENTS.md).
- Follow OOP and base classes for backend; preserve existing API contracts without breaking changes.

---

### Task 1: Backend REST API Update

**Files:**
- Modify: `includes/api/api-istatistik.php:251-266`, `includes/api/api-istatistik.php:408-421`

**Interfaces:**
- Consumes: Database table `tables['masraflar']`, parameters `date_start`, `date_end`, `depo_id`
- Produces: API response JSON field `kpi.masraf_sayisi` (integer)

- [ ] **Step 1: Update SQL query in `hizli_kasa_statistics_summary` to count masraf rows**

In `includes/api/api-istatistik.php`, update lines 255-265:
```php
    if ($depo_id > 0) {
        $masraf_rows = $wpdb->get_results($wpdb->prepare(
            "SELECT SUM(amount) as total, COUNT(id) as count FROM $m_table WHERE created_at BETWEEN %s AND %s AND location_id = %d",
            $ts_start, $ts_end, $depo_id
        ));
    } else {
        $masraf_rows = $wpdb->get_results($wpdb->prepare(
            "SELECT SUM(amount) as total, COUNT(id) as count FROM $m_table WHERE created_at BETWEEN %s AND %s",
            $ts_start, $ts_end
        ));
    }
    $toplam_masraf = (float) ($masraf_rows[0]->total ?? 0);
    $masraf_sayisi = (int) ($masraf_rows[0]->count ?? 0);
```

- [ ] **Step 2: Add `masraf_sayisi` to API return array**

In `includes/api/api-istatistik.php`, update response data around line 412:
```php
        'kpi' => [
            'toplam_ciro'            => round($toplam_ciro, 2),
            'toplam_iade'            => round($toplam_iade, 2),
            'toplam_masraf'          => round($toplam_masraf, 2),
            'masraf_sayisi'          => $masraf_sayisi,
            'toplam_iskonto'         => round($toplam_iskonto, 2),
            'toplam_urun_adedi'      => $toplam_urun_adedi,
            'sepet_ortalamasi'       => $sepet_ortalamasi,
            'sepet_urun_ortalamasi'  => $sepet_urun_ortalamasi,
            'iskonto_siparis_sayisi' => $iskonto_siparis_sayisi,
            'net_ciro'               => round($toplam_ciro - $toplam_iade - $toplam_masraf, 2),
            'siparis_sayisi'         => $siparis_sayisi,
            'iade_sayisi'            => $iade_sayisi,
        ],
```

- [ ] **Step 3: Verify syntax**

Run: `php -l includes/api/api-istatistik.php`
Expected: `No syntax errors detected in includes/api/api-istatistik.php`

---

### Task 2: Frontend Dashboard & Styles Update

**Files:**
- Modify: `assets/js/modules/statistics-dashboard.js:137-142`, `assets/js/modules/statistics-dashboard.js:278-303`
- Modify: `assets/css/modules/statistics.css:108-112`, `assets/css/modules/statistics.css:390-398`

**Interfaces:**
- Consumes: API response field `data.kpi.toplam_masraf` and `data.kpi.masraf_sayisi`, DOM element `.ust-sekme[data-tab="masraf"]`
- Produces: Rendered `kpi-masraf` card in compact grid, click redirection handler to Masraf tab

- [ ] **Step 1: Add `kpi-masraf` compact card to `_renderDashboard` in `statistics-dashboard.js`**

In `assets/js/modules/statistics-dashboard.js` (around line 137), render the 5th card:
```javascript
            // KPI Kartları — Compact Grid (5 Detay Metrik Kartı)
            html += '<div class="stat-kpi-compact-grid">';
            html += self._kpiCardCompact('🛒', 'SEPET ORT.', self._currency(kpi.sepet_ortalamasi), 'Sepet başı ortalama', 'kpi-sepet-tutar stat-kpi-clickable', 'Dağılımı Gör ➔');
            html += self._kpiCardCompact('🛍️', 'SEPET ÜRÜN', (kpi.sepet_urun_ortalamasi || 0) + ' adet/sepet', 'Sepet başı ortalama', 'kpi-sepet-adet stat-kpi-clickable', 'Dağılımı Gör ➔');
            html += self._kpiCardCompact('💸', 'MASRAF', self._currency(kpi.toplam_masraf || 0), (kpi.masraf_sayisi || 0) + ' masraf kaydı', 'kpi-masraf stat-kpi-clickable', 'Masraflara Git ➔');
            html += self._kpiCardCompact('↩️', 'İADE', self._currency(kpi.toplam_iade), kpi.iade_sayisi + ' iade', 'kpi-iade stat-kpi-clickable', 'İadeleri Gör ➔');
            html += self._kpiCardCompact('✂️', 'İSKONTO', self._currency(kpi.toplam_iskonto || 0), (kpi.iskonto_siparis_sayisi || 0) + ' siparişte', 'kpi-iskonto stat-kpi-clickable', 'İskontolu Siparişler ➔');
            html += '</div>';
```

- [ ] **Step 2: Add click handler for `kpi-masraf` in `statistics-dashboard.js`**

In `assets/js/modules/statistics-dashboard.js` (around line 278):
```javascript
                    if (card.classList.contains('kpi-masraf')) {
                        var masrafTab = document.querySelector('.ust-sekme[data-tab="masraf"]');
                        if (masrafTab) {
                            masrafTab.click();
                        }
                    } else if (card.classList.contains('kpi-iade')) {
```

- [ ] **Step 3: Add CSS rules for `kpi-masraf` and grid layout in `statistics.css`**

In `assets/css/modules/statistics.css`:
Update `.stat-kpi-compact-grid` (around line 108):
```css
.stat-kpi-compact-grid {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 12px;
}

@media (max-width: 1200px) {
    .stat-kpi-compact-grid { grid-template-columns: repeat(3, 1fr); }
}

@media (max-width: 768px) {
    .stat-kpi-compact-grid { grid-template-columns: repeat(2, 1fr); }
}

@media (max-width: 500px) {
    .stat-kpi-compact-grid { grid-template-columns: 1fr; }
}
```
Add `.stat-kpi-card.kpi-masraf` color (around line 397):
```css
.stat-kpi-card.kpi-masraf      { --kpi-color: #f59e0b; }
```

- [ ] **Step 4: Manual Verification**

1. Load POS application.
2. Navigate to Raporlar tab -> Özet İstatistik.
3. Observe the new `MASRAF` KPI card displaying `₺ X,XX` and `Y masraf kaydı`.
4. Click on the card or `Masraflara Git ➔` button.
5. Confirm that the app switches seamlessly to the Masraf tab.
