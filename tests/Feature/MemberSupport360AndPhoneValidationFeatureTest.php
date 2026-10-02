<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\User;
use App\Rules\CanonicalPhone;
use App\Support\PhoneNumber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class MemberSupport360AndPhoneValidationFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_tanzania_phone_rejects_excess_digits_with_localized_message(): void
    {
        $v = Validator::make(
            ['phone' => '2558888888888888', 'country' => 'TZ'],
            ['phone' => ['required', new CanonicalPhone('TZ')]]
        );
        $this->assertTrue($v->fails());
        $this->assertStringContainsString('9', $v->errors()->first('phone'));
        $this->assertStringNotContainsString('20', $v->errors()->first('phone'));
    }

    public function test_tanzania_phone_accepts_canonical_nine_national_digits(): void
    {
        $this->assertSame('255712345678', PhoneNumber::canonicalDigits('712345678', 'TZ'));
        $v = Validator::make(
            ['phone' => '0712345678', 'country' => 'TZ'],
            ['phone' => ['required', new CanonicalPhone('TZ')]]
        );
        $this->assertFalse($v->fails());
    }

    public function test_borrower_register_rejects_overlong_phone_without_max_20_message(): void
    {
        $response = $this->from(route('site.register.borrower'))
            ->post(route('site.register.borrower.post'), [
                'country' => 'TZ',
                'first_name' => 'Asha',
                'last_name' => 'Juma',
                'gender' => 'female',
                'phone' => '2558888888888888',
                'local_phone' => '888888888888888',
            ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('phone');
        $message = session('errors')->first('phone');
        $this->assertStringNotContainsString('20', (string) $message);
        $this->assertMatchesRegularExpression('/9|tarakimu/i', (string) $message);
    }

    public function test_authenticated_member_loan_issue_uses_account_diagnostic(): void
    {
        $user = User::factory()->create(['role' => 'borrower', 'phone' => '255715111222']);
        $customer = Customer::create([
            'user_id' => $user->id,
            'customer_number' => 'CU-SUP-1',
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Maclean',
            'last_name' => 'Test',
            'phone' => '255715111222',
            'membership_status' => 'active',
            'membership_expires_at' => now()->addYear(),
        ]);

        $product = LoanProduct::query()->first() ?? LoanProduct::create([
            'name' => 'Biashara',
            'code' => 'SUP-BIZ-'.random_int(100, 999),
            'is_active' => true,
            'min_amount' => 100000,
            'max_amount' => 5000000,
            'interest_rate' => 3.5,
            'tenure_min_months' => 1,
            'tenure_max_months' => 24,
        ]);

        LoanApplication::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'application_number' => 'APP-SUP-001',
            'status' => 'under_review',
            'current_stage' => 'screening',
            'requested_amount' => 500000,
            'requested_tenure_months' => 12,
            'submitted_at' => now()->subDay(),
        ]);

        $start = $this->actingAs($user)
            ->postJson(route('site.borrower.support.automation'), [
                'action' => 'start',
                'audience' => 'member',
            ]);
        $start->assertOk();
        $conversationId = (int) $start->json('conversation_id');
        $this->assertGreaterThan(0, $conversationId);

        $cats = $start->json('choices') ?? [];
        $apply = collect($cats)->first(fn ($c) => ($c['key'] ?? '') === 'apply-loan') ?? ($cats[0] ?? null);
        $this->assertNotNull($apply);

        $cat = $this->actingAs($user)->postJson(route('site.borrower.support.automation'), [
            'action' => 'category',
            'key' => $apply['key'],
            'conversation_id' => $conversationId,
        ]);
        $cat->assertOk();

        $issues = $cat->json('choices') ?? [];
        $issue = collect($issues)->first(fn ($i) => in_array($i['key'] ?? '', ['application-stage', 'after-applying', 'screening-meaning'], true))
            ?? ($issues[0] ?? null);
        $this->assertNotNull($issue);

        $done = $this->actingAs($user)->postJson(route('site.borrower.support.automation'), [
            'action' => 'issue',
            'slug' => $issue['key'],
            'conversation_id' => $conversationId,
        ]);
        $done->assertOk();
        $messages = collect($done->json('messages') ?? [])->pluck('text')->implode("\n");
        $this->assertTrue(
            str_contains($messages, 'APP-SUP-001')
            || str_contains($messages, 'What I found')
            || str_contains($messages, 'Nimegundua')
            || str_contains($messages, 'under review')
            || str_contains($messages, 'linakaguliwa'),
            'Expected account diagnostic in bot reply'
        );
    }
}
