<?php

namespace Database\Seeders;

use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\Vendor;
use App\Models\VendorTask;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class VendorSeeder extends Seeder
{
    public function run(): void
    {
        $vendors = [
            ['name' => 'TrackPro Installers',     'category' => 'gps_installer', 'status' => 'active',   'phone' => '+255712000001', 'email' => 'ops@trackpro.co.tz'],
            ['name' => 'SafariFleet GPS',        'category' => 'gps_installer', 'status' => 'active',   'phone' => '+255712000002', 'email' => 'hello@safarifleet.co.tz'],
            ['name' => 'EagleEye Tracking',      'category' => 'gps_installer', 'status' => 'inactive', 'phone' => '+255712000003', 'email' => 'apply@eagleeye.tz'],
            ['name' => 'Sanlam General TZ',      'category' => 'insurance',     'status' => 'active',   'phone' => '+255712000010', 'email' => 'claims@sanlam.co.tz'],
            ['name' => 'Heritage Insurance',     'category' => 'insurance',     'status' => 'active',   'phone' => '+255712000011', 'email' => 'corp@heritage.co.tz'],
            ['name' => 'Jubilee Allianz',        'category' => 'insurance',     'status' => 'inactive', 'phone' => '+255712000012', 'email' => 'biz@jubilee.tz'],
            ['name' => 'Copper Fasta Valuer',      'category' => 'valuer',        'status' => 'active',   'phone' => '+255712000023', 'email' => 'valuer@copperfasta.test', 'regions' => ['Dar es Salaam', 'Pwani']],
            ['name' => 'Apex Valuers Ltd',       'category' => 'valuer',        'status' => 'active',   'phone' => '+255712000020', 'email' => 'reports@apex.tz'],
            ['name' => 'NovaCert Valuations',    'category' => 'valuer',        'status' => 'active',   'phone' => '+255712000021', 'email' => 'team@novacert.tz'],
            ['name' => 'PrimeAsset Valuers',     'category' => 'valuer',        'status' => 'inactive', 'phone' => '+255712000022', 'email' => 'info@primeasset.tz'],
            ['name' => 'Kilimanjaro Towing',     'category' => 'towing',        'status' => 'active',   'phone' => '+255712000030', 'email' => 'dispatch@kilitow.tz'],
            ['name' => 'CentralYard Mwenge',     'category' => 'yard',          'status' => 'active',   'phone' => '+255712000040', 'email' => 'yard@centralyard.tz'],
            ['name' => 'BidWell Auctioneers',    'category' => 'auctioneer',    'status' => 'suspended','phone' => '+255712000050', 'email' => 'sales@bidwell.tz'],
        ];

        foreach ($vendors as $i => $data) {
            Vendor::query()->updateOrCreate(
                ['name' => $data['name']],
                array_merge($data, [
                    'vendor_number' => 'PTR-DEMO-'.str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT),
                    'address'       => 'Dar es Salaam, Tanzania',
                ])
            );
        }

        // Tasks: assign a few demo tasks to active vendors
        $gps   = Vendor::query()->where('category', 'gps_installer')->where('status', 'active')->get();
        $val   = Vendor::query()->where('category', 'valuer')->where('status', 'active')->get();
        $apps  = LoanApplication::query()->limit(4)->get();
        $loans = Loan::query()->limit(4)->get();

        if ($gps->isNotEmpty()) {
            foreach ($apps as $idx => $app) {
                $vendor = $gps[$idx % $gps->count()];
                VendorTask::query()->updateOrCreate(
                    ['vendor_id' => $vendor->id, 'loan_application_id' => $app->id, 'task_type' => 'gps_install'],
                    [
                        'status'  => ['assigned', 'in_progress', 'completed', 'failed'][$idx % 4],
                        'due_at'  => now()->addDays(3 + $idx),
                        'completed_at' => $idx % 4 === 2 ? now()->subDay() : null,
                        'notes'   => 'Install GPS tracker on collateral vehicle',
                    ]
                );
            }
        }

        if ($val->isNotEmpty()) {
            foreach ($loans as $idx => $loan) {
                $vendor = $val[$idx % $val->count()];
                VendorTask::query()->updateOrCreate(
                    ['vendor_id' => $vendor->id, 'loan_id' => $loan->id, 'task_type' => 'asset_valuation'],
                    [
                        'status'  => ['assigned', 'completed', 'in_progress', 'cancelled'][$idx % 4],
                        'due_at'  => now()->addDays(5 + $idx),
                        'completed_at' => $idx % 4 === 1 ? now()->subDays(2) : null,
                        'notes'   => 'Independent asset valuation report',
                    ]
                );
            }
        }
    }
}
