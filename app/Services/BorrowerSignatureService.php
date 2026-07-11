<?php

namespace App\Services;

use App\Models\ApplicationSignature;
use App\Models\Customer;
use App\Models\LoanApplication;
use Illuminate\Validation\ValidationException;

class BorrowerSignatureService
{
    public function hasProfileSignature(Customer $customer): bool
    {
        return filled($customer->legal_signature_data)
            && str_starts_with((string) $customer->legal_signature_data, 'data:image/png;base64,');
    }

    /** @return array{signer_name: string, signature_data: string, signed_at: string|null}|null */
    public function profileSignature(Customer $customer): ?array
    {
        if (! $this->hasProfileSignature($customer)) {
            return null;
        }

        return [
            'signer_name'    => (string) ($customer->legal_signer_name ?: $customer->full_name),
            'signature_data' => (string) $customer->legal_signature_data,
            'signed_at'      => optional($customer->legal_signed_at)?->toIso8601String(),
        ];
    }

    public function saveProfileSignature(Customer $customer, string $signatureData, ?string $signerName = null): Customer
    {
        if (! str_starts_with($signatureData, 'data:image/png;base64,')) {
            throw ValidationException::withMessages([
                'signature_data' => __('borrower.profile.signature_invalid'),
            ]);
        }

        $customer->forceFill([
            'legal_signature_data' => $signatureData,
            'legal_signer_name'    => $signerName ?: $customer->full_name,
            'legal_signed_at'      => now(),
        ])->save();

        return $customer->fresh();
    }

    public function clearProfileSignature(Customer $customer): void
    {
        $customer->forceFill([
            'legal_signature_data' => null,
            'legal_signer_name'    => null,
            'legal_signed_at'      => null,
        ])->save();
    }

    public function hasSignature(LoanApplication $application): bool
    {
        return $this->signature($application) !== null;
    }

    public function signature(LoanApplication $application): ?ApplicationSignature
    {
        $application->loadMissing('signatures');

        $signature = $application->signatures->firstWhere('signer_type', 'borrower');

        return filled($signature?->signature_data) ? $signature : null;
    }

    /** Prefer application signature, then fall back to the reusable profile signature. */
    public function resolveForApplication(LoanApplication $application, ?Customer $customer = null): ?array
    {
        $stored = $this->signature($application);
        if ($stored) {
            return [
                'signer_name'    => (string) $stored->signer_name,
                'signature_data' => (string) $stored->signature_data,
                'signed_at'      => optional($stored->signed_at)?->toIso8601String(),
            ];
        }

        $customer ??= $application->customer;

        return $customer ? $this->profileSignature($customer) : null;
    }
}
