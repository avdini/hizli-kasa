<?php
/**
 * Hızlı Kasa - Barkod Yardımcı Sınıfı
 *
 * Ürün verilerini barkod etiketine uygun formata dönüştürür.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Hizli_Kasa_Barcode_Helper {

    /**
     * Ürün veya varyasyon ID'sine göre barkod verisini hazırlar.
     */
    public static function prepare_label_data($product_id, $variation_id = 0) {
        $id = $variation_id ?: $product_id;
        $product = wc_get_product($id);

        if (!$product) {
            return null;
        }

        $parent_id = ($product->is_type('variation')) ? $product->get_parent_id() : $product->get_id();
        $parent = ($product->is_type('variation')) ? wc_get_product($parent_id) : $product;
        $sku = $product->is_type('variation') ? get_post_meta($id, '_sku', true) : $product->get_sku();

        $barcode_no = $sku ?: (string)$id;

        // 2. Model (Ana SKU)
        $model_no = $parent->get_sku() ?: (string)$parent_id;

        // 3. Ürün Adı (Varyant olsa da ana ürün adı kullanılır)
        $product_name = $parent->get_name();

        // 4. Özellikler (Renk / Beden)
        $attributes = self::get_formatted_attributes($product);

        // 5. Fiyatlar
        $prices = self::get_formatted_prices($product);

        return [
            'id'           => $id,
            'barcode_no'   => $barcode_no,
            'model_no'     => $model_no,
            'product_name' => $product_name,
            'attributes'   => $attributes,
            'price_data'   => $prices
        ];
    }

    /**
     * Renk ve Beden/Numara bilgilerini formatlar.
     * Renk: Normal font, Baş harf büyük.
     * Beden: BÜYÜK HARF ve kalın.
     * Kategori "Ayakkabı" ise "Numara" etiketi kullanılır.
     */
    private static function get_formatted_attributes($product) {
        $attributes = [];
        
        if (!$product->is_type('variation')) {
            return $attributes;
        }

        $variation_attributes = $product->get_variation_attributes();
        $formatted = [
            'color' => '',
            'size'  => '',
            'label' => 'Beden' // Varsayılan
        ];

        // Kategori kontrolü (Ayakkabı mı?)
        $parent_id = $product->get_parent_id();
        $terms = get_the_terms($parent_id, 'product_cat');
        if ($terms && !is_wp_error($terms)) {
            foreach ($terms as $term) {
                if (mb_stripos($term->name, 'Ayakkabı') !== false) {
                    $formatted['label'] = 'Numara';
                    break;
                }
            }
        }

        foreach ($variation_attributes as $attr_name => $attr_value) {
            // slug'ı temizle (pa_color -> color)
            $clean_name = str_replace('attribute_pa_', '', $attr_name);
            $clean_name = str_replace('attribute_', '', $clean_name);

            // Değeri al (term name)
            $taxonomy = str_replace('attribute_', '', $attr_name);
            $term = get_term_by('slug', $attr_value, $taxonomy);
            $display_value = $term ? $term->name : $attr_value;

            if (mb_stripos($clean_name, 'renk') !== false || mb_stripos($clean_name, 'color') !== false) {
                $formatted['color'] = mb_convert_case($display_value, MB_CASE_TITLE, "UTF-8");
            } elseif (mb_stripos($clean_name, 'beden') !== false || mb_stripos($clean_name, 'size') !== false || mb_stripos($clean_name, 'numara') !== false) {
                $formatted['size'] = mb_strtoupper($display_value, "UTF-8");
            } elseif (mb_stripos($clean_name, 'ölçü') !== false || mb_stripos($clean_name, 'olcu') !== false || mb_stripos($clean_name, 'boy') !== false) {
                $formatted['size'] = mb_strtoupper($display_value, "UTF-8");
                $formatted['label'] = 'Ölçü';
            }
        }

        // Eğer renk varyasyonda tanımlı değilse parent (ana) üründen çekmeyi dene (Used for variations kapalıysa)
        if (empty($formatted['color'])) {
            $parent = wc_get_product($parent_id);
            if ($parent) {
                foreach ($parent->get_attributes() as $attr_name => $attribute) {
                    $label = wc_attribute_label($attr_name, $parent);
                    if (mb_stripos($label, 'renk') !== false || mb_stripos($label, 'color') !== false) {
                        $display_value = $parent->get_attribute($attr_name);
                        if (!empty($display_value)) {
                            $formatted['color'] = mb_convert_case($display_value, MB_CASE_TITLE, "UTF-8");
                            break;
                        }
                    }
                }
            }
        }

        return $formatted;
    }

    /**
     * İndirimli ve normal fiyatları hazırlar.
     */
    private static function get_formatted_prices($product) {
        $regular_price = (float)$product->get_regular_price();
        $sale_price    = (float)$product->get_sale_price();
        $is_on_sale    = $product->is_on_sale() && $sale_price > 0 && $sale_price < $regular_price;

        $currency_symbol = get_woocommerce_currency_symbol();

        if ($is_on_sale) {
            return [
                'on_sale'       => true,
                'regular_price' => number_format($regular_price, 2, ',', '.') . ' ' . $currency_symbol,
                'sale_price'    => number_format($sale_price, 2, ',', '.') . ' ' . $currency_symbol
            ];
        } else {
            $price = $product->get_price();
            return [
                'on_sale' => false,
                'price'   => number_format((float)$price, 2, ',', '.') . ' ' . $currency_symbol
            ];
        }
    }
}
