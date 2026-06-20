<?php

namespace App\Services;

use App\Models\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PortalOnboardingResumeService
{
    public function redirectIfPending(Request $request, Customer $customer): ?RedirectResponse
    {
        if ($redirect = app(GuarantorOnboardingService::class)->redirectIfPending($request, $customer)) {
            return $redirect;
        }

        return app(GroupMemberOnboardingService::class)->redirectIfPending($request, $customer);
    }
}
