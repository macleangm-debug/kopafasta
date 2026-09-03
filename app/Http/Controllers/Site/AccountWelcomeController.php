<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Services\AccountWelcomeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AccountWelcomeController extends Controller
{
    public function complete(Request $request, AccountWelcomeService $welcome): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user, 403);

        $welcome->complete($user, $request->input('audience'));

        return back();
    }
}
