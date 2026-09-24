<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\DuplicateMemberResolutionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DuplicateMemberController extends Controller
{
    public function store(Request $request, Customer $customer, DuplicateMemberResolutionService $resolver): RedirectResponse
    {
        $this->authorize('delete', $customer);

        $data = $request->validate([
            'canonical_customer_id' => ['required', 'integer', 'exists:customers,id'],
            'reason' => ['required', 'string', 'max:180'],
        ]);

        $canonical = Customer::query()->findOrFail((int) $data['canonical_customer_id']);
        $this->authorize('update', $canonical);

        $impact = $resolver->impact($customer);
        $resolved = $resolver->resolve($customer, $canonical, $request->user(), $data['reason']);

        return redirect()
            ->route('admin.customers.show', $canonical)
            ->with('status', ($impact['action'] === 'delete_empty'
                ? 'Empty duplicate account retired into '
                : 'Duplicate history merged into ')
                .($canonical->customer_number ?: '#'.$canonical->id)
                .'. Previous member '.$resolved->customer_number.' stays on file as Merged.');
    }
}
