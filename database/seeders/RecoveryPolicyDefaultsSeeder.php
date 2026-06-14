<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class RecoveryPolicyDefaultsSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            'recovery.grace_period_days'       => 7,
            'recovery.auto_escalate'             => true,
            'recovery.auto_assign_call_center'   => true,
            'recovery.call_center_lead_days'     => 2,
            'recovery.sla_days.call_center'    => 7,
            'recovery.sla_days.debt_collector'   => 10,
            'recovery.sla_days.repossession'     => 14,
            'recovery.sla_days.auctioneer'       => 7,
            'recovery.sla_days.legal_partner'    => 21,
            'recovery.sla_days.gps_partner'      => 5,
            'recovery.commission_percent.call_center'  => 10,
            'recovery.commission_percent.debt_collector' => 15,
            'recovery.commission_percent.repossession'   => 12,
            'recovery.commission_percent.auctioneer'     => 8,
            'recovery.commission_percent.legal_partner'  => 10,
            'recovery.commission_percent.gps_partner'    => 5,
            'recovery.markup_percent.call_center'    => 3,
            'recovery.markup_percent.debt_collector'   => 3,
            'recovery.markup_percent.repossession'     => 4,
            'recovery.markup_percent.auctioneer'       => 2,
            'recovery.markup_percent.legal_partner'    => 5,
            'recovery.markup_percent.gps_partner'      => 2,
        ];

        foreach ($defaults as $key => $value) {
            if (Setting::get($key) === null) {
                Setting::set($key, $value);
            }
        }
    }
}
