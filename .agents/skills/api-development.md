# Hızlı Kasa V2 REST API Dokümantasyonu

Bu dosya, Hızlı Kasa V2 API'lerinin endpoint açıklamalarını, girdi/çıktı formatlarını ve protokollerini içerir. API ile doğrudan çalışmayan geliştirici veya ajanların (AI Agents) gereksiz yere okumasını önlemek için `.agents/skills/` klasöründe tutulmaktadır.

---

## 1. Yetkilendirme & Güvenlik
Tüm istekler WordPress REST API yetkilendirme standartlarına tabidir.
- **Header:** `X-WP-Nonce` başlığı üzerinden geçerli WordPress nonce doğrulaması yapılmalıdır.
- **Yetki Filtresi:** İstek atan kullanıcının `hizli_kasa_can_access_app()` izni olmalıdır.

---

## 2. Global İstek/Yanıt Kuralları
- **No-Cache:** Tüm V2 API istekleri tarayıcı, sunucu, LiteSpeed ve Cloudflare önbelleklerini tamamen bypass edecek header'lar ile döner.
- **Standart Yanıt Formatı:**
  ```json
  {
    "success": true/false,
    "data": { ... },     // Başarılı ise veri nesnesi, başarısız ise null
    "errors": [ ... ],   // Hata varsa string hata listesi, yoksa null
    "meta": {
      "timestamp": "YYYY-MM-DD HH:MM:SS",
      "version": "v2"
    }
  }
  ```

---

## 3. API Endpointleri

### 3.1. Ürün Depo Kodu Güncelleme
Her deponun her ürüne veya varyasyona kendi belirlediği depo kodunu (konum/raf kodu vb.) yazmasını sağlar.

- **URL:** `/wp-json/hizli-kasa/v2/product/warehouse-code`
- **Metot:** `POST`
- **İçerik Tipi (Header):** `Content-Type: application/json`

#### Girdi Parametreleri (JSON):
| Parametre | Tip | Zorunlu | Açıklama |
| :--- | :--- | :--- | :--- |
| `product_id` | `int` | Evet | WooCommerce ana ürün ID'si. |
| `variation_id` | `int` | Hayır | WooCommerce varyasyon ID'si (basit ürünler için `0`). |
| `depo_id` | `int` | Evet | İşlem yapılan deponun ID'si (`location_id`). |
| `depo_kodu` | `string` | Hayır | Maksimum 6 haneli, alfanümerik (A-Z, 0-9) karakterler. Boş bırakılırsa kod silinir. |

#### Örnek İstek Gövdesi (Payload):
```json
{
  "product_id": 1051,
  "variation_id": 0,
  "depo_id": 2,
  "depo_kodu": "RAFA05"
}
```

#### Örnek Başarılı Yanıt (200 OK):
```json
{
  "success": true,
  "data": {
    "product_id": 1051,
    "variation_id": 0,
    "depo_id": 2,
    "depo_kodu": "RAFA05",
    "message": "Depo kodu başarıyla güncellendi."
  },
  "errors": null,
  "meta": {
    "timestamp": "2026-06-09 15:15:30",
    "version": "v2"
  }
}
```

#### Örnek Hata Yanıtı (400 Bad Request):
```json
{
  "success": false,
  "data": null,
  "errors": [
    "Depo kodu en fazla 6 haneli olmalı, yalnızca harf ve rakamlardan oluşmalıdır."
  ],
  "meta": {
    "timestamp": "2026-06-09 15:15:35",
    "version": "v2"
  }
}
```
