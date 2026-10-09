import './bootstrap';

import Alpine from 'alpinejs';
import { initPickers } from './pickers';

window.Alpine = Alpine;

/*
 * Live notifications for users/traders (DECISIONS D-73). The layout renders #notify-root with the poll URL,
 * the current unread count and the newest notification id. Every N seconds (setting, default 10; and when the tab becomes visible again)
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

    /*
     * Adaptive live check (D-96) — built for 1 lakh users on shared hosting:
     * starts at the base interval (Settings → "Live check every (seconds)"), doubles while nothing changes
     * (max 120 s; hidden tabs at least 120 s), resets on a new notification, tab focus or user activity,
     * and adds ±20% random jitter so thousands of browsers never hit the server in the same second.
     */
    const base = Math.min(120, Math.max(10, Number(root.dataset.interval || 20))) * 1000;
    let delay = base;
    let timer = null;
    const schedule = () => {
        clearTimeout(timer);
        const wait = (document.hidden ? Math.max(delay, 120000) : delay) * (0.8 + Math.random() * 0.4);
        timer = setTimeout(async () => {
            const changed = await poll();
            delay = changed ? base : Math.min(delay * 2, 120000);
            schedule();
        }, wait);
    };
    const speedUp = () => { if (delay !== base) { delay = base; schedule(); } };

    const poll = async () => {
        if (busy) return false;
        busy = true;
        try {
            const res = await fetch(`${root.dataset.pollUrl}?after=${lastId}`, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            if (res.status === 401 || res.status === 419 || res.redirected) {
                window.location.reload(); // session ended (e.g. logged in elsewhere) → server shows the reason
                return true;
            }
            if (!res.ok) return false;
            const data = await res.json();
            const changed = data.unread_count !== store.unread || data.last_id > lastId;
            store.unread = data.unread_count;
            showCount();
            if (data.last_id > lastId) {
                lastId = data.last_id;
                // Hidden tab: keep them and show the toasts when the user comes back.
                data.items.forEach((item) => (document.hidden ? pending.push(item) : showToast(item)));
            }
            return changed;
        } catch (e) {
            return false; // offline or server busy — back off and try again later
        } finally {
            busy = false;
        }
    };

    schedule();
    ['click', 'keydown', 'touchstart'].forEach((ev) => document.addEventListener(ev, speedUp, { passive: true }));
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) return;
        pending.splice(0).forEach(showToast);
        delay = base;
        poll().finally(schedule); // back on the tab: check now, then fast again
    });
}

document.addEventListener('alpine:init', () => {
    startNotificationPolling();
});

Alpine.start();
initPickers(document);
