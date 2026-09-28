<?php

namespace App\Console\Commands;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerDisbursementAccount;
use App\Models\CustomerDocument;
use App\Models\CustomerKyc;
use App\Models\DocumentType;
use App\Models\FaceVerification;
use App\Models\GuarantorInvitation;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\Setting;
use App\Models\User;
use App\Services\ApplicationIntakeReadinessService;
use App\Services\ApplicationIntakeTransitionService;
use App\Services\CapacityAutoRejectService;
use App\Services\FaceVerificationService;
use App\Services\NidaVerificationService;
use App\Services\ProfileCompletionService;
use App\Services\ProfileDocumentService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Staging-only UZ16-shaped affordability-fail UAT. Parks via the real First Gate.
 * Does not fire final rejection. Does not copy production PII.
 */
class StagingUz16ParkUatCommand extends Command
{
    public const APPLICATION_NUMBER = 'APP-UAT-UZ16';

    public const PHONE = '255715016016';

    public const PIN = '1234';

    protected $signature = 'staging:uz16-park-uat';

    protected $description = 'Create one fictional staging borrower that genuinely fails First Gate and stays parked (UZ16-shaped UAT).';

    public function handle(): int
    {
        if (! app()->environment(['staging', 'local', 'testing'])) {
            $this->error('Refusing: only staging/local/testing.');

            return self::FAILURE;
        }

        Setting::set('underwriting.enable_automatic_rejection', true);
        Setting::set('underwriting.enable_capacity_auto_reject', true);
        Setting::set('underwriting.capacity_auto_reject_delay_hours', 12);

        $existing = LoanApplication::query()->where('application_number', self::APPLICATION_NUMBER)->first();
        if ($existing && app(CapacityAutoRejectService::class)->isPending($existing)) {
            $this->printReport($existing->fresh(['customer.user', 'product']));

            return self::SUCCESS;
        }

        $branch = Branch::query()->where('is_active', true)->orderBy('id')->first()
            ?? Branch::query()->orderBy('id')->first();
        if (! $branch) {
            $this->error('No branch found.');

            return self::FAILURE;
        }

        $product = LoanProduct::query()
            ->where('is_active', true)
            ->where(function ($q) {
                $q->where('code', 'like', 'IL%')
                    ->orWhere('name', 'like', '%Individual%');
            })
            ->orderBy('id')
            ->first()
            ?? LoanProduct::query()->where('is_active', true)->orderBy('id')->first();
        if (! $product) {
            $this->error('No active loan product found.');

            return self::FAILURE;
        }

        $customer = $this->makeBorrower($branch);
        $this->completeProfile($customer);
        if (! app(ProfileCompletionService::class)->isFullyComplete($customer->fresh())) {
            $this->error('Profile is not complete: '.implode(', ', app(ProfileCompletionService::class)->completionSummary($customer->fresh())['remaining'] ?? []));

            return self::FAILURE;
        }

        $application = $existing ?? LoanApplication::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'branch_id' => $branch->id,
            'application_number' => self::APPLICATION_NUMBER,
            'requested_amount' => 40_800_000,
            'requested_tenure_months' => 12,
            'purpose' => 'Staging UAT — affordability park (UZ16-shaped, fictional)',
            'status' => 'submitted',
            'current_stage' => ApplicationIntakeReadinessService::STATE_INITIAL_CHECK,
            'submitted_at' => now(),
            'application_fee_status' => 'waived',
            'screening_payload' => [
                'intake' => ['staging_uz16_sim' => true],
            ],
        ]);
        if ($existing) {
            $application->update([
                'customer_id' => $customer->id,
                'loan_product_id' => $product->id,
                'requested_amount' => 40_800_000,
                'requested_tenure_months' => 12,
                'status' => 'submitted',
                'current_stage' => ApplicationIntakeReadinessService::STATE_INITIAL_CHECK,
                'submitted_at' => now(),
            ]);
        }

        $fresh = app(ApplicationIntakeTransitionService::class)
            ->afterBorrowerSubmit($application->fresh(['customer', 'product']));

        if ((string) $fresh->current_stage !== ApplicationIntakeReadinessService::STATE_HOLD
            || ! app(CapacityAutoRejectService::class)->isPending($fresh)) {
            $this->error('Expected a genuine First-Gate park. Got '.$fresh->status.'/'.$fresh->current_stage);

            return self::FAILURE;
        }

        $invites = GuarantorInvitation::query()->where('loan_application_id', $fresh->id)->count();
        if ($invites > 0) {
            $this->error('Guarantor invitation was released. Stopping.');

            return self::FAILURE;
        }

        $this->printReport($fresh->fresh(['customer.user', 'product']));

        return self::SUCCESS;
    }

    private function makeBorrower(Branch $branch): Customer
    {
        $user = User::query()->updateOrCreate(
            ['phone' => self::PHONE],
            [
                'name' => 'Asha Uat Parked',
                'email' => 'asha.uat.parked.'.Str::lower(Str::random(4)).'@staging-uat.kopafasta.test',
                'password' => Hash::make(Str::random(24)),
                'role' => 'borrower',
                'is_active' => true,
                'pin_hash' => Hash::make(self::PIN),
            ]
        );

        return Customer::query()->updateOrCreate(
            ['phone' => self::PHONE],
            [
                'user_id' => $user->id,
                'customer_number' => 'CU-UAT-UZ16',
                'member_no' => 'MBR-UAT-UZ16',
                'type' => 'individual',
                'status' => 'active',
                'first_name' => 'Asha',
                'last_name' => 'Uat Parked',
                'email' => $user->email,
                'phone' => self::PHONE,
                'date_of_birth' => now()->subYears(34)->toDateString(),
                'gender' => 'female',
                'marital_status' => 'single',
                'number_of_children' => 0,
                'national_id' => '19920315-'.str_pad((string) random_int(1, 99999), 5, '0', STR_PAD_LEFT).'-11111-71',
                'branch_id' => $branch->id,
                'region' => 'Dar es Salaam',
                'district' => 'Kinondoni',
                'ward' => 'Mikocheni',
                'street' => 'Uat Avenue 16',
                'address' => 'Uat Avenue 16, Mikocheni, Kinondoni, Dar es Salaam',
                'lga_officer_name' => 'Hassan Mwinyi',
                'lga_officer_position' => 'Street Chairperson',
                'lga_officer_phone' => '255700111222',
                'activity_type' => 'employed',
                'employment_type' => 'employed',
                'income_range' => '500k_1m',
                'monthly_income' => 750_000,
                'activity_details' => [
                    'employer_name' => 'Uat Traders Ltd',
                    'job_title' => 'Sales Associate',
                ],
                'nok_first_name' => 'Neema',
                'nok_last_name' => 'Kin',
                'nok_name' => 'Neema Kin',
                'nok_relationship' => 'Sibling',
                'nok_phone' => '255700'.random_int(100000, 999999),
                'nok_region' => 'Dar es Salaam',
                'nok_district' => 'Ilala',
                'nok_street' => 'Kin Street 4',
                'nida_verification_status' => 'verified',
                'nida_verified_at' => now()->subDays(10),
                'identity_locked' => true,
                'face_verification_status' => 'verified',
                'membership_status' => 'active',
                'membership_issued_at' => now()->subMonths(3),
                'membership_expires_at' => now()->addYear(),
                'onboarded_at' => now()->subMonths(2)->toDateString(),
            ]
        );
    }

    private function completeProfile(Customer $customer): void
    {
        if (! DocumentType::query()->where('code', 'employment_contract')->exists()) {
            (new \Database\Seeders\KycDocumentTypeSeeder)->run();
        }

        if (! app(NidaVerificationService::class)->isVerified($customer)) {
            $customer->forceFill([
                'nida_verification_status' => 'verified',
                'nida_verified_at' => now(),
                'identity_locked' => true,
            ])->save();
        }

        foreach (['national_id_front', 'national_id_back', 'employment_contract', 'residence_letter', 'salary_slip', 'bank_statement'] as $code) {
            $type = DocumentType::query()->where('code', $code)->where('is_active', true)->first()
                ?? DocumentType::query()->where('code', $code)->first();
            if (! $type) {
                continue;
            }
            if (app(ProfileDocumentService::class)->hasProfileDocument($customer, $code)) {
                continue;
            }
            CustomerDocument::query()->updateOrCreate(
                [
                    'customer_id' => $customer->id,
                    'document_type_id' => $type->id,
                    'loan_application_id' => null,
                ],
                [
                    'file_path' => "customer/{$customer->id}/documents/uat-{$code}.pdf",
                    'status' => 'approved',
                ]
            );
        }

        $face = app(FaceVerificationService::class);
        foreach ($face->requiredAngleKeys() as $angle) {
            FaceVerification::query()->firstOrCreate(
                ['customer_id' => $customer->id, 'angle' => $angle],
                [
                    'file_path' => "borrower/{$customer->id}/face/uat-{$angle}.jpg",
                    'status' => 'verified',
                ]
            );
        }

        $png = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';
        $customer->forceFill([
            'face_verification_status' => 'verified',
            'legal_signature_data' => $png,
            'legal_signer_name' => trim($customer->first_name.' '.$customer->last_name),
            'legal_signed_at' => now()->subDays(3),
        ])->save();

        CustomerKyc::query()->firstOrCreate(
            ['customer_id' => $customer->id],
            ['status' => 'approved', 'payload' => ['uat' => true]]
        );
        CustomerDisbursementAccount::query()->firstOrCreate(
            [
                'customer_id' => $customer->id,
                'type' => 'mobile_money',
                'is_default' => true,
            ],
            [
                'account_name' => trim($customer->first_name.' '.$customer->last_name),
                'mobile_provider' => 'mpesa',
                'mobile_number' => self::PHONE,
            ]
        );
    }

    private function printReport(LoanApplication $application): void
    {
        $capacity = app(CapacityAutoRejectService::class)->state($application) ?? [];
        $customer = $application->customer;
        $this->table(
            ['Field', 'Value'],
            [
                ['Reference', 'APP-IL-UZ16'],
                ['Staging member name', trim(($customer?->first_name ?? '').' '.($customer?->last_name ?? ''))],
                ['Member number', $customer?->member_no ?? $customer?->customer_number],
                ['Phone', $customer?->phone],
                ['PIN', self::PIN],
                ['Staging application', $application->application_number],
                ['Profile complete', app(ProfileCompletionService::class)->isFullyComplete($customer) ? 'YES' : 'NO'],
                ['Status/stage', $application->status.'/'.$application->current_stage],
                ['Parked at', $capacity['parked_at'] ?? ''],
                ['Auto-reject at', $capacity['auto_reject_at'] ?? ''],
                ['Guarantor invitations', (string) GuarantorInvitation::query()->where('loan_application_id', $application->id)->count()],
                ['Capacity status', $capacity['status'] ?? ''],
            ]
        );
    }
}
