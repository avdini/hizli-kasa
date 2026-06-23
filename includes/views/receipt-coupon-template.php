<?php
if (!defined('ABSPATH')) exit;
?>
<h2 style="margin:0; font-size:18px; font-weight:bold;"><?php echo esc_html(get_bloginfo('name')); ?></h2>
<p style="margin:4px 0; font-size:12px; font-weight:bold; text-transform:uppercase; letter-spacing:1px;">İADE ÇEKİ</p>
<p id="fis-coupon-tarih" style="margin:0; font-size:11px; color:#000;"><?php echo esc_html($post_date); ?></p>

<div style="border-top:1px solid #000; border-bottom:1px solid #000; margin:15px 0; padding:10px 0;">
    <div style="font-size:11px; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:3px;">Çek Tutarı</div>
    <div id="fis-coupon-tutar" style="font-weight:bold; font-size:24px;"><?php echo esc_html($amount); ?></div>
</div>

<div style="text-align:center; margin:15px auto 5px auto;">
    <img id="fis-coupon-barkod" style="width: 100%; max-width: 220px; height: auto; margin: 0 auto; display: block;" />
</div>
<p id="fis-coupon-kodu" style="font-weight:bold; margin:0 0 15px 0; font-size:14px; letter-spacing:0.5px;"><?php echo esc_html($coupon_code); ?></p>

<div style="border-top:1px dashed #000; padding-top:10px; font-size:11px; text-align:left;">
    <div style="display:flex; justify-content:space-between; margin-bottom:5px;">
        <span>Müşteri Tel:</span>
        <span id="fis-coupon-telefon" style="font-weight:bold;"><?php echo esc_html($saved_phone); ?></span>
    </div>
</div>

<div style="text-align:center; margin-top:15px; font-size:9.5px; border-top:1px dashed #000; padding-top:10px; line-height:1.4; color:#000;">
    Bu iade çeki tek seferliktir. Kasada veya web sitemizde okutarak kullanabilirsiniz.
</div>
