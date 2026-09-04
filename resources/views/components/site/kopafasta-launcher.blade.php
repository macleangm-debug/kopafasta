@if (app(\App\Services\KopafastaLaunchService::class)->consume())
    <div class="kf-launcher" data-kf-launcher role="status" aria-live="polite" aria-label="{{ brand_name() }}">
        <div class="kf-launcher-lockup">
            <span class="kf-launcher-chevrons" aria-hidden="true">
                <span class="kf-launcher-chevron">›</span>
                <span class="kf-launcher-chevron">›</span>
                <span class="kf-launcher-chevron">›</span>
                <span class="kf-launcher-glow"></span>
            </span>
            <span class="kf-launcher-word">{{ brand_name() }}</span>
        </div>
    </div>
    <script>
        (function () {
            var root = document.querySelector('[data-kf-launcher]');
            if (! root) return;
            var reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            var min = reduced ? 180 : {{ (int) \App\Services\KopafastaLaunchService::MIN_DURATION_MS }};
            if (reduced) root.classList.add('kf-launcher-reduced');
            window.setTimeout(function () {
                root.classList.add('kf-launcher-out');
                window.setTimeout(function () {
                    if (root && root.parentNode) root.parentNode.removeChild(root);
                }, reduced ? 160 : 380);
            }, min);
        })();
    </script>
@endif
