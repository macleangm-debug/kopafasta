<?php

namespace Database\Seeders;

use App\Models\LoanProduct;
use App\Models\LoanProductRequirement;
use Illuminate\Database\Seeder;

class LoanProductRequirementSeeder extends Seeder
{
    /**
     * Map product code prefixes / categories to required documents.
     * Every product gets the baseline KYC docs; specific products add more.
     */
    public function run(): void
    {
        $baseline = [
            ['name' => 'National ID (front)',  'description' => 'Clear photo of the front side of your ID.'],
            ['name' => 'National ID (back)',   'description' => 'Clear photo of the back side of your ID.'],
            ['name' => 'Selfie with ID',       'description' => 'Selfie holding your National ID next to your face.'],
            ['name' => 'Passport photo',       'description' => 'Recent passport-size photo, plain background.'],
        ];

        // Code-prefix or substring -> extra requirements
        $rules = [
            'BIZ' => [
                ['name' => 'Business licence',          'description' => 'Valid TRA / local government business licence.'],
                ['name' => '3 months bank statement',   'description' => 'Most recent 3 months of bank or mobile-money statement.'],
                ['name' => 'Photo of business',         'description' => 'Photo showing your shop / business premises.'],
            ],
            'BZ' => [
                ['name' => 'Business licence',          'description' => 'Valid TRA / local government business licence.'],
                ['name' => '3 months bank statement',   'description' => 'Most recent 3 months of bank or mobile-money statement.'],
                ['name' => 'Photo of business',         'description' => 'Photo showing your shop / business premises.'],
            ],
            'AC'  => [ // Fundi Capital
                ['name' => 'Fundi work proof',          'description' => 'Photo of tools/work site or a customer reference.'],
            ],
            'SAL' => [
                ['name' => 'Latest 3 payslips',         'description' => 'Most recent 3 monthly payslips.'],
                ['name' => 'Employer letter',           'description' => 'Letter from your employer confirming employment.'],
                ['name' => '3 months bank statement',   'description' => 'Salary account statement for the last 3 months.'],
            ],
            'AGR' => [
                ['name' => 'Farm location proof',       'description' => 'Photo of farm or land-use letter from local leader.'],
                ['name' => 'Buyer / off-taker letter',  'description' => 'Optional: letter from co-op or buyer.', 'required' => false],
            ],
            'AG'  => [
                ['name' => 'Farm location proof',       'description' => 'Photo of farm or land-use letter from local leader.'],
            ],
            'AST' => [
                ['name' => 'Pro-forma invoice',         'description' => 'Pro-forma invoice for the asset you want to buy.'],
                ['name' => 'Supplier details',          'description' => 'Supplier name, address & contact.'],
            ],
            'AB'  => [
                ['name' => 'Collateral ownership doc',  'description' => 'Logbook / title deed of the asset offered as collateral.'],
                ['name' => 'Asset photos',              'description' => 'Photos of the asset offered as collateral.'],
            ],
            'AL'  => [
                ['name' => 'Collateral ownership doc',  'description' => 'Logbook / title deed of the asset offered as collateral.'],
            ],
            'EMG' => [
                ['name' => 'Reason / supporting doc',   'description' => 'Hospital bill, fee letter, etc. (any one).', 'required' => false],
            ],
            'EM'  => [
                ['name' => 'Reason / supporting doc',   'description' => 'Hospital bill, fee letter, etc. (any one).', 'required' => false],
            ],
            'ED'  => [
                ['name' => 'School / college fee letter','description' => 'Official fee structure or admission letter.'],
            ],
            'GL'  => [
                ['name' => 'Group constitution',        'description' => 'Group bylaws / constitution document.'],
                ['name' => 'Group member roster',       'description' => 'List of all group members with IDs.'],
            ],
            'IL'  => [
                ['name' => 'Source of income proof',    'description' => 'Any document showing how you earn money.'],
            ],
            'WM'  => [
                ['name' => 'Source of income proof',    'description' => 'Any document showing how you earn money.'],
            ],
        ];

        LoanProduct::query()->get()->each(function (LoanProduct $product) use ($baseline, $rules) {
            $set = $baseline;

            foreach ($rules as $prefix => $extras) {
                if (str_starts_with(strtoupper($product->code), $prefix)) {
                    $set = array_merge($set, $extras);
                }
            }

            foreach ($set as $row) {
                LoanProductRequirement::updateOrCreate(
                    [
                        'loan_product_id' => $product->id,
                        'name'            => $row['name'],
                    ],
                    [
                        'type'        => 'document',
                        'description' => $row['description'] ?? null,
                        'is_required' => $row['required'] ?? true,
                    ]
                );
            }
        });
    }
}
