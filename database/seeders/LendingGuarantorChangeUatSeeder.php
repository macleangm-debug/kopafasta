<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\CustomerGuarantor;
use App\Models\CustomerPayment;
use App\Models\Guarantor;
use App\Models\GuarantorInvitation;
use App\Models\LoanApplication;
use App\Models\LoanApplicationDraft;
use App\Models\LoanProduct;
use App\Models\User;
use App\Services\PinService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Mohamed-shaped application scenario under the Owner's MacLean staging borrower.
 * Staging only — never production. Does not copy Mohamed production PII/identity.
 */
class LendingGuarantorChangeUatSeeder extends Seeder
{
    public const MACLEAN_PHONE = '255715222132';

    public const APP_NUMBER = 'APP-UAT-MOH-MACLEAN-01';

    public const FEE_REF = 'PAY-UAT-MOH-MAC-01';

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
            ->first()
            ?: LoanProduct::query()
                ->where('is_active', true)
                ->where('requires_guarantor', true)
                ->orderBy('id')
                ->first();

        if (! $product) {
            $this->command?->error('LendingGuarantorChangeUatSeeder: no guarantor-required product found.');

            return;
        }

        $phoneVariants = [
            self::MACLEAN_PHONE,
            '0715222132',
            '715222132',
            '+255715222132',
            '255 715 222 132',
        ];

        $borrowerUser = User::query()
            ->whereIn('phone', $phoneVariants)
            ->orderBy('id')
            ->first();

        // Prefer the Owner MacLean member account (not partner/insurance collisions on digit-only phone).
        if (! $borrowerUser) {
            $borrowerUser = User::query()
                ->where(function ($q) {
                    $q->where('phone', 'like', '%715222132')
                        ->orWhere('email', 'like', 'macleangm@%');
                })
                ->where('name', 'like', '%Maclean%')
                ->orderBy('id')
                ->first();
        }

        $borrower = null;
        if ($borrowerUser) {
            $borrower = Customer::query()->where('user_id', $borrowerUser->id)->orderBy('id')->first();
        }
        if (! $borrower) {
            $borrower = Customer::query()
                ->whereIn('phone', $phoneVariants)
                ->orderBy('id')
                ->first();
        }

        if (! $borrower) {
            $borrower = Customer::query()
                ->where('phone', 'like', '%715222132')
                ->where(function ($inner) {
                    $inner->where('first_name', 'like', 'MacLean%')
                        ->orWhere('first_name', 'like', 'Maclean%');
                })
                ->orderBy('id')
                ->first();
        }

        if (! $borrowerUser && $borrower?->user_id) {
            $borrowerUser = User::query()->find($borrower->user_id);
        }

        // Prefer MacLean-named account when phone collision exists (e.g. partner rows).
        if ($borrowerUser && ! str_contains(strtolower((string) $borrowerUser->name), 'maclean')) {
            $named = User::query()
                ->where(function ($q) {
                    $q->whereIn('phone', ['+255715222132', '255715222132', '0715222132'])
                        ->orWhere('phone', 'like', '%715222132');
                })
                ->where('name', 'like', '%Maclean%')
                ->orderBy('id')
                ->first();
            if ($named) {
                $borrowerUser = $named;
                $borrower = Customer::query()->where('user_id', $named->id)->orderBy('id')->first() ?: $borrower;
            }
        }

        if (! $borrowerUser || ! $borrower) {
            $this->command?->error(
                'MacLean staging borrower not found for phone '.self::MACLEAN_PHONE
                .' (also tried +255715222132). Seed/migrate the Owner MacLean account first; do not invent a separate Mohamed persona.'
            );

            return;
        }

        // Keep existing phone formatting so Member login stays valid; do not overwrite KYC/identity.

        // Ensure Owner can log in with known PIN without resetting unrelated profile data.
        app(PinService::class)->setPin($borrowerUser, '1234');

        $oldGuarantorUser = User::query()->updateOrCreate(
            ['email' => 'uat.guarantor.old.maclean@staging.kopafasta.com'],
            [
                'name' => 'UAT Pending Guarantor',
                'phone' => '255700000042',
                'role' => 'borrower',
                'is_active' => true,
                'password' => Hash::make('StagingUat!2026'),
                'email_verified_at' => now(),
            ]
        );
        app(PinService::class)->setPin($oldGuarantorUser, '1234');

        $oldGuarantorCustomer = Customer::query()->updateOrCreate(
            ['customer_number' => 'CU-UAT-MOH-G-OLD'],
            [
                'user_id' => $oldGuarantorUser->id,
                'type' => 'individual',
                'status' => 'active',
                'first_name' => 'UAT',
                'last_name' => 'Pending Guarantor',
                'phone' => '255700000042',
                'country_code' => 'TZ',
                'membership_status' => 'active',
                'membership_expires_at' => now()->addYear(),
            ]
        );

        User::query()->updateOrCreate(
            ['email' => 'uat.guarantor.replacement.maclean@staging.kopafasta.com'],
            [
                'name' => 'UAT Replacement Guarantor',
                'phone' => '255700000043',
                'role' => 'borrower',
                'is_active' => true,
                'password' => Hash::make('StagingUat!2026'),
                'email_verified_at' => now(),
            ]
        );

        // Mohamed-shaped: paid fee; payment source_id deliberately null (ownership via application fields).
        $payment = CustomerPayment::query()->updateOrCreate(
            ['reference' => self::FEE_REF],
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
                        'draft_reference' => 'APP-UAT-MOH-STALE-DRAFT',
                        'scenario' => 'mohamed_awaiting_guarantor_under_maclean',
                    ],
                ],
            ]
        );

        $application = LoanApplication::query()->updateOrCreate(
            ['application_number' => self::APP_NUMBER],
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

        // Clear leftover underwriting/borrower supplement flags so Mohamed UAT is pending
        // replacement-capable (Change), not a false additional-guarantor request.
        $payload = is_array($application->screening_payload) ? $application->screening_payload : [];
        unset($payload['guarantor_supplement'], $payload['guarantor_quote_reconfirm']);
        $application->forceFill(['screening_payload' => $payload === [] ? null : $payload])->save();

        $forceReset = filter_var(env('FORCE_UAT_GUARANTOR_RESET', false), FILTER_VALIDATE_BOOLEAN);
        $liveInvite = GuarantorInvitation::query()
            ->where('loan_application_id', $application->id)
            ->latest('id')
            ->first();
        $liveLink = CustomerGuarantor::query()
            ->where('loan_application_id', $application->id)
            ->latest('id')
            ->first();

        // Never wipe Owner UAT progress (declined / accepted / reconfirm) on redeploy.
        if (! $forceReset && $liveInvite && in_array((string) $liveInvite->status, [
            'rejected', 'accepted', 'expired', 'cancelled',
        ], true)) {
            $this->command?->info('MacLean Mohamed-scenario preserved (staging only) — invitation '.$liveInvite->status.'; set FORCE_UAT_GUARANTOR_RESET=1 to reseed pending.');
            $this->command?->info('  Member phone: '.self::MACLEAN_PHONE.'  PIN: 1234');
            $this->command?->info('  Application: '.self::APP_NUMBER.'  invite token: '.$liveInvite->token);
            $this->command?->info('  Production APP-EM-MU8Q untouched.');

            return;
        }

        if (! $forceReset && $liveInvite && (string) $liveInvite->status === 'pending' && $liveLink) {
            // Keep existing pending invite stable across deploys (same token for Owner walkthrough).
            $liveInvite->forceFill([
                'guarantor_customer_id' => $oldGuarantorCustomer->id,
                'contact' => $oldGuarantorCustomer->phone,
                'invitee_name' => trim($oldGuarantorCustomer->first_name.' '.$oldGuarantorCustomer->last_name),
                'expires_at' => $liveInvite->expires_at ?: now()->addDays(14),
            ])->save();
            $this->command?->info('MacLean Mohamed-scenario pending invite retained (staging only):');
            $this->command?->info('  Member: '.trim($borrower->first_name.' '.$borrower->last_name).'  phone: '.self::MACLEAN_PHONE.'  PIN: 1234');
            $this->command?->info('  Application: '.self::APP_NUMBER.'  token: '.$liveInvite->token);
            $this->command?->info('  Pending guarantor phone: 255700000042  Replacement ready: 255700000043');

            return;
        }

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
            'token' => 'uat-moh-mac-'.Str::lower(Str::random(12)),
            'short_code' => 'UM'.random_int(100, 999),
            'contact' => $oldGuarantorCustomer->phone,
            'invitee_name' => trim($oldGuarantorCustomer->first_name.' '.$oldGuarantorCustomer->last_name),
            'status' => 'pending',
            'expires_at' => now()->addDays(14),
        ]);

        LoanApplicationDraft::query()
            ->where('customer_id', $borrower->id)
            ->where('loan_product_id', $product->id)
            ->delete();

        $this->command?->info('MacLean Mohamed-scenario application ready (staging only):');
        $this->command?->info('  Member: '.trim($borrower->first_name.' '.$borrower->last_name).'  phone: '.self::MACLEAN_PHONE.'  PIN: 1234');
        $this->command?->info('  Application: '.self::APP_NUMBER.'  status: awaiting_guarantor');
        $this->command?->info('  Fee: '.self::FEE_REF.' (paid, source_id null) — TZS 0 additional on guarantor change');
        $this->command?->info('  Pending guarantor phone: 255700000042  Replacement ready: 255700000043');
        $this->command?->info('  Production APP-EM-MU8Q untouched.');
    }
}
