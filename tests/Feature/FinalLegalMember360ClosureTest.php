<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use App\Services\LegalSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FinalLegalMember360ClosureTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create([
            'role' => 'admin',
            'email' => 'final-legal-'.uniqid('', true).'@example.com',
        ]);
    }

    public function test_signature_background_removal_produces_transparent_png(): void
    {
        Storage::fake('public');

        $img = imagecreatetruecolor(200, 80);
        $paper = imagecolorallocate($img, 168, 168, 166); // mid-grey scan rectangle that v5 kept
        $ink = imagecolorallocate($img, 20, 40, 140); // blue pen
        imagefilledrectangle($img, 0, 0, 199, 79, $paper);
        imageline($img, 20, 40, 180, 40, $ink);
        $tmp = tempnam(sys_get_temp_dir(), 'sig').'.png';
        imagepng($img, $tmp);
        imagedestroy($img);

        $processed = app(LegalSettingsService::class)->transparentStampPath($tmp);
        $this->assertNotSame($tmp, $processed);
        $this->assertFileExists($processed);

        $out = imagecreatefrompng($processed);
        $this->assertNotFalse($out);
        $corner = imagecolorat($out, 0, 0);
        $alpha = ($corner & 0x7F000000) >> 24;
        $this->assertGreaterThanOrEqual(100, $alpha, 'Corner paper should be transparent');

        $midPaper = imagecolorat($out, 100, 10);
        $midAlpha = ($midPaper & 0x7F000000) >> 24;
        $this->assertGreaterThanOrEqual(100, $midAlpha, 'Interior grey paper should be transparent');

        $inkPixel = imagecolorat($out, 100, 40);
        $inkAlpha = ($inkPixel & 0x7F000000) >> 24;
        $this->assertLessThan(80, $inkAlpha, 'Signature ink should remain opaque');

        imagedestroy($out);
        @unlink($tmp);
    }

    public function test_member_360_about_contains_signature_not_separate_tab(): void
    {
        $customer = Customer::create([
            'customer_number' => 'CU-FINAL-001',
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Amina',
            'last_name' => 'Hassan',
            'phone' => '255711000001',
            'legal_signature_data' => 'data:image/png;base64,aaa',
            'legal_signer_name' => 'Amina Hassan',
            'legal_signed_at' => now(),
        ]);

        $this->actingAs($this->admin(), 'admin')
            ->get(route('admin.customers.show', ['customer' => $customer, 'tab' => 'about']))
            ->assertOk()
            ->assertSee('National ID')
            ->assertSee('Face / identity captures')
            ->assertSee('Signature')
            ->assertSee('Marital status');

        $html = $this->actingAs($this->admin(), 'admin')
            ->get(route('admin.customers.show', $customer))
            ->assertOk()
            ->assertSee('About you')
            ->getContent();

        $this->assertStringNotContainsString('tab=signature', $html);
    }

    public function test_member_360_about_shows_marital_status_when_captured(): void
    {
        $customer = Customer::create([
            'customer_number' => 'CU-FINAL-MS-001',
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Amina',
            'last_name' => 'Hassan',
            'phone' => '255711000099',
            'marital_status' => 'married',
            'number_of_children' => 2,
            'spouse_first_name' => 'Juma',
            'spouse_last_name' => 'Hassan',
        ]);

        $this->actingAs($this->admin(), 'admin')
            ->get(route('admin.customers.show', ['customer' => $customer, 'tab' => 'about']))
            ->assertOk()
            ->assertSee('Marital status')
            ->assertSee('Married')
            ->assertSee('Juma Hassan');
    }

    public function test_member_360_payments_search_form_present(): void
    {
        $customer = Customer::create([
            'customer_number' => 'CU-FINAL-PAY-001',
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Amina',
            'last_name' => 'Hassan',
            'phone' => '255711000088',
        ]);

        $this->actingAs($this->admin(), 'admin')
            ->get(route('admin.customers.show', ['customer' => $customer, 'tab' => 'payments']))
            ->assertOk()
            ->assertSee('payments_q')
            ->assertSee('Search');
    }

    public function test_document_preview_uses_in_platform_viewer_and_legal_save_is_quiet(): void
    {
        $this->actingAs($this->admin(), 'admin')
            ->get(route('admin.document-templates.index'))
            ->assertOk()
            ->assertSee('kfOpenDocumentPreview')
            ->assertSee('Kiswahili')
            ->assertSee('English')
            ->assertSee('lang=sw');

        $this->actingAs($this->admin(), 'admin')
            ->from(route('admin.settings.legal'))
            ->put(route('admin.settings.legal.save'), [
                'jurisdiction' => 'United Republic of Tanzania',
                'offer_validity_days' => 14,
                'default_clause' => 'Default applies.',
                'contract_sections' => ['definitions' => '1', 'loan_terms' => '1'],
            ])
            ->assertRedirect()
            ->assertSessionHas('status_quietly', true)
            ->assertSessionHas('status', 'Saved');
    }

    public function test_styled_upload_on_signatory_create(): void
    {
        $this->actingAs($this->admin(), 'admin')
            ->get(route('admin.settings.signatories.create'))
            ->assertOk()
            ->assertSee('Upload signature')
            ->assertSee('Remove image background');
    }
}
