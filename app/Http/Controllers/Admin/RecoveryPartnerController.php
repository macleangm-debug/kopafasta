<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\RecoveryPartnerService;
use App\Services\RecoveryPolicyService;
use Illuminate\View\View;

class RecoveryPartnerController extends Controller
{
    public function index(RecoveryPartnerService $partners): View
    {
        $types = app(RecoveryPolicyService::class)->partnerTypes();

        $summary = collect($types)->map(function (array $meta, string $type) use ($partners) {
            return [
                'type'  => $type,
                'label' => $meta['label'],
                'count' => $partners->filteredQuery($type)->where('status', 'active')->count(),
            ];
        })->values();

        return view('admin.recovery.partners.index', compact('summary', 'types'));
    }

    public function byType(string $type, RecoveryPartnerService $partners, RecoveryPolicyService $policy): View
    {
        abort_unless(isset($policy->partnerTypes()[$type]), 404);

        return view('admin.recovery.partners.type', [
            'partnerType' => $type,
            'label'       => $policy->partnerTypeLabel($type),
        ]);
    }
}
