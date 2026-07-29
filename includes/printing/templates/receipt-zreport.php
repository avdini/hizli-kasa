<?php
if (!defined('ABSPATH')) exit;
require_once __DIR__ . '/base-template.php';

$header = $data['header'] ?? [];
$rapor = $data['extra_data'] ?? [];
$ozet = $rapor['ozet'] ?? [];
$options = $rapor['print_options'] ?? [];
$include_qr = !empty($options['include_qr']);

$format = $options['format'] ?? ($options['include_details'] ?? 'ozet');
if ($format === '1' || $format === 'true' || $format === true) {
    $format = 'detayli';
} elseif ($format === '0' || $format === 'false' || $format === false) {
    $format = 'ozet';
}

$money = static function ($value) {
    return hk_format_price($value);
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
<div class="hk-unified-print-container receipt-zreport" style="font-family:'Courier New', 'Consolas', 'Lucida Console', 'Monaco', monospace; color:#000000 !important; background-color:#ffffff !important; width:100%; max-width:300px; margin:0 auto; padding:4px 8px; box-sizing:border-box; font-size:12px; line-height:1.25; letter-spacing:-0.2px; box-shadow:none !important; border:none !important; -webkit-font-smoothing:none !important; -moz-osx-font-smoothing:unset !important; font-smooth:never !important; text-rendering:pixelated !important;">
    <div style="text-align:center; margin-bottom:8px; border-bottom:2px solid #000000; padding-bottom:8px;">
        <h2 style="margin:0; font-size:18px; font-weight:bold; color:#000000; font-family:'Courier New', 'Consolas', 'Lucida Console', 'Monaco', monospace;"><?php echo esc_html($header['store_name'] ?? get_bloginfo('name')); ?></h2>
        <p style="margin:4px 0 2px 0; font-size:13px; font-weight:bold; color:#000000; font-family:'Courier New', 'Consolas', 'Lucida Console', 'Monaco', monospace;">GÜN SONU RAPORU</p>
        <p style="margin:0; font-size:11px; color:#000000; font-family:'Courier New', 'Consolas', 'Lucida Console', 'Monaco', monospace;">Kasa: <?php echo esc_html($kasa_no . ($depo_name ? ' / ' . $depo_name : '')); ?></p>
        <p style="margin:2px 0 0 0; font-size:11px; color:#000000; font-family:'Courier New', 'Consolas', 'Lucida Console', 'Monaco', monospace;"><?php echo esc_html(!empty($report_time) ? $report_time : $date_label); ?></p>
    </div>
    <?php if ($format === 'basit'):
        $gross_kart  = (float) $value($ozet, 'kart_toplam') - (float) $value($ozet, 'iade_kart');
        $gross_iban  = (float) $value($ozet, 'iban_toplam') - (float) $value($ozet, 'iade_iban');
        $gross_qr    = (float) $value($ozet, 'qr_taksit_toplam') - (float) $value($ozet, 'iade_qr_taksit');
        $gross_nakit = (float) $value($ozet, 'nakit_toplam') - (float) $value($ozet, 'iade_nakit');

        $nakit_masraf = (float) $value($ozet, 'nakit_masraf');
        $kart_masraf  = (float) $value($ozet, 'kart_masraf');
        $iban_masraf  = (float) $value($ozet, 'iban_masraf');
        $total_masraf = $nakit_masraf + $kart_masraf + $iban_masraf;

        $net_kart  = $gross_kart - $kart_masraf;
        $net_iban  = $gross_iban - $iban_masraf;
        $net_qr    = $gross_qr;
        $net_nakit = $gross_nakit - $nakit_masraf;

        $general_total  = $gross_kart + $gross_iban + $gross_nakit + ($include_qr ? $gross_qr : 0);
        $net_kasa_total = $general_total - $total_masraf;
        ?>
        <div style="margin-bottom:8px;">
            <table style="width:100%; font-size:13px; border-collapse:collapse; color:#000000; font-family:'Courier New', 'Consolas', 'Lucida Console', 'Monaco', monospace; table-layout:fixed;">
                <tr style="border-bottom:2px solid #000000;">
                    <td style="padding:4px 0; font-weight:bold;">GENEL TOPLAM</td>
                    <td style="text-align:right; font-weight:bold;"><?php echo esc_html($money($general_total)); ?></td>
                </tr>
                <?php if ($gross_kart > 0 || $net_kart > 0): ?>
                    <tr><td style="padding:3px 0;">KART TOPLAM</td><td style="text-align:right;"><?php echo esc_html($money($gross_kart)); ?></td></tr>
                <?php endif; ?>
                <?php if ($gross_iban > 0 || $net_iban > 0): ?>
                    <tr><td style="padding:3px 0;">IBAN TOPLAM</td><td style="text-align:right;"><?php echo esc_html($money($gross_iban)); ?></td></tr>
                <?php endif; ?>
                <?php if ($include_qr && ($gross_qr > 0 || $net_qr > 0)): ?>
                    <tr><td style="padding:3px 0;">QR TAKSİT TOPLAM</td><td style="text-align:right;"><?php echo esc_html($money($gross_qr)); ?></td></tr>
                <?php endif; ?>
                <?php if ($gross_nakit > 0 || $net_nakit > 0): ?>
                    <tr><td style="padding:3px 0;">NAKİT TOPLAM</td><td style="text-align:right;"><?php echo esc_html($money($gross_nakit)); ?></td></tr>
                <?php endif; ?>

                <?php if ($total_masraf > 0): ?>
                    <tr><td colspan="2" style="height:6px; border-bottom:1px solid #000000;"></td></tr>
                    <?php if ($kart_masraf > 0): ?>
                        <tr><td style="padding:3px 0;">KART MASRAF</td><td style="text-align:right;">-<?php echo esc_html($money($kart_masraf)); ?></td></tr>
                    <?php endif; ?>
                    <?php if ($iban_masraf > 0): ?>
                        <tr><td style="padding:3px 0;">IBAN MASRAF</td><td style="text-align:right;">-<?php echo esc_html($money($iban_masraf)); ?></td></tr>
                    <?php endif; ?>
                    <?php if ($nakit_masraf > 0): ?>
                        <tr><td style="padding:3px 0;">NAKİT MASRAF</td><td style="text-align:right;">-<?php echo esc_html($money($nakit_masraf)); ?></td></tr>
                    <?php endif; ?>
                    <tr><td colspan="2" style="height:6px; border-bottom:1px solid #000000;"></td></tr>
                    <?php if ($kart_masraf > 0): ?>
                        <tr><td style="padding:3px 0; font-weight:bold;">NET KART</td><td style="text-align:right; font-weight:bold;"><?php echo esc_html($money($net_kart)); ?></td></tr>
                    <?php endif; ?>
                    <?php if ($iban_masraf > 0): ?>
                        <tr><td style="padding:3px 0; font-weight:bold;">NET IBAN</td><td style="text-align:right; font-weight:bold;"><?php echo esc_html($money($net_iban)); ?></td></tr>
                    <?php endif; ?>
                    <?php if ($nakit_masraf > 0): ?>
                        <tr><td style="padding:3px 0; font-weight:bold;">NET NAKİT</td><td style="text-align:right; font-weight:bold;"><?php echo esc_html($money($net_nakit)); ?></td></tr>
                    <?php endif; ?>
                <?php endif; ?>
            </table>
        </div>
    <?php elseif ((int) ($rapor['siparis_sayisi'] ?? 0) === 0 && (float) $value($ozet, 'toplam_iade') <= 0 && (float) $value($ozet, 'toplam_masraf') <= 0): ?>
        <p style="text-align:center; font-size:13px; margin:15px 0; color:#000000; font-family:'Courier New', 'Consolas', 'Lucida Console', 'Monaco', monospace;">Bugün işlem yapılmamıştır.</p>
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
        <!-- Standart Özet Bölümü (Yazdır & Detaylı Yazdır İçin Ortak) -->
        <div style="margin-bottom:8px;"><p style="font-weight:bold; margin:0 0 4px; font-size:12px; border-bottom:1px solid #000000; padding-bottom:2px; color:#000000; font-family:'Courier New', 'Consolas', 'Lucida Console', 'Monaco', monospace;">GENEL ÖZET</p><table style="width:100%; font-size:12px; border-collapse:collapse; color:#000000; font-family:'Courier New', 'Consolas', 'Lucida Console', 'Monaco', monospace; table-layout:fixed;">
            <tr><td>Sipariş Sayısı</td><td style="text-align:right; font-weight:bold;"><?php echo esc_html($rapor['siparis_sayisi'] ?? 0); ?></td></tr>
            <tr><td>Satılan Ürün</td><td style="text-align:right; font-weight:bold;"><?php echo esc_html($value($ozet, 'urun_adet_toplam')); ?></td></tr>
            <tr><td>İade Tutarı</td><td style="text-align:right; font-weight:bold;"><?php echo esc_html($money($value($ozet, 'toplam_iade'))); ?></td></tr>
            <tr><td>İskonto</td><td style="text-align:right; font-weight:bold;">-<?php echo esc_html($money($value($ozet, 'toplam_iskonto'))); ?></td></tr>
        </table></div>
        <div style="margin-bottom:8px;"><p style="font-weight:bold; margin:0 0 4px; font-size:12px; border-bottom:1px solid #000000; padding-bottom:2px; color:#000000; font-family:'Courier New', 'Consolas', 'Lucida Console', 'Monaco', monospace;">ÖDEME DAĞILIMI</p><table style="width:100%; font-size:12px; border-collapse:collapse; color:#000000; font-family:'Courier New', 'Consolas', 'Lucida Console', 'Monaco', monospace; table-layout:fixed;">
            <tr><td>Kredi Kartı</td><td style="text-align:right;"><?php echo esc_html($money($card_sales)); ?></td></tr>
            <tr><td>IBAN / Havale</td><td style="text-align:right;"><?php echo esc_html($money($iban_sales)); ?></td></tr>
            <?php if ($include_qr && (float) $value($ozet, 'qr_taksit_toplam') > 0): ?><tr><td>QR Taksit</td><td style="text-align:right;"><?php echo esc_html($money($value($ozet, 'qr_taksit_toplam'))); ?></td></tr><?php endif; ?>
            <tr><td>Nakit Satış</td><td style="text-align:right;"><?php echo esc_html($money($cash_sales)); ?></td></tr>
            <tr style="border-top:1px solid #000000;"><td style="font-weight:bold; font-size:13px; padding-top:3px;">TOPLAM CİRO</td><td style="text-align:right; font-weight:bold; font-size:13px; padding-top:3px;"><?php echo esc_html($money($base_total)); ?></td></tr>
        </table></div>
        <?php
        $total_masraf = (float) $value($ozet, 'toplam_masraf');
        if ($total_masraf > 0): ?>
            <div style="margin-bottom:8px;"><p style="font-weight:bold; margin:0 0 4px; font-size:12px; border-bottom:1px solid #000000; padding-bottom:2px; color:#000000; font-family:'Courier New', 'Consolas', 'Lucida Console', 'Monaco', monospace;">KASA MASRAFLARI</p><table style="width:100%; font-size:12px; border-collapse:collapse; color:#000000; font-family:'Courier New', 'Consolas', 'Lucida Console', 'Monaco', monospace; table-layout:fixed;">
                <?php foreach (['nakit_masraf' => 'Nakit Masraf', 'kart_masraf' => 'Kart Masraf', 'iban_masraf' => 'IBAN Masraf'] as $expense_key => $expense_label): ?>
                    <?php if ((float) $value($ozet, $expense_key) > 0): ?><tr><td><?php echo esc_html($expense_label); ?></td><td style="text-align:right;">-<?php echo esc_html($money($value($ozet, $expense_key))); ?></td></tr><?php endif; ?>
                <?php endforeach; ?>
                <tr style="border-top:1px solid #000000;"><td style="font-weight:bold; font-size:12px; padding-top:2px;">TOPLAM MASRAF</td><td style="text-align:right; font-weight:bold; font-size:12px; padding-top:2px;">-<?php echo esc_html($money($total_masraf)); ?></td></tr>
            </table></div>
        <?php endif; ?>
        <div style="margin-bottom:8px;"><p style="font-weight:bold; font-size:12px; text-align:center; margin:0 0 4px; border-bottom:1px solid #000000; padding-bottom:2px; color:#000000; font-family:'Courier New', 'Consolas', 'Lucida Console', 'Monaco', monospace;">--- NET KASA DURUMU ---</p><table style="width:100%; font-size:12px; border-collapse:collapse; color:#000000; font-family:'Courier New', 'Consolas', 'Lucida Console', 'Monaco', monospace; table-layout:fixed;">
            <tr><td>Net Kart Toplamı</td><td style="text-align:right; font-weight:bold;"><?php echo esc_html($money($value($ozet, 'net_kart'))); ?></td></tr>
            <tr><td>Net IBAN Toplamı</td><td style="text-align:right; font-weight:bold;"><?php echo esc_html($money($value($ozet, 'net_iban'))); ?></td></tr>
            <tr><td>Net Nakit Toplamı</td><td style="text-align:right; font-weight:bold;"><?php echo esc_html($money($value($ozet, 'net_nakit'))); ?></td></tr>
            <?php if ($include_qr && (float) $value($ozet, 'net_qr_taksit') > 0): ?><tr><td>Net QR Taksit Toplamı</td><td style="text-align:right; font-weight:bold;"><?php echo esc_html($money($value($ozet, 'net_qr_taksit'))); ?></td></tr><?php endif; ?>
            <tr style="border-top:2px solid #000000;"><td style="font-weight:bold; font-size:13px; padding-top:3px;">NET KASA TOPLAMI</td><td style="text-align:right; font-weight:bold; font-size:13px; padding-top:3px;"><?php echo esc_html($money($net_total - ($is_general ? (float) $value($ozet, 'toplam_masraf') : 0))); ?></td></tr>
        </table></div>

        <!-- Ekstra Liste Detayları (Sadece Detaylı Yazdır Seçildiğinde) -->
        <?php if ($format === 'detayli'): ?>
            <?php if (!empty($rapor['urun_dagilimi'])): ?><div style="margin-bottom:8px;"><p style="font-weight:bold; margin:0 0 4px; font-size:12px; border-bottom:1px solid #000000; padding-bottom:2px; color:#000000; font-family:'Courier New', 'Consolas', 'Lucida Console', 'Monaco', monospace;">ÜRÜN DAĞILIMI</p><table style="width:100%; font-size:11px; border-collapse:collapse; color:#000000; font-family:'Courier New', 'Consolas', 'Lucida Console', 'Monaco', monospace; table-layout:fixed;"><tr><th style="text-align:left;">Ürün</th><th style="text-align:right; width:45px;">Ad.</th><th style="text-align:right; width:65px;">Tutar</th></tr><?php foreach ($rapor['urun_dagilimi'] as $item): ?><tr><td style="padding:1px 0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; word-break:break-all;"><?php echo esc_html($item['name'] ?? ''); ?></td><td style="text-align:right; padding:1px 0;"><?php echo esc_html($item['qty'] ?? 0); ?></td><td style="text-align:right; padding:1px 0;"><?php echo esc_html($money($item['total'] ?? 0)); ?></td></tr><?php endforeach; ?></table></div><?php endif; ?>
            <?php if (!empty($rapor['siparisler'])): ?><div style="margin-bottom:8px;"><p style="font-weight:bold; margin:0 0 4px; font-size:12px; border-bottom:1px solid #000000; padding-bottom:2px; color:#000000; font-family:'Courier New', 'Consolas', 'Lucida Console', 'Monaco', monospace;">SİPARİŞLER (<?php echo esc_html($rapor['siparis_sayisi'] ?? 0); ?>)</p><table style="width:100%; font-size:11px; border-collapse:collapse; color:#000000; font-family:'Courier New', 'Consolas', 'Lucida Console', 'Monaco', monospace; table-layout:fixed;"><tr><th style="text-align:left; width:45px;">Saat</th><th style="text-align:left; width:45px;">No</th><th style="text-align:left; width:55px;">Ödm.</th><th style="text-align:right;">Tutar</th></tr><?php foreach ($rapor['siparisler'] as $item): ?><tr><td style="padding:1px 0;"><?php echo esc_html($item['saat'] ?? ''); ?></td><td style="padding:1px 0;">#<?php echo esc_html($item['id'] ?? ''); ?></td><td style="padding:1px 0; overflow:hidden; word-break:break-all;"><?php echo esc_html(substr($item['odeme_tipi'] ?? '', 0, 6)); ?></td><td style="text-align:right; padding:1px 0;"><?php echo esc_html($money($item['toplam'] ?? 0)); ?></td></tr><?php endforeach; ?></table></div><?php endif; ?>
            <?php if (!empty($rapor['iade_siparisler'])): ?><div style="margin-bottom:8px;"><p style="font-weight:bold; margin:0 0 4px; font-size:12px; border-bottom:1px solid #000000; padding-bottom:2px; color:#000000; font-family:'Courier New', 'Consolas', 'Lucida Console', 'Monaco', monospace;">İADE İŞLEMLERİ (<?php echo esc_html($value($ozet, 'iade_adet')); ?>)</p><table style="width:100%; font-size:11px; border-collapse:collapse; color:#000000; font-family:'Courier New', 'Consolas', 'Lucida Console', 'Monaco', monospace; table-layout:fixed;"><tr><th style="text-align:left; width:45px;">Saat</th><th style="text-align:left; width:45px;">No</th><th style="text-align:left; width:55px;">Ödm.</th><th style="text-align:right;">Tutar</th></tr><?php foreach ($rapor['iade_siparisler'] as $item): ?><tr><td style="padding:1px 0;"><?php echo esc_html($item['saat'] ?? ''); ?></td><td style="padding:1px 0;">#<?php echo esc_html($item['id'] ?? ''); ?></td><td style="padding:1px 0; overflow:hidden; word-break:break-all;"><?php echo esc_html(substr($item['odeme_tipi'] ?? '', 0, 6)); ?></td><td style="text-align:right; padding:1px 0;">-<?php echo esc_html($money($item['toplam'] ?? 0)); ?></td></tr><?php endforeach; ?></table></div><?php endif; ?>
            <?php if ($is_general && !empty($rapor['masraf_detay'])): ?><div style="margin-bottom:8px;"><p style="font-weight:bold; margin:0 0 4px; font-size:12px; border-bottom:1px solid #000000; padding-bottom:2px; color:#000000; font-family:'Courier New', 'Consolas', 'Lucida Console', 'Monaco', monospace;">MASRAFLAR (<?php echo count($rapor['masraf_detay']); ?>)</p><table style="width:100%; font-size:11px; border-collapse:collapse; color:#000000; font-family:'Courier New', 'Consolas', 'Lucida Console', 'Monaco', monospace; table-layout:fixed;"><tr><th style="text-align:left;">Kategori</th><th style="text-align:right; width:75px;">Tutar</th></tr><?php foreach ($rapor['masraf_detay'] as $item): ?><tr><td style="padding:1px 0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; word-break:break-all;"><?php echo esc_html($item['kategori'] ?? ''); ?></td><td style="text-align:right; padding:1px 0;">-<?php echo esc_html($money($item['tutar'] ?? 0)); ?></td></tr><?php endforeach; ?></table></div><?php endif; ?>
            <?php if (!empty($rapor['kasiyerler'])): ?><div style="margin-bottom:8px;"><p style="font-weight:bold; margin:0 0 4px; font-size:12px; border-bottom:1px solid #000000; padding-bottom:2px; color:#000000; font-family:'Courier New', 'Consolas', 'Lucida Console', 'Monaco', monospace;">KASİYERLER</p><table style="width:100%; font-size:11px; border-collapse:collapse; color:#000000; font-family:'Courier New', 'Consolas', 'Lucida Console', 'Monaco', monospace; table-layout:fixed;"><?php foreach ($rapor['kasiyerler'] as $cashier => $count): ?><tr><td><?php echo esc_html($cashier); ?></td><td style="text-align:right;"><?php echo esc_html($count); ?> sipariş</td></tr><?php endforeach; ?></table></div><?php endif; ?>
        <?php endif; ?>

        <div style="text-align:center; margin-top:10px; border-top:1px solid #000000; padding-top:8px; font-size:10px; color:#000000; font-family:'Courier New', 'Consolas', 'Lucida Console', 'Monaco', monospace;"><p style="margin:0;">Bu rapor Hızlı Kasa POS sistemi tarafından üretilmiştir.</p></div>
    <?php endif; ?>
</div>
