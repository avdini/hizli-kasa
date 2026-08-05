---
name: release-workflow
description: Kullanıcı "güncelle", "sürüm yayınla", "versiyon yükselt" veya benzeri otomatik dağıtım talimatları verdiğinde 5 adımlı otomatik sürüm yayınlama iş akışını çalıştırır.
---

# Hızlı Kasa Automated Release Workflow Skill

Kullanıcı **"güncelle"**, **"sürüm yayınla"**, **"versiyon yükselt ve güncelle"** veya benzeri tek kelimelik / kısa talimatlar verdiğinde, tüm AI ajanları ek açıklama veya komut detayı istemeksizin aşağıdaki 5 adımı sırayla ve tam otomasyonla yürütmelidir:

---

## 5 Adımlı Yayınlama Akışı

### Adım 1: İş Analizi & SemVer Versiyon Yükseltme
1. `git status` ve `git diff` çıktılarını analiz et.
2. Değişikliğin türünü belirle:
   - Hata Düzeltmesi (Bugfix): `PATCH` (örn: 12.46.2 -> 12.46.3)
   - Yeni Özellik (Feature): `MINOR` (örn: 12.46.0 -> 12.47.0)
   - Breaking Change: `MAJOR` (örn: 12.46.0 -> 13.0.0)
3. Versiyon numaralarını yükselt:
   - `hizli-kasa.php` dosyasındaki Header `Version:` başlığını
   - `includes/constants.php` içerisindeki `define('HIZLI_KASA_VERSION', '...');` sabitini yeni versiyona güncelle.

---

### Adım 2: Semantik Commit (Conventional Commits)
Değişiklikleri kapsayacak şekilde Türkçe/İngilizce semantik commit mesajı oluştur:
```bash
git add -A
git commit -m "fix(pos): resolve order tax rounding issue and bump version to 12.46.3"
```

---

### Adım 3: Özel Depoya Push (`origin main`)
Yapılan commit'i ana depoya gönder:
```bash
git push origin main
```

---

### Adım 4: Halka Açık Depoya Otomatik Patch (`public master`)
Otomatik yamalama betiğini çalıştır:
```powershell
powershell -ExecutionPolicy Bypass -File scripts/patch-public.ps1
```

---

### Adım 5: GitHub Release Yayınlama
GitHub CLI (`gh`) kullanarak yeni versiyon için otomatik release yayınla:
```bash
gh release create vX.Y.Z --title "vX.Y.Z" --notes "Sürüm notları..."
```
