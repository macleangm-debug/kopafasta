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

    public function test_my_card_uses_contextual_hero_without_duplicate_identity(): void
    {
        $customer = $this->borrower();
        $card = file_get_contents(resource_path('views/site/borrower/profile/_member_card.blade.php'));
        $memberCard = file_get_contents(resource_path('views/components/site/member-card.blade.php'));

        $this->assertStringContainsString("mode=\"contextual\"", $card);
        $this->assertStringContainsString("cta === 'profile'", $card);
        $this->assertStringContainsString('x-site.kopafasta-share-sheet', $memberCard);
        $this->assertStringContainsString('gradeShareLabel', $memberCard);
        $this->assertStringContainsString('share_plus_suffix', $memberCard);
        $this->assertStringNotContainsString('kfExportElementPngFile', $memberCard);
        $this->assertStringNotContainsString('data-kf-card-export', $memberCard);

        $html = $this->actingAs($customer->user)
            ->withSession(['locale' => 'en'])
            ->get(route('site.borrower.profile', ['section' => 'membership']))
            ->assertOk()
            ->assertSee('Kopafasta Card', false)
            ->assertDontSee(__('borrower.profile.hero_completion_cta'), false)
            ->assertSee('memberCardActions', false)
            ->getContent();

        $this->assertStringContainsString('BRONZE', strtoupper($html));
        $this->assertStringContainsString('share_message', $memberCard);
        $this->assertStringContainsString("I'm a Kopafasta member — :grade:plus_suffix.", __('borrower.membership.share_message', [
            'grade' => ':grade',
            'plus_suffix' => ':plus_suffix',
            'link' => ':link',
            'register' => ':register',
        ]));
        $hero = file_get_contents(resource_path('views/components/site/account-shell-hero.blade.php'));
        $this->assertStringNotContainsString('text-white uppercase', $hero);
    }

    public function test_profile_pages_keep_identity_hero_with_my_card_cta(): void
    {
        $customer = $this->borrower();

        $this->actingAs($customer->user)
            ->withSession(['locale' => 'en'])
            ->get(route('site.borrower.profile', ['section' => 'personal']))
            ->assertOk()
            ->assertSee('Gaspari Shiliba', false)
            ->assertSee('Kopafasta Card', false)
            ->assertDontSee('Back to profile overview', false);
    }

    public function test_payment_waiting_reuses_premium_panel_shell(): void
    {
        $flow = file_get_contents(resource_path('views/components/site/psp-payment-flow.blade.php'));

        $this->assertStringContainsString("state === 'waiting'", $flow);
        $this->assertStringContainsString('kf-premium-panel', $flow);
        $this->assertStringContainsString("state === 'failed'", $flow);
        $this->assertStringContainsString('kf-premium-panel-red', $flow);
        $this->assertStringContainsString('prompt_short', $flow);
        $this->assertStringContainsString('failed_short', $flow);
        $this->assertStringContainsString('max-w-sm text-center', $flow);
        $this->assertStringNotContainsString('step_ussd', $flow);
        $this->assertStringNotContainsString("x-text=\"@js(\$copy['successPaid'])\"", $flow);
        $this->assertStringNotContainsString('x-show="message" x-text="message"', $flow);
        $this->assertStringContainsString('rounded-2xl bg-white shadow-sm ring-1 ring-gray-200', $flow);
        $this->assertStringContainsString('kf-payment-surface-card', $flow);
    }

    public function test_plus_hero_contains_dashboard_cta_and_nav_is_not_standalone_back(): void
    {
        $hero = file_get_contents(resource_path('views/components/site/plus-hero.blade.php'));
        $nav = file_get_contents(resource_path('views/components/site/plus-nav.blade.php'));
        $offers = file_get_contents(resource_path('views/site/plus/offers.blade.php'));

        $this->assertStringContainsString('plus.nav.home', $hero);
        $this->assertStringContainsString('bg-white text-brand', $hero);
        $this->assertStringContainsString('Deprecated standalone Plus back', $nav);
        $this->assertStringNotContainsString('<x-site.plus-nav', $offers);
    }

    public function test_plus_print_footer_is_logo_and_confidential_only(): void
    {
        $reports = file_get_contents(resource_path('views/site/plus/reports.blade.php'));
        $sheet = file_get_contents(resource_path('views/site/plus/_report_sheet.blade.php'));
        $css = file_get_contents(resource_path('css/app.css'));

        $this->assertStringContainsString('kf-print-running-footer', $reports);
        $this->assertStringContainsString('kf-print-wordmark', $reports);
        $this->assertStringContainsString('logoDataUri', $reports);
        $this->assertStringContainsString('footer_confidential', $reports);
        $this->assertStringContainsString('kfPrintPlusReport', $reports);
        $this->assertStringContainsString('createObjectURL', $reports);
        $this->assertStringContainsString('data-kf-plus-print', $reports);
        $this->assertStringContainsString('plusReportActions', $reports);
        $this->assertStringContainsString('downloadShare', $reports);
        $this->assertStringContainsString('x-site.kopafasta-share-sheet', $reports);
        $this->assertStringContainsString('@click="openShare()"', $reports);
        $this->assertTrue(
            strpos($reports, 'data-kf-plus-print') !== false
            && strpos($reports, 'data-kf-plus-print') < strpos($reports, '@click="openShare()"'),
            'Print CTA must remain separate from Share'
        );
        $this->assertStringContainsString('x-site.borrower-layout', $reports);
        $this->assertStringNotContainsString('x-site.print-document', $reports);
        $this->assertStringNotContainsString('$website', $reports);
        $this->assertStringNotContainsString('$website', $sheet);
        $this->assertStringContainsString('kf-print-running-footer', $css);
        $this->assertStringContainsString('kf-print-wordmark', $css);
    }

    public function test_profile_hero_shows_canonical_completion(): void
    {
        $card = file_get_contents(resource_path('views/site/borrower/profile/_member_card.blade.php'));
        $hero = file_get_contents(resource_path('views/components/site/account-shell-hero.blade.php'));
        $partner = file_get_contents(resource_path('views/site/partner-account/_shell.blade.php'));

        $this->assertStringContainsString('ProfileCompletionService', $card);
        $this->assertStringContainsString('completion-percent', $card);
        $this->assertStringContainsString('completion-cta-url', $card);
        $this->assertStringContainsString('hero_completion_percent', $hero);
        $this->assertStringContainsString('hero_completion_done', $hero);
        $this->assertStringContainsString('completionCtaLabel', $hero);
        $this->assertStringContainsString('completionPercent', $partner);
        $this->assertStringContainsString('completion-percent', $partner);
        $this->assertStringContainsString('completionCtaUrl', $partner);

        $table = file_get_contents(resource_path('views/site/borrower/loans/_applications-table.blade.php'));
        $this->assertStringContainsString('applications_list.product', $table);
        $this->assertStringContainsString('product_name', $table);
        $this->assertStringNotContainsString('customer_name', $table);
        $this->assertStringNotContainsString('applications_list.applicant', $table);
    }

    public function test_profile_pages_render_completion_in_identity_hero(): void
    {
        $customer = $this->borrower();
        $percent = (int) (app(\App\Services\ProfileCompletionService::class)->calculate($customer)['percent'] ?? 0);

        $html = $this->actingAs($customer->user)
            ->withSession(['locale' => 'en'])
            ->get(route('site.borrower.profile', ['section' => 'personal']))
            ->assertOk()
            ->assertDontSee(__('borrower.profile.completion_hub_title'), false)
            ->getContent();

        if ($percent >= 100) {
            $this->assertStringContainsString(__('borrower.profile.hero_completion_done'), $html);
        } else {
            $this->assertStringContainsString(__('borrower.profile.hero_completion_percent', ['percent' => $percent]), $html);
            $this->assertStringContainsString('role="progressbar"', $html);
            $this->assertStringContainsString(__('borrower.membership.my_card'), $html);
            $this->assertStringNotContainsString(__('borrower.profile.hero_completion_cta'), $html);
        }
    }

    public function test_partner_shell_reuses_account_shell_hero(): void
    {
        $shell = file_get_contents(resource_path('views/site/partner-account/_shell.blade.php'));

        $this->assertStringContainsString('x-site.account-shell-hero', $shell);
        $this->assertStringContainsString("mode=\"contextual\"", $shell);
        $this->assertStringContainsString("mode=\"identity\"", $shell);
        $this->assertStringNotContainsString('hub_back', $shell);
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

        $this->actingAs($customer->user)
            ->delete(route('site.borrower.profile.documents.destroy', 'residence_letter'))
            ->assertRedirect(route('site.borrower.profile', [
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
}
