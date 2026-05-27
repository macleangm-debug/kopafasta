<?php

namespace Database\Seeders;

use App\Models\IpRule;
use App\Services\IpRuleService;
use Illuminate\Database\Seeder;

class IpRuleSeeder extends Seeder
{
    public function run(): void
    {
        $denyCidrs = $this->parse(config('security.deny_cidrs'));
        $allowCidrs = $this->parse(config('security.allow_cidrs'));

        foreach ($denyCidrs as $cidr) {
            IpRule::updateOrCreate(
                ['cidr' => $cidr, 'mode' => IpRule::MODE_DENY],
                ['reason' => 'Seeded from SECURITY_DENY_CIDRS']
            );
        }

        foreach ($allowCidrs as $cidr) {
            IpRule::updateOrCreate(
                ['cidr' => $cidr, 'mode' => IpRule::MODE_ALLOW],
                ['reason' => 'Seeded from SECURITY_ALLOW_CIDRS']
            );
        }

        app(IpRuleService::class)->flush();
    }

    /**
     * @return array<int, string>
     */
    private function parse(mixed $value): array
    {
        if (is_array($value)) {
            $items = $value;
        } elseif (is_string($value) && $value !== '') {
            $items = explode(',', $value);
        } else {
            return [];
        }

        return array_values(array_filter(array_map(
            fn ($v) => is_string($v) ? trim($v) : null,
            $items
        ), fn ($v) => $v !== null && $v !== ''));
    }
}
