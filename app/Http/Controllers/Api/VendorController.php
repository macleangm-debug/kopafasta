<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Vendor;
use App\Services\PartnerCodeService;
use Illuminate\Http\Request;

class VendorController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', Vendor::class);

        return response()->json(Vendor::query()->latest()->paginate(20));
    }

    public function store(Request $request)
    {
        $this->authorize('create', Vendor::class);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email'],
            'address' => ['nullable', 'string'],
            'status' => ['sometimes', 'string', 'max:30'],
            'metadata' => ['nullable', 'array'],
        ]);

        $data['vendor_number'] = app(PartnerCodeService::class)->generate((string) ($data['category'] ?? 'supplier'));

        return response()->json(Vendor::create($data), 201);
    }

    public function show(Vendor $vendor)
    {
        $this->authorize('view', $vendor);

        return response()->json($vendor->load('tasks'));
    }

    public function update(Request $request, Vendor $vendor)
    {
        $this->authorize('update', $vendor);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'category' => ['sometimes', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email'],
            'address' => ['nullable', 'string'],
            'status' => ['sometimes', 'string', 'max:30'],
            'metadata' => ['nullable', 'array'],
        ]);

        $vendor->update($data);

        return response()->json($vendor->fresh());
    }

    public function destroy(Vendor $vendor)
    {
        $this->authorize('delete', $vendor);

        $vendor->delete();

        return response()->json(status: 204);
    }
}
