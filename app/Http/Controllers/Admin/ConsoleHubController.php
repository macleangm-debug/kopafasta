<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\MembershipHistory;
use App\Models\PartnerTask;
use App\Models\RecoveryAssignment;
use App\Models\Repayment;
use Illuminate\View\View;

class ConsoleHubController extends Controller
{
    public function customers(): View
    {
        $counts = [
            'active' => Customer::query()->where('status', 'active')->count(),
            'total' => Customer::query()->count(),
            'suspended' => Customer::query()->where('status', 'suspended')->count(),
            'with_loans' => Customer::query()->whereHas('loans', fn ($q) => $q->whereIn('status', ['active', 'disbursed', 'arrears']))->count(),
        ];

        return view('admin.hubs.customers', compact('counts'));
    }

    public function payments(): View
    {
        $counts = [
            'pending_verify' => CustomerPayment::query()->whereIn('status', ['pending', 'processing', 'submitted'])->count(),
            'verified_today' => CustomerPayment::query()->where('status', 'verified')->whereDate('updated_at', today())->count(),
            'repayments_due' => Repayment::query()->whereIn('status', ['pending', 'due', 'overdue'])->count(),
            'membership_pending' => MembershipHistory::query()->pending()->count(),
            'missing_journal' => CustomerPayment::query()
                ->whereIn('status', ['verified', 'paid'])
                ->whereNull('journal_entry_id')
                ->count(),
        ];

        return view('admin.hubs.payments', compact('counts'));
    }

    public function fieldAssignments(): View
    {
        $openStatuses = ['assigned', 'in_progress', 'pending', 'accepted'];
        $taskType = fn (string $needle) => PartnerTask::query()
            ->whereIn('status', $openStatuses)
            ->where('task_type', 'like', '%'.$needle.'%');

        $counts = [
            'recovery_open' => RecoveryAssignment::query()->whereIn('status', ['assigned', 'in_progress'])->count(),
            'recovery_sla' => RecoveryAssignment::query()
                ->whereIn('status', ['assigned', 'in_progress'])
                ->where('sla_due_at', '<', now())
                ->count(),
            'valuer_open' => $taskType('valu')->count(),
            'gps_open' => $taskType('gps')->count(),
            'insurance_open' => $taskType('insur')->count(),
            'partner_tasks_open' => PartnerTask::query()->whereIn('status', $openStatuses)->count(),
        ];

        return view('admin.hubs.field-assignments', compact('counts'));
    }
}
