<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\CustomerGuarantor;
use App\Models\CustomerPayment;
use App\Models\Guarantor;
use App\Models\GuarantorInvitation;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\User;
use App\Services\PinService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Synthetic Mohamed-shaped lending persona for Owner UAT.
 * Staging only — never production. Does not copy production PII.
 */
class LendingGuarantorChangeUatSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction() && ! app()->environment('staging')) {
            $this->command?->warn('LendingGuarantorChangeUatSeeder skipped: not a staging environment.');

            return;
        }

        $product = LoanProduct::query()
            ->where('is_active', true)
            ->where('requires_guarantor', true)
            ->where(function ($q) {
                $q->where('code', 'like', 'EM%')
                    ->orWhere('code', 'like', 'IL%');
            })
            ->orderBy('id')
            ->first();

        if (! $product) {
            $product = LoanProduct::query()
                ->where('is_active', true)
                ->where('requires_guarantor', true)
                ->orderBy('id')
                ->first();
        }

        if (! $product) {
            $this->command?->error('LendingGuarantorChangeUatSeeder: no guarantor-required product found.');

            return;
        }

        $borrowerUser = User::query()->updateOrCreate(
            ['email' => 'uat.guarantor.change@staging.kopafasta.com'],
            [
                'name' => 'UAT Guarantor Change',
                'phone' => '255700000041',
                'role' => 'borrower',
                'is_active' => true,
                'password' => Hash::make('StagingUat!2026'),
                'email_verified_at' => now(),
            ]
        );
        app(PinService::class)->setPin($borrowerUser, '1234');

        $borrower = Customer::query()->updateOrCreate(
            ['customer_number' => 'CU-UAT-GCHG-01'],
            [
                'user_id' => $borrowerUser->id,
                'type' => 'individual',
                'status' => 'active',
                'first_name' => 'UAT',
                'last_name' => 'Guarantor Change',
                'phone' => '255700000041',
                'country_code' => 'TZ',
                'membership_status' => 'active',
                'membership_expires_at' => now()->addYear(),
                'nida_verification_status' => 'verified',
                'face_verification_status' => 'verified',
                'date_of_birth' => now()->subYears(32)->toDateString(),
                'region' => 'Dar es Salaam',
                'district' => 'Kinondoni',
            ]
        );

        $oldGuarantorUser = User::query()->updateOrCreate(
            ['email' => 'uat.guarantor.old@staging.kopafasta.com'],
            [
                'name' => 'UAT Old Guarantor',
                'phone' => '255700000042',
                'role' => 'borrower',
                'is_active' => true,
                'password' => Hash::make('StagingUat!2026'),
                'email_verified_at' => now(),
            ]
        );
        app(PinService::class)->setPin($oldGuarantorUser, '1234');

        $oldGuarantorCustomer = Customer::query()->updateOrCreate(
            ['customer_number' => 'CU-UAT-GCHG-OLD'],
            [
                'user_id' => $oldGuarantorUser->id,
                'type' => 'individual',
                'status' => 'active',
                'first_name' => 'UAT',
                'last_name' => 'Old Guarantor',
                'phone' => '255700000042',
                'country_code' => 'TZ',
                'membership_status' => 'active',
                'membership_expires_at' => now()->addYear(),
            ]
        );

        $replacementUser = User::query()->updateOrCreate(
            ['email' => 'uat.guarantor.replacement@staging.kopafasta.com'],
            [
                'name' => 'UAT Replacement Guarantor',
                'phone' => '255700000043',
                'role' => 'borrower',
                'is_active' => true,
                'password' => Hash::make('StagingUat!2026'),
                'email_verified_at' => now(),
            ]
        );
        app(PinService::class)->setPin($replacementUser, '1234');

        Customer::query()->updateOrCreate(
            ['customer_number' => 'CU-UAT-GCHG-NEW'],
            [
                'user_id' => $replacementUser->id,
                'type' => 'individual',
                'status' => 'active',
                'first_name' => 'UAT',
                'last_name' => 'Replacement Guarantor',
                'phone' => '255700000043',
                'country_code' => 'TZ',
                'membership_status' => 'active',
                'membership_expires_at' => now()->addYear(),
            ]
        );

        $appNumber = 'APP-UAT-GCHG-01';
        $paymentRef = 'PAY-UAT-GCHG-01';

        // Mohamed-shaped: paid fee on application; payment source_id deliberately null.
        $payment = CustomerPayment::query()->updateOrCreate(
            ['reference' => $paymentRef],
            [
                'customer_id' => $borrower->id,
                'loan_product_id' => $product->id,
                'payment_type' => 'application_fee',
                'payment_method' => 'mobile_money',
                'amount' => 10_000,
                'currency' => 'TZS',
                'status' => 'verified',
                'paid_at' => now()->subDay(),
                'verified_at' => now()->subDay(),
                'source_type' => null,
                'source_id' => null,
                'provider_meta' => [
                    'apply_context' => [
                        'loan_product_id' => $product->id,
                        'draft_reference' => 'APP-UAT-STALE-DRAFT',
                    ],
                ],
            ]
        );

        $application = LoanApplication::query()->updateOrCreate(
            ['application_number' => $appNumber],
            [
                'customer_id' => $borrower->id,
                'loan_product_id' => $product->id,
                'requested_amount' => 600_000,
                'requested_tenure_months' => 6,
                'status' => 'awaiting_guarantor',
                'current_stage' => 'awaiting_guarantor',
                'application_fee_status' => 'paid',
                'application_fee_amount' => 10_000,
                'application_fee_reference' => $payment->reference,
                'application_fee_paid_at' => now()->subDay(),
                'submitted_at' => now()->subDay(),
                'guarantor_deadline_at' => now()->addDays(5),
                'purpose' => 'emergency',
            ]
        );

        // Drop prior guarantor rows so re-seed is idempotent.
        GuarantorInvitation::query()->where('loan_application_id', $application->id)->delete();
        CustomerGuarantor::query()->where('loan_application_id', $application->id)->delete();

        $guarantorRecord = Guarantor::query()->create([
            'first_name' => $oldGuarantorCustomer->first_name,
            'last_name' => $oldGuarantorCustomer->last_name,
            'phone' => $oldGuarantorCustomer->phone,
            'relationship' => 'friend',
        ]);

        $link = CustomerGuarantor::query()->create([
            'customer_id' => $borrower->id,
            'guarantor_id' => $guarantorRecord->id,
            'loan_application_id' => $application->id,
            'status' => 'pending',
        ]);

        GuarantorInvitation::query()->create([
            'customer_id' => $borrower->id,
            'loan_application_id' => $application->id,
            'loan_product_id' => $product->id,
            'customer_guarantor_id' => $link->id,
            'guarantor_customer_id' => $oldGuarantorCustomer->id,
            'type' => 'internal',
            'channel' => 'whatsapp',
            'token' => 'uat-gchg-'.Str::lower(Str::random(12)),
            'short_code' => 'UG'.random_int(100, 999),
            'contact' => $oldGuarantorCustomer->phone,
            'invitee_name' => trim($oldGuarantorCustomer->first_name.' '.$oldGuarantorCustomer->last_name),
            'status' => 'pending',
            'expires_at' => now()->addDays(14),
        ]);

        // Ensure no stale draft can poison fee entitlement checks.
        \App\Models\LoanApplicationDraft::query()
            ->where('customer_id', $borrower->id)
            ->where('loan_product_id', $product->id)
            ->delete();

        $this->command?->info('Lending guarantor-change UAT persona ready:');
        $this->command?->info("  Member phone: {$borrower->phone}  PIN: 1234  Customer #: {$borrower->customer_number}");
        $this->command?->info("  Application: {$application->application_number}  Fee: {$payment->reference} (paid, source_id null)");
        $this->command?->info("  Old guarantor phone: {$oldGuarantorCustomer->phone}");
        $this->command?->info('  Replacement guarantor phone: 255700000043');
    }
}
