@if ($premium)
    <p class="text-sm text-gray-700 leading-relaxed">{{ __('site.affiliate_portal.matokeo_info_body_premium') }}</p>
@else
    <div class="space-y-2 text-sm text-gray-700 leading-relaxed">
        @if (! empty($kpiCard))
            <p>{{ __('site.affiliate_portal.info_target', [
                'achieved' => rtrim(rtrim(number_format($kpiCard['achieved'], 1, '.', ''), '0'), '.'),
                'target' => rtrim(rtrim(number_format($kpiCard['target'], 1, '.', ''), '0'), '.'),
                'percent' => $kpiCard['percent'],
            ]) }}</p>
        @endif
        <p>{{ $assessmentExplanation }}</p>
        <p>{{ __('site.affiliate_portal.info_percent_rule') }}</p>
        <p>{{ __('site.affiliate_portal.info_commission') }}</p>
        @foreach ($warningLadder as $step)
            <p>
                <span class="font-semibold">{{ __('site.affiliate_portal.miss_step', ['n' => $step['periods']]) }}</span>
                → {{ $step['label'] }}
            </p>
        @endforeach
        <p>{{ $recovery }}</p>
    </div>
@endif
