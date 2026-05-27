<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\JournalEntry;
use Illuminate\Http\Request;

class JournalEntryController extends Controller
{
    public function index(Request $request)
    {
        $q = JournalEntry::query()->orderByDesc('entry_date')->orderByDesc('id');

        if ($s = trim((string) $request->query('q', ''))) {
            $q->where(function ($w) use ($s) {
                $w->where('entry_number', 'like', "%{$s}%")
                  ->orWhere('description', 'like', "%{$s}%");
            });
        }
        if ($status = $request->query('status')) {
            $q->where('status', $status);
        }
        if ($from = $request->query('from')) $q->where('entry_date', '>=', $from);
        if ($to = $request->query('to'))     $q->where('entry_date', '<=', $to);

        $entries = $q->paginate(50)->withQueryString();

        $totalDr = (float) JournalEntry::where('status', 'posted')->sum('total_debit');
        $totalCr = (float) JournalEntry::where('status', 'posted')->sum('total_credit');

        return view('admin.journal-entries.index', compact('entries', 'totalDr', 'totalCr'));
    }

    public function show(JournalEntry $journalEntry)
    {
        $journalEntry->load(['lines.account', 'postedBy']);
        return view('admin.journal-entries.show', ['entry' => $journalEntry]);
    }
}
