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
use App\Models\NotificationLog;
use App\Models\Repayment;
use App\Models\RepaymentSchedule;
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

        return view('site.borrower.dashboard', compact(
            'customer','activeLoan','nextDue','applicationsCount',
            'latestApplication','notifications','eligibility',
            'kyc','kycRequired','kycUploaded','kycProgress','kycMissing'
        ));
    }

    /* ---------------------------------------------------------------------
     | 2. Applications
     |---------------------------------------------------------------------*/
    public function applications(): View
    {
        $customer = $this->customer();
        $applications = LoanApplication::with('product')
            ->where('customer_id', $customer->id)->latest()->get();
        return view('site.borrower.applications', compact('customer', 'applications'));
    }

    /**
     * Show a single application with its product's requirement checklist
     * and upload widgets for each requirement.
     */
    public function application(LoanApplication $application): View
    {
        $customer = $this->customer();
        abort_if($application->customer_id !== $customer->id, 404);

        $application->load('product.requirements');

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

        return view('site.borrower.application', compact(
            'customer','application','requirements','uploads','requiredCount','satisfiedCount'
        ));
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
    public function profile(): View
    {
        $customer = $this->customer();
        $kyc = $customer->kyc ?? null;
        return view('site.borrower.profile', compact('customer','kyc'));
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $customer = $this->customer();

        $data = $request->validate([
            'first_name'      => ['required','string','max:60'],
            'last_name'       => ['required','string','max:60'],
            'phone'           => ['nullable','string','max:20'],
            'email'           => ['nullable','email','max:120'],
            'date_of_birth'   => ['nullable','date'],
            'national_id'     => ['nullable','string','max:30'],
            'address'         => ['nullable','string','max:255'],
            'employment_type' => ['nullable','string','max:30'],
            'business_name'   => ['nullable','string','max:120'],
            'monthly_income'  => ['nullable','numeric','min:0'],
        ]);

        $customer->fill($data)->save();

        return redirect()->route('site.borrower.profile')
            ->with('status', 'Profile updated.');
    }

    /* ---------------------------------------------------------------------
     | 10. Support (placeholder)
     |---------------------------------------------------------------------*/
    public function support(): View
    {
        return view('site.borrower.support', ['customer' => $this->customer()]);
    }
}
