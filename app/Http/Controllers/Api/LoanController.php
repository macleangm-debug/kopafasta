<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Services\LoanDisbursementOrchestrator;
use App\Services\ReferenceNumberService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

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
            'status' => 'pending',
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

    public function disburse(Request $request, Loan $loan, LoanDisbursementOrchestrator $orchestrator)
    {
        $this->authorize('disburse', $loan);

        $data = $request->validate([
            'channel' => ['sometimes', 'string', 'max:60'],
        ]);

        try {
            $loan = $orchestrator->disburse(
                $loan,
                $request->user(),
                $data['channel'] ?? 'bank_transfer',
            );
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Disbursement failed.',
                'errors' => $e->errors(),
            ], 422);
        }

        return response()->json($loan->load(['fees', 'disbursements', 'repaymentSchedules']));
    }

    public function reverseDisbursement(Request $request, Loan $loan, LoanDisbursementOrchestrator $orchestrator)
    {
        $this->authorize('reverseDisbursement', $loan);

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        try {
            $loan = $orchestrator->reverseDisbursement($loan, $request->user(), $data['reason']);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Disbursement reversal failed.',
                'errors' => $e->errors(),
            ], 422);
        }

        return response()->json($loan->load(['customer', 'product', 'application', 'disbursements']));
    }
}
