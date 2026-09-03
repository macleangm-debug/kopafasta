<?php

return [
    'status_ramp_up' => 'New / Ramp-up',
    'status_excellent' => 'Excellent',
    'status_good' => 'Good standing',
    'status_needs_attention' => 'Needs attention',
    'status_at_risk' => 'At risk',
    'status_suspended' => 'Suspended',

    'kpi_on_time' => 'On-time completion',
    'kpi_completion' => 'Completion rate',
    'kpi_sla_breaches' => 'SLA breaches',
    'kpi_reassignments' => 'Reassignments',
    'kpi_completed' => 'Jobs / cases completed',
    'kpi_turnaround' => 'Average turnaround',

    'why_ramp_up' => 'Fewer than :min closed jobs, so a score is not assigned yet.',
    'why_on_time' => 'On-time completion is below the configured performance requirement (:actual% / target :target%).',
    'why_completion' => 'Completion rate is below the configured performance requirement (:actual% / target :target%).',
    'why_at_risk' => 'Escalation or failure rates forced At risk under Partner Performance Settings.',
    'why_excellent' => 'Score and on-time completion meet the Excellent threshold.',
    'why_good' => 'Performance meets the configured requirements.',
    'why_suspended_performance' => 'Automatically suspended after repeated at-risk performance reviews.',
    'why_suspended_other' => 'This account is restricted for a :kind reason, which is not reversed by KPI recovery.',

    'next_reassess' => 'Performance will be reassessed automatically on :date.',
    'next_recover' => 'If the recovery condition is met, performance standing is restored automatically on :date.',
    'next_suspended_manual' => 'This restriction is not reversed automatically. Partner support must review it.',

    'nudge_subject' => 'Performance is below the required standard',
    'nudge_body' => 'Hi :name, your performance standing is :status (score :score). Improve on-time completion before the next review. :remaining warning(s) remaining before a performance suspension.',
    'suspended_subject' => 'Account suspended for performance',
    'suspended_body' => 'Hi :name, your partner account was suspended after :reviews consecutive at-risk reviews. Historical earnings are preserved. Performance can be restored automatically when the recovery condition is met.',
    'recovered_subject' => 'Performance standing restored',
    'recovered_body' => 'Hi :name, your performance standing has been restored. You can receive work again subject to the usual eligibility checks.',

    'task_due_title' => 'Task due soon',
    'task_due_body' => ':hours hours remaining to complete this task. The SLA clock started at assignment.',
    'sla_breached_title' => 'SLA breached: :type #:id',
    'sla_breached_body' => ':partner missed the completion SLA. Grace may still apply before reassignment.',

    'recovery_reminder_title' => 'Recovery SLA reminder',
    'recovery_reminder_body' => 'This recovery case is due in :days day(s). SLA date: :sla.',

    'history_applied' => 'Applied',
    'history_approved' => 'Approved',
    'history_activated' => 'Activated',
    'history_terms' => 'Terms accepted',
    'history_membership' => 'Membership activated',
    'history_warning' => 'Performance warning issued',
    'history_suspended' => 'Suspended',
    'history_recovered' => 'Recovered / reactivated',
    'history_sla_breach' => 'SLA breached',
    'history_task_assigned' => 'Task assigned',
    'history_case_assigned' => 'Case assigned',

    'documents_application' => 'Application',
    'documents_partner' => 'Partner file',
    'documents_kyc' => 'Identity',

    'can_receive_yes' => 'Yes',
    'can_receive_no' => 'No',
    'why_this_status' => 'Why this status?',
    'next_system_action' => 'Next system action',
];
