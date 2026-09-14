<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use App\Services\PinService;
use App\Services\ProfileCompletionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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
        $this->assertStringContainsString('nidaDirectiveJourney', $html);
        $this->assertStringContainsString(__('borrower.profile.national_id_holder_hint'), $html);
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

    public function test_saved_nida_number_card_disables_edit(): void
    {
        $blade = file_get_contents(resource_path('views/site/borrower/profile/personal.blade.php'));
        $this->assertStringContainsString(':complete="$nidaSaved"', $blade);
        $this->assertStringContainsString(':edit-allowed="! $nidaSaved"', $blade);

        $customer = $this->borrower();
        $html = $this->actingAs($customer->user)
            ->get(route('site.borrower.profile', ['section' => 'personal', 'focus' => 'identity']))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString($customer->national_id, $html);
        $this->assertStringContainsString(__('borrower.profile.section_complete'), $html);
    }

    public function test_nida_front_only_upload_is_accepted_progressively(): void
    {
        Storage::fake('public');
        $this->seed(\Database\Seeders\KycDocumentTypeSeeder::class);
        $customer = $this->borrower();

        $this->actingAs($customer->user)
            ->post(route('site.borrower.profile.update', ['section' => 'personal']), [
                '_method' => 'PUT',
                'focus' => 'id_images',
                'national_id' => $customer->national_id,
                'national_id_front' => UploadedFile::fake()->image('front.jpg'),
            ], ['Accept' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('side', 'front')
            ->assertJsonPath('complete', false);

        $this->assertTrue(
            app(\App\Services\ProfileValidationService::class)->hasDocument($customer->fresh(), 'national_id_front')
        );
        $this->assertFalse(
            app(\App\Services\ProfileValidationService::class)->hasDocument($customer->fresh(), 'national_id_back')
        );
    }

    public function test_face_wizard_hides_start_when_all_angles_done(): void
    {
        $blade = file_get_contents(resource_path('views/components/site/face-verification-wizard.blade.php'));
        $this->assertStringContainsString("phase === 'intro' && !steps.every(s => s.done)", $blade);
        $this->assertStringContainsString('face-retake-angle', $blade);
        $this->assertStringContainsString('Clear uploading BEFORE finalize', $blade);
        $this->assertStringContainsString('kfShowInlineSaving', $blade);

        $single = file_get_contents(resource_path('views/components/site/single-image-document-upload.blade.php'));
        $this->assertStringContainsString('@document-source.window', $single);

        $picker = file_get_contents(resource_path('views/components/site/document-source-picker.blade.php'));
        $this->assertStringContainsString('document-source-open', $picker);
    }
}
