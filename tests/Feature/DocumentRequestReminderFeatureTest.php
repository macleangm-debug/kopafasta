<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\LoanApplication;
use App\Models\LoanApplicationDocumentRequest;
use App\Models\LoanProduct;
use App\Models\NotificationLog;
use App\Models\User;
use App\Services\ApplicationDocumentRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentRequestReminderFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_sends_in_app_reminder_for_requests_due_tomorrow_once(): void
    {
        [$customer, $application] = $this->applicationPair();

        LoanApplicationDocumentRequest::create([
            'loan_application_id' => $application->id,
            'requested_by' => User::factory()->create(['role' => 'admin'])->id,
            'label' => 'Updated National ID',
            'type' => 'document',
            'status' => 'pending',
            'due_at' => now()->addDay()->setTime(12, 0),
        ]);
        LoanApplicationDocumentRequest::create([
            'loan_application_id' => $application->id,
            'requested_by' => User::factory()->create(['role' => 'admin'])->id,
            'label' => 'New Asset Photo',
            'type' => 'document',
            'status' => 'rejected',
            'due_at' => now()->addDay()->setTime(15, 0),
        ]);
        LoanApplicationDocumentRequest::create([
            'loan_application_id' => $application->id,
            'requested_by' => User::factory()->create(['role' => 'admin'])->id,
            'label' => 'Later Item',
            'type' => 'document',
            'status' => 'pending',
            'due_at' => now()->addDays(3)->setTime(12, 0),
        ]);

        $service = app(ApplicationDocumentRequestService::class);

        $this->assertSame(1, $service->sendDueTomorrowReminders());
        $this->assertSame(0, $service->sendDueTomorrowReminders());

        $log = NotificationLog::query()
            ->where('customer_id', $customer->id)
            ->where('template', 'application_document_request_reminder_1')
            ->first();

        $this->assertNotNull($log);
        $this->assertStringContainsString('Updated National ID', $log->message);
        $this->assertStringContainsString('New Asset Photo', $log->message);
        $this->assertStringNotContainsString('Later Item', $log->message);
        $this->assertSame($application->id, (int) data_get($log->meta, 'loan_application_id'));
    }

    public function test_cadence_reminders_and_day_seven_close_are_not_a_credit_rejection(): void
    {
        [$customer, $application] = $this->applicationPair();
        $request = LoanApplicationDocumentRequest::create([
            'loan_application_id' => $application->id,
            'requested_by' => User::factory()->create(['role' => 'admin'])->id,
            'label' => 'Updated residence proof',
            'type' => 'document',
            'status' => 'pending',
            'due_at' => now()->subMinute(),
            'created_at' => now()->subDays(3)->setTime(10, 0),
            'updated_at' => now()->subDays(3)->setTime(10, 0),
        ]);

        $service = app(ApplicationDocumentRequestService::class);
        $this->assertGreaterThanOrEqual(1, $service->sendScheduledReminders());

        $request->forceFill(['due_at' => now()->subMinute()])->save();
        $closed = $service->expireOverdueRequests();
        $this->assertTrue($closed->contains(fn ($row) => (int) $row->id === (int) $application->id));

        $application->refresh();
        $this->assertSame('expired', $application->status);
        $this->assertNotSame('rejected', $application->status);
        $this->assertSame('Closed — Required information not provided', $application->closedReasonLabel());
        $this->assertSame('expired', $request->fresh()->status);
        $this->assertSame('required_information_not_provided', data_get($application->screening_payload, 'document_request_closure.kind'));
        $this->assertNull($application->rejection_reason);

        $notify = NotificationLog::query()
            ->where('customer_id', $customer->id)
            ->where('template', 'application_document_request_closed')
            ->first();
        $this->assertNotNull($notify);
        $this->assertStringContainsString('Updated residence proof', $notify->message);
        $this->assertStringContainsString('closed', strtolower($notify->message));
    }

    /** @return array{0: Customer, 1: LoanApplication} */
    private function applicationPair(): array
    {
        $branch = Branch::create([
            'code' => 'DR'.random_int(10, 99),
            'name' => 'Doc Reminder Branch',
            'region' => 'Dar',
            'is_active' => true,
        ]);

        $product = LoanProduct::create([
            'code' => 'IL-DR-'.random_int(100, 999),
            'name' => 'Individual Loan',
            'is_active' => true,
            'interest_rate' => 0.18,
            'min_amount' => 100_000,
            'max_amount' => 5_000_000,
            'tenure_min_months' => 1,
            'tenure_max_months' => 12,
            'application_fee_amount' => 20_000,
        ]);

        $customer = Customer::create([
            'user_id' => User::factory()->create(['role' => 'borrower', 'pin_hash' => bcrypt('1234')])->id,
            'customer_number' => 'CU-DR-'.random_int(1000, 9999),
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Doc',
            'last_name' => 'Borrower',
            'phone' => '25571'.random_int(1000000, 9999999),
            'branch_id' => $branch->id,
        ]);

        $application = LoanApplication::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'branch_id' => $branch->id,
            'application_number' => 'APP-DR-'.random_int(1000, 9999),
            'requested_amount' => 500_000,
            'requested_tenure_months' => 3,
            'status' => 'pending_documents',
            'current_stage' => 'screening',
            'submitted_at' => now(),
            'application_fee_amount' => 20_000,
            'application_fee_status' => 'paid',
        ]);

        return [$customer, $application];
    }
}
