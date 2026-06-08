<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Services\ReferenceNumberService;
use Illuminate\Http\Request;

class LoanController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', Loan::class);

        return response()->json(Loan::with(['customer', 'product', 'application'])->latest()->paginate(20));
    }

    public function store(Request $request)
    {
        $this->authorize('create', Loan::class);

        $data = $request->validate([
            'loan_application_id' => ['nullable', 'exists:loan_applications,id'],
            'customer_id' => ['required', 'exists:customers,id'],
            'loan_product_id' => ['required', 'exists:loan_products,id'],
            'principal_amount' => ['required', 'numeric', 'min:1'],
            'approved_amount' => ['required', 'numeric', 'min:1'],
            'interest_rate' => ['required', 'numeric', 'min:0'],
            'tenure_months' => ['required', 'integer', 'min:1'],
        ]);

        $product = LoanProduct::findOrFail($data['loan_product_id']);

        $loan = Loan::create(array_merge($data, [
            'loan_number' => app(ReferenceNumberService::class)->loanReference($product),
            'status' => 'approved',
            'outstanding_balance' => $data['approved_amount'],
        ]));

        if (! empty($data['loan_application_id'])) {
            LoanApplication::whereKey($data['loan_application_id'])->update([
                'status' => 'approved',
                'current_stage' => 'disbursement',
                'approved_at' => now(),
            ]);
        }

        return response()->json($loan, 201);
    }

    public function show(Loan $loan)
    {
        $this->authorize('view', $loan);

        return response()->json($loan->load(['customer', 'product', 'application', 'disbursements', 'repaymentSchedules', 'repayments']));
    }

    public function update(Request $request, Loan $loan)
    {
        $this->authorize('update', $loan);

        $data = $request->validate([
            'status' => ['sometimes', 'string', 'max:30'],
            'interest_rate' => ['sometimes', 'numeric', 'min:0'],
            'tenure_months' => ['sometimes', 'integer', 'min:1'],
            'maturity_date' => ['nullable', 'date'],
            'next_due_date' => ['nullable', 'date'],
        ]);

        $loan->update($data);

        return response()->json($loan->fresh());
    }

    public function destroy(Loan $loan)
    {
        $this->authorize('delete', $loan);

        $loan->delete();

        return response()->json(status: 204);
    }

    public function disburse(Loan $loan)
    {
        $this->authorize('disburse', $loan);

        $loan->update([
            'status' => 'active',
            'disbursement_date' => now()->toDateString(),
        ]);

        app(\App\Services\LoanDisbursementService::class)->applyFees($loan->fresh());

        if ($loan->loan_application_id) {
            LoanApplication::whereKey($loan->loan_application_id)->update([
                'status' => 'disbursed',
                'current_stage' => 'disbursement',
                'disbursed_at' => now(),
            ]);
        }

        return response()->json($loan->fresh()->load('fees'));
    }
}
