# Admin Stok Yönetimi Modern UI/UX Mimarisi

Bu belge, Hızlı Kasa eklentisinin WordPress Admin panelindeki Stok Yönetimi (`includes/views/admin-stok-yonetimi.php`) arayüzünün modernizasyonunu, görsel tasarım bileşenlerini ve klavye gezinme (Spreadsheet UX) standartlarını açıklar.

---

## 1. Mimari Genel Bakış

Admin Stok Yönetimi; WooCommerce ana stok verileri (`wc_stock`) ile özel depo tablolarındaki (`hizli_kasa_stok_konumlari`) verileri dinamik bir matriste karşılaştıran, hızlı stok düzenlemelerine ve toplu işlemlere imkan tanıyan modern bir yönetim panelidir.

### Temel Bileşenler:
1. **Canlı Metrik Kartları (Quick Stats Grid):** Toplam SKU, Stok Uyuşmazlığı, Stoğu Sıfır Olanlar ve Aktif Depo Sayısını özetleyen tıklanabilir responsive kartlar.
2. **Modern Toolbar & Filtre Çipleri (Filter Chips):** Debounced live search (300ms) ile anlık arama, Uyuşmazlık ve Sıfır Stok filtresi için aktif/pasif çipler.
3. **Sticky & Minimalist Data Grid:** Sabit başlık sütunları, hiyerarşik varyasyon ağacı yapısı, renk kodlu stok seviyeleri ve fark rozetleri (`Δ -2`).
4. **Spreadsheet Klavye Navigasyonu:** `Tab`, `Enter`, Ok tuşları (`▲/▼/◄/►`) ile hücreden hücreye serice gezinme ve `Ctrl+S` ile toplu kaydetme.
5. **Floating Action Bar (Bulk Dock):** Seçili ürünler üzerinde toplu sabit değer atama, yüzdesel stok artırma/azaltma (`%+10`, `%-5`), aşağı/yukarı doldurma (`Fill Down`, `Fill Up`).

---

## 2. Arayüz Tasarım Standartları

- **Renk Değişkenleri:** `assets/css/admin-hub.css` içerisindeki `--hk-primary` (`#f58220`), `--hk-bg` (`#f8fafc`), `--hk-border-color` (`#e2e8f0`) ve `--hk-card-bg` (`#ffffff`) CSS değişkenleri kullanılmıştır.
- **Tarayıcı Popup Yasağı:** `alert()`, `confirm()`, `prompt()` kesinlikle kullanılmamıştır. İletişim `HK.UI` ve özel HTML modallar ile yürütülmektedir.
- **Klavye Kısayolları:**
  - `Ctrl + S` / `Cmd + S`: Bekleyen stok değişikliklerini kaydeder (`savePendingChanges`).
  - Ok Tuşları (`▲/▼/◄/►`): Düzenlenebilir stok hücreleri arasında geçiş yapar.
  - `Esc`: Hücre içi düzenleme modundan çıkar.

---

## 3. İlgili Dosyalar

- **View:** `includes/views/admin-stok-yonetimi.php`
- **Styles:** `assets/css/admin-hub.css`
- **AJAX Controller:** `includes/classes/ajax/class-ajax-stock.php`
- **Stock Manager:** `includes/classes/class-stock-manager.php`
