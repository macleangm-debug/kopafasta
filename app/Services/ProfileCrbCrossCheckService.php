<?php

namespace App\Services;

use App\Models\Customer;
use Illuminate\Support\Str;

/**
 * Quick red-flag analysis: profile vs CRB personal fields + credit behaviour signals.
 * Used after capacity/affordability pass when the paid CIR is pulled.
 */
class ProfileCrbCrossCheckService
{
    /**
     * @param  array<string, mixed>  $crbSummary  from CrbCreditCheckService::summaryForCustomer
     * @return array{
     *     checked_at: string,
     *     has_photo: bool,
     *     photo_note: string,
     *     identity_flags: list<array{code: string, severity: string, title: string, detail: string}>,
     *     credit_flags: list<array{code: string, severity: string, title: string, detail: string}>,
     *     matches: list<array{code: string, label: string, profile: mixed, crb: mixed}>,
     *     critical_count: int,
     *     warning_count: int
     * }
     */
    public function analyze(Customer $customer, array $crbSummary): array
    {
        $personal = $crbSummary['personal'] ?? [];
        $credit = $crbSummary['credit_detail'] ?? [];
        $overview = $credit['overview'] ?? [];
        $identityFlags = [];
        $creditFlags = [];
        $matches = [];

        // --- Identity / personal cross-checks (profile ↔ CRB) ---

        $profileName = $this->normalizeName($customer->full_name);
        $crbName = $this->normalizeName($personal['full_name'] ?? ($crbSummary['identity']['full_name'] ?? null));
        if ($profileName !== '' && $crbName !== '') {
            if ($this->namesLikelyMatch($profileName, $crbName)) {
                $matches[] = $this->match('full_name', 'Full name', $customer->full_name, $personal['full_name'] ?? $crbName);
            } else {
                $identityFlags[] = $this->flag(
                    'name_mismatch',
                    'critical',
                    'Name mismatch vs CRB',
                    'Profile: '.$customer->full_name.' · CRB: '.($personal['full_name'] ?? $crbName)
                );
            }
        }

        $profileDob = optional($customer->date_of_birth)->format('Y-m-d');
        $crbDob = $this->normalizeDate($personal['date_of_birth'] ?? ($crbSummary['identity']['date_of_birth'] ?? null));
        if ($profileDob && $crbDob) {
            if ($profileDob === $crbDob) {
                $matches[] = $this->match('date_of_birth', 'Date of birth', $profileDob, $crbDob);
            } else {
                $identityFlags[] = $this->flag(
                    'dob_mismatch',
                    'critical',
                    'Date of birth mismatch vs CRB',
                    'Profile: '.$profileDob.' · CRB: '.$crbDob
                );
            }
        }

        $profileGender = $this->normalizeGender($customer->gender);
        $crbGender = $this->normalizeGender($personal['gender'] ?? ($crbSummary['identity']['gender'] ?? null));
        if ($profileGender && $crbGender) {
            if ($profileGender === $crbGender) {
                $matches[] = $this->match('gender', 'Gender', $customer->gender, $personal['gender'] ?? null);
            } else {
                $identityFlags[] = $this->flag(
                    'gender_mismatch',
                    'critical',
                    'Gender mismatch vs CRB',
                    'Profile: '.($customer->gender ?? '—').' · CRB: '.($personal['gender'] ?? '—')
                );
            }
        }

        $nida = preg_replace('/\D+/', '', (string) $customer->national_id);
        $crbIds = collect($personal['ids'] ?? [])
            ->map(fn ($row) => preg_replace('/\D+/', '', (string) ($row['id_number'] ?? '')))
            ->filter()
            ->values();
        if ($nida && $crbIds->isNotEmpty() && ! $crbIds->contains($nida)) {
            $identityFlags[] = $this->flag(
                'id_mismatch',
                'critical',
                'NIDA / ID not found on CRB ID list',
                'Profile NIDA does not match any ID number on the CIR.'
            );
        } elseif ($nida && $crbIds->contains($nida)) {
            $matches[] = $this->match('national_id', 'National ID', $customer->national_id, $nida);
        }

        $profileMarital = $this->normalizeMarital($customer->marital_status);
        $crbMarital = $this->normalizeMarital($personal['marital_status'] ?? null);
        if ($profileMarital && $crbMarital) {
            if ($profileMarital === $crbMarital) {
                $matches[] = $this->match('marital_status', 'Marital status', $customer->marital_status, $personal['marital_status']);
            } else {
                $identityFlags[] = $this->flag(
                    'marital_mismatch',
                    'warning',
                    'Marital status differs from CRB',
                    'Profile: '.($customer->marital_status ?? '—').' · CRB: '.($personal['marital_status'] ?? '—')
                );
            }
        }

        if ($profileMarital === 'married') {
            $spouseProfile = $this->normalizeName($this->spouseFullName($customer));
            $spouseCrb = $this->normalizeName(
                collect($personal['spouses'] ?? [])->pluck('name')->filter()->implode(' ')
                ?: collect($personal['related_persons'] ?? [])
                    ->filter(fn ($r) => str_contains(strtolower((string) ($r['relation'] ?? '')), 'spouse'))
                    ->pluck('name')
                    ->filter()
                    ->implode(' ')
            );
            if ($spouseProfile !== '' && $spouseCrb !== '') {
                if ($this->namesLikelyMatch($spouseProfile, $spouseCrb)) {
                    $matches[] = $this->match('spouse_name', 'Spouse name', $this->spouseFullName($customer), $spouseCrb);
                } else {
                    $identityFlags[] = $this->flag(
                        'spouse_mismatch',
                        'warning',
                        'Spouse name differs from CRB',
                        'Profile: '.$this->spouseFullName($customer).' · CRB: '.$spouseCrb
                    );
                }
            } elseif ($spouseProfile !== '' && $spouseCrb === '') {
                $identityFlags[] = $this->flag(
                    'spouse_missing_on_crb',
                    'info',
                    'Spouse on profile, not on CRB',
                    'Borrower declared a spouse; CIR has no spouse name filled (common gap).'
                );
            }
        }

        if ($customer->number_of_children !== null && isset($personal['number_of_children']) && $personal['number_of_children'] !== null) {
            $pChildren = (int) $customer->number_of_children;
            $cChildren = (int) $personal['number_of_children'];
            if ($pChildren === $cChildren) {
                $matches[] = $this->match('number_of_children', 'Number of children', $pChildren, $cChildren);
            } else {
                $identityFlags[] = $this->flag(
                    'children_mismatch',
                    'warning',
                    'Number of children differs from CRB',
                    "Profile: {$pChildren} · CRB: {$cChildren}"
                );
            }
        }

        $profilePhone = $this->normalizePhone($customer->phone);
        $crbPhone = $this->normalizePhone($personal['mobile'] ?? null);
        if ($profilePhone && $crbPhone) {
            if ($this->phonesMatch($profilePhone, $crbPhone)) {
                $matches[] = $this->match('mobile', 'Mobile', $customer->phone, $personal['mobile']);
            } else {
                $identityFlags[] = $this->flag(
                    'phone_mismatch',
                    'warning',
                    'Mobile differs from CRB',
                    'Profile: '.($customer->phone ?? '—').' · CRB: '.($personal['mobile'] ?? '—')
                );
            }
        }

        $profileAddress = $this->normalizeName(trim(collect([
            $customer->street,
            $customer->ward,
            $customer->district,
            $customer->region,
        ])->filter()->implode(' ')));
        $crbAddress = $this->normalizeName($personal['address'] ?? null);
        if ($profileAddress !== '' && $crbAddress !== '' && ! $this->addressLikelyMatch($profileAddress, $crbAddress)) {
            $identityFlags[] = $this->flag(
                'address_mismatch',
                'info',
                'Current address differs from CRB',
                'Profile residence does not closely match CIR address — review address history.'
            );
        }

        $employer = $this->normalizeName($personal['employer'] ?? null);
        $profession = $this->normalizeName($personal['profession'] ?? null);
        $activity = $this->normalizeName($customer->activity_type ?? $customer->employment_type ?? null);
        if ($activity && ($employer || $profession)) {
            $hay = $employer.' '.$profession;
            if (! Str::contains($hay, Str::before($activity, '_')) && ! Str::contains($activity, ['self', 'employ', 'business', 'trade'])) {
                // Soft signal only when both sides look specific
                if (strlen($activity) > 3 && strlen($hay) > 3) {
                    $identityFlags[] = $this->flag(
                        'employment_soft_mismatch',
                        'info',
                        'Profession / employer may differ',
                        'Profile activity: '.($customer->activity_type ?? $customer->employment_type ?? '—')
                            .' · CRB: '.trim(($personal['profession'] ?? '').' / '.($personal['employer'] ?? ''))
                    );
                }
            }
        }

        // --- Credit behaviour red flags (absolute, not profile compare) ---

        $recommendation = strtolower((string) ($crbSummary['recommendation'] ?? $credit['recommendation'] ?? ''));
        if ($recommendation === 'reject') {
            $creditFlags[] = $this->flag('crb_reject', 'critical', 'CRB recommends reject', 'Bureau recommendation is reject.');
        } elseif ($recommendation === 'refer') {
            $creditFlags[] = $this->flag('crb_refer', 'warning', 'CRB recommends refer', 'Bureau recommends manual referral.');
        }

        $delinquencies = (int) ($crbSummary['delinquencies'] ?? $credit['delinquencies'] ?? 0);
        if ($delinquencies > 0) {
            $creditFlags[] = $this->flag(
                'delinquencies',
                'critical',
                'Active delinquencies',
                $delinquencies.' delinquency record(s) on CIR.'
            );
        }

        $unpaid30 = (int) ($overview['unpaid_instal_30'] ?? 0);
        $unpaid60 = (int) ($overview['unpaid_instal_60'] ?? 0);
        $unpaid360 = (int) ($overview['unpaid_instal_360'] ?? 0);
        if ($unpaid30 + $unpaid60 + $unpaid360 > 0) {
            $creditFlags[] = $this->flag(
                'unpaid_instalments',
                'critical',
                'Unpaid instalments on CIR',
                "30d: {$unpaid30} · 60d: {$unpaid60} · 360d: {$unpaid360}"
            );
        }

        $pastDue = (float) data_get($credit, 'balances_by_currency.TZS.past_due', 0);
        if ($pastDue <= 0) {
            foreach ($credit['balances_by_currency'] ?? [] as $row) {
                $pastDue += (float) ($row['past_due'] ?? 0);
            }
        }
        if ($pastDue > 0) {
            $creditFlags[] = $this->flag(
                'past_due_balance',
                'critical',
                'Past-due balance',
                format_money($pastDue).' past due across facilities.'
            );
        }

        $negative = strtolower((string) ($overview['most_negative_status'] ?? $credit['most_negative_status'] ?? ''));
        if ($negative !== '' && ! str_contains($negative, 'no negative') && $negative !== 'n/a' && $negative !== 'none') {
            $creditFlags[] = $this->flag(
                'negative_status',
                'critical',
                'Negative account status',
                'Most negative status: '.($overview['most_negative_status'] ?? $credit['most_negative_status'])
            );
        }

        $openLoans = (int) ($crbSummary['existing_loans'] ?? $credit['existing_loans'] ?? 0);
        $outstanding = (float) ($crbSummary['outstanding_balance'] ?? $credit['outstanding_balance'] ?? 0);
        if ($openLoans >= 3) {
            $creditFlags[] = $this->flag(
                'many_open_facilities',
                'warning',
                'Many open facilities',
                "{$openLoans} active loans · outstanding ".format_money($outstanding)
            );
        } elseif ($outstanding >= 5_000_000) {
            $creditFlags[] = $this->flag(
                'high_outstanding',
                'warning',
                'High outstanding balance',
                format_money($outstanding).' outstanding across '.$openLoans.' loan(s).'
            );
        }

        $inquiries = (int) ($overview['inquiries_by_fa'] ?? 0);
        if ($inquiries >= 5) {
            $creditFlags[] = $this->flag(
                'inquiry_spike',
                'warning',
                'High recent inquiry count',
                "{$inquiries} inquiries by financial institutions — possible credit shopping."
            );
        }

        $guaranteed = (int) ($overview['loans_guaranteed'] ?? 0);
        if ($guaranteed > 0) {
            $creditFlags[] = $this->flag(
                'loans_guaranteed',
                'info',
                'Loans guaranteed for others',
                "{$guaranteed} guaranteed facility(ies) — contingent liability."
            );
        }

        $legal = strtolower((string) ($overview['legal_dispute'] ?? $credit['legal_dispute'] ?? ''));
        if ($legal !== '' && ! in_array($legal, ['0', 'no', 'none', 'n/a', 'false'], true)) {
            $creditFlags[] = $this->flag(
                'legal_dispute',
                'critical',
                'Legal dispute flagged',
                'CIR indicates a legal dispute: '.($overview['legal_dispute'] ?? $credit['legal_dispute'])
            );
        }

        $all = array_merge($identityFlags, $creditFlags);
        $critical = count(array_filter($all, fn ($f) => ($f['severity'] ?? '') === 'critical'));
        $warning = count(array_filter($all, fn ($f) => ($f['severity'] ?? '') === 'warning'));

        return [
            'checked_at' => now()->toIso8601String(),
            'has_photo' => false,
            'photo_note' => 'CRB / NIDA identity responses do not include a portrait. Use borrower-uploaded face and ID photos.',
            'identity_flags' => $identityFlags,
            'credit_flags' => $creditFlags,
            'matches' => $matches,
            'critical_count' => $critical,
            'warning_count' => $warning,
        ];
    }

    public function spouseFullName(Customer $customer): string
    {
        return trim(collect([
            $customer->spouse_first_name,
            $customer->spouse_middle_name,
            $customer->spouse_last_name,
        ])->filter()->implode(' '));
    }

    /** @return array{code: string, severity: string, title: string, detail: string} */
    private function flag(string $code, string $severity, string $title, string $detail): array
    {
        return compact('code', 'severity', 'title', 'detail');
    }

    /** @return array{code: string, label: string, profile: mixed, crb: mixed} */
    private function match(string $code, string $label, mixed $profile, mixed $crb): array
    {
        return compact('code', 'label', 'profile', 'crb');
    }

    private function normalizeName(?string $value): string
    {
        $value = Str::lower(trim((string) $value));
        $value = preg_replace('/\s+/', ' ', $value) ?? '';

        return $value;
    }

    private function namesLikelyMatch(string $a, string $b): bool
    {
        if ($a === $b) {
            return true;
        }

        similar_text($a, $b, $pct);
        if ($pct >= 85) {
            return true;
        }

        $tokensA = collect(explode(' ', $a))->filter()->sort()->values();
        $tokensB = collect(explode(' ', $b))->filter()->sort()->values();

        return $tokensA->intersect($tokensB)->count() >= min(2, $tokensA->count(), $tokensB->count())
            && $tokensA->diff($tokensB)->count() <= 1;
    }

    private function addressLikelyMatch(string $a, string $b): bool
    {
        if ($a === $b || Str::contains($a, $b) || Str::contains($b, $a)) {
            return true;
        }

        similar_text($a, $b, $pct);

        return $pct >= 55;
    }

    private function normalizeDate(mixed $value): ?string
    {
        if (! filled($value)) {
            return null;
        }

        try {
            return \Carbon\Carbon::parse((string) $value)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    private function normalizeGender(?string $value): ?string
    {
        $v = Str::lower(trim((string) $value));
        if ($v === '') {
            return null;
        }
        if (str_starts_with($v, 'm') || $v === 'male') {
            return 'male';
        }
        if (str_starts_with($v, 'f') || $v === 'female') {
            return 'female';
        }

        return $v;
    }

    private function normalizeMarital(?string $value): ?string
    {
        $v = Str::lower(trim((string) $value));
        if ($v === '') {
            return null;
        }
        if (str_contains($v, 'marri')) {
            return 'married';
        }
        if (str_contains($v, 'single') || str_contains($v, 'never')) {
            return 'single';
        }
        if (str_contains($v, 'divor')) {
            return 'divorced';
        }
        if (str_contains($v, 'widow')) {
            return 'widowed';
        }

        return $v;
    }

    private function normalizePhone(?string $value): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $value);
        if (! $digits) {
            return null;
        }
        if (str_starts_with($digits, '255') && strlen($digits) >= 12) {
            return substr($digits, -9);
        }
        if (str_starts_with($digits, '0') && strlen($digits) >= 10) {
            return substr($digits, -9);
        }

        return substr($digits, -9);
    }

    private function phonesMatch(string $a, string $b): bool
    {
        return $a === $b;
    }
}
