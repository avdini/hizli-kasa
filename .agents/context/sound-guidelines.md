# Ses Bildirimleri ve Sentezleme Geliştirme Yönergesi

Bu belge, Hızlı Kasa eklentisindeki ses bildirim sisteminin mimarisini ve yeni sesler/hazır ayarlar (presets) ekleme kurallarını açıklar.

---

## 1. Genel Mimari

Hızlı Kasa eklentisinde sesler, dışarıdan ses dosyaları (MP3/WAV vb.) indirilmeksizin tarayıcının yerel **Web Audio API** sentezleyicisi (`AudioContext`, `OscillatorNode`, `GainNode`) kullanılarak dinamik olarak üretilir. Bu sayede sıfır ağ gecikmesiyle, son derece hafif ve özelleştirilebilir sesler çalınır.

Tüm ses çalma işlemleri **`HizliKasa.Sound`** modülü üzerinden yürütülür:
- Ses çalmak için: `HizliKasa.Sound.play(type)` (Örn: `type = 'success'` veya `type = 'error'`).
- Ayarlar önbelleği: Ses seviyesi ve ses çeşidi öncelikle `localStorage` üzerinde saklanır. `localStorage` boşsa `kasaAyar.soundSettings` (veritabanı user meta) kullanılır, o da boşsa varsayılan klasik bip ayarları geçerlidir.
- Veritabanı sorgusu atmamak için ses çalma aşamasında asla sunucuya istek gönderilmez, her zaman yerel önbellek kullanılır.

---

## 2. Yeni Hazır Ayar (Preset) Ekleme

Tüm ses desenleri [sound-manager.js](file:///c:/Users/fikri/Desktop/avdini.com/hizli-kasa/assets/js/modules/sound-manager.js) içerisindeki `presets` nesnesinde tanımlıdır.

Yeni bir hazır ayar eklemek için:
1. `HK.Sound.presets` nesnesine yeni bir anahtar ekleyin (Örn: `futuristic`).
2. Bu anahtarın altında ses tipleri için fonksiyonları tanımlayın (`success` ve `error`).
3. Fonksiyonlar parametre olarak `(ctx, dest)` alır:
   - `ctx`: Aktif `AudioContext` nesnesi.
   - `dest`: Master ses seviyesi çarpanı uygulanmış olan hedef `GainNode`. Sentezlediğiniz sesin çıkışını (local gain) bu hedefe bağlamalısınız.

### Örnek Sentezleyici Tasarımı:
```javascript
presets: {
    futuristic: {
        success: function(ctx, dest) {
            var osc = ctx.createOscillator();
            var gain = ctx.createGain();
            osc.connect(gain);
            gain.connect(dest); // Önemli: Master hedefe bağla

            osc.type = 'sine';
            osc.frequency.setValueAtTime(1000, ctx.currentTime);
            gain.gain.setValueAtTime(0.1, ctx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.15);
            
            osc.start(ctx.currentTime);
            osc.stop(ctx.currentTime + 0.15);
        },
        error: function(ctx, dest) {
            // Hata sesi sentezleme mantığı
        }
    }
}
```

4. [tab-ayarlar.php](file:///c:/Users/fikri/Desktop/avdini.com/hizli-kasa/includes/views/tab-ayarlar.php) dosyasındaki `#hk-sound-preset` seçim alanına yeni seçeneği ekleyin:
```html
<option value="futuristic">Futuristik Bip</option>
```
5. [class-api-user-sound.php](file:///c:/Users/fikri/Desktop/avdini.com/hizli-kasa/includes/api/v2/controllers/class-api-user-sound.php) dosyasındaki input validasyonuna yeni preset ismini ekleyin:
```php
'validate_callback' => function($param) {
    return in_array($param, ['classic', 'soft', 'retro', 'digital', 'futuristic'], true);
}
```

---

## 3. Yeni Ses Tipi (Event Type) Ekleme

Şu anda sistemde `success` ve `error` olmak üzere iki temel ses tipi tetiklenmektedir. Sistemde yeni bir eyleme özel ses (örn: barkod okutulduğunda `scan`, sevk gönderildiğinde `ship` veya uyarı durumlarında `warning`) eklemek isterseniz:

1. Tüm hazır ayarların (presets) altına bu yeni tipi ekleyin:
```javascript
classic: {
    success: function(ctx, dest) { ... },
    error: function(ctx, dest) { ... },
    warning: function(ctx, dest) {
        var osc = ctx.createOscillator();
        var gain = ctx.createGain();
        osc.connect(gain);
        gain.connect(dest);
        
        osc.type = 'triangle';
        osc.frequency.setValueAtTime(440, ctx.currentTime);
        gain.gain.setValueAtTime(0.1, ctx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.2);
        
        osc.start(ctx.currentTime);
        osc.stop(ctx.currentTime + 0.2);
    }
}
```
2. Sesi çalmak istediğiniz yerde şu şekilde tetikleyin:
```javascript
HizliKasa.Sound.play('warning');
```
