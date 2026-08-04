<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\Setting;

class OperationalExpenseCategoryService
{
    /** Standard manual operational categories — partner payouts are automated elsewhere. */
    public function standards(): array
    {
        return [
            'rent'         => 'Rent',
            'salaries'     => 'Salaries & wages (payroll)',
            'utilities'    => 'Utilities',
            'marketing'    => 'Marketing',
            'insurance'    => 'Insurance',
            'office'       => 'Office & admin',
            'travel'       => 'Travel',
            'fuel'         => 'Fuel',
            'software'     => 'Software & subscriptions',
            'bank_charges' => 'Bank charges',
            'training'     => 'Training',
            'other'        => 'Other (specify)',
        ];
    }

    /** @return array<string, string> key => label */
    public function options(): array
    {
        $standards = $this->standards();
        $other = ['other' => $standards['other']];
        unset($standards['other']);

        $options = $standards;

        foreach ($this->storedCustomLabels() as $label) {
            $key = $this->slug($label);
            if ($key !== '' && $key !== 'other' && ! isset($options[$key])) {
                $options[$key] = $label;
            }
        }

        foreach ($this->historicalCategoryKeys() as $key) {
            if (! isset($options[$key]) && $key !== 'other') {
                $options[$key] = ucfirst(str_replace('_', ' ', $key));
            }
        }

        return $options + $other;
    }

    /** @return list<string> human labels stored in settings */
    public function storedCustomLabels(): array
    {
        $stored = Setting::get('finance.custom_expense_types', []);
        if (! is_array($stored)) {
            $stored = [];
        }

        return array_values(array_unique(array_filter(array_map(
            fn ($v) => trim((string) $v),
            $stored
        ))));
    }

    /** @return list<string> */
    public function historicalCategoryKeys(): array
    {
        $standards = $this->standards();

        return Expense::query()
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->distinct()
            ->pluck('category')
            ->map(fn ($c) => (string) $c)
            ->reject(fn (string $c) => array_key_exists($c, $standards))
            ->values()
            ->all();
    }

    public function rememberCustomType(string $label): void
    {
        $label = trim($label);
        if ($label === '') {
            return;
        }

        $types = $this->storedCustomLabels();
        if (! in_array($label, $types, true)) {
            $types[] = $label;
            Setting::set('finance.custom_expense_types', array_values($types));
        }
    }

    public function resolveCategory(?string $selected, ?string $customLabel): string
    {
        $selected = trim((string) $selected);
        $customLabel = trim((string) $customLabel);

        if ($selected === 'other' || $selected === '' || $selected === 'custom') {
            if ($customLabel === '') {
                return 'other';
            }
            $this->rememberCustomType($customLabel);

            return $this->slug($customLabel);
        }

        return $selected !== '' ? $selected : 'other';
    }

    public function labelFor(string $category): string
    {
        $standards = $this->standards();
        if (isset($standards[$category])) {
            return $standards[$category];
        }

        foreach ($this->storedCustomLabels() as $type) {
            if ($this->slug($type) === $category) {
                return $type;
            }
        }

        return ucfirst(str_replace('_', ' ', $category));
    }

    public function defaultGlCode(string $category): string
    {
        return match ($category) {
            'rent' => '5060',
            'salaries' => '5070',
            'utilities' => '5080',
            'marketing' => '5090',
            'insurance' => '5100',
            'office', 'software', 'training' => '5110',
            'travel', 'fuel' => '5120',
            'bank_charges' => '5050',
            default => '5050',
        };
    }

    private function slug(string $label): string
    {
        $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '_', $label) ?? ''));

        return trim($slug, '_') ?: 'other';
    }
}
