<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\User;
use Illuminate\View\View;

class CreditTeamController extends Controller
{
    public function index(): View
    {
        $underwriting = Department::query()->where('code', 'UND')->first();
        $committee = Department::query()->where('code', 'CRC')->first();

        $analysts = User::query()
            ->with(['department', 'branch'])
            ->where('is_active', true)
            ->where(function ($q) use ($underwriting) {
                $q->where('role', 'credit_analyst');
                if ($underwriting) {
                    $q->orWhere('department_id', $underwriting->id);
                }
            })
            ->orderBy('name')
            ->get();

        $committeeMembers = User::query()
            ->with(['department', 'branch'])
            ->where('is_active', true)
            ->where(function ($q) use ($committee) {
                $q->whereIn('role', ['credit_committee', 'manager']);
                if ($committee) {
                    $q->orWhere('department_id', $committee->id);
                }
            })
            ->orderBy('name')
            ->get();

        return view('admin.credit-team.index', [
            'underwriting' => $underwriting,
            'committee' => $committee,
            'analysts' => $analysts,
            'committeeMembers' => $committeeMembers,
        ]);
    }
}
