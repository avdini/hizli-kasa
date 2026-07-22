<?php
if (!defined('ABSPATH')) {
    exit;
}

$secili_pos_sayfasi = function_exists('hizli_kasa_get_pos_page_id') ? hizli_kasa_get_pos_page_id() : (int)get_option('hizli_kasa_pos_page_id', 0);
$secili_durum       = get_option('hizli_kasa_siparis_durumu', 'processing');
$secili_roller      = get_option('hizli_kasa_yetkili_roller', ['administrator', 'shop_manager', 'hizli_kasa']);
$online_depo        = get_option('hizli_kasa_varsayilan_online_depo');
$kritik_esik        = get_option('hizli_kasa_kritik_stok_esigi', 5);
$kasa_sayisi        = get_option('hizli_kasa_toplam_kasa', 3);
$edit_limit         = get_option('hizli_kasa_edit_order_limit', 5);
$edit_kapsam        = get_option('hizli_kasa_siparis_duzenle_kapsam', 'secili');
$anlik_kapsam       = get_option('hizli_kasa_anlik_kasa_kapsam', 'secili');
$edit_aktif         = get_option('hizli_kasa_siparis_duzenle_aktif', '1');
$gs_aktif           = get_option('hizli_kasa_gun_sonu_aktif', '1');
$gr_aktif           = get_option('hizli_kasa_genel_rapor_aktif', '1');
$yuvarlama_aktif    = get_option('hizli_kasa_yuvarlama_aktif', '1');
$yuvarlama_modu     = get_option('hizli_kasa_yuvarlama_modu', '1');
$fallback_sku       = get_option('hizli_kasa_fallback_sku_to_id', '0');
$tel_esik           = get_option('hizli_kasa_iskonto_telefon_esigi', 2000);
$tum_roller         = wp_roles()->get_names();
?>

<div class="hk-settings-container">
    <form method="post" action="options.php">
        <?php settings_fields('hizli_kasa_ayar_grubu'); ?>

        <!-- Form Top Action Header -->
        <div class="hk-settings-header">
            <div class="hk-settings-header-title">
                <h1>
                    <span class="dashicons dashicons-admin-generic" style="color:var(--hk-primary); font-size:26px; width:26px; height:26px;"></span>
                    Genel Ayarlar
                </h1>
                <p>POS terminali, yetkilendirme, stok ve ödeme kurallarını buradan yapılandırabilirsiniz.</p>
            </div>
            <button type="submit" class="hk-btn-save">
                <span class="dashicons dashicons-saved"></span> Ayarları Kaydet
            </button>
        </div>

        <!-- Grid Layout: Sidebar & Content -->
        <div class="hk-settings-layout">
            <!-- Sticky Sidebar -->
            <div class="hk-settings-sidebar">
                <!-- Navigation -->
                <nav class="hk-settings-nav">
                    <a href="#sec-pos" class="hk-settings-nav-item active">
                        <span class="dashicons dashicons-store"></span> POS & Sayfa
                    </a>
                    <a href="#sec-roles" class="hk-settings-nav-item">
                        <span class="dashicons dashicons-admin-users"></span> Yetkiler & Roller
                    </a>
                    <a href="#sec-depo" class="hk-settings-nav-item">
                        <span class="dashicons dashicons-database"></span> Depo & Stok
                    </a>
                    <a href="#sec-features" class="hk-settings-nav-item">
                        <span class="dashicons dashicons-tablet"></span> Terminal Özellikleri
                    </a>
                    <a href="#sec-rules" class="hk-settings-nav-item">
                        <span class="dashicons dashicons-money-alt"></span> Ödeme & Kurallar
                    </a>
                </nav>

                <!-- Canlı Sistem Durum Widget'ı -->
                <div class="hk-sidebar-widget">
                    <h4 class="hk-widget-title">
                        <span class="dashicons dashicons-chart-bar" style="color:var(--hk-primary);"></span>
                        Sistem & POS Durumu
                    </h4>
                    <ul class="hk-widget-list">
                        <li class="hk-widget-item">
                            <span class="hk-widget-item-label">POS Sayfası:</span>
                            <span class="hk-widget-item-value">
                                <?php if ($secili_pos_sayfasi > 0): ?>
                                    <span style="color:#16a34a; font-weight:700;">🟢 Aktif</span>
                                <?php else: ?>
                                    <span style="color:#d63638; font-weight:700;">🔴 Seçilmedi</span>
                                <?php endif; ?>
                            </span>
                        </li>
                        <li class="hk-widget-item">
                            <span class="hk-widget-item-label">Online Depo:</span>
                            <span class="hk-widget-item-value">
                                <?php 
                                $online_depo_adi = 'Tüm Depolar';
                                foreach($depolar as $d) {
                                    if ($d->id == $online_depo) {
                                        $online_depo_adi = esc_html($d->name);
                                        break;
                                    }
                                }
                                echo $online_depo_adi;
                                ?>
                            </span>
                        </li>
                        <li class="hk-widget-item">
                            <span class="hk-widget-item-label">Aktif Kasa:</span>
                            <span class="hk-widget-item-value"><?php echo (int)$kasa_sayisi; ?> Adet</span>
                        </li>
                        <li class="hk-widget-item">
                            <span class="hk-widget-item-label">Kasiyer Yetkisi:</span>
                            <span class="hk-widget-item-value" style="color:#16a34a;">Etkin</span>
                        </li>
                    </ul>
                </div>

                <!-- Hızlı Eylemler Widget'ı -->
                <div class="hk-sidebar-widget">
                    <h4 class="hk-widget-title">
                        <span class="dashicons dashicons-lightning" style="color:#eab308;"></span>
                        Hızlı Eylemler
                    </h4>
                    <div class="hk-widget-actions">
                        <a href="<?php echo esc_url(function_exists('hizli_kasa_get_pos_url') ? hizli_kasa_get_pos_url() : home_url('/hizli-kasa-pos/')); ?>" target="_blank" class="hk-widget-btn hk-widget-btn-primary">
                            <span class="dashicons dashicons-external"></span> POS Terminali Aç
                        </a>
                        <a href="?page=hizli-kasa&tab=onbellek" class="hk-widget-btn">
                            <span class="dashicons dashicons-update"></span> Önbelleği Temizle
                        </a>
                        <a href="?page=hizli-kasa&tab=stok" class="hk-widget-btn">
                            <span class="dashicons dashicons-database"></span> Stok Yönetimi
                        </a>
                    </div>
                </div>
            </div>

            <!-- Main Content Area -->
            <div class="hk-settings-content">

                <!-- 1. POS & Sayfa Yapılandırması -->
                <div class="hk-settings-card" id="sec-pos">
                    <div class="hk-settings-card-header">
                        <div class="hk-settings-card-icon">
                            <span class="dashicons dashicons-store"></span>
                        </div>
                        <div class="hk-settings-card-title">
                            <h3>POS & Sayfa Yapılandırması</h3>
                            <p>POS terminalinin çalışacağı varsayılan sayfa ve genel davranışlar.</p>
                        </div>
                    </div>

                    <!-- Row: POS Sayfası -->
                    <div class="hk-settings-row">
                        <div class="hk-settings-label">
                            <label for="hizli_kasa_pos_page_id">POS Terminal Sayfası</label>
                            <p class="hk-settings-desc">
                                <code>[hizli_kasa]</code> kısa kodunun eklendiği sayfayı seçin. Boş bırakırsanız sistem veritabanından otomatik algılar.
                            </p>
                        </div>
                        <div class="hk-settings-field">
                            <?php 
                            wp_dropdown_pages([
                                'name' => 'hizli_kasa_pos_page_id',
                                'id' => 'hizli_kasa_pos_page_id',
                                'class' => 'hk-select',
                                'selected' => $secili_pos_sayfasi,
                                'show_option_none' => '-- Otomatik Algıla --',
                                'option_none_value' => '0'
                            ]); 
                            ?>
                            <?php if ($secili_pos_sayfasi > 0): ?>
                                <a href="<?php echo esc_url(get_permalink($secili_pos_sayfasi)); ?>" target="_blank" class="hk-status-badge-active" style="text-decoration:none; margin-top:4px;">
                                    <span class="hk-dot"></span> Sayfayı Aç ↗
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Row: Sipariş Durumu -->
                    <div class="hk-settings-row">
                        <div class="hk-settings-label">
                            <label for="hizli_kasa_siparis_durumu">Varsayılan Sipariş Durumu</label>
                            <p class="hk-settings-desc">Terminal üzerinden ödemesi alınan yeni siparişlerin WooCommerce'deki varsayılan durumu.</p>
                        </div>
                        <div class="hk-settings-field">
                            <select name="hizli_kasa_siparis_durumu" id="hizli_kasa_siparis_durumu" class="hk-select">
                                <option value="processing" <?php selected($secili_durum, 'processing'); ?>>Hazırlanıyor (Önerilen)</option>
                                <option value="completed" <?php selected($secili_durum, 'completed'); ?>>Tamamlandı</option>
                                <option value="on-hold" <?php selected($secili_durum, 'on-hold'); ?>>Beklemede</option>
                            </select>
                        </div>
                    </div>

                    <!-- Row: Toplam Kasa Sayısı -->
                    <div class="hk-settings-row">
                        <div class="hk-settings-label">
                            <label for="hizli_kasa_toplam_kasa">Toplam Kasa Sayısı</label>
                            <p class="hk-settings-desc">Mağazanızda veya şubenizde aktif olarak kaç adet terminal (kasa) bulunduğunu belirtin.</p>
                        </div>
                        <div class="hk-settings-field">
                            <input type="number" name="hizli_kasa_toplam_kasa" id="hizli_kasa_toplam_kasa" value="<?php echo esc_attr($kasa_sayisi); ?>" min="1" max="20" step="1" class="hk-input hk-input-number">
                        </div>
                    </div>
                </div>

                <!-- 2. Yetkilendirme & Rol Yönetimi -->
                <div class="hk-settings-card" id="sec-roles">
                    <div class="hk-settings-card-header">
                        <div class="hk-settings-card-icon">
                            <span class="dashicons dashicons-admin-users"></span>
                        </div>
                        <div class="hk-settings-card-title">
                            <h3>Yetkilendirme & Rol Yönetimi</h3>
                            <p>POS terminaline giriş yapabilecek kullanıcı rollerini belirleyin.</p>
                        </div>
                    </div>

                    <div class="hk-settings-row" style="flex-direction:column; align-items:stretch;">
                        <div class="hk-settings-label" style="max-width:100%;">
                            <label>Erişim Yetkisi Olan Roller</label>
                            <p class="hk-settings-desc">
                                İşaretlenen rollere sahip kullanıcılar terminali kullanabilir. Kasiyerleriniz için eklentiyle gelen <strong>Hızlı Kasa Kasiyer</strong> rolünü kullanmanız önerilir.
                            </p>
                        </div>
                        <div class="hk-roles-grid">
                            <?php foreach ($tum_roller as $rol_slug => $rol_adi): 
                                $checked = in_array($rol_slug, (array) $secili_roller) ? 'checked' : '';
                                $is_kasiyer = ($rol_slug === 'hizli_kasa');
                            ?>
                                <label class="hk-role-card">
                                    <input type="checkbox" name="hizli_kasa_yetkili_roller[]" value="<?php echo esc_attr($rol_slug); ?>" <?php echo $checked; ?>>
                                    <span class="hk-role-name"><?php echo translate_user_role($rol_adi); ?></span>
                                    <?php if ($is_kasiyer): ?>
                                        <span class="hk-badge-recommended">Önerilen</span>
                                    <?php endif; ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- 3. Depo & Stok Entegrasyonu -->
                <div class="hk-settings-card" id="sec-depo">
                    <div class="hk-settings-card-header">
                        <div class="hk-settings-card-icon">
                            <span class="dashicons dashicons-database"></span>
                        </div>
                        <div class="hk-settings-card-title">
                            <h3>Depo & Stok Entegrasyonu</h3>
                            <p>Online siparişlerin stok düşüm öncelikleri ve kritik stok uyarıları.</p>
                        </div>
                    </div>

                    <!-- Row: Online Satış Deposu -->
                    <div class="hk-settings-row">
                        <div class="hk-settings-label">
                            <label for="hizli_kasa_varsayilan_online_depo">Online Satış Deposu (Öncelikli)</label>
                            <p class="hk-settings-desc">WooCommerce e-ticaret siparişlerinde stok öncelikli olarak bu depodan düşülür.</p>
                        </div>
                        <div class="hk-settings-field">
                            <select name="hizli_kasa_varsayilan_online_depo" id="hizli_kasa_varsayilan_online_depo" class="hk-select">
                                <option value="">-- Depo Seçilmedi --</option>
                                <?php foreach($depolar as $d): ?>
                                    <option value="<?php echo $d->id; ?>" <?php selected($online_depo, $d->id); ?>><?php echo esc_html($d->name); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Row: Kritik Stok Eşiği -->
                    <div class="hk-settings-row">
                        <div class="hk-settings-label">
                            <label for="hizli_kasa_kritik_stok_esigi">Kritik Stok Eşiği</label>
                            <p class="hk-settings-desc">Ürün veya depo stoğu bu rakama düştüğünde terminalde kırmızı stok ikazı gösterilir.</p>
                        </div>
                        <div class="hk-settings-field">
                            <input type="number" name="hizli_kasa_kritik_stok_esigi" id="hizli_kasa_kritik_stok_esigi" value="<?php echo esc_attr($kritik_esik); ?>" min="0" step="1" class="hk-input hk-input-number">
                        </div>
                    </div>
                </div>

                <!-- 4. Terminal Özellikleri & Kasiyer İzinleri -->
                <div class="hk-settings-card" id="sec-features">
                    <div class="hk-settings-card-header">
                        <div class="hk-settings-card-icon">
                            <span class="dashicons dashicons-tablet"></span>
                        </div>
                        <div class="hk-settings-card-title">
                            <h3>Terminal Özellikleri & Kasiyer İzinleri</h3>
                            <p>Terminal ekranında kasiyere sunulacak butonlar ve işlem yetkileri.</p>
                        </div>
                    </div>

                    <!-- Row: Sipariş Düzenleme Aktiflik -->
                    <div class="hk-settings-row">
                        <div class="hk-settings-label">
                            <label>Sipariş Düzenleme</label>
                            <p class="hk-settings-desc">Kasiyerlerin aynı gün içindeki geçmiş siparişleri terminal üzerinden düzenlemesine izin verin.</p>
                        </div>
                        <div class="hk-settings-field">
                            <label class="hk-switch-label">
                                <input type="hidden" name="hizli_kasa_siparis_duzenle_aktif" value="0">
                                <span class="hk-switch">
                                    <input type="checkbox" name="hizli_kasa_siparis_duzenle_aktif" value="1" <?php checked($edit_aktif, '1'); ?>>
                                    <span class="hk-slider"></span>
                                </span>
                            </label>
                        </div>
                    </div>

                    <!-- Row: Düzenlenebilir Sipariş Sayısı -->
                    <div class="hk-settings-row">
                        <div class="hk-settings-label">
                            <label for="hizli_kasa_edit_order_limit">Düzenlenebilir Sipariş Limiti</label>
                            <p class="hk-settings-desc">Kasiyerin kasa sayfasında listelenip düzenlenebileceği son sipariş sayısı.</p>
                        </div>
                        <div class="hk-settings-field">
                            <input type="number" name="hizli_kasa_edit_order_limit" id="hizli_kasa_edit_order_limit" value="<?php echo esc_attr($edit_limit); ?>" min="1" max="50" step="1" class="hk-input hk-input-number">
                        </div>
                    </div>

                    <!-- Row: Sipariş Düzenleme Kapsamı -->
                    <div class="hk-settings-row">
                        <div class="hk-settings-label">
                            <label for="hizli_kasa_siparis_duzenle_kapsam">Sipariş Düzenleme Kapsamı</label>
                            <p class="hk-settings-desc">"Sipariş Düzenle" panelinde sadece aktif kasanın mı yoksa tüm kasaların mı siparişleri görünsün?</p>
                        </div>
                        <div class="hk-settings-field">
                            <select name="hizli_kasa_siparis_duzenle_kapsam" id="hizli_kasa_siparis_duzenle_kapsam" class="hk-select">
                                <option value="secili" <?php selected($edit_kapsam, 'secili'); ?>>Sadece Seçili Kasa</option>
                                <option value="tum" <?php selected($edit_kapsam, 'tum'); ?>>Tüm Kasalar</option>
                            </select>
                        </div>
                    </div>

                    <!-- Row: Gün Sonu Raporu -->
                    <div class="hk-settings-row">
                        <div class="hk-settings-label">
                            <label>Kasa Gün Sonu Raporu</label>
                            <p class="hk-settings-desc">Terminal yan menüsünde "Kasa Gün Sonu" butonunu ve rapor ekranını göster.</p>
                        </div>
                        <div class="hk-settings-field">
                            <label class="hk-switch-label">
                                <input type="hidden" name="hizli_kasa_gun_sonu_aktif" value="0">
                                <span class="hk-switch">
                                    <input type="checkbox" name="hizli_kasa_gun_sonu_aktif" value="1" <?php checked($gs_aktif, '1'); ?>>
                                    <span class="hk-slider"></span>
                                </span>
                            </label>
                        </div>
                    </div>

                    <!-- Row: Genel Rapor -->
                    <div class="hk-settings-row">
                        <div class="hk-settings-label">
                            <label>Genel Rapor Görünürlüğü</label>
                            <p class="hk-settings-desc">Terminal yan menüsünde "Genel Rapor" butonunu göster.</p>
                        </div>
                        <div class="hk-settings-field">
                            <label class="hk-switch-label">
                                <input type="hidden" name="hizli_kasa_genel_rapor_aktif" value="0">
                                <span class="hk-switch">
                                    <input type="checkbox" name="hizli_kasa_genel_rapor_aktif" value="1" <?php checked($gr_aktif, '1'); ?>>
                                    <span class="hk-slider"></span>
                                </span>
                            </label>
                        </div>
                    </div>

                    <!-- Row: Anlık Kasa Kapsamı -->
                    <div class="hk-settings-row">
                        <div class="hk-settings-label">
                            <label for="hizli_kasa_anlik_kasa_kapsam">Anlık Kasa Gösterge Kapsamı</label>
                            <p class="hk-settings-desc">Terminalin üst kısmında yer alan "Net Kasa" widget'ının veri kaynağı.</p>
                        </div>
                        <div class="hk-settings-field">
                            <select name="hizli_kasa_anlik_kasa_kapsam" id="hizli_kasa_anlik_kasa_kapsam" class="hk-select">
                                <option value="secili" <?php selected($anlik_kapsam, 'secili'); ?>>Sadece Aktif Kasayı Göster</option>
                                <option value="tum" <?php selected($anlik_kapsam, 'tum'); ?>>Tüm Kasaların Toplamını Göster</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- 5. Ödeme, Yuvarlama & İskonto Kuralları -->
                <div class="hk-settings-card" id="sec-rules">
                    <div class="hk-settings-card-header">
                        <div class="hk-settings-card-icon">
                            <span class="dashicons dashicons-money-alt"></span>
                        </div>
                        <div class="hk-settings-card-title">
                            <h3>Ödeme, Yuvarlama & İskonto Kuralları</h3>
                            <p>Nakit yuvarlama modları, iskonto güvenlik limitleri ve SKU fallback tanımları.</p>
                        </div>
                    </div>

                    <!-- Row: Küsürat Yuvarlama Butonu -->
                    <div class="hk-settings-row">
                        <div class="hk-settings-label">
                            <label>Küsürat Yuvarlama Butonu</label>
                            <p class="hk-settings-desc">Sepet ödeme alanında "Küsürat Yuvarla" butonunu aktif et.</p>
                        </div>
                        <div class="hk-settings-field">
                            <label class="hk-switch-label">
                                <input type="hidden" name="hizli_kasa_yuvarlama_aktif" value="0">
                                <span class="hk-switch">
                                    <input type="checkbox" name="hizli_kasa_yuvarlama_aktif" value="1" <?php checked($yuvarlama_aktif, '1'); ?>>
                                    <span class="hk-slider"></span>
                                </span>
                            </label>
                        </div>
                    </div>

                    <!-- Row: Yuvarlama Modu -->
                    <div class="hk-settings-row">
                        <div class="hk-settings-label">
                            <label for="hizli_kasa_yuvarlama_modu">Küsürat Yuvarlama Adımı</label>
                            <p class="hk-settings-desc">Hızlı yuvarlama butonunun sepet tutarını hangi adıma yuvarlayacağını seçin.</p>
                        </div>
                        <div class="hk-settings-field">
                            <select name="hizli_kasa_yuvarlama_modu" id="hizli_kasa_yuvarlama_modu" class="hk-select">
                                <option value="0.5" <?php selected($yuvarlama_modu, '0.5'); ?>>0.50 TL'ye yuvarla</option>
                                <option value="1" <?php selected($yuvarlama_modu, '1'); ?>>1 TL'ye yuvarla</option>
                                <option value="5" <?php selected($yuvarlama_modu, '5'); ?>>5 TL'nin katına yuvarla</option>
                                <option value="10" <?php selected($yuvarlama_modu, '10'); ?>>10 TL'nin katına yuvarla</option>
                            </select>
                        </div>
                    </div>

                    <!-- Row: İskonto Telefon Eşiği -->
                    <div class="hk-settings-row">
                        <div class="hk-settings-label">
                            <label for="hizli_kasa_iskonto_telefon_esigi">İskonto Telefon Zorunluluğu Eşiği</label>
                            <p class="hk-settings-desc">Bu tutarın üzerindeki özel indirimlerde müşteri telefon numarası girilmesi zorunlu olur.</p>
                        </div>
                        <div class="hk-settings-field">
                            <div style="display:flex; align-items:center; gap:6px;">
                                <input type="number" name="hizli_kasa_iskonto_telefon_esigi" id="hizli_kasa_iskonto_telefon_esigi" value="<?php echo esc_attr($tel_esik); ?>" min="0" step="10" class="hk-input hk-input-number">
                                <span style="font-weight:600; color:var(--hk-text-muted);">TL</span>
                            </div>
                        </div>
                    </div>

                    <!-- Row: SKU Fallback -->
                    <div class="hk-settings-row">
                        <div class="hk-settings-label">
                            <label>SKU Eksikse Ürün ID Kullan</label>
                            <p class="hk-settings-desc">Barkod modülünde stok kodu (SKU) tanımlı olmayan ürünler için otomatik olarak Ürün ID'si kullanılır.</p>
                        </div>
                        <div class="hk-settings-field">
                            <label class="hk-switch-label">
                                <input type="hidden" name="hizli_kasa_fallback_sku_to_id" value="0">
                                <span class="hk-switch">
                                    <input type="checkbox" name="hizli_kasa_fallback_sku_to_id" value="1" <?php checked($fallback_sku, '1'); ?>>
                                    <span class="hk-slider"></span>
                                </span>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Bottom Save Action Bar -->
                <div style="display:flex; justify-content:flex-end; margin-top:20px;">
                    <button type="submit" class="hk-btn-save" style="padding:12px 32px; font-size:15px;">
                        <span class="dashicons dashicons-saved"></span> Tüm Ayarları Kaydet
                    </button>
                </div>

            </div>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Smooth scrolling & active state for side navigation items
    const navItems = document.querySelectorAll('.hk-settings-nav-item');
    const cards = document.querySelectorAll('.hk-settings-card');

    window.addEventListener('scroll', function() {
        let current = '';
        cards.forEach(card => {
            const cardTop = card.offsetTop - 100;
            if (window.pageYOffset >= cardTop) {
                current = card.getAttribute('id');
            }
        });

        navItems.forEach(item => {
            item.classList.remove('active');
            if (item.getAttribute('href') === '#' + current) {
                item.classList.add('active');
            }
        });
    });
});
</script>