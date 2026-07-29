# Hızlı Kasa Birleşik Yazdırma Mimarisi & Sürücü Standartları

Bu belge, Hızlı Kasa eklentisindeki tüm fiş (Kasa Satış, Satış Raporları Reprint, İşlem Görmüş Fiş, Z-Raporu) ve etiket/barkod yazdırma altyapısının **V2 REST API**, **OOP PHP Print Engine**, **Modüler Şablonlar** ve **JS Driver Strategy Pattern (PrintCore)** mimarisini tanımlar.

---

## 1. Mimari Genel Bakış

Tüm yazdırma süreçleri 4 katmanlı bir yapı üzerinden yönetilir:

```
[ İstemci JS Modülleri (Kasa, Raporlar, Z-Raporu, Barkod) ]
                           │
                           ▼
          [ HK.PrintCore (Orkestratör Motoru) ]
                           │
             ┌─────────────┴─────────────┐
             ▼                           ▼
[ HelperAppDriver (Sessiz EXE) ] [ BrowserNativeDriver (Fallback) ]
```

1. **İstemci Çağrısı:** `HK.PrintCore.print({ type: 'order'|'zreport'|'barcode', id: 1452 })`
2. **V2 REST API İsteği:** `/wp-json/hizli-kasa/v2/print/{type}/{id}` endpoint'ine istek atılır.
3. **Akıllı Veri Builder (`Hizli_Kasa_Order_Print_Builder`):**
   - Sipariş hiç değişmediyse: **Orijinal POS Satış Fişi (`receipt-original.php`)** render edilir.
   - Sipariş iade, değişim, indirim veya kalem revizyonu gördüyse: **İşlem Görmüş Fiş (`receipt-modified.php`)** (eksi iadeler, net kalan ürünler, değişim farkı ve işlem tarihçesi rozeti) render edilir.
4. **Sürücü Seçimi (Driver Strategy Pattern):**
   - Yerel `hizli-kasa-print-helper.exe` servisi (port 5001-5010) aktif ise `HelperAppDriver` ile tarayıcı popup'ı olmadan sessiz basılır.
   - Servis kapalıysa veya sessiz yazdırma pasifse `BrowserNativeDriver` ile tarayıcı `window.print()` ve `@page` dinamik CSS kuralları devreye girer.

---

## 2. Klasör ve Dosya Hiyerarşisi

```text
hizli-kasa/
├── includes/
│   └── printing/                           # Birleşik PHP Yazdırma Çekirdeği
│       ├── class-print-engine.php          # Ana Orkestratör ve Render Engine
│       ├── class-print-payload.php         # Standart JSON Veri Yapıcı & Normalizer
│       ├── builder/                        # Veri Toplayıcılar (Data Builders)
│       │   ├── class-order-print-builder.php    # Sipariş & Değişim/İade Veri Toplayıcı
│       │   ├── class-zreport-print-builder.php  # Gün Sonu (Z-Raporu) Veri Toplayıcı
│       │   └── class-barcode-print-builder.php  # Barkod / Etiket Veri Toplayıcı
│       └── templates/                      # PHP/HTML Fiş & Etiket Şablonları
│           ├── base-template.php           # Şablon Taban Sınıfı / Helper
│           ├── receipt-original.php        # Orijinal POS Satış Fişi
│           ├── receipt-modified.php        # İşlem Görmüş (Değişim/İade/Edit) Fişi
│           ├── receipt-zreport.php         # Gün Sonu Z-Raporu Fişi
│           └── label-barcode.php           # Barkod & Ürün Etiketi Şablonu
│
├── includes/api/v2/controllers/
│   └── class-api-print.php                 # V2 REST API Print Endpoint Controller
│
└── assets/
    └── js/
        └── printing/                       # Birleşik JS Yazdırma Katmanı
            ├── print-core.js               # HizliKasa.PrintCore (Çekirdek Yönetici)
            └── drivers/                    # Driver Strategy Pattern Sürücüleri
                ├── base-driver.js          # BasePrintDriver Taban Sınıfı
                ├── helper-app-driver.js    # Sessiz EXE Sürücüsü (Port 5001-5010 REST)
                └── browser-native-driver.js # Tarayıcı Native (@page & window.print)
```

---

## 3. V2 REST API Endpoint'leri

Tüm endpoint'ler `Hizli_Kasa_API_Controller_Base` tabanlı olup No-Cache garantilidir:

| Endpoint URL | Metot | Açıklama |
| :--- | :--- | :--- |
| `/wp-json/hizli-kasa/v2/print/order/{id}` | `GET` | Sipariş ID'ye göre orijinal veya işlem görmüş fiş HTML ve PrintPayload verisini döner. |
| `/wp-json/hizli-kasa/v2/print/z-report` | `GET` | Belirtilen tarih/kasa ID için Z-Raporu fiş HTML verisini döner. |
| `/wp-json/hizli-kasa/v2/print/barcode` | `POST` | Ürün/varyasyon barkod etiket HTML verisini döner. |

---

## 4. Kullanım Örnekleri (Frontend JS)

### 4.1. Sipariş Fişi Yazdırma (Raporlar veya POS Checkout)
```javascript
// Sipariş değişmediyse otomatik Orijinal Fiş, değiştiyse İşlem Görmüş Fiş basar:
await HK.PrintCore.print({ type: 'order', id: 1452 });
```

### 4.2. Gün Sonu Z-Raporu Yazdırma
```javascript
await HK.PrintCore.print({
    type: 'zreport',
    kasa_no: '1',
    depo_id: 2,
    tarih: '2026-07-28'
});
```

### 4.3. Barkod Etiketi Yazdırma
```javascript
await HK.PrintCore.print({
    type: 'barcode',
    product_id: 1051,
    variation_id: 0,
    qty: 5
});
```

---

## 5. Geliştirici Standartları & Kurallar

1. **Doğrudan String HTML Birleştirmeden Kaçının:** Fiş tasarımları JS içinde `html += '...'` şeklinde değil, `includes/printing/templates/` altındaki modüler PHP şablonlarında yapılmalıdır.
2. **Sürücü Ekleme:** Yeni bir yazdırma yöntemi (örneğin WebUSB) eklenirken `assets/js/printing/drivers/base-driver.js` sınıfından türetilen yeni bir sürücü oluşturulmalıdır.
3. **No Native Dialogs:** Yazdırma hatalarında asla tarayıcının `alert()` kutusu kullanılmamalı, `HK.ModalManager` veya `HK.UI` tercih edilmelidir.

---

## 6. Termal Barkod Yazdırma & Crisp-Rendering Standartları

1. **Subpixel & Anti-Aliasing (Grileşme) Önleme:** Termal yazıcılar 1-bit monochrome mantığıyla çalışır. Çizgi kalınlıklarında tarayıcının kenar yumuşatma yapmaması için tüm barkod SVG elemanlarında `shape-rendering="crispEdges"` niteliği ve `image-rendering: pixelated; image-rendering: crisp-edges;` CSS kuralları zorunludur.
2. **Dikey Yükseklik Entegrasyonu:** Barkod okuyucuların hızlı ve toleranslı taraması için etiket düzeninde dikey yükseklik maksimumda tutulmalıdır (`.barcode-container` 16mm, `JsBarcode` / SVG `height: 75px`). Negatif `transform: translateY` hizalama hack'lerinden kaçınılmalı, temiz flexbox düzeni kullanılmalıdır.
