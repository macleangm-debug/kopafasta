<x-site.borrower-layout :title="brand_title(__('borrower.apply.title'))" active="loans" content-width="narrow">
    <div>

        <div class="mb-6">
            <p class="text-xs uppercase tracking-widest text-brand mb-1">{{ brand_name() }} {{ __('borrower.apply.smart_application') }}</p>
            <h1 class="text-2xl sm:text-3xl font-bold tracking-tight">{{ __('borrower.apply.title') }}</h1>
            <p class="mt-1 text-sm text-gray-500">{{ __('borrower.apply.subtitle') }}</p>
            <p x-show="draftReference" x-cloak class="mt-2 text-sm text-gray-600">
                {{ __('borrower.apply.submit_step.reference') }}:
                <span class="font-mono font-semibold text-gray-900" x-text="draftReference"></span>
            </p>
        </div>

        @if (session('error'))
            <div class="mb-4 rounded-xl bg-red-50 ring-1 ring-red-200 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>
        @endif
        @if (session('status'))
            <div class="mb-4 rounded-xl bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="mb-4 rounded-xl bg-red-50 ring-1 ring-red-200 px-4 py-3 text-sm text-red-800">
                <p class="font-semibold mb-1">{{ __('borrower.apply.errors_fix') }}</p>
                <ul class="list-disc ml-5 space-y-1">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif

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
                                    @if (! empty($item['detail']))
                                        <span class="block text-xs text-brand mt-0.5">{{ $item['detail'] }}</span>
                                    @endif
                                    @if (! empty($item['action_url']))
                                        <a href="{{ $item['action_url'] }}" class="font-semibold underline">{{ __('borrower.apply.details.complete_missing') }}</a>
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
            $activityLabels = activity_type_options();
            $incomeRangeLabels = collect(config('income_ranges', []))->mapWithKeys(fn ($v, $k) => [$k => $v['label'] ?? $k])->all();
            $borrowerSnapshot = [
                'personal' => trim(collect([$customer->first_name, $customer->last_name])->filter()->implode(' '))
                    . ($customer->national_id ? ' · '.$customer->national_id : ''),
                'employment' => trim(collect([
                    $activityLabels[$customer->activity_type ?? $customer->employment_type ?? ''] ?? ($customer->activity_type ?? $customer->employment_type),
                    $incomeRangeLabels[$customer->income_range ?? ''] ?? $customer->income_range,
                ])->filter()->implode(' · ')),
                'residence' => collect([$customer->street ?? $customer->address, $customer->ward, $customer->district, $customer->region])->filter()->implode(', '),
            ];
            $verifiedLegalName = $customer->full_name;
            $identityVerified = app(\App\Services\NidaVerificationService::class)->isVerified($customer)
                || filled($customer->nida_verified_at);
        @endphp

        <div x-data="applyWizard({
                  products: @js($wizardProducts->map($wizardProductPayload)->values()->all()),
                  guarantorLookupUrl: @js(route('site.borrower.apply.guarantor-lookup')),
                  groupMemberLookupUrl: @js($groupMemberLookupUrl ?? route('site.borrower.apply.group-member-lookup')),
                  groupMemberInviteUrl: @js($groupMemberInviteUrl ?? route('site.borrower.apply.group-member-invite')),
                  groupMemberStatusesUrl: @js($groupMemberStatusesUrl ?? route('site.borrower.apply.group-member-statuses')),
                  previousGroupMembersUrl: @js($previousGroupMembersUrl ?? route('site.borrower.apply.previous-group-members')),
                  selectPreviousGroupMemberUrl: @js($selectPreviousGroupMemberUrl ?? route('site.borrower.apply.previous-group-member')),
                  groupLimits: @js($groupMemberLimits ?? ['min' => 5, 'max' => 30]),
                  leaderCustomerId: {{ (int) ($leaderCustomerId ?? $customer->id) }},
                  leaderName: @js($leaderName ?? $customer->full_name),
                  leaderPhone: @js($leaderPhone ?? $customer->phone),
                  guarantorInviteUrl: @js(route('site.borrower.apply.guarantor-invite')),
                  previousGuarantorsUrl: @js(route('site.borrower.apply.previous-guarantors')),
                  selectPreviousGuarantorUrl: @js(route('site.borrower.apply.previous-guarantor')),
                  guarantorStatusUrl: @js(route('site.borrower.apply.guarantor-status')),
                  guarantorExpireUrl: @js(route('site.borrower.apply.guarantor-expire')),
                  repaymentPreviewUrl: @js(route('site.borrower.apply.repayment-preview')),
                  borrowerSnapshot: @js($borrowerSnapshot),
                  incomeRangeLabels: @js($incomeRangeLabels),
                  activityTypeLabels: @js($activityLabels),
                  tanzaniaLocations: @js(config('tanzania_locations')),
                  draftSaveUrl: @js(route('site.borrower.apply.draft.save')),
                  applicationFeePayUrl: @js(route('site.borrower.apply.application-fee.pay')),
                  applicationFeeQuoteUrl: @js(route('site.borrower.apply.application-fee.quote')),
                  valuationFeePayUrl: @js(route('site.borrower.apply.valuation-fee.pay')),
                  valuationFeeQuoteUrl: @js(route('site.borrower.apply.valuation-fee.quote')),
                  assetDocumentUploadUrl: @js(route('site.borrower.apply.asset-document')),
                  assetTypeOptions: @js($assetTypeOptions ?? []),
                  assetDocumentLabels: @js($assetDocumentLabels ?? []),
                  customerAssets: @js(($customerAssets ?? collect())->map(fn ($a) => [
                      'id' => $a->id,
                      'asset_type' => $a->asset_type,
                      'label' => $a->label,
                      'description' => $a->description,
                      'registration_number' => $a->registration_number,
                      'estimated_value' => $a->estimated_value,
                  ])->values()->all()),
                  valuationFeeAmount: {{ (int) ($valuationFeeAmount ?? 0) }},
                  paymentGatewayDummy: @js($paymentGatewayDummy ?? payment_gateway_is_dummy()),
                  savedDraft: @js($savedDraft ?? null),
                  isResume: @js($isResume ?? false),
                  loansUrl: @js(route('site.borrower.loans')),
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
                  verifiedLegalName: @js($verifiedLegalName),
                  identityVerified: @js($identityVerified),
                  engagementBoosts: @js($engagementBoosts ?? null),
                  qualificationLimit: {{ (int) ($qualificationLimit ?? 0) }},
                  processingSla: @js($processingSla ?? null),
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
                          'asset_details' => __('borrower.apply.steps.asset_details'),
                          'valuation_fee' => __('borrower.apply.steps.valuation_fee'),
                          'group_setup' => __('borrower.apply.steps.group_setup'),
                          'group_members' => __('borrower.apply.steps.group_members'),
                      ],
                      'groupProgress' => [
                          'added' => __('borrower.apply.group.progress.added'),
                          'profiles' => __('borrower.apply.group.progress.profiles'),
                          'verified' => __('borrower.apply.group.progress.verified'),
                          'invitations_pending' => __('borrower.apply.group.progress.invitations_pending'),
                      ],
                      'groupScoringRiskBand' => [
                          'low' => __('borrower.apply.group.scoring.risk_band.low'),
                          'medium' => __('borrower.apply.group.scoring.risk_band.medium'),
                          'high' => __('borrower.apply.group.scoring.risk_band.high'),
                      ],
                      'alerts' => [
                          'loadProduct' => __('borrower.apply.alerts.load_product'),
                          'selectPurpose' => __('borrower.apply.alerts.select_purpose'),
                          'selectGuarantor' => __('borrower.apply.alerts.select_guarantor'),
                          'guarantor_membership' => __('borrower.apply.alerts.guarantor_membership'),
                          'guarantor_phone' => __('borrower.apply.alerts.guarantor_phone'),
                          'guarantor_name' => __('borrower.apply.alerts.guarantor_name_required'),
                          'guarantor_validate_first' => __('borrower.apply.alerts.guarantor_validate_first'),
                          'guarantor_lookup_failed' => __('borrower.apply.alerts.guarantor_lookup_failed'),
                          'guarantor_external_incomplete' => __('borrower.apply.alerts.guarantor_external_incomplete'),
                          'guarantor_invite_failed' => __('borrower.apply.alerts.guarantor_invite_failed'),
                          'guarantor_external_invite_required' => __('borrower.apply.alerts.guarantor_external_invite_required'),
                          'guarantorStatus' => [
                              'invitation_sent' => __('borrower.apply.guarantor_status.invitation_sent'),
                              'registration_in_progress' => __('borrower.apply.guarantor_status.registration_in_progress'),
                              'kyc_in_progress' => __('borrower.apply.guarantor_status.kyc_in_progress'),
                              'guarantee_pending' => __('borrower.apply.guarantor_status.guarantee_pending'),
                              'accepted' => __('borrower.apply.guarantor_status.accepted'),
                              'rejected' => __('borrower.apply.guarantor_status.rejected'),
                              'expired' => __('borrower.apply.guarantor_status.expired'),
                              'internal_validated' => __('borrower.apply.guarantor_status.pending_acceptance'),
                          ],
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
            <div x-show="phase === 'browse'" class="glass-card p-6 sm:p-8">
                <h2 class="text-xl font-semibold mb-1">{{ __('borrower.apply.browse.title') }}</h2>
                <p class="text-sm text-gray-600 mb-5">{{ __('borrower.apply.browse.subtitle') }}</p>

                <div class="flex gap-3 overflow-x-auto pb-2 snap-x snap-mandatory -mx-1 px-1">
                    @foreach ($products as $p)
                        @if (is_marketplace_loan_product($p->code))
                            <div class="snap-start shrink-0 w-64 rounded-xl border-2 border-sky-200 bg-sky-50 p-4 flex flex-col">
                                <span class="text-[10px] font-mono font-semibold text-sky-700 bg-sky-100 px-1.5 py-0.5 rounded">{{ $p->code }}</span>
                                <div class="mt-2 font-semibold text-sm">{{ $p->name }}</div>
                                <p class="text-[11px] text-gray-600 mt-2 flex-1">{{ __('borrower.marketplace.subtitle') }}</p>
                                <a href="{{ route('site.borrower.marketplace') }}" class="mt-3 text-xs font-semibold text-brand">{{ __('borrower.nav.marketplace') }} →</a>
                            </div>
                        @else
                            <button type="button" @click="openProduct(@js($wizardProductPayload($p)))"
                                    class="snap-start shrink-0 w-64 text-left rounded-xl border-2 border-gray-200/80 hover:border-brand/40 hover:bg-brand-muted/20 p-4 transition">
                                <span class="text-[10px] font-mono font-semibold text-brand bg-brand-muted px-1.5 py-0.5 rounded">{{ $p->code }}</span>
                                <div class="mt-2 font-bold text-sm text-gray-900">{{ $p->localizedName() }}</div>
                                <p class="text-[10px] font-semibold text-brand mt-1">{{ loan_product_type_label($p) }}</p>
                                <p class="text-[11px] text-gray-500 mt-1 line-clamp-2">{{ $p->description ?: __('borrower.apply.browse.flexible_terms') }}</p>
                                <div class="text-[11px] text-gray-600 mt-2">
                                    {{ format_money($p->min_amount, false) }} – {{ format_money($p->max_amount, false) }}
                                    · {{ $p->tenure_min_months }}–{{ $p->tenure_max_months }} {{ __('borrower.apply.browse.months_short') }}
                                </div>
                                <p class="text-[11px] font-semibold text-gray-800 mt-2">
                                    {{ __('borrower.apply.product_summary.application_fee') }}: {{ format_money(loan_product_application_fee($customer, $p)) }}
                                </p>
                                <p class="mt-3 text-xs font-semibold text-brand">{{ __('borrower.apply.browse.view_details') }}</p>
                            </button>
                        @endif
                    @endforeach
                </div>
                <div class="mt-6 text-center">
                    <a href="{{ route('site.borrower.dashboard') }}" class="text-sm font-semibold text-gray-600 hover:text-gray-900">{{ __('borrower.apply.back_dashboard') }}</a>
                </div>
            </div>

            {{-- Phase 2: Product details + readiness --}}
            <div x-show="phase === 'details'" class="glass-card p-6 sm:p-8">
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

                <form id="apply-wizard-form"
                      data-apply-wizard-form
                      x-ref="wizardForm"
                      method="POST"
                      action="{{ route('site.borrower.apply.submit') }}"
                      enctype="multipart/form-data"
                      novalidate
                      @submit.prevent="onSubmit($event)"
                      @sync-before-submit.window="if ($event.target === $el) syncSubmitPayload($el)">
                    @csrf
                    {{-- Authoritative POST fields — synced from Alpine before submit --}}
                    <input type="hidden" name="loan_product_id" data-submit-product>
                    <input type="hidden" name="requested_amount" data-submit-amount>
                    <input type="hidden" name="requested_tenure_months" data-submit-tenure>
                    <input type="hidden" name="purpose" data-submit-purpose>
                    <input type="hidden" name="guarantor_mode" data-submit-guarantor-mode>
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
            <div class="sticky top-0 z-20 -mx-4 px-4 py-3 mb-6 bg-[#faf8f5]/95 backdrop-blur-md border-b border-gray-200/70 lg:static lg:mx-0 lg:px-0 lg:py-0 lg:mb-6 lg:border-0 lg:bg-transparent">
                <div class="hidden lg:block h-2 bg-gray-100 rounded-full overflow-hidden mb-4">
                    <div class="h-full bg-brand transition-all duration-300 rounded-full"
                         :style="'width:' + (steps.length ? Math.round(((step + 1) / steps.length) * 100) : 0) + '%'"></div>
                </div>
                <div class="lg:hidden h-1.5 bg-gray-200 rounded-full overflow-hidden mb-3">
                    <div class="h-full bg-brand transition-all duration-300 rounded-full"
                         :style="'width:' + (steps.length ? Math.round(((step + 1) / steps.length) * 100) : 0) + '%'"></div>
                </div>
                <p class="lg:hidden text-[11px] font-semibold text-gray-500 mb-2">
                    <span x-text="(step + 1) + ' / ' + steps.length"></span>
                    · <span x-text="steps[step]?.label || ''"></span>
                </p>
            <ol class="flex items-center gap-1 overflow-x-auto pb-1 snap-x snap-mandatory scrollbar-none lg:glass-card lg:p-4 lg:ring-1 lg:ring-brand/10">
                <template x-for="(s, i) in steps" :key="s.key">
                    <li class="flex items-center gap-1 shrink-0 snap-start">
                        <button type="button" @click="goto(i)"
                                :class="i === step ? 'bg-brand text-white border-brand'
                                                   : (i < step ? 'bg-emerald-50 text-emerald-700 border-emerald-200'
                                                               : 'bg-white text-gray-500 border-gray-300')"
                                class="size-9 rounded-full grid place-items-center text-sm font-bold border-2 transition"
                                :title="(i + 1) + '. ' + s.label">
                            <template x-if="i < step"><svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 20 20" stroke="currentColor" stroke-width="3"><path d="M5 10l3 3 7-7"/></svg></template>
                            <template x-if="i >= step"><span x-text="s.icon || (i + 1)"></span></template>
                        </button>
                        <span class="text-[10px] sm:text-[11px] font-medium text-gray-600 mr-1 sm:mr-2 max-w-[5.5rem] sm:max-w-none truncate" :title="s.label">
                            <span class="text-gray-400" x-text="(i + 1) + '.'"></span>
                            <span x-text="s.label"></span>
                        </span>
                        <span x-show="i < steps.length - 1" class="text-gray-300">→</span>
                    </li>
                </template>
            </ol>
            </div>

            <div class="glass-card">

                {{-- Quote --}}
                <div x-show="stepKey === 'quote'" class="p-6 sm:p-8">
                    <p class="text-xs uppercase tracking-widest text-brand font-semibold mb-1">{{ __('borrower.apply.quote.eyebrow') }}</p>
                    <h2 class="text-xl font-semibold mb-1">{{ __('borrower.apply.quote.title') }}</h2>
                    <p class="text-sm text-gray-600 mb-5">{{ __('borrower.apply.quote.subtitle') }}</p>
                    <template x-if="current">
                        <div class="space-y-5">
                            <div class="rounded-xl ring-1 ring-brand/15 bg-gradient-to-br from-brand-muted/40 to-white p-5">
                                <div class="flex justify-between text-sm mb-2"><span class="text-gray-600">{{ __('borrower.apply.quote.loan_amount') }}</span><span class="font-bold" x-text="formatTzs(form.requested_amount)"></span></div>
                                <input type="range" :min="current.min" :max="current.max" step="50000" x-model.number="form.requested_amount" @input="updateQuote()" class="w-full accent-brand">
                                <div class="flex justify-between text-sm mb-2 mt-4"><span class="text-gray-600">{{ __('borrower.apply.quote.tenure') }}</span><span class="font-bold"><span x-text="form.requested_tenure_months"></span> {{ __('borrower.apply.quote.months') }}</span></div>
                                <input type="range" :min="current.tmin" :max="current.tmax" step="1" x-model.number="form.requested_tenure_months" @input="updateQuote()" class="w-full accent-brand">
                                <div class="flex justify-between text-sm mt-4"><span class="text-gray-600">{{ __('borrower.apply.quote.repayment_frequency') }}</span><span class="font-medium capitalize" x-text="current.frequency || 'monthly'"></span></div>
                            </div>
                            <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-3">
                                <div class="rounded-xl ring-1 ring-brand/20 bg-brand-muted/30 p-4 sm:col-span-2">
                                    <p class="text-[10px] uppercase text-brand font-semibold" x-text="repaymentCadence() === 'monthly' ? @js(__('borrower.apply.quote.monthly_installment')) : @js(__('borrower.apply.quote.weekly_installment'))"></p>
                                    <p class="text-2xl font-bold mt-1 text-gray-900" x-text="formatTzs(quote.primary ?? quote.monthly)"></p>
                                </div>
                                <div class="rounded-xl ring-1 ring-brand/15 bg-gradient-to-br from-brand-muted/30 to-white p-4"><p class="text-[10px] uppercase text-gray-500">{{ __('borrower.apply.quote.interest_est') }}</p><p class="font-bold mt-1 text-gray-900" x-text="formatTzs(quote.interest)"></p></div>
                                <div class="rounded-xl ring-1 ring-brand/15 bg-gradient-to-br from-brand-muted/30 to-white p-4"><p class="text-[10px] uppercase text-gray-500">{{ __('borrower.apply.quote.total_repayment') }}</p><p class="font-bold mt-1 text-gray-900" x-text="formatTzs(quote.total)"></p></div>
                            </div>
                            <div x-show="engagementBoosts && (engagementBoosts.factors?.length || qualificationLimit > 0)" x-cloak class="rounded-xl bg-emerald-50 ring-1 ring-emerald-200 p-4 space-y-2">
                                <p class="text-[10px] uppercase tracking-widest text-emerald-800 font-semibold">{{ __('borrower.apply.quote.engagement_title') }}</p>
                                <template x-if="qualificationLimit > 0">
                                    <p class="text-sm text-emerald-900">
                                        {{ __('borrower.apply.quote.engagement_limit') }}:
                                        <span class="font-semibold" x-text="formatTzs(qualificationLimit)"></span>
                                    </p>
                                </template>
                                <template x-if="engagementBoosts?.rate_discount_fraction > 0">
                                    <p class="text-sm text-emerald-900">
                                        {{ __('borrower.apply.quote.engagement_rate') }}:
                                        <span class="font-semibold" x-text="(engagementBoosts.rate_discount_fraction * 100).toFixed(2) + '%'"></span>
                                    </p>
                                </template>
                                <template x-if="processingSla">
                                    <p class="text-sm text-emerald-900">
                                        {{ __('borrower.apply.quote.engagement_sla') }}:
                                        <span class="font-semibold" x-text="processingSla"></span>
                                    </p>
                                </template>
                                <ul class="text-xs text-emerald-800 space-y-1" x-show="engagementBoosts?.factors?.length">
                                    <template x-for="(factor, idx) in (engagementBoosts?.factors || [])" :key="idx">
                                        <li x-text="factor.label + ': ' + factor.detail"></li>
                                    </template>
                                </ul>
                            </div>
                            <div x-show="current?.rate_disclosure?.length" class="rounded-xl bg-amber-50 ring-1 ring-amber-200 p-4 text-xs text-amber-900 space-y-1">
                                <p class="font-semibold uppercase tracking-widest text-[10px]">{{ __('borrower.rate_disclosure.title') }}</p>
                                <template x-for="(line, idx) in (current.rate_disclosure || [])" :key="idx">
                                    <p x-text="line"></p>
                                </template>
                                <p class="text-amber-800/80 pt-1">{{ __('borrower.rate_disclosure.footnote') }}</p>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.apply.quote.purpose') }}</label>
                                <select x-model="form.purpose" required class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm">
                                    <option value="">{{ __('borrower.apply.quote.select_purpose') }}</option>
                                    @foreach ($loanPurposes as $key => $label)
                                        <option value="{{ $key }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </template>
                </div>

                @include('site.apply._group-steps')

                {{-- Asset lending tenure --}}
                <div x-show="stepKey === 'asset_tenure'" class="p-6 sm:p-8">
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
                                <input type="range" min="1" :max="assetApplication.max_tenure_months" step="1" x-model.number="form.requested_tenure_months" class="w-full accent-brand">
                                <p class="text-xs text-gray-500 mt-2">{{ __('borrower.apply.asset_tenure.max_hint', ['months' => '']) }} <span x-text="assetApplication.max_tenure_months"></span> {{ __('borrower.apply.quote.months') }}</p>
                            </div>
                        </div>
                    </template>
                </div>

                {{-- Asset-backed collateral — profile assets only --}}
                <div x-show="stepKey === 'asset_details'" class="p-6 sm:p-8">
                    <h2 class="text-xl font-semibold mb-1">{{ __('borrower.apply.asset_details.title') }}</h2>
                    <p class="text-sm text-gray-600 mb-5">{{ __('borrower.apply.asset_details.subtitle') }}</p>
                    <template x-if="current">
                        <div class="space-y-5">
                            <div x-show="!customerAssets.length" class="rounded-xl bg-amber-50 ring-1 ring-amber-200 p-5">
                                <p class="text-sm font-semibold text-amber-900">{{ __('borrower.apply.asset_details.no_assets_title') }}</p>
                                <p class="text-sm text-amber-800 mt-2">{{ __('borrower.apply.asset_details.no_assets_body') }}</p>
                                <a href="{{ route('site.borrower.profile', ['section' => 'assets']) }}" class="inline-flex mt-4 text-sm font-semibold text-amber-900 underline">
                                    {{ __('borrower.apply.asset_details.add_asset_link') }} →
                                </a>
                            </div>
                            <div x-show="customerAssets.length" class="space-y-4">
                                <div class="rounded-xl bg-sky-50 ring-1 ring-sky-200 p-4">
                                    <label class="block text-sm font-semibold text-sky-900 mb-2">{{ __('borrower.apply.asset_details.choose_existing') }} <span class="text-rose-500">*</span></label>
                                    <select x-model="form.customer_asset_id" @change="applyExistingAsset()" required class="w-full rounded-lg border-gray-300 text-sm">
                                        <option value="">{{ __('borrower.profile.select') }}</option>
                                        <template x-for="asset in customerAssets" :key="asset.id">
                                            <option :value="asset.id" x-text="asset.label + (asset.registration_number ? ' · ' + asset.registration_number : '')"></option>
                                        </template>
                                    </select>
                                </div>
                                <div x-show="selectedCustomerAsset()" class="rounded-xl bg-gray-50 ring-1 ring-gray-200 p-4 text-sm space-y-2">
                                    <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">{{ __('borrower.apply.asset_details.selected_asset') }}</p>
                                    <div class="flex justify-between gap-3"><span class="text-gray-500">{{ __('borrower.apply.asset_details.asset_type') }}</span><span class="font-semibold" x-text="assetTypeOptions[form.asset_type] || form.asset_type || '—'"></span></div>
                                    <div class="flex justify-between gap-3" x-show="selectedCustomerAsset()?.registration_number"><span class="text-gray-500">Registration</span><span class="font-semibold" x-text="selectedCustomerAsset()?.registration_number"></span></div>
                                    <div x-show="selectedCustomerAsset()?.description"><span class="text-gray-500 block text-xs">Description</span><span x-text="selectedCustomerAsset()?.description"></span></div>
                                </div>
                            </div>
                            <div x-show="customerAssets.length && form.customer_asset_id" class="bg-gray-50 rounded-xl p-5">
                                <div class="flex justify-between text-sm mb-2"><span class="text-gray-600">{{ __('borrower.apply.quote.loan_amount') }}</span><span class="font-bold" x-text="formatTzs(form.requested_amount)"></span></div>
                                <input type="range" :min="current.min" :max="current.max" step="50000" x-model.number="form.requested_amount" @input="updateQuote()" class="w-full accent-brand">
                                <div class="flex justify-between text-sm mb-2 mt-4"><span class="text-gray-600">{{ __('borrower.apply.quote.tenure') }}</span><span class="font-bold"><span x-text="form.requested_tenure_months"></span> {{ __('borrower.apply.quote.months') }}</span></div>
                                <input type="range" :min="current.tmin" :max="current.tmax" step="1" x-model.number="form.requested_tenure_months" @input="updateQuote()" class="w-full accent-brand">
                                <p class="text-xs text-brand mt-3">{{ __('borrower.apply.asset_details.ltv_note') }}</p>
                            </div>
                            <div x-show="customerAssets.length && form.customer_asset_id">
                                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.apply.quote.purpose') }}</label>
                                <select x-model="form.purpose" class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm">
                                    <option value="">{{ __('borrower.apply.quote.select_purpose') }}</option>
                                    @foreach ($loanPurposes as $key => $label)
                                        <option value="{{ $key }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </template>
                </div>

                {{-- Guarantor --}}
                <div x-show="stepKey === 'guarantor'" class="p-6 sm:p-8">
                    <h2 class="text-xl font-semibold mb-1">{{ __('borrower.apply.guarantor') }}</h2>
                    <p class="text-sm text-gray-600 mb-4">{{ __('borrower.apply.guarantor_required') }}</p>
                    <div x-show="Object.keys(guarantorErrors).length" x-cloak class="mb-4 rounded-xl bg-rose-50 ring-1 ring-rose-200 px-4 py-3 text-sm text-rose-800">
                        <p class="font-semibold mb-1">{{ __('borrower.apply.guarantor_fields.missing_fields_title') }}</p>
                        <ul class="list-disc list-inside space-y-0.5">
                            <template x-for="(msg, key) in guarantorErrors" :key="key">
                                <li x-text="msg"></li>
                            </template>
                        </ul>
                    </div>
                    <div x-show="isGuarantorLocked()" x-cloak class="rounded-xl px-4 py-4 space-y-4 mb-4 ring-1"
                         :class="guarantorLockedCardClass()">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <p class="text-sm font-semibold" :class="guarantorLockedCardTextClass()" x-text="guarantorLockedSummaryText()"></p>
                            <div class="text-right">
                                <p class="text-[10px] uppercase tracking-widest" :class="guarantorLockedCardMutedClass()">{{ __('borrower.apply.guarantor_locked_status') }}</p>
                                <span class="inline-flex mt-1 rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1"
                                      :class="guarantorStatusBadgeClass()"
                                      x-text="guarantorStatusLabel()"></span>
                            </div>
                        </div>
                        <p class="text-sm" :class="guarantorLockedCardBodyClass()">
                            <span class="font-medium" x-text="form.guarantor_mode === 'internal' ? @js(__('borrower.apply.internal_guarantor')) : @js(__('borrower.apply.external_guarantor'))"></span>
                            · <span x-text="guarantorSummaryText()"></span>
                        </p>
                        <div x-show="form.guarantor_mode === 'external' && externalGuarantor?.invitation_url" x-cloak x-data="{ copied: false }" class="rounded-xl bg-white/80 ring-1 ring-emerald-200 px-4 py-4 space-y-3">
                            <p class="text-sm font-semibold text-emerald-900">{{ __('borrower.apply.guarantor_fields.share_via') }}</p>
                            <p class="text-xs text-emerald-800">{{ __('borrower.apply.guarantor_fields.share_ready') }}</p>
                            <p class="text-xs font-mono text-emerald-900 bg-emerald-100/80 rounded-lg px-3 py-2 break-all" x-text="externalGuarantor.short_url || externalGuarantor.invitation_url"></p>
                            <div class="flex flex-wrap gap-2">
                                <a :href="externalGuarantor.whatsapp_url || '#'" :class="!externalGuarantor.whatsapp_url && 'pointer-events-none opacity-50'" target="_blank" rel="noopener"
                                   class="inline-flex items-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold px-4 py-2 rounded-full text-sm">
                                    {{ __('borrower.apply.guarantor_fields.share_whatsapp') }}
                                </a>
                                <a :href="externalGuarantor.sms_url || '#'" :class="!externalGuarantor.sms_url && 'pointer-events-none opacity-50'"
                                   class="inline-flex items-center gap-2 bg-white ring-1 ring-emerald-300 text-emerald-900 font-semibold px-4 py-2 rounded-full text-sm">
                                    {{ __('borrower.apply.guarantor_fields.share_sms') }}
                                </a>
                                <a :href="externalGuarantor.email_url || '#'" :class="!externalGuarantor.email_url && 'pointer-events-none opacity-50'"
                                   class="inline-flex items-center gap-2 bg-white ring-1 ring-emerald-300 text-emerald-900 font-semibold px-4 py-2 rounded-full text-sm">
                                    {{ __('borrower.apply.guarantor_fields.share_email') }}
                                </a>
                                <button type="button"
                                        @click="navigator.clipboard.writeText(externalGuarantor.short_url || externalGuarantor.invitation_url); copied = true; setTimeout(() => copied = false, 2000)"
                                        class="inline-flex items-center gap-2 bg-white ring-1 ring-emerald-300 text-emerald-900 font-semibold px-4 py-2 rounded-full text-sm">
                                    <span x-text="copied ? @js(__('borrower.apply.guarantor_fields.link_copied')) : @js(__('borrower.apply.guarantor_fields.share_copy'))"></span>
                                </button>
                            </div>
                            <p class="text-xs text-amber-800">{{ __('borrower.apply.guarantor_fields.share_ready_continue') }}</p>
                        </div>
                        <p x-show="form.guarantor_mode === 'internal'" class="text-xs text-emerald-800">
                            {{ __('borrower.apply.guarantor_fields.membership_hint_short') }}
                        </p>
                        <button type="button"
                                @click="changeGuarantor()"
                                :disabled="guarantorChanging"
                                class="inline-flex bg-white ring-1 ring-emerald-300 text-emerald-900 font-semibold px-4 py-2 rounded-full text-sm disabled:opacity-60">
                            {{ __('borrower.apply.change_guarantor') }}
                        </button>
                    </div>
                    <div class="space-y-4" x-show="!isGuarantorLocked()">
                        <div x-show="previousGuarantors.length" x-cloak class="rounded-xl bg-sky-50 ring-1 ring-sky-200 px-4 py-4 space-y-3">
                            <p class="text-sm font-semibold text-sky-900">{{ __('borrower.apply.previous_guarantor.title') }}</p>
                            <div class="flex flex-wrap gap-3">
                                <label class="inline-flex items-center gap-2 text-sm">
                                    <input type="radio" name="guarantor_choice" value="previous" @change="form.guarantor_mode = 'previous'" :checked="form.guarantor_mode === 'previous'" class="text-brand">
                                    {{ __('borrower.apply.previous_guarantor.use_previous') }}
                                </label>
                                <label class="inline-flex items-center gap-2 text-sm">
                                    <input type="radio" name="guarantor_choice" value="new" @change="form.guarantor_mode = 'internal'" :checked="form.guarantor_mode !== 'previous'" class="text-brand">
                                    {{ __('borrower.apply.previous_guarantor.select_new') }}
                                </label>
                            </div>
                            <div x-show="form.guarantor_mode === 'previous'" class="space-y-2">
                                <template x-for="item in previousGuarantors" :key="item.id">
                                    <button type="button"
                                            @click="selectPreviousGuarantor(item.id)"
                                            class="w-full text-left rounded-lg bg-white ring-1 ring-sky-200 px-3 py-2 text-sm hover:bg-sky-100/60">
                                        <span class="font-semibold text-gray-900" x-text="item.label"></span>
                                        <span class="block text-xs text-gray-500 mt-0.5" x-text="item.kyc_fresh ? @js(__('borrower.apply.previous_guarantor.kyc_fresh')) : @js(__('borrower.apply.previous_guarantor.new_request'))"></span>
                                    </button>
                                </template>
                            </div>
                        </div>
                        <div class="flex flex-wrap gap-3" x-show="form.guarantor_mode !== 'previous'">
                            <label class="inline-flex items-center gap-2 text-sm"><input type="radio" name="guarantor_mode" value="internal" x-model="form.guarantor_mode" class="text-brand"> {{ __('borrower.apply.internal_guarantor') }}</label>
                            <label class="inline-flex items-center gap-2 text-sm"><input type="radio" name="guarantor_mode" value="external" x-model="form.guarantor_mode" class="text-brand"> {{ __('borrower.apply.external_guarantor') }}</label>
                        </div>
                        <div x-show="form.guarantor_mode === 'internal'" class="space-y-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.apply.guarantor_fields.membership_no') }}</label>
                                <div class="flex rounded-lg ring-1 overflow-hidden" :class="guarantorErrors.internal_member_no ? 'ring-rose-400' : 'ring-gray-200'">
                                    <span class="inline-flex items-center px-3 bg-gray-100 text-sm font-mono text-gray-600 border-r border-gray-200">KPF-TZ-</span>
                                    <input name="internal_member_no" x-model="form.internal_member_no" @input="delete guarantorErrors.internal_member_no; guarantorLookup.ok = false" placeholder="ABC12345" class="flex-1 border-0 px-3 py-2.5 text-sm font-mono focus:ring-0">
                                </div>
                                <p x-show="guarantorErrors.internal_member_no" class="mt-1 text-xs text-rose-600" x-text="guarantorErrors.internal_member_no"></p>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.profile.fields.phone') }}</label>
                                <div class="flex rounded-lg ring-1 overflow-hidden" :class="guarantorErrors.internal_guarantor_phone ? 'ring-rose-400' : 'ring-gray-200'">
                                    <span class="inline-flex items-center px-3 bg-gray-100 text-sm text-gray-600 border-r border-gray-200">+255</span>
                                    <input name="internal_guarantor_phone" x-model="form.internal_guarantor_phone" @input="delete guarantorErrors.internal_guarantor_phone; guarantorLookup.ok = false" inputmode="numeric" placeholder="712345678" class="flex-1 border-0 px-3 py-2.5 text-sm focus:ring-0">
                                </div>
                                <p x-show="guarantorErrors.internal_guarantor_phone" class="mt-1 text-xs text-rose-600" x-text="guarantorErrors.internal_guarantor_phone"></p>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.apply.guarantor_fields.guarantor_name') }}</label>
                                <input name="internal_guarantor_name" x-model="form.internal_guarantor_name" @input="delete guarantorErrors.internal_guarantor_name; guarantorLookup.ok = false"
                                       :class="guarantorErrors.internal_guarantor_name ? 'ring-rose-400' : 'ring-gray-200'"
                                       class="w-full rounded-lg border-gray-300 ring-1 px-3 py-2.5 text-sm" placeholder="{{ __('borrower.profile.fields.full_name') }}">
                                <p x-show="guarantorErrors.internal_guarantor_name" class="mt-1 text-xs text-rose-600" x-text="guarantorErrors.internal_guarantor_name"></p>
                                <p class="mt-1 text-xs text-gray-500">{{ __('borrower.apply.guarantor_fields.guarantor_name_hint') }}</p>
                            </div>
                            <div x-show="guarantorLookup.ok" x-cloak class="rounded-xl bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-900">
                                <p class="font-semibold">{{ __('borrower.apply.alerts.guarantor_verified') }}</p>
                                <p class="mt-1" x-text="guarantorLookup.label"></p>
                            </div>
                            <p x-show="guarantorLookup.error" x-cloak class="text-sm text-red-700" x-text="guarantorLookup.error"></p>
                            <p class="text-xs text-gray-500">{{ __('borrower.apply.guarantor_fields.membership_hint_short') }}</p>
                            <button type="button"
                                    @click="validateInternalGuarantor()"
                                    :disabled="guarantorValidating"
                                    class="inline-flex bg-brand hover:bg-brand-light disabled:opacity-60 text-white font-semibold px-5 py-2.5 rounded-xl text-sm">
                                <span x-text="guarantorValidating ? @js(__('borrower.apply.guarantor_fields.validating')) : @js(__('borrower.apply.guarantor_fields.validate'))"></span>
                            </button>
                        </div>
                        <input type="hidden" name="external_invitation_id" :value="externalGuarantor?.invitation_id || ''">
                        <div x-show="form.guarantor_mode === 'external'" class="grid sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.profile.fields.first_name') }} <span class="text-rose-500">*</span></label>
                                <input name="external_first_name" x-model="form.external_first_name" @input="delete guarantorErrors.external_first_name; invalidateExternalInvite()"
                                       :class="guarantorErrors.external_first_name ? 'ring-rose-400' : 'ring-gray-200'"
                                       class="w-full rounded-lg border-gray-300 ring-1 px-3 py-2.5 text-sm">
                                <p x-show="guarantorErrors.external_first_name" class="mt-1 text-xs text-rose-600" x-text="guarantorErrors.external_first_name"></p>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.profile.fields.middle_name') }}</label>
                                <input name="external_middle_name" x-model="form.external_middle_name" class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.profile.fields.last_name') }} <span class="text-rose-500">*</span></label>
                                <input name="external_last_name" x-model="form.external_last_name" @input="delete guarantorErrors.external_last_name; invalidateExternalInvite()"
                                       :class="guarantorErrors.external_last_name ? 'ring-rose-400' : 'ring-gray-200'"
                                       class="w-full rounded-lg border-gray-300 ring-1 px-3 py-2.5 text-sm">
                                <p x-show="guarantorErrors.external_last_name" class="mt-1 text-xs text-rose-600" x-text="guarantorErrors.external_last_name"></p>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.apply.guarantor_fields.relationship') }} <span class="text-rose-500">*</span></label>
                                <select name="external_relationship" x-model="form.external_relationship" @change="delete guarantorErrors.external_relationship; invalidateExternalInvite()"
                                        :class="guarantorErrors.external_relationship ? 'ring-rose-400' : 'ring-gray-200'"
                                        class="w-full rounded-lg border-gray-300 ring-1 px-3 py-2.5 text-sm">
                                    <option value="">{{ __('borrower.profile.select') }}</option>
                                    @foreach (trans('borrower.profile.guarantor_relationship_options') as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                                <p x-show="guarantorErrors.external_relationship" class="mt-1 text-xs text-rose-600" x-text="guarantorErrors.external_relationship"></p>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.profile.fields.phone') }} <span class="text-rose-500">*</span></label>
                                <div class="flex rounded-lg ring-1 overflow-hidden" :class="guarantorErrors.external_phone ? 'ring-rose-400' : 'ring-gray-200'">
                                    <span class="inline-flex items-center px-3 bg-gray-100 text-sm text-gray-600 border-r border-gray-200">+255</span>
                                    <input name="external_phone" x-model="form.external_phone" @input="delete guarantorErrors.external_phone; invalidateExternalInvite()" inputmode="numeric" placeholder="712345678" class="flex-1 border-0 px-3 py-2.5 text-sm focus:ring-0">
                                </div>
                                <p x-show="guarantorErrors.external_phone" class="mt-1 text-xs text-rose-600" x-text="guarantorErrors.external_phone"></p>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.profile.fields.email') }} {{ __('borrower.profile.optional') }}</label>
                                <input name="external_email" x-model="form.external_email" type="email" class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm">
                            </div>
                            <div class="sm:col-span-2 grid sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.profile.fields.region') }} <span class="text-rose-500">*</span></label>
                                    <select name="external_region" x-model="form.external_region" @change="onExternalRegionChange(); delete guarantorErrors.external_region; invalidateExternalInvite()"
                                            :class="guarantorErrors.external_region ? 'ring-rose-400' : 'ring-gray-200'"
                                            class="w-full rounded-lg border-gray-300 ring-1 px-3 py-2.5 text-sm">
                                        <option value="">{{ __('borrower.profile.select_region') }}</option>
                                        @foreach (config('tanzania_locations') as $regionName => $districts)
                                            <option value="{{ $regionName }}">{{ $regionName }}</option>
                                        @endforeach
                                    </select>
                                    <p x-show="guarantorErrors.external_region" class="mt-1 text-xs text-rose-600" x-text="guarantorErrors.external_region"></p>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.profile.fields.district') }} <span class="text-rose-500">*</span></label>
                                    <select name="external_district" x-model="form.external_district" @change="delete guarantorErrors.external_district; invalidateExternalInvite()"
                                            :class="guarantorErrors.external_district ? 'ring-rose-400' : 'ring-gray-200'"
                                            class="w-full rounded-lg border-gray-300 ring-1 px-3 py-2.5 text-sm">
                                        <option value="">{{ __('borrower.profile.select_district') }}</option>
                                        <template x-for="d in districtsForRegion()" :key="d">
                                            <option :value="d" x-text="d"></option>
                                        </template>
                                    </select>
                                    <p x-show="guarantorErrors.external_district" class="mt-1 text-xs text-rose-600" x-text="guarantorErrors.external_district"></p>
                                </div>
                            </div>
                            <div class="sm:col-span-2" x-show="isExternalGuarantorComplete() && !externalGuarantor?.invitation_url">
                                <button type="button"
                                        @click="generateExternalInvite()"
                                        :disabled="guarantorInvitePreparing"
                                        class="inline-flex bg-brand hover:bg-brand-light disabled:opacity-60 text-white font-semibold px-5 py-2.5 rounded-xl text-sm">
                                    <span x-text="guarantorInvitePreparing ? @js(__('borrower.apply.guarantor_fields.generating_link')) : @js(__('borrower.apply.guarantor_fields.generate_link'))"></span>
                                </button>
                            </div>
                            <div class="sm:col-span-2 rounded-xl bg-sky-50 ring-1 ring-sky-200 px-4 py-3 text-sm text-sky-900" x-show="!isExternalGuarantorComplete()">
                                {{ __('borrower.apply.guarantor_fields.share_generate') }}
                            </div>
                        </div>
                        <p class="text-xs text-brand font-medium">{{ __('borrower.apply.guarantor_fields.status_waiting') }}</p>
                    </div>
                    <p x-show="isGuarantorLocked()" x-cloak class="text-xs text-brand font-medium mt-4">{{ __('borrower.apply.guarantor_fields.status_waiting') }}</p>
                </div>

                @php $membershipCfg = \App\Services\MembershipService::config(); @endphp
                <x-site.application-fee-step
                    :fee-quote="$feeQuote ?? null"
                    :bank-accounts="$bankAccounts ?? []"
                    :currency="$membershipCfg['currency'] ?? 'TZS'"
                    :payment-reference="$applicationFeePaymentRef ?? null"
                    :referral-wallet="$referralWallet ?? null"
                    :referral-settings="$referralSettings ?? []"
                    :streak-reward="$streakReward ?? null"
                    :payment-gateway-dummy="$paymentGatewayDummy ?? payment_gateway_is_dummy()"
                    :apply-requirements="$applyRequirements ?? null"
                    :points-balance="$pointsBalance ?? 0"
                />

                {{-- Product-specific questions --}}
                <div x-show="stepKey === 'product_questions'" class="p-6 sm:p-8">
                    <h2 class="text-xl font-semibold mb-1">{{ __('borrower.apply.product_questions.title') }}</h2>
                    <p class="text-sm text-gray-600 mb-5">{{ __('borrower.apply.product_questions.subtitle') }}</p>
                    @foreach ($productQuestions as $code => $block)
                        <div x-show="current && current.code === @js($code)" class="rounded-xl border border-gray-200 p-5">
                            <h3 class="text-sm font-semibold text-gray-900 mb-4">{{ $block['title'] ?? __('borrower.apply.product_questions.additional') }}</h3>
                            <div class="grid sm:grid-cols-2 gap-4">
                                @foreach ($block['fields'] as $field)
                                    @if (($field['type'] ?? 'text') === 'tz_address')
                                        <div class="sm:col-span-2">
                                            <label class="block text-xs font-medium text-gray-600 mb-2">{{ $field['label'] }}</label>
                                            <x-site.address-fields
                                                form-key="product_question"
                                                :prefix="$field['prefix'] ?? ''"
                                                :required="$field['required'] ?? true"
                                            />
                                        </div>
                                    @else
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
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- Review --}}
                <div x-show="stepKey === 'review'" class="p-6 sm:p-8">
                    <p class="text-xs uppercase tracking-widest text-brand font-semibold mb-1">{{ __('borrower.apply.review_step.eyebrow') }}</p>
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
                                            @if (! empty($item['detail']))
                                                <span class="block text-xs text-brand">{{ $item['detail'] }}</span>
                                            @endif
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
                    <div class="rounded-xl ring-1 ring-gray-200/80 bg-white divide-y divide-gray-100 mb-5 text-sm shadow-sm">
                        <div class="px-4 py-3 flex justify-between gap-3"><div><span class="text-gray-500 block">{{ __('borrower.apply.review_step.product') }}</span><span class="font-medium" x-text="current ? current.name : '—'"></span></div><button type="button" @click="backToBrowse()" class="text-xs text-brand shrink-0" x-show="! reservationMode">{{ __('borrower.apply.change') }}</button></div>
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
                        <div class="px-4 py-3" x-show="hasStep('quote')"><span class="text-gray-500 block">{{ __('borrower.apply.review_step.purpose') }}</span><span class="font-medium" x-text="purposeLabels[form.purpose] || form.purpose || '—'"></span></div>
                        <div class="px-4 py-3" x-show="hasStep('group_setup')"><span class="text-gray-500 block">{{ __('borrower.apply.group_setup.name') }}</span><span class="font-medium" x-text="group.name || '—'"></span></div>
                        <div class="px-4 py-3" x-show="hasStep('group_setup')"><span class="text-gray-500 block">{{ __('borrower.apply.group_setup.purpose') }}</span><span class="font-medium" x-text="purposeLabels[group.purpose] || group.purpose || '—'"></span></div>
                    </div>

                    <h3 class="text-xs uppercase tracking-widest text-brand font-semibold mb-2">{{ __('borrower.apply.review_step.borrower_section') }}</h3>
                    <div class="rounded-xl ring-1 ring-gray-200/80 bg-white divide-y divide-gray-100 mb-5 text-sm shadow-sm">
                        <div class="px-4 py-3"><span class="text-gray-500 block">{{ __('borrower.apply.review_step.personal') }}</span><span class="font-medium" x-text="review.personal"></span></div>
                        <div class="px-4 py-3"><span class="text-gray-500 block">{{ __('borrower.apply.review_step.employment') }}</span><span class="font-medium" x-text="review.employment"></span></div>
                        <div class="px-4 py-3 flex justify-between gap-3">
                            <div><span class="text-gray-500 block">{{ __('borrower.apply.review_step.residence') }}</span><span class="font-medium" x-text="review.residence"></span></div>
                            <a :href="profileUrl" class="text-xs text-brand shrink-0">{{ __('borrower.apply.edit_profile') }}</a>
                        </div>
                    </div>

                    <h3 class="text-xs uppercase tracking-widest text-brand font-semibold mb-2">{{ __('borrower.apply.review_step.loan_section') }}</h3>
                    <div class="rounded-xl ring-1 ring-gray-200/80 bg-white divide-y divide-gray-100 mb-5 text-sm shadow-sm">
                        <div class="px-4 py-3 flex justify-between gap-3" x-show="hasStep('quote') || hasStep('asset_tenure') || hasStep('asset_details') || hasStep('group_members')">
                            <div><span class="text-gray-500 block">{{ __('borrower.apply.review_step.loan_amount') }}</span><span class="font-medium" x-text="formatTzs(form.requested_amount)"></span></div>
                            <button type="button" @click="gotoKey(hasStep('asset_details') ? 'asset_details' : (hasStep('group_members') ? 'group_members' : (hasStep('quote') ? 'quote' : 'asset_tenure')))" class="text-xs text-brand shrink-0">{{ __('borrower.apply.edit') }}</button>
                        </div>
                        <div class="px-4 py-3" x-show="hasStep('group_members')">
                            <span class="text-gray-500 block">{{ __('borrower.apply.group_members.title') }}</span>
                            <span class="font-medium"><span x-text="group.members.length"></span> members</span>
                        </div>
                        <div class="px-4 py-3" x-show="hasStep('asset_details')">
                            <span class="text-gray-500 block">{{ __('borrower.apply.asset_details.selected_asset') }}</span>
                            <span class="font-medium" x-text="selectedCustomerAsset()?.label || assetTypeOptions[form.asset_type] || form.asset_type || '—'"></span>
                        </div>
                        <div class="px-4 py-3"><span class="text-gray-500 block">{{ __('borrower.apply.review_step.duration') }}</span><span class="font-medium"><span x-text="form.requested_tenure_months"></span> {{ __('borrower.apply.browse.months_short') }}</span></div>
                        <div class="px-4 py-3"><span class="text-gray-500 block">{{ __('borrower.apply.review_step.interest_rate') }}</span><span class="font-medium" x-text="reviewSummary.monthly_rate_pct ? (reviewSummary.monthly_rate_pct + '% / month') : '—'"></span></div>
                        <div class="px-4 py-3"><span class="text-gray-500 block">{{ __('borrower.apply.review_step.application_fee') }}</span><span class="font-medium" x-text="formatTzs(reviewSummary.application_fee ?? applicationFee)"></span></div>
                        <div class="px-4 py-3"><span class="text-gray-500 block" x-text="repaymentCadence() === 'monthly' ? @js(__('borrower.apply.review_step.monthly_repayment')) : @js(__('borrower.apply.review_step.weekly_repayment'))"></span><span class="font-medium" x-text="formatTzs(reviewSummary.installment_amount ?? quote.primary ?? quote.monthly)"></span></div>
                    </div>

                    <div x-show="hasStep('guarantor')" class="mb-5">
                        <h3 class="text-xs uppercase tracking-widest text-brand font-semibold mb-2">{{ __('borrower.apply.review_step.guarantor_section') }}</h3>
                        <div class="rounded-xl ring-1 ring-gray-200/80 bg-white divide-y divide-gray-100 text-sm shadow-sm">
                            <div class="px-4 py-3 flex justify-between gap-3">
                                <div><span class="text-gray-500 block">{{ __('borrower.apply.review_step.guarantor_type') }}</span><span class="font-medium" x-text="review.guarantorType"></span></div>
                                <button type="button" @click="gotoKey('guarantor')" class="text-xs text-brand shrink-0">{{ __('borrower.apply.edit') }}</button>
                            </div>
                            <div class="px-4 py-3"><span class="text-gray-500 block">{{ __('borrower.apply.review_step.guarantor_name') }}</span><span class="font-medium" x-text="review.guarantorName || '—'"></span></div>
                            <div class="px-4 py-3"><span class="text-gray-500 block">{{ __('borrower.apply.review_step.guarantor_status') }}</span><span class="font-medium" x-text="review.guarantorStatus"></span></div>
                        </div>
                    </div>

                    <h3 class="text-xs uppercase tracking-widest text-brand font-semibold mb-1">{{ __('borrower.apply.review_step.schedule_section') }}</h3>
                    <p class="text-xs text-gray-500 mb-2" x-show="!scheduleDatesAvailable">{{ __('borrower.apply.review_step.schedule_before_disbursement') }}</p>
                    <div class="rounded-xl ring-1 ring-gray-200/80 bg-white overflow-hidden mb-5 text-sm shadow-sm">
                        <p x-show="scheduleLoading" class="px-4 py-6 text-center text-gray-500">{{ __('borrower.apply.review_step.schedule_loading') }}</p>
                        <p x-show="!scheduleLoading && !repaymentSchedule.length" class="px-4 py-6 text-center text-gray-500">{{ __('borrower.apply.review_step.schedule_empty') }}</p>
                        <div x-show="!scheduleLoading && repaymentSchedule.length" class="overflow-x-auto">
                            <table class="min-w-full text-xs">
                                <thead class="bg-brand-muted/50 text-brand">
                                    <tr>
                                        <th class="px-3 py-2 text-left font-semibold">{{ __('borrower.apply.review_step.col_installment') }}</th>
                                        <th class="px-3 py-2 text-left font-semibold" x-show="scheduleDatesAvailable">{{ __('borrower.apply.review_step.col_due_date') }}</th>
                                        <th class="px-3 py-2 text-right font-semibold">{{ __('borrower.apply.review_step.col_principal') }}</th>
                                        <th class="px-3 py-2 text-right font-semibold">{{ __('borrower.apply.review_step.col_interest') }}</th>
                                        <th class="px-3 py-2 text-right font-semibold">{{ __('borrower.apply.review_step.col_total') }}</th>
                                        <th class="px-3 py-2 text-right font-semibold">{{ __('borrower.apply.review_step.col_balance') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <template x-for="row in repaymentSchedule" :key="row.installment_no">
                                        <tr>
                                            <td class="px-3 py-2" x-text="row.label || row.installment_no"></td>
                                            <td class="px-3 py-2 whitespace-nowrap" x-show="scheduleDatesAvailable" x-text="row.due_date"></td>
                                            <td class="px-3 py-2 text-right" x-text="formatTzs(row.principal_due)"></td>
                                            <td class="px-3 py-2 text-right" x-text="formatTzs(row.interest_due)"></td>
                                            <td class="px-3 py-2 text-right font-medium" x-text="formatTzs(row.total_due)"></td>
                                            <td class="px-3 py-2 text-right" x-text="formatTzs(row.remaining_balance)"></td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                {{-- Signature --}}
                <div x-show="stepKey === 'signature'" class="p-6 sm:p-8" data-signature-step>
                    <h2 class="text-xl font-semibold mb-1">{{ __('borrower.apply.signature_title') }}</h2>
                    <p class="text-sm text-gray-600 mb-5">{{ __('borrower.apply.signature_subtitle') }}</p>
                    <div class="rounded-xl bg-gray-50 ring-1 ring-gray-200 px-4 py-4 mb-5">
                        <p class="text-sm font-semibold text-gray-900">{{ __('borrower.apply.signature_declaration') }}</p>
                    </div>
                    <label class="flex items-start gap-3 text-sm text-gray-700 mb-5">
                        <input type="checkbox"
                               name="borrower_consent"
                               value="1"
                               x-model="declarationAccepted"
                               @change="persistDeclaration()"
                               class="mt-1 rounded border-gray-300 text-amber-500 focus:ring-amber-500">
                        <span>{{ __('borrower.apply.signature_consent', ['brand' => brand_name()]) }}</span>
                    </label>
                    <p x-show="declarationAccepted" x-cloak class="text-xs font-semibold text-emerald-700 mb-4">{{ __('borrower.apply.signature_declaration_saved') }}</p>
                    <x-site.signature-pad
                        :default-name="$verifiedLegalName"
                        :readonly-name="true"
                        :verified="$identityVerified"
                        :include-in-form="false" />
                </div>

                {{-- Submit --}}
                <div x-show="stepKey === 'submit'" class="p-6 sm:p-8">
                    <h2 class="text-xl font-semibold mb-1">{{ __('borrower.apply.submit_step.title') }}</h2>
                    <p class="text-sm text-gray-600 mb-5">{{ __('borrower.apply.submit_step.subtitle') }}</p>
                    <div class="rounded-xl bg-emerald-50 ring-1 ring-emerald-200 px-4 py-4 mb-5">
                        <p class="text-sm font-semibold text-emerald-900">{{ __('borrower.apply.submit_step.signed_title') }}</p>
                        <p class="text-sm text-emerald-800 mt-1">{{ __('borrower.apply.submit_step.signed_hint') }}</p>
                    </div>
                    <div x-show="borrowerSignature?.signature_data" x-cloak class="mb-5 rounded-xl ring-1 ring-gray-200 bg-white p-4">
                        <p class="text-[10px] uppercase tracking-widest text-gray-400 font-semibold mb-2">{{ __('borrower.apply.signature_draw_label') }}</p>
                        <p class="text-sm font-semibold text-gray-900 mb-2" x-text="borrowerSignature?.signer_name || verifiedLegalName"></p>
                        <img :src="borrowerSignature?.signature_data" alt="" class="max-h-28 border border-gray-200 rounded-lg bg-white">
                    </div>
                    <p x-show="draftReference" class="text-sm text-gray-600 mb-5">
                        {{ __('borrower.apply.submit_step.reference') }}:
                        <span class="font-mono font-semibold text-gray-900" x-text="draftReference"></span>
                    </p>
                    <input type="hidden" name="signature_data" data-submit-signature>
                    <input type="hidden" name="signer_name" data-submit-signer>
                    <input type="hidden" name="consent" value="1">
                </div>

                <div class="px-6 sm:px-8 py-4 border-t border-gray-200/80 bg-brand-muted/20 rounded-b-2xl flex items-center justify-between">
                    <button type="button" @click="step > 0 ? prev() : backToDetails()" class="text-sm font-medium text-gray-600 hover:text-gray-900" x-text="step > 0 ? i18n.back : i18n.backProducts"></button>
                    <div class="ml-auto flex items-center gap-3">
                        <a href="{{ route('site.borrower.dashboard') }}" class="text-sm text-gray-500 hover:text-gray-700">{{ __('borrower.apply.cancel') }}</a>
                        <button type="button" @click.prevent="next()" :disabled="advancing || resumeLoading || (guarantorInvitePreparing && stepKey === 'guarantor') || (stepKey === 'guarantor' && form.guarantor_mode === 'internal' && !internalGuarantorFieldsFilled()) || (stepKey === 'guarantor' && form.guarantor_mode === 'external' && !isExternalGuarantorComplete())" x-show="!['signature', 'submit'].includes(stepKey)" class="bg-brand-gold hover:bg-yellow-400 disabled:opacity-60 text-brand font-bold px-5 py-2.5 rounded-xl text-sm">
                            <span x-text="(guarantorInvitePreparing && stepKey === 'guarantor') ? @js(__('borrower.apply.application_fee.processing')) : @js(__('borrower.apply.continue'))"></span>
                        </button>
                        <button type="button" @click.prevent="signApplication()" :disabled="advancing || !declarationAccepted" x-show="stepKey === 'signature'" class="bg-brand hover:bg-brand-light disabled:opacity-60 text-white font-semibold px-5 py-2.5 rounded-xl text-sm">{{ __('borrower.apply.sign_application') }}</button>
                        <button type="button" @click="submitApplication()" :disabled="submitting || advancing" x-show="stepKey === 'submit'" class="bg-brand hover:bg-brand-light disabled:opacity-60 text-white font-semibold px-5 py-2.5 rounded-xl text-sm">
                            <span x-text="submitting ? @js(__('borrower.apply.submitting')) : @js(__('borrower.apply.submit'))"></span>
                        </button>
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
                savedDistrict: initialDistrict || '',
                region: initialRegion || '',
                district: initialDistrict || '',
                districtOptions: [],
                init() {
                    this.refreshDistricts();
                    this.syncDistrictSelection();
                },
                onRegionChange() {
                    this.district = '';
                    this.savedDistrict = '';
                    this.refreshDistricts();
                },
                refreshDistricts() {
                    const districts = this.region && this.locations[this.region]
                        ? [...this.locations[this.region]]
                        : [];

                    const preserve = this.savedDistrict || this.district;
                    if (preserve && !districts.includes(preserve)) {
                        districts.unshift(preserve);
                    }

                    this.districtOptions = districts;
                },
                syncDistrictSelection() {
                    this.$nextTick(() => {
                        if (this.savedDistrict) {
                            this.district = this.savedDistrict;
                        }
                    });
                },
            };
        }
        function applyWizard(config) {
            return {
                products: config.products,
                applicationFee: config.applicationFee,
                applicationFeePayUrl: config.applicationFeePayUrl || '',
                applicationFeeQuoteUrl: config.applicationFeeQuoteUrl || '',
                applicationFeeState: config.savedDraft?.application_fee || null,
                applicationFeePaid: false,
                valuationFeePayUrl: config.valuationFeePayUrl || '',
                valuationFeeQuoteUrl: config.valuationFeeQuoteUrl || '',
                assetDocumentUploadUrl: config.assetDocumentUploadUrl || '',
                assetTypeOptions: config.assetTypeOptions || {},
                assetDocumentLabels: config.assetDocumentLabels || {},
                customerAssets: config.customerAssets || [],
                valuationFeeAmount: config.valuationFeeAmount || 0,
                valuationFeeState: config.savedDraft?.valuation_fee || null,
                valuationFeePaid: false,
                valuationFeeChannel: 'mobile_money',
                valuationFeePhone: @js(old('payment_phone', $customer->phone ?? '')),
                valuationFeePaying: false,
                valuationFeePaymentReference: @js($valuationFeePaymentRef ?? null),
                assetDocuments: config.savedDraft?.asset_documents || {},
                assetDocumentUploading: false,
                feeChannel: 'mobile_money',
                feePhone: @js(old('payment_phone', $customer->phone ?? '')),
                feeUseWallet: false,
                feeUseStreak: false,
                feePromoCode: '',
                feeQuoteData: @js($feeQuote ?? null),
                feePaying: false,
                feePaymentReference: @js($applicationFeePaymentRef ?? null),
                purposeLabels: config.purposeLabels,
                productQuestions: config.productQuestions,
                profileSections: config.profileSections,
                incomeVerification: config.incomeVerification,
                readinessUrl: config.readinessUrl,
                guarantorLookupUrl: config.guarantorLookupUrl || '',
                groupMemberLookupUrl: config.groupMemberLookupUrl || '',
                groupMemberInviteUrl: config.groupMemberInviteUrl || '',
                groupMemberStatusesUrl: config.groupMemberStatusesUrl || '',
                previousGroupMembersUrl: config.previousGroupMembersUrl || '',
                selectPreviousGroupMemberUrl: config.selectPreviousGroupMemberUrl || '',
                groupLimits: config.groupLimits || { min: 5, max: 30 },
                leaderCustomerId: config.leaderCustomerId || null,
                leaderName: config.leaderName || '',
                leaderPhone: config.leaderPhone || '',
                group: config.savedDraft?.group || { name: '', purpose: '', target_member_count: null, amount_per_member: 0, members: [] },
                groupMemberMode: 'internal',
                groupExternal: { first_name: '', last_name: '', phone: '' },
                groupExternalInvite: null,
                groupInviteLoading: false,
                groupProgressLabels: @js(app(\App\Services\GroupMemberProgressService::class)->statusLabels()),
                groupProgressSummary: null,
                groupApplicationStatus: null,
                groupScoring: null,
                groupFeeBreakdownData: null,
                groupLookupMemberNo: '',
                groupLookupPhone: '',
                groupLookupLoading: false,
                groupLookupError: '',
                previousGroupMembers: [],
                guarantorInviteUrl: config.guarantorInviteUrl || '',
                previousGuarantorsUrl: config.previousGuarantorsUrl || '',
                selectPreviousGuarantorUrl: config.selectPreviousGuarantorUrl || '',
                previousGuarantors: [],
                guarantorStatusUrl: config.guarantorStatusUrl || '',
                guarantorExpireUrl: config.guarantorExpireUrl || '',
                repaymentPreviewUrl: config.repaymentPreviewUrl || '',
                borrowerSnapshot: config.borrowerSnapshot || {},
                incomeRangeLabels: config.incomeRangeLabels || {},
                activityTypeLabels: config.activityTypeLabels || {},
                tanzaniaLocations: config.tanzaniaLocations || {},
                draftSaveUrl: config.draftSaveUrl || '',
                reservationId: config.reservationId || null,
                draftSavedAt: null,
                draftSaveTimer: null,
                draftReference: config.savedDraft?.draft_reference || '',
                borrowerSignature: config.savedDraft?.borrower_signature || null,
                guarantorLookup: { ok: false, label: '', error: '', memberKey: '', phone: '', name: '' },
                guarantorValidating: false,
                guarantorChanging: false,
                externalGuarantor: config.savedDraft?.external_guarantor || null,
                guarantorInvitePreparing: false,
                advancing: false,
                submitting: false,
                resumeLoading: false,
                isResume: !! config.isResume,
                guarantorErrors: {},
                externalInviteTimer: null,
                initialPlan: config.initialPlan || [],
                assetApplication: config.assetApplication || null,
                reservationMode: !! config.reservationMode,
                marketplaceOnlyCodes: config.marketplaceOnlyCodes || [],
                marketplaceUrl: config.marketplaceUrl || '',
                profileUrl: config.profileUrl || '',
                canApply: !! config.canApply,
                verifiedLegalName: config.verifiedLegalName || '',
                engagementBoosts: config.engagementBoosts || null,
                qualificationLimit: Number(config.qualificationLimit || 0),
                processingSla: config.processingSla || null,
                declarationAccepted: !!(config.savedDraft?.declaration_accepted || config.savedDraft?.borrower_signature),
                declarationSaveTimer: null,
                i18n: config.i18n,
                phase: 'browse',
                readiness: null,
                readinessLoading: false,
                steps: [],
                step: 0,
                stepKey: '',
                current: null,
                form: {
                    loan_product_id: null,
                    requested_amount: 0,
                    requested_tenure_months: 0,
                    purpose: '',
                    guarantor_mode: 'internal',
                    internal_member_no: '',
                    internal_guarantor_phone: '',
                    internal_guarantor_name: '',
                    income_type: 'bank',
                    external_first_name: '',
                    external_middle_name: '',
                    external_last_name: '',
                    external_relationship: '',
                    external_phone: '',
                    external_email: '',
                    external_region: '',
                    external_district: '',
                    asset_type: '',
                    asset_description: '',
                    customer_asset_id: '',
                },
                quote: { monthly: 0, weekly: 0, primary: 0, frequency: 'monthly', interest: 0, total: 0, fees: 0 },
                review: { personal: '', residence: '', employment: '', nok: '', activity: '', guarantor: '', guarantorType: '', guarantorName: '', guarantorStatus: '' },
                reviewSummary: { monthly_rate_pct: 0, application_fee: 0, monthly_installment: 0, installment_amount: 0, repayment_cadence: 'monthly' },
                repaymentSchedule: [],
                scheduleDatesAvailable: false,
                scheduleLoading: false,
                stepIcons: {
                    quote: '💰',
                    group_setup: '👥',
                    group_members: '📋',
                    asset_details: '🚗',
                    valuation_fee: '📋',
                    asset_tenure: '📅',
                    application_fee: '💳',
                    guarantor: '🤝',
                    product_questions: '📄',
                    review: '✅',
                    signature: '✍️',
                    submit: '📤',
                },

                syncStepKey() {
                    this.stepKey = this.steps[this.step]?.key ?? '';
                },

                resolveStepIndex(stepKey, fallbackIndex = 0) {
                    if (stepKey) {
                        const byKey = this.steps.findIndex(s => s.key === stepKey);
                        if (byKey >= 0) return byKey;
                    }
                    return Math.min(Math.max(0, fallbackIndex), Math.max(0, this.steps.length - 1));
                },

                districtsForRegion() {
                    const r = this.form.external_region;
                    return r && this.tanzaniaLocations[r] ? this.tanzaniaLocations[r] : [];
                },

                init() {
                    this.syncFeePaidState();
                    this.syncValuationFeePaidState();
                    window.applyWizardSaveDraft = () => this.persistDraft(true);
                    this.$watch('phase', (value, oldValue) => {
                        this.scheduleDraftSave();
                        if (value === 'application' && oldValue !== 'application') {
                            this.persistDraft(true);
                        }
                    });
                    this.$watch('step', () => {
                        this.scheduleDraftSave();
                        this.syncStepKey();
                    });
                    this.$watch('stepKey', (key) => {
                        if (key === 'application_fee') {
                            this.enterApplicationFeeStep();
                        }
                        if (key === 'guarantor') {
                            this.loadPreviousGuarantors();
                        }
                        if (key === 'group_members') {
                            this.loadPreviousGroupMembers();
                            this.refreshGroupMemberStatuses();
                        }
                        if (key === 'guarantor' && this.externalGuarantor?.invitation_id) {
                            this.refreshExternalGuarantorStatus();
                        }
                        if (key === 'signature') {
                            this.$nextTick(() => this.restoreSignaturePad());
                        }
                        if (key === 'submit') {
                            this.$nextTick(() => this.syncSubmitPayload(this.formRoot()));
                        }
                    });
                    this.$watch('steps', () => this.syncStepKey());
                    this.$watch('form.guarantor_mode', (mode) => {
                        if (mode === 'external') {
                            this.scheduleExternalInvitePrep();
                        } else if (mode === 'internal') {
                            this.guarantorLookup = { ok: false, label: '', error: '', memberKey: '', phone: '', name: '' };
                        }
                    });
                    this.syncStepKey();
                    if (this.reservationMode && this.assetApplication) {
                        this.beginReservationApplication();
                        return;
                    }
                    if (config.savedDraft) {
                        this.restoreDraft(config.savedDraft);
                        return;
                    }
                    if (config.isResume) {
                        window.location.href = config.loansUrl || '{{ route('site.borrower.loans') }}';
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

                persistDeclaration() {
                    clearTimeout(this.declarationSaveTimer);
                    this.declarationSaveTimer = setTimeout(() => this.persistDraft(true), 250);
                },

                buildDraftPayload() {
                    const inputs = {};
                    if (this.phase === 'application') {
                        const form = this.formRoot();
                        if (form) {
                            const fd = new FormData(form);
                            for (const [key, value] of fd.entries()) {
                                if (value instanceof File) continue;
                                if (key === 'signature_data' || key === 'signer_name') continue;
                                inputs[key] = value;
                            }
                        }
                    }
                    return {
                        phase: this.phase,
                        step: this.step,
                        step_key: this.stepKey,
                        application_started: this.phase === 'application',
                        loan_product_id: this.form.loan_product_id,
                        asset_reservation_id: this.reservationId,
                        form: this.form,
                        inputs,
                        guarantor_lookup: this.guarantorLookup.ok ? this.guarantorLookup : null,
                        application_fee: this.applicationFeeState,
                        valuation_fee: this.valuationFeeState,
                        asset_documents: this.assetDocuments,
                        external_guarantor: this.externalGuarantor,
                        borrower_signature: this.borrowerSignature,
                        declaration_accepted: this.declarationAccepted,
                        group: this.group,
                    };
                },

                feeAmount() {
                    return Number(this.applicationFee) || 0;
                },

                effectiveFeeAmount() {
                    if (this.feeQuoteData) {
                        const due = Number(this.feeQuoteData.cash_due ?? this.feeQuoteData.after_discount);
                        if (due > 0) return due;
                    }
                    const fromQuote = this.feeAmount();
                    const fromProduct = Number(this.current?.application_fee) || 0;
                    const fromReadiness = Number(this.readiness?.fees?.application) || 0;
                    return Math.max(fromQuote, fromProduct, fromReadiness);
                },

                showsApplicationFeePayment() {
                    return ! this.applicationFeePaid && this.effectiveFeeAmount() > 0;
                },

                enterApplicationFeeStep() {
                    const amount = this.effectiveFeeAmount();
                    if (amount > 0 && this.feeAmount() < amount) {
                        this.applicationFee = amount;
                    }
                    this.syncFeePaidState();
                    this.refreshApplicationFeeQuote();
                },

                syncFeePaidState() {
                    const amount = this.effectiveFeeAmount();
                    const st = this.applicationFeeState?.status || '';
                    if (amount > 0 && st === 'waived' && ! this.applicationFeeState?.reference) {
                        this.applicationFeeState = null;
                    }
                    const status = this.applicationFeeState?.status || '';
                    this.applicationFeePaid = amount <= 0
                        || ['paid', 'waived'].includes(status);
                },

                feeGateSatisfied() {
                    return this.effectiveFeeAmount() <= 0
                        || ['paid', 'waived', 'pending'].includes(this.applicationFeeState?.status || '');
                },

                feeGateRequiredForStep(targetStepKey) {
                    const feeIdx = this.steps.findIndex(s => s.key === 'application_fee');
                    const targetIdx = this.steps.findIndex(s => s.key === targetStepKey);
                    return feeIdx >= 0 && targetIdx > feeIdx && this.effectiveFeeAmount() > 0;
                },

                enforceStepRequirements(onResume = false) {
                    const feeIdx = this.steps.findIndex(s => s.key === 'application_fee');
                    if (feeIdx < 0 || this.effectiveFeeAmount() <= 0 || this.feeGateSatisfied()) return;
                    const currentIdx = this.steps.findIndex(s => s.key === this.stepKey);
                    if (currentIdx <= feeIdx) return;
                    if (onResume && this.applicationFeeState?.status) return;
                    if (currentIdx > feeIdx) {
                        this.step = feeIdx;
                        this.syncStepKey();
                    }
                },

                syncQuoteFormFromDom() {
                    const purpose = this.readFormField('purpose');
                    if (purpose) this.form.purpose = purpose;
                },

                async autoWaiveApplicationFeeIfNeeded() {
                    if (this.effectiveFeeAmount() > 0 || this.applicationFeePaid) return;
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

                isAssetBackedProduct(product) {
                    return (product?.code || '').toUpperCase() === 'AB';
                },

                effectiveValuationFeeAmount() {
                    return Number(this.valuationFeeAmount) || 0;
                },

                showsValuationFeePayment() {
                    return ! this.valuationFeePaid && this.effectiveValuationFeeAmount() > 0;
                },

                enterValuationFeeStep() {
                    this.syncValuationFeePaidState();
                    this.refreshValuationFeeQuote();
                },

                syncValuationFeePaidState() {
                    const amount = this.effectiveValuationFeeAmount();
                    const st = this.valuationFeeState?.status || '';
                    if (amount > 0 && st === 'waived' && ! this.valuationFeeState?.reference) {
                        this.valuationFeeState = null;
                    }
                    const status = this.valuationFeeState?.status || '';
                    this.valuationFeePaid = amount <= 0 || ['paid', 'waived'].includes(status);
                },

                valuationFeeGateSatisfied() {
                    if (! this.hasStep('valuation_fee')) return true;
                    return this.effectiveValuationFeeAmount() <= 0
                        || ['paid', 'waived', 'pending'].includes(this.valuationFeeState?.status || '');
                },

                valuationGateRequiredForStep(targetStepKey) {
                    const feeIdx = this.steps.findIndex(s => s.key === 'valuation_fee');
                    const targetIdx = this.steps.findIndex(s => s.key === targetStepKey);
                    return feeIdx >= 0 && targetIdx > feeIdx && this.effectiveValuationFeeAmount() > 0;
                },

                async refreshValuationFeeQuote() {
                    if (! this.form.loan_product_id || ! this.valuationFeeQuoteUrl) {
                        this.syncValuationFeePaidState();
                        return;
                    }
                    try {
                        const url = `${this.valuationFeeQuoteUrl}?loan_product_id=${encodeURIComponent(this.form.loan_product_id)}`;
                        const res = await fetch(url, {
                            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                            credentials: 'same-origin',
                        });
                        if (! res.ok) return;
                        const data = await res.json();
                        if (data.amount !== undefined) {
                            this.valuationFeeAmount = data.amount;
                        }
                    } catch (e) {
                        console.warn('valuation fee quote failed', e);
                    } finally {
                        this.syncValuationFeePaidState();
                    }
                },

                async payValuationFee() {
                    if (! this.valuationFeePayUrl || ! this.form.loan_product_id) return;
                    if (! this.form.asset_type) {
                        alert(@js(__('borrower.apply.asset_details.type_required')));
                        return;
                    }
                    this.valuationFeePaying = true;
                    try {
                        const body = {
                            loan_product_id: this.form.loan_product_id,
                            channel: this.valuationFeeChannel || 'mobile_money',
                            payment_phone: this.valuationFeePhone || '',
                            asset_type: this.form.asset_type,
                            asset_description: this.form.asset_description || '',
                        };
                        const res = await fetch(this.valuationFeePayUrl, {
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
                            throw new Error(data.message || @js(__('borrower.apply.valuation_fee.failed')));
                        }
                        this.valuationFeeState = data.fee;
                        this.syncValuationFeePaidState();
                        await this.persistDraft(true);
                        alert(data.message || @js(__('borrower.apply.valuation_fee.paid')));
                    } catch (e) {
                        alert(e?.message || @js(__('borrower.apply.valuation_fee.failed')));
                    } finally {
                        this.valuationFeePaying = false;
                    }
                },

                applyExistingAsset() {
                    const id = String(this.form.customer_asset_id || '');
                    if (! id) return;
                    const asset = (this.customerAssets || []).find(a => String(a.id) === id);
                    if (! asset) return;
                    this.form.asset_type = asset.asset_type || this.form.asset_type;
                    this.form.asset_description = asset.description || asset.label || this.form.asset_description;
                    if (asset.estimated_value && ! this.form.requested_amount) {
                        this.form.requested_amount = Number(asset.estimated_value);
                        this.updateQuote();
                    }
                    this.scheduleDraftSave();
                },

                selectedCustomerAsset() {
                    const id = String(this.form.customer_asset_id || '');
                    if (! id) return null;
                    return (this.customerAssets || []).find(a => String(a.id) === id) || null;
                },

                async uploadAssetDocument(code, event) {
                    const file = event.target?.files?.[0];
                    if (! file || ! this.assetDocumentUploadUrl || ! this.form.loan_product_id) return;
                    this.assetDocumentUploading = true;
                    try {
                        const formData = new FormData();
                        formData.append('loan_product_id', this.form.loan_product_id);
                        formData.append('document_code', code);
                        formData.append('file', file);
                        const res = await fetch(this.assetDocumentUploadUrl, {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '',
                            },
                            credentials: 'same-origin',
                            body: formData,
                        });
                        const data = await res.json();
                        if (! res.ok || ! data.ok) {
                            throw new Error(data.message || @js(__('borrower.apply.asset_details.upload_failed')));
                        }
                        this.assetDocuments = data.asset_documents || {};
                        await this.persistDraft(true);
                    } catch (e) {
                        alert(e?.message || @js(__('borrower.apply.asset_details.upload_failed')));
                    } finally {
                        this.assetDocumentUploading = false;
                        if (event.target) event.target.value = '';
                    }
                },

                async refreshApplicationFeeQuote() {
                    if (! this.form.loan_product_id) return;
                    if (this.current?.application_fee > 0) {
                        this.applicationFee = this.current.application_fee;
                    }
                    if (! this.applicationFeeQuoteUrl) {
                        this.syncFeePaidState();
                        return;
                    }
                    try {
                        const params = new URLSearchParams({
                            loan_product_id: String(this.form.loan_product_id),
                            use_wallet: this.feeUseWallet ? '1' : '0',
                            use_streak: this.feeUseStreak ? '1' : '0',
                        });
                        if (this.feePromoCode) {
                            params.set('promo_code', this.feePromoCode);
                        }
                        if (this.isGroupProduct(this.current)) {
                            params.set('member_count', String(Math.max(1, this.groupTargetCount())));
                        }
                        const url = `${this.applicationFeeQuoteUrl}?${params.toString()}`;
                        const res = await fetch(url, {
                            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                            credentials: 'same-origin',
                        });
                        if (! res.ok) return;
                        const data = await res.json();
                        if (data.amount !== undefined) {
                            this.applicationFee = data.amount;
                        }
                        if (data.quote) {
                            this.feeQuoteData = data.quote;
                        }
                        if (data.breakdown) {
                            this.groupFeeBreakdownData = data.breakdown;
                        }
                    } catch (e) {
                        console.warn('application fee quote failed', e);
                    } finally {
                        this.syncFeePaidState();
                    }
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
                            use_streak: !!this.feeUseStreak,
                            promo_code: this.feePromoCode || null,
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
                            throw new Error(data.message || @js(__('borrower.apply.application_fee.failed')));
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

                clearSavedDraft() {
                    if (! this.draftSaveUrl) return Promise.resolve();
                    return fetch(this.draftSaveUrl, {
                        method: 'PUT',
                        headers: this.draftHeaders(),
                        credentials: 'same-origin',
                        body: JSON.stringify({ phase: 'browse' }),
                    }).catch(() => {});
                },

                persistDraft(sync = false) {
                    if (! this.draftSaveUrl || this.phase === 'browse' || this.resumeLoading) {
                        return Promise.resolve();
                    }
                    const request = () => {
                        let payload;
                        try {
                            payload = this.buildDraftPayload();
                        } catch (e) {
                            console.warn('apply wizard draft payload failed', e);
                            payload = {
                                phase: this.phase,
                                step: this.step,
                                step_key: this.stepKey,
                                loan_product_id: this.form.loan_product_id,
                                asset_reservation_id: this.reservationId,
                                form: this.form,
                                inputs: {},
                                guarantor_lookup: this.guarantorLookup.ok ? this.guarantorLookup : null,
                                application_fee: this.applicationFeeState,
                                external_guarantor: this.externalGuarantor,
                                borrower_signature: this.borrowerSignature,
                                declaration_accepted: this.declarationAccepted,
                            };
                        }
                        return fetch(this.draftSaveUrl, {
                            method: 'PUT',
                            headers: this.draftHeaders(),
                            credentials: 'same-origin',
                            body: JSON.stringify(payload),
                        }).then(res => res.ok ? res.json() : Promise.reject(res))
                          .then((data) => {
                              this.draftSavedAt = new Date().toLocaleTimeString();
                              if (data?.draft_reference) {
                                  this.draftReference = data.draft_reference;
                              }
                          });
                    };

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
                    if (! root) return;
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
                    if (! product) {
                        if (this.isResume && config.loansUrl) {
                            window.location.href = config.loansUrl;
                        }
                        return false;
                    }

                    const target = draft.resume_target || {};
                    this.resumeLoading = true;
                    this.current = product;
                    this.form.loan_product_id = product.id;
                    this.phase = target.phase === 'application' ? 'application' : 'details';
                    this.selectProduct(product, false);

                    Object.assign(this.form, draft.form || {});
                    if (draft.inputs) {
                        this.restoreFormInputs(draft.inputs);
                        [
                            'external_first_name', 'external_middle_name', 'external_last_name',
                            'external_relationship', 'external_phone', 'external_email',
                            'external_region', 'external_district',
                            'internal_member_no', 'internal_guarantor_phone', 'internal_guarantor_name',
                        ].forEach((key) => {
                            if (draft.inputs[key]) this.form[key] = draft.inputs[key];
                        });
                    }
                    this.syncGuarantorFormFromDom();
                    if (this.form.guarantor_mode === 'external') {
                        this.scheduleExternalInvitePrep();
                    }
                    if (draft.guarantor_lookup) this.guarantorLookup = draft.guarantor_lookup;
                    if (draft.application_fee) this.applicationFeeState = draft.application_fee;
                    if (draft.valuation_fee) this.valuationFeeState = draft.valuation_fee;
                    if (draft.asset_documents) this.assetDocuments = draft.asset_documents;
                    if (draft.external_guarantor) this.externalGuarantor = draft.external_guarantor;
                    if (draft.borrower_signature) this.borrowerSignature = draft.borrower_signature;
                    if (draft.declaration_accepted || draft.borrower_signature) this.declarationAccepted = true;
                    if (draft.group) this.group = draft.group;
                    if (draft.draft_reference) this.draftReference = draft.draft_reference;
                    this.syncFeePaidState();
                    this.syncValuationFeePaidState();

                    const resumeStep = target.step ?? draft.step ?? 0;
                    const resumeKey = target.step_key ?? draft.step_key ?? '';

                    return this.loadReadiness(product.id).then(() => {
                        this.phase = target.phase === 'application' || target.phase === 'details'
                            ? target.phase
                            : 'application';
                        if (this.phase !== 'application') {
                            return true;
                        }

                        this.phase = 'application';
                        this.rebuildSteps(resumeKey);
                        this.step = this.resolveStepIndex(resumeKey, resumeStep);
                        this.updateQuote();
                        this.syncStepKey();
                        this.enforceStepRequirements(this.isResume);
                        if (this.stepKey === 'review' || this.stepKey === 'signature' || this.stepKey === 'submit') {
                            this.refreshReview(this.formRoot());
                        }
                        if (this.stepKey === 'signature') {
                            this.$nextTick(() => this.restoreSignaturePad());
                        }
                        if (this.stepKey === 'submit') {
                            this.$nextTick(() => this.syncSubmitPayload(this.formRoot()));
                        }
                        return true;
                    }).finally(() => {
                        this.resumeLoading = false;
                        window.scrollTo({ top: 0, behavior: 'smooth' });
                    });
                },

                isMarketplaceProduct(product) {
                    const code = (product?.code || '').toUpperCase();
                    return this.marketplaceOnlyCodes.map(c => c.toUpperCase()).includes(code);
                },

                isGroupProduct(product) {
                    if (! product) return false;
                    if (product.is_group) return true;
                    const code = (product.code || '').toUpperCase();
                    return code === 'GL';
                },

                initGroupLeader() {
                    if (! this.leaderCustomerId) return;
                    if (! this.group.target_member_count) {
                        this.group.target_member_count = this.groupLimits.min;
                    }
                    if (! this.group.amount_per_member) {
                        this.group.amount_per_member = this.groupAmountPerMemberMin();
                    } else {
                        this.clampGroupAmountPerMember();
                    }
                    const exists = this.group.members.some(m => Number(m.customer_id) === Number(this.leaderCustomerId));
                    if (exists) {
                        this.syncGroupAmounts();
                        return;
                    }
                    this.group.members = [{
                        customer_id: this.leaderCustomerId,
                        name: this.leaderName,
                        phone: this.leaderPhone,
                        role: 'leader',
                        requested_amount: this.group.amount_per_member,
                    }];
                    this.syncGroupAmounts();
                },

                groupTargetCount() {
                    return Number(this.group.target_member_count || this.groupLimits.min || 0);
                },

                groupAmountPerMemberMin() {
                    const totalMin = Number(this.current?.min || 1000);
                    const members = Math.max(this.groupLimits.min, this.groupTargetCount() || this.groupLimits.min);
                    return Math.max(1000, Math.ceil(totalMin / members));
                },

                groupAmountPerMemberMax() {
                    const totalMax = Number(this.current?.max || 5000000);
                    const members = Math.max(this.groupLimits.min, this.groupTargetCount() || this.groupLimits.min);
                    return Math.max(this.groupAmountPerMemberMin(), Math.floor(totalMax / members));
                },

                clampGroupAmountPerMember() {
                    const min = this.groupAmountPerMemberMin();
                    const max = this.groupAmountPerMemberMax();
                    const value = Number(this.group.amount_per_member || min);
                    this.group.amount_per_member = Math.min(max, Math.max(min, value));
                },

                groupTotalAmount() {
                    const count = this.groupTargetCount();
                    const perMember = Number(this.group.amount_per_member || 0);
                    return count * perMember;
                },

                groupFeeBreakdown() {
                    if (this.groupFeeBreakdownData) {
                        return this.groupFeeBreakdownData;
                    }
                    const perMember = Number(this.current?.application_fee || 0);
                    const count = this.groupTargetCount();
                    return {
                        per_member: perMember,
                        member_count: count,
                        total: perMember * count,
                    };
                },

                selectGroupTenure(months) {
                    this.form.requested_tenure_months = Number(months);
                    this.updateQuote();
                },

                syncGroupAmounts() {
                    this.clampGroupAmountPerMember();
                    const perMember = Number(this.group.amount_per_member || 0);
                    this.group.members = this.group.members.map((member) => ({
                        ...member,
                        requested_amount: perMember,
                    }));
                    this.form.requested_amount = this.group.members.reduce((sum, m) => sum + Number(m.requested_amount || 0), 0);
                    if (this.group.purpose) this.form.purpose = this.group.purpose;
                    this.updateQuote();
                },

                groupProgress() {
                    if (this.groupProgressSummary) {
                        return this.groupProgressSummary;
                    }
                    const target = this.groupTargetCount();
                    const added = this.group.members.length;
                    const verified = this.group.members.filter(m => (m.status_key || '') === 'kyc_complete').length;
                    const profiles = this.group.members.filter(m => ['profile_complete', 'kyc_complete'].includes(m.status_key || '')).length;
                    const invitationsPending = this.group.members.filter(m => [
                        'invitation_sent', 'link_opened', 'registration_started', 'registration_complete', 'profile_incomplete',
                    ].includes(m.status_key || (m.invitation_id ? 'invitation_sent' : ''))).length;
                    const tpl = this.i18n.groupProgress || {};
                    const fill = (text, vars) => Object.entries(vars).reduce((s, [k, v]) => s.replace(':' + k, String(v)), text || '');
                    return {
                        target,
                        added,
                        verified,
                        profiles_complete: profiles,
                        invitations_pending: invitationsPending,
                        summary: [
                            fill(tpl.added, { added, target }),
                            fill(tpl.profiles, { done: profiles, target }),
                            fill(tpl.verified, { done: verified, target }),
                            fill(tpl.invitations_pending, { count: invitationsPending }),
                        ],
                        can_submit: target > 0 && added === target && verified === target,
                    };
                },

                memberStatusLabel(member) {
                    const key = member.status_key || (member.invitation_id ? 'invitation_sent' : 'profile_incomplete');
                    return this.groupProgressLabels?.[key] || key;
                },

                memberStatusClass(member) {
                    const key = member.status_key || (member.invitation_id ? 'invitation_sent' : 'profile_incomplete');
                    return key === 'kyc_complete' ? 'text-emerald-700' : 'text-brand';
                },

                groupScoringRiskBandLabel(band) {
                    return this.i18n.groupScoringRiskBand?.[band] || band || '';
                },

                groupStatusPayload() {
                    return {
                        name: this.group.name || '',
                        purpose: this.group.purpose || '',
                        target_member_count: this.groupTargetCount(),
                    };
                },

                async refreshGroupMemberStatuses() {
                    if (! this.groupMemberStatusesUrl || ! this.group.members.length) return;
                    try {
                        const res = await fetch(this.groupMemberStatusesUrl, {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            credentials: 'same-origin',
                            body: JSON.stringify({
                                members: this.group.members,
                                target_member_count: this.groupTargetCount(),
                                group: this.groupStatusPayload(),
                            }),
                        });
                        const data = await res.json();
                        if (res.ok && data.ok) {
                            if (data.summary) {
                                this.groupProgressSummary = data.summary;
                            }
                            if (data.application_status) {
                                this.groupApplicationStatus = data.application_status;
                            }
                            if (data.scoring) {
                                this.groupScoring = data.scoring;
                            }
                            if (Array.isArray(data.members)) {
                                this.group.members = data.members;
                            }
                        }
                    } catch (e) {
                        // Non-blocking refresh
                    }
                },

                async inviteExternalGroupMember() {
                    if (! this.groupMemberInviteUrl || ! this.current) return;
                    this.groupLookupError = '';
                    this.groupInviteLoading = true;
                    try {
                        const res = await fetch(this.groupMemberInviteUrl, {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            credentials: 'same-origin',
                            body: JSON.stringify({
                                loan_product_id: this.current.id,
                                first_name: this.groupExternal.first_name,
                                last_name: this.groupExternal.last_name,
                                phone: this.groupExternal.phone,
                                invitation_reason: this.group.invitation_reason || null,
                                group: {
                                    name: this.group.name,
                                    purpose: this.group.purpose,
                                    amount_per_member: this.group.amount_per_member,
                                    requested_tenure_months: this.form.requested_tenure_months,
                                    target_member_count: this.group.target_member_count,
                                },
                            }),
                        });
                        const data = await res.json();
                        if (! res.ok || ! data.ok) {
                            this.groupLookupError = data.message || @js(__('borrower.apply.group.lookup_not_found'));
                            return;
                        }
                        this.groupExternalInvite = data.share;
                        this.group.members.push({
                            invitation_id: data.invitation_id || data.share?.invitation_id,
                            name: data.name,
                            phone: data.phone,
                            role: 'member',
                            requested_amount: this.group.amount_per_member,
                            status_key: 'invitation_sent',
                        });
                        this.groupExternal = { first_name: '', last_name: '', phone: '' };
                        this.syncGroupAmounts();
                        this.groupProgressSummary = null;
                        await this.persistDraft(true);
                    } catch (e) {
                        this.groupLookupError = @js(__('borrower.apply.group.lookup_not_found'));
                    } finally {
                        this.groupInviteLoading = false;
                    }
                },

                updateGroupTotal() {
                    const total = this.group.members.reduce((sum, m) => sum + Number(m.requested_amount || 0), 0);
                    this.form.requested_amount = total;
                    if (this.group.purpose) this.form.purpose = this.group.purpose;
                    this.updateQuote();
                },

                async loadPreviousGroupMembers() {
                    if (! this.previousGroupMembersUrl) return;
                    try {
                        const excludeIds = (this.group?.members || [])
                            .map(m => m.customer_id)
                            .filter(Boolean)
                            .join(',');
                        const url = excludeIds
                            ? `${this.previousGroupMembersUrl}?exclude=${encodeURIComponent(excludeIds)}`
                            : this.previousGroupMembersUrl;
                        const response = await fetch(url, { headers: { Accept: 'application/json' } });
                        const data = await response.json();
                        this.previousGroupMembers = data.members || [];
                    } catch (e) {
                        this.previousGroupMembers = [];
                    }
                },

                async selectPreviousGroupMember(customerId) {
                    if (! this.selectPreviousGroupMemberUrl || ! this.current || ! customerId) return;
                    if (this.group.members.length >= this.groupTargetCount()) return;
                    if (this.group.members.some(m => Number(m.customer_id) === Number(customerId))) {
                        this.groupLookupError = @js(__('borrower.apply.group_members.duplicate'));
                        return;
                    }
                    this.groupLookupError = '';
                    this.groupLookupLoading = true;
                    try {
                        const res = await fetch(this.selectPreviousGroupMemberUrl, {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            credentials: 'same-origin',
                            body: JSON.stringify({
                                customer_id: customerId,
                                loan_product_id: this.current.id,
                            }),
                        });
                        const data = await res.json();
                        if (! res.ok || ! data.ok) {
                            this.groupLookupError = data.message || @js(__('borrower.apply.group.lookup_not_found'));
                            return;
                        }
                        this.group.members.push({
                            customer_id: data.customer_id,
                            invitation_id: data.invitation_id,
                            name: data.name,
                            phone: data.phone,
                            role: 'member',
                            requested_amount: this.group.amount_per_member,
                            status_key: data.status_key || 'profile_incomplete',
                        });
                        this.updateGroupTotal();
                        await this.persistDraft(true);
                    } catch (e) {
                        this.groupLookupError = @js(__('borrower.apply.group.lookup_not_found'));
                    } finally {
                        this.groupLookupLoading = false;
                    }
                },

                async lookupGroupMember() {
                    if (! this.groupMemberLookupUrl) return;
                    this.groupLookupError = '';
                    const memberNo = (this.groupLookupMemberNo || '').trim();
                    const phone = (this.groupLookupPhone || '').trim();
                    if (! memberNo) {
                        this.groupLookupError = @js(__('borrower.apply.alerts.guarantor_membership'));
                        return;
                    }
                    if (! phone) {
                        this.groupLookupError = @js(__('borrower.apply.group.lookup_invalid_phone'));
                        return;
                    }
                    if (this.group.members.length >= this.groupTargetCount()) {
                        return;
                    }
                    this.groupLookupLoading = true;
                    try {
                        const res = await fetch(this.groupMemberLookupUrl, {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            credentials: 'same-origin',
                            body: JSON.stringify({
                                member_no: memberNo,
                                phone,
                                loan_product_id: this.current?.id,
                            }),
                        });
                        const data = await res.json();
                        if (! res.ok || ! data.ok) {
                            this.groupLookupError = data.message || @js(__('borrower.apply.group.lookup_not_found'));
                            return;
                        }
                        if (this.group.members.some(m => Number(m.customer_id) === Number(data.customer_id))) {
                            this.groupLookupError = @js(__('borrower.apply.group_members.duplicate'));
                            return;
                        }
                        this.group.members.push({
                            customer_id: data.customer_id,
                            invitation_id: data.invitation_id,
                            name: data.name,
                            phone: data.phone,
                            role: 'member',
                            requested_amount: this.group.amount_per_member,
                            status_key: data.status_key || 'profile_incomplete',
                        });
                        this.groupLookupMemberNo = '';
                        this.groupLookupPhone = '';
                        this.groupProgressSummary = null;
                        this.updateGroupTotal();
                        await this.persistDraft(true);
                    } catch (e) {
                        this.groupLookupError = @js(__('borrower.apply.group.lookup_not_found'));
                    } finally {
                        this.groupLookupLoading = false;
                    }
                },

                removeGroupMember(index) {
                    const member = this.group.members[index];
                    if (! member || member.role === 'leader') return;
                    this.group.members.splice(index, 1);
                    this.updateGroupTotal();
                },

                beginReservationApplication() {
                    const p = this.products.find(x => x.id == config.preselect);
                    if (! p) return;
                    this.selectProduct(p, true);
                    this.form.requested_amount = this.assetApplication.remaining_loan;
                    this.form.requested_tenure_months = this.assetApplication.max_tenure_months;
                    this.form.purpose = this.assetApplication.purpose || 'asset_financing';
                    this.phase = 'application';
                    this.step = 0;
                    this.syncStepKey();
                    this.loadReadiness(p.id);
                },

                openProduct(p) {
                    if (this.isMarketplaceProduct(p)) {
                        window.location.href = this.marketplaceUrl;
                        return;
                    }
                    this.selectProduct(p, false);
                    this.phase = 'details';
                    this.loadReadiness(p.id);
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                },

                backToBrowse() {
                    this.phase = 'browse';
                    this.readiness = null;
                    this.current = null;
                    this.form.loan_product_id = null;
                    this.clearSavedDraft();
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
                    if (this._readinessPromise && this._readinessProductId === productId) {
                        return this._readinessPromise;
                    }
                    this.readinessLoading = true;
                    this._readinessProductId = productId;
                    const url = this.readinessUrl.replace('__ID__', encodeURIComponent(productId));
                    this._readinessPromise = fetch(url, {
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        credentials: 'same-origin',
                    })
                        .then(res => res.ok ? res.json() : Promise.reject(res))
                        .then(data => {
                            this.readiness = data;
                            if (this.phase === 'application' && this.current && ! this.resumeLoading) {
                                this.rebuildSteps();
                                this.enforceStepRequirements(this.isResume);
                                this.syncStepKey();
                            }
                            if (data.fees?.application !== undefined) {
                                this.applicationFee = data.fees.application;
                                this.syncFeePaidState();
                            }
                            return data;
                        })
                        .catch(() => {
                            if (this.readiness?.product?.id !== productId) {
                                this.readiness = null;
                            }
                            alert(this.i18n.alerts.loadProduct);
                        })
                        .finally(() => {
                            this.readinessLoading = false;
                            this._readinessPromise = null;
                            this._readinessProductId = null;
                        });
                    return this._readinessPromise;
                },

                async startApplication() {
                    if (! this.current) return;
                    const productId = this.current.id;
                    if (! this.readiness || this.readiness?.product?.id !== productId) {
                        await this.loadReadiness(productId);
                    }
                    if (! this.steps.length) {
                        this.selectProduct(this.current, true);
                    }
                    this.phase = 'application';
                    this.rebuildSteps();
                    if (! this.steps.length) {
                        alert(this.i18n.alerts.loadProduct);
                        return;
                    }
                    this.step = 0;
                    this.syncStepKey();
                    await this.persistDraft(true);
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                },

                withStepIcon(step) {
                    return { ...step, icon: this.stepIcons[step.key] || '' };
                },

                rebuildSteps(preserveStepKey = null) {
                    const prevKey = preserveStepKey || this.stepKey || this.steps[this.step]?.key || '';
                    if (this.readiness?.step_plan?.length) {
                        this.steps = this.readiness.step_plan.map(s => this.withStepIcon(s));
                    } else if (this.initialPlan?.length) {
                        this.steps = this.initialPlan.map(s => this.withStepIcon(s));
                    } else {
                        const stepLabels = this.i18n.steps;
                        const steps = [];
                        if (this.isGroupProduct(this.current)) {
                            steps.push({ key: 'group_setup', label: stepLabels.group_setup || @js(__('borrower.apply.steps.group_setup')) });
                            steps.push({ key: 'group_members', label: stepLabels.group_members || @js(__('borrower.apply.steps.group_members')) });
                        } else if (this.isAssetBackedProduct(this.current)) {
                            steps.push({ key: 'asset_details', label: stepLabels.asset_details || @js(__('borrower.apply.steps.asset_details')) });
                        } else if (! this.isMarketplaceProduct(this.current)) {
                            steps.push({ key: 'quote', label: stepLabels.quote });
                        } else {
                            steps.push({ key: 'asset_tenure', label: stepLabels.asset_tenure || stepLabels.quote });
                        }
                        steps.push({ key: 'application_fee', label: @js(__('borrower.apply.steps.application_fee')) });
                        if (this.requiresGuarantor()) {
                            steps.push({ key: 'guarantor', label: @js(__('borrower.apply.steps.guarantor')) });
                        }
                        if (this.current?.code && this.productQuestions[this.current.code]) {
                            steps.push({ key: 'product_questions', label: stepLabels.product_questions });
                        }
                        steps.push({ key: 'review', label: @js(__('borrower.apply.steps.review')) });
                        steps.push({ key: 'signature', label: @js(__('borrower.apply.steps.signature')) });
                        steps.push({ key: 'submit', label: @js(__('borrower.apply.steps.submit')) });
                        this.steps = steps.map(s => this.withStepIcon(s));
                    }
                    this.step = this.resolveStepIndex(prevKey, this.step);
                    this.syncStepKey();
                },

                selectProduct(p, rebuild = true) {
                    this.current = p;
                    this.form.loan_product_id = p.id;
                    if (typeof p.application_fee === 'number') {
                        this.applicationFee = p.application_fee;
                    }
                    if (! this.form.requested_amount || this.form.requested_amount < p.min) this.form.requested_amount = p.min;
                    if (! this.form.requested_tenure_months || this.form.requested_tenure_months < p.tmin) this.form.requested_tenure_months = p.tmin;
                    if (this.isAssetBackedProduct(p) && ! this.form.purpose) this.form.purpose = 'asset_financing';
                    if (this.isGroupProduct(p)) {
                        this.initGroupLeader();
                        const tenureOptions = p.tenure_options || [];
                        if (tenureOptions.length) {
                            const currentTenure = Number(this.form.requested_tenure_months);
                            if (! tenureOptions.includes(currentTenure)) {
                                this.form.requested_tenure_months = tenureOptions[0];
                            }
                        }
                        if (! this.group.purpose && this.form.purpose) this.group.purpose = this.form.purpose;
                        this.refreshApplicationFeeQuote();
                    }
                    if (! this.requiresGuarantor()) this.form.guarantor_mode = 'none';
                    else if (this.form.guarantor_mode === 'none') this.form.guarantor_mode = 'previous';
                    this.updateQuote();
                    if (rebuild) this.rebuildSteps();
                },

                estimateEmi(principal, rate, months) {
                    if (principal <= 0 || months <= 0) return 0;
                    if (rate <= 0) return Math.round(principal / months);
                    const pow = Math.pow(1 + rate, months);
                    return Math.round(principal * rate * pow / (pow - 1));
                },

                estimateWeeklyInstallment(principal, rate, months) {
                    if (principal <= 0 || months <= 0) return 0;
                    const periods = Math.max(1, Math.round(months * 4.33));
                    const periodRate = rate / 4;
                    return Math.round((principal / periods) + (principal * periodRate));
                },

                repaymentCadence() {
                    const freq = (this.current?.frequency || 'weekly').toLowerCase();
                    return freq === 'monthly' ? 'monthly' : 'weekly';
                },

                resolveMonthlyRate(product, amount) {
                    if (! product) return 0;
                    const tiers = product.tiers || [];
                    let rate = 0;
                    if (tiers.length) {
                        const tier = tiers.find(t => amount >= t.min && amount <= t.max);
                        rate = tier ? tier.rate : (product.rate || 0);
                    } else {
                        rate = product.rate || 0;
                    }
                    const discount = Number(this.engagementBoosts?.rate_discount_fraction || 0);
                    return Math.max(0, rate - discount);
                },

                updateQuote() {
                    if (! this.current) return;
                    const rate = this.resolveMonthlyRate(this.current, this.form.requested_amount);
                    const months = this.form.requested_tenure_months;
                    const principal = this.form.requested_amount;
                    const cadence = this.repaymentCadence();
                    const monthly = this.estimateEmi(principal, rate, months);
                    const weekly = this.estimateWeeklyInstallment(principal, rate, months);
                    const primary = cadence === 'monthly' ? monthly : weekly;
                    const periods = cadence === 'monthly' ? months : Math.max(1, Math.round(months * 4.33));
                    const interest = Math.max(0, (primary * periods) - principal);
                    this.quote = {
                        monthly,
                        weekly,
                        primary,
                        frequency: cadence,
                        interest,
                        fees: this.applicationFee,
                        total: (primary * periods) + this.applicationFee,
                    };
                    if (this.phase === 'application') {
                        this.rebuildSteps();
                    }
                },

                hasStep(key) {
                    return this.steps.some(s => s.key === key);
                },

                requiresGuarantor() {
                    if (this.isGroupProduct(this.current)) return false;
                    if (! this.current) return false;
                    if (this.current.requires_guarantor) return true;
                    const threshold = Number(this.current.guarantor_required_above || 0);
                    const amount = Number(this.form.requested_amount || 0);

                    return threshold > 0 && amount >= threshold;
                },

                async loadPreviousGuarantors() {
                    if (! this.previousGuarantorsUrl) return;
                    try {
                        const response = await fetch(this.previousGuarantorsUrl, { headers: { Accept: 'application/json' } });
                        const data = await response.json();
                        this.previousGuarantors = data.guarantors || [];
                    } catch (e) {
                        this.previousGuarantors = [];
                    }
                },

                async selectPreviousGuarantor(id) {
                    if (! this.selectPreviousGuarantorUrl || ! id) return;
                    this.guarantorLookup.loading = true;
                    try {
                        const response = await fetch(this.selectPreviousGuarantorUrl, {
                            method: 'POST',
                            headers: {
                                Accept: 'application/json',
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '',
                            },
                            body: JSON.stringify({ customer_guarantor_id: id }),
                        });
                        const data = await response.json();
                        if (! data.ok) {
                            alert(data.message || @js(__('borrower.apply.previous_guarantor.failed')));
                            return;
                        }
                        this.form.guarantor_mode = 'internal';
                        this.form.previous_guarantor_id = id;
                        this.guarantorLookup = { ok: true, ...(data.lookup || {}) };
                        if (data.lookup?.member_no) {
                            this.form.internal_member_no = String(data.lookup.member_no).replace(/^KPF-TZ-/i, '');
                        }
                        if (data.lookup?.name) {
                            this.form.internal_guarantor_name = data.lookup.name;
                        }
                    } finally {
                        this.guarantorLookup.loading = false;
                    }
                },

                gotoKey(key) {
                    const i = this.steps.findIndex(s => s.key === key);
                    if (i >= 0 && i <= this.step) this.step = i;
                },

                isGuarantorLocked() {
                    if (this.form.guarantor_mode === 'internal' || this.form.guarantor_mode === 'previous') {
                        return this.internalGuarantorValidated();
                    }
                    if (this.form.guarantor_mode === 'external') {
                        return !! this.externalGuarantor?.invitation_url;
                    }

                    return false;
                },

                guarantorSummaryText() {
                    if (this.form.guarantor_mode === 'internal') {
                        return this.guarantorLookup.label || this.form.internal_guarantor_name || '—';
                    }
                    if (this.form.guarantor_mode === 'external') {
                        return [this.form.external_first_name, this.form.external_last_name].filter(Boolean).join(' ') || '—';
                    }

                    return '—';
                },

                async changeGuarantor() {
                    if (this.guarantorChanging || ! this.form.loan_product_id) {
                        return;
                    }
                    this.guarantorChanging = true;
                    try {
                        const res = await fetch(this.guarantorExpireUrl, {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'Content-Type': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '',
                            },
                            credentials: 'same-origin',
                            body: JSON.stringify({ loan_product_id: this.form.loan_product_id }),
                        });
                        if (! res.ok) {
                            throw new Error('expire failed');
                        }
                        this.guarantorLookup = { ok: false, label: '', error: '', memberKey: '', phone: '', name: '' };
                        this.externalGuarantor = null;
                        this.guarantorErrors = {};
                        this.form.internal_member_no = '';
                        this.form.internal_guarantor_phone = '';
                        this.form.internal_guarantor_name = '';
                        this.form.external_first_name = '';
                        this.form.external_middle_name = '';
                        this.form.external_last_name = '';
                        this.form.external_phone = '';
                        this.form.external_email = '';
                        this.form.external_relationship = '';
                        this.form.external_region = '';
                        this.form.external_district = '';
                        this.scheduleDraftSave();
                    } catch {
                        alert(this.i18n.alerts.guarantor_lookup_failed);
                    } finally {
                        this.guarantorChanging = false;
                    }
                },

                guarantorStatusLabel() {
                    if (this.form.guarantor_mode === 'internal') {
                        return this.i18n.alerts.guarantorStatus?.internal_validated
                            || @js(__('borrower.apply.guarantor_status.pending_acceptance'));
                    }
                    if (this.form.guarantor_mode === 'external') {
                        if (this.externalGuarantor?.borrower_status_label) {
                            return this.externalGuarantor.borrower_status_label;
                        }
                        const code = this.externalGuarantor?.borrower_status_code || 'invitation_sent';
                        return this.i18n.alerts.guarantorStatus?.[code]
                            || @js(__('borrower.apply.guarantor_status.invitation_sent'));
                    }

                    return '—';
                },

                guarantorStatusCode() {
                    if (this.form.guarantor_mode === 'external') {
                        return this.externalGuarantor?.borrower_status_code
                            || (this.externalGuarantor?.status === 'accepted' ? 'registration_in_progress' : 'invitation_sent');
                    }

                    return 'pending_acceptance';
                },

                guarantorLockedSummaryText() {
                    const code = this.guarantorStatusCode();
                    if (code === 'rejected') {
                        return @js(__('borrower.apply.guarantor_locked_declined'));
                    }

                    return @js(__('borrower.apply.guarantor_locked_summary'));
                },

                guarantorLockedCardClass() {
                    const code = this.guarantorStatusCode();
                    if (code === 'rejected' || code === 'expired') {
                        return 'bg-rose-50 ring-rose-200';
                    }
                    if (code === 'accepted' || code === 'guarantee_pending') {
                        return 'bg-emerald-50 ring-emerald-200';
                    }

                    return 'bg-amber-50 ring-amber-200';
                },

                guarantorLockedCardTextClass() {
                    const code = this.guarantorStatusCode();
                    if (code === 'rejected' || code === 'expired') {
                        return 'text-rose-900';
                    }
                    if (code === 'accepted' || code === 'guarantee_pending') {
                        return 'text-emerald-900';
                    }

                    return 'text-amber-900';
                },

                guarantorLockedCardMutedClass() {
                    const code = this.guarantorStatusCode();
                    if (code === 'rejected' || code === 'expired') {
                        return 'text-rose-700';
                    }
                    if (code === 'accepted' || code === 'guarantee_pending') {
                        return 'text-emerald-700';
                    }

                    return 'text-brand';
                },

                guarantorLockedCardBodyClass() {
                    const code = this.guarantorStatusCode();
                    if (code === 'rejected' || code === 'expired') {
                        return 'text-rose-800';
                    }
                    if (code === 'accepted' || code === 'guarantee_pending') {
                        return 'text-emerald-800';
                    }

                    return 'text-amber-800';
                },

                guarantorStatusBadgeClass() {
                    if (this.form.guarantor_mode === 'internal') {
                        return 'bg-amber-100 text-amber-900 ring-amber-200';
                    }

                    const code = this.guarantorStatusCode();

                    if (code === 'accepted') {
                        return 'bg-emerald-100 text-emerald-900 ring-emerald-200';
                    }
                    if (code === 'rejected' || code === 'expired') {
                        return 'bg-rose-100 text-rose-900 ring-rose-200';
                    }
                    if (code === 'guarantee_pending') {
                        return 'bg-violet-100 text-violet-900 ring-violet-200';
                    }
                    if (code === 'kyc_in_progress' || code === 'registration_in_progress') {
                        return 'bg-amber-100 text-amber-900 ring-amber-200';
                    }

                    return 'bg-sky-100 text-sky-900 ring-sky-200';
                },

                guarantorReviewStatus() {
                    return this.guarantorStatusLabel();
                },

                async loadRepaymentSchedule() {
                    if (! this.repaymentPreviewUrl || ! this.form.loan_product_id) {
                        return;
                    }
                    this.scheduleLoading = true;
                    try {
                        const previewAmount = this.isGroupProduct(this.current)
                            ? (this.group.amount_per_member || this.form.requested_amount)
                            : this.form.requested_amount;
                        const params = new URLSearchParams({
                            loan_product_id: String(this.form.loan_product_id),
                            requested_amount: String(previewAmount),
                            requested_tenure_months: String(this.form.requested_tenure_months),
                        });
                        const res = await fetch(`${this.repaymentPreviewUrl}?${params}`, {
                            headers: { Accept: 'application/json' },
                            credentials: 'same-origin',
                        });
                        const data = await res.json().catch(() => ({}));
                        if (res.ok && data.ok) {
                            this.repaymentSchedule = data.schedule || [];
                            this.scheduleDatesAvailable = !!data.dates_available;
                            if (data.engagement) {
                                if (data.engagement.limit_amount) {
                                    this.qualificationLimit = Number(data.engagement.limit_amount);
                                }
                                if (data.engagement.processing_sla) {
                                    this.processingSla = data.engagement.processing_sla;
                                }
                            }
                            this.reviewSummary = {
                                monthly_rate_pct: data.summary?.monthly_rate_pct ?? 0,
                                application_fee: data.summary?.application_fee ?? this.applicationFee,
                                monthly_installment: data.summary?.monthly_installment ?? this.quote.monthly,
                                installment_amount: data.summary?.installment_amount ?? this.quote.primary ?? this.quote.monthly,
                                repayment_cadence: data.summary?.repayment_cadence ?? this.quote.frequency ?? this.repaymentCadence(),
                            };
                        }
                    } catch {
                        this.repaymentSchedule = [];
                    } finally {
                        this.scheduleLoading = false;
                    }
                },

                refreshReview(formEl) {
                    const form = formEl instanceof HTMLFormElement ? formEl : this.formRoot();
                    const snapshot = this.borrowerSnapshot || {};
                    if (! form) {
                        this.review.personal = snapshot.personal || [this.form.first_name, this.form.last_name].filter(Boolean).join(' · ');
                        this.review.employment = snapshot.employment || '';
                        this.review.residence = snapshot.residence || '';
                    } else {
                        const fd = new FormData(form);
                        const g = (n) => fd.get(n) || '';
                        this.review.personal = snapshot.personal || [g('first_name'), g('last_name'), g('national_id')].filter(Boolean).join(' · ');
                        const activity = this.activityTypeLabels[g('activity_type')] || g('activity_type');
                        const income = this.incomeRangeLabels[g('income_range')] || g('income_range');
                        this.review.employment = snapshot.employment || [activity, income].filter(Boolean).join(' · ');
                        this.review.residence = snapshot.residence || [g('street'), g('ward'), g('district'), g('region')].filter(Boolean).join(', ');
                        this.review.nok = [g('nok_name'), g('nok_relationship'), g('nok_phone')].filter(Boolean).join(' · ');
                        this.review.activity = [activity, income].filter(Boolean).join(' · ');
                    }

                    if (this.form.guarantor_mode === 'internal') {
                        this.review.guarantorType = @js(__('borrower.apply.review_step.internal_type'));
                        this.review.guarantorName = this.guarantorLookup.label || this.form.internal_guarantor_name || this.form.internal_member_no || '—';
                    } else if (this.form.guarantor_mode === 'external') {
                        this.review.guarantorType = @js(__('borrower.apply.review_step.external_type'));
                        this.review.guarantorName = [this.form.external_first_name, this.form.external_last_name].filter(Boolean).join(' ') || '—';
                    } else {
                        this.review.guarantorType = '—';
                        this.review.guarantorName = '—';
                    }
                    this.review.guarantorStatus = this.guarantorReviewStatus();
                    this.review.guarantor = this.review.guarantorName;
                    this.loadRepaymentSchedule();
                },

                formRoot() {
                    const ref = this.$refs?.wizardForm;
                    if (ref instanceof HTMLFormElement) return ref;
                    const byId = document.getElementById('apply-wizard-form');
                    if (byId instanceof HTMLFormElement) return byId;
                    const scoped = this.$el?.querySelector?.('form[data-apply-wizard-form]');
                    if (scoped instanceof HTMLFormElement) return scoped;
                    const nested = this.$el?.querySelector?.('form');
                    if (nested instanceof HTMLFormElement) return nested;
                    return null;
                },

                onExternalRegionChange() {
                    this.form.external_district = '';
                },

                readFormField(name) {
                    const root = this.formRoot();
                    if (! root) {
                        if (Object.prototype.hasOwnProperty.call(this.form, name)) {
                            const fromModel = this.form[name];
                            if (fromModel !== undefined && fromModel !== null) {
                                return String(fromModel).trim();
                            }
                        }
                        return '';
                    }
                    const radios = root.querySelectorAll(`[name="${name}"]`);
                    if (radios.length && radios[0].type === 'radio') {
                        const checked = root.querySelector(`[name="${name}"]:checked`);
                        return (checked?.value || '').toString().trim();
                    }
                    const el = root.querySelector(`[name="${name}"]`);
                    if (el && String(el.value || '').trim() !== '') {
                        return String(el.value).trim();
                    }
                    if (Object.prototype.hasOwnProperty.call(this.form, name)) {
                        const fromModel = this.form[name];
                        if (fromModel !== undefined && fromModel !== null) {
                            return String(fromModel).trim();
                        }
                    }
                    return '';
                },

                syncGuarantorFormFromDom() {
                    const fields = [
                        'internal_member_no', 'internal_guarantor_phone', 'internal_guarantor_name',
                        'external_first_name', 'external_middle_name', 'external_last_name',
                        'external_relationship', 'external_phone', 'external_email',
                        'external_region', 'external_district',
                    ];
                    fields.forEach((name) => {
                        const value = this.readFormField(name);
                        if (value !== '' && Object.prototype.hasOwnProperty.call(this.form, name)) {
                            this.form[name] = value;
                        }
                    });
                    const mode = this.readFormField('guarantor_mode');
                    if (mode) this.form.guarantor_mode = mode;
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

                externalGuarantorFingerprint() {
                    const p = this.externalGuarantorPayload();
                    return JSON.stringify({
                        external_first_name: (p.external_first_name || '').toString().trim(),
                        external_last_name: (p.external_last_name || '').toString().trim(),
                        external_relationship: (p.external_relationship || '').toString().trim(),
                        external_phone: (p.external_phone || '').toString().trim(),
                        external_region: (p.external_region || '').toString().trim(),
                        external_district: (p.external_district || '').toString().trim(),
                    });
                },

                invalidateExternalInvite() {
                    if (! this.externalGuarantor?.invitation_url) {
                        return;
                    }
                    const current = this.externalGuarantorFingerprint();
                    if (this.externalGuarantor._fingerprint && this.externalGuarantor._fingerprint !== current) {
                        this.externalGuarantor = null;
                    }
                },

                externalGuarantorMissingFields() {
                    const required = {
                        external_first_name: @js(__('borrower.profile.fields.first_name')),
                        external_last_name: @js(__('borrower.profile.fields.last_name')),
                        external_relationship: @js(__('borrower.apply.guarantor_fields.relationship')),
                        external_phone: @js(__('borrower.profile.fields.phone')),
                        external_region: @js(__('borrower.profile.fields.region')),
                        external_district: @js(__('borrower.profile.fields.district')),
                    };
                    const p = this.externalGuarantorPayload();
                    const missing = {};
                    Object.entries(required).forEach(([key, label]) => {
                        if (! (p[key] || '').toString().trim()) {
                            missing[key] = label + ' ' + @js(__('borrower.apply.guarantor_fields.is_required'));
                        }
                    });
                    return missing;
                },

                setGuarantorFieldErrors(missingMap) {
                    this.guarantorErrors = { ...missingMap };
                },

                isExternalGuarantorComplete() {
                    return Object.keys(this.externalGuarantorMissingFields()).length === 0;
                },

                scheduleExternalInvitePrep() {
                    clearTimeout(this.externalInviteTimer);
                    this.externalInviteTimer = setTimeout(() => {
                        if (this.form.guarantor_mode !== 'external') {
                            return;
                        }
                        this.invalidateExternalInvite();
                    }, 600);
                },

                internalGuarantorFieldsFilled() {
                    this.syncGuarantorFormFromDom();
                    return !! (
                        this.readFormField('internal_member_no')
                        && this.readFormField('internal_guarantor_phone')
                        && this.readFormField('internal_guarantor_name')
                    );
                },

                async signApplication() {
                    if (this.advancing) {
                        return;
                    }
                    if (! this.declarationAccepted) {
                        alert(this.i18n.alerts.acceptTerms);
                        return;
                    }
                    const form = this.formRoot();
                    const sigData = this.readSignatureFromPad(form);
                    if (! sigData) {
                        alert(this.i18n.alerts.drawSignature);
                        return;
                    }
                    this.advancing = true;
                    try {
                        this.borrowerSignature = {
                            signer_name: this.verifiedLegalName,
                            signature_data: sigData,
                            consent_accepted: true,
                            signed_at: new Date().toISOString(),
                        };
                        this.declarationAccepted = true;
                        await this.persistDraft(true);
                        const submitIndex = this.steps.findIndex(s => s.key === 'submit');
                        if (submitIndex >= 0) {
                            this.step = submitIndex;
                        } else if (this.step < this.steps.length - 1) {
                            this.step++;
                        }
                        this.syncStepKey();
                        await this.persistDraft(true);
                        this.$nextTick(() => this.syncSubmitPayload(form));
                        window.scrollTo({ top: 0, behavior: 'smooth' });
                    } finally {
                        this.advancing = false;
                    }
                },

                async refreshExternalGuarantorStatus() {
                    if (! this.guarantorStatusUrl || ! this.externalGuarantor?.invitation_id) {
                        return;
                    }
                    try {
                        const params = new URLSearchParams({
                            invitation_id: String(this.externalGuarantor.invitation_id),
                        });
                        const res = await fetch(`${this.guarantorStatusUrl}?${params}`, {
                            headers: { Accept: 'application/json' },
                            credentials: 'same-origin',
                        });
                        const data = await res.json().catch(() => ({}));
                        if (res.ok && data.ok && data.share) {
                            this.externalGuarantor = {
                                ...this.externalGuarantor,
                                ...data.share,
                            };
                        }
                    } catch {
                        // Non-blocking refresh.
                    }
                },

                async generateExternalInvite() {
                    this.syncGuarantorFormFromDom();
                    const missing = this.externalGuarantorMissingFields();
                    if (Object.keys(missing).length) {
                        this.setGuarantorFieldErrors(missing);
                        window.scrollTo({ top: 0, behavior: 'smooth' });
                        return;
                    }
                    this.guarantorErrors = {};
                    await this.prepareExternalGuarantorInvite();
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
                        this.externalGuarantor = {
                            ...data.share,
                            _fingerprint: this.externalGuarantorFingerprint(),
                        };
                        this.scheduleDraftSave();
                        return true;
                    } catch {
                        alert(this.i18n.alerts.guarantor_invite_failed);
                        return false;
                    } finally {
                        this.guarantorInvitePreparing = false;
                    }
                },

                async validateStep() {
                    if (this.stepKey === 'quote' && this.hasStep('quote')) {
                        this.syncQuoteFormFromDom();
                        if (! this.form.purpose) {
                            alert(this.i18n.alerts.selectPurpose);
                            return false;
                        }
                    }
                    if (this.stepKey === 'group_setup' && this.hasStep('group_setup')) {
                        if (! (this.group.name || '').trim()) {
                            alert(@js(__('borrower.apply.group.name_required_step')));
                            return false;
                        }
                        const count = this.groupTargetCount();
                        if (! count || count < this.groupLimits.min || count > this.groupLimits.max) {
                            alert(@js(__('borrower.apply.group.member_count_range')));
                            return false;
                        }
                        if (! this.group.amount_per_member || Number(this.group.amount_per_member) < 1000) {
                            alert(@js(__('borrower.apply.group.amount_required')));
                            return false;
                        }
                        if (! this.group.purpose) {
                            alert(@js(__('borrower.apply.group.purpose_required')));
                            return false;
                        }
                        this.syncGroupAmounts();
                        this.form.purpose = this.group.purpose;
                    }
                    if (this.stepKey === 'group_members' && this.hasStep('group_members')) {
                        await this.refreshGroupMemberStatuses();
                        const target = this.groupTargetCount();
                        if (this.group.members.length !== target) {
                            alert(@js(__('borrower.apply.group.members_required')));
                            return false;
                        }
                        const invalidAmount = this.group.members.some(m => ! m.requested_amount || Number(m.requested_amount) < 1000);
                        if (invalidAmount) {
                            alert(@js(__('borrower.apply.group.amount_required')));
                            return false;
                        }
                        const total = this.group.members.reduce((sum, m) => sum + Number(m.requested_amount || 0), 0);
                        if (this.current && (total < this.current.min || total > this.current.max)) {
                            alert(`Total group amount must be between ${this.formatTzs(this.current.min)} and ${this.formatTzs(this.current.max)}.`);
                            return false;
                        }
                        this.syncGroupAmounts();
                    }
                    if (this.stepKey === 'asset_details' && this.hasStep('asset_details')) {
                        if (! this.customerAssets.length) {
                            alert(@js(__('borrower.apply.asset_details.no_assets_title')));
                            return false;
                        }
                        if (! this.form.customer_asset_id) {
                            alert(@js(__('borrower.apply.asset_details.asset_required')));
                            return false;
                        }
                        if (! this.form.requested_amount || this.form.requested_amount < (this.current?.min || 1000)) {
                            alert(@js(__('borrower.apply.asset_details.amount_required')));
                            return false;
                        }
                        if (! this.form.requested_tenure_months) {
                            alert(@js(__('borrower.apply.asset_details.tenure_required')));
                            return false;
                        }
                        if (! this.form.purpose) {
                            this.form.purpose = 'asset_financing';
                        }
                    }
                    if (this.stepKey === 'guarantor' && this.hasStep('guarantor')) {
                        this.syncGuarantorFormFromDom();
                        if (! this.form.guarantor_mode || this.form.guarantor_mode === 'none') {
                            alert(this.i18n.alerts.selectGuarantor);
                            return false;
                        }
                        if (this.form.guarantor_mode === 'internal' || this.form.guarantor_mode === 'previous') {
                            if (! this.internalGuarantorValidated()) {
                                alert(this.i18n.alerts.guarantor_validate_first);
                                return false;
                            }
                            return true;
                        }
                        if (this.form.guarantor_mode === 'external') {
                            if (! this.externalGuarantor?.invitation_url) {
                                const missing = this.externalGuarantorMissingFields();
                                if (Object.keys(missing).length) {
                                    this.setGuarantorFieldErrors(missing);
                                    window.scrollTo({ top: 0, behavior: 'smooth' });
                                    return false;
                                }
                                alert(this.i18n.alerts.guarantor_external_invite_required || this.i18n.alerts.guarantor_validate_first);
                                return false;
                            }
                            this.guarantorErrors = {};
                            return true;
                        }
                    }
                    if (this.stepKey === 'application_fee') {
                        await this.refreshApplicationFeeQuote();
                        if (this.effectiveFeeAmount() > 0) {
                            const st = this.applicationFeeState?.status || '';
                            if (! ['paid', 'waived', 'pending'].includes(st)) {
                                alert(@js(__('borrower.apply.application_fee.required_before_continue')));
                                return false;
                            }
                        } else if (! this.applicationFeePaid) {
                            await this.autoWaiveApplicationFeeIfNeeded();
                        }
                    }
                    if (! this.feeGateSatisfied() && this.feeGateRequiredForStep(this.stepKey)) {
                        this.enforceStepRequirements();
                        alert(@js(__('borrower.apply.application_fee.required_before_continue')));
                        return false;
                    }
                    const nextKey = this.steps[this.step + 1]?.key;
                    if (nextKey && this.feeGateRequiredForStep(nextKey) && ! this.feeGateSatisfied()) {
                        alert(@js(__('borrower.apply.application_fee.required_before_continue')));
                        return false;
                    }
                    return true;
                },

                internalGuarantorValidated() {
                    this.syncGuarantorFormFromDom();
                    const member = this.readFormField('internal_member_no');
                    const phone = this.readFormField('internal_guarantor_phone');
                    const name = this.readFormField('internal_guarantor_name');

                    return this.guarantorLookup.ok
                        && this.guarantorLookup.memberKey === member
                        && this.guarantorLookup.phone === phone
                        && this.guarantorLookup.name === name;
                },

                async validateInternalGuarantor() {
                    this.syncGuarantorFormFromDom();
                    const member = this.readFormField('internal_member_no');
                    const phone = this.readFormField('internal_guarantor_phone');
                    const name = this.readFormField('internal_guarantor_name');
                    const errors = {};
                    if (! member) {
                        errors.internal_member_no = this.i18n.alerts.guarantor_membership;
                    }
                    if (! phone) {
                        errors.internal_guarantor_phone = this.i18n.alerts.guarantor_phone;
                    }
                    if (! name) {
                        errors.internal_guarantor_name = this.i18n.alerts.guarantor_name;
                    }
                    if (Object.keys(errors).length) {
                        this.setGuarantorFieldErrors(errors);
                        window.scrollTo({ top: 0, behavior: 'smooth' });
                        return;
                    }
                    this.guarantorErrors = {};
                    this.guarantorValidating = true;
                    this.guarantorLookup = { ok: false, label: '', error: '', memberKey: member, phone, name };
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
                            body: JSON.stringify({ membership_no: member, phone, name }),
                        });
                        const data = await res.json().catch(() => ({}));
                        if (! res.ok || ! data.ok) {
                            this.guarantorLookup.error = data.message || this.i18n.alerts.guarantor_lookup_failed;
                            return;
                        }
                        this.guarantorLookup = {
                            ok: true,
                            label: data.label || data.name,
                            error: '',
                            memberKey: member,
                            phone,
                            name,
                        };
                    } catch {
                        this.guarantorLookup.error = this.i18n.alerts.guarantor_lookup_failed;
                    } finally {
                        this.guarantorValidating = false;
                    }
                },

                async next() {
                    if (this.advancing || this.resumeLoading) return;
                    if (this.guarantorInvitePreparing && this.stepKey === 'guarantor') return;
                    if (! this.steps.length) {
                        this.rebuildSteps();
                    }
                    if (! this.steps.length) {
                        alert(this.i18n.alerts.loadProduct);
                        return;
                    }
                    this.advancing = true;
                    try {
                        this.syncQuoteFormFromDom();
                        if (! await this.validateStep()) return;

                        await this.persistDraft(true);
                        if (this.step >= this.steps.length - 1) {
                            return;
                        }
                        this.step++;
                        this.syncStepKey();
                        this.enforceStepRequirements();
                        if (this.stepKey === 'application_fee') {
                            this.enterApplicationFeeStep();
                        }
                        if (this.stepKey === 'review') {
                            this.refreshReview(this.formRoot());
                        }
                        if (this.stepKey === 'submit' && ! this.borrowerSignature?.signature_data) {
                            const signatureIndex = this.steps.findIndex(s => s.key === 'signature');
                            if (signatureIndex >= 0) {
                                this.step = signatureIndex;
                                this.syncStepKey();
                            }
                        }
                        window.scrollTo({ top: 0, behavior: 'smooth' });
                    } finally {
                        this.advancing = false;
                    }
                },

                prev() {
                    if (this.step > 0) {
                        this.step--;
                        this.syncStepKey();
                        if (this.stepKey === 'signature') {
                            this.$nextTick(() => this.restoreSignaturePad());
                        }
                        window.scrollTo({ top: 0, behavior: 'smooth' });
                    }
                },

                goto(i) {
                    if (i <= this.step) {
                        this.step = i;
                        this.syncStepKey();
                        if (this.stepKey === 'signature') {
                            this.$nextTick(() => this.restoreSignaturePad());
                        }
                        if (this.stepKey === 'submit') {
                            this.$nextTick(() => this.syncSubmitPayload(this.formRoot()));
                        }
                    }
                },

                restoreSignaturePad() {
                    const sig = this.borrowerSignature?.signature_data;
                    if (! sig) return;
                    const form = this.formRoot();
                    const pad = form?.querySelector('[data-signature-pad]');
                    const alpineData = pad?._x_dataStack?.[0];
                    alpineData?.loadFromDataUrl?.(sig);
                },

                readSignatureFromPad(form) {
                    const pad = form?.querySelector('[data-signature-pad]');
                    const alpineData = pad?._x_dataStack?.[0];
                    if (alpineData?.dataUrl) {
                        return alpineData.dataUrl;
                    }
                    return form?.querySelector('[data-submit-signature]')?.value || '';
                },

                syncSubmitPayload(form) {
                    if (! form) return;
                    const set = (selector, value) => {
                        const el = form.querySelector(selector);
                        if (el != null && value !== undefined && value !== null && value !== '') {
                            el.value = String(value);
                        }
                    };
                    const sigData = this.borrowerSignature?.signature_data || this.readSignatureFromPad(form) || '';
                    const signerName = (this.borrowerSignature?.signer_name || this.verifiedLegalName || '').trim();
                    set('[data-submit-signature]', sigData);
                    set('[data-submit-signer]', signerName);
                    set('[data-submit-product]', this.form.loan_product_id);
                    set('[data-submit-amount]', this.form.requested_amount);
                    set('[data-submit-tenure]', this.form.requested_tenure_months);
                    set('[data-submit-purpose]', this.form.purpose);
                    set('[data-submit-guarantor-mode]', this.form.guarantor_mode);
                    [
                        'external_first_name', 'external_middle_name', 'external_last_name',
                        'external_phone', 'external_email', 'external_relationship',
                        'external_region', 'external_district', 'external_invitation_id',
                        'internal_member_no', 'internal_guarantor_phone', 'internal_guarantor_name',
                    ].forEach((key) => {
                        if (this.form[key] != null && this.form[key] !== '') {
                            set(`[name="${key}"]`, this.form[key]);
                        }
                    });
                },

                submitApplication() {
                    const form = this.formRoot();
                    if (! form) return;
                    this.onSubmit({ target: form, preventDefault() {} });
                },

                onSubmit(e) {
                    e.preventDefault();
                    if (this.stepKey !== 'submit') {
                        return;
                    }
                    if (! this.canApply) {
                        const url = @js($applyRequirements['first_action_url'] ?? null);
                        if (url && confirm(@js(__('borrower.apply.kyc_incomplete_submit')))) {
                            window.location.href = url;
                        } else {
                            alert(@js(__('borrower.apply.kyc_incomplete_submit')));
                        }
                        return;
                    }
                    if (this.isGroupProduct(this.current) && ! this.groupProgress().can_submit) {
                        alert(@js(__('borrower.apply.group.members_not_verified')));
                        return;
                    }
                    const sigData = this.borrowerSignature?.signature_data || this.readSignatureFromPad(e.target);
                    const signerName = (this.borrowerSignature?.signer_name || this.verifiedLegalName || e.target.elements['signer_name']?.value || '').trim();
                    if (! signerName) {
                        alert(this.i18n.alerts.drawSignature);
                        return;
                    }
                    if (! sigData) {
                        alert(this.i18n.alerts.drawSignature);
                        return;
                    }
                    this.syncSubmitPayload(e.target);
                    this.submitting = true;
                    window.confirmForm(e.target, {
                        title: this.i18n.alerts.submitTitle,
                        message: this.i18n.alerts.submitMessage,
                        confirmLabel: @js(__('borrower.apply.submit')),
                        confirmClass: 'bg-gray-900 hover:bg-gray-800 text-white',
                        onCancel: () => { this.submitting = false; },
                    });
                },

                formatTzs(v, decimals = 0) {
                    return (window.formatMoney || ((x) => 'TZS ' + x))(v, { currency: 'TZS', decimals });
                },
            };
        }

        document.addEventListener('alpine:init', () => {
            Alpine.data('applyWizard', (config) => applyWizard(config));
        });
    </script>
</x-site.borrower-layout>
