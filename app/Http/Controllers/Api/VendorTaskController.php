<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\VendorTask;
use Illuminate\Http\Request;

class VendorTaskController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', VendorTask::class);

        return response()->json(VendorTask::with(['vendor', 'loan', 'loanApplication'])->latest()->paginate(20));
    }

    public function store(Request $request)
    {
        $this->authorize('create', VendorTask::class);

        $data = $request->validate([
            'vendor_id' => ['required', 'exists:partners,id'],
            'loan_application_id' => ['nullable', 'exists:loan_applications,id'],
            'loan_id' => ['nullable', 'exists:loans,id'],
            'task_type' => ['required', 'string', 'max:100'],
            'status' => ['sometimes', 'string', 'max:30'],
            'due_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'assigned_by' => ['nullable', 'exists:users,id'],
        ]);

        return response()->json(VendorTask::create($data), 201);
    }

    public function show(VendorTask $vendorTask)
    {
        $this->authorize('view', $vendorTask);

        return response()->json($vendorTask->load(['vendor', 'loan', 'loanApplication']));
    }

    public function update(Request $request, VendorTask $vendorTask)
    {
        $this->authorize('update', $vendorTask);

        $data = $request->validate([
            'status' => ['sometimes', 'string', 'max:30'],
            'due_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'proof_path' => ['nullable', 'string'],
        ]);

        $vendorTask->update($data);

        return response()->json($vendorTask->fresh());
    }

    public function destroy(VendorTask $vendorTask)
    {
        $this->authorize('delete', $vendorTask);

        $vendorTask->delete();

        return response()->json(status: 204);
    }

    public function complete(Request $request, VendorTask $vendorTask)
    {
        $this->authorize('complete', $vendorTask);

        $data = $request->validate([
            'proof_path' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ]);

        $vendorTask->update([
            'status' => 'completed',
            'proof_path' => $data['proof_path'] ?? $vendorTask->proof_path,
            'notes' => $data['notes'] ?? $vendorTask->notes,
            'completed_at' => now(),
        ]);

        app(\App\Services\PartnerTaskLifecycleService::class)->closeLinkedValuation(
            $vendorTask->fresh(),
            'Aligned with the completed partner task.',
        );

        return response()->json($vendorTask->fresh());
    }
}
