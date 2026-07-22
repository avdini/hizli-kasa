# İade ve Değişim Ürünlerinde Birim Fiyat Düzenleme Mimarisi

## Genel Mantık
Sıfırdan iade ("Manuel İade") ve Değişim akışında müşterinin daha önce indirimli satın aldığı ürünler iadeye veya değişime getirildiğinde, kasiyer doğrudan ilgili ürünün birim fiyatına tıklayarak birim fiyatı güncelleyebilir.

## Kurallar
1. **Bağımsızlık:** Değişim iade ürünlerinin birim fiyatı (`item.price`) değiştirildiğinde, kasa genel iskontoları (`state.iskontoTutar`) veya %5 nakit/havale indirimleri bu satırlara kesinlikle etki etmez.
2. **Kasa Sepeti:** Kasa sepetindeki negatif değişim satırlarının (`_is_exchange_return: true`, `quantity < 0`) birim fiyatı tıklanarak güncellendiğinde, `CartManager.urunIskontoGuncelle` sadece o satırın `item.price` değerini ayarlar.
3. **İade Sepeti:** Sıfırdan İade ekranındaki iade sepetinde (sağ panel) birim fiyata tıklanarak (`RefundManager.editRefundCartItemPrice`) ürünün iade birim fiyatı girilebilir. Bu tutar değişime gönderildiğinde kasa sepetine doğrudan birim fiyat olarak aktarılır.
