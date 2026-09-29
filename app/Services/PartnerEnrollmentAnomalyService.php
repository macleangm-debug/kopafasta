<?php

namespace App\Services;

use App\Models\PartnerApplication;

/**
 * Surface enrolment anomalies early so reviewers decide faster.
 */
class PartnerEnrollmentAnomalyService
{
    public function __construct(
        private readonly PartnerMatchResolutionService $matchResolution,
    ) {}

    /**
     * @return list<array{code: string, severity: string, title: string, detail: string, meta?: array<string, mixed>}>
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

        $matches = $this->matchResolution->matchesFor($application);
        $unresolved = array_values(array_filter($matches, fn ($m) => ! ($m['resolved'] ?? false)));
        if ($unresolved !== [] && ! $application->partner_id) {
            $first = $unresolved[0];
            $count = count($unresolved);
            $anomalies[] = $this->item(
                'existing_partner',
                'warning',
                'Possible existing Partner found',
                $count.' possible match'.($count === 1 ? '' : 'es').' · '.($first['match_summary'] ?? 'Review required'),
                [
                    'match_count' => $count,
                    'matches' => $unresolved,
                ],
            );
        } elseif ($matches !== [] && ! $application->partner_id) {
            $resolved = $matches[0];
            $label = ($resolved['resolution'] ?? '') === 'link'
                ? 'Staff chose Link existing Partner'
                : 'Staff confirmed different people';
            $anomalies[] = $this->item(
                'existing_partner_resolved',
                'info',
                'Match reviewed',
                $label.' · '.($resolved['existing']['name'] ?? 'Partner').' ('.($resolved['match_summary'] ?? '').')',
                ['matches' => $matches],
            );
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

    /**
     * @param  array<string, mixed>  $meta
     * @return array{code: string, severity: string, title: string, detail: string, meta?: array<string, mixed>}
     */
    private function item(string $code, string $severity, string $title, string $detail, array $meta = []): array
    {
        $row = compact('code', 'severity', 'title', 'detail');
        if ($meta !== []) {
            $row['meta'] = $meta;
        }

        return $row;
    }
}
