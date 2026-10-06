import './bootstrap';

import Alpine from 'alpinejs';
import { initPickers } from './pickers';

window.Alpine = Alpine;

/*
 * Live notifications for users/traders (DECISIONS D-73). The layout renders #notify-root with the poll URL,
 * the current unread count and the newest notification id. Every 30 s (and when the tab becomes visible again)
 * we ask for anything newer, update the badge and show a toast that links to the category tab.
 */
Alpine.store('notify', { unread: 0, toasts: [] });

function startNotificationPolling() {
    const root = document.getElementById('notify-root');
    if (!root) return;

    const store = Alpine.store('notify');
    store.unread = Number(root.dataset.unread || 0);
    let lastId = Number(root.dataset.lastId || 0);
    let busy = false;
    let tick = 0;
    const pending = [];
    const baseTitle = document.title.replace(/^\(\d+\)\s*/, '');
    const showCount = () => { document.title = store.unread > 0 ? `(${store.unread}) ${baseTitle}` : baseTitle; };
    showCount();

    const showToast = (item) => {
        store.toasts.push(item);
        setTimeout(() => {
            store.toasts = store.toasts.filter((t) => t.id !== item.id);
        }, 9000);
    };

    // Visible tab: every 30 s. Background tab: every 2 min (keeps the tab title count fresh, saves server load).
    const poll = async (force = false) => {
        tick++;
        if (busy || (!force && document.hidden && tick % 4 !== 0)) return;
        busy = true;
        try {
            const res = await fetch(`${root.dataset.pollUrl}?after=${lastId}`, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            if (res.status === 401 || res.status === 419 || res.redirected) {
                window.location.reload(); // session ended (e.g. logged in elsewhere) → server shows the reason
                return;
            }
            if (!res.ok) return;
            const data = await res.json();
            store.unread = data.unread_count;
            showCount();
            if (data.last_id > lastId) {
                lastId = data.last_id;
                // Hidden tab: keep them and show the toasts when the user comes back.
                data.items.forEach((item) => (document.hidden ? pending.push(item) : showToast(item)));
            }
        } catch (e) {
            // offline or server busy — try again next round
        } finally {
            busy = false;
        }
    };

    setInterval(() => poll(), 30000);
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) return;
        pending.splice(0).forEach(showToast);
        poll(true);
    });
}

document.addEventListener('alpine:init', () => {
    startNotificationPolling();
});

Alpine.start();
initPickers(document);
