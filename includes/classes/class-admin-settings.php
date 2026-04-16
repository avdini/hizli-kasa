<?php
/**
 * Hızlı Kasa - Admin Ayarları
 *
 * Admin menüsü, ayar kayıtları ve ayarlar sayfası HTML çıktısı.
 *
 * @package HizliKasa
 */

if (!defined('ABSPATH'))
    exit;

// Admin Menüsü Ekleme
add_action('admin_menu', 'hizli_kasa_admin_menu');
function hizli_kasa_admin_menu()
{
    add_options_page(
        'Hızlı Kasa Ayarları',
        'Hızlı Kasa',
        'manage_options',
        'hizli-kasa-ayarlar',
        'hizli_kasa_ayarlar_sayfasi'
    );
}

// Ayarları Kaydetme
add_action('admin_init', 'hizli_kasa_ayarlari_kaydet');
function hizli_kasa_ayarlari_kaydet()
{
    register_setting('hizli_kasa_ayar_grubu', 'hizli_kasa_siparis_durumu');
    register_setting('hizli_kasa_ayar_grubu', 'hizli_kasa_yetkili_roller');
    register_setting('hizli_kasa_ayar_grubu', 'hizli_kasa_yuvarlama_aktif', array(
        'sanitize_callback' => function($val) { return $val ? '1' : '0'; }
    ));
    register_setting('hizli_kasa_ayar_grubu', 'hizli_kasa_yuvarlama_modu');
}

// Admin Ayarlar Sayfası Arayüzü
function hizli_kasa_ayarlar_sayfasi()
{
    ?>
    <div class="wrap">
        <h2>Hızlı Kasa Ayarları</h2>
        <form method="post" action="options.php">
            <?php settings_fields('hizli_kasa_ayar_grubu'); ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Varsayılan Sipariş Durumu</th>
                    <td>
                        <?php $secili_durum = get_option('hizli_kasa_siparis_durumu', 'processing'); ?>
                        <select name="hizli_kasa_siparis_durumu">
                            <option value="processing" <?php selected($secili_durum, 'processing'); ?>>Hazırlanıyor
                                (Önerilen)</option>
                            <option value="completed" <?php selected($secili_durum, 'completed'); ?>>Tamamlandı</option>
                            <option value="on-hold" <?php selected($secili_durum, 'on-hold'); ?>>Beklemede</option>
                        </select>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Erişim Yetkisi Olan Roller</th>
                    <td>
                        <?php
                        $secili_roller = get_option('hizli_kasa_yetkili_roller', array('administrator', 'shop_manager'));
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
                        <p class="description">Sadece seçilen role sahip kullanıcılar hızlı kasa ekranını görebilir ve işlem
                            yapabilir.</p>
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
                        <p class="description">Kasada küsürat yuvarlama butonunu aktif eder. Kapatıldığında buton gizlenir.</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Yuvarlama Modu</th>
                    <td>
                        <?php $yuvarlama_modu = get_option('hizli_kasa_yuvarlama_modu', '1'); ?>
                        <select name="hizli_kasa_yuvarlama_modu">
                            <option value="0.5" <?php selected($yuvarlama_modu, '0.5'); ?>>0.50 TL'ye yuvarla (Örn: 12.70 → 12.50)</option>
                            <option value="1" <?php selected($yuvarlama_modu, '1'); ?>>1 TL'ye yuvarla (Örn: 12.70 → 12.00)</option>
                            <option value="5" <?php selected($yuvarlama_modu, '5'); ?>>5 TL'nin katına yuvarla (Örn: 123 → 120)</option>
                            <option value="10" <?php selected($yuvarlama_modu, '10'); ?>>10 TL'nin katına yuvarla (Örn: 123 → 120)</option>
                        </select>
                        <p class="description">Küsürat yuvarlama butonuna basıldığında toplam hangi basamağa yuvarlanacağını belirler.</p>
                    </td>
                </tr>
            </table>
            <?php submit_button('Ayarları Kaydet'); ?>
        </form>
    </div>
    <?php
}
