<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\FaceVerification;
use App\Models\User;
use App\Services\FaceVerificationService;
use App\Services\PinService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FaceVerificationRemoveTest extends TestCase
{
    use RefreshDatabase;

    private function borrower(): Customer
    {
        $user = User::factory()->create(['role' => 'borrower']);
        app(PinService::class)->setPin($user, '1234');

        return Customer::create([
            'user_id'                  => $user->id,
            'customer_number'          => 'CU-FV-'.random_int(100, 999),
            'type'                     => 'individual',
            'status'                   => 'active',
            'first_name'               => 'Face',
            'last_name'                => 'Test',
            'phone'                    => '2557123400'.random_int(10, 99),
            'membership_status'        => 'active',
            'membership_expires_at'    => now()->addYear(),
            'face_verification_status' => 'incomplete',
        ]);
    }

    public function test_borrower_can_remove_face_photo_for_an_angle(): void
    {
        Storage::fake('public');
        $customer = $this->borrower();
        $path = UploadedFile::fake()->image('front.jpg')->store("borrower/{$customer->id}/face", 'public');

        FaceVerification::create([
            'customer_id' => $customer->id,
            'angle'       => 'front',
            'file_path'   => $path,
            'status'      => 'pending_review',
        ]);

        $this->actingAs($customer->user)
            ->deleteJson(route('site.borrower.face-verification.destroy', ['angle' => 'front']))
            ->assertOk()
            ->assertJson(['ok' => true, 'angle' => 'front']);

        $this->assertDatabaseMissing('face_verifications', [
            'customer_id' => $customer->id,
            'angle'       => 'front',
        ]);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_borrower_cannot_remove_face_photo_when_pending_review(): void
    {
        Storage::fake('public');
        $customer = $this->borrower();
        $customer->update(['face_verification_status' => 'pending']);
        $path = UploadedFile::fake()->image('front.jpg')->store("borrower/{$customer->id}/face", 'public');

        FaceVerification::create([
            'customer_id' => $customer->id,
            'angle'       => 'front',
            'file_path'   => $path,
            'status'      => 'pending_review',
        ]);

        $this->actingAs($customer->user)
            ->deleteJson(route('site.borrower.face-verification.destroy', ['angle' => 'front']))
            ->assertStatus(422)
            ->assertJson(['ok' => false]);

        $this->assertDatabaseHas('face_verifications', [
            'customer_id' => $customer->id,
            'angle'       => 'front',
        ]);
    }

    public function test_upload_replaces_existing_photo_file(): void
    {
        Storage::fake('public');
        $customer = $this->borrower();
        $faces = app(FaceVerificationService::class);
        $oldPath = UploadedFile::fake()->image('old.jpg')->store("borrower/{$customer->id}/face", 'public');

        FaceVerification::create([
            'customer_id' => $customer->id,
            'angle'       => 'front',
            'file_path'   => $oldPath,
            'status'      => 'pending_review',
        ]);

        $faces->upload($customer, 'front', UploadedFile::fake()->image('new.jpg'));

        Storage::disk('public')->assertMissing($oldPath);
        $this->assertSame(2, FaceVerification::where('customer_id', $customer->id)->where('angle', 'front')->count());
    }

    public function test_face_verification_page_includes_delete_urls_in_wizard(): void
    {
        Storage::fake('public');
        $customer = $this->borrower();
        $path = UploadedFile::fake()->image('front.jpg')->store("borrower/{$customer->id}/face", 'public');

        FaceVerification::create([
            'customer_id' => $customer->id,
            'angle'       => 'front',
            'file_path'   => $path,
            'status'      => 'pending_review',
        ]);

        $this->actingAs($customer->user)
            ->get(route('site.borrower.face-verification'))
            ->assertOk()
            ->assertSee('removePhoto', false)
            ->assertSee('retakeStep', false);
    }
}
