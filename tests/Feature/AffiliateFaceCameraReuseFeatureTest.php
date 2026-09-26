<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Vendor;
use App\Services\FaceVerificationService;
use App\Services\PartnerProfileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AffiliateFaceCameraReuseFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_affiliate_face_page_uses_borrower_wizard_not_partner_camera(): void
    {
        [$user] = $this->affiliateUser();

        $html = $this->actingAs($user)
            ->get(route('site.affiliate.profile', ['section' => 'face']))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('faceVerificationWizard', $html);
        $this->assertStringContainsString(__('borrower.face_verification_page.angles.front.instruction'), $html);
        $this->assertStringContainsString(__('borrower.face_verification_page.angles.left.instruction'), $html);
        $this->assertStringContainsString(__('borrower.face_verification_page.angles.right.instruction'), $html);
        $this->assertStringContainsString(__('borrower.face_verification_page.angles.holding_nida.instruction'), $html);
        $this->assertStringContainsString(__('borrower.face_verification_page.capture'), $html);
        $this->assertStringContainsString(__('borrower.document_upload.saving'), $html);
        $this->assertStringContainsString(__('borrower.document_upload.saved'), $html);
        $this->assertStringNotContainsString('partner-face-camera', $html);
        $this->assertStringNotContainsString(route('site.borrower.face-verification.submit'), $html);
    }

    public function test_affiliate_reuses_borrower_angle_catalog_and_persists_to_partner_metadata(): void
    {
        Storage::fake('public');
        [$user, $affiliate] = $this->affiliateUser();
        $borrowerAngles = array_keys(app(FaceVerificationService::class)->angles());
        $this->assertSame(['front', 'left', 'right', 'holding_nida'], $borrowerAngles);
        $this->assertSame(
            $borrowerAngles,
            app(PartnerProfileService::class)->requiredFaceAngleKeys($affiliate)
        );

        $this->actingAs($user)
            ->postJson(route('site.affiliate.face-verification.store', ['angle' => 'front']), [
                'photo' => UploadedFile::fake()->image('front.jpg', 480, 640),
            ])
            ->assertOk()
            ->assertJson([
                'ok' => true,
                'angle' => 'front',
                'complete' => false,
            ]);

        $affiliate->refresh();
        $this->assertFilledPath($affiliate->metadata['face_captures']['front'] ?? null);
        $this->assertSame($affiliate->metadata['face_captures']['front'], $affiliate->affiliate_selfie_path);

        $this->actingAs($user)
            ->postJson(route('site.affiliate.face-verification.store', ['angle' => 'left']), [
                'photo' => UploadedFile::fake()->image('left.jpg', 480, 640),
            ])->assertOk();
        $this->actingAs($user)
            ->postJson(route('site.affiliate.face-verification.store', ['angle' => 'right']), [
                'photo' => UploadedFile::fake()->image('right.jpg', 480, 640),
            ])->assertOk();
        $this->actingAs($user)
            ->postJson(route('site.affiliate.face-verification.store', ['angle' => 'holding_nida']), [
                'photo' => UploadedFile::fake()->image('id.jpg', 480, 640),
            ])
            ->assertOk()
            ->assertJson(['ok' => true, 'complete' => true]);

        $affiliate->refresh();
        $this->assertFilledPath($affiliate->metadata['face_captures']['holding_id'] ?? null);

        $this->actingAs($user)
            ->post(route('site.affiliate.face-verification.submit'))
            ->assertRedirect(route('site.affiliate.profile', ['section' => 'face']));

        $this->actingAs($user)
            ->deleteJson(route('site.affiliate.face-verification.destroy', ['angle' => 'left']))
            ->assertOk()
            ->assertJson(['ok' => true, 'angle' => 'left', 'complete' => false]);

        $affiliate->refresh();
        $this->assertArrayNotHasKey('left', $affiliate->metadata['face_captures'] ?? []);
    }

    public function test_no_physical_card_omits_holding_id_and_borrower_wizard_file_is_unchanged(): void
    {
        [$user, $affiliate] = $this->affiliateUser([
            'metadata' => [
                'identity' => ['no_physical_nida_card' => true],
            ],
        ]);

        $this->assertSame(
            ['front', 'left', 'right'],
            app(PartnerProfileService::class)->requiredFaceAngleKeys($affiliate)
        );

        $html = $this->actingAs($user)
            ->get(route('site.affiliate.profile', ['section' => 'face']))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString(
            __('borrower.face_verification_page.angles.holding_nida.instruction'),
            $html
        );

        $wizard = file_get_contents(resource_path('views/components/site/face-verification-wizard.blade.php'));
        $this->assertStringContainsString("route('site.borrower.face-verification.submit')", $wizard);
        $this->assertStringContainsString('kfFlashInlineSaved', $wizard);
        $this->assertStringContainsString('kfShowInlineSaving', $wizard);
    }

    /** @return array{0: User, 1: Vendor} */
    private function affiliateUser(array $overrides = []): array
    {
        $user = User::factory()->create(['role' => 'vendor']);
        $affiliate = Vendor::create(array_merge([
            'user_id' => $user->id,
            'vendor_number' => 'AFF-FACE-'.random_int(100, 999),
            'name' => 'Face Affiliate',
            'category' => 'affiliate',
            'status' => 'active',
            'phone' => '255712348'.random_int(100, 999),
            'affiliate_code' => 'FACE'.random_int(1000, 9999),
            'affiliate_kyc_status' => 'verified',
            'applicant_category' => 'individual',
        ], $overrides));

        return [$user, $affiliate];
    }

    private function assertFilledPath(?string $path): void
    {
        $this->assertTrue(filled($path), 'Expected a stored face path.');
        Storage::disk('public')->assertExists($path);
    }
}
