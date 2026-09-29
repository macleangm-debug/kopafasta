<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminRoleViewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AdminRoleViewController extends Controller
{
    public function search(Request $request, AdminRoleViewService $views): JsonResponse
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
        ]);

        return response()->json([
            'results' => $views->search((string) ($data['q'] ?? '')),
        ]);
    }

    public function profile(Request $request, AdminRoleViewService $views): RedirectResponse
    {
        $data = $request->validate([
            'subject_type' => ['required', 'in:partner,staff,borrower'],
            'subject_id' => ['required', 'integer', 'min:1'],
        ]);

        $url = $views->profileUrl($data['subject_type'], (int) $data['subject_id'])['url'];

        return redirect()->to($url);
    }

    public function enter(Request $request, AdminRoleViewService $views): RedirectResponse
    {
        $data = $request->validate([
            'subject_type' => ['required', 'in:partner,staff'],
            'subject_id' => ['required', 'integer', 'min:1'],
            'role_key' => ['required', 'string', 'max:64'],
        ]);

        $admin = $request->user('admin');
        abort_unless($admin, 403);

        $url = $views->enter(
            $admin,
            $data['subject_type'],
            (int) $data['subject_id'],
            $data['role_key'],
        )['url'];

        return redirect()->to($url)
            ->with('status', __('admin.role_view.entered'));
    }

    public function exit(AdminRoleViewService $views): RedirectResponse
    {
        $url = $views->exit();

        return redirect()->to($url)
            ->with('status', __('admin.role_view.exited'));
    }
}
