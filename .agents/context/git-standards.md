# Git Repository Yapısı, Sürüm Kontrolü ve Dağıtım Standartları

Bu kılavuz, Hızlı Kasa eklentisinin Git depo yapısını, versiyonlama kurallarını ve GitHub CLI kullanımını tanımlar. Geliştiriciler ve yapay zeka ajanları yama veya sürüm göndermeden önce bu standartları uygulamalıdır.

---

## 1. Git Repository Yapısı ve Remote Yönetimi

Hızlı Kasa eklentisi, GitHub üzerindeki halka açık (Public) depo üzerinden geliştirilmektedir:

- **`avdini` / `origin`**: Halka açık (Public) depo (`https://github.com/avdini/hizli-kasa.git`). Ana geliştirme dalı **`main`**'dir.

### 1.1. Değişiklikleri Yayma

Yerel olarak `main` dalında yapılan bir geliştirme veya hata çözümü test edildikten sonra depoya gönderilir:

```bash
git push avdini main
```

### 1.2. Otomatik Güncelleme Konfigürasyonu (Update Checker)

Eklenti içerisindeki `Plugin Update Checker` (`includes/updater.php`), güncellemeleri doğrudan bu halka açık depodan çeker:

- **Güncelleme Adresi:** `https://github.com/avdini/hizli-kasa/`
- **Hedef Dal:** `main`

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

Eklenti versiyon numaraları (**`MAJOR.MINOR.PATCH`** formatında, örn: `12.46.3`), semantik commit'lerle uyumlu olarak artırılmalıdır:

- **`PATCH` (En Sağdaki Hane - örn: `12.46.2` -> `12.46.3`):** Sadece geriye dönük uyumlu hata düzeltmeleri yapıldığında artırılır. Bu artış, **`fix:`** commit'leriyle tetiklenir.
- **`MINOR` (Ortadaki Hane - örn: `12.46.0` -> `12.47.0`):** Geriye dönük uyumlu yeni özellikler/fonksiyonlar eklendiğinde artırılır. Bu artış, **`feat:`** commit'leriyle tetiklenir.
- **`MAJOR` (En Soldaki Hane - örn: `12.46.0` -> `13.0.0`):** Geriye dönük uyumsuz API/altyapı değişiklikleri yapıldığında artırılır. Bu artış, commit mesajında **`BREAKING CHANGE`** uyarısı veya **`feat!:`** / **`fix!:`** etiketleri kullanıldığında tetiklenir.

Geliştiriciler ve yapay zeka ajanları, yaptıkları değişikliğin türüne göre `hizli-kasa.php` içerisindeki `Version` başlığını ve `HIZLI_KASA_VERSION` sabitini bu kurallara göre güncellemelidir.

---

## 4. GitHub Süreçleri ve GitHub CLI (gh) Kullanımı

GitHub üzerindeki yönetimsel süreçleri (Release, Issue vb.) yürütürken **GitHub CLI (`gh`)** kullanımı teşvik edilmektedir:

- **Sürüm Yayınlama (Release):** Eklenti versiyonu güncellenip ana depoda yayınlandıktan sonra, GitHub üzerinde yeni bir sürüm (Release) oluşturmak için `gh release` kullanılmalıdır:
  ```bash
  gh release create v12.46.3 --title "v12.46.3" --notes "Sürüm detayları ve hata düzeltmeleri..."
  ```
- **Hata Kayıtları (Issues):** Depolardaki açık hata kayıtlarını veya yapılacak işleri listelemek için:
  ```bash
  gh issue list
  ```

---

## 5. Otomatik Güncelleme ve Kısayol Komut Yönergesi (AI Ajan Standartları)

Kullanıcı **"güncelle"**, **"sürüm yayınla"**, **"versiyon yükselt ve güncelle"** veya benzeri tek kelimelik / kısa talimatlar verdiğinde, tüm AI ajanları ek açıklama veya komut detayı istemeksizin aşağıdaki 4 adımı sırayla ve tam otomasyonla yürütmelidir:

1. **İş Analizi & SemVer Versiyon Yükseltme:**
   - Yapılan son değişikliklerin niteliğini (hata düzeltmesi: `PATCH`, yeni özellik: `MINOR`, breaking change: `MAJOR`) analiz eder.
   - `hizli-kasa.php` dosyasındaki `Version:` başlığını ve `includes/constants.php` içindeki `define('HIZLI_KASA_VERSION', '...');` sabitini yeni versiyona yükseltir.

2. **Semantik Commit (Conventional Commits):**
   - Değişiklikleri tam kapsayacak şekilde `feat`, `fix`, `docs`, `refactor` vb. ön ekli Türkçe/İngilizce açıklayıcı commit mesajı oluşturur:
     ```bash
     git add -A
     git commit -m "feat(print): simplify summary receipt and improve thermal print contrast"
     ```

3. **Depoya Push (`avdini main`):**
   - Yapılan commit'i depoya gönderir:
     ```bash
     git push avdini main
     ```

4. **GitHub Release Yayınlama:**
   - GitHub CLI yüklü ise yeni versiyon için otomatik release notları ile sürüm yayınlar:
     ```bash
     gh release create vX.Y.Z --title "vX.Y.Z" --notes "..."
     ```
