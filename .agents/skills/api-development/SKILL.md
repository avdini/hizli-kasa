---
name: api-development
description: Hızlı Kasa V2 REST API mimarisi, controller standartları, yetkilendirme, no-cache kuralları ve endpoint yapılarını içerir. REST API geliştirme veya endpoint düzenleme işlemlerinde bu beceri rehberini kullanın.
---

# Hızlı Kasa V2 REST API Dokümantasyonu

Bu dosya, Hızlı Kasa V2 API'lerinin endpoint açıklamalarını, girdi/çıktı formatlarını ve protokollerini içerir.

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

---

### 3.2. Sevk (Shipment) Yönetimi (V2)
Tüm sevk işlemleri `/wp-json/hizli-kasa/v2/sevk/` önekiyle nesne yönelimli controller üzerinden yürütülmektedir.

- **`active-draft`**: Aktif taslak sevk denetimi (`GET`)
- **`delete-draft`**: Taslak sevk silme (`POST`)
- **`kalem-ekle`**: Sevke ürün ekleme (`POST`)
- **`kalem-miktar-guncelle`**: Kalem adet güncelleme (`POST`)
- **`kalem-sil`**: Sevten kalem çıkarma (`POST`)
- **`detay/{id}`**: Sevk detay ve kalem listesi (`GET`)

---

### 3.3. Kullanıcı Ses Ayarları (User Sound Settings)
- **URL:** `/wp-json/hizli-kasa/v2/user/sound-settings` (`POST`)

---

### 3.4. QR Ödeme Yönetimi & Sanal POS Uyumluğu
- **URL:** `/wp-json/hizli-kasa/v2/qr-payment/create` (`POST`)

---

### 3.5. Detaylı Ürün & Stok Arama API'si (Stock Search)
- **URL:** `/wp-json/hizli-kasa/v2/products/stock-search` (`GET`)
- **URL:** `/wp-json/hizli-kasa/v2/products/filter-options` (`GET`)
