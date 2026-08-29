<?php

namespace App\Services;

use App\Models\Partner;
use App\Models\Vendor;

/**
 * Catalog of who can earn money from Kopafasta, and which shared wallet they use.
 * Capital partners keep their own earning formula; payout UX is the same Money/Wallet surface.
 */
class PartnerEarningsCatalogService
{
    /**
     * @return list<array<string, mixed>>
     */
    public function earningPartnerTypes(): array
    {
        return [
            [
                'partner_type' => 'valuer',
                'earning_trigger' => 'Valuation task completed / accepted',
                'becomes_withdrawable' => 'Valuation fee auto-approved on complete (promotePendingValuationFees)',
                'settings_source' => 'Recovery policy → service partner defaults / valuation fee schedule',
                'wallet' => 'shared',
                'source_type' => 'valuation_fee',
                'formula' => 'valuation_fee',
            ],
            [
                'partner_type' => 'recovery',
                'earning_trigger' => 'Eligible recovery / collection event',
                'becomes_withdrawable' => 'Commission rule → partner_payments approved',
                'settings_source' => 'Recovery policy → partner types / commission',
                'wallet' => 'shared',
                'source_type' => RecoveryCommissionWalletService::SOURCE_TYPE,
                'formula' => 'recovery_commission',
            ],
            [
                'partner_type' => 'affiliate',
                'earning_trigger' => 'Qualified referred fee / conversion',
                'becomes_withdrawable' => 'Affiliate commission posted to partner_payments',
                'settings_source' => 'Settings → Affiliates',
                'wallet' => 'shared',
                'source_type' => 'affiliate_commission',
                'formula' => 'affiliate_commission',
            ],
            [
                'partner_type' => 'insurance',
                'earning_trigger' => 'Insurance premium on a loan file',
                'becomes_withdrawable' => 'Posted as insurance_premium then approved per settlement rule',
                'settings_source' => 'Underwriting / Recovery insurance rates',
                'wallet' => 'shared',
                'source_type' => 'insurance_premium',
                'formula' => 'insurance_premium',
            ],
            [
                'partner_type' => 'gps_installer',
                'earning_trigger' => 'Completed GPS / vendor task',
                'becomes_withdrawable' => 'vendor_task settlement',
                'settings_source' => 'Partner defaults',
                'wallet' => 'shared',
                'source_type' => 'vendor_task',
                'formula' => 'vendor_task',
            ],
            [
                'partner_type' => 'towing',
                'earning_trigger' => 'Completed towing assignment',
                'becomes_withdrawable' => 'vendor_task / recovery assignment settlement',
                'settings_source' => 'Recovery policy',
                'wallet' => 'shared',
                'source_type' => 'vendor_task',
                'formula' => 'vendor_task',
            ],
            [
                'partner_type' => 'yard',
                'earning_trigger' => 'Yard / storage service event',
                'becomes_withdrawable' => 'vendor_task settlement',
                'settings_source' => 'Recovery policy',
                'wallet' => 'shared',
                'source_type' => 'vendor_task',
                'formula' => 'vendor_task',
            ],
            [
                'partner_type' => 'supplier',
                'earning_trigger' => 'Supplier invoice / marketplace fulfilment',
                'becomes_withdrawable' => 'vendor_task or supplier settlement',
                'settings_source' => 'Partner defaults',
                'wallet' => 'shared',
                'source_type' => 'vendor_task',
                'formula' => 'vendor_task',
            ],
            [
                'partner_type' => 'capital',
                'earning_trigger' => 'Capital / funding agreement settlement',
                'becomes_withdrawable' => 'CapitalWithdrawalRequest — not a valuer commission',
                'settings_source' => 'Capital partner agreement',
                'wallet' => 'capital_shared_payout_ux',
                'source_type' => 'lender_transaction',
                'formula' => 'capital_agreement',
            ],
        ];
    }

    /**
     * @return array{
     *   available: float,
     *   pending: float,
     *   in_progress: float,
     *   paid: float,
     *   source_type: string,
     *   withdrawals_enabled: bool
     * }
     */
    public function moneyView(Vendor|Partner $partner): array
    {
        $wallet = app(PartnerWalletService::class)->summary($partner);

        return [
            'available' => (float) ($wallet['available'] ?? 0),
            'pending' => (float) ($wallet['pending'] ?? 0),
            'in_progress' => (float) ($wallet['approved'] ?? 0),
            'paid' => (float) ($wallet['paid'] ?? 0),
            'source_type' => (string) ($wallet['source_type'] ?? ''),
            'withdrawals_enabled' => (float) ($wallet['available'] ?? 0) > 0
                || in_array((string) $partner->category, ['valuer', 'affiliate', 'insurance', 'recovery'], true)
                || app(RecoveryPartnerService::class)->isRecoveryPartner($partner),
        ];
    }
}
