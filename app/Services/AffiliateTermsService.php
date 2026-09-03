<?php

namespace App\Services;

use App\Models\Partner;
use App\Models\PartnerAgreementAcceptance;
use App\Models\Setting;
use App\Models\Vendor;
use Illuminate\Http\Request;

class AffiliateTermsService
{
    public const AGREEMENT_KEY = 'affiliate_terms';

    /**
     * Approved placeholder catalogue. Terms templates may only use these keys.
     *
     * @return array<string, string>
     */
    public function variables(?Vendor $affiliate = null, ?string $locale = null): array
    {
        $locale = $locale ?: app()->getLocale();
        $membership = AffiliateMembershipService::config();
        $settings = app(AffiliateSettingsService::class);
        $eval = $settings->evaluationSettings();
        $kpis = $settings->kpiCatalog();
        $referrals = $kpis['qualified_referrals'] ?? [];
        $fee = $affiliate
            ? app(AffiliateMembershipService::class)->feeFor($affiliate)
            : (float) $membership['fee_amount_individual'];

        return [
            'membership_fee' => format_money($fee),
            'membership_fee_individual' => format_money((float) $membership['fee_amount_individual']),
            'membership_fee_company' => format_money((float) $membership['fee_amount_company']),
            'membership_duration' => (string) ($membership['duration_days'] ?? 365),
            'membership_grace_hours' => (string) ($membership['grace_period_hours'] ?? 48),
            'assessment_period' => (string) $settings->evaluationPeriodDays(),
            'assessment_period_label' => $this->periodLabel($settings->evaluationPeriodDays(), $locale),
            'minimum_qualified_referrals' => (string) ($referrals['target'] ?? $settings->monthlyRegistrationTarget()),
            'ramp_up_days' => (string) $settings->volumeMinActiveDays(),
            'warning_periods' => (string) $settings->volumeMissesBeforeWatchlist(),
            'suspension_periods' => (string) $settings->volumeMissesBeforeSuspend(),
            'recovery_enabled' => ($eval['auto_recover'] ?? true) ? __('affiliate_terms.yes', [], $locale) : __('affiliate_terms.no', [], $locale),
            'policy_version' => (string) $settings->policyVersion(),
            'brand' => brand_name(),
        ];
    }

    /** @return list<string> */
    public function approvedVariableKeys(): array
    {
        return array_keys($this->variables());
    }

    public function policyVersion(): int
    {
        return app(AffiliateSettingsService::class)->policyVersion();
    }

    public function agreementVersion(): int
    {
        return max(1, (int) Setting::get('affiliates.terms.version', 1));
    }

    public function template(?string $locale = null): string
    {
        $locale = $locale ?: app()->getLocale();
        $stored = Setting::get('affiliates.terms.body_'.$locale);
        if (filled($stored)) {
            return (string) $stored;
        }

        return (string) __('affiliate_terms.body', [], $locale);
    }

    public function render(?Vendor $affiliate = null, ?string $locale = null): string
    {
        $locale = $locale ?: app()->getLocale();
        $text = $this->template($locale);
        foreach ($this->variables($affiliate, $locale) as $key => $value) {
            $text = str_replace(['{{'.$key.'}}', '{'.$key.'}'], $value, $text);
        }

        return $text;
    }

    public function hasAccepted(Vendor|Partner $affiliate): bool
    {
        return PartnerAgreementAcceptance::query()
            ->where('partner_id', $affiliate->id)
            ->where('agreement_key', self::AGREEMENT_KEY)
            ->exists();
    }

    public function latestAcceptance(Vendor|Partner $affiliate): ?PartnerAgreementAcceptance
    {
        return PartnerAgreementAcceptance::query()
            ->where('partner_id', $affiliate->id)
            ->where('agreement_key', self::AGREEMENT_KEY)
            ->orderByDesc('accepted_at')
            ->orderByDesc('id')
            ->first();
    }

    public function accept(Vendor|Partner $affiliate, Request $request, ?string $locale = null): PartnerAgreementAcceptance
    {
        $locale = $locale ?: app()->getLocale();
        $rendered = $this->render($affiliate instanceof Vendor ? $affiliate : Vendor::query()->find($affiliate->id), $locale);
        $snapshot = $this->variables($affiliate instanceof Vendor ? $affiliate : null, $locale);

        return PartnerAgreementAcceptance::query()->create([
            'partner_id' => $affiliate->id,
            'partner_type' => 'affiliate',
            'agreement_key' => self::AGREEMENT_KEY,
            'agreement_version' => $this->agreementVersion(),
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

    private function periodLabel(int $days, ?string $locale = null): string
    {
        $locale = $locale ?: app()->getLocale();
        if ($days >= 85 && $days <= 95) {
            return __('affiliate_terms.quarterly', [], $locale);
        }

        return $days.' '.__('affiliate_terms.days', [], $locale);
    }
}
