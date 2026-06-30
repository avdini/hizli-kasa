(function(HK) {
    'use strict';

    HK.Sound = {
        play: function(type) {
            try {
                var AudioContextClass = window.AudioContext || window.webkitAudioContext;
                if (!AudioContextClass) return;
                
                var ctx = new AudioContextClass();
                var osc = ctx.createOscillator();
                var gain = ctx.createGain();
                
                osc.connect(gain);
                gain.connect(ctx.destination);
                
                if (type === 'success') {
                    osc.frequency.setValueAtTime(800, ctx.currentTime);
                    gain.gain.setValueAtTime(0.1, ctx.currentTime);
                    gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.1);
                    osc.start(ctx.currentTime);
                    osc.stop(ctx.currentTime + 0.1);
                } else if (type === 'error') {
                    osc.type = 'sawtooth';
                    osc.frequency.setValueAtTime(220, ctx.currentTime);
                    gain.gain.setValueAtTime(0.15, ctx.currentTime);
                    gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.3);
                    osc.start(ctx.currentTime);
                    osc.stop(ctx.currentTime + 0.3);
                }
            } catch(e) {
                console.warn("AudioContext error", e);
            }
        }
    };
})(window.HizliKasa = window.HizliKasa || {});
