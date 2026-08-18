<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class RecoveryPolicyDefaultsSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
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
            'recovery.commission_percent.legal_partner'  => 0,
            'recovery.commission_percent.gps_partner'    => 0,
            'recovery.markup_percent.call_center'    => 3,
            'recovery.markup_percent.debt_collector' => 3,
            'recovery.markup_percent.auctioneer'     => 2,
            'recovery.markup_percent.legal_partner'  => 0,
            'recovery.markup_percent.gps_partner'    => 0,
            'recovery.fee_type.call_center'    => 'percentage',
            'recovery.fee_type.debt_collector' => 'percentage',
            'recovery.fee_type.auctioneer'     => 'percentage',
            'recovery.fee_type.legal_partner'  => 'fixed',
            'recovery.fee_type.gps_partner'    => 'fixed',
            'recovery.fixed_amount.legal_partner' => 100_000,
            'recovery.charges_borrower.gps_partner' => false,
            'recovery.priority.call_center'    => 1,
            'recovery.priority.debt_collector' => 2,
            'recovery.priority.auctioneer'     => 3,
            'recovery.priority.legal_partner'  => 4,
            'recovery.priority.gps_partner'    => 5,
            'recovery.loan_types.call_center'    => 'all',
            'recovery.loan_types.debt_collector' => 'all',
            'recovery.loan_types.auctioneer'     => 'all',
            'recovery.loan_types.legal_partner'  => 'all',
            'recovery.loan_types.gps_partner'    => 'all',
            'recovery.collateral_scope.call_center'    => 'all',
            'recovery.collateral_scope.debt_collector' => 'all',
            'recovery.collateral_scope.auctioneer'     => 'secured',
            'recovery.collateral_scope.legal_partner'  => 'all',
            'recovery.collateral_scope.gps_partner'    => 'secured',
            'recovery.auto_escalate_type.call_center'    => true,
            'recovery.auto_escalate_type.debt_collector' => true,
            'recovery.auto_escalate_type.auctioneer'     => true,
            'recovery.auto_escalate_type.legal_partner'  => true,
            'recovery.auto_escalate_type.gps_partner'    => false,
        ];

        foreach ($defaults as $key => $value) {
            if (Setting::query()->where('key', $key)->exists()) {
                continue;
            }

            Setting::set($key, $value);
        }
    }
}
