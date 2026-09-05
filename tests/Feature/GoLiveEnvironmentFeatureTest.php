<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ReleaseInfoService;
use App\Services\SeoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GoLiveEnvironmentFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_staging_is_never_indexable_even_if_seo_setting_is_on(): void
    {
        config(['seo.allow_indexing' => true, 'app.env' => 'staging', 'app.url' => 'https://staging.kopafasta.com']);
        app()->instance('env', 'staging');

        $this->assertFalse(app(SeoService::class)->environmentAllowsIndexing());
        $this->assertTrue(app(ReleaseInfoService::class)->isStaging());
        $this->assertTrue(app(ReleaseInfoService::class)->showsBanner());
    }

    public function test_production_does_not_show_a_staging_banner(): void
    {
        config(['app.env' => 'production', 'app.url' => 'https://www.kopafasta.co.tz', 'release.show_banner' => null]);
        app()->instance('env', 'production');

        $this->assertFalse(app(ReleaseInfoService::class)->isStaging());
        $this->assertTrue(app(ReleaseInfoService::class)->isProduction());
        $this->assertFalse(app(ReleaseInfoService::class)->showsBanner());
    }

    public function test_admin_system_page_shows_environment_and_commit(): void
    {
        $path = storage_path('app/release.json');
        file_put_contents($path, json_encode([
            'commit' => 'def5678abc',
            'version' => '2026.09.04',
            'environment' => 'staging',
            'deployed_at' => '2026-09-04T15:40:00Z',
        ]));

        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.settings.system'))
            ->assertOk()
            ->assertSee('System', false)
            ->assertSee('def5678a', false)
            ->assertSee('2026.09.04', false);

        @unlink($path);
    }

    public function test_authenticated_layouts_include_the_environment_banner_component(): void
    {
        $this->assertStringContainsString(
            'x-site.environment-banner',
            file_get_contents(resource_path('views/components/admin/layout.blade.php'))
        );
        $this->assertStringContainsString(
            'x-site.environment-banner',
            file_get_contents(resource_path('views/components/site/borrower-layout.blade.php'))
        );
        $this->assertStringContainsString(
            'x-site.environment-banner',
            file_get_contents(resource_path('views/components/site/partner-shell.blade.php'))
        );
    }

    public function test_safe_configuration_seeder_does_not_include_demo_data_classes(): void
    {
        $source = file_get_contents(database_path('seeders/SafeConfigurationSeeder.php'));
        $this->assertStringContainsString('NotificationTemplateSeeder', $source);
        $this->assertStringNotContainsString('DemoLoanSeeder', $source);
        $this->assertStringNotContainsString('CustomerSeeder', $source);
        $this->assertStringNotContainsString('DemoAffiliateSeeder', $source);
    }

    public function test_production_deploy_script_requires_an_approved_commit(): void
    {
        $script = file_get_contents(base_path('scripts/deploy.sh'));
        $this->assertStringContainsString('CONFIRM_PRODUCTION', $script);
        $this->assertStringContainsString('APPROVED_COMMIT', $script);
        $this->assertStringContainsString('git archive', $script);
        $this->assertStringContainsString('SafeConfigurationSeeder', $script);
        $this->assertStringContainsString('release.json', $script);
        $this->assertStringContainsString('STAGING_SEED_MARKETPLACE', $script);
    }

    public function test_production_examples_use_the_approved_co_tz_domain(): void
    {
        $env = file_get_contents(base_path('.env.production.example'));
        $nginx = file_get_contents(base_path('deploy/nginx-production.conf.example'));
        $this->assertStringContainsString('https://www.kopafasta.co.tz', $env);
        $this->assertStringContainsString('https://www.kopafasta.co.tz/webhooks/payin', $env);
        $this->assertStringNotContainsString('www.kopafasta.com', $env);
        $this->assertStringContainsString('www.kopafasta.co.tz', $nginx);
        $this->assertStringNotContainsString('www.kopafasta.com', $nginx);
    }

    public function test_github_production_workflow_is_manual_only(): void
    {
        $staging = file_get_contents(base_path('.github/workflows/deploy-staging.yml'));
        $production = file_get_contents(base_path('.github/workflows/deploy-production.yml'));
        $this->assertStringContainsString('workflow_dispatch', $staging);
        $this->assertStringContainsString('workflow_dispatch', $production);
        $this->assertStringNotContainsString("push:", $production);
        $this->assertStringContainsString('environment:', $production);
        $this->assertStringContainsString('name: production', $production);
        $this->assertStringContainsString('https://www.kopafasta.co.tz', $production);
        $this->assertStringContainsString('https://staging.kopafasta.com', $staging);
    }
}
