/**
 * Hızlı Kasa - Masraf Yönetimi Modülü
 *
 * Masraf ekleme, listeleme ve silme işlemlerini yönetir.
 */

(function(HK) {
    'use strict';

    HK.ExpenseManager = {

        /**
         * Modülü başlat
         */
        init: function() {
            this.bindEvents();
            this.loadExpenses();
        },

        /**
         * Olay dinleyicilerini bağla
         */
        bindEvents: function() {
            const self = this;
            const form = document.getElementById('yeni-masraf-form');
            const katSelect = document.getElementById('masraf-kategori');
            const ozelKatAlan = document.getElementById('ozel-kategori-alan');

            if (!form) return;

            // Kategori değişince "Diğer" kontrolü
            katSelect.addEventListener('change', function() {
                if (this.value === 'Diger') {
                    ozelKatAlan.style.display = 'block';
                } else {
                    ozelKatAlan.style.display = 'none';
                }
            });

            // Form gönderimi
            form.addEventListener('submit', async function(e) {
                e.preventDefault();
                await self.saveExpense();
            });

            // Ödeme yöntemi seçici görsel efekti
            document.querySelectorAll('input[name="payment_method"]').forEach(input => {
                input.addEventListener('change', function() {
                    // CSS :has selectorü ile yönetiliyor ancak eski tarayıcı desteği gerekirse burası kullanılabilir
                });
            });
        },

        /**
         * Masrafları API'den yükle ve listele
         */
        loadExpenses: async function() {
            const listBody = document.getElementById('masraf-listesi-body');
            const toplamLabel = document.getElementById('gunluk-toplam-masraf');
            
            if (!listBody) return;

            try {
                const depo_id = HK.DepoManager ? HK.DepoManager.getActiveDepoId() : 0;
                const apiUrl = kasaAyar.rootApiUrl + 'hizli-kasa/v1/masraflar?depo_id=' + depo_id;
                
                const response = await fetch(apiUrl, {
                    headers: { 'X-WP-Nonce': kasaAyar.nonce }
                });

                if (!response.ok) throw new Error('Yükleme hatası');

                const masraflar = await response.json();
                
                if (masraflar.length === 0) {
                    listBody.innerHTML = '<tr><td colspan="5" style="padding: 40px; text-align: center; color: #999;">Henüz masraf girilmedi.</td></tr>';
                    toplamLabel.innerText = '0.00 TL';
                    return;
                }

                let html = '';
                let toplam = 0;

                masraflar.forEach(m => {
                    const rowTotal = parseFloat(m.amount);
                    toplam += rowTotal;

                    let methodIcon = '💵';
                    if (m.payment_method === 'kart') methodIcon = '💳';
                    if (m.payment_method === 'iban') methodIcon = '🏦';

                    html += `
                        <tr style="border-bottom: 1px solid #f0f0f0;">
                            <td style="padding: 12px; font-weight: bold;">${m.category}</td>
                            <td style="padding: 12px; font-size: 13px; color: #666;">${m.description || '-'}</td>
                            <td style="padding: 12px;">${methodIcon} ${m.payment_method.toUpperCase()}</td>
                            <td style="padding: 12px; font-weight: bold; color: #2c3e50;">${rowTotal.toFixed(2)} TL</td>
                            <td style="padding: 12px; text-align: center;">
                                <button class="masraf-sil-btn" data-id="${m.id}">Sil</button>
                            </td>
                        </tr>
                    `;
                });

                listBody.innerHTML = html;
                toplamLabel.innerText = toplam.toFixed(2) + ' TL';

                // Silme butonlarını bağla
                this.bindDeleteButtons();

            } catch (error) {
                console.error('Masraflar yüklenemedi:', error);
            }
        },

        /**
         * Yeni masraf kaydet
         */
        saveExpense: async function() {
            const btn = document.getElementById('masraf-kaydet-btn');
            const katSelect = document.getElementById('masraf-kategori');
            const ozelKatInput = document.getElementById('masraf-kategori-ozel');
            const tutarInput = document.getElementById('masraf-tutar');
            const aciklamaInput = document.getElementById('masraf-aciklama');
            const methodInput = document.querySelector('input[name="payment_method"]:checked');

            let category = katSelect.value;
            if (category === 'Diger') {
                category = ozelKatInput.value || 'Diğer';
            }

            const amount = parseFloat(tutarInput.value);
            if (!category || isNaN(amount) || amount <= 0) {
                alert('Lütfen kategori ve geçerli bir tutar girin.');
                return;
            }

            btn.disabled = true;
            btn.innerText = 'Kaydediliyor...';

            try {
                const depo_id = HK.DepoManager ? HK.DepoManager.getActiveDepoId() : 0;
                // Kasa no'yu current tab'dan alabiliriz
                const activeKasaBtn = document.querySelector('#kasa-sidebar .sidebar-btn.aktif');
                const kasa_no = activeKasaBtn ? activeKasaBtn.getAttribute('data-id') : '1';

                const response = await fetch(kasaAyar.rootApiUrl + 'hizli-kasa/v1/masraflar', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': kasaAyar.nonce
                    },
                    body: JSON.stringify({
                        category: category,
                        amount: amount,
                        payment_method: methodInput.value,
                        description: aciklamaInput.value,
                        depo_id: depo_id,
                        kasa_no: kasa_no
                    })
                });

                if (!response.ok) throw new Error('Kaydedilemedi');

                // Formu temizle
                tutarInput.value = '';
                aciklamaInput.value = '';
                ozelKatInput.value = '';
                katSelect.value = 'Mutfak (Çay, Kahve vs.)';
                document.getElementById('ozel-kategori-alan').style.display = 'none';

                // Listeyi yenile
                await this.loadExpenses();

            } catch (error) {
                alert('Hata: ' + error.message);
            } finally {
                btn.disabled = false;
                btn.innerText = 'Kaydet ve Listeye Ekle';
            }
        },

        /**
         * Masraf silme işlemi
         */
        bindDeleteButtons: function() {
            const self = this;
            document.querySelectorAll('.masraf-sil-btn').forEach(btn => {
                btn.onclick = async function() {
                    if (!confirm('Bu masraf kaydını silmek istediğinize emin misiniz?')) return;
                    
                    const id = this.getAttribute('data-id');
                    try {
                        const response = await fetch(kasaAyar.rootApiUrl + 'hizli-kasa/v1/masraflar/' + id, {
                            method: 'DELETE',
                            headers: { 'X-WP-Nonce': kasaAyar.nonce }
                        });

                        if (response.ok) {
                            await self.loadExpenses();
                        }
                    } catch (error) {
                        alert('Silme hatası');
                    }
                };
            });
        }
    };

// Sekme yüklendiğinde başlat
document.addEventListener('hkTabLoaded', function(e) {
    if (e.detail.tab === 'masraf') {
        HK.ExpenseManager.init();
    }
});
