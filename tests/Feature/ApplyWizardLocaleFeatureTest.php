<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use App\Services\ApplicationRequirementsService;
use App\Services\PinService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApplyWizardLocaleFeatureTest extends TestCase
{
    use RefreshDatabase;

    private function makeCustomer(): Customer
    {
        $user = User::factory()->create(['role' => 'borrower']);

        return Customer::create([
            'user_id'         => $user->id,
            'customer_number' => 'C-LOC'.random_int(100, 999),
            'type'            => 'individual',
            'status'          => 'active',
            'first_name'      => 'Locale',
            'last_name'       => 'Borrower',
            'phone'           => '+255700'.random_int(100000, 999999),
        ]);
    }

    public function test_checklist_labels_follow_session_locale(): void
    {
        $customer = $this->makeCustomer();

        app()->setLocale('en');
        $en = app(ApplicationRequirementsService::class)->checklist($customer);
        $enRegistration = collect($en['items'])->firstWhere('key', 'registration_fee');

        app()->setLocale('sw');
        $sw = app(ApplicationRequirementsService::class)->checklist($customer);
        $swRegistration = collect($sw['items'])->firstWhere('key', 'registration_fee');

        $this->assertSame(__('borrower.apply.checklist.registration_fee', [], 'en'), $enRegistration['label']);
        $this->assertSame(__('borrower.apply.checklist.registration_fee', [], 'sw'), $swRegistration['label']);
        $this->assertNotSame($enRegistration['label'], $swRegistration['label']);
    }

    public function test_onboarding_banner_labels_follow_session_locale(): void
    {
        $customer = $this->makeCustomer();

        app()->setLocale('sw');
        $banner = app(ApplicationRequirementsService::class)->onboardingBanner($customer);

        $this->assertSame(__('borrower.onboarding.title_complete', [], 'sw'), $banner['title']);
        $this->assertContains(
            __('borrower.onboarding.registration_fee', [], 'sw'),
            collect($banner['items'])->pluck('label')->all(),
        );
    }

    public function test_locale_switch_redirects_back_to_current_page(): void
    {
        $customer = $this->makeCustomer();
        app(PinService::class)->setPin($customer->user, '1234');

        $this->actingAs($customer->user)
            ->from('/borrower/apply')
            ->post(route('site.locale.update'), [
                'locale'   => 'sw',
                'redirect' => url('/borrower/apply?resume=1'),
            ])
            ->assertRedirect('/borrower/apply?resume=1');

        $this->assertSame('sw', session('locale'));
        $this->assertSame('sw', $customer->user->fresh()->preferences['preferred_locale'] ?? null);
    }
}
