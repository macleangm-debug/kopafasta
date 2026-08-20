<?php

namespace App\Services;

use App\Models\NotificationLog;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class PartnerAssignmentNotifier
{
    public function __construct(
        private readonly NotificationService $notifications,
    ) {}

    /**
     * Notify the partner portal user and screening/admin staff about an assignment.
     *
     * @param  array{title?: string, body?: string, action_url?: string|null, staff_permission?: string, staff_url?: string|null}  $context
     */
    public function notifyAssigned(Vendor $partner, string $taskLabel, array $context = []): void
    {
        $title = $context['title'] ?? 'New task assigned';
        $body = $context['body'] ?? ('You have been assigned: '.$taskLabel);
        $actionUrl = $context['action_url'] ?? '/partner/tasks';

        try {
            $this->notifications->notifyPartner($partner, 'partner.task_assigned', [
                'task' => $taskLabel,
                'partner' => $partner->name,
                '_fallback_subject' => $title,
                '_fallback_body' => $body,
            ], $actionUrl);
        } catch (\Throwable $e) {
            Log::warning('Partner assignment notification failed', [
                'partner_id' => $partner->id,
                'error' => $e->getMessage(),
            ]);
        }

        $this->notifyStaff(
            $title.': '.$partner->name,
            $body,
            $context['staff_url'] ?? $actionUrl,
            $context['staff_permission'] ?? 'applications.view',
        );
    }

    public function notifyStaff(string $title, string $message, ?string $actionUrl = null, string $permission = 'applications.view'): void
    {
        $this->writeStaffNotifications(
            User::query()
                ->whereIn('role', ['admin', 'super_admin', 'staff'])
                ->where('is_active', true)
                ->get()
                ->filter(fn (User $user) => $user->hasPermission($permission) || in_array($user->role, ['admin', 'super_admin'], true)),
            $title,
            $message,
            $actionUrl,
        );
    }

    public function notifyPartnerManagers(string $title, string $message, ?string $actionUrl = null): void
    {
        $this->writeStaffNotifications(
            User::query()
                ->where('is_active', true)
                ->whereIn('role', ['admin', 'super_admin', 'partner_support', 'manager', 'officer', 'staff'])
                ->with(['department', 'departments'])
                ->get()
                ->filter(fn (User $user) => $user->can('create', Vendor::class)),
            $title,
            $message,
            $actionUrl,
            'partner.coverage_staff',
        );
    }

    /**
     * @param  iterable<int, User>  $users
     */
    private function writeStaffNotifications(
        iterable $users,
        string $title,
        string $message,
        ?string $actionUrl = null,
        string $template = 'partner.assignment_staff',
    ): void {
        try {
            foreach ($users as $user) {
                $payload = [
                    'channel'   => 'in_app',
                    'category'  => 'admin',
                    'template'  => $template,
                    'recipient' => $actionUrl ?: (string) ($user->email ?: 'in_app'),
                    'message'   => Str::limit(trim($title."\n".$message), 800, ''),
                    'status'    => 'sent',
                    'sent_at'   => now(),
                ];

                if (Schema::hasColumn('notification_logs', 'user_id')) {
                    $payload['user_id'] = $user->id;
                }

                NotificationLog::create($payload);
            }
        } catch (\Throwable $e) {
            Log::warning('Staff assignment notification failed', ['error' => $e->getMessage()]);
        }
    }
}
