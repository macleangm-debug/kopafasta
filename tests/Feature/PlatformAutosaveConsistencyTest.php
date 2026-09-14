<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformAutosaveConsistencyTest extends TestCase
{
    use RefreshDatabase;

    private function borrowerWithProfile(): array
    {
        $user = User::factory()->create(['role' => 'borrower']);
        $customer = Customer::create([
            'user_id' => $user->id,
            'customer_number' => 'C-ASC'.random_int(100000, 999999),
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Asha',
            'last_name' => 'Juma',
            'phone' => '+255712'.random_int(100000, 999999),
            'email' => 'autosave-consistency@example.com',
        ]);

        return [$user, $customer];
    }

    public function test_contact_autosave_does_not_require_signature_data(): void
    {
        [$user] = $this->borrowerWithProfile();

        $response = $this->actingAs($user)->putJson(
            route('site.borrower.profile.update', ['section' => 'personal']),
            [
                'focus' => 'contact',
                'phone' => '255712000222',
                'email' => 'updated@example.com',
                // Leak simulation — must be ignored for non-signature focuses.
                'signature_data' => '',
                'signer_name' => '',
            ],
            ['X-KF-Autosave' => '1']
        );

        $response->assertOk()->assertJson(['ok' => true]);
        $this->assertDatabaseHas('customers', [
            'user_id' => $user->id,
            'phone' => '255712000222',
            'email' => 'updated@example.com',
        ]);
    }

    public function test_family_autosave_does_not_require_signature_data(): void
    {
        [$user] = $this->borrowerWithProfile();

        $response = $this->actingAs($user)->putJson(
            route('site.borrower.profile.update', ['section' => 'personal']),
            [
                'focus' => 'family',
                'marital_status' => 'single',
                'number_of_children' => 0,
                'signature_data' => '',
            ],
            ['X-KF-Autosave' => '1']
        );

        $response->assertOk()->assertJson(['ok' => true]);
        $this->assertDatabaseHas('customers', [
            'user_id' => $user->id,
            'marital_status' => 'single',
            'number_of_children' => 0,
        ]);
    }

    public function test_activity_autosave_does_not_require_signature_data(): void
    {
        [$user] = $this->borrowerWithProfile();

        $incomeKey = array_key_first(config('income_ranges', ['0-500000' => []]));

        $response = $this->actingAs($user)->putJson(
            route('site.borrower.profile.update', ['section' => 'activity']),
            [
                'activity_type' => 'business',
                'income_range' => $incomeKey,
                'signature_data' => '',
            ],
            ['X-KF-Autosave' => '1']
        );

        $response->assertOk()->assertJson(['ok' => true]);
    }

    public function test_residence_autosave_does_not_require_signature_data(): void
    {
        [$user] = $this->borrowerWithProfile();

        $response = $this->actingAs($user)->putJson(
            route('site.borrower.profile.update', ['section' => 'residence']),
            [
                'focus' => 'address',
                'region' => 'Dar es Salaam',
                'district' => 'Kinondoni',
                'ward' => 'Mwananyamala',
                'street' => 'Kawawa Road',
                'signature_data' => '',
            ],
            ['X-KF-Autosave' => '1']
        );

        $response->assertOk()->assertJson(['ok' => true]);
    }

    public function test_signature_focus_still_requires_signature_data(): void
    {
        [$user] = $this->borrowerWithProfile();

        $response = $this->actingAs($user)->putJson(
            route('site.borrower.profile.update', ['section' => 'personal']),
            [
                'focus' => 'signature',
                'signer_name' => 'Asha Juma',
            ],
            ['X-KF-Autosave' => '1']
        );

        $response->assertStatus(422)->assertJsonValidationErrors(['signature_data']);
    }

    public function test_profile_forms_opt_into_shared_autosave_without_ordinary_save_buttons(): void
    {
        $personal = file_get_contents(resource_path('views/site/borrower/profile/personal.blade.php'));
        $this->assertGreaterThanOrEqual(4, substr_count($personal, 'data-kf-autosave'));
        $this->assertDoesNotMatchRegularExpression(
            '/focus" value="family"[\s\S]{0,3500}gated-submit/',
            $personal
        );
        $this->assertDoesNotMatchRegularExpression(
            '/focus" value="signature"[\s\S]{0,1500}gated-submit/',
            $personal
        );

        $activity = file_get_contents(resource_path('views/site/borrower/profile/activity.blade.php'));
        $this->assertStringContainsString('data-kf-autosave', $activity);

        $residence = file_get_contents(resource_path('views/site/borrower/profile/residence.blade.php'));
        $this->assertStringContainsString('data-kf-autosave', $residence);
        $this->assertStringContainsString('value="verification"', $residence);
    }

    public function test_payment_page_no_longer_opens_success_modal(): void
    {
        $payment = file_get_contents(resource_path('views/site/borrower/profile/payment.blade.php'));
        $this->assertStringNotContainsString('showBorrowerFeedback', $payment);
    }

    public function test_collateral_copy_updated_for_profile_and_review(): void
    {
        $en = include resource_path('../lang/en/borrower.php');
        $sw = include resource_path('../lang/sw/borrower.php');

        $this->assertSame(
            'Upon review, Kopafasta may request collateral for any loan application.',
            $en['profile']['collateral_none_needed_body']
        );
        $this->assertSame(
            'Baada ya mapitio, Kopafasta inaweza kuomba dhamana kwa ombi lolote la mkopo.',
            $sw['profile']['collateral_none_needed_body']
        );
        $this->assertSame(
            'Upon review, Kopafasta may request collateral for this loan application.',
            $en['apply']['review_step']['collateral_disclosure']
        );
        $this->assertSame(
            'Baada ya mapitio, Kopafasta inaweza kuomba dhamana kwa ombi hili la mkopo.',
            $sw['apply']['review_step']['collateral_disclosure']
        );

        $review = file_get_contents(resource_path('views/site/apply/_review-step.blade.php'));
        $this->assertStringContainsString('collateral_disclosure', $review);
        $this->assertStringContainsString('! isAssetBackedProduct(current)', $review);
    }

    public function test_canonical_loader_supports_real_upload_percent(): void
    {
        $overlay = file_get_contents(resource_path('js/saving-overlay.js'));
        $this->assertStringContainsString('progress?.percent', $overlay);
        $this->assertStringContainsString('kfShowInlineSaving', $overlay);

        $autosave = file_get_contents(resource_path('js/kf-autosave.js'));
        $this->assertStringContainsString('xhr.upload.onprogress', $autosave);
        $this->assertStringNotContainsString('BorrowerAutosave', $autosave);
    }
}
