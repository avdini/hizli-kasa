<?php
if (!defined('ABSPATH')) exit;

class Hizli_Kasa_Hooks {
    public static function init() {
        add_action('woocommerce_new_order', [__CLASS__, 'invalidate_reports_cache']);
        add_action('woocommerce_update_order', [__CLASS__, 'invalidate_reports_cache']);
        add_action('woocommerce_order_refunded', [__CLASS__, 'invalidate_reports_cache']);
        add_action('profile_update', [__CLASS__, 'invalidate_user_perms_cache']);
        add_action('user_register', [__CLASS__, 'invalidate_user_perms_cache']);
        add_filter('rest_pre_serve_request', [__CLASS__, 'enforce_no_cache'], 10, 4);
        add_action('woocommerce_new_order', [__CLASS__, 'handle_coupon_use'], 10, 2);
        add_action('woocommerce_coupon_options', [__CLASS__, 'render_coupon_print_button']);
        add_action('wp_ajax_hk_print_coupon', [__CLASS__, 'ajax_print_coupon']);
    }

    public static function invalidate_reports_cache() {
        update_option('hk_reports_cache_version', time());
    }

    public static function invalidate_user_perms_cache($user_id) {
        delete_transient("hk_user_view_depos_{$user_id}");
        delete_transient("hk_user_manage_depos_{$user_id}");
    }

    public static function enforce_no_cache($served, $result, $request, $server) {
        $route = $request->get_route();

        if (strpos($route, '/hizli-kasa/v1/') === 0 || strpos($route, '/hizli-kasa/v2/') === 0) {
            if (!defined('DONOTCACHEPAGE')) {
                define('DONOTCACHEPAGE', true);
            }

            do_action('litespeed_control_force_nocache');

            if (!headers_sent()) {
                header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
                header('Pragma: no-cache');
                header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
                header('X-LiteSpeed-Cache-Control: no-cache');
            }
        }

        return $served;
    }

    public static function handle_coupon_use($order_id, $order = false) {
        if (!$order) {
            $order = wc_get_order($order_id);
        }
        if (!$order) {
            return;
        }

        $kasiyer = $order->get_meta('_hizli_kasa_kasiyer');
        if (!$kasiyer) {
            return;
        }

        $coupon_code = $order->get_meta('_hizli_kasa_used_coupon_code');
        if ($coupon_code) {
            $coupon = new WC_Coupon($coupon_code);
            if ($coupon->get_id()) {
                $coupon->increase_usage_count();
            }

            $lock_key = 'hk_coupon_lock_' . md5(strtoupper($coupon_code));
            delete_transient($lock_key);
        }
    }

    public static function render_coupon_print_button() {
        global $post;
        if (!$post) return;
        
        $coupon = new WC_Coupon($post->ID);
        if (!$coupon->get_id()) return;
        
        $print_url = admin_url('admin-ajax.php?action=hk_print_coupon&coupon_id=' . $post->ID);
        ?>
        <div class="options_group">
            <p class="form-field">
                <label><?php esc_html_e('Hızlı Kasa', 'hizli-kasa'); ?></label>
                <a href="<?php echo esc_url($print_url); ?>" target="_blank" class="button button-secondary">
                    🖨️ Fişi Yazdır (Barkodlu)
                </a>
                <span class="description"><?php esc_html_e('Bu kupon için Hızlı Kasa termal barkod fişini yazdırır.', 'hizli-kasa'); ?></span>
            </p>
        </div>
        <?php
    }

    public static function ajax_print_coupon() {
        if (!current_user_can('manage_woocommerce') && (!function_exists('hizli_kasa_can_access_app') || !hizli_kasa_can_access_app())) {
            wp_die('Yetkisiz erişim.');
        }

        $coupon_id = isset($_GET['coupon_id']) ? intval($_GET['coupon_id']) : 0;
        if (!$coupon_id) {
            wp_die('Geçersiz Kupon ID.');
        }

        $coupon = new WC_Coupon($coupon_id);
        if (!$coupon->get_id()) {
            wp_die('Kupon bulunamadı.');
        }

        $coupon_code = $coupon->get_code();
        $amount = number_format($coupon->get_amount(), 2, '.', '') . ' TL';
        $saved_phone = $coupon->get_meta('_hizli_kasa_coupon_phone', true) ?: '-';
        $post_date = get_the_date('d.m.Y H:i', $coupon_id);

        ?>
        <!DOCTYPE html>
        <html lang="tr">
        <head>
            <meta charset="UTF-8">
            <title>Kupon Yazdır: <?php echo esc_html($coupon_code); ?></title>
            <style>
                body {
                    margin: 0;
                    padding: 10px;
                    background: #fff;
                    font-family: 'Courier New', Courier, monospace;
                    color: #000;
                }
                #fis-coupon-sablon {
                    width: 100%;
                    max-width: 80mm;
                    margin: 0 auto;
                    box-sizing: border-box;
                    text-align: center;
                }
                @media print {
                    @page {
                        size: auto;
                        margin: 0;
                    }
                    body {
                        padding: 0 4mm;
                    }
                }
            </style>
            <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.0/dist/JsBarcode.all.min.js"></script>
        </head>
        <body>
            <div id="fis-coupon-sablon">
                <?php include HIZLI_KASA_PATH . 'includes/views/receipt-coupon-template.php'; ?>
            </div>

            <script>
                document.addEventListener("DOMContentLoaded", function() {
                    if (window.JsBarcode) {
                        JsBarcode("#fis-coupon-barkod", "<?php echo esc_js($coupon_code); ?>", {
                            format: "CODE128",
                            width: 2,
                            height: 50,
                            displayValue: false
                        });
                    }
                    setTimeout(function() {
                        window.print();
                    }, 300);
                });
            </script>
        </body>
        </html>
        <?php
        exit;
    }
}

function hizli_kasa_invalidate_reports_cache() {
    return Hizli_Kasa_Hooks::invalidate_reports_cache();
}

function hizli_kasa_invalidate_user_perms_cache($user_id) {
    return Hizli_Kasa_Hooks::invalidate_user_perms_cache($user_id);
}

function hizli_kasa_handle_coupon_use_on_new_order($order_id, $order = false) {
    return Hizli_Kasa_Hooks::handle_coupon_use($order_id, $order);
}
