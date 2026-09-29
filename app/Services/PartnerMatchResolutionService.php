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
                'is_affiliate_collision' => $comparison['is_affiliate_collision'],
                'same_person_label' => $comparison['is_affiliate_collision']
                    ? 'Same person → Use existing Affiliate'
                    : 'Same person → Link identity',
                'same_person_helper' => $comparison['is_affiliate_collision']
                    ? 'This application belongs to this existing Affiliate. No new Affiliate account will be created and existing profile information will not be automatically overwritten.'
                    : $this->linkPreview($application, $partner),
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
        $isAffiliateCollision = (bool) ($match['is_affiliate_collision'] ?? false);
        $activity[] = [
            'at' => now()->toIso8601String(),
            'label' => $decision === 'link'
                ? ($isAffiliateCollision ? 'Match resolved: use existing Affiliate' : 'Match resolved: link existing Partner')
                : 'Match resolved: different people',
            'detail' => ($match['existing']['name'] ?? 'Partner').' · '.($match['match_summary'] ?? ''),
            'actor_id' => Auth::id(),
        ];
        $payload['review_activity'] = $activity;

        $application->fill(['payload' => $payload])->save();

        return $application->fresh(['documents', 'partner', 'reviewer']);
    }

    /**
     * Correct applicant email during duplicate resolution. Preserves the originally
     * submitted address in payload history — does not rewrite historical snapshots.
     */
    public function changeApplicantEmail(PartnerApplication $application, string $email): PartnerApplication
    {
        $normalized = strtolower(trim($email));
        if ($normalized === '' || ! filter_var($normalized, FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessages(['email' => 'Enter a valid email address.']);
        }

        $current = strtolower(trim((string) $application->email));
        if ($normalized === $current) {
            throw ValidationException::withMessages(['email' => 'Enter a different email than the one already on this application.']);
        }

        $partnerHit = Partner::query()->whereRaw('LOWER(email) = ?', [$normalized])->first();
        if ($partnerHit) {
            throw ValidationException::withMessages([
                'email' => 'This email is already used by Partner '.($partnerHit->name ?: ('#'.$partnerHit->id)).'. Choose another address.',
            ]);
        }

        $userHit = User::query()->whereRaw('LOWER(email) = ?', [$normalized])->exists();
        if ($userHit) {
            throw ValidationException::withMessages([
                'email' => 'This email is already used by an existing login. Choose another address.',
            ]);
        }

        $otherApp = PartnerApplication::query()
            ->where('id', '!=', $application->id)
            ->whereRaw('LOWER(email) = ?', [$normalized])
            ->whereIn('status', ['pending', 'needs_info'])
            ->first();
        if ($otherApp) {
            throw ValidationException::withMessages([
                'email' => 'This email is already used on application PA-'.$otherApp->id.'. Choose another address.',
            ]);
        }

        $payload = is_array($application->payload) ? $application->payload : [];
        $originalSubmitted = (string) ($payload['submitted_email'] ?? $application->email);
        if (! filled($payload['submitted_email'] ?? null) && filled($application->email)) {
            $payload['submitted_email'] = (string) $application->email;
            $originalSubmitted = (string) $application->email;
        }

        $history = is_array($payload['email_history'] ?? null) ? $payload['email_history'] : [];
        $history[] = [
            'from' => (string) $application->email,
            'to' => $normalized,
            'at' => now()->toIso8601String(),
            'actor_id' => Auth::id(),
            'context' => 'duplicate_resolution',
        ];
        $payload['email_history'] = $history;

        if (is_array($payload['identity'] ?? null) && array_key_exists('email', $payload['identity'])) {
            $payload['identity']['email'] = $normalized;
        }

        $activity = is_array($payload['review_activity'] ?? null) ? $payload['review_activity'] : [];
        $activity[] = [
            'at' => now()->toIso8601String(),
            'label' => 'Applicant email corrected during duplicate resolution',
            'detail' => 'From '.$application->email.' → '.$normalized.' (original submitted: '.$originalSubmitted.')',
            'actor_id' => Auth::id(),
        ];
        $payload['review_activity'] = $activity;

        $application->fill([
            'email' => $normalized,
            'payload' => $payload,
        ])->save();

        return $application->fresh(['documents', 'partner', 'reviewer']);
    }

    /**
     * Keep-separate collisions that still share a unique login email.
     *
     * @return list<array<string, mixed>>
     */
    public function emailUniquenessBlockers(PartnerApplication $application): array
    {
        return array_values(array_filter(
            $this->matchesFor($application),
            fn (array $m) => ($m['resolution'] ?? null) === 'keep_separate'
                && ! empty($m['uniqueness']['email_shared_with_existing_login'])
        ));
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
        $isAffiliateCollision = $category === 'affiliate'
            && ($partner->isAffiliate() || $partner->hasPartnerRole('affiliate') || $partner->category === 'affiliate');

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

        // Shared email/phone cannot activate a second Partner login — Admin must correct after Keep separate.
        $emailUniqueBlock = $emailMatch
            || (filled($application->email)
                && User::query()
                    ->where('email', $application->email)
                    ->when($partner->user_id, fn ($q) => $q->where('id', '!=', $partner->user_id))
                    ->exists());

        $phoneUniqueBlock = $phoneMatch
            || (filled($application->phone)
                && (
                    Partner::query()
                        ->where('phone', $application->phone)
                        ->where('id', '!=', $partner->id)
                        ->exists()
                    || User::query()
                        ->where('phone', $application->phone)
                        ->when($partner->user_id, fn ($q) => $q->where('id', '!=', $partner->user_id))
                        ->exists()
                ));

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
            'is_affiliate_collision' => $isAffiliateCollision,
            'applicant' => $applicant,
            'existing' => $existing,
            'rows' => $rows,
            'uniqueness' => [
                'email_shared_with_existing_login' => $emailUniqueBlock,
                'phone_shared_with_existing_login' => $phoneUniqueBlock,
                'email' => $emailUniqueBlock ? (string) $application->email : null,
                'phone' => $phoneUniqueBlock ? (string) $application->phone : null,
                'message' => $this->uniquenessMessage($emailUniqueBlock, $phoneUniqueBlock),
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

    private function uniquenessMessage(bool $emailBlock, bool $phoneBlock): ?string
    {
        if ($emailBlock && $phoneBlock) {
            return 'Email and phone are already used on an existing Partner login. Keep separate does not bypass uniqueness — correct the duplicated contact details before this applicant can activate.';
        }
        if ($emailBlock) {
            return 'Email is already used on the existing Partner login. Keep separate does not bypass uniqueness — correct the duplicated email before this applicant can activate.';
        }
        if ($phoneBlock) {
            return 'Phone is already used on the existing Partner login. Keep separate does not bypass uniqueness — correct the duplicated phone before this applicant can activate.';
        }

        return null;
    }

    private function linkPreview(PartnerApplication $application, Partner $partner): string
    {
        $category = $this->enrollment->normalizeCategory(
            (string) ($application->partner_category ?: ($application->type === 'affiliate' ? 'affiliate' : 'debt_collector'))
        );
        $isAffiliateCollision = $category === 'affiliate'
            && ($partner->isAffiliate() || $partner->hasPartnerRole('affiliate') || $partner->category === 'affiliate');

        if ($isAffiliateCollision) {
            return 'This application belongs to this existing Affiliate. No new Affiliate account will be created and existing profile information will not be automatically overwritten.';
        }

        $label = $this->enrollment->categoryLabel($category);
        $hasRole = $partner->hasPartnerRole($category) || $partner->category === $category;
        $existingLabel = $this->enrollment->categoryLabel((string) ($partner->category ?: 'partner'));

        if ($hasRole) {
            return 'Link this application to '.$partner->name.' ('.$this->matchCode($partner).'). One login identity. No new Partner or user. Existing '.$label.' role remains. Profile fields are not overwritten.';
        }

        return 'Link this application to '.$partner->name.' ('.$this->matchCode($partner).'). One login identity keeps '.$existingLabel.'; '.$label.' is added as another workspace. No new user. Partner business records stay separate. Profile fields are not overwritten.';
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
