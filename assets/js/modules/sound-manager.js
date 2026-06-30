(function(HK) {
    'use strict';

    HK.Sound = {
        presets: {
            classic: {
                success: function(ctx, dest) {
                    var osc = ctx.createOscillator();
                    var gain = ctx.createGain();
                    osc.connect(gain);
                    gain.connect(dest);
                    osc.type = 'sine';
                    osc.frequency.setValueAtTime(800, ctx.currentTime);
                    gain.gain.setValueAtTime(0.6, ctx.currentTime);
                    gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.1);
                    osc.start(ctx.currentTime);
                    osc.stop(ctx.currentTime + 0.1);
                },
                error: function(ctx, dest) {
                    var osc = ctx.createOscillator();
                    var gain = ctx.createGain();
                    osc.connect(gain);
                    gain.connect(dest);
                    osc.type = 'sawtooth';
                    osc.frequency.setValueAtTime(220, ctx.currentTime);
                    gain.gain.setValueAtTime(0.7, ctx.currentTime);
                    gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.3);
                    osc.start(ctx.currentTime);
                    osc.stop(ctx.currentTime + 0.3);
                }
            },
            soft: {
                success: function(ctx, dest) {
                    var osc1 = ctx.createOscillator();
                    var gain1 = ctx.createGain();
                    osc1.connect(gain1);
                    gain1.connect(dest);
                    osc1.type = 'sine';
                    osc1.frequency.setValueAtTime(880, ctx.currentTime);
                    gain1.gain.setValueAtTime(0.5, ctx.currentTime);
                    gain1.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.08);
                    osc1.start(ctx.currentTime);
                    osc1.stop(ctx.currentTime + 0.08);

                    var osc2 = ctx.createOscillator();
                    var gain2 = ctx.createGain();
                    osc2.connect(gain2);
                    gain2.connect(dest);
                    osc2.type = 'sine';
                    osc2.frequency.setValueAtTime(1100, ctx.currentTime + 0.08);
                    gain2.gain.setValueAtTime(0.5, ctx.currentTime + 0.08);
                    gain2.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.16);
                    osc2.start(ctx.currentTime + 0.08);
                    osc2.stop(ctx.currentTime + 0.16);
                },
                error: function(ctx, dest) {
                    var osc = ctx.createOscillator();
                    var gain = ctx.createGain();
                    osc.connect(gain);
                    gain.connect(dest);
                    osc.type = 'triangle';
                    osc.frequency.setValueAtTime(180, ctx.currentTime);
                    gain.gain.setValueAtTime(0.7, ctx.currentTime);
                    gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.25);
                    osc.start(ctx.currentTime);
                    osc.stop(ctx.currentTime + 0.25);
                }
            },
            retro: {
                success: function(ctx, dest) {
                    var osc = ctx.createOscillator();
                    var gain = ctx.createGain();
                    osc.connect(gain);
                    gain.connect(dest);
                    osc.type = 'square';
                    osc.frequency.setValueAtTime(600, ctx.currentTime);
                    osc.frequency.exponentialRampToValueAtTime(1200, ctx.currentTime + 0.15);
                    gain.gain.setValueAtTime(0.3, ctx.currentTime);
                    gain.gain.exponentialRampToValueAtTime(0.004, ctx.currentTime + 0.15);
                    osc.start(ctx.currentTime);
                    osc.stop(ctx.currentTime + 0.15);
                },
                error: function(ctx, dest) {
                    var osc = ctx.createOscillator();
                    var gain = ctx.createGain();
                    osc.connect(gain);
                    gain.connect(dest);
                    osc.type = 'sawtooth';
                    osc.frequency.setValueAtTime(300, ctx.currentTime);
                    osc.frequency.linearRampToValueAtTime(100, ctx.currentTime + 0.4);
                    gain.gain.setValueAtTime(0.6, ctx.currentTime);
                    gain.gain.exponentialRampToValueAtTime(0.005, ctx.currentTime + 0.4);
                    osc.start(ctx.currentTime);
                    osc.stop(ctx.currentTime + 0.4);
                }
            },
            digital: {
                success: function(ctx, dest) {
                    var osc1 = ctx.createOscillator();
                    var gain1 = ctx.createGain();
                    osc1.connect(gain1);
                    gain1.connect(dest);
                    osc1.type = 'sine';
                    osc1.frequency.setValueAtTime(1200, ctx.currentTime);
                    gain1.gain.setValueAtTime(0.6, ctx.currentTime);
                    gain1.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.05);
                    osc1.start(ctx.currentTime);
                    osc1.stop(ctx.currentTime + 0.05);

                    var osc2 = ctx.createOscillator();
                    var gain2 = ctx.createGain();
                    osc2.connect(gain2);
                    gain2.connect(dest);
                    osc2.type = 'sine';
                    osc2.frequency.setValueAtTime(1200, ctx.currentTime + 0.07);
                    gain2.gain.setValueAtTime(0.6, ctx.currentTime + 0.07);
                    gain2.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.12);
                    osc2.start(ctx.currentTime + 0.07);
                    osc2.stop(ctx.currentTime + 0.12);
                },
                error: function(ctx, dest) {
                    var osc = ctx.createOscillator();
                    var gain = ctx.createGain();
                    osc.connect(gain);
                    gain.connect(dest);
                    osc.type = 'square';
                    osc.frequency.setValueAtTime(250, ctx.currentTime);
                    osc.frequency.setValueAtTime(180, ctx.currentTime + 0.1);
                    osc.frequency.setValueAtTime(250, ctx.currentTime + 0.2);
                    gain.gain.setValueAtTime(0.6, ctx.currentTime);
                    gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.3);
                    osc.start(ctx.currentTime);
                    osc.stop(ctx.currentTime + 0.3);
                }
            },
            sharp_click: {
                success: function(ctx, dest) {
                    var osc = ctx.createOscillator();
                    var gain = ctx.createGain();
                    osc.connect(gain);
                    gain.connect(dest);
                    osc.type = 'square';
                    osc.frequency.setValueAtTime(2200, ctx.currentTime);
                    gain.gain.setValueAtTime(0.4, ctx.currentTime);
                    gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.04);
                    osc.start(ctx.currentTime);
                    osc.stop(ctx.currentTime + 0.04);
                },
                error: function(ctx, dest) {
                    var osc = ctx.createOscillator();
                    var gain = ctx.createGain();
                    osc.connect(gain);
                    gain.connect(dest);
                    osc.type = 'sawtooth';
                    osc.frequency.setValueAtTime(130, ctx.currentTime);
                    gain.gain.setValueAtTime(0.8, ctx.currentTime);
                    gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.25);
                    osc.start(ctx.currentTime);
                    osc.stop(ctx.currentTime + 0.25);
                }
            },
            high_alert: {
                success: function(ctx, dest) {
                    var osc1 = ctx.createOscillator();
                    var gain1 = ctx.createGain();
                    osc1.connect(gain1);
                    gain1.connect(dest);
                    osc1.type = 'sawtooth';
                    osc1.frequency.setValueAtTime(1600, ctx.currentTime);
                    gain1.gain.setValueAtTime(0.3, ctx.currentTime);
                    gain1.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.06);
                    osc1.start(ctx.currentTime);
                    osc1.stop(ctx.currentTime + 0.06);

                    var osc2 = ctx.createOscillator();
                    var gain2 = ctx.createGain();
                    osc2.connect(gain2);
                    gain2.connect(dest);
                    osc2.type = 'sawtooth';
                    osc2.frequency.setValueAtTime(2000, ctx.currentTime + 0.06);
                    gain2.gain.setValueAtTime(0.3, ctx.currentTime + 0.06);
                    gain2.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.12);
                    osc2.start(ctx.currentTime + 0.06);
                    osc2.stop(ctx.currentTime + 0.12);
                },
                error: function(ctx, dest) {
                    var osc = ctx.createOscillator();
                    var gain = ctx.createGain();
                    osc.connect(gain);
                    gain.connect(dest);
                    osc.type = 'square';
                    osc.frequency.setValueAtTime(400, ctx.currentTime);
                    osc.frequency.setValueAtTime(300, ctx.currentTime + 0.1);
                    osc.frequency.setValueAtTime(400, ctx.currentTime + 0.2);
                    gain.gain.setValueAtTime(0.5, ctx.currentTime);
                    gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.3);
                    osc.start(ctx.currentTime);
                    osc.stop(ctx.currentTime + 0.3);
                }
            }
        },

        init: function() {
            var self = this;
            
            document.addEventListener('hkTabLoaded', function(e) {
                if (e.detail.tab === 'ayarlar') {
                    self.bindUI();
                }
            });

            if (document.querySelector('#ayarlar-ses')) {
                self.bindUI();
            }
        },

        getSettings: function() {
            try {
                var stored = localStorage.getItem('hk_sound_settings');
                if (stored) {
                    return JSON.parse(stored);
                }
            } catch(e) {
                console.warn(e);
            }

            if (window.kasaAyar && window.kasaAyar.soundSettings) {
                var settings = window.kasaAyar.soundSettings;
                try {
                    localStorage.setItem('hk_sound_settings', JSON.stringify(settings));
                } catch(e) {}
                return settings;
            }

            return { volume: 80, preset: 'classic' };
        },

        play: function(type) {
            try {
                var AudioContextClass = window.AudioContext || window.webkitAudioContext;
                if (!AudioContextClass) return;

                var settings = this.getSettings();
                var masterVolume = parseFloat(settings.volume) / 100;
                if (masterVolume <= 0) return;

                var ctx = new AudioContextClass();
                var gainNode = ctx.createGain();
                gainNode.gain.setValueAtTime(masterVolume, ctx.currentTime);
                gainNode.connect(ctx.destination);

                var preset = settings.preset || 'classic';
                var presetObj = this.presets[preset] || this.presets['classic'];

                if (presetObj && typeof presetObj[type] === 'function') {
                    presetObj[type](ctx, gainNode);
                }
            } catch(e) {
                console.warn("AudioContext error", e);
            }
        },

        bindUI: function() {
            var self = this;
            var $ = window.jQuery;
            if (!$) return;

            var settings = self.getSettings();

            var $volInput = $('#hk-sound-volume');
            var $volVal = $('#hk-sound-volume-val');
            var $presetInput = $('#hk-sound-preset');
            var $saveBtn = $('#hk-save-sound-settings');
            var $successMsg = $('#hk-sound-save-success-msg');

            if ($volInput.length) {
                $volInput.val(settings.volume);
                $volVal.text(settings.volume + '%');
                
                $volInput.on('input', function() {
                    $volVal.text($(this).val() + '%');
                });
            }

            if ($presetInput.length) {
                $presetInput.val(settings.preset);
            }

            $('#hk-test-success-sound').off('click').on('click', function() {
                var testSettings = {
                    volume: parseInt($volInput.val()),
                    preset: $presetInput.val()
                };
                self.playWithSettings('success', testSettings);
            });

            $('#hk-test-error-sound').off('click').on('click', function() {
                var testSettings = {
                    volume: parseInt($volInput.val()),
                    preset: $presetInput.val()
                };
                self.playWithSettings('error', testSettings);
            });

            $saveBtn.off('click').on('click', function() {
                var volume = parseInt($volInput.val());
                var preset = $presetInput.val();
                
                self.saveSettings(volume, preset, function(success) {
                    if (success) {
                        $successMsg.fadeIn().delay(2000).fadeOut();
                        if (HK.UIRenderer && HK.UIRenderer.showToast) {
                            HK.UIRenderer.showToast('Ses ayarları kaydedildi.', 'success');
                        }
                    }
                });
            });
        },

        playWithSettings: function(type, settings) {
            try {
                var AudioContextClass = window.AudioContext || window.webkitAudioContext;
                if (!AudioContextClass) return;

                var masterVolume = parseFloat(settings.volume) / 100;
                if (masterVolume <= 0) return;

                var ctx = new AudioContextClass();
                var gainNode = ctx.createGain();
                gainNode.gain.setValueAtTime(masterVolume, ctx.currentTime);
                gainNode.connect(ctx.destination);

                var preset = settings.preset || 'classic';
                var presetObj = this.presets[preset] || this.presets['classic'];

                if (presetObj && typeof presetObj[type] === 'function') {
                    presetObj[type](ctx, gainNode);
                }
            } catch(e) {
                console.warn(e);
            }
        },

        saveSettings: function(volume, preset, callback) {
            var self = this;
            var settings = { volume: volume, preset: preset };

            try {
                localStorage.setItem('hk_sound_settings', JSON.stringify(settings));
            } catch(e) {
                console.warn(e);
            }

            var apiBase = kasaAyar.rootApiUrl || (window.location.origin + '/wp-json/');
            
            fetch(apiBase + 'hizli-kasa/v2/user/sound-settings', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': kasaAyar.nonce
                },
                body: JSON.stringify(settings)
            })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                if (data.success) {
                    if (window.kasaAyar) {
                        window.kasaAyar.soundSettings = settings;
                    }
                    if (callback) callback(true);
                } else {
                    console.error('Ses ayarları kaydedilemedi:', data.errors);
                    if (callback) callback(false);
                }
            })
            .catch(function(err) {
                console.error('API hatası:', err);
                if (callback) callback(false);
            });
        }
    };
})(window.HizliKasa = window.HizliKasa || {});
