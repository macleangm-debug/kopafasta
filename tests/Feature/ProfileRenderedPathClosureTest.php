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

        $this->assertSame(['info@example.test'], $emails);
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

    public function test_family_and_kin_forms_reuse_shared_profile_select_and_phone_input(): void
    {
        $user = User::factory()->create(['role' => 'borrower']);
        Customer::create([
            'user_id' => $user->id,
            'customer_number' => 'C-FAM'.random_int(100000, 999999),
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Asha',
            'last_name' => 'Juma',
            'phone' => '255712345678',
            'marital_status' => 'married',
            'spouse_first_name' => 'Juma',
            'spouse_last_name' => 'Hassan',
            'number_of_children' => 2,
            'nok_first_name' => 'Fatuma',
            'nok_last_name' => 'Ali',
            'nok_relationship' => 'Parent',
            'nok_phone' => '255713000111',
            'nok_region' => 'Dar es Salaam',
            'nok_district' => 'Ilala',
            'nok_street' => 'Samora',
        ]);

        $html = $this->actingAs($user)
            ->get(route('site.borrower.profile', ['section' => 'personal']))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('name="marital_status"', $html);
        $this->assertStringContainsString('value="family"', $html);
        $this->assertStringContainsString('spouse_first_name', $html);
        $this->assertStringContainsString('Juma', $html);
        $this->assertStringContainsString('name="nok_phone"', $html);
        $this->assertStringContainsString('data-phone-input', $html);
        $this->assertStringContainsString('x-teleport="body"', $html);
        $this->assertStringNotContainsString('function kinPhone', $html);
    }

    public function test_family_autosave_preserves_spouse_when_status_leaves_married(): void
    {
        $user = User::factory()->create(['role' => 'borrower']);
        $customer = Customer::create([
            'user_id' => $user->id,
            'customer_number' => 'C-SP'.random_int(100000, 999999),
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Asha',
            'last_name' => 'Juma',
            'phone' => '255712345678',
            'marital_status' => 'married',
            'spouse_first_name' => 'Juma',
            'spouse_middle_name' => 'K',
            'spouse_last_name' => 'Hassan',
            'number_of_children' => 1,
        ]);

        $this->actingAs($user)
            ->call(
                'POST',
                route('site.borrower.profile.update', ['section' => 'personal']),
                [
                    '_method' => 'PUT',
                    'focus' => 'family',
                    'marital_status' => 'single',
                    'number_of_children' => 1,
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
            ->assertJson(['ok' => true]);

        $customer->refresh();
        $this->assertSame('single', $customer->marital_status);
        $this->assertSame('Juma', $customer->spouse_first_name);
        $this->assertSame('Hassan', $customer->spouse_last_name);
    }

    public function test_kin_autosave_http_path_persists_and_returns_json(): void
    {
        $user = User::factory()->create(['role' => 'borrower']);
        $customer = Customer::create([
            'user_id' => $user->id,
            'customer_number' => 'C-KIN'.random_int(100000, 999999),
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Neema',
            'last_name' => 'Mushi',
            'phone' => '255712345678',
            'nok_first_name' => 'Old',
            'nok_last_name' => 'Kin',
            'nok_relationship' => 'Sibling',
            'nok_phone' => '255711000000',
            'nok_region' => 'Dar es Salaam',
            'nok_district' => 'Ilala',
            'nok_street' => 'Old Street',
        ]);

        $this->actingAs($user)
            ->call(
                'POST',
                route('site.borrower.profile.update', ['section' => 'personal']),
                [
                    '_method' => 'PUT',
                    'focus' => 'kin',
                    'nok_first_name' => 'Fatuma',
                    'nok_middle_name' => 'A',
                    'nok_last_name' => 'Ali',
                    'nok_relationship' => 'Parent',
                    'nok_phone' => '255713000111',
                    'nok_region' => 'Dar es Salaam',
                    'nok_district' => 'Kinondoni',
                    'nok_street' => 'Mwenge',
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

        $customer->refresh();
        $this->assertSame('Fatuma', $customer->nok_first_name);
        $this->assertSame('Parent', $customer->nok_relationship);
        $this->assertSame('Kinondoni', $customer->nok_district);
    }
}
