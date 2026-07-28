<?php
if (!defined('ABSPATH')) exit;
require_once __DIR__ . '/base-template.php';

$header = $data['header'] ?? [];
$extra  = $data['extra_data'] ?? [];
$ozet   = $extra['ozet'] ?? [];
?>
<div class="hk-unified-print-container receipt-zreport" style="font-family: 'Courier New', Courier, monospace; color:#000; width:100%; max-width:300px; margin:0 auto; padding:10px; box-sizing:border-box; font-size:12px; line-height:1.3;">
    <div style="text-align:center; margin-bottom:10px; border-bottom:2px solid #000; padding-bottom:6px;">
        <h3 style="margin:0; font-size:16px; font-weight:bold; color:#000;"><?php echo esc_html($header['store_name'] ?? ''); ?></h3>
        <p style="margin:4px 0 0 0; font-size:13px; font-weight:bold; color:#000;">GÜN SONU Z-RAPORU</p>
        <p style="margin:2px 0 0 0; font-size:11px; color:#000;">Kasa: <?php echo esc_html($header['kasa_no'] ?? '1'); ?> | Tarih: <?php echo esc_html($header['tarih'] ?? ''); ?></p>
        <p style="margin:2px 0 0 0; font-size:10px; color:#000;">Rapor Zamanı: <?php echo esc_html($header['report_time'] ?? ''); ?></p>
    </div>

    <!-- General Summary -->
    <div style="margin-bottom:8px;">
        <p style="font-weight:bold; margin:0 0 4px; font-size:12px; border-bottom:1px solid #000; color:#000;">GENEL ÖZET</p>
        <table style="width:100%; font-size:12px; border-collapse:collapse;">
            <tr><td style="color:#000;">Sipariş Sayısı</td><td style="text-align:right; font-weight:bold; color:#000;"><?php echo esc_html($extra['siparis_sayisi'] ?? 0); ?></td></tr>
            <tr><td style="color:#000;">Satılan Ürün Adet</td><td style="text-align:right; font-weight:bold; color:#000;"><?php echo esc_html($ozet['urun_adet_toplam'] ?? 0); ?></td></tr>
            <tr><td style="color:#000;">Toplam İade Tutarı</td><td style="text-align:right; font-weight:bold; color:#000;"><?php echo hk_format_price($ozet['toplam_iade'] ?? 0); ?></td></tr>
            <tr><td style="color:#000;">Toplam İskonto</td><td style="text-align:right; font-weight:bold; color:#000;">-<?php echo hk_format_price($ozet['toplam_iskonto'] ?? 0); ?></td></tr>
        </table>
    </div>

    <!-- Payment Breakdown -->
    <div style="margin-bottom:8px;">
        <p style="font-weight:bold; margin:0 0 4px; font-size:12px; border-bottom:1px solid #000; color:#000;">ÖDEME DAĞILIMI</p>
        <table style="width:100%; font-size:12px; border-collapse:collapse;">
            <tr><td style="color:#000;">Kredi Kartı</td><td style="text-align:right; color:#000;"><?php echo hk_format_price($ozet['kart_toplam'] ?? 0); ?></td></tr>
            <tr><td style="color:#000;">IBAN / Havale</td><td style="text-align:right; color:#000;"><?php echo hk_format_price($ozet['iban_toplam'] ?? 0); ?></td></tr>
            <?php if (($ozet['qr_taksit_toplam'] ?? 0) > 0): ?>
                <tr><td style="color:#000;">QR Taksit</td><td style="text-align:right; color:#000;"><?php echo hk_format_price($ozet['qr_taksit_toplam']); ?></td></tr>
            <?php endif; ?>
            <tr><td style="color:#000;">Nakit Satış</td><td style="text-align:right; color:#000;"><?php echo hk_format_price($ozet['nakit_toplam'] ?? 0); ?></td></tr>
            <tr style="border-top:1px solid #000; font-weight:bold;">
                <td style="color:#000;">TOPLAM CİRO</td>
                <td style="text-align:right; color:#000;"><?php echo hk_format_price($ozet['toplam_ciro'] ?? 0); ?></td>
            </tr>
        </table>
    </div>

    <!-- Expense Breakdown -->
    <?php if (($ozet['toplam_masraf'] ?? 0) > 0): ?>
        <div style="margin-bottom:8px;">
            <p style="font-weight:bold; margin:0 0 4px; font-size:12px; border-bottom:1px solid #000; color:#000;">KASA MASRAFLARI</p>
            <table style="width:100%; font-size:12px; border-collapse:collapse;">
                <tr><td style="color:#000;">Toplam Masraf</td><td style="text-align:right; font-weight:bold; color:#000;">-<?php echo hk_format_price($ozet['toplam_masraf']); ?></td></tr>
            </table>
        </div>
    <?php endif; ?>

    <!-- Net Cash Balance -->
    <div style="border-top:2px solid #000; padding-top:6px; margin-top:10px;">
        <table style="width:100%; font-size:13px; font-weight:bold; border-collapse:collapse;">
            <tr><td style="color:#000;">NET NAKİT KASA:</td><td style="text-align:right; color:#000;"><?php echo hk_format_price(($ozet['nakit_toplam'] ?? 0) - ($ozet['iade_nakit'] ?? 0) - ($ozet['nakit_masraf'] ?? 0)); ?></td></tr>
            <tr><td style="color:#000;">NET KREDİ KARTI:</td><td style="text-align:right; color:#000;"><?php echo hk_format_price(($ozet['kart_toplam'] ?? 0) - ($ozet['iade_kart'] ?? 0)); ?></td></tr>
            <tr><td style="color:#000;">NET IBAN:</td><td style="text-align:right; color:#000;"><?php echo hk_format_price(($ozet['iban_toplam'] ?? 0) - ($ozet['iade_iban'] ?? 0)); ?></td></tr>
        </table>
    </div>
</div>
