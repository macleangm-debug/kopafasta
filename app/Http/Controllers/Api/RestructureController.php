<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Loan;
use App\Models\RestructureRequest;
use Illuminate\Http\Request;

class RestructureController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', RestructureRequest::class);

        return response()->json(RestructureRequest::with('loan')->latest()->paginate(20));
    }

    public function store(Request $request)
    {
        $this->authorize('create', RestructureRequest::class);

        $data = $request->validate([
            'loan_id' => ['required', 'exists:loans,id'],
            'reason' => ['required', 'string', 'max:255'],
            'new_tenure_months' => ['nullable', 'integer', 'min:1'],
            'new_interest_rate' => ['nullable', 'numeric', 'min:0'],
        ]);

        return response()->json(RestructureRequest::create($data), 201);
    }

    public function show(RestructureRequest $restructureRequest)
    {
        $this->authorize('view', $restructureRequest);

        return response()->json($restructureRequest->load('loan'));
    }

    public function update(Request $request, RestructureRequest $restructureRequest)
    {
        $this->authorize('update', $restructureRequest);

        $data = $request->validate([
            'reason' => ['sometimes', 'string', 'max:255'],
            'new_tenure_months' => ['nullable', 'integer', 'min:1'],
            'new_interest_rate' => ['nullable', 'numeric', 'min:0'],
            'decision_notes' => ['nullable', 'string'],
            'status' => ['sometimes', 'string', 'max:30'],
        ]);

        $restructureRequest->update($data);

        return response()->json($restructureRequest->fresh());
    }

    public function destroy(RestructureRequest $restructureRequest)
    {
        $this->authorize('delete', $restructureRequest);

        $restructureRequest->delete();

        return response()->json(status: 204);
    }

    public function approve(Request $request, RestructureRequest $restructureRequest)
    {
        $this->authorize('approve', $restructureRequest);

        $loan = Loan::findOrFail($restructureRequest->loan_id);
        $actor = $request->user();

        if ($actor && $actor->role !== 'admin') {
            $limit = (float) ($actor->approval_limit ?? 0);
            $amount = (float) ($loan->outstanding_balance ?: $loan->approved_amount);

            if ($amount > $limit) {
                return response()->json([
                    'message' => 'Approval limit exceeded',
                    'required_amount' => $amount,
                    'approval_limit' => $limit,
                ], 422);
            }
        }

        $data = $request->validate([
            'approved_by' => ['nullable', 'exists:users,id'],
            'decision_notes' => ['nullable', 'string'],
        ]);

        $restructureRequest->update([
            'status' => 'approved',
            'approved_by' => $data['approved_by'] ?? null,
            'approved_at' => now(),
            'decision_notes' => $data['decision_notes'] ?? null,
        ]);

        $loan->update([
            'tenure_months' => $restructureRequest->new_tenure_months ?? $loan->tenure_months,
            'interest_rate' => $restructureRequest->new_interest_rate ?? $loan->interest_rate,
        ]);

        // GL: post restructure fee if any (Dr Loan Receivable / Cr Fee Income)
        $feeAmount = (float) ($restructureRequest->fee_amount ?? 0);
        if ($feeAmount > 0) {
            $ledger = app(\App\Services\LedgerService::class);
            $recv = $ledger->loanReceivableAccountId();
            $feeIncome = (int) (\App\Models\Setting::get('finance.fee_income_gl_account_id') ?? 0) ?: null;
            if ($recv && $feeIncome) {
                try {
                    $ledger->post(
                        [
                            ['account_id' => $recv, 'debit' => $feeAmount, 'credit' => 0, 'description' => 'Restructure fee ' . $loan->loan_number],
                            ['account_id' => $feeIncome, 'debit' => 0, 'credit' => $feeAmount, 'description' => 'Fee income ' . $loan->loan_number],
                        ],
                        'Restructure fee ' . $loan->loan_number,
                        $restructureRequest,
                        now()->toDateString(),
                        'Loan restructure approved.'
                    );
                    $loan->update(['outstanding_balance' => (float) $loan->outstanding_balance + $feeAmount]);
                } catch (\Throwable $e) {
                    logger()->warning('Restructure JE not posted: ' . $e->getMessage());
                }
            }
        }

        return response()->json($restructureRequest->fresh('loan'));
    }
}
