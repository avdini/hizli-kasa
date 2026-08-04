# Sevk Fişi Termal Yazdırma (Thermal Receipt Printing) Walkthrough

## Summary of Changes
Implemented a 58mm and 80mm compatible thermal receipt printing feature ("Sevk Transfer Fişi") for shipments accessible via a **"🖨️ Sevk Fişi Yazdır"** button inside the **Sevk Detay Modalı** and **Sevk Kabul Detay** panels.

### Key Components Added/Modified:
1. **Shipment Print Data Builder (`class-shipment-print-builder.php`)**:
   - [`includes/printing/builder/class-shipment-print-builder.php`](file:///c:/Users/fikri/Desktop/avdini.com/hizli-kasa/includes/printing/builder/class-shipment-print-builder.php)
   - Fetches shipment metadata, source & destination warehouses, item list, SKU codes, and total quantities.

2. **Thermal Receipt Template (`receipt-shipment.php`)**:
   - [`includes/printing/templates/receipt-shipment.php`](file:///c:/Users/fikri/Desktop/avdini.com/hizli-kasa/includes/printing/templates/receipt-shipment.php)
   - Built with `%100` fluid width, `#000000` text, Consolas monospace font.
   - Features 2-line compact product rows with a `[  ]` check bracket for physical pen checkmarks.

3. **V2 REST API Print Controller (`class-api-print.php`)**:
   - [`includes/api/v2/controllers/class-api-print.php`](file:///c:/Users/fikri/Desktop/avdini.com/hizli-kasa/includes/api/v2/controllers/class-api-print.php#L32-L42)
   - Registered `/wp-json/hizli-kasa/v2/print/shipment/{id}` REST route.

4. **PrintCore Engine Integration (`print-core.js`)**:
   - [`assets/js/printing/print-core.js`](file:///c:/Users/fikri/Desktop/avdini.com/hizli-kasa/assets/js/printing/print-core.js#L72-L80)
   - Added endpoint routing for `shipment` and `sevk` print types.

5. **UI Integration (`sevk-manager.js`)**:
   - [`assets/js/modules/sevk-manager.js`](file:///c:/Users/fikri/Desktop/avdini.com/hizli-kasa/assets/js/modules/sevk-manager.js#L630-L775)
   - Embedded **"🖨️ Sevk Fişi Yazdır"** button inside Sevk Detay Modalı and Sevk Kabul Detay views.

---

## Verification
- Verified PHP syntax with `php -l` on all modified/new PHP files.
- Confirmed REST API route handling and PrintCore JS integration.
