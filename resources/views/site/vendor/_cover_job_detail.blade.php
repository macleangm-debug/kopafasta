@php
    /** Prefers live collateral dossier over frozen task meta. */
    if ($collateralAsset) {
        $assetProfile = app(\App\Services\CollateralInsurancePartnerService::class)->assetProfilePayload($collateralAsset);
    }
    $assetProfile = is_array($assetProfile ?? null) ? $assetProfile : [];
    $isOpen = ! in_array($task->status, ['completed', 'cancelled'], true);
    $prefillType = old('insurance_type', $taskMeta['insurance_type'] ?? $assetProfile['insurance_type'] ?? 'comprehensive');
    $coverTitle = trim((string) (($assetProfile['label'] ?? '') ?: ($task->vehicle_details ?: __('site.partner_portal.cover_job_fallback_title'))));
    $reg = $assetProfile['registration_number'] ?? null;
    $photos = $assetProfile['photos'] ?? [];
    $labeled = $assetProfile['labeled_details'] ?? [];
    if ($labeled === [] && ! empty($assetProfile['details']) && is_array($assetProfile['details'])) {
        foreach ($assetProfile['details'] as $key => $value) {
            if (! filled($value)) {
                continue;
            }
            $labeled[] = [
                'key' => $key,
                'label' => str_replace('_', ' ', ucfirst((string) $key)),
                'value' => is_scalar($value) ? $value : json_encode($value),
            ];
        }
    }
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
                <span class="text-xs text-gray-500">{{ __('site.partner_portal.premium') }}</span>
                <span class="font-bold text-brand">{{ format_money($taskMeta['premium'] ?? $task->fee_amount) }}</span>
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

<div class="grid lg:grid-cols-3 gap-6" x-data="{ tab: 'asset' }">
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
                    <div class="relative bg-gradient-to-br from-brand/5 via-white to-brand-muted/30">
                        <img src="{{ $photos[0] }}" alt="" class="w-full max-h-80 object-cover">
                        @if (count($photos) > 1)
                            <div class="absolute inset-x-0 bottom-0 p-3 bg-gradient-to-t from-black/50 to-transparent">
                                <div class="flex gap-2 overflow-x-auto pb-0.5">
                                    @foreach ($photos as $i => $photo)
                                        <img src="{{ $photo }}" alt="" class="size-14 rounded-lg object-cover ring-2 {{ $i === 0 ? 'ring-white' : 'ring-white/40' }} shrink-0">
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                @endif
                <div class="p-5">
                    <p class="text-[10px] uppercase tracking-widest text-brand font-bold">{{ $assetProfile['type_label'] ?? __('site.partner_portal.collateral') }}</p>
                    <p class="text-xl font-extrabold text-gray-900 mt-0.5">{{ $assetProfile['label'] ?? '—' }}</p>
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
                        <dt class="text-gray-500 text-xs">{{ __('site.partner_portal.premium_paid') }}</dt>
                        <dd class="font-bold text-brand">{{ format_money($taskMeta['premium'] ?? $task->fee_amount) }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 text-xs">{{ __('site.partner_portal.requested_cover') }}</dt>
                        <dd class="font-medium capitalize">{{ str_replace('_', ' ', (string) ($taskMeta['insurance_type'] ?? $assetProfile['insurance_type'] ?? 'comprehensive')) }}</dd>
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
                <div class="glass-card rounded-2xl ring-1 ring-brand/10 p-5">
                    <h2 class="font-bold mb-1">{{ __('site.partner_portal.record_cover') }}</h2>
                    <p class="text-sm text-gray-600 mb-4">{{ __('site.partner_portal.record_cover_hint') }}</p>
                    <form method="POST" action="{{ route('site.partner.task.complete', $task) }}" class="space-y-3"
                          @submit.prevent="window.confirmForm($el, {
                              title: @js(__('site.partner_portal.confirm.insurance_title')),
                              message: @js(__('site.partner_portal.confirm.insurance_message')),
                              confirmLabel: @js(__('site.partner_portal.confirm.record_cover_button')),
                              tone: 'warning',
                              confirmClass: 'bg-emerald-600 hover:bg-emerald-700 text-white',
                          })">
                        @csrf
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">{{ __('site.partner_portal.insurance_type') }}</label>
                            <select name="insurance_type" class="w-full rounded-lg border-gray-300 text-sm" required>
                                <option value="comprehensive" @selected($prefillType === 'comprehensive')>Comprehensive (Bima kamili)</option>
                                <option value="third_party" @selected($prefillType === 'third_party')>Third Party (Bima ya wahusika wengine)</option>
                            </select>
                            <p class="mt-1 text-[11px] text-gray-500">{{ __('site.partner_portal.insurance_type_hint') }}</p>
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">{{ __('site.partner_portal.policy_number') }}</label>
                            <input name="insurance_policy_number" value="{{ old('insurance_policy_number', $assetProfile['insurance_policy_number'] ?? '') }}"
                                   class="w-full rounded-lg border-gray-300 text-sm" placeholder="{{ __('site.partner_portal.policy_number_placeholder') }}">
                        </div>
                        <div>
                            <x-site.date-input
                                name="insurance_expires_at"
                                :label="__('site.partner_portal.cover_expiry')"
                                :required="true"
                                :min="now()->format('Y-m-d')"
                                :max="now()->addYears(15)->format('Y-m-d')"
                                :value="old('insurance_expires_at')"
                            />
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">{{ __('site.partner_portal.notes_optional') }}</label>
                            <textarea name="notes" rows="3" class="w-full rounded-lg border-gray-300 text-sm" placeholder="{{ __('site.partner_portal.notes_placeholder') }}"></textarea>
                        </div>
                        <button class="rounded-lg bg-emerald-600 text-white text-sm font-semibold px-4 py-2.5 hover:bg-emerald-700 w-full sm:w-auto">
                            {{ __('site.partner_portal.record_cover') }}
                        </button>
                    </form>
                </div>
            @else
                <div class="glass-card rounded-2xl ring-1 ring-brand/10 p-5">
                    <h2 class="font-bold mb-3">{{ __('site.partner_portal.cover_recorded') }}</h2>
                    <dl class="grid sm:grid-cols-2 gap-3 text-sm">
                        <div>
                            <dt class="text-gray-500 text-xs">{{ __('site.partner_portal.insurance_type') }}</dt>
                            <dd class="font-medium capitalize">{{ str_replace('_', ' ', (string) ($taskMeta['insurance_type'] ?? $assetProfile['insurance_type'] ?? '—')) }}</dd>
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
                        @if (! empty($assetProfile['ownership_document_url']))
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
                        @if (! empty($assetProfile['insurance_document_url']))
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
                    <form method="POST" action="{{ route('site.partner.task.proof', $task) }}" enctype="multipart/form-data" class="space-y-3">
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
