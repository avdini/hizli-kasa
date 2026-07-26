# Sipariş Düzenlemede İskonto Yönetimi

Bu doküman, POS sipariş düzenleme (Order Edit) sürecinde iskontoların nasıl işlendiğini ve kaleme yedirildiğini açıklar.

---

## Mimari Prensipler

1. **Sepet İskontolarının Ürün Kalemlerine Dağıtılması:**
   Hızlı Kasa POS üzerinde sepet düzeyinde girilen veya güncellenen iskontolar, `CartManager.iskontoTutariniGuncelle()` fonksiyonu vasıtasıyla ürün kalemlerine `line_discount` olarak dağıtılır.

2. **API Güncelleme Payload'u (`update-order`):**
   [order-processor.js](file:///c:/Users/fikri/Desktop/avdini.com/hizli-kasa/assets/js/modules/order-processor.js) tarafında sipariş güncellenirken her ürün kalemi için `line_discount` değeri sunucuya gönderilir.

3. **Backend İşleme (`hizli_kasa_update_order`):**
   - [includes/api/api-siparis.php](file:///c:/Users/fikri/Desktop/avdini.com/hizli-kasa/includes/api/api-siparis.php) içerisindeki `hizli_kasa_update_order()` fonksiyonu, gelen ürün bazlı `line_discount` tutarlarını ilgili sipariş kalemi meta verisine (`_hk_item_discount`) yazar.
   - Her bir kalemin nihai tutarı `$line_subtotal - $satir_nakit_indirim - $item_discount` olarak set edilir (`$item->set_total()`).
   - Eski tip veya mevcut tüm manuel iskonto fee kalemleri (`Düzenlenmiş İskonto`, `İskonto`) temizlenir (`remove_item()`).
   - Hiçbir koşulda siparişe `Düzenlenmiş İskonto` veya başka bir sepet ücreti (Fee line) **eklenmez**. Tüm iskontolar ürünlerin kendi satır toplamlarında yer alır.
