<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\User;
use App\Models\Vendor;
use App\Services\PinRecoveryChallengeService;
use App\Services\PinService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Sanitized staging UAT accounts only. Never run on production.
 */
class StagingUatSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction() && ! app()->environment('staging')) {
            $this->command?->warn('StagingUatSeeder skipped: not a staging environment.');

            return;
        }

        $admin = User::query()->updateOrCreate(
            ['email' => 'uat.admin@staging.kopafasta.com'],
            [
                'name' => 'Staging Admin',
                'phone' => '255700000001',
                'role' => 'admin',
                'is_active' => true,
                'password' => Hash::make('StagingUat!2026'),
                'email_verified_at' => now(),
            ]
        );
        app(PinService::class)->setPin($admin, '1234');
        $this->enrollUatRecovery($admin);

        $borrowerUser = User::query()->updateOrCreate(
            ['email' => 'uat.borrower@staging.kopafasta.com'],
            [
                'name' => 'UAT Borrower',
                'phone' => '255700000002',
                'role' => 'borrower',
                'is_active' => true,
                'password' => Hash::make('StagingUat!2026'),
                'email_verified_at' => now(),
            ]
        );
        app(PinService::class)->setPin($borrowerUser, '1234');
        $this->enrollUatRecovery($borrowerUser);

        Customer::query()->updateOrCreate(
            ['customer_number' => 'CU-UAT-0001'],
            [
                'user_id' => $borrowerUser->id,
                'type' => 'individual',
                'status' => 'active',
                'first_name' => 'UAT',
                'last_name' => 'Borrower',
                'phone' => '255700000002',
                'country_code' => 'TZ',
                'loyalty_points' => 500,
                'membership_status' => 'active',
                'membership_expires_at' => now()->addYear(),
                'nida_verification_status' => 'verified',
                'face_verification_status' => 'verified',
                'date_of_birth' => now()->subYears(30)->toDateString(),
                'region' => 'Dar es Salaam',
                'district' => 'Kinondoni',
            ]
        );

        $lowPointsUser = User::query()->updateOrCreate(
            ['email' => 'uat.borrower.low@staging.kopafasta.com'],
            [
                'name' => 'UAT Low Points',
                'phone' => '255700000003',
                'role' => 'borrower',
                'is_active' => true,
                'password' => Hash::make('StagingUat!2026'),
                'email_verified_at' => now(),
            ]
        );
        app(PinService::class)->setPin($lowPointsUser, '1234');
        $this->enrollUatRecovery($lowPointsUser);

        Customer::query()->updateOrCreate(
            ['customer_number' => 'CU-UAT-0002'],
            [
                'user_id' => $lowPointsUser->id,
                'type' => 'individual',
                'status' => 'active',
                'first_name' => 'UAT',
                'last_name' => 'LowPoints',
                'phone' => '255700000003',
                'country_code' => 'TZ',
                'loyalty_points' => 40,
                'membership_status' => 'active',
                'membership_expires_at' => now()->addYear(),
                'nida_verification_status' => 'verified',
                'face_verification_status' => 'verified',
                'date_of_birth' => now()->subYears(28)->toDateString(),
                'region' => 'Dar es Salaam',
                'district' => 'Kinondoni',
            ]
        );

        // Always refresh KITONGA so staging UAT has a working canonical affiliate promo
        // for application-fee payment.show (existing migrated rows may be incomplete).
        Vendor::query()->updateOrCreate(
            ['affiliate_code' => 'KITONGA'],
            [
                'partner_number' => 'AFF-UAT-KITONGA',
                'name' => 'UAT Kitonga Affiliate',
                'category' => 'affiliate',
                'status' => 'active',
                'phone' => '255700000010',
                'email' => 'uat.kitonga@staging.kopafasta.com',
                'affiliate_code' => 'KITONGA',
                'affiliate_kyc_status' => 'verified',
                'affiliate_lifecycle_status' => 'active',
                'membership_status' => 'active',
                'membership_started_at' => now()->subMonth(),
                'membership_expires_at' => now()->addYear(),
                'application_discount_percent' => 10,
                'registration_discount_percent' => 10,
                'activated_at' => now(),
            ]
        );

        // Ensure affiliate promo codes apply to application fees on staging.
        $applies = app(\App\Services\AffiliateSettingsService::class)->appliesTo();
        $applies['application_fee'] = true;
        \App\Models\Setting::set('affiliates.applies_to', $applies);

        // Real UAT borrowers already have applications. Without this, canonical
        // attachAffiliate refuses KITONGA at payment.show (existing-borrower block).
        $attribution = app(\App\Services\AffiliateSettingsService::class)->attributionSettings();
        $attribution['existing_customer_referral'] = true;
        \App\Models\Setting::set('affiliates.attribution', $attribution);

        $kitonga = Vendor::query()->where('affiliate_code', 'KITONGA')->first();
        if ($kitonga && ! app(\App\Services\AffiliateTermsService::class)->hasAccepted($kitonga)) {
            $terms = app(\App\Services\AffiliateTermsService::class);
            \App\Models\PartnerAgreementAcceptance::query()->create([
                'partner_id' => $kitonga->id,
                'partner_type' => 'affiliate',
                'agreement_key' => \App\Services\AffiliateTermsService::AGREEMENT_KEY,
                'agreement_version' => $terms->agreementVersion(),
                'policy_version' => $terms->policyVersion(),
                'locale' => 'en',
                'rendered_text' => 'Staging UAT terms acceptance for KITONGA.',
                'content_hash' => hash('sha256', 'staging-uat-kitonga-v'.$terms->agreementVersion()),
                'settings_snapshot' => ['seeded' => true],
                'ip_address' => '127.0.0.1',
                'user_agent' => 'StagingUatSeeder',
                'accepted_at' => now(),
            ]);
        }

        $this->seedLoginableAffiliate([
            'email' => 'uat.affiliate.standard@staging.kopafasta.com',
            'phone' => '255700000011',
            'name' => 'UAT Standard Affiliate',
            'partner_number' => 'AFF-UAT-STD',
            'affiliate_code' => 'UATSTD01',
            'applicant_category' => 'individual',
            'affiliate_premium' => false,
        ]);
        $this->seedLoginableAffiliate([
            'email' => 'uat.affiliate.premium@staging.kopafasta.com',
            'phone' => '255700000012',
            'name' => 'UAT Premium Affiliate',
            'partner_number' => 'AFF-UAT-PREM',
            'affiliate_code' => 'UATPREM1',
            'applicant_category' => 'individual',
            'affiliate_premium' => true,
        ]);
        $this->seedLoginableAffiliate([
            'email' => 'uat.affiliate.company@staging.kopafasta.com',
            'phone' => '255700000013',
            'name' => 'UAT Company Affiliate Ltd',
            'legal_name' => 'UAT Company Affiliate Ltd',
            'partner_number' => 'AFF-UAT-CO',
            'affiliate_code' => 'UATCO001',
            'applicant_category' => 'company',
            'affiliate_premium' => false,
        ]);
    }

    /** @param array<string, mixed> $row */
    private function seedLoginableAffiliate(array $row): void
    {
        $user = User::query()->updateOrCreate(
            ['email' => $row['email']],
            [
                'name' => $row['name'],
                'phone' => $row['phone'],
                'role' => 'vendor',
                'is_active' => true,
                'password' => Hash::make('StagingUat!2026'),
                'email_verified_at' => now(),
            ]
        );
        app(PinService::class)->setPin($user, '1234');
        $this->enrollUatRecovery($user);

        $prefs = is_array($user->preferences) ? $user->preferences : [];
        $prefs['account_welcome_completed_at'] = now()->toIso8601String();
        $user->forceFill(['preferences' => $prefs])->save();

        $affiliate = Vendor::query()
            ->where(function ($query) use ($user, $row) {
                $query->where('user_id', $user->id)
                    ->orWhere('affiliate_code', $row['affiliate_code'])
                    ->orWhere('partner_number', $row['partner_number']);
            })
            ->first();

        $payload = [
            'user_id' => $user->id,
            'name' => $row['name'],
            'legal_name' => $row['legal_name'] ?? $row['name'],
            'category' => 'affiliate',
            'roles' => ['affiliate'],
            'status' => 'active',
            'phone' => $row['phone'],
            'email' => $row['email'],
            'applicant_category' => $row['applicant_category'],
            'affiliate_premium' => (bool) $row['affiliate_premium'],
            'affiliate_code' => $row['affiliate_code'],
            'affiliate_kyc_status' => 'submitted',
            'affiliate_lifecycle_status' => 'active',
            'membership_status' => 'active',
            'membership_started_at' => now()->subMonth(),
            'membership_expires_at' => now()->addYear(),
            'activated_at' => now(),
            'address' => 'UAT staging residence, Kinondoni',
            'coverage_type' => 'nationwide',
        ];

        if ($affiliate) {
            $affiliate->fill($payload)->save();
        } else {
            $payload['partner_number'] = $row['partner_number'];
            $affiliate = Vendor::query()->create($payload);
        }

        $affiliate = app(\App\Services\AffiliateService::class)->canonicalizeIdentity($affiliate->fresh());

        $terms = app(\App\Services\AffiliateTermsService::class);
        if (! $terms->hasAccepted($affiliate)) {
            $terms->accept($affiliate, \Illuminate\Http\Request::create('/uat-seed', 'POST'));
        }
    }

    private function enrollUatRecovery(User $user): void
    {
        app(PinRecoveryChallengeService::class)->enroll($user, [
            'mother_first_name' => 'Asha',
            'primary_school' => 'Uhuru Primary',
            'nida_middle4' => '4582',
        ]);
    }
}
