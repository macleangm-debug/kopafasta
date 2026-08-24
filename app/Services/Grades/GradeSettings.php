<?php

namespace App\Services\Grades;

use App\Models\GradeRuleVersion;
use App\Models\Setting;

class GradeSettings
{
    public function rules(): array
    {
        $stored = Setting::get('customer_grades.rules');
        $defaults = config('customer_grades');

        return is_array($stored) ? array_replace_recursive($defaults, $stored) : $defaults;
    }

    public function save(array $rules, ?int $actorId = null): GradeRuleVersion
    {
        $merged = array_replace_recursive(config('customer_grades'), $rules);
        Setting::set('customer_grades.rules', $merged);

        $version = (int) GradeRuleVersion::query()->max('version') + 1;

        return GradeRuleVersion::query()->create([
            'version' => $version,
            'rules' => $merged,
            'created_by' => $actorId,
            'activated_at' => now(),
        ]);
    }

    public function currentVersion(): ?GradeRuleVersion
    {
        return GradeRuleVersion::query()->latest('version')->first();
    }

    public function countryBands(string $country): array
    {
        $rules = $this->rules();
        $code = strtoupper($country ?: 'TZ');

        return $rules['country_bands'][$code] ?? $rules['country_bands']['TZ'];
    }
}
