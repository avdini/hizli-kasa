# Hızlı Kasa Modal, Mesaj Kutusu ve Kullanıcı Bildirim Standartları

Bu doküman, Hızlı Kasa WooCommerce POS eklentisinde kullanıcı etkileşimleri (onay, bilgi, uyarı, metin/girdi alma) için geçerli olan **ZORUNLU** modal ve bildirim standartlarını açıklar.

---

## 1. Temel İlkeler ve Kurallar

1. **Tarayıcı Varsayılan Popup'ları Kesinlikle Yasaktır:**
   - Kod içerisinde `window.alert()`, `window.confirm()` veya `window.prompt()` **KESİNLİKLE KULLANILAMAZ**.
   - Tarayıcının varsayılan mesaj balonları (pop-up) kullanıcı deneyimini bozmakta, dokunmatik kilitli POS ekranlarında kitlenmelere yol açmakta ve uygulamanın modern tasarım kimliğini zedelemektedir.

2. **Hızlı Kasa Özel Modal Yapısı Kullanılmalıdır:**
   - Onay isteme, metin alma veya form doldurma işlemleri için `includes/views/modals.php` dosyası altında HTML modal şablonu tanımlanmalı ve JS modülü üzerinden kontrol edilmelidir.
   - Bilgilendirme ve hızlı başarı/hata uyarılarında SweetAlert (`swal('Başlıklı', 'Mesaj', 'success|error|warning')`) veya toast bildirimleri kullanılmalıdır.

3. **Mobil ve Dokunmatik (POS) Uyumluluğu:**
   - Modallar dokunmatik ekranlara ve farklı ekran çözünürlüklerine uyumlu olmalı, klavye kısayollarını (Örn: `Esc` ile kapatma, `Enter` ile onaylama) desteklemelidir.

---

## 2. Modal Mimarisi ve Bileşen Konumları

Hızlı Kasa sisteminde modal altyapısı üç katmandan oluşur:

### A. HTML Görünüm Katmanı (`includes/views/modals.php`)
- Tüm global modallar `includes/views/modals.php` dosyasında bulunur.
- Şablon Yapısı:
  ```html
  <div id="ornek-modal" class="modal-cerceve" style="display:none;">
      <div class="modal-icerik modal-icerik-sm">
          <h3>📌 Modal Başlığı</h3>
          <p>Açıklama veya form metni...</p>
          <div class="masraf-form-group">
              <label>Girdi Alanı</label>
              <textarea id="ornek-input" class="hk-input"></textarea>
          </div>
          <div class="modal-butonlar">
              <button id="ornek-vazgec" class="modal-btn-cancel">Vazgeç</button>
              <button id="ornek-onay" class="hk-btn-primary">Onayla</button>
          </div>
      </div>
  </div>
  ```

### B. JavaScript Kontrol Katmanı (`assets/js/modules/modal-manager.js` veya Modül JS)
- Modalların açma/kapama durumları, `style.display = 'flex'` veya `'none'` mantığıyla yönetilir.
- Modal açıldığında odak (focus) otomatik olarak ilk input/textarea alanına verilir.
- Buton klikleri ve doğrulama (validation) kuralları JS içerisinde işlenir.

---

## 3. İşlem Türlerine Göre Kullanım Kılavuzu

| İşlem Türü | Yanlış Kullanım (Yasak) | Doğru Kullanım (Zorunlu) |
| :--- | :--- | :--- |
| **Metin / Gerekçe Alma** | `prompt("Neden giriniz:")` | HTML Modal (`#iade-iptal-modal`) içerisindeki `<textarea>` veya `<input>` |
| **İşlem Onayı İsteme** | `confirm("İptal edilsin mi?")` | HTML Modal şablonu veya SweetAlert onay diyaloğu |
| **Başarı / Bilgi Uyarısı** | `alert("İşlem tamamlandı!")` | `swal('Başarılı', 'İşlem tamamlandı.', 'success')` |
| **Hata Uyarısı** | `alert("Hata oluştu!")` | `swal('Hata', 'Hata mesajı...', 'error')` veya modal içi `<small id="hata-mesaji">` |

---

## 4. Örnek Kod Standartları

### Doğru Modal Açma ve Doğrulama Örneği (JS):
```javascript
openCancelModal: function(orderId) {
    var modal = document.getElementById("iade-iptal-modal");
    var input = document.getElementById("iade-iptal-neden-input");
    var errorEl = document.getElementById("iade-iptal-hata-mesaji");

    if (!modal) return;

    input.value = '';
    errorEl.style.display = 'none';
    modal.style.display = "flex";

    setTimeout(function() { input.focus(); }, 100);
}
```

### Yanıt Bildirimi Örneği (JS):
```javascript
if (window.swal) {
    swal('Başarılı', 'İade başarıyla iptal edildi.', 'success');
}
```
