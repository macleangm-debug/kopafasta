<?php

namespace Database\Seeders;

use App\Models\DocumentType;
use Illuminate\Database\Seeder;

class CompanyKycDocumentTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['code' => 'biz_registration',   'name' => 'Business / Company registration certificate'],
            ['code' => 'biz_tin',            'name' => 'TIN certificate'],
            ['code' => 'biz_memo_articles',  'name' => 'Memorandum & Articles of Association'],
            ['code' => 'biz_board_resolution','name' => 'Board resolution to borrow'],
            ['code' => 'biz_directors_ids',  'name' => 'Directors\' / Owners\' IDs'],
            ['code' => 'biz_bank_statement', 'name' => 'Company bank statement (3 months)'],
        ];

        foreach ($types as $t) {
            DocumentType::updateOrCreate(
                ['code' => $t['code']],
                [
                    'name'       => $t['name'],
                    'category'   => 'kyc',
                    'applies_to' => 'business',
                    'is_active'  => true,
                ]
            );
        }
    }
}
