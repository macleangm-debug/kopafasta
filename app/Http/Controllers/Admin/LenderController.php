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
            'funding_source'      => ['required', 'in:internal,external'],
            'revenue_share_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'registration_number' => ['nullable', 'string', 'max:80'],
            'tax_id'              => ['nullable', 'string', 'max:40'],
            'license_number'      => ['nullable', 'string', 'max:80'],
            'kyc_status'          => ['nullable', 'in:pending,submitted,verified,rejected'],
            'kyc_verified_at'     => ['nullable', 'date'],
            'kyc_notes'           => ['nullable', 'string', 'max:2000'],
            'status'         => ['required', 'in:active,inactive,suspended'],
        ];
    }

    protected function transform(array $data, ?Model $existing = null): array
    {
        $data = parent::transform($data, $existing);

        if (($data['funding_source'] ?? 'external') === 'internal') {
            $data['kyc_status'] = null;
            $data['kyc_verified_at'] = null;
            $data['registration_number'] = null;
            $data['tax_id'] = null;
            $data['license_number'] = null;
            $data['kyc_notes'] = null;
        } else {
            $data['kyc_status'] = $data['kyc_status'] ?? 'pending';
            if (($data['kyc_status'] ?? '') === 'verified' && empty($data['kyc_verified_at'])) {
                $data['kyc_verified_at'] = now();
            }
            if (($data['kyc_status'] ?? '') !== 'verified') {
                $data['kyc_verified_at'] = null;
            }
        }

        if (blank($data['revenue_share_percent'] ?? null)) {
            $data['revenue_share_percent'] = null;
        }

        return $data;
    }

    protected function formData(?Model $record = null): array
    {
        $allocation = app(\App\Services\CapitalPartnerAllocationService::class);

        return [
            'types'    => ['bank' => 'Bank', 'institutional' => 'Institutional', 'individual' => 'Individual', 'sacco' => 'SACCO', 'other' => 'Other'],
            'statuses' => ['active' => 'Active', 'inactive' => 'Inactive', 'suspended' => 'Suspended'],
            'fundingSources' => [
                'external' => 'External (capital partner — participates in loan allocation)',
                'internal' => 'Internal (company balance sheet — excluded from partner allocation)',
            ],
            'kycStatuses' => [
                'pending'   => 'Pending',
                'submitted' => 'Submitted for review',
                'verified'  => 'Verified',
                'rejected'  => 'Rejected',
            ],
            'defaultRevenueSharePercent' => $allocation->partnerInterestSharePercent(),
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

        $allocationService = app(\App\Services\CapitalPartnerAllocationService::class);
        $partnerSharePercent = $allocationService->partnerInterestSharePercent($record);
        $companySharePercent = $allocationService->companyInterestSharePercent($record);

        return view('admin.lenders.show', compact(
            'record',
            'metrics',
            'pools',
            'ledger',
            'allocations',
            'fundingHistory',
            'auditTrail',
            'pendingWithdrawals',
            'partnerSharePercent',
            'companySharePercent',
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
