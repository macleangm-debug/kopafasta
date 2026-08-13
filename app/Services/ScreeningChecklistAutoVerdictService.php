<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\LoanApplication;
use Illuminate\Support\Carbon;

/**
 * Deterministic Pass / Fail / N/A for screening checklist items the platform can decide.
 * Human overrides (source != system|auto_na) are preserved.
 */
class ScreeningChecklistAutoVerdictService
{
    /**
     * @param  array<string, mixed>  $context  from ScreeningChecklistService::evidenceContext
     * @return array<string, array{verdict: string, fail_reason_code?: string|null, source: string}>
     */
    public function suggest(LoanApplication $application, string $subjectKind, array $context): array
    {
        $out = [];
        $customer = $context['customer'] ?? null;
        $crb = (array) ($context['crb'] ?? []);
        $docs = (array) ($context['documents'] ?? []);
        $afford = (array) ($context['affordability'] ?? []);
        $collateralApplies = app(ScreeningChecklistService::class)->collateralReviewApplies($application);

        // Identity — NIDA vs DOB
        $out['identity.nida_vs_dob'] = $this->nidaVsDob($customer);

        // Identity — name vs CRB
        $out['identity.name_vs_crb'] = $this->nameVsCrb($customer, $crb);

        // Identity — marital / family vs CRB
        $out['identity.marital_vs_crb'] = $this->maritalVsCrb($customer, $crb);

        // Identity — face photos presence (likeness stays human if photos exist)
        $out['identity.face_vs_nida'] = $this->facePhotosPresent($context);

        // Residence — completeness including LGO signatory phone
        $out['residence.address_consistency'] = $this->residenceComplete($customer);

        // Document-backed checklist items (ID quality, residence proof, authenticity)
        // — driven by application Documents reviews so screeners do not re-check files.
        $customerForDocs = $customer instanceof Customer ? $customer : null;
        $bridge = app(ChecklistDocumentBridge::class);
        foreach (config('checklist_document_bridge.auto_from_documents', []) as $fullKey) {
            $docVerdict = $bridge->autoVerdict($application, $customerForDocs, (string) $fullKey);
            if ($docVerdict !== null) {
                $out[(string) $fullKey] = $docVerdict;
            }
        }

        // Documents completeness
        $out['documents.required_docs_complete'] = $this->requiredDocs($docs);

        // Follow-up requests
        $openRequests = $application->documentRequests
            ?->whereIn('status', ['pending', 'rejected'])
            ->count() ?? 0;
        if ($openRequests > 0) {
            $out['documents.requested_docs_reviewed'] = [
                'verdict' => 'fail',
                'fail_reason_code' => 'still_open',
                'source' => 'system',
            ];
        } else {
            $out['documents.requested_docs_reviewed'] = [
                'verdict' => 'pass',
                'source' => 'system',
            ];
        }

        // Gate 2: statements vs declared monthly revenue (human). Capacity auto-reject is Gate 1.
        $out['activity_income.income_evidence'] = $this->statementsVsDeclaredIncome($context);
        $out['activity_income.bank_or_mobile_money'] = $this->statementsPresent($context);

        // Credit wrap-up CRB (borrower / guarantor / member variants) — includes capacity vs external debt
        $crbWrap = $this->crbWrap($crb, $afford);
        $out['credit_file.crb_reviewed'] = $crbWrap;
        $out['guarantor_wrap.crb_reviewed'] = $crbWrap;
        $out['member_wrap.crb_reviewed'] = $crbWrap;

        $out['credit_file.risk_flags_addressed'] = $this->riskFlagsAddressed($context);
        $out['credit_file.recommendation_ready'] = ['verdict' => '', 'source' => 'system_skip'];

        // Collateral — handled elsewhere as auto_na when not applicable
        if (! $collateralApplies) {
            foreach ([
                'collateral.asset_identity',
                'collateral.insurance_type',
                'collateral.insurance_cover',
                'collateral.ownership_docs',
                'collateral.valuation_or_photos',
                'collateral.gps_or_location',
            ] as $key) {
                $out[$key] = ['verdict' => 'na', 'source' => 'auto_na'];
            }
        } else {
            $out['collateral.insurance_cover'] = $this->insuranceCover($context);
        }

        return $out;
    }

    /** @return array{verdict: string, fail_reason_code?: string|null, source: string} */
    private function nidaVsDob(?Customer $customer): array
    {
        $nida = preg_replace('/\D+/', '', (string) ($customer?->national_id ?? '')) ?: '';
        $dob = $customer?->date_of_birth;
        if (strlen($nida) < 8 || ! $dob instanceof Carbon) {
            return ['verdict' => 'fail', 'fail_reason_code' => 'nida_incomplete', 'source' => 'system'];
        }

        $fromNida = substr($nida, 0, 8);
        $fromDob = $dob->format('Ymd');
        if ($fromNida === $fromDob) {
            return ['verdict' => 'pass', 'source' => 'system'];
        }

        return ['verdict' => 'fail', 'fail_reason_code' => 'nida_dob_mismatch', 'source' => 'system'];
    }

    /** @param  array<string, mixed>  $crb */
    private function nameVsCrb(?Customer $customer, array $crb): array
    {
        $personal = (array) ($crb['personal'] ?? []);
        $crbName = trim((string) ($personal['full_name'] ?? data_get($crb, 'identity.full_name') ?: ''));
        if ($crbName === '' && empty($crb['score']) && empty($crb['recommendation'])) {
            return ['verdict' => 'fail', 'fail_reason_code' => 'crb_missing', 'source' => 'system'];
        }
        if ($crbName === '') {
            return ['verdict' => 'fail', 'fail_reason_code' => 'crb_missing', 'source' => 'system'];
        }

        $profile = $this->norm((string) ($customer?->full_name ?? ''));
        $bureau = $this->norm($crbName);
        if ($profile === '' || $bureau === '') {
            return ['verdict' => 'fail', 'fail_reason_code' => 'name_mismatch', 'source' => 'system'];
        }
        if ($profile === $bureau || str_contains($bureau, $profile) || str_contains($profile, $bureau)) {
            return ['verdict' => 'pass', 'source' => 'system'];
        }

        return ['verdict' => 'fail', 'fail_reason_code' => 'name_mismatch', 'source' => 'system'];
    }

    /** @param  array<string, mixed>  $crb */
    private function maritalVsCrb(?Customer $customer, array $crb): array
    {
        $personal = (array) ($crb['personal'] ?? []);
        if ($personal === [] && empty($crb['score'])) {
            return ['verdict' => '', 'source' => 'system_skip'];
        }
        if ($personal === []) {
            return ['verdict' => 'na', 'source' => 'system'];
        }

        $profileMarital = strtolower((string) ($customer?->marital_status ?? ''));
        $crbMarital = strtolower(trim((string) ($personal['marital_status'] ?? '')));
        if ($profileMarital !== '' && $crbMarital !== '' && ! str_contains($crbMarital, $profileMarital) && ! str_contains($profileMarital, $crbMarital)) {
            return ['verdict' => 'fail', 'fail_reason_code' => 'marital_mismatch', 'source' => 'system'];
        }

        $spouseProfile = $this->norm(trim(implode(' ', array_filter([
            $customer?->spouse_first_name,
            $customer?->spouse_middle_name,
            $customer?->spouse_last_name,
        ]))));
        $spouseCrb = $this->norm(collect($personal['spouses'] ?? [])->pluck('name')->filter()->implode(' '));
        if ($spouseProfile !== '' && $spouseCrb !== '' && $spouseProfile !== $spouseCrb && ! str_contains($spouseCrb, $spouseProfile)) {
            return ['verdict' => 'fail', 'fail_reason_code' => 'spouse_mismatch', 'source' => 'system'];
        }

        if ($customer?->number_of_children !== null && array_key_exists('number_of_children', $personal) && $personal['number_of_children'] !== null) {
            if ((int) $customer->number_of_children !== (int) $personal['number_of_children']) {
                return ['verdict' => 'fail', 'fail_reason_code' => 'children_mismatch', 'source' => 'system'];
            }
        }

        return ['verdict' => 'pass', 'source' => 'system'];
    }

    /** @param  array<string, mixed>  $ctx */
    private function facePhotosPresent(array $ctx): array
    {
        $photos = $ctx['face_photos'] ?? [];
        if ($photos instanceof \Illuminate\Support\Collection) {
            $photos = $photos->all();
        }
        $count = 0;
        foreach ((array) $photos as $entry) {
            $path = is_object($entry) ? ($entry->file_path ?? null) : (is_array($entry) ? ($entry['file_path'] ?? $entry['path'] ?? null) : $entry);
            if (is_string($path) && filled($path)) {
                $count++;
            }
        }
        $nida = $ctx['nida_photo_path'] ?? null;
        if (is_object($nida)) {
            $nida = $nida->file_path ?? null;
        } elseif (is_array($nida)) {
            $nida = $nida['file_path'] ?? $nida['path'] ?? null;
        }
        $hasNida = is_string($nida) && filled($nida);
        if (! $hasNida) {
            foreach ((array) data_get($ctx, 'documents.id_files', []) as $file) {
                if (! empty($file['url']) || ! empty($file['file_path'] ?? null)) {
                    $hasNida = true;
                    break;
                }
            }
        }

        if ($count < 1 || ! $hasNida) {
            return ['verdict' => 'fail', 'fail_reason_code' => 'photos_missing', 'source' => 'system'];
        }

        // Photos present — likeness comparison stays for screening (no auto pass).
        return ['verdict' => '', 'source' => 'system_skip'];
    }

    private function residenceComplete(?Customer $customer): array
    {
        $region = filled($customer?->region);
        $district = filled($customer?->district);
        $street = filled($customer?->street ?: $customer?->address);
        $officerPhone = filled($customer?->lga_officer_phone);
        $officerName = filled($customer?->lga_officer_name);

        if (! $region || ! $district || ! $street) {
            return ['verdict' => 'fail', 'fail_reason_code' => 'incomplete', 'source' => 'system'];
        }

        // Signatory (LGO) phone + name required for residence letter verification path.
        if (! $officerPhone || ! $officerName) {
            return ['verdict' => 'fail', 'fail_reason_code' => 'incomplete', 'source' => 'system'];
        }

        return ['verdict' => 'pass', 'source' => 'system'];
    }

    /** @param  array<string, mixed>  $docs */
    private function requiredDocs(array $docs): array
    {
        $required = (int) ($docs['required'] ?? 0);
        $satisfied = (int) ($docs['satisfied'] ?? 0);
        if ($required < 1) {
            return ['verdict' => 'pass', 'source' => 'system'];
        }
        if ($satisfied >= $required) {
            return ['verdict' => 'pass', 'source' => 'system'];
        }

        return ['verdict' => 'fail', 'fail_reason_code' => 'docs_missing', 'source' => 'system'];
    }

    /** @param  array<string, mixed>  $afford */
    /**
     * Gate 2 — after capacity auto-reject. System only flags missing statements;
     * matching declared monthly revenue stays a human Pass/Fail.
     *
     * @param  array<string, mixed>  $ctx
     * @return array{verdict: string, fail_reason_code?: string|null, source: string}
     */
    private function statementsVsDeclaredIncome(array $ctx): array
    {
        $statements = (array) ($ctx['income_statements'] ?? []);
        $hasFiles = collect($statements)->contains(fn ($row) => filled($row['url'] ?? null) || filled($row['file_path'] ?? null));
        $declared = (float) ($ctx['declared_monthly_income'] ?? 0);
        $hasDeclared = $declared > 0 || filled($ctx['declared_income_label'] ?? null);

        if (! $hasFiles) {
            return ['verdict' => 'fail', 'fail_reason_code' => 'statements_missing', 'source' => 'system'];
        }
        if (! $hasDeclared) {
            return ['verdict' => 'fail', 'fail_reason_code' => 'income_insufficient', 'source' => 'system'];
        }

        return ['verdict' => '', 'source' => 'system_skip'];
    }

    /**
     * @param  array<string, mixed>  $ctx
     * @return array{verdict: string, fail_reason_code?: string|null, source: string}
     */
    private function statementsPresent(array $ctx): array
    {
        $statements = (array) ($ctx['income_statements'] ?? []);
        $hasFiles = collect($statements)->contains(fn ($row) => filled($row['url'] ?? null) || filled($row['file_path'] ?? null));
        if (! $hasFiles) {
            return ['verdict' => 'fail', 'fail_reason_code' => 'statements_missing', 'source' => 'system'];
        }

        // Patterns / anomalies after the revenue match — human judgment.
        return ['verdict' => '', 'source' => 'system_skip'];
    }

    private function affordability(array $afford): array
    {
        $verdict = strtolower((string) ($afford['verdict'] ?? ''));
        if ($verdict === '' && array_key_exists('pass', $afford)) {
            $verdict = ($afford['pass'] ?? false) ? 'pass' : 'fail';
        }
        if ($verdict === 'fail') {
            return ['verdict' => 'fail', 'fail_reason_code' => 'affordance_fail', 'source' => 'system'];
        }
        if ($verdict === 'warn') {
            return ['verdict' => 'fail', 'fail_reason_code' => 'income_insufficient', 'source' => 'system'];
        }
        if ($verdict === 'pass') {
            return ['verdict' => 'pass', 'source' => 'system'];
        }

        return ['verdict' => '', 'source' => 'system_skip'];
    }

    /**
     * @param  array<string, mixed>  $crb
     * @param  array<string, mixed>  $afford
     */
    private function crbWrap(array $crb, array $afford = []): array
    {
        $rec = strtolower((string) ($crb['recommendation'] ?? ''));
        $delinq = (int) ($crb['delinquencies'] ?? 0);
        $affVerdict = strtolower((string) ($afford['verdict'] ?? ''));
        if ($affVerdict === '' && array_key_exists('pass', $afford)) {
            $affVerdict = ($afford['pass'] ?? false) ? 'pass' : 'fail';
        }
        $externalLoans = (int) ($crb['existing_loans'] ?? 0);
        $crbOut = (float) ($crb['outstanding_balance'] ?? 0);

        if ($rec === '' && ($crb['score'] ?? null) === null && $externalLoans < 1 && $crbOut <= 0) {
            return ['verdict' => '', 'source' => 'system_skip'];
        }
        if ($delinq > 0) {
            return ['verdict' => 'fail', 'fail_reason_code' => 'delinquencies', 'source' => 'system'];
        }
        if ($rec === 'reject') {
            return ['verdict' => 'fail', 'fail_reason_code' => 'high_exposure', 'source' => 'system'];
        }
        // Cannot carry this loan given capacity — especially dangerous with other-institution debt.
        if ($affVerdict === 'fail' && ($externalLoans > 0 || $crbOut > 0 || $rec === 'refer')) {
            return ['verdict' => 'fail', 'fail_reason_code' => 'cannot_repay_with_external', 'source' => 'system'];
        }
        if ($affVerdict === 'fail') {
            return ['verdict' => 'fail', 'fail_reason_code' => 'cannot_repay_with_external', 'source' => 'system'];
        }
        if (in_array($rec, ['approve', 'refer'], true) || ($crb['score'] ?? null) !== null) {
            // Refer or external debt still needs human eyes — do not auto-pass.
            if ($rec === 'refer' || $externalLoans > 0 || $crbOut > 0) {
                return ['verdict' => '', 'source' => 'system_skip'];
            }

            return ['verdict' => 'pass', 'source' => 'system'];
        }

        return ['verdict' => '', 'source' => 'system_skip'];
    }

    /** @param  array<string, mixed>  $ctx */
    private function riskFlagsAddressed(array $ctx): array
    {
        $flags = collect($ctx['anomalies'] ?? []);
        $critical = $flags->where('severity', 'critical')->count();
        $warning = $flags->where('severity', 'warning')->count();
        if ($critical > 0) {
            // Human must explicitly Pass (with notes) or Fail — never auto-pass critical flags.
            return ['verdict' => '', 'source' => 'system_skip'];
        }
        if ($warning > 0) {
            return ['verdict' => '', 'source' => 'system_skip'];
        }

        return ['verdict' => 'pass', 'source' => 'system'];
    }

    /** @param  array<string, mixed>  $ctx */
    private function insuranceCover(array $ctx): array
    {
        $cs = (array) ($ctx['collateral_secure'] ?? []);
        $expiry = data_get($cs, 'insurance.expiry');
        $type = data_get($cs, 'insurance.insurance_type');
        if (! filled($type)) {
            return ['verdict' => 'fail', 'fail_reason_code' => 'missing', 'source' => 'system'];
        }
        if (filled($expiry)) {
            try {
                if (Carbon::parse((string) $expiry)->isPast()) {
                    return ['verdict' => 'fail', 'fail_reason_code' => 'expired', 'source' => 'system'];
                }
            } catch (\Throwable) {
                // leave for human
                return ['verdict' => '', 'source' => 'system_skip'];
            }
        }

        return ['verdict' => 'pass', 'source' => 'system'];
    }

    private function norm(string $value): string
    {
        return strtolower(trim(preg_replace('/\s+/', ' ', $value) ?? ''));
    }
}
