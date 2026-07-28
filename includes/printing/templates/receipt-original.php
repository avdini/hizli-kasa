<?php
if (!defined('ABSPATH')) exit;
require_once __DIR__ . '/base-template.php';

$header = $data['header'] ?? [];
$items  = $data['items'] ?? [];
$totals = $data['totals'] ?? [];
?>
<div class="hk-unified-print-container receipt-original" style="font-family: 'Courier New', Courier, monospace; color:#000; width:100%; max-width:300px; margin:0 auto; padding:10px; box-sizing:border-box; font-size:12px; line-height:1.3;">
    <!-- Store Header -->
    <div style="text-align:center; margin-bottom:10px; border-bottom:1px dashed #000; padding-bottom:8px;">
        <h3 style="margin:0; font-size:16px; font-weight:bold; font-family:'Courier New', monospace; color:#000;"><?php echo esc_html($header['store_name'] ?? ''); ?></h3>
        <p style="margin:4px 0 0 0; font-size:13px; font-weight:bold; color:#000;"><?php echo esc_html($header['badge'] ?? 'SATIŞ FİŞİ'); ?></p>
        <p style="margin:2px 0 0 0; font-size:11px; color:#000;">Sipariş: <?php echo esc_html($header['order_number'] ?? ''); ?> | <?php echo esc_html($header['register_no'] ?? ''); ?></p>
        <p style="margin:2px 0 0 0; font-size:11px; color:#000;"><?php echo esc_html($header['date'] ?? ''); ?> | Kasiyer: <?php echo esc_html($header['cashier'] ?? ''); ?></p>
    </div>

    <!-- Items Table -->
    <table style="width:100%; border-collapse:collapse; margin-bottom:10px; font-size:12px;">
        <thead>
            <tr style="border-bottom:1px solid #000; text-align:left;">
                <th style="padding:4px 0; color:#000;">Ürün</th>
                <th style="text-align:center; padding:4px 0; color:#000;">Adet</th>
                <th style="text-align:right; padding:4px 0; color:#000;">Tutar</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $item): ?>
                <tr style="border-bottom:1px dotted #ccc;">
                    <td style="padding:4px 0; vertical-align:top; color:#000;">
                        <?php echo esc_html($item['name']); ?>
                        <?php if ($item['item_discount'] > 0): ?>
                            <br><small style="color:#000;">(İskonto: -<?php echo hk_format_price($item['item_discount']); ?>)</small>
                        <?php endif; ?>
                    </td>
                    <td style="text-align:center; padding:4px 0; vertical-align:top; color:#000;"><?php echo esc_html($item['qty']); ?></td>
                    <td style="text-align:right; padding:4px 0; vertical-align:top; font-weight:bold; color:#000;"><?php echo hk_format_price($item['line_total']); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Totals Breakdown -->
    <div style="border-top:1px dashed #000; padding-top:6px; margin-bottom:10px;">
        <div style="display:flex; justify-content:space-between; margin-bottom:3px; color:#000;">
            <span>Brüt Toplam:</span>
            <span><?php echo hk_format_price($totals['gross_total'] ?? 0); ?></span>
        </div>
        <?php if (($totals['item_discount_total'] ?? 0) > 0): ?>
            <div style="display:flex; justify-content:space-between; margin-bottom:3px; color:#000;">
                <span>Ürün İskontoları:</span>
                <span>-<?php echo hk_format_price($totals['item_discount_total']); ?></span>
            </div>
        <?php endif; ?>
        <?php if (($totals['auto_discount'] ?? 0) > 0): ?>
            <div style="display:flex; justify-content:space-between; margin-bottom:3px; color:#000;">
                <span>Otomatik İndirim:</span>
                <span>-<?php echo hk_format_price($totals['auto_discount']); ?></span>
            </div>
        <?php endif; ?>
        <?php if (($totals['manual_discount'] ?? 0) > 0): ?>
            <div style="display:flex; justify-content:space-between; margin-bottom:3px; color:#000;">
                <span>Kasa İskontosu:</span>
                <span>-<?php echo hk_format_price($totals['manual_discount']); ?></span>
            </div>
        <?php endif; ?>
        <div style="display:flex; justify-content:space-between; border-top:1px solid #000; padding-top:4px; font-size:14px; font-weight:bold; color:#000;">
            <span>GENEL TOPLAM:</span>
            <span><?php echo hk_format_price($totals['net_paid'] ?? $totals['order_total'] ?? 0); ?></span>
        </div>
    </div>

    <!-- Barcode Container -->
    <div style="text-align:center; margin-top:12px;">
        <img class="hk-print-barcode-img" data-barcode="<?php echo esc_attr($data['barcode_value'] ?? ''); ?>" style="max-width:100%; height:45px; display:inline-block;" />
        <p style="margin:2px 0 0 0; font-size:10px; color:#000;"><?php echo esc_html($data['barcode_value'] ?? ''); ?></p>
    </div>

    <p style="text-align:center; margin-top:10px; font-size:11px; color:#000;">Bizi Tercih Ettiğiniz İçin Teşekkür Ederiz!</p>
</div>
