<?php
if (!defined('ABSPATH')) exit;

$items = $data['items'] ?? [];
$label = $items['label'] ?? [];
$qty   = max(1, intval($items['qty'] ?? 1));

$product_name = esc_html($label['product_name'] ?? $label['title'] ?? '');
$model_no     = esc_html($label['model_no'] ?? $label['model_code'] ?? '');
$barcode_no   = esc_html($label['barcode_no'] ?? $label['barcode_value'] ?? '');
$attributes   = $label['attributes'] ?? [];
$price_data   = $label['price_data'] ?? [];

$colorVal = esc_html(is_array($attributes) ? ($attributes['color'] ?? '') : '');
$sizeVal  = esc_html(is_array($attributes) ? ($attributes['size'] ?? '') : '');
$sizeLabel = esc_html(is_array($attributes) ? ($attributes['label'] ?? 'Beden') : 'Beden');

$showColorLabel = (mb_strlen($colorVal) <= 8);
$colorClass = (mb_strlen($colorVal) > 14) ? 'color-val text-shrink' : 'color-val';

$showSizeLabel = (mb_strlen($sizeVal) <= 4);
$sizeClass = (mb_strlen($sizeVal) > 7) ? 'size-val text-shrink' : 'size-val';

for ($i = 0; $i < $qty; $i++):
?>
<div class="barcode-label">
    <div class="label-header">
        <div class="product-name"><?php echo $product_name; ?></div>
    </div>
    <div class="label-body">
        <div class="col-left">
            <div class="model-no">Model: <?php echo $model_no; ?></div>
            <div class="barcode-container">
                <img class="hk-print-barcode-img" data-barcode="<?php echo $barcode_no; ?>" style="width:100%; height:100%; display:block;" />
            </div>
            <div class="sku-text">SKU: <?php echo $barcode_no; ?></div>
        </div>
        <div class="col-right">
            <div class="attributes">
                <?php if (!empty($colorVal)): ?>
                <div class="attr-color">
                    <?php if ($showColorLabel): ?><span class="color-label">Renk:</span><?php endif; ?>
                    <span class="<?php echo $colorClass; ?>"><?php echo $colorVal; ?></span>
                </div>
                <?php endif; ?>
                <?php if (!empty($sizeVal)): ?>
                <div class="attr-size">
                    <?php if ($showSizeLabel): ?><span class="size-label"><?php echo $sizeLabel; ?>:</span><?php endif; ?>
                    <span class="<?php echo $sizeClass; ?>"><?php echo $sizeVal; ?></span>
                </div>
                <?php endif; ?>
            </div>
            <div class="price-section <?php echo (!empty($price_data['on_sale'])) ? 'has-sale' : ''; ?>">
                <?php if (!empty($price_data['on_sale'])): ?>
                    <div class="price-old"><?php echo esc_html($price_data['regular_price'] ?? ''); ?></div>
                    <div class="price-new"><?php echo esc_html($price_data['sale_price'] ?? ''); ?></div>
                <?php else: ?>
                    <div class="price-single"><?php echo esc_html($price_data['price'] ?? $label['price_formatted'] ?? ''); ?></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php endfor; ?>
