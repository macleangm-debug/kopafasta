<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class RecoveryPolicyDefaultsSeeder extends Seeder
{
    public function run(): void
    {
        Setting::setMany([
            'recovery.grace_period_days'       => 2,
            'recovery.fee_base'                => 'principal',
            'recovery.auto_escalate'           => true,
            'recovery.auto_assign_call_center' => true,
            'recovery.call_center_lead_days'   => 0,
            'recovery.sla_days.call_center'    => 7,
            'recovery.sla_days.debt_collector' => 10,
            'recovery.sla_days.auctioneer'     => 11,
            'recovery.sla_days.legal_partner'  => 21,
            'recovery.sla_days.gps_partner'    => 5,
            'recovery.commission_percent.call_center'    => 10,
            'recovery.commission_percent.debt_collector' => 15,
            'recovery.commission_percent.auctioneer'     => 8,
            'recovery.commission_percent.legal_partner'  => 10,
            'recovery.commission_percent.gps_partner'    => 5,
            'recovery.markup_percent.call_center'    => 3,
            'recovery.markup_percent.debt_collector' => 3,
            'recovery.markup_percent.auctioneer'     => 2,
            'recovery.markup_percent.legal_partner'  => 5,
            'recovery.markup_percent.gps_partner'    => 2,
            'recovery.fee_type.call_center'    => 'percentage',
            'recovery.fee_type.debt_collector' => 'percentage',
            'recovery.fee_type.auctioneer'     => 'percentage',
            'recovery.fee_type.legal_partner'  => 'percentage',
            'recovery.fee_type.gps_partner'    => 'percentage',
        ]);
    }
}
