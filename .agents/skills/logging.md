# Hızlı Kasa Loglama Standartları ve AI Agent Rehberi

Bu doküman, Hızlı Kasa WooCommerce POS eklentisinde geliştirme yapan tüm AI agent'lar ve yazılımcılar için geçerli **ZORUNLU** loglama standartlarını ve mimarisini açıklar.

---

## 1. Temel İlkeler ve Kurallar

1. **Rastgele `error_log()` Kullanımı Yasaktır:** Kod içerisinde gelişigüzel `error_log("HK Debug: ...")` veya `print_r()` yazılması yasaktır. Tüm işlem ve durum logları `Hizli_Kasa_Logger` sınıfı üzerinden yönetilmelidir.
2. **İki Seviyeli Mesaj Yapısı (Dual-Level Logging):**
   - **`message` (Kullanıcı Mesajı):** Mağaza sahibinin ve kasiyerin anlayabileceği sade, temiz ve anlaşılır Türkçe metin.
   - **`context` (Geliştirici Detayı):** IP adresi, stack trace, API request/response JSON payload'ları, eski/yeni değerler gibi tüm teknik detaylar `$context` dizisi içinde saklanır.
3. **Correlation ID (İstek Kimliği - `request_id`):** Bir işlem (ör: sipariş alma, stok düşme, barkod basma) başladığında otomatik bir `request_id` üretilir. Tüm alt loglar bu ID ile etiketlenir.

---

## 2. Log Grupları (Channels) ve Kullanım Alanları

Eklenti içindeki her işlem önceden tanımlanmış şu altı kanaldan (`channel`) birine ait olmak zorundadır:

| Kanal (`channel`) | Kapsadığı İşlemler | Örnek Mesaj |
| :--- | :--- | :--- |
| **`pos`** | Kasa sipariş oluşturma, sepet düzenleme, parçalı ödemeler, fiş/fatura yazdırma, vardiya açılış/kapanış. | `Sipariş #1042 başarıyla tamamlandı (Nakit).` |
| **`stock`** | Stok düşme/artırma, manuel stok düzeltmeleri, depolar arası sevkler, stok sayım oturumları. | `Depo: Merkez - Ürün #85769 stoğu 10 adet azaltıldı.` |
| **`sku`** | Otomatik barkod/SKU üretimi, varyasyon SKU eşleme, toplu SKU cron düzeltmeleri. | `Ürün #85769 için AVD-85769 SKU'su üretildi.` |
| **`payment`** | QR ödeme alma, Sanal POS / Iyzico / PayTR entegrasyonları, nakit/kart tahsilat durumları. | `QR Ödeme İsteği Başarısız: Müşteri bakiyesi yetersiz.` |
| **`sync`** | WooCommerce ürün/sipariş senkronizasyonu, webhook entegrasyonları, arka plan cron görevleri. | `WooCommerce Sipariş #1042 senkronize edildi.` |
| **`system`** | Veritabanı yükseltmeleri, eklenti ayar değişiklikleri, lisans ve oturum işlemleri. | `Eklenti ayarları güncellendi (Otomatik SKU: Aktif).` |

---

## 3. Logger Sınıfı (`Hizli_Kasa_Logger`) Kullanım Standartları

Kod içerisinde log yazarken `Hizli_Kasa_Logger` helper metodları kullanılır:

```php
// 1. POS İşlemi Loglama
Hizli_Kasa_Logger::pos(
    'Sipariş #1042 başarıyla oluşturuldu.',
    [
        'order_id' => 1042,
        'total'    => 250.00,
        'items_count' => 3
    ],
    'info',
    'order',
    1042
);

// 2. Stok Hareketi Loglama
Hizli_Kasa_Logger::stock(
    'Ürün #85769 stoğu düşüldü.',
    ['old_stock' => 15, 'new_stock' => 10],
    'info',
    'product',
    85769
);

// 3. Otomatik SKU Loglama
Hizli_Kasa_Logger::sku(
    'Varyasyon #85769 için SKU üretildi.',
    ['generated_sku' => 'AVD-85769'],
    'info',
    'variation',
    85769
);

// 4. Kritik Hata Loglama (Hem DB'ye hem WP debug.log'a yazar)
Hizli_Kasa_Logger::error(
    'Ödeme API Bağlantı Hatası: Sunucu yanıt vermiyor.',
    [
        'channel' => 'payment',
        'gateway' => 'iyzipo',
        'trace'   => $exception->getTraceAsString()
    ]
);
```

---

## 4. Veritabanı Şeması (`wp_hizli_kasa_logs`)

Loglar `wp_hizli_kasa_logs` özel tablosunda aşağıdaki indeksli yapıda saklanır:

```sql
CREATE TABLE wp_hizli_kasa_logs (
    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    request_id VARCHAR(64) NOT NULL,
    channel VARCHAR(32) NOT NULL,
    level VARCHAR(16) NOT NULL DEFAULT 'info',
    message TEXT NOT NULL,
    user_id BIGINT(20) UNSIGNED DEFAULT 0,
    object_type VARCHAR(32) DEFAULT NULL,
    object_id BIGINT(20) UNSIGNED DEFAULT 0,
    context LONGTEXT DEFAULT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY  (id),
    KEY request_id (request_id),
    KEY channel (channel),
    KEY level (level),
    KEY user_id (user_id),
    KEY object_lookup (object_type, object_id),
    KEY created_at (created_at)
) DEFAULT CHARSET=utf8mb4;
```

---

## 5. API V2 Endpoints Standartları

Logların WooCommerce Admin UI veya dış istemciler tarafından çekilmesi için `Hizli_Kasa_API_Logs_Controller` sınıfı kullanılır (`Hizli_Kasa_API_Controller_Base` miras alınır):

- `GET /wp-json/hizli-kasa/v2/logs`: Filtrelenmiş ve sayfalanmış log listesini döner.
- `GET /wp-json/hizli-kasa/v2/logs/trace/{request_id}`: Bir işleme ait tüm zaman çizelgesini (trace) kronolojik sırayla döner.
- `DELETE /wp-json/hizli-kasa/v2/logs/clear`: Logları siler/temizler.
- `GET /wp-json/hizli-kasa/v2/logs/stats`: Günlük hata ve modül bazlı istatistikleri döner.

---

## 6. Otomatik Temizleme (Retention Cron)

Veritabanının şişmesini engellemek için `Hizli_Kasa_Logger::purge_old_logs()` metodu günlük WordPress Cron ile tetiklenir:
- Varsayılan saklama süresi **14 Gün**dür (Ayarlardan 7, 14, 30 gün seçilebilir).
- Tablodaki toplam satır sayısı 50.000'i aşarsa en eski kayıtlar otomatik temizlenir.
