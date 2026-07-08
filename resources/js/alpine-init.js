import Alpine from 'alpinejs';
import collapse from '@alpinejs/collapse';

Alpine.plugin(collapse);
window.Alpine = Alpine;

function initAlpineTrees() {
    document.querySelectorAll('[x-data]').forEach((el) => {
        if (el._x_dataStack) {
            return;
        }

        try {
            Alpine.initTree(el);
        } catch (error) {
            console.error('Alpine failed to initialize component.', el, error);
        }
    });
}

function startAlpine() {
    if (window.__alpineStarted) {
        return;
    }

    window.__alpineStarted = true;
    Alpine.start();
    initAlpineTrees();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', startAlpine);
} else {
    startAlpine();
}
