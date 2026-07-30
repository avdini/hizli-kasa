# Advanced Product Filter Modal & Active Chips Bar Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Relocate the advanced product filter cards UI from the left sidebar into a spacious, dedicated modal (`#gelismis-filtre-modal`) and add an active filter chips bar (`#active-filter-chips-bar`) to display applied filters at a glance.

**Architecture:** Modals markup defined in `includes/views/modals.php`, compact trigger button and active chips container defined in `includes/views/tab-urunler.php`, modal styling in `assets/css/modules/stock-terminal.css`, and state & UI rendering logic managed in `assets/js/modules/stock-terminal.js`.

**Tech Stack:** Vanilla JavaScript (ES6+), PHP, HTML5, CSS3 (Glassmorphism & dark-mode theme).

## Global Constraints

- **No Native Dialogs:** Never use `window.alert`, `window.confirm`, or `window.prompt`.
- **Existing API Contracts:** Maintain compatibility with `filter_groups` and `stock_calc_mode` parameters sent to `class-api-stock-search.php`.
- **Minimal Inline Comments:** Keep code comments minimal and document architectural decisions in `.agents/`.

---

### Task 1: Add Modal Markup to `includes/views/modals.php` and Trigger & Chips Bar to `includes/views/tab-urunler.php`

**Files:**
- Modify: `includes/views/modals.php`
- Modify: `includes/views/tab-urunler.php`

**Interfaces:**
- Consumes: Modal container class `.modal-cerceve`, `.modal-icerik`, `.modal-icerik-lg`.
- Produces: DOM elements `#gelismis-filtre-modal`, `#filter-cards-container`, `#filter-human-summary`, `#btn-open-advanced-filter`, `#advanced-filter-badge`, `#active-filter-chips-bar`.

- [ ] **Step 1: Add `#gelismis-filtre-modal` markup in `includes/views/modals.php`**

```html
<!-- Gelişmiş Ürün Filtreleme Modalı -->
<div id="gelismis-filtre-modal" class="modal-cerceve" style="display:none;">
    <div class="modal-icerik modal-icerik-lg">
        <div class="modal-header">
            <h3>🧩 Gelişmiş Ürün Filtreleme</h3>
            <p class="modal-subtitle">Birden fazla ürün varyasyonunu ve mantıksal kuralları (VE / VEYA) kolayca tanımlayın.</p>
        </div>

        <div class="modal-body-scrollable">
            <!-- Dinamik Kartlar Konteynırı -->
            <div id="filter-cards-container" class="filter-cards-container"></div>

            <div class="filter-cards-actions">
                <button type="button" id="btn-add-filter-card" class="btn-add-filter-card">
                    <span>➕ Yeni Arama Kartı Ekle (VEYA)</span>
                </button>
            </div>

            <!-- Canlı İnsan Dili Özeti -->
            <div id="filter-human-summary" class="human-summary-box" style="display:none;">
                <div class="summary-icon">💬</div>
                <div class="summary-text" id="human-summary-text">Henüz özel arama kartı eklenmedi.</div>
            </div>
        </div>

        <div class="modal-butonlar" style="margin-top:20px;">
            <button type="button" id="btn-clear-advanced-filters" class="modal-btn-cancel">🗑️ Tümünü Temizle</button>
            <button type="button" id="btn-cancel-advanced-filters" class="modal-btn-cancel">Vazgeç</button>
            <button type="button" id="btn-apply-advanced-filters" class="hk-btn-primary" style="padding:12px 20px;">✅ Filtreleri Uygula</button>
        </div>
    </div>
</div>
```

- [ ] **Step 2: Replace inline cards section with `#btn-open-advanced-filter` and add `#active-filter-chips-bar` in `includes/views/tab-urunler.php`**

In `includes/views/tab-urunler.php`, replace `.filter-cards-section` with:

```html
<!-- Gelişmiş Arama Kartı Açma Butonu -->
<div class="filter-section">
    <button type="button" id="btn-open-advanced-filter" class="btn-open-advanced-filter">
        <span>⚙️ Gelişmiş Filtrele</span>
        <span id="advanced-filter-badge" class="filter-badge" style="display:none;">0</span>
    </button>
</div>
```

And add `#active-filter-chips-bar` right above the product search input or product grid.

- [ ] **Step 3: Commit structural HTML changes**

```bash
git add includes/views/modals.php includes/views/tab-urunler.php
git commit -m "feat(ui): add gelismis-filtre-modal markup and trigger button with active chips bar"
```

---

### Task 2: Style Modal and Active Chips Bar in `assets/css/modules/stock-terminal.css`

**Files:**
- Modify: `assets/css/modules/stock-terminal.css`

- [ ] **Step 1: Add modal width, trigger button, badge, and active chips CSS styles**

```css
/* Gelişmiş Filtre Butonu & Badge */
.btn-open-advanced-filter {
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 10px 14px;
    background: var(--hk-bg-card, #1e293b);
    border: 1px solid var(--hk-border, #334155);
    border-radius: 8px;
    color: var(--hk-text-primary, #f8fafc);
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
}
.btn-open-advanced-filter:hover {
    border-color: #3b82f6;
    background: rgba(59, 130, 246, 0.1);
}
.btn-open-advanced-filter .filter-badge {
    background: #3b82f6;
    color: #fff;
    font-size: 11px;
    font-weight: 700;
    padding: 2px 8px;
    border-radius: 12px;
}

/* Aktif Filtre Çipleri (Chips Bar) */
.active-filter-chips-bar {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px;
    margin-bottom: 12px;
    padding: 8px 12px;
    background: var(--hk-bg-card, #1e293b);
    border: 1px solid var(--hk-border, #334155);
    border-radius: 8px;
}
.filter-chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 10px;
    background: rgba(59, 130, 246, 0.15);
    border: 1px solid rgba(59, 130, 246, 0.3);
    border-radius: 16px;
    color: #60a5fa;
    font-size: 12px;
    font-weight: 500;
}
.filter-chip-remove {
    cursor: pointer;
    font-weight: 700;
    margin-left: 2px;
    opacity: 0.8;
}
.filter-chip-remove:hover {
    opacity: 1;
    color: #ef4444;
}
```

- [ ] **Step 2: Commit CSS styles**

```bash
git add assets/css/modules/stock-terminal.css
git commit -m "style(stock-terminal): add styling for advanced filter modal and active chips bar"
```

---

### Task 3: Implement Modal State, Event Handlers, and Active Chips Rendering in `assets/js/modules/stock-terminal.js`

**Files:**
- Modify: `assets/js/modules/stock-terminal.js`

- [ ] **Step 1: Connect `#btn-open-advanced-filter` to open `#gelismis-filtre-modal`**
- [ ] **Step 2: Implement `renderActiveChipsBar()` to render applied filter pills**
- [ ] **Step 3: Wire `btn-apply-advanced-filters`, `btn-clear-advanced-filters`, `btn-cancel-advanced-filters`**
- [ ] **Step 4: Update AJAX payload to send `filter_groups` on filter application**
- [ ] **Step 5: Verify modal open/close, card add/remove, and chip single-item removal**
- [ ] **Step 6: Commit JS logic**

```bash
git add assets/js/modules/stock-terminal.js
git commit -m "feat(stock-terminal): implement modal open/close, active chips rendering, and state management"
```

---

### Task 4: Version Bump, Final Verification, and Dual Repository Push

**Files:**
- Modify: `hizli-kasa.php`

- [ ] **Step 1: Bump version in `hizli-kasa.php` to `12.30.24`**
- [ ] **Step 2: Stage and commit all changes**
- [ ] **Step 3: Push to `origin main`**
- [ ] **Step 4: Execute `scripts/patch-public.ps1` to sync `public master`**
