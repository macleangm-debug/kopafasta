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

    public function test_face_wizard_uses_review_not_saved_dead_end_when_pending(): void
    {
        $blade = file_get_contents(resource_path('views/components/site/face-verification-wizard.blade.php'));
        $this->assertStringContainsString("this.phase = 'review'", $blade);
        $this->assertStringContainsString('face-retake-angle', $blade);
        $status = file_get_contents(resource_path('views/components/site/face-verification-status.blade.php'));
        $this->assertStringContainsString('face-retake-angle', $status);
    }
}
