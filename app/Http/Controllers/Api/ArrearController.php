<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ArrearCase;
use App\Models\CollectionAction;
use Illuminate\Http\Request;

class ArrearController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', ArrearCase::class);

        return response()->json(ArrearCase::with('loan')->latest()->paginate(20));
    }

    public function show(ArrearCase $arrearCase)
    {
        $this->authorize('view', $arrearCase);

        return response()->json($arrearCase->load(['loan', 'actions']));
    }

    public function update(Request $request, ArrearCase $arrearCase)
    {
        $this->authorize('update', $arrearCase);

        $data = $request->validate([
            'status' => ['sometimes', 'string', 'max:30'],
            'days_past_due' => ['sometimes', 'integer', 'min:0'],
            'amount_in_arrears' => ['sometimes', 'numeric', 'min:0'],
            'penalty_amount' => ['sometimes', 'numeric', 'min:0'],
            'assigned_to' => ['nullable', 'exists:users,id'],
        ]);

        $arrearCase->update($data);

        return response()->json($arrearCase->fresh());
    }

    public function addAction(Request $request, ArrearCase $arrearCase)
    {
        $this->authorize('addAction', $arrearCase);

        $data = $request->validate([
            'performed_by' => ['nullable', 'exists:users,id'],
            'action_type' => ['required', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
            'result' => ['nullable', 'string', 'max:100'],
        ]);

        $action = CollectionAction::create([
            ...$data,
            'arrear_case_id' => $arrearCase->id,
            'performed_at' => now(),
        ]);

        $arrearCase->update(['last_follow_up_at' => now()]);

        return response()->json($action, 201);
    }
}
