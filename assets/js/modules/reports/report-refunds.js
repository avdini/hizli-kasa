/**
 * Hızlı Kasa - Raporlar İade Modülü
 */
(function(HK) {
    'use strict';

    HK.ReportRefunds = {
        currentPageRefunds: 1,
        perPage: 50,
        pendingCancelOrderId: null,

        init: function() {
            var self = this;
            console.log("HK.ReportRefunds: Init started");

            if (HK.ReportHub) {
                HK.ReportHub.registerReport({
                    id: 'iade-listesi',
                    categoryId: 'iade',
                    title: 'İadeler',
                    icon: '🔙',
                    panelId: 'rapor-iade-listesi',
                    onActivate: function() { self.loadRefunds(1); },
                    hasDateFilter: true,
                    hasSearch: true,
                    searchPlaceholder: 'İade ID veya Kasiyer Ara...',
                    order: 1
                });
            }

            document.addEventListener('hkActiveDepoChanged', function() {
                if (HK.ReportHub && HK.ReportHub.activeCategory === 'iade' && HK.ReportHub.activeReport === 'iade-listesi') {
                    self.loadRefunds(1);
                }
            });

            this.bindCancelModal();
        },

        bindCancelModal: function() {
            var self = this;
            var modal = document.getElementById("iade-iptal-modal");
            var btnVazgec = document.getElementById("iade-iptal-vazgec");
            var btnOnayla = document.getElementById("iade-iptal-onayla");

            if (btnVazgec && modal) {
                btnVazgec.addEventListener("click", function() {
                    modal.style.display = "none";
                    self.pendingCancelOrderId = null;
                });
            }

            if (btnOnayla) {
                btnOnayla.addEventListener("click", function() {
                    self.submitCancel();
                });
            }
        },

        openCancelModal: function(orderId) {
            this.pendingCancelOrderId = orderId;
            var modal = document.getElementById("iade-iptal-modal");
            var title = document.getElementById("iade-iptal-order-title");
            var input = document.getElementById("iade-iptal-neden-input");
            var errorEl = document.getElementById("iade-iptal-hata-mesaji");
            var btnOnayla = document.getElementById("iade-iptal-onayla");

            if (!modal) return;

            if (title) title.textContent = 'İade #' + orderId;
            if (input) input.value = '';
            if (errorEl) {
                errorEl.style.display = 'none';
                errorEl.textContent = '';
            }
            if (btnOnayla) {
                btnOnayla.disabled = false;
                btnOnayla.textContent = 'İadeyi İptal Et';
            }

            modal.style.display = "flex";
            if (input) setTimeout(function() { input.focus(); }, 100);
        },

        submitCancel: async function() {
            var self = this;
            var input = document.getElementById("iade-iptal-neden-input");
            var errorEl = document.getElementById("iade-iptal-hata-mesaji");
            var btnOnayla = document.getElementById("iade-iptal-onayla");
            var modal = document.getElementById("iade-iptal-modal");

            var reason = input ? input.value.trim() : '';

            if (!reason || reason.length < 3) {
                if (errorEl) {
                    errorEl.textContent = '⚠️ Lütfen iptal nedenini giriniz (en az 3 karakter).';
                    errorEl.style.display = 'block';
                }
                if (input) input.focus();
                return;
            }

            if (errorEl) errorEl.style.display = 'none';
            if (btnOnayla) {
                btnOnayla.disabled = true;
                btnOnayla.textContent = 'İptal Ediliyor...';
            }

            try {
                var url = `${kasaAyar.rootApiUrl}hizli-kasa/v2/refund/cancel`;
                var response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': kasaAyar.nonce
                    },
                    body: JSON.stringify({
                        refund_order_id: self.pendingCancelOrderId,
                        cancel_reason: reason
                    })
                });

                var res = await response.json();

                if (!response.ok || !res.success) {
                    var errorMsg = (res.errors && res.errors[0]) || res.message || 'İade iptal edilirken bir hata oluştu.';
                    if (errorEl) {
                        errorEl.textContent = '⚠️ ' + errorMsg;
                        errorEl.style.display = 'block';
                    }
                    if (btnOnayla) {
                        btnOnayla.disabled = false;
                        btnOnayla.textContent = 'İadeyi İptal Et';
                    }
                    return;
                }

                if (modal) modal.style.display = "none";
                self.pendingCancelOrderId = null;

                if (window.swal) {
                    swal('Başarılı', res.data && res.data.message ? res.data.message : 'İade başarıyla iptal edildi.', 'success');
                } else {
                    alert(res.data && res.data.message ? res.data.message : 'İade başarıyla iptal edildi.');
                }

                self.loadRefunds(self.currentPageRefunds);

            } catch (e) {
                console.error("HK.ReportRefunds: Submit cancel error", e);
                if (errorEl) {
                    errorEl.textContent = '⚠️ Sunucu bağlantı hatası oluştu.';
                    errorEl.style.display = 'block';
                }
                if (btnOnayla) {
                    btnOnayla.disabled = false;
                    btnOnayla.textContent = 'İadeyi İptal Et';
                }
            }
        },

        loadRefunds: async function(page) {
            this.currentPageRefunds = page || 1;
            var tbody = document.getElementById("refund-list-body");
            var pagin = document.getElementById("refund-list-pagination");
            if (!tbody) return;

            tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; padding:40px;">Yükleniyor...</td></tr>';
            
            var dateStart = document.getElementById("rhub-tarih-bas").value;
            var dateEnd = document.getElementById("rhub-tarih-bit").value;
            var search = document.getElementById("rhub-arama-input").value;
            var depoId = HK.DepoManager ? HK.DepoManager.getActiveDepo() : 0;

            try {
                var url = `${kasaAyar.rootApiUrl}hizli-kasa/v1/reports/refunds?page=${this.currentPageRefunds}&per_page=${this.perPage}&date_start=${dateStart}&date_end=${dateEnd}&search=${encodeURIComponent(search)}&depo_id=${depoId}`;
                var response = await fetch(url, { headers: { 'X-WP-Nonce': kasaAyar.nonce } });
                var res = await response.json();

                if (!response.ok) {
                    tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; padding:40px; color:orange;">⚠️ ' + (res.message || 'İadeler yüklenemedi.') + '</td></tr>';
                    return;
                }

                if (HK.ReportsCommon) {
                    HK.ReportsCommon.renderTable(tbody, res.orders, 'refunds');
                    HK.ReportsCommon.renderPagination(pagin, res.max_pages, this.currentPageRefunds, (p) => this.loadRefunds(p));
                }

            } catch (e) {
                console.error("HK.ReportRefunds: Load refunds error", e);
                tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; padding:40px; color:red;">Hata: Veriler çekilemedi.</td></tr>';
            }
        }
    };

    HK.ReportRefunds.init();
})(window.HizliKasa);
