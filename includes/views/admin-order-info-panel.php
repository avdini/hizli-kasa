<?php if (!defined('ABSPATH')) exit; ?>
        <div class="hk-aot-cards">

            <!-- Genel Bilgiler -->
            <div class="hk-aot-card">
                <div class="hk-aot-card-header">
                    <span class="dashicons dashicons-info-outline"></span>
                    <h4>Genel Bilgiler</h4>
                </div>
                <div class="hk-aot-card-body">
                    <div class="hk-aot-form-grid">
                        <div class="hk-aot-form-group">
                            <label for="hk-oi-kasiyer">Kasiyer</label>
                            <input type="text" id="hk-oi-kasiyer" value="<?php echo esc_attr($order->get_meta('_hizli_kasa_kasiyer')); ?>" placeholder="Kasiyer adi">
                        </div>
                        <div class="hk-aot-form-group">
                            <label for="hk-oi-kasa-no">Kasa No</label>
                            <input type="text" id="hk-oi-kasa-no" value="<?php echo esc_attr($order->get_meta('_hizli_kasa_kasa_no')); ?>" placeholder="Kasa numarasi">
                        </div>
                        <div class="hk-aot-form-group">
                            <label for="hk-oi-kaynak">Kaynak</label>
                            <select id="hk-oi-kaynak">
                                <option value="">Seciniz</option>
                                <?php foreach ($kaynaklar as $val => $label) : ?>
                                    <option value="<?php echo esc_attr($val); ?>" <?php selected($current_kaynak, $val); ?>><?php echo esc_html($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="hk-aot-form-group">
                            <label for="hk-oi-rapor-kaynak">Rapor Etiketi</label>
                            <input type="text" id="hk-oi-rapor-kaynak" value="<?php echo esc_attr($order->get_meta('_hk_kaynak')); ?>" placeholder="Rapor kaynagi">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Musteri Bilgileri -->
            <div class="hk-aot-card">
                <div class="hk-aot-card-header">
                    <span class="dashicons dashicons-admin-users"></span>
                    <h4>Musteri Bilgileri</h4>
                </div>
                <div class="hk-aot-card-body">
                    <div class="hk-aot-form-grid">
                        <div class="hk-aot-form-group">
                            <label for="hk-oi-telefon">Musteri Telefonu</label>
                            <input type="tel" id="hk-oi-telefon" value="<?php echo esc_attr($order->get_meta('_hizli_kasa_musteri_telefon')); ?>" placeholder="05XX XXX XX XX">
                        </div>
                        <div class="hk-aot-form-group">
                            <label>Fatura Telefonu</label>
                            <input type="tel" value="<?php echo esc_attr($order->get_billing_phone()); ?>" readonly title="WooCommerce fatura telefonu (salt okunur)">
                        </div>
                        <div class="hk-aot-form-group full-width">
                            <label for="hk-oi-note">Siparis Notu</label>
                            <textarea id="hk-oi-note" rows="2" placeholder="Siparise ozel not ekleyin..."><?php echo esc_textarea($order->get_customer_note()); ?></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Odeme Yontemi -->
            <div class="hk-aot-card">
                <div class="hk-aot-card-header">
                    <span class="dashicons dashicons-money-alt"></span>
                    <h4>Odeme Yontemi</h4>
                </div>
                <div class="hk-aot-card-body">
                    <div class="hk-aot-form-grid">
                        <div class="hk-aot-form-group">
                            <label for="hk-oi-payment-method">Odeme Yontemi</label>
                            <select id="hk-oi-payment-method">
                                <?php foreach ($payment_methods as $val => $label) : ?>
                                    <option value="<?php echo esc_attr($val); ?>" <?php selected($payment_method, $val); ?>><?php echo esc_html($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <span class="hk-aot-hint" id="hk-oi-payment-hint">
                                <?php if (!$is_split) : ?>
                                    Yontem degistirildiginde tutarlar otomatik guncellenir.
                                <?php else : ?>
                                    Bolunmus odeme â€” tutarlari asagidan duzenleyebilirsiniz.
                                <?php endif; ?>
                            </span>
                        </div>
                        <div class="hk-aot-form-group">
                            <label>Siparis Toplami</label>
                            <div class="hk-aot-order-total-display" id="hk-oi-order-total-display">
                                <?php echo wp_kses_post($order->get_formatted_order_total()); ?>
                            </div>
                            <input type="hidden" id="hk-oi-order-total" value="<?php echo esc_attr(wc_format_decimal($order_total, 2)); ?>">
                        </div>
                    </div>

                    <div class="hk-aot-divider"></div>

                    <div class="hk-aot-payment-amounts">
                        <div class="hk-aot-form-group">
                            <label for="hk-oi-nakit">Nakit (TL)</label>
                            <input type="number" step="0.01" min="0" id="hk-oi-nakit" value="<?php echo esc_attr(wc_format_decimal((float) $order->get_meta('_odeme_nakit'), 2)); ?>" <?php echo $is_split ? '' : 'readonly'; ?>>
                        </div>
                        <div class="hk-aot-form-group">
                            <label for="hk-oi-kart">Kart (TL)</label>
                            <input type="number" step="0.01" min="0" id="hk-oi-kart" value="<?php echo esc_attr(wc_format_decimal((float) $order->get_meta('_odeme_kart'), 2)); ?>" <?php echo $is_split ? '' : 'readonly'; ?>>
                        </div>
                        <div class="hk-aot-form-group">
                            <label for="hk-oi-iban">IBAN (TL)</label>
                            <input type="number" step="0.01" min="0" id="hk-oi-iban" value="<?php echo esc_attr(wc_format_decimal((float) $order->get_meta('_odeme_iban'), 2)); ?>" <?php echo $is_split ? '' : 'readonly'; ?>>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Depo Bilgileri -->
            <div class="hk-aot-card">
                <div class="hk-aot-card-header">
                    <span class="dashicons dashicons-building"></span>
                    <h4>Depo Bilgileri</h4>
                </div>
                <div class="hk-aot-card-body">
                    <div class="hk-aot-form-grid">
                        <div class="hk-aot-form-group">
                            <label for="hk-oi-depo">Cikis Deposu</label>
                            <select id="hk-oi-depo">
                                <option value="">Depo seciniz</option>
                                <?php foreach ($depolar as $depo) : ?>
                                    <option value="<?php echo esc_attr($depo['id']); ?>" data-name="<?php echo esc_attr($depo['name']); ?>" <?php selected($current_depo_id, $depo['id']); ?>>
                                        <?php echo esc_html($depo['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="hk-aot-form-group">
                            <label>Depo Adi</label>
                            <input type="text" value="<?php echo esc_attr($order->get_meta('_hk_cikis_depo_adi')); ?>" readonly id="hk-oi-depo-adi">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Iade Bilgileri -->
            <div class="hk-aot-card">
                <div class="hk-aot-card-header">
                    <span class="dashicons dashicons-undo"></span>
                    <h4>Iade & Iskonto Bilgileri</h4>
                </div>
                <div class="hk-aot-card-body">
                    <div class="hk-aot-refund-grid">
                        <div class="hk-aot-refund-item">
                            <span class="label">Iade Kaydi</span>
                            <span class="hk-aot-badge <?php echo $is_refund ? 'is-yes' : 'is-no'; ?>">
                                <?php echo $is_refund ? 'Evet' : 'Hayir'; ?>
                            </span>
                        </div>
                        <div class="hk-aot-refund-item">
                            <span class="label">Tam Iade</span>
                            <span class="hk-aot-badge <?php echo $is_fully_refunded ? 'is-warning' : 'is-no'; ?>">
                                <?php echo $is_fully_refunded ? 'Evet' : 'Hayir'; ?>
                            </span>
                        </div>
                        <div class="hk-aot-refund-item">
                            <span class="label">Iade Var</span>
                            <span class="hk-aot-badge <?php echo $has_refund ? 'is-yes' : 'is-no'; ?>">
                                <?php echo $has_refund ? 'Evet' : 'Hayir'; ?>
                            </span>
                        </div>
                        <div class="hk-aot-refund-item">
                            <span class="label">Manuel Iade</span>
                            <span class="hk-aot-badge <?php echo $order->get_meta('_hizli_kasa_manual_refund') === 'yes' ? 'is-yes' : 'is-no'; ?>">
                                <?php echo $order->get_meta('_hizli_kasa_manual_refund') === 'yes' ? 'Evet' : 'Hayir'; ?>
                            </span>
                        </div>
                    </div>

                    <div class="hk-aot-divider"></div>

                    <div class="hk-aot-form-grid">
                        <div class="hk-aot-form-group">
                            <label for="hk-oi-orijinal-siparis">Orijinal Siparis ID</label>
                            <input type="number" id="hk-oi-orijinal-siparis" min="0" value="<?php echo esc_attr($order->get_meta('_hizli_kasa_original_order')); ?>" placeholder="Iade siparisinin orjinali">
                        </div>
                        <div class="hk-aot-form-group">
                            <label for="hk-oi-toplam-iskonto">Toplam Iskonto</label>
                            <input type="number" step="0.01" id="hk-oi-toplam-iskonto" value="<?php echo esc_attr(wc_format_decimal((float) $order->get_meta('_hk_toplam_iskonto'), 2)); ?>">
                        </div>
                        <div class="hk-aot-form-group">
                            <label>Musteri Odedi</label>
                            <input type="number" step="0.01" value="<?php echo esc_attr(wc_format_decimal((float) $order->get_meta('_hk_customer_paid_total'), 2)); ?>" readonly>
                        </div>
                        <div class="hk-aot-form-group">
                            <label>Iade Edilen Iskonto</label>
                            <input type="number" step="0.01" value="<?php echo esc_attr(wc_format_decimal((float) $order->get_meta('_hk_refunded_discount'), 2)); ?>" readonly>
                        </div>
                    </div>
                </div>
            </div>

        </div>
        <?php