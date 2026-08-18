<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\Setting;
use App\Models\User;
use App\Services\WriteOffRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoanWriteOffAuthorizationFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::set('finance.write_off_approval_required', true);
    }

    public function test_loan_file_uses_premium_letterhead(): void
    {
        $admin = $this->staff('admin');
        $loan = $this->loan($admin, 'pending');

        $html = $this->actingAs($admin, 'admin')
            ->followingRedirects()
            ->get(route('admin.loans.show', $loan))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Credit management', $html);
        $this->assertStringContainsString($loan->loan_number, $html);
        $this->assertStringNotContainsString('Recommend write-off', $html);
    }

    public function test_officer_cannot_see_or_open_write_off_on_arrears_loan(): void
    {
        $officer = $this->staff('officer');
        $loan = $this->loan($officer, 'arrears');

        $html = $this->actingAs($officer, 'admin')
            ->followingRedirects()
            ->get(route('admin.loans.show', $loan))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Credit management', $html);
        $this->assertStringContainsString('Facility', $html);
        $this->assertStringNotContainsString('Recommend write-off', $html);
        $this->assertStringNotContainsString('Write-off queue', $html);

        $this->actingAs($officer, 'admin')
            ->get(route('admin.loans.write-off-form', $loan))
            ->assertForbidden();

        $this->actingAs($officer, 'admin')
            ->post(route('admin.loans.write-off-requests.store', $loan), [
                'reason' => 'Uncollectable',
                'amount' => 50_000,
            ])
            ->assertSessionHasErrors('role');
    }

    public function test_collector_can_recommend_write_off_only_when_in_arrears(): void
    {
        $collector = $this->staff('collector');
        $arrears = $this->loan($collector, 'arrears');
        $active = $this->loan($collector, 'active');

        $html = $this->actingAs($collector, 'admin')
            ->followingRedirects()
            ->get(route('admin.loans.show', $arrears))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Recommend write-off', $html);
        $this->assertStringContainsString('Write-off queue', $html);
        $this->assertStringContainsString('Credit management', $html);

        $this->actingAs($collector, 'admin')
            ->get(route('admin.loans.write-off-form', $arrears))
            ->assertOk()
            ->assertSee('Write-off', false)
            ->assertSee('data-money-input', false);

        $activeHtml = $this->actingAs($collector, 'admin')
            ->followingRedirects()
            ->get(route('admin.loans.show', $active))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('Recommend write-off', $activeHtml);

        $this->actingAs($collector, 'admin')
            ->get(route('admin.loans.write-off-form', $active))
            ->assertForbidden();
    }

    public function test_credit_analyst_cannot_recommend_write_off(): void
    {
        $analyst = $this->staff('credit_analyst');
        $loan = $this->loan($analyst, 'arrears');
        $service = app(WriteOffRequestService::class);

        $this->assertFalse($service->canRecommend($analyst));
        $this->assertFalse($service->canAccessWriteOffForm($analyst, $loan));

        $this->actingAs($analyst, 'admin')
            ->followingRedirects()
            ->get(route('admin.loans.show', $loan))
            ->assertOk()
            ->assertDontSee('Recommend write-off');
    }

    public function test_admin_can_recommend_on_defaulted_loan(): void
    {
        $admin = $this->staff('admin');
        $loan = $this->loan($admin, 'defaulted');

        $this->actingAs($admin, 'admin')
            ->followingRedirects()
            ->get(route('admin.loans.show', $loan))
            ->assertOk()
            ->assertSee('Recommend write-off')
            ->assertSee('Credit management');
    }

    private function staff(string $role): User
    {
        return User::factory()->create([
            'role'      => $role,
            'is_active' => true,
        ]);
    }

    private function loan(User $actor, string $status): Loan
    {
        $product = LoanProduct::create([
            'code'              => 'IL-WO-'.random_int(100, 999),
            'name'              => 'Installment',
            'is_active'         => true,
            'interest_rate'     => 0.18,
            'min_amount'        => 50_000,
            'max_amount'        => 5_000_000,
            'tenure_min_months' => 1,
            'tenure_max_months' => 12,
        ]);

        $customer = Customer::create([
            'user_id'         => User::factory()->create(['role' => 'borrower'])->id,
            'customer_number' => 'CU-WO-'.random_int(1000, 9999),
            'type'            => 'individual',
            'status'          => 'active',
            'first_name'      => 'Gaspari',
            'last_name'       => 'Shiliba',
            'phone'           => '25571'.random_int(1000000, 9999999),
            'branch_id'       => $actor->branch_id,
        ]);

        $application = LoanApplication::create([
            'customer_id'             => $customer->id,
            'loan_product_id'         => $product->id,
            'branch_id'               => $actor->branch_id,
            'application_number'      => 'APP-WO-'.random_int(1000, 9999),
            'requested_amount'        => 100_000,
            'requested_tenure_months' => 6,
            'status'                  => 'disbursed',
            'current_stage'           => 'disbursement',
        ]);

        return Loan::create([
            'customer_id'         => $customer->id,
            'loan_product_id'     => $product->id,
            'loan_application_id' => $application->id,
            'loan_number'         => 'LN-WO-'.strtoupper(substr(md5((string) random_int(1, 999999)), 0, 4)),
            'principal_amount'    => 100_000,
            'approved_amount'     => 100_000,
            'outstanding_balance' => 79_934,
            'interest_rate'       => 0.18,
            'tenure_months'       => 6,
            'status'              => $status,
            'disbursement_date'   => now()->subMonths(2),
        ]);
    }
}
