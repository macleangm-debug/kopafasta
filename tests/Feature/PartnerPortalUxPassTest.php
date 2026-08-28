<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BrokenPage;
use App\Models\Customer;
use App\Models\CustomerAsset;
use App\Models\CustomerPayment;
use App\Models\LoanApplication;
use App\Models\LoanApplicationAsset;
use App\Models\LoanProduct;
use App\Models\NotificationLog;
use App\Models\PartnerTask;
use App\Models\User;
use App\Models\Vendor;
use App\Services\PinService;
use App\Services\ServicePartnerReassignmentService;
use App\Services\ValuationEvidenceService;
use App\Services\ValuationInspectionService;
use App\Services\ValuationPartnerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\CompletesPartnerJobs;
use Tests\TestCase;

class PartnerPortalUxPassTest extends TestCase
{
    use CompletesPartnerJobs;
    use RefreshDatabase;

    public function test_vehicle_evidence_comes_from_source_of_truth_not_borrower_angles(): void
    {
        $evidence = app(ValuationEvidenceService::class);
        $required = $evidence->requiredAngles('vehicle');

        $this->assertContains('dashboard', $required);
        $this->assertContains('vin', $required);
        $this->assertNotContains('damage', $required);
        $this->assertGreaterThan(count(CustomerAsset::photoAngleLabels('vehicle')), count($required));
        $this->assertNotContains('dashboard', array_keys(CustomerAsset::photoAngleLabels('vehicle')));
        $this->assertSame('land', $evidence->family('land'));
        $this->assertContains('survey', $evidence->requiredAngles('land'));
    }

    public function test_premium_404_is_bilingual_and_logs_for_support(): void
    {
        $this->get('/this-page-should-not-exist-kf')
            ->assertNotFound()
            ->assertSee('This page is not here', false)
            ->assertSee('Go home', false);

        $this->get('/this-page-should-not-exist-kf?lang=sw')
            ->assertNotFound()
            ->assertSee('Ukurasa haujapatikana', false)
            ->assertSee('Rudi nyuma', false)
            ->assertSee('Nenda nyumbani', false);

        $this->withSession(['locale' => 'sw'])
            ->get('/this-page-should-not-exist-kf-session')
            ->assertNotFound()
            ->assertSee('Ukurasa haujapatikana', false);

        $this->assertDatabaseHas('broken_pages', [
            'status' => 404,
            'path' => '/this-page-should-not-exist-kf',
        ]);
        $this->assertSame(1, BrokenPage::query()->where('path', '/this-page-should-not-exist-kf')->count());
        $this->assertSame(2, (int) BrokenPage::query()->where('path', '/this-page-should-not-exist-kf')->value('occurrence_count'));

        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin, 'admin')
            ->get(route('admin.broken-pages.index'))
            ->assertOk()
            ->assertSee('Broken pages', false)
            ->assertSee('/this-page-should-not-exist-kf', false);
    }

    public function test_borrower_dashboard_does_not_repeat_financial_snapshot(): void
    {
        $user = User::factory()->create(['role' => 'borrower']);
        app(PinService::class)->setPin($user, '1234');
        app(\App\Services\PinRecoveryChallengeService::class)->enroll($user, [
            'mother_first_name' => 'Asha',
            'primary_school' => 'Uhuru Primary',
            'nida_middle4' => '4582',
        ]);
        Customer::create([
            'user_id' => $user->id,
            'customer_number' => 'CU-SNAP-1',
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Gaspari',
            'last_name' => 'Test',
            'phone' => '255700009991',
        ]);

        $this->actingAs($user)
            ->withSession(['locale' => 'sw'])
            ->get(route('site.borrower.dashboard'))
            ->assertOk()
            ->assertDontSee(__('borrower.dashboard.snapshot.title'), false)
            ->assertSee(__('borrower.nav.home'), false)
            ->assertSee(__('borrower.nav.loans'), false)
            ->assertSee(__('borrower.nav.marketplace_short'), false)
            ->assertSee(__('borrower.nav.plus_short'), false)
            ->assertDontSee('aria-label="'.__('borrower.layout.menu').'"', false);
    }

    public function test_sla_reassignment_preserves_photos_and_does_not_create_a_valuation_fee(): void
    {
        Storage::fake('public');
        [$firstUser, $first] = $this->makeValuer('V-SLA-1', 'First Valuer');
        $this->completePartnerForJobs($first);
        [, $second] = $this->makeValuer('V-SLA-2', 'Second Valuer');
        $this->completePartnerForJobs($second);

        [$application, $assignment, $asset] = $this->assignValuation($first);
        $task = $assignment->vendorTask;

        $this->actingAs($firstUser)->post(route('site.partner.task.start', $task));
        $this->actingAs($firstUser)->post(route('site.partner.task.inspect.photo', $task), [
            'customer_asset_id' => $asset->id,
            'angle' => 'front',
            'file' => UploadedFile::fake()->image('front.jpg'),
        ])->assertRedirect();

        $this->assertSame(0, CustomerPayment::query()->where('payment_type', 'valuation_fee')->count());

        $ok = app(ServicePartnerReassignmentService::class)->reassignTask(
            $task->fresh(),
            'valuer',
            User::query()->where('role', 'super_admin')->first(),
            $second,
            'Reassigned after SLA expiry.',
        );
        $this->assertTrue($ok);
        $this->assertSame('cancelled', $task->fresh()->status);

        $new = \App\Models\PartnerTask::query()
            ->where('loan_application_id', $application->id)
            ->where('partner_id', $second->id)
            ->where('task_type', 'asset_valuation')
            ->first();
        $this->assertNotNull($new);
        $photos = app(ValuationInspectionService::class)->valuerPhotosByAsset($new->load('documents'), collect([$asset]));
        $this->assertTrue(filled($photos[$asset->id]['front'] ?? null), 'expected copied front photo');
        $this->assertSame(0, CustomerPayment::query()->where('payment_type', 'valuation_fee')->count());

        $this->actingAs($firstUser)
            ->post(route('site.partner.task.inspect.photo', $task), [
                'customer_asset_id' => $asset->id,
                'angle' => 'back',
                'file' => UploadedFile::fake()->image('back.jpg'),
            ])
            ->assertForbidden();
    }

    public function test_sla_stops_auto_reassign_after_max_reassignments(): void
    {
        [, $first] = $this->makeValuer('V-SLA-MAX-1', 'Capped Valuer');
        $this->completePartnerForJobs($first);
        [, $second] = $this->makeValuer('V-SLA-MAX-2', 'Waiting Valuer');
        $this->completePartnerForJobs($second);
        User::factory()->create(['role' => 'admin', 'is_active' => true]);

        [, $assignment] = $this->assignValuation($first);
        $task = $assignment->vendorTask;
        $task->mergeNotesMeta(['reassignment_count' => 3]);
        $task->update([
            'status' => 'in_progress',
            'due_at' => now()->subHours(5),
        ]);

        $result = app(ServicePartnerReassignmentService::class)->processSla();

        $this->assertSame(0, $result['reassigned']);
        $this->assertGreaterThanOrEqual(1, $result['skipped']);
        $this->assertSame('in_progress', $task->fresh()->status);
        $this->assertSame($first->id, $task->fresh()->partner_id);
        $this->assertSame(0, PartnerTask::query()->where('partner_id', $second->id)->count());
        $this->assertTrue(
            NotificationLog::query()->get()->contains(fn (NotificationLog $log) => str_contains((string) $log->message, 'Maximum automatic reassignments')),
            'operations should be notified to assign manually',
        );
    }

    public function test_sla_sends_a_due_soon_reminder_without_creating_a_fee(): void
    {
        [, $valuer] = $this->makeValuer('V-SLA-REM', 'Reminder Valuer');
        $this->completePartnerForJobs($valuer);
        [, $assignment] = $this->assignValuation($valuer);
        $task = $assignment->vendorTask;
        $task->update([
            'status' => 'in_progress',
            'due_at' => now()->addHours(3),
        ]);

        $this->assertSame(0, CustomerPayment::query()->where('payment_type', 'valuation_fee')->count());

        $result = app(ServicePartnerReassignmentService::class)->processSla();

        $this->assertGreaterThanOrEqual(1, $result['reminded']);
        $this->assertSame(0, $result['reassigned']);
        $this->assertSame($valuer->id, $task->fresh()->partner_id);
        $this->assertNotEmpty($task->fresh()->notesMeta()['sla_reminders'] ?? []);
        $this->assertTrue(
            NotificationLog::query()->get()->contains(
                fn (NotificationLog $log) => str_contains((string) $log->message, 'hours remaining')
                    || str_contains((string) $log->message, 'Urgent: complete this task')
            ),
            'valuer should receive an SLA reminder',
        );
        $this->assertSame(0, CustomerPayment::query()->where('payment_type', 'valuation_fee')->count());
    }

    /** @return array{0: User, 1: Vendor} */
    private function makeValuer(string $number, string $name): array
    {
        $user = User::factory()->create(['role' => 'vendor']);
        app(PinService::class)->setPin($user, '1234');
        $valuer = Vendor::create([
            'user_id' => $user->id,
            'vendor_number' => $number,
            'name' => $name,
            'category' => 'valuer',
            'status' => 'active',
            'partner_cost' => 30_000,
            'regions' => ['Dar es Salaam'],
        ]);

        return [$user, $valuer];
    }

    private function assignValuation(Vendor $valuer): array
    {
        $branch = Branch::query()->firstOrCreate(
            ['code' => 'BR-SLA'],
            ['name' => 'SLA Branch', 'region' => 'Dar es Salaam', 'is_active' => true],
        );
        $customer = Customer::create([
            'branch_id' => $branch->id,
            'customer_number' => 'CU-SLA-'.uniqid(),
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Asha',
            'last_name' => 'Owner',
            'phone' => '255900000099',
            'region' => 'Dar es Salaam',
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
            'application_number' => 'APP-SLA-'.uniqid(),
            'status' => 'submitted',
            'current_stage' => 'submitted',
            'requested_amount' => 500_000,
            'requested_tenure_months' => 12,
        ]);
        $asset = CustomerAsset::create([
            'customer_id' => $customer->id,
            'asset_type' => 'vehicle',
            'label' => 'Toyota Rav4',
            'registration_number' => 'T123SLA',
            'is_active' => true,
        ]);
        LoanApplicationAsset::create([
            'loan_application_id' => $application->id,
            'customer_asset_id' => $asset->id,
            'asset_type' => 'vehicle',
            'uw_status' => LoanApplicationAsset::UW_PENDING,
            'is_primary' => true,
            'valuation_status' => 'awaiting_valuation',
        ]);
        $admin = User::factory()->create(['role' => 'super_admin']);
        $assignment = app(ValuationPartnerService::class)->assign($application, $valuer, $admin, 'Field inspection');

        return [$application, $assignment->fresh('vendorTask'), $asset];
    }
}
