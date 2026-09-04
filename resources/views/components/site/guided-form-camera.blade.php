@props([
    'steps' => [],
    'dbName' => 'kf-guided',
    'facingMode' => 'environment',
    'orientation' => 'landscape',
    'guideFrame' => null,
    'required' => true,
    'compact' => false,
    'startLabel' => null,
    'retakeLabel' => null,
    'thumbClass' => 'aspect-[1.586]',
    'subjectName' => null,
])

@php
    $startLabel = $startLabel ?: __('borrower.document_upload.nida_start');
    $retakeLabel = $retakeLabel ?: __('site.partner_portal.valuation_retake');
    $normalized = collect($steps)->values()->map(function (array $step, int $i) {
        $path = $step['path'] ?? null;

        return [
            'asset_id' => (int) ($step['asset_id'] ?? 0),
            'angle' => (string) ($step['angle'] ?? $step['field'] ?? 'shot-'.$i),
            'label' => (string) ($step['label'] ?? ''),
            'headline' => (string) ($step['headline'] ?? $step['label'] ?? ''),
            'guidance' => (string) ($step['guidance'] ?? $step['guide'] ?? ''),
            'required' => (bool) ($step['required'] ?? true),
            'facingMode' => (string) ($step['facingMode'] ?? ''),
            'inputName' => (string) ($step['inputName'] ?? $step['field'] ?? 'photo_'.$i),
            'inputId' => $step['inputId'] ?? $step['id'] ?? null,
            'path' => $path,
            'path_url' => $step['path_url'] ?? (filled($path) ? asset('storage/'.$path) : null),
        ];
    })->all();
@endphp

<div x-data="valuationCamera(@js([
        'formMode' => true,
        'dbName' => $dbName,
        'facingMode' => $facingMode,
        'orientation' => $orientation,
        'guideFrame' => $guideFrame,
        'subjectName' => $subjectName,
        'subjectLine' => filled($subjectName)
            ? __('borrower.document_upload.requested_for', ['name' => $subjectName])
            : '',
        'cameraInsecure' => __('borrower.profile.camera_insecure'),
        'cameraDenied' => __('borrower.profile.camera_denied'),
        'steps' => $normalized,
    ]))"
     class="space-y-3">
    @foreach ($normalized as $step)
        <input type="file" name="{{ $step['inputName'] }}" @if (filled($step['inputId'] ?? null)) id="{{ $step['inputId'] }}" @endif accept="image/jpeg,image/png,image/webp,image/jpg"
               class="sr-only" data-guided-input="{{ $step['inputName'] }}"
               @if ($required && blank($step['path'])) required @endif>
    @endforeach
    <input type="hidden" value="" x-bind:value="requiredDone() >= requiredTotal() ? '1' : ''" @if ($required) required @endif aria-hidden="true" tabindex="-1" class="sr-only">

    <button type="button" data-kf-cam-start @click="start()"
            @unless ($compact) x-show="pendingRequired().length" @endunless
            class="{{ $compact ? 'sr-only' : 'w-full rounded-xl bg-brand-gold text-brand text-sm font-extrabold py-3 shadow-sm hover:bg-yellow-400' }}">
        {{ $startLabel }}
    </button>

    <div x-show="review || requiredDone() >= requiredTotal()" x-cloak class="space-y-3">
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
            <template x-for="s in requiredSteps()" :key="key(s)">
                <button type="button" @click="thumbFor(s) ? (preview = thumbFor(s)) : retake(s)"
                        class="rounded-xl ring-1 ring-gray-200 p-2 text-left bg-white">
                    <div class="{{ $thumbClass }} rounded-lg overflow-hidden bg-gray-50 mb-1.5">
                        <img x-show="thumbFor(s)" :src="thumbFor(s)" alt="" class="h-full w-full object-cover">
                        <div x-show="!thumbFor(s)" class="h-full grid place-items-center text-xs text-gray-400">○</div>
                    </div>
                    <p class="text-xs font-bold truncate" x-text="(thumbFor(s) ? '✓ ' : '') + s.label"></p>
                    <span class="text-[11px] font-semibold text-brand" x-show="thumbFor(s)" @click.stop="retake(s)">{{ __('site.partner_account.face_retake') }}</span>
                </button>
            </template>
        </div>
        <button type="button" @click="start()" class="w-full text-sm font-bold text-brand py-2">
            {{ $retakeLabel }}
        </button>
    </div>

    {{ $slot }}

    <x-site.guided-camera-overlay />

    <template x-teleport="body">
        <div x-show="preview" x-cloak class="fixed inset-x-0 bottom-0 z-[90] lg:inset-0 lg:flex lg:items-center lg:justify-center bg-black/80 p-4" @click="preview = null">
            <img :src="preview" alt="" class="max-h-[80vh] max-w-full rounded-xl object-contain mx-auto">
        </div>
    </template>
</div>
