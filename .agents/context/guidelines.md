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
-   **V2 REST API Standartları:** [api-development.md](file:///c:/Users/fikri/Desktop/avdini.com/hizli-kasa/.agents/skills/api-development.md)
-   **Stok Sayımı ve Arka Plan Eşitleme Mimarisi:** [stock-sync-architecture.md](file:///c:/Users/fikri/Desktop/avdini.com/hizli-kasa/.agents/context/stock-sync-architecture.md)


---

## 3. Mimari ve API Standartları (V2)

- **OOP Yapısı:** Tüm yeni modüller ve API'ler nesne yönelimli olarak yazılmalı, `includes/api/v2/` altındaki hiyerarşiye uygun konumlandırılmalıdır.
  - Altyapı ve taban sınıflar: `includes/api/v2/core/`
  - Endpoint controller sınıfları: `includes/api/v2/controllers/`
- **Taban Sınıfları Kullanın:** Yeni bir controller oluştururken mutlaka `Hizli_Kasa_API_Controller_Base` sınıfından türetin. Yetki kontrolleri, no-cache başlıkları ve hata yakalama (try-catch) bu taban sınıfta standartlaştırılmıştır.
- **Standart Yanıt:** Tüm endpoint dönüşleri `Hizli_Kasa_API_Response` sınıfının `success()` veya `error()` metotlarını kullanmalıdır.

---

## 4. Kod Arama ve CodeGraph Kullanımı

- **CodeGraph Önceliği:** Projede CodeGraph dizini (`.codegraph/`) oluşturulmuştur. CodeGraph aracını/komutlarını kullanabilen (bu yeteneğe sahip olan) tüm AI ajanları, grep/find yapmak veya doğrudan dosyaları okumak yerine öncelikle CodeGraph'i kullanmalıdır.
- **Dürüstlük İlkesi:** Eğer çalışan AI ajanı teknik kısıtlamalar veya yetersizlikler sebebiyle CodeGraph araçlarını kullanamıyorsa (örneğin MCP entegrasyonu o oturumda aktif değilse veya desteklemiyorsa), bunu kullanıcıya dürüstçe belirtmeli ve alternatif arama/analiz yöntemlerine geçmelidir.

---

## 5. Git Repository Yapısı ve Çoklu Remote Yönetimi

Hızlı Kasa eklentisi, tek bir yerel dizin üzerinden iki farklı GitHub deposuna (remote) bağlı olarak geliştirilmektedir:

- **`origin`**: Özel (Private) depo (`https://github.com/Seyfullahkurt9/hizli-kasa.git`). Ana geliştirme dalı **`main`**'dir.
- **`public`**: Halka açık (Public) depo (`https://github.com/Seyfullahkurt9/woo-quick-pos.git`). Ana dalı **`master`**'dır.

### 5.1. Değişiklikleri Yayma ve Patch Yönetimi

Yerel olarak `main` dalında yapılan bir geliştirme veya hata çözümü test edildikten sonra her iki depoya da yansıtılmalıdır:
1. Değişiklikleri önce `main` dalında commit'leyip `origin` deposuna gönderin:
   ```bash
   git push origin main
   ```
2. Public depoya göndermek için, `public/master` dalından geçici bir dal oluşturun:
   ```bash
   git checkout -b temp-public-patch public/master
   ```
3. `main` dalında yaptığınız commit'i bu geçici dala cherry-pick yapın:
   ```bash
   git cherry-pick <commit-hash>
   ```
4. Geçici dalı `public` deposunun `master` dalına push'layın:
   ```bash
   git push public temp-public-patch:master
   ```
5. İşlem bittikten sonra yerel `main` dalına geri dönüp geçici dalı silin.

### 5.2. Kritik Konfigürasyon Farkı (Update Checker)

İki depo arasında otomatik güncelleme kontrolü (`hizli-kasa.php` içindeki `Plugin Update Checker`) için kritik bir yapılandırma farkı vardır:

- **Private (`main`) Sürümünde:** `PucFactory::buildUpdateChecker` adresi `https://github.com/Seyfullahkurt9/hizli-kasa/` olmalı ve dal `main` olarak ayarlanmalıdır.
- **Public (`master`) Sürümünde:** `PucFactory::buildUpdateChecker` adresi `https://github.com/Seyfullahkurt9/woo-quick-pos/` olmalı ve dal `master` olarak ayarlanmalıdır. 
  *(Aksi takdirde, public sürümü kullanan siteler özel depoya erişemediği için GitHub API'sinden 404 hatası alacaktır.)*

### 5.3. Semantik Commit (Conventional Commits) Standartları

Projeye yapılan tüm commit'ler semantik kurallara uygun olarak yazılmalıdır. Diğer ajanlar ve geliştiriciler commit mesajlarını şu şablona göre oluşturmalıdır:

- **`feat:`**: Yeni bir özellik veya fonksiyon eklendiğinde kullanılır. (Örn: `feat(api): add warehouse stock export endpoint`)
- **`fix:`**: Bir hata düzeltildiğinde kullanılır. (Örn: `fix(stock): resolve undefined variable $user_id warning`)
- **`docs:`**: Yalnızca dokümantasyon, kılavuzlar veya açıklama satırlarında değişiklik yapıldığında kullanılır. (Örn: `docs(guidelines): add semantic commit guidelines`)
- **`refactor:`**: Kodun işlevselliğini değiştirmeden yapılan yapısal düzenlemeler ve iyileştirmelerde kullanılır. (Örn: `refactor(db): optimize warehouse select query`)
- **`style:`**: Kodun çalışmasını etkilemeyen biçimlendirme, boşluk veya noktalı virgül gibi görsel düzenlemelerde kullanılır. (Örn: `style: format indentation in class-database.php`)
- **`chore:`**: Yapılandırma dosyaları, bağımlılık güncellemeleri veya rutin bakım görevlerinde kullanılır. (Örn: `chore(updater): update plugin update checker library`)

Mesajlar olabildiğince kısa, öz ve net olmalıdır.

