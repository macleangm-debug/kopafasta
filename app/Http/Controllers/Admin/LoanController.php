<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\AuditsActions;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Services\AuditService;
use App\Services\CapitalPartnerAllocationService;
use App\Services\CapitalPartnerMetricsService;
use App\Services\AssetReservationService;
use App\Services\LoanDisbursementOrchestrator;
use App\Services\LoanDisbursementService;
use App\Services\GuarantorNotificationService;
use App\Services\LoanOriginationService;
use App\Services\RepaymentScheduleGenerator;
use App\Services\LoanWriteOffService;
use App\Services\SmartLoanApplicationWizardService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class LoanController extends Controller
{
    use AuditsActions;

    protected function auditResourceKey(): string
    {
        return 'loans';
    }

    public function create(): View
    {
        $customers = Customer::orderBy('first_name')->limit(500)->get()
            ->mapWithKeys(fn ($c) => [$c->id => trim($c->first_name.' '.$c->last_name).($c->customer_number ? ' ('.$c->customer_number.')' : '')]);

        $products = LoanProduct::where('is_active', true)->orderBy('name')->get()->map(fn (LoanProduct $p) => [
            'id'                 => $p->id,
            'code'               => $p->code,
            'name'               => $p->name,
            'rate'               => (float) $p->interest_rate,
            'min'                => (float) $p->min_amount,
            'max'                => (float) $p->max_amount,
            'tmin'               => (int) $p->tenure_min_months,
            'tmax'               => (int) $p->tenure_max_months,
            'desc'               => $p->description,
            'requires_guarantor' => (bool) $p->requires_guarantor,
        ])->values();

        return view('admin.loans.create', [
            'customers'     => $customers,
            'products'      => $products,
            'wizardDataUrl' => route('admin.loans.wizard-data', ['customer' => '__ID__']),
        ]);
    }

    public function wizardCustomerData(Customer $customer, SmartLoanApplicationWizardService $wizard): JsonResponse
    {
        abort_unless(auth()->user()?->hasPermission('loans.view'), 403);

        $applications = LoanApplication::query()
            ->with('product')
            ->where('customer_id', $customer->id)
            ->where('current_stage', 'disbursement')
            ->whereDoesntHave('loan')
            ->latest()
            ->get()
            ->map(fn (LoanApplication $a) => [
                'id'                 => $a->id,
                'application_number' => $a->application_number,
                'product_id'         => $a->loan_product_id,
                'product_name'       => $a->product?->name,
                'product_code'       => $a->product?->code,
                'amount'             => (float) ($a->recommended_amount ?: $a->requested_amount),
                'tenure'             => (int) $a->requested_tenure_months,
                'rate'               => (float) ($a->product?->interest_rate ?? 0),
            ])
            ->values();

        return response()->json([
            'eligibility'  => $wizard->eligibilityForCustomer($customer),
            'profile'      => $wizard->profileSections($customer),
            'applications' => $applications,
        ]);
    }

    public function store(Request $request, LoanOriginationService $origination): RedirectResponse
    {
        if ($request->filled('loan_application_id')) {
            $application = LoanApplication::query()->findOrFail($request->input('loan_application_id'));

            abort_unless(
                (int) $application->customer_id === (int) $request->input('customer_id'),
                422,
                'Application does not belong to the selected customer.'
            );

            try {
                $loan = $origination->createFromApplication($application);
            } catch (\Illuminate\Validation\ValidationException $e) {
                return back()->withErrors($e->errors())->withInput();
            }

            $this->auditAdminCreated($loan);

            return redirect()
                ->route('admin.loans.show', $loan)
                ->with('status', 'Loan '.$loan->loan_number.' created from application. Disburse when ready.');
        }

        $data = $this->validated($request);

        $data['loan_number'] = $data['loan_number'] ?? $this->generateLoanNumber(
            LoanProduct::find($data['loan_product_id'] ?? null)
        );
        $data['approved_amount']     = $data['approved_amount'] ?? $data['principal_amount'];
        $data['outstanding_balance'] = $data['outstanding_balance'] ?? $data['principal_amount'];
        $data['status']              = $data['status'] ?? 'pending';

        $loan = Loan::create($data);
        $loan->load('product');
        try {
            app(CapitalPartnerAllocationService::class)->allocateForLoan($loan);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $loan->delete();

            return back()->withErrors($e->errors())->withInput();
        }
        $this->auditAdminCreated($loan);

        return redirect()
            ->route('admin.loans.show', $loan)
            ->with('status', 'Pending loan '.$loan->loan_number.' created. Disburse from the queue when ready.');
    }

    public function show(Loan $loan)
    {
        $loan->load([
            'customer',
            'product',
            'application',
            'fees',
            'disbursements',
            'capitalAllocations.lender',
            'capitalAllocations.pool',
            'repaymentSchedules' => fn ($q) => $q->orderBy('installment_no'),
        ]);

        $metrics = app(CapitalPartnerMetricsService::class);
        $capitalTotals = $metrics->loanTotals($loan);
        $capitalAllocations = $metrics->allocationsForLoan($loan);

        return view('admin.loans.show', compact('loan', 'capitalTotals', 'capitalAllocations'));
    }

    public function edit(Loan $loan)
    {
        return view('admin.loans.edit', ['loan' => $loan] + $this->formData());
    }

    public function update(Request $request, Loan $loan)
    {
        $before = app(AuditService::class)->snapshot($loan);
        $loan->update($this->validated($request));
        $loan->refresh();
        $this->auditAdminUpdated($loan, $before);

        return redirect()
            ->route('admin.loans.show', $loan)
            ->with('status', 'Loan updated.');
    }

    public function destroy(Loan $loan)
    {
        $this->auditAdminDeleted($loan);
        $loan->delete();

        return redirect()
            ->route('admin.loans.index')
            ->with('status', 'Loan deleted.');
    }

    public function disburse(Loan $loan, LoanDisbursementOrchestrator $orchestrator)
    {
        try {
            $loan = $orchestrator->disburse($loan, auth()->user());
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        $installments = $loan->repaymentSchedules()->count();
        $feesCount = \App\Models\LoanFee::where('loan_id', $loan->id)->where('charge_when', 'disbursement')->count();

        $this->auditAdmin('admin.loans.disbursed', $loan, [
            'fees_applied' => $feesCount,
            'installments' => $installments,
        ]);

        return redirect()
            ->route('admin.loans.show', $loan)
            ->with('status', 'Loan disbursed. '.$feesCount.' fee(s) applied · '.$installments.' installment(s) scheduled.');
    }

    public function writeOffForm(Loan $loan)
    {
        return view('admin.loans.write-off', compact('loan'));
    }

    public function writeOff(Request $request, Loan $loan, LoanWriteOffService $service)
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
            'amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        try {
            $entry = $service->writeOff($loan, $data['reason'], $data['amount'] ?? null);
        } catch (\Throwable $e) {
            return back()->withErrors(['amount' => $e->getMessage()]);
        }

        $this->auditAdmin('admin.loans.written_off', $loan->fresh(), [
            'reason' => $data['reason'],
            'amount' => $data['amount'] ?? null,
            'journal' => $entry?->entry_number,
        ]);

        app(GuarantorNotificationService::class)->notifyLoanClosed($loan->fresh(['application.customer']));

        return redirect()
            ->route('admin.loans.show', $loan)
            ->with('status', 'Loan written off.'.($entry ? ' Journal '.$entry->entry_number.' posted.' : ''));
    }

    protected function formData(?Model $record = null): array
    {
        return [
            'customers'    => Customer::orderBy('first_name')->get(['id', 'first_name', 'last_name', 'customer_number']),
            'products'     => LoanProduct::orderBy('name')->get(['id', 'name', 'code']),
            'applications' => LoanApplication::orderByDesc('id')->limit(200)->get(['id', 'application_number']),
            'statuses'     => ['pending', 'active', 'closed', 'defaulted', 'written_off'],
        ];
    }

    protected function validated(Request $request): array
    {
        return $request->validate([
            'customer_id'           => ['required', 'exists:customers,id'],
            'loan_product_id'       => ['required', 'exists:loan_products,id'],
            'loan_application_id'   => ['nullable', 'exists:loan_applications,id'],
            'loan_number'           => ['nullable', 'string', 'max:64'],
            'principal_amount'      => ['required', 'numeric', 'min:0'],
            'approved_amount'       => ['nullable', 'numeric', 'min:0'],
            'outstanding_balance'   => ['nullable', 'numeric', 'min:0'],
            'interest_rate'         => ['required', 'numeric', 'min:0', 'max:1'],
            'tenure_months'         => ['required', 'integer', 'min:1', 'max:120'],
            'status'                => ['required', 'in:pending,active,closed,defaulted,written_off'],
            'disbursement_date'     => ['nullable', 'date'],
            'maturity_date'         => ['nullable', 'date'],
            'next_due_date'         => ['nullable', 'date'],
        ]);
    }

    protected function generateLoanNumber(?LoanProduct $product = null): string
    {
        if ($product) {
            return app(\App\Services\ReferenceNumberService::class)->loanReference($product);
        }

        return 'LN-IL-'.str_pad((string) ((int) Loan::max('id') + 1), 4, '0', STR_PAD_LEFT);
    }
}
