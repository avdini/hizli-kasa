<?php
if (!defined('ABSPATH')) exit;
require_once __DIR__ . '/base-template.php';

$header      = $data['header'] ?? [];
$items       = $data['items'] ?? [];
$totals      = $data['totals'] ?? [];
$audit_trail = $data['audit_trail'] ?? [];
?>
<div class="hk-unified-print-container receipt-modified" style="color:#000000 !important; background:#ffffff !important; width:100%; max-width:300px; margin:0 auto; padding:4px 0; box-sizing:border-box; font-family:'Courier New', Courier, monospace; font-size:12px; line-height:1.2; box-shadow:none !important; border:none !important;">
    <!-- Store Header -->
    <div style="text-align:center; margin-bottom:8px; border-bottom:1px solid #000000; padding-bottom:8px;">
        <h2 style="margin:0; font-size:18px; font-weight:bold; color:#000000; font-family:'Courier New', Courier, monospace;"><?php echo esc_html($header['store_name'] ?? get_bloginfo('name')); ?></h2>
        <p style="margin:4px 0 2px 0; font-size:12px; font-weight:bold; color:#000000; font-family:'Courier New', Courier, monospace;"><?php echo esc_html($header['badge'] ?? 'DEĞİŞİM & İADE FİŞİ'); ?></p>
        <p style="margin:0; font-size:11px; color:#000000; font-family:'Courier New', Courier, monospace;"><?php echo esc_html($header['date'] ?? ''); ?></p>
        <p style="margin:4px 0 0 0; font-weight:bold; font-size:14px; color:#000000; font-family:'Courier New', Courier, monospace;">SİPARİŞ NO: #<?php echo esc_html($header['order_number'] ?? ''); ?></p>
        <?php if (!empty($header['cashier'])): ?>
            <p style="margin:2px 0 0 0; font-size:10px; color:#000000; font-family:'Courier New', Courier, monospace;">Kasiyer: <?php echo esc_html($header['cashier']); ?> | <?php echo esc_html($header['register_no'] ?? 'Kasa 1'); ?></p>
        <?php endif; ?>
        
        <?php if (!empty($data['barcode_value'])): ?>
            <div style="text-align:center; margin-top:6px;">
                <img class="hk-print-barcode-img" data-barcode="<?php echo esc_attr($data['barcode_value']); ?>" style="width:100%; max-width:220px; height:auto; margin:0 auto; display:block;" />
            </div>
        <?php endif; ?>
    </div>

    <!-- Items Table -->
    <table style="width:100%; border-collapse:collapse; font-size:12px; margin-bottom:8px; border:none; font-family:'Courier New', Courier, monospace;">
        <thead>
            <tr style="border-bottom:1px solid #000000;">
                <th style="text-align:left; padding:4px 0; color:#000000; font-family:'Courier New', Courier, monospace;">Ürün</th>
                <th style="text-align:right; padding:4px 0; color:#000000; font-family:'Courier New', Courier, monospace;">Net Tutar</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $item): 
                $qty = (float)($item['qty'] ?? 1);
                $net_qty = (float)($item['net_qty'] ?? $qty);
                $refunded_qty = (float)($item['refunded_qty'] ?? 0);
                $net_total = (float)($item['net_total'] ?? 0);
                $status = $item['status'] ?? 'original';
            ?>
                <tr>
                    <td class="fis-item-td-left" style="padding:3px 0; vertical-align:top; color:#000000; line-height:1.2;">
                        <div class="fis-item-name" style="font-weight:bold; font-size:12px; text-transform:uppercase; color:#000000; font-family:'Courier New', Courier, monospace;">
                            <?php echo esc_html($item['name']); ?>
                        </div>
                        
                        <div class="fis-item-sku-qty" style="font-size:10px; color:#000000; font-family:'Courier New', Courier, monospace;">
                            <?php echo !empty($item['sku']) ? esc_html($item['sku']) . ' | ' : ''; ?>
                            <?php if ($refunded_qty > 0): ?>
                                Kalan: <?php echo esc_html($net_qty); ?> Adet (Toplam: <?php echo esc_html($qty); ?> Adet)
                            <?php else: ?>
                                <?php echo esc_html($qty); ?> Adet
                            <?php endif; ?>
                        </div>

                        <?php if ($refunded_qty > 0): ?>
                            <div style="font-size:9px; font-weight:bold; color:#000000; font-family:'Courier New', Courier, monospace;">
                                ⚠️ [<?php echo esc_html($refunded_qty); ?> Adet İade Edildi]
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($item['item_discount']) && $item['item_discount'] > 0): ?>
                            <div style="font-size:9px; color:#000000; font-family:'Courier New', Courier, monospace;">
                                (İsk: -<?php echo hk_format_price($item['item_discount']); ?>)
                            </div>
                        <?php endif; ?>
                    </td>
                    <td class="fis-item-td-right" style="text-align:right; padding:3px 0 3px 10px; vertical-align:middle; white-space:nowrap; font-weight:bold; font-size:13px; color:#000000;">
                        <div class="fis-item-price" style="font-family:'Courier New', Courier, monospace; color:#000000;">
                            <?php echo hk_format_price($net_total); ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Değişim & Finansal Özet -->
    <div style="border-top:1px solid #000000; padding-top:6px; font-size:12px; margin-bottom:8px; font-family:'Courier New', Courier, monospace;">
        <div style="display:flex; justify-content:space-between; margin-bottom:2px; color:#000000;">
            <span>İlk Sipariş Toplamı:</span>
            <span><?php echo hk_format_price($totals['order_total'] ?? 0); ?></span>
        </div>
        
        <?php if (($totals['refunded_total'] ?? 0) > 0): ?>
            <div style="display:flex; justify-content:space-between; margin-bottom:2px; font-weight:bold; color:#000000;">
                <span>İade Edilen Tutar:</span>
                <span>-<?php echo hk_format_price($totals['refunded_total']); ?></span>
            </div>
        <?php endif; ?>

        <?php if (($totals['exchange_diff'] ?? 0) != 0): ?>
            <div style="display:flex; justify-content:space-between; margin-bottom:2px; color:#000000;">
                <span>Değişim Fark Mahsubu:</span>
                <span><?php echo hk_format_price($totals['exchange_diff']); ?></span>
            </div>
        <?php endif; ?>

        <div style="display:flex; justify-content:space-between; font-weight:bold; font-size:16px; margin-top:6px; border-top:1px solid #000000; padding-top:4px; color:#000000;">
            <span>GÜNCEL NET TOPLAM:</span>
            <span><?php echo hk_format_price($totals['net_paid'] ?? 0); ?></span>
        </div>
    </div>

    <!-- Değişim İşlem Geçmişi (Audit Trail) -->
    <?php if (!empty($audit_trail)): ?>
        <div style="border-top:1px solid #000000; padding-top:6px; margin-bottom:8px; font-family:'Courier New', Courier, monospace;">
            <div style="font-weight:bold; font-size:11px; text-transform:uppercase; margin-bottom:4px; color:#000000;">
                📋 DEĞİŞİM & İADE GEÇMİŞİ
            </div>
            <?php foreach ($audit_trail as $audit): ?>
                <div style="font-size:10px; margin-bottom:3px; color:#000000; line-height:1.2;">
                    • <strong><?php echo esc_html($audit['timestamp'] ?? ''); ?></strong><br>
                    &nbsp;&nbsp;<?php echo esc_html($audit['action'] ?? ''); ?>
                    <?php if (isset($audit['amount']) && $audit['amount'] != 0): ?>
                        (<?php echo hk_format_price($audit['amount']); ?>)
                    <?php endif; ?>
                    <?php if (!empty($audit['reason'])): ?>
                        <br>&nbsp;&nbsp;<em>Nedeni: <?php echo esc_html($audit['reason']); ?></em>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div style="text-align:center; margin-top:10px; font-size:10px; border-top:1px solid #000000; padding-top:8px; color:#000000; font-family:'Courier New', Courier, monospace;">
        Bu fiş siparişin güncel değişim durumunu gösterir.
    </div>
</div>
