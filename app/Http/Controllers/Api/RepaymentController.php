<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Loan;
use App\Models\Repayment;
use App\Models\RepaymentSchedule;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class RepaymentController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', Repayment::class);

        return response()->json(Repayment::with(['loan', 'schedule'])->latest()->paginate(20));
    }

    public function store(Request $request)
    {
        $this->authorize('create', Repayment::class);

        $data = $request->validate([
            'loan_id' => ['required', 'exists:loans,id'],
            'repayment_schedule_id' => ['nullable', 'exists:repayment_schedules,id'],
            'channel' => ['required', 'string', 'max:60'],
            'amount' => ['required', 'numeric', 'min:1'],
            'paid_at' => ['nullable', 'date'],
            'principal_component' => ['nullable', 'numeric', 'min:0'],
            'interest_component'  => ['nullable', 'numeric', 'min:0'],
            'penalty_component'   => ['nullable', 'numeric', 'min:0'],
        ]);

        $loan = Loan::findOrFail($data['loan_id']);

        // Auto-allocate if components not given
        $hasComp = ((float) ($data['principal_component'] ?? 0))
                 + ((float) ($data['interest_component'] ?? 0))
                 + ((float) ($data['penalty_component'] ?? 0)) > 0;
        if (!$hasComp) {
            $alloc = app(\App\Services\RepaymentPostingService::class)->allocate($loan, (float) $data['amount']);
            $data['principal_component'] = $alloc['principal'];
            $data['interest_component']  = $alloc['interest'];
            $data['penalty_component']   = $alloc['penalty'];
        }

        $repayment = Repayment::create([
            'loan_id'              => $data['loan_id'],
            'repayment_schedule_id'=> $data['repayment_schedule_id'] ?? null,
            'channel'              => $data['channel'],
            'amount'               => $data['amount'],
            'reference'            => 'RCP-'.strtoupper(Str::random(10)),
            'status'               => 'received',
            'principal_component'  => (float) ($data['principal_component'] ?? 0),
            'interest_component'   => (float) ($data['interest_component'] ?? 0),
            'penalty_component'    => (float) ($data['penalty_component'] ?? 0),
            'paid_at'              => $data['paid_at'] ?? now(),
        ]);

        app(\App\Services\RepaymentPostingService::class)->post($repayment);

        return response()->json($repayment->fresh(), 201);
    }

    public function show(Repayment $repayment)
    {
        $this->authorize('view', $repayment);

        return response()->json($repayment->load(['loan', 'schedule']));
    }

    public function schedule(Loan $loan)
    {
        $this->authorize('view', $loan);

        $schedules = $loan->repaymentSchedules()->orderBy('installment_no')->get();

        return response()->json($schedules);
    }
}
