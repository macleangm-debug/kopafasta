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
            ['code' => 'selfie',            'name' => 'Selfie with ID'],
            ['code' => 'residence_letter',  'name' => 'Residence verification letter (LGA)'],
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
