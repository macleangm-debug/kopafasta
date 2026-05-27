<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Services\LoanDisbursementService;
use App\Services\RepaymentScheduleGenerator;
use App\Services\LoanWriteOffService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class LoanController extends Controller
{
    public function create()
    {
        return view('admin.loans.create', $this->formData());
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $data['loan_number']         = $data['loan_number'] ?? $this->generateLoanNumber();
        $data['approved_amount']     = $data['approved_amount'] ?? $data['principal_amount'];
        $data['outstanding_balance'] = $data['outstanding_balance'] ?? $data['principal_amount'];

        $loan = Loan::create($data);

        return redirect()
            ->route('admin.loans.show', $loan)
            ->with('status', 'Loan created.');
    }

    public function show(Loan $loan)
    {
        $loan->load(['customer', 'product', 'application', 'fees', 'repaymentSchedules' => fn ($q) => $q->orderBy('installment_no')]);

        return view('admin.loans.show', compact('loan'));
    }

    public function edit(Loan $loan)
    {
        return view('admin.loans.edit', ['loan' => $loan] + $this->formData());
    }

    public function update(Request $request, Loan $loan)
    {
        $loan->update($this->validated($request));

        return redirect()
            ->route('admin.loans.show', $loan)
            ->with('status', 'Loan updated.');
    }

    public function destroy(Loan $loan)
    {
        $loan->delete();

        return redirect()
            ->route('admin.loans.index')
            ->with('status', 'Loan deleted.');
    }

    public function disburse(Loan $loan, LoanDisbursementService $service, RepaymentScheduleGenerator $scheduler)
    {
        $loan->update([
            'status' => 'active',
            'disbursement_date' => $loan->disbursement_date ?? now()->toDateString(),
        ]);

        $applied = $service->applyFees($loan->fresh());

        // Build the amortisation schedule (idempotent)
        $installments = $scheduler->generate($loan->fresh());

        if ($loan->loan_application_id) {
            LoanApplication::whereKey($loan->loan_application_id)->update([
                'status' => 'disbursed',
                'current_stage' => 'disbursement',
                'disbursed_at' => now(),
            ]);
        }

        return redirect()
            ->route('admin.loans.show', $loan)
            ->with('status', 'Loan disbursed. '.count($applied).' fee(s) applied · '.$installments.' installment(s) scheduled.');
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

        return redirect()
            ->route('admin.loans.show', $loan)
            ->with('status', 'Loan written off.'.($entry ? ' Journal '.$entry->entry_number.' posted.' : ''));
    }

    protected function formData(): array
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

    protected function generateLoanNumber(): string
    {
        return 'LN-'.now()->format('Ymd').'-'.Str::upper(Str::random(5));
    }
}
