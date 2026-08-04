<?php
if (!defined('ABSPATH')) {
    exit;
}
require_once __DIR__ . '/base-template.php';

$site_name     = esc_html($payload['site_name'] ?? get_bloginfo('name'));
$sevk          = $payload['sevk'];
$kalemler      = $payload['kalemler'] ?? [];
$toplam_cesit  = intval($payload['toplam_cesit'] ?? 0);
$toplam_adet   = floatval($payload['toplam_adet'] ?? 0);
$tarih         = esc_html($payload['tarih'] ?? '');
$sevk_no       = esc_html($sevk->sevk_no ?? '');
$kaynak_depo   = esc_html($sevk->kaynak_depo_adi ?? '-');
$hedef_depo    = esc_html($sevk->hedef_depo_adi ?? '-');
$durum_label   = esc_html(strtoupper($sevk->durum ?? ''));
$not_gonderici = !empty($sevk->not_gonderici) ? esc_html($sevk->not_gonderici) : '';
?>
<div class="hk-unified-print-container receipt-shipment" style="color:#000000 !important; background:#ffffff !important; width:100%; max-width:300px; margin:0 auto; padding:4px 0; box-sizing:border-box; font-family:'Courier New', Courier, monospace; font-size:12px; line-height:1.2; box-shadow:none !important; border:none !important; page-break-inside:avoid !important;">
    <!-- Store Header -->
    <div style="text-align:center; margin-bottom:8px; border-bottom:1px solid #000000; padding-bottom:8px;">
        <h2 style="margin:0; font-size:16px; font-weight:bold; color:#000000; font-family:'Courier New', Courier, monospace; text-transform:uppercase;"><?php echo $site_name; ?></h2>
        <p style="margin:4px 0 2px 0; font-size:13px; font-weight:bold; color:#000000; font-family:'Courier New', Courier, monospace;">SEVK TRANSFER FİŞİ</p>
        <p style="margin:0; font-size:11px; color:#000000; font-family:'Courier New', Courier, monospace;"><?php echo $tarih; ?></p>
        <p style="margin:4px 0 0 0; font-weight:bold; font-size:14px; color:#000000; font-family:'Courier New', Courier, monospace;">SEVK NO: <?php echo $sevk_no; ?></p>
        
        <?php if (!empty($sevk_no)): ?>
            <div style="text-align:center; margin-top:4px;">
                <?php echo hk_render_barcode_svg($sevk_no); ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Meta Info -->
    <div style="font-size:11px; margin-bottom:8px; line-height:1.4; font-family:'Courier New', Courier, monospace;">
        <div style="display:flex; justify-content:space-between; color:#000000;">
            <span>Çıkış Deposu:</span>
            <span style="font-weight:bold;"><?php echo $kaynak_depo; ?></span>
        </div>
        <div style="display:flex; justify-content:space-between; color:#000000;">
            <span>Varış Deposu:</span>
            <span style="font-weight:bold;"><?php echo $hedef_depo; ?></span>
        </div>
        <div style="display:flex; justify-content:space-between; color:#000000;">
            <span>Durum:</span>
            <span><?php echo $durum_label; ?></span>
        </div>
    </div>

    <!-- Items Header -->
    <div style="border-top:1px solid #000000; border-bottom:1px solid #000000; padding:4px 0; margin-bottom:6px; font-weight:bold; font-size:11px; font-family:'Courier New', Courier, monospace;">
        ÜRÜN LİSTESİ
    </div>

    <!-- Item Rows -->
    <div style="margin-bottom:8px;">
        <?php foreach ($kalemler as $item): ?>
            <div style="margin-bottom:6px; page-break-inside:avoid;">
                <div style="display:flex; justify-content:space-between; align-items:flex-start; font-size:12px; font-weight:bold; color:#000000; font-family:'Courier New', Courier, monospace;">
                    <span style="flex:1; padding-right:4px;">[  ] <?php echo esc_html($item['urun_adi']); ?></span>
                    <span style="white-space:nowrap;">x <?php echo $item['gonderilen_adet']; ?> ADET</span>
                </div>
                <div style="font-size:10px; color:#000000; padding-left:18px; font-family:'Courier New', Courier, monospace;">SKU: <?php echo esc_html($item['sku']); ?></div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Totals -->
    <div style="border-top:1px dashed #000000; padding-top:6px; font-size:12px; font-weight:bold; display:flex; justify-content:space-between; color:#000000; font-family:'Courier New', Courier, monospace;">
        <span>TOPLAM:</span>
        <span><?php echo $toplam_cesit; ?> Çeşit / <?php echo $toplam_adet; ?> Adet</span>
    </div>

    <?php if (!empty($not_gonderici)): ?>
        <div style="border-top:1px dashed #000000; margin-top:6px; padding-top:4px; font-size:10px; color:#000000; font-family:'Courier New', Courier, monospace;">
            <strong>Gönderici Notu:</strong> <?php echo $not_gonderici; ?>
        </div>
    <?php endif; ?>

    <!-- Signature Boxes -->
    <div style="display:flex; justify-content:space-between; margin-top:16px; padding-top:8px; border-top:1px solid #000000; font-size:11px; font-family:'Courier New', Courier, monospace;">
        <div style="text-align:center; width:48%;">
            <div>Teslim Eden</div>
            <div style="margin-top:20px;">İmza: .........</div>
        </div>
        <div style="text-align:center; width:48%;">
            <div>Teslim Alan</div>
            <div style="margin-top:20px;">İmza: .........</div>
        </div>
    </div>
</div>
