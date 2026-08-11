<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Concerns\AuditsActions;
use App\Http\Controllers\Controller;
use App\Models\ApplicationSignature;
use App\Models\AssetReservation;
use App\Models\ChargesFee;
use App\Models\Customer;
use App\Models\CustomerDocument;
use App\Models\DocumentType;
use App\Models\GuarantorInvitation;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Rules\MinimumAge;
use App\Services\AffiliateService;
use App\Services\ApplicationRequirementsService;
use App\Services\AssetReservationService;
use App\Services\FaceVerificationService;
use App\Services\GuarantorInvitationService;
use App\Services\KycFreshnessService;
use App\Services\ApplicationFeePaymentService;
use App\Services\ApplicationTrackingShareService;
use App\Services\AssetBackedApplyService;
use App\Services\CrbCreditCheckService;
use App\Services\DisplayedRateService;
use App\Services\GroupApplyService;
use App\Services\GroupApplicationStatusService;
use App\Services\GroupMemberInvitationService;
use App\Services\GroupMemberProgressService;
use App\Services\GroupLendingService;
use App\Services\GroupScoringService;
use App\Services\LoanApplicationDraftService;
use App\Services\LoanPolicyService;
use App\Services\LoanProductReadinessService;
use App\Services\ReferenceNumberService;
use App\Services\ReferralService;
use App\Services\RepaymentScheduleGenerator;
use App\Services\SmartLoanApplicationWizardService;
use App\Services\ValuationFeePaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ApplyController extends Controller
{
    use AuditsActions;

    public function show(
        Request $request,
        FaceVerificationService $faces,
        KycFreshnessService $freshness,
        ApplicationRequirementsService $requirements,
        SmartLoanApplicationWizardService $wizard,
        LoanApplicationDraftService $drafts,
    ): View|RedirectResponse {
        $customer = Auth::user()->customer ?? Customer::where('user_id', Auth::id())->first();

        if (! $customer) {
            return redirect()->route('site.borrower.dashboard')->with('error', 'Complete your profile before applying.');
        }

        $eligibility = $requirements->checklist($customer);
        $profileSections = $wizard->profileSections($customer);

        $products = borrower_catalogue_products();
        $preselect = $request->query('product');
        $preselectedProduct = null;

        if ($request->filled('from_application') && $customer) {
            $sourceApplication = LoanApplication::query()
                ->where('customer_id', $customer->id)
                ->find($request->query('from_application'));

            if ($sourceApplication && app(\App\Services\ApplicationOfferService::class)->pendingAssetConversion($sourceApplication)) {
                return redirect()->route('site.borrower.application.asset-conversion', $sourceApplication);
            }
        }

        $supplementApplication = null;
        $supplementMode = false;
        if ($request->boolean('guarantor_supplement') && $request->filled('application') && $customer) {
            $supplementApplication = LoanApplication::query()
                ->where('customer_id', $customer->id)
                ->find($request->query('application'));

            if ($supplementApplication && app(\App\Services\GuarantorSupplementService::class)->hasOpenRequest($supplementApplication)) {
                $supplementMode = true;
                $preselect = $supplementApplication->loan_product_id;
                $request->merge(['resume' => 1, 'step_key' => 'guarantor']);
            } else {
                // Never fall through to the full apply/fee wizard for a stale supplement link.
                if ($supplementApplication) {
                    return redirect()
                        ->route('site.borrower.application', $supplementApplication)
                        ->with('status', __('borrower.guarantor_supplement.submitted'));
                }

                $supplementApplication = null;
            }
        }

        if ($preselect) {
            $preselectedProduct = LoanProduct::query()
                ->where(function ($query) use ($preselect) {
                    $query->where('id', $preselect)
                          ->orWhere('code', $preselect);
                })
                ->when(! $request->boolean('resume') && ! $request->boolean('guarantor_supplement'), function ($query) {
                    $query->where('is_active', true);
                })
                ->orderByDesc('is_active')
                ->first();

            // Non-resume still requires an active product.
            if ($preselectedProduct && ! $preselectedProduct->is_active
                && ! $request->boolean('resume') && ! $request->boolean('guarantor_supplement')) {
                $preselectedProduct = null;
            }

            $preselect = $preselectedProduct?->id;
        }

        if ($preselectedProduct && ! $request->boolean('resume') && ! $supplementMode) {
            $policy = app(\App\Services\LoanPolicyService::class);
            $blockingApp = $policy->blockingApplicationForProduct($customer, $preselectedProduct);
            if ($blockingApp) {
                return redirect()
                    ->route('site.borrower.loans', ['tab' => 'applications'])
                    ->with('same_product_block', [
                        'kind' => 'application',
                        'product' => $preselectedProduct->localizedName() ?: $preselectedProduct->name,
                        'application_id' => $blockingApp->id,
                        'application_number' => $blockingApp->application_number,
                        'status' => $blockingApp->status,
                        'message' => $policy->canSubmitApplication($customer, $preselectedProduct),
                    ]);
            }

            $blockingDraft = $drafts->find($customer, (int) $preselectedProduct->id);
            if ($blockingDraft && in_array($blockingDraft->phase, ['details', 'application'], true)) {
                $resumeTarget = $drafts->resumeTarget($customer, $blockingDraft);

                return redirect()
                    ->route('site.borrower.loans', ['tab' => 'applications'])
                    ->with('same_product_block', [
                        'kind' => 'draft',
                        'product' => $preselectedProduct->localizedName() ?: $preselectedProduct->name,
                        'draft_id' => $blockingDraft->id,
                        'application_number' => $blockingDraft->draft_reference,
                        'continue_url' => $drafts->wizardApplyUrl($blockingDraft, $resumeTarget),
                        'message' => __('borrower.policy.max_active_applications', [
                            'product' => $preselectedProduct->localizedName() ?: $preselectedProduct->name,
                            'max' => 1,
                        ]),
                    ]);
            }
        }

        $selectedProduct = $preselect ? $products->firstWhere('id', (int) $preselect) : null;
        if (! $selectedProduct && $preselectedProduct) {
            $selectedProduct = $preselectedProduct;
            // Keep inactive draft products in the wizard catalogue so resume does not bounce.
            if (! $products->contains('id', $preselectedProduct->id)) {
                $products = $products->push($preselectedProduct)->values();
            }
        }
        $reservation = null;
        $assetApplication = null;
        if ($request->filled('reservation') && $customer) {
            $reservation = AssetReservation::query()
                ->where('customer_id', $customer->id)
                ->with('asset')
                ->find($request->query('reservation'));
        }
        // Marketplace products normally need a reservation, but resume into an existing draft
        // must still open the wizard (Edit Quote / Continue) without bouncing to the marketplace.
        $resumingMarketplaceDraft = $request->boolean('resume')
            && $customer
            && $preselectedProduct
            && $drafts->find($customer, (int) $preselectedProduct->id);

        if (! $supplementMode && $preselectedProduct && is_marketplace_loan_product($preselectedProduct->code)
            && ! $reservation && ! $resumingMarketplaceDraft) {
            return redirect()
                ->route('site.borrower.marketplace')
                ->with('status', __('borrower.marketplace.subtitle'));
        }

        // Marketplace AL: enter the standard loan wizard after reservation starts.
        // Deposit is paid only after approval — do not require deposit_paid here.
        if ($reservation && in_array((string) $reservation->status, ['cancelled', 'released'], true)) {
            $assetKey = $reservation->asset?->slug ?: $reservation->marketplace_asset_id;

            return redirect()
                ->route('site.borrower.marketplace.show', $assetKey)
                ->with('warning', __('borrower.marketplace.reservation_closed'));
        }

        if ($reservation?->asset) {
            $assetLoanProduct = LoanProduct::where('is_active', true)
                ->where('code', config('asset_marketplace.asset_loan_product_code', 'AL'))
                ->first();

            if (! $assetLoanProduct) {
                $assetKey = $reservation->asset->slug ?: (string) $reservation->marketplace_asset_id;

                return redirect()
                    ->route('site.borrower.marketplace.reserve', $assetKey)
                    ->with('error', __('borrower.marketplace.loan_product_unavailable'));
            }

            $selectedProduct = $assetLoanProduct;
            $preselect = $assetLoanProduct->id;

            $asset = $reservation->asset;
            $deposit = (float) ($asset->customer_deposit ?: $asset->computeCustomerDeposit());
            $assetValue = (float) ($asset->asset_value ?: max($deposit * 1.4, $deposit));
            $remainingLoan = max(0, round($assetValue - $deposit, 2));
            $tenure = effective_marketplace_asset_max_tenure($asset);
            $photoUrls = marketplace_photo_urls($asset->photos ?? []);

            $assetApplication = [
                'asset_title'        => $asset->title,
                'supplier'           => $asset->supplier_name,
                'asset_value'        => $assetValue,
                'deposit'            => $deposit,
                'remaining_loan'     => $remainingLoan,
                'weekly_installment' => (float) $asset->weekly_installment,
                'max_tenure_months'  => $tenure,
                'min_tenure_months'  => 1,
                'purpose'            => 'asset_financing',
                'photo_url'          => $photoUrls[0] ?? null,
                'photos'             => $photoUrls,
                'category'           => $asset->category,
            ];
        }

        $stepPlan = collect($supplementMode
                ? $wizard->guarantorSupplementStepPlan()
                : $wizard->borrowerStepPlan($customer, $selectedProduct))
            ->reject(fn (array $step) => $step['key'] === 'product')
            ->values()
            ->all();
        $incomeVerification = $wizard->incomeVerification($customer);
        $applicationFee = quoted_origination_fee($customer, $selectedProduct);
        $productQuestions = config('loan_product_questions', []);
        $readinessUrl = route('site.borrower.apply.product-readiness', ['product' => '__ID__']);

        $productIdForDraft = $preselect
            ?: ($request->filled('product') && ctype_digit((string) $request->query('product'))
                ? (int) $request->query('product')
                : null);
        $savedDraft = $productIdForDraft
            ? $drafts->payloadForWizard($customer, $productIdForDraft)
            : $drafts->payloadForWizard($customer);

        if ($supplementMode && $supplementApplication) {
            $feeStatus = (string) ($supplementApplication->application_fee_status ?? '');
            $feePaid = in_array($feeStatus, ['paid', 'waived'], true);
            // Always mark fee satisfied with a stable reference so the client cannot
            // clear waived state and bounce the borrower back to payment.
            $savedDraft = [
                'loan_product_id' => $supplementApplication->loan_product_id,
                'phase' => 'application',
                'step_key' => 'guarantor',
                'form' => [
                    'loan_product_id' => $supplementApplication->loan_product_id,
                    'requested_amount' => (float) $supplementApplication->requested_amount,
                    'requested_tenure_months' => (int) $supplementApplication->requested_tenure_months,
                    'purpose' => $supplementApplication->purpose ?? 'business',
                ],
                'resume_target' => [
                    'phase' => 'application',
                    'step_key' => 'guarantor',
                ],
                'supplement_mode' => true,
                'supplement_application_id' => $supplementApplication->id,
                'application_fee' => [
                    'status' => $feePaid ? $feeStatus : 'waived',
                    'reference' => $supplementApplication->application_fee_reference
                        ?: ('supplement-fee:'.$supplementApplication->id),
                    'amount' => (float) ($supplementApplication->application_fee_amount ?? 0),
                    'paid_at' => optional($supplementApplication->application_fee_paid_at)?->toIso8601String()
                        ?: now()->toIso8601String(),
                ],
            ];
        }

        $applyReturnUrl = route('site.borrower.apply', array_filter([
            'product'  => $request->filled('product') ? (int) $request->query('product') : ($selectedProduct?->id ?? null),
            'resume'   => 1,
            'step_key' => 'submit',
        ]));
        $applyRequirements = $requirements->checklistForApply($customer, $applyReturnUrl);

        $isResume = $request->boolean('resume') || $supplementMode;

        if ($isResume && ! $savedDraft && $request->filled('product') && ! $supplementMode) {
            $draft = $drafts->find($customer, (int) $request->query('product'));
            if ($draft && in_array($draft->phase, ['details', 'application'], true)) {
                $savedDraft = $drafts->payloadForWizard($customer, (int) $request->query('product'));
            }
        }

        if ($isResume && ! $savedDraft && ! $supplementMode) {
            return redirect()
                ->route('site.borrower.loans', ['tab' => 'applications'])
                ->with('error', __('borrower.applications_list.resume_not_found'));
        }

        $hasWizardContext = $preselect
            || $reservation
            || $isResume
            || $supplementMode
            || ($savedDraft && ! empty($savedDraft['loan_product_id']));

        if (! $hasWizardContext) {
            return redirect()->route('site.borrower.loan-products');
        }

        if ($isResume && $savedDraft) {
            $target = $savedDraft['resume_target'] ?? [];
            if ($request->filled('phase')) {
                $target['phase'] = (string) $request->query('phase');
            }
            if ($request->filled('step_key')) {
                $target['step_key'] = (string) $request->query('step_key');
            }
            if ($request->filled('step')) {
                $target['step'] = (int) $request->query('step');
                $savedDraft['step'] = (int) $request->query('step');
            }
            $savedDraft['resume_target'] = $target;
            if (! empty($target['step_key'])) {
                $savedDraft['step_key'] = $target['step_key'];
            }
        }

        try {
            if ($selectedProduct) {
                $syncedFee = app(ApplicationFeePaymentService::class)
                    ->syncDraftFromVerifiedPayment($customer, $selectedProduct);
                if ($syncedFee) {
                    $savedDraft = $drafts->payloadForWizard($customer, $selectedProduct->id) ?? $savedDraft;
                    if (is_array($savedDraft)) {
                        $savedDraft['application_fee'] = $syncedFee;
                        if (product_includes_valuation_fee($selectedProduct)) {
                            $savedDraft['valuation_fee'] = $syncedFee;
                        }
                    }
                }
            }

            $feeQuote = $selectedProduct
                ? app(ApplicationFeePaymentService::class)->quote(
                    $customer,
                    $selectedProduct,
                    (bool) old('use_wallet', false),
                    old('promo_code'),
                    null,
                    old('affiliate_code', old('promo_code')),
                )
                : null;
            $referralService = app(ReferralService::class);
            $referralWallet = $referralService->wallet($customer);
            $referralSettings = $referralService->settings();
            $applicationFeePaymentRef = $request->session()->get('application_fee_payment_ref')
                ?? app(ApplicationFeePaymentService::class)->generatePaymentReference();
            $request->session()->put('application_fee_payment_ref', $applicationFeePaymentRef);
            $bankAccounts = $this->paymentBankAccountsForProduct($selectedProduct, $applicationFeePaymentRef);

            $valuationFeeQuote = $selectedProduct && is_asset_backed_loan_product($selectedProduct->code)
                ? app(ValuationFeePaymentService::class)->quote($customer)
                : null;
            $valuationFeeAmount = is_asset_backed_loan_product($selectedProduct?->code)
                ? quoted_valuation_fee($customer)
                : 0;
            $valuationFeePaymentRef = $request->session()->get('valuation_fee_payment_ref')
                ?? app(ValuationFeePaymentService::class)->generatePaymentReference();
            $request->session()->put('valuation_fee_payment_ref', $valuationFeePaymentRef);
            $assetTypeOptions = app(\App\Services\AssetBackedLoanService::class)->assetTypeOptions();
            $assetDocumentLabels = app(AssetBackedApplyService::class)->documentLabels();
            $customerAssets = app(\App\Services\CustomerAssetService::class)->forCustomer($customer);
            $pointsBalance = app(\App\Services\LoyaltyPointsService::class)->balance($customer);
            $loyaltyRedemptions = app(\App\Services\LoyaltyRedemptionService::class);
            $activeRewards = $loyaltyRedemptions->activeRewards($customer);
            $loyaltyRateDiscount = $loyaltyRedemptions->additionalRateDiscount($customer);
            $feeLoyaltyOption = $loyaltyRedemptions->availableApplicationFeeOption(
                $customer,
                (float) ($feeQuote['base'] ?? $applicationFee ?? 0)
            );
            $returnTo = $request->query('return_to');
            if (! is_string($returnTo) || $returnTo === '' || strlen($returnTo) > 64) {
                $returnTo = null;
            }
        } catch (\Throwable $e) {
            report($e);

            if ($isResume) {
                return redirect()
                    ->route('site.borrower.loans', ['tab' => 'applications'])
                    ->with('error', __('borrower.applications_list.resume_not_found'));
            }

            throw $e;
        }

        return view('site.apply.wizard', compact(
            'products',
            'customer',
            'preselect',
            'applicationFee',
            'eligibility',
            'profileSections',
            'stepPlan',
            'incomeVerification',
            'productQuestions',
            'readinessUrl',
            'reservation',
            'assetApplication',
            'selectedProduct',
            'applyRequirements',
            'savedDraft',
            'isResume',
            'feeQuote',
            'bankAccounts',
            'referralWallet',
            'referralSettings',
            'applicationFeePaymentRef',
            'valuationFeeQuote',
            'valuationFeeAmount',
            'valuationFeePaymentRef',
            'assetTypeOptions',
            'assetDocumentLabels',
            'customerAssets',
            'pointsBalance',
            'activeRewards',
            'loyaltyRateDiscount',
            'feeLoyaltyOption',
            'returnTo',
            'supplementMode',
            'supplementApplication',
        ))->with('paymentGatewayDummy', payment_gateway_is_dummy())
            ->with('loanPurposes', loan_purpose_options())
            ->with('marketplaceOnlyCodes', marketplace_only_loan_codes())
            ->with('marketplaceUrl', route('site.borrower.marketplace'))
            ->with('incomeRanges', config('income_ranges'))
            ->with('activityTypes', activity_type_options())
            ->with('groupMemberLimits', array_merge(
                app(GroupApplyService::class)->memberLimits(),
                ['minAmountPerMember' => app(GroupLendingService::class)->minAmountPerMember()],
            ))
            ->with('groupMemberLookupUrl', route('site.borrower.apply.group-member-lookup'))
            ->with('groupMemberInviteUrl', route('site.borrower.apply.group-member-invite'))
            ->with('groupMemberExpireUrl', route('site.borrower.apply.group-member-expire'))
            ->with('groupMemberStatusesUrl', route('site.borrower.apply.group-member-statuses'))
            ->with('previousGroupMembersUrl', route('site.borrower.apply.previous-group-members'))
            ->with('selectPreviousGroupMemberUrl', route('site.borrower.apply.previous-group-member'))
            ->with('leaderCustomerId', $customer->id)
            ->with('leaderName', $customer->full_name)
            ->with('leaderPhone', $customer->phone)
            ->with('engagementBoosts', app(\App\Services\MemberEngagementRewardService::class)->underwritingBoosts($customer))
            ->with('qualificationLimit', (int) app(\App\Services\BorrowerCreditLimitService::class)->availableAmount($customer))
            ->with('processingSla', app(\App\Services\UnderwritingSettingsService::class)->loanReviewSlaLabel($customer));
    }

    public function productReadiness(LoanProduct $product, LoanProductReadinessService $readiness): \Illuminate\Http\JsonResponse
    {
        $customer = Auth::user()->customer ?? Customer::where('user_id', Auth::id())->first();
        abort_unless($customer, 403);

        $product = LoanProduct::where('id', $product->id)->where('is_active', true)->firstOrFail();

        return response()->json($readiness->assess($customer, $product));
    }

    public function lookupGuarantor(Request $request, GuarantorInvitationService $guarantors): \Illuminate\Http\JsonResponse
    {
        $borrower = Auth::user()->customer ?? Customer::where('user_id', Auth::id())->first();
        abort_unless($borrower, 403);

        $data = $request->validate([
            'membership_no'   => ['required', 'string', 'max:32'],
            'phone'           => ['required', 'string', 'max:20'],
            'name'            => ['nullable', 'string', 'max:120'],
            'loan_product_id' => ['nullable', 'integer', 'exists:loan_products,id'],
        ]);

        $result = $guarantors->verifyInternalMember(
            $borrower,
            $data['membership_no'],
            $data['phone'],
            $data['name'] ?? '',
        );

        if (! $result['ok']) {
            return response()->json([
                'ok'      => false,
                'message' => $result['message'],
            ], 422);
        }

        $draft = ! empty($data['loan_product_id'])
            ? app(LoanApplicationDraftService::class)->find($borrower, (int) $data['loan_product_id'])
            : null;
        $draftAmount = (float) ($draft?->payload['form']['requested_amount'] ?? 0);
        $draftTenure = isset($draft?->payload['form']['requested_tenure_months'])
            ? (int) $draft->payload['form']['requested_tenure_months']
            : null;

        if ($message = app(\App\Services\LoanPolicyService::class)->canAcceptGuarantee($result['member'], $draftAmount > 0 ? $draftAmount : null)) {
            return response()->json([
                'ok'      => false,
                'message' => $message,
            ], 422);
        }

        $invite = null;
        if (! empty($data['loan_product_id'])) {
            try {
                $existingId = (int) ($draft?->payload['internal_guarantor']['invitation_id'] ?? 0) ?: null;
                $invite = $guarantors->prepareWizardInternalInvitation(
                    $borrower,
                    $data['membership_no'],
                    $data['phone'],
                    $result['name'] ?? ($data['name'] ?? ''),
                    $existingId,
                    $draftAmount > 0 ? (int) $draftAmount : null,
                    $draftTenure,
                    (int) $data['loan_product_id'],
                );

                app(LoanApplicationDraftService::class)->save($borrower, [
                    'phase'           => $draft?->phase ?? 'application',
                    'step'            => $draft?->step ?? 0,
                    'loan_product_id' => (int) $data['loan_product_id'],
                    'form'            => array_merge($draft?->payload['form'] ?? [], [
                        'guarantor_mode'           => 'internal',
                        'internal_member_no'       => $data['membership_no'],
                        'internal_guarantor_phone' => $data['phone'],
                        'internal_guarantor_name'  => $result['name'],
                    ]),
                    'inputs'             => $draft?->payload['inputs'] ?? [],
                    'internal_guarantor' => $invite,
                    'external_guarantor' => null,
                ]);
            } catch (\InvalidArgumentException $e) {
                return response()->json([
                    'ok'      => false,
                    'message' => $e->getMessage(),
                ], 422);
            } catch (\Throwable $e) {
                report($e);

                return response()->json([
                    'ok'      => false,
                    'message' => __('borrower.apply.alerts.guarantor_invite_failed'),
                ], 500);
            }
        }

        return response()->json([
            'ok'      => true,
            'name'    => $result['name'],
            'label'   => $result['label'],
            'message' => $invite
                ? __('borrower.apply.alerts.guarantor_notified_in_app', ['name' => $result['name']])
                : ($result['message'] ?? null),
            'invite'  => $invite,
        ]);
    }

    public function lookupGroupMember(Request $request, GroupApplyService $groups): \Illuminate\Http\JsonResponse
    {
        $leader = Auth::user()->customer ?? Customer::where('user_id', Auth::id())->first();
        abort_unless($leader, 403);

        $data = $request->validate([
            'member_no'         => ['required', 'string', 'max:40'],
            'phone'             => ['required', 'string', 'max:20'],
            'name'              => ['nullable', 'string', 'max:120'],
            'loan_product_id'   => ['required', 'integer', 'exists:loan_products,id'],
            'validate_only'     => ['nullable', 'boolean'],
        ]);

        $product = LoanProduct::where('id', $data['loan_product_id'])->where('is_active', true)->firstOrFail();
        abort_unless(app(GroupLendingService::class)->isGroupProduct($product), 422);

        $result = $groups->lookupMemberByMembershipAndPhone(
            $leader,
            $data['member_no'],
            $data['phone'],
            $data['name'] ?? '',
        );

        if (! $result['ok']) {
            return response()->json([
                'ok'      => false,
                'message' => $result['message'] ?? __('borrower.apply.group.lookup_not_found'),
            ], 422);
        }

        $member = Customer::findOrFail((int) $result['customer_id']);

        if (! empty($data['validate_only'])) {
            return response()->json([
                'ok'          => true,
                'customer_id' => $member->id,
                'name'        => $result['label'] ?? $member->full_name,
                'phone'       => $member->phone,
                'label'       => $result['label'],
                'status_key'  => 'profile_incomplete',
            ]);
        }

        try {
            $share = app(GroupMemberInvitationService::class)->prepareInternalInvitation($leader, $product, $member);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'ok'            => true,
            'customer_id'   => $share['customer_id'],
            'name'          => $share['name'],
            'phone'         => $share['phone'],
            'label'         => $result['label'],
            'invitation_id' => $share['invitation_id'],
            'status_key'    => $share['status_key'],
            'share'         => $share,
        ]);
    }

    public function previousGroupMembers(Request $request, GroupMemberInvitationService $invites): \Illuminate\Http\JsonResponse
    {
        $leader = Auth::user()->customer ?? Customer::where('user_id', Auth::id())->first();
        abort_unless($leader, 403);

        $exclude = collect(explode(',', (string) $request->query('exclude', '')))
            ->map(fn ($id) => (int) trim($id))
            ->filter(fn ($id) => $id > 0)
            ->values()
            ->all();

        return response()->json([
            'members' => $invites->previousMembersForLeader($leader, $exclude),
        ]);
    }

    public function selectPreviousGroupMember(
        Request $request,
        GroupMemberInvitationService $invites,
    ): \Illuminate\Http\JsonResponse {
        $leader = Auth::user()->customer ?? Customer::where('user_id', Auth::id())->first();
        abort_unless($leader, 403);

        $data = $request->validate([
            'customer_id'     => ['required', 'integer', 'exists:customers,id'],
            'loan_product_id' => ['required', 'integer', 'exists:loan_products,id'],
        ]);

        $product = LoanProduct::where('id', $data['loan_product_id'])->where('is_active', true)->firstOrFail();
        abort_unless(app(GroupLendingService::class)->isGroupProduct($product), 422);

        $result = $invites->preparePreviousMember($leader, $product, (int) $data['customer_id']);

        if (! ($result['ok'] ?? false)) {
            return response()->json([
                'ok'      => false,
                'message' => $result['message'] ?? __('borrower.apply.group.lookup_not_found'),
            ], 422);
        }

        return response()->json($result);
    }

    public function prepareGroupMemberInvite(
        Request $request,
        GroupMemberInvitationService $invites,
    ): \Illuminate\Http\JsonResponse {
        $leader = Auth::user()->customer ?? Customer::where('user_id', Auth::id())->first();
        abort_unless($leader, 403);

        $data = $request->validate([
            'loan_product_id' => ['required', 'integer', 'exists:loan_products,id'],
            'first_name'      => ['required', 'string', 'max:60'],
            'last_name'       => ['required', 'string', 'max:80'],
            'phone'           => ['required', 'string', 'max:20'],
            'email'           => ['nullable', 'email', 'max:150'],
            'group'           => ['nullable', 'array'],
            'invitation_reason' => ['nullable', 'string', 'max:500'],
        ]);

        $product = LoanProduct::where('id', $data['loan_product_id'])->where('is_active', true)->firstOrFail();
        $group = is_array($data['group'] ?? null) ? $data['group'] : [];
        $groups = app(GroupLendingService::class);

        $context = [
            'group_name'              => $group['name'] ?? null,
            'group_purpose'           => $group['purpose'] ?? null,
            'amount_per_member'       => $group['amount_per_member'] ?? null,
            'requested_tenure_months' => $group['requested_tenure_months'] ?? ($group['tenure_months'] ?? null),
            'repayment_cadence'       => $groups->effectiveRepaymentCadence($product),
            'invitation_reason'       => $data['invitation_reason'] ?? null,
            'loan_product_id'         => $product->id,
        ];

        try {
            $share = $invites->prepareExternalInvitation(
                $leader,
                $product,
                $data['first_name'],
                null,
                $data['last_name'],
                $data['phone'],
                $data['email'] ?? null,
                null,
                $context,
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'ok'            => true,
            'invitation_id' => $share['invitation_id'],
            'name'          => $share['name'],
            'phone'         => $share['phone'],
            'share'         => $share,
        ]);
    }

    public function expireGroupMemberInvitation(
        Request $request,
        GroupMemberInvitationService $invitations,
    ): \Illuminate\Http\JsonResponse {
        $leader = Auth::user()->customer ?? Customer::where('user_id', Auth::id())->first();
        abort_unless($leader, 403);

        $data = $request->validate([
            'invitation_id' => ['required', 'integer', 'min:1'],
        ]);

        $ok = $invitations->cancelInvitationForLeader($leader, (int) $data['invitation_id']);

        return response()->json(['ok' => $ok]);
    }

    public function refreshGroupMemberStatuses(Request $request): \Illuminate\Http\JsonResponse
    {
        $leader = Auth::user()->customer ?? Customer::where('user_id', Auth::id())->first();
        abort_unless($leader, 403);

        $data = $request->validate([
            'members'             => ['required', 'array'],
            'target_member_count' => ['nullable', 'integer', 'min:1'],
            'group'               => ['nullable', 'array'],
        ]);

        $progress = app(GroupMemberProgressService::class);
        $invitations = app(GroupMemberInvitationService::class);
        $members = collect($data['members'])->map(function (array $row) use ($progress, $leader, $invitations) {
            $invitationId = (int) ($row['invitation_id'] ?? 0);
            if ($invitationId > 0) {
                $invitation = \App\Models\GroupMemberInvitation::query()
                    ->where('id', $invitationId)
                    ->where('leader_customer_id', $leader->id)
                    ->first();
                if ($invitation) {
                    if (in_array($invitation->status, ['rejected', 'cancelled', 'expired'], true)) {
                        return null;
                    }
                    $status = $progress->statusFromInvitation($invitation);
                    $row['status_key'] = $status['key'];
                    $row['status_label'] = $status['label'];
                    if ($invitation->customer_id) {
                        $row['customer_id'] = $invitation->customer_id;
                    }
                    if (in_array($invitation->status, ['pending', 'accepted'], true)) {
                        $row['share'] = $invitations->sharePayload($invitation);
                    }
                }
            } elseif (filled($row['customer_id'] ?? null)) {
                $customer = Customer::find((int) $row['customer_id']);
                if ($customer) {
                    $status = $progress->statusFromCustomer($customer);
                    $row['status_key'] = $status['key'];
                    $row['status_label'] = $status['label'];
                }
            }

            $profile = $progress->profileCompletionForMember($row);
            $row['profile_percent'] = $profile['percent'];
            $row['profile_sections'] = $profile['sections'];
            $row['progress_steps'] = [];

            return $row;
        })->filter()->values();

        $target = (int) ($request->input('target_member_count') ?: $members->count());
        $summary = $progress->summarize($members->all(), max(1, $target));

        $groupPayload = is_array($data['group'] ?? null) ? $data['group'] : [];
        $groupPayload['members'] = $members->all();
        $groupPayload['target_member_count'] = max(1, $target);

        $applicationStatus = app(GroupApplicationStatusService::class)->resolveFromDraftPayload($groupPayload);
        $scoring = app(GroupScoringService::class)->scoreFromDraftPayload($groupPayload);

        return response()->json([
            'ok'                 => true,
            'members'            => $summary['members'] ?? $members->all(),
            'summary'            => $summary,
            'application_status' => $applicationStatus,
            'scoring'            => $scoring,
        ]);
    }

    public function previousGuarantors(GuarantorInvitationService $guarantors): \Illuminate\Http\JsonResponse
    {
        $borrower = Auth::user()->customer ?? Customer::where('user_id', Auth::id())->first();
        abort_unless($borrower, 403);

        return response()->json([
            'guarantors' => $guarantors->previousGuarantorsForBorrower($borrower),
        ]);
    }

    public function selectPreviousGuarantor(Request $request, GuarantorInvitationService $guarantors): \Illuminate\Http\JsonResponse
    {
        $borrower = Auth::user()->customer ?? Customer::where('user_id', Auth::id())->first();
        abort_unless($borrower, 403);

        $data = $request->validate([
            'customer_guarantor_id' => ['required', 'integer'],
        ]);

        $result = $guarantors->prepareWizardPreviousGuarantor($borrower, (int) $data['customer_guarantor_id']);

        if (! $result['ok']) {
            return response()->json($result, 422);
        }

        return response()->json($result);
    }

    public function prepareExternalGuarantor(
        Request $request,
        GuarantorInvitationService $guarantors,
        LoanApplicationDraftService $drafts,
    ): \Illuminate\Http\JsonResponse {
        $borrower = Auth::user()->customer ?? Customer::where('user_id', Auth::id())->first();
        abort_unless($borrower, 403);

        $data = $request->validate([
            'loan_product_id'       => ['required', 'integer', 'exists:loan_products,id'],
            'external_first_name'   => ['required', 'string', 'max:60'],
            'external_middle_name'  => ['nullable', 'string', 'max:60'],
            'external_last_name'    => ['required', 'string', 'max:60'],
            'external_phone'        => ['required', 'string', 'max:20'],
            'external_email'        => ['nullable', 'email', 'max:120'],
            'external_relationship' => ['required', 'string', 'max:40'],
            'external_region'       => ['required', 'string', 'max:100'],
            'external_district'     => ['required', 'string', 'max:100'],
            'external_channel'      => ['nullable', 'in:whatsapp,sms,email'],
            'external_invitation_id'=> ['nullable', 'integer'],
        ]);

        $draft = $drafts->find($borrower, (int) $data['loan_product_id']);
        $existingId = $data['external_invitation_id']
            ?? ($draft?->payload['external_guarantor']['invitation_id'] ?? null);
        $draftForm = $draft?->payload['form'] ?? [];
        $requestedAmount = isset($draftForm['requested_amount']) ? (int) $draftForm['requested_amount'] : null;
        $requestedTenure = isset($draftForm['requested_tenure_months']) ? (int) $draftForm['requested_tenure_months'] : null;

        try {
            $share = $guarantors->prepareWizardExternalInvitation(
                $borrower,
                trim($data['external_first_name']),
                trim($data['external_middle_name'] ?? '') ?: null,
                trim($data['external_last_name']),
                $data['external_phone'],
                $data['external_email'] ?? null,
                $data['external_relationship'],
                $data['external_region'],
                $data['external_district'],
                $data['external_channel'] ?? null,
                $existingId ? (int) $existingId : null,
                $requestedAmount,
                $requestedTenure,
                (int) $data['loan_product_id'],
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'ok'      => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'ok'      => false,
                'message' => __('borrower.apply.alerts.guarantor_invite_failed'),
            ], 500);
        }

        $drafts->save($borrower, [
            'phase'           => $draft?->phase ?? 'application',
            'step'            => $draft?->step ?? 0,
            'loan_product_id' => (int) $data['loan_product_id'],
            'form'            => $draft?->payload['form'] ?? [],
            'inputs'          => $draft?->payload['inputs'] ?? [],
            'external_guarantor' => $share,
        ]);

        return response()->json(['ok' => true, 'share' => $share]);
    }

    public function guarantorInvitationStatus(
        Request $request,
        GuarantorInvitationService $guarantors,
    ): \Illuminate\Http\JsonResponse {
        $borrower = Auth::user()->customer ?? Customer::where('user_id', Auth::id())->first();
        abort_unless($borrower, 403);

        $data = $request->validate([
            'invitation_id' => ['required', 'integer'],
        ]);

        $invitation = \App\Models\GuarantorInvitation::query()
            ->where('id', (int) $data['invitation_id'])
            ->where('customer_id', $borrower->id)
            ->first();

        if (! $invitation) {
            return response()->json(['ok' => false, 'message' => 'Invitation not found.'], 404);
        }

        return response()->json([
            'ok'    => true,
            'share' => $guarantors->sharePayload($invitation, $borrower),
        ]);
    }

    public function expireGuarantorInvitation(
        Request $request,
        LoanPolicyService $policy,
        LoanApplicationDraftService $drafts,
    ): \Illuminate\Http\JsonResponse {
        $borrower = Auth::user()->customer ?? Customer::where('user_id', Auth::id())->first();
        abort_unless($borrower, 403);

        $data = $request->validate([
            'loan_product_id' => ['required', 'integer', 'exists:loan_products,id'],
        ]);

        $policy->expireSupersededGuarantorLinks($borrower);

        $draft = $drafts->find($borrower, (int) $data['loan_product_id']);
        if ($draft) {
            $payload = $draft->payload ?? [];
            $form = $payload['form'] ?? [];
            unset($payload['external_guarantor'], $payload['guarantor_lookup'], $payload['internal_guarantor']);
            $form['guarantor_mode'] = null;
            $form['internal_member_no'] = null;
            $form['internal_guarantor_phone'] = null;
            $form['internal_guarantor_name'] = null;
            $payload['form'] = $form;
            $draft->update(['payload' => $payload, 'saved_at' => now()]);
        }

        return response()->json(['ok' => true]);
    }

    public function loadDraft(LoanApplicationDraftService $drafts): \Illuminate\Http\JsonResponse
    {
        $customer = Auth::user()->customer ?? Customer::where('user_id', Auth::id())->first();
        abort_unless($customer, 403);

        return response()->json([
            'draft' => $drafts->payloadForWizard($customer),
        ]);
    }

    public function saveDraft(Request $request, LoanApplicationDraftService $drafts): \Illuminate\Http\JsonResponse
    {
        $customer = Auth::user()->customer ?? Customer::where('user_id', Auth::id())->first();
        abort_unless($customer, 403);

        $data = $request->validate([
            'phase'                => ['required', 'string', 'in:browse,details,application'],
            'step'                 => ['nullable', 'integer', 'min:0'],
            'step_key'             => ['nullable', 'string', 'max:64'],
            'loan_product_id'      => ['nullable', 'integer', 'exists:loan_products,id'],
            'asset_reservation_id' => ['nullable', 'integer'],
            'form'                 => ['nullable', 'array'],
            'inputs'               => ['nullable', 'array'],
            'guarantor_lookup'     => ['nullable', 'array'],
            'application_fee'      => ['nullable', 'array'],
            'valuation_fee'        => ['nullable', 'array'],
            'asset_documents'      => ['nullable', 'array'],
            'external_guarantor'   => ['nullable', 'array'],
            'borrower_signature'   => ['nullable', 'array'],
            'declaration_accepted' => ['nullable', 'boolean'],
            'group'                => ['nullable', 'array'],
        ]);

        if ($data['phase'] === 'browse' || empty($data['loan_product_id'])) {
            $drafts->clear($customer, $data['loan_product_id'] ? (int) $data['loan_product_id'] : null);

            return response()->json(['ok' => true, 'cleared' => true]);
        }

        $drafts->save($customer, $data);

        $draft = $drafts->find($customer, (int) ($data['loan_product_id'] ?? 0));

        return response()->json([
            'ok'              => true,
            'saved_at'        => now()->toIso8601String(),
            'draft_reference' => $draft?->draft_reference,
        ]);
    }

    public function payApplicationFee(
        Request $request,
        LoanApplicationDraftService $drafts,
        ApplicationFeePaymentService $fees,
    ): \Illuminate\Http\JsonResponse|RedirectResponse {
        $customer = Auth::user()->customer ?? Customer::where('user_id', Auth::id())->first();
        abort_unless($customer, 403);

        $dummyGateway = payment_gateway_is_dummy();
        $data = $request->validate([
            'loan_product_id' => ['required', 'integer', 'exists:loan_products,id'],
            'channel'         => ['nullable', 'in:mobile_money,bank'],
            'payment_phone'   => ['nullable', 'string', 'max:20'],
            'use_wallet'      => ['nullable', 'boolean'],
            'promo_code'      => ['nullable', 'string', 'max:40'],
            'affiliate_code'  => ['nullable', 'string', 'max:40'],
            'redeem_loyalty'  => ['nullable', 'boolean'],
            'loyalty_option_key' => ['nullable', 'string', 'max:64'],
        ]);

        $product = LoanProduct::where('id', $data['loan_product_id'])->where('is_active', true)->firstOrFail();
        $draft = $drafts->find($customer, $product->id);
        $groups = app(GroupLendingService::class);
        $memberCount = $groups->isGroupProduct($product)
            ? $groups->memberCountFromPayload($draft?->payload['group'] ?? null)
            : 1;

        $loyaltyRedeemed = false;
        if ($request->boolean('redeem_loyalty') && filled($data['loyalty_option_key'] ?? null)) {
            try {
                app(\App\Services\LoyaltyRedemptionService::class)
                    ->redeem($customer, (string) $data['loyalty_option_key']);
                $loyaltyRedeemed = true;
                $customer->refresh();
            } catch (\InvalidArgumentException $e) {
                if ($request->expectsJson()) {
                    return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
                }

                return back()->with('error', $e->getMessage());
            }
        }

        $amount = $groups->isGroupProduct($product)
            ? $groups->quotedApplicationFee($customer, $product, $memberCount)
            : quoted_origination_fee($customer, $product);

        if ($amount <= 0) {
            $feeState = ['status' => 'waived', 'reference' => null, 'channel' => 'waived', 'amount' => 0, 'paid_at' => now()->toIso8601String()];
            $drafts->saveApplicationFee($customer, $product->id, $feeState);
            if (product_includes_valuation_fee($product)) {
                $drafts->saveValuationFee($customer, $product->id, $feeState);
            }
            $drafts->advancePastApplicationFee($customer, $product->id);

            if ($request->expectsJson()) {
                $next = $fees->nextStepAfterApplicationFee(
                    $customer,
                    $product,
                    $drafts->find($customer, $product->id)?->payload,
                );

                return response()->json([
                    'ok' => true,
                    'fee' => $feeState,
                    'next_step_key' => $next,
                ]);
            }

            return redirect()->route('site.borrower.apply', [
                'product' => $product->id,
                'resume' => 1,
                'step_key' => $fees->nextStepAfterApplicationFee(
                    $customer,
                    $product,
                    $drafts->find($customer, $product->id)?->payload,
                ),
            ])->with('status', __('borrower.apply.application_fee.waived'));
        }

        $paymentReference = $request->session()->get('application_fee_payment_ref')
            ?? $fees->generatePaymentReference();
        $request->session()->put('application_fee_payment_ref', $paymentReference);

        // Always open the shared payments.show gate — method + USSD live there.
        $feeState = $fees->openSharedGate(
            $customer,
            $product,
            $paymentReference,
            $request->boolean('use_wallet'),
            $data['promo_code'] ?? null,
            $groups->isGroupProduct($product) ? $memberCount : null,
            $data['affiliate_code'] ?? $data['promo_code'] ?? null,
            $data['payment_phone'] ?? $customer->phone,
        );
        $drafts->saveApplicationFee($customer, $product->id, $feeState);
        if (product_includes_valuation_fee($product)) {
            $drafts->saveValuationFee($customer, $product->id, $feeState);
        }
        $request->session()->forget('application_fee_payment_ref');

        $message = (($feeState['status'] ?? '') === 'paid')
            ? ($dummyGateway
                ? __('borrower.apply.application_fee.dummy_paid')
                : __('borrower.apply.application_fee.paid'))
            : __('borrower.payment_waiting.ready');

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'fee' => $feeState,
                'message' => $message,
                'dummy' => $dummyGateway,
                'loyalty_redeemed' => $loyaltyRedeemed,
                'wait_url' => $feeState['wait_url']
                    ?? (! empty($feeState['payment_id'])
                        ? route('site.borrower.payments.show', $feeState['payment_id'])
                        : null),
                'processing' => in_array($feeState['status'] ?? '', ['processing', 'pending', 'paid'], true)
                    && ! empty($feeState['payment_id']),
            ]);
        }

        if (! empty($feeState['payment_id'])) {
            return redirect()
                ->route('site.borrower.payments.show', $feeState['payment_id'])
                ->with('status', $message);
        }

        return back()->with('status', $message);
    }

    public function payValuationFee(
        Request $request,
        LoanApplicationDraftService $drafts,
        ValuationFeePaymentService $fees,
        AssetBackedApplyService $assetApply,
    ): \Illuminate\Http\JsonResponse|RedirectResponse {
        $customer = Auth::user()->customer ?? Customer::where('user_id', Auth::id())->first();
        abort_unless($customer, 403);

        $dummyGateway = payment_gateway_is_dummy();
        $data = $request->validate([
            'loan_product_id' => ['required', 'integer', 'exists:loan_products,id'],
            'channel'         => ['required', 'in:mobile_money,bank'],
            'payment_phone'   => [$dummyGateway ? 'nullable' : 'required_if:channel,mobile_money', 'nullable', 'string', 'max:20'],
            'use_wallet'      => ['nullable', 'boolean'],
            'asset_type'      => ['nullable', 'string', 'max:40'],
            'asset_description' => ['nullable', 'string', 'max:500'],
        ]);

        $product = LoanProduct::where('id', $data['loan_product_id'])->where('is_active', true)->firstOrFail();
        abort_unless(is_asset_backed_loan_product($product->code), 422);

        $draft = $drafts->find($customer, $product->id);
        $form = ($draft?->payload ?? [])['form'] ?? [];
        if (filled($data['asset_type'] ?? null)) {
            $form['asset_type'] = $data['asset_type'];
        }
        if (array_key_exists('asset_description', $data)) {
            $form['asset_description'] = $data['asset_description'];
        }

        try {
            if (blank($form['asset_type'] ?? null)) {
                throw ValidationException::withMessages([
                    'asset_type' => 'Select an asset type before paying the valuation fee.',
                ]);
            }
        } catch (ValidationException $e) {
            if ($request->expectsJson()) {
                return response()->json(['ok' => false, 'message' => collect($e->errors())->flatten()->first()], 422);
            }

            return back()->withErrors($e->errors());
        }

        if ($draft) {
            $payload = $draft->payload ?? [];
            $payload['form'] = array_merge($payload['form'] ?? [], $form);
            $draft->update(['payload' => $payload, 'saved_at' => now()]);
        }

        $amount = quoted_valuation_fee($customer);

        if ($amount <= 0) {
            $feeState = ['status' => 'waived', 'reference' => null, 'channel' => 'waived', 'amount' => 0, 'paid_at' => now()->toIso8601String()];
            $drafts->saveValuationFee($customer, $product->id, $feeState);

            if ($request->expectsJson()) {
                return response()->json(['ok' => true, 'fee' => $feeState]);
            }

            return back()->with('status', __('borrower.apply.valuation_fee.waived'));
        }

        $paymentReference = $request->session()->get('valuation_fee_payment_ref')
            ?? $fees->generatePaymentReference();
        $request->session()->put('valuation_fee_payment_ref', $paymentReference);

        if ($data['channel'] === 'mobile_money') {
            $feeState = $fees->processMobileMoney(
                $customer,
                $product,
                $paymentReference,
                $request->boolean('use_wallet'),
                $data['payment_phone'] ?? $customer->phone,
            );
            $drafts->saveValuationFee($customer, $product->id, $feeState);
            $request->session()->forget('valuation_fee_payment_ref');

            $message = $dummyGateway
                ? __('borrower.apply.valuation_fee.dummy_paid')
                : (($feeState['status'] ?? '') === 'processing'
                    ? __('borrower.payment_waiting.waiting')
                    : __('borrower.apply.valuation_fee.paid'));

            if ($request->expectsJson()) {
                return response()->json([
                    'ok' => true,
                    'fee' => $feeState,
                    'message' => $message,
                    'dummy' => $dummyGateway,
                    'wait_url' => $feeState['wait_url'] ?? null,
                    'processing' => in_array($feeState['status'] ?? '', ['processing', 'pending'], true),
                ]);
            }

            if (! empty($feeState['wait_url']) && ! empty($feeState['payment_id'])) {
                return redirect()
                    ->route('site.borrower.payments.show', $feeState['payment_id'])
                    ->with('status', $message);
            }

            return back()->with('status', $message);
        }

        $feeState = $fees->processBankPending($customer, $product, $paymentReference);
        $drafts->saveValuationFee($customer, $product->id, $feeState);
        $request->session()->forget('valuation_fee_payment_ref');

        $bankMessage = (($feeState['status'] ?? '') === 'paid')
            ? ($dummyGateway
                ? __('borrower.apply.valuation_fee.dummy_paid')
                : __('borrower.apply.valuation_fee.paid'))
            : __('borrower.apply.valuation_fee.bank_submitted', ['ref' => $feeState['reference'] ?? $paymentReference]);

        if ($request->expectsJson()) {
            return response()->json([
                'ok'      => true,
                'fee'     => $feeState,
                'message' => $bankMessage,
                'dummy'   => $dummyGateway,
                'wait_url' => $feeState['wait_url'] ?? null,
                'processing' => in_array($feeState['status'] ?? '', ['processing', 'pending'], true),
            ]);
        }

        if (! empty($feeState['wait_url']) && ! empty($feeState['payment_id'])) {
            return redirect()
                ->route('site.borrower.payments.show', $feeState['payment_id'])
                ->with(($feeState['status'] ?? '') === 'paid' ? 'status' : 'warning', $bankMessage);
        }

        return back()->with(($feeState['status'] ?? '') === 'paid' ? 'status' : 'warning', $bankMessage);
    }

    public function valuationFeeQuote(Request $request, ValuationFeePaymentService $fees): \Illuminate\Http\JsonResponse
    {
        $customer = Auth::user()->customer ?? Customer::where('user_id', Auth::id())->first();
        abort_unless($customer, 403);

        LoanProduct::where('id', $request->query('loan_product_id'))
            ->where('is_active', true)
            ->firstOrFail();

        return response()->json([
            'amount' => quoted_valuation_fee($customer),
            'quote'  => $fees->quote($customer),
        ]);
    }

    public function uploadAssetDocument(
        Request $request,
        LoanApplicationDraftService $drafts,
        AssetBackedApplyService $assetApply,
    ): \Illuminate\Http\JsonResponse {
        $customer = Auth::user()->customer ?? Customer::where('user_id', Auth::id())->first();
        abort_unless($customer, 403);

        $data = $request->validate([
            'loan_product_id' => ['required', 'integer', 'exists:loan_products,id'],
            'document_code'   => ['required', 'string', 'max:60'],
            'file'            => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);

        $product = LoanProduct::where('id', $data['loan_product_id'])->where('is_active', true)->firstOrFail();
        abort_unless(is_asset_backed_loan_product($product->code), 422);

        $draft = $drafts->find($customer, $product->id)
            ?? $drafts->save($customer, [
                'phase'           => 'application',
                'loan_product_id' => $product->id,
                'form'            => [],
            ]);

        abort_unless($draft, 422);

        $document = $assetApply->uploadDocument(
            $customer,
            $draft,
            $data['document_code'],
            $request->file('file'),
        );

        $payload = $drafts->payloadForWizard($customer, $product->id);

        return response()->json([
            'ok'              => true,
            'document_id'     => $document->id,
            'document_code'   => $data['document_code'],
            'asset_documents' => $payload['asset_documents'] ?? [],
        ]);
    }

    public function applicationFeeQuote(Request $request, ApplicationFeePaymentService $fees): \Illuminate\Http\JsonResponse
    {
        $customer = Auth::user()->customer ?? Customer::where('user_id', Auth::id())->first();
        abort_unless($customer, 403);

        $product = LoanProduct::where('id', $request->query('loan_product_id'))
            ->where('is_active', true)
            ->firstOrFail();

        $useWallet = $request->boolean('use_wallet');
        $promoCode = $request->query('promo_code');
        $affiliateCode = $request->query('affiliate_code', $promoCode);
        $memberCount = max(1, (int) $request->query('member_count', 1));
        $groups = app(GroupLendingService::class);

        $amount = $groups->isGroupProduct($product)
            ? $groups->quotedApplicationFee($customer, $product, $memberCount)
            : quoted_origination_fee($customer, $product);

        return response()->json([
            'amount' => $amount,
            'quote'  => $fees->quote(
                $customer,
                $product,
                $useWallet,
                $promoCode,
                $groups->isGroupProduct($product) ? $memberCount : null,
                $affiliateCode,
            ),
            'breakdown' => $groups->isGroupProduct($product)
                ? $groups->applicationFeeBreakdown($customer, $product, $memberCount)
                : null,
        ]);
    }

    public function repaymentPreview(
        Request $request,
        RepaymentScheduleGenerator $schedules,
        SmartLoanApplicationWizardService $wizard,
    ): \Illuminate\Http\JsonResponse {
        $customer = Auth::user()->customer ?? Customer::where('user_id', Auth::id())->first();
        abort_unless($customer, 403);

        $data = $request->validate([
            'loan_product_id'  => ['required', 'exists:loan_products,id'],
            'requested_amount' => ['required', 'numeric', 'min:1000'],
            'requested_tenure_months' => ['required', 'integer', 'min:1', 'max:60'],
        ]);

        $product = LoanProduct::where('id', $data['loan_product_id'])
            ->where('is_active', true)
            ->firstOrFail();

        $amount = (float) $data['requested_amount'];
        $tenure = (int) $data['requested_tenure_months'];
        $rate = app(\App\Services\LoanRateTierService::class)->resolveRate($product, $amount, $customer);
        $cadence = app(GroupLendingService::class)->effectiveRepaymentCadence($product);
        $method = in_array(($product->interest_method ?? 'reducing'), ['flat', 'reducing'], true)
            ? (string) ($product->interest_method ?? 'reducing')
            : 'reducing';
        $rows = $schedules->preview($amount, $rate, $tenure, $cadence, null, $method);

        $balance = $amount;
        $schedule = [];
        $interestFromSchedule = 0.0;
        $totalFromSchedule = 0.0;
        foreach ($rows as $row) {
            $balance = max(0, round($balance - $row['principal_due'], 2));
            $interestFromSchedule += (float) $row['interest_due'];
            $totalFromSchedule += (float) $row['total_due'];
            $schedule[] = [
                'installment_no'      => $row['installment_no'],
                'due_date'            => null,
                'principal_due'       => round($row['principal_due'], 2),
                'interest_due'        => round($row['interest_due'], 2),
                'total_due'           => round($row['total_due'], 2),
                'remaining_balance'   => $balance,
                'label'               => $row['label'],
            ];
        }

        $periods = $schedules->periodCount($tenure, $cadence);
        $installment = $rows[0]['total_due'] ?? 0;
        if (count($rows) > 1) {
            // Prefer the regular instalment (last period may be adjusted on reducing).
            $installment = $rows[0]['total_due'];
        }
        $emi = $cadence === 'monthly' ? round((float) $installment, 2) : round($wizard->estimateEmi($amount, $rate, $tenure), 2);
        $weekly = $cadence === 'weekly' ? round((float) $installment, 2) : 0;
        $applicationFee = quoted_application_fee($customer, $product);
        $boosts = app(\App\Services\MemberEngagementRewardService::class)->underwritingBoosts($customer);
        $qualification = app(\App\Services\LoanQualificationService::class)->calculate($customer);
        $standardRate = app(DisplayedRateService::class)->displayedMonthlyRate($product, $amount);

        return response()->json([
            'ok'            => true,
            'dates_available' => false,
            'schedule'      => $schedule,
            'summary'       => [
                'monthly_rate'        => $rate,
                'monthly_rate_pct'    => round($rate * 100, 2),
                'standard_rate_pct'   => round($standardRate * 100, 2),
                'application_fee'     => $applicationFee,
                'monthly_installment' => $emi,
                'weekly_installment'  => $weekly,
                'installment_amount'  => round((float) $installment, 2),
                'repayment_cadence'   => $cadence,
                'interest_method'     => $method,
                'interest_total'      => round($interestFromSchedule, 2),
                'total_repayment'     => round($totalFromSchedule, 2),
                'periods'             => $periods,
            ],
            'engagement'    => [
                'limit_amount'          => (int) app(\App\Services\BorrowerCreditLimitService::class)->availableAmount($customer),
                'limit_multiplier'      => (float) ($boosts['limit_multiplier'] ?? 1),
                'rate_discount_pct'     => round(((float) ($boosts['rate_discount_fraction'] ?? 0)) * 100, 2),
                'processing_sla'        => app(\App\Services\UnderwritingSettingsService::class)->loanReviewSlaLabel($customer),
                'processing_priority'   => (int) ($boosts['processing_priority'] ?? 0),
                'factors'               => $boosts['factors'] ?? [],
            ],
        ]);
    }

    public function submit(
        Request $request,
        FaceVerificationService $faces,
        KycFreshnessService $freshness,
        GuarantorInvitationService $guarantors,
        ApplicationRequirementsService $requirements,
        LoanApplicationDraftService $drafts,
        CrbCreditCheckService $crbCredit,
    ): RedirectResponse {
        $customer = Auth::user()->customer ?? Customer::where('user_id', Auth::id())->first();

        abort_unless($customer, 403);

        $loanProduct = LoanProduct::where('id', $request->input('loan_product_id'))
            ->where('is_active', true)
            ->first();

        $draft = $drafts->find($customer, (int) $request->input('loan_product_id'));
        if (! $draft && $customer) {
            $draft = $drafts->find($customer);
        }

        if (! $loanProduct && $draft?->loan_product_id) {
            $loanProduct = LoanProduct::where('id', $draft->loan_product_id)
                ->where('is_active', true)
                ->first();
        }

        abort_unless($loanProduct, 404);

        $this->mergeDraftIntoSubmitRequest($request, $draft);

        // Underwriting asked for another guarantor — attach to existing application only.
        if ($request->filled('supplement_application_id')) {
            return $this->submitGuarantorSupplement($request, $customer, $guarantors, $faces, $freshness, $requirements);
        }

        $returnUrl = route('site.borrower.apply', array_filter([
            'product'  => (int) $loanProduct->id,
            'resume'   => 1,
            'step_key' => 'submit',
        ]));
        $checklist = $requirements->checklistForApply($customer, $returnUrl);

        if (! $checklist['can_apply']) {
            $incomplete = $checklist['first_incomplete'] ?? null;
            $message = ! empty($incomplete['label'])
                ? __('borrower.apply.kyc_incomplete_redirect', ['section' => $incomplete['label']])
                : __('borrower.apply.kyc_incomplete_submit');

            // Stay on the submit step and open the premium profile gate modal — do not
            // silently bounce the borrower to the profile page.
            return redirect()
                ->route('site.borrower.apply', array_filter([
                    'product'      => (int) $loanProduct->id,
                    'resume'       => 1,
                    'step_key'     => 'submit',
                    'profile_gate' => 1,
                ]))
                ->with('error', $message)
                ->with('show_profile_gate', true);
        }

        $identityPolicy = app(\App\Services\IdentityVerificationPolicyService::class);
        if ($identityPolicy->requiredDuringProfileCreation()
            && $identityPolicy->facialRequired()
            && ! $faces->profileStepComplete($customer)) {
            return redirect()
                ->route('site.borrower.apply', array_filter([
                    'product'      => (int) $loanProduct->id,
                    'resume'       => 1,
                    'step_key'     => 'submit',
                    'profile_gate' => 1,
                ]))
                ->with('error', __('borrower.apply.kyc_incomplete_redirect', [
                    'section' => __('borrower.nida.face_title'),
                ]))
                ->with('show_profile_gate', true);
        }

        if (! $freshness->canApply($customer)) {
            return redirect()
                ->route('site.borrower.apply', array_filter([
                    'product'      => (int) $loanProduct->id,
                    'resume'       => 1,
                    'step_key'     => 'submit',
                    'profile_gate' => 1,
                ]))
                ->with('error', __('borrower.apply.kyc_incomplete_redirect', [
                    'section' => __('borrower.kyc.reconfirm_title'),
                ]))
                ->with('show_profile_gate', true);
        }

        if ($existing = $this->findExistingSubmittedApplication($customer, $loanProduct, $draft)) {
            $drafts->clear($customer, (int) $loanProduct->id);

            return redirect()
                ->route('site.borrower.apply.success', $existing)
                ->with('status', __('borrower.apply.success.already_submitted_message'));
        }

        if ($message = app(\App\Services\LoanPolicyService::class)->canSubmitApplication($customer, $loanProduct)) {
            return $this->wizardSubmitRedirect($request, $draft)->withInput()->with('error', $message);
        }

        $isMarketplaceProduct = is_marketplace_loan_product($loanProduct->code);
        $isAssetBackedProduct = is_asset_backed_loan_product($loanProduct->code);
        $isGroupProduct = is_group_loan_product($loanProduct);

        $draftPayload = $draft?->payload ?? [];
        $storedSignature = $draftPayload['borrower_signature']
            ?? app(\App\Services\BorrowerSignatureService::class)->profileSignature($customer);
        $declarationAccepted = (bool) ($draftPayload['declaration_accepted'] ?? false)
            || filled($storedSignature['signature_data'] ?? null);
        $groupData = null;

        if ($storedSignature && ! $request->filled('signature_data')) {
            $request->merge([
                'signer_name'    => $storedSignature['signer_name'] ?? $customer->full_name,
                'signature_data' => $storedSignature['signature_data'] ?? '',
                'consent'        => '1',
            ]);
        } elseif ($declarationAccepted && ! $request->boolean('consent')) {
            $request->merge(['consent' => '1']);
        }

        $requirements->mergeSubmitProfileFromCustomer($request, $customer);

        if (! $requirements->hasCompleteResidence($customer)) {
            return redirect()
                ->to($requirements->withReturnUrl(
                    route('site.borrower.profile', ['section' => 'residence']),
                    $returnUrl,
                ))
                ->with('error', __('borrower.apply.errors_residence_incomplete'));
        }

        try {
            $data = $request->validate([
            'loan_product_id'         => ['required', 'exists:loan_products,id'],
            'requested_amount'        => ['required', 'numeric', 'min:1000'],
            'requested_tenure_months' => ['required', 'integer', 'min:1', 'max:60'],
            'purpose'                 => [$isMarketplaceProduct || $isGroupProduct ? 'nullable' : 'required', 'string', 'max:100'],
            'purpose_other'           => ['nullable', 'string', 'max:255'],
            'asset_type'              => [$isAssetBackedProduct ? 'required' : 'nullable', 'string', 'max:40'],
            'asset_description'       => ['nullable', 'string', 'max:500'],
            'first_name'              => ['required', 'string', 'max:60'],
            'last_name'               => ['required', 'string', 'max:60'],
            'date_of_birth'           => ['required', 'date', new MinimumAge],
            'gender'                  => ['nullable', 'string', 'in:male,female,other'],
            'national_id'             => ['required', 'string', 'max:30'],
            'region'                  => ['required', 'string', 'max:100'],
            'district'                => ['required', 'string', 'max:100'],
            'ward'                    => ['nullable', 'string', 'max:100'],
            'street'                  => ['required', 'string', 'max:255'],
            'nok_first_name'   => ['nullable', 'string', 'max:80'],
            'nok_middle_name'  => ['nullable', 'string', 'max:80'],
            'nok_last_name'    => ['nullable', 'string', 'max:80'],
            'nok_name'                => ['required', 'string', 'max:120'],
            'nok_relationship'        => ['required', 'string', 'max:40', 'in:'.implode(',', config('kin.relationships', []))],
            'nok_phone'               => ['required', 'string', 'max:20'],
            'nok_region'              => ['required', 'string', 'max:100'],
            'nok_district'            => ['required', 'string', 'max:100'],
            'activity_type'           => ['required', 'string', 'max:40'],
            'activity_details'        => ['nullable', 'array'],
            'income_range'            => ['required', 'string', 'in:'.implode(',', array_keys(config('income_ranges')))],
            'guarantor_mode'          => ['nullable', 'in:none,internal,external,previous'],
            'internal_member_no'      => ['nullable', 'string', 'max:40'],
            'internal_guarantor_phone'=> ['nullable', 'string', 'max:20'],
            'internal_guarantor_name' => ['nullable', 'string', 'max:120'],
            'external_first_name'     => ['nullable', 'string', 'max:60'],
            'external_middle_name'    => ['nullable', 'string', 'max:60'],
            'external_last_name'      => ['nullable', 'string', 'max:60'],
            'external_name'           => ['nullable', 'string', 'max:120'],
            'external_phone'          => ['nullable', 'string', 'max:20'],
            'external_email'          => ['nullable', 'email', 'max:120'],
            'external_relationship'   => ['nullable', 'string', 'max:40'],
            'external_region'         => ['nullable', 'string', 'max:100'],
            'external_district'       => ['nullable', 'string', 'max:100'],
            'external_channel'        => ['nullable', 'in:sms,whatsapp,email'],
            'external_invitation_id'  => ['nullable', 'integer'],
            'signer_name'             => ['required', 'string', 'max:120'],
            'signature_data'          => ['required', 'string', 'starts_with:data:image/png;base64,'],
            'consent'                 => ['accepted'],
            'product_question'        => ['nullable', 'array'],
            'income_document'         => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'income_document_type'    => ['nullable', 'in:bank,mobile_money'],
            'asset_reservation_id'    => ['nullable', 'integer', 'exists:asset_reservations,id'],
            ]);
        } catch (ValidationException $e) {
            $profileUrl = $requirements->profileActionUrlForValidationErrors($e->errors());
            if ($profileUrl) {
                return redirect()
                    ->to($requirements->withReturnUrl($profileUrl, $returnUrl))
                    ->withInput()
                    ->withErrors($e->errors())
                    ->with('error', __('borrower.apply.kyc_incomplete_submit'));
            }

            return $this->wizardSubmitRedirect($request, $draft)
                ->withInput()
                ->withErrors($e->errors());
        }

        if ($isMarketplaceProduct && blank($data['purpose'] ?? null)) {
            $data['purpose'] = 'asset_financing';
        }

        $purposeOther = trim((string) ($data['purpose_other'] ?? ''));
        $purposeKey = normalize_loan_purpose_key($data['purpose'] ?? null) ?? (string) ($data['purpose'] ?? '');
        $data['purpose'] = $purposeKey;

        if (
            ! $isMarketplaceProduct
            && ! $isGroupProduct
            && is_loan_purpose_other($purposeKey)
            && $purposeOther === ''
        ) {
            // Prefer draft free-text if the hidden field was omitted from the POST.
            $purposeOther = trim((string) ($draftPayload['form']['purpose_other'] ?? ''));
            if ($purposeOther !== '') {
                $data['purpose_other'] = $purposeOther;
            } else {
                return $this->wizardSubmitRedirect($request, $draft)
                    ->withInput()
                    ->withErrors(['purpose_other' => __('borrower.apply.alerts.purpose_other_required')]);
            }
        }
        if (! is_loan_purpose_other($purposeKey)) {
            $purposeOther = '';
        }
        $data['purpose_other'] = $purposeOther;

        if ($isGroupProduct) {
            $groupDraft = $draftPayload['group'] ?? [];
            if (! is_array($groupDraft)) {
                $groupDraft = [];
            }
            // Free-text for "other" lives on the Alpine form, not always inside group draft.
            $groupDraft['purpose_other'] = trim((string) (
                $groupDraft['purpose_other']
                ?? $draftPayload['form']['purpose_other']
                ?? $data['purpose_other']
                ?? ''
            ));
            try {
                $groupData = app(GroupApplyService::class)->validateGroupPayload($customer, $loanProduct, $groupDraft);
                $groupData['members'] = app(GroupMemberInvitationService::class)->resolveMembersForSubmit(
                    $customer,
                    $groupData['members'],
                );
            } catch (ValidationException $e) {
                return $this->wizardSubmitRedirect($request, $draft)
                    ->withInput()
                    ->withErrors($e->errors());
            } catch (\InvalidArgumentException $e) {
                return $this->wizardSubmitRedirect($request, $draft)
                    ->withInput()
                    ->withErrors(['group.members' => $e->getMessage()]);
            }

            $data['purpose'] = $groupData['purpose'];
            $data['purpose_other'] = $groupData['purpose_other'] ?? '';
            $purposeOther = trim((string) $data['purpose_other']);
            $data['requested_amount'] = collect($groupData['members'])->sum('requested_amount');
            $amount = (float) $data['requested_amount'];
        }

        if ($isAssetBackedProduct) {
            if (blank($data['purpose'] ?? null)) {
                return $this->wizardSubmitRedirect($request, $draft)
                    ->withInput()
                    ->withErrors(['purpose' => __('borrower.apply.quote.select_purpose')]);
            }

            $draftForm = $draftPayload['form'] ?? [];
            if (! empty($draftForm['customer_asset_ids']) && is_array($draftForm['customer_asset_ids'])) {
                $data['customer_asset_ids'] = $draftForm['customer_asset_ids'];
            }
            if (filled($draftForm['customer_asset_id'] ?? null)) {
                $data['customer_asset_id'] = $draftForm['customer_asset_id'];
            }
            if (blank($data['asset_type'] ?? null) && filled($draftForm['asset_type'] ?? null)) {
                $data['asset_type'] = $draftForm['asset_type'];
            }
            if (blank($data['asset_description'] ?? null) && filled($draftForm['asset_description'] ?? null)) {
                $data['asset_description'] = $draftForm['asset_description'];
            }
            // Soft request from draft (not a binding offer).
            if (blank($data['requested_amount'] ?? null) && filled($draftForm['requested_amount'] ?? null)) {
                $data['requested_amount'] = $draftForm['requested_amount'];
            }
            if (blank($data['requested_tenure_months'] ?? null) && filled($draftForm['requested_tenure_months'] ?? null)) {
                $data['requested_tenure_months'] = $draftForm['requested_tenure_months'];
            }

            try {
                app(AssetBackedApplyService::class)->validateAssetDetails($customer, $data);
            } catch (ValidationException $e) {
                return $this->wizardSubmitRedirect($request, $draft)
                    ->withInput()
                    ->withErrors($e->errors());
            }

            $valFee = quoted_valuation_fee($customer);
            $valFeeState = $draftPayload['valuation_fee'] ?? null;
            $valFeeService = app(ValuationFeePaymentService::class);

            if (! $valFeeService->isFeeSatisfied($valFeeState, $valFee)) {
                $appFeeState = $draftPayload['application_fee'] ?? null;
                $appFeeService = app(ApplicationFeePaymentService::class);
                $originationDue = quoted_origination_fee($customer, $loanProduct);

                if (! $appFeeService->isFeeSatisfied($appFeeState, $originationDue)) {
                    return $this->wizardSubmitRedirect($request, $draft)->withInput()->withErrors([
                        'application_fee' => __('borrower.apply.application_fee.required_before_submit'),
                    ]);
                }
            }
        }

        $amount = (float) $data['requested_amount'];
        $tenure = (int) $data['requested_tenure_months'];

        if ($amount < $loanProduct->min_amount || $amount > $loanProduct->max_amount) {
            return $this->wizardSubmitRedirect($request, $draft)->withInput()->withErrors(['requested_amount' => 'Requested amount must be between '.format_number($loanProduct->min_amount).' and '.format_number($loanProduct->max_amount).'.']);
        }

        if ($tenure < $loanProduct->tenure_min_months || $tenure > $loanProduct->tenure_max_months) {
            return $this->wizardSubmitRedirect($request, $draft)->withInput()->withErrors(['requested_tenure_months' => 'Tenure must be between '.$loanProduct->tenure_min_months.' and '.$loanProduct->tenure_max_months.' months.']);
        }

        $submittingBorrower = Auth::user()->customer ?? Customer::where('user_id', Auth::id())->first();
        $policy = app(LoanPolicyService::class);
        $guarantorRequired = $policy->requiresGuarantorForApplication($loanProduct, $amount);

        if ($guarantorRequired && ! $submittingBorrower) {
            return $this->wizardSubmitRedirect($request, $draft)->withInput()->withErrors(['guarantor_mode' => __('borrower.apply.alerts.guarantor_lookup_failed')]);
        }

        if ($guarantorRequired) {
            $mode = $data['guarantor_mode'] ?? 'none';
            $draftForm = $draftPayload['form'] ?? [];
            if ($mode === 'none' && filled($draftForm['guarantor_mode'] ?? null)) {
                $mode = (string) $draftForm['guarantor_mode'];
                $data['guarantor_mode'] = $mode;
            }
            if ($mode === 'none') {
                // Borrower submission must not be blocked by incomplete guarantor onboarding.
            } elseif ($mode === 'internal' || $mode === 'previous') {
                if ($mode === 'previous') {
                    $data['guarantor_mode'] = 'internal';
                }
                foreach (['internal_member_no', 'internal_guarantor_phone', 'internal_guarantor_name'] as $field) {
                    if (blank($data[$field] ?? null) && filled($draftForm[$field] ?? null)) {
                        $data[$field] = $draftForm[$field];
                    }
                }
            } elseif ($mode === 'external') {
                foreach ([
                    'external_first_name', 'external_last_name', 'external_phone',
                    'external_relationship', 'external_region', 'external_district',
                    'external_invitation_id',
                ] as $field) {
                    if (blank($data[$field] ?? null) && filled($draftForm[$field] ?? null)) {
                        $data[$field] = $draftForm[$field];
                    }
                }
                $externalDraft = $draftPayload['external_guarantor'] ?? [];
                if (blank($data['external_invitation_id'] ?? null) && filled($externalDraft['invitation_id'] ?? null)) {
                    $data['external_invitation_id'] = $externalDraft['invitation_id'];
                }
                $first = trim($data['external_first_name'] ?? '');
                $last = trim($data['external_last_name'] ?? '');
                if ($first === '' || $last === '') {
                    $legacy = trim($data['external_name'] ?? '');
                    if ($legacy !== '') {
                        $parts = preg_split('/\s+/', $legacy, 3) ?: [];
                        $first = $parts[0] ?? '';
                        $last = $parts[2] ?? ($parts[1] ?? '');
                    }
                }
                if ($first === '' || $last === '' || empty($data['external_phone']) || empty($data['external_relationship']) || empty($data['external_region']) || empty($data['external_district'])) {
                    // Allow submission; guarantor details can be completed independently.
                }
            }
        }

        $user = Auth::user();
        $customer = Customer::firstOrNew(['user_id' => $user->id]);
        $addressLine = trim(collect([$data['street'], $data['ward'] ?? null, $data['district'], $data['region']])->filter()->implode(', '));
        $purposeKey = normalize_loan_purpose_key($data['purpose'] ?? null) ?? (string) ($data['purpose'] ?? '');
        $purposeOther = trim((string) ($data['purpose_other'] ?? ''));
        if (! is_loan_purpose_other($purposeKey)) {
            $purposeOther = '';
        }
        $purposeLabel = loan_purpose_label($purposeKey) ?? $purposeKey;
        $purposeStored = (is_loan_purpose_other($purposeKey) && $purposeOther !== '')
            ? $purposeLabel.': '.$purposeOther
            : $purposeLabel;

        if (! $customer->identity_locked) {
            $customer->fill([
                'first_name'    => $data['first_name'],
                'last_name'     => $data['last_name'],
                'date_of_birth' => $data['date_of_birth'],
                'gender'        => $data['gender'] ?? null,
                'national_id'   => $data['national_id'],
            ]);
        }

        $nokName = filled($data['nok_name'] ?? null)
            ? $data['nok_name']
            : \App\Support\KinName::full($data['nok_first_name'] ?? null, $data['nok_middle_name'] ?? null, $data['nok_last_name'] ?? null);

        $customer->fill([
            'customer_number' => $customer->customer_number ?: 'C-'.strtoupper(Str::random(6)),
            'type'            => 'individual',
            'status'          => $customer->status ?: 'active',
            'email'           => $customer->email ?: $user->email,
            'phone'           => $customer->phone ?: $user->phone,
            'region'          => $data['region'],
            'district'        => $data['district'],
            'ward'            => $data['ward'] ?? null,
            'street'          => $data['street'],
            'address'         => $addressLine,
            'nok_first_name'  => $data['nok_first_name'] ?? $customer->nok_first_name,
            'nok_middle_name' => $data['nok_middle_name'] ?? $customer->nok_middle_name,
            'nok_last_name'   => $data['nok_last_name'] ?? $customer->nok_last_name,
            'nok_name'        => $nokName,
            'nok_relationship'=> $data['nok_relationship'],
            'nok_phone'       => $data['nok_phone'],
            'nok_region'      => $data['nok_region'],
            'nok_district'    => $data['nok_district'],
            'activity_type'   => $data['activity_type'],
            'activity_details'=> filled($data['activity_details'] ?? null)
                ? $data['activity_details']
                : ($customer->activity_details ?? []),
            'employment_type' => $data['activity_type'],
            'income_range'    => $data['income_range'],
            'monthly_income'  => config('income_ranges.'.$data['income_range'].'.midpoint'),
            'onboarded_at'    => $customer->onboarded_at ?: now(),
        ])->save();

        $status = 'submitted';
        $submittedAt = now();

        $appFee = $isGroupProduct && $groupData
            ? app(GroupLendingService::class)->quotedApplicationFee($customer, $loanProduct, count($groupData['members']))
            : quoted_application_fee($customer, $loanProduct);
        $feeState = ($draft?->payload ?? [])['application_fee'] ?? null;
        $feeService = app(ApplicationFeePaymentService::class);

        if (! $feeService->isFeeSatisfied($feeState, $appFee)) {
            return $this->wizardSubmitRedirect($request, $draft)->withInput()->withErrors([
                'application_fee' => __('borrower.apply.application_fee.required_before_submit'),
            ]);
        }

        $feeStatus = $appFee <= 0 ? 'waived' : ($feeState['status'] ?? 'unpaid');
        $feeReference = $feeState['reference'] ?? null;
        $feeChannel = $feeState['channel'] ?? null;
        $feePaidAt = isset($feeState['paid_at']) ? \Carbon\Carbon::parse($feeState['paid_at']) : ($feeStatus === 'paid' ? now() : null);

        // CRB credit pull happens only after capacity/affordability pass (see below).

        $referenceService = app(ReferenceNumberService::class);
        $draftReference = $draft?->draft_reference;

        if ($draftReference) {
            $existingApplication = LoanApplication::query()
                ->where('customer_id', $customer->id)
                ->where('application_number', $draftReference)
                ->first();

            if ($existingApplication) {
                $drafts->clear($customer, (int) $loanProduct->id);

                return redirect()
                    ->route('site.borrower.application', $existingApplication)
                    ->with('status', __('borrower.apply.success.already_submitted_message'));
            }
        }

        $applicationNumber = $referenceService->resolveApplicationReference($loanProduct, $draftReference);
        $engagementBoosts = app(\App\Services\MemberEngagementRewardService::class)->underwritingBoosts($customer);

        $app = LoanApplication::create([
            'customer_id'                => $customer->id,
            'loan_product_id'            => $data['loan_product_id'],
            'application_number'         => $applicationNumber,
            'requested_amount'           => $data['requested_amount'],
            'requested_tenure_months'    => $data['requested_tenure_months'],
            'status'                     => $status,
            'current_stage'              => 'screening',
            'purpose'                    => $purposeStored,
            'screening_payload'          => [
                'product_code'      => $loanProduct->code,
                'product_questions' => array_filter($data['product_question'] ?? []),
                'engagement'        => $engagementBoosts,
                'purpose_key'       => $purposeKey !== '' ? $purposeKey : null,
                'purpose_other'     => (is_loan_purpose_other($purposeKey) && $purposeOther !== '') ? $purposeOther : null,
            ],
            'engagement_priority'        => (int) ($engagementBoosts['processing_priority'] ?? 0),
            'registration_fee_amount'    => 0,
            'registration_fee_status'    => 'waived',
            'registration_fee_channel'   => null,
            'registration_fee_reference' => null,
            'registration_fee_paid_at'   => null,
            'application_fee_amount'     => $appFee,
            'application_fee_status'     => $feeStatus === 'pending' ? 'pending' : ($feeStatus === 'paid' || $feeStatus === 'waived' ? 'paid' : 'unpaid'),
            'application_fee_reference'  => $feeReference,
            'application_fee_channel'    => $feeChannel,
            'application_fee_paid_at'    => $feePaidAt,
            'submitted_at'               => $submittedAt,
        ]);

        if ($request->filled('asset_reservation_id')) {
            $reservation = AssetReservation::query()
                ->where('customer_id', $customer->id)
                ->find($request->input('asset_reservation_id'));
            if ($reservation) {
                app(AssetReservationService::class)->linkApplication($reservation, $app);
            }
        }

        if ($isAssetBackedProduct) {
            app(AssetBackedApplyService::class)->persistOnSubmit($app, array_merge($draftPayload, [
                'form' => array_merge($draftPayload['form'] ?? [], [
                    'customer_asset_id'       => $data['customer_asset_id'] ?? ($draftPayload['form']['customer_asset_id'] ?? null),
                    'customer_asset_ids'      => $data['customer_asset_ids'] ?? ($draftPayload['form']['customer_asset_ids'] ?? null),
                    'asset_type'              => $data['asset_type'] ?? ($draftPayload['form']['asset_type'] ?? null),
                    'asset_description'       => $data['asset_description'] ?? ($draftPayload['form']['asset_description'] ?? null),
                    'requested_amount'        => $data['requested_amount'],
                    'requested_tenure_months' => $data['requested_tenure_months'],
                ]),
            ]));
        }

        if ($isGroupProduct) {
            app(GroupLendingService::class)->createForApplication(
                $app,
                $groupData['members'],
                $groupData['name'],
                loan_purpose_label($groupData['purpose']) ?? $groupData['purpose'],
                (int) ($groupData['target_member_count'] ?? count($groupData['members'])),
            );

            app(GroupMemberInvitationService::class)->attachSignaturesToApplication($app, $groupData['members']);

            $scoring = app(GroupScoringService::class)->score(
                $groupData['members'],
                (int) ($groupData['target_member_count'] ?? count($groupData['members'])),
                $app->fresh(),
            );
            app(GroupApplicationStatusService::class)->syncApplication($app->fresh(['loanGroup']), $scoring);
        }

        app(AffiliateService::class)->trackApplication($app);

        ApplicationSignature::create([
            'loan_application_id' => $app->id,
            'signer_type'         => 'borrower',
            'signer_name'         => $customer->full_name ?: $data['signer_name'],
            'signature_data'      => $data['signature_data'],
            'signed_at'           => now(),
        ]);

        if ($request->hasFile('income_document')) {
            $incomeType = DocumentType::firstOrCreate(
                ['code' => 'income_statement'],
                [
                    'name'       => 'Income statement (6 months)',
                    'category'   => 'kyc',
                    'applies_to' => 'individual',
                    'is_active'  => true,
                ]
            );

            $path = $request->file('income_document')->store(
                "borrower/{$customer->id}/documents",
                'public'
            );

            CustomerDocument::create([
                'customer_id'         => $customer->id,
                'loan_application_id'   => $app->id,
                'document_type_id'      => $incomeType->id,
                'file_path'             => $path,
                'status'                => 'pending',
            ]);
        }

        if ($guarantorRequired && ($data['guarantor_mode'] ?? 'none') !== 'none') {
            try {
                if (($data['guarantor_mode'] ?? '') === 'internal') {
                    $memberKey = \App\Support\MemberNumberFormatter::lookupKey($data['internal_member_no'] ?? '');
                    $inviteId = (int) ($data['internal_invitation_id']
                        ?? ($draft?->payload['internal_guarantor']['invitation_id'] ?? 0));
                    if ($inviteId > 0) {
                        $guarantors->finalizeWizardInternalInvitation($customer, $app, $inviteId);
                    } elseif ($memberKey && filled($data['internal_guarantor_name'] ?? null)) {
                        $guarantors->attachInternal(
                            $customer,
                            $app,
                            $memberKey,
                            $data['internal_guarantor_phone'] ?? '',
                            $data['internal_guarantor_name'] ?? '',
                        );
                    }
                } elseif (($data['guarantor_mode'] ?? '') === 'external') {
                    $inviteId = (int) ($data['external_invitation_id'] ?? 0);
                    if ($inviteId > 0) {
                        $guarantors->finalizeWizardExternalInvitation($customer, $app, $inviteId);
                    } else {
                        $first = trim($data['external_first_name'] ?? '');
                        $last = trim($data['external_last_name'] ?? '');
                        if ($first !== '' && $last !== '' && filled($data['external_phone'] ?? null)) {
                            $guarantors->attachExternal(
                                $customer,
                                $app,
                                $first,
                                trim($data['external_middle_name'] ?? ''),
                                $last,
                                $data['external_phone'],
                                $data['external_email'] ?? null,
                                $data['external_relationship'] ?? '',
                                $data['external_region'] ?? '',
                                $data['external_district'] ?? '',
                                $data['external_channel'] ?? 'whatsapp',
                            );
                        }
                    }
                }
            } catch (\Throwable $e) {
                report($e);
            }
        }

        $guarantorPending = $guarantorRequired
            && ! $guarantors->hasReadyGuarantor($app);

        if ($guarantorPending && app(\App\Services\UnderwritingSettingsService::class)->holdApplicationsUntilGuarantorApproved()) {
            // Hold outside credit screening until guarantor accepts + completes profile.
            // CRB pull waits until release + capacity pass.
            app(\App\Services\GuarantorDeadlineService::class)->markAwaiting($app->fresh());
        } else {
            app(\App\Services\CapacityAutoRejectService::class)->evaluateAndPark($app->fresh(['customer', 'product']));
            $crbCredit->pullAndAttachAfterCapacityPass(
                $app->fresh(['customer', 'product']),
                ($isGroupProduct && $groupData) ? $groupData['members'] : null,
            );
        }

        $message = __('borrower.apply.success.submitted_message');
        if ($guarantorPending) {
            $message = __('borrower.apply.success.submitted_guarantor_pending_message');
        }

        $this->auditBorrower('application.submitted', $app, [
            'product_id' => $loanProduct->id,
            'amount'     => $app->requested_amount,
            'status'     => $app->status,
        ]);

        if ($customer) {
            $drafts->clear($customer, (int) $loanProduct->id);
        }

        $redirect = redirect()->route('site.borrower.application', $app)->with('status', $message);
        if ($guarantorPending) {
            $redirect->with('show_guarantor_remind_modal', 1);
        }

        return \App\Support\Celebration::with($redirect, 'loan_submitted');
    }

    public function success(LoanApplication $application): View
    {
        abort_unless($application->customer && $application->customer->user_id === Auth::id(), 403);
        $application->load('product');
        $underwritingStages = app(SmartLoanApplicationWizardService::class)
            ->underwritingStages($application->current_stage ?? 'submitted');

        $guarantorInvitation = GuarantorInvitation::query()
            ->where('loan_application_id', $application->id)
            ->latest()
            ->first();

        $guarantorService = app(GuarantorInvitationService::class);
        $guarantorShareUrl = $guarantorInvitation
            ? $guarantorService->whatsAppShareUrl($guarantorInvitation, $application->customer)
            : null;
        $guarantorInvitationUrl = $guarantorInvitation
            ? $guarantorService->shortInvitationUrl($guarantorInvitation)
            : null;
        $guarantorSmsUrl = $guarantorInvitation
            ? $guarantorService->smsShareUrl($guarantorInvitation)
            : null;
        $guarantorEmailUrl = $guarantorInvitation
            ? $guarantorService->emailShareUrl($guarantorInvitation)
            : null;

        $trackingShare = app(ApplicationTrackingShareService::class);
        $trackingShareUrl = $trackingShare->whatsAppShareUrl($application);
        $trackingUrl = $trackingShare->trackingUrl($application);
        $combinedShareUrl = ($guarantorInvitation && $guarantorInvitationUrl)
            ? $trackingShare->combinedWhatsAppShareUrl($application, $guarantorInvitation, $guarantorInvitationUrl)
            : null;

        return view('site.apply.success', compact(
            'application',
            'underwritingStages',
            'guarantorInvitation',
            'guarantorShareUrl',
            'guarantorInvitationUrl',
            'guarantorSmsUrl',
            'guarantorEmailUrl',
            'trackingShareUrl',
            'trackingUrl',
            'combinedShareUrl',
        ));
    }

    /** @return list<array{bank: string, account_name: string, account_number: string, branch: ?string, reference: string, instructions: ?string}> */
    private function submitGuarantorSupplement(
        Request $request,
        Customer $customer,
        GuarantorInvitationService $guarantors,
        FaceVerificationService $faces,
        KycFreshnessService $freshness,
        ApplicationRequirementsService $requirements,
    ): RedirectResponse {
        $application = LoanApplication::query()
            ->where('customer_id', $customer->id)
            ->findOrFail((int) $request->input('supplement_application_id'));

        $supplements = app(\App\Services\GuarantorSupplementService::class);
        if (! $supplements->hasOpenRequest($application)) {
            return redirect()
                ->route('site.borrower.application', $application)
                ->with('error', __('borrower.guarantor_supplement.submitted'));
        }

        $returnUrl = $supplements->borrowerWizardUrl($application);
        $checklist = $requirements->checklistForApply($customer, $returnUrl);
        if (! $checklist['can_apply']) {
            return redirect()
                ->to($returnUrl.(str_contains($returnUrl, '?') ? '&' : '?').'profile_gate=1')
                ->with('error', __('borrower.apply.kyc_incomplete_submit'))
                ->with('show_profile_gate', true);
        }

        $identityPolicy = app(\App\Services\IdentityVerificationPolicyService::class);
        if (($identityPolicy->requiredDuringProfileCreation()
                && $identityPolicy->facialRequired()
                && ! $faces->profileStepComplete($customer))
            || ! $freshness->canApply($customer)) {
            return redirect()
                ->to($returnUrl.(str_contains($returnUrl, '?') ? '&' : '?').'profile_gate=1')
                ->with('error', __('borrower.apply.kyc_incomplete_submit'))
                ->with('show_profile_gate', true);
        }

        $data = $request->all();
        $mode = $data['guarantor_mode'] ?? 'none';

        try {
            if ($mode === 'internal' || $mode === 'previous') {
                $memberKey = \App\Support\MemberNumberFormatter::lookupKey($data['internal_member_no'] ?? '');
                if (! $memberKey || blank($data['internal_guarantor_name'] ?? null)) {
                    throw new \InvalidArgumentException(__('borrower.apply.alerts.select_guarantor'));
                }
                $guarantors->attachInternal(
                    $customer,
                    $application,
                    $memberKey,
                    $data['internal_guarantor_phone'] ?? '',
                    $data['internal_guarantor_name'] ?? '',
                );
            } elseif ($mode === 'external') {
                $inviteId = (int) ($data['external_invitation_id'] ?? 0);
                if ($inviteId > 0) {
                    $guarantors->finalizeWizardExternalInvitation($customer, $application, $inviteId);
                } else {
                    $first = trim($data['external_first_name'] ?? '');
                    $last = trim($data['external_last_name'] ?? '');
                    if ($first === '' || $last === '' || blank($data['external_phone'] ?? null)) {
                        throw new \InvalidArgumentException(__('borrower.apply.alerts.select_guarantor'));
                    }
                    $guarantors->attachExternal(
                        $customer,
                        $application,
                        $first,
                        trim($data['external_middle_name'] ?? ''),
                        $last,
                        $data['external_phone'],
                        $data['external_email'] ?? null,
                        $data['external_relationship'] ?? '',
                        $data['external_region'] ?? '',
                        $data['external_district'] ?? '',
                        $data['external_channel'] ?? 'whatsapp',
                    );
                }
            } else {
                throw new \InvalidArgumentException(__('borrower.apply.alerts.select_guarantor'));
            }
        } catch (\InvalidArgumentException $e) {
            return redirect()->to($returnUrl)->withInput()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            report($e);

            return redirect()->to($returnUrl)->withInput()->with('error', __('borrower.apply.alerts.guarantor_lookup_failed'));
        }

        $supplements->markSatisfied($application);

        $this->auditBorrower('application.guarantor_supplement_submitted', $application, [
            'mode' => $mode,
        ]);

        return redirect()
            ->route('site.borrower.application', $application)
            ->with('status', __('borrower.guarantor_supplement.submitted'));
    }

    private function paymentBankAccountsForProduct(?LoanProduct $product, ?string $reference): array
    {
        $ref = $reference ?? app(\App\Services\CustomerPaymentService::class)->generateReference();

        return app(\App\Services\PaymentAccountService::class)
            ->bankAccountsForDisplay('application_fee', $ref, $product);
    }

    private function mergeDraftIntoSubmitRequest(Request $request, ?\App\Models\LoanApplicationDraft $draft): void
    {
        if (! $draft) {
            return;
        }

        $payload = $draft->payload ?? [];
        $form = $payload['form'] ?? [];

        if (! $request->filled('loan_product_id') && $draft->loan_product_id) {
            $request->merge(['loan_product_id' => $draft->loan_product_id]);
        }

        foreach ([
            'requested_amount',
            'requested_tenure_months',
            'purpose',
            'purpose_other',
            'guarantor_mode',
            'internal_member_no',
            'internal_guarantor_phone',
            'internal_guarantor_name',
            'external_first_name',
            'external_middle_name',
            'external_last_name',
            'external_phone',
            'external_email',
            'external_relationship',
            'external_region',
            'external_district',
            'external_invitation_id',
            'internal_invitation_id',
        ] as $field) {
            if (! $request->filled($field) && filled($form[$field] ?? null)) {
                $request->merge([$field => $form[$field]]);
            }
        }

        $externalDraft = $payload['external_guarantor'] ?? [];
        if (! $request->filled('external_invitation_id') && filled($externalDraft['invitation_id'] ?? null)) {
            $request->merge(['external_invitation_id' => $externalDraft['invitation_id']]);
        }

        $internalDraft = $payload['internal_guarantor'] ?? [];
        if (! $request->filled('internal_invitation_id') && filled($internalDraft['invitation_id'] ?? null)) {
            $request->merge(['internal_invitation_id' => $internalDraft['invitation_id']]);
        }

        $storedSignature = $payload['borrower_signature'] ?? null;
        if ($storedSignature && ! $request->filled('signature_data')) {
            $request->merge([
                'signer_name'    => $storedSignature['signer_name'] ?? '',
                'signature_data' => $storedSignature['signature_data'] ?? '',
            ]);
        }

        if (! $request->boolean('consent') && ($payload['declaration_accepted'] ?? false)) {
            $request->merge(['consent' => '1']);
        }
    }

    private function wizardSubmitRedirect(Request $request, ?\App\Models\LoanApplicationDraft $draft): RedirectResponse
    {
        $productId = (int) ($request->input('loan_product_id') ?: $draft?->loan_product_id ?: 0);

        return redirect()->route('site.borrower.apply', array_filter([
            'product'  => $productId ?: null,
            'resume'   => 1,
            'step_key' => 'submit',
        ]));
    }

    private function findExistingSubmittedApplication(
        Customer $customer,
        LoanProduct $loanProduct,
        ?\App\Models\LoanApplicationDraft $draft,
    ): ?LoanApplication {
        $draftReference = $draft?->draft_reference;

        if ($draftReference) {
            $byReference = LoanApplication::query()
                ->where('customer_id', $customer->id)
                ->where('application_number', $draftReference)
                ->first();

            if ($byReference) {
                return $byReference;
            }
        }

        return LoanApplication::query()
            ->where('customer_id', $customer->id)
            ->where('loan_product_id', $loanProduct->id)
            ->whereNotIn('status', ['rejected', 'withdrawn', 'disbursed'])
            ->whereNotNull('submitted_at')
            ->latest('submitted_at')
            ->first();
    }
}
