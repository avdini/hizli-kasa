# Design Specification: Advanced Product Filter Modal & Active Chips Bar

**Date:** 2026-07-30  
**Status:** Approved  
**Topic:** Relocating Advanced Search Logical Filter Cards to a Dedicated Modal and Adding Active Filter Chips Bar

---

## 1. Executive Summary

The previous inline placement of the Advanced Product Filter Cards inside the left-side filter panel created visual clutter and squeezed complex UI elements into a narrow column (~280px). This design specification moves the filter card creation UI into a dedicated, spacious modal (`#gelismis-filtre-modal`) and introduces an active filter chips bar (`#active-filter-chips-bar`) at the top of the product area.

---

## 2. Architecture & UI Components

### 2.1 Modal Component (`includes/views/modals.php`)
- **ID:** `#gelismis-filtre-modal`
- **Class:** `modal-cerceve` (standard Hızlı Kasa modal container)
- **Modal Content Size:** `modal-icerik modal-icerik-lg` (720px max-width)
- **Header:** Title "🧩 Gelişmiş Ürün Filtreleme", subtitle, and close button (`.modal-close-btn`).
- **Body:**
  - `#filter-cards-container`: Dynamic container where AND/OR logical filter cards are rendered.
  - `#btn-add-filter-card`: Button to add new VEYA (OR) filter cards.
  - `#filter-human-summary`: Live Turkish natural language summary box.
- **Footer Controls:**
  - `btn-clear-advanced-filters`: Resets all filter cards.
  - `btn-cancel-advanced-filters`: Closes modal without saving state changes.
  - `btn-apply-advanced-filters`: Saves filter state, updates active chips & badge, closes modal, and triggers search.

### 2.2 Trigger & Active Chips Bar (`includes/views/tab-urunler.php`)
- **Filter Panel Trigger:**
  - `#btn-open-advanced-filter`: Modern compact button replacing inline filter cards section.
  - `#advanced-filter-badge`: Dynamic badge showing active card count (e.g. `2`).
- **Active Filter Chips Bar:**
  - `#active-filter-chips-bar`: Positioned at the top of the product grid/list.
  - Displays individual filter pills (e.g., `[ 🏷️ Beden: S ✖ ]`, `[ 📦 Stok = 1 ✖ ]`).
  - Includes a quick `✖ Tümünü Temizle` pill when active filters exist.

### 2.3 Styling (`assets/css/modules/stock-terminal.css`)
- Clean glassmorphism & dark-mode styling consistent with Hızlı Kasa UI design system.
- Responsive styles for `#gelismis-filtre-modal` and `.filter-chip` pills.

### 2.4 State & Event Flow (`assets/js/modules/stock-terminal.js`)
- `HK_StockTerminal.state.filters.filterGroups`: Holds the filter card model.
- `openAdvancedFilterModal()`: Clones current filter state and opens modal.
- `applyAdvancedFilters()`: Saves modified filter state, renders active chips bar, updates badge, closes modal, and executes AJAX search.
- `removeFilterChip(groupIndex, attrKey/stock)`: Removes a specific filter rule directly from the chips bar without opening the modal.

---

## 3. Verification Plan

1. **Modal Mechanics:** Verify modal opens on `#btn-open-advanced-filter` click, closes on backdrop/cancel/apply clicks.
2. **Card Interactions:** Test adding cards, selecting attributes, setting stock operators, and live human language summary updates.
3. **Filter Application:** Verify clicking "Filtreleri Uygula" closes modal, updates badge, renders chips, and filters products correctly via V2 API.
4. **Chip Removal:** Test clicking individual `✖` on filter chips removes the specific rule and triggers product search update.
