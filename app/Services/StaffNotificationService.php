<?php

namespace App\Services;

use App\Models\User;

class StaffNotificationService
{
    public function notifyLoanModificationRequest(string $type, string $subject, string $body, string $adminPath): void
    {
        $url = url($adminPath);
        $message = trim($body."\n\nReview: ".$url);

        User::query()
            ->where('is_active', true)
            ->whereIn('role', ['officer', 'manager', 'admin', 'super_admin'])
            ->whereNotNull('email')
            ->pluck('email')
            ->unique()
            ->each(function (string $email) use ($subject, $message, $type): void {
                app(NotificationService::class)->sendEmail(
                    $email,
                    $subject,
                    $message,
                    null,
                    'staff_'.$type,
                );
            });
    }
}
