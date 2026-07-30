/**
 * Keeps the game usable with no signal.
 *
 * The document and the built assets are cached so a cold start works offline;
 * /api/ is never cached, because stale game state would be worse than none —
 * the page falls back to its own localStorage copy and syncs when it can.
 */

const VERSION = 'fnp-v1';
const SHELL_CACHE = `shell-${VERSION}`;
const ASSET_CACHE = `assets-${VERSION}`;

const SHELL_URLS = [
    '/',
    '/manifest.json',
    '/favicon.svg',
    '/favicon-32.png',
    '/apple-touch-icon.png',
    '/icons/icon-192.png',
    '/icons/icon-512.png',
    '/icons/maskable-512.png',
];

async function precacheBuiltAssets() {
    try {
        const response = await fetch('/build/manifest.json', { cache: 'no-cache' });

        if (!response.ok) {
            return;
        }

        const manifest = await response.json();
        const urls = new Set();

        for (const entry of Object.values(manifest)) {
            if (entry && typeof entry.file === 'string') {
                urls.add(`/build/${entry.file}`);
            }

            for (const css of entry?.css ?? []) {
                urls.add(`/build/${css}`);
            }
        }

        if (urls.size === 0) {
            return;
        }

        const cache = await caches.open(ASSET_CACHE);
        const already = new Set((await cache.keys()).map((request) => new URL(request.url).pathname));
        const missing = [...urls].filter((url) => !already.has(url));

        await Promise.allSettled(missing.map((url) => cache.add(url)));
    } catch {
        /* A missing build manifest just means less is available offline. */
    }
}

self.addEventListener('install', (event) => {
    event.waitUntil(
        (async () => {
            const cache = await caches.open(SHELL_CACHE);
            await Promise.allSettled(SHELL_URLS.map((url) => cache.add(url)));
            await precacheBuiltAssets();
            await self.skipWaiting();
        })(),
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        (async () => {
            const keys = await caches.keys();

            await Promise.all(
                keys.filter((key) => !key.endsWith(VERSION)).map((key) => caches.delete(key)),
            );

            await self.clients.claim();
        })(),
    );
});

async function documentFirst(request) {
    const cache = await caches.open(SHELL_CACHE);

    try {
        const response = await fetch(request);

        if (response.ok) {
            cache.put('/', response.clone());

            // A fresh document may reference newly hashed assets after a deploy.
            precacheBuiltAssets();
        }

        return response;
    } catch {
        const cached = await cache.match('/');

        if (cached) {
            return cached;
        }

        return new Response('<h1>Offline</h1><p>Open the game once while online to make it work offline.</p>', {
            status: 503,
            headers: { 'Content-Type': 'text/html; charset=utf-8' },
        });
    }
}

async function assetFirst(request) {
    const cache = await caches.open(ASSET_CACHE);
    const cached = await cache.match(request);

    if (cached) {
        return cached;
    }

    const response = await fetch(request);

    if (response.ok && response.type === 'basic') {
        cache.put(request, response.clone());
    }

    return response;
}

self.addEventListener('fetch', (event) => {
    const { request } = event;

    if (request.method !== 'GET') {
        return;
    }

    const url = new URL(request.url);

    if (url.origin !== self.location.origin) {
        return;
    }

    if (url.pathname.startsWith('/api/') || url.pathname.startsWith('/_boost/')) {
        return;
    }

    if (request.mode === 'navigate') {
        event.respondWith(documentFirst(request));

        return;
    }

    event.respondWith(assetFirst(request));
});
