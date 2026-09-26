const STORAGE_KEY = 'kf-account-theme';
const COOKIE = 'kf_account_theme';

function normalize(value) {
    return value === 'dark' || value === 'light' ? value : null;
}

export function currentTheme() {
    return document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
}

export function applyTheme(theme) {
    const next = normalize(theme) || 'light';
    document.documentElement.setAttribute('data-theme', next);
    document.documentElement.style.colorScheme = next;
    try {
        localStorage.setItem(STORAGE_KEY, next);
    } catch (e) {
        // Private mode may block storage.
    }
    document.cookie = `${COOKIE}=${next};path=/;max-age=${60 * 60 * 24 * 365};samesite=lax`;
}

function persistServer(theme) {
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    const url = document.querySelector('meta[name="kf-theme-url"]')?.getAttribute('content');
    if (!url || !token) {
        return;
    }

    fetch(url, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': token,
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({ theme }),
        credentials: 'same-origin',
    }).catch(() => {});
}

export function toggleAccountTheme(event) {
    const next = currentTheme() === 'dark' ? 'light' : 'dark';
    const x = event?.clientX ?? window.innerWidth / 2;
    const y = event?.clientY ?? window.innerHeight / 2;
    const end = Math.hypot(
        Math.max(x, window.innerWidth - x),
        Math.max(y, window.innerHeight - y),
    );

    const commit = () => {
        applyTheme(next);
        persistServer(next);
        document.dispatchEvent(new CustomEvent('kf-theme-changed', { detail: { theme: next } }));
    };

    if (typeof document.startViewTransition !== 'function') {
        commit();
        return;
    }

    const transition = document.startViewTransition(commit);
    transition.ready.then(() => {
        document.documentElement.animate(
            {
                clipPath: [
                    `circle(0px at ${x}px ${y}px)`,
                    `circle(${end}px at ${x}px ${y}px)`,
                ],
            },
            {
                duration: 520,
                easing: 'cubic-bezier(0.4, 0, 0.2, 1)',
                pseudoElement: '::view-transition-new(root)',
            },
        );
    }).catch(() => {});
}

export function bindAccountTheme() {
    if (typeof window === 'undefined' || window.__kfAccountThemeBound) {
        return;
    }
    window.__kfAccountThemeBound = true;
    window.kfToggleAccountTheme = toggleAccountTheme;
    document.addEventListener('click', (event) => {
        const button = event.target.closest('[data-kf-theme-toggle]');
        if (!button) {
            return;
        }
        event.preventDefault();
        toggleAccountTheme(event);
    });
}
