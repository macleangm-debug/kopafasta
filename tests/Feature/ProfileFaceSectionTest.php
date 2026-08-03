<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\FaceVerification;
use App\Models\User;
use App\Services\PinService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileFaceSectionTest extends TestCase
{
    use RefreshDatabase;

    private function borrower(string $faceStatus = 'pending'): Customer
    {
        $user = User::factory()->create(['role' => 'borrower']);
        app(PinService::class)->setPin($user, '1234');

        return Customer::create([
            'user_id'                  => $user->id,
            'customer_number'          => 'CU-PF-'.random_int(100, 999),
            'type'                     => 'individual',
            'status'                   => 'active',
            'first_name'               => 'Profile',
            'last_name'                => 'Face',
            'phone'                    => '2557133400'.random_int(10, 99),
            'membership_status'        => 'active',
            'membership_expires_at'    => now()->addYear(),
            'face_verification_status' => $faceStatus,
        ]);
    }

    public function test_complete_face_section_shows_photo_previews_and_edit(): void
    {
        Storage::fake('public');
        $customer = $this->borrower('pending');

        foreach (['front', 'left', 'right', 'holding_nida'] as $angle) {
            $path = UploadedFile::fake()->image("{$angle}.jpg")->store("borrower/{$customer->id}/face", 'public');
            FaceVerification::create([
                'customer_id' => $customer->id,
                'angle'       => $angle,
                'file_path'   => $path,
                'status'      => 'pending_review',
            ]);
        }

        $response = $this->actingAs($customer->user)
            ->get(route('site.borrower.profile', ['section' => 'personal']));

        $response->assertOk()
            ->assertSee('id="profile-face"', false)
            ->assertSee(__('borrower.profile.edit_section'), false)
            ->assertSee(__('borrower.profile.section_complete_tap'), false)
            ->assertSee('showCompleteTick', false)
            ->assertSee(__('borrower.nida.face_captured_photos'), false)
            ->assertSee(__('borrower.nida.face_view'), false)
            ->assertSee(__('borrower.profile.tap_to_enlarge'), false)
            ->assertSee('Face front', false)
            ->assertSee('x-teleport="body"', false);
    }

    public function test_revision_required_opens_face_replace_wizard(): void
    {
        Storage::fake('public');
        $customer = $this->borrower('revision_required');
        $path = UploadedFile::fake()->image('front.jpg')->store("borrower/{$customer->id}/face", 'public');
        FaceVerification::create([
            'customer_id' => $customer->id,
            'angle'       => 'front',
            'file_path'   => $path,
            'status'      => 'pending_review',
        ]);

        $this->actingAs($customer->user)
            ->get(route('site.borrower.profile', ['section' => 'personal']))
            ->assertOk()
            ->assertSee(__('borrower.apply.checklist.face_revision'), false)
            ->assertSee(__('borrower.nida.face_replace'), false)
            ->assertSee('faceVerificationWizard', false)
            ->assertSee('holding_nida', false);
    }
}
