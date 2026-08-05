# Refactoring Structure Notes

## 2026-06-22

- Admin settings procedural hooks were moved behind OOP modules in `includes/classes/admin/` and `includes/classes/ajax/`.
- `includes/classes/class-admin-settings.php` remains as a compatibility wrapper for legacy function names used by older API files.
- User warehouse permissions now live in `includes/classes/class-user-warehouse-permissions.php`; legacy helper functions forward to this class.
- Global plugin hooks now live in `includes/classes/class-hooks.php`; legacy hook helper function names are kept for existing callers.
- Stock manager source moved to `includes/classes/stock/class-stock-manager.php`; the old `includes/classes/class-stock-manager.php` path is a require shim.
- Order admin HTML was extracted to `includes/views/admin-order-meta-box.php` and `includes/views/admin-order-info-panel.php`.
