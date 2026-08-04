# Sevk Fişi Termal Yazdırma (Thermal Receipt Printing) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a 58mm and 80mm compatible thermal receipt printing feature for shipments (Sevk Transfer Fişi) accessible via a print button inside the Sevk Detay Modalı.

**Architecture:** 
- PHP Backend: `Hizli_Kasa_Shipment_Print_Builder` extracts shipment header and line items data, rendered via `receipt-shipment.php` PHP template using absolute `#000000` text and Consolas monospace font.
- REST API: V2 print endpoint `/wp-json/hizli-kasa/v2/print/shipment/{id}` in `class-api-print.php`.
- JS Frontend: `sevk-manager.js` includes a "🖨️ Sevk Fişi Yazdır" button in shipment detail views calling `HK.PrintCore.print({ type: 'shipment', id: sevkId })`.

**Tech Stack:** PHP 7.4+ (OOP), WordPress/WooCommerce V2 REST API, Vanilla JavaScript (PrintCore), CSS Thermal Media Rules.

## Global Constraints

- No native browser popups (no alert/confirm/prompt).
- No-Cache headers on all REST API responses.
- Monochrome 100% black `#000000` styling with `font-weight: 600/bold` for thermal printer anti-aliasing prevention.
- Fluid width (`width: 100%`) for 58mm and 80mm thermal paper auto-fit without horizontal overflow.

---

### Task 1: Create Shipment Data Builder (`class-shipment-print-builder.php`)

**Files:**
- Create: `includes/printing/builder/class-shipment-print-builder.php`

**Interfaces:**
- Consumes: Database table `hizli_kasa_sevkler` and `hizli_kasa_sevk_kalemleri`
- Produces: `Hizli_Kasa_Shipment_Print_Builder::build(int $shipment_id)` array payload with `sevk`, `kalemler`, `barkod_url`, `toplam_cesit`, `toplam_adet`.

- [ ] **Step 1: Create `class-shipment-print-builder.php`**

```php
<?php
if (!defined('ABSPATH')) {
    exit;
}

class Hizli_Kasa_Shipment_Print_Builder {
    public static function build(int $shipment_id): array|false {
        global $wpdb;
        $tables = Hizli_Kasa_Database::get_tables();

        $sevk = $wpdb->get_row($wpdb->prepare(
            "SELECT s.*, 
                    dk.name as kaynak_depo_adi, 
                    dh.name as hedef_depo_adi 
             FROM {$tables['sevkler']} s 
             LEFT JOIN {$tables['depolar']} dk ON s.kaynak_depo_id = dk.id 
             LEFT JOIN {$tables['depolar']} dh ON s.hedef_depo_id = dh.id 
             WHERE s.id = %d",
            $shipment_id
        ));

        if (!$sevk) {
            return false;
        }

        $items = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$tables['sevk_kalemleri']} WHERE sevk_id = %d ORDER BY id ASC",
            $shipment_id
        ));

        $toplam_cesit = count($items);
        $toplam_adet  = 0;

        $kalemler = array_map(function($i) use (&$toplam_adet) {
            $qty = (float) $i->gonderilen_adet;
            $toplam_adet += $qty;
            return [
                'id'              => (int) $i->id,
                'product_id'      => (int) $i->product_id,
                'variation_id'    => (int) $i->variation_id,
                'sku'             => $i->sku ?: '-',
                'urun_adi'        => $i->urun_adi,
                'gonderilen_adet' => $qty,
            ];
        }, $items);

        $site_name = get_bloginfo('name') ?: 'Hızlı Kasa';

        return [
            'site_name'    => $site_name,
            'sevk'         => $sevk,
            'kalemler'     => $kalemler,
            'toplam_cesit' => $toplam_cesit,
            'toplam_adet'  => $toplam_adet,
            'tarih'        => current_time('d.m.Y H:i'),
        ];
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add includes/printing/builder/class-shipment-print-builder.php
git commit -m "feat(print): add Hizli_Kasa_Shipment_Print_Builder"
```

---

### Task 2: Create Shipment Thermal Receipt Template (`receipt-shipment.php`)

**Files:**
- Create: `includes/printing/templates/receipt-shipment.php`

**Interfaces:**
- Consumes: Payload array from `Hizli_Kasa_Shipment_Print_Builder::build()`
- Produces: HTML string for thermal receipt printing.

- [ ] **Step 1: Create `receipt-shipment.php` HTML/CSS template**

```php
<?php
if (!defined('ABSPATH')) {
    exit;
}

$site_name    = esc_html($payload['site_name'] ?? 'Hızlı Kasa');
$sevk         = $payload['sevk'];
$kalemler     = $payload['kalemler'] ?? [];
$toplam_cesit = intval($payload['toplam_cesit'] ?? 0);
$toplam_adet  = floatval($payload['toplam_adet'] ?? 0);
$tarih        = esc_html($payload['tarih'] ?? '');
$sevk_no      = esc_html($sevk->sevk_no ?? '');
$kaynak_depo  = esc_html($sevk->kaynak_depo_adi ?? '-');
$hedef_depo   = esc_html($sevk->hedef_depo_adi ?? '-');
$durum_label  = esc_html(strtoupper($sevk->durum ?? ''));
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
        .text-right { text-align: right; }
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
```

- [ ] **Step 2: Commit**

```bash
git add includes/printing/templates/receipt-shipment.php
git commit -m "feat(print): add receipt-shipment.php thermal print template"
```

---

### Task 3: Register Shipment Endpoint in `class-api-print.php`

**Files:**
- Modify: `includes/api/v2/controllers/class-api-print.php`

**Interfaces:**
- Consumes: REST request `GET /wp-json/hizli-kasa/v2/print/shipment/{id}`
- Produces: JSON response with `html` string.

- [ ] **Step 1: Update `class-api-print.php` to handle shipment route**

Add route registration:
```php
register_rest_route($this->namespace, '/print/shipment/(?P<id>\d+)', [
    'methods'             => WP_REST_Server::READABLE,
    'callback'            => [$this, 'get_shipment_print'],
    'permission_callback' => [$this, 'check_permission'],
]);
```

Add handler callback:
```php
public function get_shipment_print($request) {
    $id = (int) $request['id'];
    require_once HIZLI_KASA_PATH . 'includes/printing/builder/class-shipment-print-builder.php';
    
    $payload = Hizli_Kasa_Shipment_Print_Builder::build($id);
    if (!$payload) {
        return Hizli_Kasa_API_Response::error('Sevk bulunamadı.', 404);
    }

    ob_start();
    include HIZLI_KASA_PATH . 'includes/printing/templates/receipt-shipment.php';
    $html = ob_get_clean();

    return Hizli_Kasa_API_Response::success([
        'html'    => $html,
        'payload' => $payload,
    ]);
}
```

- [ ] **Step 2: Lint & Commit**

```bash
php -l includes/api/v2/controllers/class-api-print.php
git add includes/api/v2/controllers/class-api-print.php
git commit -m "feat(api): register /print/shipment/{id} endpoint in class-api-print.php"
```

---

### Task 4: Add Print Button & Event Listener in `sevk-manager.js`

**Files:**
- Modify: `assets/js/modules/sevk-manager.js`

**Interfaces:**
- Consumes: `HK.PrintCore.print({ type: 'shipment', id: sevkId })`
- Produces: User trigger inside `renderIncomingDetail` and Sevk Detail Modal.

- [ ] **Step 1: Add "🖨️ Sevk Fişi Yazdır" button to `renderIncomingDetail` in `sevk-manager.js`**

Locate `renderIncomingDetail` in `sevk-manager.js`:
Add print button inside actions header or detail footer:
```javascript
'<button type="button" class="sevk-btn secondary" data-print-shipment="' + sevk.id + '">🖨️ Sevk Fişi Yazdır</button>'
```

Attach click listener:
```javascript
panel.querySelectorAll('[data-print-shipment]').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var id = parseInt(btn.dataset.printShipment);
        if (window.HizliKasa && window.HizliKasa.PrintCore) {
            window.HizliKasa.PrintCore.print({ type: 'shipment', id: id });
        }
    });
});
```

- [ ] **Step 2: Update `print-core.js` if needed to support `type: 'shipment'` API route mapping**

Check `assets/js/printing/print-core.js` route mapping for type `shipment` -> `print/shipment/{id}`.

- [ ] **Step 3: Commit**

```bash
git add assets/js/modules/sevk-manager.js assets/js/printing/print-core.js
git commit -m "feat(sevk): add print shipment button to sevk detail view"
```

---

### Task 5: Manual Verification & Integration Test

- [ ] **Step 1: Verify API endpoint response**
- [ ] **Step 2: Test thermal printing popup/output in Sevk Detay Modalı**
- [ ] **Step 3: Commit final plan verification**
