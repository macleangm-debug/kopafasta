@php
    $reasons = \App\Support\Celebration::reasons();
    $legacy = session('confetti');
    $shouldCelebrate = $legacy || $reasons !== [];
    $message = match (true) {
        in_array('profile_complete', $reasons, true) => __('borrower.celebration.profile_complete'),
        in_array('loan_submitted', $reasons, true) => __('borrower.celebration.loan_submitted'),
        in_array('registration', $reasons, true) => __('borrower.celebration.registration'),
        in_array('payment', $reasons, true) => __('borrower.celebration.payment'),
        in_array('membership', $reasons, true) => __('borrower.celebration.membership'),
        in_array('reward_redeemed', $reasons, true) => __('borrower.celebration.reward_redeemed'),
        in_array('points_earned', $reasons, true) => __('borrower.celebration.points_earned'),
        default => null,
    };
    $isLoanSubmitted = in_array('loan_submitted', $reasons, true);
@endphp

@if ($shouldCelebrate)
    @if ($message)
        <div class="fixed top-5 left-1/2 -translate-x-1/2 z-[10000] max-w-sm w-[calc(100%-2rem)] pointer-events-none"
             role="status" aria-live="polite">
            <div class="rounded-2xl bg-white/95 backdrop-blur px-5 py-3.5 text-center text-sm font-semibold text-brand shadow-lg ring-1 ring-brand/15 animate-[fadeIn_0.45s_ease-out]">
                {{ $message }}
            </div>
        </div>
    @endif
    <script>
        (function () {
            if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                return;
            }

            var colors = ['#f5c842', '#10b981', '#004d40', '#0d9488', '#fbbf24', '#34d399', '#ffffff'];
            var count = {{ $isLoanSubmitted ? 180 : 120 }};
            var originX = window.innerWidth / 2;
            var originY = Math.min(220, window.innerHeight * 0.28);

            for (var i = 0; i < count; i++) {
                var piece = document.createElement('div');
                var angle = (Math.random() * Math.PI * 2);
                var velocity = 8 + Math.random() * 18;
                var driftX = Math.cos(angle) * velocity * (14 + Math.random() * 18);
                var driftY = Math.sin(angle) * velocity * (6 + Math.random() * 10) - (40 + Math.random() * 80);
                var delay = Math.random() * 280;
                var duration = {{ $isLoanSubmitted ? 2800 : 2200 }} + Math.random() * 1400;
                var size = 5 + Math.random() * 7;
                var isRound = Math.random() > 0.55;

                piece.style.cssText = [
                    'position:fixed',
                    'top:' + originY + 'px',
                    'left:' + originX + 'px',
                    'width:' + size + 'px',
                    'height:' + (isRound ? size : (size * (1.2 + Math.random()))) + 'px',
                    'background:' + colors[i % colors.length],
                    'opacity:1',
                    'z-index:9999',
                    'border-radius:' + (isRound ? '999px' : '2px'),
                    'pointer-events:none',
                    'will-change:transform,opacity',
                    'transform:translate(-50%,-50%) rotate(' + (Math.random() * 360) + 'deg)',
                    'transition:transform ' + duration + 'ms cubic-bezier(0.15,0.75,0.25,1), opacity ' + duration + 'ms ease-out',
                ].join(';');

                document.body.appendChild(piece);

                (function (el, dx, dy, dly, dur) {
                    setTimeout(function () {
                        el.style.transform = 'translate(calc(-50% + ' + dx + 'px), calc(-50% + ' + (dy + window.innerHeight * 0.55) + 'px)) rotate(' + (Math.random() * 720) + 'deg)';
                        el.style.opacity = '0';
                    }, dly);
                    setTimeout(function () { el.remove(); }, dly + dur + 80);
                })(piece, driftX, driftY, delay, duration);
            }
        })();
    </script>
@endif
