# Sevk Fişi Termal Yazdırma (Thermal Receipt Printing) Tasarım Dokümanı

## 1. Genel Bakış
Hızlı Kasa POS sisteminde depolar arası yapılan transferler için Sevk Detay Modalı üzerinden 80mm ve 58mm termal fiş yazıcılarıyla tam uyumlu, dikeyde kağıt tasarrufu sağlayan, fiziksel mal kabul ve paketlemede kullanılabilir **Sevk Transfer Fişi** yazdırma özelliğinin eklenmesi.

---

## 2. Kullanıcı Akışı & Tetikleme Noktası
- **Nereden Çıkartılacak:** Yalnızca **Sevk Detay Modalı** (`sevk-detay-modal` / `renderIncomingDetail` & `renderCikis` modal görünümleri) içerisindeki **"🖨️ Sevk Fişi Yazdır"** butonu aracılığıyla.
- **Yazdırma Motoru Entegrasyonu:** `.agents/context/unified-printing-architecture.md` standartlarına uygun olarak istemci tarafında `HK.PrintCore.print({ type: 'shipment', id: sevkId })` çağrısı yapılacak.

---

## 3. Fiş İçeriği ve Şablon Yapısı

### 3.1. Üst Bilgi (Header)
- **Firma Adı:** Yalnızca metin (Logo görseli kullanılmayacak).
- **Başlık:** `SEVK TRANSFER FİŞİ`
- **Sevk No & Barkod:** Sevk No (`SVK-XXXX`) ve taranabilir SVG/İmg barkod görseli.
- **Tarih & Saat:** Sevkin oluşturulma/yazdırılma tarihi.
- **Kaynak & Varış Deposu:** 
  - `ÇIKIŞ DEPOSU: [Kaynak Depo Adı]`
  - `VARIŞ DEPOSU : [Hedef Depo Adı]`
  - `DURUM        : [Taslak / Bekleyen / Yolda / Tamamlandı]`

### 3.2. Kalemler / Ürün Listesi (2 Satırlı Compact Grid + Tik Kutusu)
Her ürün kalemi dikeyde az yer kaplaması ve 58mm/80mm kağıtlarda taşma yapmaması için 2 satırlı blok halinde basılır:

```text
--------------------------------------------------
[  ] KADIN SİYAH DERİ CEKET (L)           x 2 ADET
     SKU: KDN-SYH-L

[  ] KIRMIZI SPOR AYAKKABI (42)            x 1 ADET
     SKU: SP-AYK-42
--------------------------------------------------
```

- **`[  ]` Kontrol Kutusu:** İşçilerin teslim alma/paketleme sırasında kalemle rahatça tik atabilmesi için satır başında 3 karakterlik boş kutu.
- **Ürün Adı & Adet:** Ürün adı sol tarafta esnek alan kaplar, Adet miktarı (`x N ADET`) sağa dayalı ve kalın (bold) basılır.
- **SKU Satırı:** Alt satırda 10px font boyutuyla hafif girintili olarak basılır.

### 3.3. Alt Bilgi (Footer) & İmzalar
- **Özet:** `TOPLAM: X Çeşit / Y Adet`
- **Gönderici Notu:** Varsa sevk notu basılır.
- **İmza Alanları:**
  - `[ Teslim Eden ]` (İmza Alanı)
  - `[ Teslim Alan ]` (İmza Alanı)

---

## 4. Teknik Mimari & Standartlar

1. **PHP Builder:** `includes/printing/builder/class-shipment-print-builder.php`
   - Sevk verilerini (depolar, kalemler, barkod URL, adet toplamları) normalize eder.
2. **PHP Template:** `includes/printing/templates/receipt-shipment.php`
   - HTML/CSS şablonu. `%100` mutlak siyah (`#000000`), `Consolas, "Segoe UI Mono", monospace` font ailesi ve `font-weight: 600/bold` kurallarına uyar.
3. **V2 REST API Endpoint:** `includes/api/v2/controllers/class-api-print.php`
   - `GET /wp-json/hizli-kasa/v2/print/shipment/{id}` endpoint'i eklenir. No-Cache garantilidir.
4. **CSS & Yazıcı Uyumluluğu (58mm & 80mm Fluid Layout):**
   - `%100` genişlik, flexbox space-between yerleşimi.
   - `@page { margin: 0; size: auto; }` kuralı ile sıfır kenar boşluğu.

---

## 5. Uygulama Adımları
1. **Veri & Şablon Katmanı:**
   - `class-shipment-print-builder.php` oluştur.
   - `receipt-shipment.php` şablonunu hazırla.
   - `class-api-print.php` içinde `shipment` tipini kaydet.
2. **Frontend Entegrasyonu:**
   - `sevk-manager.js` içinde Sevk Detay Modalı'na **"🖨️ Sevk Fişi Yazdır"** butonunu ve tıklama olayını ekle.
3. **Test & Doğrulama:**
   - Termal yazdırma çıktısını ve API yanıtını doğrula.
