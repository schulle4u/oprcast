import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const source = readFileSync(new URL('../system/themes/default/player.js', import.meta.url), 'utf8');
function player(query = '?t=30', clipboardFails = false) {
    const audioEvents = {};
    const windowEvents = {};
    const linkEvents = {};
    const buttonEvents = {};
    const announcementTimers = new Map();
    const statusChanges = [];
    let timerId = 0;
    const audio = { readyState: 0, duration: 60, currentTime: 0, addEventListener: (name, handler) => { audioEvents[name] = handler; } };
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
        dataset: { copyLabel: 'Copy', copiedLabel: 'Copied', copyFailed: 'Copy manually' },
        querySelector: (selector) => selector === 'audio' ? audio : status,
        querySelectorAll: () => [link],
    };
    const context = {
        URL, Number, location: { href: `https://example.test/sub/cast/episode/${query}` },
        setTimeout: (callback) => { announcementTimers.set(++timerId, callback); return timerId; },
        clearTimeout: (id) => { announcementTimers.delete(id); },
        window: { addEventListener: (name, handler) => { windowEvents[name] = handler; } },
        history: { pushState: (_, __, url) => { context.location.href = url.href; } },
        navigator: { clipboard: { writeText: async (text) => { if (clipboardFails) throw new Error('Denied'); copies.push(text); } } },
        document: {
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
    return { audio, status, statusChanges, announcementTimers, flushAnnouncements, copies, link, get button() { return button; }, audioEvents, windowEvents, linkEvents, buttonEvents, context };
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
console.log('All player tests passed.');
