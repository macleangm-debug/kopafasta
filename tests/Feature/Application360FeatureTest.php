<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\User;
use App\Services\Application360Presenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Application360FeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_application_show_surfaces_application_360_with_canonical_next_action(): void
    {
        [$admin, $app] = $this->screeningFile();

        $html = $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-applications.show', $app))
            ->assertOk()
            ->assertSee('id="application-360"', false)
            ->assertSee('Needs Attention / Next Action', false)
            ->assertSee('Participants', false)
            ->assertSee('Journey / readiness', false)
            ->assertSee('Open Member 360', false)
            ->getContent();

        $this->assertStringContainsString($app->application_number, $html);
        $this->assertStringContainsString('Application 360', $html);
        $this->assertStringContainsString('What is missing?', $html);

        $panel = app(Application360Presenter::class)->forApplication($app, $admin);
        $this->assertNotSame('', (string) ($panel['next']['cta'] ?? ''));
        $this->assertNotSame('', (string) ($panel['next']['href'] ?? ''));
        $this->assertNotEmpty($panel['lifecycle']);
        $this->assertNotEmpty($panel['people']);
        $this->assertNotEmpty($panel['readiness']);
        $this->assertSame('screening', $panel['next']['source'] ?? null);
        $this->assertSame('Borrower', $panel['people'][0]['role'] ?? null);
        $this->assertArrayHasKey('completion_cards', $panel['people'][0]);
        $this->assertArrayHasKey('documents', $panel['people'][0]);
    }

    public function test_application_360_keeps_member_link_separate_from_credit_file(): void
    {
        [$admin, $app] = $this->screeningFile();
        $panel = app(Application360Presenter::class)->forApplication($app, $admin);

        $this->assertStringContainsString('/admin/customers/', (string) $panel['member_url']);
        $this->assertStringContainsString('guided-screening', (string) ($panel['next']['href'] ?? ''));
    }

    public function test_incomplete_draft_show_renders_application_360_hierarchy(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $borrower = User::factory()->create(['role' => 'borrower']);
        $customer = Customer::create([
            'user_id' => $borrower->id,
            'customer_number' => 'CU-DR-'.random_int(100, 999),
            'member_no' => 'M-DR-'.random_int(100, 999),
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Gaspari',
            'last_name' => 'Shiliba',
            'phone' => '25571'.random_int(1000000, 9999999),
            'national_id' => '19900101-12107-00001-21',
            'date_of_birth' => '1990-01-01',
            'nida_verification_status' => 'unverified',
            'face_verification_status' => 'revision_required',
            'membership_status' => 'active',
            'membership_expires_at' => now()->addYear(),
        ]);
        $product = LoanProduct::query()->where('code', 'IL')->first()
            ?? LoanProduct::create([
                'code' => 'IL',
                'name' => 'Individual Loan',
                'category' => 'individual',
                'interest_rate' => 0.18,
                'min_amount' => 500_000,
                'max_amount' => 10_000_000,
                'tenure_min_months' => 3,
                'tenure_max_months' => 24,
                'is_active' => true,
                'status' => 'active',
            ]);
        $draft = \App\Models\LoanApplicationDraft::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'phase' => 'application',
            'step' => 2,
            'draft_reference' => 'APP-IL-TEST'.random_int(100, 999),
            'payload' => ['form' => ['requested_amount' => 2_000_000]],
            'saved_at' => now(),
        ]);

        $html = $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-applications.incomplete.show', $draft))
            ->assertOk()
            ->assertSee('id="application-360"', false)
            ->assertSee('Needs Attention / Next Action', false)
            ->assertSee('Participants', false)
            ->assertSee('Borrower', false)
            ->assertSee('Continue application', false)
            ->assertDontSee('Identity verification incomplete — Face / NIDA still pending')
            ->assertDontSee('Journey / readiness')
            ->getContent();

        $this->assertStringContainsString('What is missing?', $html);
        $this->assertStringContainsString('aria-label="Profile sections"', $html);
        $this->assertStringContainsString('Personal', $html);
        $this->assertStringContainsString('Employment', $html);
        $this->assertStringContainsString('Residence', $html);
        $this->assertStringNotContainsString('Personal information', $html);
        preg_match('/id="application-360".*?<\/section>/s', $html, $panelMatch);
        $panelHtml = $panelMatch[0] ?? '';
        $this->assertNotSame('', $panelHtml);
        $this->assertStringNotContainsString('Face verification', $panelHtml);
        $this->assertStringNotContainsString('Journey / readiness', $panelHtml);
        $panel = app(Application360Presenter::class)->forDraft($draft);
        $this->assertTrue((bool) ($panel['is_draft'] ?? false));
        $this->assertNotEmpty($panel['people']);
        $this->assertSame('Borrower', $panel['people'][0]['role'] ?? null);
        $this->assertSame((int) $customer->id, (int) ($panel['people'][0]['customer_id'] ?? 0));
        $this->assertArrayHasKey('completion_percent', $panel['people'][0]);
        $this->assertArrayHasKey('completion_cards', $panel['people'][0]);
        $this->assertEmpty($panel['lifecycle'] ?? []);
        $this->assertSame('Continue application', $panel['next']['cta'] ?? null);
    }

    public function test_application_360_groups_documents_and_dedupes_historical_uploads(): void
    {
        [$admin, $app] = $this->screeningFile();
        $type = \App\Models\DocumentType::create([
            'code' => 'national_id',
            'name' => 'National ID',
            'category' => 'kyc',
            'is_active' => true,
        ]);
        \App\Models\CustomerDocument::create([
            'customer_id' => $app->customer_id,
            'document_type_id' => $type->id,
            'file_path' => 'customer/'.$app->customer_id.'/documents/id-old.jpg',
            'status' => 'verified',
            'created_at' => now()->subDays(10),
        ]);
        $latest = \App\Models\CustomerDocument::create([
            'customer_id' => $app->customer_id,
            'document_type_id' => $type->id,
            'file_path' => 'customer/'.$app->customer_id.'/documents/id-new.jpg',
            'status' => 'verified',
            'created_at' => now()->subDay(),
        ]);

        $panel = app(Application360Presenter::class)->forApplication($app, $admin);
        $docs = $panel['people'][0]['documents'] ?? [];
        $ids = collect($docs)->where('type_code', 'national_id')->values();
        $this->assertCount(1, $ids);
        $this->assertSame('identity', $ids[0]['category'] ?? null);
        $this->assertSame('doc-'.$latest->id, $ids[0]['key'] ?? null);
        $this->assertCount(1, $ids[0]['history'] ?? []);
        $this->assertNotEmpty($panel['people'][0]['completion_cards'] ?? []);

        $html = $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-applications.show', $app))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('document-holder', $html);
        $this->assertStringContainsString('Identity', $html);
    }

    public function test_application_360_does_not_copy_borrower_onto_guarantor(): void
    {
        [$admin, $app] = $this->screeningFile();
        $app->update([
            'status' => 'awaiting_guarantor',
            'current_stage' => 'awaiting_guarantor',
        ]);

        $gUser = User::factory()->create(['role' => 'borrower']);
        $guarantorCustomer = Customer::create([
            'user_id' => $gUser->id,
            'customer_number' => 'CU-G-'.random_int(100, 999),
            'member_no' => 'M-G-'.random_int(100, 999),
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Paul',
            'last_name' => 'Albert Mtawa',
            'phone' => '25568'.random_int(1000000, 9999999),
            'membership_status' => 'active',
            'membership_expires_at' => now()->addYear(),
        ]);
        $record = \App\Models\Guarantor::create([
            'first_name' => 'Paul',
            'last_name' => 'Albert Mtawa',
            'phone' => $guarantorCustomer->phone,
            'relationship' => 'member',
        ]);
        $link = \App\Models\CustomerGuarantor::create([
            'customer_id' => $app->customer_id,
            'guarantor_id' => $record->id,
            'loan_application_id' => $app->id,
            'status' => 'pending',
        ]);
        \App\Models\GuarantorInvitation::create([
            'customer_id' => $app->customer_id,
            'loan_application_id' => $app->id,
            'loan_product_id' => $app->loan_product_id,
            'customer_guarantor_id' => $link->id,
            'guarantor_customer_id' => $guarantorCustomer->id,
            'type' => 'internal',
            'channel' => 'in_app',
            'invitee_name' => 'Paul Albert Mtawa',
            'token' => 'g360-'.random_int(1000, 9999),
            'short_code' => 'G360'.random_int(100, 999),
            'contact' => $guarantorCustomer->phone,
            'status' => 'pending',
            'expires_at' => now()->addDays(7),
        ]);

        $panel = app(Application360Presenter::class)->forApplication($app->fresh(), $admin);
        $guarantors = collect($panel['people'])->where('role', 'Guarantor')->values();
        $this->assertCount(1, $guarantors);
        $this->assertSame('Paul Albert Mtawa', $guarantors[0]['name'] ?? null);
        $this->assertSame((int) $guarantorCustomer->id, (int) ($guarantors[0]['customer_id'] ?? 0));
        $this->assertNotSame((int) $app->customer_id, (int) ($guarantors[0]['customer_id'] ?? 0));
        $this->assertSame('Waiting for guarantor profile', $panel['next']['missing'] ?? null);
        $this->assertSame('Awaiting guarantor', $panel['next']['primary_status'] ?? $panel['status_label'] ?? null);
        $this->assertSame('Guarantor', $panel['next']['who'] ?? null);
        $this->assertNotSame($panel['next']['missing'] ?? null, $panel['next']['headline'] ?? null);

        $html = $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-applications.show', $app))
            ->assertOk()
            ->getContent();
        preg_match('/id="application-360".*?<\/section>/s', $html, $panelMatch);
        $panelHtml = $panelMatch[0] ?? '';
        $this->assertStringContainsString('Paul Albert Mtawa', $panelHtml);
        $this->assertStringContainsString('Waiting for guarantor profile', $panelHtml);
        $this->assertTrue(! empty($guarantors[0]['is_member']));
        $this->assertNotEmpty($guarantors[0]['href'] ?? null);
    }

    public function test_application_360_invite_only_guarantor_is_not_a_member(): void
    {
        [$admin, $app] = $this->screeningFile();
        $app->update([
            'status' => 'awaiting_guarantor',
            'current_stage' => 'awaiting_guarantor',
        ]);

        $record = \App\Models\Guarantor::create([
            'first_name' => 'Paulo',
            'last_name' => 'Albert Mtawa',
            'phone' => '+255667094545',
            'relationship' => 'relative',
        ]);
        $link = \App\Models\CustomerGuarantor::create([
            'customer_id' => $app->customer_id,
            'guarantor_id' => $record->id,
            'loan_application_id' => $app->id,
            'status' => 'rejected',
        ]);
        \App\Models\GuarantorInvitation::create([
            'customer_id' => $app->customer_id,
            'loan_application_id' => $app->id,
            'loan_product_id' => $app->loan_product_id,
            'customer_guarantor_id' => $link->id,
            'guarantor_customer_id' => null,
            'type' => 'external',
            'channel' => 'sms',
            'invitee_name' => 'Paulo Albert Mtawa',
            'token' => 'g360-invite-'.random_int(1000, 9999),
            'short_code' => 'GINV'.random_int(100, 999),
            'contact' => '+255667094545',
            'status' => 'rejected',
            'expires_at' => now()->addDays(7),
        ]);

        $panel = app(Application360Presenter::class)->forApplication($app->fresh(), $admin);
        $guarantors = collect($panel['people'])->where('role', 'Guarantor')->values();
        $this->assertCount(0, $guarantors);
        $previous = collect($panel['previous_guarantors'] ?? [])->values();
        $this->assertCount(1, $previous);
        $this->assertSame('Paulo Albert Mtawa', $previous[0]['name'] ?? null);
        $this->assertSame('Rejected', $previous[0]['status'] ?? null);
        $this->assertStringContainsString('Paulo Albert Mtawa — Rejected', (string) ($previous[0]['label'] ?? ''));
        $this->assertSame('Awaiting new guarantor', $panel['status_label'] ?? null);
        $this->assertSame('Notify borrower to replace guarantor', $panel['next']['cta'] ?? null);
        $this->assertSame('confirm_notify', $panel['next']['cta_kind'] ?? null);
        $this->assertSame('Borrower', $panel['next']['who'] ?? null);
        $this->assertSame('Previous invitation declined', $panel['next']['reason'] ?? $panel['next']['missing'] ?? null);
        $this->assertSame('current', data_get(collect($panel['lifecycle'] ?? [])->firstWhere('key', 'application'), 'state'));
        $this->assertNull(data_get(collect($panel['lifecycle'] ?? [])->firstWhere('key', 'screening'), 'href'));

        $html = $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-applications.show', $app))
            ->assertOk()
            ->getContent();
        preg_match('/id="application-360".*?<\/section>/s', $html, $panelMatch);
        $panelHtml = $panelMatch[0] ?? '';
        $this->assertStringContainsString('Paulo Albert Mtawa', $panelHtml);
        $this->assertStringContainsString('Previous guarantor', $panelHtml);
        $this->assertStringContainsString('Rejected', $panelHtml);
        $this->assertStringContainsString('Notify borrower to replace guarantor', $html);
        $this->assertStringNotContainsString('Invite replacement guarantor', $html);
        $this->assertStringNotContainsString('not a Kopafasta member', $panelHtml);
        $this->assertStringContainsString('Open Member 360', $panelHtml);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.loan-applications.notify-replace-guarantor', $app), ['confirmed' => '1'])
            ->assertRedirect();
        $this->assertDatabaseHas('notification_logs', [
            'customer_id' => $app->customer_id,
            'template' => 'guarantor_change_request',
        ]);
    }

    /** @return array{0: User, 1: LoanApplication} */
    private function screeningFile(): array
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $borrower = User::factory()->create(['role' => 'borrower']);
        $customer = Customer::create([
            'user_id' => $borrower->id,
            'customer_number' => 'CU-360-'.random_int(100, 999),
            'member_no' => 'M-360-'.random_int(100, 999),
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Steward',
            'last_name' => 'Amuli',
            'phone' => '25571'.random_int(1000000, 9999999),
            'national_id' => '19960815-12107-00005-21',
            'date_of_birth' => '1996-08-15',
            'nida_verification_status' => 'verified',
            'membership_status' => 'active',
            'membership_expires_at' => now()->addYear(),
        ]);
        $product = LoanProduct::query()->where('code', 'IL')->first()
            ?? LoanProduct::create([
                'code' => 'IL',
                'name' => 'Individual Loan',
                'category' => 'individual',
                'interest_rate' => 0.18,
                'min_amount' => 500_000,
                'max_amount' => 10_000_000,
                'tenure_min_months' => 3,
                'tenure_max_months' => 24,
                'is_active' => true,
                'status' => 'active',
                'application_fee_amount' => 10_000,
            ]);

        $app = LoanApplication::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'application_number' => 'APP-360-'.random_int(1000, 9999),
            'status' => 'under_review',
            'current_stage' => 'screening',
            'requested_amount' => 2_000_000,
            'requested_tenure_months' => 12,
            'purpose' => 'business',
            'submitted_at' => now()->subDay(),
            'application_fee_status' => 'paid',
        ]);

        return [$admin, $app];
    }
}
