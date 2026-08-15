<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\User;
use App\Services\PinRecoveryChallengeService;
use App\Services\PinService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase26FeatureTest extends TestCase
{
    use RefreshDatabase;

    private function completeBorrower(): Customer
    {
        $user = User::factory()->create(['role' => 'borrower']);
        app(PinService::class)->setPin($user, '1234');
        app(PinRecoveryChallengeService::class)->enroll($user, [
            'mother_first_name' => 'Asha',
            'primary_school' => 'Uhuru Primary',
            'nida_middle4' => '4582',
        ]);

        return Customer::create([
            'user_id'                  => $user->id,
            'customer_number'          => 'CU-P26-'.random_int(100, 999),
            'type'                     => 'individual',
            'status'                   => 'active',
            'first_name'               => 'Complete',
            'last_name'                => 'Borrower',
            'phone'                    => '2557123462'.random_int(10, 99),
            'membership_status'        => 'active',
            'membership_expires_at'    => now()->addYear(),
        ]);
    }

    public function test_swahili_offer_membership_and_support_strings_are_available(): void
    {
        $this->assertSame(
            'Kagua ofa ya mkopo',
            __('borrower.offer.title', [], 'sw')
        );
        $this->assertSame(
            'Kadi ya Mwanachama wa KopaFasta',
            __('borrower.membership.card_title', [], 'sw')
        );
        $this->assertSame(
            'Kituo cha msaada',
            __('borrower.support_page.title', [], 'sw')
        );
        $this->assertSame(
            'Nyaraka zangu',
            __('borrower.documents_page.title', [], 'sw')
        );
    }

    public function test_face_verification_wizard_attempts_camera_on_init(): void
    {
        $customer = $this->completeBorrower();

        $this->actingAs($customer->user)
            ->get(route('site.borrower.face-verification'))
            ->assertRedirect(route('site.borrower.profile', ['section' => 'personal', 'focus' => 'face']));

        $this->actingAs($customer->user)
            ->get(route('site.borrower.profile', ['section' => 'personal', 'focus' => 'face']))
            ->assertOk()
            ->assertSee('await this.startScan()', false);
    }

    public function test_admin_loan_product_form_includes_dynamic_tier_script(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-products.create'))
            ->assertOk()
            ->assertSee('function addRateTierRow()', false)
            ->assertSee('+ Add tier', false);
    }

    public function test_support_documents_and_loan_profile_use_wide_content_layout(): void
    {
        $customer = $this->completeBorrower();

        $this->actingAs($customer->user)
            ->get(route('site.borrower.support'))
            ->assertOk()
            ->assertSee('max-w-7xl', false)
            ->assertSee(__('borrower.support_page.title'), false);

        $this->actingAs($customer->user)
            ->get(route('site.borrower.documents'))
            ->assertOk()
            ->assertSee('max-w-7xl', false);

        $product = LoanProduct::create([
            'code'              => 'IL-P26',
            'name'              => 'Profile Layout Product',
            'is_active'         => true,
            'interest_rate'     => 0.15,
            'min_amount'        => 100_000,
            'max_amount'        => 5_000_000,
            'tenure_min_months' => 3,
            'tenure_max_months' => 24,
        ]);

        $application = LoanApplication::create([
            'customer_id'             => $customer->id,
            'loan_product_id'         => $product->id,
            'application_number'      => 'APP-P26-LAYOUT',
            'requested_amount'        => 500_000,
            'requested_tenure_months' => 12,
            'status'                  => 'submitted',
            'current_stage'           => 'submitted',
        ]);

        $this->actingAs($customer->user)
            ->get(route('site.borrower.application', $application))
            ->assertOk()
            ->assertSee('max-w-3xl', false);
    }

    public function test_membership_page_uses_wide_layout_and_translated_heading(): void
    {
        $customer = $this->completeBorrower();

        $this->actingAs($customer->user)
            ->get(route('site.borrower.profile', ['section' => 'membership']))
            ->assertOk()
            ->assertSee('max-w-7xl', false)
            ->assertSee(__('borrower.profile.panel_membership'), false);
    }
}
