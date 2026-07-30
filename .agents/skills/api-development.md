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

---

## 3.2. Sevk (Shipment) Yönetimi (V2)
Tüm sevk işlemleri `/wp-json/hizli-kasa/v2/sevk/` önekiyle nesne yönelimli controller üzerinden yürütülmektedir.

### 3.2.1. Aktif Taslak Sevk Kontrolü
Seçilen kaynak depodan çıkış yapılmış aktif bir taslak sevk olup olmadığını denetler.

- **URL:** `/wp-json/hizli-kasa/v2/sevk/active-draft`
- **Metot:** `GET`
- **Parametreler:** `kaynak_depo_id` (Zorunlu, int)

#### Örnek Başarılı Yanıt (Taslak Var):
```json
{
  "success": true,
  "data": {
    "sevk": {
      "id": 14,
      "sevk_no": "SVK-20260630-0001",
      "kaynak_depo_id": 1,
      "kaynak_depo_adi": "Merkez Depo",
      "hedef_depo_id": 2,
      "hedef_depo_adi": "Şube Depo",
      "durum": "taslak",
      "durum_label": "Taslak",
      "toplam_cesit": 0,
      "toplam_adet": 0,
      "created_at": "2026-06-30 14:40:00",
      "updated_at": "2026-06-30 14:40:00"
    }
  },
  "errors": null,
  "meta": {
    "timestamp": "2026-06-30 14:41:00",
    "version": "v2"
  }
}
```

### 3.2.2. Taslak Sevk Silme / İptali
Oluşturulmuş ancak onay sürecine gönderilmemiş taslak sevki ve tüm kalemlerini veritabanından siler.

- **URL:** `/wp-json/hizli-kasa/v2/sevk/delete-draft`
- **Metot:** `POST`
- **Girdi Gövdesi (JSON):**
  ```json
  {
    "sevk_id": 14
  }
  ```

#### Örnek Başarılı Yanıt:
```json
{
  "success": true,
  "data": {
    "message": "Taslak sevk başarıyla silindi."
  },
  "errors": null,
  "meta": {
    "timestamp": "2026-06-30 14:42:00",
    "version": "v2"
  }
}
```

### 3.2.3. Sevk Mutasyon İşlemleri (Performans Uyumlu)
Barkod tarama, kalem ekleme, silme ve adet güncellemeleri gibi sık tetiklenen mutasyon isteklerinde (kalem-ekle, kalem-sil, kalem-miktar-guncelle, teslim-miktar-guncelle) tüm listeyi dönmek yerine sadece sevk özetini ve işlem yapılan ilgili kalemi döner.

#### A. Kalem Ekleme
- **URL:** `/wp-json/hizli-kasa/v2/sevk/kalem-ekle`
- **Metot:** `POST`
- **Girdi Gövdesi (JSON):**
  ```json
  {
    "sevk_id": 14,
    "sku": "BARKOD123",
    "qty": 1
  }
  ```

##### Örnek Başarılı Yanıt:
```json
{
  "success": true,
  "data": {
    "sevk_id": 14,
    "toplam_cesit": 1,
    "toplam_adet": 1.0,
    "durum": "taslak",
    "durum_label": "Taslak",
    "updated_at": "2026-06-30 15:20:00",
    "kalem": {
      "id": 45,
      "product_id": 12,
      "variation_id": 0,
      "sku": "BARKOD123",
      "urun_adi": "Örnek Ürün",
      "gonderilen_adet": 1.0,
      "teslim_alinan_adet": null,
      "image": "http://site.com/wp-content/uploads/img.jpg"
    }
  },
  "errors": null,
  "meta": {
    "timestamp": "2026-06-30 15:20:00",
    "version": "v2"
  }
}
```

#### B. Miktar Güncelleme
- **URL:** `/wp-json/hizli-kasa/v2/sevk/kalem-miktar-guncelle`
- **Metot:** `POST`
- **Girdi Gövdesi (JSON):**
  ```json
  {
    "sevk_id": 14,
    "kalem_id": 45,
    "qty": 5
  }
  ```

#### C. Kalem Silme
- **URL:** `/wp-json/hizli-kasa/v2/sevk/kalem-sil`
- **Metot:** `POST`
- **Girdi Gövdesi (JSON):**
  ```json
  {
    "sevk_id": 14,
    "kalem_id": 45
  }
  ```
*(Not: Kalem silme başarılı yanıtında `kalem` alanı `null` döner.)*

### 3.2.4. Sevk Detay Sorgusu (Tüm Kalemler & Görseller)
Bir sevkin tüm detaylarını ve içerdiği tüm kalemleri görselleriyle birlikte çeker. Bu endpoint görselleri batch (toplu) SQL JOIN sorgusu ile N+1 problemine yol açmadan performanslı bir şekilde çeker.

- **URL:** `/wp-json/hizli-kasa/v2/sevk/detay/{id}`
- **Metot:** `GET`

##### Örnek Başarılı Yanıt:
```json
{
  "success": true,
  "data": {
    "sevk": {
      "id": 14,
      "sevk_no": "SVK-20260630-0001",
      "kaynak_depo_id": 1,
      "kaynak_depo_adi": "Merkez Depo",
      "hedef_depo_id": 2,
      "hedef_depo_adi": "Şube Depo",
      "durum": "taslak",
      "durum_label": "Taslak",
      "toplam_cesit": 2,
      "toplam_adet": 6.0,
      "not_gonderici": "",
      "not_alici": "",
      "created_at": "2026-06-30 14:40:00",
      "updated_at": "2026-06-30 15:20:00",
      "kalemler": [
        {
          "id": 45,
          "product_id": 12,
          "variation_id": 0,
          "sku": "BARKOD123",
          "urun_adi": "Örnek Ürün",
          "gonderilen_adet": 5.0,
          "teslim_alinan_adet": null,
          "image": "http://site.com/wp-content/uploads/img.jpg"
        }
      ]
    }
  },
  "errors": null,
  "meta": {
    "timestamp": "2026-06-30 15:21:00",
    "version": "v2"
  }
}
```

---

### 3.3. Kullanıcı Ses Ayarları (User Sound Settings)
Kullanıcının terminal bildirim ses seviyesini ve ses çeşidini (preset) kaydeder.

- **URL:** `/wp-json/hizli-kasa/v2/user/sound-settings`
- **Metot:** `POST`
- **İçerik Tipi (Header):** `Content-Type: application/json`

#### Girdi Parametreleri (JSON):
| Parametre | Tip | Zorunlu | Açıklama |
| :--- | :--- | :--- | :--- |
| `volume` | `int` | Evet | 0 ile 100 arasında ses seviyesi yüzdesi. |
| `preset` | `string` | Evet | Ses efekti tipi (`classic`, `soft`, `retro`, `digital`, `sharp_click`, `high_alert`). |

#### Örnek İstek Gövdesi (Payload):
```json
{
  "volume": 80,
  "preset": "soft"
}
```

#### Örnek Başarılı Yanıt (200 OK):
```json
{
  "success": true,
  "data": {
    "volume": 80,
    "preset": "soft",
    "message": "Ses ayarları kaydedildi."
  },
  "errors": null,
  "meta": {
    "timestamp": "2026-06-30 15:58:00",
    "version": "v2"
  }
}
```

---

### 3.4. QR Ödeme Yönetimi & Sanal POS Uyumluğu (QR Payment)
Kasadan QR Taksitli Ödeme siparişi oluşturur ve Sanal POS eklentilerinin (iyzico, PayTR, Sipay vb.) teslimat adresi gereksinimlerini (`ShippingAddress gönderilmesi zorunludur`) otomatik karşılar.

- **URL:** `/wp-json/hizli-kasa/v2/qr-payment/create`
- **Metot:** `POST`
- **İçerik Tipi:** `application/json`

#### Adres Enjeksiyon Mimarisi:
330: 
331: ---
332: 
333: ### 3.5. Detaylı Ürün & Stok Arama API'si (Stock Search)
334: Gelişmiş süzme, varyasyon/toplam stok hesaplaması, nitelikler, kategoriler, markalar ve tarih bazlı arama.
335: 
336: #### 3.5.1. Stok Arama Endpoint'i
337: - **URL:** `/wp-json/hizli-kasa/v2/products/stock-search`
338: - **Metot:** `GET`
339: - **Parametreler:**
340:   - `scope`: `all` | `parent_sum` | `variation` | `simple`
341:   - `min_stock` & `max_stock`: Stok miktarı süzgeci (Örn: min:1, max:1)
342:   - `stock_status`: `all` | `instock` | `lowstock` | `outofstock`

### 3.5. Detaylı Ürün & Stok Arama API'si (Stock Search)
Gelişmiş süzme, varyasyon/toplam stok hesaplaması, nitelikler, kategoriler, markalar ve tarih bazlı arama.

#### 3.5.1. Stok Arama Endpoint'i
- **URL:** `/wp-json/hizli-kasa/v2/products/stock-search`
- **Metot:** `GET`
- **Parametreler:**
  - `scope`: `all` | `parent_sum` | `variation` | `simple`
  - `min_stock` & `max_stock`: Stok miktarı süzgeci (Örn: min:1, max:1)
  - `stock_status`: `all` | `instock` | `lowstock` | `outofstock`
  - `category_ids`, `brand_ids`, `tag_ids`: Virgülle ayrılmış ID listesi
  - `attribute_slug` & `attribute_term_ids`: Nitelik (pa_size vb.) süzgeci
  - `search`: Ürün adı, SKU veya Barkod
  - `days_since_last_sale`: Son X gündür satılmayanlar (Örn: 30)
  - `sort_by`: `date_desc`, `title_asc`, `stock_asc`, `price_asc` vb.
  - `page` & `per_page`: Sayfalandırma

- **Yanıt Meta Payload (`data.meta`):**
  - `search`: Arama terimi
  - `search_strategy`: `EXACT_SKU_MATCH` | `PARTIAL_LIKE_SEARCH` | `NO_SEARCH`
  - `exact_sku_match`: `boolean` (Tam SKU / Barkod eşleşmesi bulunup bulunmadığı)
  - `exact_sku_product_id`: `int` (Varsa tam eşleşen ürün/barkod ID'si)

#### 3.5.2. Filtre Opsiyonları Endpoint'i
Panel UI kontrollerini doldurmak için kategorileri, markaları, nitelikleri ve etiketleri döner.
- **URL:** `/wp-json/hizli-kasa/v2/products/filter-options`
- **Metot:** `GET`
