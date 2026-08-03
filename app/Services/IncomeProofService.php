<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerDocument;
use App\Models\Setting;
use Illuminate\Support\Collection;

class IncomeProofService
{
    /** @var list<string> */
    public const PRIMARY_CODES = ['bank_statement', 'mobile_money_statement', 'mpesa_statement'];

    public function isRequired(): bool
    {
        return (bool) (Setting::group('kyc')['require_income_proof'] ?? false);
    }

    public function activityType(Customer $customer): ?string
    {
        $type = $customer->activity_type ?? $customer->employment_type;

        return filled($type) ? (string) $type : null;
    }

    public function isEmployed(Customer $customer): bool
    {
        return $this->activityType($customer) === (string) config('income_proof.employed_type', 'employed');
    }

    public function isBusinessOwner(Customer $customer): bool
    {
        $type = $this->activityType($customer);

        return $type && in_array($type, config('income_proof.business_owner_types', []), true);
    }

    public function isSelfEmployed(Customer $customer): bool
    {
        $type = $this->activityType($customer);

        return $type && in_array($type, config('income_proof.self_employed_types', []), true);
    }

    public function isArtisan(Customer $customer): bool
    {
        $type = $this->activityType($customer);

        return $type && in_array($type, config('income_proof.artisan_types', []), true);
    }

    public function showsBusinessPhotos(Customer $customer): bool
    {
        if ($this->isEmployed($customer)) {
            return false;
        }

        $type = $this->activityType($customer);

        return $type && in_array($type, config('income_proof.business_photo_types', []), true);
    }

    public function isInformal(Customer $customer): bool
    {
        if ($this->isEmployed($customer)) {
            return false;
        }

        return filled($this->activityType($customer));
    }

    public function hasDocument(Customer $customer, string $code): bool
    {
        return app(ProfileDocumentService::class)->hasProfileDocument($customer, $code);
    }

    public function hasPrimaryProof(Customer $customer): bool
    {
        foreach (config('income_proof.informal_required_any_codes', self::PRIMARY_CODES) as $code) {
            if ($this->hasDocument($customer, $code)) {
                return true;
            }
        }

        return false;
    }

    public function selectedPrimaryMethod(Customer $customer): ?string
    {
        $pref = $customer->activity_details['income_proof_method'] ?? null;

        if (in_array($pref, ['bank_statement', 'mobile_money_statement'], true)) {
            return $pref;
        }

        if ($this->hasDocument($customer, 'mobile_money_statement') || $this->hasDocument($customer, 'mpesa_statement')) {
            return 'mobile_money_statement';
        }

        if ($this->hasDocument($customer, 'bank_statement')) {
            return 'bank_statement';
        }

        return null;
    }

    /** @return list<array{key: string, label: string}> */
    public function informalPrimaryOptions(): array
    {
        return [
            ['key' => 'bank_statement', 'label' => __('borrower.profile.income_bank_statement')],
            ['key' => 'mobile_money_statement', 'label' => __('borrower.profile.income_mobile_money_statement')],
        ];
    }

    public function primaryDocument(Customer $customer): ?CustomerDocument
    {
        $uploads = app(ProfileDocumentService::class)->latestByCodes(
            $customer,
            config('income_proof.informal_required_any_codes', self::PRIMARY_CODES),
        );

        foreach (config('income_proof.informal_required_any_codes', self::PRIMARY_CODES) as $code) {
            if ($uploads->has($code)) {
                return $uploads->get($code);
            }
        }

        return null;
    }

    public function hasBusinessRegistration(Customer $customer): bool
    {
        foreach (config('income_proof.business_registration_codes', []) as $code) {
            if ($this->hasDocument($customer, $code)) {
                return true;
            }
        }

        return false;
    }

    public function satisfiesRequirement(Customer $customer): bool
    {
        if (! $this->isRequired()) {
            return true;
        }

        if (! filled($this->activityType($customer))) {
            return false;
        }

        if ($this->isEmployed($customer)) {
            foreach (config('income_proof.employed_required_codes', []) as $code) {
                if (! $this->hasDocument($customer, $code)) {
                    return false;
                }
            }

            return true;
        }

        return $this->hasPrimaryProof($customer);
    }

    /**
     * @return list<array{key: string, label: string, required: bool, complete: bool, document: CustomerDocument|null, group?: string, multi?: bool, optional?: bool}>
     */
    public function checklist(Customer $customer): array
    {
        $codes = array_merge(
            config('income_proof.employed_required_codes', []),
            config('income_proof.informal_required_any_codes', []),
            config('income_proof.business_registration_codes', []),
            config('income_proof.informal_optional_codes', []),
        );

        $uploads = app(ProfileDocumentService::class)->latestByCodes($customer, $codes);

        if ($this->isEmployed($customer)) {
            return $this->employedChecklist($uploads);
        }

        return $this->informalChecklist($customer, $uploads);
    }

    /**
     * Items that block completion / application submission.
     *
     * @return list<array{key: string, label: string, complete: bool, action_url: string|null}>
     */
    public function requirementItems(Customer $customer): array
    {
        if (! $this->isRequired()) {
            return [[
                'key'        => 'income',
                'label'      => __('borrower.loan_profile.sections.proof_of_income'),
                'complete'   => true,
                'action_url' => null,
            ]];
        }

        if (! filled($this->activityType($customer))) {
            return [[
                'key'        => 'income',
                'label'      => __('borrower.loan_profile.sections.proof_of_income'),
                'complete'   => false,
                'action_url' => route('site.borrower.profile', ['section' => 'activity']),
            ]];
        }

        if ($this->isEmployed($customer)) {
            $items = [];
            foreach ($this->employedChecklist(app(ProfileDocumentService::class)->latestByCodes($customer, ['salary_slip', 'bank_statement'])) as $item) {
                $items[] = [
                    'key'        => $item['key'],
                    'label'      => $item['label'],
                    'complete'   => (bool) $item['complete'],
                    'action_url' => route('site.borrower.profile', ['section' => 'activity', 'focus' => 'income']),
                ];
            }

            return $items;
        }

        return [[
            'key'        => 'income',
            'label'      => __('borrower.loan_profile.sections.proof_of_income'),
            'complete'   => $this->hasPrimaryProof($customer),
            'action_url' => $this->hasPrimaryProof($customer) ? null : route('site.borrower.profile', ['section' => 'activity', 'focus' => 'income']),
        ]];
    }

    /** @return list<string> */
    public function missingDocumentLabels(Customer $customer): array
    {
        if (! $this->isRequired() || $this->satisfiesRequirement($customer)) {
            return [];
        }

        if ($this->isEmployed($customer)) {
            return collect($this->checklist($customer))
                ->filter(fn (array $item) => ($item['required'] ?? false) && ! ($item['complete'] ?? false))
                ->pluck('label')
                ->values()
                ->all();
        }

        if (! $this->hasPrimaryProof($customer)) {
            return [__('borrower.profile.income_primary_one_required')];
        }

        return [];
    }

    /** @param  Collection<string, CustomerDocument>  $uploads */
    private function employedChecklist(Collection $uploads): array
    {
        return [
            array_merge($this->checklistRow('salary_slip', __('borrower.profile.income_salary_slip'), true, $uploads), ['multi' => true]),
            array_merge($this->checklistRow('bank_statement', __('borrower.profile.income_bank_statement'), true, $uploads), ['multi' => true]),
        ];
    }

    /** @param  Collection<string, CustomerDocument>  $uploads */
    private function informalChecklist(Customer $customer, Collection $uploads): array
    {
        $primaryComplete = $this->hasPrimaryProof($customer);
        $selectedMethod = $this->selectedPrimaryMethod($customer);

        $items = [
            array_merge(
                $this->checklistRow('bank_statement', __('borrower.profile.income_bank_statement'), true, $uploads),
                [
                    'multi'    => true,
                    'group'    => 'primary',
                    'complete' => $primaryComplete,
                    'document' => $uploads->get('bank_statement'),
                    'visible'  => $selectedMethod === null || $selectedMethod === 'bank_statement',
                ],
            ),
            array_merge(
                $this->checklistRow('mobile_money_statement', __('borrower.profile.income_mobile_money_statement'), true, $uploads),
                [
                    'multi'    => true,
                    'group'    => 'primary',
                    'complete' => $primaryComplete,
                    'document' => $uploads->get('mobile_money_statement') ?? $uploads->get('mpesa_statement'),
                    'visible'  => $selectedMethod === null || $selectedMethod === 'mobile_money_statement',
                ],
            ),
        ];

        foreach ([
            'business_registration' => __('borrower.profile.income_business_registration'),
            'business_license'      => __('borrower.profile.income_business_license'),
            'tin_certificate'       => __('borrower.profile.income_tin_certificate'),
            'vat_certificate'       => __('borrower.profile.income_vat_certificate'),
            'business_photos'       => __('borrower.profile.income_business_photos'),
            'workshop_photos'       => __('borrower.profile.income_workshop_photos'),
        ] as $code => $label) {
            $items[] = array_merge(
                $this->checklistRow($code, $label, false, $uploads),
                ['multi' => true, 'optional' => true],
            );
        }

        return $items;
    }

    /** @param  Collection<string, CustomerDocument>  $uploads */
    private function checklistRow(string $code, string $label, bool $required, Collection $uploads): array
    {
        return [
            'key'      => $code,
            'label'    => $label,
            'required' => $required,
            'complete' => $uploads->has($code),
            'document' => $uploads->get($code),
        ];
    }
}
