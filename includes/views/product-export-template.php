<?php
if (!defined('ABSPATH')) {
    exit;
}

$export_data = [];
foreach ($product_ids as $product_id) {
    $product = wc_get_product($product_id);
    if (!$product) {
        continue;
    }

    $price_clean = html_entity_decode(strip_tags($product->get_price_html()), ENT_QUOTES, 'UTF-8');

    $item = [
        'id' => $product_id,
        'name' => $product->get_name(),
        'sku' => $product->get_sku(),
        'price' => $price_clean,
        'type' => $product->get_type(),
        'variations' => []
    ];

    $item['stocks'] = [];
    $item['total_stock'] = 0;
    foreach ($warehouses as $wh) {
        $qty = isset($stock_map[$product_id][0][$wh->id]) ? $stock_map[$product_id][0][$wh->id] : 0;
        $item['stocks'][$wh->id] = $qty;
        $item['total_stock'] += $qty;
    }

    if ($product->is_type('variable')) {
        $variation_ids = $product->get_children();
        $var_total_stock = 0;
        foreach ($variation_ids as $var_id) {
            $variation = wc_get_product($var_id);
            if (!$variation) {
                continue;
            }

            $attrs = [];
            foreach ($variation->get_variation_attributes() as $attr_name => $attr_val) {
                $taxonomy = str_replace('attribute_', '', $attr_name);
                $label = wc_attribute_label($taxonomy, $product);
                $val = '';
                if (taxonomy_exists($taxonomy)) {
                    $term = get_term_by('slug', $attr_val, $taxonomy);
                    $val = $term ? $term->name : $attr_val;
                } else {
                    $val = $attr_val;
                }
                $attrs[] = $val;
            }
            $attrs_str = implode(', ', $attrs);
            $var_price_clean = html_entity_decode(strip_tags(wc_price($variation->get_price())), ENT_QUOTES, 'UTF-8');

            $var_item = [
                'id' => $var_id,
                'name' => $attrs_str,
                'sku' => $variation->get_sku(),
                'price' => $var_price_clean,
                'stocks' => []
            ];

            $var_stock_sum = 0;
            foreach ($warehouses as $wh) {
                $qty = isset($stock_map[$product_id][$var_id][$wh->id]) ? $stock_map[$product_id][$var_id][$wh->id] : 0;
                $var_item['stocks'][$wh->id] = $qty;
                $var_stock_sum += $qty;
            }
            $var_item['total_stock'] = $var_stock_sum;
            $var_total_stock += $var_stock_sum;

            $item['variations'][] = $var_item;
        }

        if (!empty($item['variations'])) {
            $item['total_stock'] = $var_total_stock;
            foreach ($warehouses as $wh) {
                $wh_sum = 0;
                foreach ($item['variations'] as $v_item) {
                    $wh_sum += $v_item['stocks'][$wh->id];
                }
                $item['stocks'][$wh->id] = $wh_sum;
            }
        }
    }

    $export_data[] = $item;
}

$xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><urunler/>');
foreach ($export_data as $p) {
    $urun = $xml->addChild('urun');
    $urun->addChild('isim', htmlspecialchars($p['name']));
    $urun->addChild('sku', htmlspecialchars($p['sku']));
    $urun->addChild('fiyat', htmlspecialchars($p['price']));
    $urun->addChild('tip', htmlspecialchars($p['type']));
    $urun->addChild('toplam_stok', $p['total_stock']);
    
    $depolar_xml = $urun->addChild('depolar');
    foreach ($warehouses as $wh) {
        $depo_node = $depolar_xml->addChild('depo', $p['stocks'][$wh->id]);
        $depo_node->addAttribute('isim', htmlspecialchars($wh->name));
    }
    
    if (!empty($p['variations'])) {
        $varyasyonlar_xml = $urun->addChild('varyasyonlar');
        foreach ($p['variations'] as $v) {
            $var_node = $varyasyonlar_xml->addChild('varyasyon');
            $var_node->addChild('isim', htmlspecialchars($v['name']));
            $var_node->addChild('sku', htmlspecialchars($v['sku']));
            $var_node->addChild('fiyat', htmlspecialchars($v['price']));
            $var_node->addChild('toplam_stok', $v['total_stock']);
            
            $v_depolar_xml = $var_node->addChild('depolar');
            foreach ($warehouses as $wh) {
                $v_depo_node = $v_depolar_xml->addChild('depo', $v['stocks'][$wh->id]);
                $v_depo_node->addAttribute('isim', htmlspecialchars($wh->name));
            }
        }
    }
}
$dom = new DOMDocument("1.0", "UTF-8");
$dom->preserveWhiteSpace = false;
$dom->formatOutput = true;
$dom->loadXML($xml->asXML());
$xml_string = $dom->saveXML();

$md = "# Ürün Bilgi Tablosu\n\n";
$headers = ["Ürün", "SKU", "Fiyat", "Toplam Stok"];
foreach ($warehouses as $wh) {
    $headers[] = $wh->name;
}
$md .= "| " . implode(" | ", $headers) . " |\n";
$md .= "| " . implode(" | ", array_fill(0, count($headers), "---")) . " |\n";

foreach ($export_data as $p) {
    $row = [
        "**" . $p['name'] . "**",
        $p['sku'] ?: "-",
        $p['price'],
        $p['total_stock']
    ];
    foreach ($warehouses as $wh) {
        $row[] = $p['stocks'][$wh->id];
    }
    $md .= "| " . implode(" | ", $row) . " |\n";
    
    if (!empty($p['variations'])) {
        foreach ($p['variations'] as $v) {
            $v_row = [
                "&nbsp;&nbsp;↳ *" . $v['name'] . "*",
                $v['sku'] ?: "-",
                $v['price'],
                $v['total_stock']
            ];
            foreach ($warehouses as $wh) {
                $v_row[] = $v['stocks'][$wh->id];
            }
            $md .= "| " . implode(" | ", $v_row) . " |\n";
        }
    }
}

$csv_lines = [];
$csv_headers = ["Ürün", "SKU", "Fiyat", "Toplam Stok"];
foreach ($warehouses as $wh) {
    $csv_headers[] = $wh->name;
}
$csv_lines[] = $csv_headers;

foreach ($export_data as $p) {
    $row = [
        $p['name'],
        $p['sku'] ?: "-",
        $p['price'],
        $p['total_stock']
    ];
    foreach ($warehouses as $wh) {
        $row[] = $p['stocks'][$wh->id];
    }
    $csv_lines[] = $row;
    
    if (!empty($p['variations'])) {
        foreach ($p['variations'] as $v) {
            $v_row = [
                "  ↳ " . $v['name'],
                $v['sku'] ?: "-",
                $v['price'],
                $v['total_stock']
            ];
            foreach ($warehouses as $wh) {
                $v_row[] = $v['stocks'][$wh->id];
            }
            $csv_lines[] = $v_row;
        }
    }
}

$csv_output = "";
foreach ($csv_lines as $line) {
    $clean_line = array_map(fn($v) => '"' . str_replace('"', '""', $v) . '"', $line);
    $csv_output .= implode(',', $clean_line) . "\r\n";
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Müşteri Ürün Bilgi Listesi</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <style>
        :root {
            --primary: #4f46e5;
            --primary-hover: #4338ca;
            --bg: #f8fafc;
            --card-bg: #ffffff;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --border: #e2e8f0;
            --green: #10b981;
            --orange: #f59e0b;
            --red: #ef4444;
        }

        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background-color: var(--bg);
            color: var(--text-main);
            margin: 0;
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .header-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: var(--card-bg);
            padding: 20px 24px;
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.05), 0 2px 4px -2px rgb(0 0 0 / 0.05);
            margin-bottom: 24px;
            border: 1px solid var(--border);
        }

        .header-title h1 {
            margin: 0;
            font-size: 20px;
            font-weight: 600;
            letter-spacing: -0.025em;
        }

        .header-title p {
            margin: 4px 0 0 0;
            font-size: 13px;
            color: var(--text-muted);
        }

        .actions {
            display: flex;
            gap: 10px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-family: inherit;
            font-size: 13px;
            font-weight: 500;
            padding: 8px 16px;
            border-radius: 8px;
            border: 1px solid var(--border);
            background: var(--card-bg);
            color: var(--text-main);
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn:hover {
            background: var(--bg);
            border-color: var(--text-muted);
        }

        .btn-primary {
            background: var(--primary);
            color: #fff;
            border-color: var(--primary);
        }

        .btn-primary:hover {
            background: var(--primary-hover);
            border-color: var(--primary-hover);
        }

        .table-card {
            background: var(--card-bg);
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.05), 0 2px 4px -2px rgb(0 0 0 / 0.05);
            border: 1px solid var(--border);
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
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding: 14px 20px;
            border-bottom: 1px solid var(--border);
        }

        td {
            padding: 14px 20px;
            border-bottom: 1px solid var(--border);
        }

        tr:last-child td {
            border-bottom: none;
        }

        .product-name {
            font-weight: 600;
        }

        .variation-row {
            background-color: #fafbfd;
        }

        .variation-name {
            font-weight: 400;
            color: var(--text-muted);
            padding-left: 36px;
            position: relative;
        }

        .variation-name::before {
            content: "↳";
            position: absolute;
            left: 20px;
            color: var(--text-muted);
        }

        .sku-code {
            font-family: monospace;
            color: var(--text-muted);
            font-size: 13px;
        }

        .price-tag {
            font-weight: 500;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            font-size: 12px;
            font-weight: 500;
            padding: 2px 8px;
            border-radius: 9999px;
        }

        .badge-success {
            background-color: #ecfdf5;
            color: var(--green);
        }

        .badge-warning {
            background-color: #fffbeb;
            color: var(--orange);
        }

        .badge-danger {
            background-color: #fef2f2;
            color: var(--red);
        }

        .toast {
            position: fixed;
            bottom: 24px;
            right: 24px;
            background: #0f172a;
            color: #fff;
            padding: 12px 20px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 500;
            box-shadow: 0 10px 15px -3px rgb(0 0 0 / 0.1);
            transform: translateY(100px);
            opacity: 0;
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            z-index: 9999;
        }

        .toast.show {
            transform: translateY(0);
            opacity: 1;
        }

        @media print {
            body {
                background: #fff;
                padding: 0;
            }
            .no-print {
                display: none !important;
            }
            .table-card {
                border: none;
                box-shadow: none;
                border-radius: 0;
            }
            th {
                background: #f1f5f9 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            tr {
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header-bar no-print">
            <div class="header-title">
                <h1>Ürün Bilgi & Fiyat Tablosu</h1>
                <p>Müşterileriniz ve ortaklarınız için optimize edilmiş görünüm</p>
            </div>
            <div class="actions">
                <button class="btn" onclick="copyMarkdown()">
                    📋 Markdown Kopyala
                </button>
                <button class="btn" onclick="downloadXML()">
                    📥 XML İndir
                </button>
                <button class="btn" onclick="downloadCSV()">
                    📊 Excel / CSV İndir
                </button>
                <button class="btn" onclick="downloadPDF()">
                    📥 PDF İndir
                </button>
                <button class="btn btn-primary" onclick="window.print()">
                    🖨️ Direkt Yazdır
                </button>
            </div>
        </div>

        <div class="table-card">
            <table>
                <thead>
                    <tr>
                        <th>Ürün / Özellik</th>
                        <th>SKU</th>
                        <th>Fiyat</th>
                        <?php foreach ($warehouses as $wh): ?>
                            <th><?php echo esc_html($wh->name); ?></th>
                        <?php endforeach; ?>
                        <th>Toplam Stok</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($export_data as $p): ?>
                        <tr>
                            <td>
                                <span class="product-name"><?php echo esc_html($p['name']); ?></span>
                            </td>
                            <td>
                                <span class="sku-code"><?php echo esc_html($p['sku'] ?: '-'); ?></span>
                            </td>
                            <td>
                                <span class="price-tag"><?php echo esc_html($p['price']); ?></span>
                            </td>
                            <?php foreach ($warehouses as $wh): ?>
                                <td>
                                    <?php 
                                    $qty = $p['stocks'][$wh->id];
                                    $badge = 'badge-danger';
                                    if ($qty > 5) {
                                        $badge = 'badge-success';
                                    } elseif ($qty > 0) {
                                        $badge = 'badge-warning';
                                    }
                                    ?>
                                    <span class="badge <?php echo $badge; ?>"><?php echo $qty; ?></span>
                                </td>
                            <?php endforeach; ?>
                            <td>
                                <?php 
                                $tot = $p['total_stock'];
                                $badge = 'badge-danger';
                                if ($tot > 5) {
                                    $badge = 'badge-success';
                                } elseif ($tot > 0) {
                                    $badge = 'badge-warning';
                                }
                                ?>
                                <span class="badge <?php echo $badge; ?>" style="font-weight:700;"><?php echo $tot; ?></span>
                            </td>
                        </tr>

                        <?php if (!empty($p['variations'])): ?>
                            <?php foreach ($p['variations'] as $v): ?>
                                <tr class="variation-row">
                                    <td>
                                        <span class="variation-name"><?php echo esc_html($v['name']); ?></span>
                                    </td>
                                    <td>
                                        <span class="sku-code"><?php echo esc_html($v['sku'] ?: '-'); ?></span>
                                    </td>
                                    <td>
                                        <span class="price-tag"><?php echo esc_html($v['price']); ?></span>
                                    </td>
                                    <?php foreach ($warehouses as $wh): ?>
                                        <td>
                                            <?php 
                                            $qty = $v['stocks'][$wh->id];
                                            $badge = 'badge-danger';
                                            if ($qty > 5) {
                                                $badge = 'badge-success';
                                            } elseif ($qty > 0) {
                                                $badge = 'badge-warning';
                                            }
                                            ?>
                                            <span class="badge <?php echo $badge; ?>"><?php echo $qty; ?></span>
                                        </td>
                                    <?php endforeach; ?>
                                    <td>
                                        <?php 
                                        $tot = $v['total_stock'];
                                        $badge = 'badge-danger';
                                        if ($tot > 5) {
                                            $badge = 'badge-success';
                                        } elseif ($tot > 0) {
                                            $badge = 'badge-warning';
                                        }
                                        ?>
                                        <span class="badge <?php echo $badge; ?>" style="font-weight:700;"><?php echo $tot; ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="toast" id="toast">Markdown tablosu panoya kopyalandı!</div>

    <script>
        function copyMarkdown() {
            const mdText = <?php echo json_encode($md); ?>;
            navigator.clipboard.writeText(mdText).then(() => {
                showToast("Markdown tablosu panoya kopyalandı!");
            }).catch(() => {
                showToast("Panoya kopyalama başarısız oldu.");
            });
        }

        function downloadXML() {
            const xmlText = <?php echo json_encode($xml_string); ?>;
            const blob = new Blob([xmlText], { type: "application/xml;charset=utf-8;" });
            const link = document.createElement("a");
            link.href = URL.createObjectURL(blob);
            link.setAttribute("download", "urun-bilgi-listesi.xml");
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        function downloadCSV() {
            const csvText = '\uFEFF' + <?php echo json_encode($csv_output); ?>;
            const blob = new Blob([csvText], { type: "text/csv;charset=utf-8;" });
            const link = document.createElement("a");
            link.href = URL.createObjectURL(blob);
            link.setAttribute("download", "urun-bilgi-listesi.csv");
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        function downloadPDF() {
            const element = document.querySelector('.table-card');
            const opt = {
                margin:       10,
                filename:     'urun-bilgi-listesi.pdf',
                image:        { type: 'jpeg', quality: 0.98 },
                html2canvas:  { scale: 2, useCORS: true },
                jsPDF:        { unit: 'mm', format: 'a4', orientation: 'landscape' }
            };
            html2pdf().set(opt).from(element).save();
        }

        function showToast(message) {
            const toast = document.getElementById("toast");
            toast.textContent = message;
            toast.classList.add("show");
            setTimeout(() => {
                toast.classList.remove("show");
            }, 3000);
        }
    </script>
</body>
</html>
