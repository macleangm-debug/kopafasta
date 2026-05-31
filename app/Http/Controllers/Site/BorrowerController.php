<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerDocument;
use App\Models\CustomerGuarantor;
use App\Models\CustomerKyc;
use App\Models\DocumentType;
use App\Models\Guarantor;
use App\Models\Loan;
use App\Models\LoanApplication;
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
use App\Services\FaceVerificationService;
use App\Services\GuarantorInvitationService;
use App\Services\KycFreshnessService;
use App\Services\NidaVerificationService;
use App\Services\PinService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class BorrowerController extends Controller
{
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
        $income = (float) ($c->monthly_income ?? 0);
        if ($income <= 0 && $c->income_range) {
            $income = (float) (config('income_ranges.'.$c->income_range.'.midpoint') ?? 0);
        }
        $cap    = (int) min(max($income * 4, 0), 5_000_000);
        // Bonus if there's at least one fully repaid loan.
        $hasGoodHistory = Loan::where('customer_id', $c->id)->where('status', 'closed')->exists();
        if ($hasGoodHistory) $cap = (int) min($cap * 1.5, 7_500_000);
        return [
            'amount'   => $cap,
            'has_data' => $income > 0,
        ];
    }

    /* ---------------------------------------------------------------------
     | 1. Dashboard
     |---------------------------------------------------------------------*/
    public function dashboard(): View
    {
        $customer = $this->customer();

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
        $latestApplication = LoanApplication::with('product')
            ->where('customer_id', $customer->id)->latest()->first();

        $notifications = NotificationLog::where('customer_id', $customer->id)
            ->latest()->limit(4)->get();

        $eligibility = $this->eligibility($customer);

        // KYC snapshot for the dashboard reminder, scoped to customer type
        $kyc = CustomerKyc::where('customer_id', $customer->id)->first();
        $applicable    = ['any', $customer->type ?? 'individual'];
        $kycTypes      = DocumentType::where('is_active', true)
            ->where('category', 'kyc')
            ->whereIn('applies_to', $applicable)
            ->orderBy('name')
            ->get();
        $kycRequired   = $kycTypes->count();
        $kycUploadedTypeIds = $kycRequired > 0
            ? CustomerDocument::where('customer_id', $customer->id)
                ->whereIn('document_type_id', $kycTypes->pluck('id'))
                ->distinct()->pluck('document_type_id')
            : collect();
        $kycUploaded   = $kycUploadedTypeIds->count();
        $kycProgress   = $kycRequired > 0 ? (int) round(($kycUploaded / $kycRequired) * 100) : 0;
        $kycMissing    = $kycTypes->reject(fn ($t) => $kycUploadedTypeIds->contains($t->id))->values();

        // Active loan products available for application
        $products = LoanProduct::where('is_active', true)->orderBy('name')->get();

        $openDocumentRequests = app(ApplicationDocumentRequestService::class)->openRequestsForCustomer($customer);

        return view('site.borrower.dashboard', compact(
            'customer','activeLoan','nextDue','applicationsCount',
            'latestApplication','notifications','eligibility',
            'kyc','kycRequired','kycUploaded','kycProgress','kycMissing',
            'products','openDocumentRequests'
        ));
    }

    /* ---------------------------------------------------------------------
     | 2. Applications
     |---------------------------------------------------------------------*/
    public function applications(Request $request): View
    {
        $customer = $this->customer();
        $applications = LoanApplication::with('product')
            ->where('customer_id', $customer->id)->latest()->get();

        $user = Auth::user();
        $viewMode = $request->query('view');
        if (in_array($viewMode, ['cards', 'table'], true)) {
            $prefs = $user->preferences ?? [];
            $prefs['applications_view'] = $viewMode;
            $user->update(['preferences' => $prefs]);
        } else {
            $viewMode = $user->preferences['applications_view'] ?? 'cards';
        }

        return view('site.borrower.applications', compact('customer', 'applications', 'viewMode'));
    }

    /**
     * Show a single application with its product's requirement checklist
     * and upload widgets for each requirement.
     */
    public function application(LoanApplication $application): View
    {
        $customer = $this->customer();
        abort_if($application->customer_id !== $customer->id, 404);

        $application->load('product.requirements', 'documentRequests.uploads');

        // Documents already uploaded for THIS application
        $uploads = CustomerDocument::where('customer_id', $customer->id)
            ->where('loan_application_id', $application->id)
            ->whereNotNull('loan_product_requirement_id')
            ->latest()
            ->get()
            ->groupBy('loan_product_requirement_id');

        $requirements = $application->product?->requirements ?? collect();
        $requiredCount  = $requirements->where('is_required', true)->count();
        $satisfiedCount = $requirements->where('is_required', true)
            ->filter(fn ($r) => $uploads->has($r->id))->count();

        $documentRequests = $application->documentRequests()->with('uploads')->latest()->get();

        return view('site.borrower.application', compact(
            'customer','application','requirements','uploads','requiredCount','satisfiedCount','documentRequests'
        ));
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

        return redirect()
            ->route('site.borrower.application', $application->id)
            ->with('status', 'Uploaded — pending review.');
    }

    /* ---------------------------------------------------------------------
     | 3. My loans
     |---------------------------------------------------------------------*/
    public function loans(): View
    {
        $customer = $this->customer();
        $loans = Loan::with('product')->where('customer_id', $customer->id)->latest()->get();
        return view('site.borrower.loans', compact('customer', 'loans'));
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

        Repayment::create([
            'loan_id'   => $loan->id,
            'reference' => strtoupper($data['reference']),
            'channel'   => $data['channel'],
            'amount'    => $data['amount'],
            'status'    => 'pending',
            'paid_at'   => now(),
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

        CustomerDocument::create([
            'customer_id'      => $customer->id,
            'document_type_id' => $data['document_type_id'],
            'file_path'        => $path,
            'status'           => 'pending',
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

        CustomerGuarantor::create([
            'customer_id'  => $customer->id,
            'guarantor_id' => $guarantor->id,
            'status'       => 'pending',
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

        return view($view, compact('customer', 'kyc', 'trustedDevices'));
    }

    public function updateProfile(Request $request, string $section = 'personal'): RedirectResponse
    {
        $customer = $this->customer();
        $section = in_array($section, ['personal', 'activity', 'residence'], true) ? $section : 'personal';

        if ($section === 'personal') {
            $locked = (bool) $customer->identity_locked;

            $rules = [
                'phone' => ['nullable', 'string', 'max:20'],
                'email' => ['nullable', 'email', 'max:120'],
            ];

            if (! $locked) {
                $rules = array_merge($rules, [
                    'first_name'    => ['required', 'string', 'max:60'],
                    'last_name'     => ['required', 'string', 'max:60'],
                    'date_of_birth' => ['required', 'date', new MinimumAge],
                    'gender'        => ['nullable', 'string', 'in:male,female,other'],
                    'national_id'   => ['nullable', 'string', 'max:30'],
                ]);
            }

            $data = $request->validate($rules);

            if ($locked) {
                $customer->fill(collect($data)->only(['phone', 'email'])->all())->save();
            } else {
                $customer->fill($data)->save();
            }
        }

        if ($section === 'activity') {
            $data = $request->validate([
                'activity_type'    => ['required', 'string', 'max:40'],
                'activity_details' => ['nullable', 'array'],
                'income_range'     => ['required', 'string', 'in:'.implode(',', array_keys(config('income_ranges')))],
            ]);
            $customer->fill([
                'activity_type'    => $data['activity_type'],
                'activity_details' => $data['activity_details'] ?? [],
                'employment_type'  => $data['activity_type'],
                'income_range'     => $data['income_range'],
                'monthly_income'   => config('income_ranges.'.$data['income_range'].'.midpoint'),
            ])->save();
        }

        if ($section === 'residence') {
            $data = $request->validate([
                'region'   => ['required', 'string', 'max:100'],
                'district' => ['required', 'string', 'max:100'],
                'ward'     => ['nullable', 'string', 'max:100'],
                'street'   => ['required', 'string', 'max:255'],
            ]);
            $customer->fill([
                ...$data,
                'address' => trim(collect([$data['street'], $data['ward'], $data['district'], $data['region']])->filter()->implode(', ')),
            ])->save();
        }

        return redirect()
            ->route('site.borrower.profile', ['section' => $section])
            ->with('status', 'Profile updated.');
    }

    public function verifyNida(Request $request, NidaVerificationService $nida): RedirectResponse
    {
        $customer = $this->customer();

        $data = $request->validate([
            'national_id' => ['required', 'string', 'max:30', new ValidNidaNumber],
        ]);

        $result = $nida->verify($customer, $data['national_id']);

        if ($result->success) {
            return redirect()
                ->route('site.borrower.profile', ['section' => 'personal'])
                ->with('status', 'NIDA verified successfully. Your name and date of birth were confirmed via the credit bureau.');
        }

        if ($result->isMultihit()) {
            return redirect()
                ->route('site.borrower.profile', ['section' => 'personal'])
                ->with('error', $result->message)
                ->with('crb_candidates', $result->candidates);
        }

        return redirect()
            ->route('site.borrower.profile', ['section' => 'personal'])
            ->with('error', $result->message ?? 'NIDA verification failed. Please check your number and try again.');
    }

    public function confirmNidaCandidate(Request $request, NidaVerificationService $nida): RedirectResponse
    {
        $customer = $this->customer();

        $data = $request->validate([
            'national_id'       => ['required', 'string', 'max:30', new ValidNidaNumber],
            'search_request_id' => ['required', 'string', 'max:40'],
            'entity_key'        => ['required', 'string', 'max:40'],
        ]);

        $result = $nida->confirmCandidate(
            $customer,
            $data['national_id'],
            $data['search_request_id'],
            $data['entity_key'],
        );

        if ($result->success) {
            return redirect()
                ->route('site.borrower.profile', ['section' => 'personal'])
                ->with('status', 'Identity confirmed and profile updated from CRB records.');
        }

        return redirect()
            ->route('site.borrower.profile', ['section' => 'personal'])
            ->with('error', $result->message ?? 'Could not confirm the selected CRB match.');
    }

    public function faceVerification(FaceVerificationService $faces): View
    {
        $customer = $this->customer();
        $photos = $faces->latestByAngle($customer);
        $progress = $faces->progress($customer);
        $status = $faces->statusLabel($customer);
        $angles = $faces->angles();

        return view('site.borrower.face-verification', compact(
            'customer', 'photos', 'progress', 'status', 'angles'
        ));
    }

    public function uploadFaceVerification(Request $request, string $angle, FaceVerificationService $faces): RedirectResponse
    {
        $customer = $this->customer();

        if ($faces->isVerified($customer)) {
            return redirect()->route('site.borrower.face-verification')
                ->with('status', 'Your face verification is already approved.');
        }

        if ($customer->face_verification_status === 'pending') {
            return redirect()->route('site.borrower.face-verification')
                ->with('error', 'Your photos are under review. You cannot upload new ones until review is complete.');
        }

        $request->validate([
            'photo' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        try {
            $faces->upload($customer, $angle, $request->file('photo'));
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        $customer->refresh();
        $message = $faces->progress($customer)['complete']
            ? 'All face photos uploaded. Our team will review them shortly.'
            : 'Photo saved. Capture the remaining angles to complete face verification.';

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
            'region'           => ['required', 'string', 'max:100'],
            'district'         => ['required', 'string', 'max:100'],
            'ward'             => ['nullable', 'string', 'max:100'],
            'street'           => ['required', 'string', 'max:255'],
            'activity_type'    => ['required', 'string', 'max:40'],
            'activity_details' => ['nullable', 'array'],
            'income_range'     => ['required', 'string', 'in:'.implode(',', array_keys(config('income_ranges')))],
        ]);

        $customer->fill([
            ...collect($data)->only(['region', 'district', 'ward', 'street'])->all(),
            'address'         => trim(collect([$data['street'], $data['ward'] ?? null, $data['district'], $data['region']])->filter()->implode(', ')),
            'activity_type'   => $data['activity_type'],
            'activity_details'=> $data['activity_details'] ?? [],
            'employment_type' => $data['activity_type'],
            'income_range'    => $data['income_range'],
            'monthly_income'  => config('income_ranges.'.$data['income_range'].'.midpoint'),
        ])->save();

        $freshness->markReconfirmed($customer);

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

    public function respondGuarantorRequest(Request $request, CustomerGuarantor $customerGuarantor, GuarantorInvitationService $guarantors): RedirectResponse
    {
        $customer = $this->customer();

        $invitation = \App\Models\GuarantorInvitation::query()
            ->where('customer_guarantor_id', $customerGuarantor->id)
            ->where('guarantor_customer_id', $customer->id)
            ->first();

        abort_unless($invitation, 403);
        abort_unless($customerGuarantor->status === 'pending', 422);

        $data = $request->validate([
            'action' => ['required', 'in:approve,reject'],
            'notes'  => ['nullable', 'string', 'max:500'],
        ]);

        if ($data['action'] === 'approve') {
            $guarantors->approve($customerGuarantor);
            $msg = 'Guarantor request approved.';
        } else {
            $guarantors->reject($customerGuarantor, $data['notes'] ?? null);
            $msg = 'Guarantor request declined.';
        }

        return back()->with('status', $msg);
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

        return redirect()->route('site.borrower.profile', ['section' => 'security'])
            ->with('status', 'PIN updated successfully.');
    }

    public function revokeTrustedDevice(TrustedDevice $trustedDevice): RedirectResponse
    {
        abort_unless($trustedDevice->user_id === auth()->id(), 404);
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
}
