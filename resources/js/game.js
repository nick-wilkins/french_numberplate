/**
 * The plate hunt runs entirely client side so that tapping a plate keeps working
 * with no signal. Every change is written to localStorage immediately and queued
 * in an outbox; the outbox is replayed against /api/sync whenever the network
 * comes back. Each splodge carries a client generated uid, so replaying a queue
 * can never create duplicates.
 */

const STATE_KEY = 'fnp.state.v1';
const POLL_INTERVAL = 5000;
const NUDGE_DELAY = 350;
const SPLAT_VARIANTS = 6;
const MAX_SPLATS_PER_CAR = 4;
const LONG_PRESS_MS = 550;
const LONG_PRESS_SLOP = 12;
const TOAST_MS = 2600;

const CAR_CLASS = { 1: 'bg-car-blue', 2: 'bg-car-red' };
const CAR_NAME = { 1: 'Blue', 2: 'Red' };
const CAR_FILL = { 1: 'fill-car-blue', 2: 'fill-car-red' };

/*
 * Each car paints into its own rotation of fixed slots, so one car's splodges
 * never bury the other's — with a splat at 64% of the plate you can always see
 * that both cars found it, and one/two/three/four reads as a growing cluster.
 */
const SPLAT_SLOTS = {
    1: [
        [37, 37],
        [37, 63],
        [63, 37],
        [63, 63],
    ],
    2: [
        [63, 63],
        [63, 37],
        [37, 63],
        [37, 37],
    ],
};

const state = {
    car: null,
    filter: 'all',
    revision: '',
    sightings: [],
    messages: [],
    messagesCursor: 0,
    readCount: 0,
    cheerDismissed: false,
    outboxAdd: [],
    outboxRemove: [],
    outboxMessages: [],
};

let online = true;
let syncing = false;
let saveTimer = null;
let nudgeTimer = null;
let toastTimer = null;
let root = null;
let el = {};
const plates = new Map();
const splatElements = new Map();

/* ------------------------------------------------------------------ helpers */

function uuid() {
    if (globalThis.crypto?.randomUUID) {
        return globalThis.crypto.randomUUID();
    }

    const bytes = new Uint8Array(16);

    if (globalThis.crypto?.getRandomValues) {
        globalThis.crypto.getRandomValues(bytes);
    } else {
        for (let i = 0; i < 16; i += 1) {
            bytes[i] = Math.floor(Math.random() * 256);
        }
    }

    bytes[6] = (bytes[6] & 0x0f) | 0x40;
    bytes[8] = (bytes[8] & 0x3f) | 0x80;

    const hex = [...bytes].map((byte) => byte.toString(16).padStart(2, '0')).join('');

    return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`;
}

function randomInt(min, max) {
    return min + Math.floor(Math.random() * (max - min + 1));
}

function clamp(value, min, max) {
    return Math.min(max, Math.max(min, value));
}

/* ------------------------------------------------------- paint splat shapes */

function seededRandom(seed) {
    let value = seed >>> 0;

    return () => {
        value = (value + 0x6d2b79f5) >>> 0;
        let t = Math.imul(value ^ (value >>> 15), 1 | value);
        t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t;

        return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
    };
}

function closedCurve(points) {
    const total = points.length;
    const round = (value) => Math.round(value * 100) / 100;
    let path = `M${round(points[0][0])} ${round(points[0][1])}`;

    for (let i = 0; i < total; i += 1) {
        const previous = points[(i - 1 + total) % total];
        const current = points[i];
        const next = points[(i + 1) % total];
        const following = points[(i + 2) % total];

        const c1x = current[0] + (next[0] - previous[0]) / 6;
        const c1y = current[1] + (next[1] - previous[1]) / 6;
        const c2x = next[0] - (following[0] - current[0]) / 6;
        const c2y = next[1] - (following[1] - current[1]) / 6;

        path += `C${round(c1x)} ${round(c1y)} ${round(c2x)} ${round(c2y)} ${round(next[0])} ${round(next[1])}`;
    }

    return `${path}Z`;
}

const shapeCache = new Map();

function splatShape(variant) {
    if (shapeCache.has(variant)) {
        return shapeCache.get(variant);
    }

    const random = seededRandom(variant * 7919 + 17);
    const lobes = 9;
    const points = [];

    for (let i = 0; i < lobes; i += 1) {
        const angle = (i / lobes) * Math.PI * 2;
        const radius = 29 + random() * 15;
        points.push([50 + Math.cos(angle) * radius, 50 + Math.sin(angle) * radius]);
    }

    let markup = `<path d="${closedCurve(points)}"/>`;

    const droplets = 3 + Math.floor(random() * 3);

    for (let i = 0; i < droplets; i += 1) {
        const angle = random() * Math.PI * 2;
        const distance = 34 + random() * 12;
        const cx = Math.round((50 + Math.cos(angle) * distance) * 100) / 100;
        const cy = Math.round((50 + Math.sin(angle) * distance) * 100) / 100;
        const r = Math.round((2 + random() * 3) * 100) / 100;
        markup += `<circle cx="${cx}" cy="${cy}" r="${r}"/>`;
    }

    shapeCache.set(variant, markup);

    return markup;
}

function createSplatElement(sighting) {
    const wrapper = document.createElement('span');
    const fill = CAR_FILL[sighting.car] ?? CAR_FILL[1];

    wrapper.className = 'splat splat--enter';
    wrapper.style.setProperty('--splat-x', `${sighting.x}%`);
    wrapper.style.setProperty('--splat-y', `${sighting.y}%`);
    wrapper.style.setProperty('--splat-rotate', `${sighting.rotation}deg`);
    wrapper.style.setProperty('--splat-scale', String(sighting.scale / 100));
    wrapper.innerHTML = `<svg viewBox="0 0 100 100" class="${fill}">${splatShape(sighting.variant)}</svg>`;

    wrapper.addEventListener('animationend', () => wrapper.classList.remove('splat--enter'), { once: true });

    return wrapper;
}

/* -------------------------------------------------------------- persistence */

function save() {
    if (saveTimer) {
        clearTimeout(saveTimer);
    }

    saveTimer = setTimeout(writeNow, 200);
}

function writeNow() {
    saveTimer = null;

    try {
        localStorage.setItem(STATE_KEY, JSON.stringify(state));
    } catch {
        /* A full or unavailable store must never break the game. */
    }
}

function restore() {
    let stored = null;

    try {
        stored = JSON.parse(localStorage.getItem(STATE_KEY) ?? 'null');
    } catch {
        stored = null;
    }

    if (!stored || typeof stored !== 'object') {
        return;
    }

    for (const key of Object.keys(state)) {
        const value = stored[key];

        if (value === undefined || value === null) {
            continue;
        }

        if (Array.isArray(state[key]) !== Array.isArray(value)) {
            continue;
        }

        state[key] = value;
    }

    if (state.car !== 1 && state.car !== 2) {
        state.car = null;
    }

    if (!['all', 'mine', 'ours', 'todo'].includes(state.filter)) {
        state.filter = 'all';
    }
}

/* ------------------------------------------------------------------- render */

function sightingsByCode() {
    const grouped = new Map();

    for (const sighting of state.sightings) {
        const list = grouped.get(sighting.code);

        if (list) {
            list.push(sighting);
        } else {
            grouped.set(sighting.code, [sighting]);
        }
    }

    return grouped;
}

function renderBoard() {
    const grouped = sightingsByCode();
    const live = new Set();
    let found = 0;
    let blue = 0;
    let red = 0;
    let mine = 0;

    for (const [code, plate] of plates) {
        const list = grouped.get(code) ?? [];
        const byBlue = list.some((sighting) => sighting.car === 1);
        const byRed = list.some((sighting) => sighting.car === 2);
        const byMe = state.car === 1 ? byBlue : state.car === 2 ? byRed : false;

        for (const sighting of list) {
            live.add(sighting.uid);

            if (!splatElements.has(sighting.uid)) {
                const element = createSplatElement(sighting);
                splatElements.set(sighting.uid, element);
                plate.splats.appendChild(element);
            }
        }

        toggleAttribute(plate.button, 'data-mine', byMe);
        toggleAttribute(plate.button, 'data-any', list.length > 0);
        plate.both.hidden = !(byBlue && byRed);
        plate.status.textContent = statusText(plate.name, byBlue, byRed);

        if (list.length > 0) {
            found += 1;
        }

        if (byBlue) {
            blue += 1;
        }

        if (byRed) {
            red += 1;
        }

        if (byMe) {
            mine += 1;
        }
    }

    for (const [uid, element] of splatElements) {
        if (!live.has(uid)) {
            element.remove();
            splatElements.delete(uid);
        }
    }

    const total = plates.size;

    el.foundCount.textContent = String(found);
    el.blueCount.textContent = String(blue);
    el.redCount.textContent = String(red);
    el.progressBar.style.width = total ? `${(found / total) * 100}%` : '0%';
    el.progressBar.setAttribute('aria-valuenow', String(found));
    el.progressBar.setAttribute('aria-valuetext', `${found} of ${total} plates found`);

    el.filterCounts.all.textContent = String(total);
    el.filterCounts.mine.textContent = String(mine);
    el.filterCounts.ours.textContent = String(found);
    el.filterCounts.todo.textContent = String(total - found);

    el.cheer.hidden = !(total > 0 && found === total) || state.cheerDismissed;
    el.undo.disabled = !state.car || !state.sightings.some((sighting) => sighting.car === state.car);

    applyFilter();
}

function toggleAttribute(element, name, on) {
    if (on) {
        element.setAttribute(name, '');
    } else {
        element.removeAttribute(name);
    }
}

function statusText(name, byBlue, byRed) {
    if (byBlue && byRed) {
        return `${name}. Found by both cars.`;
    }

    if (byBlue) {
        return `${name}. Found by the Blue car.`;
    }

    if (byRed) {
        return `${name}. Found by the Red car.`;
    }

    return `${name}. Nobody has spotted this one yet.`;
}

const EMPTY_MESSAGES = {
    mine: "You haven't painted any plates yet. Tap one the moment you spot it!",
    ours: 'No plates found yet. Keep your eyes on the road!',
    todo: 'Every single plate has been found. You two are unstoppable!',
    all: '',
};

function isVisible(button) {
    switch (state.filter) {
        case 'mine':
            return button.hasAttribute('data-mine');
        case 'ours':
            return button.hasAttribute('data-any');
        case 'todo':
            return !button.hasAttribute('data-any');
        default:
            return true;
    }
}

function applyFilter() {
    root.dataset.filter = state.filter;

    for (const button of el.filterButtons) {
        button.setAttribute('aria-pressed', button.dataset.filterButton === state.filter ? 'true' : 'false');
    }

    let visible = 0;

    for (const section of el.sections) {
        const count = section.plates.filter(isVisible).length;
        section.element.hidden = count === 0;
        visible += count;
    }

    for (const group of el.groups) {
        group.element.hidden = group.sections.every((section) => section.hidden);
    }

    for (const note of el.groupNotes) {
        note.hidden = state.filter !== 'all';
    }

    el.emptyState.hidden = visible > 0;
    el.emptyState.textContent = visible > 0 ? '' : EMPTY_MESSAGES[state.filter];
}

/* -------------------------------------------------------------------- toast */

function toast(message) {
    el.toastText.textContent = message;
    el.toast.hidden = false;
    el.toast.classList.remove('toast');
    void el.toast.offsetWidth;
    el.toast.classList.add('toast');

    if (toastTimer) {
        clearTimeout(toastTimer);
    }

    toastTimer = setTimeout(() => {
        toastTimer = null;
        el.toast.hidden = true;
    }, TOAST_MS);
}

/* --------------------------------------------------------------------- chat */

function timeLabel(value) {
    const at = new Date(value);

    if (Number.isNaN(at.getTime())) {
        return '';
    }

    return at.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}

function renderChat() {
    const log = el.chatLog;

    log.replaceChildren();

    if (state.messages.length === 0) {
        const empty = document.createElement('p');
        empty.className = 'my-auto text-center text-base text-pretty text-zinc-600 dark:text-zinc-300';
        empty.textContent = 'No messages yet. Say hello to the other car!';
        log.appendChild(empty);
    }

    for (const message of state.messages) {
        const mine = message.car === state.car;
        const row = document.createElement('div');
        row.className = `flex ${mine ? 'justify-end' : 'justify-start'}`;

        const bubble = document.createElement('div');
        bubble.className = [
            'max-w-[80%] rounded-2xl px-3.5 py-2',
            mine
                ? `${CAR_CLASS[message.car]} text-white`
                : 'bg-zinc-100 text-zinc-950 dark:bg-zinc-700 dark:text-white',
        ].join(' ');

        const who = document.createElement('p');
        who.className = `text-xs font-semibold ${mine ? 'text-white/85' : 'text-zinc-600 dark:text-zinc-200'}`;
        const stamp = timeLabel(message.at);
        const label = mine ? 'You' : `${CAR_NAME[message.car] ?? 'Other'} car`;
        who.textContent = message.pending ? `${label} · sending…` : stamp ? `${label} · ${stamp}` : label;

        const body = document.createElement('p');
        body.className = 'text-base wrap-anywhere';
        body.textContent = message.body;

        bubble.append(who, body);
        row.appendChild(bubble);
        log.appendChild(row);
    }

    log.scrollTop = log.scrollHeight;
    renderUnread();
}

function renderUnread() {
    const unread = state.messages.filter(
        (message, index) => index >= state.readCount && message.car !== state.car,
    ).length;

    el.unread.hidden = unread === 0;
    el.unread.textContent = String(unread);
}

function sendMessage(text) {
    const body = text.trim().slice(0, 240);

    if (body === '' || !state.car) {
        return;
    }

    const message = { uid: uuid(), car: state.car, body, at: new Date().toISOString(), pending: true };

    state.messages.push(message);
    state.outboxMessages.push({ uid: message.uid, car: message.car, body: message.body });
    state.readCount = state.messages.length;

    renderChat();
    save();
    nudgeSync();
}

/* ------------------------------------------------------------------ dialogs */

function setBackgroundInert(inert) {
    for (const region of el.inertRegions) {
        region.inert = inert;
    }

    document.body.classList.toggle('overflow-hidden', inert);
}

function openDialog(dialog, focusTarget) {
    closeDialogs({ silent: true });
    dialog.hidden = false;
    setBackgroundInert(true);
    focusTarget?.focus();
}

function closeDialogs({ silent = false } = {}) {
    const wasChatOpen = !el.chat.hidden;

    el.chat.hidden = true;
    el.help.hidden = true;
    el.carPicker.hidden = true;
    setBackgroundInert(false);

    if (wasChatOpen) {
        state.readCount = state.messages.length;
        renderUnread();
        save();
    }

    if (!silent) {
        el.lastTrigger?.focus();
    }
}

function openChat() {
    el.lastTrigger = el.openChat;
    state.readCount = state.messages.length;
    renderChat();
    save();
    openDialog(el.chat, el.chatInput);
}

function openHelp() {
    el.lastTrigger = el.openHelp;
    openDialog(el.help, el.closeHelp);
}

function openPicker() {
    el.lastTrigger = el.carChip;
    el.dismissPicker.hidden = !state.car;
    el.pickerPrompt.textContent = state.car ? 'Swap to the other car?' : 'Which car are you in?';

    for (const [car, marker] of Object.entries(el.currentCarMarkers)) {
        const isCurrent = state.car === Number(car);
        marker.hidden = !isCurrent;
        marker.closest('[data-choose-car]').setAttribute('aria-pressed', isCurrent ? 'true' : 'false');
    }

    openDialog(el.carPicker, el.carButtons[state.car === 2 ? 1 : 0]);
}

/* ------------------------------------------------------------------ actions */

function ownSplodgesOn(code) {
    return state.sightings.filter((sighting) => sighting.code === code && sighting.car === state.car);
}

function addSplodge(code) {
    if (!state.car) {
        return;
    }

    const plate = plates.get(code);
    const own = ownSplodgesOn(code);

    if (own.length >= MAX_SPLATS_PER_CAR) {
        pop(plate);
        toast(`${code} is fully painted. Press and hold to wipe it.`);

        return;
    }

    const [slotX, slotY] = SPLAT_SLOTS[state.car][own.length];

    const sighting = {
        uid: uuid(),
        code,
        car: state.car,
        variant: randomInt(0, SPLAT_VARIANTS - 1),
        x: clamp(slotX + randomInt(-4, 4), 0, 100),
        y: clamp(slotY + randomInt(-4, 4), 0, 100),
        rotation: randomInt(-180, 180),
        scale: randomInt(90, 112),
    };

    state.sightings.push(sighting);
    state.outboxAdd.push(sighting);

    pop(plate);
    renderBoard();
    save();
    nudgeSync();
}

function pop(plate) {
    if (!plate) {
        return;
    }

    plate.button.classList.remove('plate--pop');
    void plate.button.offsetWidth;
    plate.button.classList.add('plate--pop');
}

function flash(plate) {
    if (!plate) {
        return;
    }

    plate.button.classList.remove('plate--flash');
    void plate.button.offsetWidth;
    plate.button.classList.add('plate--flash');
}

function dropSightings(doomed) {
    if (doomed.length === 0) {
        return;
    }

    const doomedUids = new Set(doomed.map((sighting) => sighting.uid));

    state.sightings = state.sightings.filter((sighting) => !doomedUids.has(sighting.uid));

    for (const uid of doomedUids) {
        const queued = state.outboxAdd.findIndex((sighting) => sighting.uid === uid);

        if (queued === -1) {
            state.outboxRemove.push(uid);
        } else {
            state.outboxAdd.splice(queued, 1);
        }
    }

    renderBoard();
    save();
    nudgeSync();
}

function clearPlate(code) {
    if (!state.car) {
        return;
    }

    const own = ownSplodgesOn(code);

    if (own.length === 0) {
        return;
    }

    dropSightings(own);
    flash(plates.get(code));
    toast(`Wiped your paint off ${code}.`);
}

function undo() {
    if (!state.car) {
        return;
    }

    let last = null;

    for (let i = state.sightings.length - 1; i >= 0; i -= 1) {
        if (state.sightings[i].car === state.car) {
            last = state.sightings[i];
            break;
        }
    }

    if (!last) {
        return;
    }

    const { code } = last;

    dropSightings([last]);

    const plate = plates.get(code);

    if (plate && isVisible(plate.button)) {
        plate.button.scrollIntoView({ block: 'center', behavior: 'smooth' });
        flash(plate);
    }

    toast(`Removed your last splat from ${code}.`);
}

function chooseCar(car, { announce = false } = {}) {
    const switching = state.car !== null && state.car !== car;

    state.car = car;
    state.readCount = state.messages.length;

    el.carChip.className = `${el.carChip.dataset.baseClass} ${CAR_CLASS[car]}`;
    el.carChipLabel.textContent = CAR_NAME[car];
    el.carChip.setAttribute('aria-label', `You are the ${CAR_NAME[car]} car. Tap to change.`);
    el.chatSend.className = `${el.chatSend.dataset.baseClass} ${CAR_CLASS[car]}`;

    renderBoard();
    renderChat();
    save();

    if (announce && switching) {
        toast(`You are now the ${CAR_NAME[car]} car.`);
    }
}

/* --------------------------------------------------------------------- sync */

function setOnline(value) {
    online = value;

    const waiting = state.outboxAdd.length + state.outboxRemove.length + state.outboxMessages.length > 0;

    el.offline.hidden = value;
    el.offlineText.textContent = waiting ? "Offline — we'll send your spots later" : 'Offline — reconnecting';
}

function mergeServerState(data) {
    if (Array.isArray(data.sightings)) {
        const dropped = new Set(state.outboxRemove);
        const confirmed = data.sightings.filter((sighting) => !dropped.has(sighting.uid));
        const known = new Set(confirmed.map((sighting) => sighting.uid));
        const stillQueued = state.outboxAdd.filter((sighting) => !known.has(sighting.uid));

        state.sightings = [...confirmed, ...stillQueued];
        state.revision = typeof data.revision === 'string' ? data.revision : state.revision;
    }

    if (typeof data.messages_cursor === 'number') {
        state.messagesCursor = data.messages_cursor;
    }

    if (!Array.isArray(data.messages) || data.messages.length === 0) {
        return false;
    }

    const existing = new Map(state.messages.map((message) => [message.uid, message]));
    let added = false;

    for (const message of data.messages) {
        const local = existing.get(message.uid);

        if (local) {
            delete local.pending;
            local.at = message.at;
        } else {
            state.messages.push({ ...message });
            existing.set(message.uid, message);
            added = true;
        }
    }

    state.messages.sort((a, b) => String(a.at).localeCompare(String(b.at)));

    return added;
}

async function sync() {
    if (syncing) {
        return;
    }

    syncing = true;

    const sentAdd = state.outboxAdd.length;
    const sentRemove = state.outboxRemove.length;
    const sentMessages = state.outboxMessages.length;

    try {
        const response = await fetch('/api/sync', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
            body: JSON.stringify({
                revision: state.revision,
                messages_since: state.messagesCursor,
                add: state.outboxAdd.slice(0, sentAdd),
                remove: state.outboxRemove.slice(0, sentRemove),
                messages: state.outboxMessages.slice(0, sentMessages),
            }),
        });

        if (!response.ok) {
            throw new Error(`sync failed with ${response.status}`);
        }

        const data = await response.json();

        state.outboxAdd.splice(0, sentAdd);
        state.outboxRemove.splice(0, sentRemove);
        state.outboxMessages.splice(0, sentMessages);

        if (!data.unchanged) {
            const chatChanged = mergeServerState(data);

            renderBoard();

            if (chatChanged || sentMessages > 0) {
                if (el.chat.hidden) {
                    renderUnread();
                } else {
                    state.readCount = state.messages.length;
                    renderChat();
                }
            }
        }

        setOnline(true);
        save();
    } catch {
        setOnline(false);
    } finally {
        syncing = false;
    }
}

function nudgeSync() {
    if (nudgeTimer) {
        clearTimeout(nudgeTimer);
    }

    nudgeTimer = setTimeout(() => {
        nudgeTimer = null;
        sync();
    }, NUDGE_DELAY);
}

/* --------------------------------------------------------------------- boot */

function measureHeader() {
    root.style.setProperty('--header-height', `${el.header.offsetHeight}px`);
}

function collect() {
    root = document.querySelector('[data-game]');

    if (!root) {
        return false;
    }

    const carChip = root.querySelector('[data-car-chip]');
    carChip.dataset.baseClass = carChip.className;

    const chatSend = root.querySelector('[data-chat-send]');
    chatSend.dataset.baseClass = chatSend.className;

    const sections = [...root.querySelectorAll('[data-section]')].map((element) => ({
        element,
        plates: [...element.querySelectorAll('[data-plate]')],
    }));

    el = {
        header: root.querySelector('[data-header]'),
        foundCount: root.querySelector('[data-found-count]'),
        blueCount: root.querySelector('[data-blue-count]'),
        redCount: root.querySelector('[data-red-count]'),
        progressBar: root.querySelector('[data-progress-bar]'),
        undo: root.querySelector('[data-undo]'),
        carChip,
        carChipLabel: root.querySelector('[data-car-chip-label]'),
        carPicker: root.querySelector('[data-car-picker]'),
        pickerPrompt: root.querySelector('[data-picker-prompt]'),
        dismissPicker: root.querySelector('[data-dismiss-picker]'),
        carButtons: [...root.querySelectorAll('[data-choose-car]')],
        currentCarMarkers: {
            1: root.querySelector('[data-current-car="1"]'),
            2: root.querySelector('[data-current-car="2"]'),
        },
        cheer: root.querySelector('[data-cheer]'),
        emptyState: root.querySelector('[data-empty-state]'),
        offline: root.querySelector('[data-offline]'),
        offlineText: root.querySelector('[data-offline-text]'),
        toast: root.querySelector('[data-toast]'),
        toastText: root.querySelector('[data-toast-text]'),
        openChat: root.querySelector('[data-open-chat]'),
        openHelp: root.querySelector('[data-open-help]'),
        closeHelp: root.querySelector('[data-close-help]'),
        help: root.querySelector('[data-help]'),
        chat: root.querySelector('[data-chat]'),
        chatLog: root.querySelector('[data-chat-log]'),
        chatForm: root.querySelector('[data-chat-form]'),
        chatInput: root.querySelector('[data-chat-input]'),
        chatSend,
        unread: root.querySelector('[data-unread]'),
        inertRegions: [root.querySelector('header'), root.querySelector('main'), root.querySelector('footer')].filter(
            Boolean,
        ),
        lastTrigger: null,
        filterButtons: [...root.querySelectorAll('[data-filter-button]')],
        filterCounts: {
            all: root.querySelector('[data-filter-count="all"]'),
            mine: root.querySelector('[data-filter-count="mine"]'),
            ours: root.querySelector('[data-filter-count="ours"]'),
            todo: root.querySelector('[data-filter-count="todo"]'),
        },
        sections,
        groups: [...root.querySelectorAll('[data-group]')].map((element) => ({
            element,
            sections: [...element.querySelectorAll('[data-section]')],
        })),
        groupNotes: [...root.querySelectorAll('[data-group-note]')],
    };

    for (const button of root.querySelectorAll('[data-plate]')) {
        plates.set(button.dataset.code, {
            button,
            splats: button.querySelector('[data-splats]'),
            both: button.querySelector('[data-both]'),
            status: button.querySelector('[data-plate-status]'),
            name: button.dataset.name,
        });
    }

    return true;
}

function listenForLongPress() {
    let timer = null;
    let startX = 0;
    let startY = 0;
    let target = null;
    let fired = false;

    const cancel = () => {
        if (timer) {
            clearTimeout(timer);
            timer = null;
        }

        target = null;
    };

    root.addEventListener(
        'pointerdown',
        (event) => {
            const plate = event.target.closest('[data-plate]');

            if (!plate || event.button !== 0) {
                return;
            }

            target = plate;
            startX = event.clientX;
            startY = event.clientY;
            fired = false;

            timer = setTimeout(() => {
                timer = null;
                fired = true;
                clearPlate(plate.dataset.code);
            }, LONG_PRESS_MS);
        },
        { passive: true },
    );

    root.addEventListener(
        'pointermove',
        (event) => {
            if (!target) {
                return;
            }

            if (Math.abs(event.clientX - startX) > LONG_PRESS_SLOP || Math.abs(event.clientY - startY) > LONG_PRESS_SLOP) {
                cancel();
            }
        },
        { passive: true },
    );

    root.addEventListener('pointerup', cancel, { passive: true });
    root.addEventListener('pointercancel', cancel, { passive: true });

    return () => fired;
}

function listen() {
    const longPressFired = listenForLongPress();

    root.addEventListener('click', (event) => {
        const plate = event.target.closest('[data-plate]');

        if (plate) {
            if (!longPressFired()) {
                addSplodge(plate.dataset.code);
            }

            return;
        }

        const filter = event.target.closest('[data-filter-button]');

        if (filter) {
            state.filter = filter.dataset.filterButton;
            applyFilter();
            save();

            return;
        }

        const chooser = event.target.closest('[data-choose-car]');

        if (chooser) {
            chooseCar(Number(chooser.dataset.chooseCar), { announce: true });
            closeDialogs({ silent: true });
            el.carChip.focus();

            return;
        }

        if (event.target.closest('[data-undo]')) {
            undo();

            return;
        }

        if (event.target.closest('[data-open-chat]')) {
            openChat();

            return;
        }

        if (event.target.closest('[data-open-help]')) {
            openHelp();

            return;
        }

        if (event.target.closest('[data-car-chip]')) {
            openPicker();

            return;
        }

        if (event.target.closest('[data-dismiss-cheer]')) {
            state.cheerDismissed = true;
            el.cheer.hidden = true;
            save();

            return;
        }

        if (
            event.target.closest('[data-close-chat]') ||
            event.target.closest('[data-chat-backdrop]') ||
            event.target.closest('[data-close-help]') ||
            event.target.closest('[data-help-backdrop]') ||
            event.target.closest('[data-dismiss-picker]')
        ) {
            closeDialogs();
        }
    });

    el.chatForm.addEventListener('submit', (event) => {
        event.preventDefault();
        sendMessage(el.chatInput.value);
        el.chatInput.value = '';
    });

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') {
            return;
        }

        if (!el.chat.hidden || !el.help.hidden || (!el.carPicker.hidden && state.car)) {
            closeDialogs();
        }
    });

    window.addEventListener('online', () => {
        setOnline(true);
        sync();
    });

    window.addEventListener('offline', () => setOnline(false));

    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') {
            sync();
        }
    });

    window.addEventListener('resize', measureHeader);
    window.addEventListener('pagehide', writeNow);
}

export function startGame() {
    if (!collect()) {
        return;
    }

    restore();
    listen();
    measureHeader();

    if (state.car) {
        chooseCar(state.car);
    } else {
        renderBoard();
        renderChat();
        openPicker();
    }

    setOnline(navigator.onLine !== false);
    sync();
    setInterval(sync, POLL_INTERVAL);
}
