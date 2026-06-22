<?php if (!defined('ABSPATH')) exit; ?>
                <form method="post" action="options.php">
                    <?php settings_fields('hizli_kasa_ayar_grubu'); ?>
                    <table class="form-table">
                        <tr valign="top">
                            <th scope="row">VarsayÄ±lan SipariÅŸ Durumu</th>
                            <td>
                                <?php $secili_durum = get_option('hizli_kasa_siparis_durumu', 'processing'); ?>
                                <select name="hizli_kasa_siparis_durumu">
                                    <option value="processing" <?php selected($secili_durum, 'processing'); ?>>HazÄ±rlanÄ±yor (Ã–nerilen)</option>
                                    <option value="completed" <?php selected($secili_durum, 'completed'); ?>>TamamlandÄ±</option>
                                    <option value="on-hold" <?php selected($secili_durum, 'on-hold'); ?>>Beklemede</option>
                                </select>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">EriÅŸim Yetkisi Olan Roller</th>
                            <td>
                                <?php
                                 $secili_roller = get_option('hizli_kasa_yetkili_roller', array('administrator', 'shop_manager', 'hizli_kasa'));
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
                                     <strong>Ã–neri:</strong> Kasiyerleriniz iÃ§in eklentinin Ã¶zel olarak oluÅŸturduÄŸu <strong>HÄ±zlÄ± Kasa Kasiyer</strong> rolÃ¼nÃ¼ kullanmanÄ±z tavsiye edilir. 
                                     Bu rol sadece POS terminaline eriÅŸebilir, admin paneline giremez.
                                 </p>
                             </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">Online SatÄ±ÅŸ Deposu (Ã–ncelikli)</th>
                            <td>
                                <?php 
                                $online_depo = get_option('hizli_kasa_varsayilan_online_depo'); 
                                ?>
                                <select name="hizli_kasa_varsayilan_online_depo">
                                    <option value="">-- Depo SeÃ§ilmedi --</option>
                                    <?php foreach($depolar as $d): ?>
                                        <option value="<?php echo $d->id; ?>" <?php selected($online_depo, $d->id); ?>><?php echo esc_html($d->name); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description">Online satÄ±ÅŸlarda stok Ã¶nce bu depodan dÃ¼ÅŸÃ¼lÃ¼r. Yoksa Ã¶ncelik sÄ±rasÄ±na gÃ¶re diÄŸerlerine bakÄ±lÄ±r.</p>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">Kritik Stok EÅŸiÄŸi</th>
                            <td>
                                <?php $kritik_esik = get_option('hizli_kasa_kritik_stok_esigi', 5); ?>
                                <input type="number" name="hizli_kasa_kritik_stok_esigi" value="<?php echo esc_attr($kritik_esik); ?>" min="0" step="1" class="small-text"> Adet
                                <p class="description">Depo stoÄŸu bu rakama ve altÄ±na dÃ¼ÅŸtÃ¼ÄŸÃ¼nde terminalde kÄ±rmÄ±zÄ± uyarÄ± gÃ¶sterilir.</p>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">Toplam Kasa SayÄ±sÄ±</th>
                            <td>
                                <?php $kasa_sayisi = get_option('hizli_kasa_toplam_kasa', 3); ?>
                                <input type="number" name="hizli_kasa_toplam_kasa" value="<?php echo esc_attr($kasa_sayisi); ?>" min="1" max="20" step="1" class="small-text"> Adet
                                <p class="description">Sistemde kaÃ§ adet terminal (kasa) olduÄŸunu belirtin.</p>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">DÃ¼zenlenebilir SipariÅŸ SayÄ±sÄ±</th>
                            <td>
                                <?php $edit_limit = get_option('hizli_kasa_edit_order_limit', 5); ?>
                                <input type="number" name="hizli_kasa_edit_order_limit" value="<?php echo esc_attr($edit_limit); ?>" min="1" max="50" step="1" class="small-text"> Adet
                                <p class="description">Kasiyerin kasa sayfasÄ±ndan dÃ¼zenleyebileceÄŸi (aynÄ± gÃ¼n iÃ§indeki) son sipariÅŸ sayÄ±sÄ±.</p>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">SipariÅŸ DÃ¼zenleme KapsamÄ±</th>
                            <td>
                                <?php $edit_kapsam = get_option('hizli_kasa_siparis_duzenle_kapsam', 'secili'); ?>
                                <select name="hizli_kasa_siparis_duzenle_kapsam">
                                    <option value="secili" <?php selected($edit_kapsam, 'secili'); ?>>Sadece SeÃ§ili Kasa</option>
                                    <option value="tum" <?php selected($edit_kapsam, 'tum'); ?>>TÃ¼m Kasalar</option>
                                </select>
                                <p class="description">"SipariÅŸ DÃ¼zenle" butonuna tÄ±klandÄ±ÄŸÄ±nda hangi sipariÅŸlerin gÃ¶sterileceÄŸini belirleyin.</p>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">AnlÄ±k Kasa GÃ¶sterge KapsamÄ±</th>
                            <td>
                                <?php $anlik_kapsam = get_option('hizli_kasa_anlik_kasa_kapsam', 'secili'); ?>
                                <select name="hizli_kasa_anlik_kasa_kapsam">
                                    <option value="secili" <?php selected($anlik_kapsam, 'secili'); ?>>Sadece Aktif KasayÄ± GÃ¶ster</option>
                                    <option value="tum" <?php selected($anlik_kapsam, 'tum'); ?>>TÃ¼m KasalarÄ±n ToplamÄ±nÄ± GÃ¶ster</option>
                                </select>
                                <p class="description">Terminalin Ã¼st kÄ±smÄ±ndaki "Net Kasa" gÃ¶stergesinin hangi veriyi baz alacaÄŸÄ±nÄ± seÃ§in.</p>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">SipariÅŸ DÃ¼zenleme</th>
                            <td>
                                <?php $edit_aktif = get_option('hizli_kasa_siparis_duzenle_aktif', '1'); ?>
                                <label>
                                    <input type="checkbox" name="hizli_kasa_siparis_duzenle_aktif" value="1" <?php checked($edit_aktif, '1'); ?>>
                                    Terminalde "SipariÅŸ DÃ¼zenle" butonunu gÃ¶ster
                                </label>
                                <p class="description">Kasiyerlerin geÃ§miÅŸ sipariÅŸleri (aynÄ± gÃ¼n iÃ§indeki) dÃ¼zenlemesine izin verin.</p>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">GÃ¼n Sonu Raporu</th>
                            <td>
                                <?php $gs_aktif = get_option('hizli_kasa_gun_sonu_aktif', '1'); ?>
                                <label>
                                    <input type="checkbox" name="hizli_kasa_gun_sonu_aktif" value="1" <?php checked($gs_aktif, '1'); ?>>
                                    Terminalde "Kasa GÃ¼n Sonu" butonunu gÃ¶ster
                                </label>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">Genel Rapor</th>
                            <td>
                                <?php $gr_aktif = get_option('hizli_kasa_genel_rapor_aktif', '1'); ?>
                                <label>
                                    <input type="checkbox" name="hizli_kasa_genel_rapor_aktif" value="1" <?php checked($gr_aktif, '1'); ?>>
                                    Terminalde "Genel Rapor" butonunu gÃ¶ster
                                </label>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">KÃ¼sÃ¼rat Yuvarlama</th>
                            <td>
                                <?php $yuvarlama_aktif = get_option('hizli_kasa_yuvarlama_aktif', '1'); ?>
                                <label>
                                    <input type="checkbox" name="hizli_kasa_yuvarlama_aktif" value="1" <?php checked($yuvarlama_aktif, '1'); ?>>
                                    "KÃ¼sÃ¼rat Yuvarla" butonunu gÃ¶ster
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
                                    <option value="5" <?php selected($yuvarlama_modu, '5'); ?>>5 TL'nin katÄ±na yuvarla</option>
                                    <option value="10" <?php selected($yuvarlama_modu, '10'); ?>>10 TL'nin katÄ±na yuvarla</option>
                                </select>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">SKU Eksikse ÃœrÃ¼n ID Kullan</th>
                            <td>
                                <?php $fallback_sku = get_option('hizli_kasa_fallback_sku_to_id', '0'); ?>
                                <label>
                                    <input type="checkbox" name="hizli_kasa_fallback_sku_to_id" value="1" <?php checked($fallback_sku, '1'); ?>>
                                    SKU'su olmayan Ã¼rÃ¼nlerde Ã¼rÃ¼n ID'sini SKU olarak kabul et
                                </label>
                                <p class="description">Barkod yazdÄ±rma ekranÄ±nda Ã¼rÃ¼nÃ¼n SKU'su yoksa uyarÄ± vermek yerine Ã¼rÃ¼n ID'si barkod numarasÄ± olarak kullanÄ±lÄ±r.</p>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">Ä°skonto Telefon ZorunluluÄŸu</th>
                            <td>
                                <?php $tel_esik = get_option('hizli_kasa_iskonto_telefon_esigi', 2000); ?>
                                <input type="number" name="hizli_kasa_iskonto_telefon_esigi" value="<?php echo esc_attr($tel_esik); ?>" min="0" step="10" class="small-text"> TL
                                <p class="description">Bu tutarÄ±n Ã¼zerindeki iskontolarda mÃ¼ÅŸteri telefonu zorunlu olur. Devre dÄ±ÅŸÄ± bÄ±rakmak iÃ§in Ã§ok yÃ¼ksek bir rakam girin.</p>
                            </td>
                        </tr>
                    </table>
                    <?php submit_button('AyarlarÄ± Kaydet'); ?>
                </form>