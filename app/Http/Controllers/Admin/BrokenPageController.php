<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BrokenPage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BrokenPageController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->string('status')->toString() ?: 'open';
        $query = BrokenPage::query()->with('user')->latest('last_seen_at')->latest('id');

        if ($status === 'open') {
            $query->whereNull('resolved_at');
        } elseif ($status === 'resolved') {
            $query->whereNotNull('resolved_at');
        }

        $rows = $query->paginate(30)->withQueryString();
        $openCount = BrokenPage::query()->whereNull('resolved_at')->count();

        return view('admin.broken-pages.index', compact('rows', 'status', 'openCount'));
    }

    public function show(BrokenPage $brokenPage): View
    {
        $brokenPage->load(['user', 'resolver']);

        return view('admin.broken-pages.show', compact('brokenPage'));
    }

    public function resolve(Request $request, BrokenPage $brokenPage): RedirectResponse
    {
        $data = $request->validate([
            'resolution_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $brokenPage->update([
            'resolved_at' => now(),
            'resolved_by' => $request->user()?->id,
            'resolution_notes' => $data['resolution_notes'] ?? $brokenPage->resolution_notes,
        ]);

        return back()->with('status', 'Marked as resolved.');
    }
}
