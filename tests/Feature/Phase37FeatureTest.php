<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\LoanProduct;
use App\Models\User;
use App\Services\PinService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase37FeatureTest extends TestCase
{
    use RefreshDatabase;

    private function completeBorrower(string $suffix = '001'): Customer
    {
        $user = User::factory()->create(['role' => 'borrower']);
        app(PinService::class)->setPin($user, '1234');

        return Customer::create([
            'user_id'               => $user->id,
            'customer_number'       => 'CU-P37-'.$suffix,
            'type'                  => 'individual',
            'status'                => 'active',
            'first_name'            => 'Complete',
            'last_name'             => 'Borrower',
            'phone'                 => '2557123500'.substr($suffix, -2),
            'membership_status'     => 'active',
            'membership_expires_at' => now()->addYear(),
        ]);
    }

    public function test_swahili_kyc_face_and_dashboard_strings_are_available(): void
    {
        $this->assertSame('Uthibitisho wa KYC', __('borrower.kyc_page.title', [], 'sw'));
        $this->assertSame(
            'Uthibitisho wa uso wako umeidhinishwa. Unaweza kuomba mkopo.',
            __('borrower.face_verification_page.approved_hint', [], 'sw')
        );
        $this->assertSame(
            'Hakuna bidhaa za mkopo zinazopatikana kwa sasa.',
            __('borrower.dashboard_page.no_products', [], 'sw')
        );
    }

    public function test_kyc_page_uses_wide_layout_and_translated_copy(): void
    {
        $customer = $this->completeBorrower('010');

        $this->actingAs($customer->user)
            ->get(route('site.borrower.kyc'))
            ->assertOk()
            ->assertSee('max-w-7xl', false)
            ->assertSee(__('borrower.kyc_page.title'), false)
            ->assertSee(__('borrower.kyc_page.eyebrow'), false)
            ->assertSee(__('borrower.kyc_page.upload_required'), false);
    }

    public function test_face_verification_shows_translated_approved_copy(): void
    {
        $customer = $this->completeBorrower('020');
        $customer->update(['face_verification_status' => 'verified']);

        $this->actingAs($customer->user)
            ->get(route('site.borrower.face-verification'))
            ->assertOk()
            ->assertSee(__('borrower.face_verification_page.approved_hint'), false)
            ->assertSee(__('borrower.face_verification_page.apply_cta'), false)
            ->assertSee(__('borrower.face_verification_page.back_to_documents'), false);
    }

    public function test_dashboard_shows_translated_empty_states(): void
    {
        $customer = $this->completeBorrower('030');
        LoanProduct::query()->update(['is_active' => false]);

        $this->actingAs($customer->user)
            ->get(route('site.borrower.dashboard'))
            ->assertOk()
            ->assertSee(__('borrower.dashboard_page.no_products'), false)
            ->assertSee(__('borrower.dashboard_page.no_messages'), false);
    }

    public function test_marketplace_reservation_payment_form_strings_are_available(): void
    {
        $this->assertSame('Promo code (optional)', __('borrower.marketplace.promo_code_label'));
        $this->assertSame('PROMO2026', __('borrower.marketplace.promo_code_placeholder'));
        $this->assertSame(
            'Msimbo wa promo (si lazima)',
            __('borrower.marketplace.promo_code_label', [], 'sw')
        );
    }

    public function test_face_verification_rejected_banner_uses_translated_copy(): void
    {
        $customer = $this->completeBorrower('050');
        $customer->update([
            'face_verification_status' => 'rejected',
            'face_rejection_notes'     => 'Image too dark',
        ]);

        $this->actingAs($customer->user)
            ->get(route('site.borrower.face-verification'))
            ->assertOk()
            ->assertSee(__('borrower.face_verification_page.rejected_title'), false)
            ->assertSee(__('borrower.face_verification_page.rejected_hint'), false)
            ->assertSee('Image too dark', false);
    }
}
