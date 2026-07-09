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

    public function test_personal_profile_shows_nida_verify_and_add_details(): void
    {
        $customer = $this->makeCustomer();

        $this->actingAs($customer->user)
            ->get(route('site.borrower.profile', ['section' => 'personal']))
            ->assertOk()
            ->assertSee(__('borrower.nida.verify_button'), false)
            ->assertSee(__('borrower.profile.add_details'), false)
            ->assertSee(__('borrower.nida.number'), false)
            ->assertSee(__('borrower.nida.format_hint'), false);
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
            ->assertSee('peer-checked:border-brand', false)
            ->assertSee('value="'.$loan->id.'"', false)
            ->assertSee('selected', false);

        $this->actingAs($customer->user)
            ->get(route('site.borrower.payments'))
            ->assertOk()
            ->assertSee('from-brand', false)
            ->assertSee(__('borrower.payments_page.make_repayment'), false);
    }
}
