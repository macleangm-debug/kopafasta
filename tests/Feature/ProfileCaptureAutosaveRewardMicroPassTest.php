<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\LoyaltyPointTransaction;
use App\Models\User;
use App\Services\MemberEngagementRewardService;
use App\Services\ProfileCompletionService;
use App\Support\Celebration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileCaptureAutosaveRewardMicroPassTest extends TestCase
{
    use RefreshDatabase;

    private function makeCustomer(array $overrides = []): Customer
    {
        $user = User::factory()->create(['role' => 'borrower']);

        return Customer::create(array_merge([
            'user_id'         => $user->id,
            'customer_number' => 'C-PCR'.random_int(100000, 999999),
            'type'            => 'individual',
            'status'          => 'active',
            'first_name'      => 'Profile',
            'last_name'       => 'Capture',
            'phone'           => '+255701'.random_int(100000, 999999),
        ], $overrides));
    }

    public function test_profile_document_field_starts_closed_with_plus_picker_only(): void
    {
        $holder = file_get_contents(resource_path('views/components/site/profile-document-field.blade.php'));
        $single = file_get_contents(resource_path('views/components/site/single-image-document-upload.blade.php'));
        $picker = file_get_contents(resource_path('views/components/site/document-source-picker.blade.php'));

        $this->assertStringContainsString('captureOpen: false', $holder);
        $this->assertStringContainsString('document-source-picker', $holder);
        $this->assertStringContainsString('take_photo', $picker);
        $this->assertStringContainsString("matchMedia('(min-width: 1024px)')", $picker);
        $this->assertStringContainsString('bottom-sheet', $picker);
        $this->assertStringContainsString('@if ($sourceDriven)', $single);
        $this->assertStringContainsString('x-ref="uploadInput"', $single);
    }

    public function test_face_wizard_autosaves_and_only_warns_on_local_blobs(): void
    {
        $blade = file_get_contents(resource_path('views/components/site/face-verification-wizard.blade.php'));
        $this->assertStringContainsString('await this.uploadBlob(blob, step)', $blade);
        $this->assertStringContainsString('return (this.steps || []).some((step) => !! step.localBlob)', $blade);
        $this->assertStringNotContainsString("['scanning', 'saving', 'preview', 'review'].includes(this.phase)", $blade);
        $this->assertStringContainsString('await this.submitVerification()', $blade);
    }

    public function test_profile_completion_reward_is_25_and_idempotent_once(): void
    {
        $customer = $this->makeCustomer(['loyalty_points' => 0]);

        $rewards = app(MemberEngagementRewardService::class);

        $first = app(\App\Services\LoyaltyPointsService::class)->earn(
            $customer,
            'complete_profile',
            'Profile complete',
            'profile_completion',
            (int) $customer->id,
        );
        $second = app(\App\Services\LoyaltyPointsService::class)->earn(
            $customer->fresh(),
            'complete_profile',
            'Profile complete',
            'profile_completion',
            (int) $customer->id,
        );

        $this->assertSame(25, $first);
        $this->assertSame(0, $second);
        $this->assertSame(25, (int) $customer->fresh()->loyalty_points);
        $this->assertDatabaseHas('loyalty_point_transactions', [
            'customer_id' => $customer->id,
            'action_key' => 'complete_profile',
            'reference_type' => 'profile_completion',
            'reference_id' => $customer->id,
            'points' => 25,
            'type' => 'credit',
        ]);
        $this->assertSame(1, LoyaltyPointTransaction::query()
            ->where('customer_id', $customer->id)
            ->where('action_key', 'complete_profile')
            ->count());

        $rewards->afterDocumentUploaded($customer->fresh(), 'national_id_front');
        $rewards->afterProfileSectionSaved($customer->fresh(), 'residence');
        $this->assertSame(25, (int) $customer->fresh()->loyalty_points);
    }

    public function test_config_defaults_disable_fragmented_profile_upload_rewards(): void
    {
        $actions = config('gamification.loyalty_points.actions');
        $this->assertSame(25, (int) ($actions['complete_profile']['points'] ?? 0));
        $this->assertSame(0, (int) ($actions['upload_documents']['points'] ?? -1));
        $this->assertSame(0, (int) ($actions['update_information']['points'] ?? -1));
    }

    public function test_reaching_100_percent_does_not_open_a_profile_complete_modal(): void
    {
        $customer = $this->makeCustomer(['loyalty_points' => 0]);

        $this->mock(ProfileCompletionService::class, function ($mock): void {
            $mock->shouldReceive('calculate')->andReturn(['percent' => 100]);
        });

        app(MemberEngagementRewardService::class)->afterDocumentUploaded($customer, 'residence_letter');
        app(MemberEngagementRewardService::class)->afterProfileSectionSaved($customer->fresh(), 'residence');

        $this->assertNotContains('profile_complete', Celebration::reasons());
        $this->assertSame([], Celebration::reasons());
        $this->assertSame(25, (int) $customer->fresh()->loyalty_points);

        $rewards = file_get_contents(app_path('Services/MemberEngagementRewardService.php'));
        $controller = file_get_contents(app_path('Http/Controllers/Site/BorrowerController.php'));
        $holder = file_get_contents(resource_path('views/components/site/profile-document-field.blade.php'));

        $this->assertStringNotContainsString("flashOne('profile_complete')", $rewards);
        $this->assertStringNotContainsString("Celebration::flashOne('profile_complete')", $controller);
        $this->assertStringNotContainsString('profile_ready_to_submit', $controller);
        $this->assertStringContainsString('kfFlashInlineSaved', $holder);
    }
}
