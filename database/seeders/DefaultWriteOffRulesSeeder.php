<?php

namespace Database\Seeders;

use App\Models\WriteOffRule;
use Illuminate\Database\Seeder;

class DefaultWriteOffRulesSeeder extends Seeder
{
    public function run(): void
    {
        WriteOffRule::firstOrCreate(
            ['name' => '90 Days — Recommend Write-Off'],
            [
                'days_past_due'              => 90,
                'min_outstanding'            => null,
                'max_outstanding'            => null,
                'require_committee_approval' => true,
                'auto_propose'               => true,
                'description'                => 'Auto-propose a recommended write-off after 90 days past due. Requires manager and finance approval before execution.',
                'is_active'                  => true,
            ],
        );

        WriteOffRule::firstOrCreate(
            ['name' => '180 Days — Escalate Priority'],
            [
                'days_past_due'              => 180,
                'min_outstanding'            => null,
                'max_outstanding'            => null,
                'require_committee_approval' => true,
                'auto_propose'               => false,
                'description'                => 'Escalate collection case priority at 180 days past due. Write-off still requires full approval workflow.',
                'is_active'                  => true,
            ],
        );
    }
}
