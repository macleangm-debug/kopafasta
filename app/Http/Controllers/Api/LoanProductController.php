<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LoanProduct;
use Illuminate\Http\Request;

class LoanProductController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', LoanProduct::class);

        return response()->json(LoanProduct::query()->latest()->paginate(20));
    }

    public function store(Request $request)
    {
        $this->authorize('create', LoanProduct::class);

        $data = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:loan_products,code'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'category' => ['required', 'string', 'max:100'],
            'interest_rate' => ['required', 'numeric', 'min:0'],
            'tenure_min_months' => ['required', 'integer', 'min:1'],
            'tenure_max_months' => ['required', 'integer', 'gte:tenure_min_months'],
            'min_amount' => ['required', 'numeric', 'min:0'],
            'max_amount' => ['required', 'numeric', 'gte:min_amount'],
            'requires_collateral' => ['sometimes', 'boolean'],
            'requires_guarantor' => ['sometimes', 'boolean'],
            'collateral_rules' => ['nullable', 'array'],
            'approval_workflow_id' => ['nullable', 'exists:approval_workflows,id'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        return response()->json(LoanProduct::create($data), 201);
    }

    public function show(LoanProduct $loanProduct)
    {
        $this->authorize('view', $loanProduct);

        return response()->json($loanProduct->load('requirements'));
    }

    public function update(Request $request, LoanProduct $loanProduct)
    {
        $this->authorize('update', $loanProduct);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'category' => ['sometimes', 'string', 'max:100'],
            'interest_rate' => ['sometimes', 'numeric', 'min:0'],
            'tenure_min_months' => ['sometimes', 'integer', 'min:1'],
            'tenure_max_months' => ['sometimes', 'integer'],
            'min_amount' => ['sometimes', 'numeric', 'min:0'],
            'max_amount' => ['sometimes', 'numeric'],
            'requires_collateral' => ['sometimes', 'boolean'],
            'requires_guarantor' => ['sometimes', 'boolean'],
            'collateral_rules' => ['nullable', 'array'],
            'approval_workflow_id' => ['nullable', 'exists:approval_workflows,id'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $loanProduct->update($data);

        return response()->json($loanProduct->fresh());
    }

    public function destroy(LoanProduct $loanProduct)
    {
        $this->authorize('delete', $loanProduct);

        $loanProduct->delete();

        return response()->json(status: 204);
    }
}
