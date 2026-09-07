<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerDocument;
use App\Models\DocumentType;
use App\Models\User;
use App\Services\PinService;
use App\Services\ProfileDocumentService;
use Database\Seeders\KycDocumentTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentTypeExpiresMicroPassATest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(KycDocumentTypeSeeder::class);
    }

    private function borrower(): Customer
    {
        $user = User::factory()->create(['role' => 'borrower']);
        app(PinService::class)->setPin($user, '1234');

        return Customer::create([
            'user_id' => $user->id,
            'customer_number' => 'CU-DOC-'.random_int(1000, 9999),
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Docs',
            'last_name' => 'Borrower',
            'phone' => '25571234'.random_int(1000, 9999),
            'membership_status' => 'active',
            'membership_expires_at' => now()->addYear(),
        ]);
    }

    public function test_business_license_expires_and_other_current_types_do_not(): void
    {
        $license = DocumentType::query()->where('code', 'business_license')->first();
        $this->assertNotNull($license);
        $this->assertTrue($license->requiresExpiry());

        $otherExpires = DocumentType::query()
            ->where('code', '!=', 'business_license')
            ->where('expires', true)
            ->pluck('code')
            ->all();

        $this->assertSame([], $otherExpires);
    }

    public function test_documents_upload_requires_expiry_only_for_expiring_types(): void
    {
        Storage::fake('public');
        $customer = $this->borrower();
        $license = DocumentType::query()->where('code', 'business_license')->firstOrFail();
        $tin = DocumentType::query()->where('code', 'tin_certificate')->firstOrFail();

        $this->actingAs($customer->user)
            ->post(route('site.borrower.documents.store'), [
                'document_type_id' => $license->id,
                'file' => UploadedFile::fake()->create('license.pdf', 120, 'application/pdf'),
            ])
            ->assertSessionHasErrors('expires_at');

        $this->actingAs($customer->user)
            ->post(route('site.borrower.documents.store'), [
                'document_type_id' => $license->id,
                'expires_at' => now()->addYear()->toDateString(),
                'file' => UploadedFile::fake()->create('license.pdf', 120, 'application/pdf'),
            ])
            ->assertRedirect(route('site.borrower.documents'));

        $this->actingAs($customer->user)
            ->post(route('site.borrower.documents.store'), [
                'document_type_id' => $tin->id,
                'file' => UploadedFile::fake()->create('tin.pdf', 120, 'application/pdf'),
            ])
            ->assertRedirect(route('site.borrower.documents'));

        $licenseDoc = CustomerDocument::query()
            ->where('customer_id', $customer->id)
            ->where('document_type_id', $license->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($licenseDoc);
        $service = app(ProfileDocumentService::class);
        $this->assertNotNull($service->expiryDate($licenseDoc));
        $this->assertFalse($service->isExpired($licenseDoc));
    }

    public function test_payment_account_shows_canonical_phone_prefix_and_step_copy(): void
    {
        $customer = $this->borrower();

        $this->actingAs($customer->user)
            ->get(route('site.borrower.profile', ['section' => 'payment']))
            ->assertOk()
            ->assertSee(__('borrower.payment_details.step_of', ['current' => 1, 'total' => 3]), false)
            ->assertDontSee('BORROWER.APPLY.STEP_OF', false)
            ->assertDontSee('borrower.apply.step_of', false)
            ->assertSee('+255', false)
            ->assertSee('name="mobile_number"', false);
    }

    public function test_documents_page_shows_replace_on_existing_holder(): void
    {
        Storage::fake('public');
        $customer = $this->borrower();
        $tin = DocumentType::query()->where('code', 'tin_certificate')->firstOrFail();

        CustomerDocument::create([
            'customer_id' => $customer->id,
            'document_type_id' => $tin->id,
            'file_path' => 'customer/'.$customer->id.'/documents/tin.pdf',
            'status' => 'pending',
            'notes' => json_encode(['original_name' => 'tin.pdf']),
        ]);

        $this->actingAs($customer->user)
            ->get(route('site.borrower.documents'))
            ->assertOk()
            ->assertSee(__('borrower.profile.replace_document'), false)
            ->assertSee(__('borrower.profile.view_document'), false)
            ->assertDontSee('Ongeza hati nyingine', false);
    }
}
