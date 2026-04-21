<?php
/**
 * Hızlı Kasa - Masraf Yönetimi Sekmesi
 *
 * Masraf giriş formu ve günlük masraf listesi.
 */
if (!defined('ABSPATH')) exit;
?>

<div id="masraf-sekmesi-icerik" class="hk-tab-scrollable-container">
    <div class="hk-split-layout">
        
        <!-- Masraf Ekleme Formu -->
        <div class="hk-panel masraf-form-panel">
            <h3 class="hk-panel-title">💸 Yeni Masraf Ekle</h3>
            
            <form id="yeni-masraf-form">
                <div style="margin-bottom: 20px;">
                    <label>Kategori</label>
                    <select id="masraf-kategori" class="hk-input">
                        <option value="Mutfak (Çay, Kahve vs.)">Mutfak (Çay, Kahve vs.)</option>
                        <option value="Fatura (Elektrik, Su vs.)">Fatura (Elektrik, Su vs.)</option>
                        <option value="Ürün Masrafı">Ürün Masrafı</option>
                        <option value="Çalışan Giderleri">Çalışan Giderleri</option>
                        <option value="Kırtasiye / Ofis">Kırtasiye / Ofis</option>
                        <option value="Temizlik">Temizlik</option>
                        <option value="Diger">Diğer...</option>
                    </select>
                </div>

                <div id="ozel-kategori-alan" style="margin-bottom: 20px; display: none;">
                    <label>Özel Kategori Adı</label>
                    <input type="text" id="masraf-kategori-ozel" class="hk-input" placeholder="Kategori ismini yazın">
                </div>

                <div style="margin-bottom: 20px;">
                    <label>Tutar (TL)</label>
                    <input type="number" id="masraf-tutar" class="hk-input" step="0.01" min="0.01" placeholder="0.00" style="font-size: 20px; font-weight: 800; color: var(--hk-success);">
                </div>

                <div style="margin-bottom: 20px;">
                    <label>Ödeme Yöntemi</label>
                    <div style="display: flex; gap: 10px;">
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

                <div style="margin-bottom: 25px;">
                    <label>Açıklama (Opsiyonel)</label>
                    <textarea id="masraf-aciklama" class="hk-input" rows="3" placeholder="Masraf detayı..." style="resize: none;"></textarea>
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

            <div style="overflow-x: auto;">
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
                            <td colspan="5" style="padding: 40px; text-align: center; color: var(--hk-text-muted);">
                                Henüz masraf girilmedi.
                            </td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr class="hk-table-foot">
                            <td colspan="3" style="text-align: right;">GÜNLÜK TOPLAM:</td>
                            <td style="color: var(--hk-danger);" id="gunluk-toplam-masraf">0.00 TL</td>
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

<style>
    .payment-method-row {
        flex: 1; 
        border: 2px solid var(--hk-border); 
        padding: 12px; 
        border-radius: 10px; 
        cursor: pointer; 
        text-align: center; 
        font-weight: 800; 
        transition: all 0.2s;
        background: var(--hk-bg-card);
        color: var(--hk-text-main);
    }
    
    .payment-method-row:has(input:checked) {
        background: var(--hk-accent) !important;
        color: white !important;
        border-color: var(--hk-accent) !important;
        box-shadow: 0 4px 10px rgba(59, 130, 246, 0.3);
    }
    
    .masraf-sil-btn {
        background: var(--hk-danger);
        color: white;
        border: none;
        padding: 6px 12px;
        border-radius: 6px;
        cursor: pointer;
        font-size: 12px;
        font-weight: 700;
    }
    
    .masraf-sil-btn:hover {
        opacity: 0.8;
    }
</style>
