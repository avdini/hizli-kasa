# Stok Sayımı ve Arka Plan Eşitleme Mimarisi (Stock Sync & Inventory Count)

Bu belge, Hızlı Kasa eklentisindeki fiziksel depo sayımı, asenkron WooCommerce stok senkronizasyonu ve veri güvenliği mekanizmalarını açıklar. Proje üzerinde çalışacak AI ajanlarının ve geliştiricilerin bu mimariyi anlaması kritik önem taşır.

---

## 1. Mimari Genel Bakış ve Problem Tanımı

Büyük ölçekli e-ticaret sitelerinde yüzlerce veya binlerce ürünün stok adetlerinin tek bir HTTP isteğinde güncellenmesi, sunucu tarafında PHP işlem süresi (timeout) limitlerinin aşılmasına ve veri kayıplarına (stokların yarım güncellenmesi gibi) yol açar.

Bu sorunu çözmek için Hızlı Kasa'da **Asenkron El Sıkışma (Handshake) ve WP Cron Tabanlı Sıralı Kuyruk Mimarisi** kurulmuştur.

---

## 2. Veritabanı Modeli

Sayım işlemleri için `includes/classes/class-database.php` altında iki ana tablo tanımlanmıştır:
1.  **`sayim_sessions` (Sayım Oturumları):**
    -   `id`: Benzersiz oturum kimliği.
    -   `location_id`: Sayımın yapıldığı depo.
    -   `status`: Oturum durumu (`aktif`, `processing`, `tamamlandi`, `iptal`).
    -   `update_type`: Güncelleme tipi (`partial` - kısmi veya `full` - tam sayım).
    -   `total_items`: Sayılan toplam ürün çeşidi.
    -   `total_diff`: Sayım sonucu oluşan toplam stok farkı.
    -   `report_data`: Sayılan tüm kalemlerin snapshot verisi (JSON formatında arşivlenir).
2.  **`sayim_kalemleri` (Sayım Detayları):**
    -   Oturuma ait taranan her ürünün `counted_qty` (sayılan adet) ve taranma anındaki `system_qty` (sistem adeti) verilerini tutar.
    -   Asenkron eşitleme (cron) sırasında işlenen her 100'lük grup bu tablodan silinir.

---

## 3. Asenkron Eşitleme ve Kuyruk Mantığı

1.  **Sayımı Bitirme Tetikleyicisi (`POST /sayim/complete`):**
    -   Kullanıcı sayımı tamamladığında sunucuya istek atılır.
    -   Sunucu, oturum durumunu anında `processing` yapar. Eşitleme tipi `full` (Tam Eşitleme) ise sayılmayan diğer depo ürünlerini `0` adetle hızlıca `sayim_kalemleri` tablosuna ekler.
    -   Ardından, arka planda çalışmak üzere tek seferlik bir WP Cron görevi planlar (`wp_schedule_single_event`) ve asenkron bir istek atarak (`wp_remote_post(site_url('wp-cron.php'))`) tarayıcıyı **0.1 saniyede** serbest bırakır.
2.  **Sıralı Kuyruk İşleme (`hizli_kasa_sayim_background_sync`):**
    -   Geri planda çalışan Cron işlevi, `sayim_kalemleri` tablosundan **100 kalemi** çeker.
    -   Bu kalemlerin stoklarını depolar üzerinde günceller (`Hizli_Kasa_Stock_Manager::update_warehouse_stock_set`), rapor arşivini günceller ve işlenen bu 100 kaydı veritabanından kalıcı olarak siler.
    -   Eğer tabloda hala güncellenecek kalem varsa, kendisini **1 saniye sonra** çalışacak şekilde tekrar planlar (`wp_schedule_single_event(time() + 1, ...)`) ve sonlanır.
    -   Bu yapı sayesinde sunucuda kesinti olsa bile sistem kaldığı yerden devam edebilir (Self-Healing).

---

## 4. Küresel Eşzamanlılık Kilidi (Concurrency Lock) ve İlerleme Takibi

Veri tutarlılığını korumak için sistemde küresel düzeyde tekil (singleton) işlem mantığı uygulanır:

1.  **Küresel Engelleme:**
    -   Herhangi bir depoda `status = 'processing'` durumunda aktif bir cron eşitlemesi varken, diğer depolar da dahil olmak üzere yeni bir sayım seansı başlatılması (`POST /sayim/start`) veya taranan aktif bir sayımın tamamlanması (`POST /sayim/complete`) REST katmanında engellenir ve kullanıcıya açıklayıcı bir hata dönülür.
2.  **Küresel İlerleme Gösterimi:**
    -   `GET /sayim/active` sorgusu atıldığında, seçilen depoda aktif oturum olmasa bile arka planda `processing` durumunda olan herhangi bir depo seansı varsa, bu seansın ilerleme bilgileri (`processed`, `remaining`, `total`, `percentage`) ve deponun adı (`other_warehouse_name`) dönülür.
    -   Arayüz, bu yanıtı aldığında kullanıcının bulunduğu depo fark etmeksizin terminali kilitler ve animasyonlu ilerleme barı ile birlikte eşitlenen depo ismini gösterir.
3.  **Güvenli Sorgulama (Zamanlayıcı Temizliği):**
    -   İlerlemeyi anlık izlemek için frontend'de 3 saniyede bir tetiklenen polling istekleri `state.pollingTimer` değişkenine atanır. Yeni bir istek döngüsü başlatılmadan veya sayım modundan çıkıldığında eski `setTimeout` referansları temizlenerek çakışmalar ve gereksiz ağ trafiği önlenir.

---

## 5. Personel Hatalarını Önleyici UX Araçları

Kullanıcının yanlış okutma, mükerrer tarama ve eksik sayım yapmasını önlemek için geliştirilen mekanizmalar:

1.  **HUD (Heads-Up Display) Kartı:** Sol sütunda en son okutulan ürünün adını, görselini, SKU'sunu, sayılan adeti ve sistem stoğuna göre farkını büyük bir kartta görselleştirir.
2.  **Çift Okutma Koruması:** 1.5 saniye aralıkla üst üste gelen aynı barkod taramalarını engeller, sesli ve görsel hata uyarısı tetikler.
3.  **Miktar Doğrulama Modu (Quantity Prompt):** Aktif edildiğinde, barkod okunduğunda adet 1 artmak yerine ekranda adet giriş kutusu açılır. Personel adeti yazıp Enter'a bastığında hızlıca sıradaki ürüne odaklanır.
4.  **Büyük Fark Uyarıları:** Sayımı tamamlarken sistem stoğu ile sayım stoğu arasında kritik düzeyde fark (>= 20 adet veya %90 üzerinde sapma) olan ürünleri listeler ve son onay ister.
