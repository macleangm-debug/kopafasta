<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerAsset;
use App\Models\LoanApplication;
use App\Models\LoanApplicationAsset;
use App\Models\LoanProduct;
use App\Models\User;
use App\Models\Vendor;
use App\Services\CustomerAssetService;
use App\Services\PinRecoveryChallengeService;
use App\Services\PinService;
use App\Services\ValuationPartnerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ValuationInspectionFlowTest extends TestCase
{
    use RefreshDatabase;

    private function makeValuerUser(): array
    {
        $user = User::factory()->create(['role' => 'vendor']);
        app(PinService::class)->setPin($user, '1234');

        $valuer = Vendor::create([
            'user_id' => $user->id,
            'vendor_number' => 'V-INSP-1',
            'name' => 'Inspection Valuer',
            'category' => 'valuer',
            'status' => 'active',
            'partner_cost' => 30_000,
            'regions' => ['Dar es Salaam'],
        ]);

        return [$user, $valuer];
    }

    private function makeVehicleAsset(Customer $customer): CustomerAsset
    {
        return CustomerAsset::create([
            'customer_id' => $customer->id,
            'asset_type' => 'vehicle',
            'label' => 'Toyota Rav4',
            'registration_number' => 'T123ABC',
            'is_active' => true,
            'photo_paths' => [
                'assets/front.jpg',
                'assets/back.jpg',
                'assets/left.jpg',
                'assets/right.jpg',
            ],
            'metadata' => [
                'photo_angles' => [
                    'front' => 'assets/front.jpg',
                    'back' => 'assets/back.jpg',
                    'left' => 'assets/left.jpg',
                    'right' => 'assets/right.jpg',
                ],
                'person_with_asset_path' => 'assets/owner.jpg',
                'ownership_document_path' => 'assets/title.pdf',
                'insurance_document_path' => 'assets/ins.pdf',
                'details' => [
                    'make' => 'Toyota',
                    'year' => 2018,
                    'insurance_expires_at' => now()->addYear()->toDateString(),
                ],
            ],
        ]);
    }

    private function assignJob(Vendor $valuer, int $requestedAmount = 777_777): array
    {
        $branch = Branch::create([
            'code' => 'BR-INSP',
            'name' => 'Inspection Branch',
            'region' => 'Dar es Salaam',
            'is_active' => true,
        ]);

        $customer = Customer::create([
            'branch_id' => $branch->id,
            'customer_number' => 'CU-INSP-001',
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Geofrey',
            'last_name' => 'Owner',
            'phone' => '255900000002',
            'region' => 'Dar es Salaam',
            'district' => 'Kigoma',
            'street' => 'Kigoma Rural',
        ]);

        $product = LoanProduct::create([
            'code' => 'AB',
            'name' => 'Asset Backed',
            'is_active' => true,
            'interest_rate' => 3.5,
            'min_amount' => 100_000,
            'max_amount' => 10_000_000,
            'tenure_min_months' => 3,
            'tenure_max_months' => 24,
        ]);

        $application = LoanApplication::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'application_number' => 'APP-INSP-77',
            'status' => 'submitted',
            'current_stage' => 'submitted',
            'requested_amount' => $requestedAmount,
            'requested_tenure_months' => 12,
        ]);

        $asset = $this->makeVehicleAsset($customer);
        LoanApplicationAsset::create([
            'loan_application_id' => $application->id,
            'customer_asset_id' => $asset->id,
            'asset_type' => 'vehicle',
            'uw_status' => LoanApplicationAsset::UW_PENDING,
            'is_primary' => true,
            'valuation_status' => 'awaiting_valuation',
        ]);

        $admin = User::factory()->create(['role' => 'super_admin']);
        $assignment = app(ValuationPartnerService::class)->assign(
            $application,
            $valuer,
            $admin,
            'Inspect at owner location',
        );

        return [$application, $assignment->fresh('vendorTask'), $asset];
    }

    public function test_valuer_task_uses_tabs_and_hides_loan_details(): void
    {
        [$user, $valuer] = $this->makeValuerUser();
        [, $assignment] = $this->assignJob($valuer);
        $task = $assignment->vendorTask;

        $this->actingAs($user)
            ->withSession(['locale' => 'en'])
            ->get(route('site.partner.task', $task))
            ->assertOk()
            ->assertSee(__('site.partner_portal.tab_overview'), false)
            ->assertSee(__('site.partner_portal.tab_asset'), false)
            ->assertSee(__('site.partner_portal.tab_inspect'), false)
            ->assertSee(__('site.partner_portal.tab_values'), false)
            ->assertSee(__('site.partner_portal.valuation_no_loan_hint'), false)
            ->assertSee(__('site.partner_portal.valuation_start_work'), false)
            ->assertDontSee('777,777', false)
            ->assertDontSee('777777', false)
            ->assertDontSee('APP-INSP-77', false)
            ->assertDontSee('Related loan', false);
    }

    public function test_start_work_opens_inspection_and_blocks_complete_without_photos(): void
    {
        Storage::fake('public');
        [$user, $valuer] = $this->makeValuerUser();
        [, $assignment, $asset] = $this->assignJob($valuer);
        $task = $assignment->vendorTask;

        $this->actingAs($user)
            ->post(route('site.partner.task.complete', $task), [
                'values' => [
                    $asset->id => [
                        'market_value' => '5,000,000',
                        'forced_sale_value' => '4,000,000',
                    ],
                ],
            ])
            ->assertSessionHasErrors();

        $this->actingAs($user)
            ->post(route('site.partner.task.start', $task))
            ->assertRedirect(route('site.partner.task', ['task' => $task, 'tab' => 'inspect']));

        $this->actingAs($user)
            ->withSession(['locale' => 'en'])
            ->get(route('site.partner.task', ['task' => $task, 'tab' => 'inspect']))
            ->assertOk()
            ->assertSee(__('site.partner_portal.valuation_photos_intro'), false)
            ->assertSee(__('borrower.document_upload.camera'), false)
            ->assertSee('capture="environment"', false);

        $this->actingAs($user)
            ->from(route('site.partner.task', ['task' => $task, 'tab' => 'inspect']))
            ->post(route('site.partner.task.proof', $task), [
                'angle' => 'front',
                'customer_asset_id' => $asset->id,
                'file' => UploadedFile::fake()->image('gallery.jpg'),
            ])
            ->assertRedirect(route('site.partner.task', ['task' => $task, 'tab' => 'inspect']))
            ->assertSessionHasErrors('file');
    }

    public function test_formatted_values_complete_after_camera_photos_and_seeded_checks(): void
    {
        Storage::fake('public');
        [$user, $valuer] = $this->makeValuerUser();
        [, $assignment, $asset] = $this->assignJob($valuer);
        $task = $assignment->vendorTask;

        $this->actingAs($user)->post(route('site.partner.task.start', $task));

        foreach (array_keys(CustomerAsset::photoAngleLabels('vehicle')) as $angle) {
            $this->actingAs($user)
                ->post(route('site.partner.task.inspect.photo', $task), [
                    'customer_asset_id' => $asset->id,
                    'angle' => $angle,
                    'file' => UploadedFile::fake()->image($angle.'.jpg'),
                ])
                ->assertRedirect();
        }

        $this->actingAs($user)
            ->post(route('site.partner.task.inspect.checks', $task), [
                'engine' => 'starts_smooth',
            ])
            ->assertRedirect();

        $this->actingAs($user)
            ->post(route('site.partner.task.inspect.checks', $task), [
                'engine' => 'starts_smooth',
                'test_drive' => 'drives_normal',
            ])
            ->assertRedirect(route('site.partner.task', ['task' => $task, 'tab' => 'values']));

        $this->actingAs($user)
            ->post(route('site.partner.task.complete', $task), [
                'values' => [
                    $asset->id => [
                        'market_value' => '5,000,000',
                        'forced_sale_value' => '4,250,000',
                    ],
                ],
            ])
            ->assertRedirect(route('site.partner.task', $task));

        $this->assertSame('completed', $assignment->fresh()->status);
        $this->assertEquals(5_000_000.0, (float) $assignment->fresh()->market_value);
        $this->assertEquals(4_250_000.0, (float) $assignment->fresh()->forced_sale_value);
    }

    public function test_borrower_cannot_save_asset_without_every_required_photo(): void
    {
        $user = User::factory()->create(['role' => 'borrower']);
        app(PinService::class)->setPin($user, '1234');
        app(PinRecoveryChallengeService::class)->enroll($user, [
            'mother_first_name' => 'Asha',
            'primary_school' => 'Uhuru Primary',
            'nida_middle4' => '4582',
        ]);

        Customer::create([
            'user_id' => $user->id,
            'customer_number' => 'CU-PHOTO-1',
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Owner',
            'last_name' => 'Photos',
            'phone' => '255712009999',
        ]);

        $this->actingAs($user)
            ->from(route('site.borrower.profile', ['section' => 'assets']))
            ->post(route('site.borrower.profile.assets.store'), [
                'asset_type' => 'land',
                'label' => 'Plot A',
                'details' => [
                    'plot_number' => 'P-1',
                    'location' => 'Kigoma',
                    'size' => '1 acre',
                    'land_use' => 'Residential',
                    'ownership' => 'Titled',
                ],
                'photos' => [
                    'front' => UploadedFile::fake()->image('front.jpg'),
                ],
                'person_photo' => UploadedFile::fake()->image('owner.jpg'),
                'ownership_document' => UploadedFile::fake()->create('title.pdf', 120, 'application/pdf'),
            ])
            ->assertRedirect(route('site.borrower.profile', ['section' => 'assets']))
            ->assertSessionHasErrors('photos');

        $this->assertSame(0, CustomerAsset::query()->count());
    }

    public function test_incomplete_reason_requires_every_vehicle_angle(): void
    {
        $customer = Customer::create([
            'customer_number' => 'CU-INC-1',
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'A',
            'last_name' => 'B',
            'phone' => '255700000001',
        ]);

        $asset = CustomerAsset::create([
            'customer_id' => $customer->id,
            'asset_type' => 'vehicle',
            'label' => 'Vitz',
            'is_active' => true,
            'photo_paths' => ['assets/front.jpg', 'assets/back.jpg'],
            'metadata' => [
                'person_with_asset_path' => 'assets/owner.jpg',
                'ownership_document_path' => 'assets/title.pdf',
                'insurance_document_path' => 'assets/ins.pdf',
            ],
        ]);

        $this->assertSame('photos', app(CustomerAssetService::class)->incompleteReason($asset));
        $this->assertFalse($asset->hasCompletePhotoSet());
    }
}
