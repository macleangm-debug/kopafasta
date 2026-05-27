<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CustomerController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', Customer::class);

        return response()->json(Customer::with('kyc')->latest()->paginate(20));
    }

    public function store(Request $request)
    {
        $this->authorize('create', Customer::class);

        $data = $request->validate([
            'user_id' => ['nullable', 'exists:users,id'],
            'branch_id' => ['required', 'exists:branches,id'],
            'type' => ['sometimes', 'string', 'max:30'],
            'status' => ['sometimes', 'string', 'max:30'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['nullable', 'email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'date_of_birth' => ['nullable', 'date'],
            'national_id' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string'],
            'employment_type' => ['nullable', 'string', 'max:100'],
            'business_name' => ['nullable', 'string', 'max:255'],
            'monthly_income' => ['nullable', 'numeric', 'min:0'],
        ]);

        $data['customer_number'] = 'CUST-'.strtoupper(Str::random(8));
        $data['onboarded_at'] = now()->toDateString();

        return response()->json(Customer::create($data), 201);
    }

    public function show(Customer $customer)
    {
        $this->authorize('view', $customer);

        return response()->json($customer->load(['kyc', 'documents', 'applications', 'loans']));
    }

    public function update(Request $request, Customer $customer)
    {
        $this->authorize('update', $customer);

        $data = $request->validate([
            'branch_id' => ['nullable', 'exists:branches,id'],
            'type' => ['sometimes', 'string', 'max:30'],
            'status' => ['sometimes', 'string', 'max:30'],
            'first_name' => ['sometimes', 'string', 'max:100'],
            'last_name' => ['sometimes', 'string', 'max:100'],
            'email' => ['nullable', 'email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'date_of_birth' => ['nullable', 'date'],
            'national_id' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string'],
            'employment_type' => ['nullable', 'string', 'max:100'],
            'business_name' => ['nullable', 'string', 'max:255'],
            'monthly_income' => ['nullable', 'numeric', 'min:0'],
        ]);

        $customer->update($data);

        return response()->json($customer->fresh());
    }

    public function destroy(Customer $customer)
    {
        $this->authorize('delete', $customer);

        $customer->delete();

        return response()->json(status: 204);
    }
}
