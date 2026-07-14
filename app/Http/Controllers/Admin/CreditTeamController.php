<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Department;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CreditTeamController extends Controller
{
    public function index(): View
    {
        abort_unless(auth()->user()?->hasPermission('applications.view'), 403);

        $underwriting = Department::query()->where('code', 'UND')->first();
        $committee = Department::query()->where('code', 'CRC')->first();

        $analysts = User::query()
            ->with(['department', 'branch'])
            ->where('is_active', true)
            ->where(function ($q) use ($underwriting) {
                $q->where('role', 'credit_analyst');
                if ($underwriting) {
                    $q->orWhere(function ($inner) use ($underwriting) {
                        $inner->where('department_id', $underwriting->id)
                            ->whereIn('role', ['credit_analyst', 'manager', 'admin']);
                    });
                }
            })
            ->orderBy('name')
            ->get();

        $committeeMembers = User::query()
            ->with(['department', 'branch'])
            ->where('is_active', true)
            ->where(function ($q) use ($committee) {
                $q->where('role', 'credit_committee');
                if ($committee) {
                    $q->orWhere(function ($inner) use ($committee) {
                        $inner->where('department_id', $committee->id)
                            ->whereIn('role', ['credit_committee', 'manager', 'admin']);
                    });
                }
            })
            ->orderBy('name')
            ->get();

        return view('admin.credit-team.index', [
            'underwriting' => $underwriting,
            'committee' => $committee,
            'analysts' => $analysts,
            'committeeMembers' => $committeeMembers,
            'branches' => Branch::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()?->hasPermission('users.manage'), 403);

        $team = $request->validate([
            'team' => ['required', Rule::in(['analyst', 'committee'])],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:40'],
            'password' => ['required', 'string', 'min:6', 'max:100'],
            'branch_id' => ['nullable', 'exists:branches,id'],
        ]);

        $underwriting = Department::query()->where('code', 'UND')->first();
        $committee = Department::query()->where('code', 'CRC')->first();

        $isCommittee = $team['team'] === 'committee';
        $department = $isCommittee ? $committee : $underwriting;

        User::query()->create([
            'name' => $team['name'],
            'email' => $team['email'],
            'phone' => $team['phone'] ?? null,
            'password' => $team['password'],
            'role' => $isCommittee ? 'credit_committee' : 'credit_analyst',
            'department_id' => $department?->id,
            'branch_id' => $team['branch_id'] ?? null,
            'is_active' => true,
        ]);

        return redirect()
            ->route('admin.credit-team.index')
            ->with('status', $isCommittee
                ? 'Credit committee member added.'
                : 'Credit analyst added to underwriting.');
    }
}
