<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Services\NotificationService;
use App\Services\PromotionService;
use Illuminate\Console\Command;

class SendBirthdayWishes extends Command
{
    protected $signature = 'customers:send-birthday-wishes';

    protected $description = 'Send birthday in-app notifications to customers whose birthday is today';

    public function handle(PromotionService $promotions, NotificationService $notifications): int
    {
        $today = now();

        $customers = Customer::query()
            ->whereNotNull('date_of_birth')
            ->whereMonth('date_of_birth', $today->month)
            ->whereDay('date_of_birth', $today->day)
            ->get();

        $sent = 0;

        foreach ($customers as $customer) {
            $message = $promotions->birthdayMessage($customer)
                ?? 'Happy birthday, '.$customer->first_name.'! Wishing you a wonderful year ahead from KopaFasta.';

            $alreadySent = $customer->reminders_sent['birthday_'.$today->format('Y')] ?? false;
            if ($alreadySent) {
                continue;
            }

            $notifications->notifyInApp($customer, $message, 'promotion', 'birthday');

            if ($customer->phone) {
                $notifications->sendSms($customer->phone, $message, $customer, 'birthday');
            }

            $reminders = $customer->reminders_sent ?? [];
            $reminders['birthday_'.$today->format('Y')] = true;
            $customer->update(['reminders_sent' => $reminders]);

            $sent++;
        }

        $this->info("Birthday wishes sent to {$sent} customer(s).");

        return self::SUCCESS;
    }
}
