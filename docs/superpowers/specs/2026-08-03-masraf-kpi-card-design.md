# İstatistik Dashboard - Masraf KPI Kartı Tasarım Belgesi

## Amaç
Raporlar sekmesindeki Özet İstatistik Dashboard'una seçilen tarih aralığı ve depodaki toplam masrafları gösteren ve tıklandığında ana **Masraf** sekmesine yönlendiren mini (compact) bir masraf bilgi kartı eklenmesi.

## 1. Backend Değişiklikleri (`includes/api/api-istatistik.php`)
- `/hizli-kasa/v1/statistics/summary` REST API endpoint'indeki SQL sorgusu güncellenerek masraf tutarının yanı sıra toplam masraf kaydı adedi (`COUNT(id) as count`) çekilecek.
- Yanıt nesnesinin (`kpi`) içerisine `masraf_sayisi` anahtarı eklenecek:
  - `toplam_masraf`: float (örn. `450.00`)
  - `masraf_sayisi`: int (örn. `3`)

## 2. Frontend Değişiklikleri (`assets/js/modules/statistics-dashboard.js`)
- `_renderDashboard` metodunda `stat-kpi-compact-grid` kapsayıcısına 5. compact kart eklenecek:
  - **İkon:** `💸`
  - **Etiket:** `MASRAF`
  - **Değer:** Formatlanmış toplam masraf (örn. `₺ 450,00`)
  - **Alt Metin:** `${kpi.masraf_sayisi || 0} masraf kaydı`
  - **Sınıf:** `kpi-masraf stat-kpi-clickable`
  - **Aksiyon Metni:** `Masraflara Git ➔`
- Tıklanabilir KPI Kart Event Listener'ı içerisine `kpi-masraf` sınıfı kontrolü eklenecek:
  - `kpi-masraf` kartına veya butonuna tıklandığında `.ust-sekme[data-tab="masraf"]` DOM elementi tetiklenerek (`click()`) Masraf sekmesine yumuşak geçiş sağlanacak.

## 3. CSS Değişiklikleri (`assets/css/modules/statistics.css`)
- `.stat-kpi-card.kpi-masraf` için temaya uygun renk tanımı yapılacak (`--kpi-color: #f59e0b;`).
- `.stat-kpi-compact-grid` responsive grid düzeni 5 karta uygun şekilde düzenlenecek:
  - Masaüstü: `repeat(5, 1fr)`
  - 1200px altı: `repeat(3, 1fr)`
  - 768px altı: `repeat(2, 1fr)`
  - 500px altı: `1fr`

## Test ve Doğrulama Planı
1. Raporlar sekmesinde Özet İstatistik Dashboard'unun doğru yüklendiği ve masraf kartında seçili tarih/depoya ait toplam masraf ile kayıt sayısının basıldığı doğrulanacak.
2. Masraf kartına ve "Masraflara Git ➔" linkine tıklandığında uygulamanın akıcı bir şekilde Masraf sekmesine geçtiği doğrulanacak.
