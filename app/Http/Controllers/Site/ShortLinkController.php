<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\GroupMemberInvitation;
use App\Models\GuarantorInvitation;
use Illuminate\Http\RedirectResponse;

class ShortLinkController extends Controller
{
    public function resolve(string $code): RedirectResponse
    {
        $upper = strtoupper($code);

        $guarantor = GuarantorInvitation::query()
            ->where('short_code', $upper)
            ->first();

        if ($guarantor) {
            return redirect()->route('site.guarantor.show', $guarantor->token);
        }

        $groupMember = GroupMemberInvitation::query()
            ->where('short_code', $upper)
            ->firstOrFail();

        return redirect()->route('site.group-member.invite', $groupMember->token);
    }

    /** @deprecated Use resolve() */
    public function guarantor(string $code): RedirectResponse
    {
        return $this->resolve($code);
    }
}
