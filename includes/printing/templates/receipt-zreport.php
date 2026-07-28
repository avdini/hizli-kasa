<?php
if (!defined('ABSPATH')) exit;
require_once __DIR__ . '/base-template.php';

$header = $data['header'] ?? [];
$rapor = $data['extra_data'] ?? [];
$ozet = $rapor['ozet'] ?? [];
$options = $rapor['print_options'] ?? [];
$include_qr = !empty($options['include_qr']);
$include_details = !isset($options['include_details']) || $options['include_details'] !== false;
$money = static function ($value) {
    return number_format((float) $value, 2, '.', '') . ' TL';
};
$value = static function ($array, $key, $default = 0) {
    return $array[$key] ?? $default;
};
$kasa_no = $rapor['kasa_no'] ?? ($header['kasa_no'] ?? '1');
$is_general = $kasa_no === 'Genel';
$depo_name = $options['depo_name'] ?? ($rapor['depo_adi'] ?? '');
$date_label = $rapor['tarih_okunabilir'] ?? ($header['tarih'] ?? '');
$report_time = $rapor['rapor_zamani'] ?? ($header['report_time'] ?? '');
?>
<div class="hk-unified-print-container receipt-zreport" style="font-family:'Courier New', Courier, monospace; color:#000; width:80mm; max-width:80mm; margin:0; padding:3mm; background:#fff; box-sizing:border-box; font-size:12px; line-height:1.3;">
    <div style="text-align:center; margin-bottom:8px; border-bottom:1px solid #000; padding-bottom:8px;">
        <h2 style="margin:0; font-size:16px;"><?php echo esc_html($header['store_name'] ?? get_bloginfo('name')); ?></h2>
        <p style="margin:3px 0; font-size:13px; font-weight:bold;"><?php echo $is_general ? 'GENEL GÜN SONU RAPORU' : 'GÜN SONU RAPORU'; ?></p>
        <p style="margin:2px 0; font-size:11px;">Kasa: <?php echo esc_html($kasa_no . ($depo_name ? ' / ' . $depo_name : '')); ?></p>
        <p style="margin:2px 0; font-size:11px;"><?php echo esc_html($date_label); ?></p>
        <p style="margin:2px 0; font-size:10px;">Rapor: <?php echo esc_html($report_time); ?></p>
    </div>

    <?php if (!$include_details):
        $net_kart = (float) $value($ozet, 'kart_toplam') - (float) $value($ozet, 'iade_kart');
        $net_iban = (float) $value($ozet, 'iban_toplam') - (float) $value($ozet, 'iade_iban');
        $net_qr = (float) $value($ozet, 'qr_taksit_toplam') - (float) $value($ozet, 'iade_qr_taksit');
        $net_nakit = (float) $value($ozet, 'nakit_toplam') - (float) $value($ozet, 'iade_nakit');
        $general_total = $include_qr
            ? (float) $value($ozet, 'toplam_ciro_kupon_haric', $value($ozet, 'toplam_ciro')) - (float) $value($ozet, 'toplam_iade_kupon_haric', $value($ozet, 'toplam_iade'))
            : $net_kart + $net_iban + $net_nakit;
        $total_expense = (float) $value($ozet, 'toplam_masraf');
        ?>
        <div style="margin-bottom:10px;">
            <table style="width:100%; font-size:14px; border-collapse:collapse; font-family:monospace;">
                <tr style="border-bottom:1px dashed #000;"><td style="padding:4px 0;">GENEL TOPLAM</td><td style="text-align:right; font-weight:bold;"><?php echo esc_html($money($general_total)); ?></td></tr>
                <tr><td style="padding:4px 0;">KART TOPLAM</td><td style="text-align:right;"><?php echo esc_html($money($net_kart)); ?></td></tr>
                <tr><td style="padding:4px 0;">IBAN TOPLAM</td><td style="text-align:right;"><?php echo esc_html($money($net_iban)); ?></td></tr>
                <?php if ($include_qr && $net_qr > 0): ?><tr><td style="padding:4px 0;">QR TAKSİT TOPLAM</td><td style="text-align:right;"><?php echo esc_html($money($net_qr)); ?></td></tr><?php endif; ?>
                <tr><td style="padding:4px 0;">NAKİT TOPLAM</td><td style="text-align:right;"><?php echo esc_html($money($net_nakit)); ?></td></tr>
                <?php if ($total_expense > 0): ?>
                    <tr><td colspan="2" style="height:10px;"></td></tr>
                    <?php if ($general_total < ($net_kart + $net_iban + $net_nakit) || (float) $value($ozet, 'toplam_iade') > 0): ?><tr><td style="padding:4px 0;">İADE EDİLEN TOPLAM</td><td style="text-align:right;">-<?php echo esc_html($money($value($ozet, 'toplam_iade'))); ?></td></tr><?php endif; ?>
                    <?php foreach (['kart_masraf' => 'KART MASRAF', 'iban_masraf' => 'IBAN MASRAF', 'nakit_masraf' => 'NAKİT MASRAF'] as $expense_key => $expense_label): ?>
                        <?php if ((float) $value($ozet, $expense_key) > 0): ?><tr><td style="padding:4px 0;"><?php echo esc_html($expense_label); ?></td><td style="text-align:right;">-<?php echo esc_html($money($value($ozet, $expense_key))); ?></td></tr><?php endif; ?>
                    <?php endforeach; ?>
                    <tr><td colspan="2" style="height:10px;"></td></tr><tr><td colspan="2" style="border-top:1px dashed #000;"></td></tr>
                    <?php foreach (['kart_masraf' => ['NET KART', $net_kart], 'iban_masraf' => ['NET IBAN', $net_iban], 'nakit_masraf' => ['NET NAKİT', $net_nakit]] as $expense_key => $net_item): ?>
                        <?php if ((float) $value($ozet, $expense_key) > 0): ?><tr style="font-size:14px;"><td style="padding:6px 0; font-weight:bold;"><?php echo esc_html($net_item[0]); ?></td><td style="text-align:right; font-weight:bold;"><?php echo esc_html($money($net_item[1] - (float) $value($ozet, $expense_key))); ?></td></tr><?php endif; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </table>
            <div style="height:120px;"></div>
        </div>
    <?php elseif ((int) ($rapor['siparis_sayisi'] ?? 0) === 0 && (float) $value($ozet, 'toplam_iade') <= 0 && (float) $value($ozet, 'toplam_masraf') <= 0): ?>
        <p style="text-align:center; font-size:14px; margin:20px 0;">Bugün işlem yapılmamıştır.</p>
    <?php else:
        $cash_sales = (float) $value($ozet, 'nakit_toplam');
        $card_sales = (float) $value($ozet, 'kart_toplam');
        $iban_sales = (float) $value($ozet, 'iban_toplam');
        $cash_refunds = (float) $value($ozet, 'iade_nakit');
        $card_refunds = (float) $value($ozet, 'iade_kart');
        $iban_refunds = (float) $value($ozet, 'iade_iban');
        $base_total = $include_qr ? (float) $value($ozet, 'toplam_ciro_kupon_haric', $value($ozet, 'toplam_ciro')) : $cash_sales + $card_sales + $iban_sales;
        $base_refund = $include_qr ? (float) $value($ozet, 'toplam_iade_kupon_haric', $value($ozet, 'toplam_iade')) : $cash_refunds + $card_refunds + $iban_refunds;
        $net_total = $base_total - $base_refund;
        ?>
        <div style="margin-bottom:8px;"><p style="font-weight:bold; margin:0 0 4px; font-size:12px; border-bottom:1px solid #000;">GENEL ÖZET</p><table style="width:100%; font-size:12px; border-collapse:collapse;">
            <tr><td>Sipariş Sayısı</td><td style="text-align:right; font-weight:bold;"><?php echo esc_html($rapor['siparis_sayisi'] ?? 0); ?></td></tr>
            <tr><td>Satılan Ürün</td><td style="text-align:right; font-weight:bold;"><?php echo esc_html($value($ozet, 'urun_adet_toplam')); ?></td></tr>
            <tr><td>İade Tutarı</td><td style="text-align:right; font-weight:bold;"><?php echo esc_html($money($value($ozet, 'toplam_iade'))); ?></td></tr>
            <tr><td>İskonto</td><td style="text-align:right; font-weight:bold;">-<?php echo esc_html($money($value($ozet, 'toplam_iskonto'))); ?></td></tr>
        </table></div>
        <div style="margin-bottom:8px;"><p style="font-weight:bold; margin:0 0 4px; font-size:12px; border-bottom:1px solid #000;">ÖDEME DAĞILIMI</p><table style="width:100%; font-size:12px; border-collapse:collapse;">
            <tr><td>Kredi Kartı</td><td style="text-align:right;"><?php echo esc_html($money($card_sales)); ?></td></tr>
            <tr><td>IBAN / Havale</td><td style="text-align:right;"><?php echo esc_html($money($iban_sales)); ?></td></tr>
            <?php if ($include_qr && (float) $value($ozet, 'qr_taksit_toplam') > 0): ?><tr><td>QR Taksit</td><td style="text-align:right;"><?php echo esc_html($money($value($ozet, 'qr_taksit_toplam'))); ?></td></tr><?php endif; ?>
            <tr><td>Nakit Satış</td><td style="text-align:right;"><?php echo esc_html($money($cash_sales)); ?></td></tr>
            <tr style="border-top:1px dashed #000;"><td style="font-weight:bold; font-size:14px; padding-top:2px;">TOPLAM CİRO</td><td style="text-align:right; font-weight:bold; font-size:14px; padding-top:2px;"><?php echo esc_html($money($base_total)); ?></td></tr>
        </table></div>
        <?php
        $total_expense = $base_refund + ($is_general ? (float) $value($ozet, 'toplam_masraf') : 0);
        if ($total_expense > 0): ?>
            <div style="margin-bottom:8px;"><p style="font-weight:bold; margin:0 0 4px; font-size:12px; border-bottom:1px solid #000;">GİDERLER</p><table style="width:100%; font-size:12px; border-collapse:collapse;">
                <?php if ($base_refund > 0): ?><tr><td style="font-weight:bold;">İADE EDİLEN TOPLAM</td><td style="text-align:right; font-weight:bold;">-<?php echo esc_html($money($base_refund)); ?></td></tr><?php endif; ?>
                <?php foreach (['iade_kart' => 'Kart İade', 'iade_iban' => 'IBAN İade', 'iade_nakit' => 'Nakit İade', 'iade_qr_taksit' => 'QR Taksit İade', 'iade_kupon' => 'Kupon İade'] as $refund_key => $refund_label): ?><?php if ($include_qr || $refund_key !== 'iade_qr_taksit'): ?><?php if ((float) $value($ozet, $refund_key) > 0): ?><tr><td><?php echo esc_html($refund_label); ?></td><td style="text-align:right;">-<?php echo esc_html($money($value($ozet, $refund_key))); ?></td></tr><?php endif; ?><?php endif; ?><?php endforeach; ?>
                <?php if ($is_general): ?><?php foreach (['kart_masraf' => 'Kart Masraf', 'iban_masraf' => 'IBAN Masraf', 'nakit_masraf' => 'Nakit Masraf'] as $expense_key => $expense_label): ?><?php if ((float) $value($ozet, $expense_key) > 0): ?><tr><td><?php echo esc_html($expense_label); ?></td><td style="text-align:right;">-<?php echo esc_html($money($value($ozet, $expense_key))); ?></td></tr><?php endif; ?><?php endforeach; ?><?php endif; ?>
                <tr style="border-top:1px dashed #000;"><td style="font-weight:bold; font-size:13px; padding-top:2px;">TOPLAM GİDER</td><td style="text-align:right; font-weight:bold; font-size:13px; padding-top:2px;">-<?php echo esc_html($money($total_expense)); ?></td></tr>
            </table></div>
        <?php endif; ?>
        <div style="margin-bottom:8px;"><p style="font-weight:bold; font-size:13px; text-align:center; margin:0 0 5px;">--- NET KASA DURUMU ---</p><table style="width:100%; font-size:12px; border-collapse:collapse;">
            <tr style="border-bottom:1px dashed #eee;"><td>Net Kart Toplamı</td><td style="text-align:right; font-weight:bold;"><?php echo esc_html($money($value($ozet, 'net_kart'))); ?></td></tr>
            <tr style="border-bottom:1px dashed #eee;"><td>Net IBAN Toplamı</td><td style="text-align:right; font-weight:bold;"><?php echo esc_html($money($value($ozet, 'net_iban'))); ?></td></tr>
            <tr style="border-bottom:1px dashed #eee;"><td>Net Nakit Toplamı</td><td style="text-align:right; font-weight:bold;"><?php echo esc_html($money($value($ozet, 'net_nakit'))); ?></td></tr>
            <?php if ($include_qr && (float) $value($ozet, 'net_qr_taksit') > 0): ?><tr style="border-bottom:1px dashed #eee;"><td>Net QR Taksit Toplamı</td><td style="text-align:right; font-weight:bold;"><?php echo esc_html($money($value($ozet, 'net_qr_taksit'))); ?></td></tr><?php endif; ?>
            <tr style="border-top:1px solid #000;"><td style="font-weight:bold; font-size:14px; padding-top:4px;">NET KASA TOPLAMI</td><td style="text-align:right; font-weight:bold; font-size:14px; padding-top:4px;"><?php echo esc_html($money($net_total - ($is_general ? (float) $value($ozet, 'toplam_masraf') : 0))); ?></td></tr>
        </table></div>
        <?php if (!empty($rapor['urun_dagilimi'])): ?><div style="margin-bottom:8px;"><p style="font-weight:bold; margin:0 0 4px; font-size:12px; border-bottom:1px solid #000;">ÜRÜN DAĞILIMI</p><table style="width:100%; font-size:11px; border-collapse:collapse;"><tr style="border-bottom:1px solid #000;"><th style="text-align:left;">Ürün</th><th style="text-align:right;">Ad.</th><th style="text-align:right;">Tutar</th></tr><?php foreach ($rapor['urun_dagilimi'] as $item): ?><tr><td style="padding:1px 0; max-width:140px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;"><?php echo esc_html($item['name'] ?? ''); ?></td><td style="text-align:right; padding:1px 0;"><?php echo esc_html($item['qty'] ?? 0); ?></td><td style="text-align:right; padding:1px 0;"><?php echo esc_html($money($item['total'] ?? 0)); ?></td></tr><?php endforeach; ?></table></div><?php endif; ?>
        <?php if (!empty($rapor['siparisler'])): ?><div style="margin-bottom:8px;"><p style="font-weight:bold; margin:0 0 4px; font-size:12px; border-bottom:1px solid #000;">SİPARİŞLER (<?php echo esc_html($rapor['siparis_sayisi'] ?? 0); ?>)</p><table style="width:100%; font-size:11px; border-collapse:collapse;"><tr style="border-bottom:1px solid #000;"><th style="text-align:left;">Saat</th><th style="text-align:left;">No</th><th style="text-align:left;">Ödm.</th><th style="text-align:right;">Tutar</th></tr><?php foreach ($rapor['siparisler'] as $item): ?><tr><td style="padding:1px 0;"><?php echo esc_html($item['saat'] ?? ''); ?></td><td style="padding:1px 0;">#<?php echo esc_html($item['id'] ?? ''); ?></td><td style="padding:1px 0; max-width:50px; overflow:hidden;"><?php echo esc_html(substr($item['odeme_tipi'] ?? '', 0, 6)); ?></td><td style="text-align:right; padding:1px 0;"><?php echo esc_html($money($item['toplam'] ?? 0)); ?></td></tr><?php endforeach; ?></table></div><?php endif; ?>
        <?php if (!empty($rapor['iade_siparisler'])): ?><div style="margin-bottom:8px;"><p style="font-weight:bold; margin:0 0 4px; font-size:12px; border-bottom:1px solid #000;">İADE İŞLEMLERİ (<?php echo esc_html($value($ozet, 'iade_adet')); ?>)</p><table style="width:100%; font-size:11px; border-collapse:collapse;"><tr style="border-bottom:1px solid #000;"><th style="text-align:left;">Saat</th><th style="text-align:left;">No</th><th style="text-align:left;">Ödm.</th><th style="text-align:right;">Tutar</th></tr><?php foreach ($rapor['iade_siparisler'] as $item): ?><tr><td style="padding:1px 0;"><?php echo esc_html($item['saat'] ?? ''); ?></td><td style="padding:1px 0;">#<?php echo esc_html($item['id'] ?? ''); ?></td><td style="padding:1px 0; max-width:50px; overflow:hidden;"><?php echo esc_html(substr($item['odeme_tipi'] ?? '', 0, 6)); ?></td><td style="text-align:right; padding:1px 0;">-<?php echo esc_html($money($item['toplam'] ?? 0)); ?></td></tr><?php endforeach; ?></table></div><?php endif; ?>
        <?php if ($is_general && !empty($rapor['masraf_detay'])): ?><div style="margin-bottom:8px;"><p style="font-weight:bold; margin:0 0 4px; font-size:12px; border-bottom:1px solid #000;">MASRAFLAR (<?php echo count($rapor['masraf_detay']); ?>)</p><table style="width:100%; font-size:11px; border-collapse:collapse;"><tr style="border-bottom:1px solid #000;"><th style="text-align:left;">Kategori</th><th style="text-align:right;">Tutar</th></tr><?php foreach ($rapor['masraf_detay'] as $item): ?><tr><td style="padding:1px 0; max-width:140px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;"><?php echo esc_html($item['kategori'] ?? ''); ?></td><td style="text-align:right; padding:1px 0;">-<?php echo esc_html($money($item['tutar'] ?? 0)); ?></td></tr><?php endforeach; ?></table></div><?php endif; ?>
        <?php if (!empty($rapor['kasiyerler'])): ?><div style="margin-bottom:8px;"><p style="font-weight:bold; margin:0 0 4px; font-size:12px; border-bottom:1px solid #000;">KASİYERLER</p><table style="width:100%; font-size:11px; border-collapse:collapse;"><?php foreach ($rapor['kasiyerler'] as $cashier => $count): ?><tr><td><?php echo esc_html($cashier); ?></td><td style="text-align:right;"><?php echo esc_html($count); ?> sipariş</td></tr><?php endforeach; ?></table></div><?php endif; ?>
        <div style="text-align:center; margin-top:10px; padding-top:8px; border-top:1px solid #000; font-size:10px;"><p style="margin:0;">Bu rapor Hızlı Kasa POS<br>sistemi tarafından üretilmiştir.</p></div>
        <div style="height:120px;"></div>
    <?php endif; ?>
</div>
