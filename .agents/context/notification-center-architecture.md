# Hızlı Kasa Bildirim Merkezi ve Canlı Olay (Event-Driven) Mimarisi

> **SÜRÜM & HEDEF:** Bu doküman, Hızlı Kasa POS sisteminde kullanıcılar (kasiyerler, depo sorumluları, adminler) ve depolar arası **gerçek zamanlı, direkt sekme yönlendirmeli (Direct Tab Switch) bildirim merkezi** mimarisini ve gelecek vizyonunu açıklar.

---

## 1. MİMARİ GENEL BAKIŞ & BİLDİRİM AKIŞI

Hızlı Kasa bildirim sistemi, olay tabanlı (event-driven) bir mimari ile çalışır. Sunucu tarafında (WooCommerce siparişi, depo sevki, stok uyuşmazlığı vb.) gerçekleşen bir olay ilgili deponun kasiyerlerine bildirilir.

**ZORUNLU UX KURALI:** Bildirime tıklandığında ekranda geçici baloncuk (toast) AÇILMAZ. Bildirim direkt olarak o işlevi yerine getiren ilgili Sekmeye (`Sevk`, `Stok`, `İade`, `Raporlar`) geçiş yapar ve ilgili veriyi ekranda odaklar.

```
┌────────────────────────────────────────────────────────────────────────────────────────┐
│                               SUNUCU OLAYLARI (SERVER EVENTS)                          │
├───────────────────────────────┬───────────────────────────────┬────────────────────────┤
│ A. Yeni Sevk Talebi           │ B. E-Ticaret İnternet Satışı  │ C. Stok Uyuşmazlığı    │
│ (Kaynak -> Hedef Depo)        │ (Stok Düşen Depo)             │ (Cron / Sistem Uyarısı)│
└───────────────┬───────────────┴───────────────┬───────────────┴───────────────┬────────┘
                │                               │                               │
                ▼                               ▼                               ▼
┌────────────────────────────────────────────────────────────────────────────────────────┐
│                          BİLDİRİM ROTALAMA MOTORU (ROUTING ENGINE)                     │
│               Target Filters: target_depo_id | target_user_id | user_role             │
└───────────────────────────────────────┬────────────────────────────────────────────────┘
                                        │
                                        ▼
┌────────────────────────────────────────────────────────────────────────────────────────┐
│                        VERİTABANI KAYDI & REST API (V2 ENDPOINTS)                      │
│     action_type: "switch_tab" | action_target: "sevk"|"stok"|"iade"|"raporlar"          │
└───────────────────────────────────────┬────────────────────────────────────────────────┘
                                        │
                                        ▼
┌────────────────────────────────────────────────────────────────────────────────────────┐
│                    DİREKT SEKME YÖNLENDİRME (HK.AppNavigation)                         │
│   • HK.AppNavigation.switchTab('sevk', { sevk_id: 104 })                               │
│   • Rozet Sayacı (Badge Count) Güncellenir: HK.UI.badge.set(unreadCount)              │
└────────────────────────────────────────────────────────────────────────────────────────┘
```

---

## 2. GERÇEK ZAMANLI OLAY VE SEKME YÖNLENDİRME SENARYOLARI

### Senaryo A: Depolar Arası Sevk Talebi (`sevk_geldi`)
- **Tetikleyici Olay:** A Deposu, B Deposuna sevk çıkışı yaptığında (`POST /hizli-kasa/v2/sevk/create`).
- **Hedef Kitle:** Hedef Depo (`hedef_depo_id = B`) üzerinde aktif olan tüm kasiyerler.
- **Direkt Yönlendirme Davranışı:** Bildirime tıklandığı anda baloncuk çıkmaz; POS arayüzü direkt **`Sevk` Sekmesine (`tab=sevk`)** geçer ve `#sevk-detay-modal` penceresinde ilgili sevki (`sevk_id: 104`) otomatik açar.
- **Aksiyon Kodu:** `HK.AppNavigation.switchTab('sevk', { open_sevk_id: 104 });`

### Senaryo B: E-Ticaret İnternet Satışı Stok Düşümü (`e_ticaret_siparis`)
- **Tetikleyici Olay:** WooCommerce web sitesinden yeni bir sipariş alındığında ve ilgili ürünün stoğu belirli bir depodan düştüğünde.
- **Hedef Kitle:** Stoğu düşen deponun (`depo_id`) kasiyerleri.
- **Direkt Yönlendirme Davranışı:** Bildirime tıklandığında direkt **`Stok Terminali` Sekmesine (`tab=stok`)** geçer ve stoğu düşen ürünü listede odaklar.
- **Aksiyon Kodu:** `HK.AppNavigation.switchTab('stok', { highlight_sku: 'SKU123' });`

### Senaryo C: Otomatik Stok Uyuşmazlığı Tespiti (`stok_uyusmazligi`)
- **Tetikleyici Olay:** Arka plan WP-Cron taraması fiziki stok ile site stoğunda fark bulduğunda.
- **Hedef Kitle:** Mağaza yöneticisi / Admin rolündeki tüm kullanıcılar.
- **Direkt Yönlendirme Davranışı:** Bildirime tıklandığında direkt **`Stok Uyuşmazlık Raporu` Sekmesine (`tab=stok&filter_mismatch=true`)** yönlendirir.
- **Aksiyon Kodu:** `window.location.href = 'admin.php?page=hizli-kasa&tab=stok&filter_mismatch=true';`

---

## 3. VERİTABANI ŞEMASI VE V2 REST API TASARIMI

### 3.1 Veritabanı Tablosu (`wp_hizli_kasa_notifications`)
```sql
CREATE TABLE `wp_hizli_kasa_notifications` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) DEFAULT NULL,        -- NULL ise depoya ait tüm kullanıcılar görür
  `depo_id` bigint(20) DEFAULT NULL,        -- Hangi depoya yönlendirildiği
  `target_role` varchar(50) DEFAULT NULL,   -- 'administrator', 'kasiyer' vb.
  `type` varchar(50) NOT NULL,              -- 'sevk', 'e_ticaret', 'stok_uyusmazlik', 'system'
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `action_type` varchar(50) NOT NULL DEFAULT 'switch_tab', -- 'switch_tab', 'redirect_url'
  `action_target` varchar(100) NOT NULL,    -- 'sevk', 'stok', 'iade', 'raporlar'
  `action_payload` longtext DEFAULT NULL,   -- JSON veri (örn: { "sevk_id": 104 })
  `is_read` tinyint(1) NOT NULL DEFAULT 0,  -- 0: Okunmadı, 1: Okundu
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `depo_user_idx` (`depo_id`, `user_id`, `is_read`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 3.2 V2 REST API Endpoint Yanıtı Örneği

```json
{
  "success": true,
  "data": {
    "unread_count": 2,
    "notifications": [
      {
        "id": 102,
        "type": "sevk",
        "title": "📦 Yeni Sevk Geldi",
        "message": "Merkez depodan #SVK-104 numaralı sevk gönderildi.",
        "action_type": "switch_tab",
        "action_target": "sevk",
        "action_payload": { "sevk_id": 104 },
        "created_at": "2026-07-28 12:50:00"
      }
    ]
  },
  "errors": null,
  "meta": { "timestamp": "2026-07-28 12:51:00", "version": "v2" }
}
```

---

## 4. FRONTEND YÖNLENDİRME MOTORU (HK.AppNavigation)

Bildirim tıklandığı anda baloncuk açılmadan doğrudan ilgili sekmeyi tetikleyen JS mantığı:

```javascript
function handleNotificationClick(notif) {
    // 1. Bildirimi okundu yap
    fetch('/wp-json/hizli-kasa/v2/notifications/read', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': HK_DATA.nonce },
        body: JSON.stringify({ notification_id: notif.id })
    });

    // 2. Rozeti düşür
    HK.UI.badge.decrement();

    // 3. Baloncuk Açmadan DİREKT Sekmeye Yönlendir
    if (notif.action_type === 'switch_tab') {
        var payload = typeof notif.action_payload === 'string' ? JSON.parse(notif.action_payload) : notif.action_payload;
        HK.AppNavigation.switchTab(notif.action_target, payload);
    } else if (notif.action_type === 'redirect_url' && notif.action_target) {
        window.location.href = notif.action_target;
    }
}
```

---

Geliştiriciler ve AI ajanları baloncuk (toast) yerine doğrudan sekme geçişi (`switchTab`) kuralına tam uyum sağlayacaktır.
