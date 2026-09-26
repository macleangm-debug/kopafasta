<?php

namespace App\Services;

use App\Models\Partner;
use App\Models\Setting;
use Illuminate\Support\Str;

class PartnerCodeService
{
    /** @var array<string, string> */
    private const TYPE_CODES = [
        'affiliate'      => 'AF',
        'supplier'       => 'SP',
        'capital'        => 'CP',
        'gps_installer'  => 'GI',
        'insurance'      => 'IN',
        'valuer'         => 'VL',
        'towing'         => 'TW',
        'yard'           => 'YD',
        'auctioneer'     => 'AU',
        'call_center'    => 'CC',
        'debt_collector' => 'DC',
        'legal_partner'  => 'LP',
    ];

    /** @return array<string, string> */
    public function typeCodes(): array
    {
        return self::TYPE_CODES;
    }

    public function typeCode(string $category): string
    {
        return self::TYPE_CODES[$category]
            ?? strtoupper(substr(preg_replace('/[^a-z]/', '', $category) ?: 'XX', 0, 2));
    }

    public function prefixFor(string $category): string
    {
        return $this->prefix().'-'.$this->typeCode($category).'-'.$this->defaultCountryCode().'-';
    }

    public function prefix(): string
    {
        return strtoupper((string) Setting::get('partners.code_prefix', 'PT'));
    }

    public function defaultCountryCode(): string
    {
        return strtoupper((string) Setting::get('partners.default_country_code', 'TZ'));
    }

    public function generate(string $category): string
    {
        $typeCode = self::TYPE_CODES[$category] ?? strtoupper(substr(preg_replace('/[^a-z]/', '', $category) ?: 'XX', 0, 2));
        $country = $this->defaultCountryCode();
        $prefix = $this->prefix();

        do {
            $suffix = strtoupper(Str::random(4));
            $code = "{$prefix}-{$typeCode}-{$country}-{$suffix}";
        } while (Partner::query()->where('partner_number', $code)->exists());

        return $code;
    }

    public function ensure(Partner $partner): string
    {
        if (filled($partner->partner_number)) {
            return $partner->partner_number;
        }

        $code = $this->generate((string) $partner->category);
        $partner->update(['partner_number' => $code]);

        return $code;
    }
}
