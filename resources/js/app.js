import { startGame } from './game.js';

startGame();

if (import.meta.env.PROD && 'serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch(() => {
            /* Without a service worker the game still works, it just isn't installable. */
        });
    });
}
