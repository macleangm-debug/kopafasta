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
        default => null,
    };
@endphp

@if ($shouldCelebrate)
    @if ($message)
        <div class="fixed top-4 left-1/2 -translate-x-1/2 z-[10000] max-w-sm w-[calc(100%-2rem)] pointer-events-none">
            <div class="glass-card px-4 py-3 text-center text-sm font-semibold text-brand shadow-lg animate-[fadeIn_0.4s_ease-out]">
                {{ $message }}
            </div>
        </div>
    @endif
    <script>
        (function () {
            const colors = ['#f5c842', '#10b981', '#004d40', '#3b82f6', '#ef4444', '#a855f7'];
            const count = 140;
            for (let i = 0; i < count; i++) {
                const piece = document.createElement('div');
                const left = Math.random() * 100;
                const delay = Math.random() * 400;
                const duration = 2200 + Math.random() * 1200;
                piece.style.cssText = [
                    'position:fixed',
                    'top:-12px',
                    'left:' + left + 'vw',
                    'width:' + (6 + Math.random() * 6) + 'px',
                    'height:' + (10 + Math.random() * 8) + 'px',
                    'background:' + colors[i % colors.length],
                    'opacity:0.95',
                    'z-index:9999',
                    'border-radius:2px',
                    'pointer-events:none',
                    'transform:rotate(' + (Math.random() * 360) + 'deg)',
                    'transition:transform ' + duration + 'ms ease-out, top ' + duration + 'ms ease-out, opacity ' + duration + 'ms ease-out',
                ].join(';');
                document.body.appendChild(piece);
                setTimeout(function () {
                    piece.style.top = (75 + Math.random() * 20) + 'vh';
                    piece.style.transform = 'translateX(' + ((Math.random() - 0.5) * 240) + 'px) rotate(' + (Math.random() * 720) + 'deg)';
                    piece.style.opacity = '0';
                }, delay);
                setTimeout(function () { piece.remove(); }, delay + duration + 100);
            }
        })();
    </script>
@endif
