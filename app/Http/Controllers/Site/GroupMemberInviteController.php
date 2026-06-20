<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\GroupMemberInvitation;
use Illuminate\View\View;

class GroupMemberInviteController extends Controller
{
    public function show(string $token): View
    {
        $invitation = GroupMemberInvitation::query()
            ->where('token', $token)
            ->where('status', 'pending')
            ->firstOrFail();

        return view('site.group-member.invite', [
            'invitation' => $invitation,
            'registerUrl' => route('site.register.borrower', ['group_invite' => $invitation->token]),
        ]);
    }
}
