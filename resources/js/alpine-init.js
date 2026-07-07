import Alpine from 'alpinejs';
import collapse from '@alpinejs/collapse';

Alpine.plugin(collapse);
window.Alpine = Alpine;

function startAlpine() {
    if (window.__alpineStarted) {
        return;
    }

    window.__alpineStarted = true;
    Alpine.start();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', startAlpine);
} else {
    startAlpine();
}
