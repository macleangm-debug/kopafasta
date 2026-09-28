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
     * @param  array<string, string>  $overrides
     * @return array<string, string>
     */
    public function variables(?Vendor $affiliate = null, ?string $locale = null, array $overrides = []): array
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
        $premium = $affiliate?->isPremiumAffiliate() ?? false;
        $contractMonths = app(AffiliateSettingsService::class)->premiumContractDurationMonths();
        $contractLabel = $this->contractDurationLabel($contractMonths, $locale);
        $commercial = app(AffiliateCommercialTermsService::class)->contractSnapshot($affiliate);
        $minWithdrawal = (string) ($commercial['minimum_withdrawal_amount'] ?? format_money($settings->minimumPayoutAmount()));
        $effectiveDate = now()->format('d M Y');
        $ratesTable = $this->formatRatesTable($commercial, $settings, $affiliate, $locale);
        $benefitsTable = $this->formatBenefitsTable($commercial, $settings, $affiliate, $locale);
        $note = $affiliate ? app(AffiliateCommercialTermsService::class)->commercialNote($affiliate) : null;
        $negotiated = filled($note) ? (string) $note : (string) __('affiliate_terms.negotiated_none', [], $locale);
        $exclusivity = (string) __('affiliate_terms.exclusivity_none', [], $locale);
        $agreementTerm = $premium
            ? $contractLabel
            : $this->standardContractTerm($affiliate);
        $signatory = trim((string) Setting::get('company.authorized_signatory', ''));
        $signatoryTitle = trim((string) Setting::get('company.authorized_signatory_title', ''));
        if ($signatory === '') {
            $signatory = brand_name();
        }
        if ($signatoryTitle === '') {
            $signatoryTitle = 'Authorized signatory';
        }

        $vars = [
            'membership_fee' => format_money($fee),
            'membership_fee_individual' => format_money((float) $membership['fee_amount_individual']),
            'membership_fee_company' => format_money((float) $membership['fee_amount_company']),
            'membership_duration' => (string) ($membership['duration_days'] ?? 365),
            'membership_grace_hours' => (string) ($membership['grace_period_hours'] ?? 48),
            'premium_contract_months' => (string) $contractMonths,
            'premium_contract_label' => $contractLabel,
            'affiliate_name' => $affiliate?->name ?: '—',
            'affiliate_number' => (string) ($affiliate?->partner_number ?: ($affiliate ? '#'.$affiliate->id : '—')),
            'effective_date' => $effectiveDate,
            'accepted_at' => '—',
            'executed_at' => '—',
            'agreement_version' => (string) $this->agreementVersion(),
            'authorized_signatory' => $signatory,
            'authorized_signatory_title' => $signatoryTitle,
            'company_signature' => 'Electronic acceptance',
            'affiliate_signature_or_acceptance' => 'Electronic acceptance',
            'affiliate_type' => $premium
                ? __('site.affiliate_portal.premium_partner')
                : __('site.affiliate_portal.hero_type_affiliate'),
            'rate_source' => $commercial['commercial_rate_source_label'],
            'commission_percent' => $commercial['commission_percent'],
            'commission_rate' => $commercial['commission_percent'],
            'commission_basis' => $settings->commissionBasisLabel($locale),
            'commission_basis_clause' => (string) __('affiliate_terms.commission_basis_clause', [], $locale),
            'registration_discount_percent' => $settings->benefitAppliesInTerritory('registration_fee', $settings->assessmentCountry($affiliate))
                ? $commercial['registration_discount_percent']
                : __('admin.partners.commercial_not_applicable'),
            'application_discount_percent' => $settings->benefitAppliesInTerritory('application_fee', $settings->assessmentCountry($affiliate))
                ? $commercial['application_discount_percent']
                : __('admin.partners.commercial_not_applicable'),
            'plus_discount_percent' => $settings->benefitAppliesInTerritory('kopafasta_plus', $settings->assessmentCountry($affiliate))
                ? $commercial['plus_discount_percent']
                : __('admin.partners.commercial_not_applicable'),
            'commercial_effective_from' => $commercial['commercial_effective_from'],
            'commercial_terms_effective_date' => $commercial['commercial_effective_from'],
            'effective_rates_table' => $ratesTable,
            'commission_table' => $ratesTable,
            'customer_benefits_table' => $benefitsTable,
            'benefits_table' => $benefitsTable,
            'minimum_withdrawal_amount' => $minWithdrawal,
            'withdrawal_terms' => (string) __('affiliate_terms.withdrawal_terms_text', [], $locale),
            'agreement_term' => $agreementTerm,
            'exclusivity_terms' => $exclusivity,
            'negotiated_terms' => $negotiated,
            'application_commission_description' => (string) __('affiliate_terms.application_commission_description', [], $locale),
            'plus_commission_description' => (string) __('affiliate_terms.plus_commission_description', [], $locale),
            'other_commission_description' => (string) __('affiliate_terms.other_commission_description', [], $locale),
            'membership_clause' => $this->membershipClause($affiliate, $locale),
            'territory' => $settings->assessmentCountry($affiliate),
            'agreement_start' => $affiliate?->membership_started_at?->format('d M Y') ?? $effectiveDate,
            'agreement_end' => $affiliate?->membership_expires_at?->format('d M Y') ?? '—',
            'assessment_period' => $premium ? __('admin.partners.commercial_not_applicable') : (string) $settings->evaluationPeriodDays(),
            'assessment_period_label' => $premium ? __('admin.partners.commercial_not_applicable') : $this->periodLabel($settings->evaluationPeriodDays(), $locale),
            'minimum_qualified_referrals' => $premium ? __('admin.partners.commercial_not_applicable') : (string) ($referrals['target'] ?? $settings->monthlyRegistrationTarget()),
            'ramp_up_days' => $premium ? __('admin.partners.commercial_not_applicable') : (string) $settings->volumeMinActiveDays(),
            'warning_periods' => $premium ? __('admin.partners.commercial_not_applicable') : (string) $settings->volumeMissesBeforeWatchlist(),
            'suspension_periods' => $premium ? __('admin.partners.commercial_not_applicable') : (string) $settings->volumeMissesBeforeSuspend(),
            'recovery_enabled' => $premium
                ? __('admin.partners.commercial_not_applicable')
                : (($eval['auto_recover'] ?? true) ? __('affiliate_terms.yes', [], $locale) : __('affiliate_terms.no', [], $locale)),
            'policy_version' => (string) $settings->policyVersion(),
            'brand' => brand_name(),
        ];

        foreach ($overrides as $key => $value) {
            $vars[$key] = (string) $value;
        }

        return $vars;
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
        $this->ensurePublishedContractContent();

        // Content revision 4: strengthened Affiliate conduct / customer-protection obligations.
        return max(4, (int) Setting::get('affiliates.terms.version', 4));
    }

    /**
     * Publish Owner contract pack into Settings SoT and clear stale Hub body overrides
     * so existing Affiliates see the new EN/SW templates (re-acceptance required).
     * Prior PartnerAgreementAcceptance rows remain immutable evidence of older versions.
     */
    public function ensurePublishedContractContent(): void
    {
        if ((int) Setting::get('affiliates.terms.content_revision', 0) >= 4) {
            return;
        }

        foreach ([
            'affiliates.terms.body_en',
            'affiliates.terms.body_sw',
            'affiliates.terms.premium.body_en',
            'affiliates.terms.premium.body_sw',
        ] as $key) {
            Setting::set($key, '');
        }

        $current = max(4, (int) Setting::get('affiliates.terms.version', 1));
        Setting::set('affiliates.terms.version', $current);
        Setting::set('affiliates.terms.content_revision', 4);
    }

    public function template(?string $locale = null, ?Vendor $affiliate = null): string
    {
        $this->ensurePublishedContractContent();
        $locale = $locale ?: app()->getLocale();
        $premium = $affiliate?->isPremiumAffiliate() ?? false;
        $key = $premium
            ? 'affiliates.terms.premium.body_'.$locale
            : 'affiliates.terms.body_'.$locale;
        $stored = Setting::get($key);
        if (filled($stored)) {
            return (string) $stored;
        }

        $fallback = $premium
            ? __('affiliate_terms.premium_body', [], $locale)
            : __('affiliate_terms.body', [], $locale);

        return (string) (filled($fallback) ? $fallback : __('affiliate_terms.body', [], $locale));
    }

    public function agreementTitle(?Vendor $affiliate = null, ?string $locale = null): string
    {
        $locale = $locale ?: app()->getLocale();
        if ($affiliate?->isPremiumAffiliate()) {
            return (string) __('affiliate_terms.premium_title', [], $locale);
        }

        return (string) __('affiliate_terms.title', [], $locale);
    }

    /**
     * @param  array<string, string>  $overrides
     */
    public function render(?Vendor $affiliate = null, ?string $locale = null, array $overrides = []): string
    {
        $locale = $locale ?: app()->getLocale();
        $text = $this->template($locale, $affiliate);
        foreach ($this->variables($affiliate, $locale, $overrides) as $key => $value) {
            $text = str_replace(['{{'.$key.'}}', '{'.$key.'}'], $value, $text);
        }

        // Never leave unresolved placeholders in customer-facing output.
        $text = preg_replace('/\{\{[a-z0-9_]+\}\}/i', '', $text) ?? $text;

        return $text;
    }

    public function hasAccepted(Vendor|Partner $affiliate): bool
    {
        $latest = $this->latestAcceptance($affiliate);
        if (! $latest) {
            return false;
        }

        // Existing Affiliates on an older signed pack must accept the current contract version.
        return (int) $latest->agreement_version >= $this->agreementVersion();
    }

    public function needsAcceptance(Vendor|Partner $affiliate): bool
    {
        return ! $this->hasAccepted($affiliate);
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
        $vendor = $affiliate instanceof Vendor ? $affiliate : Vendor::query()->find($affiliate->id);
        $acceptedAt = now();
        $stamp = $acceptedAt->format('d M Y H:i');
        $overrides = [
            'accepted_at' => $stamp,
            'executed_at' => $stamp,
            'effective_date' => $acceptedAt->format('d M Y'),
        ];
        $rendered = $this->render($vendor, $locale, $overrides);
        $snapshot = array_merge(
            $this->variables($vendor, $locale, $overrides),
            $vendor ? app(AffiliateCommercialTermsService::class)->contractSnapshot($vendor) : [],
            [
                'contract_type' => $vendor?->isPremiumAffiliate()
                    ? 'premium_affiliate_partnership'
                    : 'affiliate_agreement',
                'language' => $locale,
                'classification' => $vendor?->isPremiumAffiliate() ? 'premium_affiliate' : 'affiliate',
                'accepted_at' => $stamp,
                'executed_at' => $stamp,
            ],
        );

        $acceptance = PartnerAgreementAcceptance::query()->create([
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
            'accepted_at' => $acceptedAt,
        ]);

        if ($affiliate->isPremiumAffiliate()) {
            app(AffiliateMembershipService::class)->startPremiumAgreement($affiliate);
        }

        return $acceptance;
    }

    /** @param  array<string, mixed>  $commercial */
    /** @return array<string, mixed> */
    public function documentHeader(Vendor $affiliate, array $commercial, ?PartnerAgreementAcceptance $acceptance = null): array
    {
        $locale = $acceptance?->locale ?: app()->getLocale();

        return [
            'title' => $this->agreementTitle($affiliate, $locale),
            'affiliate_name' => $affiliate->name,
            'affiliate_id' => $affiliate->partner_number ?: '#'.$affiliate->id,
            'affiliate_type' => $affiliate->isPremiumAffiliate()
                ? __('site.affiliate_portal.premium_partner')
                : __('site.affiliate_portal.hero_type_affiliate'),
            'agreement_version' => $acceptance?->agreement_version ?? $this->agreementVersion(),
            'policy_version' => $acceptance?->policy_version ?? $this->policyVersion(),
            'effective_date' => $acceptance?->accepted_at?->format('d M Y') ?? now()->format('d M Y'),
            'contract_term' => $commercial['premium'] ?? false
                ? $this->contractDurationLabel((int) ($commercial['duration_months'] ?? app(AffiliateSettingsService::class)->premiumContractDurationMonths()), $locale)
                : $this->standardContractTerm($affiliate),
            'start_date' => $commercial['started_at']?->format('d M Y'),
            'end_date' => $commercial['expires_at']?->format('d M Y'),
            'accepted_at' => $acceptance?->accepted_at?->format('d M Y'),
            'minimum_withdrawal_amount' => data_get($acceptance?->settings_snapshot, 'minimum_withdrawal_amount')
                ?? format_money(app(AffiliateSettingsService::class)->minimumPayoutAmount()),
        ];
    }

    private function standardContractTerm(?Vendor $affiliate): string
    {
        $membership = app(AffiliateMembershipService::class);
        if ($affiliate && $membership->usesPremiumAgreement($affiliate)) {
            return __('admin.partners.commercial_not_applicable');
        }
        if (! AffiliateMembershipService::config()['enabled']) {
            return __('admin.partners.commercial_not_applicable');
        }

        return __('affiliate_terms.annual_membership_term', ['days' => AffiliateMembershipService::config()['duration_days'] ?? 365]);
    }

    private function membershipClause(?Vendor $affiliate, string $locale): string
    {
        $membership = app(AffiliateMembershipService::class);
        $cfg = AffiliateMembershipService::config();
        if ($affiliate && $membership->usesPremiumAgreement($affiliate)) {
            return __('affiliate_terms.membership_not_required_premium', [], $locale);
        }
        if (! ($cfg['enabled'] ?? false)) {
            return __('affiliate_terms.membership_not_required_territory', [], $locale);
        }

        return __('affiliate_terms.membership_required_clause', [
            'membership_fee_individual' => format_money((float) $cfg['fee_amount_individual']),
            'membership_fee_company' => format_money((float) $cfg['fee_amount_company']),
            'membership_duration' => (string) ($cfg['duration_days'] ?? 365),
            'membership_grace_hours' => (string) ($cfg['grace_period_hours'] ?? 48),
        ], $locale);
    }

    /**
     * Parse agreement text into meta rows + numbered clause sections.
     *
     * @return array{
     *   meta: list<array{label: string, value: string}>,
     *   intro: string,
     *   sections: list<array{title: string, body: string, body_html: string}>
     * }
     */
    public function parseDocument(Vendor $affiliate, ?PartnerAgreementAcceptance $acceptance = null): array
    {
        $text = trim((string) ($acceptance?->rendered_text ?: $this->render($affiliate)));
        // Title lives in the branded header — drop the markdown H1.
        $text = preg_replace('/^#\s+[^\n]*\n*/', '', $text) ?? $text;

        $lines = preg_split("/\n/", $text) ?: [];
        $introLines = [];
        $meta = [];
        $i = 0;
        $count = count($lines);

        while ($i < $count) {
            $raw = $lines[$i];
            $line = trim($raw);
            if ($line === '') {
                if ($meta !== [] || $introLines !== []) {
                    // Blank line after meta/intro ends the preamble scan once meta started.
                    if ($meta !== []) {
                        $i++;
                        break;
                    }
                }
                $i++;
                continue;
            }
            if (str_starts_with($line, '## ')) {
                break;
            }
            if (preg_match('/^\*{0,2}\s*(.+?)\s*\*{0,2}:\s*\*{0,2}\s*(.*?)\s*\*{0,2}$/u', $line, $match)) {
                $label = trim($match[1], " \t*");
                $value = trim($match[2], " \t*");
                if ($label !== '' && $value !== '' && $value !== '—') {
                    $meta[] = ['label' => $label, 'value' => $value];
                }
                $i++;
                continue;
            }
            if ($meta !== []) {
                break;
            }
            $introLines[] = $raw;
            $i++;
        }

        $remainder = trim(implode("\n", array_slice($lines, $i)));
        $chunks = $remainder === '' ? [] : (preg_split("/\n(?=##\s+)/", $remainder) ?: []);
        $sections = [];
        foreach ($chunks as $chunk) {
            $chunk = trim($chunk);
            if ($chunk === '') {
                continue;
            }
            if (preg_match('/^##\s+(.+?)\n(.*)$/s', $chunk, $matches)) {
                $title = trim($matches[1]);
                $title = preg_replace('/^\d+\.\s+/', '', $title) ?? $title;
                $body = trim($matches[2]);
                $sections[] = [
                    'title' => $title,
                    'body' => $this->stripMarkdownMarkers($body),
                    'body_html' => $this->formatAgreementHtml($body),
                ];
            }
        }

        $intro = trim(implode("\n", $introLines));

        return [
            'meta' => $meta,
            'intro' => $this->stripMarkdownMarkers($intro),
            'intro_html' => $this->formatAgreementHtml($intro),
            'sections' => $sections,
        ];
    }

    /** @return list<array{title: string, body: string, body_html?: string}> */
    public function documentSections(Vendor $affiliate, ?PartnerAgreementAcceptance $acceptance = null): array
    {
        return $this->parseDocument($affiliate, $acceptance)['sections'];
    }

    private function stripMarkdownMarkers(string $text): string
    {
        $text = preg_replace('/\*\*(.+?)\*\*/u', '$1', $text) ?? $text;
        $text = preg_replace('/__(.+?)__/u', '$1', $text) ?? $text;

        return trim($text);
    }

    private function formatAgreementHtml(string $text): string
    {
        $parts = preg_split('/(\*\*.+?\*\*|__.+?__)/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$text];
        $html = '';
        foreach ($parts as $part) {
            if (preg_match('/^\*\*(.+)\*\*$/us', $part, $match) || preg_match('/^__(.+)__$/us', $part, $match)) {
                $html .= '<strong>'.e($match[1]).'</strong>';
            } else {
                $html .= e($part);
            }
        }

        return nl2br($html, false);
    }

    public function contractDurationLabel(int $months, ?string $locale = null): string
    {
        $locale = $locale ?: app()->getLocale();

        return trans_choice('affiliate_terms.contract_months', $months, ['count' => $months], $locale);
    }

    private function periodLabel(int $days, ?string $locale = null): string
    {
        $locale = $locale ?: app()->getLocale();
        if ($days >= 85 && $days <= 95) {
            return __('affiliate_terms.quarterly', [], $locale);
        }

        return $days.' '.__('affiliate_terms.days', [], $locale);
    }

    /**
     * @param  array<string, mixed>  $commercial
     */
    private function formatRatesTable(
        array $commercial,
        AffiliateSettingsService $settings,
        ?Vendor $affiliate,
        string $locale
    ): string {
        $country = $settings->assessmentCountry($affiliate);
        $rows = [
            __('affiliate_terms.rate_commission', [], $locale).': '.$commercial['commission_percent'],
            __('affiliate_terms.commission_basis_label', [], $locale).': '.$settings->commissionBasisLabel($locale),
        ];
        if ($settings->benefitAppliesInTerritory('registration_fee', $country)) {
            $rows[] = __('affiliate_terms.rate_registration', [], $locale).': '.$commercial['registration_discount_percent'];
        }
        if ($settings->benefitAppliesInTerritory('application_fee', $country)) {
            $rows[] = __('affiliate_terms.rate_application', [], $locale).': '.$commercial['application_discount_percent'];
        }
        if ($settings->benefitAppliesInTerritory('kopafasta_plus', $country)) {
            $rows[] = __('affiliate_terms.rate_plus', [], $locale).': '.$commercial['plus_discount_percent'];
        }

        return implode("\n", $rows);
    }

    /**
     * @param  array<string, mixed>  $commercial
     */
    private function formatBenefitsTable(
        array $commercial,
        AffiliateSettingsService $settings,
        ?Vendor $affiliate,
        string $locale
    ): string {
        $country = $settings->assessmentCountry($affiliate);
        $rows = [];
        if ($settings->benefitAppliesInTerritory('registration_fee', $country)
            && (float) str_replace('%', '', (string) $commercial['registration_discount_percent']) > 0) {
            $rows[] = __('affiliate_terms.rate_registration', [], $locale).': '.$commercial['registration_discount_percent'];
        }
        if ($settings->benefitAppliesInTerritory('application_fee', $country)
            && (float) str_replace('%', '', (string) $commercial['application_discount_percent']) > 0) {
            $rows[] = __('affiliate_terms.rate_application', [], $locale).': '.$commercial['application_discount_percent'];
        }
        if ($settings->benefitAppliesInTerritory('kopafasta_plus', $country)
            && (float) str_replace('%', '', (string) $commercial['plus_discount_percent']) > 0) {
            $rows[] = __('affiliate_terms.rate_plus', [], $locale).': '.$commercial['plus_discount_percent'];
        }

        if ($rows === []) {
            return (string) __('affiliate_terms.benefit_none', [], $locale);
        }

        return implode("\n", $rows);
    }
}
