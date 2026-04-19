<?php
/**
 * Hızlı Kasa - Masraf Yönetimi Sekmesi
 *
 * Masraf giriş formu ve günlük masraf listesi.
 */
if (!defined('ABSPATH')) exit;
?>

<div id="masraf-sekmesi-icerik" style="padding: 20px; height: 100%; overflow-y: auto; background: #f0f2f5;">
    <div style="display: flex; gap: 25px; align-items: flex-start; max-width: 1200px; margin: 0 auto;">
        
        <!-- Masraf Ekleme Formu -->
        <div class="masraf-form-kort" style="background: white; padding: 25px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); width: 400px; flex-shrink: 0;">
            <h3 style="margin-top: 0; margin-bottom: 20px; color: #2c3e50; border-bottom: 2px solid #eee; padding-bottom: 10px;">💸 Yeni Masraf Ekle</h3>
            
            <form id="yeni-masraf-form">
                <div style="margin-bottom: 15px;">
                    <label style="display: block; font-weight: bold; margin-bottom: 5px; color: #555;">Kategori</label>
                    <select id="masraf-kategori" style="width: 100%; padding: 10px; border-radius: 6px; border: 1px solid #ddd; font-size: 15px;">
                        <option value="Mutfak (Çay, Kahve vs.)">Mutfak (Çay, Kahve vs.)</option>
                        <option value="Fatura (Elektrik, Su vs.)">Fatura (Elektrik, Su vs.)</option>
                        <option value="Ürün Masrafı">Ürün Masrafı</option>
                        <option value="Çalışan Giderleri">Çalışan Giderleri</option>
                        <option value="Kırtasiye / Ofis">Kırtasiye / Ofis</option>
                        <option value="Temizlik">Temizlik</option>
                        <option value="Diger">Diğer...</option>
                    </select>
                </div>

                <div id="ozel-kategori-alan" style="margin-bottom: 15px; display: none;">
                    <label style="display: block; font-weight: bold; margin-bottom: 5px; color: #555;">Özel Kategori Adı</label>
                    <input type="text" id="masraf-kategori-ozel" placeholder="Kategori ismini yazın" style="width: 100%; padding: 10px; border-radius: 6px; border: 1px solid #ddd;">
                </div>

                <div style="margin-bottom: 15px;">
                    <label style="display: block; font-weight: bold; margin-bottom: 5px; color: #555;">Tutar (TL)</label>
                    <input type="number" id="masraf-tutar" step="0.01" min="0.01" placeholder="0.00" style="width: 100%; padding: 10px; border-radius: 6px; border: 1px solid #ddd; font-size: 18px; font-weight: bold; color: #27ae60;">
                </div>

                <div style="margin-bottom: 15px;">
                    <label style="display: block; font-weight: bold; margin-bottom: 5px; color: #555;">Ödeme Yöntemi</label>
                    <div style="display: flex; gap: 10px;">
                        <label class="payment-method-row" style="flex: 1; border: 2px solid #ddd; padding: 10px; border-radius: 8px; cursor: pointer; text-align: center; font-weight: bold; transition: all 0.2s;">
                            <input type="radio" name="payment_method" value="nakit" checked style="display: none;">
                            💵 Nakit
                        </label>
                        <label class="payment-method-row" style="flex: 1; border: 2px solid #ddd; padding: 10px; border-radius: 8px; cursor: pointer; text-align: center; font-weight: bold; transition: all 0.2s;">
                            <input type="radio" name="payment_method" value="kart" style="display: none;">
                            💳 Kart
                        </label>
                        <label class="payment-method-row" style="flex: 1; border: 2px solid #ddd; padding: 10px; border-radius: 8px; cursor: pointer; text-align: center; font-weight: bold; transition: all 0.2s;">
                            <input type="radio" name="payment_method" value="iban" style="display: none;">
                            🏦 IBAN
                        </label>
                    </div>
                </div>

                <div style="margin-bottom: 20px;">
                    <label style="display: block; font-weight: bold; margin-bottom: 5px; color: #555;">Açıklama (Opsiyonel)</label>
                    <textarea id="masraf-aciklama" rows="3" placeholder="Masraf detayı..." style="width: 100%; padding: 10px; border-radius: 6px; border: 1px solid #ddd; resize: none;"></textarea>
                </div>

                <button type="button" id="masraf-kaydet-btn" style="width: 100%; padding: 15px; background: #2c3e50; color: white; border: none; border-radius: 8px; font-size: 16px; font-weight: bold; cursor: pointer; transition: background 0.3s;">
                    Kaydet ve Listeye Ekle
                </button>
            </form>
        </div>

        <!-- Günlük Masraf Listesi -->
        <div style="flex: 1; background: white; padding: 25px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 2px solid #eee; padding-bottom: 10px;">
                <h3 style="margin: 0; color: #2c3e50;">📊 Günlük Masraf Listesi</h3>
                <div style="font-size: 14px; color: #666; font-weight: bold;" id="masraf-liste-tarih">
                    Tarih: <?php echo date('d.m.Y'); ?>
                </div>
            </div>

            <div style="overflow-x: auto;">
                <table id="masraf-tablosu" style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="text-align: left; background: #f8f9fa;">
                            <th style="padding: 12px; border-bottom: 2px solid #dee2e6;">Kategori</th>
                            <th style="padding: 12px; border-bottom: 2px solid #dee2e6;">Açıklama</th>
                            <th style="padding: 12px; border-bottom: 2px solid #dee2e6;">Yöntem</th>
                            <th style="padding: 12px; border-bottom: 2px solid #dee2e6;">Tutar</th>
                            <th style="padding: 12px; border-bottom: 2px solid #dee2e6; text-align: center;">Eylem</th>
                        </tr>
                    </thead>
                    <tbody id="masraf-listesi-body">
                        <!-- JS ile doldurulacak -->
                        <tr>
                            <td colspan="5" style="padding: 40px; text-align: center; color: #999;">
                                Henüz masraf girilmedi.
                            </td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr style="background: #f8f9fa; font-weight: bold; font-size: 16px;">
                            <td colspan="3" style="padding: 15px; border-top: 2px solid #dee2e6; text-align: right;">GÜNLÜK TOPLAM:</td>
                            <td style="padding: 15px; border-top: 2px solid #dee2e6; color: #e74c3c;" id="gunluk-toplam-masraf">0.00 TL</td>
                            <td style="border-top: 2px solid #dee2e6;"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            
            <div id="masraf-ozet-bilgi" style="margin-top: 20px; padding: 15px; background: #fffdf0; border-left: 4px solid #f1c40f; font-size: 13px; color: #856404;">
                <strong>Not:</strong> Sadece <b>Nakit</b> ödeme yöntemi ile girilen masraflar Gün Sonu raporunda kasanızdan düşülür. Kart ve IBAN ödemeleri kasanızı etkilemez, takip amaçlı kaydedilir.
            </div>
        </div>
    </div>
</div>

<style>
    .payment-method-row:has(input:checked) {
        background: #2c3e50 !important;
        color: white !important;
        border-color: #2c3e50 !important;
    }
    
    .masraf-sil-btn {
        background: #ff7675;
        color: white;
        border: none;
        padding: 5px 10px;
        border-radius: 4px;
        cursor: pointer;
        font-size: 12px;
    }
    .masraf-sil-btn:hover {
        background: #d63031;
    }
</style>
