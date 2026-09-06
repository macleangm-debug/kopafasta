<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BrokenPage;
use App\Models\Setting;
use App\Services\BrokenPageClassifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BrokenPageController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->string('status')->toString() ?: 'needs_attention';
        $query = BrokenPage::query()->with('user')->latest('last_seen_at')->latest('id');

        if ($status === 'needs_attention') {
            $query->needsAttention();
        } elseif ($status === 'open') {
            $query->whereNull('resolved_at');
        } elseif ($status === 'resolved') {
            $query->whereNotNull('resolved_at');
        } elseif ($status === 'scanner') {
            $query->where('category', 'scanner_bot');
        }

        $rows = $query->paginate(30)->withQueryString();
        $needsAttentionCount = BrokenPage::query()->needsAttention()->count();
        $openCount = BrokenPage::query()->whereNull('resolved_at')->count();
        $baselineAt = Setting::get('broken_pages.baseline_at');

        return view('admin.broken-pages.index', compact(
            'rows',
            'status',
            'needsAttentionCount',
            'openCount',
            'baselineAt',
        ));
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
            'category' => ['nullable', 'string', 'max:40'],
        ]);

        $brokenPage->update([
            'resolved_at' => now(),
            'resolved_by' => $request->user()?->id,
            'resolution_notes' => $data['resolution_notes'] ?? $brokenPage->resolution_notes,
            'category' => $data['category'] ?? $brokenPage->category,
        ]);

        return back()->with('status', 'Marked as resolved.');
    }

    public function resetBaseline(Request $request): RedirectResponse
    {
        Setting::set('broken_pages.baseline_at', now()->toIso8601String());

        return back()->with('status', 'Broken Pages monitoring baseline reset. New actionable defects will appear as Needs Attention.');
    }

    public function classifyOpen(Request $request, BrokenPageClassifier $classifier): RedirectResponse
    {
        $resolved = 0;
        $classified = 0;

        BrokenPage::query()->whereNull('resolved_at')->orderBy('id')->chunkById(100, function ($rows) use ($classifier, $request, &$resolved, &$classified): void {
            foreach ($rows as $row) {
                $result = $classifier->classify(
                    (string) $row->path,
                    (int) $row->status,
                    $row->exception,
                    $row->message,
                    $row->user_agent,
                    $row->method,
                );
                $classified++;
                $payload = [
                    'category' => $result['category'],
                    'classification_notes' => $result['notes'],
                ];
                if ($result['auto_resolve']) {
                    $payload['resolved_at'] = now();
                    $payload['resolved_by'] = $request->user()?->id;
                    $payload['resolution_notes'] = $result['notes'];
                    $resolved++;
                }
                $row->update($payload);
            }
        });

        return back()->with('status', "Classified {$classified} open incidents; auto-resolved {$resolved} non-actionable rows.");
    }
}
