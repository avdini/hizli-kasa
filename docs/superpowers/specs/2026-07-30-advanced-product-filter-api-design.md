# Gelişmiş Ürün & Varyasyon Filtreleme V2 API Tasarımı

**Tarih:** 2026-07-30  
**Endpoint:** `POST /wp-json/hizli-kasa/v2/stock-search` & `GET /wp-json/hizli-kasa/v2/stock-search`  
**Sorumlu Controller:** `includes/api/v2/controllers/class-api-stock-search.php`  
**Sınıf Bağlantısı:** `Hizli_Kasa_API_Controller_Base`  

---

## 1. Amaç ve Kapsam

Bu tasarım dokümanı, Hızlı Kasa Stok Terminali / Ürünler arama motorundaki mevcut sınırlı tekli nitelik aramasını (tek `attribute_slug` + tek `attribute_term_ids`), karmaşık varyasyon kombinasyonlarını ve mantıksal grupları destekleyen esnek bir V2 API yapısına dönüştürmeyi amaçlar.

### Desteklenen Örnek Senaryo:
* **Grup 1:** (Renk: Mavi **AND** Beden: S **AND** Varyasyon Stoğu = 1)  
* **VEYA (OR)**  
* **Grup 2:** (Renk: Turuncu **AND** Beden: XL **AND** Varyasyon Stoğu < 3)  
* **Arama Kapsamı (Scope):** Tüm Ürünler (`all`), Sadece Basit Ürünler (`simple`) veya Sadece Varyasyonlar (`variation`)  
* **Stok Hesaplama Modu:** Varyasyon Bazlı Stok (`variation`) veya Toplam Ürün Stoğu (`parent_total`)  

---

## 2. API Sözleşmesi ve Request Payload Yapısı

Endpoint isteği hem `GET` (JSON string veya URL query param) hem de `POST` (JSON body) yöntemlerini destekler. Geriye dönük uyumluluk %100 korunur.

### 2.1. İsteğe Eklenen Yeni Parametreler

| Parametre Adı | Tip | Varsayılan | Açıklama |
| :--- | :--- | :--- | :--- |
| `scope` | `string` | `'all'` | `'all'` (Tüm Ürünler/Varyasyonlar), `'simple'` (Sadece Basit Ürünler), `'variation'` (Sadece Varyasyonlar) |
| `stock_calc_mode` | `string` | `'variation'` | `'variation'` (Tekil varyasyon stoğu), `'parent_total'` (Tüm alt varyasyonların stok toplamı) |
| `filter_groups` | `array` | `[]` | Mantıksal kural grupları dizisi (Her grup kendi içinde `AND`, gruplar arası `OR` olarak işlenir) |

### 2.2. JSON Request Payload Örneği

```json
{
  "search": "",
  "category_ids": [12],
  "brand_ids": [],
  "scope": "all",
  "stock_calc_mode": "variation",
  "filter_groups": [
    {
      "attributes": {
        "pa_renk": ["mavi"],
        "pa_beden": ["s"]
      },
      "stock_operator": "=",
      "stock_value": 1
    },
    {
      "attributes": {
        "pa_renk": ["turuncu"],
        "pa_beden": ["xl"]
      },
      "stock_operator": "<",
      "stock_value": 3
    }
  ],
  "page": 1,
  "per_page": 20
}
```

### 2.3. Group Elemanı Şeması

```json
{
  "attributes": {
    "taxonomy_slug": ["term_slug_or_id_1", "term_slug_or_id_2"]
  },
  "stock_operator": "= | != | < | <= | > | >=",
  "stock_value": 0
}
```

---

## 3. SQL Sorgu Motoru Mimarisi (`build_complex_group_sql`)

`class-api-stock-search.php` denetleyicisine yeni bir yardımcı metot eklenecektir: `build_complex_group_sql(array $filter_groups, string $stock_calc_mode)`.

### 3.1. Çalışma Prensibi

1. **Grup İçi (AND):** Bir grup içindeki her nitelik (Örn: `pa_renk=mavi` VE `pa_beden=s`) ve o gruba ait stok kısıtı (`stock_value = 1`) SQL tarafında alt sorgu (subquery) / `EXISTS` şartları ile `AND` ile bağlanır.
2. **Gruplar Arası (OR):** Dizi içindeki kartlar (Gruplar) ana SQL `WHERE` bloğuna `OR` bağlacı ile eklenir:
   ```sql
   WHERE (
       /* GROUP 1 */
       ( 
         p.ID IN (SELECT object_id FROM wp_term_relationships WHERE term_taxonomy_id IN (...mavi...))
         AND p.ID IN (SELECT object_id FROM wp_term_relationships WHERE term_taxonomy_id IN (...s...))
         AND pm_stock.meta_value = 1
       )
       OR
       /* GROUP 2 */
       (
         p.ID IN (SELECT object_id FROM wp_term_relationships WHERE term_taxonomy_id IN (...turuncu...))
         AND p.ID IN (SELECT object_id FROM wp_term_relationships WHERE term_taxonomy_id IN (...xl...))
         AND pm_stock.meta_value < 3
       )
   )
   ```

3. **Parent Total Stock Modu (`stock_calc_mode = 'parent_total'`):**
   * Eğer stok hesaplama modu `parent_total` seçilirse, stok filtresi tekil varyasyon stoğuna değil, `post_parent` ile ilişkili varyasyonların `SUM(pm_stock.meta_value)` toplamına `HAVING` veya alt sorgu grubu ile uygulanır.

---

## 4. Güvenlik, Performans ve Geriye Dönük Uyumluluk

1. **Güvenlik & Sanitization:**
   * Tüm slug ve ID listeleri `sanitize_text_field` ve `absint` ile doğrulanır.
   * Mantıksal operatörler (`=`, `<`, `>`, `<=`, `>=`, `!=`) whitelist kontrolünden geçirilir; yabancı string injection engellenir.
   * Tüm dinamik SQL parçaları `$wpdb->prepare()` kullanılarak çalıştırılır.

2. **Geriye Dönük Uyumluluk (No Breaking Changes):**
   * İlemede `filter_groups` dizisi boş ise veya gönderilmemişse, denetleyici mevcut eski davranışına (`attribute_slug` ve `attribute_term_ids` okuma) geri düşer (fallback).

3. **No-Cache & Standart Yanıt:**
   * Yetkilendirme ve `Hizli_Kasa_API_Controller_Base` standartlarına tam uyum sağlanır.
   * `Hizli_Kasa_API_Response::success()` ile yanıt döndürülür.

---

## 5. Doğrulama ve Test Planı

1. **Birim / SQL Testi:**
   * Tekli grup araması (`Mavi + S + Stok 1`)
   * Çoklu OR grubu araması (`(Mavi + S + Stok 1) OR (Turuncu + XL + Stok < 3)`)
   * Toplam stok modunda arama (`parent_total`)
2. **Geriye Dönük Uyumluluk Testi:**
   * `filter_groups` parametresi olmadan yapılan eski isteklerin sorunsuz çalıştığının doğrulanması.
