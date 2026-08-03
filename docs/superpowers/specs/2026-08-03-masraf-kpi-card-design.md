# İstatistik Dashboard - Masraf KPI Kartı ve Kart Sıralaması Tasarım Belgesi

## Amaç
Raporlar sekmesindeki Özet İstatistik Dashboard'una seçilen tarih aralığı ve depodaki toplam masrafları gösteren ve tıklandığında ana **Masraf** sekmesine yönlendiren mini (compact) bir masraf bilgi kartı eklenmesi ve compact kartların sıralamasının ekran düzenine uyumlu şekilde güncellenmesi.

## Kart Sıralama Mantığı (UX Düzenlemesi)
Hero strip üzerindeki 2 büyük kartın yerleşimi:
- Sol Taraf: **CİRO ANALİZİ** (`kpi-ciro`)
- Sağ Taraf: **SATIŞ HACMİ** (`kpi-siparis`)

Satış hacmi (sipariş adedi ve satılan ürün adedi) ile ilgili metriklerin (`SEPET ORT.` ve `SEPET ÜRÜN`) sağ taraftaki **SATIŞ HACMİ** kartının tam altına hizalanması için compact grid sıralaması şu şekilde güncellenecektir:
1. `💸 MASRAF` (`kpi-masraf`)
2. `↩️ İADE` (`kpi-iade`)
3. `✂️ İSKONTO` (`kpi-iskonto`)
4. `🛒 SEPET ORT.` (`kpi-sepet-tutar`)
5. `🛍️ SEPET ÜRÜN` (`kpi-sepet-adet`)

## 1. Backend Değişiklikleri (`includes/api/api-istatistik.php`)
- `/hizli-kasa/v1/statistics/summary` REST API endpoint'inde `toplam_masraf` ve `masraf_sayisi` döndürülüyor.

## 2. Frontend Değişiklikleri (`assets/js/modules/statistics-dashboard.js`)
- `_renderDashboard` metodunda `stat-kpi-compact-grid` kart sıralaması yukarıdaki yeni düzene göre oluşturulacak.

## 3. CSS Değişiklikleri (`assets/css/modules/statistics.css`)
- 5'li compact grid CSS düzeni korunacak:
  - Masaüstü: `repeat(5, 1fr)`
  - 1200px altı: `repeat(3, 1fr)`
  - 768px altı: `repeat(2, 1fr)`
  - 500px altı: `1fr`

## Test ve Doğrulama Planı
1. 5'li compact kart diziliminde `SEPET ORT.` ve `SEPET ÜRÜN` kartlarının sağ tarafa yerleşip `SATIŞ HACMİ` kartının altına hizalandığı doğrulanacak.
2. Tüm tıklama event'lerinin ve yönlendirmelerin düzgün çalıştığı kontrol edilecek.
