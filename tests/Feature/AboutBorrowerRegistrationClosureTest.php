<?php

namespace Tests\Feature;

use App\Livewire\Admin\CustomersTable;
use App\Models\Customer;
use App\Models\User;
use App\Services\PinService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class AboutBorrowerRegistrationClosureTest extends TestCase
{
    use RefreshDatabase;

    private function makeActiveBorrower(array $attrs = []): Customer
    {
        $user = User::factory()->create(['role' => 'borrower']);
        app(PinService::class)->setPin($user, '1234');

        return Customer::create(array_merge([
            'user_id' => $user->id,
            'customer_number' => 'CU-CL'.random_int(1000, 9999),
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Amina',
            'last_name' => 'Mwinyi',
            'gender' => 'female',
            'phone' => '255700'.random_int(100000, 999999),
            'membership_status' => 'active',
            'membership_expires_at' => now()->addYear(),
        ], $attrs));
    }

    public function test_operational_members_list_excludes_pending_registration_drafts(): void
    {
        $active = $this->makeActiveBorrower([
            'first_name' => 'Active',
            'last_name' => 'Member',
            'phone' => '255711111001',
        ]);
        $draftUser = User::factory()->needsPinSetup()->create([
            'role' => 'borrower',
            'is_active' => false,
            'password' => Hash::make('Password1!'),
            'phone' => '255711111002',
        ]);
        Customer::create([
            'user_id' => $draftUser->id,
            'customer_number' => 'CU-DR'.random_int(1000, 9999),
            'type' => 'individual',
            'status' => 'pending',
            'first_name' => 'Draft',
            'last_name' => 'Only',
            'phone' => '255711111002',
        ]);

        Livewire::test(CustomersTable::class)
            ->assertSee('Active Member')
            ->assertDontSee('Draft Only');

        Livewire::test(CustomersTable::class)
            ->set('status', 'pending')
            ->assertSee('Draft Only')
            ->assertDontSee('Active Member');

        $this->assertSame('pending', Customer::where('phone', '255711111002')->value('status'));
        $this->assertSame('active', $active->fresh()->status);
    }

    public function test_personal_page_scopes_draft_owner_and_shows_canonical_about_me(): void
    {
        $customer = $this->makeActiveBorrower([
            'date_of_birth' => '1990-03-12',
        ]);
        $this->actingAs($customer->user);

        $html = $this->get(route('site.borrower.profile', ['section' => 'personal']))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-kf-draft-owner="customer:'.$customer->id.'"', $html);
        $this->assertStringContainsString('name="kf-draft-owner"', $html);
        $this->assertStringContainsString('content="customer:'.$customer->id.'"', $html);
        $this->assertStringContainsString('Amina Mwinyi', $html);
        $this->assertStringContainsString('12 Mar 1990', $html);
        $this->assertStringContainsString(__('borrower.profile.hub.remaining_short'), $html);
        $tabs = file_get_contents(resource_path('views/site/borrower/profile/_tabs.blade.php'));
        $this->assertStringContainsString('sectionGaps', $tabs);
        $this->assertStringContainsString('remaining_short', $tabs);
    }

    public function test_member_b_personal_page_does_not_render_member_a_family_or_kin(): void
    {
        $a = $this->makeActiveBorrower([
            'first_name' => 'Alpha',
            'last_name' => 'Leak',
            'phone' => '255722200001',
            'marital_status' => 'married',
            'number_of_children' => 2,
            'spouse_first_name' => 'SpouseAlpha',
            'spouse_last_name' => 'UniqueZZZ',
            'nok_first_name' => 'KinAlpha',
            'nok_last_name' => 'UniqueYYY',
            'nok_phone' => '255733300001',
            'nok_relationship' => 'sibling',
            'nok_region' => 'Dar es Salaam',
            'nok_district' => 'Ilala',
            'nok_street' => 'Alpha Kin Street 99',
        ]);
        $b = $this->makeActiveBorrower([
            'first_name' => 'Beta',
            'last_name' => 'Clean',
            'phone' => '255722200002',
            'marital_status' => null,
            'number_of_children' => null,
            'spouse_first_name' => null,
            'spouse_last_name' => null,
            'nok_first_name' => null,
            'nok_last_name' => null,
            'nok_phone' => null,
            'nok_relationship' => null,
            'nok_region' => null,
            'nok_district' => null,
            'nok_street' => null,
        ]);

        $this->actingAs($b->user);
        $html = $this->get(route('site.borrower.profile', ['section' => 'personal']))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Beta Clean', $html);
        $this->assertStringNotContainsString('SpouseAlpha', $html);
        $this->assertStringNotContainsString('UniqueZZZ', $html);
        $this->assertStringNotContainsString('KinAlpha', $html);
        $this->assertStringNotContainsString('UniqueYYY', $html);
        $this->assertStringNotContainsString('Alpha Kin Street 99', $html);
        $this->assertStringContainsString('customer:'.$b->id, $html);
        $this->assertStringNotContainsString('customer:'.$a->id, $html);

        // Empty Family / Kin must offer Add, not a populated View of foreign values.
        $this->assertMatchesRegularExpression('/id="profile-family"[\s\S]*?'.preg_quote(__('borrower.profile.add_details'), '/').'/', $html);
        $this->assertMatchesRegularExpression('/id="profile-kin"[\s\S]*?'.preg_quote(__('borrower.profile.add_details'), '/').'/', $html);
    }

    public function test_about_dob_autosave_and_signature_return_view_fields(): void
    {
        $customer = $this->makeActiveBorrower(['date_of_birth' => null]);
        $this->actingAs($customer->user);

        $dob = $this->withHeaders([
            'X-KF-Autosave' => '1',
            'Accept' => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
        ])->put(route('site.borrower.profile.update', ['section' => 'personal']), [
            'focus' => 'about',
            'date_of_birth' => '1988-07-04',
        ]);
        $dob->assertOk()->assertJsonPath('ok', true);
        $this->assertSame('04 Jul 1988', $dob->json('view_fields.date_of_birth'));
        $this->assertSame('Amina Mwinyi', $dob->json('view_fields.full_name'));
        $this->assertSame('1988-07-04', optional($customer->fresh()->date_of_birth)->format('Y-m-d'));

        $png = 'data:image/png;base64,'.base64_encode(base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));
        $sig = $this->withHeaders([
            'X-KF-Autosave' => '1',
            'Accept' => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
        ])->put(route('site.borrower.profile.update', ['section' => 'personal']), [
            'focus' => 'signature',
            'signature_data' => $png,
            'signer_name' => 'Amina Mwinyi',
        ]);
        $sig->assertOk()->assertJsonPath('ok', true);
        $this->assertNotEmpty($sig->json('view_fields.legal_signature_data'));
        $this->assertSame('Amina Mwinyi', $sig->json('view_fields.legal_signer_name'));
        $this->assertNotEmpty($customer->fresh()->legal_signature_data);
    }

    public function test_form_draft_js_keys_include_owner_segment(): void
    {
        $js = file_get_contents(resource_path('js/form-draft.js'));
        $this->assertStringContainsString('data-kf-draft-owner', $js);
        $this->assertStringContainsString("STORAGE_PREFIX + owner + ':'", $js);
        $this->assertStringNotContainsString("STORAGE_PREFIX + location.pathname + location.search + '#'", $js);
    }

    public function test_autosave_core_file_untouched_in_this_pass(): void
    {
        $this->assertFileExists(resource_path('js/kf-autosave.js'));
        // Guardrail: this closure pass must not rewrite the frozen autosave engine.
        $diff = trim((string) shell_exec('git diff --name-only HEAD -- resources/js/kf-autosave.js 2>/dev/null'));
        $this->assertSame('', $diff);
    }
}
