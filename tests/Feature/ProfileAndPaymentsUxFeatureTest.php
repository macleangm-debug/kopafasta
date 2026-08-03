<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\User;
use App\Services\PinService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileAndPaymentsUxFeatureTest extends TestCase
{
    use RefreshDatabase;

    private function makeCustomer(): Customer
    {
        $user = User::factory()->create(['role' => 'borrower']);
        app(PinService::class)->setPin($user, '1234');

        return Customer::create([
            'user_id'               => $user->id,
            'customer_number'       => 'CU-PPUX-'.random_int(100, 999),
            'type'                  => 'individual',
            'status'                => 'active',
            'first_name'            => 'Profile',
            'last_name'             => 'Polish',
            'phone'                 => '+255700'.random_int(100000, 999999),
            'membership_status'     => 'active',
            'membership_issued_at'  => now(),
            'membership_expires_at' => now()->addYear(),
        ]);
    }

    public function test_personal_profile_shows_nida_id_images_and_face(): void
    {
        $customer = $this->makeCustomer();

        $this->actingAs($customer->user)
            ->get(route('site.borrower.profile', ['section' => 'personal']))
            ->assertOk()
            ->assertDontSee(__('borrower.nida.verify_button'), false)
            ->assertSee(__('borrower.profile.add_details'), false)
            ->assertSee(__('borrower.nida.number'), false)
            ->assertSee('nida-boxes', false)
            ->assertSee(__('borrower.profile.id_images_title'), false)
            ->assertSee(__('borrower.nida.face_title'), false);
    }

    public function test_residence_and_address_fields_use_mobile_pickers(): void
    {
        $customer = $this->makeCustomer();

        $this->actingAs($customer->user)
            ->get(route('site.borrower.profile', ['section' => 'residence']))
            ->assertOk()
            ->assertSee(__('borrower.profile.add_details'), false)
            ->assertSee('regionPickerOpen', false)
            ->assertSee('districtPickerOpen', false);
    }

    public function test_kin_relationship_options_are_translated_in_swahili(): void
    {
        $customer = $this->makeCustomer();
        app()->setLocale('sw');

        $html = $this->actingAs($customer->user)
            ->get(route('site.borrower.profile', ['section' => 'personal', 'focus' => 'kin']))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString(__('kin.relationships.spouse', [], 'sw'), $html);
        $this->assertStringContainsString(__('kin.relationships.parent', [], 'sw'), $html);
    }

    public function test_saved_national_id_is_readonly_and_shows_photo_fields(): void
    {
        $customer = $this->makeCustomer();
        $customer->update(['national_id' => '19800101123456789012']);

        $html = $this->actingAs($customer->user)
            ->get(route('site.borrower.profile', ['section' => 'personal']))
            ->assertOk()
            ->assertSee(__('borrower.nida.saved_locked_title'), false)
            ->assertSee(__('borrower.profile.nida_front'), false)
            ->assertSee(__('borrower.profile.nida_back'), false)
            ->getContent();

        $this->assertStringContainsString('readonly', $html);

        $this->actingAs($customer->user)
            ->put(route('site.borrower.profile.update', ['section' => 'personal']), [
                'focus'      => 'identity',
                'national_id'=> '19900101123456789099',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('national_id');

        $this->assertSame('19800101123456789012', $customer->fresh()->national_id);
    }

    public function test_incomplete_hub_sections_use_add_action_label(): void
    {
        $customer = $this->makeCustomer();
        $cards = app(\App\Services\ProfileSectionBuilderService::class)->hubCards($customer);
        $personal = collect($cards)->firstWhere('key', 'personal');

        $this->assertNotNull($personal);
        $this->assertNotSame('complete', $personal['status']);
        $this->assertSame(__('borrower.profile.hub.add'), $personal['action_label']);
    }

    public function test_hub_cards_stay_main_categories_even_with_field_definitions(): void
    {
        $customer = $this->makeCustomer();

        \App\Models\ProfileSectionDefinition::create([
            'key'          => 'national_id_field',
            'icon'         => '🪪',
            'name_en'      => 'National ID',
            'name_sw'      => 'NIDA',
            'is_required'  => true,
            'is_active'    => true,
            'display_order'=> 1,
            'input_type'   => 'text',
            'metadata'     => ['maps_to' => 'personal'],
        ]);
        \App\Models\ProfileSectionDefinition::create([
            'key'          => 'face_field',
            'icon'         => '🙂',
            'name_en'      => 'Facial Verification',
            'name_sw'      => 'Uthibitishaji wa uso',
            'is_required'  => true,
            'is_active'    => true,
            'display_order'=> 2,
            'input_type'   => 'file_upload',
            'metadata'     => ['maps_to' => 'personal'],
        ]);
        \App\Models\ProfileSectionDefinition::create([
            'key'          => 'kin_field',
            'icon'         => '👪',
            'name_en'      => 'Next of Kin',
            'name_sw'      => 'Ndugu wa karibu',
            'is_required'  => false,
            'is_active'    => true,
            'display_order'=> 3,
            'input_type'   => 'text',
            'metadata'     => ['maps_to' => 'personal'],
        ]);

        $cards = app(\App\Services\ProfileSectionBuilderService::class)->hubCards($customer);
        $keys = collect($cards)->pluck('key')->all();

        $this->assertSame(
            ['personal', 'activity', 'residence', 'payment', 'assets'],
            $keys
        );
        $this->assertEmpty(
            collect($cards)->where('key', '!=', 'personal')->pluck('description')->filter()->all()
        );
        $personal = collect($cards)->firstWhere('key', 'personal');
        $this->assertNotNull($personal['description']);

        $assets = collect($cards)->firstWhere('key', 'assets');
        $this->assertSame('optional', $assets['status']);
        $this->assertSame(__('borrower.profile.status.optional'), $assets['status_label']);
    }

    public function test_empty_collateral_page_explains_optional(): void
    {
        $customer = $this->makeCustomer();

        $this->actingAs($customer->user)
            ->get(route('site.borrower.profile', ['section' => 'assets']))
            ->assertOk()
            ->assertSee(__('borrower.profile.collateral_none_needed_title'), false)
            ->assertSee(__('borrower.profile.collateral_none_needed_body'), false);
    }

    public function test_payments_create_accepts_loan_query_and_uses_brand_ui(): void
    {
        $customer = $this->makeCustomer();
        $product = LoanProduct::create([
            'code'              => 'IL-PPUX',
            'name'              => 'Payments UX Product',
            'is_active'         => true,
            'interest_rate'     => 0.15,
            'min_amount'        => 100_000,
            'max_amount'        => 5_000_000,
            'tenure_min_months' => 3,
            'tenure_max_months' => 24,
        ]);

        $loan = Loan::create([
            'customer_id'         => $customer->id,
            'loan_product_id'     => $product->id,
            'loan_number'         => 'LN-PPUX-1',
            'principal_amount'    => 500_000,
            'approved_amount'     => 500_000,
            'outstanding_balance' => 400_000,
            'interest_rate'       => 0.15,
            'tenure_months'       => 12,
            'status'              => 'active',
        ]);

        $this->actingAs($customer->user)
            ->get(route('site.borrower.payments.create', ['loan' => $loan->id]))
            ->assertOk()
            ->assertSee('from-brand', false)
            ->assertSee('peer-checked:ring-brand', false)
            ->assertSee('value="'.$loan->id.'"', false)
            ->assertSee('selected', false);

        $this->actingAs($customer->user)
            ->get(route('site.borrower.payments'))
            ->assertOk()
            ->assertSee('from-brand', false)
            ->assertSee(__('borrower.payments_page.make_repayment'), false)
            ->assertSee(__('borrower.payments_page.history_title'), false);
    }

    public function test_residence_profile_save_persists_officer_and_address(): void
    {
        $customer = $this->makeCustomer();
        $customer->update([
            'region' => 'Dar es Salaam',
            'district' => 'Ilala',
            'street' => 'Old Street',
        ]);

        $this->actingAs($customer->user)
            ->put(route('site.borrower.profile.update', ['section' => 'residence']), [
                'focus' => 'verification',
                'region' => 'Dar es Salaam',
                'district' => 'Ilala',
                'ward' => 'Kariakoo',
                'street' => 'Uhuru Street 12',
                'lga_officer_name' => 'Macmillan Gomera',
                'lga_officer_position' => 'Afisa wa Mtaa',
                'lga_officer_phone' => '255712345678',
            ])
            ->assertRedirect(route('site.borrower.profile', [
                'section' => 'residence',
                'focus' => 'verification',
            ]).'#profile-residence-verification')
            ->assertSessionHas('status');

        $customer->refresh();
        $this->assertSame('Dar es Salaam', $customer->region);
        $this->assertSame('Ilala', $customer->district);
        $this->assertSame('Kariakoo', $customer->ward);
        $this->assertSame('Uhuru Street 12', $customer->street);
        $this->assertSame('Macmillan Gomera', $customer->lga_officer_name);
        $this->assertSame('Afisa wa Mtaa', $customer->lga_officer_position);
        $this->assertSame('255712345678', preg_replace('/\D+/', '', (string) $customer->lga_officer_phone));
    }

    public function test_residence_address_save_uses_address_focus(): void
    {
        $customer = $this->makeCustomer();

        $this->actingAs($customer->user)
            ->put(route('site.borrower.profile.update', ['section' => 'residence']), [
                'focus' => 'address',
                'region' => 'Arusha',
                'district' => 'Arusha City',
                'ward' => 'Sekei',
                'street' => 'Clock Tower Road',
            ])
            ->assertRedirect(route('site.borrower.profile', [
                'section' => 'residence',
                'focus' => 'address',
            ]).'#profile-residence-address')
            ->assertSessionHas('status');

        $customer->refresh();
        $this->assertSame('Arusha', $customer->region);
        $this->assertSame('Arusha City', $customer->district);
        $this->assertSame('Clock Tower Road', $customer->street);
    }

    public function test_residence_and_activity_save_forms_submit_without_confirm_wrapper(): void
    {
        $customer = $this->makeCustomer();

        $residence = $this->actingAs($customer->user)
            ->get(route('site.borrower.profile', ['section' => 'residence']))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('confirmForm($el', $residence);
        $this->assertStringContainsString('name="lga_officer_name"', $residence);
        $this->assertStringContainsString('method="POST"', $residence);
        // Nested remove forms break the parent Save button — remove uses a button + dynamic form.
        $this->assertStringNotContainsString('method="DELETE"', $residence);
        $this->assertStringContainsString('kfGatedSubmit', $residence);
        $this->assertStringContainsString(__('borrower.profile.save'), $residence);

        $activity = $this->actingAs($customer->user)
            ->get(route('site.borrower.profile', ['section' => 'activity']))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('confirmForm($el', $activity);

        $personal = $this->actingAs($customer->user)
            ->get(route('site.borrower.profile', ['section' => 'personal']))
            ->assertOk()
            ->getContent();

        // Contact/kin/identity saves should not wrap in the generic save confirm dialog.
        $this->assertStringNotContainsString(__('borrower.profile.save_confirm_title'), $personal);
    }
}
