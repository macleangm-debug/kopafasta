<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\LoanProduct;
use App\Models\User;
use App\Services\PinRecoveryChallengeService;
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
        app(PinRecoveryChallengeService::class)->enroll($user, [
            'mother_first_name' => 'Asha',
            'primary_school' => 'Uhuru Primary',
            'nida_middle4' => '4582',
        ]);

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
        $this->assertSame('Uthibitisho wa wasifu', __('borrower.kyc_page.title', [], 'sw'));
        $this->assertSame(
            'Picha zako za uso zimeidhinishwa. Unaweza kuomba mkopo.',
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
            ->assertRedirect(route('site.borrower.profile', ['section' => 'personal', 'focus' => 'face']));

        $this->actingAs($customer->user)
            ->get(route('site.borrower.profile', ['section' => 'personal', 'focus' => 'face']))
            ->assertOk()
            ->assertSee(__('borrower.profile.edit_face_photos_hint'), false)
            ->assertSee(__('borrower.nida.face_replace'), false)
            ->assertSee('faceVerificationWizard', false);
    }

    public function test_dashboard_shows_translated_empty_states(): void
    {
        $customer = $this->completeBorrower('030');
        LoanProduct::query()->update(['is_active' => false]);

        $this->actingAs($customer->user)
            ->get(route('site.borrower.dashboard'))
            ->assertOk()
            ->assertSee(__('borrower.dashboard_page.no_products'), false);
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
            ->assertRedirect(route('site.borrower.profile', ['section' => 'personal', 'focus' => 'face']));

        $this->actingAs($customer->user)
            ->get(route('site.borrower.profile', ['section' => 'personal', 'focus' => 'face']))
            ->assertOk()
            ->assertSee('Image too dark', false)
            ->assertSee('faceVerificationWizard', false);
    }
}
