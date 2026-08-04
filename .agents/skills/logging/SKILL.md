---
name: logging
description: Hızlı Kasa loglama standartları, Hizli_Kasa_Logger sınıfı, log kanalları ve DB şema standartlarını içerir. Hata yakalama, log ekleme veya loglama mimarisi işlemlerinde bu beceri rehberini kullanın.
---

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

| Kanal (`channel`) | Kapsadığı İşlemler |
| :--- | :--- |
| **`pos`** | Kasa sipariş oluşturma, sepet düzenleme, parçalı ödemeler, fiş/fatura yazdırma, vardiya açılış/kapanış. |
| **`stock`** | Stok düşme/artırma, manuel stok düzeltmeleri, depolar arası sevkler, stok sayım oturumları. |
| **`sku`** | Otomatik barkod/SKU üretimi, varyasyon SKU eşleme, toplu SKU cron düzeltmeleri. |
| **`payment`** | QR ödeme alma, Sanal POS / Iyzico / PayTR entegrasyonları, nakit/kart tahsilat durumları. |
| **`sync`** | WooCommerce ürün/sipariş senkronizasyonu, webhook entegrasyonları, arka plan cron görevleri. |
| **`system`** | Veritabanı yükseltmeleri, eklenti ayar değişiklikleri, lisans ve oturum işlemleri. |

---

## 3. Logger Sınıfı (`Hizli_Kasa_Logger`) Kullanımı

```php
Hizli_Kasa_Logger::pos('Sipariş #1042 tamamlandı.', ['order_id' => 1042], 'info', 'order', 1042);
Hizli_Kasa_Logger::stock('Ürün #85769 stoğu düşüldü.', ['old' => 15, 'new' => 10], 'info', 'product', 85769);
Hizli_Kasa_Logger::sku('Varyasyon #85769 için SKU üretildi.', ['sku' => 'AVD-85769'], 'info', 'variation', 85769);
Hizli_Kasa_Logger::error('Ödeme API Bağlantı Hatası', ['channel' => 'payment', 'trace' => $e->getTraceAsString()]);
```
