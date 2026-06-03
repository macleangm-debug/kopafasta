<x-site.borrower-layout :title="brand_title(__('borrower.apply.title'))" active="loans">
    <div class="max-w-4xl mx-auto">

        <div class="mb-6">
            <p class="text-xs uppercase tracking-widest text-amber-600 mb-1">{{ brand_name() }} {{ __('borrower.apply.smart_application') }}</p>
            <h1 class="text-2xl sm:text-3xl font-bold tracking-tight">{{ __('borrower.apply.title') }}</h1>
            <p class="mt-1 text-sm text-gray-500">{{ __('borrower.apply.subtitle') }}</p>
        </div>

        @if (! ($applyRequirements['can_apply'] ?? false))
            <div class="mb-6 rounded-xl bg-amber-50 ring-1 ring-amber-200 px-4 py-4 text-sm text-amber-900">
                <p class="font-semibold">{{ __('borrower.apply.kyc_incomplete_title') }}</p>
                <p class="mt-1 text-amber-800">{{ __('borrower.apply.kyc_incomplete_hint') }}</p>
                <ul class="mt-3 space-y-1 text-amber-800">
                    @foreach (($applyRequirements['items'] ?? []) as $item)
                        @if (! ($item['complete'] ?? false))
                            <li class="flex items-start gap-2">
                                <span>•</span>
                                <span>
                                    {{ $item['label'] }}
                                    @if (! empty($item['action_url']))
                                        — <a href="{{ $item['action_url'] }}" class="font-semibold underline">{{ __('borrower.apply.details.complete_missing') }}</a>
                                    @endif
                                </span>
                            </li>
                        @endif
                    @endforeach
                </ul>
            </div>
        @endif


                @if ($reservation ?? null)
            <div class="mb-6 rounded-xl bg-amber-50 ring-1 ring-amber-200 px-4 py-3 text-sm text-amber-900">
                {{ __('borrower.apply.asset_reservation', ['asset' => $reservation->asset?->title, 'amount' => format_number($reservation->deposit_amount)]) }}
            </div>
        @endif

        @php
            $wizardProducts = $products;
            if (($assetApplication ?? null) && ($selectedProduct ?? null)) {
                $wizardProducts = collect($products)
                    ->push($selectedProduct)
                    ->unique('id')
                    ->values();
            }
            $wizardProductPayload = fn ($p) => loan_product_wizard_payload($p, $customer);
        @endphp

        <div x-data="applyWizard({
                  products: @js($wizardProducts->map($wizardProductPayload)->values()->all()),
                  guarantorLookupUrl: @js(route('site.borrower.apply.guarantor-lookup')),
                  guarantorInviteUrl: @js(route('site.borrower.apply.guarantor-invite')),
                  tanzaniaLocations: @js(config('tanzania_locations')),
                  draftSaveUrl: @js(route('site.borrower.apply.draft.save')),
                  applicationFeePayUrl: @js(route('site.borrower.apply.application-fee.pay')),
                  savedDraft: @js($savedDraft ?? null),
                  reservationId: {{ ($reservation ?? null) ? (int) $reservation->id : 'null' }},
                  preselect: {{ $preselect ? (int)$preselect : 'null' }},
                  applicationFee: {{ (int) ($applicationFee ?? 0) }},
                  initialPlan: @js($stepPlan),
                  assetApplication: @js($assetApplication),
                  reservationMode: {{ ($reservation ?? null) ? 'true' : 'false' }},
                  marketplaceOnlyCodes: @js($marketplaceOnlyCodes ?? marketplace_only_loan_codes()),
                  marketplaceUrl: @js($marketplaceUrl ?? route('site.borrower.marketplace')),
                  profileUrl: @js(route('site.borrower.profile')),
                  canApply: @js((bool) ($applyRequirements['can_apply'] ?? false)),
                  profileSections: @js($profileSections),
                  incomeVerification: @js($incomeVerification),
                  productQuestions: @js($productQuestions),
                  purposeLabels: @js(loan_purpose_options()),
                  readinessUrl: @js($readinessUrl),
                  i18n: @js([
                      'flexibleTerms' => __('borrower.apply.browse.flexible_terms'),
                      'monthsShort' => __('borrower.apply.browse.months_short'),
                      'months' => __('borrower.apply.quote.months'),
                      'back' => __('borrower.apply.back'),
                      'backProducts' => __('borrower.apply.back_products'),
                      'incomeDocument' => __('borrower.apply.income.income_document'),
                      'steps' => [
                          'quote' => __('borrower.apply.steps.quote'),
                          'personal' => __('borrower.apply.steps.personal'),
                          'residence' => __('borrower.apply.steps.residence'),
                          'kin' => __('borrower.apply.steps.kin'),
                          'activity' => __('borrower.apply.steps.activity'),
                          'income' => __('borrower.apply.steps.income'),
                          'product_questions' => __('borrower.apply.steps.product_questions'),
                          'asset_tenure' => __('borrower.apply.steps.asset_tenure'),
                      ],
                      'alerts' => [
                          'loadProduct' => __('borrower.apply.alerts.load_product'),
                          'selectPurpose' => __('borrower.apply.alerts.select_purpose'),
                          'selectGuarantor' => __('borrower.apply.alerts.select_guarantor'),
                          'guarantor_membership' => __('borrower.apply.alerts.guarantor_membership'),
                          'guarantor_phone' => __('borrower.apply.alerts.guarantor_phone'),
                          'guarantor_lookup_failed' => __('borrower.apply.alerts.guarantor_lookup_failed'),
                          'guarantor_external_incomplete' => __('borrower.apply.alerts.guarantor_external_incomplete'),
                          'guarantor_invite_failed' => __('borrower.apply.alerts.guarantor_invite_failed'),
                          'acceptTerms' => __('borrower.apply.alerts.accept_terms'),
                          'drawSignature' => __('borrower.apply.alerts.draw_signature'),
                          'submitTitle' => __('borrower.apply.alerts.submit_title'),
                          'submitMessage' => __('borrower.apply.alerts.submit_message'),
                      ],
                      'review' => [
                          'documentOnFile' => __('borrower.apply.income.document_on_file'),
                          'uploadAtStep' => __('borrower.apply.income.upload_at_step'),
                      ],
                  ]),
              })"
             x-init="init()"
             @beforeunload.window="persistDraft(true)"
             x-cloak>

            <div x-show="draftSavedAt" x-cloak class="mb-4 rounded-lg bg-gray-50 ring-1 ring-gray-200 px-3 py-2 text-xs text-gray-600">
                {{ __('borrower.apply.draft.autosaved') }}
            </div>

            {{-- Phase 1: Browse products --}}
            <div x-show="phase === 'browse'" class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6 sm:p-8">
                <h2 class="text-xl font-semibold mb-1">{{ __('borrower.apply.browse.title') }}</h2>
                <p class="text-sm text-gray-600 mb-5">{{ __('borrower.apply.browse.subtitle') }}</p>

                @if (($resumableDrafts ?? []) !== [])
                    <div class="mb-6 rounded-xl border border-amber-200 bg-amber-50/70 p-4">
                        <p class="text-sm font-semibold text-amber-900">{{ __('borrower.applications_list.drafts_title') }}</p>
                        <ul class="mt-3 space-y-2">
                            @foreach ($resumableDrafts as $draft)
                                <li class="flex flex-wrap items-center justify-between gap-2 bg-white rounded-lg ring-1 ring-amber-200 px-3 py-2.5">
                                    <div>
                                        <p class="text-sm font-medium text-gray-900">{{ $draft['label'] }}</p>
                                        <p class="text-xs text-gray-600">{{ $draft['detail'] }}</p>
                                    </div>
                                    <a href="{{ $draft['url'] }}" class="text-xs font-semibold text-amber-700 hover:underline shrink-0">{{ __('borrower.applications_list.resume') }} →</a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="flex gap-3 overflow-x-auto pb-2 snap-x snap-mandatory -mx-1 px-1">
                    @foreach ($products as $p)
                        @if (is_marketplace_loan_product($p->code))
                            <div class="snap-start shrink-0 w-64 rounded-xl border-2 border-sky-200 bg-sky-50 p-4 flex flex-col">
                                <span class="text-[10px] font-mono font-semibold text-sky-700 bg-sky-100 px-1.5 py-0.5 rounded">{{ $p->code }}</span>
                                <div class="mt-2 font-semibold text-sm">{{ $p->name }}</div>
                                <p class="text-[11px] text-gray-600 mt-2 flex-1">{{ __('borrower.marketplace.subtitle') }}</p>
                                <a href="{{ route('site.borrower.marketplace') }}" class="mt-3 text-xs font-semibold text-amber-700">{{ __('borrower.nav.marketplace') }} →</a>
                            </div>
                        @else
                            <button type="button" @click="openProduct(@js($wizardProductPayload($p)))"
                                    class="snap-start shrink-0 w-64 text-left rounded-xl border-2 border-gray-200 hover:border-amber-300 p-4 transition">
                                <span class="text-[10px] font-mono font-semibold text-amber-700 bg-amber-100 px-1.5 py-0.5 rounded">{{ $p->code }}</span>
                                <div class="mt-2 font-semibold text-sm">{{ $p->name }}</div>
                                <p class="text-[10px] font-semibold text-amber-800 mt-1">{{ loan_product_type_label($p) }}</p>
                                <p class="text-[11px] text-gray-500 mt-1 line-clamp-2">{{ $p->description ?: __('borrower.apply.browse.flexible_terms') }}</p>
                                <div class="text-[11px] text-gray-600 mt-2">
                                    {{ format_money($p->min_amount, false) }} – {{ format_money($p->max_amount, false) }}
                                    · {{ $p->tenure_min_months }}–{{ $p->tenure_max_months }} {{ __('borrower.apply.browse.months_short') }}
                                </div>
                                <p class="text-[11px] font-semibold text-gray-800 mt-2">
                                    {{ __('borrower.apply.product_summary.application_fee') }}: {{ format_money(loan_product_application_fee($customer, $p)) }}
                                </p>
                                <p class="mt-3 text-xs font-semibold text-amber-700">{{ __('borrower.apply.browse.view_details') }}</p>
                            </button>
                        @endif
                    @endforeach
                </div>
                <div class="mt-6 text-center">
                    <a href="{{ route('site.borrower.dashboard') }}" class="text-sm font-semibold text-gray-600 hover:text-gray-900">{{ __('borrower.apply.back_dashboard') }}</a>
                </div>
            </div>

            {{-- Phase 2: Product details + readiness --}}
            <div x-show="phase === 'details'" class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6 sm:p-8">
                <x-site.loan-product-details />
            </div>

            {{-- Phase 3: Application wizard --}}
            <div x-show="phase === 'application'">
                <x-site.apply-product-summary />

                @if ($errors->any())
                    <div class="mb-6 p-4 rounded-xl bg-red-50 border border-red-200 text-sm text-red-700">
                        <p class="font-semibold mb-1">{{ __('borrower.apply.errors_fix') }}</p>
                        <ul class="list-disc ml-5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('site.borrower.apply.submit') }}" enctype="multipart/form-data" novalidate
                      @submit="onSubmit($event)">
                    @csrf
                    @if ($reservation ?? null)
                        <input type="hidden" name="asset_reservation_id" value="{{ $reservation->id }}">
                    @endif

            {{-- Profile/KYC data always comes from the customer record — never re-collected in the wizard --}}
            <input type="hidden" name="first_name" value="{{ old('first_name', $customer->first_name) }}">
            <input type="hidden" name="last_name" value="{{ old('last_name', $customer->last_name) }}">
            <input type="hidden" name="date_of_birth" value="{{ old('date_of_birth', $customer->date_of_birth?->format('Y-m-d')) }}">
            <input type="hidden" name="gender" value="{{ old('gender', $customer->gender) }}">
            <input type="hidden" name="national_id" value="{{ old('national_id', $customer->national_id) }}">
            <input type="hidden" name="region" value="{{ old('region', $customer->region) }}">
            <input type="hidden" name="district" value="{{ old('district', $customer->district) }}">
            <input type="hidden" name="ward" value="{{ old('ward', $customer->ward) }}">
            <input type="hidden" name="street" value="{{ old('street', $customer->street ?? $customer->address) }}">
            <input type="hidden" name="nok_name" value="{{ old('nok_name', $customer->nok_name) }}">
            <input type="hidden" name="nok_relationship" value="{{ old('nok_relationship', $customer->nok_relationship) }}">
            <input type="hidden" name="nok_phone" value="{{ old('nok_phone', $customer->nok_phone) }}">
            <input type="hidden" name="nok_region" value="{{ old('nok_region', $customer->nok_region) }}">
            <input type="hidden" name="nok_district" value="{{ old('nok_district', $customer->nok_district) }}">
            <input type="hidden" name="activity_type" value="{{ old('activity_type', $customer->activity_type ?? $customer->employment_type) }}">
            <input type="hidden" name="income_range" value="{{ old('income_range', $customer->income_range) }}">
            @if ($assetApplication ?? null)
                <input type="hidden" name="requested_amount" value="{{ old('requested_amount', $assetApplication['remaining_loan']) }}">
                <input type="hidden" name="requested_tenure_months" value="{{ old('requested_tenure_months', $assetApplication['max_tenure_months']) }}">
                <input type="hidden" name="purpose" value="asset_financing">
            @endif
            @foreach ($customer->activity_details ?? [] as $detailKey => $detailValue)
                <input type="hidden" name="activity_details[{{ $detailKey }}]" value="{{ $detailValue }}">
            @endforeach

            <template x-if="current && !current.requires_guarantor">
                <input type="hidden" name="guarantor_mode" value="none">
            </template>

            {{-- Step progress --}}
            <ol class="flex items-center gap-1 mb-8 overflow-x-auto pb-2">
                <template x-for="(s, i) in steps" :key="s.key">
                    <li class="flex items-center gap-1 shrink-0">
                        <button type="button" @click="goto(i)"
                                :class="i === step ? 'bg-amber-500 text-gray-900 border-amber-500'
                                                   : (i < step ? 'bg-emerald-50 text-emerald-700 border-emerald-200'
                                                               : 'bg-white text-gray-500 border-gray-300')"
                                class="size-8 rounded-full grid place-items-center text-xs font-bold border-2 transition">
                            <template x-if="i < step"><svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 20 20" stroke="currentColor" stroke-width="3"><path d="M5 10l3 3 7-7"/></svg></template>
                            <template x-if="i >= step"><span x-text="i + 1"></span></template>
                        </button>
                        <span class="hidden sm:inline text-[11px] font-medium text-gray-600 mr-2" x-text="s.label"></span>
                        <span x-show="i < steps.length - 1" class="text-gray-300 hidden sm:inline">→</span>
                    </li>
                </template>
            </ol>

            <div class="bg-white rounded-2xl border border-gray-200 shadow-sm">

                    <input type="hidden" name="loan_product_id" :value="form.loan_product_id">

                {{-- Quote --}}
                <div x-show="currentStepKey === 'quote'" class="p-6 sm:p-8">
                    <h2 class="text-xl font-semibold mb-1">{{ __('borrower.apply.quote.title') }}</h2>
                    <p class="text-sm text-gray-600 mb-5">{{ __('borrower.apply.quote.subtitle') }}</p>
                    <template x-if="current">
                        <div class="space-y-5">
                            <div class="bg-gray-50 rounded-xl p-5">
                                <div class="flex justify-between text-sm mb-2"><span class="text-gray-600">{{ __('borrower.apply.quote.loan_amount') }}</span><span class="font-bold" x-text="formatTzs(form.requested_amount)"></span></div>
                                <input type="range" :min="current.min" :max="current.max" step="50000" x-model.number="form.requested_amount" @input="updateQuote()" class="w-full accent-amber-500">
                                <input type="hidden" name="requested_amount" :value="form.requested_amount">
                                <div class="flex justify-between text-sm mb-2 mt-4"><span class="text-gray-600">{{ __('borrower.apply.quote.tenure') }}</span><span class="font-bold"><span x-text="form.requested_tenure_months"></span> {{ __('borrower.apply.quote.months') }}</span></div>
                                <input type="range" :min="current.tmin" :max="current.tmax" step="1" x-model.number="form.requested_tenure_months" @input="updateQuote()" class="w-full accent-amber-500">
                                <input type="hidden" name="requested_tenure_months" :value="form.requested_tenure_months">
                                <div class="flex justify-between text-sm mt-4"><span class="text-gray-600">{{ __('borrower.apply.quote.repayment_frequency') }}</span><span class="font-medium capitalize" x-text="current.frequency || 'monthly'"></span></div>
                            </div>
                            <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-3">
                                <div class="rounded-xl ring-1 ring-gray-100 bg-white p-4"><p class="text-[10px] uppercase text-gray-500">{{ __('borrower.apply.quote.monthly_installment') }}</p><p class="font-bold mt-1" x-text="formatTzs(quote.monthly)"></p></div>
                                <div class="rounded-xl ring-1 ring-gray-100 bg-white p-4"><p class="text-[10px] uppercase text-gray-500">{{ __('borrower.apply.quote.weekly_installment') }}</p><p class="font-bold mt-1" x-text="formatTzs(quote.weekly)"></p></div>
                                <div class="rounded-xl ring-1 ring-gray-100 bg-white p-4"><p class="text-[10px] uppercase text-gray-500">{{ __('borrower.apply.quote.interest_est') }}</p><p class="font-bold mt-1" x-text="formatTzs(quote.interest)"></p></div>
                                <div class="rounded-xl ring-1 ring-gray-100 bg-white p-4"><p class="text-[10px] uppercase text-gray-500">{{ __('borrower.apply.quote.total_repayment') }}</p><p class="font-bold mt-1" x-text="formatTzs(quote.total)"></p></div>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.apply.quote.purpose') }}</label>
                                <select name="purpose" x-model="form.purpose" required class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm">
                                    <option value="">{{ __('borrower.apply.quote.select_purpose') }}</option>
                                    @foreach ($loanPurposes as $key => $label)
                                        <option value="{{ $key }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </template>
                </div>

                {{-- Asset lending tenure --}}
                <div x-show="currentStepKey === 'asset_tenure'" class="p-6 sm:p-8">
                    <h2 class="text-xl font-semibold mb-1">{{ __('borrower.apply.asset_tenure.title') }}</h2>
                    <p class="text-sm text-gray-600 mb-5">{{ __('borrower.apply.asset_tenure.subtitle') }}</p>
                    <template x-if="assetApplication">
                        <div class="space-y-5">
                            <div class="rounded-xl ring-1 ring-gray-200 bg-gray-50 p-5 space-y-2 text-sm">
                                <div class="flex justify-between gap-3"><span class="text-gray-500">{{ __('borrower.apply.review_step.asset') }}</span><span class="font-semibold text-right" x-text="assetApplication.asset_title"></span></div>
                                <div class="flex justify-between gap-3" x-show="assetApplication.supplier"><span class="text-gray-500">{{ __('borrower.marketplace.supplier') }}</span><span class="font-semibold" x-text="assetApplication.supplier"></span></div>
                                <div class="flex justify-between gap-3"><span class="text-gray-500">{{ __('borrower.marketplace.asset_value') }}</span><span class="font-semibold" x-text="formatTzs(assetApplication.asset_value)"></span></div>
                                <div class="flex justify-between gap-3"><span class="text-gray-500">{{ __('borrower.marketplace.deposit') }}</span><span class="font-semibold" x-text="formatTzs(assetApplication.deposit)"></span></div>
                                <div class="flex justify-between gap-3"><span class="text-gray-500">{{ __('borrower.apply.asset_tenure.financed_amount') }}</span><span class="font-semibold" x-text="formatTzs(assetApplication.remaining_loan)"></span></div>
                                <div class="flex justify-between gap-3"><span class="text-gray-500">{{ __('borrower.marketplace.weekly_installment') }}</span><span class="font-semibold" x-text="formatTzs(assetApplication.weekly_installment)"></span></div>
                            </div>
                            <div>
                                <div class="flex justify-between text-sm mb-2"><span class="text-gray-600">{{ __('borrower.apply.quote.tenure') }}</span><span class="font-bold"><span x-text="form.requested_tenure_months"></span> {{ __('borrower.apply.quote.months') }}</span></div>
                                <input type="range" min="1" :max="assetApplication.max_tenure_months" step="1" x-model.number="form.requested_tenure_months" class="w-full accent-amber-500">
                                <input type="hidden" name="requested_tenure_months" :value="form.requested_tenure_months">
                                <input type="hidden" name="requested_amount" :value="assetApplication.remaining_loan">
                                <p class="text-xs text-gray-500 mt-2">{{ __('borrower.apply.asset_tenure.max_hint', ['months' => '']) }} <span x-text="assetApplication.max_tenure_months"></span> {{ __('borrower.apply.quote.months') }}</p>
                            </div>
                        </div>
                    </template>
                </div>

                {{-- Guarantor --}}
                <div x-show="currentStepKey === 'guarantor'" class="p-6 sm:p-8">
                    <h2 class="text-xl font-semibold mb-1">{{ __('borrower.apply.guarantor') }}</h2>
                    <p class="text-sm text-gray-600 mb-4">{{ __('borrower.apply.guarantor_required') }}</p>
                    <div class="space-y-4">
                        <div class="flex flex-wrap gap-3">
                            <label class="inline-flex items-center gap-2 text-sm"><input type="radio" name="guarantor_mode" value="internal" x-model="form.guarantor_mode" class="text-amber-500"> {{ __('borrower.apply.internal_guarantor') }}</label>
                            <label class="inline-flex items-center gap-2 text-sm"><input type="radio" name="guarantor_mode" value="external" x-model="form.guarantor_mode" class="text-amber-500"> {{ __('borrower.apply.external_guarantor') }}</label>
                        </div>
                        <div x-show="form.guarantor_mode === 'internal'" class="space-y-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.apply.guarantor_fields.membership_no') }}</label>
                                <div class="flex rounded-lg ring-1 ring-gray-200 overflow-hidden">
                                    <span class="inline-flex items-center px-3 bg-gray-100 text-sm font-mono text-gray-600 border-r border-gray-200">KPF-TZ-</span>
                                    <input name="internal_member_no" placeholder="ABC12345" class="flex-1 border-0 px-3 py-2.5 text-sm font-mono focus:ring-0">
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.profile.fields.phone') }}</label>
                                <div class="flex rounded-lg ring-1 ring-gray-200 overflow-hidden">
                                    <span class="inline-flex items-center px-3 bg-gray-100 text-sm text-gray-600 border-r border-gray-200">+255</span>
                                    <input name="internal_guarantor_phone" inputmode="numeric" placeholder="712345678" class="flex-1 border-0 px-3 py-2.5 text-sm focus:ring-0">
                                </div>
                            </div>
                            <p x-show="guarantorLookup.ok" class="text-sm text-emerald-800 font-medium" x-text="guarantorLookup.label"></p>
                            <p x-show="guarantorLookup.error" class="text-sm text-red-700" x-text="guarantorLookup.error"></p>
                            <p class="text-xs text-gray-500">{{ __('borrower.apply.guarantor_fields.membership_hint_short') }}</p>
                        </div>
                        <input type="hidden" name="external_invitation_id" :value="externalGuarantor?.invitation_id || ''">
                        <div x-show="form.guarantor_mode === 'external'" class="grid sm:grid-cols-2 gap-4">
                            <div><label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.profile.fields.first_name') }}</label><input name="external_first_name" x-model="form.external_first_name" class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm"></div>
                            <div><label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.profile.fields.middle_name') }}</label><input name="external_middle_name" x-model="form.external_middle_name" class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm"></div>
                            <div><label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.profile.fields.last_name') }}</label><input name="external_last_name" x-model="form.external_last_name" class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm"></div>
                            <div><label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.apply.guarantor_fields.relationship') }}</label>
                                <select name="external_relationship" x-model="form.external_relationship" class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm">
                                    <option value="">{{ __('borrower.profile.select') }}</option>
                                    @foreach (trans('borrower.profile.guarantor_relationship_options') as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div><label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.profile.fields.phone') }}</label>
                                <div class="flex rounded-lg ring-1 ring-gray-200 overflow-hidden">
                                    <span class="inline-flex items-center px-3 bg-gray-100 text-sm text-gray-600 border-r border-gray-200">+255</span>
                                    <input name="external_phone" x-model="form.external_phone" inputmode="numeric" placeholder="712345678" class="flex-1 border-0 px-3 py-2.5 text-sm focus:ring-0">
                                </div>
                            </div>
                            <div><label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.profile.fields.email') }} {{ __('borrower.profile.optional') }}</label><input name="external_email" x-model="form.external_email" type="email" class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm"></div>
                            <div class="sm:col-span-2 grid sm:grid-cols-2 gap-4">
                                <div><label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.profile.fields.region') }}</label>
                                    <select name="external_region" x-model="form.external_region" @change="onExternalRegionChange()" class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm">
                                        <option value="">{{ __('borrower.profile.select_region') }}</option>
                                        @foreach (config('tanzania_locations') as $regionName => $districts)
                                            <option value="{{ $regionName }}">{{ $regionName }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div><label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.profile.fields.district') }}</label>
                                    <select name="external_district" x-model="form.external_district" class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm">
                                        <option value="">{{ __('borrower.profile.select_district') }}</option>
                                        <template x-for="d in externalDistrictOptions" :key="d">
                                            <option :value="d" x-text="d"></option>
                                        </template>
                                    </select>
                                </div>
                            </div>
                            <div class="sm:col-span-2" x-show="externalGuarantor?.invitation_url" x-cloak>
                                <p class="text-sm font-semibold text-emerald-900 mb-2">{{ __('borrower.apply.guarantor_fields.share_via') }}</p>
                                <p class="text-xs text-emerald-800 mb-3">{{ __('borrower.apply.guarantor_fields.share_ready') }}</p>
                                <div class="flex flex-wrap gap-2" x-data="{ copied: false }">
                                    <a x-show="externalGuarantor?.invitation_url" :href="externalGuarantor.whatsapp_url || '#'" :class="!externalGuarantor.whatsapp_url && 'pointer-events-none opacity-50'" target="_blank" rel="noopener"
                                       class="inline-flex items-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold px-4 py-2 rounded-full text-sm">
                                        {{ __('borrower.apply.guarantor_fields.share_whatsapp') }}
                                    </a>
                                    <a x-show="externalGuarantor?.invitation_url" :href="externalGuarantor.sms_url || '#'" :class="!externalGuarantor.sms_url && 'pointer-events-none opacity-50'"
                                       class="inline-flex items-center gap-2 bg-white ring-1 ring-emerald-300 text-emerald-900 font-semibold px-4 py-2 rounded-full text-sm">
                                        {{ __('borrower.apply.guarantor_fields.share_sms') }}
                                    </a>
                                    <a x-show="externalGuarantor?.invitation_url" :href="externalGuarantor.email_url || '#'" :class="!externalGuarantor.email_url && 'pointer-events-none opacity-50'"
                                       class="inline-flex items-center gap-2 bg-white ring-1 ring-emerald-300 text-emerald-900 font-semibold px-4 py-2 rounded-full text-sm">
                                        {{ __('borrower.apply.guarantor_fields.share_email') }}
                                    </a>
                                    <button type="button"
                                            x-show="externalGuarantor?.invitation_url"
                                            @click="navigator.clipboard.writeText(externalGuarantor.invitation_url); copied = true; setTimeout(() => copied = false, 2000)"
                                            class="inline-flex items-center gap-2 bg-white ring-1 ring-emerald-300 text-emerald-900 font-semibold px-4 py-2 rounded-full text-sm">
                                        <span x-text="copied ? @js(__('borrower.apply.guarantor_fields.link_copied')) : @js(__('borrower.apply.guarantor_fields.share_copy'))"></span>
                                    </button>
                                </div>
                            </div>
                            <div class="sm:col-span-2 rounded-xl bg-sky-50 ring-1 ring-sky-200 px-4 py-3 text-sm text-sky-900" x-show="!externalGuarantor?.invitation_url">
                                {{ __('borrower.apply.guarantor_fields.share_generate') }}
                            </div>
                            <div class="sm:col-span-2 rounded-xl bg-amber-50 ring-1 ring-amber-200 px-4 py-3 text-sm text-amber-900" x-show="externalGuarantor?.invitation_url" x-cloak>
                                {{ __('borrower.apply.guarantor_fields.share_ready_continue') }}
                            </div>
                        </div>
                        <p class="text-xs text-amber-700 font-medium">{{ __('borrower.apply.guarantor_fields.status_waiting') }}</p>
                    </div>
                </div>

                @php $membershipCfg = \App\Services\MembershipService::config(); @endphp
                <x-site.application-fee-step
                    :fee-quote="$feeQuote ?? null"
                    :bank-accounts="$bankAccounts ?? []"
                    :currency="$membershipCfg['currency'] ?? 'TZS'"
                    :payment-reference="$applicationFeePaymentRef ?? null"
                    :referral-wallet="$referralWallet ?? null"
                    :referral-settings="$referralSettings ?? []"
                />

                {{-- Product-specific questions --}}
                <div x-show="currentStepKey === 'product_questions'" class="p-6 sm:p-8">
                    <h2 class="text-xl font-semibold mb-1">{{ __('borrower.apply.product_questions.title') }}</h2>
                    <p class="text-sm text-gray-600 mb-5">{{ __('borrower.apply.product_questions.subtitle') }}</p>
                    @foreach ($productQuestions as $code => $block)
                        <div x-show="current && current.code === @js($code)" class="rounded-xl border border-gray-200 p-5">
                            <h3 class="text-sm font-semibold text-gray-900 mb-4">{{ $block['title'] ?? __('borrower.apply.product_questions.additional') }}</h3>
                            <div class="grid sm:grid-cols-2 gap-4">
                                @foreach ($block['fields'] as $field)
                                    <div class="{{ ($field['type'] ?? 'text') === 'textarea' ? 'sm:col-span-2' : '' }}">
                                        <label class="block text-xs font-medium text-gray-600 mb-1">{{ $field['label'] }}</label>
                                        @if (($field['type'] ?? 'text') === 'select')
                                            <select name="product_question[{{ $field['key'] }}]" class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm">
                                                <option value="">{{ __('borrower.profile.select') }}</option>
                                                @foreach ($field['options'] ?? [] as $value => $label)
                                                    <option value="{{ $value }}">{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        @elseif (($field['type'] ?? 'text') === 'textarea')
                                            <textarea name="product_question[{{ $field['key'] }}]" rows="3" placeholder="{{ $field['placeholder'] ?? '' }}" class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm"></textarea>
                                        @else
                                            <input type="text" name="product_question[{{ $field['key'] }}]" placeholder="{{ $field['placeholder'] ?? '' }}" class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm">
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- Review --}}
                <div x-show="currentStepKey === 'review'" class="p-6 sm:p-8">
                    <h2 class="text-xl font-semibold mb-1">{{ __('borrower.apply.review_step.title') }}</h2>
                    <p class="text-sm text-gray-600 mb-4">{{ __('borrower.apply.review_step.subtitle') }}</p>
                    @if (! ($applyRequirements['can_apply'] ?? false))
                        <div class="mb-4 rounded-xl bg-amber-50 ring-1 ring-amber-200 px-4 py-4 text-sm text-amber-900">
                            <p class="font-semibold">{{ __('borrower.apply.kyc_incomplete_submit') }}</p>
                            <ul class="mt-2 space-y-1 text-amber-800">
                                @foreach (($applyRequirements['items'] ?? []) as $item)
                                    @if (! ($item['complete'] ?? false))
                                        <li>
                                            {{ $item['label'] }}
                                            @if (! empty($item['action_url']))
                                                — <a href="{{ $item['action_url'] }}" class="font-semibold underline">{{ __('borrower.apply.details.complete_missing') }}</a>
                                            @endif
                                        </li>
                                    @endif
                                @endforeach
                            </ul>
                            @if ($applyRequirements['first_action_url'] ?? null)
                                <a href="{{ $applyRequirements['first_action_url'] }}" class="inline-flex mt-3 bg-white hover:bg-gray-50 text-gray-900 font-semibold px-4 py-2 rounded-full text-xs ring-1 ring-amber-300">
                                    {{ __('borrower.apply.details.complete_missing') }}
                                </a>
                            @endif
                        </div>
                    @endif
                    <div class="rounded-xl border border-gray-200 divide-y divide-gray-200 mb-5 text-sm">
                        <div class="px-4 py-3 flex justify-between gap-3"><div><span class="text-gray-500 block">{{ __('borrower.apply.review_step.product') }}</span><span class="font-medium" x-text="current ? current.name : '—'"></span></div><button type="button" @click="backToBrowse()" class="text-xs text-amber-700 shrink-0" x-show="! reservationMode">{{ __('borrower.apply.change') }}</button></div>
                        <template x-if="assetApplication">
                            <div class="px-4 py-3">
                                <span class="text-gray-500 block">{{ __('borrower.apply.review_step.asset') }}</span>
                                <span class="font-medium" x-text="assetApplication.asset_title"></span>
                                <p class="text-xs text-gray-500 mt-1">
                                    <span x-text="formatTzs(assetApplication.asset_value)"></span> ·
                                    {{ __('borrower.marketplace.deposit') }} <span x-text="formatTzs(assetApplication.deposit)"></span> ·
                                    <span x-text="assetApplication.max_tenure_months"></span> {{ __('borrower.apply.browse.months_short') }}
                                </p>
                            </div>
                        </template>
                        <div class="px-4 py-3 flex justify-between gap-3" x-show="hasStep('quote')"><div><span class="text-gray-500 block">{{ __('borrower.apply.review_step.amount_tenure') }}</span><span class="font-medium"><span x-text="formatTzs(form.requested_amount)"></span> · <span x-text="form.requested_tenure_months"></span> {{ __('borrower.apply.browse.months_short') }}</span></div><button type="button" @click="gotoKey('quote')" class="text-xs text-amber-700 shrink-0">{{ __('borrower.apply.edit') }}</button></div>
                        <div class="px-4 py-3 flex justify-between gap-3" x-show="hasStep('asset_tenure')"><div><span class="text-gray-500 block">{{ __('borrower.apply.review_step.amount_tenure') }}</span><span class="font-medium"><span x-text="formatTzs(form.requested_amount)"></span> · <span x-text="form.requested_tenure_months"></span> {{ __('borrower.apply.browse.months_short') }}</span></div><button type="button" @click="gotoKey('asset_tenure')" class="text-xs text-amber-700 shrink-0">{{ __('borrower.apply.edit') }}</button></div>
                        <div class="px-4 py-3" x-show="hasStep('quote')"><span class="text-gray-500 block">{{ __('borrower.apply.review_step.purpose') }}</span><span class="font-medium" x-text="purposeLabels[form.purpose] || form.purpose || '—'"></span></div>
                        <div class="px-4 py-3">
                            <span class="text-gray-500 block">{{ __('borrower.apply.review_step.profile_on_file') }}</span>
                            <span class="font-medium" x-text="review.personal"></span>
                            <p class="text-xs text-gray-500 mt-1" x-show="review.residence" x-text="review.residence"></p>
                            <a :href="profileUrl" class="text-xs text-amber-700 font-medium mt-1 inline-block">{{ __('borrower.apply.edit_profile') }}</a>
                        </div>
                        <div class="px-4 py-3 flex justify-between gap-3" x-show="hasStep('guarantor')"><div><span class="text-gray-500 block">{{ __('borrower.apply.guarantor') }}</span><span class="font-medium" x-text="review.guarantor || '—'"></span></div><button type="button" @click="gotoKey('guarantor')" class="text-xs text-amber-700 shrink-0">{{ __('borrower.apply.edit') }}</button></div>
                    </div>
                </div>

                {{-- Signature --}}
                <div x-show="currentStepKey === 'signature'" class="p-6 sm:p-8">
                    <h2 class="text-xl font-semibold mb-1">{{ __('borrower.apply.signature_title') }}</h2>
                    <p class="text-sm text-gray-600 mb-5">{{ __('borrower.apply.signature_subtitle') }}</p>
                    <label class="flex items-start gap-3 text-sm text-gray-700 mb-5">
                        <input type="checkbox" name="consent" value="1" required class="mt-1 rounded border-gray-300 text-amber-500 focus:ring-amber-500">
                        <span>{{ __('borrower.apply.signature_consent', ['brand' => brand_name()]) }}</span>
                    </label>
                    <x-site.signature-pad :default-name="trim(($customer->first_name ?? '').' '.($customer->last_name ?? ''))" />
                </div>

                <div class="px-6 sm:px-8 py-4 border-t border-gray-200 bg-gray-50 rounded-b-2xl flex items-center justify-between">
                    <button type="button" @click="step > 0 ? prev() : backToDetails()" class="text-sm font-medium text-gray-600 hover:text-gray-900" x-text="step > 0 ? i18n.back : i18n.backProducts"></button>
                    <div class="ml-auto flex items-center gap-3">
                        <a href="{{ route('site.borrower.dashboard') }}" class="text-sm text-gray-500 hover:text-gray-700">{{ __('borrower.apply.cancel') }}</a>
                        <button type="button" @click="next()" :disabled="guarantorInvitePreparing" x-show="currentStepKey !== 'signature' && currentStepKey !== 'application_fee'" class="bg-amber-500 hover:bg-amber-400 disabled:opacity-60 text-gray-900 font-semibold px-5 py-2.5 rounded-full text-sm">
                            <span x-text="guarantorInvitePreparing ? @js(__('borrower.apply.application_fee.processing')) : @js(__('borrower.apply.continue'))"></span>
                        </button>
                        <button type="button" @click="next()" x-show="currentStepKey === 'application_fee' && (['paid','waived','pending'].includes(applicationFeeState?.status || '') || applicationFee <= 0)" class="bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-5 py-2.5 rounded-full text-sm">{{ __('borrower.apply.continue') }}</button>
                        <button type="submit" x-show="currentStepKey === 'signature'" class="bg-gray-900 hover:bg-gray-800 text-white font-semibold px-5 py-2.5 rounded-full text-sm">{{ __('borrower.apply.submit') }}</button>
                    </div>
                </div>
            </div>
                </form>
            </div>
        </div>
    </div>

    @stack('scripts')
    <script>
        function tzAddress(locations, initialRegion, initialDistrict, labels) {
            return {
                locations,
                labels: labels || {},
                region: initialRegion || '',
                district: initialDistrict || '',
                districtOptions: [],
                init() { this.refreshDistricts(); },
                onRegionChange() { this.district = ''; this.refreshDistricts(); },
                refreshDistricts() {
                    this.districtOptions = this.region && this.locations[this.region] ? this.locations[this.region] : [];
                },
            };
        }
        function applyWizard(config) {
            return {
                products: config.products,
                applicationFee: config.applicationFee,
                applicationFeePayUrl: config.applicationFeePayUrl || '',
                applicationFeeState: config.savedDraft?.application_fee || null,
                applicationFeePaid: false,
                feeChannel: 'mobile_money',
                feePhone: @js(old('payment_phone', $customer->phone ?? '')),
                feeUseWallet: false,
                feePaying: false,
                feePaymentReference: @js($applicationFeePaymentRef ?? null),
                purposeLabels: config.purposeLabels,
                productQuestions: config.productQuestions,
                profileSections: config.profileSections,
                incomeVerification: config.incomeVerification,
                readinessUrl: config.readinessUrl,
                guarantorLookupUrl: config.guarantorLookupUrl || '',
                guarantorInviteUrl: config.guarantorInviteUrl || '',
                tanzaniaLocations: config.tanzaniaLocations || {},
                draftSaveUrl: config.draftSaveUrl || '',
                reservationId: config.reservationId || null,
                draftSavedAt: null,
                draftSaveTimer: null,
                guarantorLookup: { ok: false, label: '', error: '', memberKey: '', phone: '' },
                externalGuarantor: config.savedDraft?.external_guarantor || null,
                guarantorInvitePreparing: false,
                initialPlan: config.initialPlan || [],
                assetApplication: config.assetApplication || null,
                reservationMode: !! config.reservationMode,
                marketplaceOnlyCodes: config.marketplaceOnlyCodes || [],
                marketplaceUrl: config.marketplaceUrl || '',
                profileUrl: config.profileUrl || '',
                canApply: !! config.canApply,
                i18n: config.i18n,
                phase: 'browse',
                readiness: null,
                readinessLoading: false,
                steps: [],
                step: 0,
                current: null,
                form: {
                    loan_product_id: null,
                    requested_amount: 0,
                    requested_tenure_months: 0,
                    purpose: '',
                    guarantor_mode: 'internal',
                    income_type: 'bank',
                    external_first_name: '',
                    external_middle_name: '',
                    external_last_name: '',
                    external_relationship: '',
                    external_phone: '',
                    external_email: '',
                    external_region: '',
                    external_district: '',
                },
                quote: { monthly: 0, weekly: 0, interest: 0, total: 0, fees: 0 },
                review: { personal: '', residence: '', nok: '', activity: '', guarantor: '' },

                get currentStepKey() {
                    return this.steps[this.step]?.key ?? '';
                },

                get externalDistrictOptions() {
                    const r = this.form.external_region;
                    return r && this.tanzaniaLocations[r] ? this.tanzaniaLocations[r] : [];
                },

                init() {
                    this.syncFeePaidState();
                    window.applyWizardSaveDraft = () => this.persistDraft(true);
                    this.$watch('phase', () => this.scheduleDraftSave());
                    this.$watch('step', () => this.scheduleDraftSave());
                    if (this.reservationMode && this.assetApplication) {
                        this.beginReservationApplication();
                        return;
                    }
                    if (config.savedDraft && this.restoreDraft(config.savedDraft)) {
                        return;
                    }
                    if (config.preselect) {
                        const p = this.products.find(x => x.id == config.preselect);
                        if (p) this.openProduct(p);
                    }
                },

                scheduleDraftSave() {
                    clearTimeout(this.draftSaveTimer);
                    this.draftSaveTimer = setTimeout(() => this.persistDraft(), 900);
                },

                buildDraftPayload() {
                    const inputs = {};
                    if (this.phase === 'application') {
                        const fd = new FormData(this.formRoot());
                        for (const [key, value] of fd.entries()) {
                            if (value instanceof File) continue;
                            inputs[key] = value;
                        }
                    }
                    return {
                        phase: this.phase,
                        step: this.step,
                        loan_product_id: this.form.loan_product_id,
                        asset_reservation_id: this.reservationId,
                        form: this.form,
                        inputs,
                        guarantor_lookup: this.guarantorLookup.ok ? this.guarantorLookup : null,
                        application_fee: this.applicationFeeState,
                        external_guarantor: this.externalGuarantor,
                    };
                },

                syncFeePaidState() {
                    const st = this.applicationFeeState?.status;
                    this.applicationFeePaid = this.applicationFee <= 0
                        || ['paid', 'waived'].includes(st || '');
                },

                feeGateSatisfied() {
                    return this.applicationFee <= 0
                        || ['paid', 'waived', 'pending'].includes(this.applicationFeeState?.status || '');
                },

                feeGateRequiredForStep(stepKey) {
                    const feeIdx = this.steps.findIndex(s => s.key === 'application_fee');
                    const targetIdx = this.steps.findIndex(s => s.key === stepKey);
                    return feeIdx >= 0 && targetIdx > feeIdx && this.applicationFee > 0;
                },

                async autoWaiveApplicationFeeIfNeeded() {
                    if (this.applicationFee > 0 || this.applicationFeePaid) return;
                    this.applicationFeeState = {
                        status: 'waived',
                        reference: null,
                        channel: 'waived',
                        amount: 0,
                        paid_at: new Date().toISOString(),
                    };
                    this.syncFeePaidState();
                    await this.persistDraft(true);
                },

                onApplicationFeeStep() {
                    if (this.currentStepKey !== 'application_fee') return;
                    this.autoWaiveApplicationFeeIfNeeded();
                },

                async payApplicationFee() {
                    if (! this.applicationFeePayUrl || ! this.form.loan_product_id) return;
                    this.feePaying = true;
                    try {
                        const body = {
                            loan_product_id: this.form.loan_product_id,
                            channel: this.feeChannel || 'mobile_money',
                            payment_phone: this.feePhone || '',
                            use_wallet: !!this.feeUseWallet,
                        };
                        const res = await fetch(this.applicationFeePayUrl, {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'Content-Type': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '',
                            },
                            credentials: 'same-origin',
                            body: JSON.stringify(body),
                        });
                        const data = await res.json();
                        if (! res.ok || ! data.ok) {
                            throw new Error(data.message || 'Payment failed');
                        }
                        this.applicationFeeState = data.fee;
                        this.syncFeePaidState();
                        await this.persistDraft(true);
                        alert(data.message || @js(__('borrower.apply.application_fee.paid')));
                    } catch (e) {
                        alert(e?.message || @js(__('borrower.apply.application_fee.failed')));
                    } finally {
                        this.feePaying = false;
                    }
                },

                persistDraft(sync = false) {
                    if (! this.draftSaveUrl || this.phase === 'browse') {
                        if (this.phase === 'browse' && this.draftSaveUrl) {
                            const clear = () => fetch(this.draftSaveUrl, {
                                method: 'PUT',
                                headers: this.draftHeaders(),
                                credentials: 'same-origin',
                                body: JSON.stringify({ phase: 'browse' }),
                            });
                            return sync ? clear() : clear().catch(() => {});
                        }
                        return Promise.resolve();
                    }
                    const request = () => fetch(this.draftSaveUrl, {
                        method: 'PUT',
                        headers: this.draftHeaders(),
                        credentials: 'same-origin',
                        body: JSON.stringify(this.buildDraftPayload()),
                    }).then(res => res.ok ? res.json() : Promise.reject(res))
                      .then(() => { this.draftSavedAt = new Date().toLocaleTimeString(); });

                    return sync ? request().catch(() => {}) : request().catch(() => {});
                },

                draftHeaders() {
                    return {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '',
                    };
                },

                restoreFormInputs(inputs) {
                    const root = this.formRoot();
                    Object.entries(inputs || {}).forEach(([name, value]) => {
                        const el = root.querySelector(`[name="${name}"]`);
                        if (! el || el.type === 'file') return;
                        if (el.type === 'radio') {
                            const radio = root.querySelector(`[name="${name}"][value="${value}"]`);
                            if (radio) radio.checked = true;
                        } else {
                            el.value = value;
                        }
                        el.dispatchEvent(new Event('change', { bubbles: true }));
                    });
                },

                restoreDraft(draft) {
                    const product = this.products.find(p => p.id == draft.loan_product_id);
                    if (! product) return false;
                    this.current = product;
                    Object.assign(this.form, draft.form || {});
                    if (draft.inputs) {
                        this.restoreFormInputs(draft.inputs);
                        [
                            'external_first_name', 'external_middle_name', 'external_last_name',
                            'external_relationship', 'external_phone', 'external_email',
                            'external_region', 'external_district',
                        ].forEach((key) => {
                            if (draft.inputs[key]) this.form[key] = draft.inputs[key];
                        });
                    }
                    if (draft.guarantor_lookup) this.guarantorLookup = draft.guarantor_lookup;
                    if (draft.application_fee) this.applicationFeeState = draft.application_fee;
                    if (draft.external_guarantor) this.externalGuarantor = draft.external_guarantor;
                    this.syncFeePaidState();
                    this.phase = draft.phase;
                    if (draft.phase === 'details') {
                        this.loadReadiness(product.id);
                        window.scrollTo({ top: 0, behavior: 'smooth' });
                        return true;
                    }
                    if (draft.phase === 'application') {
                        this.phase = 'application';
                        const resumeStep = draft.step || 0;
                        this.loadReadiness(product.id).then(() => {
                            this.rebuildSteps();
                            this.step = Math.min(resumeStep, Math.max(0, this.steps.length - 1));
                            this.updateQuote();
                        });
                        window.scrollTo({ top: 0, behavior: 'smooth' });
                        return true;
                    }
                    return false;
                },

                isMarketplaceProduct(product) {
                    const code = (product?.code || '').toUpperCase();
                    return this.marketplaceOnlyCodes.map(c => c.toUpperCase()).includes(code);
                },

                beginReservationApplication() {
                    const p = this.products.find(x => x.id == config.preselect);
                    if (! p) return;
                    this.current = p;
                    this.form.loan_product_id = p.id;
                    this.form.requested_amount = this.assetApplication.remaining_loan;
                    this.form.requested_tenure_months = this.assetApplication.max_tenure_months;
                    this.form.purpose = this.assetApplication.purpose || 'asset_financing';
                    if (! p.requires_guarantor) this.form.guarantor_mode = 'none';
                    this.phase = 'application';
                    this.steps = this.initialPlan.map(s => ({ key: s.key, label: s.label }));
                    this.step = 0;
                    this.loadReadiness(p.id);
                },

                openProduct(p) {
                    if (this.isMarketplaceProduct(p)) {
                        window.location.href = this.marketplaceUrl;
                        return;
                    }
                    this.current = p;
                    this.form.loan_product_id = p.id;
                    this.phase = 'details';
                    this.loadReadiness(p.id);
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                },

                backToBrowse() {
                    this.phase = 'browse';
                    this.readiness = null;
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                },

                backToDetails() {
                    this.phase = 'details';
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                },

                completeMissingRequirements() {
                    const url = this.readiness?.missing_action_url;
                    if (url) {
                        window.location.href = url;
                        return;
                    }
                    this.startApplication();
                },

                loadReadiness(productId) {
                    this.readinessLoading = true;
                    this.readiness = null;
                    const url = this.readinessUrl.replace('__ID__', encodeURIComponent(productId));
                    return fetch(url, {
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        credentials: 'same-origin',
                    })
                        .then(res => res.ok ? res.json() : Promise.reject(res))
                        .then(data => {
                            this.readiness = data;
                            if (this.phase === 'application' && this.current) {
                                this.rebuildSteps();
                            }
                            if (data.fees?.application !== undefined) {
                                this.applicationFee = data.fees.application;
                            }
                            return data;
                        })
                        .catch(() => { this.readiness = null; alert(this.i18n.alerts.loadProduct); })
                        .finally(() => { this.readinessLoading = false; });
                },

                startApplication() {
                    if (! this.current) return;
                    this.selectProduct(this.current, false);
                    this.phase = 'application';
                    this.rebuildSteps();
                    this.step = 0;
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                },

                rebuildSteps() {
                    if (this.readiness?.step_plan?.length) {
                        this.steps = this.readiness.step_plan.map(s => ({ key: s.key, label: s.label }));
                    } else if (this.initialPlan?.length) {
                        this.steps = this.initialPlan.map(s => ({ key: s.key, label: s.label }));
                    } else {
                        const stepLabels = this.i18n.steps;
                        const steps = [];
                        if (! this.isMarketplaceProduct(this.current)) {
                            steps.push({ key: 'quote', label: stepLabels.quote });
                        } else {
                            steps.push({ key: 'asset_tenure', label: stepLabels.asset_tenure || stepLabels.quote });
                        }
                        if (this.current?.requires_guarantor) {
                            steps.push({ key: 'guarantor', label: @js(__('borrower.apply.guarantor')) });
                        }
                        steps.push({ key: 'application_fee', label: @js(__('borrower.apply.steps.application_fee')) });
                        if (this.current?.code && this.productQuestions[this.current.code]) {
                            steps.push({ key: 'product_questions', label: stepLabels.product_questions });
                        }
                        steps.push({ key: 'review', label: @js(__('borrower.apply.review')) });
                        steps.push({ key: 'signature', label: @js(__('borrower.apply.sign')) });
                        this.steps = steps;
                    }
                    if (this.step >= this.steps.length) this.step = this.steps.length - 1;
                },

                selectProduct(p, rebuild = true) {
                    this.current = p;
                    this.form.loan_product_id = p.id;
                    if (! this.form.requested_amount || this.form.requested_amount < p.min) this.form.requested_amount = p.min;
                    if (! this.form.requested_tenure_months || this.form.requested_tenure_months < p.tmin) this.form.requested_tenure_months = p.tmin;
                    if (! p.requires_guarantor) this.form.guarantor_mode = 'none';
                    else if (this.form.guarantor_mode === 'none') this.form.guarantor_mode = 'internal';
                    this.updateQuote();
                    if (rebuild) this.rebuildSteps();
                },

                estimateEmi(principal, rate, months) {
                    if (principal <= 0 || months <= 0) return 0;
                    if (rate <= 0) return Math.round(principal / months);
                    const pow = Math.pow(1 + rate, months);
                    return Math.round(principal * rate * pow / (pow - 1));
                },

                resolveMonthlyRate(product, amount) {
                    if (! product) return 0;
                    const tiers = product.tiers || [];
                    if (tiers.length) {
                        const tier = tiers.find(t => amount >= t.min && amount <= t.max);
                        if (tier) return tier.rate;
                    }
                    return product.rate || 0;
                },

                updateQuote() {
                    if (! this.current) return;
                    const rate = this.resolveMonthlyRate(this.current, this.form.requested_amount);
                    const emi = this.estimateEmi(this.form.requested_amount, rate, this.form.requested_tenure_months);
                    const interest = Math.max(0, (emi * this.form.requested_tenure_months) - this.form.requested_amount);
                    this.quote = {
                        monthly: emi,
                        weekly: Math.round(emi / 4.33),
                        interest,
                        fees: this.applicationFee,
                        total: (emi * this.form.requested_tenure_months) + this.applicationFee,
                    };
                },

                hasStep(key) {
                    return this.steps.some(s => s.key === key);
                },

                gotoKey(key) {
                    const i = this.steps.findIndex(s => s.key === key);
                    if (i >= 0 && i <= this.step) this.step = i;
                },

                refreshReview(formEl) {
                    const fd = new FormData(formEl);
                    const g = (n) => fd.get(n) || '';
                    this.review.personal = [g('first_name'), g('last_name'), g('national_id')].filter(Boolean).join(' · ');
                    this.review.residence = [g('street'), g('ward'), g('district'), g('region')].filter(Boolean).join(', ');
                    this.review.nok = [g('nok_name'), g('nok_relationship'), g('nok_phone')].filter(Boolean).join(' · ');
                    this.review.activity = [g('activity_type'), g('income_range')].filter(Boolean).join(' · ');
                    if (this.form.guarantor_mode === 'internal') this.review.guarantor = g('internal_member_no');
                    if (this.form.guarantor_mode === 'external') {
                        const name = [this.form.external_first_name, this.form.external_last_name].filter(Boolean).join(' ');
                        this.review.guarantor = name || '—';
                    }
                },

                formRoot() {
                    return this.$el.querySelector('form') || this.$el;
                },

                onExternalRegionChange() {
                    this.form.external_district = '';
                },

                readFormField(name) {
                    const root = this.formRoot();
                    if (Object.prototype.hasOwnProperty.call(this.form, name)) {
                        const fromModel = this.form[name];
                        if (fromModel !== undefined && fromModel !== null && String(fromModel).trim() !== '') {
                            return String(fromModel).trim();
                        }
                    }
                    const radios = root.querySelectorAll(`[name="${name}"]`);
                    if (radios.length && radios[0].type === 'radio') {
                        const checked = root.querySelector(`[name="${name}"]:checked`);
                        return (checked?.value || '').toString().trim();
                    }
                    const el = root.querySelector(`[name="${name}"]`);
                    return el ? (el.value || '').toString().trim() : '';
                },

                externalGuarantorPayload() {
                    return {
                        loan_product_id: this.form.loan_product_id,
                        external_first_name: this.readFormField('external_first_name') || this.form.external_first_name,
                        external_middle_name: this.readFormField('external_middle_name') || this.form.external_middle_name,
                        external_last_name: this.readFormField('external_last_name') || this.form.external_last_name,
                        external_phone: this.readFormField('external_phone') || this.form.external_phone,
                        external_email: this.readFormField('external_email') || this.form.external_email,
                        external_relationship: this.readFormField('external_relationship') || this.form.external_relationship,
                        external_region: this.readFormField('external_region') || this.form.external_region,
                        external_district: this.readFormField('external_district') || this.form.external_district,
                        external_invitation_id: this.externalGuarantor?.invitation_id || null,
                    };
                },

                externalGuarantorMissingFields() {
                    const labels = {
                        external_first_name: @js(__('borrower.profile.fields.first_name')),
                        external_last_name: @js(__('borrower.profile.fields.last_name')),
                        external_relationship: @js(__('borrower.apply.guarantor_fields.relationship')),
                        external_phone: @js(__('borrower.profile.fields.phone')),
                        external_region: @js(__('borrower.profile.fields.region')),
                        external_district: @js(__('borrower.profile.fields.district')),
                    };
                    const p = this.externalGuarantorPayload();
                    return Object.entries(labels).filter(([key]) => ! (p[key] || '').toString().trim()).map(([, label]) => label);
                },

                isExternalGuarantorComplete() {
                    return this.externalGuarantorMissingFields().length === 0;
                },

                async prepareExternalGuarantorInvite() {
                    if (! this.guarantorInviteUrl) {
                        alert(this.i18n.alerts.guarantor_invite_failed);
                        return false;
                    }
                    if (! this.form.loan_product_id) {
                        alert(this.i18n.alerts.loadProduct);
                        return false;
                    }
                    this.guarantorInvitePreparing = true;
                    try {
                        const res = await fetch(this.guarantorInviteUrl, {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'Content-Type': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '',
                            },
                            credentials: 'same-origin',
                            body: JSON.stringify(this.externalGuarantorPayload()),
                        });
                        const data = await res.json().catch(() => ({}));
                        if (! res.ok || ! data.ok || ! data.share) {
                            alert(data.message || this.i18n.alerts.guarantor_invite_failed);
                            return false;
                        }
                        this.externalGuarantor = data.share;
                        return true;
                    } catch {
                        alert(this.i18n.alerts.guarantor_invite_failed);
                        return false;
                    } finally {
                        this.guarantorInvitePreparing = false;
                    }
                },

                async validateStep() {
                    if (this.currentStepKey === 'quote' && this.hasStep('quote') && ! this.form.purpose) {
                        alert(this.i18n.alerts.selectPurpose);
                        return false;
                    }
                    if (this.currentStepKey === 'guarantor' && this.current?.requires_guarantor) {
                        if (! this.form.guarantor_mode || this.form.guarantor_mode === 'none') {
                            alert(this.i18n.alerts.selectGuarantor);
                            return false;
                        }
                        if (this.form.guarantor_mode === 'internal') {
                            return await this.verifyInternalGuarantor();
                        }
                        if (this.form.guarantor_mode === 'external') {
                            const missing = this.externalGuarantorMissingFields();
                            if (missing.length) {
                                alert(@js(__('borrower.apply.alerts.guarantor_external_incomplete')) + ': ' + missing.join(', '));
                                return false;
                            }
                            return true;
                        }
                    }
                    if (this.currentStepKey === 'application_fee') {
                        if (this.applicationFee > 0) {
                            const st = this.applicationFeeState?.status || '';
                            if (! ['paid', 'waived', 'pending'].includes(st)) {
                                alert(@js(__('borrower.apply.application_fee.required_before_continue')));
                                return false;
                            }
                        } else if (! this.applicationFeePaid) {
                            await this.autoWaiveApplicationFeeIfNeeded();
                        }
                    }
                    const nextKey = this.steps[this.step + 1]?.key;
                    if (nextKey && this.feeGateRequiredForStep(nextKey) && ! this.feeGateSatisfied()) {
                        alert(@js(__('borrower.apply.application_fee.required_before_continue')));
                        return false;
                    }
                    return true;
                },

                async verifyInternalGuarantor() {
                    const fd = new FormData(this.formRoot());
                    const member = (fd.get('internal_member_no') || '').toString().trim();
                    const phone = (fd.get('internal_guarantor_phone') || '').toString().trim();
                    if (! member) {
                        alert(this.i18n.alerts.guarantor_membership);
                        return false;
                    }
                    if (! phone) {
                        alert(this.i18n.alerts.guarantor_phone);
                        return false;
                    }
                    if (this.guarantorLookup.ok && this.guarantorLookup.memberKey === member && this.guarantorLookup.phone === phone) {
                        return true;
                    }
                    this.guarantorLookup = { ok: false, label: '', error: '', memberKey: member, phone };
                    try {
                        const res = await fetch(this.guarantorLookupUrl, {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'Content-Type': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '',
                            },
                            credentials: 'same-origin',
                            body: JSON.stringify({ membership_no: member, phone }),
                        });
                        const data = await res.json().catch(() => ({}));
                        if (! res.ok || ! data.ok) {
                            this.guarantorLookup.error = data.message || this.i18n.alerts.guarantor_lookup_failed;
                            alert(this.guarantorLookup.error);
                            return false;
                        }
                        this.guarantorLookup = { ok: true, label: data.label || data.name, error: '', memberKey: member, phone };
                        return true;
                    } catch {
                        this.guarantorLookup.error = this.i18n.alerts.guarantor_lookup_failed;
                        alert(this.guarantorLookup.error);
                        return false;
                    }
                },

                async next() {
                    if (this.guarantorInvitePreparing) return;
                    if (! await this.validateStep()) return;

                    if (this.currentStepKey === 'guarantor' && this.form.guarantor_mode === 'external' && ! this.externalGuarantor?.invitation_url) {
                        const ok = await this.prepareExternalGuarantorInvite();
                        if (! ok) return;
                        await this.persistDraft(true);
                    }

                    await this.persistDraft(true);
                    const nextKey = this.steps[this.step + 1]?.key;
                    if (nextKey === 'review') {
                        this.refreshReview(this.formRoot());
                    }
                    if (this.step < this.steps.length - 1) {
                        this.step++;
                        window.scrollTo({ top: 0, behavior: 'smooth' });
                    }
                },

                prev() {
                    if (this.step > 0) {
                        this.step--;
                        window.scrollTo({ top: 0, behavior: 'smooth' });
                    }
                },

                goto(i) {
                    if (i <= this.step) this.step = i;
                },

                onSubmit(e) {
                    if (! this.canApply) {
                        e.preventDefault();
                        const url = @js($applyRequirements['first_action_url'] ?? null);
                        if (url && confirm(@js(__('borrower.apply.kyc_incomplete_submit')))) {
                            window.location.href = url;
                        } else {
                            alert(@js(__('borrower.apply.kyc_incomplete_submit')));
                        }
                        return;
                    }
                    const consent = e.target.elements['consent'];
                    const sig = e.target.elements['signature_data'];
                    if (consent && ! consent.checked) {
                        e.preventDefault();
                        alert(this.i18n.alerts.acceptTerms);
                        return;
                    }
                    if (sig && ! sig.value) {
                        e.preventDefault();
                        alert(this.i18n.alerts.drawSignature);
                        return;
                    }
                    e.preventDefault();
                    window.confirmForm(e.target, {
                        title: this.i18n.alerts.submitTitle,
                        message: this.i18n.alerts.submitMessage,
                        confirmLabel: @js(__('borrower.apply.submit')),
                        confirmClass: 'bg-gray-900 hover:bg-gray-800 text-white',
                    });
                },

                formatTzs(v, decimals = 0) {
                    return (window.formatMoney || ((x) => 'TZS ' + x))(v, { currency: 'TZS', decimals });
                },
            };
        }
    </script>
</x-site.borrower-layout>
