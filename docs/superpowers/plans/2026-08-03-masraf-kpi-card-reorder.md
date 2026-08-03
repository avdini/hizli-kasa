# KPI Kart Sıralaması Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Reorder the compact KPI cards in the statistics dashboard so `SEPET ORT.` and `SEPET ÜRÜN` are positioned on the right side under the `SATIŞ HACMİ` hero card.

**Architecture:** Update `statistics-dashboard.js` to render compact KPI cards in the new order: `MASRAF`, `İADE`, `İSKONTO`, `SEPET ORT.`, `SEPET ÜRÜN`.

**Tech Stack:** Vanilla JS (DOM manipulation).

## Global Constraints
- Follow AGENTS.md clean code rules.
- Maintain existing click listeners and navigation behavior.

---

### Task 1: Reorder Compact KPI Cards

**Files:**
- Modify: `assets/js/modules/statistics-dashboard.js:136-143`

- [ ] **Step 1: Update HTML render order in `_renderDashboard` in `statistics-dashboard.js`**

```javascript
            // KPI Kartları — Compact Grid (5 Detay Metrik Kartı)
            html += '<div class="stat-kpi-compact-grid">';
            html += self._kpiCardCompact('💸', 'MASRAF', self._currency(kpi.toplam_masraf || 0), (kpi.masraf_sayisi || 0) + ' masraf kaydı', 'kpi-masraf stat-kpi-clickable', 'Masraflara Git ➔');
            html += self._kpiCardCompact('↩️', 'İADE', self._currency(kpi.toplam_iade), kpi.iade_sayisi + ' iade', 'kpi-iade stat-kpi-clickable', 'İadeleri Gör ➔');
            html += self._kpiCardCompact('✂️', 'İSKONTO', self._currency(kpi.toplam_iskonto || 0), (kpi.iskonto_siparis_sayisi || 0) + ' siparişte', 'kpi-iskonto stat-kpi-clickable', 'İskontolu Siparişler ➔');
            html += self._kpiCardCompact('🛒', 'SEPET ORT.', self._currency(kpi.sepet_ortalamasi), 'Sepet başı ortalama', 'kpi-sepet-tutar stat-kpi-clickable', 'Dağılımı Gör ➔');
            html += self._kpiCardCompact('🛍️', 'SEPET ÜRÜN', (kpi.sepet_urun_ortalamasi || 0) + ' adet/sepet', 'Sepet başı ortalama', 'kpi-sepet-adet stat-kpi-clickable', 'Dağılımı Gör ➔');
            html += '</div>';
```

- [ ] **Step 2: Manual Verification**

1. Load POS application.
2. Open Raporlar -> Özet İstatistik.
3. Confirm that `SEPET ORT.` and `SEPET ÜRÜN` appear on the right side under `SATIŞ HACMİ`.
