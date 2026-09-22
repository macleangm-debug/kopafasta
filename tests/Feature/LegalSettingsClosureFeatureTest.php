<?php

namespace Tests\Feature;

use App\Models\CompanySignatory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LegalSettingsClosureFeatureTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create([
            'role'  => 'admin',
            'email' => 'legal-closure@example.com',
        ]);
    }

    public function test_document_templates_show_preview_and_hide_add_template(): void
    {
        $this->actingAs($this->admin(), 'admin')
            ->get(route('admin.document-templates.index'))
            ->assertOk()
            ->assertSee('Offer Letter')
            ->assertSee('Decision Letter')
            ->assertSee('Loan Contract')
            ->assertSee('Preview')
            ->assertSee('Offer validity')
            ->assertSee('Arbitrary new document types are')
            ->assertDontSee('+ New template')
            ->assertDontSee('New template');
    }

    public function test_contracts_clauses_no_longer_duplicate_stamp_or_offer_validity(): void
    {
        $this->actingAs($this->admin(), 'admin')
            ->get(route('admin.settings.legal'))
            ->assertOk()
            ->assertSee('Contract sections')
            ->assertSee('Used in:')
            ->assertSee('Configured under Signatories')
            ->assertSee('Configured with Offer Letter')
            ->assertDontSee('name="offer_validity_days"')
            ->assertDontSee('name="stamp_image"');
    }

    public function test_signatory_form_requires_method_choice_not_both_workflows(): void
    {
        $this->actingAs($this->admin(), 'admin')
            ->get(route('admin.settings.signatories.create'))
            ->assertOk()
            ->assertSee('How would you like to add the signature?')
            ->assertSee('Draw signature')
            ->assertSee('Upload signature')
            ->assertSee('Remove image background');
    }

    public function test_delete_signatory_confirmation_copy_is_consequential(): void
    {
        CompanySignatory::create([
            'name' => 'Amina Hassan',
            'signatory_type' => 'ceo',
            'is_active' => true,
        ]);

        $this->actingAs($this->admin(), 'admin')
            ->get(route('admin.settings.signatories.index'))
            ->assertOk()
            ->assertSee('Delete signatory?')
            ->assertSee('Existing issued documents must remain unchanged')
            ->assertSee('Replace stamp');
    }

    public function test_stamp_replace_requires_confirmation_flag(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin(), 'admin')
            ->post(route('admin.settings.signatories.stamp'), [
                'stamp_image' => UploadedFile::fake()->image('stamp.png', 200, 200),
                'remove_stamp_background' => '1',
            ])
            ->assertSessionHasErrors('confirm_replace_stamp');
    }
}
