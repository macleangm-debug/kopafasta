<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Disbursement;
use App\Models\Loan;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DisbursementController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', Disbursement::class);

        return response()->json(Disbursement::with(['loan', 'recipients'])->latest()->paginate(20));
    }

    public function store(Request $request)
    {
        $this->authorize('create', Disbursement::class);

        $data = $request->validate([
            'loan_id' => ['required', 'exists:loans,id'],
            'channel' => ['required', 'string', 'max:60'],
            'amount' => ['required', 'numeric', 'min:1'],
            'status' => ['sometimes', 'string', 'max:30'],
            'notes' => ['nullable', 'string'],
        ]);

        $data['reference'] = 'DSB-'.strtoupper(Str::random(10));

        return response()->json(Disbursement::create($data), 201);
    }

    public function show(Disbursement $disbursement)
    {
        $this->authorize('view', $disbursement);

        return response()->json($disbursement->load(['loan', 'recipients']));
    }

    public function update(Request $request, Disbursement $disbursement)
    {
        $this->authorize('update', $disbursement);

        $data = $request->validate([
            'status' => ['sometimes', 'string', 'max:30'],
            'notes' => ['nullable', 'string'],
        ]);

        $disbursement->update($data);

        return response()->json($disbursement->fresh());
    }

    public function destroy(Disbursement $disbursement)
    {
        $this->authorize('delete', $disbursement);

        $disbursement->delete();

        return response()->json(status: 204);
    }

    public function release(Disbursement $disbursement)
    {
        $this->authorize('release', $disbursement);

        $actor = request()->user();
        if ($actor && $actor->role !== 'admin') {
            $limit = (float) ($actor->approval_limit ?? 0);
            $amount = (float) $disbursement->amount;

            if ($amount > $limit) {
                return response()->json([
                    'message' => 'Approval limit exceeded',
                    'required_amount' => $amount,
                    'approval_limit' => $limit,
                ], 422);
            }
        }

        $disbursement->update([
            'status' => 'released',
            'released_at' => now(),
        ]);

        Loan::whereKey($disbursement->loan_id)->update([
            'status' => 'active',
            'disbursement_date' => now()->toDateString(),
        ]);

        if ($loan = Loan::find($disbursement->loan_id)) {
            app(\App\Services\LoanDisbursementService::class)->applyFees($loan);
        }

        return response()->json($disbursement->fresh());
    }
}
