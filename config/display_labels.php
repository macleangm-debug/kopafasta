<?php

/**
 * Human-readable labels for stored codes (enums, status values, etc.).
 * Use via display_label($code, $group) or DisplayLabelService.
 */
return [
    'groups' => [
        'role' => [], // resolved via RoleService + roles.name

        'application_status' => [
            'draft'               => 'Draft',
            'awaiting_guarantor'  => 'Submitted – Awaiting Guarantor Completion',
            'submitted'           => 'Submitted',
            'pending'             => 'Pending',
            'pending_documents'   => 'Pending documents',
            'under_review'        => 'Under review',
            'pre_approved'        => 'Pre-approved',
            'approved'            => 'Approved',
            'rejected'            => 'Rejected',
            'cancelled'           => 'Cancelled',
            'disbursed'           => 'Disbursed',
            'in_progress'         => 'In progress',
        ],

        'application_stage' => [], // resolved via LoanApplicationWorkflowService

        'loan_status' => [
            'pending'      => 'Pending',
            'approved'     => 'Approved',
            'active'       => 'Active',
            'disbursed'    => 'Disbursed',
            'closed'       => 'Closed',
            'written_off'  => 'Written off',
            'restructured' => 'Restructured',
            'restructuring'=> 'Restructuring',
            'defaulted'    => 'Defaulted',
            'cancelled'    => 'Cancelled',
        ],

        'record_status' => [
            'pending'      => 'Pending',
            'open'         => 'Open',
            'closed'       => 'Closed',
            'active'       => 'Active',
            'inactive'     => 'Inactive',
            'suspended'    => 'Suspended',
            'approved'     => 'Approved',
            'rejected'     => 'Rejected',
            'cancelled'    => 'Cancelled',
            'completed'    => 'Completed',
            'failed'       => 'Failed',
            'received'     => 'Received',
            'investigating'=> 'Investigating',
            'resolved'     => 'Resolved',
            'escalated'    => 'Escalated',
            'in_progress'  => 'In progress',
            'in_review'    => 'In review',
            'assigned'     => 'Assigned',
            'allocated'    => 'Allocated',
            'reversed'     => 'Reversed',
            'recorded'     => 'Recorded',
            'paid'         => 'Paid',
            'reconciled'   => 'Reconciled',
            'disputed'     => 'Disputed',
            'deployed'     => 'Deployed',
            'matured'      => 'Matured',
            'balanced'     => 'Balanced',
            'variance'     => 'Variance',
        ],

        'channel' => [
            'bank_transfer'  => 'Bank transfer',
            'mobile_money'   => 'Mobile money',
            'cash'           => 'Cash',
            'cheque'         => 'Cheque',
            'check'          => 'Cheque',
            'standing_order' => 'Standing order',
            'wallet'         => 'Wallet',
            'bank'           => 'Bank',
            'sms'            => 'SMS',
            'email'          => 'Email',
            'push'           => 'Push notification',
            'whatsapp'       => 'WhatsApp',
        ],

        'payment_method' => [
            'cash'           => 'Cash',
            'bank_transfer'  => 'Bank transfer',
            'mobile_money'   => 'Mobile money',
            'cheque'         => 'Cheque',
            'card'           => 'Card',
        ],

        'mobile_provider' => [
            'm_pesa'       => 'M-Pesa',
            'tigo_pesa'    => 'Tigo Pesa',
            'airtel_money' => 'Airtel Money',
            'halopesa'     => 'Halopesa',
            'other'        => 'Other',
        ],

        'vendor_category' => [
            'gps_installer'       => 'GPS installer',
            'insurance'           => 'Insurance provider',
            'valuer'              => 'Valuer',
            'lawyer'              => 'Lawyer',
            'auctioneer'          => 'Auctioneer',
            'dealer'              => 'Dealer',
            'general'             => 'General vendor',
        ],

        'vendor_task_type' => [
            'gps_installation'    => 'GPS installation',
            'gps_removal'         => 'GPS removal',
            'insurance_policy'    => 'Insurance policy',
            'valuation'           => 'Valuation',
            'repossession'        => 'Repossession',
            'legal_notice'        => 'Legal notice',
        ],

        'vendor_task_status' => [
            'pending'    => 'Pending',
            'assigned'   => 'Assigned',
            'in_progress'=> 'In progress',
            'completed'  => 'Completed',
            'cancelled'  => 'Cancelled',
        ],

        'kyc_status' => [
            'pending'   => 'Pending',
            'submitted' => 'Submitted',
            'verified'  => 'Verified',
            'rejected'  => 'Rejected',
            'expired'   => 'Expired',
        ],

        'face_verification_status' => [
            'none'     => 'Not started',
            'pending'  => 'Pending review',
            'verified' => 'Verified',
            'rejected' => 'Rejected',
            'failed'   => 'Failed',
            'skipped'  => 'Skipped',
        ],

        'nida_verification_status' => [
            'unverified'     => 'Not verified',
            'verified'       => 'Verified',
            'name_mismatch'  => 'Name mismatch',
            'multihit'       => 'Multiple matches',
            'failed'         => 'Failed',
        ],

        'activity_type' => [
            'business_owner'     => 'Business owner',
            'farmer'             => 'Farmer',
            'artisan'            => 'Artisan',
            'trader'             => 'Trader',
            'employed'           => 'Employed',
            'student'            => 'Student',
            'casual_worker'      => 'Casual worker',
            'transport_operator' => 'Transport operator',
            'freelancer'         => 'Freelancer',
            'unemployed'         => 'Unemployed',
        ],

        'document_status' => [
            'pending'         => 'Pending',
            'pending_review'  => 'Pending review',
            'approved'        => 'Approved',
            'verified'        => 'Verified',
            'rejected'        => 'Rejected',
        ],

        'ticket_status' => [
            'open'         => 'Open',
            'in_progress'  => 'In progress',
            'resolved'     => 'Resolved',
            'closed'       => 'Closed',
        ],

        'complaint_status' => [
            'open'         => 'Open',
            'investigating'=> 'Investigating',
            'resolved'     => 'Resolved',
            'closed'       => 'Closed',
        ],

        'aml_rule_type' => [
            'large_txn'              => 'Large transaction',
            'velocity'               => 'Velocity',
            'structuring'            => 'Structuring',
            'repeated_early_settle'  => 'Repeated early settlement',
            'multi_account'          => 'Multi-account',
            'geo'                    => 'Geographic',
            'pattern'                => 'Pattern',
        ],

        'charge_type' => [
            'origination'       => 'Origination',
            'processing'        => 'Processing',
            'late_fee'          => 'Late fee',
            'penalty'           => 'Penalty',
            'insurance'         => 'Insurance',
            'gps'               => 'GPS',
            'valuation'         => 'Valuation',
            'restructure'       => 'Restructure',
            'early_settlement'  => 'Early settlement',
            'other'             => 'Other',
        ],

        'charge_basis' => [
            'fixed'            => 'Fixed amount',
            'percentage'       => 'Percentage',
            'per_day'          => 'Per day',
            'per_installment'  => 'Per installment',
        ],

        'pep_category' => [
            'domestic'             => 'Domestic PEP',
            'foreign'              => 'Foreign PEP',
            'international_org'    => 'International organisation',
            'family'               => 'Family member',
            'associate'            => 'Close associate',
        ],

        'product_category' => [
            'individual'   => 'Individual loan',
            'group'        => 'Group loan',
            'asset_backed' => 'Asset-backed',
            'emergency'    => 'Emergency',
            'salary'       => 'Salary advance',
        ],

        'approval_action' => [
            'loan_approve'    => 'Loan approval',
            'loan_disburse'   => 'Loan disbursement',
            'write_off'       => 'Write-off',
            'restructure'     => 'Restructure',
            'fee_waiver'      => 'Fee waiver',
            'manual_payment'  => 'Manual payment',
        ],

        'account_type' => [
            'asset'     => 'Asset',
            'liability' => 'Liability',
            'equity'    => 'Equity',
            'income'    => 'Income',
            'expense'   => 'Expense',
        ],

        'risk_level' => [
            'low'      => 'Low',
            'medium'   => 'Medium',
            'high'     => 'High',
            'extreme'  => 'Extreme',
            'critical' => 'Critical',
        ],

        'suspicious_activity_status' => [
            'open'           => 'Open',
            'investigating'  => 'Investigating',
            'cleared'        => 'Cleared',
            'reported'       => 'Reported',
            'closed'         => 'Closed',
        ],
    ],
];
