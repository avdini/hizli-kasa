<?php
if (!defined('ABSPATH')) {
    exit;
}

class Hizli_Kasa_Auto_Sku_Manager {
    private static $syncing_ids = [];

    public static function init() {
        add_action('woocommerce_update_product', [self::class, 'handle_product_save'], 20, 1);
        add_action('woocommerce_update_product_variation', [self::class, 'handle_product_save'], 20, 1);
        add_action('woocommerce_new_product', [self::class, 'handle_product_save'], 20, 1);
        add_action('woocommerce_new_product_variation', [self::class, 'handle_product_save'], 20, 1);
        add_action('save_post_product', [self::class, 'handle_save_post_product'], 20, 3);
        add_action('save_post_product_variation', [self::class, 'handle_save_post_product'], 20, 3);

        add_action('woocommerce_product_import_inserted_product_object', [self::class, 'handle_import_save'], 20, 2);

        add_action('added_post_meta', [self::class, 'handle_meta_change'], 20, 4);
        add_action('updated_post_meta', [self::class, 'handle_meta_change'], 20, 4);

        add_filter('cron_schedules', [self::class, 'register_cron_schedule']);
        add_action('hizli_kasa_auto_sku_cron', [self::class, 'run_cron_fix']);

        add_action('update_option_hizli_kasa_auto_sku_cron_seconds', [self::class, 'reschedule_cron']);
        add_action('update_option_hizli_kasa_auto_sku_cron_aktif', [self::class, 'reschedule_cron']);
        add_action('update_option_hizli_kasa_auto_sku_aktif', [self::class, 'reschedule_cron']);

        self::manage_cron();
    }

    public static function register_cron_schedule($schedules) {
        $seconds = (int)get_option('hizli_kasa_auto_sku_cron_seconds', 3600);
        if ($seconds > 0) {
            $schedules['hizli_kasa_auto_sku_custom'] = [
                'interval' => $seconds,
                'display'  => sprintf(__('Her %d Saniyede Bir', 'hizli-kasa'), $seconds)
            ];
        }
        return $schedules;
    }

    public static function manage_cron() {
        $cron_aktif = get_option('hizli_kasa_auto_sku_cron_aktif', '0') === '1';
        $sku_aktif = get_option('hizli_kasa_auto_sku_aktif', '0') === '1';
        $hook_name = 'hizli_kasa_auto_sku_cron';

        if ($sku_aktif && $cron_aktif) {
            if (!wp_next_scheduled($hook_name)) {
                wp_schedule_event(time(), 'hizli_kasa_auto_sku_custom', $hook_name);
            }
        } else {
            $timestamp = wp_next_scheduled($hook_name);
            if ($timestamp) {
                wp_unschedule_event($timestamp, $hook_name);
            }
        }
    }

    public static function reschedule_cron() {
        $hook_name = 'hizli_kasa_auto_sku_cron';
        $timestamp = wp_next_scheduled($hook_name);
        if ($timestamp) {
            wp_unschedule_event($timestamp, $hook_name);
        }
        self::manage_cron();
    }

    public static function handle_product_save($product_id) {
        $triggers = get_option('hizli_kasa_auto_sku_tetikleyiciler', ['save', 'import']);
        if (!in_array('save', $triggers)) {
            return;
        }
        self::sync_sku_by_id($product_id);
    }

    public static function handle_save_post_product($post_id, $post, $update) {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if ($post->post_type !== 'product' && $post->post_type !== 'product_variation') return;
        
        $triggers = get_option('hizli_kasa_auto_sku_tetikleyiciler', ['save', 'import']);
        if (!in_array('save', $triggers)) {
            return;
        }

        self::sync_sku_by_id($post_id);
    }

    public static function handle_import_save($product, $data) {
        $triggers = get_option('hizli_kasa_auto_sku_tetikleyiciler', ['save', 'import']);
        if (!in_array('import', $triggers)) {
            return;
        }
        if ($product && $product->get_id()) {
            self::sync_sku_by_id($product->get_id());
        }
    }

    public static function handle_meta_change($meta_id, $object_id, $meta_key, $_meta_value) {
        if (in_array($object_id, self::$syncing_ids)) {
            return;
        }
        if ($meta_key !== '_sku') {
            return;
        }

        if (empty($_meta_value)) {
            $triggers = get_option('hizli_kasa_auto_sku_tetikleyiciler', ['save', 'import']);
            if (in_array('save', $triggers)) {
                self::sync_sku_by_id($object_id);
            }
        }
    }

    public static function sync_sku_by_id($product_id) {
        error_log("HK SKU Debug: sync_sku_by_id triggered for ID " . $product_id);

        if (in_array($product_id, self::$syncing_ids)) {
            error_log("HK SKU Debug: ID " . $product_id . " is already syncing, skipping.");
            return false;
        }

        $aktif = get_option('hizli_kasa_auto_sku_aktif', '0');
        error_log("HK SKU Debug: Auto SKU active state: " . $aktif);
        if ($aktif !== '1') {
            return false;
        }

        $product = wc_get_product($product_id);
        if (!$product) {
            error_log("HK SKU Debug: Failed to load product for ID " . $product_id);
            return false;
        }

        $type = $product->get_type();
        $allowed_types = get_option('hizli_kasa_auto_sku_tipler', ['simple', 'product_variation']);
        error_log("HK SKU Debug: ID " . $product_id . " type is '" . $type . "'. Allowed: " . print_r($allowed_types, true));
        
        $mapped_type = ($type === 'variation') ? 'product_variation' : $type;
        if (!in_array($mapped_type, $allowed_types)) {
            error_log("HK SKU Debug: Type '" . $mapped_type . "' not in allowed types.");
            return false;
        }

        $sku = $product->get_sku('edit');
        error_log("HK SKU Debug: ID " . $product_id . " current SKU: '" . $sku . "'");
        if ($sku !== '') {
            return false;
        }

        self::$syncing_ids[] = $product_id;

        $prefix = get_option('hizli_kasa_auto_sku_prefix', 'AVD-');
        $new_sku = $prefix . $product_id;

        error_log("HK SKU Debug: Generating SKU '" . $new_sku . "' for ID " . $product_id);
        $product->set_sku($new_sku);
        $product->save();

        self::$syncing_ids = array_diff(self::$syncing_ids, [$product_id]);
        error_log("HK SKU Debug: Finished successfully for ID " . $product_id);
        return true;
    }

    public static function run_cron_fix() {
        global $wpdb;

        $allowed_types = get_option('hizli_kasa_auto_sku_tipler', ['simple', 'product_variation']);
        if (empty($allowed_types)) {
            return;
        }

        $db_types = [];
        if (in_array('simple', $allowed_types) || in_array('variable', $allowed_types)) {
            $db_types[] = 'product';
        }
        if (in_array('product_variation', $allowed_types)) {
            $db_types[] = 'product_variation';
        }

        if (empty($db_types)) {
            return;
        }

        $db_types_in = implode("','", array_map('esc_sql', $db_types));

        $missing_sku_ids = $wpdb->get_col("
            SELECT DISTINCT p.ID
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_sku'
            WHERE p.post_type IN ('$db_types_in')
              AND p.post_status IN ('publish', 'private', 'draft')
              AND (pm.meta_value IS NULL OR pm.meta_value = '')
            LIMIT 100
        ");

        if (!empty($missing_sku_ids)) {
            foreach ($missing_sku_ids as $id) {
                $p = wc_get_product((int)$id);
                if ($p) {
                    $type = $p->get_type();
                    $mapped_type = ($type === 'variation') ? 'product_variation' : $type;
                    if (in_array($mapped_type, $allowed_types)) {
                        self::sync_sku_by_id((int)$id);
                    }
                }
            }
        }
    }
}
