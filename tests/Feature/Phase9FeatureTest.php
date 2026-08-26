<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerDocument;
use App\Models\DocumentType;
use App\Models\LoanApplicationDraft;
use App\Models\LoanProduct;
use App\Models\User;
use App\Models\Vendor;
use App\Services\LoanApplicationDraftService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class Phase9FeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_draft_snapshot_resolves_asset_document_urls_from_customer_documents(): void
    {
        Storage::fake('public');

        $customer = Customer::create([
            'customer_number' => 'CU-P9-001',
            'type'            => 'individual',
            'status'          => 'active',
            'first_name'      => 'Asset',
            'last_name'       => 'Borrower',
            'phone'           => '255712345690',
        ]);

        $product = LoanProduct::create([
            'code'              => 'AL-P9',
            'name'              => 'Asset Loan',
            'is_active'         => true,
            'interest_rate'     => 0.15,
            'min_amount'        => 100_000,
            'max_amount'        => 5_000_000,
            'tenure_min_months' => 3,
            'tenure_max_months' => 24,
        ]);

        $photoType = DocumentType::create([
            'code'       => 'asset_photo_front',
            'name'       => 'Front photo',
            'category'   => 'collateral',
            'applies_to' => 'individual',
            'is_active'  => true,
        ]);

        $path = 'borrower/'.$customer->id.'/collateral/front.jpg';
        Storage::disk('public')->put($path, 'fake-image');

        $document = CustomerDocument::create([
            'customer_id'      => $customer->id,
            'document_type_id' => $photoType->id,
            'file_path'        => $path,
            'status'           => 'pending',
        ]);

        $draft = LoanApplicationDraft::create([
            'customer_id'     => $customer->id,
            'loan_product_id' => $product->id,
            'phase'           => 'application',
            'step'            => 2,
            'draft_reference' => 'DR-P9-001',
            'payload'         => [
                'asset_documents' => [
                    'asset_photo_front' => [
                        'customer_document_id' => $document->id,
                        'code'                 => 'asset_photo_front',
                        'label'                => 'Front photo',
                    ],
                    'insurance_certificate' => [
                        'customer_document_id' => null,
                        'code'                 => 'insurance_certificate',
                        'label'                => 'Insurance certificate',
                        'path'                 => 'borrower/'.$customer->id.'/collateral/insurance.pdf',
                    ],
                ],
            ],
        ]);

        Storage::disk('public')->put('borrower/'.$customer->id.'/collateral/insurance.pdf', 'fake-pdf');

        $snapshot = app(LoanApplicationDraftService::class)->adminSnapshot($draft);

        $this->assertCount(1, $snapshot['asset_photos']);
        $this->assertStringContainsString('front.jpg', (string) $snapshot['asset_photos'][0]['url']);
        $this->assertTrue($snapshot['asset_photos'][0]['is_image']);

        $this->assertCount(1, $snapshot['insurance_documents']);
        $this->assertStringContainsString('insurance.pdf', (string) $snapshot['insurance_documents'][0]['url']);
        $this->assertFalse($snapshot['insurance_documents'][0]['is_image']);
    }

    public function test_guest_partner_root_redirects_to_partner_start(): void
    {
        $this->get('/partner')
            ->assertRedirect(route('site.partner.start'));
    }

    public function test_authenticated_partner_root_loads_dashboard(): void
    {
        $user = $this->makePartnerUser();

        $this->actingAs($user)
            ->get('/partner')
            ->assertOk()
            ->assertSee('Partner dashboard', false);
    }

    public function test_campaigns_live_in_growth_workspace_not_settings_hub(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $growthRoutes = collect(app(\App\Services\ConsoleNavService::class)->visibleSections($admin))
            ->firstWhere('label', 'Growth')['items'] ?? [];
        $this->assertContains('admin.promotions.index', array_column($growthRoutes, 1));

        $this->actingAs($admin, 'admin')
            ->get(route('admin.promotions.index'))
            ->assertOk();

        $this->actingAs($admin, 'admin')
            ->get(route('admin.settings.index'))
            ->assertOk()
            ->assertSee('Affiliates', false)
            ->assertSee('Membership', false);
    }

    public function test_affiliate_verification_page_shows_qr_code_for_active_affiliate(): void
    {
        Vendor::create([
            'vendor_number'         => 'AFF-P9-001',
            'name'                  => 'Phase 9 Affiliate',
            'category'              => 'affiliate',
            'status'                => 'active',
            'affiliate_code'        => 'AFFP9',
            'affiliate_kyc_status'  => 'verified',
            'phone'                 => '255712345691',
        ]);

        $this->get(route('site.affiliate.verify', 'AFFP9'))
            ->assertOk()
            ->assertSee('Verified affiliate partner', false)
            ->assertSee('create-qr-code', false)
            ->assertSee('AFFP9', false);
    }

    private function makePartnerUser(): User
    {
        $user = User::factory()->create(['role' => 'vendor']);
        Vendor::create([
            'user_id'       => $user->id,
            'vendor_number' => 'PTR-P9-001',
            'name'          => 'Phase 9 Partner',
            'category'      => 'gps',
            'status'        => 'active',
        ]);
        app(\App\Services\PinService::class)->setPin($user, '1234');

        return $user;
    }
}
