<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Partner;
use App\Support\MemberNumberFormatter;
use Illuminate\Support\Str;

class CardVerificationService
{
    /**
     * Predefined card types with fixed prefixes. User enters the suffix only (no dashes).
     *
     * @return array<string, array{label_key: string, prefix: string, kind: string, category?: string}>
     */
    public function types(): array
    {
        $partnerPrefix = app(PartnerCodeService::class)->prefix();
        $country = app(PartnerCodeService::class)->defaultCountryCode();

        $partnerTypes = [
            'affiliate' => 'site.card_verify.types.affiliate',
            'supplier' => 'site.card_verify.types.supplier',
            'debt_collector' => 'site.card_verify.types.debt_collector',
            'call_center' => 'site.card_verify.types.call_center',
            'legal_partner' => 'site.card_verify.types.legal_partner',
            'gps_installer' => 'site.card_verify.types.gps_installer',
            'insurance' => 'site.card_verify.types.insurance',
            'valuer' => 'site.card_verify.types.valuer',
            'auctioneer' => 'site.card_verify.types.auctioneer',
        ];

        $typeCodes = [
            'affiliate' => 'AF',
            'supplier' => 'SP',
            'debt_collector' => 'DC',
            'call_center' => 'CC',
            'legal_partner' => 'LP',
            'gps_installer' => 'GI',
            'insurance' => 'IN',
            'valuer' => 'VL',
            'auctioneer' => 'AU',
        ];

        $types = [
            'member' => [
                'label_key' => 'site.card_verify.types.member',
                'prefix' => MembershipService::PREFIX,
                'kind' => 'member',
            ],
        ];

        foreach ($partnerTypes as $category => $labelKey) {
            $code = $typeCodes[$category];
            $types[$category] = [
                'label_key' => $labelKey,
                'prefix' => "{$partnerPrefix}-{$code}-{$country}-",
                'kind' => 'partner',
                'category' => $category,
            ];
        }

        return $types;
    }

    /**
     * Build the stored ID from a predefined type + user-entered number (no dashes required).
     */
    public function composeId(string $type, string $number): ?string
    {
        $types = $this->types();
        if (! isset($types[$type])) {
            return null;
        }

        $clean = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $number) ?? '');
        if ($clean === '') {
            return null;
        }

        $prefix = $types[$type]['prefix'];
        $prefixClean = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $prefix) ?? '');

        // Allow pasting a full ID (with or without dashes) while a type is selected.
        if (str_starts_with($clean, $prefixClean)) {
            $suffix = substr($clean, strlen($prefixClean));
        } else {
            $suffix = $clean;
        }

        if ($suffix === '') {
            return null;
        }

        return $prefix.$suffix;
    }

    /**
     * @return array{
     *   found: bool,
     *   verified: bool,
     *   kind: string|null,
     *   type: string|null,
     *   id: string|null,
     *   id_display: string|null,
     *   name: string|null,
     *   role: string|null,
     *   photo_url: string|null,
     *   status_label: string|null,
     *   status_color: string,
     *   issued: string|null,
     *   expires: string|null,
     *   days_left: int|null,
     *   customer?: Customer|null,
     *   partner?: Partner|null,
     * }
     */
    public function lookup(string $type, string $number): array
    {
        $empty = [
            'found' => false,
            'verified' => false,
            'kind' => null,
            'type' => $type,
            'id' => null,
            'id_display' => null,
            'name' => null,
            'role' => null,
            'photo_url' => null,
            'status_label' => null,
            'status_color' => 'slate',
            'issued' => null,
            'expires' => null,
            'days_left' => null,
        ];

        $types = $this->types();
        if (! isset($types[$type])) {
            return $empty;
        }

        $meta = $types[$type];
        $id = $this->composeId($type, $number);
        if (! $id) {
            return $empty;
        }

        if ($meta['kind'] === 'member') {
            return $this->lookupMember($id, $type);
        }

        return $this->lookupPartner($id, $type, $meta['category'] ?? null);
    }

    /**
     * Parse a scanned QR payload (verify URL or full card number) into form fields.
     *
     * @return array{type: string, number: string}|null
     */
    public function parseScanPayload(string $raw): ?array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        $token = $raw;
        $preferPartner = false;
        $path = $raw;

        if (preg_match('#^https?://#i', $raw) || str_starts_with($raw, '/')) {
            if (preg_match('#^https?://#i', $raw)) {
                $path = (string) (parse_url($raw, PHP_URL_PATH) ?: '');
            }
            $path = rawurldecode(rtrim($path, '/'));
            if (preg_match('#/v/p/([^/]+)$#i', $path, $match)
                || preg_match('#/borrower/verify/p/([^/]+)$#i', $path, $match)) {
                $token = rawurldecode($match[1]);
                $preferPartner = true;
            } elseif (preg_match('#/borrower/verify/member/([^/]+)$#i', $path, $match)
                || preg_match('#/v/([^/]+)$#i', $path, $match)) {
                $token = rawurldecode($match[1]);
            }
        }

        $clean = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $token) ?? '');
        if ($clean === '') {
            return null;
        }

        $types = $this->types();
        $search = $preferPartner
            ? array_filter($types, fn (array $meta) => ($meta['kind'] ?? '') === 'partner')
            : $types;

        $matched = $this->matchTypeFromCleanId($search, $clean)
            ?? $this->matchTypeFromCleanId($types, $clean);

        if ($matched) {
            return $matched;
        }

        if ($preferPartner) {
            return null;
        }

        return ['type' => 'member', 'number' => $clean];
    }

    /**
     * @param  array<string, array{prefix: string, kind?: string}>  $types
     * @return array{type: string, number: string}|null
     */
    private function matchTypeFromCleanId(array $types, string $clean): ?array
    {
        foreach ($types as $type => $meta) {
            $prefixClean = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) ($meta['prefix'] ?? '')) ?? '');
            if ($prefixClean === '' || ! str_starts_with($clean, $prefixClean)) {
                continue;
            }
            $suffix = substr($clean, strlen($prefixClean));
            if ($suffix === '') {
                return null;
            }

            return ['type' => $type, 'number' => $suffix];
        }

        return null;
    }

    /**
     * Resolve a short-link token (with or without dashes) to a verification result.
     *
     * @return array<string, mixed>
     */
    public function resolveToken(string $token): array
    {
        $clean = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $token) ?? '');
        if ($clean === '') {
            return $this->lookup('member', '');
        }

        foreach ($this->types() as $type => $meta) {
            $prefixClean = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $meta['prefix']) ?? '');
            if ($prefixClean !== '' && str_starts_with($clean, $prefixClean)) {
                return $this->lookup($type, substr($clean, strlen($prefixClean)));
            }
        }

        // Legacy member short links often pass only the suffix or full KPF-TZ-…
        return $this->lookup('member', $token);
    }

    /**
     * Resolve a partner short-link token (prefix optional; dashes optional).
     *
     * @return array<string, mixed>
     */
    public function resolvePartnerToken(string $token): array
    {
        $clean = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $token) ?? '');
        if ($clean === '') {
            return $this->lookup('supplier', '');
        }

        foreach ($this->types() as $type => $meta) {
            if (($meta['kind'] ?? '') !== 'partner') {
                continue;
            }
            $prefixClean = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $meta['prefix']) ?? '');
            if ($prefixClean !== '' && str_starts_with($clean, $prefixClean)) {
                return $this->lookup($type, substr($clean, strlen($prefixClean)));
            }
        }

        $partner = Partner::query()
            ->whereRaw("REPLACE(UPPER(partner_number), '-', '') = ?", [$clean])
            ->first();

        if ($partner) {
            $category = (string) ($partner->category ?? 'supplier');

            return $this->lookup(
                isset($this->types()[$category]) ? $category : 'supplier',
                $clean
            );
        }

        return $this->lookup('supplier', $token);
    }

    /** @return array<string, mixed> */
    private function lookupMember(string $id, string $type): array
    {
        $normalized = MemberNumberFormatter::lookupKey($id);
        $customer = $normalized
            ? Customer::query()->where('member_no', $normalized)->first()
            : null;

        $display = MemberNumberFormatter::display($customer?->member_no ?? $id);
        $verified = $customer && $customer->hasMembership() && ! $customer->isMembershipExpired();

        $photoUrl = null;
        if ($customer) {
            $photoUrl = app(FaceVerificationService::class)->avatarUrl($customer);
        }

        $name = $customer
            ? strtoupper(trim(($customer->first_name ?? '').' '.($customer->last_name ?? '')))
            : null;

        return [
            'found' => (bool) $customer,
            'verified' => $verified,
            'kind' => 'member',
            'type' => $type,
            'id' => $customer?->member_no ?? $normalized,
            'id_display' => $display,
            'name' => $name ?: null,
            'role' => __('site.card_verify.roles.member'),
            'photo_url' => $photoUrl,
            'status_label' => $customer?->membershipStatusLabel(),
            'status_color' => $customer?->membershipStatusColor() ?? 'slate',
            'issued' => optional($customer?->membership_issued_at)->format('d M Y'),
            'expires' => optional($customer?->membership_expires_at)->format('d M Y'),
            'days_left' => $customer ? max(0, (int) $customer->membershipDaysRemaining()) : null,
            'customer' => $customer,
            'partner' => null,
        ];
    }

    /** @return array<string, mixed> */
    private function lookupPartner(string $id, string $type, ?string $category): array
    {
        $normalized = strtoupper(trim($id));
        $query = Partner::query()->where('partner_number', $normalized);
        if ($category) {
            $query->where('category', $category);
        }
        $partner = $query->first();

        // Fallback: match number ignoring category if typed ID is exact.
        if (! $partner) {
            $partner = Partner::query()->where('partner_number', $normalized)->first();
        }

        $membership = app(PartnerMembershipService::class);
        $verified = $partner
            && ($partner->status ?? '') === 'active'
            && $membership->isActive($partner);

        $photoUrl = $partner
            ? app(PartnerProfileService::class)->frontPhotoUrl($partner)
            : null;

        $statusColor = match (true) {
            $verified => 'green',
            (bool) $partner => 'orange',
            default => 'slate',
        };

        $statusLabel = match (true) {
            $verified => __('site.card_verify.status.active'),
            (bool) $partner => __('site.card_verify.status.inactive'),
            default => null,
        };

        $roleKey = 'site.card_verify.roles.'.($partner?->category ?? $type);
        $role = __($roleKey);
        if ($role === $roleKey) {
            $role = Str::headline(str_replace('_', ' ', (string) ($partner?->category ?? $type)));
        }

        return [
            'found' => (bool) $partner,
            'verified' => $verified,
            'kind' => 'partner',
            'type' => $type,
            'id' => $partner?->partner_number ?? $normalized,
            'id_display' => $partner?->partner_number ?? $normalized,
            'name' => $partner?->name ? strtoupper((string) $partner->name) : null,
            'role' => $role,
            'photo_url' => $photoUrl,
            'status_label' => $statusLabel,
            'status_color' => $statusColor,
            'issued' => optional($partner?->membership_started_at)->format('d M Y'),
            'expires' => optional($partner?->membership_expires_at)->format('d M Y'),
            'days_left' => $partner && $partner->membership_expires_at
                ? max(0, (int) now()->diffInDays($partner->membership_expires_at, false))
                : null,
            'customer' => null,
            'partner' => $partner,
        ];
    }
}
