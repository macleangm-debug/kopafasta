<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerDocument;
use App\Models\Setting;
use Carbon\Carbon;

class ProfileValidationService
{
    /** @return array<string, mixed> */
    public function kycSettings(): array
    {
        try {
            return Setting::group('kyc');
        } catch (\Throwable) {
            return [];
        }
    }

    public function minAge(): int
    {
        return (int) ($this->kycSettings()['min_age'] ?? 18);
    }

    public function maxAge(): int
    {
        return (int) ($this->kycSettings()['max_age'] ?? 75);
    }

    public function dateOfBirthValid(?Carbon $dob): bool
    {
        if (! $dob) {
            return false;
        }

        $age = $dob->age;

        return $age >= $this->minAge() && $age <= $this->maxAge();
    }

    public function requiresResidenceLetter(): bool
    {
        $settings = $this->kycSettings();

        return (bool) ($settings['require_residence_letter'] ?? $settings['require_address_proof'] ?? false);
    }

    public function hasDocument(Customer $customer, string $code): bool
    {
        return app(ProfileDocumentService::class)->hasProfileDocument($customer, $code);
    }

    public function hasResidenceLetter(Customer $customer): bool
    {
        return $this->hasDocument($customer, 'residence_letter');
    }

    public function requiresNationalIdUploads(): bool
    {
        return true;
    }

    public function nationalIdUploadsComplete(Customer $customer): bool
    {
        return $this->hasDocument($customer, 'national_id_front');
    }

    public function identityDocumentsComplete(Customer $customer): bool
    {
        return $this->nationalIdUploadsComplete($customer);
    }

    public function isKinComplete(Customer $customer): bool
    {
        return filled($customer->nok_name)
            && filled($customer->nok_phone)
            && filled($customer->nok_relationship)
            && filled($customer->nok_region)
            && filled($customer->nok_district)
            && filled($customer->nok_street);
    }

    public function requiresEmploymentContract(Customer $customer): bool
    {
        return ($customer->activity_type ?? $customer->employment_type) === 'employed';
    }

    public function employmentContractComplete(Customer $customer): bool
    {
        if (! $this->requiresEmploymentContract($customer)) {
            return true;
        }

        return $this->hasDocument($customer, 'employment_contract');
    }

    public function isPersonalInfoComplete(Customer $customer): bool
    {
        if (! filled($customer->first_name) || ! filled($customer->last_name) || ! $customer->date_of_birth) {
            return false;
        }

        if (! $this->dateOfBirthValid($customer->date_of_birth)) {
            return false;
        }

        if ($this->requiresNationalIdUploads() && ! $this->nationalIdUploadsComplete($customer)) {
            return false;
        }

        if ((bool) ($this->kycSettings()['require_nida'] ?? true)) {
            if (! app(NidaVerificationService::class)->isVerified($customer)) {
                return false;
            }
        }

        return $this->isKinComplete($customer);
    }
}
