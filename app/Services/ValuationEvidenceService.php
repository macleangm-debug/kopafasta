<?php

namespace App\Services;

use App\Models\Setting;

class ValuationEvidenceService
{
    public function family(?string $assetType): string
    {
        return match ((string) $assetType) {
            'land' => 'land',
            'house', 'building' => 'building',
            default => 'vehicle',
        };
    }

    /**
     * Ordered checklist for a valuer: required angles first, then optional.
     *
     * @return list<array{angle: string, label: string, guidance: string, required: bool}>
     */
    public function checklist(?string $assetType): array
    {
        $family = $this->family($assetType);
        $spec = $this->spec($family);
        $out = [];

        foreach ($spec['required'] as $angle) {
            $out[] = $this->item($angle, true);
        }
        foreach ($spec['optional'] as $angle) {
            $out[] = $this->item($angle, false);
        }

        return $out;
    }

    /** @return array<string, string> */
    public function labels(?string $assetType, bool $requiredOnly = false): array
    {
        $labels = [];
        foreach ($this->checklist($assetType) as $item) {
            if ($requiredOnly && ! $item['required']) {
                continue;
            }
            $labels[$item['angle']] = $item['label'];
        }

        return $labels;
    }

    /** @return list<string> */
    public function requiredAngles(?string $assetType): array
    {
        return array_keys($this->labels($assetType, requiredOnly: true));
    }

    public function guidance(string $angle): string
    {
        $key = 'site.partner_portal.valuation_guide_'.$angle;

        return trans()->has($key) ? __($key) : __('site.partner_portal.valuation_guide_default');
    }

    public function labelFor(string $angle): string
    {
        $lookup = ['back' => 'rear'][$angle] ?? $angle;
        $key = 'site.partner_portal.valuation_angle_'.$lookup;

        return trans()->has($key) ? __($key) : \Illuminate\Support\Str::headline(str_replace('_', ' ', $angle));
    }

    /**
     * @return array{required: list<string>, optional: list<string>}
     */
    public function spec(string $family): array
    {
        $defaults = config('valuation_evidence.'.$family, config('valuation_evidence.vehicle'));
        $stored = Setting::group('valuation_evidence') ?? [];

        $required = $this->parseList($stored[$family.'.required'] ?? null) ?? ($defaults['required'] ?? []);
        $optional = $this->parseList($stored[$family.'.optional'] ?? null) ?? ($defaults['optional'] ?? []);

        return [
            'required' => array_values(array_filter($required, fn ($v) => is_string($v) && $v !== '')),
            'optional' => array_values(array_filter($optional, fn ($v) => is_string($v) && $v !== '')),
        ];
    }

    /** @return array{angle: string, label: string, guidance: string, required: bool} */
    private function item(string $angle, bool $required): array
    {
        return [
            'angle' => $angle,
            'label' => $this->labelFor($angle),
            'guidance' => $this->guidance($angle),
            'required' => $required,
        ];
    }

    /** @return list<string>|null */
    private function parseList(mixed $value): ?array
    {
        if (is_array($value)) {
            return array_values(array_map('strval', $value));
        }
        if (is_string($value) && trim($value) !== '') {
            return array_values(array_filter(array_map('trim', explode(',', $value))));
        }

        return null;
    }
}
