@php
    $inspection = app(\App\Services\ValuationInspectionService::class);
    $assignment = $task->valuationAssignment
        ?? \App\Models\ValuationAssignment::query()->where('partner_task_id', $task->id)->first();
    $assets = $collateralAssets->isNotEmpty()
        ? $collateralAssets
        : $inspection->assetsForTask($task);
    $checks = $assignment ? $inspection->checksSummary($assignment) : ['engine' => null, 'test_drive' => null, 'body_condition' => '', 'tyres' => '', 'interior' => ''];
    $needsVehicle = $assets->contains(fn ($asset) => $asset->isVehicleLike());
    $missingPhotos = $inspection->missingValuerAngles($task, $assets);
    $photosDone = $missingPhotos === [];
    $conditionDone = ! $needsVehicle
        || (filled($checks['body_condition'] ?? null) && filled($checks['tyres'] ?? null) && filled($checks['interior'] ?? null));
    $engineDone = ! $needsVehicle || filled($checks['engine'] ?? null);
    $driveDone = ! $needsVehicle || filled($checks['test_drive'] ?? null);
    $inspectionDone = ! $needsVehicle || ($conditionDone && $engineDone && $driveDone);
    $started = in_array($task->status, ['in_progress', 'completed'], true) || filled($task->started_at);
    $open = $task->isWritable();
    $reassigned = $task->status === 'cancelled';
    $completed = $task->status === 'completed';
    $photoSteps = $inspection->photoSteps($task, $assets);
    $requiredSteps = collect($photoSteps)->where('required', true)->values();
    $requiredTotal = $requiredSteps->count();
    $requiredDoneCount = $requiredSteps->filter(fn ($step) => filled($step['path'] ?? null))->count();
    $compareSteps = collect($photoSteps)->filter(fn ($step) => filled($step['borrower_path'] ?? null) && filled($step['path'] ?? null))->values();
    $jobBlock = app(\App\Services\PartnerProfileService::class)->jobBlockReason($vendor);
    $payRoute = $vendor->isAffiliate() ? 'site.affiliate.membership.pay' : 'site.partner.membership.pay';
    $title = $assets->pluck('label')->filter()->implode(' · ') ?: ($task->vehicle_details ?: __('site.partner_portal.valuation_job_eyebrow'));
    $allowedSteps = ['asset', 'photos', 'condition', 'values', 'review'];
    $initialStep = match (true) {
        ! $started => 'asset',
        ! $photosDone => 'photos',
        ! $inspectionDone => 'condition',
        $completed => 'review',
        default => 'values',
    };
    $requestedStep = (string) request('step', request('tab', ''));
    $requestedStep = match ($requestedStep) {
        'overview', 'asset' => 'asset',
        'inspect' => $photosDone ? 'condition' : 'photos',
        'values' => 'values',
        'review' => 'review',
        default => $requestedStep,
    };
    if (in_array($requestedStep, $allowedSteps, true)) {
        $initialStep = $requestedStep;
    }
    $workflow = [
        ['key' => 'asset', 'label' => __('site.partner_portal.tab_asset'), 'done' => $started],
        ['key' => 'photos', 'label' => __('site.partner_portal.valuation_step_photos'), 'done' => $photosDone],
        ['key' => 'condition', 'label' => __('site.partner_portal.tab_inspect'), 'done' => $inspectionDone],
        ['key' => 'values', 'label' => __('site.partner_portal.tab_values'), 'done' => $completed],
        ['key' => 'review', 'label' => __('site.partner_portal.valuation_step_review'), 'done' => $completed],
    ];
    $currentWorkflow = 1;
    foreach ($workflow as $i => $row) {
        if (! $row['done']) {
            $currentWorkflow = $i + 1;
            break;
        }
        $currentWorkflow = $i + 1;
    }
    $nextActionLabel = match (true) {
        $reassigned => __('site.partner_portal.task_reassigned_title'),
        $task->status === 'assigned' && $jobBlock === 'profile' => __('site.partner_portal.cta_complete_profile'),
        $task->status === 'assigned' && $jobBlock === 'payment' => __('site.partner_portal.cta_pay_membership'),
        $task->status === 'assigned' => __('site.partner_portal.accept_task'),
        $open && ! $started => __('site.partner_portal.valuation_start_work'),
        $open && ! $photosDone => __('site.partner_portal.valuation_continue_photos'),
        $open && ! $inspectionDone => __('site.partner_portal.valuation_step_vehicle'),
        $open && ! $completed => __('site.partner_portal.valuation_review_valuation'),
        default => __('site.partner_portal.completed_at'),
    };
    $nextStepKey = match (true) {
        ! $started => 'asset',
        ! $photosDone => 'photos',
        ! $inspectionDone => 'condition',
        default => 'values',
    };
    $afterPhotosUrl = route('site.partner.task', [
        'task' => $task,
        'step' => $needsVehicle ? 'condition' : 'values',
    ]);
    $cameraCfg = [
        'csrf' => csrf_token(),
        'uploadUrl' => route('site.partner.task.inspect.photo', $task),
        'steps' => collect($photoSteps)->map(fn (array $step) => [
            'asset_id' => $step['asset_id'],
            'asset_label' => $step['asset_label'],
            'angle' => $step['angle'],
            'label' => $step['label'],
            'path' => $step['path'],
            'path_url' => $step['path_url'],
            'guidance' => $step['guidance'],
            'required' => $step['required'],
        ])->values()->all(),
        'dbName' => 'kf-valuation-'.$task->id,
        'step' => $initialStep,
        'afterPhotosUrl' => $afterPhotosUrl,
        'assets' => $assets->map(fn ($asset) => [
            'id' => (int) $asset->id,
            'label' => (string) $asset->label,
        ])->values()->all(),
        'cameraInsecure' => __('borrower.profile.camera_insecure'),
        'cameraDenied' => __('borrower.profile.camera_denied'),
        'savingMessage' => __('borrower.profile.uploading_documents'),
    ];
@endphp

<div class="mb-4">
    <a href="{{ route('site.partner.tasks') }}" data-kf-motion="pop" class="text-sm text-brand hover:underline">← {{ __('site.partner_portal.back_to_valuation_jobs') }}</a>
</div>

@if ($reassigned)
    <div class="mb-5 rounded-2xl bg-amber-50 ring-1 ring-amber-200 px-4 py-3 text-sm text-amber-950">
        <p class="font-semibold">{{ __('site.partner_portal.task_reassigned_title') }}</p>
        <p class="mt-1">{{ __('site.partner_portal.task_reassigned_body', ['id' => $task->id]) }}</p>
    </div>
@endif

<div
    class="space-y-4 min-w-0"
    x-data="valuationCamera(@js($cameraCfg))"
>
    <div class="rounded-2xl ring-1 ring-brand/15 bg-white px-4 py-3">
        <p class="text-[10px] uppercase tracking-[0.18em] text-brand font-bold">{{ __('site.partner_portal.valuation_job_eyebrow') }}</p>
        <h1 class="text-lg font-extrabold text-gray-900 tracking-tight mt-1 leading-tight">{{ $title }}</h1>
        <ol class="mt-2 flex flex-wrap gap-x-3 gap-y-1 text-xs sm:text-sm">
            @foreach ($workflow as $i => $row)
                <li>
                    <button type="button"
                            @click="go(@js($row['key']))"
                            class="{{ $row['done'] ? 'text-emerald-700 font-semibold' : ($i + 1 === $currentWorkflow ? 'text-brand font-extrabold' : 'text-gray-400') }}">
                        {{ $row['done'] ? '✓' : ($i + 1 === $currentWorkflow ? '●' : '○') }} {{ $row['label'] }}
                    </button>
                </li>
            @endforeach
        </ol>
        <p class="text-xs font-semibold text-gray-500 mt-2">{{ __('site.partner_portal.valuation_no_loan_hint') }}</p>
        <div class="mt-2 flex flex-wrap items-center gap-2">
            <span class="px-3 py-1 rounded-full text-xs font-semibold {{ $priorityBadge }}">{{ $priority['label'] }} {{ __('site.partner_portal.priority_suffix') }}</span>
            <span class="px-3 py-1 rounded-full text-xs font-semibold {{ $badge }}">{{ str_replace('_',' ', $task->status) }}</span>
        </div>
    </div>

    @if ($open && $started && ! $completed)
        <div class="rounded-xl bg-brand text-white px-4 py-3 flex items-center justify-between gap-3">
            <div class="min-w-0">
                <p class="text-[10px] uppercase tracking-[0.16em] text-brand-gold font-bold">{{ __('site.partner_portal.next_step') }}</p>
                @if (! $photosDone)
                    <p class="text-sm font-bold mt-0.5 truncate">{{ __('site.partner_portal.valuation_asset_photos') }} · {{ __('site.partner_portal.valuation_photos_done', ['done' => $requiredDoneCount, 'total' => $requiredTotal]) }}</p>
                @else
                    <p class="text-sm font-bold mt-0.5 truncate">{{ $nextActionLabel }}</p>
                @endif
            </div>
            <button type="button" @click="go(@js($nextStepKey))" class="shrink-0 rounded-lg bg-brand-gold text-brand text-xs font-extrabold px-3 py-2">
                {{ $nextActionLabel }}
            </button>
        </div>
    @endif

    <div class="space-y-4">
        <p class="text-[10px] uppercase tracking-[0.18em] text-brand font-bold">{{ __('site.partner_portal.tab_asset') }}</p>
        @forelse ($assets as $asset)
            @php
                $assetOwner = $asset->customer?->full_name ?: $task->customer_name;
                $valuationApplication = $assignment?->application;
                $valuerCard = app(\App\Services\CollateralCardService::class)->forAsset(
                    $asset,
                    $valuationApplication,
                    \App\Services\CollateralCardService::VIEWER_VALUER,
                    ['belongs_to' => $assetOwner]
                );
            @endphp
            <x-site.collateral-card :selected="$valuerCard">
                @if ($open && ! $completed)
                    <button type="button" @click="details = !details" class="mt-2 text-sm font-bold text-brand">
                        <span x-show="!details">{{ __('site.partner_portal.valuation_view_details') }}</span>
                        <span x-show="details" x-cloak>{{ __('site.partner_portal.valuation_hide_details') }}</span>
                    </button>
                @endif
            </x-site.collateral-card>
        @empty
            <div class="rounded-2xl ring-1 ring-brand/15 bg-brand-muted/25 p-5 text-sm font-semibold text-gray-700">{{ $task->vehicle_details ?: '—' }}</div>
        @endforelse
    </div>

    <div x-show="step === 'asset'" class="space-y-4">
        <div x-show="details" x-cloak class="rounded-2xl ring-1 ring-brand/15 bg-white p-4 grid grid-cols-2 gap-3 text-sm">
            <div><dt class="text-gray-500 text-xs font-semibold">{{ __('site.partner_portal.customer') }}</dt><dd class="font-extrabold text-gray-900 mt-0.5">{{ $task->customer_name ?: '—' }}</dd></div>
            <div><dt class="text-gray-500 text-xs font-semibold">{{ __('site.partner_portal.phone') }}</dt><dd class="font-extrabold text-gray-900 mt-0.5">{{ $task->customer_phone ?: '—' }}</dd></div>
            <div class="col-span-2"><dt class="text-gray-500 text-xs font-semibold">{{ __('site.partner_portal.location') }}</dt><dd class="font-extrabold text-gray-900 mt-0.5">{{ $task->location ?: '—' }}</dd></div>
            <div><dt class="text-gray-500 text-xs font-semibold">{{ __('site.partner_portal.due') }}</dt><dd class="font-extrabold text-gray-900 mt-0.5">{{ $task->due_at?->format('d M Y H:i') ?: '—' }}</dd></div>
            <div><dt class="text-gray-500 text-xs font-semibold">{{ __('site.partner_portal.your_payout') }}</dt><dd class="font-extrabold text-gray-900 mt-0.5">{{ format_money($task->fee_amount) }}</dd></div>
        </div>

        @if ($task->status === 'assigned' && $open)
            @if ($jobBlock === 'profile')
                <p class="text-sm font-bold text-gray-800">{{ __('site.partner_portal.job_requires_profile') }}</p>
                <a href="{{ route('site.partner.profile') }}" class="block w-full text-center rounded-xl bg-brand-gold text-brand text-sm font-extrabold py-3">{{ __('site.partner_portal.cta_complete_profile') }}</a>
            @elseif ($jobBlock === 'payment')
                <p class="text-sm font-bold text-gray-800">{{ __('site.partner_portal.job_requires_payment') }}</p>
                <a href="{{ route($payRoute) }}" class="block w-full text-center rounded-xl bg-brand-gold text-brand text-sm font-extrabold py-3">{{ __('site.partner_portal.cta_pay_membership') }}</a>
            @else
                <form method="POST" action="{{ route('site.partner.task.accept', $task) }}">
                    @csrf
                    <button class="w-full rounded-xl bg-brand-gold text-brand text-sm font-extrabold py-3">{{ __('site.partner_portal.accept_task') }}</button>
                </form>
                <p class="text-sm font-bold text-gray-800">{{ __('site.partner_portal.valuation_start_hint') }}</p>
                <form method="POST" action="{{ route('site.partner.task.start', $task) }}">
                    @csrf
                    <button class="w-full rounded-xl bg-gray-900 text-white text-sm font-extrabold py-3">{{ __('site.partner_portal.valuation_start_work') }}</button>
                </form>
                <button type="button" @click="declineOpen = true" class="w-full rounded-xl ring-1 ring-gray-200 text-sm font-semibold py-3">{{ __('site.partner_portal.decline_task') }}</button>
            @endif
        @elseif ($open && ! $started)
            @if ($jobBlock === 'profile')
                <p class="text-sm font-bold text-gray-800">{{ __('site.partner_portal.job_requires_profile') }}</p>
                <a href="{{ route('site.partner.profile') }}" class="block w-full text-center rounded-xl bg-brand-gold text-brand text-sm font-extrabold py-3">{{ __('site.partner_portal.cta_complete_profile') }}</a>
            @elseif ($jobBlock === 'payment')
                <p class="text-sm font-bold text-gray-800">{{ __('site.partner_portal.job_requires_payment') }}</p>
                <a href="{{ route($payRoute) }}" class="block w-full text-center rounded-xl bg-brand-gold text-brand text-sm font-extrabold py-3">{{ __('site.partner_portal.cta_pay_membership') }}</a>
            @else
                <p class="text-sm font-bold text-gray-800">{{ __('site.partner_portal.valuation_start_hint') }}</p>
                <form method="POST" action="{{ route('site.partner.task.start', $task) }}">
                    @csrf
                    <button class="w-full rounded-xl bg-gray-900 text-white text-sm font-extrabold py-3">{{ __('site.partner_portal.valuation_start_work') }}</button>
                </form>
            @endif
        @elseif ($open && $started)
            <button type="button" @click="go('photos')" class="w-full rounded-xl bg-brand text-white text-sm font-extrabold py-3">{{ __('site.partner_portal.valuation_inspect_asset') }}</button>
        @endif
    </div>

    <div class="space-y-4">
        <div x-show="step === 'photos' && {{ $started ? 'true' : 'false' }}" class="space-y-4">
            <div class="rounded-2xl ring-1 ring-brand/15 bg-white p-4 sm:p-5 space-y-3">
                <p class="text-[10px] uppercase tracking-[0.18em] text-brand font-bold">{{ __('site.partner_portal.valuation_asset_photos') }}</p>
                <p class="text-lg font-extrabold text-gray-900"
                   x-text="@js(__('site.partner_portal.valuation_photos_done', ['done' => '__D__', 'total' => '__T__'])).replace('__D__', String(requiredDone())).replace('__T__', String(requiredTotal()))">
                    {{ __('site.partner_portal.valuation_photos_done', ['done' => $requiredDoneCount, 'total' => $requiredTotal]) }}
                </p>
                <p class="text-sm font-semibold text-gray-700">{{ __('site.partner_portal.valuation_start_photos_hint') }}</p>
                @if ($open && ! $photosDone)
                    <button type="button" @click="start()" class="w-full rounded-xl bg-brand text-white text-sm font-extrabold py-3">{{ __('site.partner_portal.valuation_start_photos') }}</button>
                @elseif ($open && $photosDone)
                    <p class="text-sm font-bold text-emerald-800">{{ __('site.partner_portal.valuation_required_ok', ['done' => $requiredTotal, 'total' => $requiredTotal]) }}</p>
                    <button type="button" @click="start(true)" class="text-sm font-bold text-brand">{{ __('site.partner_portal.valuation_add_another_photo') }}</button>
                    <button type="button" @click="go('condition')" class="w-full rounded-xl bg-brand text-white text-sm font-extrabold py-3">{{ __('site.partner_portal.valuation_continue') }}</button>
                @endif
            </div>
        </div>

        <div x-show="review" x-cloak class="rounded-2xl ring-1 ring-brand/15 bg-white p-4 space-y-4">
            <p class="text-[10px] uppercase tracking-[0.18em] text-brand font-bold">{{ __('site.partner_portal.valuation_asset_photos') }}</p>
            <div class="grid grid-cols-2 gap-2">
                <template x-for="s in requiredSteps()" :key="key(s)">
                    <button type="button" @click="preview = thumbFor(s); $nextTick(() => { if (! thumbFor(s) && {{ $open ? 'true' : 'false' }}) retake(s) })"
                            class="rounded-xl ring-1 ring-gray-200 p-2 text-left">
                        <div class="aspect-square rounded-lg overflow-hidden bg-gray-50 mb-1.5">
                            <img x-show="thumbFor(s)" :src="thumbFor(s)" alt="" class="h-full w-full object-cover">
                            <div x-show="!thumbFor(s)" class="h-full grid place-items-center text-xs text-gray-400">○</div>
                        </div>
                        <p class="text-xs font-bold truncate" x-text="(thumbFor(s) ? '✓ ' : '') + s.label"></p>
                    </button>
                </template>
            </div>
            <template x-if="optionalSteps().some((s) => thumbFor(s))">
                <div class="grid grid-cols-2 gap-2">
                    <template x-for="s in optionalSteps()" :key="key(s)">
                        <button type="button" x-show="thumbFor(s)" @click="preview = thumbFor(s)" class="rounded-xl ring-1 ring-gray-200 p-2 text-left">
                            <div class="aspect-square rounded-lg overflow-hidden bg-gray-50 mb-1.5">
                                <img :src="thumbFor(s)" alt="" class="h-full w-full object-cover">
                            </div>
                            <p class="text-xs font-bold truncate" x-text="'✓ ' + s.label"></p>
                        </button>
                    </template>
                </div>
            </template>
            @if ($open)
                <p x-show="failed.length" x-cloak class="text-sm font-semibold text-amber-800">{{ __('site.partner_portal.valuation_retry_failed') }}</p>
                <button type="button" x-show="!uploading && failed.length === 0" @click="uploadAll()"
                        class="w-full rounded-xl bg-brand text-white text-sm font-extrabold py-3"
                        x-text="@js(__('site.partner_portal.valuation_upload_n', ['count' => '__N__'])).replace('__N__', String(steps.filter(s => !s.path && captures[key(s)]).length || requiredTotal()))">
                    {{ __('site.partner_portal.valuation_upload_n', ['count' => $requiredTotal]) }}
                </button>
                <button type="button" x-show="failed.length" x-cloak @click="retryFailed()" class="w-full rounded-xl bg-brand text-white text-sm font-extrabold py-3">{{ __('site.partner_portal.valuation_retry_failed') }}</button>
                <button type="button" @click="start(requiredDone() >= requiredTotal())" class="w-full text-sm font-bold text-brand py-2">{{ __('site.partner_portal.valuation_retake') }}</button>
            @endif
        </div>

        <template x-teleport="body">
            <div x-show="open" x-cloak class="fixed inset-0 z-[95] bg-black flex flex-col">
                <video x-ref="camVideo" autoplay playsinline webkit-playsinline muted class="absolute inset-0 z-[1] w-full h-full object-cover bg-gray-900"></video>
                <div class="relative z-[4] pt-[max(1rem,env(safe-area-inset-top))] px-4 flex items-start justify-between gap-3 bg-gradient-to-b from-black/80 to-transparent pb-8">
                    <div class="min-w-0 max-w-md rounded-2xl bg-black/40 backdrop-blur-sm px-4 py-3 text-white">
                        <p class="text-[11px] uppercase tracking-widest text-brand-gold" x-text="current() && current().required ? (captureOrdinal() + ' of ' + requiredTotal() + ' — ' + current().label) : (current() ? current().label : '')"></p>
                        <p class="text-sm font-semibold mt-1" x-text="current()?.guidance || ''"></p>
                    </div>
                    <button type="button" @click="closeCamera()" class="shrink-0 text-xs font-semibold text-white/90 bg-white/15 ring-1 ring-white/25 px-3 py-2 rounded-full">{{ __('site.partner_portal.valuation_camera_close') }}</button>
                </div>
                <div x-show="flash" x-cloak class="relative z-[5] mt-auto mb-auto px-6 text-center text-white">
                    <p class="text-xl font-extrabold" x-text="flash ? ('✓ ' + flash.label) : ''"></p>
                    <p class="text-sm font-semibold mt-1" x-show="flash?.next" x-text="flash ? @js(__('site.partner_portal.valuation_next_is', ['label' => '__L__'])).replace('__L__', flash.next) : ''"></p>
                </div>
                <p x-show="cameraNotice" x-cloak class="relative z-[4] mx-4 rounded-xl bg-amber-50 text-amber-950 text-sm font-semibold p-3" x-text="cameraNotice"></p>
                <button type="button" x-show="cameraNotice" x-cloak @click="openCam()" class="relative z-[4] mx-4 mt-3 w-[calc(100%-2rem)] rounded-xl bg-brand-gold text-brand text-sm font-extrabold py-3">{{ __('site.partner_portal.valuation_camera_retry') }}</button>
                <div class="relative z-[4] mt-auto px-4 pb-[max(1.25rem,env(safe-area-inset-bottom))] pt-8 bg-gradient-to-t from-black/80 via-black/40 to-transparent">
                    <button type="button" @click="capture()" class="w-full max-w-md mx-auto block size-16 rounded-full bg-brand-gold text-brand font-extrabold shadow-lg grid place-items-center mx-auto">●</button>
                    <p class="text-center text-white text-sm font-bold mt-3">{{ __('site.partner_portal.valuation_camera_capture') }}</p>
                </div>
            </div>
        </template>
    </div>

    @if ($needsVehicle)
        <form method="POST" action="{{ route('site.partner.task.inspect.checks', $task) }}" x-show="step === 'condition' && {{ $started && $photosDone ? 'true' : 'false' }}" x-cloak
              class="rounded-2xl ring-1 ring-brand/15 bg-white p-4 sm:p-5 space-y-5">
            @csrf
            <div>
                <p class="text-[10px] uppercase tracking-[0.18em] text-brand font-bold">{{ __('site.partner_portal.tab_inspect') }}</p>
                <h3 class="text-xl font-extrabold text-gray-900 mt-1 leading-tight">{{ __('site.partner_portal.valuation_step_vehicle') }}</h3>
                <p class="text-sm font-bold text-gray-800 mt-2 leading-relaxed">{{ __('site.partner_portal.valuation_vehicle_check_intro') }}</p>
            </div>
            <x-site.sheet-select name="body_condition" :label="__('site.partner_portal.valuation_body_condition')" :options="$inspection->bodyConditionOptions()" :value="old('body_condition', $checks['body_condition'] ?? '')" />
            <x-site.sheet-select name="tyres" :label="__('site.partner_portal.valuation_tyres')" :options="$inspection->tyreOptions()" :value="old('tyres', $checks['tyres'] ?? '')" />
            <x-site.sheet-select name="interior" :label="__('site.partner_portal.valuation_interior')" :options="$inspection->interiorOptions()" :value="old('interior', $checks['interior'] ?? '')" />
            <x-site.sheet-select name="engine" :label="__('site.partner_portal.valuation_step_engine')" :options="$inspection->engineOptions()" :value="old('engine', $checks['engine'] ?? '')" />
            <x-site.sheet-select name="test_drive" :label="__('site.partner_portal.valuation_step_drive')" :options="$inspection->driveOptions()" :value="old('test_drive', $checks['test_drive'] ?? '')" />
            @if ($open)
                <button class="w-full rounded-xl bg-brand text-white text-sm font-extrabold px-5 py-3">{{ __('site.partner_portal.valuation_continue') }}</button>
            @endif
        </form>
    @endif

    <div x-show="(step === 'values' || step === 'review') && {{ $photosDone && $inspectionDone ? 'true' : 'false' }}" x-cloak>
        @if (! $photosDone || ! $inspectionDone)
            <div class="rounded-2xl ring-1 ring-amber-200 bg-amber-50 p-5 text-sm font-bold text-amber-950 leading-relaxed">
                {{ __('site.partner_portal.valuation_photos_required', ['list' => implode(', ', $missingPhotos) ?: __('site.partner_portal.valuation_step_engine')]) }}
            </div>
        @elseif ($open)
            <form method="POST" action="{{ route('site.partner.task.complete', $task) }}" class="space-y-4"
                  @submit.prevent="if (step !== 'review') { step = 'review'; return; } window.confirmForm($el, {
                      title: @js(__('site.partner_portal.confirm.valuation_title')),
                      message: @js(__('site.partner_portal.confirm.valuation_message')),
                      confirmLabel: @js(__('site.partner_portal.confirm.task_complete_button')),
                      tone: 'warning',
                  })">
                @csrf
                <div x-show="step === 'values'" class="rounded-2xl ring-1 ring-brand/15 bg-white p-4 sm:p-5 space-y-4">
                    <p class="text-[10px] uppercase tracking-[0.18em] text-brand font-bold">{{ __('site.partner_portal.valuation_step_values') }}</p>
                    <p class="text-sm font-bold text-gray-800 leading-relaxed">{{ __('site.partner_portal.valuation_values_intro') }}</p>
                    @foreach ($assets as $valAsset)
                        <div class="rounded-xl ring-1 ring-gray-200 bg-white p-4 space-y-3">
                            <p class="text-sm font-extrabold">{{ $valAsset->label }}</p>
                            <x-site.numeric-input :name="'values['.$valAsset->id.'][market_value]'" :label="__('site.partner_portal.valuation_market_value')" money :required="true" />
                            <x-site.numeric-input :name="'values['.$valAsset->id.'][forced_sale_value]'" :label="__('site.partner_portal.valuation_fsv')" money :required="true" />
                        </div>
                    @endforeach
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">{{ __('site.partner_portal.notes_optional') }}</label>
                        <textarea name="notes" rows="3" class="w-full rounded-lg border-gray-300 text-sm">{{ old('notes') }}</textarea>
                    </div>
                    <button type="button" @click="go('review')" class="w-full rounded-xl bg-brand text-white text-sm font-extrabold px-5 py-3">{{ __('site.partner_portal.valuation_review_valuation') }}</button>
                </div>

                <div x-show="step === 'review'" class="rounded-2xl ring-1 ring-brand/15 bg-white p-4 sm:p-5 space-y-4">
                    <p class="text-[10px] uppercase tracking-[0.18em] text-brand font-bold">{{ __('site.partner_portal.valuation_review_before') }}</p>
                    <ul class="text-sm font-semibold space-y-1">
                        <li class="text-emerald-700">{{ __('site.partner_portal.tab_asset') }} ✓</li>
                        <li class="text-emerald-700">{{ __('site.partner_portal.valuation_required_ok', ['done' => $requiredDoneCount, 'total' => $requiredTotal]) }}</li>
                        <li class="text-emerald-700">{{ __('site.partner_portal.tab_inspect') }} ✓</li>
                    </ul>
                    <div class="rounded-xl ring-1 ring-gray-200 p-3 space-y-2">
                        <p class="text-xs uppercase tracking-widest font-bold text-brand">{{ __('site.partner_portal.tab_values') }}</p>
                        <template x-for="line in valueLines" :key="line.label">
                            <div class="text-sm">
                                <p class="font-extrabold text-gray-900" x-text="line.label"></p>
                                <p class="text-gray-700">{{ __('site.partner_portal.valuation_market_value') }}: <span class="font-semibold" x-text="line.market"></span></p>
                                <p class="text-gray-700">{{ __('site.partner_portal.valuation_fsv') }}: <span class="font-semibold" x-text="line.fsv"></span></p>
                            </div>
                        </template>
                    </div>
                    @if ($compareSteps->isNotEmpty())
                        <div class="space-y-3">
                            <p class="text-sm font-extrabold">{{ __('site.partner_portal.valuation_compare_title') }}</p>
                            @foreach ($compareSteps as $cmp)
                                <div class="grid grid-cols-2 gap-2">
                                    <div class="rounded-xl ring-1 ring-gray-200 overflow-hidden">
                                        <p class="text-[10px] font-bold uppercase tracking-widest text-brand px-2 pt-2">{{ __('site.partner_portal.valuation_owner_photo') }}</p>
                                        <img src="{{ asset('storage/'.$cmp['borrower_path']) }}" alt="" class="aspect-square w-full object-contain bg-gray-50">
                                    </div>
                                    <div class="rounded-xl ring-1 ring-gray-200 overflow-hidden">
                                        <p class="text-[10px] font-bold uppercase tracking-widest text-brand px-2 pt-2">{{ $cmp['label'] }}</p>
                                        <img src="{{ $cmp['path_url'] }}" alt="" class="aspect-square w-full object-contain bg-gray-50">
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                    <button type="button" @click="step = 'values'" class="w-full rounded-xl ring-1 ring-gray-200 text-sm font-bold py-3">{{ __('site.partner_portal.valuation_photo_back') }}</button>
                    <button class="w-full rounded-xl bg-emerald-600 text-white text-sm font-extrabold px-5 py-3 hover:bg-emerald-700">{{ __('site.partner_portal.valuation_submit_report') }}</button>
                </div>
            </form>
        @else
            <div class="rounded-2xl ring-1 ring-brand/15 bg-white p-5 text-sm space-y-2">
                <p>{{ __('site.partner_portal.valuation_market_value') }}: <span class="font-semibold">{{ format_money($assignment?->market_value) }}</span></p>
                <p>{{ __('site.partner_portal.valuation_fsv') }}: <span class="font-semibold">{{ format_money($assignment?->forced_sale_value) }}</span></p>
            </div>
        @endif
    </div>

    <template x-teleport="body">
        <div x-show="preview" x-cloak class="fixed inset-0 z-[90] bg-black/80 flex items-center justify-center p-4" @click="preview = null">
            <img :src="preview" alt="" class="max-h-[80vh] max-w-full rounded-xl object-contain">
        </div>
    </template>

    <div x-show="declineOpen" x-cloak class="fixed inset-0 z-[80]">
        <div class="absolute inset-0 bg-black/40" @click="declineOpen = false"></div>
        <div class="absolute inset-x-0 bottom-0 bg-white rounded-t-2xl p-5 space-y-3" style="padding-bottom: max(1.25rem, env(safe-area-inset-bottom))">
            <h3 class="font-bold">{{ __('site.partner_portal.decline_task') }}</h3>
            <form method="POST" action="{{ route('site.partner.task.decline', $task) }}" class="space-y-3">
                @csrf
                @foreach ([
                    'too_far' => __('site.partner_portal.decline_too_far'),
                    'unavailable' => __('site.partner_portal.decline_unavailable'),
                    'conflict' => __('site.partner_portal.decline_conflict'),
                    'other' => __('site.partner_portal.decline_other'),
                ] as $code => $label)
                    <label class="flex items-center gap-3 rounded-xl ring-1 ring-gray-200 px-3 py-3 text-sm">
                        <input type="radio" name="reason" value="{{ $code }}" class="text-brand" required>
                        <span>{{ $label }}</span>
                    </label>
                @endforeach
                <textarea name="detail" rows="2" class="w-full rounded-lg border-gray-300 text-sm" placeholder="{{ __('site.partner_portal.notes_optional') }}"></textarea>
                <button class="w-full rounded-xl bg-red-600 text-white text-sm font-semibold py-3">{{ __('site.partner_portal.decline_task') }}</button>
            </form>
        </div>
    </div>
</div>
