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
            'vendor_id'   => ['nullable', 'exists:partners,id'],
            'admin_notes' => ['nullable', 'string', 'max:2000'],
        ]);

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

        if ($assetRequest->vendor_id && $assetRequest->wasChanged('partner_id')) {
            $vendor = Vendor::find($assetRequest->vendor_id);
            if ($vendor?->email) {
                app(NotificationService::class)->sendEmail(
                    $vendor->email,
                    'New asset request assigned',
                    "A borrower requested: {$assetRequest->asset_name}. Budget: ".format_money((float) ($assetRequest->budget ?? 0)).'. Log in to your supplier portal for details.',
                    $assetRequest->customer,
                    'asset_request_assigned',
                );
            }
        }

        return back()->with('status', 'Asset request updated.');
    }
}
