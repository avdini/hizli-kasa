<?php
if (!defined('ABSPATH')) exit;
require_once __DIR__ . '/base-template.php';

$header      = $data['header'] ?? [];
$items       = $data['items'] ?? [];
$totals      = $data['totals'] ?? [];
$audit_trail = $data['audit_trail'] ?? [];
?>
<div class="hk-unified-print-container receipt-modified" style="font-family: 'Courier New', Courier, monospace; color:#000; width:100%; max-width:300px; margin:0 auto; padding:10px; box-sizing:border-box; font-size:12px; line-height:1.3;">
    <!-- Store Header -->
    <div style="text-align:center; margin-bottom:10px; border-bottom:2px double #000; padding-bottom:8px;">
        <h3 style="margin:0; font-size:16px; font-weight:bold; font-family:'Courier New', monospace; color:#000;"><?php echo esc_html($header['store_name'] ?? ''); ?></h3>
        <div style="display:inline-block; border:1px solid #000; padding:2px 8px; font-weight:bold; margin-top:4px; font-size:11px; background:#f0f0f0; color:#000;">
            ⚠️ <?php echo esc_html($header['badge'] ?? 'İŞLEM GÖRMÜŞ FİŞ'); ?>
        </div>
        <p style="margin:4px 0 0 0; font-size:11px; color:#000;">Sipariş: <?php echo esc_html($header['order_number'] ?? ''); ?> | <?php echo esc_html($header['register_no'] ?? ''); ?></p>
        <p style="margin:2px 0 0 0; font-size:11px; color:#000;"><?php echo esc_html($header['date'] ?? ''); ?> | Kasiyer: <?php echo esc_html($header['cashier'] ?? ''); ?></p>
    </div>

    <!-- Items Table (Current & Refunded Breakdown) -->
    <div style="margin-bottom:6px; font-weight:bold; font-size:11px; text-transform:uppercase; border-bottom:1px solid #000; color:#000;">
        GÜNCEL ÜRÜN BİLGİSİ & İADELER
    </div>
    <table style="width:100%; border-collapse:collapse; margin-bottom:10px; font-size:11px;">
        <thead>
            <tr style="border-bottom:1px solid #000; text-align:left;">
                <th style="padding:4px 0; color:#000;">Ürün</th>
                <th style="text-align:center; padding:4px 0; color:#000;">Net/İade</th>
                <th style="text-align:right; padding:4px 0; color:#000;">Net Tutar</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $item): ?>
                <tr style="border-bottom:1px dotted #ccc;">
                    <td style="padding:4px 0; vertical-align:top; color:#000;">
                        <?php echo esc_html($item['name']); ?>
                        <?php if ($item['refunded_qty'] > 0): ?>
                            <br><span style="font-weight:bold; color:#000;">[<?php echo esc_html($item['refunded_qty']); ?> Adet İade Edildi]</span>
                        <?php endif; ?>
                    </td>
                    <td style="text-align:center; padding:4px 0; vertical-align:top; color:#000;">
                        <?php echo esc_html($item['net_qty']); ?> / <?php echo esc_html($item['qty']); ?>
                    </td>
                    <td style="text-align:right; padding:4px 0; vertical-align:top; font-weight:bold; color:#000;">
                        <?php echo hk_format_price($item['net_total']); ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Financial Totals -->
    <div style="border-top:1px dashed #000; padding-top:6px; margin-bottom:10px;">
        <div style="display:flex; justify-content:space-between; margin-bottom:3px; color:#000;">
            <span>İlk Sipariş Toplamı:</span>
            <span><?php echo hk_format_price($totals['order_total'] ?? 0); ?></span>
        </div>
        <?php if (($totals['refunded_total'] ?? 0) > 0): ?>
            <div style="display:flex; justify-content:space-between; margin-bottom:3px; font-weight:bold; color:#000;">
                <span>Toplam İade Tutarı:</span>
                <span>-<?php echo hk_format_price($totals['refunded_total']); ?></span>
            </div>
        <?php endif; ?>
        <?php if (($totals['exchange_diff'] ?? 0) != 0): ?>
            <div style="display:flex; justify-content:space-between; margin-bottom:3px; color:#000;">
                <span>Değişim Farkı:</span>
                <span><?php echo hk_format_price($totals['exchange_diff']); ?></span>
            </div>
        <?php endif; ?>
        <div style="display:flex; justify-content:space-between; border-top:1px solid #000; padding-top:4px; font-size:13px; font-weight:bold; color:#000;">
            <span>GÜNCEL NET KALAN:</span>
            <span><?php echo hk_format_price($totals['net_paid'] ?? 0); ?></span>
        </div>
    </div>

    <!-- Audit Trail History -->
    <?php if (!empty($audit_trail)): ?>
        <div style="border-top:1px solid #000; padding-top:6px; margin-bottom:10px;">
            <div style="font-weight:bold; font-size:10px; text-transform:uppercase; margin-bottom:4px; color:#000;">
                İŞLEM GEÇMİŞİ / AUDIT TRAIL
            </div>
            <?php foreach ($audit_trail as $audit): ?>
                <div style="font-size:10px; margin-bottom:3px; border-bottom:1px dotted #e0e0e0; padding-bottom:2px; color:#000;">
                    <div><strong><?php echo esc_html($audit['timestamp'] ?? ''); ?></strong> - <?php echo esc_html($audit['action'] ?? ''); ?></div>
                    <?php if (isset($audit['amount'])): ?>
                        <div>Tutar: <?php echo hk_format_price($audit['amount']); ?> <?php if (!empty($audit['reason'])): ?>(<?php echo esc_html($audit['reason']); ?>)<?php endif; ?></div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Barcode Container -->
    <div style="text-align:center; margin-top:10px;">
        <img class="hk-print-barcode-img" data-barcode="<?php echo esc_attr($data['barcode_value'] ?? ''); ?>" style="max-width:100%; height:45px; display:inline-block;" />
        <p style="margin:2px 0 0 0; font-size:10px; color:#000;"><?php echo esc_html($data['barcode_value'] ?? ''); ?></p>
    </div>

    <p style="text-align:center; margin-top:10px; font-size:10px; font-style:italic; color:#000;">Bu fiş siparişin güncel durumunu gösterir (İade/Değişim/Düzenlemeler Dahildir).</p>
</div>
