<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Setting;

class ProfileCompletionService
{
    /** @return array{percent: int, sections: list<array{key: string, label: string, complete: bool, weight: int}>} */
    public function calculate(Customer $customer): array
    {
        $sections = [
            [
                'key'      => 'personal',
                'label'    => 'Personal information',
                'complete' => filled($customer->first_name) && filled($customer->last_name) && filled($customer->date_of_birth),
                'weight'   => 15,
            ],
            [
                'key'      => 'nida',
                'label'    => 'NIDA verification',
                'complete' => app(NidaVerificationService::class)->isVerified($customer),
                'weight'   => 20,
            ],
            [
                'key'      => 'face',
                'label'    => 'Face verification',
                'complete' => app(FaceVerificationService::class)->isVerified($customer),
                'weight'   => 20,
            ],
            [
                'key'      => 'activity',
                'label'    => 'Activity information',
                'complete' => filled($customer->activity_type) && filled($customer->income_range),
                'weight'   => 15,
            ],
            [
                'key'      => 'residence',
                'label'    => 'Residence information',
                'complete' => filled($customer->region) && filled($customer->district) && filled($customer->street),
                'weight'   => 15,
            ],
            [
                'key'      => 'kin',
                'label'    => 'Next of kin',
                'complete' => filled($customer->nok_name) && filled($customer->nok_phone) && filled($customer->nok_relationship),
                'weight'   => 15,
            ],
        ];

        $totalWeight = array_sum(array_column($sections, 'weight'));
        $earned = 0;
        foreach ($sections as $section) {
            if ($section['complete']) {
                $earned += $section['weight'];
            }
        }

        return [
            'percent'   => $totalWeight > 0 ? (int) round(($earned / $totalWeight) * 100) : 0,
            'sections'  => $sections,
            'threshold' => (int) (Setting::group('loan')['qualification_min_profile_percent'] ?? 60),
        ];
    }

    public function meetsThreshold(Customer $customer): bool
    {
        $result = $this->calculate($customer);

        return $result['percent'] >= $result['threshold'];
    }
}
