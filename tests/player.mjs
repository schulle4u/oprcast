import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const source = readFileSync(new URL('../system/themes/default/player.js', import.meta.url), 'utf8');
function player(query = '?t=30', clipboardFails = false, options = {}) {
    const audioEvents = {};
    const windowEvents = {};
    const documentEvents = {};
    const linkEvents = {};
    const buttonEvents = {};
    const announcementTimers = new Map();
    const statusChanges = [];
    let timerId = 0;
    const listen = (events, name, handler) => {
        const previous = events[name];
        events[name] = (...args) => { previous?.(...args); handler(...args); };
    };
    const audio = { readyState: 0, duration: 60, currentTime: 0, playbackRate: 1, ended: false, addEventListener: (name, handler) => listen(audioEvents, name, handler) };
    const skipEvents = [{}, {}];
    const skipButtons = [-15, 30].map((seconds, index) => ({
        dataset: { skip: String(seconds) }, disabled: true,
        addEventListener: (name, handler) => listen(skipEvents[index], name, handler),
    }));
    const speedEvents = {};
    const speed = {
        value: '1', options: [0.75, 1, 1.25, 1.5, 2].map((value) => ({ value: String(value) })),
        addEventListener: (name, handler) => listen(speedEvents, name, handler),
        append(option) { this.options.push(option); },
    };
    const controls = { hidden: true, querySelectorAll: () => skipButtons, querySelector: () => speed };
    const positions = options.positions ?? new Map();
    const episodeId = options.episodeId ?? 'urn:uuid:player-test';
    const storageKey = 'oprcast:position:' + encodeURIComponent(episodeId);
    let storageWrites = 0;
    const storage = {
        getItem(key) { if (options.storageBlocked) throw new Error('Blocked'); return positions.get(key) ?? null; },
        setItem(key, value) { if (options.storageBlocked) throw new Error('Blocked'); positions.set(key, value); storageWrites++; },
        removeItem(key) { if (options.storageBlocked) throw new Error('Blocked'); positions.delete(key); },
    };
    let statusText = '';
    const status = {
        get textContent() { return statusText; },
        set textContent(value) { statusText = value; statusChanges.push(value); },
    };
    const copies = [];
    let button;
    const link = {
        href: 'https://example.test/sub/cast/episode/?t=45', textContent: '00:45',
        addEventListener: (name, handler) => { linkEvents[name] = handler; },
        after: (element) => { button = element; }, focus: () => { link.focused = true; },
    };
    const article = {
        dataset: { episodeId, positionLabel: 'Position: {time}.', resumeLabel: 'Restored: {time}.', copyLabel: 'Copy', copiedLabel: 'Copied', copyFailed: 'Copy manually' },
        querySelector: (selector) => selector === 'audio' ? audio : selector === '[data-player-controls]' ? controls : status,
        querySelectorAll: () => [link],
    };
    const context = {
        URL, Number, location: { href: `https://example.test/sub/cast/episode/${query}` },
        localStorage: storage,
        setTimeout: (callback) => { announcementTimers.set(++timerId, callback); return timerId; },
        clearTimeout: (id) => { announcementTimers.delete(id); },
        window: { addEventListener: (name, handler) => listen(windowEvents, name, handler) },
        history: { pushState: (_, __, url) => { context.location.href = url.href; } },
        navigator: { clipboard: { writeText: async (text) => { if (clipboardFails) throw new Error('Denied'); copies.push(text); } } },
        document: {
            visibilityState: 'visible',
            addEventListener: (name, handler) => listen(documentEvents, name, handler),
            querySelector: () => article,
            createElement: () => ({ setAttribute() {}, addEventListener: (name, handler) => { buttonEvents[name] = handler; } }),
        },
    };
    vm.runInNewContext(source, context);
    const flushAnnouncements = () => {
        const callbacks = [...announcementTimers.values()];
        announcementTimers.clear();
        callbacks.forEach((callback) => callback());
    };
    return { audio, status, statusChanges, announcementTimers, flushAnnouncements, copies, link, get button() { return button; }, audioEvents, windowEvents, documentEvents, linkEvents, buttonEvents, context, controls, skipButtons, skipEvents, speed, speedEvents, positions, storageKey, get storageWrites() { return storageWrites; } };
}

const p = player();
assert.equal(p.audio.currentTime, 0, 'Deep links wait until metadata is available.');
p.audio.readyState = 1;
p.audioEvents.loadedmetadata();
assert.equal(p.audio.currentTime, 30, 'Deep links seek once metadata is available.');
let prevented = false;
p.linkEvents.click({ button: 0, preventDefault() { prevented = true; } });
assert.equal(prevented, true);
assert.equal(p.audio.currentTime, 45, 'Chapter links seek inside the current player.');
assert.equal(p.context.location.href, p.link.href, 'Chapter links update the shareable URL, including a subdirectory.');
await p.buttonEvents.click();
assert.deepEqual(p.copies, [p.link.href]);
assert.equal(p.status.textContent, '', 'The first message is inserted in a separate task.');
p.flushAnnouncements();
assert.equal(p.status.textContent, 'Copied');
await p.buttonEvents.click();
assert.equal(p.status.textContent, '', 'Repeated copying clears the previous message.');
p.flushAnnouncements();
assert.deepEqual(p.statusChanges, ['', 'Copied', '', 'Copied'], 'Identical feedback is removed and reinserted for every copy.');
await p.buttonEvents.click();
await p.buttonEvents.click();
assert.equal(p.announcementTimers.size, 1, 'Rapid clicks cancel superseded announcements.');
p.flushAnnouncements();
assert.equal(p.status.textContent, 'Copied');
p.context.location.href = 'https://example.test/sub/cast/episode/?t=12';
p.windowEvents.popstate();
assert.equal(p.audio.currentTime, 12, 'Back navigation restores the timestamp.');
prevented = false;
p.linkEvents.click({ button: 0, ctrlKey: true, preventDefault() { prevented = true; } });
assert.equal(prevented, false, 'Modified clicks retain native link behavior.');
for (const query of ['?t=-1', '?t=Infinity', '?t=NaN', '?t=', '?t=1e999']) {
    const invalid = player(query);
    invalid.audio.readyState = 1;
    invalid.audioEvents.loadedmetadata();
    assert.equal(invalid.audio.currentTime, 0, 'Invalid timestamps are ignored.');
}
const end = player('?t=999');
end.audio.readyState = 1;
end.audioEvents.loadedmetadata();
assert.ok(end.audio.currentTime < 60 && end.audio.currentTime > 59, 'Times beyond the duration clamp to the end.');
const denied = player('', true);
await denied.buttonEvents.click();
assert.equal(denied.status.textContent, '');
denied.flushAnnouncements();
assert.equal(denied.status.textContent, 'Copy manually');
assert.equal(denied.link.focused, true, 'Clipboard denial leaves an accessible link fallback.');
await denied.buttonEvents.click();
denied.flushAnnouncements();
assert.deepEqual(denied.statusChanges, ['', 'Copy manually', '', 'Copy manually'], 'Repeated failures also produce fresh announcements.');

const comfort = player('');
assert.equal(comfort.controls.hidden, false, 'Additional controls appear after JavaScript initialization.');
assert.ok(comfort.skipButtons.every((button) => button.disabled), 'Skip controls wait for audio metadata.');
comfort.audio.readyState = 1;
comfort.audioEvents.loadedmetadata();
assert.ok(comfort.skipButtons.every((button) => !button.disabled));
comfort.audio.currentTime = 5;
comfort.skipEvents[0].click();
assert.equal(comfort.audio.currentTime, 0, 'Skipping back never seeks before the start.');
comfort.skipEvents[1].click();
assert.equal(comfort.audio.currentTime, 30, 'Skipping forward advances 30 seconds.');
comfort.skipEvents[0].click();
assert.equal(comfort.audio.currentTime, 15, 'Skipping back rewinds 15 seconds.');
comfort.audio.currentTime = 50;
comfort.skipEvents[1].click();
assert.ok(comfort.audio.currentTime < 60 && comfort.audio.currentTime > 59, 'Skipping forward stays within the audio duration.');
comfort.flushAnnouncements();
assert.equal(comfort.status.textContent, 'Position: 0:59.', 'Skip feedback uses the existing accessible live region.');
comfort.speed.value = '1.5';
comfort.speedEvents.change();
assert.equal(comfort.audio.playbackRate, 1.5, 'The speed selector sets audio playback speed.');
comfort.audio.playbackRate = 1.25;
comfort.audioEvents.ratechange();
assert.equal(comfort.speed.value, '1.25', 'Native speed changes update the selector.');
comfort.audio.playbackRate = 0.5;
comfort.audioEvents.ratechange();
assert.equal(comfort.speed.value, '0.5', 'Native speeds outside the presets remain visible.');
assert.ok(comfort.speed.options.some((option) => option.value === '0.5'));
comfort.speed.value = '1';
comfort.speedEvents.change();
comfort.speed.value = '0.5';
comfort.speedEvents.change();
assert.equal(comfort.audio.playbackRate, 0.5, 'A speed discovered through native controls can be selected again.');

const saved = player('');
saved.audio.readyState = 1;
saved.audioEvents.loadedmetadata();
saved.audio.currentTime = 20;
saved.audioEvents.timeupdate();
assert.equal(saved.positions.size, 0, 'Merely loading or seeking in a page does not overwrite a saved position.');
saved.audioEvents.play();
saved.audioEvents.timeupdate();
assert.equal(saved.positions.get(saved.storageKey), '20');
saved.audio.currentTime = 21;
saved.audioEvents.timeupdate();
assert.equal(saved.storageWrites, 1, 'Time updates throttle storage writes to five-second buckets.');
saved.audioEvents.pause();
assert.equal(saved.positions.get(saved.storageKey), '21', 'Pausing saves the exact current position.');
saved.audio.currentTime = 22;
saved.windowEvents.pagehide();
assert.equal(saved.positions.get(saved.storageKey), '22', 'Leaving the page saves the current position.');
saved.audio.currentTime = 23;
saved.context.document.visibilityState = 'hidden';
saved.documentEvents.visibilitychange();
assert.equal(saved.positions.get(saved.storageKey), '23', 'Backgrounding the page saves the current position.');
const resumed = player('', false, { positions: saved.positions });
resumed.audioEvents.timeupdate();
assert.equal(resumed.positions.get(resumed.storageKey), '23', 'Loading metadata cannot erase a pending resume position.');
resumed.audio.readyState = 1;
resumed.audioEvents.loadedmetadata();
assert.equal(resumed.audio.currentTime, 23, 'Revisiting the episode restores its position after metadata loads.');
resumed.flushAnnouncements();
assert.equal(resumed.status.textContent, 'Restored: 0:23.');
const shared = player('?t=0', false, { positions: saved.positions });
shared.audio.readyState = 1;
shared.audioEvents.loadedmetadata();
assert.equal(shared.audio.currentTime, 0, 'An explicit timestamp, including zero, overrides the saved position.');
const other = player('', false, { positions: saved.positions, episodeId: 'urn:uuid:another-episode' });
other.audio.readyState = 1;
other.audioEvents.loadedmetadata();
assert.equal(other.audio.currentTime, 0, 'Positions are isolated by permanent episode identity.');
saved.audio.ended = true;
saved.audioEvents.ended();
saved.windowEvents.pagehide();
assert.equal(saved.positions.has(saved.storageKey), false, 'Completed episodes clear their saved position and do not resave it on exit.');
for (const value of ['59.5', '999', '-1', 'NaN', '{}']) {
    const positions = new Map([[saved.storageKey, value]]);
    const stale = player('', false, { positions });
    stale.audio.readyState = 1;
    stale.audioEvents.loadedmetadata();
    assert.equal(stale.audio.currentTime, 0, 'Invalid or completed saved positions never resume at the end.');
}
const blocked = player('', false, { storageBlocked: true });
blocked.audio.readyState = 1;
blocked.audioEvents.loadedmetadata();
blocked.audioEvents.play();
blocked.audio.currentTime = 25;
blocked.audioEvents.timeupdate();
blocked.audioEvents.pause();
blocked.audioEvents.ended();
blocked.skipEvents[0].click();
assert.equal(blocked.audio.currentTime, 10, 'Blocked storage does not break playback controls.');
console.log('All player tests passed.');
