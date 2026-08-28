<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\FaceVerification;
use App\Models\User;
use App\Services\FaceVerificationService;
use App\Services\PinRecoveryChallengeService;
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
        app(PinRecoveryChallengeService::class)->enroll($user, [
            'mother_first_name' => 'Asha',
            'primary_school' => 'Uhuru Primary',
            'nida_middle4' => '4582',
        ]);

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

    public function test_borrower_can_remove_face_photo_when_pending_review(): void
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
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertDatabaseMissing('face_verifications', [
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

    public function test_upload_json_returns_stable_preview_url(): void
    {
        Storage::fake('public');
        $customer = $this->borrower();

        $response = $this->actingAs($customer->user)
            ->postJson(route('site.borrower.face-verification.store', ['angle' => 'front']), [
                'photo' => UploadedFile::fake()->image('front.jpg'),
            ]);

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('angle', 'front');

        $previewUrl = $response->json('previewUrl');
        $this->assertIsString($previewUrl);
        $this->assertStringContainsString('/storage/', $previewUrl);
        $this->assertStringNotContainsString('blob:', $previewUrl);
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
            ->assertRedirect(route('site.borrower.profile', ['section' => 'personal', 'focus' => 'face']));

        $this->actingAs($customer->user)
            ->get(route('site.borrower.profile', ['section' => 'personal', 'focus' => 'face']))
            ->assertOk()
            ->assertSee('removePhoto', false)
            ->assertSee('retakeStep', false)
            ->assertSee('submitVerification', false)
            ->assertSee('flushLocalUploads', false)
            ->assertSee('localBlob', false)
            ->assertSee(__('borrower.profile.uploading_documents'), false)
            ->assertSee('from-brand', false)
            ->assertSee('faceVerificationWizard', false);
    }

    public function test_fourth_upload_stays_incomplete_until_explicit_submit(): void
    {
        Storage::fake('public');
        $customer = $this->borrower();
        $faces = app(FaceVerificationService::class);

        foreach (['front', 'left', 'right', 'holding_nida'] as $angle) {
            $faces->upload($customer, $angle, UploadedFile::fake()->image($angle.'.jpg'));
        }

        $customer->refresh();
        $this->assertSame('incomplete', $customer->face_verification_status);
        $this->assertTrue($faces->progress($customer)['complete']);

        $this->actingAs($customer->user)
            ->deleteJson(route('site.borrower.face-verification.destroy', ['angle' => 'front']))
            ->assertOk()
            ->assertJson(['ok' => true, 'angle' => 'front']);

        $this->assertDatabaseMissing('face_verifications', [
            'customer_id' => $customer->id,
            'angle'       => 'front',
            'deleted_at'  => null,
        ]);
    }

    public function test_submit_locks_photos_for_review(): void
    {
        Storage::fake('public');
        $customer = $this->borrower();
        $faces = app(FaceVerificationService::class);

        foreach (['front', 'left', 'right', 'holding_nida'] as $angle) {
            $faces->upload($customer, $angle, UploadedFile::fake()->image($angle.'.jpg'));
        }

        $this->actingAs($customer->user)
            ->postJson(route('site.borrower.face-verification.submit'))
            ->assertOk()
            ->assertJson(['ok' => true, 'status' => 'pending']);

        $customer->refresh();
        $this->assertSame('pending', $customer->face_verification_status);

        $this->actingAs($customer->user)
            ->deleteJson(route('site.borrower.face-verification.destroy', ['angle' => 'front']))
            ->assertOk()
            ->assertJson(['ok' => true]);
    }

    public function test_submit_requires_all_photos(): void
    {
        Storage::fake('public');
        $customer = $this->borrower();
        $faces = app(FaceVerificationService::class);
        $faces->upload($customer, 'front', UploadedFile::fake()->image('front.jpg'));

        $this->actingAs($customer->user)
            ->postJson(route('site.borrower.face-verification.submit'))
            ->assertStatus(422)
            ->assertJson(['ok' => false]);

        $this->assertSame('incomplete', $customer->fresh()->face_verification_status);
    }
}
