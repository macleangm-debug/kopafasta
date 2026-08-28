<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use App\Services\PinService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase38FeatureTest extends TestCase
{
    use RefreshDatabase;

    private function completeBorrower(string $suffix = '001'): Customer
    {
        $user = User::factory()->create(['role' => 'borrower']);
        app(PinService::class)->setPin($user, '1234');

        return Customer::create([
            'user_id'               => $user->id,
            'customer_number'       => 'CU-P38-'.$suffix,
            'type'                  => 'individual',
            'status'                => 'active',
            'first_name'            => 'Complete',
            'last_name'             => 'Borrower',
            'phone'                 => '2557123510'.substr($suffix, -2),
            'membership_status'     => 'active',
            'membership_expires_at' => now()->addYear(),
        ]);
    }

    /** @return list<string> */
    private function flatTranslationKeys(array $arr, string $prefix = ''): array
    {
        $keys = [];
        foreach ($arr as $key => $value) {
            $path = $prefix === '' ? (string) $key : $prefix.'.'.$key;
            if (is_array($value)) {
                $keys = array_merge($keys, $this->flatTranslationKeys($value, $path));
            } else {
                $keys[] = $path;
            }
        }

        return $keys;
    }

    public function test_swahili_borrower_lang_has_full_en_parity(): void
    {
        $en = include lang_path('en/borrower.php');
        $sw = include lang_path('sw/borrower.php');

        $missing = array_diff($this->flatTranslationKeys($en), $this->flatTranslationKeys($sw));

        $this->assertSame([], array_values($missing));
    }

    public function test_swahili_delivery_arc_spot_checks_remain_available(): void
    {
        $this->assertSame('Mkataba wa mkopo', __('borrower.contract.page_title', [], 'sw'));
        $this->assertSame('Wadhamini wangu', __('borrower.guarantors_page.title', [], 'sw'));
        $this->assertSame('Maendeleo', __('borrower.kyc_page.progress', [], 'sw'));
        $this->assertSame('Omba mali', __('borrower.marketplace.reserve_title', [], 'sw'));
    }

    public function test_membership_page_referral_section_uses_translated_copy(): void
    {
        $customer = $this->completeBorrower('010');

        $this->actingAs($customer->user)
            ->get(route('site.borrower.engagement', ['tab' => 'referrals']))
            ->assertOk()
            ->assertSee(__('borrower.referrals.your_code'), false)
            ->assertSee($customer->referral_code ?? '', false);
    }

    public function test_kyc_page_shows_account_type_badge(): void
    {
        $customer = $this->completeBorrower('020');

        $this->actingAs($customer->user)
            ->get(route('site.borrower.kyc'))
            ->assertOk()
            ->assertSee(__('borrower.kyc_page.kinds.individual'), false)
            ->assertSee(__('borrower.kyc_page.progress'), false);
    }

    public function test_guarantors_confirm_dialog_uses_translated_strings(): void
    {
        $customer = $this->completeBorrower('030');

        $this->actingAs($customer->user)
            ->get(route('site.borrower.guarantors'))
            ->assertOk()
            ->assertSee(__('borrower.guarantors_page.confirm_title'), false)
            ->assertSee(__('borrower.guarantors_page.confirm_message'), false)
            ->assertSee(__('borrower.guarantors_page.confirm_label'), false);
    }

    public function test_dashboard_wide_layout_regression(): void
    {
        $customer = $this->completeBorrower('040');

        $this->actingAs($customer->user)
            ->get(route('site.borrower.dashboard'))
            ->assertOk()
            ->assertSee('max-w-7xl', false)
            ->assertSee(__('borrower.welcome'), false);
    }
}
