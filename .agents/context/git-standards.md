# Git Repository Yapısı, Sürüm Kontrolü ve Dağıtım Standartları

Bu kılavuz, Hızlı Kasa eklentisinin çoklu depo yapısını (multi-remote), versiyonlama kurallarını ve GitHub CLI kullanımını tanımlar. Geliştiriciler ve yapay zeka ajanları yama veya sürüm göndermeden önce bu standartları uygulamalıdır.

---

## 1. Git Repository Yapısı ve Çoklu Remote Yönetimi

Hızlı Kasa eklentisi, tek bir yerel dizin üzerinden iki farklı GitHub deposuna (remote) bağlı olarak geliştirilmektedir:

- **`origin`**: Özel (Private) depo (`https://github.com/Seyfullahkurt9/hizli-kasa.git`). Ana geliştirme dalı **`main`**'dir.
- **`public`**: Halka açık (Public) depo (`https://github.com/Seyfullahkurt9/woo-quick-pos.git`). Ana dalı **`master`**'dır.

### 1.1. Değişiklikleri Yayma ve Patch Yönetimi

Yerel olarak `main` dalında yapılan bir geliştirme veya hata çözümü test edildikten sonra her iki depoya da yansıtılmalıdır:

1. Değişiklikleri önce `main` dalında commit'leyip `origin` deposuna gönderin:
   ```bash
   git push origin main
   ```
2. Bu değişikliği otomatik olarak `public` (Woo Quick POS) deposunun `master` dalına aktarmak (patch) için, projedeki hazır PowerShell betiğini çalıştırın:
   ```powershell
   powershell -ExecutionPolicy Bypass -File scripts/patch-public.ps1 <commit-hash>
   ```
   *(Eğer `<commit-hash>` parametresi belirtilmezse, varsayılan olarak son commit (`HEAD`) baz alınır.)*
   
   Bu betik arka planda otomatik olarak:
   - Uzak sunucudan (`public`) son değişiklikleri çeker.
   - `public/master` tabanlı geçici bir dal açar.
   - Belirttiğiniz commit'i bu dala cherry-pick yapar.
   - Değişikliği `public` remote'un `master` dalına push'lar.
   - Eski yerel dalınıza geri dönerek geçici dalı siler ve temizlik yapar.

### 1.2. Kritik Konfigürasyon Farkı (Update Checker)

İki depo arasında otomatik güncelleme kontrolü (`hizli-kasa.php` içindeki `Plugin Update Checker`) için kritik bir yapılandırma farkı vardır:

- **Private (`main`) Sürümünde:** `PucFactory::buildUpdateChecker` adresi `https://github.com/Seyfullahkurt9/hizli-kasa/` olmalı ve dal `main` olarak ayarlanmalıdır.
- **Public (`master`) Sürümünde:** `PucFactory::buildUpdateChecker` adresi `https://github.com/Seyfullahkurt9/woo-quick-pos/` olmalı ve dal `master` olarak ayarlanmalıdır. 
  *(Aksi takdirde, public sürümü kullanan siteler özel depoya erişemediği için GitHub API'sinden 404 hatası alacaktır.)*

---

## 2. Semantik Commit (Conventional Commits) Standartları

Projeye yapılan tüm commit'ler semantik kurallara uygun olarak yazılmalıdır. Diğer ajanlar ve geliştiriciler commit mesajlarını şu şablona göre oluşturmalıdır:

- **`feat:`**: Yeni bir özellik veya fonksiyon eklendiğinde kullanılır. (Örn: `feat(api): add warehouse stock export endpoint`)
- **`fix:`**: Bir hata düzeltildiğinde kullanılır. (Örn: `fix(stock): resolve undefined variable $user_id warning`)
- **`docs:`**: Yalnızca dokümantasyon, kılavuzlar veya açıklama satırlarında değişiklik yapıldığında kullanılır. (Örn: `docs(guidelines): add semantic commit guidelines`)
- **`refactor:`**: Kodun işlevselliğini değiştirmeden yapılan yapısal düzenlemeler ve iyileştirmelerde kullanılır. (Örn: `refactor(db): optimize warehouse select query`)
- **`style:`**: Kodun çalışmasını etkilemeyen biçimlendirme, boşluk veya noktalı virgül gibi görsel düzenlemelerde kullanılır. (Örn: `style: format indentation in class-database.php`)
- **`chore:`**: Yapılandırma dosyaları, bağımlılık güncellemeleri veya rutin bakım görevlerinde kullanılır. (Örn: `chore(updater): update plugin update checker library`)

Mesajlar olabildiğince kısa, öz ve net olmalıdır.

---

## 3. Semantik Versiyonlama (SemVer) ve Commit İlişkisi

Eklenti versiyon numaraları (**`MAJOR.MINOR.PATCH`** formatında, örn: `11.5.1`), semantik commit'lerle uyumlu olarak artırılmalıdır:

- **`PATCH` (En Sağdaki Hane - örn: `11.5.0` -> `11.5.1`):** Sadece geriye dönük uyumlu hata düzeltmeleri yapıldığında artırılır. Bu artış, **`fix:`** commit'leriyle tetiklenir.
- **`MINOR` (Ortadaki Hane - örn: `11.5.0` -> `11.6.0`):** Geriye dönük uyumlu yeni özellikler/fonksiyonlar eklendiğinde artırılır. Bu artış, **`feat:`** commit'leriyle tetiklenir.
- **`MAJOR` (En Soldaki Hane - örn: `11.5.0` -> `12.0.0`):** Geriye dönük uyumsuz API/altyapı değişiklikleri yapıldığında artırılır. Bu artış, commit mesajında **`BREAKING CHANGE`** uyarısı veya **`feat!:`** / **`fix!:`** etiketleri kullanıldığında tetiklenir.

Geliştiriciler ve yapay zeka ajanları, yaptıkları değişikliğin türüne göre `hizli-kasa.php` içerisindeki `Version` başlığını ve `HIZLI_KASA_VERSION` sabitini bu kurallara göre güncellemelidir.

---

## 4. GitHub Süreçleri ve GitHub CLI (gh) Teşviki

GitHub üzerindeki yönetimsel süreçleri (PR, Release, Issue vb.) yürütürken API isteklerini ve sayfa karmaşasını azaltmak için **GitHub CLI (`gh`)** kullanımı teşvik edilmektedir. Geliştiriciler ve yapay zeka ajanları şu kurallara uymalıdır:

- **Sürüm Yayınlama (Release):** Eklenti versiyonu güncellenip ana depoda yayınlandıktan sonra, GitHub üzerinde yeni bir sürüm (Release) oluşturmak için `gh release` kullanılmalıdır:
  ```bash
  gh release create v11.5.1 --title "v11.5.1" --notes "Sürüm detayları ve hata düzeltmeleri..."
  ```
- **Çekme İstekleri (Pull Request):** Public depoya doğrudan push yapmak yerine bir inceleme (code review) süreci işletilecekse, `gh pr` ile hızlıca PR oluşturulmalıdır:
  ```bash
  gh pr create --repo Seyfullahkurt9/woo-quick-pos --title "fix(stock): resolve user_id warning" --body "Açıklama..."
  ```
- **Hata Kayıtları (Issues):** Depolardaki açık hata kayıtlarını veya yapılacak işleri listelemek için:
  ```bash
  gh issue list
  ```
  *(Not: Eğer ajanların çalıştığı yerel ortamda `gh` yetkilendirmesi (login) yapılmamışsa veya `gh` yüklü değilse, ajanlar hata vermeden güvenli bir şekilde standart Git komutlarına veya kullanıcıyı bilgilendirme yöntemine geri dönmelidir.)*

---

## 5. Otomatik Güncelleme ve Kısayol Komut Yönergesi (AI Ajan Standartları)

Kullanıcı **"güncelle"**, **"sürüm yayınla"**, **"versiyon yükselt ve güncelle"** veya benzeri tek kelimelik / kısa talimatlar verdiğinde, tüm AI ajanları ek açıklama veya komut detayı istemeksizin aşağıdaki 5 adımı sırayla ve tam otomasyonla yürütmelidir:

1. **İş Analizi & SemVer Versiyon Yükseltme:**
   - Yapılan son değişikliklerin niteliğini (hata düzeltmesi: `PATCH`, yeni özellik: `MINOR`, breaking change: `MAJOR`) analiz eder.
   - `hizli-kasa.php` dosyasındaki `Version:` başlığını ve `define('HIZLI_KASA_VERSION', '...');` sabitini yeni versiyona yükseltir.

2. **Semantik Commit (Conventional Commits):**
   - Değişiklikleri tam kapsayacak şekilde `feat`, `fix`, `docs`, `refactor` vb. ön ekli Türkçe/İngilizce açıklayıcı commit mesajı oluşturur:
     ```bash
     git add -A
     git commit -m "feat(print): simplify summary receipt and improve thermal print contrast"
     ```

3. **Özel Depoya Push (`origin main`):**
   - Yapılan commit'i ana depoya gönderir:
     ```bash
     git push origin main
     ```

4. **Halka Açık Depoya Otomatik Patch (`public master`):**
   - Projedeki otomatik yamalama betiğini çalıştırır:
     ```powershell
     powershell -ExecutionPolicy Bypass -File scripts/patch-public.ps1
     ```

5. **GitHub Release Yayınlama:**
   - GitHub CLI yüklü ise yeni versiyon için otomatik release notları ile sürüm yayınlar:
     ```bash
     gh release create vX.Y.Z --title "vX.Y.Z" --notes "..."
     ```
