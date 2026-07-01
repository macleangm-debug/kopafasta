<?php

namespace App\Services;

use App\Models\CreditHistory;
use App\Models\LoanApplication;
use App\Models\Setting;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class CrbBillingService
{
    public function costPerRequest(): float
    {
        return max(0, (float) (Setting::group('kyc')['crb_cost_per_request'] ?? 0));
    }

    /** @return array{month: string, requests: int, estimated_cost: float, fresh_reuse_count: int} */
    public function monthlySummary(?CarbonImmutable $month = null): array
    {
        $start = ($month ?? CarbonImmutable::now())->startOfMonth();
        $end = $start->endOfMonth();

        $requests = CreditHistory::query()
            ->whereBetween('checked_at', [$start, $end])
            ->whereIn('source', ['crb', 'crb_stub'])
            ->count();

        $costPerRequest = $this->costPerRequest();

        return [
            'month'            => $start->format('F Y'),
            'requests'         => $requests,
            'estimated_cost'   => round($requests * $costPerRequest, 2),
            'fresh_reuse_count'=> $this->freshReuseCount($start, $end),
        ];
    }

    /** @return Collection<int, array{month: string, requests: int, estimated_cost: float}> */
    public function recentMonths(int $months = 6): Collection
    {
        return collect(range(0, max(0, $months - 1)))
            ->map(fn (int $offset) => $this->monthlySummary(CarbonImmutable::now()->subMonths($offset)->startOfMonth()));
    }

    /**
     * @return array{
     *     rows: list<array<string, mixed>>,
     *     summary: array{requests: int, estimated_cost: float, month: string}
     * }
     */
    public function auditReport(?CarbonImmutable $from = null, ?CarbonImmutable $to = null): array
    {
        $from ??= CarbonImmutable::now()->startOfMonth();
        $to ??= CarbonImmutable::now()->endOfDay();
        $costPerRequest = $this->costPerRequest();

        $histories = CreditHistory::query()
            ->with(['customer'])
            ->whereBetween('checked_at', [$from, $to])
            ->whereIn('source', ['crb', 'crb_stub'])
            ->orderByDesc('checked_at')
            ->get();

        $rows = $histories->map(fn (CreditHistory $history) => $this->auditRow($history, $costPerRequest))->values()->all();

        return [
            'rows'    => $rows,
            'summary' => [
                'requests'       => count($rows),
                'estimated_cost' => round(count($rows) * $costPerRequest, 2),
                'month'          => $from->format('F Y').' – '.$to->format('M j, Y'),
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function auditRow(CreditHistory $history, ?float $costPerRequest = null): array
    {
        $costPerRequest ??= $this->costPerRequest();
        $payload = $history->payload ?? [];
        $audit = is_array($payload['audit'] ?? null) ? $payload['audit'] : [];
        $customer = $history->customer;
        $application = $this->resolveApplication($history, $audit);

        $reportType = (string) ($payload['report_type'] ?? 'credit');
        $hasCredit = isset($payload['credit']) || $reportType === 'credit';
        $hasError = filled($payload['error'] ?? $audit['error'] ?? null);

        return [
            'id'                   => $history->id,
            'request_date'         => $history->checked_at?->format('Y-m-d'),
            'request_time'         => $history->checked_at?->format('H:i:s'),
            'customer_name'        => $customer?->full_name ?? '—',
            'national_id'          => $payload['national_id'] ?? $customer?->national_id ?? '—',
            'application_id'       => $application?->application_number ?? ($audit['application_number'] ?? '—'),
            'application_type'     => $application?->loan_group_id ? 'Group' : 'Individual',
            'provider'             => $history->source === 'crb_stub' ? 'CRB (stub)' : 'CRB',
            'request_status'       => $hasError ? 'Failed' : 'Success',
            'response_status'      => $hasCredit && $history->score !== null
                ? 'Score '.$history->score
                : ($reportType === 'identity' ? 'Identity verified' : ($hasError ? 'No response' : 'Received')),
            'cost'                 => round($costPerRequest, 2),
            'invoice_status'       => (string) ($audit['invoice_status'] ?? 'Pending'),
            'requested_by'         => (string) ($audit['requested_by_name'] ?? $audit['requested_by'] ?? 'System'),
            'reference_number'     => (string) ($payload['crb_ruid'] ?? $audit['reference'] ?? ('CRB-'.$history->id)),
            'reused'               => (bool) ($payload['reused'] ?? false),
        ];
    }

    /** @param  array<string, mixed>  $audit */
    private function resolveApplication(CreditHistory $history, array $audit): ?LoanApplication
    {
        $applicationId = (int) ($audit['loan_application_id'] ?? 0);
        if ($applicationId > 0) {
            return LoanApplication::find($applicationId);
        }

        if (! $history->customer_id) {
            return null;
        }

        return LoanApplication::query()
            ->where('customer_id', $history->customer_id)
            ->where('created_at', '<=', $history->checked_at ?? now())
            ->latest('created_at')
            ->first();
    }

    /** @return array<string, mixed> */
    public function auditContext(?LoanApplication $application = null, ?User $actor = null, string $trigger = 'system'): array
    {
        return array_filter([
            'loan_application_id' => $application?->id,
            'application_number'  => $application?->application_number,
            'application_type'    => $application?->loan_group_id ? 'group' : 'individual',
            'requested_by'        => $actor?->id,
            'requested_by_name'   => $actor?->name,
            'trigger'             => $trigger,
            'invoice_status'      => 'Pending',
            'requested_at'        => now()->toIso8601String(),
        ]);
    }

    private function freshReuseCount(CarbonImmutable $start, CarbonImmutable $end): int
    {
        return CreditHistory::query()
            ->whereBetween('checked_at', [$start, $end])
            ->whereIn('source', ['crb', 'crb_stub'])
            ->where('payload->reused', true)
            ->count();
    }
}
