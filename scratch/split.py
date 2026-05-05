import os
import re

source_file = 'includes/classes/class-rest-api.php'
dest_dir = 'includes/api'

with open(source_file, 'r', encoding='utf-8') as f:
    lines = f.readlines()

def get_function_lines(func_name, start_index=0):
    start = -1
    for i in range(start_index, len(lines)):
        if re.match(r'^(?:abstract )?(?:public |private |protected )?function\s+' + func_name + r'\s*\(', lines[i]):
            start = i
            break
    if start == -1:
        # Also check for variables holding closures like $sevk_permission = function
        for i in range(start_index, len(lines)):
            if re.search(r'\b' + func_name + r'\s*=\s*function\s*\(', lines[i]):
                start = i
                break
    
    if start == -1:
        return []
    
    # Simple brace counting
    braces = 0
    in_func = False
    end = start
    for i in range(start, len(lines)):
        line = lines[i]
        braces += line.count('{')
        braces -= line.count('}')
        if '{' in line:
            in_func = True
        if in_func and braces == 0:
            end = i
            break
            
    # Include docblock if present
    doc_start = start
    for i in range(start - 1, max(-1, start - 15), -1):
        if lines[i].strip() == '*/':
            for j in range(i, max(-1, i - 15), -1):
                if lines[j].strip() == '/**':
                    doc_start = j
                    break
            break
            
    return lines[doc_start:end + 1]

def create_file(filename, functions, route_regexes=None):
    content = "<?php\nif (!defined('ABSPATH')) exit;\n\n"
    
    if route_regexes:
        content += "add_action('rest_api_init', function () {\n"
        for i, line in enumerate(lines):
            # Check if line contains register_rest_route
            if 'register_rest_route' in line:
                for regex in route_regexes:
                    if re.search(regex, line):
                        # extract the statement
                        # assuming it ends with );
                        stmt = ""
                        braces = 0
                        started = False
                        for j in range(i, len(lines)):
                            l = lines[j]
                            braces += l.count('(')
                            braces -= l.count(')')
                            if '(' in l: started = True
                            stmt += l
                            if started and braces == 0 and ';' in l:
                                break
                        # Add a simple sevk_permission if needed
                        if 'sevk' in filename and '$sevk_permission' not in content:
                            content += "    $sevk_permission = function () { return hizli_kasa_can_access_app(); };\n"
                        content += stmt + "\n"
                        break
        content += "});\n\n"
        
    for f in functions:
        content += "".join(get_function_lines(f)) + "\n"
        
    with open(os.path.join(dest_dir, filename), 'w', encoding='utf-8') as out:
        out.write(content)

# Define modules
modules = {
    'api-helpers.php': {
        'functions': [
            'hizli_kasa_is_manual_discount_fee',
            'hizli_kasa_get_order_manual_discount',
            'hizli_kasa_get_order_total_discount',
            'hizli_kasa_hydrate_products_batch',
            'hizli_kasa_format_urun_row',
            'hizli_kasa_prepare_search_terms',
            'hizli_kasa_get_aws_ranked_product_ids',
            'hizli_kasa_get_local_ranked_product_ids',
            'hizli_kasa_get_terminal_search_product_ids'
        ],
        'routes': []
    },
    'api-user.php': {
        'functions': [
            'hizli_kasa_api_user_depolar',
            'hizli_kasa_api_set_active_depo',
            'hizli_kasa_api_set_user_theme',
            'hizli_kasa_load_tab_content'
        ],
        'routes': [r"'/user/depolar'", r"'/user/set-active-depo'", r"'/user/set-theme'", r"'/load-tab'"]
    },
    'api-kasa.php': {
        'functions': [
            'hizli_kasa_ozel_arama',
            'hizli_kasa_warehouse_stock_check',
            'hizli_kasa_api_get_barcode_data'
        ],
        'routes': [r"'/search'", r"'/warehouse-stock-check'", r"'/barcode/label-data'"]
    },
    'api-siparis.php': {
        'functions': [
            'hizli_kasa_get_order_details',
            'hizli_kasa_search_orders',
            'hizli_kasa_get_recent_orders',
            'hizli_kasa_update_order',
            'hizli_kasa_get_edit_logs'
        ],
        'routes': [r"'/get-order'", r"'/search-orders'", r"'/recent-orders'", r"'/update-order'", r"'/edit-logs'"]
    },
    'api-iade.php': {
        'functions': [
            'hizli_kasa_process_refund',
            'hizli_kasa_send_custom_refund_email'
        ],
        'routes': [r"'/process-refund'"]
    },
    'api-sevk.php': {
        'functions': [
            'hizli_kasa_sevk_tables',
            'hizli_kasa_sevk_status_label',
            'hizli_kasa_sevk_generate_no',
            'hizli_kasa_sevk_refresh_totals',
            'hizli_kasa_sevk_get',
            'hizli_kasa_sevk_user_can_source',
            'hizli_kasa_sevk_user_can_target',
            'hizli_kasa_sevk_format',
            'hizli_kasa_sevk_find_product_by_sku',
            'hizli_kasa_sevk_olustur',
            'hizli_kasa_sevk_kalem_ekle',
            'hizli_kasa_sevk_kalem_sil',
            'hizli_kasa_sevk_gonder_onayla',
            'hizli_kasa_sevk_alici_onayla',
            'hizli_kasa_sevk_alici_reddet',
            'hizli_kasa_sevk_yola_cikart',
            'hizli_kasa_sevk_has_mismatch',
            'hizli_kasa_sevk_teslim_barkod',
            'hizli_kasa_sevk_teslim_onayla',
            'hizli_kasa_sevk_liste',
            'hizli_kasa_sevk_detay',
            'hizli_kasa_sevk_bekleyen_sayisi'
        ],
        'routes': [r"'/sevk/"]
    },
    'api-masraf.php': {
        'functions': [
            'hizli_kasa_get_masraflar',
            'hizli_kasa_add_masraf',
            'hizli_kasa_delete_masraf'
        ],
        'routes': [r"'/masraflar'"]
    },
    'api-raporlar.php': {
        'functions': [
            'hizli_kasa_gun_sonu_raporu',
            'hizli_kasa_get_reports_orders',
            'hizli_kasa_get_reports_refunds',
            'hizli_kasa_get_reports_order_receipt',
            'hizli_kasa_get_reports_data',
            'hizli_kasa_get_day_end_history'
        ],
        'routes': [r"'/gun-sonu-raporu'", r"'/reports/orders'", r"'/reports/refunds'", r"'/reports/order-receipt/.*'", r"'/reports/day-end-history'"]
    },
    'api-terminal.php': {
        'functions': [
            'hizli_kasa_terminal_products',
            'hizli_kasa_terminal_update_stock'
        ],
        'routes': [r"'/terminal/products'", r"'/terminal/update-stock'"]
    },
    'api-istatistik.php': {
        'functions': [
            'hizli_kasa_statistics_summary'
        ],
        'routes': [r"'/statistics/summary'"]
    }
}

for filename, data in modules.items():
    create_file(filename, data['functions'], data['routes'])

# Now rewrite class-rest-api.php
new_class_content = """<?php
/**
 * Hızlı Kasa - REST API Loader
 *
 * Modüler API dosyalarını yükler.
 *
 * @package HizliKasa
 */

if (!defined('ABSPATH'))
    exit;

$api_dir = HIZLI_KASA_PATH . 'includes/api/';

require_once $api_dir . 'api-helpers.php';
require_once $api_dir . 'api-user.php';
require_once $api_dir . 'api-kasa.php';
require_once $api_dir . 'api-siparis.php';
require_once $api_dir . 'api-iade.php';
require_once $api_dir . 'api-sevk.php';
require_once $api_dir . 'api-masraf.php';
require_once $api_dir . 'api-raporlar.php';
require_once $api_dir . 'api-terminal.php';
require_once $api_dir . 'api-istatistik.php';
"""

with open(source_file, 'w', encoding='utf-8') as out:
    out.write(new_class_content)

print("Files split successfully!")
