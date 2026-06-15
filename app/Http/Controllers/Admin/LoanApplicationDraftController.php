<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LoanApplicationDraft;
use App\Services\LoanApplicationDraftService;
use Illuminate\View\View;

class LoanApplicationDraftController extends Controller
{
    public function show(LoanApplicationDraft $draft, LoanApplicationDraftService $drafts): View
    {
        $draft->load(['customer', 'product']);
        $snapshot = $drafts->adminSnapshot($draft);
        $badge = $drafts->statusBadge($draft);

        return view('admin.loan-applications.draft-show', compact('draft', 'snapshot', 'badge'));
    }
}
