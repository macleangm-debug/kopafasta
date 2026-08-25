<?php

namespace App\Services\Grades;

use App\Models\Customer;
use App\Models\LoanProduct;

class GradeBenefitService
{
    public function potentialAccess(Customer $customer): float
    {
        $bands = app(GradeSettings::class)->countryBands((string) ($customer->country_code ?? 'TZ'));
        $grade = (string) ($customer->grade ?: 'bronze');

        return (float) ($bands['potential_access'][$grade] ?? 0);
    }

    public function productEligible(Customer $customer, LoanProduct $product): bool
    {
        $grades = $product->eligible_grades;
        if (! is_array($grades) || $grades === []) {
            return true;
        }

        return in_array((string) ($customer->grade ?: 'bronze'), $grades, true);
    }

    public function repeatJourney(Customer $customer): string
    {
        $rules = app(GradeSettings::class)->rules();
        $grade = (string) ($customer->grade ?: 'bronze');

        return (string) ($rules['benefits'][$grade]['repeat_journey'] ?? 'full');
    }

    public function trustLabel(int $percent, string $locale = 'en'): array
    {
        $bands = app(GradeSettings::class)->rules()['trust_labels'] ?? [];
        foreach ($bands as $band) {
            if ($percent <= (int) $band['max']) {
                return [
                    'key' => $band['key'],
                    'label' => $band[$locale] ?? $band['en'],
                    'percent' => $percent,
                ];
            }
        }

        return ['key' => 'excellent', 'label' => $locale === 'sw' ? 'Bora' : 'Excellent', 'percent' => $percent];
    }

    /** @return list<string> */
    public function customerBenefits(Customer $customer, string $locale = 'en', ?float $accessAmount = null): array
    {
        $grade = (string) ($customer->grade ?: 'bronze');
        $access = $accessAmount ?? $this->potentialAccess($customer);
        $amount = function_exists('format_money') ? format_money($access) : (string) $access;

        $copy = [
            'bronze' => [
                'en' => ['Potential access up to '.$amount, 'Build a strong repayment record', 'Standard service'],
                'sw' => ['Uwezekano wa kufikia '.$amount, 'Jenga historia nzuri ya malipo', 'Huduma ya kawaida'],
            ],
            'silver' => [
                'en' => ['Potential access up to '.$amount, 'Faster repeat application', 'Silver offers', 'More eligible products'],
                'sw' => ['Uwezekano wa kufikia '.$amount, 'Maombi ya kurudia yanakuwa haraka', 'Ofa za Silver', 'Bidhaa zaidi zinazostahili'],
            ],
            'gold' => [
                'en' => ['Potential access up to '.$amount, 'Priority service', 'Gold offers', 'Welcome-back repeat journey'],
                'sw' => ['Uwezekano wa kufikia '.$amount, 'Huduma ya kipaumbele', 'Ofa za Gold', 'Maombi ya kurudia yanakuwa rahisi'],
            ],
            'platinum' => [
                'en' => ['Potential access up to '.$amount, 'Highest service priority', 'Exclusive opportunities', 'Prefill repeat journey'],
                'sw' => ['Uwezekano wa kufikia '.$amount, 'Kipaumbele cha juu kabisa', 'Fursa maalum', 'Maombi yanajazwa mapema'],
            ],
        ];

        $list = $copy[$grade][$locale] ?? $copy[$grade]['en'] ?? $copy['bronze']['en'];
        $extra = app(GradeSettings::class)->rules()['benefits'][$grade] ?? [];
        foreach (['exclusive', 'rewards'] as $key) {
            if (filled($extra[$key] ?? null) && ! in_array((string) $extra[$key], $list, true)) {
                $list[] = (string) $extra[$key];
            }
        }

        return array_values($list);
    }

    public function maxTenureMonths(Customer $customer): ?int
    {
        $grade = (string) ($customer->grade ?: 'bronze');
        $value = app(GradeSettings::class)->rules()['benefits'][$grade]['max_tenure_months'] ?? null;

        return filled($value) ? max(1, (int) $value) : null;
    }

    public function servicePriority(Customer $customer): string
    {
        $grade = (string) ($customer->grade ?: 'bronze');

        return (string) (app(GradeSettings::class)->rules()['benefits'][$grade]['priority'] ?? 'standard');
    }

    public function offerTier(Customer $customer): string
    {
        $grade = (string) ($customer->grade ?: 'bronze');

        return (string) (app(GradeSettings::class)->rules()['benefits'][$grade]['offer_tier'] ?? $grade);
    }

    /** Internal Grade Watch copy. Never shown to customers. */
    public function staffIntegrityCopy(\App\Models\Customer $customer): array
    {
        $evaluation = \App\Models\CustomerGradeEvaluation::query()
            ->where('customer_id', $customer->id)
            ->latest('id')
            ->first();
        $signals = (array) ($evaluation?->integrity_signals ?? []);
        $facts = (array) ($evaluation?->facts ?? []);
        $lines = [];

        if (in_array('rapid_facility_cycling', $signals, true) || in_array('rapid_facility_cycling', $signals, true)) {
            $count = (int) ($facts['rapid_cycle_count'] ?? $facts['rapid_cycle_count'] ?? 0);
            $lines[] = [
                'title' => 'Rapid facility cycling detected',
                'body' => $count > 0
                    ? $count.' qualifying facilities completed within a short window.'
                    : 'Several qualifying facilities were completed close together.',
            ];
        }
        if (in_array('payment_reversals', $signals, true) || in_array('payment_reversals', $signals, true)) {
            $count = (int) ($facts['reversed_payments_count'] ?? $facts['reversed_payments_count'] ?? 0);
            $lines[] = [
                'title' => 'Unusual payment reversals',
                'body' => $count > 0
                    ? $count.' repayments were subsequently reversed.'
                    : 'Repayments were subsequently reversed.',
            ];
        }
        if (in_array('many_tiny_facilities', $signals, true) || in_array('many_tiny_facilities', $signals, true)) {
            $lines[] = [
                'title' => 'Many small facilities',
                'body' => 'A cluster of very small completed facilities was recorded.',
            ];
        }
        if ($lines === []) {
            $status = (string) ($customer->grade_integrity ?: 'normal');
            $lines[] = [
                'title' => 'Integrity status: '.$status,
                'body' => (string) ($evaluation?->reason ?: 'Integrity flags require a staff decision.'),
            ];
        }

        return $lines;
    }

    public function nextGradeCopy(Customer $customer, string $locale = 'en'): array
    {
        $grade = (string) ($customer->grade ?: 'bronze');
        $next = ['bronze' => 'silver', 'silver' => 'gold', 'gold' => 'platinum', 'platinum' => null][$grade] ?? 'silver';
        if ($next === null) {
            return [
                'grade' => null,
                'title' => $locale === 'sw' ? 'Hii ni hadhi ya juu kabisa.' : 'This is our highest customer status.',
                'body' => $locale === 'sw'
                    ? 'Endelea kuweka ahadi zako kwa wakati.'
                    : 'Keep honouring your commitments on time.',
            ];
        }

        return [
            'grade' => $next,
            'title' => $locale === 'sw' ? 'Ifuatayo: '.ucfirst($next) : 'Next: '.ucfirst($next),
            'body' => $locale === 'sw'
                ? 'Endelea kujenga historia thabiti ya fedha.'
                : 'Keep building your strong financial history.',
        ];
    }
}
