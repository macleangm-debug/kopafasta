<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use App\Services\PinService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfilePersonalRegistrationMicropassTest extends TestCase
{
    use RefreshDatabase;

    private function makeCustomer(array $attrs = []): Customer
    {
        $user = User::factory()->create(['role' => 'borrower']);
        app(PinService::class)->setPin($user, '1234');

        return Customer::create(array_merge([
            'user_id' => $user->id,
            'customer_number' => 'CU-PM'.random_int(1000, 9999),
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Asha',
            'last_name' => 'Juma',
            'phone' => '255700'.random_int(100000, 999999),
            'membership_status' => 'active',
            'membership_expires_at' => now()->addYear(),
        ], $attrs));
    }

    public function test_personal_page_includes_dob_field_and_nida_completeness_gate(): void
    {
        $customer = $this->makeCustomer();
        $this->actingAs($customer->user);

        $html = $this->get(route('site.borrower.profile', ['section' => 'personal']))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('profile-about', $html);
        $this->assertStringContainsString('name="date_of_birth"', $html);
        $this->assertStringContainsString('submitValue', file_get_contents(resource_path('views/components/site/nida-input.blade.php')));
        $blade = file_get_contents(resource_path('views/site/borrower/profile/personal.blade.php'));
        $this->assertStringContainsString('messageHtml', $blade);
        $this->assertStringContainsString("marital === 'married'", $html);
        $this->assertStringContainsString('guide-frame="id-card"', file_get_contents(resource_path('views/site/borrower/profile/_national_id_holder.blade.php')));
    }

    public function test_about_autosave_persists_dob_and_returns_view_fields(): void
    {
        $customer = $this->makeCustomer(['date_of_birth' => null]);
        $this->actingAs($customer->user);

        $response = $this->withHeaders([
            'X-KF-Autosave' => '1',
            'Accept' => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
        ])->put(route('site.borrower.profile.update', ['section' => 'personal']), [
            'focus' => 'about',
            'date_of_birth' => '1992-05-15',
        ]);

        $response->assertOk()->assertJsonPath('ok', true);
        $this->assertSame('15 May 1992', $response->json('view_fields.date_of_birth'));
        $this->assertSame('1992-05-15', optional($customer->fresh()->date_of_birth)->format('Y-m-d'));
    }

    public function test_family_married_autosave_exposes_spouse_and_updates_view(): void
    {
        $customer = $this->makeCustomer([
            'marital_status' => 'single',
            'number_of_children' => 0,
        ]);
        $this->actingAs($customer->user);

        $response = $this->withHeaders([
            'X-KF-Autosave' => '1',
            'Accept' => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
        ])->put(route('site.borrower.profile.update', ['section' => 'personal']), [
            'focus' => 'family',
            'marital_status' => 'married',
            'spouse_first_name' => 'Juma',
            'spouse_middle_name' => 'Ali',
            'spouse_last_name' => 'Hassan',
            'number_of_children' => 1,
        ]);

        $response->assertOk()->assertJsonPath('ok', true);
        $this->assertSame('married', $response->json('view_fields.marital_status_key'));
        $this->assertSame('Juma', $response->json('view_fields.spouse_first_name'));
        $this->assertSame('Hassan', $response->json('view_fields.spouse_last_name'));
        $fresh = $customer->fresh();
        $this->assertSame('married', $fresh->marital_status);
        $this->assertSame('Juma', $fresh->spouse_first_name);
    }

    public function test_incomplete_nida_rejected_by_server_validation(): void
    {
        $customer = $this->makeCustomer(['national_id' => null, 'identity_locked' => false]);
        $this->actingAs($customer->user);

        $response = $this->from(route('site.borrower.profile', ['section' => 'personal', 'focus' => 'identity']))
            ->put(route('site.borrower.profile.update', ['section' => 'personal']), [
                'focus' => 'identity',
                'national_id' => '12345678-90234-56789-0',
                'lock_national_id' => '1',
            ]);

        $response->assertSessionHasErrors('national_id');
        $this->assertNull($customer->fresh()->national_id);
    }
}
