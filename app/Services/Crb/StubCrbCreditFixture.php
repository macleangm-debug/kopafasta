<?php

namespace App\Services\Crb;

use App\Models\Customer;
use App\Support\NidaNumber;
use Illuminate\Support\Str;

/**
 * Builds participant-specific stub CIR payloads for Gate 3 testing.
 * Stored CreditHistory.source remains crb_stub — never presented as live D&B.
 */
class StubCrbCreditFixture
{
    public const SCENARIOS = [
        'clean',
        'pass',
        'refer',
        'hard_fail',
        'wrong_subject',
        'stale',
        'no_record',
    ];

    /**
     * Resolve scenario for this customer (explicit → KYC payload → NIDA map → clean).
     */
    public function resolveScenario(Customer $customer, ?string $explicit = null): string
    {
        $explicit = $this->normalizeScenario($explicit);
        if ($explicit !== null) {
            return $explicit;
        }

        $fromKyc = $this->normalizeScenario(data_get($customer->kyc?->payload, 'crb_stub_scenario'));
        if ($fromKyc !== null) {
            return $fromKyc;
        }

        $nida = NidaNumber::format((string) $customer->national_id);
        if ($nida) {
            $mapped = config('crb_credit_samples.by_nida.'.$nida)
                ?? config('crb_credit_samples.by_nida.'.NidaNumber::digits($nida));
            $mapped = $this->normalizeScenario(is_string($mapped) ? $mapped : null);
            if ($mapped !== null) {
                return $mapped;
            }
        }

        // Default stub pull matches the participant — never the universal Amina fixture.
        return 'clean';
    }

    /**
     * @return array{
     *     credit: array<string, mixed>,
     *     personal: array<string, mixed>,
     *     report_meta: array<string, mixed>,
     *     scenario: string,
     *     status?: string,
     *     error?: string
     * }
     */
    public function build(Customer $customer, ?string $scenario = null): array
    {
        $scenario = $this->resolveScenario($customer, $scenario);

        if ($scenario === 'no_record') {
            return [
                'credit' => [
                    'score' => null,
                    'risk_grade' => null,
                    'recommendation' => null,
                    'existing_loans' => 0,
                    'outstanding_balance' => 0,
                    'delinquencies' => 0,
                    'loan_history' => [],
                    'overview' => [],
                ],
                'personal' => [],
                'report_meta' => $this->reportMeta($customer, $scenario),
                'scenario' => $scenario,
                'status' => 'no record',
                'error' => 'No matching identity record was found at the credit bureau.',
            ];
        }

        $personal = $scenario === 'wrong_subject'
            ? $this->wrongSubjectPersonal()
            : $this->personalFromCustomer($customer, $scenario);

        $creditKey = match ($scenario) {
            'hard_fail' => 'hard_fail',
            'refer', 'wrong_subject' => 'refer',
            default => 'clean', // clean, pass, stale
        };
        $credit = config('crb_credit_samples.credit_templates.'.$creditKey, []);
        if (! is_array($credit) || $credit === []) {
            $credit = config('crb_credit_samples.credit_templates.clean', []);
        }

        return [
            'credit' => $credit,
            'personal' => $personal,
            'report_meta' => $this->reportMeta($customer, $scenario),
            'scenario' => $scenario,
        ];
    }

    public function normalizeScenario(?string $scenario): ?string
    {
        $scenario = strtolower(trim((string) $scenario));
        if ($scenario === '') {
            return null;
        }
        if ($scenario === 'pass') {
            return 'clean';
        }

        $allowed = ['clean', 'refer', 'hard_fail', 'wrong_subject', 'stale', 'no_record'];

        return in_array($scenario, $allowed, true) ? $scenario : null;
    }

    /** @return array<string, mixed> */
    private function personalFromCustomer(Customer $customer, string $scenario): array
    {
        $nida = NidaNumber::format((string) $customer->national_id) ?: (string) $customer->national_id;
        $parts = preg_split('/\s+/', trim((string) $customer->full_name)) ?: [];
        $last = count($parts) > 1 ? array_pop($parts) : (string) ($customer->last_name ?? 'Citizen');
        $first = implode(' ', $parts) ?: (string) ($customer->first_name ?? 'Stub');
        $middle = trim((string) ($customer->middle_name ?? ''));
        $dob = optional($customer->date_of_birth)->format('d-M-Y')
            ?: optional($customer->date_of_birth)->format('d M Y')
            ?: '01-Jan-1990';
        $gender = $this->genderLabel($customer->gender);
        $phone = $customer->phone ? (str_starts_with((string) $customer->phone, '+') ? $customer->phone : '+'.$customer->phone) : '+255700000000';
        $address = trim(collect([
            $customer->street,
            $customer->ward,
            $customer->district,
            $customer->region,
        ])->filter()->implode(', ')) ?: 'Dar es Salaam, Tanzania';

        $personal = [
            'full_name' => $customer->full_name,
            'surname' => $last,
            'first_name' => $first,
            'middle_names' => $middle,
            'gender' => $gender,
            'date_of_birth' => $dob,
            'nationality' => 'Tanzania, United Republic Of',
            'country_of_birth' => 'Tanzania, United Republic Of',
            'district_of_birth' => $customer->district ?: 'Kinondoni',
            'marital_status' => $this->maritalLabel($customer->marital_status),
            'number_of_spouses' => in_array(strtolower((string) $customer->marital_status), ['married', 'spouse'], true) ? 1 : 0,
            'spouses' => [],
            'number_of_children' => $customer->number_of_children,
            'education' => 'Secondary',
            'profession' => $customer->activity_type ?: ($customer->employment_type ?: 'Self employed'),
            'employer' => $customer->employer_name ?: 'Self employed',
            'mobile' => $phone,
            'address' => $address,
            'ids' => $nida !== '' ? [
                ['id_number' => $nida, 'id_type' => 'National ID'],
            ] : [],
            'address_history' => [
                ['type' => 'Physical', 'address' => $address, 'date_reported' => now()->subYear()->format('d-M-Y')],
            ],
            'contact_history' => [
                ['type' => 'Mobile Telephone', 'detail' => $phone, 'date_reported' => now()->subMonths(3)->format('d-M-Y')],
            ],
            'employment_history' => [
                [
                    'employer' => $customer->employer_name ?: 'Self employed',
                    'profession' => $customer->activity_type ?: 'Trader',
                    'date_reported' => now()->subMonths(6)->format('d-M-Y'),
                ],
            ],
            'related_persons' => [],
        ];

        $spouse = trim(collect([
            $customer->spouse_first_name,
            $customer->spouse_middle_name,
            $customer->spouse_last_name,
        ])->filter()->implode(' '));
        if ($spouse !== '') {
            $personal['spouses'] = [['name' => $spouse]];
            $personal['related_persons'] = [['name' => $spouse, 'relation' => 'Spouse']];
            $personal['number_of_spouses'] = 1;
        }

        // Refer scenario: keep hard identity matching, add reviewable soft gaps.
        if ($scenario === 'refer') {
            $personal['mobile'] = '+255712345678';
            $personal['address'] = 'Mikocheni, Kinondoni, Dar es Salaam';
            if (($personal['number_of_children'] ?? null) !== null) {
                $personal['number_of_children'] = max(0, (int) $personal['number_of_children'] + 1);
            } else {
                $personal['number_of_children'] = 2;
            }
            if ($spouse !== '') {
                $personal['spouses'] = [['name' => 'Hassan Ali Mwinyi']];
                $personal['related_persons'] = [['name' => 'Hassan Ali Mwinyi', 'relation' => 'Spouse']];
            }
        }

        return $personal;
    }

    /** @return array<string, mixed> */
    private function wrongSubjectPersonal(): array
    {
        $personal = config('crb_credit_samples.wrong_subject_personal', []);

        return is_array($personal) ? $personal : [];
    }

    /** @return array<string, mixed> */
    private function reportMeta(Customer $customer, string $scenario): array
    {
        $digits = NidaNumber::digits((string) $customer->national_id) ?: (string) $customer->id;

        return [
            'cir_number' => 'W-STUB/'.strtoupper($scenario).'/'.$customer->id,
            'ruid' => 'stub-'.substr($digits, -12),
            'ordered_at' => now()->format('d-M-Y'),
            'institution_name' => 'Kopafasta (stub)',
            'search_score' => '100%',
            'driver' => 'stub',
            'scenario' => $scenario,
        ];
    }

    private function genderLabel(?string $gender): string
    {
        $g = Str::lower(trim((string) $gender));
        if (str_starts_with($g, 'f')) {
            return 'Female';
        }
        if (str_starts_with($g, 'm')) {
            return 'Male';
        }

        return $gender ? Str::title($gender) : 'Male';
    }

    private function maritalLabel(?string $status): string
    {
        $v = Str::lower(trim((string) $status));
        if (str_contains($v, 'marri') || $v === 'spouse') {
            return 'Married';
        }
        if (str_contains($v, 'single') || str_contains($v, 'never')) {
            return 'Single';
        }
        if (str_contains($v, 'divor')) {
            return 'Divorced';
        }
        if (str_contains($v, 'widow')) {
            return 'Widowed';
        }

        return $status ? Str::title($status) : 'Single';
    }
}
