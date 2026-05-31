<?php

namespace App\Services;

use App\DataTransferObjects\CrbIdentityResult;
use App\Models\Customer;
use App\Models\CustomerKyc;
use App\Support\NidaNumber;
use Illuminate\Support\Facades\DB;

class NidaVerificationService
{
    public function __construct(
        private readonly CrbService $crb,
        private readonly IdentityNameService $names,
    ) {}

    public function isVerified(Customer $customer): bool
    {
        return $customer->nida_verification_status === 'verified' && $customer->identity_locked;
    }

    public function verify(Customer $customer, string $nidaNumber): CrbIdentityResult
    {
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
            ]);
        });

        return $result;
    }

    /** @param array<string, mixed> $data */
    private function lockIdentity(Customer $customer, array $data): void
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
        ])->save();

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
    }

    public function confirmCandidate(
        Customer $customer,
        string $nidaNumber,
        string $searchRequestId,
        string $entityKey,
    ): CrbIdentityResult {
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
}
