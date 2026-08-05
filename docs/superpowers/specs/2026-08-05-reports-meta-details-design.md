# Raporlar Modülü: 2 Katmanlı ve Kategorize Detay (Meta) Tasarımı

## 🎯 Amaç ve Özet
Hızlı Kasa rapor sekmelerinde (Tüm Siparişler, İnternet Siparişleri, İadeler) "Detay 🔍" butonuna tıklandığında açılan sipariş meta bilgilerinin kasiyerler ve yöneticiler için "altın tepside" son derece şık, anlaşılır ve düzenli bir görünüme kavuşturulmasıdır.

Gereksiz dikey yükseklikten kaçınmak ve görsel kafa karışıklığını önlemek için **2 Katmanlı (2-Tiered) 3 Sütunlu Grid Yapısı** benimsenecektir.

---

## 🎨 Tasarım Standartları & Görsel Yapı

### 1. Katman: Şık & Minimal Kategori Kartları (Varsayılan Açık)
Detay açıldığında sipariş meta bilgileri 3 yan yana sütunda (grid) şık mini kartlar halinde sunulur:

1. **💳 Finansal Özet Kartı**
   - Ödeme Türleri (Nakit, Kart, IBAN, QR Taksit, Kupon)
   - İskonto / İndirimler
   - Ara Toplam ve Net Tutar

2. **👤 Kasiyer & Müşteri Kartı**
   - Kasiyer Adı & Kasa No
   - Müşteri Adı & Telefonu
   - Çıkış / İade Depo Bilgisi

3. **ℹ️ İşlem & Durum Kartı**
   - İşlem Kaynağı (Kasa Satışı, Kasa İadesi, İnternet Siparişi)
   - İade Durumu & Orijinal Sipariş ID (varsa)
   - İşlem Tarihi / Notlar

### 2. Katman: Gelişmiş / Diğer Metalar (İsteğe Bağlı Açılır)
Grid kartlarının altında sağa/ortaya hizalı minimal bir buton yer alır:
* `[⚙️ Diğer Teknik Detayları Göster (+N)]`

Butona tıklandığında sistemin ham/teknik meta verileri (ör. WooCommerce özel eklenti alanları) alt kısımda kompakt etiketler (inline badges) halinde açılır.

---

## 🗺️ Kullanıcı Dostu Key Mapping (Türkçe Eşleştirme)

Sistemdeki tüm ham meta anahtarları kullanıcı dostu Türkçe başlıklara ve emoji/simgelere dönüştürülecektir:

| Ham Meta Key | Gösterim Etiketi | Simge |
| :--- | :--- | :--- |
| `_odeme_nakit` | Nakit Ödeme | 💵 |
| `_odeme_kart` | Kart Ödeme | 💳 |
| `_odeme_iban` | IBAN Ödeme | 🏦 |
| `_odeme_qr_taksit` | QR Taksit Ödeme | 📱 |
| `_odeme_coupon` | Kupon Ödeme | 🎟️ |
| `_hizli_kasa_kasiyer` | Kasiyer | 👤 |
| `_hizli_kasa_kasa_no` | Kasa No | 🖥️ |
| `_hizli_kasa_musteri_telefon` | Müşteri Tel | 📞 |
| `_hk_cikis_depo_adi` | Çıkış Deposu | 🏪 |
| `_hk_kaynak` | İşlem Kaynağı | ⚡ |
| `_hizli_kasa_original_order` | Orijinal Sipariş | 🔗 |
| `_hk_is_fully_refunded` | Tam İade Durumu | 🔄 |

---

## 🛠️ Değişiklik Yapılacak Dosyalar

1. **[`assets/js/modules/reports/reports-common.js`](file:///c:/Users/fikri/Desktop/avdini.com/hizli-kasa/assets/js/modules/reports/reports-common.js)**
   - `renderOrderMetaDetails` fonksiyonu kategorize kartlar üretecek ve 2. katman akordeon açılış mantığını içerecek şekilde yeniden yazılacak.
   - `fieldLabelMap` genişletilecek.

2. **[`assets/css/modules/reports.css`](file:///c:/Users/fikri/Desktop/avdini.com/hizli-kasa/assets/css/modules/reports.css)**
   - `.meta-details-container`, `.meta-grid`, `.meta-card`, `.meta-card-title` ve `.meta-extra-toggle` için minimal, şık, dark mode uyumlu CSS kuralları eklenecek.

---

## ✅ Doğrulama Planı

1. **Tüm Siparişler Sekmesi:** Bir siparişte Detay 🔍 butonuna basıp 3 sütunlu grid kartlarının ve en alttaki `+ Diğer Metalar` butonunun doğru çalıştığı doğrulanacak.
2. **İnternet Siparişleri & İadeler Sekmesi:** Diğer sekmelerde de detay panellerinin aynı şıklık ve düzende açıldığı test edilecek.
3. **Karanlık Tema (Dark Mode):** Dark mode aktifken kart renkleri, kenarlıklar ve yazı okunabilirliği kontrol edilecek.
