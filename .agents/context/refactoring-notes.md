# Refactoring Structure Notes
**2026-06-22**

Admin settings procedural code was split into OOP modules:
  - `includes/classes/admin/` (menu, settings page, depo controller, mismatch bubble)
  - `includes/classes/ajax/` (stock, import-export, unmatched, tools)
`includes/classes/class-admin-settings.php` remains as a compatibility wrapper
for legacy function names used by V1 API files.

User warehouse permissions now live in
`includes/classes/class-user-warehouse-permissions.php`;
legacy helper functions forward to this class.

Global plugin hooks (cache invalidation, REST no-cache, coupon) now live in
`includes/classes/class-hooks.php`.

Stock manager was split into focused modules under `includes/classes/stock/`:
  - `class-stock-manager.php` (core operations: update, set, reserve, log, sync)
  - `class-stock-order-handler.php` (WC order hooks: POS, reservation, completion, cancellation, conflict)
  - `class-stock-allocation.php` (priority reservation/deduction, transfers)
  - `class-stock-import-export.php` (CSV/JSON import-export, unmatched items)
The old `includes/classes/class-stock-manager.php` path is a require shim.
`Hizli_Kasa_Stock_Manager` retains backward-compat wrapper methods that delegate
to the new classes so existing callers (V1 APIs, AJAX handlers) keep working.

Settings page inline HTML tabs were extracted to `includes/views/`:
  - `admin-settings-genel.php`, `admin-settings-onbellek.php`, `admin-settings-araclar.php`

Order admin HTML was extracted to `includes/views/`:
  - `admin-order-meta-box.php`, `admin-order-info-panel.php`
