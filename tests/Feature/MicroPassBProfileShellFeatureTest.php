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

    public function test_profile_section_pages_use_identity_summary_without_tab_bar(): void
    {
        $customer = $this->borrower();

        $html = $this->actingAs($customer->user)
            ->withSession(['locale' => 'en'])
            ->get(route('site.borrower.profile', ['section' => 'personal']))
            ->assertOk()
            ->assertSee('My Card', false)
            ->assertSee('Gaspari Shiliba', false)
            ->getContent();

        $this->assertStringContainsString('BRONZE', strtoupper($html));
        $this->assertStringContainsString('tracking-[0.16em]', $html);
        // Profile | My Card segment control must be gone (section accordion tabs may remain).
        $this->assertStringNotContainsString('ring-gray-200/80 bg-white/80 backdrop-blur p-0.5', $html);
        $shell = file_get_contents(resource_path('views/site/borrower/profile/_profile_shell.blade.php'));
        $this->assertStringNotContainsString('_account_segments', $shell);
    }

    public function test_profile_identity_summary_reuses_dashboard_grade_badge_component(): void
    {
        $summary = file_get_contents(resource_path('views/site/borrower/profile/_member_card.blade.php'));
        $card = file_get_contents(resource_path('views/components/site/member-card.blade.php'));

        $this->assertStringContainsString('x-site.grade-badge', $summary);
        $this->assertStringContainsString('x-site.grade-badge', $card);
        $this->assertStringNotContainsString('window.confirm(', file_get_contents(resource_path('views/components/site/profile-document-field.blade.php')));
    }

    public function test_my_card_page_has_profile_cta_and_full_card_without_tabs(): void
    {
        $customer = $this->borrower();

        $html = $this->actingAs($customer->user)
            ->get(route('site.borrower.profile', ['section' => 'membership']))
            ->assertOk()
            ->assertSee(__('borrower.membership.my_card'), false)
            ->assertSee(__('borrower.profile.panel_profile'), false)
            ->assertSee('memberCardActions', false)
            ->getContent();

        $this->assertStringContainsString('BRONZE', strtoupper($html));
        $this->assertStringNotContainsString('confirm(@js', $html);
    }

    public function test_document_remove_uses_confirm_form_not_native_dialog(): void
    {
        $field = file_get_contents(resource_path('views/components/site/profile-document-field.blade.php'));

        $this->assertStringContainsString('window.confirmForm', $field);
        $this->assertStringNotContainsString('confirm(@js', $field);
        $this->assertStringNotContainsString('if (! confirm(', $field);
        $this->assertStringContainsString('remove_document_confirm_title', $field);
    }

    public function test_payment_account_delete_stays_on_expanded_list(): void
    {
        $customer = $this->borrower();
        $keep = CustomerDisbursementAccount::create([
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
            ->assertRedirect(route('site.borrower.profile', ['section' => 'payment', 'open' => 1]));

        $this->assertDatabaseMissing('customer_disbursement_accounts', ['id' => $remove->id]);
        $this->assertDatabaseHas('customer_disbursement_accounts', ['id' => $keep->id]);
    }

    public function test_residence_document_remove_stays_on_verification_list(): void
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
    }

    public function test_payment_section_page_omits_my_account_heading(): void
    {
        $customer = $this->borrower();

        $html = $this->actingAs($customer->user)
            ->get(route('site.borrower.profile', ['section' => 'payment']))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('>'.__('borrower.profile.account_title').'<', $html);
        $this->assertStringContainsString(__('borrower.payment_details.section_title'), $html);
    }

    public function test_payment_show_layout_centers_in_content_column(): void
    {
        $layout = file_get_contents(resource_path('views/components/site/borrower-layout.blade.php'));
        $show = file_get_contents(resource_path('views/site/borrower/payments/show.blade.php'));

        $this->assertStringContainsString("'wide'   => 'max-w-7xl mx-auto'", $layout);
        $this->assertStringContainsString('content-width="narrow"', $show);
    }

    public function test_plus_print_report_uses_single_brand_header_and_app_footer(): void
    {
        $sheet = file_get_contents(resource_path('views/site/plus/_report_sheet.blade.php'));
        $printDoc = file_get_contents(resource_path('views/components/site/print-document.blade.php'));

        $this->assertStringContainsString('kf-print-header', $sheet);
        $this->assertStringContainsString('kf-print-app-footer', $sheet);
        $this->assertStringContainsString('print_plus_label', $sheet);
        $this->assertStringContainsString("kf-print-running-footer { display: none !important; }", $printDoc);
    }
}
