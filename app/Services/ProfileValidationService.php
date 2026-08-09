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

    public function requiresMarriageCertificate(): bool
    {
        // Temporarily disabled — re-enable via KYC settings when required again.
        return false;
    }

    public function hasMarriageCertificate(Customer $customer): bool
    {
        return $this->hasDocument($customer, 'marriage_certificate');
    }

    public function isFamilyComplete(Customer $customer): bool
    {
        if (! filled($customer->marital_status)) {
            return false;
        }

        if ($customer->number_of_children === null) {
            return false;
        }

        if ($this->isMarried($customer)) {
            if (! filled($customer->spouse_first_name) || ! filled($customer->spouse_last_name)) {
                return false;
            }
        }

        if ($this->requiresMarriageCertificate() && $this->isMarried($customer) && ! $this->hasMarriageCertificate($customer)) {
            return false;
        }

        return true;
    }

    public function isMarried(Customer $customer): bool
    {
        return strtolower((string) $customer->marital_status) === 'married';
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
        if ($customer->no_physical_nida_card) {
            return true;
        }

        return $this->hasDocument($customer, 'national_id_front')
            && $this->hasDocument($customer, 'national_id_back');
    }

    public function identityDocumentsComplete(Customer $customer): bool
    {
        return $this->nationalIdUploadsComplete($customer);
    }

    public function isKinComplete(Customer $customer): bool
    {
        $hasNames = (filled($customer->nok_first_name) && filled($customer->nok_last_name))
            || filled($customer->nok_name);

        return $hasNames
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
        return $this->isCorePersonalComplete($customer)
            && $this->isFamilyComplete($customer)
            && $this->isKinComplete($customer);
    }

    /**
     * Exact incomplete personal items for hub/section guidance.
     *
     * @return list<array{key: string, label: string, url: string}>
     */
    public function personalGaps(Customer $customer): array
    {
        $gaps = [];
        $personalUrl = route('site.borrower.profile', ['section' => 'personal']);
        $faceUrl = route('site.borrower.face-verification');

        if (! filled($customer->first_name) || ! filled($customer->last_name)) {
            $gaps[] = [
                'key' => 'name',
                'label' => __('borrower.profile.gaps.full_name'),
                'url' => $personalUrl.'#profile-identity',
            ];
        }
        if (! $customer->date_of_birth || ! $this->dateOfBirthValid($customer->date_of_birth)) {
            $gaps[] = [
                'key' => 'dob',
                'label' => __('borrower.profile.gaps.date_of_birth'),
                'url' => $personalUrl.'#profile-identity',
            ];
        }
        if ((bool) ($this->kycSettings()['require_nida'] ?? true)
            && app(IdentityVerificationPolicyService::class)->requiredDuringProfileCreation()
            && app(IdentityVerificationPolicyService::class)->nidaRequired()
            && ! app(NidaVerificationService::class)->isVerified($customer)) {
            $gaps[] = [
                'key' => 'nida',
                'label' => __('borrower.profile.gaps.nida_verify'),
                'url' => $personalUrl.'#profile-identity',
            ];
        }
        if (app(IdentityVerificationPolicyService::class)->requiredDuringProfileCreation()
            && app(IdentityVerificationPolicyService::class)->nidaRequired()
            && ! app(ProfileRevisionService::class)->nidaStepComplete($customer)) {
            if (! $this->hasDocument($customer, 'national_id_front')) {
                $gaps[] = [
                    'key' => 'nida_front',
                    'label' => __('borrower.profile.gaps.nida_front'),
                    'url' => $personalUrl.'#profile-identity',
                ];
            }
        }
        if (! $this->isFamilyComplete($customer)) {
            $gaps[] = [
                'key' => 'family',
                'label' => __('borrower.profile.gaps.family'),
                'url' => $personalUrl.'#profile-family',
            ];
        }
        if (! $this->isKinComplete($customer)) {
            $gaps[] = [
                'key' => 'kin',
                'label' => __('borrower.profile.gaps.next_of_kin'),
                'url' => $personalUrl.'#next-of-kin',
            ];
        }
        $faceStatus = (string) ($customer->face_verification_status ?? 'incomplete');
        if (app(IdentityVerificationPolicyService::class)->requiredDuringProfileCreation()
            && app(IdentityVerificationPolicyService::class)->facialRequired()
            && (! in_array($faceStatus, ['verified', 'pending'], true)
                || app(ProfileRevisionService::class)->hasOpenRevision($customer, 'face'))) {
            $gaps[] = [
                'key' => 'face',
                'label' => __('borrower.profile.gaps.face'),
                'url' => $faceUrl,
            ];
        }

        // De-dupe by key while keeping order.
        $seen = [];
        $unique = [];
        foreach ($gaps as $gap) {
            if (isset($seen[$gap['key']])) {
                continue;
            }
            $seen[$gap['key']] = true;
            $unique[] = $gap;
        }

        return $unique;
    }

    public function isCorePersonalComplete(Customer $customer): bool
    {
        if (! filled($customer->first_name) || ! filled($customer->last_name) || ! $customer->date_of_birth) {
            return false;
        }

        if (! $this->dateOfBirthValid($customer->date_of_birth)) {
            return false;
        }

        if ((bool) ($this->kycSettings()['require_nida'] ?? true)
            && app(IdentityVerificationPolicyService::class)->requiredDuringProfileCreation()
            && app(IdentityVerificationPolicyService::class)->nidaRequired()
            && ! app(NidaVerificationService::class)->isVerified($customer)) {
            return false;
        }

        return true;
    }
}
