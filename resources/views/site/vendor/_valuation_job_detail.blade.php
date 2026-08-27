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
    $vehicleCheckDone = ! $needsVehicle || ($conditionDone && $engineDone);
    $started = in_array($task->status, ['in_progress', 'completed'], true) || filled($task->started_at);
    $open = $task->isWritable();
    $reassigned = $task->status === 'cancelled';
    $initialTab = request('tab', $started ? 'inspect' : 'overview');
    $initialStep = ! $photosDone ? 'photos' : (! $vehicleCheckDone ? 'condition' : (! $driveDone ? 'drive' : 'values'));
    if (in_array(request('step'), ['photos', 'condition', 'drive', 'values'], true)) {
        $initialStep = (string) request('step');
    }
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
        ['key' => 'inspect', 'label' => __('site.partner_portal.tab_inspect'), 'done' => $photosDone && $vehicleCheckDone && $driveDone],
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
        $open && ! $vehicleCheckDone => __('site.partner_portal.valuation_step_vehicle'),
        $open && ! $driveDone => __('site.partner_portal.valuation_step_drive'),
        $open => __('site.partner_portal.valuation_step_values'),
        default => __('site.partner_portal.completed_at'),
    };
    $sectionLabels = [
        'overview' => __('site.partner_portal.tab_overview'),
        'asset' => __('site.partner_portal.tab_asset'),
        'inspect' => __('site.partner_portal.tab_inspect'),
        'values' => __('site.partner_portal.tab_values'),
    ];
    $photoAccents = [
        ['wrap' => 'bg-brand-muted/30 ring-brand/15', 'eyebrow' => 'text-brand'],
        ['wrap' => 'bg-[#fff8e8] ring-brand-gold/50', 'eyebrow' => 'text-amber-800'],
        ['wrap' => 'bg-emerald-50 ring-emerald-200/80', 'eyebrow' => 'text-emerald-800'],
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

<div class="rounded-2xl ring-1 ring-brand/15 overflow-hidden bg-brand-muted/25 p-4 sm:p-5 mb-5">
    <p class="text-[10px] uppercase tracking-[0.18em] text-brand font-bold">{{ __('site.partner_portal.valuation_job_eyebrow') }}</p>
    <h1 class="text-xl sm:text-2xl font-extrabold text-gray-900 tracking-tight mt-1 leading-tight">{{ $title }}</h1>
    <p class="text-sm font-bold text-gray-800 mt-2 leading-relaxed">{{ __('site.partner_portal.valuation_step_of', ['current' => $currentWorkflow, 'total' => 4, 'label' => $workflow[$currentWorkflow - 1]['label']]) }}</p>
    <p class="text-sm font-semibold text-gray-700 mt-1.5 leading-relaxed">{{ __('site.partner_portal.valuation_no_loan_hint') }}</p>
    <ol class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-sm">
        @foreach ($workflow as $i => $row)
            <li class="{{ $row['done'] ? 'text-emerald-700 font-semibold' : ($i + 1 === $currentWorkflow ? 'text-brand font-extrabold' : 'text-gray-400') }}">
                {{ $row['done'] ? '✓' : ($i + 1 === $currentWorkflow ? '●' : '○') }} {{ $row['label'] }}
            </li>
        @endforeach
    </ol>
    <div class="mt-3 flex items-center gap-2">
        <span class="px-3 py-1 rounded-full text-xs font-semibold {{ $priorityBadge }}">{{ $priority['label'] }} {{ __('site.partner_portal.priority_suffix') }}</span>
        <span class="px-3 py-1 rounded-full text-xs font-semibold {{ $badge }}">{{ str_replace('_',' ', $task->status) }}</span>
    </div>
</div>

<div class="grid lg:grid-cols-3 gap-6" x-data="{
    tab: @js($initialTab),
    step: @js($initialStep),
    photo: {{ $initialPhoto }},
    declineOpen: false,
    sectionOpen: false,
    sections: @js($sectionLabels),
    goPhoto(i) {
        this.photo = i;
        this.tab = 'inspect';
        this.step = 'photos';
        const url = new URL(window.location.href);
        url.searchParams.set('tab', 'inspect');
        url.searchParams.set('photo', String(i));
        url.searchParams.delete('step');
        history.replaceState({}, '', url);
    }
}">
    {{-- Next-step card first on mobile (Kopafasta branded), sticky on desktop --}}
    <div class="lg:col-start-3 lg:row-start-1 space-y-4 lg:sticky lg:top-24 self-start">
        <div class="rounded-2xl bg-brand text-white overflow-hidden ring-1 ring-brand/20 shadow-lg">
            <div class="px-5 pt-4 pb-5">
                <p class="text-[10px] uppercase tracking-[0.18em] text-brand-gold font-bold">Kopafasta</p>
                <h3 class="text-xl font-extrabold mt-1 leading-tight">{{ __('site.partner_portal.next_step') }}</h3>
                <p class="text-sm font-bold text-white mt-2 leading-relaxed">{{ $nextActionLabel }}</p>
                @if ($started && $open)
                    <p class="text-xs font-semibold text-white/80 mt-1">{{ __('site.partner_portal.valuation_photos_done', ['done' => $requiredDoneCount, 'total' => $requiredTotal]) }}</p>
                @endif
                <div class="mt-4 space-y-2">
                    @if ($task->status === 'assigned' && $open)
                        @if ($jobBlock === 'profile')
                            <a href="{{ route('site.partner.profile') }}" class="block w-full text-center rounded-xl bg-brand-gold text-brand text-sm font-extrabold py-3">{{ __('site.partner_portal.cta_complete_profile') }}</a>
                        @elseif ($jobBlock === 'payment')
                            <a href="{{ route($payRoute) }}" class="block w-full text-center rounded-xl bg-brand-gold text-brand text-sm font-extrabold py-3">{{ __('site.partner_portal.cta_pay_membership') }}</a>
                        @else
                            <form method="POST" action="{{ route('site.partner.task.accept', $task) }}">
                                @csrf
                                <button class="w-full rounded-xl bg-brand-gold text-brand text-sm font-extrabold py-3">{{ __('site.partner_portal.accept_task') }}</button>
                            </form>
                            <button type="button" @click="declineOpen = true" class="w-full rounded-xl bg-white/10 ring-1 ring-white/25 text-sm font-semibold py-3">{{ __('site.partner_portal.decline_task') }}</button>
                        @endif
                    @endif
                    @if ($open && ! $started && $task->status !== 'assigned')
                        @if ($jobBlock === 'profile')
                            <a href="{{ route('site.partner.profile') }}" class="block w-full text-center rounded-xl bg-brand-gold text-brand text-sm font-extrabold py-3">{{ __('site.partner_portal.cta_complete_profile') }}</a>
                        @elseif ($jobBlock === 'payment')
                            <a href="{{ route($payRoute) }}" class="block w-full text-center rounded-xl bg-brand-gold text-brand text-sm font-extrabold py-3">{{ __('site.partner_portal.cta_pay_membership') }}</a>
                        @else
                            <form method="POST" action="{{ route('site.partner.task.start', $task) }}">
                                @csrf
                                <button class="w-full rounded-xl bg-brand-gold text-brand text-sm font-extrabold py-3">{{ __('site.partner_portal.valuation_start_work') }}</button>
                            </form>
                        @endif
                    @elseif ($open && $started)
                        <button type="button" @click="tab = 'inspect'; step = '{{ $initialStep }}'" class="w-full rounded-xl bg-brand-gold text-brand text-sm font-extrabold py-3">{{ $nextActionLabel }}</button>
                    @endif
                </div>
                <div class="mt-4 pt-4 border-t border-white/15 text-xs space-y-1 text-white/70">
                    @if ($task->accepted_at)<p>{{ __('site.partner_portal.accepted') }} {{ $task->accepted_at->format('d M H:i') }}</p>@endif
                    @if ($task->started_at)<p>{{ __('site.partner_portal.started') }} {{ $task->started_at->format('d M H:i') }}</p>@endif
                    @if ($task->completed_at)<p>{{ __('site.partner_portal.completed_at') }} {{ $task->completed_at->format('d M H:i') }}</p>@endif
                </div>
            </div>
        </div>
    </div>

    <div class="lg:col-span-2 lg:col-start-1 lg:row-start-1 space-y-4">
        <div>
            <div class="relative hidden lg:block max-w-xs">
                <button type="button" @click="sectionOpen = !sectionOpen"
                        class="w-full text-left rounded-xl ring-1 ring-brand/15 bg-white px-4 py-3 text-sm font-bold flex items-center justify-between gap-3">
                    <span x-text="sections[tab] || sections.inspect"></span>
                    <span class="text-gray-400" aria-hidden="true">▾</span>
                </button>
                <div x-show="sectionOpen" x-cloak @click.outside="sectionOpen = false"
                     class="absolute z-20 mt-1 w-full rounded-xl bg-white ring-1 ring-gray-200 shadow-lg overflow-hidden">
                    @foreach ($sectionLabels as $key => $label)
                        <button type="button" @click="tab = '{{ $key }}'; sectionOpen = false"
                                class="w-full text-left px-4 py-3 text-sm font-semibold hover:bg-brand-muted/40"
                                :class="tab === '{{ $key }}' ? 'bg-brand-muted text-brand' : 'text-gray-800'">
                            {{ $label }}
                        </button>
                    @endforeach
                </div>
            </div>
            <button type="button" @click="sectionOpen = true"
                    class="lg:hidden w-full text-left rounded-xl ring-1 ring-brand/15 bg-white px-4 py-3.5 text-sm font-extrabold flex items-center justify-between">
                <span x-text="sections[tab] || sections.inspect"></span>
                <span class="text-gray-400" aria-hidden="true">▾</span>
            </button>
            <template x-teleport="body">
                <div x-show="sectionOpen" x-cloak class="lg:hidden fixed inset-0 z-[80]" role="dialog" aria-modal="true">
                    <div class="absolute inset-0 bg-black/40" @click="sectionOpen = false"></div>
                    <div class="absolute inset-x-0 bottom-0 bg-white rounded-t-2xl p-5 space-y-2"
                         style="padding-bottom: max(1.25rem, env(safe-area-inset-bottom))">
                        <div class="flex justify-center pb-1"><div class="w-10 h-1 rounded-full bg-gray-300"></div></div>
                        <p class="font-extrabold">{{ __('site.partner_portal.valuation_job_eyebrow') }}</p>
                        @foreach ($sectionLabels as $key => $label)
                            <button type="button" @click="tab = '{{ $key }}'; sectionOpen = false"
                                    class="w-full text-left rounded-xl ring-1 ring-gray-200 px-4 py-3.5 text-sm font-semibold"
                                    :class="tab === '{{ $key }}' ? 'ring-brand bg-brand-muted/40 text-brand' : ''">
                                {{ $label }}
                            </button>
                        @endforeach
                    </div>
                </div>
            </template>
        </div>

        <div x-show="tab === 'overview'" x-cloak class="space-y-4 hidden lg:block">
            <div class="rounded-2xl ring-1 ring-brand/15 overflow-hidden bg-brand-muted/25 p-4 sm:p-5">
                <p class="text-[10px] uppercase tracking-[0.18em] text-brand font-bold">{{ __('site.partner_portal.tab_overview') }}</p>
                <h2 class="text-lg font-extrabold text-gray-900 mt-1 leading-tight">{{ __('site.partner_portal.tab_overview') }}</h2>
                <p class="text-sm font-bold text-gray-800 mt-2 leading-relaxed">{{ __('site.partner_portal.valuation_customer_meet') }}</p>
                <dl class="grid grid-cols-2 gap-3 text-sm mt-4">
                    <div><dt class="text-gray-500 text-xs font-semibold">{{ __('site.partner_portal.customer') }}</dt><dd class="font-extrabold text-gray-900 mt-0.5">{{ $task->customer_name ?: '—' }}</dd></div>
                    <div><dt class="text-gray-500 text-xs font-semibold">{{ __('site.partner_portal.phone') }}</dt><dd class="font-extrabold text-gray-900 mt-0.5">{{ $task->customer_phone ?: '—' }}</dd></div>
                    <div class="col-span-2"><dt class="text-gray-500 text-xs font-semibold">{{ __('site.partner_portal.location') }}</dt><dd class="font-extrabold text-gray-900 mt-0.5">{{ $task->location ?: '—' }}</dd></div>
                    <div><dt class="text-gray-500 text-xs font-semibold">{{ __('site.partner_portal.due') }}</dt><dd class="font-extrabold text-gray-900 mt-0.5">{{ $task->due_at?->format('d M Y H:i') ?: '—' }}</dd></div>
                    <div><dt class="text-gray-500 text-xs font-semibold">{{ __('site.partner_portal.your_payout') }}</dt><dd class="font-extrabold text-gray-900 mt-0.5">{{ format_money($task->fee_amount) }}</dd></div>
                </dl>
            </div>
        </div>

        <div x-show="tab === 'asset'" x-cloak class="space-y-4 hidden lg:block">
            @forelse ($assets as $asset)
                <div class="rounded-2xl ring-1 ring-brand/15 overflow-hidden bg-brand-muted/25 p-4 sm:p-5 space-y-2">
                    <p class="text-[10px] uppercase tracking-[0.18em] text-brand font-bold">{{ __('site.partner_portal.tab_asset') }}</p>
                    <h2 class="text-lg font-extrabold text-gray-900 leading-tight">{{ $asset->label }}</h2>
                    <p class="text-sm font-semibold text-gray-700">{{ $asset->registration_number ?: \Illuminate\Support\Str::headline(str_replace('_', ' ', (string) $asset->asset_type)) }}</p>
                    <p class="text-sm font-bold text-gray-800 leading-relaxed">{{ __('site.partner_portal.valuation_angles_to_capture') }}</p>
                </div>
            @empty
                <div class="rounded-2xl ring-1 ring-brand/15 bg-brand-muted/25 p-5 text-sm font-semibold text-gray-700">{{ $task->vehicle_details ?: '—' }}</div>
            @endforelse
        </div>

        <div x-show="tab === 'inspect' || tab === 'overview' || tab === 'asset'" class="space-y-4" :class="(tab === 'overview' || tab === 'asset') ? 'lg:hidden' : ''">
            @if (! $started && $open)
                <div class="rounded-2xl ring-1 ring-brand/15 bg-brand-muted/25 p-6 text-center space-y-3">
                    @if ($jobBlock === 'profile')
                        <p class="text-sm font-bold text-gray-800 leading-relaxed">{{ __('site.partner_portal.job_requires_profile') }}</p>
                        <a href="{{ route('site.partner.profile') }}" class="inline-flex w-full justify-center rounded-xl bg-brand-gold text-brand text-sm font-extrabold px-5 py-3">{{ __('site.partner_portal.cta_complete_profile') }}</a>
                    @elseif ($jobBlock === 'payment')
                        <p class="text-sm font-bold text-gray-800 leading-relaxed">{{ __('site.partner_portal.job_requires_payment') }}</p>
                        <a href="{{ route($payRoute) }}" class="inline-flex w-full justify-center rounded-xl bg-brand-gold text-brand text-sm font-extrabold px-5 py-3">{{ __('site.partner_portal.cta_pay_membership') }}</a>
                    @else
                        <p class="text-sm font-bold text-gray-800 leading-relaxed">{{ __('site.partner_portal.valuation_start_hint') }}</p>
                        <form method="POST" action="{{ route('site.partner.task.start', $task) }}">
                            @csrf
                            <button class="w-full rounded-xl bg-gray-900 text-white text-sm font-extrabold px-5 py-3 hover:bg-black">{{ __('site.partner_portal.valuation_start_work') }}</button>
                        </form>
                    @endif
                </div>
            @else
                <div x-show="step === 'photos'" class="space-y-4">
                    @if ($photoSteps !== [])
                        <div class="flex gap-2 overflow-x-auto pb-1" role="list" aria-label="{{ __('site.partner_portal.valuation_photos_gallery') }}">
                            @foreach ($photoSteps as $i => $s)
                                <button type="button" @click="goPhoto({{ $i }})"
                                        role="listitem"
                                        class="shrink-0 w-16 space-y-1">
                                    <span class="block aspect-square rounded-xl overflow-hidden ring-2 bg-gray-100"
                                          :class="photo === {{ $i }} ? 'ring-brand' : '{{ filled($s['path']) ? 'ring-emerald-400' : 'ring-gray-200' }}'">
                                        @if (! empty($s['path']))
                                            <img src="{{ asset('storage/'.$s['path']) }}" alt="{{ $s['label'] }}" class="h-full w-full object-contain bg-white">
                                        @else
                                            <span class="h-full w-full grid place-items-center text-[10px] font-bold text-gray-400">{{ $i + 1 }}</span>
                                        @endif
                                    </span>
                                    <span class="block text-[10px] font-bold text-gray-600 truncate">{{ $s['label'] }}</span>
                                </button>
                            @endforeach
                        </div>
                    @endif
                    @forelse ($photoSteps as $i => $s)
                        @php $accent = $photoAccents[$i % count($photoAccents)]; @endphp
                        <template x-if="photo === {{ $i }}">
                            <div class="rounded-2xl ring-1 {{ $accent['wrap'] }} overflow-hidden p-4 sm:p-5 space-y-3"
                                 x-data="{ retake: {{ empty($s['path']) ? 'true' : 'false' }}, sending: false, hasPreview: false }">
                                <p class="text-[10px] uppercase tracking-[0.18em] font-bold {{ $accent['eyebrow'] }} leading-relaxed">{{ __('site.partner_portal.valuation_photo_progress', ['current' => $i + 1, 'total' => max(1, count($photoSteps))]) }}</p>
                                <h3 class="text-2xl font-extrabold text-gray-900 leading-tight">{{ $s['label'] }}</h3>
                                @if (filled($s['guidance'] ?? null))
                                    <p class="text-sm font-bold text-gray-800 leading-relaxed">{{ $s['guidance'] }}</p>
                                @endif
                                <p class="text-sm font-semibold text-brand">{{ $s['asset_label'] }}</p>
                                @if (! ($s['required'] ?? true))
                                    <p class="text-xs font-semibold text-gray-500">{{ __('site.partner_portal.valuation_optional') }}</p>
                                @endif

                                @php
                                    $stepAsset = $assets->firstWhere('id', $s['asset_id']);
                                    $borrowerAngles = array_keys(\App\Models\CustomerAsset::photoAngleLabels($stepAsset?->asset_type));
                                    $isOwnerAngle = in_array($s['angle'], $borrowerAngles, true);
                                @endphp

                                <div class="grid grid-cols-2 gap-3">
                                    <div class="rounded-xl bg-white/80 ring-1 ring-brand/10 p-2">
                                        <p class="text-[10px] font-bold uppercase tracking-widest text-brand mb-1.5 leading-relaxed">{{ __('site.partner_portal.valuation_owner_reference') }}</p>
                                        @if (! empty($s['borrower_path']))
                                            <img src="{{ asset('storage/'.$s['borrower_path']) }}" alt="" class="aspect-square w-full object-contain bg-gray-50 rounded-lg">
                                        @elseif ($isOwnerAngle)
                                            <p class="aspect-square grid place-items-center text-[11px] font-semibold text-amber-800 bg-amber-50 rounded-lg px-2 text-center">{{ __('site.partner_portal.valuation_no_owner_photo') }}</p>
                                        @else
                                            <p class="aspect-square grid place-items-center text-[11px] font-semibold text-gray-600 bg-gray-50 rounded-lg px-2 text-center">{{ __('site.partner_portal.valuation_valuer_only_angle') }}</p>
                                        @endif
                                    </div>
                                    <div class="rounded-xl bg-white/80 ring-1 ring-brand/10 p-2">
                                        <p class="text-[10px] font-bold uppercase tracking-widest text-brand mb-1.5 leading-relaxed">{{ __('site.partner_portal.valuation_your_photo') }}</p>
                                        @if (! empty($s['path']))
                                            <img x-show="!retake" src="{{ asset('storage/'.$s['path']) }}" alt="" class="aspect-square w-full object-contain bg-gray-50 rounded-lg ring-1 ring-gray-100">
                                        @endif
                                        <div x-show="retake && !hasPreview" class="aspect-square grid place-items-center text-[11px] font-semibold text-gray-400 bg-gray-50 rounded-lg">{{ __('site.partner_portal.valuation_take_next_photo') }}</div>
                                    </div>
                                </div>

                                @if ($open)
                                    <form x-show="retake" method="POST" action="{{ route('site.partner.task.inspect.photo', $task) }}" enctype="multipart/form-data" class="space-y-3"
                                          @doc-preview="hasPreview = $event.detail.filled"
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
                                            :large-preview="true"
                                            :guide="$s['guidance'] ?? $s['label']"
                                        />
                                        <p x-show="sending" x-cloak class="text-sm font-semibold text-amber-800">{{ __('site.partner_portal.valuation_pending_upload') }}</p>
                                        <div x-show="hasPreview" x-cloak class="flex flex-col sm:flex-row gap-2">
                                            <button type="submit" class="flex-1 rounded-xl bg-brand text-white text-sm font-extrabold py-3">{{ __('site.partner_portal.valuation_use_photo') }}</button>
                                            <button type="button" @click="hasPreview = false; $dispatch('clear-capture', { hostId: @js('val-photo-'.$s['asset_id'].'-'.$s['angle']) })" class="flex-1 rounded-xl ring-1 ring-gray-200 text-sm font-bold py-3">{{ __('site.partner_portal.valuation_retake') }}</button>
                                        </div>
                                    </form>
                                @endif
                                <div class="flex items-center justify-between gap-3 pt-1">
                                    @if (! ($s['required'] ?? true) && empty($s['path']))
                                        <button type="button" @click="goPhoto({{ min($i + 1, count($photoSteps) - 1) }})" class="text-sm font-semibold text-gray-600">{{ __('site.partner_portal.valuation_skip_optional') }}</button>
                                    @else
                                        <span></span>
                                    @endif
                                    <div class="flex items-center gap-3">
                                        @if (! empty($s['path']))
                                            @if ($open)
                                                <button type="button" x-show="!retake" @click="retake = true; hasPreview = false" class="text-sm font-bold text-brand">{{ __('site.partner_portal.valuation_retake') }}</button>
                                            @endif
                                            @if ($i < count($photoSteps) - 1)
                                                <button type="button" x-show="!retake" @click="goPhoto({{ $i + 1 }})" class="rounded-xl bg-brand text-white text-sm font-extrabold px-5 py-2.5">{{ __('site.partner_portal.valuation_continue') }}</button>
                                            @elseif ($photosDone && $needsVehicle)
                                                <button type="button" x-show="!retake" @click="step = 'condition'" class="rounded-xl bg-brand text-white text-sm font-extrabold px-5 py-2.5">{{ __('site.partner_portal.valuation_continue') }}</button>
                                            @elseif ($photosDone)
                                                <button type="button" x-show="!retake" @click="tab = 'values'" class="rounded-xl bg-brand text-white text-sm font-extrabold px-5 py-2.5">{{ __('site.partner_portal.valuation_continue') }}</button>
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
                    <form method="POST" action="{{ route('site.partner.task.inspect.checks', $task) }}" x-show="step === 'condition'" x-cloak
                          class="rounded-2xl ring-1 ring-brand/15 bg-brand-muted/25 p-4 sm:p-5 space-y-5">
                        @csrf
                        @if (filled($checks['test_drive'] ?? null))
                            <input type="hidden" name="test_drive" value="{{ $checks['test_drive'] }}">
                        @endif
                        <div>
                            <p class="text-[10px] uppercase tracking-[0.18em] text-brand font-bold">{{ __('site.partner_portal.valuation_step_vehicle') }}</p>
                            <h3 class="text-xl font-extrabold text-gray-900 mt-1 leading-tight">{{ __('site.partner_portal.valuation_step_vehicle') }}</h3>
                            <p class="text-sm font-bold text-gray-800 mt-2 leading-relaxed">{{ __('site.partner_portal.valuation_vehicle_check_intro') }}</p>
                        </div>
                        <x-site.sheet-select name="body_condition" :label="__('site.partner_portal.valuation_body_condition')" :options="$inspection->bodyConditionOptions()" :value="old('body_condition', $checks['body_condition'] ?? '')" />
                        <x-site.sheet-select name="tyres" :label="__('site.partner_portal.valuation_tyres')" :options="$inspection->tyreOptions()" :value="old('tyres', $checks['tyres'] ?? '')" />
                        <x-site.sheet-select name="interior" :label="__('site.partner_portal.valuation_interior')" :options="$inspection->interiorOptions()" :value="old('interior', $checks['interior'] ?? '')" />
                        <x-site.sheet-select name="engine" :label="__('site.partner_portal.valuation_step_engine')" :options="$inspection->engineOptions()" :value="old('engine', $checks['engine'] ?? '')" />
                        <button class="w-full rounded-xl bg-brand text-white text-sm font-extrabold px-5 py-3">{{ __('site.partner_portal.valuation_continue') }}</button>
                    </form>

                    <form method="POST" action="{{ route('site.partner.task.inspect.checks', $task) }}" x-show="step === 'drive'" x-cloak
                          class="rounded-2xl ring-1 ring-brand-gold/40 bg-[#fff8e8] p-4 sm:p-5 space-y-4">
                        @csrf
                        @if (filled($checks['engine'] ?? null))
                            <input type="hidden" name="engine" value="{{ $checks['engine'] }}">
                        @endif
                        <div>
                            <p class="text-[10px] uppercase tracking-[0.18em] text-amber-800 font-bold">{{ __('site.partner_portal.valuation_step_drive') }}</p>
                            <h3 class="text-xl font-extrabold text-gray-900 mt-1 leading-tight">{{ __('site.partner_portal.valuation_step_drive') }}</h3>
                            <p class="text-sm font-bold text-gray-800 mt-2 leading-relaxed">{{ __('site.partner_portal.valuation_drive_intro') }}</p>
                        </div>
                        <x-site.sheet-select name="test_drive" :label="__('site.partner_portal.valuation_step_drive')" :options="$inspection->driveOptions()" :value="old('test_drive', $checks['test_drive'] ?? '')" />
                        <button class="w-full rounded-xl bg-brand text-white text-sm font-extrabold px-5 py-3">{{ __('site.partner_portal.valuation_continue') }}</button>
                    </form>
                @endif
            @endif
        </div>

        <div x-show="tab === 'values'" x-cloak>
            @if (! $photosDone || ($needsVehicle && (! $engineDone || ! $driveDone)))
                <div class="rounded-2xl ring-1 ring-amber-200 bg-amber-50 p-5 text-sm font-bold text-amber-950 leading-relaxed">
                    {{ __('site.partner_portal.valuation_photos_required', ['list' => implode(', ', $missingPhotos) ?: __('site.partner_portal.valuation_step_engine')]) }}
                </div>
            @elseif ($open)
                <form method="POST" action="{{ route('site.partner.task.complete', $task) }}" class="rounded-2xl ring-1 ring-brand/15 bg-brand-muted/25 p-4 sm:p-5 space-y-4"
                      @submit.prevent="window.confirmForm($el, {
                          title: @js(__('site.partner_portal.confirm.valuation_title')),
                          message: @js(__('site.partner_portal.confirm.valuation_message')),
                          confirmLabel: @js(__('site.partner_portal.confirm.task_complete_button')),
                          tone: 'warning',
                      })">
                    @csrf
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
                    <button class="w-full rounded-xl bg-emerald-600 text-white text-sm font-extrabold px-5 py-3 hover:bg-emerald-700">{{ __('site.partner_portal.valuation_submit_report') }}</button>
                </form>
            @else
                <div class="rounded-2xl ring-1 ring-brand/15 bg-brand-muted/25 p-5 text-sm space-y-2">
                    <p>{{ __('site.partner_portal.valuation_market_value') }}: <span class="font-semibold">{{ format_money($assignment?->market_value) }}</span></p>
                    <p>{{ __('site.partner_portal.valuation_fsv') }}: <span class="font-semibold">{{ format_money($assignment?->forced_sale_value) }}</span></p>
                </div>
            @endif
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
