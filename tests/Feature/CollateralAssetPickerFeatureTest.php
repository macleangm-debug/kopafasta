<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerAsset;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\LoanApplicationAsset;
use App\Models\LoanGroup;
use App\Models\LoanGroupMember;
use App\Models\LoanProduct;
use App\Models\PartnerTask;
use App\Models\User;
use App\Models\ValuationAssignment;
use App\Models\Vendor;
use App\Services\ApplicationDocumentRequestService;
use App\Services\CollateralSecureService;
use App\Services\CustomerAssetService;
use App\Services\PinRecoveryChallengeService;
use App\Services\PinService;
use Database\Seeders\ValuationPricingDefaultsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CollateralAssetPickerFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ValuationPricingDefaultsSeeder::class);
    }

    private function completeBorrower(): Customer
    {
        $user = User::factory()->create(['role' => 'borrower']);
        app(PinService::class)->setPin($user, '1234');
        app(PinRecoveryChallengeService::class)->enroll($user, [
            'mother_first_name' => 'Asha',
            'primary_school' => 'Uhuru Primary',
            'nida_middle4' => '4582',
        ]);

        return Customer::create([
            'user_id' => $user->id,
            'customer_number' => 'CU-AST-'.random_int(100, 999),
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Leader',
            'last_name' => 'Asset',
            'phone' => '25571234'.random_int(1000, 9999),
            'membership_status' => 'active',
            'membership_expires_at' => now()->addYear(),
        ]);
    }

    private function applicationFor(Customer $customer): LoanApplication
    {
        $product = LoanProduct::create([
            'code' => 'IL-AST-'.random_int(100, 999),
            'name' => 'Installment',
            'is_active' => true,
            'interest_rate' => 0.15,
            'min_amount' => 100_000,
            'max_amount' => 5_000_000,
            'tenure_min_months' => 3,
            'tenure_max_months' => 12,
        ]);

        return LoanApplication::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'application_number' => 'APP-AST-'.random_int(100, 999),
            'status' => 'under_review',
            'current_stage' => 'screening',
            'requested_amount' => 800_000,
            'requested_tenure_months' => 6,
            'submitted_at' => now(),
        ]);
    }

    private function completeAsset(Customer $customer, string $label = 'Plot A'): CustomerAsset
    {
        return CustomerAsset::create([
            'customer_id' => $customer->id,
            'asset_type' => 'land',
            'label' => $label,
            'is_active' => true,
            'photo_paths' => ['assets/front.jpg', 'assets/back.jpg'],
            'metadata' => [
                'ownership_document_path' => 'assets/title.pdf',
                'person_with_asset_path' => 'assets/owner.jpg',
            ],
        ]);
    }

    public function test_existing_asset_can_be_chosen_for_underwriting_request(): void
    {
        $customer = $this->completeBorrower();
        $application = $this->applicationFor($customer);
        $asset = $this->completeAsset($customer, 'Shamba la kiongozi');
        $admin = User::factory()->create(['role' => 'admin']);
        app(ApplicationDocumentRequestService::class)->create($application, $admin, 'Add collateral asset');

        $html = $this->actingAs($customer->user)
            ->get(route('site.borrower.profile', [
                'section' => 'assets',
                'uw' => 1,
                'application' => $application->id,
            ]))
            ->assertOk()
            ->assertSee('Shamba la kiongozi', false)
            ->assertSee(__('borrower.profile.collateral_use_this'), false)
            ->assertSee(__('borrower.profile.collateral_ready'), false)
            ->getContent();

        $this->assertStringContainsString('Shamba la kiongozi', $html);

        $this->actingAs($customer->user)
            ->post(route('site.borrower.profile.assets.use', $asset), [
                'application_id' => $application->id,
            ])
            ->assertRedirect(route('site.borrower.application', $application));

        $this->assertDatabaseHas('loan_application_assets', [
            'loan_application_id' => $application->id,
            'customer_asset_id' => $asset->id,
        ]);
        $this->assertDatabaseHas('loan_application_document_requests', [
            'loan_application_id' => $application->id,
            'label' => 'Add collateral asset',
            'status' => 'uploaded',
        ]);
    }

    public function test_asset_tied_to_another_loan_shows_why_it_cannot_be_used(): void
    {
        $customer = $this->completeBorrower();
        $thisLoan = $this->applicationFor($customer);
        $otherLoan = $this->applicationFor($customer);
        $asset = $this->completeAsset($customer, 'Gari la kiongozi');

        LoanApplicationAsset::create([
            'loan_application_id' => $otherLoan->id,
            'customer_asset_id' => $asset->id,
            'asset_type' => 'land',
            'uw_status' => LoanApplicationAsset::UW_PENDING,
        ]);

        $availability = app(CustomerAssetService::class)
            ->availabilityForApplication($asset->fresh(), $thisLoan);

        $this->assertSame('pledged_other', $availability['code']);
        $this->assertFalse($availability['selectable']);
        $this->assertSame($otherLoan->application_number, $availability['application_number']);

        $this->actingAs($customer->user)
            ->get(route('site.borrower.profile', [
                'section' => 'assets',
                'uw' => 1,
                'application' => $thisLoan->id,
            ]))
            ->assertOk()
            ->assertSee('Gari la kiongozi', false)
            ->assertSee(__('borrower.profile.collateral_tied_named', ['number' => $otherLoan->application_number]), false)
            ->assertDontSee(__('borrower.profile.collateral_use_this'), false);
    }

    public function test_incomplete_asset_is_tagged_instead_of_selectable(): void
    {
        $customer = $this->completeBorrower();
        $application = $this->applicationFor($customer);
        $asset = CustomerAsset::create([
            'customer_id' => $customer->id,
            'asset_type' => 'land',
            'label' => 'Plot without docs',
            'is_active' => true,
        ]);

        $availability = app(CustomerAssetService::class)
            ->availabilityForApplication($asset, $application);

        $this->assertSame('incomplete', $availability['code']);
        $this->assertSame('photos', $availability['incomplete']);
        $this->assertFalse($availability['selectable']);
    }

    public function test_collateral_deep_link_keeps_existing_assets_visible(): void
    {
        $customer = $this->completeBorrower();
        $application = $this->applicationFor($customer);
        $this->completeAsset($customer);
        $admin = User::factory()->create(['role' => 'admin']);
        $request = app(ApplicationDocumentRequestService::class)
            ->create($application, $admin, 'Add collateral asset');

        $url = app(ApplicationDocumentRequestService::class)
            ->borrowerActionUrl($request->fresh(), $customer);

        $this->assertStringContainsString('application='.$application->id, $url);
        $this->assertStringNotContainsString('add=1', $url);
        $this->assertStringContainsString('uw=1', $url);
    }

    public function test_loan_application_shows_saved_assets_to_pick(): void
    {
        $customer = $this->completeBorrower();
        $application = $this->applicationFor($customer);
        $this->completeAsset($customer, 'Shamba la kiongozi');
        $admin = User::factory()->create(['role' => 'admin']);
        app(ApplicationDocumentRequestService::class)->create($application, $admin, 'Add collateral asset');

        $this->actingAs($customer->user)
            ->get(route('site.borrower.application', $application))
            ->assertOk()
            ->assertSee($application->application_number, false);
    }

    public function test_asset_view_opens_read_only_with_edit_option(): void
    {
        $customer = $this->completeBorrower();
        $asset = $this->completeAsset($customer, 'Plot A');

        $html = $this->actingAs($customer->user)
            ->get(route('site.borrower.profile', [
                'section' => 'assets',
                'view' => $asset->id,
            ]))
            ->assertOk()
            ->assertSee('data-asset-mode="view"', false)
            ->assertSee(__('borrower.apply.edit'), false)
            ->getContent();

        $this->assertStringContainsString('data-asset-mode="edit"', $html);
    }

    public function test_rejected_and_closed_loans_release_the_asset(): void
    {
        $customer = $this->completeBorrower();
        $rejectedLoan = $this->applicationFor($customer);
        $rejectedLoan->update(['status' => 'rejected']);
        $closedApp = $this->applicationFor($customer);
        $closedApp->update(['status' => 'disbursed']);
        $thisLoan = $this->applicationFor($customer);
        $rejectedAsset = $this->completeAsset($customer, 'Rejected plot');
        $closedAsset = $this->completeAsset($customer, 'Closed plot');

        LoanApplicationAsset::create([
            'loan_application_id' => $rejectedLoan->id,
            'customer_asset_id' => $rejectedAsset->id,
            'asset_type' => 'land',
            'uw_status' => LoanApplicationAsset::UW_PENDING,
        ]);
        LoanApplicationAsset::create([
            'loan_application_id' => $closedApp->id,
            'customer_asset_id' => $closedAsset->id,
            'asset_type' => 'land',
            'uw_status' => LoanApplicationAsset::UW_ACCEPTED,
        ]);
        Loan::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $closedApp->loan_product_id,
            'loan_application_id' => $closedApp->id,
            'loan_number' => 'LN-AST-'.random_int(1000, 9999),
            'principal_amount' => 500_000,
            'approved_amount' => 500_000,
            'outstanding_balance' => 0,
            'interest_rate' => 0.15,
            'tenure_months' => 6,
            'status' => 'closed',
            'closed_at' => now(),
        ]);

        $service = app(CustomerAssetService::class);

        $this->assertSame('available', $service->availabilityForApplication($rejectedAsset->fresh(), $thisLoan)['code']);
        $this->assertSame('available', $service->availabilityForApplication($closedAsset->fresh(), $thisLoan)['code']);
        $this->assertTrue($service->availabilityForApplication($rejectedAsset->fresh(), $thisLoan)['selectable']);
        $this->assertTrue($service->availabilityForApplication($closedAsset->fresh(), $thisLoan)['selectable']);
    }

    public function test_on_this_loan_badge_names_the_application(): void
    {
        $customer = $this->completeBorrower();
        $application = $this->applicationFor($customer);
        $asset = $this->completeAsset($customer, 'Named plot');

        LoanApplicationAsset::create([
            'loan_application_id' => $application->id,
            'customer_asset_id' => $asset->id,
            'asset_type' => 'land',
            'uw_status' => LoanApplicationAsset::UW_PENDING,
        ]);

        $this->actingAs($customer->user)
            ->get(route('site.borrower.profile', [
                'section' => 'assets',
                'uw' => 1,
                'application' => $application->id,
            ]))
            ->assertOk()
            ->assertSee(__('borrower.profile.collateral_on_this_loan', [
                'number' => $application->application_number,
            ]), false)
            ->assertDontSee('Ready for this loan', false)
            ->assertDontSee('On this loan', false);
    }

    public function test_admin_collateral_tab_lists_only_assets_on_this_loan(): void
    {
        $customer = $this->completeBorrower();
        $application = $this->applicationFor($customer);
        $pledged = $this->completeAsset($customer, 'Toyota Rav4');
        $this->completeAsset($customer, 'Vitz');
        $admin = User::factory()->create(['role' => 'admin']);

        LoanApplicationAsset::create([
            'loan_application_id' => $application->id,
            'customer_asset_id' => $pledged->id,
            'asset_type' => 'vehicle',
            'uw_status' => LoanApplicationAsset::UW_PENDING,
        ]);

        $html = $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-applications.show', [
                'loan_application' => $application,
                'workspace' => 'profiles',
                'tab' => 'collateral',
                'person' => 'borrower',
            ]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Toyota Rav4', $html);
        $this->assertStringContainsString('On this loan', $html);
        $this->assertStringContainsString('View collateral', $html);
        $this->assertStringNotContainsString('>Vitz<', $html);
        $this->assertStringNotContainsString('>Saved<', $html);
        $this->assertStringContainsString('Request valuation', $html);
        $this->assertStringContainsString('Waiting for the borrower', $html);
        $this->assertGreaterThanOrEqual(1, substr_count($html, 'ring-brand/15'));
    }

    public function test_admin_collateral_cards_use_the_same_detail_grid_for_vehicles(): void
    {
        $customer = $this->completeBorrower();
        $application = $this->applicationFor($customer);
        $full = CustomerAsset::create([
            'customer_id' => $customer->id,
            'asset_type' => 'vehicle',
            'label' => 'Toyota Rav4',
            'is_active' => true,
            'registration_number' => 'T123ABC',
            'photo_paths' => ['assets/rav4.jpg'],
            'metadata' => [
                'details' => [
                    'make' => 'Toyota',
                    'year' => '2025',
                    'chassis_number' => '23456789',
                ],
            ],
        ]);
        CustomerAsset::create([
            'customer_id' => $customer->id,
            'asset_type' => 'vehicle',
            'label' => 'Vitz',
            'is_active' => true,
            'registration_number' => '1234',
            'photo_paths' => ['assets/vitz.jpg'],
            'metadata' => [],
        ]);
        $admin = User::factory()->create(['role' => 'admin']);

        LoanApplicationAsset::create([
            'loan_application_id' => $application->id,
            'customer_asset_id' => $full->id,
            'asset_type' => 'vehicle',
            'uw_status' => LoanApplicationAsset::UW_PENDING,
        ]);

        $html = $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-applications.show', [
                'loan_application' => $application,
                'workspace' => 'profiles',
                'tab' => 'collateral',
                'person' => 'borrower',
            ]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Toyota Rav4', $html);
        $this->assertStringNotContainsString('>Vitz<', $html);
        $this->assertStringContainsString('Registration number', $html);
        $this->assertStringContainsString('Year of manufacture', $html);
        $this->assertStringContainsString('Make', $html);
        $this->assertStringContainsString('Chassis number', $html);
        $this->assertStringContainsString('T123ABC', $html);
        $this->assertStringNotContainsString('>1234<', $html);
        $this->assertStringContainsString('•••6789', $html);
        $this->assertStringContainsString('Request valuation', $html);
        $this->assertStringContainsString('Waiting for the borrower', $html);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-applications.show', [
                'loan_application' => $application,
                'workspace' => 'profiles',
                'tab' => 'personal',
                'person' => 'borrower',
            ]))
            ->assertOk()
            ->assertSee('NIDA number', false)
            ->assertDontSee('NIDA status', false);
    }

    public function test_admin_collateral_tab_shows_valuation_result_instead_of_request_when_complete(): void
    {
        $customer = $this->completeBorrower();
        $application = $this->applicationFor($customer);
        $pledged = $this->completeAsset($customer, 'Toyota Rav4');
        $pledged->update(['asset_type' => 'vehicle']);
        $this->completeAsset($customer, 'Vitz')->update(['asset_type' => 'vehicle']);
        $admin = User::factory()->create(['role' => 'admin']);

        LoanApplicationAsset::create([
            'loan_application_id' => $application->id,
            'customer_asset_id' => $pledged->id,
            'asset_type' => 'vehicle',
            'uw_status' => LoanApplicationAsset::UW_PENDING,
        ]);
        app(CustomerAssetService::class)->persistOnLoanIds($application, [(int) $pledged->id]);

        $valuer = Vendor::create([
            'vendor_number' => 'V-DONE-'.random_int(100, 999),
            'name' => 'Geofrey Mwaijjonga',
            'category' => 'valuer',
            'status' => 'active',
        ]);
        $task = PartnerTask::query()->create([
            'partner_id' => $valuer->id,
            'loan_application_id' => $application->id,
            'task_type' => 'asset_valuation',
            'status' => 'completed',
        ]);
        ValuationAssignment::query()->create([
            'loan_application_id' => $application->id,
            'vendor_id' => $valuer->id,
            'vendor_task_id' => $task->id,
            'status' => ValuationAssignment::STATUS_COMPLETED,
            'market_value' => 20_000_000,
            'forced_sale_value' => 15_000_000,
            'completed_at' => now(),
        ]);
        $payload = is_array($application->screening_payload) ? $application->screening_payload : [];
        $payload['collateral_secure'] = [
            'status' => CollateralSecureService::STATUS_SECURED,
            'customer_asset_id' => $pledged->id,
            'valuation_fee_paid_at' => now()->toIso8601String(),
        ];
        $application->update(['screening_payload' => $payload]);

        $html = $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-applications.show', [
                'loan_application' => $application,
                'workspace' => 'profiles',
                'tab' => 'collateral',
                'person' => 'borrower',
            ]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Forced sale value', $html);
        $this->assertStringContainsString('Valuation is on the collateral card', $html);
        $this->assertStringContainsString('Geofrey Mwaijjonga', $html);
        $this->assertStringNotContainsString('Next step for screening', $html);
        $this->assertStringNotContainsString('>Request valuation<', $html);
        $this->assertStringContainsString('On this loan', $html);
        $this->assertStringNotContainsString('>Vitz<', $html);
        $this->assertStringNotContainsString('Collateral secure status:', $html);
        $this->assertStringNotContainsString('Request collateral from', $html);
        $this->assertStringContainsString('Request collateral', $html);
        $this->assertStringContainsString('Review request', $html);
    }

    public function test_admin_collateral_tab_caps_cards_and_offers_show_all(): void
    {
        $customer = $this->completeBorrower();
        $application = $this->applicationFor($customer);
        $ids = [];
        for ($i = 1; $i <= 11; $i++) {
            $asset = $this->completeAsset($customer, 'Plot '.$i);
            LoanApplicationAsset::create([
                'loan_application_id' => $application->id,
                'customer_asset_id' => $asset->id,
                'asset_type' => 'land',
                'uw_status' => LoanApplicationAsset::UW_PENDING,
            ]);
            $ids[] = (int) $asset->id;
        }
        app(CustomerAssetService::class)->persistOnLoanIds($application, $ids);
        $admin = User::factory()->create(['role' => 'admin']);

        $html = $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-applications.show', [
                'loan_application' => $application,
                'workspace' => 'profiles',
                'tab' => 'collateral',
                'person' => 'borrower',
            ]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('View all collateral (11)', $html);
        $this->assertStringContainsString('Search type, registration, owner, status', $html);
    }

    public function test_collateral_request_needs_review_then_shows_waiting_state(): void
    {
        $customer = $this->completeBorrower();
        $application = $this->applicationFor($customer);
        $this->completeAsset($customer, 'Plot A');
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin, 'admin')
            ->from(route('admin.loan-applications.show', [
                'loan_application' => $application,
                'workspace' => 'profiles',
                'tab' => 'collateral',
                'person' => 'borrower',
            ]))
            ->post(route('admin.loan-applications.document-requests.store', $application), [
                'type' => 'document',
                'intent' => 'collateral',
                'presets' => ['New collateral photo', 'Updated collateral insurance certificate'],
                'instructions' => 'Please upload the updated insurance document before we continue.',
                'review_person' => 'borrower',
                'return_workspace' => 'profiles',
                'return_tab' => 'collateral',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('confirmed');

        $this->assertSame(0, $application->documentRequests()->count());

        $this->actingAs($admin, 'admin')
            ->post(route('admin.loan-applications.document-requests.store', $application), [
                'type' => 'document',
                'intent' => 'collateral',
                'confirmed' => '1',
                'presets' => ['New collateral photo', 'Updated collateral insurance certificate'],
                'instructions' => 'Please upload the updated insurance document before we continue.',
                'review_person' => 'borrower',
                'return_workspace' => 'profiles',
                'return_tab' => 'collateral',
            ])
            ->assertRedirect(route('admin.loan-applications.show', [
                'loan_application' => $application,
                'workspace' => 'profiles',
                'tab' => 'collateral',
                'person' => 'borrower',
                'review_person' => 'borrower',
            ]).'#collateral-requests');

        $this->assertSame(2, $application->fresh()->documentRequests()->count());

        $html = $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-applications.show', [
                'loan_application' => $application,
                'workspace' => 'profiles',
                'tab' => 'collateral',
                'person' => 'borrower',
            ]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Requested', $html);
        $this->assertStringContainsString('Waiting for borrower', $html);
        $this->assertStringContainsString('New collateral photo', $html);
        $this->assertStringContainsString('View request', $html);
        $this->assertStringContainsString('Cancel request', $html);
        $this->assertStringContainsString('Please upload the updated insurance document before we continue.', $html);

        $ids = $application->fresh()->documentRequests()->pluck('id')->all();
        $this->actingAs($admin, 'admin')
            ->post(route('admin.loan-applications.document-requests.cancel', $application), [
                'ids' => $ids,
                'confirmed' => '1',
                'return_workspace' => 'profiles',
                'return_tab' => 'collateral',
                'review_person' => 'borrower',
            ])
            ->assertRedirect();

        $this->assertSame(['cancelled', 'cancelled'], $application->fresh()->documentRequests()->pluck('status')->all());
    }

    public function test_add_collateral_opens_type_as_wizard_step(): void
    {
        $customer = $this->completeBorrower();
        $this->completeAsset($customer, 'Plot A');

        $this->actingAs($customer->user)
            ->get(route('site.borrower.profile', ['section' => 'assets']))
            ->assertOk()
            ->assertDontSee(__('borrower.profile.choose_asset_type'), false)
            ->assertSee(__('borrower.profile.add_new_collateral'), false);

        $this->actingAs($customer->user)
            ->get(route('site.borrower.profile', [
                'section' => 'assets',
                'add' => 1,
            ]))
            ->assertOk()
            ->assertSee(__('borrower.profile.choose_asset_type'), false)
            ->assertSee(__('borrower.profile.asset_types.vehicle'), false)
            ->assertDontSee('name="label"', false);

        $this->actingAs($customer->user)
            ->get(route('site.borrower.profile', [
                'section' => 'assets',
                'add' => 1,
                'type' => 'vehicle',
            ]))
            ->assertOk()
            ->assertSee('valuationCamera', false)
            ->assertSee('formMode', false)
            ->assertSee('guided-photos-ready', false)
            ->assertSee(__('site.partner_portal.valuation_start_photos'), false)
            ->assertSee('photos[front]', false)
            ->assertSee('photos[left]', false);
    }

    public function test_account_shells_opt_into_view_transitions(): void
    {
        $customer = $this->completeBorrower();

        $this->actingAs($customer->user)
            ->get(route('site.borrower.dashboard'))
            ->assertOk()
            ->assertSee('kf-chrome-sidebar', false)
            ->assertSee('kf-chrome-page', false)
            ->assertSee('data-kf-motion="tab"', false)
            ->assertSee('$store.kfSaving', false)
            ->assertSee('items-center justify-center p-4', false);
    }

    public function test_vehicle_thumbnail_uses_asset_photo_not_person_shot(): void
    {
        $customer = $this->completeBorrower();
        $asset = CustomerAsset::create([
            'customer_id' => $customer->id,
            'asset_type' => 'vehicle',
            'label' => 'Vitz',
            'is_active' => true,
            'photo_paths' => ['assets/vitz-front.jpg', 'assets/vitz-side.jpg'],
            'metadata' => [
                'person_with_asset_path' => 'assets/selfie.jpg',
                'ownership_document_path' => 'assets/title.pdf',
            ],
        ]);

        $this->assertSame('assets/vitz-front.jpg', $asset->thumbnailPath());
        $this->assertSame('assets/selfie.jpg', $asset->galleryPaths()[2] ?? null);
    }

    public function test_opening_group_review_does_not_pledge_every_saved_leader_asset(): void
    {
        $customer = $this->completeBorrower();
        $customer->update(['first_name' => 'Gaspari']);
        $product = LoanProduct::create([
            'code' => 'GL-AST-'.random_int(100, 999),
            'name' => 'Group Loan',
            'category' => 'group',
            'is_active' => true,
            'interest_rate' => 0.15,
            'min_amount' => 100_000,
            'max_amount' => 5_000_000,
            'tenure_min_months' => 3,
            'tenure_max_months' => 12,
        ]);
        $application = LoanApplication::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'application_number' => 'APP-GL-AST-'.random_int(1000, 9999),
            'status' => 'under_review',
            'current_stage' => 'screening',
            'requested_amount' => 800_000,
            'requested_tenure_months' => 6,
            'submitted_at' => now(),
        ]);
        $group = LoanGroup::create([
            'group_number' => 'GRP-AST-'.random_int(100, 999),
            'name' => 'Asset Group',
            'leader_customer_id' => $customer->id,
            'primary_application_id' => $application->id,
            'status' => 'active',
            'target_member_count' => 2,
        ]);
        LoanGroupMember::create([
            'loan_group_id' => $group->id,
            'customer_id' => $customer->id,
            'loan_application_id' => $application->id,
            'role' => 'leader',
            'requested_amount' => 400_000,
            'sort_order' => 1,
            'member_status' => 'active',
        ]);
        $application->update(['loan_group_id' => $group->id]);

        $pledged = $this->completeAsset($customer, 'Toyota Rav4');
        $this->completeAsset($customer, 'Vitz');
        LoanApplicationAsset::create([
            'loan_application_id' => $application->id,
            'customer_asset_id' => $pledged->id,
            'asset_type' => 'land',
            'uw_status' => LoanApplicationAsset::UW_PENDING,
        ]);

        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-applications.show', [
                'loan_application' => $application,
                'workspace' => 'profiles',
                'tab' => 'collateral',
            ]))
            ->assertOk()
            ->assertSee('Toyota Rav4', false)
            ->assertDontSee('>Vitz<', false)
            ->assertSee('On this loan', false)
            ->assertDontSee('Saved', false);

        $this->assertSame(1, LoanApplicationAsset::query()->where('loan_application_id', $application->id)->count());
    }

    public function test_review_keeps_one_on_this_loan_when_leftover_pledges_exist(): void
    {
        $customer = $this->completeBorrower();
        $product = LoanProduct::create([
            'code' => 'GL-HEAL-'.random_int(100, 999),
            'name' => 'Group Loan',
            'category' => 'group',
            'is_active' => true,
            'interest_rate' => 0.15,
            'min_amount' => 100_000,
            'max_amount' => 5_000_000,
            'tenure_min_months' => 3,
            'tenure_max_months' => 12,
        ]);
        $application = LoanApplication::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'application_number' => 'APP-GL-HEAL-'.random_int(1000, 9999),
            'status' => 'under_review',
            'current_stage' => 'screening',
            'requested_amount' => 800_000,
            'requested_tenure_months' => 6,
            'submitted_at' => now(),
        ]);
        $group = LoanGroup::create([
            'group_number' => 'GRP-HEAL-'.random_int(100, 999),
            'name' => 'Heal Group',
            'leader_customer_id' => $customer->id,
            'primary_application_id' => $application->id,
            'status' => 'active',
            'target_member_count' => 2,
        ]);
        LoanGroupMember::create([
            'loan_group_id' => $group->id,
            'customer_id' => $customer->id,
            'loan_application_id' => $application->id,
            'role' => 'leader',
            'requested_amount' => 400_000,
            'sort_order' => 1,
            'member_status' => 'active',
        ]);
        $application->update(['loan_group_id' => $group->id]);

        $rav4 = CustomerAsset::create([
            'customer_id' => $customer->id,
            'asset_type' => 'vehicle',
            'label' => 'Toyota Rav4',
            'is_active' => true,
            'photo_paths' => ['assets/rav4-front.jpg', 'assets/rav4-back.jpg'],
            'metadata' => [
                'person_with_asset_path' => 'assets/selfie.jpg',
                'ownership_document_path' => 'assets/title.pdf',
                'insurance_document_path' => 'assets/ins.pdf',
            ],
        ]);
        $vitz = CustomerAsset::create([
            'customer_id' => $customer->id,
            'asset_type' => 'vehicle',
            'label' => 'Vitz',
            'is_active' => true,
            'photo_paths' => ['assets/vitz-front.jpg', 'assets/vitz-back.jpg'],
            'metadata' => [
                'person_with_asset_path' => 'assets/selfie2.jpg',
                'ownership_document_path' => 'assets/title2.pdf',
            ],
        ]);
        LoanApplicationAsset::create([
            'loan_application_id' => $application->id,
            'customer_asset_id' => $rav4->id,
            'asset_type' => 'vehicle',
            'uw_status' => LoanApplicationAsset::UW_PENDING,
            'is_primary' => true,
        ]);
        LoanApplicationAsset::create([
            'loan_application_id' => $application->id,
            'customer_asset_id' => $vitz->id,
            'asset_type' => 'vehicle',
            'uw_status' => LoanApplicationAsset::UW_PENDING,
            'is_primary' => false,
        ]);

        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-applications.show', [
                'loan_application' => $application,
                'workspace' => 'profiles',
                'tab' => 'collateral',
            ]))
            ->assertOk()
            ->assertSee('Toyota Rav4', false)
            ->assertDontSee('>Vitz<', false)
            ->assertSee('1 collateral asset', false)
            ->assertDontSee('Saved', false);

        $this->assertSame(1, LoanApplicationAsset::query()->where('loan_application_id', $application->id)->count());
        $this->assertSame(
            $rav4->id,
            (int) LoanApplicationAsset::query()->where('loan_application_id', $application->id)->value('customer_asset_id')
        );
        $this->assertSame(
            [
                'front' => 'assets/rav4-front.jpg',
                'back' => 'assets/rav4-back.jpg',
                'owner' => 'assets/selfie.jpg',
            ],
            $rav4->photosByAngle()
        );
    }

    public function test_borrower_can_pledge_a_second_asset_and_admin_cannot(): void
    {
        $customer = $this->completeBorrower();
        $application = $this->applicationFor($customer);
        $first = $this->completeAsset($customer, 'Rav4');
        $second = $this->completeAsset($customer, 'Vitz');
        $admin = User::factory()->create(['role' => 'admin']);

        app(CustomerAssetService::class)->attachToApplication($first, $application, $customer);
        app(CustomerAssetService::class)->attachToApplication($second, $application, $customer);

        $this->assertSame(
            [$first->id, $second->id],
            app(CustomerAssetService::class)->onLoanAssetIds($application->fresh())
        );

        $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-applications.show', [
                'loan_application' => $application,
                'workspace' => 'profiles',
                'tab' => 'collateral',
            ]))
            ->assertOk()
            ->assertSee('2 collateral asset', false)
            ->assertDontSee('Use on this loan', false);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.loan-applications.collateral.request-additional', $application))
            ->assertRedirect();

        $this->assertDatabaseHas('loan_application_document_requests', [
            'loan_application_id' => $application->id,
            'label' => 'Add collateral asset',
            'status' => 'pending',
        ]);
    }

    public function test_view_collateral_lists_only_loan_requested_documents(): void
    {
        $customer = $this->completeBorrower();
        $application = $this->applicationFor($customer);
        $pledged = $this->completeAsset($customer, 'Toyota Rav4');
        app(CustomerAssetService::class)->attachToApplication($pledged, $application, $customer);
        $admin = User::factory()->create(['role' => 'admin']);

        app(ApplicationDocumentRequestService::class)->create(
            $application->fresh(),
            $admin,
            'Updated collateral insurance certificate',
        );

        $html = $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-applications.show', [
                'loan_application' => $application,
                'workspace' => 'profiles',
                'tab' => 'collateral',
                'person' => 'borrower',
            ]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Documents requested for this loan', $html);
        $this->assertStringContainsString('Updated collateral insurance certificate', $html);
        $this->assertStringContainsString('On the asset record', $html);
        $this->assertStringNotContainsString('No additional collateral documents have been requested for this loan.', $html);
    }

    public function test_checklist_hides_outstanding_documents_once_accepted(): void
    {
        $customer = $this->completeBorrower();
        $application = $this->applicationFor($customer);
        $admin = User::factory()->create(['role' => 'admin']);
        $open = app(ApplicationDocumentRequestService::class)->create(
            $application->fresh(),
            $admin,
            'Updated Bank Statement',
        );

        $asked = $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-applications.show', [
                'loan_application' => $application,
                'workspace' => 'checklist',
            ]))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('Outstanding requests (1)', $asked);
        $this->assertStringContainsString('Updated Bank Statement', $asked);
        $this->assertStringContainsString('Requested ', $asked);

        $open->update([
            'status' => 'satisfied',
            'satisfied_at' => now(),
            'satisfied_by' => $admin->id,
        ]);

        $done = $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-applications.show', [
                'loan_application' => $application,
                'workspace' => 'checklist',
            ]))
            ->assertOk()
            ->getContent();
        $this->assertStringNotContainsString('Outstanding requests', $done);
        $this->assertStringNotContainsString('Application evidence', $done);

        $history = $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-applications.show', [
                'loan_application' => $application,
                'workspace' => 'profiles',
                'tab' => 'documents',
                'person' => 'borrower',
            ]))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('Updated Bank Statement', $history);
        $this->assertStringContainsString('Application evidence', $history);
    }
}
