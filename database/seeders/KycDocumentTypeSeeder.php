<?php

namespace Database\Seeders;

use App\Models\DocumentType;
use Illuminate\Database\Seeder;

class KycDocumentTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['code' => 'national_id_front', 'name' => 'National ID — front'],
            ['code' => 'national_id_back',  'name' => 'National ID — back'],
            ['code' => 'identity_verification_photo', 'name' => 'Identity verification photo'],
            ['code' => 'selfie',            'name' => 'Selfie with ID'],
            ['code' => 'residence_letter',  'name' => 'Residence verification letter (LGA)'],
            ['code' => 'employment_contract', 'name' => 'Employment contract'],
            ['code' => 'bank_statement',      'name' => 'Bank statement (last 6 months)'],
            ['code' => 'mobile_money_statement', 'name' => 'Mobile money statement (last 6 months)'],
            ['code' => 'mpesa_statement',     'name' => 'M-Pesa statement (last 6 months)'],
            ['code' => 'salary_slip',         'name' => 'Salary slip'],
            ['code' => 'business_license',    'name' => 'Business license'],
            ['code' => 'tin_certificate',     'name' => 'TIN certificate'],
            ['code' => 'vat_certificate',     'name' => 'VAT certificate'],
            ['code' => 'business_photos',     'name' => 'Business photos'],
            ['code' => 'workshop_photos',     'name' => 'Workshop photos'],
            ['code' => 'business_registration', 'name' => 'Business registration documents'],
            ['code' => 'income_statement',    'name' => 'Income statement'],
            ['code' => 'signature',         'name' => 'Signature sample'],
        ];

        foreach ($types as $t) {
            DocumentType::updateOrCreate(
                ['code' => $t['code']],
                [
                    'name'       => $t['name'],
                    'category'   => 'kyc',
                    'applies_to' => 'individual',
                    'is_active'  => true,
                ]
            );
        }
    }
}
