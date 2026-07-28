<?php
if (!defined('ABSPATH')) exit;
require_once __DIR__ . '/base-template.php';

$header      = $data['header'] ?? [];
$items       = $data['items'] ?? [];
$totals      = $data['totals'] ?? [];
$audit_trail = $data['audit_trail'] ?? [];
?>
<div class="hk-unified-print-container receipt-modified" style="color:#000; background:#fff; width:100%; max-width:300px; margin:0 auto; padding:4px 0; box-sizing:border-box; font-family: Arial, Helvetica, sans-serif; font-size:12px; line-height:1.2; box-shadow:none !important; border:none !important;">
    <!-- Store Header -->
    <div style="text-align:center; margin-bottom:8px; border-bottom:1px solid #000; padding-bottom:8px;">
        <h2 style="margin:0; font-size:18px; font-weight:bold; color:#000;"><?php echo esc_html($header['store_name'] ?? get_bloginfo('name')); ?></h2>
        <p style="margin:4px 0 2px 0; font-size:12px; font-weight:bold; color:#000;">İŞLEM GÖRMÜŞ SATIŞ FİŞİ</p>
        <p style="margin:0; font-size:11px; color:#000;"><?php echo esc_html($header['date'] ?? ''); ?></p>
        <p style="margin:4px 0 0 0; font-weight:bold; font-size:14px; color:#000;">SİPARİŞ NO: #<?php echo esc_html($header['order_number'] ?? ''); ?></p>
        <?php if (!empty($header['cashier'])): ?>
            <p style="margin:2px 0 0 0; font-size:10px; color:#000;">Kasiyer: <?php echo esc_html($header['cashier']); ?> | <?php echo esc_html($header['register_no'] ?? 'Kasa 1'); ?></p>
        <?php endif; ?>
    </div>

    <!-- Items Table (Current & Refunded Breakdown) -->
    <table style="width:100%; border-collapse:collapse; font-size:12px; margin-bottom:8px;">
        <thead>
            <tr style="border-bottom:1px solid #000;">
                <th style="text-align:left; padding:4px 0; color:#000;">Ürün</th>
                <th style="text-align:right; padding:4px 0; color:#000;">Toplam</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $item): ?>
                <tr style="border-bottom:1px dashed #ccc;">
                    <td style="padding:4px 0; vertical-align:top; color:#000; line-height:1.2;">
                        <div style="font-weight:bold; font-size:12px; text-transform:uppercase; color:#000;"><?php echo esc_html($item['name']); ?></div>
                        <div style="font-size:10px; color:#000;">
                            Net: <?php echo esc_html($item['net_qty']); ?> / Toplam: <?php echo esc_html($item['qty']); ?> Adet
                        </div>
                        <?php if ($item['refunded_qty'] > 0): ?>
                            <div style="font-size:9px; font-weight:bold; color:#000;">(<?php echo esc_html($item['refunded_qty']); ?> Adet İade Edildi)</div>
                        <?php endif; ?>
                    </td>
                    <td style="text-align:right; padding:4px 0; vertical-align:middle; white-space:nowrap; font-weight:bold; font-size:13px; color:#000;">
                        <?php echo hk_format_price($item['net_total']); ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Financial Totals -->
    <div style="border-top:1px solid #000; padding-top:6px; font-size:12px; margin-bottom:8px;">
        <div style="display:flex; justify-content:space-between; margin-bottom:2px; color:#000;">
            <span>İlk Sipariş Toplamı:</span>
            <span><?php echo hk_format_price($totals['order_total'] ?? 0); ?></span>
        </div>
        <?php if (($totals['refunded_total'] ?? 0) > 0): ?>
            <div style="display:flex; justify-content:space-between; margin-bottom:2px; font-weight:bold; color:#000;">
                <span>Toplam İade Tutarı:</span>
                <span>-<?php echo hk_format_price($totals['refunded_total']); ?></span>
            </div>
        <?php endif; ?>
        <?php if (($totals['exchange_diff'] ?? 0) != 0): ?>
            <div style="display:flex; justify-content:space-between; margin-bottom:2px; color:#000;">
                <span>Değişim Farkı:</span>
                <span><?php echo hk_format_price($totals['exchange_diff']); ?></span>
            </div>
        <?php endif; ?>
        <div style="display:flex; justify-content:space-between; font-weight:bold; font-size:15px; margin-top:4px; border-top:1px solid #000; padding-top:4px; color:#000;">
            <span>GÜNCEL TOPLAM:</span>
            <span><?php echo hk_format_price($totals['net_paid'] ?? 0); ?></span>
        </div>
    </div>

    <!-- Audit Trail History -->
    <?php if (!empty($audit_trail)): ?>
        <div style="border-top:1px dashed #000; padding-top:6px; margin-bottom:8px;">
            <div style="font-weight:bold; font-size:10px; text-transform:uppercase; margin-bottom:3px; color:#000;">
                İşlem Geçmişi
            </div>
            <?php foreach ($audit_trail as $audit): ?>
                <div style="font-size:9px; margin-bottom:2px; color:#000;">
                    • <strong><?php echo esc_html($audit['timestamp'] ?? ''); ?></strong> - <?php echo esc_html($audit['action'] ?? ''); ?>
                    <?php if (isset($audit['amount'])): ?>
                        (<?php echo hk_format_price($audit['amount']); ?>)
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Barcode Container -->
    <?php if (!empty($data['barcode_value'])): ?>
        <div style="text-align:center; margin-top:8px;">
            <img class="hk-print-barcode-img" data-barcode="<?php echo esc_attr($data['barcode_value']); ?>" style="width:100%; max-width:220px; height:auto; margin:0 auto; display:block;" />
        </div>
    <?php endif; ?>

    <div style="text-align:center; margin-top:10px; font-size:10px; border-top:1px solid #000; padding-top:8px; color:#000;">
        Bu fiş siparişin güncel durumunu gösterir.
    </div>
</div>
