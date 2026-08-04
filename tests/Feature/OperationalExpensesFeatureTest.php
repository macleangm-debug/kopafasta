<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\Setting;
use App\Models\User;
use App\Services\OperationalExpenseCategoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperationalExpensesFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_form_is_operational_only_without_partner_select(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.expenses.create'))
            ->assertOk()
            ->assertSee('Manual operational expenses', false)
            ->assertSee('Payout ledger', false)
            ->assertDontSee('name="vendor_id"', false)
            ->assertDontSee('name="partner_id"', false)
            ->assertSee('Rent', false)
            ->assertSee('Salaries &amp; wages (payroll)', false)
            ->assertSee('Other (specify)', false);
    }

    public function test_store_records_rent_and_ignores_partner_payload(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.expenses.store'), [
                'category'       => 'rent',
                'description'    => 'Office rent July',
                'amount'         => 1500000,
                'currency'       => 'TZS',
                'expense_date'   => now()->toDateString(),
                'payment_method' => 'bank_transfer',
                'status'         => 'recorded',
                'vendor_id'      => 999,
                'partner_id'     => 999,
            ])
            ->assertRedirect();

        $expense = Expense::query()->latest('id')->first();
        $this->assertNotNull($expense);
        $this->assertSame('rent', $expense->category);
        $this->assertNull($expense->partner_id);
        $this->assertNull($expense->vendor_id);
        $this->assertSame('Office rent July', $expense->description);
    }

    public function test_custom_expense_type_is_remembered_for_reuse(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.expenses.store'), [
                'category'        => 'other',
                'category_custom' => 'Staff welfare',
                'description'     => 'Team outing',
                'amount'          => 250000,
                'currency'        => 'TZS',
                'expense_date'    => now()->toDateString(),
                'status'          => 'pending',
            ])
            ->assertRedirect();

        $expense = Expense::query()->latest('id')->first();
        $this->assertSame('staff_welfare', $expense->category);

        $labels = Setting::get('finance.custom_expense_types', []);
        $this->assertContains('Staff welfare', $labels);

        $options = app(OperationalExpenseCategoryService::class)->options();
        $this->assertArrayHasKey('staff_welfare', $options);
        $this->assertSame('Staff welfare', $options['staff_welfare']);
    }
}
