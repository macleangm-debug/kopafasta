<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use App\Services\PinService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase36FeatureTest extends TestCase
{
    use RefreshDatabase;

    private function completeBorrower(string $suffix = '001'): Customer
    {
        $user = User::factory()->create(['role' => 'borrower']);
        app(PinService::class)->setPin($user, '1234');

        return Customer::create([
            'user_id'               => $user->id,
            'customer_number'       => 'CU-P36-'.$suffix,
            'type'                  => 'individual',
            'status'                => 'active',
            'first_name'            => 'Complete',
            'last_name'             => 'Borrower',
            'phone'                 => '2557123490'.substr($suffix, -2),
            'membership_status'     => 'active',
            'membership_expires_at' => now()->addYear(),
        ]);
    }

    public function test_swahili_guarantors_and_membership_strings_are_available(): void
    {
        $this->assertSame('Wadhamini wangu', __('borrower.guarantors_page.title', [], 'sw'));
        $this->assertSame('Taarifa za kibinafsi', __('borrower.membership_page.personal', [], 'sw'));
        $this->assertSame('Tuma ombi la mdhamini', __('borrower.guarantors_page.send_request', [], 'sw'));
        $this->assertSame('Fungua kituo cha rufaa →', __('borrower.membership_page.open_referrals', [], 'sw'));
    }

    public function test_guarantors_page_shows_translated_copy(): void
    {
        $customer = $this->completeBorrower('010');

        $this->actingAs($customer->user)
            ->get(route('site.borrower.guarantors'))
            ->assertOk()
            ->assertSee(__('borrower.guarantors_page.title'), false)
            ->assertSee(__('borrower.guarantors_page.subtitle'), false)
            ->assertSee(__('borrower.guarantors_page.add_title'), false)
            ->assertSee(__('borrower.guarantors_page.linked_title'), false)
            ->assertSee(__('borrower.guarantors_page.empty'), false);
    }

    public function test_guarantors_page_uses_wide_layout(): void
    {
        $customer = $this->completeBorrower('020');

        $this->actingAs($customer->user)
            ->get(route('site.borrower.guarantors'))
            ->assertOk()
            ->assertSee('max-w-7xl', false);
    }

    public function test_guarantors_page_shows_relationship_options(): void
    {
        $customer = $this->completeBorrower('030');

        $response = $this->actingAs($customer->user)
            ->get(route('site.borrower.guarantors'))
            ->assertOk();

        foreach (__('borrower.profile.guarantor_relationship_options') as $label) {
            $response->assertSee($label, false);
        }
    }

    public function test_membership_page_shows_translated_section_labels(): void
    {
        $customer = $this->completeBorrower('040');

        $this->actingAs($customer->user)
            ->get(route('site.membership.show'))
            ->assertOk()
            ->assertSee(__('borrower.membership_page.personal'), false)
            ->assertSee(__('borrower.membership_page.activity'), false)
            ->assertSee(__('borrower.membership_page.residence'), false)
            ->assertSee(__('borrower.membership_page.kyc'), false)
            ->assertSee(__('borrower.membership_page.history_title'), false);
    }

    public function test_membership_page_uses_wide_layout(): void
    {
        $customer = $this->completeBorrower('050');

        $this->actingAs($customer->user)
            ->get(route('site.membership.show'))
            ->assertOk()
            ->assertSee('max-w-7xl', false)
            ->assertSee(__('borrower.membership.card_title'), false);
    }
}
