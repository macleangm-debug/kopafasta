<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPartnerCreateWizardStepGateFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_wizard_hides_excluded_steps_instead_of_clearing_them(): void
    {
        $wizard = file_get_contents(resource_path('views/components/admin/wizard.blade.php'));

        $this->assertStringContainsString('function hideExcludedStep', $wizard);
        $this->assertStringContainsString('hideExcludedStep(el)', $wizard);
        $this->assertStringContainsString('wizard-step-inactive', $wizard);
    }

    public function test_affiliate_create_form_excludes_coverage_gate_for_affiliates(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $html = $this->actingAs($admin, 'admin')
            ->get(route('admin.partners.create', ['category' => 'affiliate']))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('needsCoverage && ! isAffiliate', $html);
        $this->assertStringContainsString('Affiliate program', $html);
        $this->assertStringContainsString('Premium Affiliate', $html);
        $this->assertStringContainsString('x-show="isAffiliate"', $html);
    }
}
