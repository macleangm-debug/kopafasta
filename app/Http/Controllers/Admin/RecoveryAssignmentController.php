<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\AuditsActions;
use App\Http\Controllers\Controller;
use App\Models\CollectionAction;
use App\Models\RecoveryAssignment;
use App\Services\RecoveryAssignmentService;
use App\Services\RecoveryPolicyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class RecoveryAssignmentController extends Controller
{
    use AuditsActions;

    public function index(): View
    {
        $assignments = RecoveryAssignment::query()
            ->with(['vendor', 'arrearCase.loan.customer', 'assigner'])
            ->latest('assigned_at')
            ->paginate(25);

        $counts = [
            'open'       => RecoveryAssignment::whereIn('status', ['assigned', 'in_progress'])->count(),
            'completed'  => RecoveryAssignment::where('status', 'completed')->count(),
            'escalated'  => RecoveryAssignment::where('status', 'escalated')->count(),
            'sla_breach' => RecoveryAssignment::query()
                ->whereIn('status', ['assigned', 'in_progress'])
                ->where('sla_due_at', '<', now())
                ->count(),
        ];

        return view('admin.recovery.assignments.index', compact('assignments', 'counts'));
    }

    public function show(RecoveryAssignment $recoveryAssignment): View
    {
        $recoveryAssignment->load([
            'vendor.user',
            'arrearCase.loan.customer',
            'arrearCase.loan.product',
            'assigner',
            'vendorTask',
        ]);

        $activity = CollectionAction::query()
            ->where(function ($query) use ($recoveryAssignment): void {
                $query->where('recovery_assignment_id', $recoveryAssignment->id);
                if ($recoveryAssignment->arrear_case_id) {
                    $query->orWhere(function ($inner) use ($recoveryAssignment): void {
                        $inner->where('arrear_case_id', $recoveryAssignment->arrear_case_id)
                            ->whereIn('action_type', [
                                'partner_reminder',
                                'reminder_sent',
                                'recovery_partner_assigned',
                                'recovery_partner_completed',
                                'recovery_partner_escalated',
                                'recovery_partner_reassigned',
                            ]);
                    });
                }
            })
            ->with('performer')
            ->latest('performed_at')
            ->limit(12)
            ->get();

        $lastPartnerReminder = $activity->firstWhere('action_type', 'partner_reminder');
        $lastBorrowerReminder = $activity->firstWhere('action_type', 'reminder_sent');

        return view('admin.recovery.assignments.show', [
            'assignment' => $recoveryAssignment,
            'types'      => app(RecoveryPolicyService::class)->partnerTypes(),
            'activity'   => $activity,
            'lastPartnerReminder' => $lastPartnerReminder,
            'lastBorrowerReminder' => $lastBorrowerReminder,
        ]);
    }

    public function start(RecoveryAssignment $recoveryAssignment, RecoveryAssignmentService $service): RedirectResponse
    {
        $service->start($recoveryAssignment, request()->user());

        return back()->with('status', 'Recovery case marked in progress.');
    }

    public function complete(RecoveryAssignment $recoveryAssignment, RecoveryAssignmentService $service): RedirectResponse
    {
        $data = request()->validate([
            'outcome' => ['required', 'string', 'max:80'],
            'notes'   => ['nullable', 'string', 'max:2000'],
        ]);

        $service->complete($recoveryAssignment, request()->user(), $data['outcome'], $data['notes'] ?? null);

        return back()->with('status', 'Recovery case completed.');
    }

    public function escalate(RecoveryAssignment $recoveryAssignment, RecoveryAssignmentService $service): RedirectResponse
    {
        $data = request()->validate([
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $service->escalate($recoveryAssignment, request()->user(), $data['notes'] ?? null);

        return back()->with('status', 'Recovery case escalated.');
    }

    public function remindPartner(RecoveryAssignment $recoveryAssignment, RecoveryAssignmentService $service): RedirectResponse
    {
        $service->remindPartner($recoveryAssignment, request()->user());

        return back()->with('status', 'Reminder sent to the assigned partner.');
    }

    public function remindBorrower(RecoveryAssignment $recoveryAssignment, RecoveryAssignmentService $service): RedirectResponse
    {
        $service->remindBorrower($recoveryAssignment, request()->user());

        return back()->with('status', 'Payment reminder sent to the borrower.');
    }
}
