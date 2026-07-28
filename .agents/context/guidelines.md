# Hızlı Kasa Geliştirme ve AI Agent Yönergeleri

Bu belge, Hızlı Kasa eklentisi üzerinde çalışan yapay zeka ajanlarının (AI Agents) ve geliştiricilerin uyması gereken temiz kod yazım standartlarını ve proje prensiplerini tanımlar.

---

## 1. Temiz Kod Prensipleri (Yorum Satırı ve Kod Kalabalığı Yasağı)

- **Kod İçi Yorum Kısıtlaması:** Kod dosyalarında aşırı uzun, gereksiz veya ad-hoc açıklayıcı yorum satırları yazmaktan kaçının. Yorum satırları token maliyetlerini artırır, kodun okunabilirliğini bozar ve ajanların kod üzerinde işlem yapmasını zorlaştırır.
- **Yorum Yerine Dokümantasyon:** Mimari kararları, API açıklamalarını ve karmaşık mantıkları kodun içerisine destan gibi yazmak yerine **`.agents/`** klasörü altındaki markdown dosyalarında belgeleyin.
- **Kodu Kendini Belgeleyecek Şekilde Yazın:** Değişken isimlerini, fonksiyon ve sınıf adlarını açıklayıcı seçerek yorum satırı ihtiyacını en aza indirin.

---

## 2. Proje Belleği: `.agents/` Klasörü

Bu klasör projenin "hafızasıdır". Çalıştığınız alanda daha önce bir dokümantasyon yoksa veya mevcut olanlar güncelliğini yitirmişse:
1.  **Yeni Notlar Ekleyin:** Yaptığınız mimari güncellemeleri veya eklediğiniz modülleri açıklayan yeni bir `.md` dosyası oluşturun.
2.  **Mevcut Notları Güncelleyin:** Bir dosyanın yapısını veya çalışma mantığını değiştirdiyseniz, `.agents/` içerisindeki ilgili dokümanı güncelleyin.
3.  **Arama Standardı:** Projede çalışmaya başlayan her ajan ilk iş olarak `.agents/` klasörünü analiz etmeli ve projenin yapısına buradan hakim olmalıdır.

### Önemli Bellek Dokümanları:
-   **Genel Kılavuzlar:** [guidelines.md](file:///c:/Users/fikri/Desktop/avdini.com/hizli-kasa/.agents/context/guidelines.md)
-   **Modal ve Mesaj Kutusu Standartları:** [modal-notification-standards.md](file:///c:/Users/fikri/Desktop/avdini.com/hizli-kasa/.agents/context/modal-notification-standards.md)
-   **V2 REST API Standartları:** [api-development.md](file:///c:/Users/fikri/Desktop/avdini.com/hizli-kasa/.agents/skills/api-development.md)
-   **Loglama ve Logger Standartları:** [logging.md](file:///c:/Users/fikri/Desktop/avdini.com/hizli-kasa/.agents/skills/logging.md)
-   **Stok Sayımı ve Arka Plan Eşitleme Mimarisi:** [stock-sync-architecture.md](file:///c:/Users/fikri/Desktop/avdini.com/hizli-kasa/.agents/context/stock-sync-architecture.md)

---

## 3. Mimari ve API Standartları (V2)

- **OOP Yapısı:** Tüm yeni modüller ve API'ler nesne yönelimli olarak yazılmalı, `includes/api/v2/` altındaki hiyerarşiye uygun konumlandırılmalıdır.
  - Altyapı ve taban sınıflar: `includes/api/v2/core/`
  - Endpoint controller sınıfları: `includes/api/v2/controllers/`
- **Taban Sınıfları Kullanın:** Yeni bir controller oluştururken mutlaka `Hizli_Kasa_API_Controller_Base` sınıfından türetin. Yetki kontrolleri, no-cache başlıkları ve hata yakalama (try-catch) bu taban sınıfta standartlaştırılmıştır.
- **Standart Yanıt:** Tüm endpoint dönüşleri `Hizli_Kasa_API_Response` sınıfının `success()` veya `error()` metotlarını kullanmalıdır.

---

## 4. Modal, Uyarı ve Kullanıcı İletişim Standartları

- **Tarayıcı Popup Yasağı (No Native Dialogs):** Kullanıcıdan girdi alma, onay isteme veya bilgilendirme süreçlerinde kesinlikle tarayıcının varsayılan bildirim kutuları (`alert()`, `confirm()`, `prompt()`) kullanılmamalıdır.
- **Özel Modal Entegrasyonu:** Tüm etkileşimler `includes/views/modals.php` içindeki HTML modallar, SweetAlert (`swal`) veya özel CSS/JS overlay yapıları ile şık, tutarlı ve POS arayüzüne uygun olarak sunulmalıdır. Detaylar için [modal-notification-standards.md](file:///c:/Users/fikri/Desktop/avdini.com/hizli-kasa/.agents/context/modal-notification-standards.md) dosyasına bakın.

---

## 5. Kod Arama ve CodeGraph Kullanımı

- **CodeGraph Önceliği:** Projede CodeGraph dizini (`.codegraph/`) oluşturulmuştur. CodeGraph aracını/komutlarını kullanabilen (bu yeteneğe sahip olan) tüm AI ajanları, grep/find yapmak veya doğrudan dosyaları okumak yerine öncelikle CodeGraph'i kullanmalıdır.
- **Dürüstlük İlkesi:** Eğer çalışan AI ajanı teknik kısıtlamalar veya yetersizlikler sebebiyle CodeGraph araçlarını kullanamıyorsa (örneğin MCP entegrasyonu o oturumda aktif değilse veya desteklemiyorsa), bunu kullanıcıya dürüstçe belirtmeli ve alternatif arama/analiz yöntemlerine geçmelidir.

---

## 6. Git, Sürüm Kontrolü ve GitHub Yönetim Standartları

Çoklu remote yönetimi (origin/public), sürüm/patch süreçleri, semantik commit/versiyonlama kuralları ve GitHub CLI (`gh`) kullanım yönergeleri ayrı bir dosyaya taşınmıştır. Geliştirme ve yayınlama yapmadan önce lütfen bu rehberi inceleyin:

- [git-standards.md](file:///c:/Users/fikri/Desktop/avdini.com/hizli-kasa/.agents/context/git-standards.md)
