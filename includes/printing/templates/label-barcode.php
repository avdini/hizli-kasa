<?php
if (!defined('ABSPATH')) exit;

$items = $data['items'] ?? [];
$label = $items['label'] ?? [];
$qty   = $items['qty'] ?? 1;
?>
<div class="hk-unified-print-container label-barcode" style="width: 50mm; height: 35mm; max-height: 35mm; padding: 1.5mm; box-sizing: border-box; font-family: Arial, sans-serif; overflow: hidden; background: #fff; color: #000; margin: 0 auto;">
    <!-- Product Name -->
    <div style="font-size: 10px; font-weight: bold; line-height: 1.1; max-height: 2.2em; overflow: hidden; text-transform: uppercase; margin-bottom: 2px; text-align: center; color: #000;">
        <?php echo esc_html($label['title'] ?? ''); ?>
    </div>

    <div style="display: flex; justify-content: space-between; align-items: center;">
        <!-- Left Side: Barcode -->
        <div style="width: 60%; text-align: center;">
            <img class="hk-print-barcode-img" data-barcode="<?php echo esc_attr($label['barcode_value'] ?? ''); ?>" style="width: 100%; height: 22mm; display: block;" />
            <div style="font-size: 8px; margin-top: 1px; color: #000; font-family: monospace;"><?php echo esc_html($label['barcode_value'] ?? ''); ?></div>
        </div>

        <!-- Right Side: Model & Price -->
        <div style="width: 38%; text-align: right; font-size: 9px; line-height: 1.2;">
            <?php if (!empty($label['model_code'])): ?>
                <div style="font-weight: bold; font-size: 8px; color: #000;"><?php echo esc_html($label['model_code']); ?></div>
            <?php endif; ?>
            <?php if (!empty($label['attributes'])): ?>
                <div style="font-size: 8px; color: #333; margin-top: 1px;"><?php echo esc_html(implode(' / ', $label['attributes'])); ?></div>
            <?php endif; ?>
            
            <div style="margin-top: 4px; font-weight: bold; font-size: 11px; color: #000;">
                <?php echo esc_html($label['price_formatted'] ?? ''); ?>
            </div>
        </div>
    </div>
</div>
