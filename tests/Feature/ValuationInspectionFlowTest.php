<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerAsset;
use App\Models\CustomerPayment;
use App\Models\LoanApplication;
use App\Models\LoanApplicationAsset;
use App\Models\LoanProduct;
use App\Models\Setting;
use App\Models\User;
use App\Models\Vendor;
use App\Services\CustomerAssetService;
use App\Services\PartnerMembershipService;
use App\Services\PartnerWelcomeService;
use App\Services\PinRecoveryChallengeService;
use App\Services\PinService;
use App\Services\ValuationPartnerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
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

    private function completeValuerForJobs(Vendor $valuer, bool $payMembership = true): Vendor
    {
        $valuer->update([
            'phone' => '255700000001',
            'email' => 'valuer@test.local',
            'legal_name' => 'Inspection Valuer Ltd',
            'registration_number' => 'REG-VAL-1',
            'metadata' => [
                'contact_person' => ['name' => 'Jane Contact'],
                'identity' => [
                    'national_id' => '19800101123456789012',
                    'no_physical_nida_card' => true,
                ],
                'residence' => [
                    'region' => 'Dar es Salaam',
                    'district' => 'Ilala',
                ],
                'payout_account' => ['type' => 'mobile_money'],
            ],
        ]);

        if ($payMembership) {
            app(PartnerMembershipService::class)->activate($valuer);
        }

        $terms = app(\App\Services\PartnerTermsService::class);
        if ($terms->appliesTo($valuer) && ! $terms->hasSatisfiedTerms($valuer)) {
            $terms->accept($valuer, \Illuminate\Http\Request::create('/partner/terms', 'POST'));
        }

        return $valuer->fresh();
    }

    private function requiredPhotoTotal(): int
    {
        return count(app(\App\Services\ValuationEvidenceService::class)->requiredAngles('vehicle'));
    }

    public function test_valuer_task_uses_tabs_and_hides_loan_details(): void
    {
        [$user, $valuer] = $this->makeValuerUser();
        $this->completeValuerForJobs($valuer);
        [, $assignment] = $this->assignJob($valuer);
        $task = $assignment->vendorTask;

        $this->actingAs($user)
            ->withSession(['locale' => 'en'])
            ->get(route('site.partner.task', $task))
            ->assertOk()
            ->assertSee(__('site.partner_portal.tab_asset'), false)
            ->assertSee(__('site.partner_portal.valuation_step_photos'), false)
            ->assertSee(__('site.partner_portal.tab_inspect'), false)
            ->assertSee(__('site.partner_portal.tab_values'), false)
            ->assertSee(__('site.partner_portal.valuation_step_review'), false)
            ->assertDontSee(__('site.partner_portal.tab_overview'), false)
            ->assertSee(__('site.partner_portal.valuation_no_loan_hint'), false)
            ->assertSee(__('site.partner_portal.valuation_start_work'), false)
            ->assertDontSee('777,777', false)
            ->assertDontSee('777777', false)
            ->assertDontSee('APP-INSP-77', false)
            ->assertDontSee('Related loan', false)
            ->assertDontSee('assets/owner.jpg', false)
            ->assertDontSee(__('site.partner_portal.valuation_owner_reference'), false)
            ->assertDontSee(__('site.partner_portal.valuation_no_owner_photo'), false)
            ->assertSee(__('site.partner_portal.valuation_camera_retry'), false)
            ->assertSee(__('site.partner_portal.valuation_camera_close'), false)
            ->assertSee('Geofrey Owner', false)
            ->assertSee('T123ABC', false)
            ->assertSee(__('site.partner_portal.valuation_belongs_to'), false)
            ->assertSee(__('borrower.profile.uploading_documents'), false)
            ->assertSee('isDeterminate', false);
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
        $branch = Branch::query()->firstOrCreate(
            ['code' => 'BR-INSP'],
            [
                'name' => 'Inspection Branch',
                'region' => 'Dar es Salaam',
                'is_active' => true,
            ],
        );

        $customer = Customer::create([
            'branch_id' => $branch->id,
            'customer_number' => 'CU-INSP-'.uniqid(),
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Geofrey',
            'last_name' => 'Owner',
            'phone' => '255900000002',
            'region' => 'Dar es Salaam',
            'district' => 'Kigoma',
            'street' => 'Kigoma Rural',
        ]);

        $product = LoanProduct::query()->firstOrCreate(
            ['code' => 'AB'],
            [
                'name' => 'Asset Backed',
                'is_active' => true,
                'interest_rate' => 3.5,
                'min_amount' => 100_000,
                'max_amount' => 10_000_000,
                'tenure_min_months' => 3,
                'tenure_max_months' => 24,
            ],
        );

        $application = LoanApplication::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'application_number' => 'APP-INSP-'.uniqid(),
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

    public function test_start_work_opens_inspection_and_blocks_complete_without_photos(): void
    {
        Storage::fake('public');
        [$user, $valuer] = $this->makeValuerUser();
        $this->completeValuerForJobs($valuer);
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
            ->assertSee(__('site.partner_portal.valuation_start_photos'), false)
            ->assertSee(__('site.partner_portal.valuation_photos_done', [
                'done' => 0,
                'total' => $this->requiredPhotoTotal(),
            ]), false)
            ->assertDontSee(__('site.partner_portal.valuation_owner_reference'), false)
            ->assertDontSee(__('site.partner_portal.valuation_no_owner_photo'), false)
            ->assertDontSee('assets/owner.jpg', false)
            ->assertDontSee('capture="environment"', false)
            ->assertDontSee('name="file"', false)
            ->assertDontSee(__('site.partner_portal.valuation_photo_progress', [
                'current' => 1,
                'total' => $this->requiredPhotoTotal() + 1,
            ]), false);

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

    public function test_swahili_inspection_uses_translated_angle_headings_and_advances_after_photo(): void
    {
        Storage::fake('public');
        [$user, $valuer] = $this->makeValuerUser();
        $this->completeValuerForJobs($valuer);
        [, $assignment, $asset] = $this->assignJob($valuer);
        $task = $assignment->vendorTask;

        $this->actingAs($user)->post(route('site.partner.task.start', $task));

        $this->actingAs($user)
            ->withSession(['locale' => 'sw'])
            ->get(route('site.partner.task', ['task' => $task, 'tab' => 'inspect']))
            ->assertOk()
            ->assertSee(__('site.partner_portal.valuation_start_photos'), false)
            ->assertSee(__('site.partner_portal.valuation_photos_done', [
                'done' => 0,
                'total' => $this->requiredPhotoTotal(),
            ]), false)
            ->assertSee('Mbele', false)
            ->assertSee('Nyuma', false)
            ->assertDontSee(__('site.partner_portal.valuation_owner_reference'), false)
            ->assertDontSee('>Back</h3>', false);

        $this->actingAs($user)
            ->withSession(['locale' => 'sw'])
            ->postJson(route('site.partner.task.inspect.photo', $task), [
                'customer_asset_id' => $asset->id,
                'angle' => 'front',
                'file' => UploadedFile::fake()->image('front.jpg'),
            ])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->actingAs($user)
            ->withSession(['locale' => 'sw'])
            ->post(route('site.partner.task.inspect.photo', $task), [
                'customer_asset_id' => $asset->id,
                'angle' => 'back',
                'file' => UploadedFile::fake()->image('back.jpg'),
            ])
            ->assertRedirect();

        $this->actingAs($user)
            ->withSession(['locale' => 'sw'])
            ->get(route('site.partner.task', ['task' => $task, 'tab' => 'inspect']))
            ->assertOk()
            ->assertSee(__('site.partner_portal.valuation_photos_done', [
                'done' => 2,
                'total' => $this->requiredPhotoTotal(),
            ]), false)
            ->assertDontSee(__('site.partner_portal.valuation_photo_progress', [
                'current' => 3,
                'total' => $this->requiredPhotoTotal() + 1,
            ]), false);
    }

    public function test_formatted_values_complete_after_camera_photos_and_seeded_checks(): void
    {
        Storage::fake('public');
        [$user, $valuer] = $this->makeValuerUser();
        $this->completeValuerForJobs($valuer);
        [, $assignment, $asset] = $this->assignJob($valuer);
        $task = $assignment->vendorTask;

        $this->actingAs($user)->post(route('site.partner.task.start', $task));

        foreach (app(\App\Services\ValuationEvidenceService::class)->requiredAngles('vehicle') as $angle) {
            $this->actingAs($user)
                ->post(route('site.partner.task.inspect.photo', $task), [
                    'customer_asset_id' => $asset->id,
                    'angle' => $angle,
                    'file' => UploadedFile::fake()->image($angle.'.jpg'),
                ])
                ->assertRedirect();
        }

        $this->actingAs($user)
            ->post(route('site.partner.task.complete', $task), [
                'values' => [
                    $asset->id => [
                        'market_value' => '5,000,000',
                        'forced_sale_value' => '4,250,000',
                    ],
                ],
            ])
            ->assertSessionHasErrors();

        $this->actingAs($user)
            ->post(route('site.partner.task.inspect.checks', $task), [
                'body_condition' => 'good',
                'tyres' => 'good',
                'interior' => 'good',
                'engine' => 'starts_smooth',
                'test_drive' => 'drives_normal',
            ])
            ->assertRedirect(route('site.partner.task', ['task' => $task, 'step' => 'values']));

        $this->actingAs($user)
            ->withSession(['locale' => 'en'])
            ->get(route('site.partner.task', ['task' => $task, 'step' => 'values']))
            ->assertOk()
            ->assertSee(__('site.partner_portal.valuation_market_value'), false)
            ->assertSee(__('site.partner_portal.valuation_review_before'), false)
            ->assertSee(__('site.partner_portal.valuation_fsv'), false);

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

        $this->actingAs($user)
            ->withSession(['locale' => 'en'])
            ->get(route('site.partner.task', $task))
            ->assertOk()
            ->assertSee('Toyota Rav4', false)
            ->assertSee('T123ABC', false)
            ->assertSee(__('site.partner_portal.valuation_belongs_to'), false)
            ->assertSee(__('borrower.profile.uploading_documents'), false);
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

    public function test_incomplete_profile_cannot_receive_or_start_a_valuation_job(): void
    {
        [$user, $valuer] = $this->makeValuerUser();

        try {
            $this->assignJob($valuer);
            $this->fail('Incomplete valuers must not receive assignments.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('vendor_id', $e->errors());
        }

        $this->completeValuerForJobs($valuer);
        [, $assignment] = $this->assignJob($valuer);
        $task = $assignment->vendorTask;

        $valuer->update([
            'phone' => null,
            'email' => null,
            'metadata' => [],
        ]);

        $this->actingAs($user)
            ->withSession(['locale' => 'en'])
            ->get(route('site.partner.task', $task))
            ->assertOk()
            ->assertSee(__('site.partner_portal.job_requires_profile'), false)
            ->assertSee(__('site.partner_portal.cta_complete_profile'), false)
            ->assertDontSee(__('site.partner_portal.valuation_start_work'), false);

        $this->actingAs($user)
            ->from(route('site.partner.task', $task))
            ->post(route('site.partner.task.start', $task))
            ->assertRedirect(route('site.partner.profile'));

        $this->actingAs($user)
            ->from(route('site.partner.task', $task))
            ->post(route('site.partner.task.accept', $task))
            ->assertRedirect(route('site.partner.profile'));
    }

    public function test_unpaid_membership_cannot_receive_or_start_until_paid(): void
    {
        [$user, $valuer] = $this->makeValuerUser();
        $this->completeValuerForJobs($valuer, payMembership: false);

        try {
            $this->assignJob($valuer);
            $this->fail('Unpaid membership valuers must not receive assignments.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('vendor_id', $e->errors());
        }

        $this->actingAs($user)
            ->withSession(['locale' => 'en'])
            ->get(route('site.partner.dashboard'))
            ->assertOk()
            ->assertSee(__('site.partner_portal.cta_pay_membership'), false);

        app(PartnerMembershipService::class)->activate($valuer);
        [, $assignment] = $this->assignJob($valuer->fresh());
        $task = $assignment->vendorTask;

        $valuer->update([
            'membership_status' => 'expired',
            'membership_expires_at' => now()->subDay(),
        ]);

        $this->actingAs($user)
            ->withSession(['locale' => 'en'])
            ->get(route('site.partner.task', $task))
            ->assertOk()
            ->assertSee(__('site.partner_portal.job_requires_payment'), false)
            ->assertSee(__('site.partner_portal.cta_pay_membership'), false);

        $this->actingAs($user)
            ->from(route('site.partner.task', $task))
            ->post(route('site.partner.task.start', $task))
            ->assertRedirect(route('site.partner.membership.pay'));

        Setting::set('partners.membership', [
            'enabled' => true,
            'default_fee_amount' => 15000,
            'default_duration_days' => 365,
            'grace_period_days' => 14,
            'notify_days_before_expiry' => 30,
            'categories_requiring_payment' => ['valuer' => true],
            'category_fees' => ['valuer' => 15000],
        ]);

        $this->actingAs($user)
            ->withSession(['locale' => 'en'])
            ->get(route('site.partner.membership.pay'))
            ->assertOk()
            ->assertSee(__('borrower.membership.pay_now'), false)
            ->assertDontSee(__('borrower.membership.apply_promo_link'), false)
            ->assertDontSee(__('site.partner_portal.membership_confirm_paid'), false)
            ->assertSee('15,000', false)
            ->assertDontSee('50,000', false);

        app(PartnerMembershipService::class)->activate($valuer->fresh());

        $this->assertTrue(app(PartnerMembershipService::class)->isActive($valuer->fresh()));

        $this->actingAs($user)
            ->post(route('site.partner.task.start', $task))
            ->assertRedirect(route('site.partner.task', ['task' => $task, 'tab' => 'inspect']));
    }

    public function test_paid_membership_profile_shows_days_remaining(): void
    {
        [$user, $valuer] = $this->makeValuerUser();
        $this->completeValuerForJobs($valuer, payMembership: true);

        $days = app(PartnerMembershipService::class)->daysRemaining($valuer->fresh());

        $this->actingAs($user)
            ->withSession(['locale' => 'en'])
            ->get(route('site.partner.profile'))
            ->assertOk()
            ->assertSee((string) $days, false)
            ->assertSee(__('borrower.membership.days_unit'), false);

        $this->actingAs($user)
            ->withSession(['locale' => 'en'])
            ->get(route('site.partner.dashboard'))
            ->assertOk()
            ->assertDontSee(__('borrower.membership.days_unit'), false);
    }

    public function test_payment_form_shows_save_button(): void
    {
        [$user, $valuer] = $this->makeValuerUser();
        $this->completeValuerForJobs($valuer, payMembership: false);

        $this->actingAs($user)
            ->withSession(['locale' => 'en'])
            ->get(route('site.partner.profile', ['section' => 'payment']))
            ->assertOk()
            ->assertSee(__('site.partner_account.save_payment'), false);
    }

    public function test_unpaid_dashboard_explains_valuer_membership_fee(): void
    {
        [$user, $valuer] = $this->makeValuerUser();
        $this->completeValuerForJobs($valuer, payMembership: false);

        $this->actingAs($user)
            ->withSession(['locale' => 'en'])
            ->get(route('site.partner.dashboard'))
            ->assertOk()
            ->assertSee(__('site.partner_portal.membership_due_title'), false)
            ->assertSee(__('site.partner_portal.cta_pay_membership'), false)
            ->assertSee(route('site.partner.membership.pay'), false)
            ->assertDontSee(__('borrower.membership.days_unit'), false);
    }

    public function test_first_login_welcome_lands_in_the_notification_bell(): void
    {
        [$user, $valuer] = $this->makeValuerUser();

        app(PartnerWelcomeService::class)->sendIfFirstLogin($user);

        $logs = \App\Models\NotificationLog::query()->where('template', 'partner_welcome')->get();
        $this->assertNotEmpty($logs);
        $count = $logs->count();

        app(PartnerWelcomeService::class)->sendIfFirstLogin($user->fresh());

        $this->assertSame(
            $count,
            \App\Models\NotificationLog::query()->where('template', 'partner_welcome')->count()
        );
    }

    public function test_group_member_collateral_loads_on_the_shared_card(): void
    {
        [$user, $valuer] = $this->makeValuerUser();
        $this->completeValuerForJobs($valuer);

        $leader = Customer::create([
            'customer_number' => 'CU-GL-LEAD',
            'type' => 'group',
            'status' => 'active',
            'first_name' => 'Leader',
            'last_name' => 'Kopa',
            'phone' => '255900000010',
            'region' => 'Dar es Salaam',
        ]);
        $member = Customer::create([
            'customer_number' => 'CU-GL-MEM',
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Rogate',
            'last_name' => 'Nyela',
            'phone' => '255900000011',
            'region' => 'Dar es Salaam',
        ]);
        $product = LoanProduct::query()->firstOrCreate(
            ['code' => 'GL'],
            [
                'name' => 'Group Loan',
                'category' => 'group',
                'is_active' => true,
                'interest_rate' => 0.15,
                'min_amount' => 100_000,
                'max_amount' => 10_000_000,
                'tenure_min_months' => 3,
                'tenure_max_months' => 12,
            ],
        );
        $application = LoanApplication::create([
            'customer_id' => $leader->id,
            'loan_product_id' => $product->id,
            'application_number' => 'APP-GL-VAL-1',
            'status' => 'under_review',
            'current_stage' => 'screening',
            'requested_amount' => 1_500_000,
            'requested_tenure_months' => 6,
        ]);
        $asset = $this->makeVehicleAsset($member);
        $asset->update(['label' => 'Member Prado']);
        LoanApplicationAsset::create([
            'loan_application_id' => $application->id,
            'customer_asset_id' => $asset->id,
            'asset_type' => 'vehicle',
            'uw_status' => LoanApplicationAsset::UW_PENDING,
            'is_primary' => true,
            'valuation_status' => 'awaiting_valuation',
            'valuation_fee_paid_at' => now(),
        ]);
        app(CustomerAssetService::class)->persistOnLoanIds($application, [$asset->id]);

        CustomerPayment::create([
            'reference' => 'VAL-GL-EXISTING',
            'customer_id' => $leader->id,
            'payment_type' => 'valuation_fee',
            'payment_method' => 'mobile_money',
            'amount' => 50_000,
            'currency' => 'TZS',
            'status' => 'verified',
            'paid_at' => now(),
            'source_type' => $application->getMorphClass(),
            'source_id' => $application->id,
        ]);

        $admin = User::factory()->create(['role' => 'super_admin']);
        $assignment = app(ValuationPartnerService::class)->assign($application, $valuer, $admin, 'Group valuation');
        $task = $assignment->vendorTask;

        $this->assertSame(1, CustomerPayment::query()->where('payment_type', 'valuation_fee')->count());
        $this->assertSame('VAL-GL-EXISTING', CustomerPayment::query()->value('reference'));

        $this->actingAs($user)
            ->withSession(['locale' => 'en'])
            ->get(route('site.partner.task', $task))
            ->assertOk()
            ->assertSee('Member Prado', false)
            ->assertSee('Rogate Nyela', false)
            ->assertSee(__('site.partner_portal.valuation_belongs_to'), false)
            ->assertSee(__('site.partner_portal.valuation_camera_retry'), false)
            ->assertDontSee('1,500,000', false)
            ->assertDontSee('1500000', false);

        $this->actingAs($user)->post(route('site.partner.task.start', $task));
        $this->actingAs($user)
            ->post(route('site.partner.task.inspect.checks', $task), [
                'engine' => 'starts_smooth',
            ])
            ->assertRedirect(route('site.partner.task', ['task' => $task, 'step' => 'condition']));
    }
}
