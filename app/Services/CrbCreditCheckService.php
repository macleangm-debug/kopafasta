<?php

namespace App\Services;

use App\DataTransferObjects\CrbIdentityResult;
use App\Models\CreditHistory;
use App\Models\Customer;
use App\Models\LoanApplication;
use App\Models\Setting;
use App\Support\NidaNumber;
use Illuminate\Support\Facades\Log;

class CrbCreditCheckService
{
    public function __construct(
        private readonly CrbService $crb,
        private readonly CrbFreshnessService $freshness,
        private readonly NidaVerificationService $nida,
    ) {}

    /** Credit report pulls for underwriting — not a borrower profile requirement. */
    public function creditPullEnabled(): bool
    {
        return (bool) (Setting::group('kyc')['crb_check_required'] ?? true);
    }

    public function latest(Customer $customer): ?CreditHistory
    {
        return CreditHistory::query()
            ->where('customer_id', $customer->id)
            ->latest('checked_at')
            ->first();
    }

    /**
     * Store CRB identity lookup after successful NIDA verification.
     * Does not expose credit data to the borrower.
     */
    public function recordIdentityVerification(Customer $customer, array $identityData, array $raw = []): CreditHistory
    {
        $latest = $this->latest($customer);
        if ($latest && $this->freshness->isFresh($latest)) {
            return $latest;
        }

        $payload = [
            'report_type'     => 'identity',
            'national_id'     => $identityData['national_id'] ?? $customer->national_id,
            'full_name'       => $identityData['full_name'] ?? null,
            'search_score'    => $identityData['search_score'] ?? null,
            'crb_ruid'        => $identityData['crb_ruid'] ?? null,
            'identity_raw'    => $raw,
        ];

        return CreditHistory::create([
            'customer_id' => $customer->id,
            'source'      => $this->crb->usesStub() ? 'crb_stub' : 'crb',
            'score'       => null,
            'risk_grade'  => null,
            'payload'     => $payload,
            'checked_at'  => now(),
        ]);
    }

    /**
     * @return array{history: CreditHistory|null, reused: bool, refreshed: bool, error: string|null}
     */
    public function ensureFreshForSubmission(Customer $customer): array
    {
        if (! $this->creditPullEnabled()) {
            return [
                'history'   => $this->latest($customer),
                'reused'    => true,
                'refreshed' => false,
                'error'     => null,
            ];
        }

        $latest = $this->latest($customer);

        if ($latest && $this->freshness->isFresh($latest) && $this->hasCreditSection($latest)) {
            return [
                'history'   => $latest,
                'reused'    => true,
                'refreshed' => false,
                'error'     => null,
            ];
        }

        if ($latest && $this->freshness->isFresh($latest) && ! $this->hasCreditSection($latest)) {
            $enriched = $this->enrichWithCreditData($customer, $latest);

            return [
                'history'   => $enriched,
                'reused'    => false,
                'refreshed' => true,
                'error'     => null,
            ];
        }

        try {
            $history = $this->refreshCreditReport($customer);

            return [
                'history'   => $history,
                'reused'    => false,
                'refreshed' => (bool) $history,
                'error'     => $history ? null : 'CRB credit report could not be retrieved.',
            ];
        } catch (\Throwable $e) {
            Log::warning('CRB refresh failed on loan submission', [
                'customer_id' => $customer->id,
                'message'     => $e->getMessage(),
            ]);

            return [
                'history'   => $latest,
                'reused'    => (bool) $latest,
                'refreshed' => false,
                'error'     => $e->getMessage(),
            ];
        }
    }

    /**
     * Paid CRB pull. Never call when a fresh credit report already exists unless $force is true.
     * Freshness window comes from Settings → KYC → crb_freshness_days (default 90).
     */
    public function refreshCreditReport(Customer $customer, array $auditContext = [], bool $force = false): ?CreditHistory
    {
        if (! $force) {
            $latest = $this->latest($customer);
            if ($latest && $this->freshness->isFresh($latest) && $this->hasCreditSection($latest)) {
                return $latest;
            }
        }

        if (! filled($customer->national_id)) {
            return null;
        }

        $formatted = NidaNumber::format($customer->national_id);

        if (! $formatted) {
            return null;
        }

        $identityResult = $this->crb->verifyConsumerIdentity(
            identifierNumber: $formatted,
            fullName: $customer->full_name,
            dateOfBirth: optional($customer->date_of_birth)->format('Y-m-d'),
            mobile: $customer->phone,
        );

        if (! $identityResult->success && ! $this->nida->isVerified($customer)) {
            return null;
        }

        $credit = $this->buildCreditPayload($customer, $identityResult);
        $payload = array_merge($credit, [
            'report_type'  => 'credit',
            'national_id'  => $formatted,
            'crb_ruid'     => $identityResult->crbRuid
                ?? ($credit['report_meta']['ruid'] ?? null)
                ?? ($customer->kyc?->payload['nida_verification']['crb_ruid'] ?? null),
            'search_score' => $identityResult->searchScore
                ?? ($credit['report_meta']['search_score'] ?? null),
            'identity_raw' => $identityResult->raw,
        ]);

        if ($auditContext !== []) {
            $payload['audit'] = $auditContext;
        }

        return CreditHistory::create([
            'customer_id' => $customer->id,
            'source'      => $this->crb->usesStub() ? 'crb_stub' : 'crb',
            'score'       => $credit['credit']['score'] ?? null,
            'risk_grade'  => $credit['credit']['risk_grade'] ?? null,
            'payload'     => $payload,
            'checked_at'  => now(),
        ]);
    }

    /** @param  array{history?: CreditHistory|null, reused?: bool, refreshed?: bool, error?: string|null}  $meta */
    /**
     * Ensure a fresh (or reused) CRB for a guarantor Customer and store a pointer on the application.
     * Reuses within crb_freshness_days — does not re-bill when already fresh.
     *
     * @return array{summary: array<string, mixed>, reused: bool, refreshed: bool, error: string|null}
     */
    public function ensureGuarantorCrb(Customer $guarantor, LoanApplication $application, bool $force = false): array
    {
        if ($force) {
            try {
                $history = $this->refreshCreditReport($guarantor, [
                    'purpose' => 'guarantor_underwriting',
                    'loan_application_id' => $application->id,
                ], force: true);
                $meta = [
                    'history' => $history,
                    'reused' => false,
                    'refreshed' => (bool) $history,
                    'error' => $history ? null : 'CRB credit report could not be retrieved.',
                ];
            } catch (\Throwable $e) {
                Log::warning('Guarantor CRB force refresh failed', [
                    'customer_id' => $guarantor->id,
                    'application_id' => $application->id,
                    'message' => $e->getMessage(),
                ]);
                $meta = [
                    'history' => $this->latest($guarantor),
                    'reused' => false,
                    'refreshed' => false,
                    'error' => $e->getMessage(),
                ];
            }
        } else {
            $meta = $this->ensureFreshForSubmission($guarantor);
        }

        $history = $meta['history'] ?? null;
        $this->storeGuarantorCrbPointer($application, $guarantor, $meta);

        // Never use the borrower's application CRB blob for the guarantor.
        $summary = $this->summaryForCustomer($guarantor);
        $summary['submission_meta'] = [
            'reused' => (bool) ($meta['reused'] ?? false),
            'refreshed' => (bool) ($meta['refreshed'] ?? false),
            'error' => $meta['error'] ?? null,
        ];

        return [
            'summary' => $summary,
            'reused' => (bool) ($meta['reused'] ?? false),
            'refreshed' => (bool) ($meta['refreshed'] ?? false),
            'error' => $meta['error'] ?? null,
            'history' => $history,
        ];
    }

    /**
     * @param  array{history?: ?CreditHistory, reused?: bool, refreshed?: bool, error?: ?string}  $meta
     */
    public function storeGuarantorCrbPointer(LoanApplication $application, Customer $guarantor, array $meta): void
    {
        $history = $meta['history'] ?? null;
        $payload = $application->credit_appraisal_payload ?? [];
        $rows = collect($payload['guarantor_crb'] ?? [])
            ->reject(fn ($row) => (int) ($row['customer_id'] ?? 0) === (int) $guarantor->id)
            ->values()
            ->all();

        $rows[] = [
            'customer_id'       => $guarantor->id,
            'credit_history_id' => $history?->id,
            'checked_at'        => $history?->checked_at?->toIso8601String(),
            'score'             => $history?->score,
            'risk_grade'        => $history?->risk_grade,
            'reused'            => (bool) ($meta['reused'] ?? false),
            'refreshed'         => (bool) ($meta['refreshed'] ?? false),
            'error'             => $meta['error'] ?? null,
        ];

        $payload['guarantor_crb'] = $rows;
        $application->update(['credit_appraisal_payload' => $payload]);
    }

    public function attachToApplication(LoanApplication $application, ?CreditHistory $history, array $meta = []): void
    {
        if (! $history) {
            return;
        }

        $payload = $application->credit_appraisal_payload ?? [];
        $payload['crb'] = [
            'credit_history_id' => $history->id,
            'checked_at'        => $history->checked_at?->toIso8601String(),
            'source'            => $history->source,
            'score'             => $history->score,
            'risk_grade'        => $history->risk_grade,
            'freshness'         => $this->freshness->statusMeta($history),
            'days_since_check'  => $this->freshness->daysSinceCheck($history),
            'reused'            => (bool) ($meta['reused'] ?? false),
            'refreshed'         => (bool) ($meta['refreshed'] ?? false),
            'error'             => $meta['error'] ?? null,
            'report'            => $history->payload,
        ];

        $application->update(['credit_appraisal_payload' => $payload]);
    }

    /**
     * @param  list<array{customer_id: int, invitation_id?: int}>  $members
     */
    public function attachGroupMemberCrbs(LoanApplication $application, array $members): void
    {
        $payload = $application->credit_appraisal_payload ?? [];
        $rows = [];

        foreach ($members as $member) {
            $customer = Customer::find((int) ($member['customer_id'] ?? 0));
            if (! $customer) {
                continue;
            }

            $meta = $this->ensureFreshForSubmission($customer);
            $history = $meta['history'] ?? null;

            $rows[] = [
                'customer_id'       => $customer->id,
                'invitation_id'     => $member['invitation_id'] ?? null,
                'credit_history_id' => $history?->id,
                'checked_at'        => $history?->checked_at?->toIso8601String(),
                'score'             => $history?->score,
                'risk_grade'        => $history?->risk_grade,
                'reused'            => (bool) ($meta['reused'] ?? false),
                'refreshed'         => (bool) ($meta['refreshed'] ?? false),
                'error'             => $meta['error'] ?? null,
            ];
        }

        $payload['group_member_crb'] = $rows;
        $application->update(['credit_appraisal_payload' => $payload]);
    }

    /**
     * Fail-closed CRB freshness check for every group member before submission.
     *
     * @param  list<array{customer_id: int, invitation_id?: int, name?: string}>  $members
     * @return list<string> human-readable blocking errors (empty = ok)
     */
    public function validateGroupMemberCrbs(array $members): array
    {
        if (! $this->creditPullEnabled()) {
            return [];
        }

        $errors = [];

        foreach ($members as $member) {
            $customer = Customer::find((int) ($member['customer_id'] ?? 0));
            if (! $customer) {
                $errors[] = __('borrower.apply.group.crb_member_missing');
                continue;
            }

            $meta = $this->ensureFreshForSubmission($customer);
            $name = $member['name'] ?? $customer->full_name ?? ('#'.$customer->id);

            if (! empty($meta['error']) || ! ($meta['history'] ?? null)) {
                $errors[] = __('borrower.apply.group.crb_member_failed', [
                    'name'   => $name,
                    'reason' => $meta['error'] ?? __('borrower.apply.group.crb_unavailable'),
                ]);
            }
        }

        return $errors;
    }

    /** @return array<string, mixed> */
    public function summaryForCustomer(Customer $customer, ?LoanApplication $application = null): array
    {
        $applicationCrb = $application?->credit_appraisal_payload['crb'] ?? null;
        $history = $applicationCrb
            ? CreditHistory::find($applicationCrb['credit_history_id'] ?? null)
            : null;
        $history ??= $this->latest($customer);

        $payload = $history?->payload ?? [];
        $credit = $payload['credit'] ?? [];
        if ($credit === [] && isset($payload['score'])) {
            $credit = $payload;
        }
        $personal = $payload['personal'] ?? [];
        $reportMeta = $payload['report_meta'] ?? [];
        $kyc = $customer->kyc?->payload ?? [];
        $nidaVerification = $kyc['nida_verification'] ?? [];
        $freshness = $this->freshness->statusMeta($history);

        $score = $history?->score ?? ($credit['score'] ?? null);
        $recommendation = $credit['recommendation'] ?? $this->recommendationFromScore(is_numeric($score) ? (int) $score : null);

        $identityFromCustomer = [
            'full_name' => $customer->full_name,
            'national_id' => $customer->national_id,
            'date_of_birth' => optional($customer->date_of_birth)->format('d M Y'),
            'gender' => $customer->gender,
        ];

        return [
            'status'               => $history?->source ? strtoupper(str_replace('_', ' ', (string) $history->source)) : ($this->nida->isVerified($customer) ? 'Identity verified' : 'Not checked'),
            'freshness_label'      => $freshness['label'],
            'freshness_tone'       => $freshness['tone'],
            'is_fresh'             => $this->freshness->isFresh($history),
            'days_since_check'     => $this->freshness->daysSinceCheck($history),
            'score'                => $score,
            'risk_grade'           => $history?->risk_grade ?? ($credit['risk_grade'] ?? $customer->risk_band),
            'existing_loans'       => (int) ($credit['existing_loans'] ?? 0),
            'outstanding_balance'  => (float) ($credit['outstanding_balance'] ?? 0),
            'delinquencies'        => (int) ($credit['delinquencies'] ?? 0),
            'loan_history'         => $credit['loan_history'] ?? [],
            'recommendation'       => $recommendation,
            'checked_at'           => $history?->checked_at,
            'crb_ruid'             => $payload['crb_ruid'] ?? $reportMeta['ruid'] ?? $nidaVerification['crb_ruid'] ?? null,
            'report_type'          => $payload['report_type'] ?? null,
            'search_score'         => $payload['search_score'] ?? $reportMeta['search_score'] ?? null,
            'identity'             => array_merge($identityFromCustomer, array_filter([
                'full_name' => $personal['full_name'] ?? null,
                'surname' => $personal['surname'] ?? null,
                'first_name' => $personal['first_name'] ?? null,
                'middle_names' => $personal['middle_names'] ?? null,
                'gender' => $personal['gender'] ?? null,
                'date_of_birth' => $personal['date_of_birth'] ?? null,
                'national_id' => $customer->national_id,
            ], fn ($v) => filled($v))),
            'personal'             => $personal,
            'credit_detail'        => $credit,
            'report_meta'          => $reportMeta,
            'submission_meta'      => $applicationCrb ? [
                'reused'    => (bool) ($applicationCrb['reused'] ?? false),
                'refreshed' => (bool) ($applicationCrb['refreshed'] ?? false),
                'error'     => $applicationCrb['error'] ?? null,
            ] : null,
        ];
    }

    private function hasCreditSection(CreditHistory $history): bool
    {
        $payload = $history->payload ?? [];

        return isset($payload['credit']) || ($payload['report_type'] ?? null) === 'credit';
    }

    private function enrichWithCreditData(Customer $customer, CreditHistory $history): CreditHistory
    {
        $xml = data_get($history->payload, 'identity_raw.response');
        $built = $this->buildCreditPayloadFromXml(is_string($xml) ? $xml : null);
        $credit = $built['credit'];
        $payload = $history->payload ?? [];
        $payload['credit'] = $credit;
        $payload['personal'] = $built['personal'];
        $payload['report_meta'] = $built['report_meta'];
        $payload['report_type'] = 'credit';

        $history->update([
            'score'      => $credit['score'] ?? null,
            'risk_grade' => $credit['risk_grade'] ?? null,
            'payload'    => $payload,
            'checked_at' => now(),
        ]);

        return $history->fresh();
    }

    /** @return array{credit: array<string, mixed>, personal?: array<string, mixed>, report_meta?: array<string, mixed>} */
    private function buildCreditPayload(Customer $customer, ?CrbIdentityResult $identityResult): array
    {
        if ($this->crb->usesStub()) {
            $sample = config('crb_credit_samples.default', []);

            return [
                'credit' => $sample['credit'] ?? $sample,
                'personal' => $sample['personal'] ?? [],
                'report_meta' => $sample['report_meta'] ?? [],
            ];
        }

        $xml = $identityResult?->raw['response'] ?? null;

        return $this->buildCreditPayloadFromXml(is_string($xml) ? $xml : null);
    }

    /** @return array{credit: array<string, mixed>, personal: array<string, mixed>, report_meta: array<string, mixed>} */
    private function buildCreditPayloadFromXml(?string $xml): array
    {
        $parsed = app(\App\Services\Crb\CrbConsumerReportParser::class)->parse($xml);
        $credit = $parsed['credit'];
        $score = $credit['score'] ?? null;
        $credit['recommendation'] = $this->recommendationFromScore(is_numeric($score) ? (int) $score : null);

        return [
            'credit' => $credit,
            'personal' => $parsed['personal'],
            'report_meta' => $parsed['report_meta'],
        ];
    }

    private function recommendationFromScore(?int $score): string
    {
        if ($score === null) {
            return 'refer';
        }

        return $score >= 650 ? 'approve' : ($score >= 500 ? 'refer' : 'reject');
    }

    /** @return array{summary: string, reasons: list<string>} */
    public function recommendationExplanation(array $summary): array
    {
        $recommendation = strtolower((string) ($summary['recommendation'] ?? 'refer'));

        $reasons = [];

        if ((int) ($summary['existing_loans'] ?? 0) > 0) {
            $reasons[] = 'Existing active loans on credit report';
        }

        if ((float) ($summary['outstanding_balance'] ?? 0) > 0) {
            $reasons[] = 'Outstanding balances reported';
        }

        if ((int) ($summary['delinquencies'] ?? 0) > 0) {
            $reasons[] = 'Delinquency history on record';
        }

        if (($summary['score'] ?? null) === null) {
            $reasons[] = 'Incomplete credit profile';
        }

        if (! ($summary['is_fresh'] ?? false)) {
            $reasons[] = 'Credit report may need refresh before final decision';
        }

        if ($reasons === [] && $recommendation === 'refer') {
            $reasons[] = 'Manual review recommended before lending decision';
        }

        $text = match ($recommendation) {
            'approve' => 'The credit report supports proceeding, subject to underwriting review.',
            'reject'  => 'The credit report indicates elevated risk. Manual review is required before any approval.',
            default   => 'Referral means the application requires manual review before a lending decision can be made.',
        };

        return [
            'summary' => $text,
            'reasons' => $reasons,
        ];
    }
}
