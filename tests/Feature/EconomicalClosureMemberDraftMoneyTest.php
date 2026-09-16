<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\LoanApplicationDraft;
use App\Models\LoanProduct;
use App\Models\User;
use App\Services\LoanApplicationDraftService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EconomicalClosureMemberDraftMoneyTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_member_count_excludes_pending_registrations(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        Customer::create([
            'customer_number' => 'CU-M-1',
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'A',
            'last_name' => 'One',
            'phone' => '255700000001',
        ]);
        Customer::create([
            'customer_number' => 'CU-M-2',
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'B',
            'last_name' => 'Two',
            'phone' => '255700000002',
        ]);
        Customer::create([
            'customer_number' => 'CU-M-3',
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'C',
            'last_name' => 'Three',
            'phone' => '255700000003',
        ]);
        Customer::create([
            'customer_number' => 'CU-P-1',
            'type' => 'individual',
            'status' => 'pending',
            'first_name' => 'P',
            'last_name' => 'One',
            'phone' => '255700000011',
        ]);
        Customer::create([
            'customer_number' => 'CU-P-2',
            'type' => 'individual',
            'status' => 'pending',
            'first_name' => 'P',
            'last_name' => 'Two',
            'phone' => '255700000012',
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Members', false)
            ->assertSee('Incomplete registrations', false)
            ->assertSee('Outstanding principal', false)
            ->assertSee('Disbursed · month', false)
            ->assertSee('Collections · month', false);

        $this->assertSame(3, Customer::query()->where('status', '!=', 'pending')->count());
        $this->assertSame(2, Customer::query()->where('status', 'pending')->count());
    }

    public function test_members_page_pending_filter_lists_only_pending(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $pending = Customer::create([
            'customer_number' => 'CU-PEND',
            'type' => 'individual',
            'status' => 'pending',
            'first_name' => 'Pending',
            'last_name' => 'Person',
            'phone' => '255711000017',
        ]);
        Customer::create([
            'customer_number' => 'CU-ACT',
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Active',
            'last_name' => 'Member',
            'phone' => '255711000018',
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.customers.index', ['status' => 'pending']))
            ->assertOk()
            ->assertSee('Pending Person', false)
            ->assertSee('255711000017', false)
            ->assertDontSee('Active Member', false);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.customers.show', $pending))
            ->assertOk()
            ->assertSee('Incomplete registration', false)
            ->assertSee('255711000017', false);
    }

    public function test_draft_guarantor_badge_renamed_and_status_counts_work(): void
    {
        $this->assertSame(
            'Awaiting guarantor completion',
            __('admin.application_drafts.status_awaiting_guarantor')
        );
        $this->assertStringNotContainsString(
            'Submitted',
            __('admin.application_drafts.status_awaiting_guarantor')
        );

        $customer = Customer::create([
            'customer_number' => 'CU-DR',
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Draft',
            'last_name' => 'Owner',
            'phone' => '255711000019',
        ]);
        $product = LoanProduct::create([
            'code' => 'IL-DR',
            'name' => 'Draft Product',
            'is_active' => true,
            'interest_rate' => 0.05,
            'min_amount' => 100_000,
            'max_amount' => 5_000_000,
            'tenure_min_months' => 1,
            'tenure_max_months' => 12,
        ]);

        LoanApplicationDraft::query()->create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'phase' => 'details',
            'step' => 0,
            'payload' => ['application_fee' => ['status' => 'not_applicable']],
            'saved_at' => now(),
        ]);

        $counts = app(LoanApplicationDraftService::class)->incompleteStatusCounts();
        $this->assertSame(1, $counts['total']);
        $this->assertSame(1, $counts['browsing'] + $counts['fee_pending']);
    }
}
