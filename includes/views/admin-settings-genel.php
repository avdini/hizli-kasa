<?php if (!defined('ABSPATH')) exit; ?>
                <form method="post" action="options.php">
                    <?php settings_fields('hizli_kasa_ayar_grubu'); ?>
                    <table class="form-table">
                        <tr valign="top">
                            <th scope="row">Varsayılan Sipariş Durumu</th>
                            <td>
                                <?php $secili_durum = get_option('hizli_kasa_siparis_durumu', 'processing'); ?>
                                <select name="hizli_kasa_siparis_durumu">
                                    <option value="processing" <?php selected($secili_durum, 'processing'); ?>>Hazırlanıyor (Önerilen)</option>
                                    <option value="completed" <?php selected($secili_durum, 'completed'); ?>>Tamamlandı</option>
                                    <option value="on-hold" <?php selected($secili_durum, 'on-hold'); ?>>Beklemede</option>
                                </select>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">Erişim Yetkisi Olan Roller</th>
                            <td>
                                <?php
                                 $secili_roller = get_option('hizli_kasa_yetkili_roller', ['administrator', 'shop_manager', 'hizli_kasa']);
                                $tum_roller = wp_roles()->get_names();

                                foreach ($tum_roller as $rol_slug => $rol_adi):
                                    $checked = in_array($rol_slug, (array) $secili_roller) ? 'checked' : '';
                                    ?>
                                    <label style="display:block; margin-bottom:5px;">
                                        <input type="checkbox" name="hizli_kasa_yetkili_roller[]"
                                            value="<?php echo esc_attr($rol_slug); ?>" <?php echo $checked; ?>>
                                        <?php echo translate_user_role($rol_adi); ?>
                                    </label>
                                 <?php endforeach; ?>
                                 <p class="description" style="margin-top:10px;">
                                     <strong>Öneri:</strong> Kasiyerleriniz için eklentinin özel olarak oluşturduğu <strong>Hızlı Kasa Kasiyer</strong> rolünü kullanmanız tavsiye edilir. 
                                     Bu rol sadece POS terminaline erişebilir, admin paneline giremez.
                                 </p>
                             </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">Online Satış Deposu (Öncelikli)</th>
                            <td>
                                <?php 
                                $online_depo = get_option('hizli_kasa_varsayilan_online_depo'); 
                                ?>
                                <select name="hizli_kasa_varsayilan_online_depo">
                                    <option value="">-- Depo Seçilmedi --</option>
                                    <?php foreach($depolar as $d): ?>
                                        <option value="<?php echo $d->id; ?>" <?php selected($online_depo, $d->id); ?>><?php echo esc_html($d->name); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description">Online satışlarda stok önce bu depodan düşülür. Yoksa öncelik sırasına göre diğerlerine bakılır.</p>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">Kritik Stok Eşiği</th>
                            <td>
                                <?php $kritik_esik = get_option('hizli_kasa_kritik_stok_esigi', 5); ?>
                                <input type="number" name="hizli_kasa_kritik_stok_esigi" value="<?php echo esc_attr($kritik_esik); ?>" min="0" step="1" class="small-text"> Adet
                                <p class="description">Depo stoğu bu rakama ve altına düştüğünde terminalde kırmızı uyarı gösterilir.</p>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">Toplam Kasa Sayısı</th>
                            <td>
                                <?php $kasa_sayisi = get_option('hizli_kasa_toplam_kasa', 3); ?>
                                <input type="number" name="hizli_kasa_toplam_kasa" value="<?php echo esc_attr($kasa_sayisi); ?>" min="1" max="20" step="1" class="small-text"> Adet
                                <p class="description">Sistemde kaç adet terminal (kasa) olduğunu belirtin.</p>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">Düzenlenebilir Sipariş Sayısı</th>
                            <td>
                                <?php $edit_limit = get_option('hizli_kasa_edit_order_limit', 5); ?>
                                <input type="number" name="hizli_kasa_edit_order_limit" value="<?php echo esc_attr($edit_limit); ?>" min="1" max="50" step="1" class="small-text"> Adet
                                <p class="description">Kasiyerin kasa sayfasından düzenleyebileceği (aynı gün içindeki) son sipariş sayısı.</p>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">Sipariş Düzenleme Kapsamı</th>
                            <td>
                                <?php $edit_kapsam = get_option('hizli_kasa_siparis_duzenle_kapsam', 'secili'); ?>
                                <select name="hizli_kasa_siparis_duzenle_kapsam">
                                    <option value="secili" <?php selected($edit_kapsam, 'secili'); ?>>Sadece Seçili Kasa</option>
                                    <option value="tum" <?php selected($edit_kapsam, 'tum'); ?>>Tüm Kasalar</option>
                                </select>
                                <p class="description">"Sipariş Düzenle" butonuna tıklandığında hangi siparişlerin gösterileceğini belirleyin.</p>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">Anlık Kasa Gösterge Kapsamı</th>
                            <td>
                                <?php $anlik_kapsam = get_option('hizli_kasa_anlik_kasa_kapsam', 'secili'); ?>
                                <select name="hizli_kasa_anlik_kasa_kapsam">
                                    <option value="secili" <?php selected($anlik_kapsam, 'secili'); ?>>Sadece Aktif Kasayı Göster</option>
                                    <option value="tum" <?php selected($anlik_kapsam, 'tum'); ?>>Tüm Kasaların Toplamını Göster</option>
                                </select>
                                <p class="description">Terminalin üst kısmındaki "Net Kasa" göstergesinin hangi veriyi baz alacağını seçin.</p>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">Sipariş Düzenleme</th>
                            <td>
                                <?php $edit_aktif = get_option('hizli_kasa_siparis_duzenle_aktif', '1'); ?>
                                <label>
                                    <input type="checkbox" name="hizli_kasa_siparis_duzenle_aktif" value="1" <?php checked($edit_aktif, '1'); ?>>
                                    Terminalde "Sipariş Düzenle" butonunu göster
                                </label>
                                <p class="description">Kasiyerlerin geçmiş siparişleri (aynı gün içindeki) düzenlemesine izin verin.</p>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">Gün Sonu Raporu</th>
                            <td>
                                <?php $gs_aktif = get_option('hizli_kasa_gun_sonu_aktif', '1'); ?>
                                <label>
                                    <input type="checkbox" name="hizli_kasa_gun_sonu_aktif" value="1" <?php checked($gs_aktif, '1'); ?>>
                                    Terminalde "Kasa Gün Sonu" butonunu göster
                                </label>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">Genel Rapor</th>
                            <td>
                                <?php $gr_aktif = get_option('hizli_kasa_genel_rapor_aktif', '1'); ?>
                                <label>
                                    <input type="checkbox" name="hizli_kasa_genel_rapor_aktif" value="1" <?php checked($gr_aktif, '1'); ?>>
                                    Terminalde "Genel Rapor" butonunu göster
                                </label>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">Küsürat Yuvarlama</th>
                            <td>
                                <?php $yuvarlama_aktif = get_option('hizli_kasa_yuvarlama_aktif', '1'); ?>
                                <label>
                                    <input type="checkbox" name="hizli_kasa_yuvarlama_aktif" value="1" <?php checked($yuvarlama_aktif, '1'); ?>>
                                    "Küsürat Yuvarla" butonunu göster
                                </label>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">Yuvarlama Modu</th>
                            <td>
                                <?php $yuvarlama_modu = get_option('hizli_kasa_yuvarlama_modu', '1'); ?>
                                <select name="hizli_kasa_yuvarlama_modu">
                                    <option value="0.5" <?php selected($yuvarlama_modu, '0.5'); ?>>0.50 TL'ye yuvarla</option>
                                    <option value="1" <?php selected($yuvarlama_modu, '1'); ?>>1 TL'ye yuvarla</option>
                                    <option value="5" <?php selected($yuvarlama_modu, '5'); ?>>5 TL'nin katına yuvarla</option>
                                    <option value="10" <?php selected($yuvarlama_modu, '10'); ?>>10 TL'nin katına yuvarla</option>
                                </select>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">SKU Eksikse Ürün ID Kullan</th>
                            <td>
                                <?php $fallback_sku = get_option('hizli_kasa_fallback_sku_to_id', '0'); ?>
                                <label>
                                    <input type="checkbox" name="hizli_kasa_fallback_sku_to_id" value="1" <?php checked($fallback_sku, '1'); ?>>
                                    SKU'su olmayan ürünlerde ürün ID'sini SKU olarak kabul et
                                </label>
                                <p class="description">Barkod yazdırma ekranında ürünün SKU'su yoksa uyarı vermek yerine ürün ID'si barkod numarası olarak kullanılır.</p>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">İskonto Telefon Zorunluluğu</th>
                            <td>
                                <?php $tel_esik = get_option('hizli_kasa_iskonto_telefon_esigi', 2000); ?>
                                <input type="number" name="hizli_kasa_iskonto_telefon_esigi" value="<?php echo esc_attr($tel_esik); ?>" min="0" step="10" class="small-text"> TL
                                <p class="description">Bu tutarın üzerindeki iskontolarda müşteri telefonu zorunlu olur. Devre dışı bırakmak için çok yüksek bir rakam girin.</p>
                            </td>
                        </tr>
                    </table>
                    <?php submit_button('Ayarları Kaydet'); ?>
                </form>