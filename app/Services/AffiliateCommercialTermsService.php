<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Partner;
use App\Models\PartnerAgreementAcceptance;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class AffiliateCommercialTermsService
{
    public const SOURCE_STANDARD = 'standard';

    public const SOURCE_NEGOTIATED = 'negotiated';

    public function __construct(
        private readonly AffiliateSettingsService $settings,
        private readonly AuditService $audit,
    ) {}

    /** @return array<string, float> */
    public function settingsRates(): array
    {
        $form = $this->settings->forForm();

        return [
            'registration_discount_percent' => (float) ($form['default_registration_discount_percent'] ?? 10),
            'application_discount_percent' => (float) ($form['default_application_discount_percent'] ?? 10),
            'affiliate_commission_percent' => (float) ($form['default_commission_percent'] ?? 10),
            'plus_discount_percent' => (float) ($form['default_plus_discount_percent'] ?? 10),
        ];
    }

    public function rateSource(Vendor|Partner $affiliate): string
    {
        $stored = (string) data_get($affiliate->metadata, 'commercial.rate_source', '');
        if (in_array($stored, [self::SOURCE_STANDARD, self::SOURCE_NEGOTIATED], true)) {
            return $stored;
        }

        if ($affiliate->isPremiumAffiliate() && $this->hasStoredOverride($affiliate)) {
            return self::SOURCE_NEGOTIATED;
        }

        return self::SOURCE_STANDARD;
    }

    public function usesNegotiatedRates(Vendor|Partner $affiliate): bool
    {
        return $affiliate->isPremiumAffiliate()
            && $this->rateSource($affiliate) === self::SOURCE_NEGOTIATED;
    }

    /** @return array<string, float> */
    public function effectiveRates(Vendor|Partner $affiliate): array
    {
        $defaults = $this->settingsRates();
        if (! $this->usesNegotiatedRates($affiliate)) {
            return $defaults;
        }

        $snapshot = data_get($affiliate->metadata, 'commercial.rates');
        $plus = data_get($affiliate->metadata, 'plus_discount_percent');

        return [
            'registration_discount_percent' => (float) ($affiliate->registration_discount_percent
                ?? data_get($snapshot, 'registration_discount_percent')
                ?? $defaults['registration_discount_percent']),
            'application_discount_percent' => (float) ($affiliate->application_discount_percent
                ?? data_get($snapshot, 'application_discount_percent')
                ?? $defaults['application_discount_percent']),
            'affiliate_commission_percent' => (float) ($affiliate->affiliate_commission_percent
                ?? data_get($snapshot, 'affiliate_commission_percent')
                ?? $defaults['affiliate_commission_percent']),
            'plus_discount_percent' => (float) ($plus
                ?? data_get($snapshot, 'plus_discount_percent')
                ?? $defaults['plus_discount_percent']),
        ];
    }

    public function commercialNote(Vendor|Partner $affiliate): ?string
    {
        $note = trim((string) data_get($affiliate->metadata, 'commercial.note', ''));

        return $note !== '' ? $note : null;
    }

    public function effectiveFrom(Vendor|Partner $affiliate): ?Carbon
    {
        $raw = data_get($affiliate->metadata, 'commercial.effective_from');
        if (! filled($raw)) {
            return $affiliate->activated_at ?: $affiliate->created_at;
        }

        try {
            return Carbon::parse((string) $raw);
        } catch (\Throwable) {
            return $affiliate->activated_at ?: $affiliate->created_at;
        }
    }

    public function latestAgreement(Vendor|Partner $affiliate): ?PartnerAgreementAcceptance
    {
        return app(AffiliateTermsService::class)->latestAcceptance($affiliate);
    }

    /** @return array<string, mixed> */
    public function arrangement(Vendor|Partner $affiliate): array
    {
        $premium = $affiliate->isPremiumAffiliate();
        $source = $this->rateSource($affiliate);
        $rates = $this->effectiveRates($affiliate);
        $agreement = $this->latestAgreement($affiliate);

        return [
            'premium' => $premium,
            'type_label' => $premium
                ? __('site.affiliate_portal.premium_partner')
                : __('site.affiliate_portal.standard_partner'),
            'rate_source' => $source,
            'rate_source_label' => $source === self::SOURCE_NEGOTIATED
                ? __('admin.partners.commercial_source_negotiated')
                : __('admin.partners.commercial_source_standard'),
            'rates' => $rates,
            'note' => $this->commercialNote($affiliate),
            'effective_from' => $this->effectiveFrom($affiliate),
            'agreement' => $agreement,
            'agreement_url' => route('admin.partners.show', ['vendor' => $affiliate, 'tab' => 'agreements']),
        ];
    }

    /** @return array<string, mixed> */
    public function contractSnapshot(?Vendor $affiliate = null): array
    {
        $rates = $affiliate
            ? $this->effectiveRates($affiliate)
            : $this->settingsRates();
        $premium = $affiliate?->isPremiumAffiliate() ?? false;
        $source = $affiliate ? $this->rateSource($affiliate) : self::SOURCE_STANDARD;

        return [
            'affiliate_classification' => $premium ? 'premium' : 'standard',
            'rate_source' => $source,
            'registration_discount_percent' => $this->formatPercent($rates['registration_discount_percent']),
            'application_discount_percent' => $this->formatPercent($rates['application_discount_percent']),
            'commission_percent' => $this->formatPercent($rates['affiliate_commission_percent']),
            'plus_discount_percent' => $this->formatPercent($rates['plus_discount_percent']),
            'minimum_withdrawal_amount' => format_money($this->settings->minimumPayoutAmount()),
            'commercial_effective_from' => $affiliate
                ? ($this->effectiveFrom($affiliate)?->format('d M Y') ?? '—')
                : '—',
            'commercial_rate_source_label' => $source === self::SOURCE_NEGOTIATED
                ? __('admin.partners.commercial_source_negotiated')
                : __('admin.partners.commercial_source_standard'),
        ];
    }

    /**
     * Apply create/draft commercial terms. Activated partners must use the
     * consequential change endpoints instead of casual form fields.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function applyFromForm(array $data, ?Vendor $existing, bool $canNegotiate): array
    {
        $isAffiliate = ($data['category'] ?? '') === 'affiliate'
            || in_array('affiliate', $data['roles'] ?? [], true)
            || ($existing instanceof Vendor && $existing->isAffiliate());

        if (! $isAffiliate) {
            return $this->stripCommercialInputs($data);
        }

        if ($existing instanceof Vendor && $this->isActivated($existing)) {
            unset(
                $data['affiliate_premium'],
                $data['commercial_rate_source'],
                $data['commercial_note'],
                $data['commercial_effective_from'],
            );

            return $this->stripRateInputs($data);
        }

        $premium = filter_var($data['affiliate_premium'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $source = $premium && $canNegotiate && (string) ($data['commercial_rate_source'] ?? self::SOURCE_STANDARD) === self::SOURCE_NEGOTIATED
            ? self::SOURCE_NEGOTIATED
            : self::SOURCE_STANDARD;

        $note = $premium ? trim((string) ($data['commercial_note'] ?? '')) : '';
        $effectiveFrom = now()->toDateString();
        $defaults = $this->settingsRates();
        $rates = $source === self::SOURCE_NEGOTIATED
            ? $this->ratesFromInput($data, $defaults)
            : $defaults;

        $meta = is_array($data['metadata'] ?? null) ? $data['metadata'] : [];
        $meta = $this->writeCommercialMeta($meta, $source, $note, $effectiveFrom, $rates, $source === self::SOURCE_NEGOTIATED);

        $data['affiliate_premium'] = $premium;
        $data['metadata'] = $meta;
        $data = $this->writeRateColumns($data, $source === self::SOURCE_NEGOTIATED ? $rates : null);

        return $this->stripCommercialInputs($data);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function changeTerms(Vendor $affiliate, array $input, User $actor): Vendor
    {
        if (! $affiliate->isAffiliate()) {
            throw ValidationException::withMessages([
                'rate_source' => 'Commercial terms apply only to Affiliates.',
            ]);
        }

        $reason = trim((string) ($input['reason'] ?? ''));
        if (mb_strlen($reason) < 8) {
            throw ValidationException::withMessages([
                'reason' => 'Give a short reason for the commercial-term change.',
            ]);
        }

        $effectiveFrom = Carbon::parse((string) ($input['effective_from'] ?? now()->toDateString()))->startOfDay();
        if ($effectiveFrom->lt(now()->startOfDay())) {
            throw ValidationException::withMessages([
                'effective_from' => 'The new terms must apply from today or a future date.',
            ]);
        }

        $source = (string) ($input['rate_source'] ?? self::SOURCE_STANDARD);
        if (! $affiliate->isPremiumAffiliate()) {
            $source = self::SOURCE_STANDARD;
        } elseif (! in_array($source, [self::SOURCE_STANDARD, self::SOURCE_NEGOTIATED], true)) {
            throw ValidationException::withMessages([
                'rate_source' => 'Choose standard or negotiated rates.',
            ]);
        }

        $defaults = $this->settingsRates();
        $rates = $source === self::SOURCE_NEGOTIATED
            ? $this->ratesFromInput($input, $defaults)
            : $defaults;
        $note = $affiliate->isPremiumAffiliate() ? trim((string) ($input['note'] ?? '')) : '';
        $before = $this->arrangement($affiliate);

        $meta = is_array($affiliate->metadata) ? $affiliate->metadata : [];
        $meta = $this->writeCommercialMeta($meta, $source, $note, $effectiveFrom->toDateString(), $rates, $source === self::SOURCE_NEGOTIATED);

        $payload = [
            'metadata' => $meta,
        ];
        $payload = $this->writeRateColumns($payload, $source === self::SOURCE_NEGOTIATED ? $rates : null);
        $affiliate->update($payload);

        $fresh = $affiliate->fresh() ?? $affiliate;
        $this->audit->log($actor, 'affiliate.commercial_terms.changed', $fresh, [
            'rate_source' => $before['rate_source'],
            'rates' => $before['rates'],
            'effective_from' => $before['effective_from']?->toDateString(),
        ], [
            'rate_source' => $source,
            'rates' => $rates,
            'effective_from' => $effectiveFrom->toDateString(),
            'reason' => $reason,
            'actor_id' => $actor->id,
        ]);

        return $fresh;
    }

    public function changeClassification(Vendor $affiliate, bool $premium, string $reason, User $actor): Vendor
    {
        if (! $affiliate->isAffiliate()) {
            throw ValidationException::withMessages([
                'affiliate_premium' => 'Classification applies only to Affiliates.',
            ]);
        }

        $reason = trim($reason);
        if (mb_strlen($reason) < 8) {
            throw ValidationException::withMessages([
                'reason' => 'Give a short reason for the classification change.',
            ]);
        }

        $wasPremium = $affiliate->isPremiumAffiliate();
        if ($wasPremium === $premium) {
            return $affiliate;
        }

        $before = $this->arrangement($affiliate);
        $defaults = $this->settingsRates();
        $meta = is_array($affiliate->metadata) ? $affiliate->metadata : [];
        $meta = $this->writeCommercialMeta(
            $meta,
            self::SOURCE_STANDARD,
            $premium ? (string) ($this->commercialNote($affiliate) ?? '') : '',
            now()->toDateString(),
            $defaults,
            false,
        );

        $payload = [
            'affiliate_premium' => $premium,
            'metadata' => $meta,
        ];
        $payload = $this->writeRateColumns($payload, null);
        $affiliate->update($payload);

        $fresh = $affiliate->fresh() ?? $affiliate;
        app(AffiliateEvaluationService::class)->syncPremiumStanding($fresh);
        $fresh = $fresh->fresh() ?? $fresh;

        $this->audit->log($actor, 'affiliate.classification.changed', $fresh, [
            'classification' => $wasPremium ? 'premium' : 'standard',
            'rate_source' => $before['rate_source'],
        ], [
            'classification' => $premium ? 'premium' : 'standard',
            'rate_source' => self::SOURCE_STANDARD,
            'reason' => $reason,
            'actor_id' => $actor->id,
            'changed_at' => now()->toIso8601String(),
        ]);

        return $fresh;
    }

    public function latestClassificationAudit(Vendor|Partner $affiliate): ?AuditLog
    {
        return AuditLog::query()
            ->where('auditable_type', $affiliate->getMorphClass())
            ->where('auditable_id', $affiliate->getKey())
            ->where('event', 'affiliate.classification.changed')
            ->orderByDesc('id')
            ->first();
    }

    public function isActivated(Vendor|Partner $affiliate): bool
    {
        return filled($affiliate->activated_at) || (string) $affiliate->status === 'active';
    }

    private function hasStoredOverride(Vendor|Partner $affiliate): bool
    {
        return $affiliate->registration_discount_percent !== null
            || $affiliate->application_discount_percent !== null
            || $affiliate->affiliate_commission_percent !== null
            || filled(data_get($affiliate->metadata, 'plus_discount_percent'))
            || is_array(data_get($affiliate->metadata, 'commercial.rates'));
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  array<string, float>  $defaults
     * @return array<string, float>
     */
    private function ratesFromInput(array $input, array $defaults): array
    {
        return [
            'registration_discount_percent' => $this->percent($input['registration_discount_percent'] ?? $defaults['registration_discount_percent']),
            'application_discount_percent' => $this->percent($input['application_discount_percent'] ?? $defaults['application_discount_percent']),
            'affiliate_commission_percent' => $this->percent($input['affiliate_commission_percent'] ?? $defaults['affiliate_commission_percent']),
            'plus_discount_percent' => $this->percent($input['plus_discount_percent'] ?? $defaults['plus_discount_percent']),
        ];
    }

    /**
     * @param  array<string, mixed>  $meta
     * @param  array<string, float>  $rates
     * @return array<string, mixed>
     */
    private function writeCommercialMeta(array $meta, string $source, string $note, string $effectiveFrom, array $rates, bool $storeRates): array
    {
        $commercial = is_array($meta['commercial'] ?? null) ? $meta['commercial'] : [];
        $commercial['rate_source'] = $source;
        $commercial['effective_from'] = $effectiveFrom;
        $commercial['note'] = $note !== '' ? $note : null;
        $commercial['rates'] = $storeRates ? $rates : null;
        $meta['commercial'] = $commercial;

        if ($storeRates) {
            $meta['plus_discount_percent'] = $rates['plus_discount_percent'];
        } else {
            unset($meta['plus_discount_percent']);
        }

        return $meta;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, float>|null  $rates
     * @return array<string, mixed>
     */
    private function writeRateColumns(array $data, ?array $rates): array
    {
        $data['registration_discount_percent'] = $rates['registration_discount_percent'] ?? null;
        $data['application_discount_percent'] = $rates['application_discount_percent'] ?? null;
        $data['affiliate_commission_percent'] = $rates['affiliate_commission_percent'] ?? null;

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function stripRateInputs(array $data): array
    {
        unset(
            $data['registration_discount_percent'],
            $data['application_discount_percent'],
            $data['affiliate_commission_percent'],
            $data['plus_discount_percent'],
        );

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function stripCommercialInputs(array $data): array
    {
        unset(
            $data['commercial_rate_source'],
            $data['commercial_note'],
            $data['commercial_effective_from'],
            $data['plus_discount_percent'],
        );

        return $data;
    }

    private function percent(mixed $value): float
    {
        $number = is_numeric($value) ? (float) $value : 0.0;

        return max(0, min(100, $number));
    }

    private function formatPercent(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.').'%';
    }
}
