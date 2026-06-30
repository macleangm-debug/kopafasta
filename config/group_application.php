<?php

return [
    /**
     * Group application status keys (leader/admin-facing).
     * Computed by GroupApplicationStatusService.
     */
    'statuses' => [
        'draft' => [
            'label' => 'Draft',
            'tone'  => 'gray',
        ],
        'inviting_members' => [
            'label' => 'Inviting members',
            'tone'  => 'amber',
        ],
        'member_completion' => [
            'label' => 'Member completion',
            'tone'  => 'amber',
        ],
        'ready_for_submission' => [
            'label' => 'Ready for submission',
            'tone'  => 'emerald',
        ],
        'under_review' => [
            'label' => 'Under review',
            'tone'  => 'blue',
        ],
        'approved' => [
            'label' => 'Approved',
            'tone'  => 'emerald',
        ],
        'rejected' => [
            'label' => 'Rejected',
            'tone'  => 'red',
        ],
        'disbursed' => [
            'label' => 'Disbursed',
            'tone'  => 'emerald',
        ],
        'cancelled' => [
            'label' => 'Cancelled',
            'tone'  => 'gray',
        ],
    ],

    /** Weights for composite group risk score (must sum to 100). Higher score = lower risk. */
    'scoring_weights' => [
        'completion' => 35,
        'credit'     => 40,
        'income'     => 25,
    ],

    /** CRB score normalization range. */
    'crb_score_min' => 300,
    'crb_score_max' => 850,

    /**
     * Income tiers for scoring normalization (TZS monthly midpoint).
     * Score contribution scales linearly between min and max tier midpoint.
     */
    'income_score_min' => 200_000,
    'income_score_max' => 5_000_000,

    /** Risk band thresholds (group_risk_score 0–100). */
    'risk_bands' => [
        'low'    => 70,
        'medium' => 45,
    ],
];
