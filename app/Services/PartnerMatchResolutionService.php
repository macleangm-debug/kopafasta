<?php

namespace App\Services;

use App\Models\Partner;
use App\Models\PartnerApplication;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Application ↔ existing Partner match comparison + staff resolution.
 * Reuses Partner duplicate detection — no second identity / merge engine.
 */
class PartnerMatchResolutionService
{
    public function __construct(
        private readonly PartnerEnrollmentService $enrollment,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function matchesFor(PartnerApplication $application): array
    {
        if ($application->partner_id) {
            return [];
        }

        $partners = $this->candidatePartners($application);
        $resolutions = $this->resolutions($application);
        $out = [];

        foreach ($partners as $partner) {
            $comparison = $this->compare($application, $partner);
            $prior = $resolutions[(string) $partner->id] ?? null;
            $out[] = [
                'partner_id' => $partner->id,
                'partner_url' => route('admin.partners.show', ['vendor' => $partner->id, 'operational' => 1]),
                'canonical_360_url' => route('admin.partners.show', $partner),
                'matched_fields' => $comparison['matched_fields'],
                'conflict_fields' => $comparison['conflict_fields'],
                'match_summary' => $this->matchSummaryLabel($comparison['matched_fields']),
                'likely_same_person' => $comparison['likely_same_person'],
                'resolution' => $prior['decision'] ?? null,
                'resolved' => filled($prior['decision'] ?? null),
                'uniqueness' => $comparison['uniqueness'],
                'applicant' => $comparison['applicant'],
                'existing' => $comparison['existing'],
                'rows' => $comparison['rows'],
                'link_preview' => $this->linkPreview($application, $partner),
            ];
        }

        return $out;
    }

    /** @return list<array<string, mixed>> */
    public function unresolvedMatches(PartnerApplication $application): array
    {
        return array_values(array_filter(
            $this->matchesFor($application),
            fn (array $m) => ! ($m['resolved'] ?? false)
        ));
    }

    /** @return list<int> */
    public function keepSeparatePartnerIds(PartnerApplication $application): array
    {
        $ids = [];
        foreach ($this->resolutions($application) as $partnerId => $row) {
            if (($row['decision'] ?? '') === 'keep_separate') {
                $ids[] = (int) $partnerId;
            }
        }

        return $ids;
    }

    public function linkedPartnerId(PartnerApplication $application): ?int
    {
        foreach ($this->resolutions($application) as $partnerId => $row) {
            if (($row['decision'] ?? '') === 'link') {
                return (int) $partnerId;
            }
        }

        return null;
    }

    /**
     * @param  array{decision: string, partner_id: int}  $data
     */
    public function resolve(PartnerApplication $application, array $data): PartnerApplication
    {
        $decision = (string) ($data['decision'] ?? '');
        $partnerId = (int) ($data['partner_id'] ?? 0);

        if (! in_array($decision, ['keep_separate', 'link'], true)) {
            throw ValidationException::withMessages(['decision' => 'Choose a valid match resolution.']);
        }

        $match = collect($this->matchesFor($application))
            ->first(fn (array $m) => (int) $m['partner_id'] === $partnerId);
        if (! $match) {
            throw ValidationException::withMessages(['partner_id' => 'That partner is not a current match for this application.']);
        }

        if ($decision === 'link' && ! ($match['likely_same_person'] ?? false)) {
            // Still allowed — staff may override after seeing conflicts — but require explicit confirm flag.
            if (! ($data['confirm_despite_conflicts'] ?? false)) {
                throw ValidationException::withMessages([
                    'decision' => 'Identity fields conflict. Confirm you still want to link, or choose Keep separate.',
                ]);
            }
        }

        $payload = is_array($application->payload) ? $application->payload : [];
        $resolutions = is_array($payload['match_resolutions'] ?? null) ? $payload['match_resolutions'] : [];
        $resolutions[(string) $partnerId] = [
            'partner_id' => $partnerId,
            'decision' => $decision,
            'matched_fields' => $match['matched_fields'],
            'conflict_fields' => $match['conflict_fields'],
            'decided_at' => now()->toIso8601String(),
            'decided_by' => Auth::id(),
        ];
        $payload['match_resolutions'] = $resolutions;

        $activity = is_array($payload['review_activity'] ?? null) ? $payload['review_activity'] : [];
        $activity[] = [
            'at' => now()->toIso8601String(),
            'label' => $decision === 'link' ? 'Match resolved: link existing Partner' : 'Match resolved: different people',
            'detail' => ($match['existing']['name'] ?? 'Partner').' · '.($match['match_summary'] ?? ''),
            'actor_id' => Auth::id(),
        ];
        $payload['review_activity'] = $activity;

        $application->fill(['payload' => $payload])->save();

        return $application->fresh(['documents', 'partner', 'reviewer']);
    }

    /**
     * @return \Illuminate\Support\Collection<int, Partner>
     */
    private function candidatePartners(PartnerApplication $application)
    {
        if (! filled($application->phone) && ! filled($application->email) && ! filled($application->tin)) {
            return collect();
        }

        return Partner::query()
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
            ->orderByDesc('id')
            ->limit(10)
            ->get();
    }

    /** @return array<string, array<string, mixed>> */
    private function resolutions(PartnerApplication $application): array
    {
        $payload = is_array($application->payload) ? $application->payload : [];
        $rows = is_array($payload['match_resolutions'] ?? null) ? $payload['match_resolutions'] : [];
        $out = [];
        foreach ($rows as $key => $row) {
            if (! is_array($row)) {
                continue;
            }
            $id = (string) ($row['partner_id'] ?? $key);
            $out[$id] = $row;
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    private function compare(PartnerApplication $application, Partner $partner): array
    {
        $payload = is_array($application->payload) ? $application->payload : [];
        $identity = is_array($payload['identity'] ?? null) ? $payload['identity'] : [];
        $decl = is_array(data_get($payload, 'declarations.applicant')) ? data_get($payload, 'declarations.applicant') : [];

        $appDob = $identity['date_of_birth'] ?? ($decl['date_of_birth'] ?? null);
        $appGender = $identity['gender'] ?? ($decl['gender'] ?? null);
        $appNida = $this->maskNida($this->applicationNida($application));
        $partnerMeta = is_array($partner->metadata) ? $partner->metadata : [];
        $partnerDob = $partnerMeta['date_of_birth'] ?? null;
        $partnerGender = $partnerMeta['gender'] ?? null;
        $partnerNida = $this->maskNida($partnerMeta['nida_number'] ?? $partnerMeta['national_id'] ?? null);

        $category = $this->enrollment->normalizeCategory(
            (string) ($application->partner_category ?: ($application->type === 'affiliate' ? 'affiliate' : 'debt_collector'))
        );
        $appRole = $this->enrollment->categoryLabel($category);
        $existingRoles = collect($partner->partnerRoles())
            ->map(fn ($r) => $this->enrollment->categoryLabel((string) $r))
            ->implode(', ');

        $matched = [];
        $conflicts = [];

        $phoneMatch = filled($application->phone) && (string) $partner->phone === (string) $application->phone;
        $emailMatch = filled($application->email)
            && strtolower((string) $partner->email) === strtolower((string) $application->email);
        $tinMatch = filled($application->tin) && filled($partner->tin)
            && (string) $partner->tin === (string) $application->tin;

        if ($phoneMatch) {
            $matched[] = 'phone';
        } elseif (filled($application->phone) && filled($partner->phone)) {
            $conflicts[] = 'phone';
        }
        if ($emailMatch) {
            $matched[] = 'email';
        } elseif (filled($application->email) && filled($partner->email)) {
            $conflicts[] = 'email';
        }
        if ($tinMatch) {
            $matched[] = 'tin';
        } elseif (filled($application->tin) && filled($partner->tin)) {
            $conflicts[] = 'tin';
        }

        $nameSame = $this->normalizeName((string) $application->full_name)
            === $this->normalizeName((string) $partner->name);
        if (! $nameSame && filled($application->full_name) && filled($partner->name)) {
            $conflicts[] = 'name';
        } elseif ($nameSame && filled($application->full_name)) {
            $matched[] = 'name';
        }

        if (filled($appDob) && filled($partnerDob)) {
            if ((string) $appDob === (string) $partnerDob) {
                $matched[] = 'date_of_birth';
            } else {
                $conflicts[] = 'date_of_birth';
            }
        }
        if (filled($appGender) && filled($partnerGender)) {
            if (strtolower((string) $appGender) === strtolower((string) $partnerGender)) {
                $matched[] = 'gender';
            } else {
                $conflicts[] = 'gender';
            }
        }

        $likelySame = $nameSame && ($phoneMatch || $tinMatch || (count($matched) >= 2 && ! in_array('name', $conflicts, true)));
        // Strong path: phone+name or tin+name. Email alone never likely_same.
        if ($emailMatch && ! $phoneMatch && ! $tinMatch && ! $nameSame) {
            $likelySame = false;
        }

        // Shared email cannot activate a second Partner login — Admin must correct it after Keep separate.
        $emailUniqueBlock = $emailMatch
            || (filled($application->email)
                && User::query()
                    ->where('email', $application->email)
                    ->when($partner->user_id, fn ($q) => $q->where('id', '!=', $partner->user_id))
                    ->exists());

        $applicant = [
            'name' => $application->full_name,
            'reference' => 'PA-'.$application->id,
            'role' => $appRole.' applicant',
            'phone' => $application->phone ?: '—',
            'email' => $application->email ?: '—',
            'nida' => $appNida ?: 'Not available',
            'tin' => filled($application->tin) ? $application->tin : 'Not provided',
            'date_of_birth' => $appDob ? \Illuminate\Support\Carbon::parse($appDob)->format('d M Y') : 'Not provided',
            'gender' => $appGender ? Str::title((string) $appGender) : 'Not provided',
            'status' => 'Under review',
        ];

        $existing = [
            'name' => $partner->name,
            'reference' => $partner->vendor_number ?: ($partner->partner_number ?: 'P-'.$partner->id),
            'role' => $existingRoles !== '' ? $existingRoles : Str::title(str_replace('_', ' ', (string) $partner->category)),
            'phone' => $partner->phone ?: '—',
            'email' => $partner->email ?: '—',
            'nida' => $partnerNida ?: 'Not available',
            'tin' => filled($partner->tin) ? $partner->tin : 'Not provided',
            'date_of_birth' => $partnerDob ? \Illuminate\Support\Carbon::parse($partnerDob)->format('d M Y') : 'Not provided',
            'gender' => $partnerGender ? Str::title((string) $partnerGender) : 'Not provided',
            'status' => $partner->activated_at ? 'Active' : Str::title((string) $partner->status),
        ];

        $rows = [
            $this->row('Name', $applicant['name'], $existing['name'], in_array('name', $matched, true), in_array('name', $conflicts, true)),
            $this->row('Partner / application no.', $applicant['reference'], $existing['reference'], false, false),
            $this->row('Role', $applicant['role'], $existing['role'], false, false),
            $this->row('Phone', $applicant['phone'], $existing['phone'], in_array('phone', $matched, true), in_array('phone', $conflicts, true)),
            $this->row('Email', $applicant['email'], $existing['email'], in_array('email', $matched, true), in_array('email', $conflicts, true)),
            $this->row('NIDA', $applicant['nida'], $existing['nida'], false, false),
            $this->row('TIN', $applicant['tin'], $existing['tin'], in_array('tin', $matched, true), in_array('tin', $conflicts, true)),
            $this->row('Date of birth', $applicant['date_of_birth'], $existing['date_of_birth'], in_array('date_of_birth', $matched, true), in_array('date_of_birth', $conflicts, true)),
            $this->row('Gender', $applicant['gender'], $existing['gender'], in_array('gender', $matched, true), in_array('gender', $conflicts, true)),
            $this->row('Status', $applicant['status'], $existing['status'], false, false),
        ];

        return [
            'matched_fields' => $matched,
            'conflict_fields' => $conflicts,
            'likely_same_person' => $likelySame,
            'applicant' => $applicant,
            'existing' => $existing,
            'rows' => $rows,
            'uniqueness' => [
                'email_shared_with_existing_login' => $emailUniqueBlock,
                'message' => $emailUniqueBlock
                    ? 'Email is already used on the existing Partner login. Keep separate requires a unique email before this applicant can activate.'
                    : null,
            ],
        ];
    }

    /** @return array{label: string, applicant: string, existing: string, matched: bool, conflict: bool} */
    private function row(string $label, string $applicant, string $existing, bool $matched, bool $conflict): array
    {
        return compact('label', 'applicant', 'existing', 'matched', 'conflict');
    }

    /** @param  list<string>  $fields */
    private function matchSummaryLabel(array $fields): string
    {
        if ($fields === []) {
            return 'Possible match';
        }

        return collect($fields)->map(fn ($f) => match ($f) {
            'phone' => 'Phone',
            'email' => 'Email',
            'tin' => 'TIN',
            'name' => 'Name',
            'date_of_birth' => 'Date of birth',
            'gender' => 'Gender',
            default => Str::title(str_replace('_', ' ', $f)),
        })->implode(' · ').' matched';
    }

    private function linkPreview(PartnerApplication $application, Partner $partner): string
    {
        $category = $this->enrollment->normalizeCategory(
            (string) ($application->partner_category ?: ($application->type === 'affiliate' ? 'affiliate' : 'debt_collector'))
        );
        $label = $this->enrollment->categoryLabel($category);
        $hasRole = $partner->hasPartnerRole($category) || $partner->category === $category;

        if ($hasRole) {
            return 'Link this application to '.$partner->name.' ('.$this->matchCode($partner).'). No new Partner identity. Existing '.$label.' role remains.';
        }

        return 'Link this application to '.$partner->name.' ('.$this->matchCode($partner).') and add '.$label.' to that Partner’s roles. No new Partner identity will be created.';
    }

    private function matchCode(Partner $partner): string
    {
        return (string) ($partner->vendor_number ?: $partner->partner_number ?: 'P-'.$partner->id);
    }

    private function applicationNida(PartnerApplication $application): ?string
    {
        $payload = is_array($application->payload) ? $application->payload : [];
        $nida = data_get($payload, 'identity.nida_number')
            ?? data_get($payload, 'identity.national_id')
            ?? data_get($payload, 'nida_number');

        return filled($nida) ? (string) $nida : null;
    }

    private function maskNida(?string $value): ?string
    {
        if (! filled($value)) {
            return null;
        }
        $digits = preg_replace('/\D+/', '', $value) ?? '';
        if (strlen($digits) < 6) {
            return '••••';
        }

        return substr($digits, 0, 4).str_repeat('•', max(0, strlen($digits) - 8)).substr($digits, -4);
    }

    private function normalizeName(string $name): string
    {
        return strtolower(trim(preg_replace('/\s+/', ' ', $name) ?? ''));
    }
}
