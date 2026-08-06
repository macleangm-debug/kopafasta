@php
    $reasons = \App\Support\Celebration::reasons();
    $legacy = session('confetti');
    $shouldCelebrate = $legacy || $reasons !== [];
    $pointsEarned = (int) session('celebration_points', 0);
    $remainingSections = session('celebration_remaining', []);
    $remainingSections = is_array($remainingSections) ? array_values($remainingSections) : [];
    $streakPayload = session('celebration_streak', []);
    $streakPayload = is_array($streakPayload) ? $streakPayload : [];
    $isPointsProgress = in_array('points_earned', $reasons, true)
        && ! in_array('profile_complete', $reasons, true)
        && $pointsEarned > 0;
    $isStreakMilestone = in_array('streak_milestone', $reasons, true);
    $isRepaymentOnTime = in_array('repayment_on_time', $reasons, true);
    // Streak payload survives even if celebrate reasons were overwritten by payment flash.
    if ($streakPayload !== [] && ! $isStreakMilestone && ! $isRepaymentOnTime) {
        if ((int) ($streakPayload['milestone_points'] ?? 0) > 0) {
            $isStreakMilestone = true;
        } else {
            $isRepaymentOnTime = true;
        }
    }
    $message = match (true) {
        $isStreakMilestone => __('borrower.celebration.streak_milestone', [
            'count' => (int) ($streakPayload['milestone_count'] ?? $streakPayload['count'] ?? 0),
            'points' => number_format((int) ($streakPayload['milestone_points'] ?? 0)),
        ]),
        $isRepaymentOnTime && empty($streakPayload['next_count']) => __('borrower.celebration.repayment_on_time_done', [
            'count' => (int) ($streakPayload['count'] ?? 0),
        ]),
        $isRepaymentOnTime => __('borrower.celebration.repayment_on_time', [
            'count' => (int) ($streakPayload['count'] ?? 0),
            'remaining' => (int) ($streakPayload['remaining'] ?? 0),
            'points' => number_format((int) ($streakPayload['next_points'] ?? 0)),
        ]),
        in_array('profile_complete', $reasons, true) => __('borrower.celebration.profile_complete'),
        in_array('loan_submitted', $reasons, true) => __('borrower.celebration.loan_submitted'),
        in_array('registration', $reasons, true) => __('borrower.celebration.registration'),
        in_array('application_fee', $reasons, true) => __('borrower.celebration.application_fee'),
        in_array('post_approval_fee', $reasons, true) => __('borrower.celebration.post_approval_fee'),
        in_array('payment', $reasons, true) => __('borrower.celebration.payment'),
        in_array('membership', $reasons, true) => __('borrower.celebration.membership'),
        in_array('reward_redeemed', $reasons, true) => __('borrower.celebration.reward_redeemed'),
        $isPointsProgress => __('borrower.celebration.points_earned_section', [
            'points' => number_format($pointsEarned),
            'count' => count($remainingSections),
        ]),
        in_array('points_earned', $reasons, true) => __('borrower.celebration.points_earned'),
        default => null,
    };
    $modalTitle = match (true) {
        $isStreakMilestone => __('borrower.celebration.streak_milestone_title'),
        $isRepaymentOnTime => __('borrower.celebration.repayment_on_time_title'),
        in_array('profile_complete', $reasons, true) => __('borrower.celebration.profile_complete_title'),
        $isPointsProgress => __('borrower.celebration.points_earned_title', ['points' => number_format($pointsEarned)]),
        in_array('loan_submitted', $reasons, true) => __('borrower.apply.success.submitted_title'),
        in_array('membership', $reasons, true) => __('borrower.celebration.membership_title'),
        in_array('application_fee', $reasons, true) => __('borrower.celebration.application_fee_title'),
        in_array('post_approval_fee', $reasons, true) => __('borrower.celebration.post_approval_fee_title'),
        in_array('payment', $reasons, true) => __('borrower.celebration.payment_title'),
        in_array('registration', $reasons, true) => __('borrower.celebration.registration_title'),
        default => __('borrower.celebration.default_title'),
    };
    $statusFlash = session('status');
    $modalMessage = (is_string($statusFlash) && $statusFlash !== '' && ! $isPointsProgress && ! $isRepaymentOnTime && ! $isStreakMilestone)
        ? $statusFlash
        : ($message ?? __('borrower.celebration.payment'));
    if ($isPointsProgress && $remainingSections !== []) {
        $modalMessage .= ' '.__('borrower.celebration.points_earned_keep_going', [
            'sections' => implode(', ', array_slice($remainingSections, 0, 3)),
        ]);
    }
    $forceStreakModal = $isRepaymentOnTime || $isStreakMilestone;
    $useModal = $shouldCelebrate
        && filled($message)
        && (
            $forceStreakModal
            || (
                ! in_array('membership', $reasons, true)
                && ! in_array('payment', $reasons, true)
            )
        );
    $confettiCount = ($isPointsProgress || $isRepaymentOnTime) ? 56 : 160;
    $okLabel = match (true) {
        $forceStreakModal => __('borrower.celebration.cta_streak'),
        in_array('profile_complete', $reasons, true) => __('borrower.celebration.cta_apply'),
        in_array('reward_redeemed', $reasons, true) => __('borrower.celebration.cta_rewards'),
        $isPointsProgress => __('borrower.celebration.cta_keep_going'),
        default => __('borrower.celebration.cta_continue'),
    };
@endphp

@if ($shouldCelebrate)
    <script>
        (function () {
            @if ($useModal)
            document.addEventListener('alpine:initialized', function () {
                window.dispatchEvent(new CustomEvent('open-feedback-default', {
                    detail: {
                        tone: 'success',
                        title: @js($modalTitle),
                        message: @js($modalMessage),
                        okLabel: @js($okLabel),
                    },
                }));
            });
            @endif

            if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                return;
            }

            // Confetti must sit above feedback modal (z-10050) and its green blur.
            var confettiLayer = document.createElement('div');
            confettiLayer.setAttribute('aria-hidden', 'true');
            confettiLayer.style.cssText = 'position:fixed;inset:0;z-index:10120;pointer-events:none;overflow:visible;';
            document.body.appendChild(confettiLayer);

            var colors = ['#f5c842', '#10b981', '#004d40', '#0d9488', '#fbbf24', '#34d399', '#ffffff'];
            var count = {{ (int) $confettiCount }};
            var originX = window.innerWidth / 2;
            var originY = Math.min(220, window.innerHeight * 0.28);

            for (var i = 0; i < count; i++) {
                var piece = document.createElement('div');
                var angle = (Math.random() * Math.PI * 2);
                var velocity = 8 + Math.random() * 18;
                var driftX = Math.cos(angle) * velocity * (14 + Math.random() * 18);
                var driftY = Math.sin(angle) * velocity * (6 + Math.random() * 10) - (40 + Math.random() * 80);
                var delay = Math.random() * 280;
                var duration = 2600 + Math.random() * 1400;
                var size = 5 + Math.random() * 7;
                var isRound = Math.random() > 0.55;

                piece.style.cssText = [
                    'position:absolute',
                    'top:' + originY + 'px',
                    'left:' + originX + 'px',
                    'width:' + size + 'px',
                    'height:' + (isRound ? size : (size * (1.2 + Math.random()))) + 'px',
                    'background:' + colors[i % colors.length],
                    'opacity:1',
                    'border-radius:' + (isRound ? '999px' : '2px'),
                    'pointer-events:none',
                    'will-change:transform,opacity',
                    'transform:translate(-50%,-50%) rotate(' + (Math.random() * 360) + 'deg)',
                    'transition:transform ' + duration + 'ms cubic-bezier(0.15,0.75,0.25,1), opacity ' + duration + 'ms ease-out',
                ].join(';');

                confettiLayer.appendChild(piece);

                (function (el, dx, dy, dly, dur) {
                    setTimeout(function () {
                        el.style.transform = 'translate(calc(-50% + ' + dx + 'px), calc(-50% + ' + (dy + window.innerHeight * 0.55) + 'px)) rotate(' + (Math.random() * 720) + 'deg)';
                        el.style.opacity = '0';
                    }, dly);
                    setTimeout(function () { el.remove(); }, dly + dur + 80);
                })(piece, driftX, driftY, delay, duration);
            }

            setTimeout(function () { confettiLayer.remove(); }, 5200);
        })();
    </script>
@endif
