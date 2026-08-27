@php
    $inspection = app(\App\Services\ValuationInspectionService::class);
    $assignment = $task->valuationAssignment
        ?? \App\Models\ValuationAssignment::query()->where('partner_task_id', $task->id)->first();
    $assets = $collateralAssets->isNotEmpty()
        ? $collateralAssets
        : $inspection->assetsForTask($task);
    $valuerPhotos = $inspection->valuerPhotosByAsset($task, $assets);
    $checks = $assignment ? $inspection->checksSummary($assignment) : ['engine' => null, 'test_drive' => null, 'body_condition' => '', 'tyres' => '', 'interior' => ''];
    $needsVehicle = $assets->contains(fn ($asset) => $asset->isVehicleLike());
    $missingPhotos = $inspection->missingValuerAngles($task, $assets);
    $photosDone = $missingPhotos === [];
    $conditionDone = ! $needsVehicle
        || (filled($checks['body_condition'] ?? null) && filled($checks['tyres'] ?? null) && filled($checks['interior'] ?? null));
    $engineDone = ! $needsVehicle || filled($checks['engine'] ?? null);
    $driveDone = ! $needsVehicle || filled($checks['test_drive'] ?? null);
    $started = in_array($task->status, ['in_progress', 'completed'], true) || filled($task->started_at);
    $open = $task->isWritable();
    $reassigned = $task->status === 'cancelled';
    $initialTab = request('tab', $started ? 'inspect' : 'overview');
    $initialStep = ! $photosDone ? 'photos' : (! $conditionDone ? 'condition' : (! $engineDone ? 'engine' : (! $driveDone ? 'drive' : 'values')));
    $photoSteps = $inspection->photoSteps($task, $assets);
    $requiredSteps = collect($photoSteps)->where('required', true)->values();
    $requiredTotal = $requiredSteps->count();
    $requiredDoneCount = $requiredSteps->filter(fn ($step) => filled($step['path'] ?? null))->count();
    $requestedPhoto = (int) request('photo', -1);
    $firstOpenPhoto = collect($photoSteps)->search(fn ($step) => ($step['required'] ?? true) && blank($step['path'] ?? null));
    if ($firstOpenPhoto === false) {
        $firstOpenPhoto = collect($photoSteps)->search(fn ($step) => blank($step['path'] ?? null));
    }
    $initialPhoto = ($requestedPhoto >= 0 && isset($photoSteps[$requestedPhoto]))
        ? $requestedPhoto
        : ($firstOpenPhoto === false ? 0 : (int) $firstOpenPhoto);
    $jobBlock = app(\App\Services\PartnerProfileService::class)->jobBlockReason($vendor);
    $payRoute = $vendor->isAffiliate() ? 'site.affiliate.membership.pay' : 'site.partner.membership.pay';
    $title = $assets->pluck('label')->filter()->implode(' · ') ?: ($task->vehicle_details ?: __('site.partner_portal.valuation_job_eyebrow'));
    $workflow = [
        ['key' => 'asset', 'label' => __('site.partner_portal.tab_asset'), 'done' => $started],
        ['key' => 'inspect', 'label' => __('site.partner_portal.tab_inspect'), 'done' => $photosDone && $conditionDone && $engineDone && $driveDone],
        ['key' => 'values', 'label' => __('site.partner_portal.tab_values'), 'done' => $task->status === 'completed'],
        ['key' => 'submit', 'label' => __('site.partner_portal.valuation_submit_report'), 'done' => $task->status === 'completed'],
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
        $open && ! $photosDone => __('site.partner_portal.valuation_take_next_photo'),
        $open && ! $conditionDone => __('site.partner_portal.valuation_step_condition'),
        $open && ! $engineDone => __('site.partner_portal.valuation_step_engine'),
        $open && ! $driveDone => __('site.partner_portal.valuation_step_drive'),
        $open => __('site.partner_portal.valuation_step_values'),
        default => __('site.partner_portal.completed_at'),
    };
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

<div class="glass-card rounded-2xl ring-1 ring-brand/10 p-4 sm:p-5 mb-5">
    <h1 class="text-xl sm:text-2xl font-extrabold text-gray-900 tracking-tight truncate">{{ $title }}</h1>
    <p class="text-sm text-gray-500 mt-1">{{ __('site.partner_portal.valuation_step_of', ['current' => $currentWorkflow, 'total' => 4, 'label' => $workflow[$currentWorkflow - 1]['label']]) }}</p>
    <p class="text-sm text-gray-600 mt-2">{{ __('site.partner_portal.valuation_no_loan_hint') }}</p>
    <ol class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-sm">
        @foreach ($workflow as $i => $row)
            <li class="{{ $row['done'] ? 'text-emerald-700' : ($i + 1 === $currentWorkflow ? 'text-brand font-bold' : 'text-gray-400') }}">
                {{ $row['done'] ? '✓' : ($i + 1 === $currentWorkflow ? '●' : '○') }} {{ $row['label'] }}
            </li>
        @endforeach
    </ol>
    <div class="mt-3 flex items-center gap-2">
        <span class="px-3 py-1 rounded-full text-xs font-semibold {{ $priorityBadge }}">{{ $priority['label'] }} {{ __('site.partner_portal.priority_suffix') }}</span>
        <span class="px-3 py-1 rounded-full text-xs font-semibold {{ $badge }}">{{ str_replace('_',' ', $task->status) }}</span>
    </div>
</div>

<div class="grid lg:grid-cols-3 gap-6" x-data="{ tab: @js($initialTab), step: @js($initialStep), photo: {{ $initialPhoto }}, declineOpen: false }">
    <div class="lg:col-span-2 space-y-4">
        <div class="inline-flex flex-wrap rounded-xl ring-1 ring-gray-200/80 bg-white/90 p-0.5 text-sm gap-0.5 w-full sm:w-auto">
            @foreach ([
                'overview' => __('site.partner_portal.tab_overview'),
                'asset' => __('site.partner_portal.tab_asset'),
                'inspect' => __('site.partner_portal.tab_inspect'),
                'values' => __('site.partner_portal.tab_values'),
            ] as $key => $label)
                <button type="button"
                        @click="tab = '{{ $key }}'"
                        :class="tab === '{{ $key }}' ? 'bg-brand text-white shadow-sm' : 'text-gray-600 hover:bg-brand-muted/50'"
                        class="px-3.5 py-2 rounded-lg font-semibold transition flex-1 sm:flex-none text-center">
                    {{ $label }}
                </button>
            @endforeach
        </div>

        <div x-show="tab === 'overview'" x-cloak class="space-y-4 hidden lg:block">
            <div class="glass-card rounded-2xl ring-1 ring-brand/10 p-5">
                <h2 class="font-bold mb-3">{{ __('site.partner_portal.tab_overview') }}</h2>
                <p class="text-sm text-gray-600 mb-4">{{ __('site.partner_portal.valuation_customer_meet') }}</p>
                <dl class="grid grid-cols-2 gap-3 text-sm">
                    <div><dt class="text-gray-500 text-xs">{{ __('site.partner_portal.customer') }}</dt><dd class="font-medium">{{ $task->customer_name ?: '—' }}</dd></div>
                    <div><dt class="text-gray-500 text-xs">{{ __('site.partner_portal.phone') }}</dt><dd class="font-medium">{{ $task->customer_phone ?: '—' }}</dd></div>
                    <div class="col-span-2"><dt class="text-gray-500 text-xs">{{ __('site.partner_portal.location') }}</dt><dd class="font-medium">{{ $task->location ?: '—' }}</dd></div>
                    <div><dt class="text-gray-500 text-xs">{{ __('site.partner_portal.due') }}</dt><dd class="font-medium">{{ $task->due_at?->format('d M Y H:i') ?: '—' }}</dd></div>
                    <div><dt class="text-gray-500 text-xs">{{ __('site.partner_portal.your_payout') }}</dt><dd class="font-medium">{{ format_money($task->fee_amount) }}</dd></div>
                </dl>
            </div>
        </div>

        <div x-show="tab === 'asset'" x-cloak class="space-y-4 hidden lg:block">
            @forelse ($assets as $asset)
                <div class="glass-card rounded-2xl ring-1 ring-brand/10 p-5 space-y-3">
                    <h2 class="font-bold">{{ $asset->label }}</h2>
                    <p class="text-xs text-gray-500">{{ $asset->registration_number ?: \Illuminate\Support\Str::headline(str_replace('_', ' ', (string) $asset->asset_type)) }}</p>
                    <p class="text-sm text-gray-600">{{ __('site.partner_portal.valuation_angles_to_capture') }}</p>
                </div>
            @empty
                <div class="glass-card rounded-2xl ring-1 ring-brand/10 p-5 text-sm text-gray-600">{{ $task->vehicle_details ?: '—' }}</div>
            @endforelse
        </div>

        <div x-show="tab === 'inspect' || tab === 'overview' || tab === 'asset'" class="space-y-4" :class="(tab === 'overview' || tab === 'asset') ? 'lg:hidden' : ''">
            @if (! $started && $open)
                <div class="glass-card rounded-2xl ring-1 ring-brand/10 p-6 text-center space-y-3">
                    @if ($jobBlock === 'profile')
                        <p class="text-sm text-gray-600">{{ __('site.partner_portal.job_requires_profile') }}</p>
                        <a href="{{ route('site.partner.profile') }}" class="inline-flex w-full justify-center rounded-xl bg-brand-gold text-brand text-sm font-semibold px-5 py-3">{{ __('site.partner_portal.cta_complete_profile') }}</a>
                    @elseif ($jobBlock === 'payment')
                        <p class="text-sm text-gray-600">{{ __('site.partner_portal.job_requires_payment') }}</p>
                        <a href="{{ route($payRoute) }}" class="inline-flex w-full justify-center rounded-xl bg-brand-gold text-brand text-sm font-semibold px-5 py-3">{{ __('site.partner_portal.cta_pay_membership') }}</a>
                    @else
                        <p class="text-sm text-gray-600">{{ __('site.partner_portal.valuation_start_hint') }}</p>
                        <form method="POST" action="{{ route('site.partner.task.start', $task) }}">
                            @csrf
                            <button class="w-full rounded-xl bg-gray-900 text-white text-sm font-semibold px-5 py-3 hover:bg-black">{{ __('site.partner_portal.valuation_start_work') }}</button>
                        </form>
                    @endif
                </div>
            @else
                <div class="flex flex-wrap gap-2">
                    <button type="button" @click="tab = 'inspect'; step = 'photos'"
                            :class="step === 'photos' ? 'bg-brand text-white' : 'bg-white text-gray-700 ring-1 ring-gray-200'"
                            class="rounded-full px-3 py-1.5 text-xs font-semibold">{{ __('site.partner_portal.valuation_step_photos') }}</button>
                    @if ($needsVehicle)
                        <button type="button" @click="tab = 'inspect'; step = 'condition'"
                                :class="step === 'condition' ? 'bg-brand text-white' : 'bg-white text-gray-700 ring-1 ring-gray-200'"
                                class="rounded-full px-3 py-1.5 text-xs font-semibold">{{ __('site.partner_portal.valuation_step_condition') }}</button>
                        <button type="button" @click="step = 'engine'"
                                :class="step === 'engine' ? 'bg-brand text-white' : 'bg-white text-gray-700 ring-1 ring-gray-200'"
                                class="rounded-full px-3 py-1.5 text-xs font-semibold">{{ __('site.partner_portal.valuation_step_engine') }}</button>
                        <button type="button" @click="step = 'drive'"
                                :class="step === 'drive' ? 'bg-brand text-white' : 'bg-white text-gray-700 ring-1 ring-gray-200'"
                                class="rounded-full px-3 py-1.5 text-xs font-semibold">{{ __('site.partner_portal.valuation_step_drive') }}</button>
                    @endif
                    <button type="button" @click="tab = 'values'; step = 'values'"
                            :class="tab === 'values' ? 'bg-brand text-white' : 'bg-white text-gray-700 ring-1 ring-gray-200'"
                            class="rounded-full px-3 py-1.5 text-xs font-semibold">{{ __('site.partner_portal.valuation_step_values') }}</button>
                </div>

                <div x-show="step === 'photos'" class="space-y-4">
                    <p class="text-sm text-gray-600">{{ __('site.partner_portal.valuation_photos_intro') }}</p>
                    <p class="text-sm font-semibold text-gray-900">{{ __('site.partner_portal.valuation_photos_done', ['done' => $requiredDoneCount, 'total' => $requiredTotal]) }}</p>
                    @if ($photoSteps !== [])
                        <div class="flex items-center gap-1.5 overflow-x-auto pb-1" role="list" aria-label="{{ __('site.partner_portal.valuation_step_photos') }}">
                            @foreach ($photoSteps as $i => $s)
                                <button type="button" @click="photo = {{ $i }}"
                                        role="listitem"
                                        class="size-2.5 shrink-0 rounded-full transition"
                                        :class="photo === {{ $i }} ? 'bg-brand scale-125' : '{{ filled($s['path']) ? 'bg-emerald-500' : 'bg-gray-300' }}'"
                                        title="{{ $s['label'] }}"
                                        aria-label="{{ $s['label'] }}"></button>
                            @endforeach
                        </div>
                    @endif
                    @forelse ($photoSteps as $i => $s)
                        <template x-if="photo === {{ $i }}">
                            <div class="glass-card rounded-2xl ring-1 ring-brand/10 p-5 space-y-4" x-data="{ retake: {{ empty($s['path']) ? 'true' : 'false' }}, sending: false }">
                                <p class="text-[11px] uppercase tracking-widest text-brand font-semibold">{{ __('site.partner_portal.valuation_photo_progress', ['current' => $i + 1, 'total' => max(1, count($photoSteps))]) }}</p>
                                <h3 class="text-lg font-bold text-gray-900">{{ $s['label'] }}</h3>
                                <p class="text-sm text-gray-600">{{ $s['guidance'] ?? '' }}</p>
                                <p class="text-sm text-gray-500">{{ $s['asset_label'] }}</p>
                                @if (! ($s['required'] ?? true))
                                    <p class="text-xs text-gray-500">{{ __('site.partner_portal.valuation_optional') }}</p>
                                @endif

                                @php
                                    $stepAsset = $assets->firstWhere('id', $s['asset_id']);
                                    $borrowerAngles = array_keys(\App\Models\CustomerAsset::photoAngleLabels($stepAsset?->asset_type));
                                    $isOwnerAngle = in_array($s['angle'], $borrowerAngles, true);
                                @endphp
                                @if (! empty($s['borrower_path']))
                                    <div class="rounded-xl bg-brand-muted/40 ring-1 ring-brand/15 p-3">
                                        <p class="text-xs font-semibold text-brand mb-2">{{ __('site.partner_portal.valuation_owner_reference') }}</p>
                                        <img src="{{ asset('storage/'.$s['borrower_path']) }}" alt="" class="h-40 w-full object-cover rounded-lg ring-1 ring-brand/10">
                                    </div>
                                @elseif ($isOwnerAngle)
                                    <p class="text-xs text-amber-800 bg-amber-50 ring-1 ring-amber-200 rounded-xl px-3 py-2">{{ __('site.partner_portal.valuation_no_owner_photo') }}</p>
                                @else
                                    <p class="text-xs text-gray-600 bg-gray-50 ring-1 ring-gray-200 rounded-xl px-3 py-2">{{ __('site.partner_portal.valuation_valuer_only_angle') }}</p>
                                @endif

                                @if (! empty($s['path']))
                                    <img x-show="!retake" src="{{ asset('storage/'.$s['path']) }}" alt="" class="h-56 w-full object-cover rounded-xl ring-1 ring-gray-200">
                                @endif
                                @if ($open)
                                    <form x-show="retake" method="POST" action="{{ route('site.partner.task.inspect.photo', $task) }}" enctype="multipart/form-data" class="space-y-3"
                                          @submit="sending = true">
                                        @csrf
                                        <input type="hidden" name="customer_asset_id" value="{{ $s['asset_id'] }}">
                                        <input type="hidden" name="angle" value="{{ $s['angle'] }}">
                                        <x-site.single-image-document-upload
                                            name="file"
                                            :input-host-id="'val-photo-'.$s['asset_id'].'-'.$s['angle']"
                                            facing="environment"
                                            :required="empty($s['path'])"
                                            :camera-only="false"
                                            :auto-submit="false"
                                            :guide="$s['guidance'] ?? $s['label']"
                                        />
                                        <p x-show="sending" x-cloak class="text-sm font-semibold text-amber-800">{{ __('site.partner_portal.valuation_pending_upload') }}</p>
                                        <div class="flex flex-col sm:flex-row gap-2">
                                            <button type="submit" class="flex-1 rounded-xl bg-brand text-white text-sm font-semibold py-3">{{ __('site.partner_portal.valuation_use_photo') }}</button>
                                            <button type="button" @click="retake = true; $dispatch('clear-capture')" class="flex-1 rounded-xl ring-1 ring-gray-200 text-sm font-semibold py-3">{{ __('site.partner_portal.valuation_retake') }}</button>
                                        </div>
                                    </form>
                                @endif
                                <div class="flex items-center justify-between gap-3">
                                    @if ($i > 0)
                                        <button type="button" @click="photo = {{ $i - 1 }}" class="text-sm font-semibold text-brand">{{ __('site.partner_portal.valuation_photo_back') }}</button>
                                    @else
                                        <span></span>
                                    @endif
                                    <div class="flex items-center gap-3">
                                        @if (! ($s['required'] ?? true) && empty($s['path']))
                                            <button type="button" @click="photo = {{ min($i + 1, count($photoSteps) - 1) }}" class="text-sm font-semibold text-gray-600">{{ __('site.partner_portal.valuation_skip_optional') }}</button>
                                        @endif
                                        @if (! empty($s['path']))
                                            @if ($open)
                                                <button type="button" x-show="!retake" @click="retake = true" class="text-sm font-semibold text-brand hover:underline">{{ __('site.partner_portal.valuation_retake') }}</button>
                                            @endif
                                            @if ($i < count($photoSteps) - 1)
                                                <button type="button" x-show="!retake" @click="photo = {{ $i + 1 }}" class="rounded-lg bg-brand text-white text-sm font-semibold px-4 py-2">{{ __('site.partner_portal.valuation_continue') }}</button>
                                            @elseif ($photosDone && $needsVehicle)
                                                <button type="button" x-show="!retake" @click="step = 'condition'" class="rounded-lg bg-brand text-white text-sm font-semibold px-4 py-2">{{ __('site.partner_portal.valuation_continue') }}</button>
                                            @elseif ($photosDone)
                                                <button type="button" x-show="!retake" @click="tab = 'values'" class="rounded-lg bg-brand text-white text-sm font-semibold px-4 py-2">{{ __('site.partner_portal.valuation_continue') }}</button>
                                            @endif
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </template>
                    @empty
                        <p class="text-sm text-gray-600">{{ $task->vehicle_details ?: '—' }}</p>
                    @endforelse
                </div>

                @if ($needsVehicle)
                    <form method="POST" action="{{ route('site.partner.task.inspect.checks', $task) }}" x-show="step === 'condition'" x-cloak class="glass-card rounded-2xl ring-1 ring-brand/10 p-5 space-y-5">
                        @csrf
                        @if (filled($checks['engine'] ?? null))
                            <input type="hidden" name="engine" value="{{ $checks['engine'] }}">
                        @endif
                        @if (filled($checks['test_drive'] ?? null))
                            <input type="hidden" name="test_drive" value="{{ $checks['test_drive'] }}">
                        @endif
                        <div class="space-y-2">
                            <p class="text-sm font-semibold">{{ __('site.partner_portal.valuation_body_condition') }}</p>
                            @foreach ($inspection->bodyConditionOptions() as $code => $label)
                                <label class="flex items-start gap-3 rounded-xl ring-1 ring-gray-200 px-3 py-3 text-sm has-[:checked]:ring-brand has-[:checked]:bg-brand-muted/30">
                                    <input type="radio" name="body_condition" value="{{ $code }}" class="mt-0.5 text-brand" @checked(old('body_condition', $checks['body_condition'] ?? '') === $code) required>
                                    <span>{{ $label }}</span>
                                </label>
                            @endforeach
                        </div>
                        <div class="space-y-2">
                            <p class="text-sm font-semibold">{{ __('site.partner_portal.valuation_tyres') }}</p>
                            @foreach ($inspection->tyreOptions() as $code => $label)
                                <label class="flex items-start gap-3 rounded-xl ring-1 ring-gray-200 px-3 py-3 text-sm has-[:checked]:ring-brand has-[:checked]:bg-brand-muted/30">
                                    <input type="radio" name="tyres" value="{{ $code }}" class="mt-0.5 text-brand" @checked(old('tyres', $checks['tyres'] ?? '') === $code) required>
                                    <span>{{ $label }}</span>
                                </label>
                            @endforeach
                        </div>
                        <div class="space-y-2">
                            <p class="text-sm font-semibold">{{ __('site.partner_portal.valuation_interior') }}</p>
                            @foreach ($inspection->interiorOptions() as $code => $label)
                                <label class="flex items-start gap-3 rounded-xl ring-1 ring-gray-200 px-3 py-3 text-sm has-[:checked]:ring-brand has-[:checked]:bg-brand-muted/30">
                                    <input type="radio" name="interior" value="{{ $code }}" class="mt-0.5 text-brand" @checked(old('interior', $checks['interior'] ?? '') === $code) required>
                                    <span>{{ $label }}</span>
                                </label>
                            @endforeach
                        </div>
                        <button class="w-full rounded-xl bg-brand text-white text-sm font-semibold px-5 py-3">{{ __('site.partner_portal.valuation_continue') }}</button>
                    </form>

                    <form method="POST" action="{{ route('site.partner.task.inspect.checks', $task) }}" x-show="step === 'engine'" x-cloak class="glass-card rounded-2xl ring-1 ring-brand/10 p-5 space-y-3">
                        @csrf
                        <p class="text-sm text-gray-600">{{ __('site.partner_portal.valuation_engine_intro') }}</p>
                        @foreach ($inspection->engineOptions() as $code => $label)
                            <label class="flex items-start gap-3 rounded-xl ring-1 ring-gray-200 px-3 py-3 text-sm has-[:checked]:ring-brand has-[:checked]:bg-brand-muted/30">
                                <input type="radio" name="engine" value="{{ $code }}" class="mt-0.5 text-brand" @checked(old('engine', $checks['engine'] ?? '') === $code) required>
                                <span>{{ $label }}</span>
                            </label>
                        @endforeach
                        @if (filled($checks['test_drive'] ?? null))
                            <input type="hidden" name="test_drive" value="{{ $checks['test_drive'] }}">
                        @endif
                        <button class="w-full rounded-xl bg-brand text-white text-sm font-semibold px-5 py-3">{{ __('site.partner_portal.valuation_continue') }}</button>
                    </form>

                    <form method="POST" action="{{ route('site.partner.task.inspect.checks', $task) }}" x-show="step === 'drive'" x-cloak class="glass-card rounded-2xl ring-1 ring-brand/10 p-5 space-y-3">
                        @csrf
                        @if (filled($checks['engine'] ?? null))
                            <input type="hidden" name="engine" value="{{ $checks['engine'] }}">
                        @endif
                        <p class="text-sm text-gray-600">{{ __('site.partner_portal.valuation_drive_intro') }}</p>
                        @foreach ($inspection->driveOptions() as $code => $label)
                            <label class="flex items-start gap-3 rounded-xl ring-1 ring-gray-200 px-3 py-3 text-sm has-[:checked]:ring-brand has-[:checked]:bg-brand-muted/30">
                                <input type="radio" name="test_drive" value="{{ $code }}" class="mt-0.5 text-brand" @checked(old('test_drive', $checks['test_drive'] ?? '') === $code) required>
                                <span>{{ $label }}</span>
                            </label>
                        @endforeach
                        <button class="w-full rounded-xl bg-brand text-white text-sm font-semibold px-5 py-3">{{ __('site.partner_portal.valuation_continue') }}</button>
                    </form>
                @endif
            @endif
        </div>

        <div x-show="tab === 'values'" x-cloak>
            @if (! $photosDone || ($needsVehicle && (! $engineDone || ! $driveDone)))
                <div class="glass-card rounded-2xl ring-1 ring-amber-200 bg-amber-50 p-5 text-sm text-amber-950">
                    {{ __('site.partner_portal.valuation_photos_required', ['list' => implode(', ', $missingPhotos) ?: __('site.partner_portal.valuation_step_engine')]) }}
                </div>
            @elseif ($open)
                <form method="POST" action="{{ route('site.partner.task.complete', $task) }}" class="glass-card rounded-2xl ring-1 ring-brand/10 p-5 space-y-4"
                      @submit.prevent="window.confirmForm($el, {
                          title: @js(__('site.partner_portal.confirm.valuation_title')),
                          message: @js(__('site.partner_portal.confirm.valuation_message')),
                          confirmLabel: @js(__('site.partner_portal.confirm.task_complete_button')),
                          tone: 'warning',
                      })">
                    @csrf
                    <p class="text-sm text-gray-600">{{ __('site.partner_portal.valuation_values_intro') }}</p>
                    @foreach ($assets as $valAsset)
                        <div class="rounded-xl ring-1 ring-gray-200 p-4 space-y-3">
                            <p class="text-sm font-semibold">{{ $valAsset->label }}</p>
                            <x-site.numeric-input :name="'values['.$valAsset->id.'][market_value]'" :label="__('site.partner_portal.valuation_market_value')" money :required="true" />
                            <x-site.numeric-input :name="'values['.$valAsset->id.'][forced_sale_value]'" :label="__('site.partner_portal.valuation_fsv')" money :required="true" />
                        </div>
                    @endforeach
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">{{ __('site.partner_portal.notes_optional') }}</label>
                        <textarea name="notes" rows="3" class="w-full rounded-lg border-gray-300 text-sm">{{ old('notes') }}</textarea>
                    </div>
                    <button class="w-full rounded-xl bg-emerald-600 text-white text-sm font-semibold px-5 py-3 hover:bg-emerald-700">{{ __('site.partner_portal.valuation_submit_report') }}</button>
                </form>
            @else
                <div class="glass-card rounded-2xl ring-1 ring-brand/10 p-5 text-sm space-y-2">
                    <p>{{ __('site.partner_portal.valuation_market_value') }}: <span class="font-semibold">{{ format_money($assignment?->market_value) }}</span></p>
                    <p>{{ __('site.partner_portal.valuation_fsv') }}: <span class="font-semibold">{{ format_money($assignment?->forced_sale_value) }}</span></p>
                </div>
            @endif
        </div>
    </div>

    <div class="space-y-4 lg:sticky lg:top-24 self-start">
        <div class="glass-card rounded-2xl ring-1 ring-brand/10 p-5">
            <h3 class="font-bold mb-1">{{ __('site.partner_portal.next_step') }}</h3>
            <p class="text-sm font-semibold text-gray-900 mb-1">{{ $nextActionLabel }}</p>
            @if ($started && $open)
                <p class="text-xs text-gray-500 mb-3">{{ __('site.partner_portal.valuation_photos_done', ['done' => $requiredDoneCount, 'total' => $requiredTotal]) }}</p>
            @endif
            <div class="space-y-2">
                @if ($task->status === 'assigned' && $open)
                    @if ($jobBlock === 'profile')
                        <a href="{{ route('site.partner.profile') }}" class="block w-full text-center rounded-lg bg-brand-gold text-brand text-sm font-semibold py-3">{{ __('site.partner_portal.cta_complete_profile') }}</a>
                    @elseif ($jobBlock === 'payment')
                        <a href="{{ route($payRoute) }}" class="block w-full text-center rounded-lg bg-brand-gold text-brand text-sm font-semibold py-3">{{ __('site.partner_portal.cta_pay_membership') }}</a>
                    @else
                        <form method="POST" action="{{ route('site.partner.task.accept', $task) }}">
                            @csrf
                            <button class="w-full rounded-lg bg-brand text-white text-sm font-semibold py-3 hover:bg-brand-light">{{ __('site.partner_portal.accept_task') }}</button>
                        </form>
                        <button type="button" @click="declineOpen = true" class="w-full rounded-lg ring-1 ring-gray-200 text-sm font-semibold py-3">{{ __('site.partner_portal.decline_task') }}</button>
                    @endif
                @endif
                @if ($open && ! $started && $task->status !== 'assigned')
                    @if ($jobBlock === 'profile')
                        <a href="{{ route('site.partner.profile') }}" class="block w-full text-center rounded-lg bg-gray-900 text-white text-sm font-semibold py-3">{{ __('site.partner_portal.cta_complete_profile') }}</a>
                    @elseif ($jobBlock === 'payment')
                        <a href="{{ route($payRoute) }}" class="block w-full text-center rounded-lg bg-gray-900 text-white text-sm font-semibold py-3">{{ __('site.partner_portal.cta_pay_membership') }}</a>
                    @else
                        <form method="POST" action="{{ route('site.partner.task.start', $task) }}">
                            @csrf
                            <button class="w-full rounded-lg bg-gray-900 text-white text-sm font-semibold py-3 hover:bg-black">{{ __('site.partner_portal.valuation_start_work') }}</button>
                        </form>
                    @endif
                @elseif ($open && $started)
                    <button type="button" @click="tab = 'inspect'; step = '{{ $initialStep }}'" class="w-full rounded-lg bg-gray-900 text-white text-sm font-semibold py-3">{{ $nextActionLabel }}</button>
                @endif
            </div>
            <div class="mt-4 pt-4 border-t border-gray-100 text-xs space-y-1 text-gray-500">
                @if ($task->accepted_at)<p>{{ __('site.partner_portal.accepted') }} {{ $task->accepted_at->format('d M H:i') }}</p>@endif
                @if ($task->started_at)<p>{{ __('site.partner_portal.started') }} {{ $task->started_at->format('d M H:i') }}</p>@endif
                @if ($task->completed_at)<p>{{ __('site.partner_portal.completed_at') }} {{ $task->completed_at->format('d M H:i') }}</p>@endif
            </div>
        </div>
    </div>

    <div x-show="declineOpen" x-cloak class="fixed inset-0 z-[80] lg:col-span-3">
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
