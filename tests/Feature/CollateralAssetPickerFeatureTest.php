<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerAsset;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\LoanApplicationAsset;
use App\Models\LoanProduct;
use App\Models\User;
use App\Services\ApplicationDocumentRequestService;
use App\Services\CustomerAssetService;
use App\Services\PinRecoveryChallengeService;
use App\Services\PinService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CollateralAssetPickerFeatureTest extends TestCase
{
    use RefreshDatabase;

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
            'metadata' => ['ownership_document_path' => 'assets/title.pdf'],
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
            ->assertSee('Shamba la kiongozi', false)
            ->assertSee(__('borrower.profile.collateral_use_this'), false)
            ->assertSee(__('borrower.profile.collateral_ready'), false)
            ->assertSee(__('borrower.profile.view_asset'), false);
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

    public function test_admin_collateral_tab_lists_all_assets_with_pledged_first(): void
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
        $this->assertStringContainsString('>Vitz<', $html);
        $this->assertStringContainsString('On this loan', $html);
        $this->assertStringContainsString('Saved', $html);
        $this->assertTrue(
            strpos($html, 'Toyota Rav4') < strpos($html, '>Vitz<'),
            'Pledged asset should appear before other profile assets'
        );
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
}
