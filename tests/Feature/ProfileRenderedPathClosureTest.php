<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Setting;
use App\Models\User;
use App\Support\PhoneNumber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileRenderedPathClosureTest extends TestCase
{
    use RefreshDatabase;

    public function test_rendered_contact_form_is_wired_to_kf_autosave(): void
    {
        $user = User::factory()->create(['role' => 'borrower']);
        Customer::create([
            'user_id' => $user->id,
            'customer_number' => 'C-PATH'.random_int(100000, 999999),
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Neema',
            'last_name' => 'Mushi',
            'phone' => '255712345678',
            'email' => 'neema@example.com',
        ]);

        $html = $this->actingAs($user)
            ->get(route('site.borrower.profile', ['section' => 'personal']))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-kf-autosave', $html);
        $this->assertStringContainsString('name="email"', $html);
        $this->assertStringContainsString('focus" value="contact"', $html);
        $this->assertStringContainsString('neema@example.com', $html);
        $this->assertStringContainsString('data-kf-profile-page', $html);
    }

    public function test_contact_email_autosave_http_path_persists_and_returns_json(): void
    {
        $user = User::factory()->create(['role' => 'borrower']);
        $customer = Customer::create([
            'user_id' => $user->id,
            'customer_number' => 'C-MAIL'.random_int(100000, 999999),
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Neema',
            'last_name' => 'Mushi',
            'phone' => '255712345678',
            'email' => 'old@example.com',
        ]);

        $this->actingAs($user)
            ->call(
                'POST',
                route('site.borrower.profile.update', ['section' => 'personal']),
                [
                    '_method' => 'PUT',
                    'focus' => 'contact',
                    'phone' => '255712345678',
                    'email' => 'new-contact@example.com',
                ],
                [],
                [],
                [
                    'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
                    'HTTP_ACCEPT' => 'application/json',
                    'HTTP_X_KF_AUTOSAVE' => '1',
                ]
            )
            ->assertOk()
            ->assertJson(['ok' => true, 'saved' => true]);

        $this->assertSame('new-contact@example.com', $customer->fresh()->email);
    }

    public function test_phone_number_collapses_duplicate_plus_prefix(): void
    {
        $this->assertSame('+255 742024747', PhoneNumber::format('++255742024747'));
        $this->assertSame('255742024747', PhoneNumber::normalizeForCountry('++255742024747', 'TZ'));
    }

    public function test_support_phones_read_company_settings_and_normalize_display(): void
    {
        Setting::set('company.email', 'info@example.test');
        Setting::set('company.support_email', 'hello@example.test');
        Setting::set('company.phone', '255742024747');
        Setting::set('company.phone_2', '++255742024747');

        $emails = support_emails();
        $phones = support_phones();

        $this->assertSame(['info@example.test', 'hello@example.test'], $emails);
        $this->assertNotEmpty($phones);
        foreach ($phones as $phone) {
            $this->assertStringNotContainsString('++', $phone);
        }
    }

    public function test_employed_activity_without_contract_is_incomplete(): void
    {
        $user = User::factory()->create(['role' => 'borrower']);
        $customer = Customer::create([
            'user_id' => $user->id,
            'customer_number' => 'C-EMP'.random_int(100000, 999999),
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Job',
            'last_name' => 'Holder',
            'phone' => '255712111222',
            'activity_type' => 'employed',
            'income_range' => array_key_first(config('income_ranges', ['0-500000' => []])),
            'activity_details' => [
                'employer_name' => 'Acme',
                'job_title' => 'Clerk',
            ],
        ]);

        $this->assertFalse(
            app(\App\Services\ProfileCompletionService::class)->isActivityFieldsComplete($customer)
        );
    }
}
