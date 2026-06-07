<?php

if (!defined('ABSPATH')) {
    exit;
}

class Hizli_Kasa_Email_Modifier {
    public static function init() {
        // Admin'e giden yeni sipariş maili
        add_filter('woocommerce_email_subject_new_order', [__CLASS__, 'modify_subject'], 99, 3);
        // Müşteriye giden tamamlandı maili
        add_filter('woocommerce_email_subject_customer_completed_order', [__CLASS__, 'modify_subject'], 99, 3);
        // Müşteriye giden işleniyor maili
        add_filter('woocommerce_email_subject_customer_processing_order', [__CLASS__, 'modify_subject'], 99, 3);
    }

    public static function modify_subject($subject, $order, $email) {
        if (!$order || !is_a($order, 'WC_Order')) {
            return $subject;
        }

        $kasiyer = $order->get_meta('_hizli_kasa_kasiyer');
        $depo_adi = $order->get_meta('_hk_cikis_depo_adi');
        
        if (!empty($kasiyer)) {
            // Bu bir hızlı kasa siparişi
            $order_number = $order->get_order_number();
            
            // Daha sade ve ayırt edici bir konu satırı oluşturuyoruz
            $new_subject = sprintf('[Hızlı Kasa] Sipariş #%s', $order_number);
            
            if (!empty($depo_adi)) {
                $new_subject .= ' - ' . $depo_adi;
            }

            return $new_subject;
        }

        return $subject;
    }
}
