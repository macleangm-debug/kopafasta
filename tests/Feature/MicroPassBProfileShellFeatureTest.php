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

    public function test_profile_nav_uses_my_card_label(): void
    {
        $customer = $this->borrower();

        $this->actingAs($customer->user)
            ->get(route('site.borrower.profile', ['section' => 'personal']))
            ->assertOk()
            ->assertSee(__('borrower.profile.panel_membership'), false)
            ->assertSee(__('borrower.profile.panel_profile'), false)
            ->assertDontSee('>Membership<', false);
    }

    public function test_my_card_page_reuses_member_card_and_grade_badge(): void
    {
        $customer = $this->borrower();

        $html = $this->actingAs($customer->user)
            ->get(route('site.borrower.profile', ['section' => 'membership']))
            ->assertOk()
            ->assertSee(__('borrower.membership.my_card'), false)
            ->assertSee('memberCardActions', false)
            ->getContent();

        $this->assertStringContainsString('BRONZE', strtoupper($html));
        $this->assertStringNotContainsString('HAI · PLUS', strtoupper($html));
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

    public function test_member_card_component_uses_grade_plus_badge_for_permanent(): void
    {
        $card = file_get_contents(resource_path('views/components/site/member-card.blade.php'));
        $this->assertStringContainsString("\$gradeKey.(\$plusActive ? ' · '.__('plus.card.plus') : '')", $card);
        $this->assertStringNotContainsString("\$plusActive ? \$label.' · '.__('plus.card.plus') : \$label", $card);
    }
}
