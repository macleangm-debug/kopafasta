<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use App\Services\PinService;
use App\Services\ProfileCompletionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IdentityClosureVisiblePathTest extends TestCase
{
    use RefreshDatabase;

    private function borrower(): Customer
    {
        $user = User::factory()->create(['role' => 'borrower']);
        app(PinService::class)->setPin($user, '1234');

        return Customer::create([
            'user_id' => $user->id,
            'customer_number' => 'CU-ID'.random_int(1000, 9999),
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Asha',
            'last_name' => 'Juma',
            'phone' => '255700'.random_int(100000, 999999),
            'membership_status' => 'active',
            'membership_expires_at' => now()->addYear(),
            'national_id' => '19900101123456789012',
            'nida_verification_status' => 'verified',
            'identity_locked' => true,
            'face_verification_status' => 'pending',
            'no_physical_nida_card' => false,
        ]);
    }

    public function test_personal_profile_renders_one_national_id_holder_markup(): void
    {
        $customer = $this->borrower();

        $html = $this->actingAs($customer->user)
            ->get(route('site.borrower.profile', ['section' => 'personal', 'focus' => 'id_images']))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-kf-national-id-holder', $html);
        $this->assertStringContainsString(__('borrower.profile.national_id_holder_hint'), $html);
        $this->assertStringContainsString('nida-holder-replace', $html);
        $this->assertStringContainsString(__('borrower.profile.id_images_title'), $html);
    }

    public function test_saved_nida_number_does_not_surface_as_add_number_gap(): void
    {
        $customer = $this->borrower();
        $sections = app(ProfileCompletionService::class)->displaySections($customer, true);
        $keys = collect($sections)->pluck('key')->all();

        $this->assertNotContains('nida_number', $keys);
        $this->assertNotContains('identity', $keys);
    }

    public function test_saved_nida_number_card_uses_number_not_uploads_for_complete(): void
    {
        $blade = file_get_contents(resource_path('views/site/borrower/profile/personal.blade.php'));
        $this->assertStringContainsString(':complete="$nidaSaved"', $blade);
        $this->assertStringContainsString(':empty="! $nidaSaved"', $blade);
        $this->assertStringNotContainsString(':complete="$hasIdentity"', $blade);
        $this->assertStringContainsString('empty-opens-view', $blade);
        $this->assertStringContainsString('idImagesDefaultEdit', $blade);
    }

    public function test_personal_profile_with_saved_nida_shows_complete_not_add_on_identity_card(): void
    {
        $customer = $this->borrower();

        $html = $this->actingAs($customer->user)
            ->get(route('site.borrower.profile', ['section' => 'personal', 'focus' => 'identity']))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString($customer->national_id, $html);
        // Complete tick SSR uses section_complete; Add CTA only when empty.
        $this->assertStringContainsString(__('borrower.profile.section_complete'), $html);
        $this->assertStringContainsString('data-kf-national-id-holder', $html);
        $this->assertStringContainsString('data-kf-nida-next-side', $html);
    }

    public function test_face_wizard_clears_uploading_before_finalize_after_replace(): void
    {
        $blade = file_get_contents(resource_path('views/components/site/face-verification-wizard.blade.php'));
        $this->assertStringContainsString("this.isUploading = false;", $blade);
        $this->assertStringContainsString('Clear uploading BEFORE finalize', $blade);
        $this->assertStringContainsString("window.addEventListener('face-retake-angle'", $blade);
        // Listener registered before the early-return all-done path.
        $initPos = strpos($blade, 'async init()');
        $listenerPos = strpos($blade, "window.addEventListener('face-retake-angle'", $initPos ?: 0);
        $earlyReturnMarker = strpos($blade, 'this.stepIndex >= this.steps.length', $initPos ?: 0);
        $this->assertNotFalse($listenerPos);
        $this->assertNotFalse($earlyReturnMarker);
        $this->assertLessThan($earlyReturnMarker, $listenerPos);
    }
}
