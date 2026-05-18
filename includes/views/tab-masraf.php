<?php
/**
 * Hızlı Kasa - Masraf Yönetimi Sekmesi
 *
 * Masraf giriş formu ve Masraf listesi.
 */
if (!defined('ABSPATH')) exit;
?>

<div id="masraf-sekmesi-icerik" class="hk-tab-scrollable-container">
    <div class="hk-split-layout">
        
        <!-- Masraf Ekleme Formu -->
        <div class="hk-panel masraf-form-panel">
            <h3 class="hk-panel-title">💸 Yeni Masraf Ekle</h3>
            
            <form id="yeni-masraf-form">
                <div class="masraf-form-group">
                    <label>Kategori</label>
                    <select id="masraf-kategori" class="hk-input">
                        <option value="Çalışan Giderleri" selected>Çalışan Giderleri</option>
                        <option value="Ürün Masrafı">Ürün Masrafı</option>
                        <option value="Kargo">Kargo</option>
                        <option value="Mutfak (Çay, Kahve vs.)">Mutfak (Çay, Kahve vs.)</option>
                        <option value="Fatura (Elektrik, Su vs.)">Fatura (Elektrik, Su vs.)</option>
                        <option value="Kırtasiye / Ofis">Kırtasiye / Ofis</option>
                        <option value="Temizlik">Temizlik</option>
                        <option value="Diger">Diğer...</option>
                    </select>
                </div>

                <div id="ozel-kategori-alan" class="masraf-form-group" style="display: none;">
                    <label>Özel Kategori Adı</label>
                    <input type="text" id="masraf-kategori-ozel" class="hk-input" placeholder="Kategori ismini yazın">
                </div>

                <div class="masraf-form-group">
                    <label>Tutar (TL)</label>
                    <input type="text" id="masraf-tutar" class="hk-input hk-currency-mask" placeholder="0,00" inputmode="decimal">
                </div>

                <div class="masraf-form-group">
                    <label>Ödeme Yöntemi</label>
                    <div class="masraf-payment-methods">
                        <label class="payment-method-row">
                            <input type="radio" name="payment_method" value="nakit" checked style="display: none;">
                            💵 Nakit
                        </label>
                        <label class="payment-method-row">
                            <input type="radio" name="payment_method" value="kart" style="display: none;">
                            💳 Kart
                        </label>
                        <label class="payment-method-row">
                            <input type="radio" name="payment_method" value="iban" style="display: none;">
                            🏦 IBAN
                        </label>
                    </div>
                </div>

                <div class="masraf-form-group">
                    <label>Açıklama (Opsiyonel)</label>
                    <textarea id="masraf-aciklama" class="hk-input" rows="3" placeholder="Masraf detayı..."></textarea>
                </div>

                <button type="button" id="masraf-kaydet-btn" class="hk-btn-primary">
                    Kaydet ve Listeye Ekle
                </button>
            </form>
        </div>

        <!-- Günlük Masraf Listesi -->
        <div class="hk-panel hk-panel-flex">
            <div class="hk-panel-header">
                <h3 class="hk-panel-title">📊 Günlük Masraf Listesi</h3>
                <div class="hk-panel-meta" id="masraf-liste-tarih">
                    Tarih: <?php echo date('d.m.Y'); ?>
                </div>
            </div>

            <div class="masraf-table-wrapper">
                <table id="masraf-tablosu" class="hk-table">
                    <thead>
                        <tr class="hk-table-head">
                            <th>Kategori</th>
                            <th>Açıklama</th>
                            <th>Yöntem</th>
                            <th>Tutar</th>
                            <th style="text-align: center;">Eylem</th>
                        </tr>
                    </thead>
                    <tbody id="masraf-listesi-body">
                        <!-- JS ile doldurulacak -->
                        <tr>
                            <td colspan="5" class="masraf-empty-row">
                                Henüz masraf girilmedi.
                            </td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr class="hk-table-foot">
                            <td colspan="3" style="text-align: right;">GÜNLÜK TOPLAM:</td>
                            <td id="gunluk-toplam-masraf">0.00 TL</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            
            <div id="masraf-ozet-bilgi" class="hk-panel-note">
                <strong>Not:</strong> Sadece <b>Nakit</b> ödeme yöntemi ile girilen masraflar Gün Sonu raporunda kasanızdan düşülür. Kart ve IBAN ödemeleri kasanızı etkilemez, takip amaçlı kaydedilir.
            </div>
        </div>
    </div>
</div>
