<?php

namespace App\Services;

use App\Models\Partner;
use App\Models\PartnerAgreementAcceptance;
use App\Models\Setting;
use App\Models\Vendor;
use Illuminate\Http\Request;

class PartnerTermsService
{
    /** @return list<string> */
    public function governedTypes(): array
    {
        return app(PartnerEfficiencyPolicy::class)->governanceCategories();
    }

    public function appliesTo(Partner|Vendor $partner): bool
    {
        return in_array((string) $partner->category, $this->governedTypes(), true);
    }

    public function agreementKey(string $type): string
    {
        return $type.'_terms';
    }

    public function typeFor(Partner|Vendor $partner): string
    {
        return (string) $partner->category;
    }

    /** @return array<string, mixed> */
    public function settings(): array
    {
        $defaults = config('partners.terms', []);
        $stored = Setting::get('partners.terms');

        if (! is_array($stored)) {
            return $defaults;
        }

        $merged = array_merge($defaults, $stored);
        $merged['types'] = array_replace_recursive($defaults['types'] ?? [], $stored['types'] ?? []);

        return $merged;
    }

    public function requireBeforeJobs(): bool
    {
        return (bool) ($this->settings()['require_before_jobs'] ?? true);
    }

    public function materialChangeRequiresReacceptance(): bool
    {
        return (bool) ($this->settings()['material_change_requires_reacceptance'] ?? false);
    }

    public function policyVersion(): int
    {
        return max(1, (int) ($this->settings()['policy_version'] ?? 1));
    }

    public function conductVersion(): string
    {
        return (string) ($this->settings()['conduct_version'] ?? '2026.09');
    }

    public function launchedAt(): ?string
    {
        $value = $this->settings()['launched_at'] ?? Setting::get('partners.terms.launched_at');

        return filled($value) ? (string) $value : null;
    }

    public function agreementVersion(string $type): int
    {
        $types = $this->settings()['types'] ?? [];

        return max(1, (int) ($types[$type]['version'] ?? 1));
    }

    /**
     * @return array<string, string>
     */
    public function variables(string $type, ?Partner $partner = null, ?string $locale = null): array
    {
        $locale = $locale ?: app()->getLocale();
        $efficiency = app(PartnerEfficiencyPolicy::class);
        $membership = app(PartnerMembershipService::class);
        $assign = app(PartnerAutoAssignPolicy::class);
        $recovery = app(RecoveryPolicyService::class);

        $serviceCategory = in_array($type, ['valuer', 'gps_installer', 'insurance'], true) ? $type : null;
        $recoveryType = match ($type) {
            'gps_installer' => 'gps_partner',
            'call_center', 'debt_collector', 'auctioneer', 'legal_partner' => $type,
            default => null,
        };

        $slaDays = $serviceCategory ? $assign->slaDaysForService($serviceCategory) : ($recoveryType ? $recovery->slaDaysForType($recoveryType) : null);
        $slaHours = $serviceCategory ? $assign->slaHoursForService($serviceCategory) : null;
        $remindHours = $serviceCategory ? implode(', ', $assign->remindHoursForService($serviceCategory)) : '';
        $graceHours = $serviceCategory ? $assign->graceHoursForService($serviceCategory) : null;
        $maxReassign = $serviceCategory ? $assign->maxReassignmentsForService($serviceCategory) : null;
        $recoverySla = $recoveryType ? $recovery->slaDaysForType($recoveryType) : null;
        $recoveryRemind = $recoveryType ? implode(', ', $recovery->remindDaysForType($recoveryType)) : '';

        $membershipRequired = $partner
            ? ($membership->requiresPayment($partner) ? __('partner_terms.yes', [], $locale) : __('partner_terms.no', [], $locale))
            : ((bool) (config('partners.membership.categories_requiring_payment.'.$type) ?? false)
                ? __('partner_terms.yes', [], $locale)
                : __('partner_terms.no', [], $locale));

        $feeIndividual = format_money((float) (config('partners.membership.category_fees.valuer.individual') ?? 0));
        $feeCompany = format_money((float) (config('partners.membership.category_fees.valuer.company') ?? 0));
        if ($partner && $membership->requiresPayment($partner)) {
            $feeIndividual = format_money($membership->feeFor($partner));
            $feeCompany = $feeIndividual;
        }

        return [
            'brand' => brand_name(),
            'partner_type' => __('partner_terms.types.'.$type, [], $locale),
            'sla_days' => (string) ($slaDays ?? '—'),
            'sla_hours' => (string) ($slaHours ?? '—'),
            'remind_hours' => $remindHours !== '' ? $remindHours : '—',
            'grace_hours' => (string) ($graceHours ?? '—'),
            'max_reassignments' => (string) ($maxReassign ?? '—'),
            'recovery_sla_days' => (string) ($recoverySla ?? '—'),
            'recovery_remind_days' => $recoveryRemind !== '' ? $recoveryRemind : '—',
            'sla_starts' => __('partner_terms.sla_starts_assignment', [], $locale),
            'target_on_time' => (string) $efficiency->targetOnTimePercent(),
            'target_completion' => (string) $efficiency->targetCompletionPercent(),
            'min_jobs_for_score' => (string) $efficiency->minJobsForScore(),
            'warnings_before_suspend' => (string) $efficiency->warningsBeforeSuspend(),
            'auto_recover' => $efficiency->autoRecover() ? __('partner_terms.yes', [], $locale) : __('partner_terms.no', [], $locale),
            'membership_required' => $membershipRequired,
            'membership_fee_individual' => $feeIndividual,
            'membership_fee_company' => $feeCompany,
            'policy_version' => (string) $this->policyVersion(),
            'agreement_version' => (string) $this->agreementVersion($type),
            'conduct_version' => $this->conductVersion(),
        ];
    }

    public function template(string $type, ?string $locale = null): string
    {
        $locale = $locale ?: app()->getLocale();
        $stored = Setting::get('partners.terms.body.'.$type.'.'.$locale);
        if (filled($stored)) {
            return (string) $stored;
        }

        return (string) __('partner_terms.'.$type.'.body', [], $locale);
    }

    public function render(string $type, ?Partner $partner = null, ?string $locale = null): string
    {
        $locale = $locale ?: app()->getLocale();
        $text = $this->template($type, $locale);
        foreach ($this->variables($type, $partner, $locale) as $key => $value) {
            $text = str_replace(['{{'.$key.'}}', '{'.$key.'}'], (string) $value, $text);
        }

        return $text;
    }

    public function title(string $type, ?string $locale = null): string
    {
        return (string) __('partner_terms.'.$type.'.title', [], $locale ?: app()->getLocale());
    }

    public function hasSatisfiedTerms(Partner|Vendor $partner): bool
    {
        if (! $this->appliesTo($partner) || ! $this->requireBeforeJobs()) {
            return true;
        }

        $type = $this->typeFor($partner);
        $latest = $this->latestAcceptance($partner);
        if ($latest) {
            if (! $this->materialChangeRequiresReacceptance()) {
                return true;
            }

            return (int) $latest->agreement_version >= $this->agreementVersion($type)
                && (int) $latest->policy_version >= $this->policyVersion();
        }

        return $this->isGrandfathered($partner);
    }

    public function isGrandfathered(Partner|Vendor $partner): bool
    {
        $launched = $this->launchedAt();
        if (! $launched) {
            return false;
        }

        $cutoff = \Illuminate\Support\Carbon::parse($launched);
        if ($partner->activated_at && $partner->activated_at->lte($cutoff)) {
            return true;
        }

        $conductAt = data_get($partner->metadata, 'collection_conduct_accepted_at');

        return filled($conductAt) && \Illuminate\Support\Carbon::parse((string) $conductAt)->lte($cutoff);
    }

    public function latestAcceptance(Partner|Vendor $partner): ?PartnerAgreementAcceptance
    {
        return PartnerAgreementAcceptance::query()
            ->where('partner_id', $partner->id)
            ->where('agreement_key', $this->agreementKey($this->typeFor($partner)))
            ->orderByDesc('accepted_at')
            ->orderByDesc('id')
            ->first();
    }

    /** @return \Illuminate\Support\Collection<int, PartnerAgreementAcceptance> */
    public function history(Partner|Vendor $partner)
    {
        return PartnerAgreementAcceptance::query()
            ->where('partner_id', $partner->id)
            ->where('agreement_key', $this->agreementKey($this->typeFor($partner)))
            ->orderByDesc('accepted_at')
            ->orderByDesc('id')
            ->get();
    }

    public function accept(Partner|Vendor $partner, Request $request, ?string $locale = null): PartnerAgreementAcceptance
    {
        $locale = $locale ?: app()->getLocale();
        $type = $this->typeFor($partner);
        $rendered = $this->render($type, $partner instanceof Partner ? $partner : Partner::query()->find($partner->id), $locale);
        $snapshot = $this->variables($type, $partner instanceof Partner ? $partner : Partner::query()->find($partner->id), $locale);

        return PartnerAgreementAcceptance::query()->create([
            'partner_id' => $partner->id,
            'partner_type' => $type,
            'agreement_key' => $this->agreementKey($type),
            'agreement_version' => $this->agreementVersion($type),
            'policy_version' => $this->policyVersion(),
            'locale' => $locale,
            'rendered_text' => $rendered,
            'content_hash' => hash('sha256', $rendered),
            'settings_snapshot' => $snapshot,
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 500),
            'accepted_at' => now(),
        ]);
    }

    public function partnerLocale(Partner|Vendor $partner): string
    {
        $locale = $partner->user?->locale ?? app()->getLocale();

        return in_array($locale, ['en', 'sw'], true) ? $locale : app()->getLocale();
    }
}
