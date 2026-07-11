<x-site.borrower-layout :title="brand_title(__('borrower.apply.title'))" active="loans" content-width="narrow">
    <div>

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
            <x-site.kyc-gate-banner :apply-requirements="$applyRequirements" class="mb-6" />
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
            $incomeRangeLabels = collect(config('income_ranges', []))
                ->mapWithKeys(fn ($v, $k) => [$k => income_range_label($k) ?? ($v['label'] ?? $k)])
                ->all();
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
                  loyaltyRateDiscount: {{ (float) ($loyaltyRateDiscount ?? 0) }},
                  activeRewards: @js(($activeRewards ?? collect())->map(fn ($r) => [
                      'label' => $r->label,
                      'benefit_type' => $r->benefit_type,
                      'fee_type' => $r->fee_type,
                  ])->values()->all()),
                  pointsBalance: {{ (int) ($pointsBalance ?? 0) }},
                  profileSections: @js($profileSections),
                  incomeVerification: @js($incomeVerification),
                  productQuestions: @js($productQuestions),
                  purposeLabels: @js(loan_purpose_options()),
                  readinessUrl: @js($readinessUrl),
                  loanProductsUrl: @js(route('site.borrower.loan-products')),
                  paymentPhone: @js(old('payment_phone', $customer->phone ?? '')),
                  valuationFeePaymentReference: @js($valuationFeePaymentRef ?? null),
                  feeQuoteData: @js($feeQuote ?? null),
                  feePaymentReference: @js($applicationFeePaymentRef ?? null),
                  groupProgressLabels: @js(app(\App\Services\GroupMemberProgressService::class)->statusLabels()),
                  firstActionUrl: @js($applyRequirements['first_action_url'] ?? null),
                  supplementMode: @js((bool) ($supplementMode ?? false)),
                  supplementApplicationId: @js(($supplementApplication ?? null)?->id),
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
                          'application_fee' => __('borrower.apply.steps.application_fee'),
                          'guarantor' => __('borrower.apply.steps.guarantor'),
                          'review' => __('borrower.apply.steps.review'),
                          'signature' => __('borrower.apply.steps.signature'),
                          'submit' => __('borrower.apply.steps.submit'),
                      ],
                      'valuationFee' => [
                          'failed' => __('borrower.apply.valuation_fee.failed'),
                          'paid' => __('borrower.apply.valuation_fee.paid'),
                      ],
                      'assetDetails' => [
                          'typeRequired' => __('borrower.apply.asset_details.type_required'),
                          'uploadFailed' => __('borrower.apply.asset_details.upload_failed'),
                          'noAssetsTitle' => __('borrower.apply.asset_details.no_assets_title'),
                          'assetRequired' => __('borrower.apply.asset_details.asset_required'),
                          'amountRequired' => __('borrower.apply.asset_details.amount_required'),
                          'tenureRequired' => __('borrower.apply.asset_details.tenure_required'),
                      ],
                      'applicationFee' => [
                          'failed' => __('borrower.apply.application_fee.failed'),
                          'paid' => __('borrower.apply.application_fee.paid'),
                          'requiredBeforeContinue' => __('borrower.apply.application_fee.required_before_continue'),
                      ],
                      'group' => [
                          'lookupNotFound' => __('borrower.apply.group.lookup_not_found'),
                          'nameRequiredStep' => __('borrower.apply.group.name_required_step'),
                          'memberCountRange' => __('borrower.apply.group.member_count_range'),
                          'amountRequired' => __('borrower.apply.group.amount_required'),
                          'purposeRequired' => __('borrower.apply.group.purpose_required'),
                          'membersRequired' => __('borrower.apply.group.members_required'),
                          'lookupInvalidPhone' => __('borrower.apply.group.lookup_invalid_phone'),
                          'membersNotVerified' => __('borrower.apply.group.members_not_verified'),
                      ],
                      'groupMembers' => [
                          'duplicate' => __('borrower.apply.group_members.duplicate'),
                      ],
                      'previousGuarantor' => [
                          'failed' => __('borrower.apply.previous_guarantor.failed'),
                      ],
                      'guarantorLocked' => [
                          'declined' => __('borrower.apply.guarantor_locked_declined'),
                          'summary' => __('borrower.apply.guarantor_locked_summary'),
                      ],
                      'reviewStep' => [
                          'internalType' => __('borrower.apply.review_step.internal_type'),
                          'externalType' => __('borrower.apply.review_step.external_type'),
                      ],
                      'guarantorFields' => [
                          'isRequired' => __('borrower.apply.guarantor_fields.is_required'),
                          'labels' => [
                              'external_first_name' => __('borrower.profile.fields.first_name'),
                              'external_last_name' => __('borrower.profile.fields.last_name'),
                              'external_relationship' => __('borrower.apply.guarantor_fields.relationship'),
                              'external_phone' => __('borrower.profile.fields.phone'),
                              'external_region' => __('borrower.profile.fields.region'),
                              'external_district' => __('borrower.profile.fields.district'),
                          ],
                      ],
                      'kycIncompleteSubmit' => __('borrower.apply.kyc_incomplete_submit'),
                      'submitConfirmLabel' => __('borrower.apply.submit'),
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

            <x-site.premium-loan-wizard-header />

            <div x-show="draftSavedAt" x-cloak class="mb-4 rounded-lg bg-gray-50 ring-1 ring-gray-200 px-3 py-2 text-xs text-gray-600">
                {{ __('borrower.apply.draft.autosaved') }}
            </div>

            {{-- Phase: Product details + readiness --}}
            <div x-show="phase === 'details'" class="glass-card overflow-hidden ring-1 ring-brand/10">
                <x-site.loan-product-details />
            </div>

            {{-- Phase: Application wizard --}}
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
                    <input type="hidden" name="guarantor_mode" data-submit-guarantor-mode value="">
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

            <x-site.wizard-step-nav />

            <div class="glass-card">

                @include('site.apply._quote-step')

                @include('site.apply._group-steps')

                @include('site.apply._asset-tenure-step')

                @include('site.apply._asset-details-step')

                @include('site.apply._guarantor-step')

                @php $membershipCfg = \App\Services\MembershipService::config(); @endphp
                <x-site.application-fee-step
                    :fee-quote="$feeQuote ?? null"
                    :bank-accounts="$bankAccounts ?? []"
                    :currency="$membershipCfg['currency'] ?? 'TZS'"
                    :payment-reference="$applicationFeePaymentRef ?? null"
                    :referral-wallet="$referralWallet ?? null"
                    :referral-settings="$referralSettings ?? []"
                    :payment-gateway-dummy="$paymentGatewayDummy ?? payment_gateway_is_dummy()"
                    :apply-requirements="$applyRequirements ?? null"
                    :points-balance="$pointsBalance ?? 0"
                />

                @include('site.apply._product-questions-step')

                @include('site.apply._review-step')

                @include('site.apply._signature-step')

                @include('site.apply._submit-step')

                <x-site.wizard-footer />
            </div>
                </form>
            </div>

            {{-- Incomplete profile gate modal (client UX; server still enforces) --}}
            <div x-show="showProfileGateModal"
                 x-cloak
                 class="fixed inset-0 z-[90] flex items-center justify-center p-4"
                 role="dialog"
                 aria-modal="true"
                 @keydown.escape.window="showProfileGateModal = false">
                <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="showProfileGateModal = false"></div>
                <div class="relative w-full max-w-md glass-card ring-1 ring-brand/15 overflow-hidden shadow-xl">
                    <div class="bg-gradient-to-br from-brand via-brand to-brand-light px-6 py-5 text-white">
                        <p class="text-[10px] uppercase tracking-widest text-white/70 font-semibold">{{ __('borrower.apply.steps.submit') }}</p>
                        <h3 class="text-lg font-bold mt-1">{{ __('borrower.apply.kyc_incomplete_submit') }}</h3>
                    </div>
                    <div class="px-6 py-5">
                        <p class="text-sm text-gray-600">{{ __('borrower.apply.kyc_incomplete_submit_hint') }}</p>
                        <div class="mt-5 flex flex-col sm:flex-row gap-2">
                            <a :href="profileGateActionUrl() || profileUrl || '#'"
                               class="inline-flex justify-center bg-brand-gold hover:bg-yellow-400 text-brand font-bold px-5 py-2.5 rounded-xl text-sm">
                                {{ __('borrower.loan_profile.complete_profile') }}
                            </a>
                            <button type="button"
                                    @click="showProfileGateModal = false"
                                    class="inline-flex justify-center bg-white ring-1 ring-gray-200 hover:bg-gray-50 text-gray-700 font-semibold px-5 py-2.5 rounded-xl text-sm">
                                {{ __('borrower.apply.submit_step.gate_dismiss') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
        @vite('resources/js/apply-wizard.js')
    @endpush
</x-site.borrower-layout>
