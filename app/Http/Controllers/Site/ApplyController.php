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
use App\Services\AssetBackedApplyService;
use App\Services\CrbCreditCheckService;
use App\Services\DisplayedRateService;
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

        if ($preselect) {
            $preselectedProduct = LoanProduct::where('is_active', true)
                ->where(function ($query) use ($preselect) {
                    $query->where('id', $preselect)
                          ->orWhere('code', $preselect);
                })
                ->first();

            $preselect = $preselectedProduct?->id;
        }

        $selectedProduct = $preselect ? $products->firstWhere('id', (int) $preselect) : null;
        $reservation = null;
        $assetApplication = null;
        if ($request->filled('reservation') && $customer) {
            $reservation = AssetReservation::query()
                ->where('customer_id', $customer->id)
                ->with('asset')
                ->find($request->query('reservation'));
        }
        if ($preselectedProduct && is_marketplace_loan_product($preselectedProduct->code) && ! $reservation) {
            return redirect()
                ->route('site.borrower.marketplace')
                ->with('status', __('borrower.marketplace.subtitle'));
        }

        if ($reservation && $reservation->status !== 'deposit_paid') {
            $assetKey = $reservation->asset?->slug ?: $reservation->marketplace_asset_id;

            return redirect()
                ->route('site.borrower.marketplace.reserve', $assetKey)
                ->with('warning', __('borrower.marketplace.complete_reservation_first'));
        }

        if ($reservation?->asset) {
            $assetLoanProduct = LoanProduct::where('is_active', true)
                ->where('code', config('asset_marketplace.asset_loan_product_code', 'AL'))
                ->first();

            if ($assetLoanProduct) {
                $selectedProduct = $assetLoanProduct;
                $preselect = $assetLoanProduct->id;

                $asset = $reservation->asset;
                $deposit = (float) ($asset->customer_deposit ?: $asset->computeCustomerDeposit());
                $assetValue = (float) ($asset->asset_value ?: max($deposit * 1.4, $deposit));
                $remainingLoan = max(0, round($assetValue - $deposit, 2));
                $tenure = effective_marketplace_asset_max_tenure($asset);

                $assetApplication = [
                    'asset_title'        => $asset->title,
                    'supplier'           => $asset->supplier_name,
                    'asset_value'        => $assetValue,
                    'deposit'            => $deposit,
                    'remaining_loan'     => $remainingLoan,
                    'weekly_installment' => (float) $asset->weekly_installment,
                    'max_tenure_months'  => $tenure,
                    'purpose'            => 'asset_financing',
                ];
            }
        }

        $stepPlan = collect($wizard->borrowerStepPlan($customer, $selectedProduct))
            ->reject(fn (array $step) => $step['key'] === 'product')
            ->values()
            ->all();
        $incomeVerification = $wizard->incomeVerification($customer);
        $applicationFee = quoted_application_fee($customer, $selectedProduct);
        $productQuestions = config('loan_product_questions', []);
        $readinessUrl = route('site.borrower.apply.product-readiness', ['product' => '__ID__']);

        $applyRequirements = $requirements->checklist($customer);
        $savedDraft = $request->filled('product')
            ? $drafts->payloadForWizard($customer, (int) $request->query('product'))
            : $drafts->payloadForWizard($customer);

        $isResume = $request->boolean('resume');

        if ($isResume && ! $savedDraft && $request->filled('product')) {
            $draft = $drafts->find($customer, (int) $request->query('product'));
            if ($draft && in_array($draft->phase, ['details', 'application'], true)) {
                $savedDraft = $drafts->payloadForWizard($customer, (int) $request->query('product'));
            }
        }

        if ($isResume && ! $savedDraft) {
            return redirect()
                ->route('site.borrower.loans', ['tab' => 'applications'])
                ->with('error', __('borrower.applications_list.resume_not_found'));
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

        $feeQuote = $selectedProduct
            ? app(ApplicationFeePaymentService::class)->quote($customer, $selectedProduct)
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
        ))->with('paymentGatewayDummy', payment_gateway_is_dummy())
            ->with('loanPurposes', loan_purpose_options())
            ->with('marketplaceOnlyCodes', marketplace_only_loan_codes())
            ->with('marketplaceUrl', route('site.borrower.marketplace'))
            ->with('incomeRanges', config('income_ranges'))
            ->with('activityTypes', activity_type_options());
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
            'name'            => ['required', 'string', 'max:120'],
            'loan_product_id' => ['nullable', 'integer', 'exists:loan_products,id'],
        ]);

        $result = $guarantors->verifyInternalMember(
            $borrower,
            $data['membership_no'],
            $data['phone'],
            $data['name'],
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

        if ($message = app(\App\Services\LoanPolicyService::class)->canAcceptGuarantee($result['member'], $draftAmount > 0 ? $draftAmount : null)) {
            return response()->json([
                'ok'      => false,
                'message' => $message,
            ], 422);
        }

        return response()->json([
            'ok'    => true,
            'name'  => $result['name'],
            'label' => $result['label'],
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
            unset($payload['external_guarantor'], $payload['guarantor_lookup']);
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
            'channel'         => ['required', 'in:mobile_money,bank'],
            'payment_phone'   => [$dummyGateway ? 'nullable' : 'required_if:channel,mobile_money', 'nullable', 'string', 'max:20'],
            'use_wallet'      => ['nullable', 'boolean'],
        ]);

        $product = LoanProduct::where('id', $data['loan_product_id'])->where('is_active', true)->firstOrFail();
        $amount = quoted_application_fee($customer, $product);

        if ($amount <= 0) {
            $feeState = ['status' => 'waived', 'reference' => null, 'channel' => 'waived', 'amount' => 0, 'paid_at' => now()->toIso8601String()];
            $drafts->saveApplicationFee($customer, $product->id, $feeState);

            if ($request->expectsJson()) {
                return response()->json(['ok' => true, 'fee' => $feeState]);
            }

            return back()->with('status', __('borrower.apply.application_fee.waived'));
        }

        $paymentReference = $request->session()->get('application_fee_payment_ref')
            ?? $fees->generatePaymentReference();
        $request->session()->put('application_fee_payment_ref', $paymentReference);

        if ($data['channel'] === 'mobile_money') {
            $feeState = $fees->processMobileMoney(
                $customer,
                $product,
                $paymentReference,
                $request->boolean('use_wallet'),
            );
            $drafts->saveApplicationFee($customer, $product->id, $feeState);
            $request->session()->forget('application_fee_payment_ref');

            $message = $dummyGateway
                ? __('borrower.apply.application_fee.dummy_paid')
                : __('borrower.apply.application_fee.paid');

            if ($request->expectsJson()) {
                return response()->json(['ok' => true, 'fee' => $feeState, 'message' => $message, 'dummy' => $dummyGateway]);
            }

            return back()->with('status', $message);
        }

        $feeState = $fees->processBankPending($customer, $product, $paymentReference);
        $drafts->saveApplicationFee($customer, $product->id, $feeState);
        $request->session()->forget('application_fee_payment_ref');

        $bankMessage = ($dummyGateway && ($feeState['status'] ?? '') === 'paid')
            ? __('borrower.apply.application_fee.dummy_paid')
            : __('borrower.apply.application_fee.bank_submitted', ['ref' => $paymentReference]);

        if ($request->expectsJson()) {
            return response()->json([
                'ok'      => true,
                'fee'     => $feeState,
                'message' => $bankMessage,
                'dummy'   => $dummyGateway,
            ]);
        }

        return back()->with(($feeState['status'] ?? '') === 'paid' ? 'status' : 'warning', $bankMessage);
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
            );
            $drafts->saveValuationFee($customer, $product->id, $feeState);
            $request->session()->forget('valuation_fee_payment_ref');

            $message = $dummyGateway
                ? __('borrower.apply.valuation_fee.dummy_paid')
                : __('borrower.apply.valuation_fee.paid');

            if ($request->expectsJson()) {
                return response()->json(['ok' => true, 'fee' => $feeState, 'message' => $message, 'dummy' => $dummyGateway]);
            }

            return back()->with('status', $message);
        }

        $feeState = $fees->processBankPending($customer, $product, $paymentReference);
        $drafts->saveValuationFee($customer, $product->id, $feeState);
        $request->session()->forget('valuation_fee_payment_ref');

        $bankMessage = ($dummyGateway && ($feeState['status'] ?? '') === 'paid')
            ? __('borrower.apply.valuation_fee.dummy_paid')
            : __('borrower.apply.valuation_fee.bank_submitted', ['ref' => $paymentReference]);

        if ($request->expectsJson()) {
            return response()->json([
                'ok'      => true,
                'fee'     => $feeState,
                'message' => $bankMessage,
                'dummy'   => $dummyGateway,
            ]);
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

        return response()->json([
            'amount' => quoted_application_fee($customer, $product),
            'quote'  => $fees->quote($customer, $product),
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
        $rate = app(DisplayedRateService::class)->displayedMonthlyRate($product, $amount);
        $cadence = $product->repayment_cadence ?? 'weekly';
        $rows = $schedules->preview($amount, $rate, $tenure, $cadence);

        $balance = $amount;
        $schedule = [];
        foreach ($rows as $row) {
            $balance = max(0, round($balance - $row['principal_due'], 2));
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

        $quote = $wizard->loanQuote($product, $amount, $tenure);

        return response()->json([
            'ok'            => true,
            'dates_available' => false,
            'schedule'      => $schedule,
            'summary'       => [
                'monthly_rate'        => $rate,
                'monthly_rate_pct'    => round($rate * 100, 2),
                'application_fee'     => quoted_application_fee($customer, $product),
                'monthly_installment' => $quote['monthly_installment'],
                'interest_total'      => $quote['interest_total'],
                'total_repayment'     => $quote['total_repayment'],
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

        if ($customer) {
            $checklist = $requirements->checklist($customer);
            if (! $checklist['can_apply']) {
                return redirect()
                    ->route('site.borrower.dashboard')
                    ->with('error', 'You must complete all loan application requirements before submitting.');
            }
        }

        if ($customer && ! $faces->profileStepComplete($customer)) {
            return redirect()
                ->route('site.borrower.face-verification')
                ->with('error', 'Face verification photos must be submitted before you can submit an application.');
        }

        if ($customer && ! $freshness->canApply($customer)) {
            return redirect()
                ->route('site.borrower.kyc-reconfirm')
                ->with('error', 'Please reconfirm your KYC details before submitting.');
        }

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

        $draftPayload = $draft?->payload ?? [];
        $storedSignature = $draftPayload['borrower_signature'] ?? null;
        $declarationAccepted = (bool) ($draftPayload['declaration_accepted'] ?? false);

        if ($storedSignature && ! $request->filled('signature_data')) {
            $request->merge([
                'signer_name'    => $storedSignature['signer_name'] ?? '',
                'signature_data' => $storedSignature['signature_data'] ?? '',
                'consent'        => '1',
            ]);
        } elseif ($declarationAccepted && ! $request->boolean('consent')) {
            $request->merge(['consent' => '1']);
        }

        abort_unless($customer, 403);
        $requirements->mergeSubmitProfileFromCustomer($request, $customer);

        if (! $requirements->hasCompleteResidence($customer)) {
            return $this->wizardSubmitRedirect($request, $draft)
                ->withInput()
                ->withErrors([
                    'region' => __('borrower.apply.errors_residence_incomplete'),
                ]);
        }

        try {
            $data = $request->validate([
            'loan_product_id'         => ['required', 'exists:loan_products,id'],
            'requested_amount'        => ['required', 'numeric', 'min:1000'],
            'requested_tenure_months' => ['required', 'integer', 'min:1', 'max:60'],
            'purpose'                 => [$isMarketplaceProduct || $isAssetBackedProduct ? 'nullable' : 'required', 'string', 'max:100'],
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
            'nok_name'                => ['required', 'string', 'max:120'],
            'nok_relationship'        => ['required', 'string', 'max:40'],
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
            return $this->wizardSubmitRedirect($request, $draft)
                ->withInput()
                ->withErrors($e->errors());
        }

        if ($isMarketplaceProduct && blank($data['purpose'] ?? null)) {
            $data['purpose'] = 'asset_financing';
        }

        if ($isAssetBackedProduct) {
            if (blank($data['purpose'] ?? null)) {
                $data['purpose'] = 'asset_financing';
            }

            try {
                app(AssetBackedApplyService::class)->validateAssetDetails($data);
            } catch (ValidationException $e) {
                return $this->wizardSubmitRedirect($request, $draft)
                    ->withInput()
                    ->withErrors($e->errors());
            }

            $valFee = quoted_valuation_fee($customer);
            $valFeeState = $draftPayload['valuation_fee'] ?? null;
            $valFeeService = app(ValuationFeePaymentService::class);

            if (! $valFeeService->isFeeSatisfied($valFeeState, $valFee)) {
                return $this->wizardSubmitRedirect($request, $draft)->withInput()->withErrors([
                    'valuation_fee' => __('borrower.apply.valuation_fee.required_before_submit'),
                ]);
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
        $purposeLabel = loan_purpose_label($data['purpose']) ?? $data['purpose'];

        if (! $customer->identity_locked) {
            $customer->fill([
                'first_name'    => $data['first_name'],
                'last_name'     => $data['last_name'],
                'date_of_birth' => $data['date_of_birth'],
                'gender'        => $data['gender'] ?? null,
                'national_id'   => $data['national_id'],
            ]);
        }

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
            'nok_name'        => $data['nok_name'],
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

        $appFee = quoted_application_fee($customer, $loanProduct);
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

        $crbMeta = $crbCredit->ensureFreshForSubmission($customer);

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
                    ->route('site.borrower.apply.success', $existingApplication)
                    ->with('status', __('borrower.apply.success.already_submitted_message'));
            }
        }

        $applicationNumber = $referenceService->resolveApplicationReference($loanProduct, $draftReference);

        $app = LoanApplication::create([
            'customer_id'                => $customer->id,
            'loan_product_id'            => $data['loan_product_id'],
            'application_number'         => $applicationNumber,
            'requested_amount'           => $data['requested_amount'],
            'requested_tenure_months'    => $data['requested_tenure_months'],
            'status'                     => $status,
            'current_stage'              => $status,
            'purpose'                    => $purposeLabel,
            'screening_payload'          => [
                'product_code'      => $loanProduct->code,
                'product_questions' => array_filter($data['product_question'] ?? []),
            ],
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

        $crbCredit->attachToApplication($app, $crbMeta['history'] ?? null, $crbMeta);

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
                    'asset_type'              => $data['asset_type'] ?? ($draftPayload['form']['asset_type'] ?? null),
                    'asset_description'       => $data['asset_description'] ?? ($draftPayload['form']['asset_description'] ?? null),
                    'requested_amount'        => $data['requested_amount'],
                    'requested_tenure_months' => $data['requested_tenure_months'],
                ]),
            ]));
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
                    if ($memberKey && filled($data['internal_guarantor_name'] ?? null)) {
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
            && ! $guarantors->hasApprovedGuarantor($app);

        if ($guarantorPending && app(\App\Services\UnderwritingSettingsService::class)->holdApplicationsUntilGuarantorApproved()) {
            $app->update(['status' => 'awaiting_guarantor']);
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

        return redirect()->route('site.borrower.apply.success', $app)->with('status', $message);
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

        return view('site.apply.success', compact(
            'application',
            'underwritingStages',
            'guarantorInvitation',
            'guarantorShareUrl',
            'guarantorInvitationUrl',
            'guarantorSmsUrl',
            'guarantorEmailUrl',
        ));
    }

    /** @return list<array{bank: string, account_name: string, account_number: string, branch: ?string, reference: string, instructions: ?string}> */
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
        ] as $field) {
            if (! $request->filled($field) && filled($form[$field] ?? null)) {
                $request->merge([$field => $form[$field]]);
            }
        }

        $externalDraft = $payload['external_guarantor'] ?? [];
        if (! $request->filled('external_invitation_id') && filled($externalDraft['invitation_id'] ?? null)) {
            $request->merge(['external_invitation_id' => $externalDraft['invitation_id']]);
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
