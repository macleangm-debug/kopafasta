<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\AuditsActions;
use App\Http\Controllers\Controller;
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

        return view('admin.recovery.assignments.show', [
            'assignment' => $recoveryAssignment,
            'types'      => app(RecoveryPolicyService::class)->partnerTypes(),
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
}
