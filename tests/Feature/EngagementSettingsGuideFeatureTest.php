<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EngagementSettingsGuideFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_underwriting_settings_page_explains_borrower_impact(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.settings.engagement.underwriting'))
            ->assertOk()
            ->assertSee('How underwriting boosts work', false)
            ->assertSee('What the borrower sees', false)
            ->assertSee('Worked example', false)
            ->assertSee('Limit multiplier', false);
    }

    public function test_engagement_hub_lists_borrower_facing_outcomes(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.settings.engagement'))
            ->assertOk()
            ->assertSee('How engagement settings map to the borrower app', false)
            ->assertSee('Loan quote step', false);
    }
}
