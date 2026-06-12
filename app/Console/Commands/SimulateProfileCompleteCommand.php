<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\CustomerDocument;
use App\Models\CustomerKyc;
use App\Models\DocumentType;
use App\Models\FaceVerification;
use App\Services\FaceVerificationService;
use App\Services\NidaVerificationService;
use App\Services\ProfileCompletionService;
use App\Services\ProfileDocumentService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SimulateProfileCompleteCommand extends Command
{
    protected $signature = 'customer:simulate-profile-complete {identifier : Customer number or partial match (e.g. Q6IV)}';

    protected $description = 'Mark a customer profile as 100% complete for testing (guarantor / portal flows).';

    public function handle(): int
    {
        $identifier = strtoupper(trim($this->argument('identifier')));

        $customer = Customer::query()
            ->where('customer_number', $identifier)
            ->orWhere('customer_number', 'like', '%'.$identifier.'%')
            ->orWhere('member_no', $identifier)
            ->orWhere('member_no', 'like', '%'.$identifier.'%')
            ->first();

        if (! $customer) {
            $this->error("No customer found matching [{$identifier}].");

            return self::FAILURE;
        }

        DB::transaction(function () use ($customer): void {
            $customer->fill([
                'region'          => $customer->region ?: 'Dar es Salaam',
                'district'        => $customer->district ?: 'Kinondoni',
                'ward'            => $customer->ward ?: 'Mikocheni',
                'street'          => $customer->street ?: 'Test Street 1',
                'address'         => $customer->address ?: 'Test Street 1, Mikocheni, Kinondoni, Dar es Salaam',
                'activity_type'   => $customer->activity_type ?: 'employed',
                'employment_type' => $customer->employment_type ?: 'employed',
                'income_range'    => $customer->income_range ?: '500k_1m',
                'nok_first_name'  => $customer->nok_first_name ?: 'Jane',
                'nok_middle_name' => $customer->nok_middle_name,
                'nok_last_name'   => $customer->nok_last_name ?: 'Doe',
                'nok_name'        => $customer->nok_name ?: trim(($customer->nok_first_name ?: 'Jane').' '.($customer->nok_last_name ?: 'Doe')),
                'nok_relationship'=> $customer->nok_relationship ?: 'Sibling',
                'nok_phone'       => $customer->nok_phone ?: '+255700000001',
                'nok_region'      => $customer->nok_region ?: 'Dar es Salaam',
                'nok_district'    => $customer->nok_district ?: 'Kinondoni',
                'nok_street'      => $customer->nok_street ?: 'Kin Street 2',
            ])->save();

            if (! $customer->hasMembership()) {
                $customer->forceFill([
                    'membership_issued_at'  => now()->subMonths(2),
                    'membership_expires_at' => now()->addYear(),
                ])->save();
            }

            if (! app(NidaVerificationService::class)->isVerified($customer)) {
                $customer->forceFill([
                    'national_id'              => $customer->national_id ?: '19810713-00001-23456-78',
                    'nida_verification_status' => 'verified',
                    'nida_verified_at'         => now(),
                    'identity_locked'          => true,
                    'nida_locked_until'        => null,
                    'nida_mismatch_attempts'   => 0,
                ])->save();
            }

            $this->ensureDocument($customer, 'national_id_front');
            $this->ensureDocument($customer, 'employment_contract');
            $this->ensureDocument($customer, 'residence_letter');
            $this->ensureDocument($customer, 'bank_statement');

            $face = app(FaceVerificationService::class);
            foreach ($face->requiredAngleKeys() as $angle) {
                FaceVerification::query()->firstOrCreate(
                    ['customer_id' => $customer->id, 'angle' => $angle],
                    ['file_path' => "borrower/{$customer->id}/face/simulated-{$angle}.jpg", 'status' => 'pending_review']
                );
            }

            if (! in_array($customer->face_verification_status, ['pending', 'verified'], true)) {
                $customer->update(['face_verification_status' => 'pending']);
            }

            CustomerKyc::firstOrCreate(
                ['customer_id' => $customer->id],
                ['status' => 'in_review', 'payload' => []]
            );
        });

        $customer->refresh();
        $percent = app(ProfileCompletionService::class)->calculate($customer)['percent'] ?? 0;
        $complete = app(ProfileCompletionService::class)->isFullyComplete($customer);

        $this->info("Customer #{$customer->customer_number} (ID {$customer->id}) — profile {$percent}% — ".($complete ? 'COMPLETE' : 'incomplete'));

        return $complete ? self::SUCCESS : self::FAILURE;
    }

    private function ensureDocument(Customer $customer, string $code): void
    {
        if (app(\App\Services\ProfileDocumentService::class)->hasProfileDocument($customer, $code)) {
            return;
        }

        $type = DocumentType::query()->where('code', $code)->where('is_active', true)->first();
        if (! $type) {
            return;
        }

        CustomerDocument::query()->updateOrCreate(
            [
                'customer_id'         => $customer->id,
                'document_type_id'    => $type->id,
                'loan_application_id' => null,
            ],
            [
                'file_path' => "customer/{$customer->id}/documents/simulated-{$code}.pdf",
                'status'    => 'approved',
            ],
        );
    }
}
