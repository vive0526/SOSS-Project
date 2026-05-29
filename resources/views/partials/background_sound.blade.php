<audio id="backgroundSound" data-src="{{ asset('sounds/NATURE.mp3') }}" loop preload="none"></audio>
<button type="button" class="bg-sound-toggle" id="bgSoundToggle" aria-pressed="false">
    Sound: Off
</button>

<script>
    (function () {
        const audioEl = document.getElementById('backgroundSound');
        const toggle = document.getElementById('bgSoundToggle');
        if (!audioEl || !toggle) return;

        const storageKey = 'soss_bg_sound_enabled';
        const timeKey = 'soss_bg_sound_time';
        const enabledDefault = false;

        const getEnabled = () => {
            const raw = localStorage.getItem(storageKey);
            if (raw === null) return enabledDefault;
            return raw === '1';
        };

        const setEnabled = (on) => localStorage.setItem(storageKey, on ? '1' : '0');

        const ensureSrc = () => {
            if (audioEl.src) return;
            const src = audioEl.getAttribute('data-src');
            if (!src) return;
            audioEl.src = src;
        };

        const setUi = (on) => {
            toggle.setAttribute('aria-pressed', on ? 'true' : 'false');
            toggle.textContent = on ? 'Sound: On' : 'Sound: Off';
        };

        const tryPlay = async () => {
            ensureSrc();
            audioEl.volume = 0.2;
            try {
                await audioEl.play();
            } catch (_) {
                // Autoplay with sound is blocked until user gesture.
            }
        };

        const stop = () => {
            try { audioEl.pause(); } catch (_) {}
            sessionStorage.removeItem(timeKey);
            try { audioEl.currentTime = 0; } catch (_) {}
        };

        let saveTimer = null;
        const startSavingTime = () => {
            if (saveTimer) return;
            saveTimer = window.setInterval(() => {
                if (audioEl.paused) return;
                sessionStorage.setItem(timeKey, String(audioEl.currentTime || 0));
            }, 1000);
        };

        const stopSavingTime = () => {
            if (!saveTimer) return;
            window.clearInterval(saveTimer);
            saveTimer = null;
        };

        const restoreTimeIfAny = () => {
            const raw = sessionStorage.getItem(timeKey);
            const t = raw ? Number(raw) : 0;
            if (!Number.isFinite(t) || t <= 0) return;

            const apply = () => {
                try {
                    const dur = Number(audioEl.duration);
                    if (Number.isFinite(dur) && dur > 0) {
                        audioEl.currentTime = Math.min(Math.max(0, t), Math.max(0, dur - 0.25));
                    } else {
                        audioEl.currentTime = Math.max(0, t);
                    }
                } catch (_) {}
            };

            if (Number.isFinite(audioEl.duration) && audioEl.duration > 0) apply();
            else audioEl.addEventListener('loadedmetadata', apply, { once: true });
        };

        const apply = (on) => {
            setUi(on);
            if (on) {
                ensureSrc();
                restoreTimeIfAny();
                tryPlay();
                startSavingTime();
            } else {
                stopSavingTime();
                stop();
            }
        };

        apply(getEnabled());

        toggle.addEventListener('click', () => {
            const next = !getEnabled();
            setEnabled(next);
            apply(next);
        });

        document.addEventListener('click', () => {
            if (!getEnabled()) return;
            if (!audioEl.paused) return;
            tryPlay();
        }, { once: true });

        window.addEventListener('beforeunload', () => {
            if (!getEnabled()) return;
            try {
                sessionStorage.setItem(timeKey, String(audioEl.currentTime || 0));
            } catch (_) {}
        });
    })();
</script>

