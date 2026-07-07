<?php
if (!defined('ABSPATH')) {
    exit;
}

$is_public = isset($is_public) ? (bool) $is_public : false;

$export_data = [];
foreach ($product_ids as $product_id) {
    $product = wc_get_product($product_id);
    if (!$product) {
        continue;
    }

    $price_clean = html_entity_decode(strip_tags($product->get_price_html()), ENT_QUOTES, 'UTF-8');

    $image_id   = $product->get_image_id();
    $large_url  = $image_id ? wp_get_attachment_image_url($image_id, 'full') : '';
    $thumb_url  = $image_id ? wp_get_attachment_image_url($image_id, 'woocommerce_thumbnail') : '';
    if (!$thumb_url) {
        $thumb_url = $large_url;
    }

    $item = [
        'id'         => $product_id,
        'name'       => $product->get_name(),
        'sku'        => $product->get_sku(),
        'price'      => $price_clean,
        'type'       => $product->get_type(),
        'thumb'      => $thumb_url,
        'large'      => $large_url,
        'variations' => [],
        'stocks'     => [],
        'total_stock'=> 0,
    ];

    foreach ($warehouses as $wh) {
        $qty = isset($stock_map[$product_id][0][$wh->id]) ? $stock_map[$product_id][0][$wh->id] : 0;
        $item['stocks'][$wh->id] = $qty;
        $item['total_stock'] += $qty;
    }

    if ($product->is_type('variable')) {
        $variation_ids  = $product->get_children();
        $var_total_stock = 0;
        foreach ($variation_ids as $var_id) {
            $variation = wc_get_product($var_id);
            if (!$variation) {
                continue;
            }

            $attrs = [];
            foreach ($variation->get_variation_attributes() as $attr_name => $attr_val) {
                $taxonomy = str_replace('attribute_', '', $attr_name);
                $label    = wc_attribute_label($taxonomy, $product);
                if (taxonomy_exists($taxonomy)) {
                    $term = get_term_by('slug', $attr_val, $taxonomy);
                    $val  = $term ? $term->name : $attr_val;
                } else {
                    $val = $attr_val;
                }
                $attrs[] = $label . ': ' . $val;
            }
            $attrs_str      = implode(' / ', $attrs);
            $var_price      = html_entity_decode(strip_tags(wc_price($variation->get_price())), ENT_QUOTES, 'UTF-8');

            $var_item = [
                'id'          => $var_id,
                'name'        => $attrs_str,
                'sku'         => $variation->get_sku(),
                'price'       => $var_price,
                'stocks'      => [],
                'total_stock' => 0,
            ];

            foreach ($warehouses as $wh) {
                $qty = isset($stock_map[$product_id][$var_id][$wh->id]) ? $stock_map[$product_id][$var_id][$wh->id] : 0;
                $var_item['stocks'][$wh->id] = $qty;
                $var_item['total_stock']     += $qty;
            }
            $var_total_stock += $var_item['total_stock'];
            $item['variations'][] = $var_item;
        }

        if (!empty($item['variations'])) {
            $item['total_stock'] = $var_total_stock;
            foreach ($warehouses as $wh) {
                $wh_sum = 0;
                foreach ($item['variations'] as $v) {
                    $wh_sum += $v['stocks'][$wh->id];
                }
                $item['stocks'][$wh->id] = $wh_sum;
            }
        }
    }

    $export_data[] = $item;
}

$catalog_title = isset($options['title']) && !empty($options['title'])
    ? esc_html($options['title'])
    : 'Ürün Bilgi & Fiyat Kataloğu';

$md = "# Ürün Bilgi Tablosu\n\n";
$md_headers = ["Ürün", "SKU", "Fiyat", "Toplam Stok"];
foreach ($warehouses as $wh) {
    $md_headers[] = $wh->name;
}
$md .= "| " . implode(" | ", $md_headers) . " |\n";
$md .= "| " . implode(" | ", array_fill(0, count($md_headers), "---")) . " |\n";
foreach ($export_data as $p) {
    $row = ["**" . $p['name'] . "**", $p['sku'] ?: "-", $p['price'], $p['total_stock']];
    foreach ($warehouses as $wh) {
        $row[] = $p['stocks'][$wh->id];
    }
    $md .= "| " . implode(" | ", $row) . " |\n";
    foreach ($p['variations'] as $v) {
        $vr = ["&nbsp;&nbsp;↳ *" . $v['name'] . "*", $v['sku'] ?: "-", $v['price'], $v['total_stock']];
        foreach ($warehouses as $wh) {
            $vr[] = $v['stocks'][$wh->id];
        }
        $md .= "| " . implode(" | ", $vr) . " |\n";
    }
}

$csv_lines = [array_merge(["Ürün", "SKU", "Fiyat", "Toplam Stok"], array_column((array)$warehouses, 'name'))];
foreach ($export_data as $p) {
    $csv_lines[] = array_merge([$p['name'], $p['sku'] ?: "-", $p['price'], $p['total_stock']], array_map(fn($wh) => $p['stocks'][$wh->id], (array)$warehouses));
    foreach ($p['variations'] as $v) {
        $csv_lines[] = array_merge(["  ↳ " . $v['name'], $v['sku'] ?: "-", $v['price'], $v['total_stock']], array_map(fn($wh) => $v['stocks'][$wh->id], (array)$warehouses));
    }
}
$csv_output = "";
foreach ($csv_lines as $line) {
    $csv_output .= implode(',', array_map(fn($v) => '"' . str_replace('"', '""', $v) . '"', $line)) . "\r\n";
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $catalog_title; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <style>
        :root {
            --primary: #4f46e5;
            --primary-dark: #4338ca;
            --primary-light: #eef2ff;
            --bg: #f1f5f9;
            --card-bg: #ffffff;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --border: #e2e8f0;
            --green: #059669;
            --green-bg: #ecfdf5;
            --orange: #d97706;
            --orange-bg: #fffbeb;
            --red: #dc2626;
            --red-bg: #fef2f2;
            --radius: 14px;
            --shadow: 0 4px 24px -4px rgb(0 0 0 / 0.1);
            --shadow-sm: 0 1px 6px -1px rgb(0 0 0 / 0.07);
        }

        *, *::before, *::after { box-sizing: border-box; }

        body {
            font-family: 'Inter', system-ui, sans-serif;
            background: var(--bg);
            color: var(--text-main);
            margin: 0;
            padding: 0;
            min-height: 100vh;
        }

        /* ── TOP BAR ── */
        .topbar {
            position: sticky;
            top: 0;
            z-index: 100;
            background: rgba(255,255,255,0.85);
            backdrop-filter: blur(16px);
            border-bottom: 1px solid var(--border);
            padding: 12px 24px;
            display: flex;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
        }

        .topbar-brand {
            display: flex;
            flex-direction: column;
            margin-right: auto;
        }

        .topbar-brand h1 {
            margin: 0;
            font-size: 17px;
            font-weight: 700;
            letter-spacing: -0.03em;
            color: var(--text-main);
        }

        .topbar-brand span {
            font-size: 12px;
            color: var(--text-muted);
            margin-top: 1px;
        }

        .search-wrap {
            position: relative;
        }

        .search-wrap svg {
            position: absolute;
            left: 10px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            pointer-events: none;
        }

        #catalogSearch {
            font-family: inherit;
            font-size: 13px;
            padding: 8px 12px 8px 34px;
            border: 1px solid var(--border);
            border-radius: 8px;
            background: #f8fafc;
            color: var(--text-main);
            width: 220px;
            transition: border-color 0.2s, box-shadow 0.2s;
            outline: none;
        }

        #catalogSearch:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--primary-light);
            background: #fff;
        }

        .view-toggle {
            display: flex;
            gap: 4px;
            background: #f1f5f9;
            border-radius: 8px;
            padding: 3px;
        }

        .view-toggle button {
            font-family: inherit;
            font-size: 12px;
            font-weight: 500;
            padding: 5px 12px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            background: transparent;
            color: var(--text-muted);
            transition: all 0.2s;
        }

        .view-toggle button.active {
            background: #fff;
            color: var(--text-main);
            box-shadow: var(--shadow-sm);
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-family: inherit;
            font-size: 13px;
            font-weight: 500;
            padding: 7px 14px;
            border-radius: 8px;
            border: 1px solid var(--border);
            background: var(--card-bg);
            color: var(--text-main);
            cursor: pointer;
            transition: all 0.18s;
            text-decoration: none;
            white-space: nowrap;
        }

        .btn:hover { background: #f8fafc; border-color: #94a3b8; }

        .btn-primary {
            background: var(--primary);
            color: #fff;
            border-color: var(--primary);
        }

        .btn-primary:hover { background: var(--primary-dark); border-color: var(--primary-dark); }

        /* ── MAIN CONTAINER ── */
        .container {
            max-width: 1320px;
            margin: 0 auto;
            padding: 24px 20px;
        }

        /* ── SUMMARY BAR ── */
        .summary-bar {
            display: flex;
            gap: 12px;
            margin-bottom: 24px;
            flex-wrap: wrap;
        }

        .summary-card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 12px 18px;
            display: flex;
            align-items: center;
            gap: 10px;
            box-shadow: var(--shadow-sm);
        }

        .summary-card .icon {
            font-size: 20px;
            line-height: 1;
        }

        .summary-card .info .label {
            font-size: 11px;
            color: var(--text-muted);
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .summary-card .info .value {
            font-size: 18px;
            font-weight: 700;
            color: var(--text-main);
            line-height: 1.2;
        }

        /* ── GRID VIEW ── */
        #gridView {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
            gap: 20px;
        }

        .product-card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
            transition: transform 0.2s, box-shadow 0.2s;
            display: flex;
            flex-direction: column;
        }

        .product-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow);
        }

        .card-image {
            width: 100%;
            aspect-ratio: 1 / 1;
            overflow: hidden;
            background: #f8fafc;
            cursor: zoom-in;
            position: relative;
        }

        .card-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.35s ease;
        }

        .card-image:hover img { transform: scale(1.06); }

        .no-image-placeholder {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
        }

        .no-image-placeholder svg {
            width: 56px;
            height: 56px;
            color: #cbd5e1;
        }

        .card-badge-corner {
            position: absolute;
            top: 10px;
            right: 10px;
        }

        .card-body {
            padding: 14px 16px;
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .card-title {
            font-size: 14px;
            font-weight: 700;
            color: var(--text-main);
            line-height: 1.4;
            margin: 0;
        }

        .card-sku {
            font-family: 'SF Mono', 'Fira Code', monospace;
            font-size: 11px;
            color: var(--text-muted);
        }

        .card-price {
            font-size: 16px;
            font-weight: 700;
            color: var(--primary);
            margin-top: 2px;
        }

        .card-stocks {
            display: flex;
            flex-wrap: wrap;
            gap: 4px;
            margin-top: 6px;
        }

        .card-stock-item {
            font-size: 11px;
            font-weight: 500;
            padding: 2px 8px;
            border-radius: 6px;
            border: 1px solid var(--border);
            color: var(--text-muted);
            background: #f8fafc;
        }

        .card-actions {
            padding: 0 16px 14px;
        }

        .expand-btn {
            width: 100%;
            font-family: inherit;
            font-size: 12px;
            font-weight: 500;
            padding: 7px 0;
            border: 1px solid var(--border);
            border-radius: 8px;
            background: transparent;
            color: var(--text-muted);
            cursor: pointer;
            transition: all 0.18s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }

        .expand-btn:hover {
            background: var(--primary-light);
            border-color: var(--primary);
            color: var(--primary);
        }

        .expand-btn svg {
            transition: transform 0.2s;
        }

        .expand-btn.open svg { transform: rotate(180deg); }

        /* ── VARIATIONS PANEL (Grid) ── */
        .variations-panel {
            display: none;
            border-top: 1px solid var(--border);
            background: #f8fafc;
        }

        .variations-panel.open { display: block; }

        .var-row {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 9px 16px;
            border-bottom: 1px solid var(--border);
            flex-wrap: wrap;
        }

        .var-row:last-child { border-bottom: none; }

        .var-arrow {
            color: var(--text-muted);
            font-size: 13px;
            flex-shrink: 0;
        }

        .var-name {
            font-size: 12px;
            font-weight: 500;
            color: var(--text-main);
            flex: 1;
            min-width: 100px;
        }

        .var-sku {
            font-family: monospace;
            font-size: 11px;
            color: var(--text-muted);
        }

        .var-price {
            font-size: 12px;
            font-weight: 600;
            color: var(--primary);
        }

        /* ── TABLE VIEW ── */
        #tableView { display: none; }

        .table-card {
            background: var(--card-bg);
            border-radius: var(--radius);
            border: 1px solid var(--border);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
            font-size: 14px;
        }

        th {
            background: #f1f5f9;
            color: var(--text-muted);
            font-weight: 600;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding: 13px 18px;
            border-bottom: 1px solid var(--border);
            white-space: nowrap;
        }

        td { padding: 13px 18px; border-bottom: 1px solid var(--border); }
        tr:last-child td { border-bottom: none; }
        tr.product-row { cursor: pointer; transition: background 0.15s; }
        tr.product-row:hover td { background: #f8fafc; }

        .product-cell {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .product-thumb-sm {
            width: 44px;
            height: 44px;
            border-radius: 8px;
            object-fit: cover;
            border: 1px solid var(--border);
            flex-shrink: 0;
            cursor: zoom-in;
            transition: transform 0.2s;
        }

        .product-thumb-sm:hover { transform: scale(1.1); }

        .thumb-placeholder-sm {
            width: 44px;
            height: 44px;
            border-radius: 8px;
            background: #f1f5f9;
            border: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            color: #cbd5e1;
        }

        .product-name-cell { font-weight: 600; }
        .sku-code { font-family: monospace; color: var(--text-muted); font-size: 12px; }
        .price-tag { font-weight: 600; color: var(--primary); }

        .variation-row td { background: #fafbfd; }
        .variation-row.hidden { display: none; }

        .variation-cell {
            padding-left: 24px !important;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .chevron-icon { transition: transform 0.2s; color: var(--text-muted); }
        .chevron-icon.open { transform: rotate(90deg); }

        /* ── BADGE ── */
        .badge {
            display: inline-flex;
            align-items: center;
            font-size: 12px;
            font-weight: 600;
            padding: 3px 9px;
            border-radius: 9999px;
            min-width: 32px;
            justify-content: center;
        }

        .badge-success { background: var(--green-bg); color: var(--green); }
        .badge-warning { background: var(--orange-bg); color: var(--orange); }
        .badge-danger  { background: var(--red-bg); color: var(--red); }

        /* ── LIGHTBOX ── */
        .lightbox-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.85);
            z-index: 9999;
            align-items: center;
            justify-content: center;
            cursor: zoom-out;
            backdrop-filter: blur(4px);
            animation: fadeIn 0.2s ease;
        }

        .lightbox-overlay.open { display: flex; }

        .lightbox-img {
            max-width: min(90vw, 860px);
            max-height: 88vh;
            border-radius: 12px;
            object-fit: contain;
            box-shadow: 0 25px 60px rgb(0 0 0 / 0.5);
            animation: zoomIn 0.25s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        .lightbox-close {
            position: fixed;
            top: 20px;
            right: 24px;
            color: #fff;
            font-size: 28px;
            cursor: pointer;
            background: rgba(255,255,255,0.15);
            border: none;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.2s;
        }

        .lightbox-close:hover { background: rgba(255,255,255,0.28); }

        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        @keyframes zoomIn { from { opacity: 0; transform: scale(0.88); } to { opacity: 1; transform: scale(1); } }

        /* ── TOAST ── */
        .toast {
            position: fixed;
            bottom: 24px;
            right: 24px;
            background: #0f172a;
            color: #fff;
            padding: 12px 20px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 500;
            box-shadow: 0 10px 30px rgb(0 0 0 / 0.2);
            transform: translateY(80px);
            opacity: 0;
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            z-index: 9998;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .toast.show { transform: translateY(0); opacity: 1; }

        /* ── EMPTY STATE ── */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-muted);
            display: none;
        }

        .empty-state svg { width: 48px; height: 48px; margin-bottom: 12px; color: #cbd5e1; }
        .empty-state p { font-size: 15px; font-weight: 500; }

        /* ── PRINT ── */
        @media print {
            body { background: #fff; padding: 0; }
            .topbar, .no-print, .summary-bar { display: none !important; }
            #gridView { grid-template-columns: repeat(3, 1fr) !important; gap: 12px; }
            .product-card { break-inside: avoid; box-shadow: none; border: 1px solid #e2e8f0; }
            .variations-panel { display: block !important; }
            #tableView { display: none !important; }
        }

        /* ── RESPONSIVE ── */
        @media (max-width: 640px) {
            .topbar { gap: 8px; }
            #catalogSearch { width: 160px; }
            .btn span { display: none; }
        }
    </style>
</head>
<body>

<!-- TOP BAR -->
<header class="topbar no-print">
    <div class="topbar-brand">
        <h1><?php echo $catalog_title; ?></h1>
        <span><?php echo date('d.m.Y'); ?> · <?php echo count($export_data); ?> ürün</span>
    </div>

    <div class="search-wrap">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
        <input type="text" id="catalogSearch" placeholder="Ürün ara..." autocomplete="off">
    </div>

    <div class="view-toggle">
        <button id="btnGrid" class="active" onclick="setView('grid')" title="Kart Görünümü">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
            Kart
        </button>
        <button id="btnTable" onclick="setView('table')" title="Tablo Görünümü">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 3H5a2 2 0 00-2 2v4m6-6h10a2 2 0 012 2v4M9 3v18m0 0h10a2 2 0 002-2V9M9 21H5a2 2 0 01-2-2V9m0 0h18"/></svg>
            Tablo
        </button>
    </div>

    <?php if (!$is_public): ?>
    <button class="btn" onclick="copyMarkdown()">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"/></svg>
        <span>Markdown</span>
    </button>
    <button class="btn" onclick="downloadCSV()">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
        <span>CSV</span>
    </button>
    <?php endif; ?>
    <button class="btn btn-primary" onclick="downloadPDF()">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
        <span>PDF</span>
    </button>
</header>

<div class="container">
    <!-- SUMMARY -->
    <div class="summary-bar no-print">
        <?php
        $total_all = array_sum(array_column($export_data, 'total_stock'));
        $total_products = count($export_data);
        $total_vars = array_sum(array_map(fn($p) => count($p['variations']), $export_data));
        ?>
        <div class="summary-card">
            <div class="icon">📦</div>
            <div class="info">
                <div class="label">Toplam Ürün</div>
                <div class="value"><?php echo $total_products; ?></div>
            </div>
        </div>
        <?php if ($total_vars > 0): ?>
        <div class="summary-card">
            <div class="icon">🔀</div>
            <div class="info">
                <div class="label">Varyasyon</div>
                <div class="value"><?php echo $total_vars; ?></div>
            </div>
        </div>
        <?php endif; ?>
        <div class="summary-card">
            <div class="icon">🏪</div>
            <div class="info">
                <div class="label">Toplam Stok</div>
                <div class="value"><?php echo number_format($total_all, 0, ',', '.'); ?></div>
            </div>
        </div>
        <?php foreach ($warehouses as $wh):
            $wh_total = array_sum(array_map(fn($p) => $p['stocks'][$wh->id], $export_data));
        ?>
        <div class="summary-card">
            <div class="icon">🗄️</div>
            <div class="info">
                <div class="label"><?php echo esc_html($wh->name); ?></div>
                <div class="value"><?php echo number_format($wh_total, 0, ',', '.'); ?></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- GRID VIEW -->
    <div id="gridView">
        <?php foreach ($export_data as $p):
            $has_variations = !empty($p['variations']);
            $has_img        = !empty($p['thumb']);
            $badge_class    = $p['total_stock'] > 5 ? 'badge-success' : ($p['total_stock'] > 0 ? 'badge-warning' : 'badge-danger');
        ?>
        <div class="product-card" data-name="<?php echo esc_attr(strtolower($p['name'])); ?>" data-sku="<?php echo esc_attr(strtolower($p['sku'])); ?>">
            <div class="card-image" onclick="openLightbox('<?php echo esc_attr($has_img ? $p['large'] : ''); ?>', '<?php echo esc_attr($p['name']); ?>')">
                <?php if ($has_img): ?>
                    <img src="<?php echo esc_url($p['thumb']); ?>" alt="<?php echo esc_attr($p['name']); ?>" loading="lazy">
                <?php else: ?>
                    <div class="no-image-placeholder">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                    </div>
                <?php endif; ?>
                <div class="card-badge-corner">
                    <span class="badge <?php echo $badge_class; ?>"><?php echo $p['total_stock']; ?></span>
                </div>
            </div>
            <div class="card-body">
                <p class="card-title"><?php echo esc_html($p['name']); ?></p>
                <?php if ($p['sku']): ?>
                <p class="card-sku">SKU: <?php echo esc_html($p['sku']); ?></p>
                <?php endif; ?>
                <p class="card-price"><?php echo esc_html($p['price']); ?></p>
                <div class="card-stocks">
                    <?php foreach ($warehouses as $wh): ?>
                    <span class="card-stock-item"><?php echo esc_html($wh->name); ?>: <?php echo $p['stocks'][$wh->id]; ?></span>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php if ($has_variations): ?>
            <div class="card-actions">
                <button class="expand-btn" onclick="toggleVariations(this)">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>
                    <?php echo count($p['variations']); ?> varyasyon
                </button>
            </div>
            <div class="variations-panel">
                <?php foreach ($p['variations'] as $v):
                    $vb = $v['total_stock'] > 5 ? 'badge-success' : ($v['total_stock'] > 0 ? 'badge-warning' : 'badge-danger');
                ?>
                <div class="var-row">
                    <span class="var-arrow">↳</span>
                    <span class="var-name"><?php echo esc_html($v['name']); ?></span>
                    <?php if ($v['sku']): ?>
                    <span class="var-sku"><?php echo esc_html($v['sku']); ?></span>
                    <?php endif; ?>
                    <span class="var-price"><?php echo esc_html($v['price']); ?></span>
                    <span class="badge <?php echo $vb; ?>" style="margin-left:auto;"><?php echo $v['total_stock']; ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
        <div class="empty-state" id="emptyState">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
            <p>Aradığınız ürün bulunamadı.</p>
        </div>
    </div>

    <!-- TABLE VIEW -->
    <div id="tableView">
        <div class="table-card">
            <table>
                <thead>
                    <tr>
                        <th style="width:40%">Ürün / Özellik</th>
                        <th>SKU</th>
                        <th>Fiyat</th>
                        <?php foreach ($warehouses as $wh): ?>
                        <th><?php echo esc_html($wh->name); ?></th>
                        <?php endforeach; ?>
                        <th>Toplam</th>
                    </tr>
                </thead>
                <tbody id="tableBody">
                    <?php foreach ($export_data as $idx => $p):
                        $has_variations = !empty($p['variations']);
                        $has_img = !empty($p['thumb']);
                        $tot_b = $p['total_stock'] > 5 ? 'badge-success' : ($p['total_stock'] > 0 ? 'badge-warning' : 'badge-danger');
                    ?>
                    <tr class="product-row" data-name="<?php echo esc_attr(strtolower($p['name'])); ?>" data-sku="<?php echo esc_attr(strtolower($p['sku'])); ?>" data-idx="<?php echo $idx; ?>" <?php if ($has_variations) echo 'onclick="toggleTableVariations(' . $idx . ', this)"'; ?>>
                        <td>
                            <div class="product-cell">
                                <?php if ($has_img): ?>
                                <img class="product-thumb-sm" src="<?php echo esc_url($p['thumb']); ?>" alt="<?php echo esc_attr($p['name']); ?>" onclick="event.stopPropagation(); openLightbox('<?php echo esc_attr($p['large']); ?>', '<?php echo esc_attr($p['name']); ?>')" loading="lazy">
                                <?php else: ?>
                                <div class="thumb-placeholder-sm">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                                </div>
                                <?php endif; ?>
                                <span class="product-name-cell"><?php echo esc_html($p['name']); ?></span>
                                <?php if ($has_variations): ?>
                                <svg class="chevron-icon" id="chevron-<?php echo $idx; ?>" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td><span class="sku-code"><?php echo esc_html($p['sku'] ?: '-'); ?></span></td>
                        <td><span class="price-tag"><?php echo esc_html($p['price']); ?></span></td>
                        <?php foreach ($warehouses as $wh):
                            $qty = $p['stocks'][$wh->id];
                            $b   = $qty > 5 ? 'badge-success' : ($qty > 0 ? 'badge-warning' : 'badge-danger');
                        ?>
                        <td><span class="badge <?php echo $b; ?>"><?php echo $qty; ?></span></td>
                        <?php endforeach; ?>
                        <td><span class="badge <?php echo $tot_b; ?>" style="font-weight:700;"><?php echo $p['total_stock']; ?></span></td>
                    </tr>
                    <?php foreach ($p['variations'] as $v):
                        $vb   = $v['total_stock'] > 5 ? 'badge-success' : ($v['total_stock'] > 0 ? 'badge-warning' : 'badge-danger');
                    ?>
                    <tr class="variation-row hidden" data-parent="<?php echo $idx; ?>">
                        <td>
                            <div class="variation-cell">
                                <span style="color:var(--text-muted)">↳</span>
                                <span style="font-size:13px;color:var(--text-muted);"><?php echo esc_html($v['name']); ?></span>
                            </div>
                        </td>
                        <td><span class="sku-code"><?php echo esc_html($v['sku'] ?: '-'); ?></span></td>
                        <td><span class="price-tag" style="font-size:13px;"><?php echo esc_html($v['price']); ?></span></td>
                        <?php foreach ($warehouses as $wh):
                            $qty = $v['stocks'][$wh->id];
                            $b   = $qty > 5 ? 'badge-success' : ($qty > 0 ? 'badge-warning' : 'badge-danger');
                        ?>
                        <td><span class="badge <?php echo $b; ?>"><?php echo $qty; ?></span></td>
                        <?php endforeach; ?>
                        <td><span class="badge <?php echo $vb; ?>" style="font-weight:700;"><?php echo $v['total_stock']; ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- LIGHTBOX -->
<div class="lightbox-overlay" id="lightboxOverlay" onclick="closeLightbox()">
    <button class="lightbox-close" onclick="closeLightbox()">✕</button>
    <img class="lightbox-img" id="lightboxImg" src="" alt="">
</div>

<!-- TOAST -->
<div class="toast" id="toast">✓ Kopyalandı!</div>

<script>
    const MD_DATA  = <?php echo json_encode($md); ?>;
    const CSV_DATA = <?php echo json_encode($csv_output); ?>;

    let currentView = 'grid';

    function setView(view) {
        currentView = view;
        document.getElementById('gridView').style.display  = view === 'grid' ? 'grid' : 'none';
        document.getElementById('tableView').style.display = view === 'table' ? 'block' : 'none';
        document.getElementById('btnGrid').classList.toggle('active', view === 'grid');
        document.getElementById('btnTable').classList.toggle('active', view === 'table');
    }

    document.getElementById('catalogSearch').addEventListener('input', function () {
        const q = this.value.toLowerCase().trim();

        if (currentView === 'grid') {
            let visible = 0;
            document.querySelectorAll('#gridView .product-card').forEach(card => {
                const match = !q || card.dataset.name.includes(q) || card.dataset.sku.includes(q);
                card.style.display = match ? '' : 'none';
                if (match) visible++;
            });
            document.getElementById('emptyState').style.display = visible === 0 ? 'block' : 'none';
        } else {
            document.querySelectorAll('#tableBody .product-row').forEach(row => {
                const match = !q || row.dataset.name.includes(q) || row.dataset.sku.includes(q);
                row.style.display = match ? '' : 'none';
                if (!match) {
                    const idx = row.dataset.idx;
                    document.querySelectorAll(`.variation-row[data-parent="${idx}"]`).forEach(vr => vr.style.display = 'none');
                }
            });
        }
    });

    function toggleVariations(btn) {
        const panel = btn.closest('.product-card').querySelector('.variations-panel');
        const isOpen = panel.classList.toggle('open');
        btn.classList.toggle('open', isOpen);
        btn.querySelector('svg + text, svg').style.transform = isOpen ? 'rotate(180deg)' : '';
    }

    const tableOpenState = {};
    function toggleTableVariations(idx, row) {
        tableOpenState[idx] = !tableOpenState[idx];
        const chevron = document.getElementById('chevron-' + idx);
        if (chevron) chevron.classList.toggle('open', tableOpenState[idx]);
        document.querySelectorAll(`.variation-row[data-parent="${idx}"]`).forEach(vr => {
            vr.classList.toggle('hidden', !tableOpenState[idx]);
        });
    }

    function openLightbox(url, alt) {
        if (!url) return;
        const img = document.getElementById('lightboxImg');
        img.src = url;
        img.alt = alt;
        document.getElementById('lightboxOverlay').classList.add('open');
        document.body.style.overflow = 'hidden';
    }

    function closeLightbox() {
        document.getElementById('lightboxOverlay').classList.remove('open');
        document.body.style.overflow = '';
    }

    document.addEventListener('keydown', e => { if (e.key === 'Escape') closeLightbox(); });

    function showToast(msg) {
        const t = document.getElementById('toast');
        t.textContent = msg;
        t.classList.add('show');
        setTimeout(() => t.classList.remove('show'), 2800);
    }

    function copyMarkdown() {
        navigator.clipboard.writeText(MD_DATA)
            .then(() => showToast('✓ Markdown panoya kopyalandı!'))
            .catch(() => showToast('✗ Kopyalama başarısız.'));
    }

    function downloadCSV() {
        const blob = new Blob(['\uFEFF' + CSV_DATA], { type: 'text/csv;charset=utf-8;' });
        const a    = document.createElement('a');
        a.href     = URL.createObjectURL(blob);
        a.download = 'urun-katalog.csv';
        a.click();
    }

    function downloadPDF() {
        const el  = document.querySelector(currentView === 'grid' ? '#gridView' : '.table-card');
        const opt = {
            margin: 10,
            filename: 'urun-katalog.pdf',
            image: { type: 'jpeg', quality: 0.95 },
            html2canvas: { scale: 2, useCORS: true },
            jsPDF: { unit: 'mm', format: 'a4', orientation: currentView === 'grid' ? 'portrait' : 'landscape' }
        };
        html2pdf().set(opt).from(el).save();
        showToast('⏳ PDF hazırlanıyor...');
    }
</script>
</body>
</html>
