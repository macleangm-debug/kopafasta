<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Str;

class StaffNotificationService
{
    public function notifyLoanModificationRequest(string $type, string $subject, string $body, string $adminPath): void
    {
        $delivery = app(\App\Services\Messaging\NotificationDeliverySettings::class);
        if (! $delivery->managementEventEnabled('applications') && ! $delivery->operationalEventEnabled('finance')) {
            return;
        }

        $url = url($adminPath);
        $message = trim($body."\n\nReview: ".$url);
        $smsBody = Str::limit($body.' Review: '.$url, 160, '…');
        $smsEnabled = (bool) Setting::get('gateway.staff_sms_alerts', true)
            && ($delivery->managementChannelEnabled('sms') || $delivery->operationalChannelEnabled('sms'));

        $staff = User::query()
            ->where('is_active', true)
            ->whereIn('role', ['officer', 'manager', 'admin', 'super_admin'])
            ->get(['email', 'phone']);

        $notify = app(NotificationService::class);

        if ($delivery->managementChannelEnabled('email') || $delivery->operationalChannelEnabled('email')) {
            $staff->pluck('email')->filter()->unique()->each(function (string $email) use ($notify, $subject, $message, $type): void {
                $notify->sendEmail($email, $subject, $message, null, 'staff_'.$type);
            });
        }

        if ($smsEnabled) {
            $staff->pluck('phone')->filter()->unique()->each(function (string $phone) use ($notify, $smsBody, $type): void {
                $notify->sendSms($phone, $smsBody, null, 'staff_'.$type);
            });
        }
    }
}
