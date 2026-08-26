<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminShortcutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AdminShortcutController extends Controller
{
    public function index(Request $request, AdminShortcutService $shortcuts): JsonResponse
    {
        $user = $request->user('admin') ?? $request->user();
        abort_unless($user, 403);

        return response()->json(['shortcuts' => $shortcuts->list($user)]);
    }

    public function store(Request $request, AdminShortcutService $shortcuts): RedirectResponse|JsonResponse
    {
        $user = $request->user('admin') ?? $request->user();
        abort_unless($user, 403);
        $data = $request->validate([
            'route' => ['required', 'string', 'max:120'],
            'label' => ['required', 'string', 'max:80'],
        ]);
        $shortcuts->add($user, $data['route'], $data['label']);

        if ($request->expectsJson()) {
            return response()->json(['shortcuts' => $shortcuts->list($user->fresh())]);
        }

        return back()->with('status', 'Added to shortcuts.');
    }

    public function destroy(Request $request, AdminShortcutService $shortcuts): RedirectResponse|JsonResponse
    {
        $user = $request->user('admin') ?? $request->user();
        abort_unless($user, 403);
        $route = (string) $request->input('route', $request->route('shortcut'));
        $shortcuts->remove($user, $route);

        if ($request->expectsJson()) {
            return response()->json(['shortcuts' => $shortcuts->list($user->fresh())]);
        }

        return back()->with('status', 'Shortcut removed.');
    }

    public function reorder(Request $request, AdminShortcutService $shortcuts): JsonResponse|RedirectResponse
    {
        $user = $request->user('admin') ?? $request->user();
        abort_unless($user, 403);
        $data = $request->validate([
            'routes' => ['required', 'array', 'max:6'],
            'routes.*' => ['string'],
        ]);
        $shortcuts->reorder($user, $data['routes']);

        if ($request->expectsJson()) {
            return response()->json(['shortcuts' => $shortcuts->list($user->fresh())]);
        }

        return back()->with('status', 'Shortcuts updated.');
    }
}
