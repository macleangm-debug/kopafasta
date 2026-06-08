<?php

namespace App\Services;

use App\DataTransferObjects\CrbIdentityResult;
use App\Models\Customer;
use App\Models\CustomerKyc;
use App\Models\Setting;
use App\Models\User;
use App\Support\NidaNumber;
use Illuminate\Support\Facades\DB;

class NidaVerificationService
{
    public function __construct(
        private readonly CrbService $crb,
        private readonly IdentityNameService $names,
        private readonly AuditService $audit,
    ) {}

    public function isVerified(Customer $customer): bool
    {
        return $customer->nida_verification_status === 'verified' && $customer->identity_locked;
    }

    /** @return array{max_mismatch_attempts: int, lock_hours: int} */
    public function settings(): array
    {
        $group = Setting::group('identity_verification');

        return [
            'max_mismatch_attempts' => (int) ($group['max_mismatch_attempts'] ?? config('identity_verification.max_mismatch_attempts', 3)),
            'lock_hours'            => (int) ($group['lock_hours'] ?? config('identity_verification.lock_hours', 24)),
        ];
    }

    public function isLocked(Customer $customer): bool
    {
        return $customer->nida_locked_until && now()->lt($customer->nida_locked_until);
    }

    public function lockMessage(Customer $customer): ?string
    {
        if (! $this->isLocked($customer)) {
            return null;
        }

        return __('borrower.nida.account_locked_until', [
            'time' => $customer->nida_locked_until->format('d M Y H:i'),
        ]);
    }

    public function assertCanVerify(Customer $customer): ?string
    {
        return $this->lockMessage($customer);
    }

    public function mismatchWarningLevel(Customer $customer): int
    {
        return min($this->settings()['max_mismatch_attempts'], (int) $customer->nida_mismatch_attempts);
    }

    public function mismatchMessage(Customer $customer, int $level): string
    {
        if ($this->isLocked($customer)) {
            return $this->lockMessage($customer) ?? __('borrower.nida.result.locked_default');
        }

        return match ($level) {
            1       => __('borrower.nida.mismatch_warning_1'),
            2       => __('borrower.nida.mismatch_warning_2'),
            default => __('borrower.nida.result.mismatch_default'),
        };
    }

    public function unlockIdentityVerification(Customer $customer, ?User $admin = null): void
    {
        $customer->update([
            'nida_mismatch_attempts' => 0,
            'nida_locked_until'      => null,
        ]);

        $user = $this->linkedUser($customer);
        if ($user?->locked_until?->isFuture()) {
            $user->forceFill(['locked_until' => null])->save();
        }

        if ($admin) {
            $this->audit->logAdminAction($admin, 'nida.identity_unlocked', $customer, [
                'customer_id' => $customer->id,
            ]);
        }
    }

    public function verify(Customer $customer, string $nidaNumber): CrbIdentityResult
    {
        if ($message = $this->assertCanVerify($customer)) {
            return CrbIdentityResult::failed($message, 'locked');
        }

        $formatted = NidaNumber::format($nidaNumber);

        if (! $formatted) {
            return CrbIdentityResult::failed('Invalid NIDA number format.');
        }

        if ($this->isVerified($customer) && $customer->national_id === $formatted) {
            return CrbIdentityResult::verified(
                fullName: $customer->full_name,
                firstName: $customer->first_name,
                lastName: $customer->last_name,
                dateOfBirth: optional($customer->date_of_birth)->format('Y-m-d'),
                gender: $customer->gender,
                nationalId: $formatted,
                searchScore: '100%',
            );
        }

        $latest = app(CrbCreditCheckService::class)->latest($customer);
        if ($latest
            && app(CrbFreshnessService::class)->isFresh($latest)
            && ($latest->payload['national_id'] ?? null) === $formatted
            && filled($latest->payload['full_name'] ?? null)) {
            $payload = $latest->payload;
            $parsed = $this->names->parse($payload['full_name'], null, null);

            return $this->finalizeSuccessfulLookup($customer, $formatted, CrbIdentityResult::verified(
                fullName: $payload['full_name'],
                firstName: $parsed['first_name'],
                lastName: $parsed['last_name'],
                dateOfBirth: optional($customer->date_of_birth)->format('Y-m-d'),
                gender: $customer->gender,
                nationalId: $formatted,
                searchScore: $payload['search_score'] ?? null,
                crbRuid: $payload['crb_ruid'] ?? null,
                raw: $payload['identity_raw'] ?? [],
            ));
        }

        $result = $this->crb->verifyConsumerIdentity(
            identifierNumber: $formatted,
            fullName: $customer->full_name,
            dateOfBirth: optional($customer->date_of_birth)->format('Y-m-d'),
            mobile: $customer->phone,
        );

        if (! $result->success) {
            $this->recordAttempt($customer, $formatted, $result);

            return $result;
        }

        return $this->finalizeSuccessfulLookup($customer, $formatted, $result);
    }

    public function acceptVerifiedNames(Customer $customer): bool
    {
        if ($this->isLocked($customer)) {
            return false;
        }

        $kyc = $customer->kyc;
        $verified = $kyc?->payload['nida_verified_names'] ?? null;

        if (! is_array($verified) || $customer->nida_verification_status !== 'name_mismatch') {
            return false;
        }

        DB::transaction(function () use ($customer, $verified): void {
            $this->lockIdentity($customer, [
                'national_id'  => $customer->national_id,
                'first_name'   => $verified['first_name'] ?? $customer->first_name,
                'middle_name'  => $verified['middle_name'] ?? $customer->middle_name,
                'last_name'    => $verified['last_name'] ?? $customer->last_name,
                'date_of_birth'=> $verified['date_of_birth'] ?? $customer->date_of_birth,
                'gender'       => $verified['gender'] ?? $customer->gender,
                'search_score' => $verified['search_score'] ?? null,
                'crb_ruid'     => $verified['crb_ruid'] ?? null,
                'full_name'    => $verified['full_name'] ?? null,
            ]);
        });

        return true;
    }

    /** @return array{matched: bool, mismatches: list<array<string, string|null>>}|null */
    public function nameMismatch(Customer $customer): ?array
    {
        if ($customer->nida_verification_status !== 'name_mismatch') {
            return null;
        }

        return $customer->kyc?->payload['nida_name_mismatch'] ?? null;
    }

    private function finalizeSuccessfulLookup(Customer $customer, string $formatted, CrbIdentityResult $result): CrbIdentityResult
    {
        $parsed = $this->names->parse($result->fullName, $result->firstName, $result->lastName);
        $comparison = $this->names->compare($customer, $parsed);

        if (! $comparison['matched']) {
            DB::transaction(function () use ($customer, $formatted, $result, $parsed, $comparison): void {
                $customer->update([
                    'national_id'              => $formatted,
                    'nida_verification_status' => 'name_mismatch',
                ]);

                $this->recordMismatchAttempt($customer);

                $kyc = $customer->kyc ?? CustomerKyc::firstOrCreate(
                    ['customer_id' => $customer->id],
                    ['status' => 'pending', 'payload' => []]
                );

                $payload = $kyc->payload ?? [];
                $payload['nida_verified_names'] = array_merge($parsed, [
                    'date_of_birth' => $result->dateOfBirth,
                    'gender'        => $result->gender,
                    'full_name'     => $result->fullName,
                    'search_score'  => $result->searchScore,
                    'crb_ruid'      => $result->crbRuid,
                ]);
                $payload['nida_name_mismatch'] = $comparison;
                $payload['crb_identity_raw'] = $result->raw;

                $kyc->update(['payload' => $payload]);
            });

            return CrbIdentityResult::failed(
                'Name mismatch detected between your registration and NIDA records.',
                'name_mismatch',
                $result->raw,
            );
        }

        DB::transaction(function () use ($customer, $formatted, $result, $parsed): void {
            $this->lockIdentity($customer, [
                'national_id'   => $formatted,
                'first_name'    => $parsed['first_name'] ?: $customer->first_name,
                'middle_name'   => $parsed['middle_name'],
                'last_name'     => $parsed['last_name'] ?: $customer->last_name,
                'date_of_birth' => $result->dateOfBirth ?: $customer->date_of_birth,
                'gender'        => $result->gender ?: $customer->gender,
                'search_score'  => $result->searchScore,
                'crb_ruid'      => $result->crbRuid,
                'full_name'     => $result->fullName,
            ], $result->raw);
        });

        return $result;
    }

    /** @param  array<string, mixed>  $data */
    private function lockIdentity(Customer $customer, array $data, array $raw = []): void
    {
        $customer->fill([
            'national_id'              => $data['national_id'],
            'first_name'               => $data['first_name'],
            'middle_name'              => $data['middle_name'] ?? null,
            'last_name'                => $data['last_name'],
            'date_of_birth'            => $data['date_of_birth'],
            'gender'                   => $data['gender'],
            'nida_verification_status' => 'verified',
            'nida_verified_at'         => now(),
            'nida_verified_source'     => $this->crb->usesStub() ? 'stub' : 'crb',
            'identity_locked'          => true,
            'nida_mismatch_attempts'   => 0,
            'nida_locked_until'        => null,
        ])->save();

        $user = $this->linkedUser($customer);
        if ($user?->locked_until?->isFuture()) {
            $user->forceFill(['locked_until' => null])->save();
        }

        $kyc = $customer->kyc ?? CustomerKyc::firstOrCreate(
            ['customer_id' => $customer->id],
            ['status' => 'pending', 'payload' => []]
        );

        $payload = $kyc->payload ?? [];
        unset($payload['nida_name_mismatch'], $payload['nida_verified_names']);
        $payload['nida_verification'] = [
            'national_id'  => $data['national_id'],
            'verified_at'  => now()->toIso8601String(),
            'source'       => $this->crb->usesStub() ? 'stub' : 'crb',
            'search_score' => $data['search_score'] ?? null,
            'crb_ruid'     => $data['crb_ruid'] ?? null,
            'full_name'    => $data['full_name'] ?? null,
        ];
        $payload['crb_identity_raw'] = $payload['crb_identity_raw'] ?? [];

        $kyc->update([
            'payload' => $payload,
            'status'  => $kyc->status === 'rejected' ? 'in_review' : $kyc->status,
        ]);

        app(CrbCreditCheckService::class)->recordIdentityVerification($customer, $data, $raw);
    }

    public function confirmCandidate(
        Customer $customer,
        string $nidaNumber,
        string $searchRequestId,
        string $entityKey,
    ): CrbIdentityResult {
        if ($message = $this->assertCanVerify($customer)) {
            return CrbIdentityResult::failed($message, 'locked');
        }

        $formatted = NidaNumber::format($nidaNumber);

        if (! $formatted) {
            return CrbIdentityResult::failed('Invalid NIDA number format.');
        }

        $result = $this->crb->fetchByEntityKey($searchRequestId, $entityKey, $formatted);

        if (! $result->success) {
            $this->recordAttempt($customer, $formatted, $result);

            return $result;
        }

        return $this->finalizeSuccessfulLookup($customer, $formatted, $result);
    }

    private function recordAttempt(Customer $customer, string $formatted, CrbIdentityResult $result): void
    {
        $status = match ($result->status) {
            'multihit' => 'multihit',
            'no_hit'   => 'failed',
            default    => 'failed',
        };

        $customer->update([
            'national_id'              => $formatted,
            'nida_verification_status' => $status,
        ]);

        $kyc = $customer->kyc ?? CustomerKyc::firstOrCreate(
            ['customer_id' => $customer->id],
            ['status' => 'pending', 'payload' => []]
        );

        $payload = $kyc->payload ?? [];
        $payload['nida_verification_attempt'] = [
            'at'      => now()->toIso8601String(),
            'status'  => $result->status,
            'message' => $result->message,
        ];

        if ($result->isMultihit()) {
            $payload['crb_candidates'] = $result->candidates;
            $payload['crb_search_request_id'] = $result->raw['search_request_id'] ?? null;
        }

        $kyc->update(['payload' => $payload]);
    }

    private function recordMismatchAttempt(Customer $customer): void
    {
        $settings = $this->settings();
        $attempts = (int) $customer->nida_mismatch_attempts + 1;
        $updates = ['nida_mismatch_attempts' => $attempts];

        if ($attempts >= $settings['max_mismatch_attempts']) {
            $until = now()->addHours($settings['lock_hours']);
            $updates['nida_locked_until'] = $until;
            $customer->update($updates);
            $this->syncUserLock($customer->fresh(), $until);

            $this->audit->logBorrower(auth()->user(), 'nida.account_locked', $customer, [
                'attempts'     => $attempts,
                'locked_until' => $until->toIso8601String(),
            ]);

            return;
        }

        $customer->update($updates);

        $this->audit->logBorrower(auth()->user(), 'nida.mismatch_attempt', $customer, [
            'attempts' => $attempts,
            'level'    => $attempts,
        ]);
    }

    private function syncUserLock(Customer $customer, \DateTimeInterface $until): void
    {
        $user = $this->linkedUser($customer);
        if ($user) {
            $user->forceFill(['locked_until' => $until])->save();
        }
    }

    private function linkedUser(Customer $customer): ?User
    {
        if ($customer->relationLoaded('user')) {
            return $customer->user;
        }

        return User::query()->where('id', $customer->user_id)->first();
    }
}
