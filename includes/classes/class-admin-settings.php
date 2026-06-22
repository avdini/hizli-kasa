<?php
if (!defined('ABSPATH')) exit;

require_once HIZLI_KASA_PATH . 'includes/classes/class-user-warehouse-permissions.php';
require_once HIZLI_KASA_PATH . 'includes/classes/admin/class-admin-menu.php';
require_once HIZLI_KASA_PATH . 'includes/classes/admin/class-admin-settings-register.php';
require_once HIZLI_KASA_PATH . 'includes/classes/admin/class-admin-settings-page.php';
require_once HIZLI_KASA_PATH . 'includes/classes/admin/class-admin-depo-controller.php';
require_once HIZLI_KASA_PATH . 'includes/classes/admin/class-admin-mismatch-bubble.php';
require_once HIZLI_KASA_PATH . 'includes/classes/ajax/class-ajax-stock.php';
require_once HIZLI_KASA_PATH . 'includes/classes/ajax/class-ajax-import-export.php';
require_once HIZLI_KASA_PATH . 'includes/classes/ajax/class-ajax-unmatched.php';
require_once HIZLI_KASA_PATH . 'includes/classes/ajax/class-ajax-tools.php';

function hizli_kasa_render_mismatch_bubble() { return Hizli_Kasa_Admin_Mismatch_Bubble::render(); }
function hizli_kasa_admin_menu() { return Hizli_Kasa_Admin_Menu::register(); }
function hizli_kasa_ayarlari_kaydet() { return Hizli_Kasa_Admin_Settings_Register::register(); }
function hizli_kasa_handle_depo_actions() { return Hizli_Kasa_Admin_Depo_Controller::handle_actions(); }
function hizli_kasa_ayarlar_sayfasi() { return Hizli_Kasa_Admin_Settings_Page::render(); }

function hizli_kasa_get_user_view_depos($user_id) { return Hizli_Kasa_User_Warehouse_Permissions::get_view_depos($user_id); }
function hizli_kasa_get_user_manage_depos($user_id) { return Hizli_Kasa_User_Warehouse_Permissions::get_manage_depos($user_id); }
function hizli_kasa_can_user_view_depo($user_id, $depo_id) { return Hizli_Kasa_User_Warehouse_Permissions::can_view($user_id, $depo_id); }
function hizli_kasa_can_user_manage_depo($user_id, $depo_id) { return Hizli_Kasa_User_Warehouse_Permissions::can_manage($user_id, $depo_id); }
function hizli_kasa_get_user_active_depo($user_id) { return Hizli_Kasa_User_Warehouse_Permissions::get_active_depo($user_id); }
function hizli_kasa_migrate_legacy_depo($user_id) { return Hizli_Kasa_User_Warehouse_Permissions::migrate_legacy($user_id); }
function hizli_kasa_user_warehouse_field($user) { return Hizli_Kasa_User_Warehouse_Permissions::render_field($user); }
function hizli_kasa_save_user_warehouse_field($user_id) { return Hizli_Kasa_User_Warehouse_Permissions::save_field($user_id); }

function hizli_kasa_ajax_get_admin_stock_list() { return Hizli_Kasa_Ajax_Stock::get_list(); }
function hizli_kasa_ajax_admin_update_stock() { return Hizli_Kasa_Ajax_Stock::update(); }
function hizli_kasa_ajax_batch_update_stock() { return Hizli_Kasa_Ajax_Stock::batch_update(); }
function hizli_kasa_ajax_export_stocks() { return Hizli_Kasa_Ajax_Import_Export::export(); }
function hizli_kasa_ajax_import_stocks() { return Hizli_Kasa_Ajax_Import_Export::import(); }
function hizli_kasa_ajax_get_unmatched() { return Hizli_Kasa_Ajax_Unmatched::get_list(); }
function hizli_kasa_ajax_delete_unmatched() { return Hizli_Kasa_Ajax_Unmatched::delete(); }
function hizli_kasa_ajax_clear_all_unmatched() { return Hizli_Kasa_Ajax_Unmatched::clear_all(); }
function hizli_kasa_ajax_setup() { return Hizli_Kasa_Ajax_Tools::setup(); }
function hizli_kasa_ajax_reset() { return Hizli_Kasa_Ajax_Tools::reset(); }
function hizli_kasa_ajax_repair_db() { return Hizli_Kasa_Ajax_Tools::repair_db(); }
function hizli_kasa_ajax_debug_db() { return Hizli_Kasa_Ajax_Tools::debug_db(); }
function hizli_kasa_ajax_manual_mismatch_check() { return Hizli_Kasa_Ajax_Tools::mismatch_check(); }
function hizli_kasa_ajax_clear_cache() { return Hizli_Kasa_Ajax_Tools::clear_cache(); }
function hizli_kasa_ajax_sync_wh_to_wc_start() { return Hizli_Kasa_Ajax_Tools::sync_start(); }
function hizli_kasa_ajax_sync_wh_to_wc_step() { return Hizli_Kasa_Ajax_Tools::sync_step(); }
