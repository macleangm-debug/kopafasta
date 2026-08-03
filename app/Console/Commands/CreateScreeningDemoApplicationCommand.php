<?php

namespace App\Console\Commands;

use App\Models\ApplicationStageHistory;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerDisbursementAccount;
use App\Models\CustomerDocument;
use App\Models\CustomerGuarantor;
use App\Models\CustomerKyc;
use App\Models\DocumentType;
use App\Models\FaceVerification;
use App\Models\Guarantor;
use App\Models\GuarantorInvitation;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\User;
use App\Services\ApplicationRequirementsService;
use App\Services\FaceVerificationService;
use App\Services\GuarantorOnboardingService;
use App\Services\NidaVerificationService;
use App\Services\ProfileCompletionService;
use App\Services\ProfileDocumentService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CreateScreeningDemoApplicationCommand extends Command
{
    protected $signature = 'demo:screening-application
                            {--amount=750000 : Requested amount}
                            {--tenure=6 : Tenure in months}';

    protected $description = 'Create a fresh screening-queue loan with dummy complete borrower + guarantor profiles.';

    public function handle(): int
    {
        $suffix = strtoupper(Str::random(4));
        $branch = Branch::query()->where('is_active', true)->orderBy('id')->first()
            ?? Branch::query()->orderBy('id')->first();

        if (! $branch) {
            $this->error('No branch found. Seed branches first.');

            return self::FAILURE;
        }

        $product = LoanProduct::query()
            ->where('is_active', true)
            ->where('requires_guarantor', true)
            ->orderBy('id')
            ->first()
            ?? LoanProduct::query()->where('is_active', true)->orderBy('id')->first();

        if (! $product) {
            $this->error('No active loan product found.');

            return self::FAILURE;
        }

        $amount = (float) $this->option('amount');
        $tenure = (int) $this->option('tenure');

        $result = DB::transaction(function () use ($suffix, $branch, $product, $amount, $tenure) {
            $borrower = $this->makeCustomer([
                'first' => 'Amina',
                'last' => 'DemoBorrower',
                'phone' => '+25571'.random_int(2000000, 8999999),
                'nida' => '19850415-'.str_pad((string) random_int(1, 99999), 5, '0', STR_PAD_LEFT).'-11111-71',
                'gender' => 'female',
                'employer' => 'Demo Traders Ltd',
                'job' => 'Sales Manager',
                'income' => 950000,
                'suffix' => $suffix,
                'role' => 'B',
                'branch_id' => $branch->id,
            ]);

            $guarantorCustomer = $this->makeCustomer([
                'first' => 'Joseph',
                'last' => 'DemoGuarantor',
                'phone' => '+25576'.random_int(2000000, 8999999),
                'nida' => '19820822-'.str_pad((string) random_int(1, 99999), 5, '0', STR_PAD_LEFT).'-22222-76',
                'gender' => 'male',
                'employer' => 'Harbor Logistics Co',
                'job' => 'Operations Lead',
                'income' => 1400000,
                'suffix' => $suffix,
                'role' => 'G',
                'branch_id' => $branch->id,
            ]);

            $this->completeProfile($borrower);
            $this->completeProfile($guarantorCustomer);

            $appNumber = 'APP-SCR-'.$suffix;
            $app = LoanApplication::create([
                'customer_id'             => $borrower->id,
                'loan_product_id'         => $product->id,
                'branch_id'               => $branch->id,
                'application_number'      => $appNumber,
                'requested_amount'        => $amount,
                'requested_tenure_months' => $tenure,
                'purpose'                 => 'Demo screening file — stock restock and working capital',
                'status'                  => 'under_review',
                'current_stage'           => 'screening',
                'submitted_at'            => now()->subHours(2),
            ]);

            ApplicationStageHistory::create([
                'loan_application_id' => $app->id,
                'from_stage'          => 'submitted',
                'to_stage'            => 'screening',
                'changed_by'          => null,
                'remarks'             => 'Demo: auto-advanced to screening',
            ]);

            $contact = Guarantor::create([
                'first_name'   => $guarantorCustomer->first_name,
                'last_name'    => $guarantorCustomer->last_name,
                'phone'        => $guarantorCustomer->phone,
                'email'        => $guarantorCustomer->email,
                'national_id'  => $guarantorCustomer->national_id,
                'address'      => $guarantorCustomer->address,
                'relationship' => 'colleague',
            ]);

            $link = CustomerGuarantor::create([
                'customer_id'         => $borrower->id,
                'guarantor_id'        => $contact->id,
                'loan_application_id' => $app->id,
                'status'              => 'approved',
            ]);

            GuarantorInvitation::create([
                'customer_id'           => $borrower->id,
                'customer_guarantor_id'  => $link->id,
                'loan_application_id'   => $app->id,
                'loan_product_id'       => $product->id,
                'guarantor_customer_id' => $guarantorCustomer->id,
                'type'                  => 'member',
                'status'                => 'accepted',
                'contact'               => $guarantorCustomer->phone,
                'invitee_name'          => $guarantorCustomer->full_name,
                'token'                 => 'demo-scr-'.Str::lower($suffix).'-'.Str::random(16),
                'requested_amount'      => $amount,
                'responded_at'          => now()->subHour(),
            ]);

            return compact('app', 'borrower', 'guarantorCustomer', 'product');
        });

        /** @var LoanApplication $app */
        $app = $result['app']->fresh();
        $borrower = $result['borrower']->fresh();
        $guarantor = $result['guarantorCustomer']->fresh();

        $bPct = app(ProfileCompletionService::class)->calculate($borrower)['percent'] ?? 0;
        $gPct = app(ProfileCompletionService::class)->calculate($guarantor)['percent'] ?? 0;
        $gMet = app(GuarantorOnboardingService::class)->guarantorProfileStatus($guarantor)['met'] ?? false;
        $bCan = app(ApplicationRequirementsService::class)->checklist($borrower)['can_apply'] ?? false;

        $this->newLine();
        $this->info('Screening demo application ready');
        $this->line("  Application : {$app->application_number} (id {$app->id})");
        $this->line("  Stage       : {$app->current_stage}");
        $this->line("  Product     : {$result['product']->name} ({$result['product']->code})");
        $this->line('  Amount      : '.number_format((float) $app->requested_amount));
        $this->line("  Borrower    : {$borrower->full_name} · {$borrower->customer_number} · profile {$bPct}% · can_apply=".($bCan ? 'yes' : 'no'));
        $this->line("  Guarantor   : {$guarantor->full_name} · {$guarantor->customer_number} · profile {$gPct}% · ready=".($gMet ? 'yes' : 'no'));
        $this->line('  Review URL  : '.route('admin.loan-applications.show', $app));
        $this->line('  Screening   : '.route('admin.teams.screening'));
        $this->newLine();

        return self::SUCCESS;
    }

    /** @param  array{first:string,last:string,phone:string,nida:string,gender:string,employer:string,job:string,income:int,suffix:string,role:string,branch_id:int}  $data */
    private function makeCustomer(array $data): Customer
    {
        $email = strtolower($data['first'].'.'.$data['last'].'.'.$data['suffix']).'@demo.kopafasta.tz';

        $user = User::create([
            'name'     => $data['first'].' '.$data['last'],
            'email'    => $email,
            'phone'    => $data['phone'],
            'password' => Hash::make('DemoPass123!'),
            'role'     => 'borrower',
            'is_active'=> true,
        ]);

        return Customer::create([
            'user_id'                  => $user->id,
            'customer_number'          => 'CUS-SCR'.$data['role'].'-'.$data['suffix'],
            'member_no'                => 'MBR-SCR'.$data['role'].'-'.$data['suffix'],
            'type'                     => 'individual',
            'status'                   => 'active',
            'first_name'               => $data['first'],
            'last_name'                => $data['last'],
            'email'                    => $email,
            'phone'                    => $data['phone'],
            'date_of_birth'            => now()->subYears($data['role'] === 'B' ? 32 : 41)->toDateString(),
            'gender'                   => $data['gender'],
            'national_id'              => $data['nida'],
            'branch_id'                => $data['branch_id'],
            'region'                   => 'Dar es Salaam',
            'district'                 => 'Kinondoni',
            'ward'                     => 'Mikocheni',
            'street'                   => $data['role'] === 'B' ? 'Demo Avenue 12' : 'Guarantor Lane 7',
            'address'                  => ($data['role'] === 'B' ? 'Demo Avenue 12' : 'Guarantor Lane 7').', Mikocheni, Kinondoni, Dar es Salaam',
            'lga_officer_name'         => 'Hassan Mwinyi',
            'lga_officer_position'     => 'Street Chairperson',
            'lga_officer_phone'        => '+255700111222',
            'activity_type'            => 'employed',
            'employment_type'          => 'employed',
            'income_range'             => '500k_1m',
            'monthly_income'           => $data['income'],
            'activity_details'         => [
                'employer_name' => $data['employer'],
                'job_title'     => $data['job'],
            ],
            'nok_first_name'           => 'Neema',
            'nok_last_name'            => 'Kin',
            'nok_name'                 => 'Neema Kin',
            'nok_relationship'         => 'Sibling',
            'nok_phone'                => '+255700'.random_int(100000, 999999),
            'nok_region'               => 'Dar es Salaam',
            'nok_district'             => 'Ilala',
            'nok_street'               => 'Kin Street 4',
            'nida_verification_status' => 'verified',
            'nida_verified_at'         => now()->subDays(10),
            'identity_locked'          => true,
            'face_verification_status' => 'verified',
            'membership_status'        => 'active',
            'membership_issued_at'     => now()->subMonths(3),
            'membership_expires_at'    => now()->addYear(),
            'onboarded_at'             => now()->subMonths(2)->toDateString(),
        ]);
    }

    private function completeProfile(Customer $customer): void
    {
        $this->ensureDocumentTypes();

        if (! app(NidaVerificationService::class)->isVerified($customer)) {
            $customer->forceFill([
                'nida_verification_status' => 'verified',
                'nida_verified_at'         => now(),
                'identity_locked'          => true,
            ])->save();
        }

        foreach (['national_id_front', 'national_id_back', 'employment_contract', 'residence_letter', 'salary_slip', 'bank_statement'] as $code) {
            $this->ensureDocument($customer, $code);
        }

        $face = app(FaceVerificationService::class);
        foreach ($face->requiredAngleKeys() as $angle) {
            FaceVerification::query()->firstOrCreate(
                ['customer_id' => $customer->id, 'angle' => $angle],
                [
                    'file_path' => "borrower/{$customer->id}/face/demo-{$angle}.jpg",
                    'status'    => 'verified',
                ]
            );
        }

        // Tiny 1×1 PNG — satisfies BorrowerSignatureService::hasProfileSignature
        $png = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

        $customer->forceFill([
            'face_verification_status' => 'verified',
            'legal_signature_data'     => $png,
            'legal_signer_name'        => $customer->full_name,
            'legal_signed_at'          => now()->subDays(3),
        ])->save();

        CustomerKyc::query()->firstOrCreate(
            ['customer_id' => $customer->id],
            ['status' => 'approved', 'payload' => ['demo' => true]]
        );

        CustomerDisbursementAccount::query()->firstOrCreate(
            [
                'customer_id' => $customer->id,
                'type'        => 'mobile_money',
                'is_default'  => true,
            ],
            [
                'account_name'    => trim($customer->first_name.' '.$customer->last_name),
                'mobile_provider' => 'mpesa',
                'mobile_number'   => preg_replace('/\D/', '', (string) $customer->phone),
            ]
        );
    }

    private function ensureDocumentTypes(): void
    {
        if (DocumentType::query()->where('code', 'employment_contract')->exists()) {
            return;
        }

        (new \Database\Seeders\KycDocumentTypeSeeder)->run();
    }

    private function ensureDocument(Customer $customer, string $code): void
    {
        if (app(ProfileDocumentService::class)->hasProfileDocument($customer, $code)) {
            return;
        }

        $type = DocumentType::query()->where('code', $code)->where('is_active', true)->first()
            ?? DocumentType::query()->where('code', $code)->first();

        if (! $type) {
            $this->warn("Document type [{$code}] missing — skipped for {$customer->customer_number}");

            return;
        }

        CustomerDocument::query()->updateOrCreate(
            [
                'customer_id'         => $customer->id,
                'document_type_id'    => $type->id,
                'loan_application_id' => null,
            ],
            [
                'file_path' => "customer/{$customer->id}/documents/demo-{$code}.pdf",
                'status'    => 'approved',
            ]
        );
    }
}
