<?php

namespace App\Services;

use App\Models\Partner;
use App\Models\PartnerApplication;

/**
 * Surface enrolment anomalies early so reviewers decide faster.
 */
class PartnerEnrollmentAnomalyService
{
    /**
     * @return list<array{code: string, severity: string, title: string, detail: string}>
     */
    public function forApplication(PartnerApplication $application, array $review = []): array
    {
        $application->loadMissing(['documents']);
        $anomalies = [];

        $checklist = $review['checklist'] ?? [];
        $missing = collect($checklist)->where('present', false)->values();
        if ($missing->isNotEmpty()) {
            $anomalies[] = $this->item(
                'docs_missing',
                'critical',
                'Required documents missing',
                $missing->pluck('label')->implode(', '),
            );
        }

        $identity = $review['identity'] ?? [];
        foreach (['national_id_front' => 'National ID (front)', 'national_id_back' => 'National ID (back)'] as $key => $label) {
            if (empty($identity[$key])) {
                $anomalies[] = $this->item('id_missing_'.$key, 'critical', $label.' missing', 'Identity document not uploaded.');
            }
        }

        if (($application->applicant_category ?? '') === 'company') {
            if (! filled($application->registration_number)) {
                $anomalies[] = $this->item('brela_missing', 'warning', 'BRELA / registration missing', 'Company applications should include a registration number.');
            }
            if (! filled($application->tin)) {
                $anomalies[] = $this->item('tin_missing', 'warning', 'TIN missing', 'Company applications should include a TIN.');
            }
            if (! filled($application->business_name) && ! filled($application->legal_name)) {
                $anomalies[] = $this->item('business_name_missing', 'warning', 'Business name missing', 'Trading or legal name is empty.');
            }
        }

        if (! filled($application->phone) || ! filled($application->email)) {
            $anomalies[] = $this->item('contact_incomplete', 'warning', 'Contact incomplete', 'Phone or email is missing.');
        }

        $duplicatePhone = PartnerApplication::query()
            ->where('id', '!=', $application->id)
            ->where('phone', $application->phone)
            ->whereIn('status', ['pending', 'needs_info', 'approved'])
            ->exists();
        $duplicateEmail = PartnerApplication::query()
            ->where('id', '!=', $application->id)
            ->where('email', $application->email)
            ->whereIn('status', ['pending', 'needs_info', 'approved'])
            ->exists();
        if ($duplicatePhone || $duplicateEmail) {
            $anomalies[] = $this->item(
                'duplicate_application',
                'critical',
                'Possible duplicate enrolment',
                ($duplicatePhone ? 'Same phone' : '').($duplicatePhone && $duplicateEmail ? ' and ' : '').($duplicateEmail ? 'same email' : '').' already on another application.',
            );
        }

        if (filled($application->phone) || filled($application->email) || filled($application->tin)) {
            $existingPartner = Partner::query()
                ->where(function ($q) use ($application) {
                    if (filled($application->phone)) {
                        $q->orWhere('phone', $application->phone);
                    }
                    if (filled($application->email)) {
                        $q->orWhere('email', $application->email);
                    }
                    if (filled($application->tin)) {
                        $q->orWhere('tin', $application->tin);
                    }
                })
                ->exists();
            if ($existingPartner && ! $application->partner_id) {
                $anomalies[] = $this->item(
                    'existing_partner',
                    'warning',
                    'Matching partner already exists',
                    'Phone, email, or TIN matches an existing partner record.',
                );
            }
        }

        if (($application->type !== 'affiliate' && $application->partner_category !== 'affiliate')
            && empty($application->coverage_regions)
            && ! filled($application->region)) {
            $anomalies[] = $this->item('coverage_missing', 'info', 'Coverage not specified', 'No primary region or coverage regions provided.');
        }

        $severity = ['critical' => 0, 'warning' => 1, 'info' => 2];
        usort($anomalies, fn ($a, $b) => ($severity[$a['severity']] ?? 9) <=> ($severity[$b['severity']] ?? 9));

        return $anomalies;
    }

    /** @return array{code: string, severity: string, title: string, detail: string} */
    private function item(string $code, string $severity, string $title, string $detail): array
    {
        return compact('code', 'severity', 'title', 'detail');
    }
}
