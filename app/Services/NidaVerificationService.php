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

        DB::transaction(function () use ($customer, $formatted, $result): void {
            $customer->fill([
                'national_id'               => $formatted,
                'first_name'                => $result->firstName ?: $customer->first_name,
                'last_name'                 => $result->lastName ?: $customer->last_name,
                'date_of_birth'             => $result->dateOfBirth ?: $customer->date_of_birth,
                'gender'                    => $result->gender ?: $customer->gender,
                'nida_verification_status'  => 'verified',
                'nida_verified_at'          => now(),
                'nida_verified_source'      => $this->crb->usesStub() ? 'stub' : 'crb',
                'identity_locked'           => true,
            ])->save();

            $kyc = $customer->kyc ?? CustomerKyc::firstOrCreate(
                ['customer_id' => $customer->id],
                ['status' => 'pending', 'payload' => []]
            );

            $payload = $kyc->payload ?? [];
            $payload['nida_verification'] = [
                'national_id'  => $formatted,
                'verified_at'  => now()->toIso8601String(),
                'source'       => $this->crb->usesStub() ? 'stub' : 'crb',
                'search_score' => $result->searchScore,
                'crb_ruid'     => $result->crbRuid,
                'full_name'    => $result->fullName,
            ];
            $payload['crb_identity_raw'] = $result->raw;

            $kyc->update([
                'payload' => $payload,
                'status'  => $kyc->status === 'rejected' ? 'in_review' : $kyc->status,
            ]);
        });

        return $result;
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

        DB::transaction(function () use ($customer, $formatted, $result): void {
            $customer->fill([
                'national_id'               => $formatted,
                'first_name'                => $result->firstName ?: $customer->first_name,
                'last_name'                 => $result->lastName ?: $customer->last_name,
                'date_of_birth'             => $result->dateOfBirth ?: $customer->date_of_birth,
                'gender'                    => $result->gender ?: $customer->gender,
                'nida_verification_status'  => 'verified',
                'nida_verified_at'          => now(),
                'nida_verified_source'      => $this->crb->usesStub() ? 'stub' : 'crb',
                'identity_locked'           => true,
            ])->save();

            $kyc = $customer->kyc ?? CustomerKyc::firstOrCreate(
                ['customer_id' => $customer->id],
                ['status' => 'pending', 'payload' => []]
            );

            $payload = $kyc->payload ?? [];
            $payload['nida_verification'] = [
                'national_id'  => $formatted,
                'verified_at'  => now()->toIso8601String(),
                'source'       => $this->crb->usesStub() ? 'stub' : 'crb',
                'search_score' => $result->searchScore,
                'crb_ruid'     => $result->crbRuid,
                'full_name'    => $result->fullName,
            ];
            $payload['crb_identity_raw'] = $result->raw;

            $kyc->update(['payload' => $payload]);
        });

        return $result;
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
