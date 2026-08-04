<?php
if (!defined('ABSPATH')) {
    exit;
}

$site_name     = esc_html($payload['site_name'] ?? 'Hızlı Kasa');
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
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Sevk Fişi - <?php echo $sevk_no; ?></title>
    <style>
        @page {
            margin: 0;
            size: auto;
        }
        body {
            margin: 0;
            padding: 8px 6px;
            background: #ffffff !important;
            color: #000000 !important;
            font-family: Consolas, "Segoe UI Mono", "Courier New", monospace;
            font-size: 12px;
            line-height: 1.3;
            font-weight: 600;
            -webkit-print-color-adjust: exact;
        }
        .hk-shipment-receipt {
            width: 100% !important;
            max-width: 100% !important;
            box-sizing: border-box;
        }
        .text-center { text-align: center; }
        .bold { font-weight: bold; }
        
        .header-title {
            font-size: 15px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 2px;
        }
        .sub-header {
            font-size: 13px;
            font-weight: 700;
            border-bottom: 2px solid #000000;
            padding-bottom: 6px;
            margin-bottom: 8px;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            font-size: 11px;
            margin-bottom: 3px;
        }
        .divider-dashed {
            border-top: 1px dashed #000000;
            margin: 8px 0;
        }
        .divider-double {
            border-top: 2px solid #000000;
            margin: 8px 0;
        }
        
        .item-block {
            margin-bottom: 6px;
        }
        .item-main {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 4px;
        }
        .item-title {
            flex: 1;
            font-size: 12px;
            font-weight: 700;
            word-break: break-word;
        }
        .item-qty {
            font-size: 12px;
            font-weight: 800;
            white-space: nowrap;
        }
        .item-sku {
            font-size: 10px;
            padding-left: 20px;
            color: #000000;
        }
        .signature-grid {
            display: flex;
            justify-content: space-between;
            margin-top: 16px;
            padding-top: 8px;
            border-top: 1px solid #000000;
            font-size: 11px;
        }
        .signature-box {
            text-align: center;
            width: 48%;
        }
    </style>
</head>
<body>
    <div class="hk-shipment-receipt">
        <div class="text-center header-title"><?php echo $site_name; ?></div>
        <div class="text-center sub-header">SEVK TRANSFER FİŞİ</div>

        <div class="info-row"><span>Tarih:</span> <span><?php echo $tarih; ?></span></div>
        <div class="info-row"><span>Sevk No:</span> <span class="bold"><?php echo $sevk_no; ?></span></div>
        <div class="info-row"><span>Çıkış Deposu:</span> <span class="bold"><?php echo $kaynak_depo; ?></span></div>
        <div class="info-row"><span>Varış Deposu:</span> <span class="bold"><?php echo $hedef_depo; ?></span></div>
        <div class="info-row"><span>Durum:</span> <span><?php echo $durum_label; ?></span></div>

        <div class="divider-double"></div>

        <div style="font-size: 11px; font-weight: 800; margin-bottom: 6px;">ÜRÜN LİSTESİ</div>

        <?php foreach ($kalemler as $item): ?>
            <div class="item-block">
                <div class="item-main">
                    <span class="item-title">[  ] <?php echo esc_html($item['urun_adi']); ?></span>
                    <span class="item-qty">x <?php echo $item['gonderilen_adet']; ?> ADET</span>
                </div>
                <div class="item-sku">SKU: <?php echo esc_html($item['sku']); ?></div>
            </div>
        <?php endforeach; ?>

        <div class="divider-dashed"></div>

        <div class="info-row bold"><span>TOPLAM:</span> <span><?php echo $toplam_cesit; ?> Çeşit / <?php echo $toplam_adet; ?> Adet</span></div>

        <?php if (!empty($not_gonderici)): ?>
            <div class="divider-dashed"></div>
            <div style="font-size: 10px;"><strong>Gönderici Notu:</strong> <?php echo $not_gonderici; ?></div>
        <?php endif; ?>

        <div class="signature-grid">
            <div class="signature-box">
                <div>Teslim Eden</div>
                <div style="margin-top: 24px;">İmza: .........</div>
            </div>
            <div class="signature-box">
                <div>Teslim Alan</div>
                <div style="margin-top: 24px;">İmza: .........</div>
            </div>
        </div>
    </div>
</body>
</html>
