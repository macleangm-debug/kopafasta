<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\GuarantorInvitation;
use Illuminate\Http\RedirectResponse;

class ShortLinkController extends Controller
{
    public function guarantor(string $code): RedirectResponse
    {
        $invitation = GuarantorInvitation::query()
            ->where('short_code', strtoupper($code))
            ->firstOrFail();

        return redirect()->route('site.guarantor.show', $invitation->token);
    }
}
