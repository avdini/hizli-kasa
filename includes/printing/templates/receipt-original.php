<?php
if (!defined('ABSPATH')) exit;
require_once __DIR__ . '/base-template.php';

$header = $data['header'] ?? [];
$items  = $data['items'] ?? [];
$totals = $data['totals'] ?? [];
?>
<div class="hk-unified-print-container receipt-original" style="color:#000000 !important; background:#ffffff !important; width:100%; max-width:300px; margin:0 auto; padding:4px 0; box-sizing:border-box; font-family:'Courier New', Courier, monospace; font-size:12px; line-height:1.2; box-shadow:none !important; border:none !important;">
    <!-- Store Header -->
    <div style="text-align:center; margin-bottom:8px; border-bottom:1px solid #000000; padding-bottom:8px;">
        <h2 style="margin:0; font-size:18px; font-weight:bold; color:#000000; font-family:'Courier New', Courier, monospace;"><?php echo esc_html($header['store_name'] ?? get_bloginfo('name')); ?></h2>
        <p style="margin:4px 0 2px 0; font-size:12px; font-weight:bold; color:#000000; font-family:'Courier New', Courier, monospace;"><?php echo esc_html($header['badge'] ?? 'HIZLI KASA SATIŞ FİŞİ'); ?></p>
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
                <th style="text-align:right; padding:4px 0; color:#000000; font-family:'Courier New', Courier, monospace;">Toplam</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $item): 
                $qty = (float)($item['qty'] ?? 1);
                $line_total = (float)($item['line_total'] ?? 0);
                $etiket_fiyat = (float)($item['etiket_fiyat'] ?? ($item['price'] ?? 0));
                $kampanya_fiyat = (float)($item['kampanya_fiyat'] ?? ($item['price'] ?? 0));
                
                $satir_etiket_toplam = $etiket_fiyat * $qty;
                $satir_kampanya_toplam = $kampanya_fiyat * $qty;
                $satir_net_toplam = $line_total;
                $item_discount = (float)($item['item_discount'] ?? 0);
            ?>
                <tr>
                    <td class="fis-item-td-left" style="padding:3px 0; vertical-align:top; color:#000000; line-height:1.2;">
                        <div class="fis-item-name" style="font-weight:bold; font-size:12px; text-transform:uppercase; color:#000000; font-family:'Courier New', Courier, monospace;"><?php echo esc_html($item['name']); ?></div>
                        <div class="fis-item-sku-qty" style="font-size:10px; color:#000000; font-family:'Courier New', Courier, monospace;"><?php echo !empty($item['sku']) ? esc_html($item['sku']) . ' | ' : ''; ?><?php echo esc_html($qty); ?> Adet</div>
                        <?php if ($item_discount > 0): ?>
                            <div style="font-size:9px; color:#000000; font-family:'Courier New', Courier, monospace;">(İsk: -<?php echo hk_format_price($item_discount); ?>)</div>
                        <?php endif; ?>
                    </td>
                    <td class="fis-item-td-right" style="text-align:right; padding:3px 0 3px 10px; vertical-align:middle; white-space:nowrap; font-weight:bold; font-size:13px; color:#000000;">
                        <?php if ($satir_etiket_toplam > $satir_kampanya_toplam + 0.01): ?>
                            <div style="font-size:10px; text-decoration:line-through; font-weight:normal; color:#000000; font-family:'Courier New', Courier, monospace;"><?php echo hk_format_price($satir_etiket_toplam); ?></div>
                        <?php endif; ?>
                        <?php if ($satir_kampanya_toplam > $satir_net_toplam + 0.01): ?>
                            <div style="font-size:10px; text-decoration:line-through; font-weight:normal; color:#000000; font-family:'Courier New', Courier, monospace;"><?php echo hk_format_price($satir_kampanya_toplam); ?></div>
                        <?php endif; ?>
                        <div class="fis-item-price" style="font-family:'Courier New', Courier, monospace; font-size:13px; font-weight:bold; color:#000000;"><?php echo hk_format_price($satir_net_toplam); ?></div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Totals Breakdown -->
    <div style="border-top:1px solid #000000; padding-top:6px; font-size:12px; margin-bottom:8px; font-family:'Courier New', Courier, monospace;">
        <?php if (($totals['gross_total'] ?? 0) > ($totals['net_paid'] ?? $totals['order_total'] ?? 0)): ?>
            <div style="display:flex; justify-content:space-between; margin-bottom:2px; color:#000000;">
                <span>Etiket Toplamı:</span>
                <span><?php echo hk_format_price($totals['gross_total']); ?></span>
            </div>
        <?php endif; ?>
        <?php if (($totals['item_discount_total'] ?? 0) > 0): ?>
            <div style="display:flex; justify-content:space-between; margin-bottom:2px; color:#000000;">
                <span>İskonto Toplamı:</span>
                <span>-<?php echo hk_format_price($totals['item_discount_total']); ?></span>
            </div>
        <?php endif; ?>
        <?php if (($totals['auto_discount'] ?? 0) > 0): ?>
            <div style="display:flex; justify-content:space-between; margin-bottom:2px; color:#000000;">
                <span>Otomatik İndirim (%5):</span>
                <span>-<?php echo hk_format_price($totals['auto_discount']); ?></span>
            </div>
        <?php endif; ?>
        <?php if (($totals['coupon_discount'] ?? 0) > 0): ?>
            <div style="display:flex; justify-content:space-between; margin-bottom:2px; color:#000000;">
                <span>İade Çeki / Kupon:</span>
                <span>-<?php echo hk_format_price($totals['coupon_discount']); ?></span>
            </div>
        <?php endif; ?>
        <?php if (($totals['exchange_diff'] ?? 0) > 0): ?>
            <div style="display:flex; justify-content:space-between; margin-bottom:2px; color:#000000;">
                <span>Değişim Mahsubu:</span>
                <span>-<?php echo hk_format_price($totals['exchange_diff']); ?></span>
            </div>
        <?php endif; ?>
        <div style="display:flex; justify-content:space-between; font-weight:bold; font-size:16px; margin-top:6px; border-top:1px solid #000000; padding-top:4px; color:#000000;">
            <span>TOPLAM:</span>
            <span><?php echo hk_format_price($totals['net_paid'] ?? $totals['order_total'] ?? 0); ?></span>
        </div>
    </div>

    <div style="text-align:center; margin-top:12px; font-size:11px; border-top:1px solid #000000; padding-top:8px; color:#000000; font-family:'Courier New', Courier, monospace;">
        Bizi tercih ettiğiniz için teşekkür ederiz.
    </div>
</div>
