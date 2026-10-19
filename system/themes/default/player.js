'use strict';

// Optional enhancement: the native player and ordinary chapter links remain usable.
const episode = document.querySelector('[data-episode-player]');
if (episode) {
    const audio = episode.querySelector('audio');
    const status = episode.querySelector('[role="status"]');
    let pendingTime = null;
    const timestamp = (value) => value !== null && /^\d+(?:\.\d+)?$/.test(value) && Number.isFinite(Number(value)) ? Number(value) : null;
    const applyTime = () => {
        if (pendingTime !== null && audio.readyState >= 1) {
            const time = Number.isFinite(audio.duration) ? Math.min(pendingTime, Math.max(0, audio.duration - 0.01)) : pendingTime;
            try {
                audio.currentTime = time;
                pendingTime = null;
            } catch (_) { /* Retry when media metadata becomes available. */ }
        }
    };
    const seek = (time) => {
        pendingTime = time;
        applyTime();
    };
    audio.addEventListener('loadedmetadata', applyTime);
    audio.addEventListener('durationchange', applyTime);
    const initialTime = timestamp(new URL(location.href).searchParams.get('t'));
    if (initialTime !== null) seek(initialTime);
    window.addEventListener('popstate', () => {
        const time = timestamp(new URL(location.href).searchParams.get('t'));
        if (time !== null) seek(time);
    });

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
                status.textContent = episode.dataset.copiedLabel;
            } catch (_) {
                // A normal link is available even without Clipboard API permission.
                status.textContent = episode.dataset.copyFailed;
                link.focus();
            }
        });
        link.after(copy);
    }
}
