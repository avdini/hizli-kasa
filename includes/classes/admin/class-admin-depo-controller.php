<?php
if (!defined('ABSPATH')) exit;

class Hizli_Kasa_Admin_Depo_Controller {
    public static function init() {
        add_action('admin_init', [__CLASS__, 'handle_actions']);
    }

    public static function handle_actions() {
    if (!isset($_GET['page']) || $_GET['page'] !== 'hizli-kasa') return;
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'hizli_kasa_depolar';

    // MesajlarÄ± YÃ¶net (YÃ¶nlendirme sonrasÄ± gÃ¶sterim iÃ§in)
    if (isset($_GET['hizli_kasa_msg'])) {
        switch ($_GET['hizli_kasa_msg']) {
            case 'depo_eklendi':
                add_settings_error('hizli_kasa_messages', 'depo_eklendi', 'Yeni depo baÅŸarÄ±yla eklendi.', 'updated');
                break;
            case 'depo_hata':
                $err = isset($_GET['hizli_kasa_err']) ? sanitize_text_field($_GET['hizli_kasa_err']) : 'Depo eklenirken bir hata oluÅŸtu.';
                add_settings_error('hizli_kasa_messages', 'depo_hata', $err, 'error');
                break;
            case 'depo_silindi':
                add_settings_error('hizli_kasa_messages', 'depo_silindi', 'Depo baÅŸarÄ±yla silindi.', 'updated');
                break;
            case 'depo_silme_hata':
                add_settings_error('hizli_kasa_messages', 'depo_silme_hata', 'Depo silinirken bir hata oluÅŸtu.', 'error');
                break;
            case 'db_onarildi':
                add_settings_error('hizli_kasa_messages', 'db_onarildi', 'VeritabanÄ± tablolarÄ± kontrol edildi ve onarÄ±ldÄ±.', 'updated');
                break;
            case 'depo_guncellendi':
                add_settings_error('hizli_kasa_messages', 'depo_guncellendi', 'Depo bilgileri baÅŸarÄ±yla gÃ¼ncellendi.', 'updated');
                break;
        }
    }

    // Yeni Depo Ekleme
    if (isset($_POST['hizli_kasa_depo_ekle'])) {
        check_admin_referer('depo_ekle_action', 'depo_ekle_nonce');
        
        $name = sanitize_text_field($_POST['depo_name']);
        
        // MÃ¼kerrer KayÄ±t KontrolÃ¼
        $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table_name WHERE name = %s", $name));
        if ($exists) {
            wp_safe_redirect(admin_url('admin.php?page=hizli-kasa&tab=depolar&hizli_kasa_msg=depo_hata&hizli_kasa_err=' . urlencode('Bu isimde bir depo zaten mevcut.')));
            exit;
        }

        $inserted = $wpdb->insert($table_name, [
            'name'        => $name,
            'address'     => sanitize_textarea_field($_POST['depo_address']),
            'description' => sanitize_textarea_field($_POST['depo_desc']),
            'priority'    => intval($_POST['depo_priority']),
            'created_at'  => current_time('mysql')
        ]);

        if ($inserted === false) {
            $error_msg = $wpdb->last_error ?: "VeritabanÄ± hatasÄ±.";
            wp_safe_redirect(admin_url('admin.php?page=hizli-kasa&tab=depolar&hizli_kasa_msg=depo_hata&hizli_kasa_err=' . urlencode($error_msg)));
            exit;
        }

        delete_transient('hk_depo_list_all');

        wp_redirect(admin_url('admin.php?page=hizli-kasa&tab=depolar&hizli_kasa_msg=depo_eklendi'));
        exit;
    }

    // Depo GÃ¼ncelleme
    if (isset($_POST['hizli_kasa_depo_guncelle'])) {
        check_admin_referer('depo_guncelle_action', 'depo_guncelle_nonce');
        
        $id = intval($_POST['depo_id']);
        $name = sanitize_text_field($_POST['depo_name']);
        
        // Ä°sim Ã§akÄ±ÅŸma kontrolÃ¼ (Kendi ID'si hariÃ§)
        $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table_name WHERE name = %s AND id != %d", $name, $id));
        if ($exists) {
            wp_safe_redirect(admin_url('admin.php?page=hizli-kasa&tab=depolar&hizli_kasa_msg=depo_hata&hizli_kasa_err=' . urlencode('Bu isimde baÅŸka bir depo zaten mevcut.')));
            exit;
        }

        $updated = $wpdb->update($table_name, [
            'name'        => $name,
            'address'     => sanitize_textarea_field($_POST['depo_address']),
            'description' => sanitize_textarea_field($_POST['depo_desc']),
            'priority'    => intval($_POST['depo_priority'])
        ], ['id' => $id]);

        delete_transient('hk_depo_list_all');

        wp_redirect(admin_url('admin.php?page=hizli-kasa&tab=depolar&hizli_kasa_msg=depo_guncellendi'));
        exit;
    }

    // Depo Silme
    if (isset($_GET['delete_depo'])) {
        $depo_id = intval($_GET['delete_depo']);
        check_admin_referer('delete_depo_' . $depo_id);

        $depo_name = $wpdb->get_var($wpdb->prepare("SELECT name FROM $table_name WHERE id = %d", $depo_id));
        $deleted = $wpdb->delete($table_name, ['id' => $depo_id]);

        if ($deleted) {
            // SÄ±kÄ± Ä°zolasyon: Deponun tÃ¼m stoklarÄ±nÄ±, loglarÄ±nÄ± ve uyuÅŸmazlÄ±klarÄ±nÄ± sil
            $wpdb->delete($wpdb->prefix . 'hizli_kasa_stok_konumlari',  ['location_id' => $depo_id]);
            $wpdb->delete($wpdb->prefix . 'hizli_kasa_stok_hareketleri', ['location_id' => $depo_id]);
            if ($depo_name) {
                $wpdb->delete($wpdb->prefix . 'hizli_kasa_unmatched_items', ['warehouse_name' => $depo_name]);
            }
            delete_transient('hk_depo_list_all');
        }

        $msg = $deleted ? 'depo_silindi' : 'depo_silme_hata';
        wp_redirect(admin_url('admin.php?page=hizli-kasa&tab=depolar&hizli_kasa_msg=' . $msg));
        exit;
    }

}
}
