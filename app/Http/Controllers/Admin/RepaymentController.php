<?php

namespace App\Http\Controllers\Admin;

use App\Models\Loan;
use App\Models\Repayment;
use App\Services\RepaymentPostingService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class RepaymentController extends ResourceController
{
    protected string $model = Repayment::class;
    protected string $routePrefix = 'admin.repayments';
    protected string $viewFolder = 'repayments';
    protected string $singular = 'repayment';

    protected function rules(?Model $model = null): array
    {
        return [
            'loan_id'              => ['required', 'exists:loans,id'],
            'repayment_schedule_id'=> ['nullable', 'exists:repayment_schedules,id'],
            'reference'            => ['nullable', 'string', 'max:80'],
            'channel'              => ['required', 'string', 'max:50'],
            'amount'               => ['required', 'numeric', 'min:0'],
            'principal_component'  => ['nullable', 'numeric', 'min:0'],
            'interest_component'   => ['nullable', 'numeric', 'min:0'],
            'penalty_component'    => ['nullable', 'numeric', 'min:0'],
            'status'               => ['required', 'in:pending,posted,reversed,failed,received,allocated'],
            'paid_at'              => ['nullable', 'date'],
        ];
    }

    protected function formData(?Model $record = null): array
    {
        return [
            'loans'    => Loan::orderByDesc('id')->limit(300)->pluck('loan_number', 'id'),
            'channels' => ['cash' => 'Cash', 'mpesa' => 'M-Pesa', 'tigopesa' => 'Tigo Pesa', 'airtel_money' => 'Airtel Money', 'bank' => 'Bank transfer', 'cheque' => 'Cheque'],
            'statuses' => ['pending' => 'Pending', 'posted' => 'Posted', 'reversed' => 'Reversed', 'failed' => 'Failed'],
        ];
    }

    public function create()
    {
        $record = null;
        if (request()->filled('loan_id')) {
            $record = new Repayment(['loan_id' => (int) request('loan_id')]);
        }

        return view("admin.{$this->viewFolder}.create", $this->formData($record) + compact('record'));
    }

    protected function transform(array $data, ?Model $existing = null): array
    {
        if (empty($data['reference'])) {
            $data['reference'] = 'RPY-'.now()->format('ymd').'-'.Str::upper(Str::random(5));
        }

        // Auto-allocate if components not supplied
        $hasComponents = ((float) ($data['principal_component'] ?? 0))
                       + ((float) ($data['interest_component'] ?? 0))
                       + ((float) ($data['penalty_component'] ?? 0)) > 0;
        if (!$hasComponents && !empty($data['loan_id']) && !empty($data['amount'])) {
            $loan = Loan::find($data['loan_id']);
            if ($loan) {
                $alloc = app(RepaymentPostingService::class)->allocate($loan, (float) $data['amount']);
                $data['principal_component'] = $alloc['principal'];
                $data['interest_component']  = $alloc['interest'];
                $data['penalty_component']   = $alloc['penalty'];
            }
        }

        $data['paid_at'] = $data['paid_at'] ?? now();
        return $data;
    }

    public function store(Request $request)
    {
        $data = $this->transform($request->validate($this->rules()));
        $repayment = Repayment::create($data);

        app(RepaymentPostingService::class)->post($repayment);
        $this->auditAdminCreated($repayment);

        return redirect()
            ->route('admin.repayments.show', $repayment)
            ->with('status', 'Repayment recorded and posted to ledger.');
    }
}

