<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AffiliateSettingsEditorFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_affiliates_page_is_read_only_with_in_page_tabs(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.settings.affiliates'))
            ->assertOk()
            ->assertSee('Affiliate Settings', false)
            ->assertSee('Edit', false)
            ->assertSee('Membership', false)
            ->assertSee('Individual annual fee (TZS)', false)
            ->assertSee('Require affiliate membership', false)
            ->assertSee('editing: false', false)
            ->assertSee('Save affiliate settings', false)
            ->assertSee(':disabled="!editing"', false);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.settings.affiliates', ['tab' => 'membership']))
            ->assertOk()
            ->assertSee('name="_tab"', false)
            ->assertSee('value="membership"', false);
    }

    public function test_affiliates_page_opens_for_edit_when_requested(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.settings.affiliates', ['edit' => 1]))
            ->assertOk()
            ->assertSee('editing: true', false);
    }
}
