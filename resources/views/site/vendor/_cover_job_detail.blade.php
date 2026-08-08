@php
    /** Prefers live collateral dossier over frozen task meta. */
    if ($collateralAsset) {
        $assetProfile = app(\App\Services\CollateralInsurancePartnerService::class)->assetProfilePayload($collateralAsset);
    }
    $assetProfile = is_array($assetProfile ?? null) ? $assetProfile : [];
    $isOpen = ! in_array($task->status, ['completed', 'cancelled'], true);
    $coverTitle = trim((string) (($assetProfile['label'] ?? '') ?: ($task->vehicle_details ?: __('site.partner_portal.cover_job_fallback_title'))));
    $reg = $assetProfile['registration_number'] ?? null;
    $photos = array_values(array_filter($assetProfile['photos'] ?? []));
    $labeled = collect($assetProfile['labeled_details'] ?? [])
        ->reject(fn ($row) => in_array(($row['key'] ?? ''), ['insurance_type', 'insurance_policy_number', 'insurance_expires_at'], true))
        ->values()
        ->all();
    if ($labeled === [] && ! empty($assetProfile['details']) && is_array($assetProfile['details'])) {
        foreach ($assetProfile['details'] as $key => $value) {
            if (! filled($value) || in_array($key, ['insurance_type', 'insurance_policy_number', 'insurance_expires_at'], true)) {
                continue;
            }
            $labeled[] = [
                'key' => $key,
                'label' => str_replace('_', ' ', ucfirst((string) $key)),
                'value' => is_scalar($value) ? $value : json_encode($value),
            ];
        }
    }
    $hasOwnershipDoc = filled($assetProfile['ownership_document_url'] ?? null);
    $hasInsuranceDoc = filled($assetProfile['insurance_document_url'] ?? null);
@endphp

<div class="mb-5">
    <a href="{{ route('site.partner.tasks') }}" class="text-sm text-brand hover:underline">← {{ __('site.partner_portal.back_to_cover_jobs') }}</a>
</div>

<div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-4 mb-6">
    <div class="min-w-0">
        <p class="text-[11px] uppercase tracking-[0.18em] text-brand font-bold mb-1">{{ __('site.partner_portal.cover_job_eyebrow') }}</p>
        <h1 class="text-2xl sm:text-3xl font-extrabold text-gray-900 tracking-tight truncate">{{ $coverTitle }}</h1>
        <p class="text-sm text-gray-500 mt-1">
            {{ __('site.partner_portal.cover_job_meta', ['id' => $task->id, 'date' => $task->created_at->format('d M Y')]) }}
            @if ($reg)
                · {{ __('site.partner_portal.reg_short', ['reg' => $reg]) }}
            @endif
        </p>
        <div class="mt-3 flex flex-wrap items-center gap-2 text-sm">
            @if (! empty($taskMeta['insured_value']))
                <span class="inline-flex items-center gap-1.5 rounded-lg bg-white ring-1 ring-brand/10 px-3 py-1.5">
                    <span class="text-xs text-gray-500">{{ __('site.partner_portal.insured_value') }}</span>
                    <span class="font-bold text-gray-900">{{ format_money($taskMeta['insured_value']) }}</span>
                </span>
            @endif
            <span class="inline-flex items-center gap-1.5 rounded-lg bg-white ring-1 ring-brand/10 px-3 py-1.5">
                <span class="text-xs text-gray-500">{{ __('site.partner_portal.your_payout') }}</span>
                <span class="font-bold text-brand">{{ format_money($taskMeta['partner_share'] ?? $taskMeta['base_premium'] ?? $task->fee_amount) }}</span>
            </span>
            @if (! empty($taskMeta['premium']) && (int) ($taskMeta['premium'] ?? 0) !== (int) ($taskMeta['partner_share'] ?? $taskMeta['base_premium'] ?? $task->fee_amount ?? 0))
                <span class="inline-flex items-center gap-1.5 rounded-lg bg-white ring-1 ring-gray-200 px-3 py-1.5">
                    <span class="text-xs text-gray-500">{{ __('site.partner_portal.premium_paid') }}</span>
                    <span class="font-semibold text-gray-700">{{ format_money($taskMeta['premium']) }}</span>
                </span>
            @endif
            <span class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-50 ring-1 ring-emerald-200/80 px-3 py-1.5">
                <span class="text-xs text-emerald-700/80">{{ __('site.partner_portal.requested_cover') }}</span>
                <span class="font-bold text-emerald-800">{{ __('site.partner_portal.comprehensive_only') }}</span>
            </span>
            @if ($task->due_at)
                <span class="inline-flex items-center gap-1.5 rounded-lg bg-white ring-1 ring-brand/10 px-3 py-1.5">
                    <span class="text-xs text-gray-500">{{ __('site.partner_portal.due') }}</span>
                    <span class="font-semibold text-gray-900">{{ $task->due_at->format('d M Y H:i') }}</span>
                </span>
            @endif
        </div>
    </div>
    <div class="flex items-center gap-2 shrink-0">
        <span class="px-3 py-1 rounded-full text-xs font-semibold {{ $priorityBadge }}">{{ $priority['label'] }} {{ __('site.partner_portal.priority_suffix') }}</span>
        <span class="px-3 py-1 rounded-full text-xs font-semibold {{ $badge }}">{{ str_replace('_',' ', $task->status) }}</span>
    </div>
</div>

<div class="grid lg:grid-cols-3 gap-6" x-data="{ tab: @js(request('tab', 'asset')) }">
    <div class="lg:col-span-2 space-y-4">
        <div class="inline-flex flex-wrap rounded-xl ring-1 ring-gray-200/80 bg-white/90 backdrop-blur p-0.5 text-sm gap-0.5 w-full sm:w-auto">
            @foreach ([
                'asset' => __('site.partner_portal.tab_asset'),
                'cover' => __('site.partner_portal.tab_cover'),
                'issue' => __('site.partner_portal.tab_issue'),
                'documents' => __('site.partner_portal.tab_documents'),
            ] as $key => $label)
                <button type="button"
                        @click="tab = '{{ $key }}'"
                        :class="tab === '{{ $key }}' ? 'bg-brand text-white shadow-sm' : 'text-gray-600 hover:bg-brand-muted/50'"
                        class="px-3.5 py-2 rounded-lg font-semibold transition flex-1 sm:flex-none text-center">
                    {{ $label }}
                </button>
            @endforeach
        </div>

        {{-- Asset dossier --}}
        <div x-show="tab === 'asset'" x-cloak class="space-y-4">
            <div class="glass-card rounded-2xl ring-1 ring-brand/10 overflow-hidden">
                @if ($photos !== [])
                    <div
                        class="relative bg-gradient-to-br from-brand/5 via-slate-900/5 to-brand-muted/30"
                        x-data="{
                            photos: @js($photos),
                            index: 0,
                            get current() { return this.photos[this.index] || this.photos[0]; },
                            get count() { return this.photos.length; },
                            prev() { this.index = (this.index - 1 + this.count) % this.count; },
                            next() { this.index = (this.index + 1) % this.count; },
                            go(i) { this.index = i; },
                        }"
                    >
                        <div class="relative aspect-[16/10] bg-gray-100">
                            <img :src="current" alt="" class="absolute inset-0 w-full h-full object-cover">
                            @if (count($photos) > 1)
                                <button type="button" @click="prev()"
                                        class="absolute left-3 top-1/2 -translate-y-1/2 size-10 rounded-full bg-black/55 text-white grid place-items-center hover:bg-black/75 transition shadow-lg"
                                        aria-label="{{ __('site.partner_portal.prev_image') }}">
                                    <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                                </button>
                                <button type="button" @click="next()"
                                        class="absolute right-3 top-1/2 -translate-y-1/2 size-10 rounded-full bg-black/55 text-white grid place-items-center hover:bg-black/75 transition shadow-lg"
                                        aria-label="{{ __('site.partner_portal.next_image') }}">
                                    <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                                </button>
                                <p class="absolute top-3 right-3 rounded-full bg-black/55 text-white text-[11px] font-semibold px-2.5 py-1 tabular-nums"
                                   x-text="(index + 1) + ' / ' + count"></p>
                            @endif
                        </div>
                        @if (count($photos) > 1)
                            <div class="flex gap-2 overflow-x-auto p-3 bg-white/90 border-t border-gray-100">
                                <template x-for="(photo, i) in photos" :key="i">
                                    <button type="button" @click="go(i)"
                                            class="shrink-0 rounded-lg overflow-hidden ring-2 transition focus:outline-none"
                                            :class="i === index ? 'ring-brand' : 'ring-transparent opacity-70 hover:opacity-100'">
                                        <img :src="photo" alt="" class="size-14 object-cover">
                                    </button>
                                </template>
                            </div>
                        @endif
                    </div>
                @endif
                <div class="p-5">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="text-[10px] uppercase tracking-widest text-brand font-bold">{{ $assetProfile['type_label'] ?? __('site.partner_portal.collateral') }}</p>
                            <p class="text-xl font-extrabold text-gray-900 mt-0.5">{{ $assetProfile['label'] ?? '—' }}</p>
                        </div>
                        <span class="inline-flex items-center rounded-full bg-emerald-50 text-emerald-800 text-xs font-bold px-3 py-1 ring-1 ring-emerald-200">
                            {{ __('site.partner_portal.comprehensive_only') }}
                        </span>
                    </div>
                    @if (! empty($assetProfile['description']))
                        <p class="text-sm text-gray-600 mt-2">{{ $assetProfile['description'] }}</p>
                    @endif
                    @if (! empty($assetProfile['estimated_value']))
                        <p class="text-sm text-gray-600 mt-2">{{ __('site.partner_portal.est_value') }}: <span class="font-semibold">{{ format_money($assetProfile['estimated_value']) }}</span></p>
                    @endif

                    @if ($labeled !== [])
                        <dl class="mt-5 grid sm:grid-cols-2 gap-3 text-sm border-t border-gray-100 pt-4">
                            @foreach ($labeled as $row)
                                <div>
                                    <dt class="text-gray-500 text-xs">{{ $row['label'] }}</dt>
                                    <dd class="font-medium text-gray-900">{{ $row['value'] }}</dd>
                                </div>
                            @endforeach
                        </dl>
                    @endif
                </div>
            </div>

            {{-- Asset documents visible on Asset tab --}}
            <div class="glass-card rounded-2xl ring-1 ring-brand/10 p-5">
                <div class="flex items-center justify-between gap-3 mb-3">
                    <h2 class="font-bold">{{ __('site.partner_portal.collateral_documents') }}</h2>
                    <button type="button" @click="tab = 'documents'" class="text-xs font-semibold text-brand hover:underline">
                        {{ __('site.partner_portal.tab_documents') }} →
                    </button>
                </div>
                <div class="grid sm:grid-cols-2 gap-3">
                    <div class="rounded-xl bg-gray-50 ring-1 ring-gray-100 p-4 flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-gray-900">{{ __('borrower.profile.ownership_document') }}</p>
                            <p class="text-xs text-gray-500 mt-0.5">{{ __('borrower.profile.ownership_document_hint') }}</p>
                        </div>
                        @if ($hasOwnershipDoc)
                            <x-site.document-view-button :url="$assetProfile['ownership_document_url']" :label="__('site.partner_portal.view')" class="text-brand hover:underline text-xs font-semibold shrink-0" />
                        @else
                            <span class="text-xs text-gray-400 shrink-0">{{ __('site.partner_portal.not_provided') }}</span>
                        @endif
                    </div>
                    <div class="rounded-xl bg-gray-50 ring-1 ring-gray-100 p-4 flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-gray-900">{{ __('borrower.profile.collateral_step_insurance_doc') }}</p>
                            <p class="text-xs text-gray-500 mt-0.5">{{ __('site.partner_portal.existing_cert_hint') }}</p>
                        </div>
                        @if ($hasInsuranceDoc)
                            <x-site.document-view-button :url="$assetProfile['insurance_document_url']" :label="__('site.partner_portal.view')" class="text-brand hover:underline text-xs font-semibold shrink-0" />
                        @else
                            <span class="text-xs text-gray-400 shrink-0">{{ __('site.partner_portal.not_provided') }}</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- Cover context --}}
        <div x-show="tab === 'cover'" x-cloak class="space-y-4">
            <div class="glass-card rounded-2xl ring-1 ring-brand/10 p-5">
                <h2 class="font-bold mb-1">{{ __('site.partner_portal.tab_cover') }}</h2>
                <p class="text-sm text-gray-600 mb-4">{{ __('site.partner_portal.cover_brief') }}</p>
                <dl class="grid sm:grid-cols-2 gap-3 text-sm">
                    <div>
                        <dt class="text-gray-500 text-xs">{{ __('site.partner_portal.customer') }}</dt>
                        <dd class="font-medium">{{ $task->customer_name ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 text-xs">{{ __('site.partner_portal.phone') }}</dt>
                        <dd class="font-medium">{{ $task->customer_phone ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 text-xs">{{ __('site.partner_portal.insured_value') }}</dt>
                        <dd class="font-medium">{{ ! empty($taskMeta['insured_value']) ? format_money($taskMeta['insured_value']) : '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 text-xs">{{ __('site.partner_portal.your_payout') }}</dt>
                        <dd class="font-bold text-brand">{{ format_money($taskMeta['partner_share'] ?? $taskMeta['base_premium'] ?? $task->fee_amount) }}</dd>
                    </div>
                    @if (! empty($taskMeta['premium']) && (int) ($taskMeta['premium'] ?? 0) !== (int) ($taskMeta['partner_share'] ?? $taskMeta['base_premium'] ?? $task->fee_amount ?? 0))
                        <div>
                            <dt class="text-gray-500 text-xs">{{ __('site.partner_portal.premium_paid') }}</dt>
                            <dd class="font-medium">{{ format_money($taskMeta['premium']) }}</dd>
                        </div>
                    @endif
                    <div>
                        <dt class="text-gray-500 text-xs">{{ __('site.partner_portal.requested_cover') }}</dt>
                        <dd class="font-semibold text-emerald-800">{{ __('site.partner_portal.comprehensive_only') }}</dd>
                    </div>
                    @if (! empty($taskMeta['payment_reference']))
                        <div>
                            <dt class="text-gray-500 text-xs">{{ __('site.partner_portal.payment_ref') }}</dt>
                            <dd class="font-mono text-xs">{{ $taskMeta['payment_reference'] }}</dd>
                        </div>
                    @endif
                    @if ($task->location)
                        <div class="sm:col-span-2">
                            <dt class="text-gray-500 text-xs">{{ __('site.partner_portal.location') }}</dt>
                            <dd class="font-medium">{{ $task->location }}</dd>
                        </div>
                    @endif
                </dl>
            </div>
        </div>

        {{-- Issue policy --}}
        <div x-show="tab === 'issue'" x-cloak class="space-y-4">
            @if ($isOpen)
                <div class="glass-card rounded-2xl ring-1 ring-brand/10 p-5 sm:p-6">
                    <div class="flex flex-wrap items-start justify-between gap-3 mb-5">
                        <div>
                            <h2 class="text-lg font-extrabold text-gray-900 tracking-tight">{{ __('site.partner_portal.record_cover') }}</h2>
                            <p class="text-sm text-gray-500 mt-1 max-w-md">{{ __('site.partner_portal.record_cover_hint') }}</p>
                        </div>
                        <span class="inline-flex items-center rounded-full bg-emerald-50 text-emerald-800 text-xs font-bold px-3 py-1.5 ring-1 ring-emerald-200">
                            {{ __('site.partner_portal.comprehensive_only') }}
                        </span>
                    </div>

                    <form method="POST" action="{{ route('site.partner.task.complete', $task) }}" class="max-w-md space-y-4"
                          @submit.prevent="window.confirmForm($el, {
                              title: @js(__('site.partner_portal.confirm.insurance_title')),
                              message: @js(__('site.partner_portal.confirm.insurance_message')),
                              confirmLabel: @js(__('site.partner_portal.confirm.record_cover_button')),
                              tone: 'warning',
                              confirmClass: 'bg-emerald-600 hover:bg-emerald-700 text-white',
                          })">
                        @csrf
                        <input type="hidden" name="insurance_type" value="comprehensive">

                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1.5">{{ __('site.partner_portal.policy_number') }}</label>
                            <input name="insurance_policy_number"
                                   value="{{ old('insurance_policy_number', $assetProfile['insurance_policy_number'] ?? '') }}"
                                   class="w-full max-w-xs rounded-xl border-gray-200 bg-white text-sm px-3.5 py-2.5 focus:border-brand focus:ring-2 focus:ring-brand/10"
                                   placeholder="{{ __('site.partner_portal.policy_number_placeholder') }}">
                        </div>

                        <div class="max-w-xs">
                            <x-site.date-input
                                name="insurance_expires_at"
                                :label="__('site.partner_portal.cover_expiry')"
                                :required="true"
                                :min="now()->format('Y-m-d')"
                                :max="now()->addYears(15)->format('Y-m-d')"
                                :value="old('insurance_expires_at')"
                                input-class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 focus:border-brand focus:ring-2 focus:ring-brand/10 text-sm outline-none transition"
                            />
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1.5">{{ __('site.partner_portal.notes_optional') }}</label>
                            <textarea name="notes" rows="2"
                                      class="w-full max-w-sm rounded-xl border-gray-200 bg-white text-sm px-3.5 py-2.5 focus:border-brand focus:ring-2 focus:ring-brand/10"
                                      placeholder="{{ __('site.partner_portal.notes_placeholder') }}"></textarea>
                        </div>

                        <button class="rounded-xl bg-emerald-600 text-white text-sm font-semibold px-5 py-2.5 hover:bg-emerald-700 shadow-sm shadow-emerald-600/20">
                            {{ __('site.partner_portal.record_cover') }}
                        </button>
                    </form>
                </div>
            @else
                <div class="glass-card rounded-2xl ring-1 ring-brand/10 p-5">
                    <h2 class="font-bold mb-3">{{ __('site.partner_portal.cover_recorded') }}</h2>
                    <dl class="grid sm:grid-cols-2 gap-3 text-sm max-w-lg">
                        <div>
                            <dt class="text-gray-500 text-xs">{{ __('site.partner_portal.insurance_type') }}</dt>
                            <dd class="font-semibold text-emerald-800">{{ __('site.partner_portal.comprehensive_only') }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500 text-xs">{{ __('site.partner_portal.policy_number') }}</dt>
                            <dd class="font-medium">{{ $taskMeta['insurance_policy_number'] ?? $assetProfile['insurance_policy_number'] ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500 text-xs">{{ __('site.partner_portal.cover_expiry') }}</dt>
                            <dd class="font-medium">{{ $taskMeta['insurance_expires_at'] ?? $assetProfile['insurance_expires_at'] ?? '—' }}</dd>
                        </div>
                        @if ($task->completed_at)
                            <div>
                                <dt class="text-gray-500 text-xs">{{ __('site.partner_portal.completed_at') }}</dt>
                                <dd class="font-medium">{{ $task->completed_at->format('d M Y H:i') }}</dd>
                            </div>
                        @endif
                    </dl>
                </div>
            @endif
        </div>

        {{-- Documents --}}
        <div x-show="tab === 'documents'" x-cloak class="space-y-4">
            <div class="glass-card rounded-2xl ring-1 ring-brand/10 p-5">
                <h2 class="font-bold mb-3">{{ __('site.partner_portal.collateral_documents') }}</h2>
                <ul class="divide-y divide-gray-100 text-sm">
                    <li class="py-3 flex items-center justify-between gap-3">
                        <div>
                            <p class="font-semibold text-gray-900">{{ __('borrower.profile.ownership_document') }}</p>
                            <p class="text-xs text-gray-500">{{ __('borrower.profile.ownership_document_hint') }}</p>
                        </div>
                        @if ($hasOwnershipDoc)
                            <x-site.document-view-button :url="$assetProfile['ownership_document_url']" :label="__('site.partner_portal.view')" class="text-brand hover:underline text-xs font-semibold shrink-0" />
                        @else
                            <span class="text-xs text-gray-400">{{ __('site.partner_portal.not_provided') }}</span>
                        @endif
                    </li>
                    <li class="py-3 flex items-center justify-between gap-3">
                        <div>
                            <p class="font-semibold text-gray-900">{{ __('borrower.profile.collateral_step_insurance_doc') }}</p>
                            <p class="text-xs text-gray-500">{{ __('site.partner_portal.existing_cert_hint') }}</p>
                        </div>
                        @if ($hasInsuranceDoc)
                            <x-site.document-view-button :url="$assetProfile['insurance_document_url']" :label="__('site.partner_portal.view')" class="text-brand hover:underline text-xs font-semibold shrink-0" />
                        @else
                            <span class="text-xs text-gray-400">{{ __('site.partner_portal.not_provided') }}</span>
                        @endif
                    </li>
                </ul>
            </div>

            @if ($isOpen)
                <div class="glass-card rounded-2xl ring-1 ring-brand/10 p-5">
                    <h2 class="font-bold mb-1">{{ __('site.partner_portal.upload_policy_document') }}</h2>
                    <p class="text-sm text-gray-600 mb-4">{{ __('site.partner_portal.upload_policy_hint') }}</p>
                    <form method="POST" action="{{ route('site.partner.task.proof', $task) }}" enctype="multipart/form-data" class="space-y-3 max-w-md">
                        @csrf
                        <input type="hidden" name="label" value="{{ __('site.partner_portal.policy_document_label') }}">
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">{{ __('site.partner_portal.file_label') }}</label>
                            <input type="file" name="file" required accept=".jpg,.jpeg,.png,.pdf" class="w-full text-sm">
                        </div>
                        <button class="rounded-lg bg-brand text-white text-sm font-semibold px-4 py-2 hover:bg-brand-light">{{ __('site.partner_portal.upload') }}</button>
                    </form>
                </div>
            @endif

            @if ($task->documents->isNotEmpty())
                <div class="glass-card rounded-2xl ring-1 ring-brand/10 p-5">
                    <h2 class="font-bold mb-3">{{ __('site.partner_portal.uploaded_by_you') }}</h2>
                    <ul class="divide-y divide-gray-100">
                        @foreach ($task->documents as $d)
                            <li class="py-2 flex items-center justify-between text-sm gap-2">
                                <span class="truncate">{{ $d->label }}</span>
                                <x-site.document-view-button :url="asset('storage/'.$d->file_path)" :label="__('site.partner_portal.view')" class="text-brand hover:underline text-xs shrink-0" />
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    </div>

    {{-- Right rail --}}
    <div class="space-y-4 lg:sticky lg:top-4 self-start">
        <div class="glass-card rounded-2xl ring-1 ring-brand/10 p-5">
            <h3 class="font-bold mb-3">{{ __('site.partner_portal.next_step') }}</h3>
            @if ($task->status === 'assigned')
                <p class="text-sm text-gray-600 mb-3">{{ __('site.partner_portal.accept_cover_hint') }}</p>
                <form method="POST" action="{{ route('site.partner.task.accept', $task) }}"
                      @submit.prevent="window.confirmForm($el, {
                          title: @js(__('site.partner_portal.confirm.accept_cover_title')),
                          message: @js(__('site.partner_portal.confirm.accept_cover_message')),
                          confirmLabel: @js(__('site.partner_portal.confirm.accept_task_button')),
                          tone: 'confirm',
                      })">
                    @csrf
                    <button class="w-full rounded-lg bg-brand text-white text-sm font-semibold py-2.5 hover:bg-brand-light">
                        {{ __('site.partner_portal.accept_cover') }}
                    </button>
                </form>
            @elseif ($isOpen)
                <p class="text-sm text-gray-600 mb-3">{{ __('site.partner_portal.issue_next_hint') }}</p>
                <button type="button" @click="tab = 'issue'"
                        class="w-full rounded-lg bg-emerald-600 text-white text-sm font-semibold py-2.5 hover:bg-emerald-700">
                    {{ __('site.partner_portal.go_to_issue') }}
                </button>
                <button type="button" @click="tab = 'documents'"
                        class="w-full mt-2 rounded-lg bg-white text-brand text-sm font-semibold py-2.5 ring-1 ring-brand/20 hover:bg-brand-muted/40">
                    {{ __('site.partner_portal.go_to_documents') }}
                </button>
            @else
                <p class="text-sm text-emerald-700 font-medium">{{ __('site.partner_portal.cover_complete_banner') }}</p>
            @endif

            <div class="mt-4 pt-4 border-t border-gray-100 text-xs space-y-1 text-gray-500">
                @if ($task->accepted_at)<p>{{ __('site.partner_portal.accepted_at', ['when' => $task->accepted_at->format('d M H:i')]) }}</p>@endif
                @if ($task->completed_at)<p>{{ __('site.partner_portal.completed_at_short', ['when' => $task->completed_at->format('d M H:i')]) }}</p>@endif
            </div>
        </div>

        @if ($task->payment)
            <div class="glass-card rounded-2xl ring-1 ring-brand/10 p-5">
                <h3 class="font-bold mb-3">{{ __('site.partner_portal.payment_card') }}</h3>
                <p class="text-sm"><span class="text-gray-500">{{ __('site.partner_portal.invoice') }}:</span> <span class="font-mono">{{ $task->payment->invoice_number }}</span></p>
                <p class="text-sm"><span class="text-gray-500">{{ __('site.partner_portal.amount') }}:</span> {{ format_money($task->payment->amount) }}</p>
                @php $pc = $task->payment->status === 'paid' ? 'emerald' : 'amber'; @endphp
                <p class="mt-1"><span class="px-2 py-0.5 rounded-full text-[11px] font-semibold bg-{{ $pc }}-100 text-{{ $pc }}-700">{{ $task->payment->status }}</span></p>
                <a href="{{ route('site.partner.invoice', $task->payment) }}" class="block mt-3 text-sm text-brand hover:underline">{{ __('site.partner_portal.view_invoice') }} →</a>
            </div>
        @endif
    </div>
</div>
