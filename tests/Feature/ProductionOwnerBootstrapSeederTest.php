<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\ProductionOwnerBootstrapSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionOwnerBootstrapSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_is_skipped_outside_production(): void
    {
        $this->seed(ProductionOwnerBootstrapSeeder::class);

        $this->assertSame(0, User::query()->count());
    }

    public function test_init_script_never_imports_staging_or_triptz_dumps(): void
    {
        $script = file_get_contents(base_path('scripts/init-production-database.sh'));
        $this->assertStringContainsString('CONFIRM_PRODUCTION', $script);
        $this->assertStringContainsString('production:assert-clean', $script);
        $this->assertStringContainsString('ProductionOwnerBootstrapSeeder', $script);
        $this->assertStringContainsString('SafeConfigurationSeeder', $script);
        $this->assertStringNotContainsString('mysqldump', $script);
        $this->assertStringNotContainsString('kopafasta_staging', $script);
        $this->assertStringNotContainsString('MarketplaceAssetSeeder', $script);
        $this->assertStringNotContainsString('StagingUatSeeder', $script);
        $this->assertStringNotContainsString('DemoLoanSeeder', $script);
    }

    public function test_restore_rehearsal_replaces_the_database(): void
    {
        $script = file_get_contents(base_path('scripts/staging-restore-rehearsal.sh'));
        $this->assertStringContainsString('DROP DATABASE', $script);
        $this->assertStringContainsString('CREATE DATABASE', $script);
        $this->assertStringContainsString('_restore_probe', $script);
        $this->assertStringContainsString('Refusing restore rehearsal on production', $script);
        $this->assertStringContainsString('KITONGA', $script);
    }

    public function test_staging_marketplace_seeder_is_opt_in(): void
    {
        $script = file_get_contents(base_path('scripts/deploy.sh'));
        $this->assertStringContainsString('STAGING_SEED_MARKETPLACE', $script);
        $this->assertStringContainsString('MarketplaceAssetSeeder', $script);
        $this->assertStringContainsString('StagingUatSeeder', $script);
        $this->assertStringContainsString('== "staging"', $script);
    }
}
