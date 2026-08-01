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
            'statuses'  => config('asset_lending.asset_request_statuses', [
                'sourcing'  => 'Asset sourcing request',
                'reviewing' => 'Under review',
                'matched'   => 'Matched to supplier',
                'closed'    => 'Closed',
            ]),
        ]);
    }

    public function update(Request $request, AssetRequest $assetRequest): RedirectResponse
    {
        $data = $request->validate([
            'status'      => ['required', 'in:sourcing,pending,reviewing,matched,closed'],
            'vendor_id'   => ['nullable', 'exists:partners,id'],
            'admin_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        // Assigning a supplier releases the request to their portal (reviewing).
        if (! empty($data['vendor_id']) && in_array($data['status'], ['sourcing', 'pending'], true)) {
            $data['status'] = 'reviewing';
        }

        if (in_array($data['status'], ['reviewing', 'matched'], true) && empty($data['vendor_id']) && ! $assetRequest->vendor_id) {
            return back()->withErrors(['vendor_id' => 'Assign a supplier before releasing or matching this request.'])->withInput();
        }

        $previousVendorId = $assetRequest->vendor_id;
        $assetRequest->update($data);

        if ($assetRequest->wasChanged('status') && $assetRequest->status === 'matched') {
            $customer = $assetRequest->customer;
            if ($customer?->user?->email ?? $customer?->email) {
                app(NotificationService::class)->sendEmail(
                    $customer->user?->email ?? $customer->email,
                    'Asset request matched',
                    "Good news — we found a match for your request: {$assetRequest->asset_name}. Log in to browse the asset marketplace or contact support for next steps.",
                    $customer,
                    'asset_request_matched',
                );
            }
        }

        $assignedVendorId = $assetRequest->vendor_id;
        if ($assignedVendorId && (int) $assignedVendorId !== (int) $previousVendorId) {
            $vendor = Vendor::find($assignedVendorId);
            if ($vendor?->email) {
                app(NotificationService::class)->sendEmail(
                    $vendor->email,
                    'New asset request assigned',
                    "A borrower requested: {$assetRequest->asset_name}. Budget: ".format_money((float) ($assetRequest->budget ?? 0)).'. Log in to your supplier portal — only admin-approved requests appear there.',
                    $assetRequest->customer,
                    'asset_request_assigned',
                );
            }
        }

        return back()->with('status', 'Asset request updated.');
    }
}
