<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\NotificationLog;
use App\Models\NotificationTemplate;
use App\Services\Sms\SmsManager;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class NotificationService
{
    public function __construct(private readonly SmsManager $sms) {}

    /**
     * Send a raw SMS. Logs to notification_logs.
     */
    public function sendSms(string $phone, string $message, ?Customer $customer = null, ?string $templateCode = null): NotificationLog
    {
        $log = NotificationLog::create([
            'customer_id' => $customer?->id,
            'channel'     => 'sms',
            'template'    => $templateCode,
            'recipient'   => $phone,
            'message'     => Str::limit($message, 800, ''),
            'status'      => 'queued',
        ]);

        $result = $this->sms->driver()->send($phone, $message);

        $log->update([
            'status'  => $result['ok'] ? 'sent' : 'failed',
            'sent_at' => $result['ok'] ? now() : null,
        ]);

        return $log;
    }

    /**
     * Send a raw email via Laravel mail. Logs to notification_logs.
     */
    public function sendEmail(string $email, string $subject, string $body, ?Customer $customer = null, ?string $templateCode = null): NotificationLog
    {
        $log = NotificationLog::create([
            'customer_id' => $customer?->id,
            'channel'     => 'email',
            'template'    => $templateCode,
            'recipient'   => $email,
            'message'     => "[{$subject}] ".Str::limit($body, 500, ''),
            'status'      => 'queued',
        ]);

        try {
            Mail::raw($body, function ($m) use ($email, $subject) {
                $m->to($email)->subject($subject);
            });
            $log->update(['status' => 'sent', 'sent_at' => now()]);
        } catch (\Throwable $e) {
            $log->update(['status' => 'failed']);
        }

        return $log;
    }

    /**
     * Send to a customer via the best channels available, using a template lookup if provided.
     *
     * @param array<string,mixed> $vars
     */
    public function notifyCustomer(Customer $customer, string $templateCode, array $vars = []): void
    {
        $tpl = NotificationTemplate::where('code', $templateCode)->where('is_active', true)->first();

        $body    = $tpl ? $this->render($tpl->body, $vars) : ($vars['_fallback_body'] ?? '');
        $subject = $tpl ? $this->render((string) $tpl->subject, $vars) : ($vars['_fallback_subject'] ?? 'Kopa Fasta');

        if (! $body) return;

        $channel = $tpl?->channel ?? 'sms';

        if ($channel === 'sms' || $channel === 'all') {
            if ($customer->phone) {
                $this->sendSms($customer->phone, $body, $customer, $templateCode);
            }
        }
        if ($channel === 'email' || $channel === 'all') {
            $email = $customer->email ?? optional($customer->user)->email;
            if ($email) {
                $this->sendEmail($email, $subject, $body, $customer, $templateCode);
            }
        }
    }

    /**
     * @param  array{title_key?: string, body_key?: string, params?: array<string, mixed>}|null  $i18n
     *        When provided, title/body are re-translated at read time from these keys.
     */
    public function notifyInApp(
        Customer $customer,
        string $message,
        string $category = 'general',
        ?string $template = null,
        ?string $title = null,
        ?string $actionUrl = null,
        ?string $actionLabel = null,
        ?array $i18n = null,
    ): NotificationLog {
        $payload = [
            'customer_id' => $customer->id,
            'channel'     => 'in_app',
            'category'    => $category,
            'template'    => $template,
            'recipient'   => $this->normalizeActionRecipient($actionUrl)
                ?: (string) ($customer->phone ?: $customer->email ?: 'in_app'),
            'message'     => Str::limit(trim(($title ? $title."\n" : '').$message), 800, ''),
            'status'      => 'sent',
            'sent_at'     => now(),
        ];

        if (is_array($i18n) && (filled($i18n['title_key'] ?? null) || filled($i18n['body_key'] ?? null))) {
            $payload['meta'] = [
                'title_key' => $i18n['title_key'] ?? null,
                'body_key'  => $i18n['body_key'] ?? null,
                'params'    => is_array($i18n['params'] ?? null) ? $i18n['params'] : [],
            ];
        }

        return NotificationLog::create($payload);
    }

    /** Store CTA as a site-relative path so notification UIs can detect it. */
    private function normalizeActionRecipient(?string $actionUrl): ?string
    {
        if (! filled($actionUrl)) {
            return null;
        }

        if (str_starts_with($actionUrl, '/')) {
            return $actionUrl;
        }

        $path = parse_url($actionUrl, PHP_URL_PATH);
        if (! filled($path) || ! str_starts_with($path, '/')) {
            return null;
        }

        $query = parse_url($actionUrl, PHP_URL_QUERY);

        return $query ? $path.'?'.$query : $path;
    }

    private function render(string $template, array $vars): string
    {
        $out = $template;
        foreach ($vars as $k => $v) {
            $out = str_replace(['{{ '.$k.' }}', '{{'.$k.'}}'], (string) $v, $out);
        }
        return $out;
    }
}
