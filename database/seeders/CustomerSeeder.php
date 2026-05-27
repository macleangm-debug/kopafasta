<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Customer;
use Illuminate\Database\Seeder;

class CustomerSeeder extends Seeder
{
    public function run(): void
    {
        $branchIds = Branch::query()->pluck('id')->all();
        if (empty($branchIds)) {
            $branchIds = [null];
        }

        $samples = [
            ['Amina', 'Hassan', 'individual', 'salaried', 850000, 'F'],
            ['Juma', 'Mwakyusa', 'individual', 'self_employed', 1200000, 'M'],
            ['Neema', 'Mushi', 'individual', 'business_owner', 2400000, 'F'],
            ['Baraka', 'Kweka', 'business', 'business_owner', 5000000, 'M'],
            ['Asha', 'Salim', 'individual', 'farmer', 600000, 'F'],
            ['John', 'Mbwambo', 'individual', 'salaried', 1100000, 'M'],
            ['Fatuma', 'Said', 'individual', 'self_employed', 750000, 'F'],
            ['Peter', 'Mollel', 'business', 'business_owner', 3200000, 'M'],
            ['Grace', 'Lyimo', 'individual', 'salaried', 980000, 'F'],
            ['Hamisi', 'Juma', 'individual', 'farmer', 450000, 'M'],
            ['Zainab', 'Omar', 'individual', 'self_employed', 1350000, 'F'],
            ['Daudi', 'Massawe', 'group', 'business_owner', 2100000, 'M'],
        ];

        foreach ($samples as $i => [$first, $last, $type, $employment, $income, $gender]) {
            $businessName = $type !== 'individual'
                ? $first.' & Co. '.($type === 'group' ? 'Group' : 'Enterprises')
                : null;

            Customer::query()->updateOrCreate(
                ['customer_number' => 'CUS-'.str_pad((string) ($i + 1), 5, '0', STR_PAD_LEFT)],
                [
                    'branch_id' => $branchIds[$i % count($branchIds)],
                    'type' => $type,
                    'status' => 'active',
                    'first_name' => $first,
                    'last_name' => $last,
                    'email' => strtolower($first.'.'.$last).'@example.tz',
                    'phone' => '+25571'.str_pad((string) (1000000 + $i * 113), 7, '0', STR_PAD_LEFT),
                    'date_of_birth' => now()->subYears(25 + ($i % 25))->subMonths($i * 3)->toDateString(),
                    'national_id' => '199'.($i % 10).str_pad((string) (10000000 + $i * 7), 11, '0', STR_PAD_LEFT),
                    'address' => 'House '.($i + 1).', '.($i % 2 ? 'Kariakoo' : 'Sinza').', '.($i % 3 ? 'Dar es Salaam' : 'Arusha'),
                    'employment_type' => $employment,
                    'business_name' => $businessName,
                    'monthly_income' => $income,
                    'onboarded_at' => now()->subDays(30 + $i * 5)->toDateString(),
                ]
            );
        }
    }
}
