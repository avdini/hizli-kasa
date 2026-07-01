<?php
/**
 * Hızlı Kasa V2 API Product Statistics Controller
 *
 * GET /hizli-kasa/v2/statistics/product
 * GET /hizli-kasa/v2/statistics/product/preview
 *
 * @package HizliKasa
 */

if (!defined('ABSPATH')) {
    exit;
}

class Hizli_Kasa_API_Product_Statistics extends Hizli_Kasa_API_Controller_Base {

    public function register_routes() {
        register_rest_route($this->namespace, '/statistics/product', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_product_stats_callback'],
            'permission_callback' => [$this, 'check_permission'],
            'args'                => [
                'sku' => [
                    'required'          => true,
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'date_start' => [
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'date_end' => [
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'depo_id' => [
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);

        register_rest_route($this->namespace, '/statistics/product/preview', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_product_preview_callback'],
            'permission_callback' => [$this, 'check_permission'],
            'args'                => [
                'sku' => [
                    'required'          => true,
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);
    }

    public function get_product_preview_callback($request) {
        return $this->handle_request([$this, 'get_product_preview'], $request);
    }

    public function get_product_stats_callback($request) {
        return $this->handle_request([$this, 'get_product_stats'], $request);
    }

    protected function get_product_preview($request) {
        $sku = $request->get_param('sku');
        $product_data = $this->resolve_product_by_sku($sku);

        if (!$product_data) {
            return Hizli_Kasa_API_Response::error('SKU ile eşleşen ürün bulunamadı.', 404);
        }

        return Hizli_Kasa_API_Response::success(['product' => $product_data]);
    }

    protected function get_product_stats($request) {
        $sku        = $request->get_param('sku');
        $date_start = $request->get_param('date_start') ?: current_time('Y-m-d');
        $date_end   = $request->get_param('date_end')   ?: current_time('Y-m-d');
        $depo_id    = absint($request->get_param('depo_id'));

        $product_data = $this->resolve_product_by_sku($sku);
        if (!$product_data) {
            return Hizli_Kasa_API_Response::error('SKU ile eşleşen ürün bulunamadı.', 404);
        }

        $product_ids = $product_data['all_variation_ids'];

        $ts_start = $date_start . ' 00:00:00';
        $ts_end   = $date_end   . ' 23:59:59';

        $order_args = [
            'limit'        => -1,
            'status'       => ['processing', 'completed', 'on-hold'],
            'date_created' => $ts_start . '...' . $ts_end,
        ];

        $meta_query = [['key' => '_hizli_kasa_kasa_no', 'compare' => 'EXISTS']];
        if ($depo_id > 0) {
            $meta_query[] = ['key' => '_hk_cikis_depo_id', 'value' => $depo_id];
        }
        $order_args['meta_query'] = $meta_query;

        $all_orders = wc_get_orders($order_args);

        $satis_adet     = 0;
        $satis_ciro     = 0.0;
        $iade_adet      = 0;
        $iade_tutar     = 0.0;
        $gun_map        = [];
        $satis_listesi  = [];
        $variation_map  = [];

        foreach ($all_orders as $order) {
            $is_refund  = ($order->get_meta('_hizli_kasa_is_refund') === 'yes');
            $created_dt = $order->get_date_created();

            foreach ($order->get_items() as $item) {
                if (!$item instanceof WC_Order_Item_Product) {
                    continue;
                }

                $item_product = $item->get_product();
                if (!$item_product) {
                    continue;
                }

                $item_pid = $item_product->get_id();
                if (!in_array($item_pid, $product_ids, true)) {
                    continue;
                }

                $item_qty   = (int) $item->get_quantity();
                $item_total = (float) $item->get_total();
                $gun_key    = $created_dt->date('Y-m-d');

                if (!isset($gun_map[$gun_key])) {
                    $gun_map[$gun_key] = ['satis_adet' => 0, 'satis_ciro' => 0.0, 'iade_adet' => 0, 'order_ids' => []];
                }

                $var_name = $item_product->get_name();
                $var_sku  = $item_product->get_sku();
                $var_key  = $item_pid;

                if (!isset($variation_map[$var_key])) {
                    $variation_map[$var_key] = ['name' => $var_name, 'sku' => $var_sku, 'satis_adet' => 0, 'satis_ciro' => 0.0, 'iade_adet' => 0];
                }

                if ($is_refund) {
                    $iade_adet  += $item_qty;
                    $iade_tutar += abs($item_total);
                    $gun_map[$gun_key]['iade_adet'] += $item_qty;
                    $variation_map[$var_key]['iade_adet'] += $item_qty;
                } else {
                    $satis_adet += $item_qty;
                    $satis_ciro += $item_total;
                    $gun_map[$gun_key]['satis_adet'] += $item_qty;
                    $gun_map[$gun_key]['satis_ciro'] += $item_total;
                    $variation_map[$var_key]['satis_adet'] += $item_qty;
                    $variation_map[$var_key]['satis_ciro'] += $item_total;

                    if (!in_array($order->get_id(), $gun_map[$gun_key]['order_ids'], true)) {
                        $gun_map[$gun_key]['order_ids'][] = $order->get_id();
                    }

                    $birim_fiyat = $item_qty > 0 ? round($item_total / $item_qty, 2) : 0;
                    $kasiyer     = $order->get_meta('_hizli_kasa_kasiyer') ?: 'Bilinmeyen';

                    $satis_listesi[] = [
                        'order_id'    => $order->get_id(),
                        'tarih'       => $created_dt->date('Y-m-d H:i:s'),
                        'tarih_kisa'  => $created_dt->date('d.m.Y H:i'),
                        'kasiyer'     => $kasiyer,
                        'adet'        => $item_qty,
                        'birim_fiyat' => $birim_fiyat,
                        'toplam'      => round($item_total, 2),
                        'variation'   => $var_name !== $product_data['name'] ? $var_name : '',
                    ];
                }
            }
        }

        usort($satis_listesi, fn($a, $b) => strcmp($b['tarih'], $a['tarih']));

        ksort($gun_map);
        $gunluk_trend = [];
        foreach ($gun_map as $tarih => $v) {
            $gunluk_trend[] = [
                'tarih'       => $tarih,
                'tarih_kisa'  => date_i18n('d.m', strtotime($tarih)),
                'satis_adet'  => $v['satis_adet'],
                'satis_ciro'  => round($v['satis_ciro'], 2),
                'iade_adet'   => $v['iade_adet'],
                'order_ids'   => $v['order_ids'],
            ];
        }

        $net_ciro = round($satis_ciro - $iade_tutar, 2);

        $maliyet_birim = $this->resolve_unit_cost($product_data);
        $toplam_maliyet = $maliyet_birim !== null ? round($maliyet_birim * $satis_adet, 2) : null;
        $brut_kar       = $toplam_maliyet !== null ? round($satis_ciro - $toplam_maliyet, 2) : null;

        $variations_out = [];
        foreach ($variation_map as $vid => $vdata) {
            $variations_out[] = [
                'product_id'  => $vid,
                'name'        => $vdata['name'],
                'sku'         => $vdata['sku'],
                'satis_adet'  => $vdata['satis_adet'],
                'satis_ciro'  => round($vdata['satis_ciro'], 2),
                'iade_adet'   => $vdata['iade_adet'],
            ];
        }

        usort($variations_out, fn($a, $b) => $b['satis_adet'] <=> $a['satis_adet']);

        $response_data = [
            'product' => $product_data,
            'kpi'     => [
                'toplam_satis_adet'  => $satis_adet,
                'toplam_satis_ciro'  => round($satis_ciro, 2),
                'toplam_iade_adet'   => $iade_adet,
                'toplam_iade_tutar'  => round($iade_tutar, 2),
                'net_ciro'           => $net_ciro,
                'maliyet_birim'      => $maliyet_birim,
                'maliyet_kaynak'     => $this->get_cost_source_label($product_data),
                'toplam_maliyet'     => $toplam_maliyet,
                'brut_kar'           => $brut_kar,
            ],
            'gunluk_trend'    => $gunluk_trend,
            'satis_listesi'   => $satis_listesi,
            'variations'      => $variations_out,
        ];

        return Hizli_Kasa_API_Response::success($response_data);
    }

    private function resolve_product_by_sku($sku) {
        $product_id = wc_get_product_id_by_sku($sku);

        if (!$product_id) {
            return null;
        }

        $product = wc_get_product($product_id);
        if (!$product) {
            return null;
        }

        $type      = $product->get_type();
        $parent_id = 0;
        $all_variation_ids = [$product_id];

        if ($type === 'variable') {
            $variation_ids     = $product->get_children();
            $all_variation_ids = $variation_ids;
        } elseif ($type === 'variation') {
            $parent_id         = $product->get_parent_id();
            $all_variation_ids = [$product_id];
        }

        // Fetch image details
        $image_id = $product->get_image_id();
        if (!$image_id && $type === 'variation') {
            $parent_temp = wc_get_product($parent_id);
            if ($parent_temp) {
                $image_id = $parent_temp->get_image_id();
            }
        }
        $image_url = $image_id ? wp_get_attachment_image_url($image_id, 'thumbnail') : wc_placeholder_img_src();
        $image_full_url = $image_id ? wp_get_attachment_image_url($image_id, 'full') : wc_placeholder_img_src();

        // Build relations
        $relations = [
            'parent'   => null,
            'siblings' => [],
            'children' => [],
        ];

        if ($type === 'variation' && $parent_id) {
            $parent_product = wc_get_product($parent_id);
            if ($parent_product) {
                $p_img_id = $parent_product->get_image_id();
                $relations['parent'] = [
                    'id'             => $parent_id,
                    'name'           => $parent_product->get_name(),
                    'sku'            => $parent_product->get_sku(),
                    'image_url'      => $p_img_id ? wp_get_attachment_image_url($p_img_id, 'thumbnail') : wc_placeholder_img_src(),
                    'image_full_url' => $p_img_id ? wp_get_attachment_image_url($p_img_id, 'full') : wc_placeholder_img_src(),
                ];

                $sibling_ids = $parent_product->get_children();
                foreach ($sibling_ids as $sib_id) {
                    if ($sib_id === $product_id) {
                        continue;
                    }
                    $sib_product = wc_get_product($sib_id);
                    if ($sib_product) {
                        $s_img_id = $sib_product->get_image_id() ?: $p_img_id;
                        $relations['siblings'][] = [
                            'id'             => $sib_id,
                            'name'           => $sib_product->get_name(),
                            'sku'            => $sib_product->get_sku(),
                            'image_url'      => $s_img_id ? wp_get_attachment_image_url($s_img_id, 'thumbnail') : wc_placeholder_img_src(),
                            'image_full_url' => $s_img_id ? wp_get_attachment_image_url($s_img_id, 'full') : wc_placeholder_img_src(),
                        ];
                    }
                }
            }
        } elseif ($type === 'variable') {
            $child_ids = $product->get_children();
            foreach ($child_ids as $c_id) {
                $c_product = wc_get_product($c_id);
                if ($c_product) {
                    $c_img_id = $c_product->get_image_id() ?: $image_id;
                    $relations['children'][] = [
                        'id'             => $c_id,
                        'name'           => $c_product->get_name(),
                        'sku'            => $c_product->get_sku(),
                        'image_url'      => $c_img_id ? wp_get_attachment_image_url($c_img_id, 'thumbnail') : wc_placeholder_img_src(),
                        'image_full_url' => $c_img_id ? wp_get_attachment_image_url($c_img_id, 'full') : wc_placeholder_img_src(),
                    ];
                }
            }
        }

        return [
            'id'                  => $product_id,
            'name'                => $product->get_name(),
            'sku'                 => $product->get_sku(),
            'type'                => $type,
            'parent_id'           => $parent_id,
            'all_variation_ids'   => $all_variation_ids,
            'wc_cost'             => get_post_meta($product_id, '_wc_cog_cost', true),
            'image_url'           => $image_url,
            'image_full_url_real' => $image_full_url,
            'relations'           => $relations,
        ];
    }

    private function resolve_unit_cost($product_data) {
        $wc_cost = floatval($product_data['wc_cost']);
        if ($wc_cost > 0) {
            return $wc_cost;
        }

        $product_id   = $product_data['id'];
        $variation_id = ($product_data['type'] === 'variation') ? $product_id : 0;

        $tables = Hizli_Kasa_Database::get_tables();
        if (!isset($tables['purchase_order_items'])) {
            return null;
        }

        global $wpdb;

        if ($variation_id > 0) {
            $avg = $wpdb->get_var($wpdb->prepare(
                "SELECT AVG(unit_cost) FROM {$tables['purchase_order_items']} WHERE variation_id = %d AND unit_cost > 0",
                $variation_id
            ));
        } else {
            $avg = $wpdb->get_var($wpdb->prepare(
                "SELECT AVG(unit_cost) FROM {$tables['purchase_order_items']} WHERE product_id = %d AND unit_cost > 0",
                $product_id
            ));
        }

        if ($avg && floatval($avg) > 0) {
            return round(floatval($avg), 4);
        }

        return null;
    }

    private function get_cost_source_label($product_data) {
        $wc_cost = floatval($product_data['wc_cost']);
        if ($wc_cost > 0) {
            return 'wc_cog';
        }

        global $wpdb;
        $tables = Hizli_Kasa_Database::get_tables();
        if (!isset($tables['purchase_order_items'])) {
            return 'yok';
        }

        $product_id   = $product_data['id'];
        $variation_id = ($product_data['type'] === 'variation') ? $product_id : 0;

        if ($variation_id > 0) {
            $count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$tables['purchase_order_items']} WHERE variation_id = %d AND unit_cost > 0",
                $variation_id
            ));
        } else {
            $count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$tables['purchase_order_items']} WHERE product_id = %d AND unit_cost > 0",
                $product_id
            ));
        }

        return intval($count) > 0 ? 'hk_po' : 'yok';
    }
}
