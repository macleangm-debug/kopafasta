@php
    $release = app(\App\Services\ReleaseInfoService::class);
@endphp
@if ($release->showsBanner())
    <div class="kf-env-banner bg-amber-500 text-gray-900 text-center text-[11px] sm:text-xs font-bold tracking-wide py-1.5 px-3" data-environment="{{ $release->environment() }}">
        {{ $release->label() }}
        @if ($release->commit())
            <span class="font-mono font-semibold tracking-normal opacity-80">· {{ substr($release->commit(), 0, 8) }}</span>
        @endif
        <span class="font-medium tracking-normal">— test environment, not live customers</span>
    </div>
@endif
