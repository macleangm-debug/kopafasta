<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Concerns\AuditsActions;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerAsset;
use App\Models\CustomerDocument;
use App\Models\CustomerGuarantor;
use App\Models\CustomerKyc;
use App\Models\DocumentType;
use App\Models\Guarantor;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\LoanApplicationDraft;
use App\Models\LoanApplicationDocumentRequest;
use App\Models\NotificationLog;
use App\Models\Repayment;
use App\Models\RepaymentSchedule;
use App\Models\LoanProduct;
use App\Models\LoanTopUpRequest;
use App\Models\RestructureRequest;
use App\Models\TrustedDevice;
use App\Support\KinName;
use App\Rules\MinimumAge;
use App\Rules\ValidNationalId;
use App\Support\NationalIdValidator;
use App\Services\ApplicationDocumentRequestService;
use App\Services\ApplicationRequirementsService;
use App\Services\CrbService;
use App\Services\FaceVerificationService;
use App\Services\GuarantorInvitationService;
use App\Services\GuarantorOnboardingService;
use App\Services\GuarantorSignatureService;
use App\Services\KycFreshnessService;
use App\Services\LoanQualificationService;
use App\Services\NidaVerificationService;
use App\Services\PinService;
use App\Services\PostApprovalFeeService;
use App\Services\DocumentPageMerger;
use App\Services\ProfileCompletionService;
use App\Services\ProfileWizardService;
use App\Services\ProfileValidationService;
use App\Services\AffiliateService;
use App\Services\ReferralService;
use App\Services\ResidenceLetterMerger;
use Illuminate\Validation\Rule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class BorrowerController extends Controller
{
    use AuditsActions;

    /* ---------------------------------------------------------------------
     | Helpers
     |---------------------------------------------------------------------*/
    protected function customer(): Customer
    {
        $c = Customer::where('user_id', Auth::id())->first();
        if (! $c) {
            $u = Auth::user();
            $c = Customer::create([
                'user_id'         => $u->id,
                'customer_number' => 'CUS-'.strtoupper(Str::random(6)),
                'first_name'      => $u->name,
                'last_name'       => '',
                'email'           => $u->email,
                'type'            => 'individual',
                'status'          => 'active',
            ]);
        }
        return $c;
    }

    protected function eligibility(Customer $c): array
    {
        return app(LoanQualificationService::class)->calculate($c);
    }

    /* ---------------------------------------------------------------------
     | 1. Dashboard
     |---------------------------------------------------------------------*/
    public function dashboard(
        Request $request,
        LoanQualificationService $qualification,
        ApplicationRequirementsService $requirements,
        ApplicationDocumentRequestService $documentRequests,
    ): View|RedirectResponse {
        $customer = $this->customer();

        if ($redirect = app(\App\Services\PortalOnboardingResumeService::class)->redirectIfPending($request, $customer)) {
            return $redirect;
        }

        $activeLoan = Loan::where('customer_id', $customer->id)
            ->whereIn('status', ['active','disbursed','arrears'])
            ->latest('disbursement_date')->first();

        $nextDue = null;
        if ($activeLoan) {
            $nextDue = RepaymentSchedule::where('loan_id', $activeLoan->id)
                ->where('status', '!=', 'paid')
                ->orderBy('due_date')->first();
        }

        $applicationsCount = LoanApplication::where('customer_id', $customer->id)->count();

        $portal = app(\App\Services\PortalContextService::class);
        $notifications = $portal->borrowerNotificationsQuery($customer)
            ->latest()->limit(4)->get();
        $unreadNotificationCount = $portal->borrowerNotificationsQuery($customer)
            ->whereNull('read_at')->count();

        $eligibility = $qualification->calculate($customer);
        $applyRequirements = $requirements->checklist($customer);
        $onboardingBanner = $requirements->onboardingBanner($customer);
        $groupInviteBanner = app(\App\Services\GroupMemberApplicationService::class)->dashboardBanner($customer);
        $applyDraftResume = app(\App\Services\LoanApplicationDraftService::class)->resumeSummary($customer);

        $activeApplications = LoanApplication::with('product')
            ->where('customer_id', $customer->id)
            ->whereNotIn('status', ['rejected', 'disbursed'])
            ->latest()
            ->limit(5)
            ->get();

        $applicationsDashboard = app(\App\Services\BorrowerApplicationsDashboardService::class);
        $activeApplicationRows = collect(
            $activeApplications->map(fn (LoanApplication $app) => $applicationsDashboard->formatSubmitted($app))->all()
        )
            ->concat(app(\App\Services\GroupMemberApplicationService::class)->applicationRowsForCustomer($customer))
            ->values()
            ->all();

        // Active loan products — public catalogue order
        $products = borrower_catalogue_products();

        $openDocumentRequests = $documentRequests->openRequestsForCustomer($customer);

        $referralService = app(ReferralService::class);
        $referralService->ensureCode($customer);
        $referralCode = $customer->referral_code;
        $referralLink = $referralService->referralLink($customer);
        $referralShareMessage = $referralService->shareMessage($customer);
        $referralWallet = $referralService->wallet($customer);
        $dashboardHero = app(\App\Services\BorrowerDashboardHeroService::class)->forCustomer($customer, $activeLoan, $nextDue);
        $financialSnapshot = app(\App\Services\BorrowerFinancialSnapshotService::class)->forCustomer($customer, $activeLoan);
        $financialHealth = app(\App\Services\BorrowerFinancialHealthService::class)->forCustomer($customer, $activeLoan);
        $kycFreshness = app(KycFreshnessService::class);
        $kycSectionsDue = $kycFreshness->sectionsDueForRefresh($customer);

        return view('site.borrower.dashboard', compact(
            'customer','activeLoan','nextDue','applicationsCount',
            'notifications','eligibility',
            'products','applyRequirements','onboardingBanner','groupInviteBanner','applyDraftResume','activeApplications','activeApplicationRows','unreadNotificationCount',
            'openDocumentRequests','referralCode','referralLink','referralShareMessage','referralWallet','dashboardHero','financialSnapshot','financialHealth','kycSectionsDue',
        ));
    }

    /* ---------------------------------------------------------------------
     | 2. Applications
     |---------------------------------------------------------------------*/
    public function applications(Request $request): RedirectResponse
    {
        return redirect()->route('site.borrower.loans', ['tab' => 'applications']);
    }

    /**
     * Loan profile dashboard for an in-progress draft.
     */
    public function loanProfileDraft(LoanApplicationDraft $draft): View|RedirectResponse
    {
        $customer = $this->customer();
        abort_if($draft->customer_id !== $customer->id, 404);

        $profile = app(\App\Services\LoanApplicationProfileService::class)->forDraft($customer, $draft);

        return view('site.borrower.loan-profile', compact('customer', 'profile'));
    }

    /**
     * Show a single application — unified loan profile dashboard.
     */
    public function application(LoanApplication $application): View
    {
        $customer = $this->customer();
        abort_if($application->customer_id !== $customer->id, 404);

        $profile = app(\App\Services\LoanApplicationProfileService::class)->forApplication($customer, $application);
        $groupFeedback = app(\App\Services\GroupLoanMemberReviewService::class)->leaderFeedbackSummary($application);
        $groupContract = app(\App\Services\GroupMemberReplacementService::class)->leaderDashboard($application, $customer);
        $groupPayout = null;
        $application->loadMissing('loanGroup');
        if ($application->loanGroup) {
            $groupPayout = app(\App\Services\GroupPayoutService::class)->queueForGroup($application->loanGroup);
        }

        return view('site.borrower.loan-profile', compact('customer', 'profile', 'groupFeedback', 'groupContract', 'groupPayout'));
    }

    public function applicationOffer(LoanApplication $application): View
    {
        $customer = $this->customer();
        abort_if($application->customer_id !== $customer->id, 404);
        abort_unless($application->offer_status === 'pending_borrower', 404);

        $application->loadMissing(['product']);
        $installment = app(\App\Services\AffordabilityService::class)->estimateInstallment(
            (float) $application->offered_amount,
            (float) ($application->product?->interest_rate ?? 0),
            (int) ($application->offered_tenure_months ?? $application->requested_tenure_months),
        );

        return view('site.borrower.offer', compact('customer', 'application', 'installment'));
    }

    public function respondToOffer(Request $request, LoanApplication $application): RedirectResponse
    {
        $customer = $this->customer();
        abort_if($application->customer_id !== $customer->id, 404);

        $data = $request->validate(['decision' => ['required', 'in:accept,decline']]);
        $offers = app(\App\Services\ApplicationOfferService::class);

        if ($data['decision'] === 'accept') {
            $application = $offers->acceptOffer($application, $customer);
            $message = __('borrower.offer.accepted');
            $this->auditBorrower('application.offer_accept', $application);

            $readiness = app(\App\Services\ApplicationDisbursementReadinessService::class);
            if ($readiness->needsPostApprovalFees($application)) {
                return redirect()
                    ->route('site.borrower.application.post-approval-fees', $application)
                    ->with('status', $message);
            }

            if ($readiness->needsDisbursementDetailsConfirmation($application)) {
                return redirect()
                    ->route('site.borrower.application.disbursement-details', $application)
                    ->with('status', $message);
            }

            app(\App\Services\LoanAgreementService::class)->ensureLoanContractAfterFees($application);
            if ($readiness->needsContractSignature($application)) {
                return redirect()
                    ->route('site.borrower.application.contract', $application)
                    ->with('status', $message);
            }

            return redirect()
                ->route('site.borrower.application', $application->id)
                ->with('status', $message);
        }

        $offers->declineOffer($application, $customer, $request->input('reason'));
        $message = __('borrower.offer.declined');
        $this->auditBorrower('application.offer_decline', $application);

        return redirect()
            ->route('site.borrower.application', $application->id)
            ->with('status', $message);
    }

    public function assetConversion(LoanApplication $application): View
    {
        $customer = $this->customer();
        abort_if($application->customer_id !== $customer->id, 404);

        $offers = app(\App\Services\ApplicationOfferService::class);
        abort_unless(
            $offers->pendingAssetConversion($application) || $offers->needsConversionFee($application),
            404,
        );

        $application->loadMissing(['product', 'alternativeProduct']);
        $quote = app(\App\Services\ApplicationFeeCreditService::class)->conversionQuote(
            $application,
            $application->alternativeProduct,
        );
        $feeQuote = app(\App\Services\ApplicationConversionFeePaymentService::class)->quote($application);
        $paymentReference = $application->application_number ?? app(\App\Services\CustomerPaymentService::class)->generateReference();
        $accounts = app(\App\Services\PaymentAccountService::class);
        $bankAccounts = $accounts->bankAccountsForDisplay('application_fee', $paymentReference, $application->alternativeProduct);
        $mobileResolved = $accounts->resolve('application_fee', 'mobile_money', $application->alternativeProduct);
        $mobileDetails = $accounts->mobileMoneyDetails($mobileResolved['mobile_money_account'], $paymentReference);
        $needsFee = $offers->needsConversionFee($application) || ($quote['due'] ?? 0) > 0;
        $wallet = app(ReferralService::class)->wallet($customer);
        $referralSettings = app(ReferralService::class)->settings();

        return view('site.borrower.asset-conversion', compact(
            'customer',
            'application',
            'quote',
            'feeQuote',
            'paymentReference',
            'bankAccounts',
            'mobileDetails',
            'needsFee',
            'wallet',
            'referralSettings',
        ));
    }

    public function respondToAssetConversion(Request $request, LoanApplication $application): RedirectResponse
    {
        $customer = $this->customer();
        abort_if($application->customer_id !== $customer->id, 404);

        $data = $request->validate(['decision' => ['required', 'in:accept,decline']]);
        $offers = app(\App\Services\ApplicationOfferService::class);

        if ($data['decision'] === 'accept') {
            $result = $offers->acceptAssetConversion($application, $customer);
            $this->auditBorrower('application.asset_conversion_accept', $application, ['due' => $result['quote']['due'] ?? 0]);

            if ($result['status'] === 'fee_due') {
                return redirect()
                    ->route('site.borrower.application.asset-conversion', $application->id)
                    ->with('status', __('borrower.offer.asset_conversion_fee_due'));
            }

            return redirect()
                ->route('site.borrower.application', $application->id)
                ->with('status', __('borrower.offer.asset_conversion_accepted'));
        }

        $offers->declineAssetConversion($application, $customer);
        $this->auditBorrower('application.asset_conversion_decline', $application);

        return redirect()
            ->route('site.borrower.application', $application->id)
            ->with('status', __('borrower.offer.asset_conversion_declined'));
    }

    public function payAssetConversionFee(Request $request, LoanApplication $application): RedirectResponse
    {
        $customer = $this->customer();
        abort_if($application->customer_id !== $customer->id, 404);

        $offers = app(\App\Services\ApplicationOfferService::class);
        abort_unless($offers->needsConversionFee($application), 422);

        $data = $request->validate([
            'channel'       => ['required', 'in:mobile_money,bank'],
            'mobile_number' => ['required_if:channel,mobile_money', 'nullable', 'string', 'max:20'],
            'payment_date'  => ['nullable', 'date'],
            'proof'         => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'use_wallet'    => ['nullable', 'boolean'],
        ]);

        $paymentService = app(\App\Services\ApplicationConversionFeePaymentService::class);
        $reference = $application->application_number ?? app(\App\Services\CustomerPaymentService::class)->generateReference();
        $useWallet = $request->boolean('use_wallet');

        if ($data['channel'] === 'mobile_money') {
            $result = $paymentService->processMobileMoney($customer, $application, $reference, $useWallet);
            $payment = $result['payment'];

            if (! $payment) {
                return redirect()
                    ->route('site.borrower.application', $application->id)
                    ->with('status', __('borrower.offer.asset_conversion_accepted'));
            }

            return redirect()
                ->route('site.borrower.application', $application->id)
                ->with('status', payment_gateway_is_dummy()
                    ? __('borrower.apply.application_fee.dummy_paid')
                    : __('borrower.apply.application_fee.paid'));
        }

        $result = $paymentService->processBankPending($customer, $application, $reference, $useWallet);
        $payment = $result['payment'];

        if (! $payment) {
            return redirect()
                ->route('site.borrower.application', $application->id)
                ->with('status', __('borrower.offer.asset_conversion_accepted'));
        }

        if ($request->hasFile('proof')) {
            app(\App\Services\CustomerPaymentService::class)->uploadProof($payment, $request->file('proof'));
        }

        return redirect()
            ->route('site.borrower.payments.show', $payment)
            ->with('status', payment_gateway_is_dummy()
                ? __('borrower.apply.application_fee.dummy_paid')
                : __('borrower.apply.application_fee.bank_submitted', ['ref' => $reference]));
    }

    public function uploadDocumentRequest(
        Request $request,
        LoanApplication $application,
        LoanApplicationDocumentRequest $documentRequest,
        ApplicationDocumentRequestService $docRequests,
    ): RedirectResponse {
        $customer = $this->customer();
        abort_if($application->customer_id !== $customer->id, 404);
        abort_if($documentRequest->loan_application_id !== $application->id, 404);

        if (! $documentRequest->needsBorrowerAction()) {
            return back()->withErrors(['upload' => 'This request is no longer open for uploads.']);
        }

        $files = array_filter(array_merge(
            $request->file('files', []) ?? [],
            $request->file('file') ? [$request->file('file')] : [],
        ));

        if ($documentRequest->type === 'clarification') {
            $data = $request->validate([
                'response' => ['nullable', 'string', 'max:2000'],
            ]);

            if (empty($data['response']) && empty($files)) {
                return back()->withErrors(['response' => 'Please provide a written response or upload supporting files.']);
            }

            if (! empty($data['response'])) {
                $docRequests->recordClarification($documentRequest, $data['response']);
            }
        } elseif (empty($files)) {
            return back()->withErrors(['files' => 'Please upload at least one file.']);
        }

        if (! empty($files)) {
            $request->validate([
                'files.*' => ['file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
                'file'    => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            ]);

            $docRequests->recordUploads($documentRequest, $customer, $files);
        }

        $this->auditBorrower('application.document_request_uploaded', $application, [
            'document_request_id' => $documentRequest->id,
            'type'                => $documentRequest->type,
        ]);

        return redirect()
            ->route('site.borrower.application', $application->id)
            ->with('status', 'Submitted — our team will review it shortly.');
    }

    public function uploadApplicationDocument(Request $request, LoanApplication $application): RedirectResponse
    {
        $customer = $this->customer();
        abort_if($application->customer_id !== $customer->id, 404);

        $data = $request->validate([
            'loan_product_requirement_id' => ['required','exists:loan_product_requirements,id'],
            'file'                        => ['required','file','mimes:jpg,jpeg,png,pdf','max:5120'],
            'notes'                       => ['nullable','string','max:500'],
        ]);

        // Make sure the requirement belongs to the application's product
        $requirement = \App\Models\LoanProductRequirement::where('id', $data['loan_product_requirement_id'])
            ->where('loan_product_id', $application->loan_product_id)
            ->firstOrFail();

        $path = $request->file('file')->store(
            "borrower/{$customer->id}/applications/{$application->id}", 'public'
        );

        CustomerDocument::create([
            'customer_id'                 => $customer->id,
            'loan_application_id'         => $application->id,
            'document_type_id'            => null,
            'loan_product_requirement_id' => $requirement->id,
            'file_path'                   => $path,
            'status'                      => 'pending_review',
            'notes'                       => $data['notes'] ?? null,
        ]);

        $this->auditBorrower('application.document_uploaded', $application, [
            'requirement_id' => $requirement->id,
        ]);

        return redirect()
            ->route('site.borrower.application', $application->id)
            ->with('status', 'Uploaded — pending review.');
    }

    /* ---------------------------------------------------------------------
     | 3. My loans
     |---------------------------------------------------------------------*/
    public function loans(Request $request): View
    {
        $customer = $this->customer();

        $portal = app(\App\Services\PortalContextService::class);
        $pendingGuarantorRequests = $portal->pendingGuarantorLinks($customer);
        $guaranteedLinks = app(\App\Services\GuaranteedLoanService::class)->linksForGuarantor($customer);

        $applicationsDashboard = app(\App\Services\BorrowerApplicationsDashboardService::class);
        $applicationRows = $applicationsDashboard->applicationsForCustomer($customer);

        $loans = Loan::with('product')
            ->where('customer_id', $customer->id)
            ->whereIn('status', ['active', 'disbursed', 'arrears'])
            ->latest()
            ->get();

        $allowedTabs = ['applications', 'active'];
        if ($portal->hasGuarantorWork($customer) || $pendingGuarantorRequests->isNotEmpty()) {
            $allowedTabs[] = 'guarantor';
        }
        if ($guaranteedLinks->isNotEmpty()) {
            $allowedTabs[] = 'guaranteed';
        }

        $activeTab = $request->query('tab');
        if (! $activeTab) {
            if ($pendingGuarantorRequests->isNotEmpty() && empty($applicationRows)) {
                $activeTab = 'guarantor';
            } elseif ($guaranteedLinks->isNotEmpty() && empty($applicationRows) && $loans->isEmpty() && $pendingGuarantorRequests->isEmpty()) {
                $activeTab = 'guaranteed';
            } else {
                $activeTab = 'applications';
            }
        }
        if (! in_array($activeTab, $allowedTabs, true)) {
            if ($pendingGuarantorRequests->isNotEmpty()) {
                $activeTab = 'guarantor';
            } elseif ($guaranteedLinks->isNotEmpty()) {
                $activeTab = 'guaranteed';
            } else {
                $activeTab = 'applications';
            }
        }

        $user = Auth::user();
        $viewMode = $request->query('view');
        if (in_array($viewMode, ['cards', 'table'], true)) {
            $prefs = $user->preferences ?? [];
            if ($activeTab === 'active') {
                $prefs['active_loans_view'] = $viewMode;
            } else {
                $prefs['applications_view'] = $viewMode;
            }
            $user->update(['preferences' => $prefs]);
        } elseif ($activeTab === 'active') {
            $viewMode = $user->preferences['active_loans_view'] ?? 'cards';
        } else {
            $viewMode = $user->preferences['applications_view'] ?? 'table';
        }

        $guarantorExposure = ($portal->hasGuarantorWork($customer) || $guaranteedLinks->isNotEmpty())
            ? app(\App\Services\LoanPolicyService::class)->guarantorExposureSummary($customer)
            : null;

        $isGuarantorPortal = $pendingGuarantorRequests->isNotEmpty()
            && empty($applicationRows)
            && $loans->isEmpty()
            && $guaranteedLinks->isEmpty();

        return view('site.borrower.loans', compact(
            'customer',
            'activeTab',
            'applicationRows',
            'viewMode',
            'loans',
            'pendingGuarantorRequests',
            'guaranteedLinks',
            'guarantorExposure',
            'isGuarantorPortal',
        ))->with([
            'showGuarantorTab'   => in_array('guarantor', $allowedTabs, true),
            'showGuaranteedTab'  => in_array('guaranteed', $allowedTabs, true),
        ]);
    }

    public function showGuaranteedLoan(CustomerGuarantor $customerGuarantor): View
    {
        $customer = $this->customer();
        abort_unless(app(\App\Services\GuarantorAccessService::class)->canViewGuarantee($customer, $customerGuarantor), 404);

        $row = app(\App\Services\GuaranteedLoanService::class)->formatLink(
            $customerGuarantor->load([
                'customer',
                'application.product',
                'application.loan.repaymentSchedules',
                'invitation.borrower',
                'invitation.product',
            ])
        );

        $timeline = $row->application
            ? app(\App\Services\ApplicationBorrowerStatusService::class)->timeline($row->application)
            : ['percent' => 0, 'steps' => []];

        return view('site.borrower.guaranteed-show', compact('customer', 'row', 'timeline'));
    }

    /* ---------------------------------------------------------------------
     | 4. Repayment schedule
     |---------------------------------------------------------------------*/
    public function schedule(Request $request, ?Loan $loan = null): View
    {
        $customer = $this->customer();

        if (! $loan || ! $loan->exists) {
            $loan = Loan::where('customer_id', $customer->id)
                ->whereIn('status', ['active','disbursed','arrears'])
                ->latest('disbursement_date')->first()
                ?? Loan::where('customer_id', $customer->id)->latest()->first();
        }

        abort_if($loan && $loan->customer_id !== $customer->id, 404);

        $schedule = $loan
            ? RepaymentSchedule::where('loan_id', $loan->id)->orderBy('installment_no')->get()
            : collect();

        $allLoans = Loan::where('customer_id', $customer->id)->get(['id','loan_number']);

        return view('site.borrower.schedule', compact('customer','loan','schedule','allLoans'));
    }

    public function showLoan(Loan $loan): View
    {
        $customer = $this->customer();
        abort_if($loan->customer_id !== $customer->id, 404);

        $loan->loadMissing(['product', 'repaymentSchedules', 'repayments', 'application']);
        $servicing = app(\App\Services\ActiveLoanServicingService::class)->forLoan($loan);
        $recentRepayments = $loan->repayments()->latest('paid_at')->limit(5)->get();

        $finalContract = null;
        $scheduleAnnex = null;
        if ($loan->loan_application_id) {
            $finalContract = \App\Models\LoanAgreement::query()
                ->where('loan_application_id', $loan->loan_application_id)
                ->where('document_type', 'final_loan_contract')
                ->latest('id')
                ->first();
            $scheduleAnnex = \App\Models\LoanAgreement::query()
                ->where('loan_application_id', $loan->loan_application_id)
                ->where('document_type', 'repayment_schedule')
                ->latest('id')
                ->first();
        }

        $policy = app(\App\Services\LoanPolicyService::class);
        $canRestructure = $policy->canSubmitRestructureRequest($loan) === null;
        $canTopUp = $policy->canSubmitTopUpRequest($loan) === null;
        $timeline = app(\App\Services\LoanServicingTimelineService::class)->forLoan($loan);

        return view('site.borrower.loan-show', compact(
            'customer',
            'loan',
            'servicing',
            'recentRepayments',
            'finalContract',
            'scheduleAnnex',
            'canRestructure',
            'canTopUp',
            'timeline',
        ));
    }

    public function finalContract(Loan $loan)
    {
        $customer = $this->customer();
        abort_if($loan->customer_id !== $customer->id, 404);
        abort_unless($loan->isServicingLocked(), 404);

        $agreement = \App\Models\LoanAgreement::query()
            ->where('loan_application_id', $loan->loan_application_id)
            ->where('document_type', 'final_loan_contract')
            ->where('customer_id', $customer->id)
            ->latest('id')
            ->first();

        abort_unless($agreement?->file_path, 404);

        return redirect()->route('site.borrower.agreement.download', $agreement);
    }

    public function restructureLoan(Loan $loan): View
    {
        $customer = $this->customer();
        abort_unless($loan->customer_id === $customer->id, 404);

        $policy = app(\App\Services\LoanPolicyService::class);
        $blocked = $policy->canSubmitRestructureRequest($loan);

        $loanSettings = $policy->settings();

        return view('site.borrower.loan-restructure', [
            'customer'              => $customer,
            'loan'                  => $loan->loadMissing('product'),
            'blocked'               => $blocked,
            'holidayMaxMonths'      => $loanSettings['payment_holiday_max_months'],
            'holidayAccrueInterest' => $loanSettings['payment_holiday_accrue_interest'],
            'types'                 => [
                'extend_term'     => __('borrower.loan_actions.restructure_types.extend_term'),
                'payment_holiday' => __('borrower.loan_actions.restructure_types.payment_holiday'),
            ],
        ]);
    }

    public function submitRestructure(Request $request, Loan $loan): RedirectResponse
    {
        $customer = $this->customer();
        abort_unless($loan->customer_id === $customer->id, 404);

        $policy = app(\App\Services\LoanPolicyService::class);
        if ($message = $policy->canSubmitRestructureRequest($loan)) {
            return back()->withErrors(['restructure' => $message]);
        }

        $data = $request->validate([
            'restructure_type'  => ['required', 'in:extend_term,payment_holiday'],
            'reason'            => ['required', 'string', 'max:500'],
            'new_tenure_months' => ['nullable', 'integer', 'min:1', 'max:120'],
        ]);

        if ($data['restructure_type'] === 'payment_holiday') {
            if (empty($data['new_tenure_months'])) {
                return back()->withErrors(['new_tenure_months' => __('borrower.loan_actions.holiday_months_required')]);
            }

            $maxMonths = $policy->settings()['payment_holiday_max_months'];
            if ((int) $data['new_tenure_months'] > $maxMonths) {
                return back()->withErrors(['new_tenure_months' => __('borrower.loan_actions.holiday_months_max', ['max' => $maxMonths])]);
            }
        }

        $record = RestructureRequest::create([
            'loan_id'           => $loan->id,
            'customer_id'       => $customer->id,
            'restructure_type'  => $data['restructure_type'],
            'reason'            => $data['reason'],
            'new_tenure_months' => $data['new_tenure_months'] ?? null,
            'status'            => 'pending',
        ]);

        $this->auditBorrower('loan.restructure_requested', $record, [
            'loan_id' => $loan->id,
            'type'    => $data['restructure_type'],
        ]);

        app(\App\Services\StaffNotificationService::class)->notifyLoanModificationRequest(
            'restructure_request',
            'New restructure request — '.$loan->loan_number,
            trim($customer->full_name.' requested '.$data['restructure_type'].' for loan '.$loan->loan_number.'.'),
            '/admin/restructure-requests/'.$record->id,
        );

        app(\App\Services\GuarantorNotificationService::class)->notifyRestructureRequested($loan, $data['restructure_type']);

        return redirect()
            ->route('site.borrower.loans', ['tab' => 'active'])
            ->with('status', __('borrower.loan_actions.restructure_submitted'));
    }

    public function topUpLoan(Loan $loan): View
    {
        $customer = $this->customer();
        abort_unless($loan->customer_id === $customer->id, 404);

        $policy = app(\App\Services\LoanPolicyService::class);
        $blocked = $policy->canSubmitTopUpRequest($loan);
        $available = $blocked ? 0 : $policy->topUpAvailableAmount($loan, $customer);

        return view('site.borrower.loan-top-up', [
            'customer'  => $customer,
            'loan'      => $loan->loadMissing('product'),
            'blocked'   => $blocked,
            'available' => $available,
        ]);
    }

    public function submitTopUp(Request $request, Loan $loan): RedirectResponse
    {
        $customer = $this->customer();
        abort_unless($loan->customer_id === $customer->id, 404);

        $policy = app(\App\Services\LoanPolicyService::class);
        $available = $policy->topUpAvailableAmount($loan, $customer);

        if ($message = $policy->canSubmitTopUpRequest($loan)) {
            return back()->withErrors(['top_up' => $message]);
        }

        $data = $request->validate([
            'requested_amount' => ['required', 'numeric', 'min:1000', 'max:'.$available],
            'reason'           => ['required', 'string', 'max:500'],
        ]);

        $record = LoanTopUpRequest::create([
            'loan_id'          => $loan->id,
            'customer_id'      => $customer->id,
            'requested_amount' => $data['requested_amount'],
            'reason'           => $data['reason'],
            'status'           => 'pending',
        ]);

        $this->auditBorrower('loan.top_up_requested', $record, [
            'loan_id' => $loan->id,
            'amount'  => $data['requested_amount'],
        ]);

        app(\App\Services\StaffNotificationService::class)->notifyLoanModificationRequest(
            'top_up_request',
            'New top-up request — '.$loan->loan_number,
            trim($customer->full_name.' requested a top-up of '.format_money($data['requested_amount']).' on loan '.$loan->loan_number.'.'),
            '/admin/top-up-requests/'.$record->id,
        );

        app(\App\Services\GuarantorNotificationService::class)->notifyTopUpRequested($loan, (float) $data['requested_amount']);

        return redirect()
            ->route('site.borrower.loans', ['tab' => 'active'])
            ->with('status', __('borrower.loan_actions.top_up_submitted'));
    }

    /* ---------------------------------------------------------------------
     | 5. Make payment
     |---------------------------------------------------------------------*/
    public function payments(): View
    {
        $customer = $this->customer();
        $loans = Loan::where('customer_id', $customer->id)
            ->whereIn('status', ['active','disbursed','arrears'])->get();
        return view('site.borrower.payments', compact('customer', 'loans'));
    }

    public function submitPayment(Request $request): RedirectResponse
    {
        $customer = $this->customer();

        $data = $request->validate([
            'loan_id'   => ['required','exists:loans,id'],
            'channel'   => ['required','string','max:30'],
            'amount'    => ['required','numeric','min:100'],
            'reference' => ['required','string','max:60'],
        ]);

        $loan = Loan::where('id', $data['loan_id'])->where('customer_id', $customer->id)->firstOrFail();

        $channels = payment_channels_for_amount((float) $data['amount']);
        if (! in_array($data['channel'], $channels['channels'], true)) {
            return back()
                ->withInput()
                ->withErrors(['channel' => 'Selected payment channel is not allowed for this amount.']);
        }

        $repayment = Repayment::create([
            'loan_id'   => $loan->id,
            'reference' => strtoupper($data['reference']),
            'channel'   => $data['channel'],
            'amount'    => $data['amount'],
            'status'    => 'pending',
            'paid_at'   => now(),
        ]);

        $this->auditBorrower('payment.submitted', $repayment, [
            'loan_id'   => $loan->id,
            'reference' => $repayment->reference,
            'amount'    => $repayment->amount,
        ]);

        return redirect()->route('site.borrower.payments')
            ->with('status', 'Payment submitted. We will confirm it in a few minutes.');
    }

    /* ---------------------------------------------------------------------
     | 6. Documents
     |---------------------------------------------------------------------*/
    public function documents(): View
    {
        $customer = $this->customer();
        $types = DocumentType::where('is_active', true)->orderBy('name')->get();
        $documents = CustomerDocument::with('documentType')
            ->where('customer_id', $customer->id)->latest()->get();
        $verificationSections = collect(app(\App\Services\ProfileCompletionService::class)->displaySections($customer, false))
            ->filter(fn (array $section) => in_array($section['key'], ['personal', 'documents', 'face', 'identity'], true))
            ->values();

        return view('site.borrower.documents', compact('customer', 'types', 'documents', 'verificationSections'));
    }

    public function uploadDocument(Request $request): RedirectResponse
    {
        $customer = $this->customer();

        $data = $request->validate([
            'document_type_id' => ['required','exists:document_types,id'],
            'file'             => ['required','file','mimes:jpg,jpeg,png,pdf','max:5120'],
        ]);

        $path = $request->file('file')->store(
            "borrower/{$customer->id}/documents", 'public'
        );

        $document = CustomerDocument::create([
            'customer_id'      => $customer->id,
            'document_type_id' => $data['document_type_id'],
            'file_path'        => $path,
            'status'           => 'pending',
        ]);

        $this->auditBorrower('document.uploaded', $document, [
            'document_type_id' => $data['document_type_id'],
        ]);

        try {
            $type = $document->documentType;
            app(\App\Services\MemberEngagementRewardService::class)->afterDocumentUploaded(
                $customer,
                (string) ($type?->code ?? 'document_'.$document->id),
            );
        } catch (\Throwable $e) {
            report($e);
        }

        return redirect()->route('site.borrower.documents')
            ->with('status', 'Document uploaded — pending review.');
    }

    /* ---------------------------------------------------------------------
     | 6b. KYC
     |---------------------------------------------------------------------*/
    public function kyc(): View
    {
        $customer = $this->customer();
        $kyc = CustomerKyc::firstOrCreate(
            ['customer_id' => $customer->id],
            ['status' => 'pending', 'payload' => []]
        );

        // Required KYC document types, scoped to the customer's type
        // (individual / business / group). 'any' types apply to everyone.
        $applicable = ['any', $customer->type ?? 'individual'];
        $types = DocumentType::where('is_active', true)
            ->where('category', 'kyc')
            ->whereIn('applies_to', $applicable)
            ->orderBy('name')
            ->get();

        // Existing uploads for those types
        $uploads = CustomerDocument::with('documentType')
            ->where('customer_id', $customer->id)
            ->whereIn('document_type_id', $types->pluck('id'))
            ->latest()
            ->get()
            ->groupBy('document_type_id');

        $required = $types->count();
        $uploaded = $uploads->keys()->count();
        $progress = $required > 0 ? (int) round(($uploaded / $required) * 100) : 0;
        $missing  = $types->reject(fn ($t) => $uploads->has($t->id))->values();

        return view('site.borrower.kyc', compact(
            'customer', 'kyc', 'types', 'uploads',
            'required', 'uploaded', 'progress', 'missing'
        ));
    }

    public function uploadKyc(Request $request): RedirectResponse
    {
        $customer = $this->customer();

        $data = $request->validate([
            'document_type_id' => ['required', 'exists:document_types,id'],
            'file'             => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'notes'            => ['nullable', 'string', 'max:500'],
        ]);

        // Confirm the chosen type is a KYC type
        $type = DocumentType::findOrFail($data['document_type_id']);
        if (($type->category ?? null) !== 'kyc') {
            return back()->withErrors(['document_type_id' => 'That document type is not a KYC document.']);
        }

        $path = $request->file('file')->store("borrower/{$customer->id}/kyc", 'public');

        CustomerDocument::create([
            'customer_id'      => $customer->id,
            'document_type_id' => $type->id,
            'file_path'        => $path,
            'status'           => 'pending_review',
            'notes'            => $data['notes'] ?? null,
        ]);

        // Update KYC envelope
        $kyc = CustomerKyc::firstOrCreate(
            ['customer_id' => $customer->id],
            ['status' => 'pending', 'payload' => []]
        );

        // If every required KYC type now has at least one upload, advance to in_review.
        // Required = types applicable to this customer's type ('any' + customer.type).
        $applicable = ['any', $customer->type ?? 'individual'];
        $requiredIds = DocumentType::where('is_active', true)
            ->where('category', 'kyc')
            ->whereIn('applies_to', $applicable)
            ->pluck('id');
        $uploadedIds = CustomerDocument::where('customer_id', $customer->id)
            ->whereIn('document_type_id', $requiredIds)
            ->pluck('document_type_id')
            ->unique();

        if ($requiredIds->isNotEmpty() && $uploadedIds->count() >= $requiredIds->count() && $kyc->status === 'pending') {
            $kyc->update(['status' => 'in_review']);
        }

        $this->auditBorrower('kyc.uploaded', $customer, [
            'document_type_id' => $type->id,
        ]);

        return redirect()->route('site.borrower.kyc')
            ->with('status', 'KYC document uploaded — our team will review it shortly.');
    }

    /* ---------------------------------------------------------------------
     | 7. Guarantors
     |---------------------------------------------------------------------*/
    public function guarantors(): View
    {
        $customer = $this->customer();
        $links = CustomerGuarantor::with(['guarantor','application'])
            ->where('customer_id', $customer->id)->latest()->get();
        return view('site.borrower.guarantors', compact('customer','links'));
    }

    public function addGuarantor(Request $request): RedirectResponse
    {
        $customer = $this->customer();

        $data = $request->validate([
            'first_name'   => ['required','string','max:60'],
            'last_name'    => ['required','string','max:60'],
            'phone'        => ['required','string','max:20'],
            'email'        => ['nullable','email','max:120'],
            'national_id'  => ['required','string','max:30'],
            'address'      => ['nullable','string','max:255'],
            'relationship' => ['required','string','max:30'],
        ]);

        $guarantor = Guarantor::create($data);

        $link = CustomerGuarantor::create([
            'customer_id'  => $customer->id,
            'guarantor_id' => $guarantor->id,
            'status'       => 'pending',
        ]);

        $this->auditBorrower('guarantor.added', $link, [
            'guarantor_id' => $guarantor->id,
        ]);

        return redirect()->route('site.borrower.guarantors')
            ->with('status', 'Guarantor request sent.');
    }

    /* ---------------------------------------------------------------------
     | 8. Notifications
     |---------------------------------------------------------------------*/
    public function notifications(Request $request): View
    {
        $customer = $this->customer();
        $center = app(\App\Services\NotificationCenterService::class);
        $category = $request->query('category', 'all');
        $groups = $center->groupedForCustomer($customer, $category === 'all' ? null : $category);
        $categories = $center->categories();
        $unreadCount = app(\App\Services\PortalContextService::class)
            ->borrowerNotificationsQuery($customer)
            ->whereNull('read_at')
            ->count();

        return view('site.borrower.notifications', compact('customer', 'groups', 'categories', 'category', 'unreadCount', 'center'));
    }

    public function guarantorNotifications(): View
    {
        $customer = $this->customer();
        $items = app(\App\Services\PortalContextService::class)
            ->guarantorNotificationsQuery($customer)
            ->latest()
            ->paginate(20);

        return view('site.borrower.guarantor-notifications', compact('customer', 'items'));
    }

    public function guarantorMarkNotificationsRead(Request $request): RedirectResponse
    {
        $customer = $this->customer();
        app(\App\Services\PortalContextService::class)
            ->guarantorNotificationsQuery($customer)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return back();
    }

    public function guarantorClearAllNotifications(): RedirectResponse
    {
        $customer = $this->customer();
        app(\App\Services\PortalContextService::class)
            ->guarantorNotificationsQuery($customer)
            ->delete();

        return back()->with('status', 'All guarantor notifications cleared.');
    }

    public function notificationPreview(): \Illuminate\Http\JsonResponse
    {
        $customer = $this->customer();
        $portal = app(\App\Services\PortalContextService::class);
        $items = $portal->borrowerNotificationsQuery($customer)
            ->latest()
            ->limit(8)
            ->get()
            ->map(fn (NotificationLog $n) => [
                'id'       => $n->id,
                'message'  => $n->message ?: $n->template,
                'category' => $n->category ?: 'general',
                'read'     => (bool) $n->read_at,
                'when'     => $n->created_at?->diffForHumans(),
            ]);

        return response()->json([
            'unread' => $portal->borrowerNotificationsQuery($customer)->whereNull('read_at')->count(),
            'items'  => $items,
        ]);
    }

    public function markNotificationRead(NotificationLog $notification): RedirectResponse
    {
        $customer = $this->customer();
        abort_unless($notification->customer_id === $customer->id, 404);
        $notification->update(['read_at' => now()]);

        return back();
    }

    public function clearNotification(NotificationLog $notification): RedirectResponse
    {
        $customer = $this->customer();
        abort_unless($notification->customer_id === $customer->id, 404);
        $notification->delete();

        return back()->with('status', 'Notification removed.');
    }

    public function clearAllNotifications(): RedirectResponse
    {
        $customer = $this->customer();
        app(\App\Services\PortalContextService::class)
            ->borrowerNotificationsQuery($customer)
            ->delete();

        return back()->with('status', 'All notifications cleared.');
    }

    public function markNotificationsRead(Request $request): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        $customer = $this->customer();
        app(\App\Services\PortalContextService::class)
            ->borrowerNotificationsQuery($customer)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        if ($request->wantsJson()) {
            return response()->json(['unread' => 0]);
        }

        return back();
    }

    /* ---------------------------------------------------------------------
     | 9. Profile & KYC
     |---------------------------------------------------------------------*/
    public function profileWizard(): RedirectResponse
    {
        $customer = $this->customer();
        $url = app(ProfileWizardService::class)->resumeUrl($customer);

        return redirect()->to($url);
    }

    public function profile(Request $request, ?string $section = null): View|RedirectResponse
    {
        $customer = $this->customer();
        $section = $section ?? 'hub';

        if ($section === 'hub') {
            return view('site.borrower.profile.hub', compact('customer'));
        }

        $kyc = $customer->kyc ?? CustomerKyc::firstOrCreate(
            ['customer_id' => $customer->id],
            ['status' => 'pending', 'payload' => []]
        );

        $wizardMode = $request->boolean('wizard');

        if ($section === 'membership') {
            $referrals = app(\App\Services\ReferralService::class);

            return view('site.borrower.profile.membership', [
                'customer'       => $customer,
                'history'        => $customer->membershipHistories()->latest()->limit(20)->get(),
                'referralLink'   => $referrals->referralLink($customer),
                'referralCode'   => $referrals->ensureCode($customer),
                'referralWallet' => $referrals->wallet($customer),
            ]);
        }

        if ($section === 'kin') {
            return view('site.borrower.profile.kin', compact('customer', 'wizardMode'));
        }

        $section = in_array($section, ['personal', 'activity', 'residence', 'kyc', 'security', 'payment', 'assets'], true)
            ? $section
            : 'personal';

        $view = match ($section) {
            'activity'  => 'site.borrower.profile.activity',
            'residence' => 'site.borrower.profile.residence',
            'kyc'       => 'site.borrower.profile.kyc',
            'security'  => 'site.borrower.profile.security',
            'payment'   => 'site.borrower.profile.payment',
            'assets'    => 'site.borrower.profile.assets',
            default     => 'site.borrower.profile.personal',
        };

        $trustedDevices = $section === 'security'
            ? TrustedDevice::where('user_id', auth()->id())->where('expires_at', '>', now())->latest('last_used_at')->get()
            : collect();

        $nidaDocuments = app(\App\Services\ProfileDocumentService::class)
            ->latestByCodes($customer, ['national_id_front']);

        $employmentContract = app(\App\Services\ProfileDocumentService::class)
            ->latestByCodes($customer, ['employment_contract'])
            ->get('employment_contract');

        $residenceLetter = app(\App\Services\ProfileDocumentService::class)
            ->latestByCodes($customer, ['residence_letter'])
            ->get('residence_letter');

        $incomeProofChecklist = app(\App\Services\IncomeProofService::class)->checklist($customer);
        $incomeProofEmployed = app(\App\Services\IncomeProofService::class)->isEmployed($customer);
        $incomeProofMethod = app(\App\Services\IncomeProofService::class)->selectedPrimaryMethod($customer);
        $incomePrimaryOptions = app(\App\Services\IncomeProofService::class)->informalPrimaryOptions();
        $completionSummary = app(\App\Services\ProfileCompletionService::class)->completionSummary($customer);
        $returnUrl = $request->query('return');
        $wizardKey = match ($section) {
            'activity'  => 'activity',
            'residence' => 'residence',
            'kyc'       => 'documents',
            default     => $request->query('focus') === 'kin' ? 'kin' : 'nida',
        };

        $detailsService = app(\App\Services\CustomerDisbursementDetailsService::class);
        $borrowerLegalName = $customer->legalDisplayName() ?? trim(($customer->first_name ?? '').' '.($customer->last_name ?? ''));

        $faceSteps = null;
        $faceUploadUrls = null;
        $faceWizard = null;
        $facePhotos = null;
        $faceAngles = null;

        if ($section === 'personal') {
            $faces = app(\App\Services\FaceVerificationService::class);
            $facePhotos = $faces->latestByAngle($customer);
            $faceAngles = $faces->angles();
            $faceWizard = $faces->wizardState($customer);
            $faceUploadUrls = collect($faceWizard['order'])->mapWithKeys(fn (string $key) => [
                $key => route('site.borrower.face-verification.store', ['angle' => $key]),
            ])->all();
            $faceSteps = collect($faceWizard['order'])->map(function (string $key) use ($faceAngles, $facePhotos) {
                $meta = $faceAngles[$key] ?? [];

                return [
                    'key'         => $key,
                    'label'       => $meta['label'] ?? $key,
                    'step_title'  => $meta['step_title'] ?? ($meta['label'] ?? $key),
                    'instruction' => $meta['instruction'] ?? '',
                    'pose'        => match ($key) {
                        'left'  => 'left',
                        'right' => 'right',
                        default => 'front',
                    },
                    'done'        => isset($facePhotos[$key]) && ($facePhotos[$key]->status ?? '') !== 'rejected',
                ];
            })->values()->all();
        }

        return view($view, compact('customer', 'kyc', 'trustedDevices', 'nidaDocuments', 'employmentContract', 'residenceLetter', 'incomeProofChecklist', 'incomeProofEmployed', 'incomeProofMethod', 'incomePrimaryOptions', 'completionSummary', 'returnUrl', 'wizardMode', 'wizardKey', 'faceSteps', 'faceUploadUrls', 'faceWizard', 'facePhotos', 'faceAngles'))
            ->with('editing', $wizardMode || $request->boolean('edit'))
            ->with('crbUsesStub', app(CrbService::class)->usesStub())
            ->with('crbSamples', config('crb_samples.scenarios', []))
            ->with('profileSections', app(ProfileCompletionService::class)->displaySections($customer))
            ->with('paymentAccounts', $section === 'payment' ? $detailsService->accountsForCustomer($customer) : collect())
            ->with('borrowerLegalName', $borrowerLegalName)
            ->with('detailsService', $detailsService)
            ->with('assets', $section === 'assets' ? app(\App\Services\CustomerAssetService::class)->forCustomer($customer) : collect())
            ->with('assetTypes', \App\Models\CustomerAsset::typeOptions());
    }

    public function updateProfile(Request $request, string $section = 'personal'): RedirectResponse
    {
        $customer = $this->customer();

        if ($section === 'kin') {
            $data = $request->validate([
                'nok_first_name'   => ['required', 'string', 'max:80'],
                'nok_middle_name'  => ['nullable', 'string', 'max:80'],
                'nok_last_name'    => ['required', 'string', 'max:80'],
                'nok_relationship' => ['required', 'string', 'max:60'],
                'nok_phone'        => ['required', 'string', 'max:30'],
                'nok_region'       => ['required', 'string', 'max:100'],
                'nok_district'     => ['required', 'string', 'max:100'],
                'nok_ward'         => ['nullable', 'string', 'max:100'],
                'nok_street'       => ['required', 'string', 'max:255'],
            ]);

            $customer->fill([
                'nok_first_name'   => $data['nok_first_name'],
                'nok_middle_name'  => $data['nok_middle_name'] ?? null,
                'nok_last_name'    => $data['nok_last_name'],
                'nok_name'         => \App\Support\KinName::full($data['nok_first_name'], $data['nok_middle_name'] ?? null, $data['nok_last_name']),
                'nok_relationship' => $data['nok_relationship'],
                'nok_phone'        => $data['nok_phone'],
                'nok_region'       => $data['nok_region'],
                'nok_district'     => $data['nok_district'],
                'nok_ward'         => $data['nok_ward'] ?? null,
                'nok_street'       => $data['nok_street'],
            ])->save();

            app(KycFreshnessService::class)->markSectionConfirmed($customer->fresh(), 'kin');

            try {
                app(\App\Services\MemberEngagementRewardService::class)->afterProfileSectionSaved($customer->fresh(), 'kin');
            } catch (\Throwable $e) {
                report($e);
            }

            return redirect()
                ->route('site.borrower.profile', ['section' => 'kin'])
                ->with('status', __('borrower.profile.kin_saved'));
        }

        $section = in_array($section, ['personal', 'activity', 'residence', 'kyc', 'payment'], true) ? $section : 'personal';
        $validation = app(ProfileValidationService::class);

        if ($section === 'personal') {
            $focus = (string) $request->input('focus', 'all');
            $identityRequired = app(\App\Services\ProfileCompletionService::class)->identityRequiredDuringProfile();
            $kinRequired = in_array($focus, ['kin', 'all'], true) && (! $request->boolean('wizard') || $request->input('focus') === 'kin');

            $rules = [
                'phone' => ['nullable', 'string', 'max:20'],
                'email' => ['nullable', 'email', 'max:120'],
                'national_id' => ['nullable', 'string', 'max:30', new \App\Rules\ValidNidaNumber],
                'nok_first_name'   => [$kinRequired ? 'required' : 'nullable', 'string', 'max:80'],
                'nok_middle_name'  => ['nullable', 'string', 'max:80'],
                'nok_last_name'    => [$kinRequired ? 'required' : 'nullable', 'string', 'max:80'],
                'nok_relationship' => [$kinRequired ? 'required' : 'nullable', 'string', 'max:60'],
                'nok_phone'        => [$kinRequired ? 'required' : 'nullable', 'string', 'max:30'],
                'nok_region'       => [$kinRequired ? 'required' : 'nullable', 'string', 'max:100'],
                'nok_district'     => [$kinRequired ? 'required' : 'nullable', 'string', 'max:100'],
                'nok_ward'         => ['nullable', 'string', 'max:100'],
                'nok_street'       => [$kinRequired ? 'required' : 'nullable', 'string', 'max:255'],
                'national_id_front' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            ];

            $data = $request->validate($rules);

            if (in_array($focus, ['contact', 'all'], true)) {
                $customer->fill(array_filter([
                    'phone' => $data['phone'] ?? $customer->phone,
                    'email' => $data['email'] ?? $customer->email,
                ], fn ($value) => $value !== null));
            }

            if (in_array($focus, ['identity', 'all'], true) && filled($data['national_id'] ?? null) && ! $customer->identity_locked) {
                $customer->national_id = $data['national_id'];
            }

            if (in_array($focus, ['kin', 'all'], true)) {
                $customer->fill(array_filter([
                    'nok_first_name'   => $data['nok_first_name'] ?? null,
                    'nok_middle_name'  => $data['nok_middle_name'] ?? null,
                    'nok_last_name'    => $data['nok_last_name'] ?? null,
                    'nok_name'         => KinName::full($data['nok_first_name'] ?? null, $data['nok_middle_name'] ?? null, $data['nok_last_name'] ?? null) ?: null,
                    'nok_relationship' => $data['nok_relationship'] ?? null,
                    'nok_phone'        => $data['nok_phone'] ?? null,
                    'nok_region'       => $data['nok_region'] ?? null,
                    'nok_district'     => $data['nok_district'] ?? null,
                    'nok_ward'         => $data['nok_ward'] ?? null,
                    'nok_street'       => $data['nok_street'] ?? null,
                ], fn ($value) => $value !== null));
            }

            $customer->save();

            if (in_array($focus, ['identity', 'all'], true)) {
                $this->persistProfileDocumentUpload($customer, 'national_id_front', $request->file('national_id_front'), []);

                if ($identityRequired && ! $validation->nationalIdUploadsComplete($customer->fresh())) {
                    return redirect()
                        ->route('site.borrower.profile', ['section' => 'personal'])
                        ->withErrors(['national_id_front' => __('borrower.profile.nida_upload_required')])
                        ->withInput()
                        ->withFragment('profile-identity');
                }
            }
        }

        if ($section === 'activity') {
            $employed = $request->input('activity_type') === 'employed';
            $hasContract = $validation->hasDocument($customer, 'employment_contract');
            $contractPages = array_values(array_filter($request->file('employment_contract_pages', []) ?? []));
            $needsContract = $employed && ! $hasContract && ! $request->hasFile('employment_contract') && $contractPages === [];

            $data = $request->validate([
                'activity_type'    => ['required', 'string', 'max:40'],
                'activity_details' => ['nullable', 'array'],
                'income_range'     => ['required', 'string', 'in:'.implode(',', array_keys(config('income_ranges')))],
                'employment_contract' => [
                    Rule::requiredIf($needsContract),
                    'nullable',
                    'file',
                    'mimes:jpg,jpeg,png,pdf',
                    'max:5120',
                ],
                'employment_contract_pages' => ['nullable', 'array'],
                'employment_contract_pages.*' => ['file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            ]);

            $customer->fill([
                'activity_type'    => $data['activity_type'],
                'activity_details' => $data['activity_details'] ?? [],
                'employment_type'  => $data['activity_type'],
                'income_range'     => $data['income_range'],
                'monthly_income'   => config('income_ranges.'.$data['income_range'].'.midpoint'),
            ])->save();

            $this->persistProfileDocumentUpload(
                $customer,
                'employment_contract',
                $request->file('employment_contract'),
                $request->file('employment_contract_pages', []) ?? []
            );

            if ($employed && ! $validation->employmentContractComplete($customer->fresh())) {
                return redirect()
                    ->route('site.borrower.profile', ['section' => 'activity'])
                    ->withErrors(['employment_contract' => __('borrower.profile.employment_contract_required')])
                    ->withInput();
            }

            app(KycFreshnessService::class)->markSectionConfirmed($customer->fresh(), 'activity');
        }

        if ($section === 'residence') {
            $data = $request->validate([
                'region'   => ['required', 'string', 'max:100'],
                'district' => ['required', 'string', 'max:100'],
                'ward'     => ['nullable', 'string', 'max:100'],
                'street'   => ['required', 'string', 'max:255'],
                'residence_letter' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
                'residence_letter_pages' => ['nullable', 'array'],
                'residence_letter_pages.*' => ['file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            ]);
            $customer->fill([
                'region'   => $data['region'],
                'district' => $data['district'],
                'ward'     => $data['ward'] ?? null,
                'street'   => $data['street'],
                'address'  => trim(collect([$data['street'], $data['ward'] ?? null, $data['district'], $data['region']])->filter()->implode(', ')),
            ])->save();

            $pageFiles = array_values(array_filter($request->file('residence_letter_pages', []) ?? []));
            $this->persistProfileDocumentUpload(
                $customer,
                'residence_letter',
                $request->file('residence_letter'),
                $pageFiles,
            );

            $residenceParams = array_filter([
                'section' => 'residence',
                'wizard'  => $request->boolean('wizard') ? 1 : null,
            ]);

            if ($validation->requiresResidenceLetter() && ! $validation->hasResidenceLetter($customer->fresh())) {
                return redirect()
                    ->route('site.borrower.profile', $residenceParams)
                    ->with('status', __('borrower.profile.residence_address_saved'))
                    ->withErrors(['residence_letter' => __('borrower.profile.residence_letter_required')])
                    ->withInput();
            }

            app(KycFreshnessService::class)->markSectionConfirmed($customer->fresh(), 'residence');
        }

        if ($section === 'kyc') {
            $pageRules = ['nullable', 'array'];
            $pageItemRules = ['file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'];
            $codes = [
                'bank_statement',
                'mobile_money_statement',
                'salary_slip',
                'business_license',
                'business_registration',
                'tin_certificate',
                'vat_certificate',
                'business_photos',
                'workshop_photos',
            ];
            $rules = [
                'income_proof_method' => ['nullable', 'string', 'in:bank_statement,mobile_money_statement'],
            ];
            foreach ($codes as $code) {
                $rules[$code] = ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'];
                $rules[$code.'_pages'] = $pageRules;
                $rules[$code.'_pages.*'] = $pageItemRules;
            }
            $request->validate($rules);

            if ($request->filled('income_proof_method')) {
                $details = $customer->activity_details ?? [];
                $details['income_proof_method'] = $request->input('income_proof_method');
                $customer->update(['activity_details' => $details]);
            }

            foreach ($codes as $code) {
                $this->persistProfileDocumentUpload(
                    $customer,
                    $code,
                    $request->file($code),
                    $request->file($code.'_pages', []) ?? [],
                );
            }

            app(KycFreshnessService::class)->markSectionConfirmed($customer->fresh(), 'documents');
        }

        if ($section === 'payment') {
            return $this->storePaymentAccount($request, $customer);
        }

        if ($section === 'personal') {
            app(KycFreshnessService::class)->markSectionConfirmed($customer->fresh(), 'kin');
        }

        $this->auditBorrower('profile.updated', $customer, ['section' => $section]);

        try {
            app(\App\Services\MemberEngagementRewardService::class)->afterProfileSectionSaved($customer->fresh(), $section);
        } catch (\Throwable $e) {
            report($e);
        }

        if ($return = $this->validatedReturnUrl($request)) {
            $redirect = redirect($return)->with('status', __('borrower.profile.saved_return'));
        } elseif ($request->boolean('wizard')) {
            $redirect = $this->redirectWizardStep($request, $customer, $section);
        } else {
            $profileRedirect = redirect()
                ->route('site.borrower.profile', array_filter(['section' => $section !== 'personal' ? $section : 'personal']))
                ->with('status', __('borrower.profile.save_confirm_title'));

            $fragment = match ($section) {
                'personal' => match ((string) $request->input('focus')) {
                    'contact'  => 'profile-contact',
                    'kin'      => 'profile-kin',
                    'identity' => 'profile-identity',
                    default    => null,
                },
                default => null,
            };

            if ($fragment) {
                $profileRedirect = $profileRedirect->withFragment($fragment);
            }

            $redirect = $this->redirectWithGuarantorResume($request, $customer, $profileRedirect);
        }

        if (app(\App\Services\ProfileCompletionService::class)->isFullyComplete($customer->fresh())) {
            \App\Support\Celebration::flashOne('profile_complete');
        }

        return $redirect;
    }

    private function redirectWizardStep(Request $request, Customer $customer, string $section): RedirectResponse
    {
        $wizard = app(ProfileWizardService::class);
        $currentKey = match ($section) {
            'activity'  => 'activity',
            'residence' => 'residence',
            'kyc'       => 'documents',
            default     => $request->input('focus') === 'kin' ? 'kin' : 'nida',
        };

        $next = $wizard->navigation($customer->fresh(), $currentKey)['next'];

        if ($next) {
            return redirect()->to($next['url'])->with('status', __('borrower.profile_wizard.saved_continue'));
        }

        return $this->redirectWithGuarantorResume(
            $request,
            $customer,
            redirect()->route('site.borrower.dashboard')->with('status', __('borrower.profile_wizard.complete')),
        );
    }

    private function redirectWithGuarantorResume(Request $request, Customer $customer, RedirectResponse $default): RedirectResponse
    {
        if ($redirect = app(\App\Services\PortalOnboardingResumeService::class)->redirectIfPending($request, $customer)) {
            return $redirect;
        }

        return $default;
    }

    private function storePaymentAccount(Request $request, Customer $customer): RedirectResponse
    {
        $detailsService = app(\App\Services\CustomerDisbursementDetailsService::class);
        $type = $request->input('type', $request->input('preferred_disbursement_method'));
        $data = $request->validate($detailsService->validationRules($type, $customer));

        try {
            $detailsService->createAccount($customer, $data);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        $this->auditBorrower('profile.payment_account_added', $customer, ['type' => $type]);

        if ($return = $this->validatedReturnUrl($request)) {
            return redirect($return)->with('status', __('borrower.payment_details.account_saved'));
        }

        return redirect()
            ->route('site.borrower.profile', ['section' => 'payment'])
            ->with('status', __('borrower.payment_details.account_saved'));
    }

    public function destroyPaymentAccount(Request $request, \App\Models\CustomerDisbursementAccount $account): RedirectResponse
    {
        $customer = $this->customer();
        abort_if((int) $account->customer_id !== (int) $customer->id, 404);

        app(\App\Services\CustomerDisbursementDetailsService::class)->deleteAccount($customer, $account);
        $this->auditBorrower('profile.payment_account_removed', $customer, ['account_id' => $account->id]);

        if ($return = $this->validatedReturnUrl($request)) {
            return redirect($return)->with('status', __('borrower.payment_details.account_removed'));
        }

        return redirect()
            ->route('site.borrower.profile', ['section' => 'payment'])
            ->with('status', __('borrower.payment_details.account_removed'));
    }

    private function validatedReturnUrl(Request $request): ?string
    {
        $return = (string) ($request->query('return') ?? $request->input('return') ?? '');
        if ($return === '') {
            return null;
        }

        $path = parse_url($return, PHP_URL_PATH) ?: '';
        if (! str_starts_with($path, '/')) {
            return null;
        }

        return $return;
    }

    public function verifyNida(Request $request, NidaVerificationService $nida): RedirectResponse
    {
        $customer = $this->customer();

        $data = $request->validate([
            'national_id' => ['required', 'string', 'max:30', new ValidNationalId($customer->country_code)],
        ]);

        $data['national_id'] = NationalIdValidator::format($data['national_id'], $customer->country_code)
            ?? $data['national_id'];

        if ($message = $nida->assertCanVerify($customer)) {
            return redirect()
                ->route('site.borrower.profile', ['section' => 'personal'])
                ->with('nida_result', ['status' => 'locked', 'message' => $message]);
        }

        $result = $nida->verify($customer, $data['national_id']);

        $this->auditBorrower('nida.verification_attempted', $customer, [
            'status' => $result->status ?? ($result->success ? 'verified' : 'failed'),
        ]);

        if ($result->success) {
            return $this->redirectWithGuarantorResume(
                $request,
                $customer->fresh(),
                redirect()
                    ->route('site.borrower.profile', ['section' => 'personal'])
                    ->with('nida_result', ['status' => 'verified']),
            );
        }

        if ($result->status === 'name_mismatch') {
            return $this->nidaMismatchRedirect($customer, $nida);
        }

        if ($result->isMultihit()) {
            return redirect()
                ->route('site.borrower.profile', ['section' => 'personal'])
                ->with('nida_result', ['status' => 'multihit', 'message' => $result->message])
                ->with('crb_candidates', $result->candidates)
                ->with('crb_search_request_id', $result->raw['search_request_id'] ?? null);
        }

        return redirect()
            ->route('site.borrower.profile', ['section' => 'personal'])
            ->with('nida_result', ['status' => 'failed', 'message' => $result->message ?? 'NIDA verification failed. Please check your number and try again.']);
    }

    public function acceptNidaNames(NidaVerificationService $nida): RedirectResponse
    {
        return redirect()
            ->route('site.borrower.profile', ['section' => 'personal'])
            ->with('nida_result', [
                'status'  => 'failed',
                'message' => __('borrower.nida.mismatch_no_override'),
            ]);
    }

    public function confirmNidaCandidate(Request $request, NidaVerificationService $nida): RedirectResponse
    {
        $customer = $this->customer();

        $data = $request->validate([
            'national_id'       => ['required', 'string', 'max:30', new ValidNationalId($customer->country_code)],
            'search_request_id' => ['required', 'string', 'max:120'],
            'entity_key'        => ['required', 'string', 'max:80'],
        ]);

        $data['national_id'] = NationalIdValidator::format($data['national_id'], $customer->country_code)
            ?? $data['national_id'];

        if ($message = $nida->assertCanVerify($customer)) {
            return redirect()
                ->route('site.borrower.profile', ['section' => 'personal'])
                ->with('nida_result', ['status' => 'locked', 'message' => $message]);
        }

        $result = $nida->confirmCandidate(
            $customer,
            $data['national_id'],
            $data['search_request_id'],
            $data['entity_key'],
        );

        $this->auditBorrower('nida.candidate_confirmed', $customer, [
            'status' => $result->status ?? ($result->success ? 'verified' : 'failed'),
        ]);

        if ($result->success) {
            return $this->redirectWithGuarantorResume(
                $request,
                $customer->fresh(),
                redirect()
                    ->route('site.borrower.profile', ['section' => 'personal'])
                    ->with('nida_result', ['status' => 'verified']),
            );
        }

        if ($result->status === 'name_mismatch') {
            return $this->nidaMismatchRedirect($customer, $nida);
        }

        return redirect()
            ->route('site.borrower.profile', ['section' => 'personal'])
            ->with('nida_result', ['status' => 'failed', 'message' => $result->message ?? 'Could not confirm the selected match.']);
    }

    public function faceVerification(Request $request, FaceVerificationService $faces): View
    {
        $customer = $this->customer();
        $wizardMode = $request->boolean('wizard');
        $photos = $faces->latestByAngle($customer);
        $progress = $faces->progress($customer);
        $status = $faces->statusLabel($customer);
        $angles = $faces->angles();
        $wizard = $faces->wizardState($customer);

        $uploadUrls = collect($wizard['order'])->mapWithKeys(fn (string $key) => [
            $key => route('site.borrower.face-verification.store', ['angle' => $key]),
        ])->all();

        $steps = collect($wizard['order'])->map(function (string $key) use ($angles, $photos) {
            $meta = $angles[$key] ?? [];

            return [
                'key'         => $key,
                'label'       => $meta['label'] ?? $key,
                'step_title'  => $meta['step_title'] ?? ($meta['label'] ?? $key),
                'instruction' => $meta['instruction'] ?? '',
                'pose'        => match ($key) {
                    'left'  => 'left',
                    'right' => 'right',
                    default => 'front',
                },
                'done'        => isset($photos[$key]) && ($photos[$key]->status ?? '') !== 'rejected',
            ];
        })->values()->all();

        return view('site.borrower.face-verification', compact(
            'customer', 'photos', 'progress', 'status', 'angles', 'wizard', 'uploadUrls', 'steps', 'wizardMode'
        ))->with('wizardKey', 'face');
    }

    public function uploadFaceVerification(Request $request, string $angle, FaceVerificationService $faces): RedirectResponse|JsonResponse
    {
        $customer = $this->customer();

        if ($faces->isVerified($customer)) {
            if ($request->expectsJson()) {
                return response()->json(['ok' => false, 'message' => 'Your face verification is already approved.'], 422);
            }

            return redirect()->route('site.borrower.face-verification')
                ->with('status', 'Your face verification is already approved.');
        }

        if ($customer->face_verification_status === 'pending') {
            if ($request->expectsJson()) {
                return response()->json(['ok' => false, 'message' => 'Your photos are under review.'], 422);
            }

            return redirect()->route('site.borrower.face-verification')
                ->with('error', 'Your photos are under review. You cannot upload new ones until review is complete.');
        }

        $request->validate([
            'photo' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        try {
            $faces->upload($customer, $angle, $request->file('photo'));
        } catch (\InvalidArgumentException $e) {
            if ($request->expectsJson()) {
                return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
            }

            return back()->with('error', $e->getMessage());
        }

        $this->auditBorrower('face_verification.uploaded', $customer, [
            'angle'    => $angle,
            'complete' => $faces->progress($customer->fresh())['complete'] ?? false,
        ]);

        $customer->refresh();
        $progress = $faces->progress($customer);
        $wizard = $faces->wizardState($customer);
        $message = $progress['complete']
            ? 'All face photos uploaded. Our team will review them shortly.'
            : 'Photo saved.';

        if ($request->expectsJson()) {
            return response()->json([
                'ok'       => true,
                'angle'    => $angle,
                'progress' => $progress,
                'wizard'   => $wizard,
                'status'   => $customer->face_verification_status,
                'message'  => $message,
                'complete' => $progress['complete'],
            ]);
        }

        if ($progress['complete']) {
            return $this->redirectWithGuarantorResume(
                $request,
                $customer,
                redirect()->route('site.borrower.face-verification')->with('status', $message),
            );
        }

        return redirect()->route('site.borrower.face-verification')->with('status', $message);
    }

    public function kycReconfirm(KycFreshnessService $freshness): View|RedirectResponse
    {
        $customer = $this->customer();

        if (! $freshness->sectionsDueForRefresh($customer)) {
            return redirect()->route('site.borrower.dashboard')
                ->with('status', 'Your KYC information is up to date.');
        }

        return view('site.borrower.kyc-reconfirm', [
            'customer' => $customer,
            'staleSections' => $freshness->sectionsDueForRefresh($customer),
        ]);
    }

    public function updateKycReconfirm(Request $request, KycFreshnessService $freshness): RedirectResponse
    {
        $customer = $this->customer();

        $staleBefore = $freshness->sectionsDueForRefresh($customer);

        $data = $request->validate([
            'residence_unchanged' => ['nullable', 'boolean'],
            'region'           => [in_array('residence', $staleBefore, true) ? 'required' : 'nullable', 'string', 'max:100'],
            'district'         => [in_array('residence', $staleBefore, true) ? 'required' : 'nullable', 'string', 'max:100'],
            'ward'             => ['nullable', 'string', 'max:100'],
            'street'           => [in_array('residence', $staleBefore, true) ? 'required' : 'nullable', 'string', 'max:255'],
            'activity_type'    => [in_array('activity', $staleBefore, true) ? 'required' : 'nullable', 'string', 'max:40'],
            'activity_details' => ['nullable', 'array'],
            'income_range'     => [in_array('activity', $staleBefore, true) ? 'required' : 'nullable', 'string', 'in:'.implode(',', array_keys(config('income_ranges')))],
            'residence_letter' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'residence_letter_pages' => ['nullable', 'array'],
            'residence_letter_pages.*' => ['file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);

        if (in_array('residence', $staleBefore, true)) {
            $unchanged = (bool) ($data['residence_unchanged'] ?? false);
            $addressMatches = $customer->region === ($data['region'] ?? $customer->region)
                && $customer->district === ($data['district'] ?? $customer->district)
                && ($customer->ward ?? '') === ($data['ward'] ?? '')
                && $customer->street === ($data['street'] ?? $customer->street);

            if ($unchanged && ! $addressMatches) {
                return back()->withErrors(['region' => __('borrower.kyc.residence_unchanged_mismatch')])->withInput();
            }

            if (! $unchanged) {
                $customer->fill([
                    'region'   => $data['region'],
                    'district' => $data['district'],
                    'ward'     => $data['ward'] ?? null,
                    'street'   => $data['street'],
                    'address'  => trim(collect([$data['street'], $data['ward'] ?? null, $data['district'], $data['region']])->filter()->implode(', ')),
                ]);
            }
        }

        if (in_array('activity', $staleBefore, true)) {
            $customer->fill([
                'activity_type'   => $data['activity_type'],
                'activity_details'=> $data['activity_details'] ?? [],
                'employment_type' => $data['activity_type'],
                'income_range'    => $data['income_range'],
                'monthly_income'  => config('income_ranges.'.$data['income_range'].'.midpoint'),
            ]);
        }

        $customer->save();

        if (in_array('residence', $staleBefore, true) && ! (bool) ($data['residence_unchanged'] ?? false)) {
            $pageFiles = array_values(array_filter($request->file('residence_letter_pages', []) ?? []));
            $this->persistProfileDocumentUpload(
                $customer,
                'residence_letter',
                $request->file('residence_letter'),
                $pageFiles,
            );
        }

        $freshness->markReconfirmed($customer->fresh(), $staleBefore);

        $this->auditBorrower('kyc.reconfirmed', $customer);

        return redirect()->route('site.borrower.dashboard')
            ->with('status', 'Thank you. Your profile has been reconfirmed.');
    }

    public function guarantorRequests(): View
    {
        $customer = $this->customer();
        $requests = \App\Models\GuarantorInvitation::with(['borrower', 'application.product', 'customerGuarantor'])
            ->where('guarantor_customer_id', $customer->id)
            ->where('status', 'pending')
            ->latest()
            ->get();

        return view('site.borrower.guarantor-requests', compact('customer', 'requests'));
    }

    public function respondGuarantorRequest(Request $request, CustomerGuarantor $customerGuarantor, GuarantorInvitationService $guarantors, GuarantorSignatureService $signatures, GuarantorOnboardingService $guarantorOnboarding): RedirectResponse
    {
        $customer = $this->customer();

        $invitation = \App\Models\GuarantorInvitation::query()
            ->where('customer_guarantor_id', $customerGuarantor->id)
            ->where('guarantor_customer_id', $customer->id)
            ->first();

        abort_unless($invitation, 403);
        abort_unless($customerGuarantor->status === 'pending', 422);

        $data = $request->validate([
            'action'         => ['required', 'in:approve,reject'],
            'notes'          => ['nullable', 'string', 'max:500'],
            'signer_name'    => ['nullable', 'string', 'max:120'],
            'signature_data' => ['required_if:action,approve', 'nullable', 'string', 'starts_with:data:image/png;base64,'],
        ]);

        if ($data['action'] === 'approve') {
            $profileStatus = $guarantorOnboarding->guarantorProfileStatus($customer);
            if (! $profileStatus['met']) {
                return redirect()
                    ->route('site.borrower.guarantor-requests.show', $customerGuarantor)
                    ->with('error', __('borrower.guarantor.profile_incomplete', [
                        'percent' => $profileStatus['percent'],
                    ]));
            }

            $application = $customerGuarantor->application ?? $invitation->application;

            $signerName = trim($data['signer_name'] ?? '') ?: trim($customer->first_name.' '.$customer->last_name);

            if ($application) {
                $signatures->record(
                    $application,
                    $signerName,
                    $data['signature_data'],
                    $customerGuarantor,
                    $invitation,
                );
            } else {
                $signatures->recordForInvitation(
                    $invitation,
                    $signerName,
                    $data['signature_data'],
                );
            }
            $guarantors->approve($customerGuarantor);
            $msg = __('borrower.guarantor.approved_success');
        } else {
            $guarantors->reject($customerGuarantor, $data['notes'] ?? null);
            $msg = __('borrower.guarantor.declined_success');
        }

        $this->auditBorrower('guarantor_request.'.$data['action'], $customerGuarantor, [
            'invitation_id' => $invitation?->id,
        ]);

        return redirect()
            ->route('site.borrower.loans', ['tab' => $data['action'] === 'approve' ? 'guaranteed' : 'guarantor'])
            ->with('status', $data['action'] === 'approve'
                ? __('borrower.guaranteed.approved_track_message')
                : $msg);
    }

    public function showGuarantorRequest(CustomerGuarantor $customerGuarantor, GuarantorOnboardingService $guarantorOnboarding): View
    {
        $customer = $this->customer();

        $invitation = \App\Models\GuarantorInvitation::query()
            ->with(['borrower', 'application.product', 'product'])
            ->where('customer_guarantor_id', $customerGuarantor->id)
            ->where('guarantor_customer_id', $customer->id)
            ->first();

        abort_unless($invitation, 403);
        abort_unless($customerGuarantor->status === 'pending', 404);

        $profileStatus = $guarantorOnboarding->guarantorProfileStatus($customer);
        $guarantorExposure = app(\App\Services\PortalContextService::class)->hasGuarantorWork($customer)
            ? app(\App\Services\LoanPolicyService::class)->guarantorExposureSummary($customer)
            : null;

        return view('site.borrower.guarantor-request-show', compact(
            'customer',
            'invitation',
            'customerGuarantor',
            'profileStatus',
            'guarantorExposure',
        ));
    }

    public function disbursementDetails(LoanApplication $application): View|RedirectResponse
    {
        $customer = $this->customer();
        abort_if($application->customer_id !== $customer->id, 404);

        $readiness = app(\App\Services\ApplicationDisbursementReadinessService::class);

        if ($readiness->needsBorrowerSignature($application)) {
            return redirect()
                ->route('site.borrower.application.agreement', $application)
                ->with('status', __('borrower.contract.sign_offer_first'));
        }

        if ($readiness->needsPostApprovalFees($application)) {
            return redirect()
                ->route('site.borrower.application.post-approval-fees', $application)
                ->with('status', __('borrower.contract.pay_fees_first'));
        }

        if ($readiness->disbursementDetailsConfirmed($application)) {
            return redirect()
                ->route('site.borrower.application.contract', $application)
                ->with('status', __('borrower.disbursement_details.already_confirmed'));
        }

        $detailsService = app(\App\Services\CustomerDisbursementDetailsService::class);
        $loanAmount = app(\App\Services\ApplicationOfferService::class)->effectiveAmount($application);
        $accounts = $detailsService->accountsForCustomer($customer)
            ->filter(fn ($account) => $detailsService->accountIsComplete($account));
        $borrowerLegalName = $customer->legalDisplayName() ?? trim(($customer->first_name ?? '').' '.($customer->last_name ?? ''));
        $paymentComplete = $accounts->isNotEmpty();

        return view('site.borrower.disbursement-details', compact(
            'customer',
            'application',
            'detailsService',
            'loanAmount',
            'accounts',
            'borrowerLegalName',
            'paymentComplete',
        ));
    }

    public function confirmDisbursementDetails(Request $request, LoanApplication $application): RedirectResponse
    {
        $customer = $this->customer();
        abort_if($application->customer_id !== $customer->id, 404);

        $readiness = app(\App\Services\ApplicationDisbursementReadinessService::class);
        $detailsService = app(\App\Services\CustomerDisbursementDetailsService::class);

        if ($readiness->disbursementDetailsConfirmed($application)) {
            return redirect()
                ->route('site.borrower.application.contract', $application)
                ->with('status', __('borrower.disbursement_details.already_confirmed'));
        }

        if (! $readiness->needsDisbursementDetailsConfirmation($application)) {
            if ($readiness->needsPostApprovalFees($application)) {
                return redirect()
                    ->route('site.borrower.application.post-approval-fees', $application)
                    ->with('error', __('borrower.contract.pay_fees_first'));
            }

            if ($readiness->needsBorrowerSignature($application)) {
                return redirect()
                    ->route('site.borrower.application.agreement', $application)
                    ->with('error', __('borrower.contract.sign_offer_first'));
            }

            return redirect()
                ->route('site.borrower.application', $application)
                ->with('error', __('borrower.disbursement_details.not_ready'));
        }

        $data = $request->validate([
            'disbursement_account_id' => ['required', 'integer', 'exists:customer_disbursement_accounts,id'],
        ]);

        $account = \App\Models\CustomerDisbursementAccount::query()
            ->where('customer_id', $customer->id)
            ->where('id', $data['disbursement_account_id'])
            ->firstOrFail();

        try {
            $detailsService->confirmForApplication($application, $customer, $account);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        $this->auditBorrower('disbursement_details.confirmed', $application, [
            'account_id' => $account->id,
            'method'     => $account->type,
        ]);

        $application = $application->fresh();
        app(\App\Services\LoanAgreementService::class)->ensureLoanContractAfterFees($application);

        $contract = $readiness->loanContract($application->fresh());
        if (! $contract) {
            return redirect()
                ->route('site.borrower.application', $application)
                ->with('status', __('borrower.disbursement_details.confirmed_contract_pending'));
        }

        return redirect()
            ->route('site.borrower.application.contract', $application)
            ->with('status', __('borrower.disbursement_details.confirmed'));
    }

    public function postApprovalFees(LoanApplication $application, PostApprovalFeeService $fees): View|RedirectResponse
    {
        $customer = $this->customer();
        abort_if($application->customer_id !== $customer->id, 404);

        $readiness = app(\App\Services\ApplicationDisbursementReadinessService::class);
        if ($readiness->needsBorrowerSignature($application)) {
            return redirect()
                ->route('site.borrower.application.agreement', $application)
                ->with('status', __('borrower.contract.sign_offer_first'));
        }

        $application->load('product', 'postApprovalFees');
        if ($application->postApprovalFees->isEmpty()) {
            $fees->generateForApplication($application);
            $application->load('postApprovalFees');
        } else {
            $fees->syncManualFees($application);
            $application->load('postApprovalFees');
        }

        app(\App\Services\PostApprovalFeePaymentService::class)->reconcileVerifiedPayment($application);
        $application->refresh();

        if ($readiness->feesPaid($application) || ! $readiness->hasPostApprovalFees($application)) {
            if ($readiness->needsDisbursementDetailsConfirmation($application)) {
                return redirect()
                    ->route('site.borrower.application.disbursement-details', $application)
                    ->with('status', __('borrower.post_approval_fees.already_paid'));
            }

            if ($readiness->needsContractSignature($application)) {
                return redirect()
                    ->route('site.borrower.application.contract', $application)
                    ->with('status', __('borrower.post_approval_fees.already_paid'));
            }
        }

        $wallet = app(ReferralService::class)->wallet($customer);
        $referrals = app(ReferralService::class);
        $useWallet = (bool) old('use_wallet', false);
        $promoCode = old('promo_code');
        $paymentService = app(\App\Services\PostApprovalFeePaymentService::class);
        $feeQuote = $paymentService->quote($customer, $application, $useWallet, $promoCode);
        $maxWalletQuote = $paymentService->quote($customer, $application, true, $promoCode);
        $referralSettings = $referrals->settings();

        $paymentReference = $paymentService->generatePaymentReference($application);
        $accounts = app(\App\Services\PaymentAccountService::class);
        $bankAccounts = $accounts->bankAccountsForDisplay('post_approval_fee', $paymentReference, $application->product);
        $mobileResolved = $accounts->resolve('post_approval_fee', 'mobile_money', $application->product);
        $mobileDetails = $accounts->mobileMoneyDetails($mobileResolved['mobile_money_account'], $paymentReference);
        $channelOptions = payment_channels_for_amount($feeQuote['after_discount']);
        $loanAmount = app(\App\Services\ApplicationOfferService::class)->effectiveAmount($application);
        $feeLines = $application->postApprovalFees->map(fn ($fee) => [
            'name'       => $fee->name,
            'fee_type'   => $fee->fee_type,
            'rate_label' => $fee->fee_type === 'percent'
                ? rtrim(rtrim(format_number($fee->configured_amount, 2), '0'), '.').'%'
                : null,
            'amount'     => (float) $fee->calculated_amount,
            'paid'       => $fee->isPaid(),
        ])->values()->all();

        return view('site.borrower.post-approval-fees', compact(
            'customer',
            'application',
            'wallet',
            'feeQuote',
            'maxWalletQuote',
            'referralSettings',
            'paymentReference',
            'bankAccounts',
            'mobileDetails',
            'channelOptions',
            'loanAmount',
            'feeLines',
        ));
    }

    public function payPostApprovalFees(Request $request, LoanApplication $application, PostApprovalFeeService $fees): RedirectResponse
    {
        $customer = $this->customer();
        abort_if($application->customer_id !== $customer->id, 404);

        $data = $request->validate([
            'channel'        => ['required', 'in:mobile_money,bank'],
            'mobile_number'  => ['required_if:channel,mobile_money', 'nullable', 'string', 'max:20'],
            'payment_date'   => ['nullable', 'date'],
            'proof'          => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'use_wallet'     => ['nullable', 'boolean'],
            'promo_code'     => ['nullable', 'string', 'max:40'],
        ]);

        $paymentService = app(\App\Services\PostApprovalFeePaymentService::class);
        $reference = $paymentService->generatePaymentReference($application);
        $useWallet = $request->boolean('use_wallet');

        if ($data['channel'] === 'mobile_money') {
            $result = $paymentService->processMobileMoney(
                $customer,
                $application,
                $reference,
                $useWallet,
                $data['mobile_number'] ?? null,
                $data['promo_code'] ?? null,
            );
            $payment = $result['payment'];

            $this->auditBorrower('post_approval_fees.paid', $application, [
                'channel' => 'mobile_money',
                'amount'  => $result['quote']['after_discount'] ?? 0,
            ]);

            if (! $payment) {
                return redirect()
                    ->route('site.borrower.application', $application)
                    ->with('status', __('borrower.post_approval_fees.waived'));
            }

            return redirect()
                ->route('site.borrower.application.disbursement-details', $application)
                ->with('status', payment_gateway_is_dummy()
                    ? __('borrower.post_approval_fees.paid_dummy')
                    : __('borrower.post_approval_fees.paid_mobile'));
        }

        $result = $paymentService->processBankPending(
            $customer,
            $application,
            $reference,
            $useWallet,
            $data['payment_date'] ?? null,
            $data['promo_code'] ?? null,
        );
        $payment = $result['payment'];

        if (! $payment) {
            return redirect()
                ->route('site.borrower.application', $application)
                ->with('status', __('borrower.post_approval_fees.waived'));
        }

        if ($request->hasFile('proof')) {
            app(\App\Services\CustomerPaymentService::class)->uploadProof($payment, $request->file('proof'));
        }

        $this->auditBorrower('post_approval_fees.submitted', $application, [
            'channel' => 'bank',
            'amount'  => $result['quote']['after_discount'] ?? 0,
        ]);

        return redirect()
            ->route('site.borrower.payments.show', $payment)
            ->with('status', payment_gateway_is_dummy()
                ? __('borrower.post_approval_fees.paid_dummy')
                : __('borrower.post_approval_fees.bank_submitted'));
    }

    public function updatePin(Request $request, PinService $pins): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user && $user->role === 'borrower', 403);

        $rules = [
            'pin' => ['required', 'string', new FourDigitPin, 'confirmed'],
        ];

        if ($pins->hasPin($user)) {
            $rules['current_pin'] = ['required', 'string', new FourDigitPin];
        }

        $data = $request->validate($rules);

        if ($pins->hasPin($user) && ! $pins->verify($data['current_pin'], $user->pin_hash)) {
            return back()->withErrors(['current_pin' => 'Current PIN is incorrect.']);
        }

        $pins->setPin($user, $data['pin']);

        $this->auditBorrower('pin.updated', $user);

        return redirect()->route('site.borrower.profile', ['section' => 'security'])
            ->with('status', 'PIN updated successfully.');
    }

    public function revokeTrustedDevice(TrustedDevice $trustedDevice): RedirectResponse
    {
        abort_unless($trustedDevice->user_id === auth()->id(), 404);
        $this->auditBorrower('trusted_device.revoked', $trustedDevice, [
            'device_name' => $trustedDevice->name,
        ]);
        $trustedDevice->delete();

        return redirect()->route('site.borrower.profile', ['section' => 'security'])
            ->with('status', 'Trusted device removed.');
    }

    /* ---------------------------------------------------------------------
     | 10. Support (placeholder)
     |---------------------------------------------------------------------*/

    public function support(): View
    {
        return view('site.borrower.support', ['customer' => $this->customer()]);
    }

    public function destroyProfileDocument(string $code): RedirectResponse
    {
        $customer = $this->customer();
        $type = DocumentType::where('code', $code)->where('is_active', true)->first();
        abort_unless($type, 404);

        $document = CustomerDocument::query()
            ->where('customer_id', $customer->id)
            ->where('document_type_id', $type->id)
            ->whereNull('loan_application_id')
            ->latest()
            ->first();

        if ($document) {
            if ($document->file_path) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($document->file_path);
            }
            $document->delete();
        }

        $this->auditBorrower('profile.document_removed', $customer, ['code' => $code]);

        return back()->with('status', __('borrower.profile.document_removed'));
    }

    /**
     * @param  list<\Illuminate\Http\UploadedFile>  $pageFiles
     */
    private function persistProfileDocumentUpload(
        Customer $customer,
        string $documentCode,
        ?\Illuminate\Http\UploadedFile $single,
        array $pageFiles,
    ): void {
        $pageFiles = array_values(array_filter($pageFiles));
        if (! $single && $pageFiles === []) {
            return;
        }

        $type = DocumentType::where('code', $documentCode)->where('is_active', true)->first();
        if (! $type) {
            return;
        }

        $path = $single
            ? $single->store("customer/{$customer->id}/documents", 'public')
            : app(DocumentPageMerger::class)->merge($pageFiles, $customer->id, $documentCode);

        $pageCount = $single ? 1 : count($pageFiles);
        $originalName = $single?->getClientOriginalName()
            ?? ($pageCount === 1 ? ($pageFiles[0]->getClientOriginalName() ?? null) : null);

        CustomerDocument::updateOrCreate(
            [
                'customer_id'       => $customer->id,
                'document_type_id'  => $type->id,
                'loan_application_id' => null,
            ],
            [
                'file_path' => $path,
                'status'    => 'pending_review',
                'notes'     => json_encode(array_filter([
                    'page_count'    => max(1, $pageCount),
                    'original_name' => $originalName,
                ])),
            ]
        );

        try {
            app(\App\Services\MemberEngagementRewardService::class)->afterDocumentUploaded($customer, $documentCode);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function nidaMismatchRedirect(Customer $customer, NidaVerificationService $nida): RedirectResponse
    {
        $customer->refresh();
        $level = $nida->mismatchWarningLevel($customer);
        $message = $nida->mismatchMessage($customer, $level);
        $status = $nida->isLocked($customer) ? 'locked' : 'name_mismatch';

        $this->auditBorrower('nida.name_mismatch', $customer, [
            'level'  => $level,
            'locked' => $status === 'locked',
        ]);

        return redirect()
            ->route('site.borrower.profile', ['section' => 'personal'])
            ->with('nida_result', ['status' => $status, 'level' => $level, 'message' => $message]);
    }

    public function storeAsset(Request $request): RedirectResponse
    {
        $customer = $this->customer();
        $data = $request->validate([
            'asset_type'          => ['required', 'string', 'max:40'],
            'label'               => ['required', 'string', 'max:150'],
            'description'         => ['nullable', 'string', 'max:2000'],
            'registration_number' => ['nullable', 'string', 'max:80'],
            'estimated_value'     => ['nullable', 'numeric', 'min:0'],
            'photos'              => ['required', 'array', 'min:2', 'max:4'],
            'photos.*'            => ['required', 'image', 'max:5120'],
            'person_photo'        => ['required', 'image', 'max:5120'],
            'ownership_document'  => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:8192'],
        ]);

        app(\App\Services\CustomerAssetService::class)->store($customer, $data, [
            'photos'              => array_values(array_filter($request->file('photos', []) ?? [])),
            'person_photo'        => $request->file('person_photo'),
            'ownership_document'  => $request->file('ownership_document'),
        ]);

        return redirect()
            ->route('site.borrower.profile', ['section' => 'assets'])
            ->with('status', __('borrower.profile.asset_saved'));
    }

    public function updateNotificationPreferences(Request $request): RedirectResponse
    {
        $user = auth()->user();
        abort_unless($user, 403);

        $data = $request->validate([
            'notifications' => ['nullable', 'array'],
            'notifications.loan_updates' => ['nullable', 'boolean'],
            'notifications.payments'     => ['nullable', 'boolean'],
            'notifications.promotions'   => ['nullable', 'boolean'],
            'notifications.push'         => ['nullable', 'boolean'],
        ]);

        $incoming = $data['notifications'] ?? [];
        $notifications = [
            'loan_updates' => array_key_exists('loan_updates', $incoming),
            'payments'     => array_key_exists('payments', $incoming),
            'promotions'   => array_key_exists('promotions', $incoming),
            'push'         => array_key_exists('push', $incoming),
        ];

        $prefs = $user->preferences ?? [];
        $prefs['notifications'] = $notifications;
        $user->update(['preferences' => $prefs]);

        return redirect()
            ->route('site.borrower.profile', ['section' => 'security'])
            ->with('status', __('borrower.security_tab.notifications_saved'));
    }

    public function destroyAsset(CustomerAsset $asset): RedirectResponse
    {
        abort_unless($asset->customer_id === $this->customer()->id, 403);
        app(\App\Services\CustomerAssetService::class)->deactivate($asset);

        return redirect()
            ->route('site.borrower.profile', ['section' => 'assets'])
            ->with('status', __('borrower.profile.asset_removed'));
    }
}
