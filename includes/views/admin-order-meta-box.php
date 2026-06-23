<?php if (!defined('ABSPATH')) exit; ?>
        <div class="hk-admin-order-tools" data-order-id="<?php echo esc_attr($order_id); ?>">
            <div class="hk-aot-toolbar">
                <div>
                    <strong>#<?php echo esc_html($order_id); ?></strong>
                    <span class="hk-aot-pill"><?php echo esc_html($order->get_formatted_order_total()); ?></span>
                    <span class="hk-aot-pill"><?php echo esc_html(wc_get_order_status_name($order->get_status())); ?></span>
                    <?php
                    $used_coupon_code = $order->get_meta('_hizli_kasa_used_coupon_code');
                    $used_coupon_amount = $order->get_meta('_hizli_kasa_used_coupon_amount');
                    if ($used_coupon_code) {
                        ?>
                        <span class="hk-aot-pill" style="background-color: #27ae60; color: white; font-weight: bold;">
                            ü¬éüÔ∏¬è Kullanƒ±lan ƒ∞ade √áeki: <?php echo esc_html($used_coupon_code); ?> (-<?php echo esc_html(wc_format_decimal($used_coupon_amount, 2)); ?> TL)
                        </span>
                        <?php
                    }
                    ?>
                </div>
                <label class="hk-aot-check">
                    <input type="checkbox" id="hk-aot-recalculate" checked>
                    Toplamlari yeniden hesapla
                </label>
            </div>

            <div class="hk-aot-tabs" role="tablist">
                <button type="button" class="is-active" data-hk-aot-tab="items">Sepet</button>
                <button type="button" data-hk-aot-tab="fees">Ucret / Kargo</button>
                <button type="button" data-hk-aot-tab="order-info">Siparis Bilgileri</button>
                <button type="button" data-hk-aot-tab="meta">Metalar</button>
            </div>

            <section class="hk-aot-panel is-active" data-hk-aot-panel="items">
                <div class="hk-aot-section-head">
                    <div>
                        <h4>Sepet kalemleri</h4>
                        <p>Adet, ara toplam, toplam ve kaleme bagli depo/iskonto metalarini duzenler.</p>
                    </div>
                </div>
                <table class="widefat striped hk-aot-table">
                    <thead>
                        <tr>
                            <th>Urun</th>
                            <th>Kalem metalari</th>
                            <th>Adet</th>
                            <th>Ara toplam</th>
                            <th>Toplam</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="hk-aot-items">
                        <?php foreach ($order->get_items('line_item') as $item_id => $item) : ?>
                            <tr data-item-id="<?php echo esc_attr($item_id); ?>">
                                <td>
                                    <strong><?php echo esc_html($item->get_name()); ?></strong>
                                    <small>ID: <?php echo esc_html($item_id); ?> | Urun: <?php echo esc_html($item->get_product_id()); ?><?php echo $item->get_variation_id() ? ' | Varyasyon: ' . esc_html($item->get_variation_id()) : ''; ?></small>
                                </td>
                                <td><textarea class="hk-aot-item-meta" rows="3"><?php echo esc_textarea(self::format_item_meta($item)); ?></textarea></td>
                                <td><input type="number" step="1" min="0" class="hk-aot-qty" value="<?php echo esc_attr($item->get_quantity()); ?>"></td>
                                <td><input type="number" step="0.01" class="hk-aot-subtotal" value="<?php echo esc_attr(wc_format_decimal($item->get_subtotal(), 2)); ?>"></td>
                                <td><input type="number" step="0.01" class="hk-aot-total" value="<?php echo esc_attr(wc_format_decimal($item->get_total(), 2)); ?>"></td>
                                <td><label><input type="checkbox" class="hk-aot-remove"> Sil</label></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <div class="hk-aot-add-row">
                    <input type="number" id="hk-aot-add-product-id" min="1" placeholder="Urun/Varyasyon ID">
                    <input type="number" id="hk-aot-add-product-qty" min="1" step="1" value="1" placeholder="Adet">
                    <input type="number" id="hk-aot-add-product-total" step="0.01" placeholder="Toplam (bos ise fiyat)">
                    <button type="button" class="button" id="hk-aot-add-product">Urun ekle</button>
                </div>
                <div id="hk-aot-new-products"></div>
            </section>

            <section class="hk-aot-panel" data-hk-aot-panel="fees">
                <div class="hk-aot-section-head">
                    <div>
                        <h4>Ucretler ve kargo</h4>
                        <p>Negatif tutar indirim, pozitif tutar ek ucret olarak hesaplanir.</p>
                    </div>
                </div>
                <div id="hk-aot-fees">
                    <?php foreach ($order->get_fees() as $fee_id => $fee) : ?>
                        <div class="hk-aot-grid-row" data-fee-id="<?php echo esc_attr($fee_id); ?>">
                            <input type="text" class="hk-aot-fee-name" value="<?php echo esc_attr($fee->get_name()); ?>" placeholder="Ad">
                            <input type="number" step="0.01" class="hk-aot-fee-total" value="<?php echo esc_attr(wc_format_decimal($fee->get_total(), 2)); ?>" placeholder="Toplam">
                            <label><input type="checkbox" class="hk-aot-fee-remove"> Sil</label>
                        </div>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="button" id="hk-aot-add-fee">Ucret ekle</button>

                <h4 class="hk-aot-subtitle">Kargo</h4>
                <div id="hk-aot-shipping">
                    <?php foreach ($order->get_shipping_methods() as $shipping_id => $shipping) : ?>
                        <div class="hk-aot-grid-row" data-shipping-id="<?php echo esc_attr($shipping_id); ?>">
                            <input type="text" class="hk-aot-shipping-title" value="<?php echo esc_attr($shipping->get_method_title()); ?>" placeholder="Baslik">
                            <input type="number" step="0.01" class="hk-aot-shipping-total" value="<?php echo esc_attr(wc_format_decimal($shipping->get_total(), 2)); ?>" placeholder="Toplam">
                            <label><input type="checkbox" class="hk-aot-shipping-remove"> Sil</label>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="hk-aot-panel" data-hk-aot-panel="order-info">
                <?php self::render_order_info_panel($order); ?>
            </section>

            <section class="hk-aot-panel" data-hk-aot-panel="meta">
                <div class="hk-aot-section-head">
                    <div>
                        <h4>Siparis metalari</h4>
                        <p>Hizli Kasa'nin kullandigi siparis metalarini listeden secip hizlica ekleyebilirsiniz.</p>
                    </div>
                    <div class="hk-aot-meta-picker">
                        <select id="hk-aot-meta-template">
                            <option value="">Meta sec</option>
                            <?php foreach ($meta_catalog['order'] as $meta_key => $meta_info) : ?>
                                <option value="<?php echo esc_attr($meta_key); ?>" data-default="<?php echo esc_attr($meta_info['default']); ?>">
                                    <?php echo esc_html($meta_info['label'] . ' - ' . $meta_key); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" class="button" id="hk-aot-add-selected-meta">Secileni ekle</button>
                    </div>
                </div>
                <details class="hk-aot-meta-reference">
                    <summary>Kalem/depo meta anahtarlarini goster</summary>
                    <div>
                        <?php foreach ($meta_catalog['item'] as $meta_key => $meta_info) : ?>
                            <code title="<?php echo esc_attr($meta_info['label']); ?>"><?php echo esc_html($meta_key); ?></code>
                        <?php endforeach; ?>
                    </div>
                </details>
                <div class="hk-aot-meta-list" id="hk-aot-meta-list">
                    <?php foreach ($order->get_meta_data() as $meta) : ?>
                        <?php
                        $data = $meta->get_data();
                        $value = self::format_meta_value($data['value']);
                        ?>
                        <div class="hk-aot-meta-row" data-meta-id="<?php echo esc_attr($data['id']); ?>">
                            <input type="text" class="hk-aot-meta-key" value="<?php echo esc_attr($data['key']); ?>">
                            <textarea class="hk-aot-meta-value" rows="2"><?php echo esc_textarea($value); ?></textarea>
                            <label><input type="checkbox" class="hk-aot-meta-remove"> Sil</label>
                        </div>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="button" id="hk-aot-add-meta">Bos meta ekle</button>
            </section>

            <div class="hk-aot-actions">
                <span class="hk-aot-currency">Para birimi: <?php echo esc_html($currency); ?></span>
                <span id="hk-aot-message" aria-live="polite"></span>
                <button type="button" class="button button-primary" id="hk-aot-save">Kaydet ve hesapla</button>
            </div>
        </div>
        <?php