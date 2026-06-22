<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\LoanApplication;
use App\Services\GroupMemberReplacementService;
use Illuminate\Http\JsonResponse;

class GroupContractProgressController extends Controller
{
    public function borrower(LoanApplication $application, GroupMemberReplacementService $replacements): JsonResponse
    {
        $customer = auth()->user()?->customer
            ?? \App\Models\Customer::where('user_id', auth()->id())->first();

        abort_unless($customer, 403);
        abort_unless((int) $application->customer_id === (int) $customer->id, 403);

        $dashboard = $replacements->leaderDashboard($application, $customer);
        abort_unless($dashboard, 404);

        return response()->json([
            'ok'       => true,
            'progress' => $dashboard,
        ]);
    }
}
