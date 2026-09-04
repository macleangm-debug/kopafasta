<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\LoanApplicationDraft;
use App\Models\LoanProduct;
use App\Models\PlusGoal;
use App\Models\PlusSubscription;
use App\Models\User;
use App\Services\AccountWelcomeService;
use App\Services\BorrowerApplicationsDashboardService;
use App\Services\KopafastaLaunchService;
use App\Services\LoanApplicationDraftService;
use App\Services\Plus\PlusNudgeService;
use Database\Seeders\NotificationTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OwnerWalkthroughCorrectionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_swahili_financing_card_and_points_card_use_approved_copy(): void
    {
        $borrower = User::factory()->needsWelcome()->create(['role' => 'borrower']);
        app()->setLocale('sw');
        $payload = app(AccountWelcomeService::class)->forUser($borrower);

        $this->assertNotNull($payload);
        $titles = collect($payload['cards'])->pluck('title')->all();
        $this->assertContains('Zaidi ya aina 10 za mikopo', $titles);
        $this->assertContains('Pata pointi. Fungua zaidi.', $titles);
        $this->assertFalse(collect($payload['cards'])->contains(fn (array $card) => str_contains($card['body'], '25%')));
        $this->assertTrue(collect($payload['cards'])->contains(fn (array $card) => ($card['illustration'] ?? '') === 'rewards'));
        $this->assertTrue(collect($payload['cards'])->every(fn (array $card) => filled($card['illustration'] ?? null)));
        app()->setLocale('en');
    }

    public function test_welcome_finish_arms_launcher_and_dashboard_renders_it(): void
    {
        $borrower = User::factory()->needsWelcome()->create(['role' => 'borrower']);
        Customer::create([
            'user_id' => $borrower->id,
            'customer_number' => 'CU-LAUNCH-'.random_int(1000, 9999),
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Asha',
            'last_name' => 'Mushi',
            'phone' => '2557199'.random_int(10000, 99999),
        ]);

        $this->actingAs($borrower)
            ->post(route('site.account-welcome.complete'), ['audience' => 'borrower'])
            ->assertRedirect(route('site.borrower.dashboard'));

        $this->assertTrue(session()->get(KopafastaLaunchService::SESSION_KEY));

        $this->actingAs($borrower->fresh())
            ->withSession([KopafastaLaunchService::SESSION_KEY => true])
            ->get(route('site.borrower.dashboard'))
            ->assertOk()
            ->assertSee('kf-launcher', false)
            ->assertSee('data-kf-launcher', false);
    }

    public function test_discarded_draft_does_not_resurrection_on_save_or_refresh(): void
    {
        $user = User::factory()->create(['role' => 'borrower']);
        $customer = Customer::create([
            'user_id' => $user->id,
            'customer_number' => 'CU-DEL-'.random_int(1000, 9999),
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Delete',
            'last_name' => 'Loan',
            'phone' => '2557177'.random_int(10000, 99999),
            'membership_status' => 'active',
            'membership_expires_at' => now()->addYear(),
        ]);
        $product = LoanProduct::create([
            'code' => 'IL',
            'name' => 'Individual Loan',
            'category' => 'individual',
            'is_active' => true,
            'interest_rate' => 0.19,
            'min_amount' => 500_000,
            'max_amount' => 5_000_000,
            'tenure_min_months' => 1,
            'tenure_max_months' => 12,
            'application_fee_amount' => 10_000,
        ]);
        $draft = LoanApplicationDraft::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'phase' => 'application',
            'step' => 0,
            'draft_reference' => 'APP-IL-GONE',
            'saved_at' => now(),
            'payload' => [
                'application_started' => true,
                'form' => ['loan_product_id' => $product->id, 'requested_amount' => 500000],
            ],
        ]);

        $this->actingAs($user)
            ->post(route('site.borrower.draft.discard', $draft))
            ->assertRedirect(route('site.borrower.loans', ['tab' => 'applications']));

        $rows = app(BorrowerApplicationsDashboardService::class)->applicationsForCustomer($customer->fresh());
        $this->assertFalse(collect($rows)->contains(fn (array $row) => ($row['application_number'] ?? '') === 'APP-IL-GONE'));

        $this->actingAs($user)
            ->putJson(route('site.borrower.apply.draft.save'), [
                'phase' => 'application',
                'loan_product_id' => $product->id,
                'step' => 0,
                'form' => ['loan_product_id' => $product->id],
            ])
            ->assertStatus(410);

        $this->assertNull(app(LoanApplicationDraftService::class)->find($customer->fresh(), $product->id));

        $this->actingAs($user)
            ->get(route('site.borrower.apply', ['product' => $product->id]))
            ->assertRedirect(route('site.borrower.loans', ['tab' => 'applications']));

        $this->actingAs($user)
            ->get(route('site.borrower.loan-profile.draft', ['draft' => 999999]))
            ->assertRedirect(route('site.borrower.loans', ['tab' => 'applications']));
    }

    public function test_plus_goal_nudges_are_deduped_by_milestone(): void
    {
        $this->seed(NotificationTemplateSeeder::class);
        $user = User::factory()->create(['role' => 'borrower']);
        $customer = Customer::create([
            'user_id' => $user->id,
            'customer_number' => 'CU-NUDGE-'.random_int(1000, 9999),
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Nudge',
            'last_name' => 'Member',
            'phone' => '2557166'.random_int(10000, 99999),
        ]);
        PlusSubscription::create([
            'customer_id' => $customer->id,
            'plan' => 'monthly',
            'status' => 'active',
            'starts_at' => now()->subDay(),
            'expires_at' => now()->addMonth(),
        ]);
        $goal = PlusGoal::create([
            'customer_id' => $customer->id,
            'kind' => 'savings',
            'title' => 'School fees',
            'target_amount' => 1000,
            'saved_amount' => 0,
            'status' => 'active',
        ]);

        $nudges = app(PlusNudgeService::class);
        $nudges->onGoalCreated($customer->fresh('user'), $goal);
        $nudges->onGoalCreated($customer->fresh('user'), $goal);

        $notices = data_get($user->fresh()->preferences, 'lifecycle_notices', []);
        $this->assertArrayHasKey('goal_created:goal:'.$goal->id.':created', $notices);

        $goal->update(['saved_amount' => 500]);
        $nudges->onGoalProgress($customer->fresh('user'), $goal->fresh());
        $nudges->onGoalProgress($customer->fresh('user'), $goal->fresh());
        $notices = data_get($user->fresh()->preferences, 'lifecycle_notices', []);
        $this->assertArrayHasKey('goal_progress:goal:'.$goal->id.':p50', $notices);
    }
}
