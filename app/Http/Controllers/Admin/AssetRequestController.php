<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AssetRequest;
use App\Models\Vendor;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AssetRequestController extends Controller
{
    public function index(): View
    {
        $requests = AssetRequest::query()
            ->with(['customer', 'vendor'])
            ->latest()
            ->paginate(25);

        return view('admin.asset-requests.index', [
            'requests'  => $requests,
            'suppliers' => Vendor::query()->where('category', 'supplier')->where('status', 'active')->orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function update(Request $request, AssetRequest $assetRequest): RedirectResponse
    {
        $data = $request->validate([
            'status'      => ['required', 'in:pending,reviewing,matched,closed'],
            'vendor_id'   => ['nullable', 'exists:vendors,id'],
            'admin_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $assetRequest->update($data);

        if ($assetRequest->vendor_id && $assetRequest->wasChanged('vendor_id')) {
            $vendor = Vendor::find($assetRequest->vendor_id);
            if ($vendor?->email) {
                app(NotificationService::class)->sendEmail(
                    $vendor->email,
                    'New asset request assigned',
                    "A borrower requested: {$assetRequest->asset_name}. Budget: ".number_format((float) ($assetRequest->budget ?? 0)).'. Log in to your supplier portal for details.',
                    $assetRequest->customer,
                    'asset_request_assigned',
                );
            }
        }

        return back()->with('status', 'Asset request updated.');
    }
}
