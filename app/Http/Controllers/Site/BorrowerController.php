<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Concerns\AuditsActions;
use App\Http\Controllers\Controller;
use App\Models\Customer;
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
use App\Models\TrustedDevice;
use App\Rules\FourDigitPin;
use App\Rules\MinimumAge;
use App\Rules\ValidNidaNumber;
use App\Services\ApplicationDocumentRequestService;
use App\Services\ApplicationRequirementsService;
use App\Services\CrbService;
use App\Services\FaceVerificationService;
use App\Services\GuarantorInvitationService;
use App\Services\GuarantorSignatureService;
use App\Services\KycFreshnessService;
use App\Services\LoanQualificationService;
use App\Services\NidaVerificationService;
use App\Services\PinService;
use App\Services\PostApprovalFeeService;
use App\Services\DocumentPageMerger;
use App\Services\ProfileCompletionService;
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

        if ($redirect = app(\App\Services\GuarantorOnboardingService::class)->redirectIfPending($request, $customer)) {
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

        $notifications = NotificationLog::where('customer_id', $customer->id)
            ->latest()->limit(4)->get();
        $unreadNotificationCount = NotificationLog::where('customer_id', $customer->id)
            ->whereNull('read_at')->count();

        $eligibility = $qualification->calculate($customer);
        $applyRequirements = $requirements->checklist($customer);
        $onboardingBanner = $requirements->onboardingBanner($customer);
        $applyDraftResume = app(\App\Services\LoanApplicationDraftService::class)->resumeSummary($customer);

        $activeApplications = LoanApplication::with('product')
            ->where('customer_id', $customer->id)
            ->whereNotIn('status', ['rejected', 'disbursed'])
            ->latest()
            ->limit(5)
            ->get();

        // Active loan products — public catalogue order
        $products = borrower_catalogue_products();

        $openDocumentRequests = $documentRequests->openRequestsForCustomer($customer);

        $referralService = app(ReferralService::class);
        $referralService->ensureCode($customer);
        $referralCode = $customer->referral_code;
        $referralLink = $referralService->referralLink($customer);
        $referralWallet = $referralService->wallet($customer);

        return view('site.borrower.dashboard', compact(
            'customer','activeLoan','nextDue','applicationsCount',
            'notifications','eligibility',
            'products','applyRequirements','onboardingBanner','applyDraftResume','activeApplications','unreadNotificationCount',
            'openDocumentRequests','referralCode','referralLink','referralWallet',
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

        return view('site.borrower.loan-profile', compact('customer', 'profile'));
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

        $activeTab = $request->query('tab', 'applications');
        if (! in_array($activeTab, ['applications', 'active'], true)) {
            $activeTab = 'applications';
        }

        $applicationsDashboard = app(\App\Services\BorrowerApplicationsDashboardService::class);
        $applicationRows = $applicationsDashboard->applicationsForCustomer($customer);

        $loans = Loan::with('product')
            ->where('customer_id', $customer->id)
            ->whereIn('status', ['active', 'disbursed', 'arrears'])
            ->latest()
            ->get();

        $user = Auth::user();
        $viewMode = $request->query('view');
        if (in_array($viewMode, ['cards', 'table'], true)) {
            $prefs = $user->preferences ?? [];
            $prefs['applications_view'] = $viewMode;
            $user->update(['preferences' => $prefs]);
        } else {
            $viewMode = $user->preferences['applications_view'] ?? 'table';
        }

        return view('site.borrower.loans', compact(
            'customer',
            'activeTab',
            'applicationRows',
            'viewMode',
            'loans',
        ));
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
        return view('site.borrower.documents', compact('customer','types','documents'));
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
    public function notifications(): View
    {
        $customer = $this->customer();
        $items = NotificationLog::where('customer_id', $customer->id)
            ->latest()->paginate(20);

        return view('site.borrower.notifications', compact('customer','items'));
    }

    public function notificationPreview(): \Illuminate\Http\JsonResponse
    {
        $customer = $this->customer();
        $items = NotificationLog::where('customer_id', $customer->id)
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
            'unread' => NotificationLog::where('customer_id', $customer->id)->whereNull('read_at')->count(),
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
        NotificationLog::where('customer_id', $customer->id)->delete();

        return back()->with('status', 'All notifications cleared.');
    }

    public function markNotificationsRead(Request $request): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        $customer = $this->customer();
        NotificationLog::where('customer_id', $customer->id)
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
    public function profile(Request $request, string $section = 'personal'): View|RedirectResponse
    {
        $customer = $this->customer();
        $kyc = $customer->kyc ?? CustomerKyc::firstOrCreate(
            ['customer_id' => $customer->id],
            ['status' => 'pending', 'payload' => []]
        );

        if ($section === 'kin') {
            return redirect()->to(route('site.borrower.profile', ['section' => 'personal']).'#next-of-kin');
        }

        $section = in_array($section, ['personal', 'activity', 'residence', 'kyc', 'security'], true)
            ? $section
            : 'personal';

        $view = match ($section) {
            'activity'  => 'site.borrower.profile.activity',
            'residence' => 'site.borrower.profile.residence',
            'kyc'       => 'site.borrower.profile.kyc',
            'security'  => 'site.borrower.profile.security',
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

        return view($view, compact('customer', 'kyc', 'trustedDevices', 'nidaDocuments', 'employmentContract', 'residenceLetter', 'incomeProofChecklist', 'incomeProofEmployed', 'incomeProofMethod', 'incomePrimaryOptions', 'completionSummary', 'returnUrl'))
            ->with('crbUsesStub', app(CrbService::class)->usesStub())
            ->with('crbSamples', config('crb_samples.scenarios', []))
            ->with('profileSections', app(ProfileCompletionService::class)->displaySections($customer));
    }

    public function updateProfile(Request $request, string $section = 'personal'): RedirectResponse
    {
        $customer = $this->customer();
        if ($section === 'kin') {
            $section = 'personal';
        }

        $section = in_array($section, ['personal', 'activity', 'residence', 'kyc'], true) ? $section : 'personal';
        $validation = app(ProfileValidationService::class);

        if ($section === 'personal') {
            $data = $request->validate([
                'phone' => ['nullable', 'string', 'max:20'],
                'email' => ['nullable', 'email', 'max:120'],
                'nok_name'         => ['required', 'string', 'max:120'],
                'nok_relationship' => ['required', 'string', 'max:60'],
                'nok_phone'        => ['required', 'string', 'max:30'],
                'nok_region'       => ['required', 'string', 'max:100'],
                'nok_district'     => ['required', 'string', 'max:100'],
                'nok_ward'         => ['nullable', 'string', 'max:100'],
                'nok_street'       => ['required', 'string', 'max:255'],
                'national_id_front' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            ]);

            $customer->fill([
                'phone'            => $data['phone'] ?? $customer->phone,
                'email'            => $data['email'] ?? $customer->email,
                'nok_name'         => $data['nok_name'],
                'nok_relationship' => $data['nok_relationship'],
                'nok_phone'        => $data['nok_phone'],
                'nok_region'       => $data['nok_region'],
                'nok_district'     => $data['nok_district'],
                'nok_ward'         => $data['nok_ward'] ?? null,
                'nok_street'       => $data['nok_street'],
            ])->save();

            $this->persistProfileDocumentUpload($customer, 'national_id_front', $request->file('national_id_front'), []);

            if (! $validation->nationalIdUploadsComplete($customer->fresh())) {
                return redirect()
                    ->route('site.borrower.profile', ['section' => 'personal'])
                    ->withErrors(['national_id_front' => __('borrower.profile.nida_upload_required')])
                    ->withInput();
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

            if ($validation->requiresResidenceLetter() && ! $validation->hasResidenceLetter($customer->fresh())) {
                return redirect()
                    ->route('site.borrower.profile', ['section' => 'residence'])
                    ->withErrors(['residence_letter' => __('borrower.profile.residence_letter_required')])
                    ->withInput();
            }
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
        }

        $this->auditBorrower('profile.updated', $customer, ['section' => $section]);

        if ($return = $this->validatedReturnUrl($request)) {
            return redirect($return)->with('status', __('borrower.profile.saved_return'));
        }

        return redirect()
            ->route('site.borrower.profile', array_filter(['section' => $section !== 'personal' ? $section : null]))
            ->with('status', 'Profile updated.');
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
            'national_id' => ['required', 'string', 'max:30', new ValidNidaNumber],
        ]);

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
            return redirect()
                ->route('site.borrower.profile', ['section' => 'personal'])
                ->with('nida_result', ['status' => 'verified']);
        }

        if ($result->status === 'name_mismatch') {
            return $this->nidaMismatchRedirect($customer, $nida);
        }

        if ($result->isMultihit()) {
            return redirect()
                ->route('site.borrower.profile', ['section' => 'personal'])
                ->with('nida_result', ['status' => 'multihit', 'message' => $result->message])
                ->with('crb_candidates', $result->candidates);
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
            'national_id'       => ['required', 'string', 'max:30', new ValidNidaNumber],
            'search_request_id' => ['required', 'string', 'max:40'],
            'entity_key'        => ['required', 'string', 'max:40'],
        ]);

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
            return redirect()
                ->route('site.borrower.profile', ['section' => 'personal'])
                ->with('nida_result', ['status' => 'verified']);
        }

        if ($result->status === 'name_mismatch') {
            return $this->nidaMismatchRedirect($customer, $nida);
        }

        return redirect()
            ->route('site.borrower.profile', ['section' => 'personal'])
            ->with('nida_result', ['status' => 'failed', 'message' => $result->message ?? 'Could not confirm the selected match.']);
    }

    public function faceVerification(FaceVerificationService $faces): View
    {
        $customer = $this->customer();
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
            'customer', 'photos', 'progress', 'status', 'angles', 'wizard', 'uploadUrls', 'steps'
        ));
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

        return redirect()->route('site.borrower.face-verification')->with('status', $message);
    }

    public function kycReconfirm(KycFreshnessService $freshness): View|RedirectResponse
    {
        $customer = $this->customer();

        if (! $freshness->isStale($customer)) {
            return redirect()->route('site.borrower.dashboard')
                ->with('status', 'Your KYC information is up to date.');
        }

        return view('site.borrower.kyc-reconfirm', compact('customer'));
    }

    public function updateKycReconfirm(Request $request, KycFreshnessService $freshness): RedirectResponse
    {
        $customer = $this->customer();

        $data = $request->validate([
            'residence_unchanged' => ['nullable', 'boolean'],
            'region'           => ['required', 'string', 'max:100'],
            'district'         => ['required', 'string', 'max:100'],
            'ward'             => ['nullable', 'string', 'max:100'],
            'street'           => ['required', 'string', 'max:255'],
            'activity_type'    => ['required', 'string', 'max:40'],
            'activity_details' => ['nullable', 'array'],
            'income_range'     => ['required', 'string', 'in:'.implode(',', array_keys(config('income_ranges')))],
            'residence_letter' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'residence_letter_pages' => ['nullable', 'array'],
            'residence_letter_pages.*' => ['file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);

        $unchanged = (bool) ($data['residence_unchanged'] ?? false);
        $addressMatches = $customer->region === $data['region']
            && $customer->district === $data['district']
            && ($customer->ward ?? '') === ($data['ward'] ?? '')
            && $customer->street === $data['street'];

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

        $customer->fill([
            'activity_type'   => $data['activity_type'],
            'activity_details'=> $data['activity_details'] ?? [],
            'employment_type' => $data['activity_type'],
            'income_range'    => $data['income_range'],
            'monthly_income'  => config('income_ranges.'.$data['income_range'].'.midpoint'),
        ])->save();

        if (! $unchanged) {
            $pageFiles = array_values(array_filter($request->file('residence_letter_pages', []) ?? []));
            $this->persistProfileDocumentUpload(
                $customer,
                'residence_letter',
                $request->file('residence_letter'),
                $pageFiles,
            );
        }

        $freshness->markReconfirmed($customer);

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

    public function respondGuarantorRequest(Request $request, CustomerGuarantor $customerGuarantor, GuarantorInvitationService $guarantors, GuarantorSignatureService $signatures): RedirectResponse
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
            'signer_name'    => ['required_if:action,approve', 'nullable', 'string', 'max:120'],
            'signature_data' => ['required_if:action,approve', 'nullable', 'string', 'starts_with:data:image/png;base64,'],
        ]);

        if ($data['action'] === 'approve') {
            $application = $customerGuarantor->application;
            abort_unless($application, 422);

            $signatures->record(
                $application,
                $data['signer_name'],
                $data['signature_data'],
                $customerGuarantor,
                $invitation,
            );
            $guarantors->approve($customerGuarantor);
            $msg = 'Guarantor request approved and signed.';
        } else {
            $guarantors->reject($customerGuarantor, $data['notes'] ?? null);
            $msg = 'Guarantor request declined.';
        }

        $this->auditBorrower('guarantor_request.'.$data['action'], $customerGuarantor, [
            'invitation_id' => $invitation?->id,
        ]);

        return back()->with('status', $msg);
    }

    public function postApprovalFees(LoanApplication $application, PostApprovalFeeService $fees): View
    {
        $customer = $this->customer();
        abort_if($application->customer_id !== $customer->id, 404);

        $application->load('product', 'postApprovalFees');
        if ($application->postApprovalFees->isEmpty()) {
            $fees->generateForApplication($application);
            $application->load('postApprovalFees');
        }

        $wallet = app(ReferralService::class)->wallet($customer);
        $referrals = app(ReferralService::class);
        $baseTotal = (float) $application->postApprovalFees->where('status', '!=', 'paid')->sum('calculated_amount');
        $feeQuote = $this->postApprovalFeeQuote($customer, $baseTotal, false, $referrals);
        $maxWalletQuote = $this->postApprovalFeeQuote($customer, $baseTotal, true, $referrals);
        $referralSettings = $referrals->settings();

        return view('site.borrower.post-approval-fees', compact('application', 'wallet', 'feeQuote', 'maxWalletQuote', 'referralSettings'));
    }

    public function payPostApprovalFees(Request $request, LoanApplication $application, PostApprovalFeeService $fees): RedirectResponse
    {
        $customer = $this->customer();
        abort_if($application->customer_id !== $customer->id, 404);

        $request->validate([
            'use_wallet' => ['nullable', 'boolean'],
        ]);

        $result = $fees->markAllPaid($application, $customer, $request->boolean('use_wallet'));

        $this->auditBorrower('post_approval_fees.paid', $application, [
            'total'      => $fees->totalDue($application),
            'settlement' => $result['settlement'],
        ]);

        return redirect()
            ->route('site.borrower.application', $application)
            ->with('status', 'Post-approval fees recorded. Our team will proceed with disbursement preparation.');
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

    /** @return array<string, mixed> */
    private function postApprovalFeeQuote(Customer $customer, float $baseTotal, bool $useWallet, ReferralService $referrals): array
    {
        if ($referrals->referrer($customer)) {
            return $referrals->quoteFee($customer, $baseTotal, $useWallet, 'post_approval_fee');
        }

        $affiliateQuote = app(AffiliateService::class)->quoteFee($customer, $baseTotal, 'post_approval_fee');
        $walletQuote = $referrals->quoteFee($customer, $affiliateQuote['after_discount'], $useWallet, 'post_approval_fee', applyDiscount: false);

        return array_merge($affiliateQuote, [
            'wallet_usable'  => $walletQuote['wallet_usable'],
            'wallet_applied' => $walletQuote['wallet_applied'],
            'cash_due'       => max(0, round($affiliateQuote['after_discount'] - $walletQuote['wallet_applied'], 2)),
            'has_referrer'   => false,
            'referrer'       => null,
        ]);
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
}
