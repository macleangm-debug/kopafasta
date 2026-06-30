<?php

namespace Tests\Feature;

use App\Models\CompanySignatory;
use App\Models\Customer;
use App\Models\GroupMemberInvitation;
use App\Models\LoanProduct;
use App\Models\MarketplaceAsset;
use App\Models\User;
use App\Services\GroupMemberOnboardingService;
use App\Services\MarketplaceAssetService;
use App\Services\PinService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class Phase73DevelopmentTasksFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_signatory_update_preserves_signature_when_not_replaced(): void
    {
        Storage::fake('public');

        $admin = User::factory()->create(['role' => 'admin']);
        $signatory = CompanySignatory::create([
            'name'            => 'Jane Doe',
            'signatory_type'  => 'company',
            'signature_path'  => 'signatories/existing.png',
            'is_active'       => true,
        ]);
        Storage::disk('public')->put('signatories/existing.png', 'signature-bytes');

        $this->actingAs($admin, 'admin')
            ->put(route('admin.settings.signatories.update', $signatory), [
                'name'             => 'Jane Doe Updated',
                'signatory_type'   => 'company',
                'is_active'        => '1',
                'signature_data'   => '',
                'signature_touched'=> '0',
            ])
            ->assertRedirect(route('admin.settings.signatories.index'));

        $signatory->refresh();
        $this->assertSame('Jane Doe Updated', $signatory->name);
        $this->assertSame('signatories/existing.png', $signatory->signature_path);
    }

    public function test_legal_advocate_can_be_created_with_stamp_only(): void
    {
        Storage::fake('public');

        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.settings.signatories.store'), [
                'name'             => 'Legal Officer',
                'signatory_type'   => 'legal_advocate',
                'position'         => 'Advocate',
                'is_active'        => '1',
                'signature_touched'=> '0',
                'stamp_image'      => UploadedFile::fake()->image('stamp.png'),
            ])
            ->assertRedirect(route('admin.settings.signatories.index'));

        $signatory = CompanySignatory::query()->where('name', 'Legal Officer')->first();
        $this->assertNotNull($signatory);
        $this->assertNull($signatory->signature_path);
        $this->assertNotNull($signatory->stamp_path);
        Storage::disk('public')->assertExists($signatory->stamp_path);
    }

    public function test_group_invitation_matches_by_email(): void
    {
        $leader = Customer::create([
            'customer_number' => 'CU-P73-L',
            'type'            => 'individual',
            'status'          => 'active',
            'first_name'      => 'Leader',
            'last_name'       => 'One',
            'phone'           => '255712345801',
        ]);
        $member = Customer::create([
            'customer_number' => 'CU-P73-M',
            'type'            => 'individual',
            'status'          => 'active',
            'first_name'      => 'Invited',
            'last_name'       => 'Member',
            'email'           => 'invited@example.com',
            'phone'           => '255799999999',
            'membership_expires_at' => now()->addYear(),
        ]);
        $product = LoanProduct::create([
            'code'              => 'GL',
            'name'              => 'Group Loan',
            'category'          => 'group',
            'is_active'         => true,
            'interest_rate'     => 0.15,
            'min_amount'        => 200_000,
            'max_amount'        => 2_000_000,
            'tenure_min_months' => 3,
            'tenure_max_months' => 12,
        ]);

        GroupMemberInvitation::create([
            'leader_customer_id' => $leader->id,
            'loan_product_id'    => $product->id,
            'invitee_first_name' => 'Invited',
            'invitee_last_name'  => 'Member',
            'invitee_email'      => 'invited@example.com',
            'invitee_phone'      => '255712345802',
            'token'              => 'email-invite-token-123456789012345678901',
            'status'             => 'pending',
            'amount_per_member'  => 100000,
        ]);

        $invitation = app(GroupMemberOnboardingService::class)->pendingInvitationForCustomer($member);
        $this->assertNotNull($invitation);
        $this->assertSame('invited@example.com', $invitation->invitee_email);
    }

    public function test_invited_member_can_view_dashboard_without_forced_redirect(): void
    {
        $leader = Customer::create([
            'customer_number' => 'CU-P73-L2',
            'type'            => 'individual',
            'status'          => 'active',
            'first_name'      => 'Leader',
            'last_name'       => 'Two',
            'phone'           => '255712345803',
        ]);
        $user = User::factory()->create(['role' => 'borrower']);
        $member = Customer::create([
            'user_id'         => $user->id,
            'customer_number' => 'CU-P73-M2',
            'type'            => 'individual',
            'status'          => 'active',
            'first_name'      => 'Member',
            'last_name'       => 'Two',
            'phone'           => '255712345804',
            'membership_expires_at' => now()->addYear(),
        ]);
        app(PinService::class)->setPin($user, '1234');
        $product = LoanProduct::create([
            'code'              => 'GL',
            'name'              => 'Group Loan',
            'category'          => 'group',
            'is_active'         => true,
            'interest_rate'     => 0.15,
            'min_amount'        => 200_000,
            'max_amount'        => 2_000_000,
            'tenure_min_months' => 3,
            'tenure_max_months' => 12,
        ]);

        $invitation = GroupMemberInvitation::create([
            'leader_customer_id' => $leader->id,
            'loan_product_id'    => $product->id,
            'invitee_first_name' => 'Member',
            'invitee_last_name'  => 'Two',
            'invitee_phone'      => '255712345804',
            'token'              => 'dashboard-invite-token-123456789012345678',
            'status'             => 'accepted',
            'customer_id'        => $member->id,
            'draft_reference'    => 'DRF-GL-DASH',
            'amount_per_member'  => 100000,
        ]);

        $this->actingAs($user)
            ->withSession(['group_member_invite_token' => $invitation->token])
            ->get(route('site.borrower.dashboard'))
            ->assertOk()
            ->assertSee('DRF-GL-DASH');
    }

    public function test_marketplace_resolve_or_materialize_finds_locked_asset_by_slug(): void
    {
        MarketplaceAsset::create([
            'slug'                => 'mac-001',
            'category'            => 'machinery',
            'title'               => 'Mac asset',
            'supplier_name'       => 'Supplier',
            'asset_value'         => 1_000_000,
            'supplier_deposit'    => 200_000,
            'weekly_installment'  => 25_000,
            'max_tenure_months'   => 12,
            'is_active'           => true,
            'availability_status' => 'locked',
        ]);

        $resolved = app(MarketplaceAssetService::class)->resolveOrMaterialize('mac-001');
        $this->assertNotNull($resolved);
        $this->assertSame('mac-001', $resolved->slug);
    }
}
