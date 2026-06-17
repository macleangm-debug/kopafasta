<?php

namespace App\Http\Controllers\Admin;

use App\Models\CapitalWithdrawalRequest;
use App\Models\Lender;
use App\Services\CapitalPartnerCapitalService;
use App\Services\CapitalPartnerLedgerService;
use App\Services\CapitalPartnerMetricsService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class LenderController extends ResourceController
{
    protected string $model = Lender::class;
    protected string $routePrefix = 'admin.lenders';
    protected string $viewFolder = 'lenders';
    protected string $singular = 'lender';

    protected function rules(?Model $model = null): array
    {
        return [
            'code'           => ['required', 'string', 'max:30'],
            'name'           => ['required', 'string', 'max:150'],
            'type'           => ['required', 'in:bank,institutional,individual,sacco,other'],
            'contact_person' => ['nullable', 'string', 'max:150'],
            'phone'          => ['nullable', 'string', 'max:30'],
            'email'          => ['nullable', 'email', 'max:150'],
            'address'        => ['nullable', 'string', 'max:500'],
            'credit_limit'   => ['nullable', 'numeric', 'min:0'],
            'allocation_priority' => ['nullable', 'integer', 'min:1', 'max:9999'],
            'status'         => ['required', 'in:active,inactive,suspended'],
        ];
    }

    protected function formData(?Model $record = null): array
    {
        return [
            'types'    => ['bank' => 'Bank', 'institutional' => 'Institutional', 'individual' => 'Individual', 'sacco' => 'SACCO', 'other' => 'Other'],
            'statuses' => ['active' => 'Active', 'inactive' => 'Inactive', 'suspended' => 'Suspended'],
        ];
    }

    public function show($id)
    {
        $record = Lender::with('pools')->findOrFail($id);
        $metricsService = app(CapitalPartnerMetricsService::class);
        $metrics = $metricsService->forLender($record);
        $pools = $metricsService->poolRows($record);
        $ledger = app(CapitalPartnerLedgerService::class)->forLender($record);
        $allocations = $metricsService->allocationsForLender($record);
        $fundingHistory = app(CapitalPartnerLedgerService::class)->fundingHistory($record);
        $auditTrail = $metricsService->auditTrailForLender($record);
        $pendingWithdrawals = CapitalWithdrawalRequest::query()
            ->where('lender_id', $record->id)
            ->where('status', 'pending')
            ->latest('id')
            ->get();

        return view('admin.lenders.show', compact(
            'record',
            'metrics',
            'pools',
            'ledger',
            'allocations',
            'fundingHistory',
            'auditTrail',
            'pendingWithdrawals',
        ));
    }

    public function adjustCapitalForm($id)
    {
        $record = Lender::findOrFail($id);
        $metrics = app(CapitalPartnerMetricsService::class)->forLender($record);

        return view('admin.lenders.adjust-capital', compact('record', 'metrics'));
    }

    public function adjustCapital(Request $request, $id, CapitalPartnerCapitalService $capital)
    {
        $record = Lender::findOrFail($id);
        $data = $request->validate([
            'direction' => ['required', 'in:increase,decrease'],
            'amount'    => ['required', 'numeric', 'min:1'],
            'notes'     => ['nullable', 'string', 'max:2000'],
        ]);

        $amount = (float) $data['amount'];
        if ($data['direction'] === 'increase') {
            $capital->increaseCapital($record, $amount, $data['notes'] ?? null, $request->user());
            $message = 'Capital increased successfully.';
        } else {
            $capital->decreaseCapital($record, $amount, $data['notes'] ?? null, $request->user());
            $message = 'Available capital reduced.';
        }

        return redirect()
            ->route('admin.lenders.show', $record)
            ->with('status', $message);
    }

    public function requestWithdrawal(Request $request, $id, CapitalPartnerCapitalService $capital)
    {
        $record = Lender::findOrFail($id);
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:1'],
            'notes'  => ['nullable', 'string', 'max:2000'],
        ]);

        $capital->requestWithdrawal($record, (float) $data['amount'], $data['notes'] ?? null, $request->user());

        return redirect()
            ->route('admin.lenders.show', $record)
            ->with('status', 'Withdrawal request submitted for approval.');
    }
}
