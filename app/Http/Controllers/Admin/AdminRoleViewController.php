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

    /**
     * Role-first: Account / Role → click role → open that workspace (no person required).
     */
    public function enter(Request $request, AdminRoleViewService $views): RedirectResponse
    {
        $admin = $request->user('admin');
        abort_unless($admin, 403);

        // New role-first payload.
        if ($request->filled('workspace_key') || ($request->input('subject_type') === 'workspace')) {
            $data = $request->validate([
                'workspace_key' => ['required', 'string', 'max:64'],
            ]);

            $url = $views->enterWorkspace($admin, $data['workspace_key'])['url'];

            // 303 so a refresh cannot re-POST the previous role enter.
            return redirect()->to($url, 303)
                ->with('status', __('admin.role_view.entered'));
        }

        // Legacy person-first / partner enter (still used by partner portal viewing).
        $data = $request->validate([
            'subject_type' => ['required', 'in:partner,staff'],
            'subject_id' => ['required', 'integer', 'min:1'],
            'role_key' => ['required', 'string', 'max:64'],
        ]);

        $url = $views->enter(
            $admin,
            $data['subject_type'],
            (int) $data['subject_id'],
            $data['role_key'],
        )['url'];

        return redirect()->to($url, 303)
            ->with('status', __('admin.role_view.entered'));
    }

    public function selectStaff(Request $request, AdminRoleViewService $views): RedirectResponse
    {
        $admin = $request->user('admin');
        abort_unless($admin, 403);

        $data = $request->validate([
            'staff_id' => ['nullable', 'integer', 'min:0'],
        ]);

        $staffId = isset($data['staff_id']) && (int) $data['staff_id'] > 0
            ? (int) $data['staff_id']
            : null;

        $url = $views->selectWorkspaceStaff($admin, $staffId)['url'];

        return redirect()->to($url)
            ->with('status', __('admin.role_view.filter_updated'));
    }

    public function exit(AdminRoleViewService $views): RedirectResponse
    {
        $url = $views->exit();

        return redirect()->to($url)
            ->with('status', __('admin.role_view.exited'));
    }
}
