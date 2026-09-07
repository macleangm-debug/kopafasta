<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerDisbursementAccount;
use App\Models\CustomerDocument;
use App\Models\DocumentType;
use App\Models\User;
use App\Services\PinService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MicroPassBProfileShellFeatureTest extends TestCase
{
    use RefreshDatabase;

    private function borrower(): Customer
    {
        $user = User::factory()->create(['role' => 'borrower']);
        app(PinService::class)->setPin($user, '1234');

        return Customer::create([
            'user_id'                  => $user->id,
            'customer_number'          => 'CU-B'.random_int(1000, 9999),
            'type'                     => 'individual',
            'status'                   => 'active',
            'first_name'               => 'Gaspari',
            'last_name'                => 'Shiliba',
            'phone'                    => '25571522'.random_int(1000, 9999),
            'membership_status'        => 'active',
            'membership_expires_at'    => now()->addYear(),
            'grade'                    => 'bronze',
            'nida_verification_status' => 'verified',
            'face_verification_status' => 'pending',
            'date_of_birth'            => now()->subYears(30)->toDateString(),
            'national_id'              => '19900101123456789012',
            'region'                   => 'Dar es Salaam',
            'district'                 => 'Kinondoni',
            'street'                   => 'Samora',
            'activity_type'            => 'trader',
            'income_range'             => '500k_1m',
            'activity_details'         => ['trade_type' => 'food'],
            'nok_first_name'           => 'Next',
            'nok_last_name'            => 'Kin',
            'nok_name'                 => 'Next Kin',
            'nok_relationship'         => 'spouse',
            'nok_phone'                => '255712348099',
            'nok_region'               => 'Dar es Salaam',
            'nok_district'             => 'Kinondoni',
            'nok_street'               => 'Kin Street',
        ]);
    }

    public function test_profile_hero_matches_dashboard_hero_language(): void
    {
        $hero = file_get_contents(resource_path('views/site/borrower/profile/_member_card.blade.php'));
        $dashboard = file_get_contents(resource_path('views/components/site/borrower-dashboard-hero.blade.php'));
        $shell = file_get_contents(resource_path('views/site/borrower/profile/_profile_shell.blade.php'));

        $this->assertStringContainsString('kf-premium-panel', $hero);
        $this->assertStringContainsString('x-site.brand-mark', $hero);
        $this->assertStringContainsString('x-site.grade-badge', $hero);
        $this->assertStringContainsString("size=\"lg\"", $hero);
        $this->assertStringContainsString('bg-white text-brand', $hero);
        $this->assertStringContainsString('rounded-xl', $hero);
        $this->assertStringContainsString('flex items-start justify-between', $dashboard);
        $this->assertStringNotContainsString('hub.back', $shell);
        $this->assertStringNotContainsString("('active ?? '') !== 'hub'", $shell);
    }

    public function test_profile_section_pages_use_identity_hero_without_tab_bar_or_back_link(): void
    {
        $customer = $this->borrower();

        $html = $this->actingAs($customer->user)
            ->withSession(['locale' => 'en'])
            ->get(route('site.borrower.profile', ['section' => 'personal']))
            ->assertOk()
            ->assertSee('My Card', false)
            ->assertSee('Gaspari Shiliba', false)
            ->assertDontSee('Back to profile overview', false)
            ->getContent();

        $this->assertStringContainsString('BRONZE', strtoupper($html));
        $this->assertStringNotContainsString('ring-gray-200/80 bg-white/80 backdrop-blur p-0.5', $html);
        $this->assertStringNotContainsString(' · PLUS', strtoupper($html));
    }

    public function test_my_card_page_reuses_same_hero_with_profile_cta(): void
    {
        $customer = $this->borrower();

        $this->actingAs($customer->user)
            ->withSession(['locale' => 'en'])
            ->get(route('site.borrower.profile', ['section' => 'membership']))
            ->assertOk()
            ->assertSee('My Card', false)
            ->assertSee('Profile', false)
            ->assertSee('memberCardActions', false)
            ->assertDontSee('Back to profile overview', false);
    }

    public function test_document_remove_uses_confirm_form_bottom_sheet_capable_modal(): void
    {
        $field = file_get_contents(resource_path('views/components/site/profile-document-field.blade.php'));
        $confirm = file_get_contents(resource_path('views/components/site/confirm-modal.blade.php'));

        $this->assertStringContainsString('window.confirmForm', $field);
        $this->assertStringNotContainsString('if (! confirm(', $field);
        $this->assertStringContainsString('x-teleport="body"', $confirm);
        $this->assertStringContainsString('inset-x-0 bottom-0', $confirm);
        $this->assertStringContainsString('lg:left-1/2 lg:top-1/2', $confirm);
    }

    public function test_residence_document_remove_stays_on_list_without_status_flash(): void
    {
        Storage::fake('public');
        $customer = $this->borrower();
        $type = DocumentType::query()->firstOrCreate(
            ['code' => 'residence_letter'],
            [
                'name' => 'Residence letter',
                'category' => 'kyc',
                'applies_to' => 'borrower',
                'is_active' => true,
                'expires' => false,
            ]
        );

        $path = 'borrower/'.$customer->id.'/residence.pdf';
        Storage::disk('public')->put($path, 'pdf');
        CustomerDocument::create([
            'customer_id'      => $customer->id,
            'document_type_id' => $type->id,
            'file_path'        => $path,
            'status'           => 'pending',
        ]);

        $response = $this->actingAs($customer->user)
            ->delete(route('site.borrower.profile.documents.destroy', 'residence_letter'));

        $response->assertRedirect(route('site.borrower.profile', [
            'section' => 'residence',
            'focus' => 'verification',
            'open' => 1,
        ]).'#profile-residence-verification');

        $this->assertNull(session('status'));
    }

    public function test_payment_account_delete_still_flashes_status(): void
    {
        $customer = $this->borrower();
        CustomerDisbursementAccount::create([
            'customer_id'     => $customer->id,
            'type'            => 'mobile_money',
            'account_name'    => 'Gaspari Shiliba',
            'mobile_provider' => 'mpesa',
            'mobile_number'   => '255715222111',
            'is_default'      => true,
        ]);
        $remove = CustomerDisbursementAccount::create([
            'customer_id'     => $customer->id,
            'type'            => 'bank',
            'account_name'    => 'Gaspari Shiliba',
            'bank_name'       => 'CRDB',
            'account_number'  => '123456789',
            'is_default'      => false,
        ]);

        $this->actingAs($customer->user)
            ->delete(route('site.borrower.profile.payment-accounts.destroy', $remove))
            ->assertRedirect(route('site.borrower.profile', ['section' => 'payment', 'open' => 1]))
            ->assertSessionHas('status');
    }

    public function test_payment_show_desktop_centering_structure(): void
    {
        $show = file_get_contents(resource_path('views/site/borrower/payments/show.blade.php'));
        $body = file_get_contents(resource_path('views/site/borrower/payments/_show_body.blade.php'));
        $css = file_get_contents(resource_path('css/app.css'));

        $this->assertStringContainsString('kf-payment-page', $show);
        $this->assertStringContainsString('content-width="wide"', $show);
        $this->assertStringContainsString('max-width: min(28rem, 100%)', $css);
        $this->assertStringContainsString(':has(.kf-payment-page)', $css);
        // Overlay payment flow must not sit inside a constraining max-w-xl parent.
        $this->assertMatchesRegularExpression(
            '/@if \(\$isPayInWaiting.*?<x-site\.psp-payment-flow/s',
            $body
        );
    }

    public function test_plus_print_uses_fixed_running_footer_every_page(): void
    {
        $printDoc = file_get_contents(resource_path('views/components/site/print-document.blade.php'));
        $css = file_get_contents(resource_path('css/app.css'));

        $this->assertStringContainsString('kf-print-running-footer', $printDoc);
        $this->assertStringContainsString('position: fixed', $printDoc);
        $this->assertStringContainsString('kf-print-app-footer', $css);
        $this->assertStringContainsString('display: none !important', $css);
        $this->assertStringContainsString('padding-bottom: 14mm', $printDoc);
    }
}
