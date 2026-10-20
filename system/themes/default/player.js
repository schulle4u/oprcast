'use strict';

// Optional enhancement: the native player and ordinary chapter links remain usable.
const episode = document.querySelector('[data-episode-player]');
if (episode) {
    const audio = episode.querySelector('audio');
    const status = episode.querySelector('[role="status"]');
    let announcementTimer;
    const announce = (message) => {
        clearTimeout(announcementTimer);
        // Separate removal and insertion so repeated messages are announced too.
        status.textContent = '';
        announcementTimer = setTimeout(() => {
            status.textContent = message;
        }, 100);
    };
    const formatTime = (seconds) => {
        const whole = Math.floor(seconds);
        const minutes = Math.floor(whole / 60);
        return `${minutes}:${String(whole % 60).padStart(2, '0')}`;
    };
    const storageKey = 'oprcast:position:' + encodeURIComponent(episode.dataset.episodeId);
    const removePosition = () => {
        try { localStorage.removeItem(storageKey); } catch (_) { /* Storage is optional. */ }
    };
    let pendingTime = null;
    let restoringPosition = false;
    const timestamp = (value) => value !== null && /^\d+(?:\.\d+)?$/.test(value) && Number.isFinite(Number(value)) ? Number(value) : null;
    const applyTime = () => {
        if (pendingTime !== null && audio.readyState >= 1) {
            if (restoringPosition && Number.isFinite(audio.duration) && pendingTime >= audio.duration - 1) {
                pendingTime = null;
                restoringPosition = false;
                removePosition();
                return;
            }
            const time = Number.isFinite(audio.duration) ? Math.min(pendingTime, Math.max(0, audio.duration - 0.01)) : pendingTime;
            try {
                audio.currentTime = time;
                pendingTime = null;
                if (restoringPosition) {
                    restoringPosition = false;
                    announce(episode.dataset.resumeLabel.replace('{time}', formatTime(time)));
                }
            } catch (_) { /* Retry when media metadata becomes available. */ }
        }
    };
    const seek = (time) => {
        restoringPosition = false;
        pendingTime = Math.max(0, time);
        applyTime();
    };
    audio.addEventListener('loadedmetadata', applyTime);
    audio.addEventListener('durationchange', applyTime);
    const initialTime = timestamp(new URL(location.href).searchParams.get('t'));
    if (initialTime !== null) {
        seek(initialTime);
    } else {
        try {
            const remembered = timestamp(localStorage.getItem(storageKey));
            if (remembered !== null && remembered > 0) {
                pendingTime = remembered;
                restoringPosition = true;
                applyTime();
            }
        } catch (_) { /* Playback still works if storage is blocked or unavailable. */ }
    }
    window.addEventListener('popstate', () => {
        const time = timestamp(new URL(location.href).searchParams.get('t'));
        if (time !== null) seek(time);
    });

    let hasPlayed = false;
    let lastSavedBucket = null;
    const savePosition = (force = false) => {
        if (!hasPlayed || pendingTime !== null || !Number.isFinite(audio.currentTime)) return;
        const bucket = Math.floor(audio.currentTime / 5);
        if (!force && bucket === lastSavedBucket) return;
        lastSavedBucket = bucket;
        if (audio.ended || audio.currentTime <= 0 || (Number.isFinite(audio.duration) && audio.currentTime >= audio.duration - 1)) {
            removePosition();
        } else {
            try { localStorage.setItem(storageKey, String(audio.currentTime)); } catch (_) { /* Storage is optional. */ }
        }
    };
    audio.addEventListener('play', () => { hasPlayed = true; });
    audio.addEventListener('timeupdate', () => savePosition());
    audio.addEventListener('pause', () => savePosition(true));
    audio.addEventListener('seeked', () => savePosition(true));
    audio.addEventListener('ended', () => {
        hasPlayed = false;
        removePosition();
    });
    window.addEventListener('pagehide', () => savePosition(true));
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'hidden') savePosition(true);
    });

    const controls = episode.querySelector('[data-player-controls]');
    const skipButtons = controls.querySelectorAll('[data-skip]');
    const updateSkipButtons = () => {
        for (const button of skipButtons) {
            button.disabled = audio.readyState < 1 || !Number.isFinite(audio.duration) || audio.duration <= 0;
        }
    };
    audio.addEventListener('loadedmetadata', updateSkipButtons);
    audio.addEventListener('durationchange', updateSkipButtons);
    for (const button of skipButtons) {
        button.addEventListener('click', () => {
            seek(audio.currentTime + Number(button.dataset.skip));
            announce(episode.dataset.positionLabel.replace('{time}', formatTime(audio.currentTime)));
        });
    }
    const speed = controls.querySelector('[data-playback-speed]');
    speed.addEventListener('change', () => {
        const rate = Number(speed.value);
        if (Number.isFinite(rate) && rate > 0 && [...speed.options].some((option) => option.value === speed.value)) {
            audio.playbackRate = rate;
        }
    });
    const updateSpeed = () => {
        const rate = String(audio.playbackRate);
        if (![...speed.options].some((option) => option.value === rate)) {
            const option = document.createElement('option');
            option.value = rate;
            option.textContent = rate + '×';
            speed.append(option);
        }
        speed.value = rate;
    };
    audio.addEventListener('ratechange', updateSpeed);
    updateSpeed();
    updateSkipButtons();
    controls.hidden = false;

    for (const link of episode.querySelectorAll('[data-show-notes] a[href^="?t="]')) {
        const url = new URL(link.href);
        const time = timestamp(url.searchParams.get('t'));
        if (time === null) continue;
        link.addEventListener('click', (event) => {
            if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
            event.preventDefault();
            seek(time);
            history.pushState(null, '', url);
        });
        const copy = document.createElement('button');
        copy.type = 'button';
        copy.className = 'chapter-copy';
        copy.textContent = episode.dataset.copyLabel;
        copy.setAttribute('aria-label', `${episode.dataset.copyLabel} (${link.textContent})`);
        copy.addEventListener('click', async () => {
            try {
                await navigator.clipboard.writeText(url.href);
                announce(episode.dataset.copiedLabel);
            } catch (_) {
                // A normal link is available even without Clipboard API permission.
                link.focus();
                announce(episode.dataset.copyFailed);
            }
        });
        link.after(copy);
    }
}
