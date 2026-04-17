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
    register_setting('hizli_kasa_ayar_grubu', 'hizli_kasa_varsayilan_online_depo');
}

/**
 * Kullanıcı Profili Entegrasyonu
 */
add_action('show_user_profile', 'hizli_kasa_user_warehouse_field');
add_action('edit_user_profile', 'hizli_kasa_user_warehouse_field');
add_action('personal_options_update', 'hizli_kasa_save_user_warehouse_field');
add_action('edit_user_profile_update', 'hizli_kasa_save_user_warehouse_field');

function hizli_kasa_user_warehouse_field($user) {
    if (!current_user_can('manage_options')) return;

    global $wpdb;
    $depo_table = $wpdb->prefix . 'hizli_kasa_depolar';
    $depolar = $wpdb->get_results("SELECT id, name FROM $depo_table ORDER BY name ASC");
    $selected_depo = get_user_meta($user->ID, '_hizli_kasa_depo_id', true);
    ?>
    <h3>Hızlı Kasa Yetkilendirme</h3>
    <table class="form-table">
        <tr>
            <th><label for="hizli_kasa_depo">Bağlı Olduğu Depo</label></th>
            <td>
                <select name="hizli_kasa_depo" id="hizli_kasa_depo">
                    <option value="">-- Depo Seçilmedi --</option>
                    <?php foreach ($depolar as $d): ?>
                        <option value="<?php echo $d->id; ?>" <?php selected($selected_depo, $d->id); ?>>
                            <?php echo esc_html($d->name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="description">Bu personel POS cihazında işlem yaptığında stoklar seçilen depodan düşülür.</p>
            </td>
        </tr>
    </table>
    <?php
}

function hizli_kasa_save_user_warehouse_field($user_id) {
    if (!current_user_can('manage_options')) return false;

    if (isset($_POST['hizli_kasa_depo'])) {
        update_user_meta($user_id, '_hizli_kasa_depo_id', sanitize_text_field($_POST['hizli_kasa_depo']));
    }
}

/**
 * AJAX Handlers for Admin Tools
 */
add_action('wp_ajax_hizli_kasa_setup', 'hizli_kasa_ajax_setup');
add_action('wp_ajax_hizli_kasa_reset', 'hizli_kasa_ajax_reset');

function hizli_kasa_ajax_setup() {
    if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'Yetkisiz işlem!']);
    
    $depo_id = intval($_POST['depo_id']);
    if (!$depo_id) wp_send_json_error(['message' => 'Geçersiz depo ID.']);

    require_once HIZLI_KASA_PATH . 'includes/classes/class-stock-manager.php';
    $result = Hizli_Kasa_Stock_Manager::initial_sync($depo_id);

    if ($result) {
        wp_send_json_success(['message' => 'Sistem başarıyla başlatıldı. Tüm stoklar kopyalandı.']);
    } else {
        wp_send_json_error(['message' => 'Bir hata oluştu.']);
    }
}

function hizli_kasa_ajax_reset() {
    if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'Yetkisiz işlem!']);
    
    require_once HIZLI_KASA_PATH . 'includes/classes/class-database.php';
    Hizli_Kasa_Database::drop_everything();
    Hizli_Kasa_Database::init(); // Tabloları boş olarak tekrar oluştur

    wp_send_json_success(['message' => 'Sistem tamamen sıfırlandı.']);
}

// Admin Ayarlar Sayfası Arayüzü
function hizli_kasa_ayarlar_sayfasi()
{
    $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'genel';

    global $wpdb;
    $depo_table = $wpdb->prefix . 'hizli_kasa_depolar';
    $depolar = $wpdb->get_results("SELECT id, name FROM $depo_table ORDER BY priority DESC");
    ?>
    <div class="wrap">
        <h1>Hızlı Kasa Ayarları</h1>
        
        <h2 class="nav-tab-wrapper">
            <a href="?page=hizli-kasa-ayarlar&tab=genel" class="nav-tab <?php echo $active_tab == 'genel' ? 'nav-tab-active' : ''; ?>">Genel Ayarlar</a>
            <a href="?page=hizli-kasa-ayarlar&tab=depolar" class="nav-tab <?php echo $active_tab == 'depolar' ? 'nav-tab-active' : ''; ?>">Depo Yönetimi</a>
            <a href="?page=hizli-kasa-ayarlar&tab=araclar" class="nav-tab <?php echo $active_tab == 'araclar' ? 'nav-tab-active' : ''; ?>">Sistem Araçları</a>
        </h2>

        <div style="margin-top: 20px;">
            <?php if ($active_tab == 'genel'): ?>
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
                    </table>
                    <?php submit_button('Ayarları Kaydet'); ?>
                </form>

            <?php elseif ($active_tab == 'depolar'): ?>
                <?php include HIZLI_KASA_PATH . 'includes/views/admin-depo-yonetimi.php'; ?>

            <?php elseif ($active_tab == 'araclar'): ?>
                <div class="card">
                    <h3>Sistemi Başlat: Stokları Kopyala</h3>
                    <p>Mevcut WooCommerce ana stoklarını seçilen depoya transfer eder ve sistemi kullanıma hazırlar.</p>
                    <form id="hizli-kasa-setup-form">
                        <select id="setup-target-depo" required>
                            <option value="">-- Hedef Depo Seçin --</option>
                            <?php foreach($depolar as $d): ?>
                                <option value="<?php echo $d->id; ?>"><?php echo esc_html($d->name); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" id="btn-hizli-kasa-setup" class="button button-primary">Sistemi Başlat</button>
                    </form>
                </div>

                <div class="card" style="margin-top:20px; border-color:#d63638;">
                    <h3 style="color:#d63638;">⚠️ Tehlikeli Bölge: Sistemi Sıfırla</h3>
                    <p>Bu işlem tüm depo verilerini, stok konumlarını ve hareket loglarını kalıcı olarak siler!</p>
                    <button type="button" id="btn-hizli-kasa-reset" class="button button-link-delete">Sistemi Sıfırla (Fabrika Ayarları)</button>
                </div>

                <script>
                jQuery(document).ready(function($) {
                    $('#btn-hizli-kasa-setup').on('click', function() {
                        const depoId = $('#setup-target-depo').val();
                        if(!depoId) return alert('Lütfen bir hedef depo seçin.');
                        if(!confirm('Tüm ürün stokları bu depoya kopyalanacak. Devam edilsin mi?')) return;
                        
                        $(this).prop('disabled', true).text('İşleniyor...');
                        $.post(ajaxurl, { action: 'hizli_kasa_setup', depo_id: depoId }, function(res) {
                            alert(res.data.message);
                            location.reload();
                        });
                    });

                    $('#btn-hizli-kasa-reset').on('click', function() {
                        if(!confirm('DİKKAT! Tüm veriler silinecek. Bu işlem geri alınamaz. Emin misiniz?')) return;
                        if(!confirm('SON UYARI: Gerçekten her şeyi silmek istiyor musunuz?')) return;

                        $(this).prop('disabled', true).text('Siliniyor...');
                        $.post(ajaxurl, { action: 'hizli_kasa_reset' }, function(res) {
                            alert(res.data.message);
                            location.reload();
                        });
                    });
                });
                </script>
            <?php endif; ?>
        </div>
    </div>
    <?php
}
