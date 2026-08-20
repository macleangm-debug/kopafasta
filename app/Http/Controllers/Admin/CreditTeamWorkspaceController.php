<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\User;
use App\Models\Vendor;
use App\Services\CapacityAutoRejectService;
use App\Services\PartnerCoverageRequestService;
use App\Services\PartnerStaffService;
use Illuminate\View\View;

class CreditTeamWorkspaceController extends Controller
{
    public function screening(): View
    {
        abort_unless(auth()->user()?->hasPermission('applications.view'), 403);

        $counts = [
            'screening' => LoanApplication::query()->where('current_stage', 'screening')->count(),
            'appraisal' => LoanApplication::query()->where('current_stage', 'credit_appraisal')->count(),
            'mine' => LoanApplication::query()
                ->whereIn('current_stage', ['screening', 'credit_appraisal'])
                ->where('assigned_analyst_id', auth()->id())
                ->count(),
        ];

        return view('admin.teams.screening', compact('counts'));
    }

    public function committee(): View
    {
        abort_unless(auth()->user()?->hasPermission('applications.view'), 403);

        $counts = [
            'pre_approval' => LoanApplication::query()->where('current_stage', 'pre_approval')->count(),
            'awaiting_decision' => LoanApplication::query()
                ->where('current_stage', 'pre_approval')
                ->whereNotNull('recommendation_type')
                ->count(),
            'system_sorted' => LoanApplication::query()
                ->whereIn('current_stage', ['submitted', 'screening', 'credit_appraisal'])
                ->where('screening_payload->capacity_auto_reject->status', CapacityAutoRejectService::STATUS_PENDING)
                ->count(),
        ];

        return view('admin.teams.committee', compact('counts'));
    }

    public function management(): View
    {
        abort_unless(auth()->user()?->hasPermission('applications.view') || auth()->user()?->hasPermission('loans.view'), 403);

        $counts = [
            'approved' => LoanApplication::query()->where('current_stage', 'approval')->count(),
            'disbursement_stage' => LoanApplication::query()->where('current_stage', 'disbursement')->count(),
            'pending_loans' => Loan::query()->where('status', 'pending')->count(),
        ];

        return view('admin.teams.management', compact('counts'));
    }

    public function partners(): View
    {
        abort_unless(auth()->user()?->can('create', Vendor::class), 403, app(PartnerStaffService::class)->policyMessage('open Partner support'));

        $staff = app(PartnerStaffService::class);
        $prtId = $staff->departmentId();
        $members = User::query()
            ->where('is_active', true)
            ->where(function ($q) use ($prtId): void {
                $q->where('role', PartnerStaffService::ROLE);
                if ($prtId) {
                    $q->orWhere('department_id', $prtId)
                        ->orWhereHas('departments', fn ($d) => $d->where('id', $prtId));
                }
            })
            ->orderBy('name')
            ->get();

        $coverage = app(PartnerCoverageRequestService::class);
        $coverageAlerts = $coverage->staffAlerts();
        $counts = [
            'gaps' => $coverageAlerts->count(),
            'partners' => Vendor::query()->where('status', 'active')->count(),
        ];

        return view('admin.teams.partners', compact('counts', 'members', 'coverageAlerts'));
    }
}
