<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\LoanApplication;
use App\Support\NationalIdDob;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

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
        $application->loadMissing('documentRequests');

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

        // Follow-up requests — only asks for the person on this desk.
        $docRequests = app(ApplicationDocumentRequestService::class);
        $openForSubject = collect($application->documentRequests ?? [])
            ->whereIn('status', ['pending', 'rejected'])
            ->filter(fn ($req) => $docRequests->targetsReviewSubject(
                $req,
                (string) ($context['subject_person'] ?? $subjectKind),
                $customer instanceof Customer ? $customer->id : null,
                isset($context['subject_member_id']) ? (int) $context['subject_member_id'] : null,
                $application->customer_id,
            ))
            ->count();
        if ($openForSubject > 0) {
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

        // Gate 2: pre-save chips only (missing files / no declared income). Save-time totals decide pass/fail.
        $out['activity_income.income_evidence'] = $this->statementsVsDeclaredIncome($context);
        $out['activity_income.bank_or_mobile_money'] = $this->statementsPresent($context);

        // Credit wrap-up CRB — auto-Fail delinquencies / bureau Reject; Pass is always human.
        $crbWrap = $this->crbWrap($crb);
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
                'collateral.valuation_or_photos',
                'collateral.valuation_fee',
                'collateral.valuation_report',
                'collateral.ltv_covers',
                'collateral.gps_or_location',
            ] as $key) {
                $out[$key] = ['verdict' => 'na', 'source' => 'auto_na'];
            }
        } else {
            $out['collateral.asset_identity'] = $this->assetIdentity($context, $application);
            $out['collateral.insurance_type'] = $this->insuranceType($context, $application);
            $out['collateral.insurance_cover'] = $this->insuranceCover($context, $application);
            $out['collateral.valuation_or_photos'] = $this->valuationPhotos($context, $application);
            $out['collateral.valuation_fee'] = $this->valuationFee($context, $application);
            $out['collateral.valuation_report'] = $this->valuationReport($context, $application);
            $out['collateral.ltv_covers'] = $this->ltvCovers($context, $application);
            $out['collateral.gps_or_location'] = $this->gpsOrLocation($context, $application);
        }

        return $out;
    }

    /** @return array{verdict: string, fail_reason_code?: string|null, source: string} */
    private function nidaVsDob(?Customer $customer): array
    {
        $cmp = NationalIdDob::matchesBorrower($customer?->national_id, $customer?->date_of_birth);
        $derived = $cmp['derived'] ?? [];
        if (! ($derived['ok'] ?? false)) {
            $code = match ((string) ($derived['reason'] ?? 'unverifiable')) {
                'missing' => 'nida_missing',
                'malformed' => 'nida_malformed',
                'impossible' => 'nida_impossible',
                default => 'nida_unverifiable',
            };

            return ['verdict' => 'fail', 'fail_reason_code' => $code, 'source' => 'system'];
        }
        if (! ($cmp['borrower'] instanceof Carbon)) {
            return ['verdict' => 'fail', 'fail_reason_code' => 'nida_incomplete', 'source' => 'system'];
        }
        if ($cmp['match'] ?? false) {
            return ['verdict' => 'pass', 'source' => 'system'];
        }

        return ['verdict' => 'fail', 'fail_reason_code' => 'nida_dob_mismatch', 'source' => 'system'];
    }

    /** @param  array<string, mixed>  $crb */
    private function nameVsCrb(?Customer $customer, array $crb): array
    {
        $profile = $this->norm((string) ($customer?->full_name ?? ''));
        if ($profile === '') {
            return ['verdict' => 'fail', 'fail_reason_code' => 'profile_name_missing', 'source' => 'system'];
        }

        $personal = (array) ($crb['personal'] ?? []);
        $crbName = trim((string) ($personal['full_name'] ?? data_get($crb, 'identity.full_name') ?: ''));
        $performed = $this->crbWasPerformed($crb);

        if (! $performed) {
            return ['verdict' => 'fail', 'fail_reason_code' => 'crb_never_checked', 'source' => 'system'];
        }
        if ($crbName === '' && $this->crbLooksLikeNoRecord($crb)) {
            return ['verdict' => 'fail', 'fail_reason_code' => 'crb_no_record', 'source' => 'system'];
        }
        if ($crbName === '') {
            return ['verdict' => 'fail', 'fail_reason_code' => 'crb_name_unusable', 'source' => 'system'];
        }

        $bureau = $this->norm($crbName);
        if ($bureau === '') {
            return ['verdict' => 'fail', 'fail_reason_code' => 'crb_name_unusable', 'source' => 'system'];
        }
        if ($profile === $bureau || str_contains($bureau, $profile) || str_contains($profile, $bureau)) {
            return ['verdict' => 'pass', 'source' => 'system'];
        }

        return ['verdict' => 'fail', 'fail_reason_code' => 'name_mismatch', 'source' => 'system'];
    }

    /** @param  array<string, mixed>  $crb */
    private function crbWasPerformed(array $crb): bool
    {
        if (isset($crb['score']) && is_numeric($crb['score'])) {
            return true;
        }
        if (filled(data_get($crb, 'personal.full_name')) || filled(data_get($crb, 'identity.full_name'))) {
            return true;
        }
        if (is_array($crb['loan_history'] ?? null) && $crb['loan_history'] !== []) {
            return true;
        }
        $rec = strtolower(trim((string) ($crb['recommendation'] ?? $crb['status'] ?? '')));
        if (in_array($rec, ['approve', 'refer', 'reject', 'checked'], true)) {
            return true;
        }
        if (in_array($rec, ['', 'not checked', 'pending', 'skipped'], true)) {
            return false;
        }

        return filled($rec) && ! str_contains($rec, 'not check');
    }

    /** @param  array<string, mixed>  $crb */
    private function crbLooksLikeNoRecord(array $crb): bool
    {
        $error = strtolower((string) ($crb['error'] ?? $crb['status'] ?? ''));

        return str_contains($error, 'no record')
            || str_contains($error, 'not found')
            || str_contains($error, 'no hit');
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
        $familyGap = false;
        if ($profileMarital !== '' && $crbMarital !== '' && ! str_contains($crbMarital, $profileMarital) && ! str_contains($profileMarital, $crbMarital)) {
            $familyGap = true;
        }

        $spouseProfile = $this->norm(trim(implode(' ', array_filter([
            $customer?->spouse_first_name,
            $customer?->spouse_middle_name,
            $customer?->spouse_last_name,
        ]))));
        $spouseCrb = $this->norm(collect($personal['spouses'] ?? [])->pluck('name')->filter()->implode(' '));
        if ($spouseProfile !== '' && $spouseCrb === '') {
            $familyGap = true;
        } elseif ($spouseProfile !== '' && $spouseCrb !== '' && $spouseProfile !== $spouseCrb && ! str_contains($spouseCrb, $spouseProfile)) {
            $familyGap = true;
        }

        if ($customer?->number_of_children !== null && array_key_exists('number_of_children', $personal) && $personal['number_of_children'] !== null) {
            if ((int) $customer->number_of_children !== (int) $personal['number_of_children']) {
                $familyGap = true;
            }
        }

        // Family fields on CIR are often stale. Do not auto-Fail — analyst reviews or waives.
        if ($familyGap) {
            return ['verdict' => '', 'source' => 'system_skip'];
        }

        return ['verdict' => 'pass', 'source' => 'system'];
    }

    /** @param  array<string, mixed>  $ctx */
    private function facePhotosPresent(array $ctx): array
    {
        $photos = $ctx['face_photos'] ?? [];
        if ($photos instanceof Collection) {
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

        if ($count < 1 && ! $hasNida) {
            return ['verdict' => 'fail', 'fail_reason_code' => 'photos_missing', 'source' => 'system'];
        }
        if ($count < 1) {
            return ['verdict' => 'fail', 'fail_reason_code' => 'face_photo_missing', 'source' => 'system'];
        }
        if (! $hasNida) {
            return ['verdict' => 'fail', 'fail_reason_code' => 'id_photo_missing', 'source' => 'system'];
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
     * Gate 2 — after capacity auto-reject. Pre-save chips flag missing statements
     * or undeclared income; matching revenue is decided at save from keyed totals.
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
     */
    private function crbWrap(array $crb): array
    {
        $rec = strtolower((string) ($crb['recommendation'] ?? ''));
        $delinq = (int) ($crb['delinquencies'] ?? 0);
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

        // A clean bureau score is not a visual check — screening must open the CRB report and Pass/Fail.
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
    private function assetIdentity(array $ctx, LoanApplication $application): array
    {
        $pledged = collect($ctx['pledged_assets'] ?? []);
        if ($pledged->isEmpty()) {
            return $this->awaitingData(
                $application,
                'There is no data for this checklist',
                'Open collateral',
            );
        }

        return ['verdict' => '', 'source' => 'system_skip'];
    }

    /** @param  array<string, mixed>  $ctx */
    private function insuranceType(array $ctx, LoanApplication $application): array
    {
        $pledged = collect($ctx['pledged_assets'] ?? []);
        $vehicles = $pledged->filter(fn ($row) => strtolower((string) ($row['asset_type'] ?? '')) === 'vehicle');
        if ($pledged->isNotEmpty() && $vehicles->isEmpty()) {
            return ['verdict' => 'na', 'source' => 'system'];
        }
        if ($vehicles->isEmpty()) {
            $first = (array) $pledged->first();
            $assetType = strtolower((string) ($first['asset_type'] ?? ''));
            if ($assetType !== '' && $assetType !== 'vehicle') {
                return ['verdict' => 'na', 'source' => 'system'];
            }
        }
        $missing = $vehicles->first(function ($row) {
            $type = (string) ($row['insurance_type'] ?? '');

            return ! filled($type) || $type === '—';
        });
        if ($vehicles->isEmpty() || $missing) {
            return $this->awaitingData(
                $application,
                'There is no data for this checklist',
                'Open collateral',
            );
        }

        return ['verdict' => 'pass', 'source' => 'system'];
    }

    /** @param  array<string, mixed>  $ctx */
    private function insuranceCover(array $ctx, LoanApplication $application): array
    {
        $pledged = collect($ctx['pledged_assets'] ?? []);
        $vehicles = $pledged->filter(fn ($row) => strtolower((string) ($row['asset_type'] ?? '')) === 'vehicle');
        if ($pledged->isNotEmpty() && $vehicles->isEmpty()) {
            return ['verdict' => 'na', 'source' => 'system'];
        }
        if ($vehicles->isEmpty()) {
            return $this->awaitingData(
                $application,
                'There is no data for this checklist',
                'Open collateral',
            );
        }

        foreach ($vehicles as $row) {
            $expiry = $row['insurance_expiry'] ?? null;
            $type = $row['insurance_type'] ?? null;
            $hasDoc = ! empty($row['has_insurance_doc']);
            if ((! filled($type) || $type === '—') && ! $hasDoc && (! filled($expiry) || $expiry === '—')) {
                return $this->awaitingData(
                    $application,
                    'There is no data for this checklist',
                    'Open collateral',
                );
            }
            if (filled($expiry) && $expiry !== '—') {
                try {
                    if (Carbon::parse((string) $expiry)->isPast()) {
                        return ['verdict' => 'fail', 'fail_reason_code' => 'expired', 'source' => 'system'];
                    }
                } catch (\Throwable) {
                    return $this->awaitingData(
                        $application,
                        'There is no data for this checklist',
                        'Open collateral',
                    );
                }
            }
            if (! $hasDoc && (! filled($type) || $type === '—')) {
                return $this->awaitingData(
                    $application,
                    'There is no data for this checklist',
                    'Open collateral',
                );
            }
        }

        return ['verdict' => 'pass', 'source' => 'system'];
    }

    /** @param  array<string, mixed>  $ctx */
    private function valuationPhotos(array $ctx, LoanApplication $application): array
    {
        $pairs = (array) ($ctx['photo_pairs'] ?? []);
        if ($pairs === []) {
            $pairs = $this->photoPairsFromContext($ctx);
        }
        $hasSomethingToLookAt = collect($pairs)->contains(
            fn ($row) => filled(data_get($row, 'borrower.url')) || filled(data_get($row, 'valuer.url'))
        );
        if (! $hasSomethingToLookAt) {
            return $this->awaitingData(
                $application,
                'There is no data for this checklist',
                'Open collateral',
            );
        }

        // Files existing is not a match. Screening looks at the pairs (and extra valuer shots) and Pass / Fail.
        return ['verdict' => '', 'source' => 'system_skip'];
    }

    /** @param  array<string, mixed>  $ctx */
    private function valuationFee(array $ctx, LoanApplication $application): array
    {
        $valuer = (array) ($ctx['valuer'] ?? []);
        $cs = (array) ($ctx['collateral_secure'] ?? []);
        if (! empty($valuer['fee_paid']) || filled($cs['valuation_fee_paid_at'] ?? null)) {
            return ['verdict' => 'pass', 'source' => 'system'];
        }
        $status = (string) ($cs['status'] ?? '');
        if ($status === CollateralSecureService::STATUS_AWAITING_VALUATION_FEE) {
            return ['verdict' => 'fail', 'fail_reason_code' => 'fee_unpaid', 'source' => 'system'];
        }

        return $this->awaitingData(
            $application,
            'There is no data for this checklist',
            'Request valuation',
            'checklist',
        );
    }

    /** @param  array<string, mixed>  $ctx */
    private function valuationReport(array $ctx, LoanApplication $application): array
    {
        $valuer = (array) ($ctx['valuer'] ?? []);
        $fsv = (string) ($valuer['fsv'] ?? '');
        if (filled($fsv) && $fsv !== '—') {
            return ['verdict' => 'pass', 'source' => 'system'];
        }

        return $this->awaitingData(
            $application,
            'There is no data for this checklist',
            'Open collateral',
        );
    }

    /** @param  array<string, mixed>  $ctx */
    private function ltvCovers(array $ctx, LoanApplication $application): array
    {
        $coverage = $ctx['coverage'] ?? app(CollateralCoverageService::class)->forApplication($application);
        $valuer = (array) ($ctx['valuer'] ?? []);
        $fsv = (string) ($valuer['fsv'] ?? '');
        if (! is_array($coverage) || $coverage === [] || ! filled($fsv) || $fsv === '—') {
            return $this->awaitingData(
                $application,
                'There is no data for this checklist',
                'Open collateral',
            );
        }
        if (! empty($coverage['sufficient'])) {
            return ['verdict' => 'pass', 'source' => 'system'];
        }

        return ['verdict' => 'fail', 'fail_reason_code' => 'ltv_shortfall', 'source' => 'system'];
    }

    /** @param  array<string, mixed>  $ctx */
    private function gpsOrLocation(array $ctx, LoanApplication $application): array
    {
        $gps = (array) ($ctx['gps'] ?? []);
        if ($gps === []) {
            $gps = $this->gpsSummary($application);
        }
        if (empty($gps['required'])) {
            return ['verdict' => 'na', 'source' => 'system'];
        }
        if (! empty($gps['secured'])) {
            return ['verdict' => 'pass', 'source' => 'system'];
        }

        return $this->awaitingData(
            $application,
            'There is no data for this checklist',
            'Open collateral',
        );
    }

    /**
     * @param  array<string, mixed>  $ctx
     * @return list<array<string, mixed>>
     */
    private function photoPairsFromContext(array $ctx): array
    {
        return collect($ctx['pledged_assets'] ?? [])
            ->flatMap(fn ($asset) => $asset['photo_pairs'] ?? [])
            ->values()
            ->all();
    }

    /** @return array{required: bool, secured: bool, status: string} */
    private function gpsSummary(LoanApplication $application): array
    {
        $items = app(GpsDeviceService::class)->forApplication($application);
        $required = collect($items)->contains(fn ($row) => ! empty($row['gps_required']));
        $secured = collect($items)->contains(fn ($row) => ($row['gps_status'] ?? '') === 'secured');

        return [
            'required' => $required,
            'secured' => $secured,
            'status' => $secured ? 'secured' : ($required ? 'required' : 'not_required'),
        ];
    }

    /**
     * @return array{verdict: string, source: string, message: string, cta: array{label: string, href: string}}
     */
    private function awaitingData(
        LoanApplication $application,
        string $message = 'There is no data for this checklist',
        string $ctaLabel = 'Open collateral',
        string $workspace = 'profiles',
    ): array {
        $href = route('admin.loan-applications.show', array_filter([
            'loan_application' => $application,
            'workspace' => $workspace === 'checklist' ? 'checklist' : 'profiles',
            'tab' => $workspace === 'checklist' ? null : 'collateral',
        ]));

        return [
            'verdict' => '',
            'source' => 'awaiting_data',
            'message' => $message,
            'cta' => [
                'label' => $ctaLabel,
                'href' => $href,
            ],
        ];
    }

    private function norm(string $value): string
    {
        return strtolower(trim(preg_replace('/\s+/', ' ', $value) ?? ''));
    }
}
