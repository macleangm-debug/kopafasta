@php
    $inspection = app(\App\Services\ValuationInspectionService::class);
    $assignment = $task->valuationAssignment
        ?? \App\Models\ValuationAssignment::query()->where('partner_task_id', $task->id)->first();
    $assets = $collateralAssets->isNotEmpty()
        ? $collateralAssets
        : $inspection->assetsForTask($task);
    $valuerPhotos = $inspection->valuerPhotosByAsset($task, $assets);
    $checks = $assignment ? $inspection->checksSummary($assignment) : ['engine' => null, 'test_drive' => null];
    $needsVehicle = $assets->contains(fn ($asset) => $asset->isVehicleLike());
    $missingPhotos = $inspection->missingValuerAngles($task, $assets);
    $photosDone = $missingPhotos === [];
    $engineDone = ! $needsVehicle || filled($checks['engine'] ?? null);
    $driveDone = ! $needsVehicle || filled($checks['test_drive'] ?? null);
    $started = in_array($task->status, ['in_progress', 'completed'], true) || filled($task->started_at);
    $open = ! in_array($task->status, ['completed', 'cancelled'], true);
    $initialTab = request('tab', $started ? 'inspect' : 'overview');
    $initialStep = ! $photosDone ? 'photos' : (! $engineDone ? 'engine' : (! $driveDone ? 'drive' : 'values'));
    $photoSteps = $inspection->photoSteps($task, $assets);
    $requestedPhoto = (int) request('photo', -1);
    $firstOpenPhoto = collect($photoSteps)->search(fn ($step) => blank($step['path'] ?? null));
    $initialPhoto = ($requestedPhoto >= 0 && isset($photoSteps[$requestedPhoto]))
        ? $requestedPhoto
        : ($firstOpenPhoto === false ? 0 : (int) $firstOpenPhoto);
    $jobBlock = app(\App\Services\PartnerProfileService::class)->jobBlockReason($vendor);
    $payRoute = $vendor->isAffiliate() ? 'site.affiliate.membership.pay' : 'site.partner.membership.pay';
    $title = $assets->pluck('label')->filter()->implode(' · ') ?: ($task->vehicle_details ?: __('site.partner_portal.valuation_job_eyebrow'));
@endphp

<div class="mb-5">
    <a href="{{ route('site.partner.tasks') }}" data-kf-motion="pop" class="text-sm text-brand hover:underline">← {{ __('site.partner_portal.back_to_valuation_jobs') }}</a>
</div>

<div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-4 mb-6">
    <div class="min-w-0">
        <p class="text-[11px] uppercase tracking-[0.18em] text-brand font-bold mb-1">{{ __('site.partner_portal.valuation_job_eyebrow') }}</p>
        <h1 class="text-2xl sm:text-3xl font-extrabold text-gray-900 tracking-tight truncate">{{ $title }}</h1>
        <p class="text-sm text-gray-500 mt-1">{{ __('site.partner_portal.valuation_job_meta', ['id' => $task->id, 'date' => $task->created_at->format('d M Y')]) }}</p>
        <p class="text-sm text-gray-600 mt-2 max-w-xl">{{ __('site.partner_portal.valuation_no_loan_hint') }}</p>
    </div>
    <div class="flex items-center gap-2 shrink-0">
        <span class="px-3 py-1 rounded-full text-xs font-semibold {{ $priorityBadge }}">{{ $priority['label'] }} {{ __('site.partner_portal.priority_suffix') }}</span>
        <span class="px-3 py-1 rounded-full text-xs font-semibold {{ $badge }}">{{ str_replace('_',' ', $task->status) }}</span>
    </div>
</div>

<div class="grid lg:grid-cols-3 gap-6" x-data="{ tab: @js($initialTab), step: @js($initialStep), photo: {{ $initialPhoto }} }">
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

        <div x-show="tab === 'overview'" x-cloak class="space-y-4">
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

        <div x-show="tab === 'asset'" x-cloak class="space-y-4">
            @forelse ($assets as $asset)
                @php
                    $angles = \App\Models\CustomerAsset::photoAngleLabels($asset->asset_type);
                @endphp
                <div class="glass-card rounded-2xl ring-1 ring-brand/10 p-5 space-y-3">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h2 class="font-bold">{{ $asset->label }}</h2>
                            <p class="text-xs text-gray-500">{{ $asset->registration_number ?: \Illuminate\Support\Str::headline(str_replace('_', ' ', (string) $asset->asset_type)) }}</p>
                        </div>
                    </div>
                    <p class="text-sm text-gray-600">{{ __('site.partner_portal.valuation_angles_to_capture') }}</p>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($angles as $angleLabel)
                            <span class="rounded-full bg-gray-50 ring-1 ring-gray-200 px-3 py-1 text-xs font-semibold text-gray-700">{{ $angleLabel }}</span>
                        @endforeach
                    </div>
                </div>
            @empty
                <div class="glass-card rounded-2xl ring-1 ring-brand/10 p-5 text-sm text-gray-600">{{ $task->vehicle_details ?: '—' }}</div>
            @endforelse
        </div>

        <div x-show="tab === 'inspect'" x-cloak class="space-y-4">
            @if (! $started && $open)
                <div class="glass-card rounded-2xl ring-1 ring-brand/10 p-6 text-center space-y-3">
                    @if ($jobBlock === 'profile')
                        <p class="text-sm text-gray-600">{{ __('site.partner_portal.job_requires_profile') }}</p>
                        <a href="{{ route('site.partner.profile') }}" class="inline-flex rounded-xl bg-brand-gold text-brand text-sm font-semibold px-5 py-2.5">{{ __('site.partner_portal.cta_complete_profile') }}</a>
                    @elseif ($jobBlock === 'payment')
                        <p class="text-sm text-gray-600">{{ __('site.partner_portal.job_requires_payment') }}</p>
                        <a href="{{ route($payRoute) }}" class="inline-flex rounded-xl bg-brand-gold text-brand text-sm font-semibold px-5 py-2.5">{{ __('site.partner_portal.cta_pay_membership') }}</a>
                    @else
                        <p class="text-sm text-gray-600">{{ __('site.partner_portal.valuation_start_hint') }}</p>
                        <form method="POST" action="{{ route('site.partner.task.start', $task) }}">
                            @csrf
                            <button class="rounded-xl bg-gray-900 text-white text-sm font-semibold px-5 py-2.5 hover:bg-black">{{ __('site.partner_portal.valuation_start_work') }}</button>
                        </form>
                    @endif
                </div>
            @else
                <div class="flex flex-wrap gap-2">
                    <button type="button" @click="step = 'photos'"
                            :class="step === 'photos' ? 'bg-brand text-white' : 'bg-white text-gray-700 ring-1 ring-gray-200'"
                            class="rounded-full px-3 py-1.5 text-xs font-semibold">{{ __('site.partner_portal.valuation_step_photos') }}</button>
                    @if ($needsVehicle)
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

                <div x-show="step === 'photos'" class="space-y-5">
                    <p class="text-sm text-gray-600">{{ __('site.partner_portal.valuation_photos_intro') }}</p>
                    @forelse ($photoSteps as $i => $s)
                        <div x-show="photo === {{ $i }}" x-cloak class="glass-card rounded-2xl ring-1 ring-brand/10 p-5 space-y-4" x-data="{ retake: {{ empty($s['path']) ? 'true' : 'false' }}, sending: false }">
                            <p class="text-[11px] uppercase tracking-widest text-brand font-semibold">{{ __('site.partner_portal.valuation_photo_progress', ['current' => $i + 1, 'total' => count($photoSteps)]) }}</p>
                            <h3 class="text-lg font-bold text-gray-900">{{ $s['label'] }}</h3>
                            <p class="text-sm text-gray-500">{{ $s['asset_label'] }}</p>
                            @if (! empty($s['path']))
                                <img x-show="!retake" src="{{ asset('storage/'.$s['path']) }}" alt="" class="h-56 w-full object-cover rounded-xl ring-1 ring-gray-200">
                            @endif
                            @if ($open)
                                <form x-show="retake" method="POST" action="{{ route('site.partner.task.inspect.photo', $task) }}" enctype="multipart/form-data" class="space-y-3"
                                      @submit="sending = true">
                                    @csrf
                                    <input type="hidden" name="customer_asset_id" value="{{ $s['asset_id'] }}">
                                    <input type="hidden" name="angle" value="{{ $s['angle'] }}">
                                    <x-site.single-image-document-upload name="file" facing="environment" :required="empty($s['path'])" :camera-only="true" :auto-submit="true" />
                                </form>
                            @endif
                            <div class="flex items-center justify-end gap-3">
                                @if (! empty($s['path']))
                                    <div class="flex items-center gap-3">
                                        @if ($open)
                                            <button type="button" x-show="!retake" @click="retake = true" class="text-sm font-semibold text-brand hover:underline">{{ __('site.partner_portal.valuation_retake') }}</button>
                                        @endif
                                        @if ($i < count($photoSteps) - 1)
                                            <button type="button" x-show="!retake" @click="photo = {{ $i + 1 }}" class="rounded-lg bg-brand text-white text-sm font-semibold px-4 py-2">{{ __('site.partner_portal.valuation_continue') }}</button>
                                        @elseif ($photosDone && $needsVehicle)
                                            <button type="button" x-show="!retake" @click="step = 'engine'" class="rounded-lg bg-brand text-white text-sm font-semibold px-4 py-2">{{ __('site.partner_portal.valuation_continue') }}</button>
                                        @elseif ($photosDone)
                                            <button type="button" x-show="!retake" @click="tab = 'values'" class="rounded-lg bg-brand text-white text-sm font-semibold px-4 py-2">{{ __('site.partner_portal.valuation_continue') }}</button>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-gray-600">{{ $task->vehicle_details ?: '—' }}</p>
                    @endforelse
                </div>

                @if ($needsVehicle)
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
                        <button class="rounded-xl bg-brand text-white text-sm font-semibold px-5 py-2.5">{{ __('site.partner_portal.valuation_continue') }}</button>
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
                        <button class="rounded-xl bg-brand text-white text-sm font-semibold px-5 py-2.5">{{ __('site.partner_portal.valuation_continue') }}</button>
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
                    <button class="rounded-xl bg-emerald-600 text-white text-sm font-semibold px-5 py-2.5 hover:bg-emerald-700">{{ __('site.partner_portal.valuation_submit_report') }}</button>
                </form>
            @else
                <div class="glass-card rounded-2xl ring-1 ring-brand/10 p-5 text-sm space-y-2">
                    <p>{{ __('site.partner_portal.valuation_market_value') }}: <span class="font-semibold">{{ format_money($assignment?->market_value) }}</span></p>
                    <p>{{ __('site.partner_portal.valuation_fsv') }}: <span class="font-semibold">{{ format_money($assignment?->forced_sale_value) }}</span></p>
                </div>
            @endif
        </div>
    </div>

    <div class="space-y-4">
        <div class="glass-card rounded-2xl ring-1 ring-brand/10 p-5">
            <h3 class="font-bold mb-3">{{ __('site.partner_portal.next_step') }}</h3>
            <div class="space-y-2">
                @if ($task->status === 'assigned')
                    @if ($jobBlock === 'profile')
                        <a href="{{ route('site.partner.profile') }}" class="block w-full text-center rounded-lg bg-brand-gold text-brand text-sm font-semibold py-2.5">{{ __('site.partner_portal.cta_complete_profile') }}</a>
                    @elseif ($jobBlock === 'payment')
                        <a href="{{ route($payRoute) }}" class="block w-full text-center rounded-lg bg-brand-gold text-brand text-sm font-semibold py-2.5">{{ __('site.partner_portal.cta_pay_membership') }}</a>
                    @else
                        <form method="POST" action="{{ route('site.partner.task.accept', $task) }}">
                            @csrf
                            <button class="w-full rounded-lg bg-brand text-white text-sm font-semibold py-2.5 hover:bg-brand-light">{{ __('site.partner_portal.accept_task') }}</button>
                        </form>
                    @endif
                @endif
                @if ($open && ! $started)
                    @if ($jobBlock === 'profile')
                        <a href="{{ route('site.partner.profile') }}" class="block w-full text-center rounded-lg bg-gray-900 text-white text-sm font-semibold py-2.5">{{ __('site.partner_portal.cta_complete_profile') }}</a>
                    @elseif ($jobBlock === 'payment')
                        <a href="{{ route($payRoute) }}" class="block w-full text-center rounded-lg bg-gray-900 text-white text-sm font-semibold py-2.5">{{ __('site.partner_portal.cta_pay_membership') }}</a>
                    @else
                        <form method="POST" action="{{ route('site.partner.task.start', $task) }}">
                            @csrf
                            <button class="w-full rounded-lg bg-gray-900 text-white text-sm font-semibold py-2.5 hover:bg-black">{{ __('site.partner_portal.valuation_start_work') }}</button>
                        </form>
                    @endif
                @elseif ($open && $started)
                    <button type="button" @click="tab = 'inspect'; step = '{{ $initialStep }}'" class="w-full rounded-lg bg-gray-900 text-white text-sm font-semibold py-2.5">{{ __('site.partner_portal.tab_inspect') }}</button>
                @endif
            </div>
            <div class="mt-4 pt-4 border-t border-gray-100 text-xs space-y-1 text-gray-500">
                @if ($task->accepted_at)<p>{{ __('site.partner_portal.accepted') }} {{ $task->accepted_at->format('d M H:i') }}</p>@endif
                @if ($task->started_at)<p>{{ __('site.partner_portal.started') }} {{ $task->started_at->format('d M H:i') }}</p>@endif
                @if ($task->completed_at)<p>{{ __('site.partner_portal.completed_at') }} {{ $task->completed_at->format('d M H:i') }}</p>@endif
            </div>
        </div>
    </div>
</div>
