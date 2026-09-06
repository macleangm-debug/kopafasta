<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\NotificationLog;
use App\Models\NotificationTemplate;
use App\Models\Partner;
use App\Services\Marketing\DemoGuard;
use App\Services\Messaging\TransactionalMessagingService;
use App\Services\Messaging\WhatsApp\WhatsAppManager;
use App\Services\Mail\GatewayMailConfigurator;
use App\Services\Sms\SmsManager;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class NotificationService
{
    public function __construct(
        private readonly SmsManager $sms,
        private readonly TransactionalMessagingService $messaging,
        private readonly WhatsAppManager $whatsapp,
        private readonly GatewayMailConfigurator $mailConfig,
    ) {}

    /**
     * Send a raw SMS. Logs to notification_logs.
     */
    public function sendSms(string $phone, string $message, ?Customer $customer = null, ?string $templateCode = null): NotificationLog
    {
        $message = $this->ensureLicensedSmsIdentity($message);

        if (app(DemoGuard::class)->isActive()) {
            return $this->skippedLog('sms', $phone, $message, $customer, $templateCode);
        }

        if (! $this->messaging->channelEnabled('sms')
            || ($templateCode && ! $this->messaging->eventEnabled($templateCode))) {
            return $this->skippedLog('sms', $phone, $message, $customer, $templateCode);
        }

        $log = NotificationLog::create([
            'customer_id' => $customer?->id,
            'channel' => 'sms',
            'template' => $templateCode,
            'recipient' => $phone,
            'message' => Str::limit($message, 800, ''),
            'status' => 'queued',
        ]);

        $result = $this->sms->driver()->send($phone, $message);

        $log->update([
            'status' => $result['ok'] ? 'sent' : 'failed',
            'sent_at' => $result['ok'] ? now() : null,
        ]);

        return $log;
    }

    /**
     * Send a raw email via Laravel mail. Logs to notification_logs.
     */
    public function sendEmail(string $email, string $subject, string $body, ?Customer $customer = null, ?string $templateCode = null): NotificationLog
    {
        if (! $this->messaging->channelEnabled('email')
            || ($templateCode && ! $this->messaging->eventEnabled($templateCode))) {
            return $this->skippedLog('email', $email, "[{$subject}] ".$body, $customer, $templateCode);
        }

        $log = NotificationLog::create([
            'customer_id' => $customer?->id,
            'channel' => 'email',
            'template' => $templateCode,
            'recipient' => $email,
            'message' => "[{$subject}] ".Str::limit($body, 500, ''),
            'status' => 'queued',
        ]);

        try {
            $this->mailConfig->apply();
            Mail::raw($body, function ($m) use ($email, $subject) {
                $m->to($email)->subject($subject);
            });
            $log->update(['status' => 'sent', 'sent_at' => now()]);
        } catch (\Throwable $e) {
            $log->update(['status' => 'failed']);
        }

        return $log;
    }

    public function sendWhatsApp(string $phone, string $message, ?Customer $customer = null, ?string $templateCode = null): NotificationLog
    {
        if (! $this->messaging->channelEnabled('whatsapp')
            || ($templateCode && ! $this->messaging->eventEnabled($templateCode))) {
            return $this->skippedLog('whatsapp', $phone, $message, $customer, $templateCode);
        }

        $log = NotificationLog::create([
            'customer_id' => $customer?->id,
            'channel' => 'whatsapp',
            'template' => $templateCode,
            'recipient' => $phone,
            'message' => Str::limit($message, 800, ''),
            'status' => 'queued',
        ]);

        $result = $this->whatsapp->driver()->send($phone, $message);

        $log->update([
            'status' => $result['ok'] ? 'sent' : 'failed',
            'sent_at' => $result['ok'] ? now() : null,
        ]);

        return $log;
    }

    /**
     * Send to a customer via the best channels available, using a template lookup if provided.
     *
     * @param  array<string,mixed>  $vars
     */
    public function notifyCustomer(Customer $customer, string $templateCode, array $vars = []): void
    {
        if (! $this->messaging->eventEnabled($templateCode)) {
            return;
        }

        $vars = $this->withIdentityVars($vars);

        $tpl = NotificationTemplate::resolveActive(
            $templateCode,
            $vars['_locale']
                ?? data_get($customer, 'preferred_locale')
                ?? data_get($customer, 'locale')
                ?? optional($customer->user)->locale
                ?? app()->getLocale()
        );

        $body = $tpl ? $this->render($tpl->body, $vars) : ($vars['_fallback_body'] ?? '');
        $subject = $tpl ? $this->render((string) $tpl->subject, $vars) : ($vars['_fallback_subject'] ?? brand_legal_name());

        if (! $body) {
            return;
        }

        $body = $this->ensureLicensedSmsIdentity($body);

        $actionUrl = is_string($vars['_action_url'] ?? null) ? $vars['_action_url'] : null;

        $allowed = $this->messaging->allowedChannelsFor($templateCode);
        if ($allowed === []) {
            // Fall back to template channel if event has no channel list yet.
            $channel = $tpl?->channel ?? 'sms';
            $allowed = match ($channel) {
                'all' => ['sms', 'email', 'in_app'],
                default => [$channel],
            };
            $allowed = array_values(array_filter(
                $allowed,
                fn (string $ch) => $this->messaging->channelEnabled($ch)
            ));
        }

        if (! empty($vars['_skip_in_app'])) {
            $allowed = array_values(array_filter($allowed, fn (string $ch) => $ch !== 'in_app'));
        }

        foreach ($allowed as $channel) {
            if ($channel === 'sms' && $customer->phone) {
                $this->sendSms($customer->phone, $body, $customer, $templateCode);
            }
            if ($channel === 'email') {
                $email = $customer->email ?? optional($customer->user)->email;
                if ($email) {
                    $this->sendEmail($email, $subject, $body, $customer, $templateCode);
                }
            }
            if ($channel === 'whatsapp' && $customer->phone) {
                $this->sendWhatsApp($customer->phone, $body, $customer, $templateCode);
            }
            if ($channel === 'in_app') {
                $this->notifyInApp(
                    $customer,
                    $body,
                    'loan_updates',
                    $templateCode,
                    $subject,
                    $actionUrl,
                );
            }
        }

        // Critical borrower receipts always land in the in-app inbox even if SMS is the only selected channel.
        $config = $this->messaging->eventConfig($templateCode);
        if (($config['critical'] ?? false) && ! in_array('in_app', $allowed, true) && empty($vars['_skip_in_app'])) {
            $this->notifyInApp(
                $customer,
                $body,
                'loan_updates',
                $templateCode,
                $subject,
            );
        }
    }

    /**
     * @param  array{title_key?: string, body_key?: string, params?: array<string, mixed>}|null  $i18n
     *                                                                                                  When provided, title/body are re-translated at read time from these keys.
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
        // In-app inbox is always written for the user. Channel kill-switches apply to SMS/email/WhatsApp only.
        $payload = [
            'customer_id' => $customer->id,
            'channel' => 'in_app',
            'category' => $category,
            'template' => $template,
            'recipient' => $this->normalizeActionRecipient($actionUrl)
                ?: (string) ($customer->phone ?: $customer->email ?: 'in_app'),
            'message' => Str::limit(trim(($title ? $title."\n" : '').$message), 800, ''),
            'status' => 'sent',
            'sent_at' => now(),
        ];

        if (Schema::hasColumn('notification_logs', 'user_id') && $customer->user_id) {
            $payload['user_id'] = $customer->user_id;
        }

        if (is_array($i18n) && (filled($i18n['title_key'] ?? null) || filled($i18n['body_key'] ?? null) || isset($i18n['customer_guarantor_id']) || isset($i18n['loan_application_id']) || isset($i18n['loan_application_document_request_id']) || isset($i18n['due_on']))) {
            $payload['meta'] = array_filter([
                'title_key' => $i18n['title_key'] ?? null,
                'body_key' => $i18n['body_key'] ?? null,
                'params' => is_array($i18n['params'] ?? null) ? $i18n['params'] : [],
                'customer_guarantor_id' => $i18n['customer_guarantor_id'] ?? null,
                'loan_application_id' => $i18n['loan_application_id'] ?? null,
                'loan_application_document_request_id' => $i18n['loan_application_document_request_id'] ?? null,
                'due_on' => $i18n['due_on'] ?? null,
            ], static fn ($v) => $v !== null && $v !== []);
        }

        return NotificationLog::create($payload);
    }

    /**
     * Send at most once per fingerprint (and optional cooldown).
     *
     * @param  array<string, mixed>  $vars
     */
    public function notifyCustomerOnce(
        Customer $customer,
        string $templateCode,
        array $vars = [],
        ?string $fingerprint = null,
        int $cooldownHours = 8760,
    ): bool {
        $user = $customer->user;
        $key = $templateCode.':'.($fingerprint ?: 'once');
        if ($user) {
            $prefs = is_array($user->preferences) ? $user->preferences : [];
            $sentAt = data_get($prefs, 'lifecycle_notices.'.$key);
            if (is_string($sentAt) && $sentAt !== '' && now()->lt(Carbon::parse($sentAt)->addHours($cooldownHours))) {
                return false;
            }
        }

        $this->notifyCustomer($customer, $templateCode, $vars);

        if ($user) {
            $prefs = is_array($user->preferences) ? $user->preferences : [];
            $notices = is_array($prefs['lifecycle_notices'] ?? null) ? $prefs['lifecycle_notices'] : [];
            $notices[$key] = now()->toIso8601String();
            $prefs['lifecycle_notices'] = $notices;
            $user->forceFill(['preferences' => $prefs])->save();
        }

        return true;
    }

    /**
     * In-app (and optional SMS/email) notice for a partner portal user.
     *
     * @param  array<string, mixed>  $vars
     */
    public function notifyPartner(
        Partner $partner,
        string $templateCode,
        array $vars = [],
        ?string $actionUrl = null,
    ): ?NotificationLog {
        if (! $this->messaging->eventEnabled($templateCode)) {
            return null;
        }

        $user = $partner->user;
        $locale = data_get($user?->preferences, 'preferred_locale')
            ?: $user?->locale
            ?: app()->getLocale();
        $tpl = NotificationTemplate::resolveActive($templateCode, $locale);

        $body = $tpl
            ? $this->render($tpl->body, $vars + ['partner' => $partner->name])
            : (string) ($vars['_fallback_body'] ?? '');
        $subject = $tpl
            ? $this->render((string) $tpl->subject, $vars + ['partner' => $partner->name])
            : (string) ($vars['_fallback_subject'] ?? brand_name());

        if ($body === '') {
            return null;
        }

        $payload = [
            'channel' => 'in_app',
            'category' => 'partner',
            'template' => $templateCode,
            'recipient' => $this->normalizeActionRecipient($actionUrl)
                ?: (string) ($partner->phone ?: $partner->email ?: $user?->email ?: 'in_app'),
            'message' => Str::limit(trim(($subject ? $subject."\n" : '').$body), 800, ''),
            'status' => 'sent',
            'sent_at' => now(),
        ];

        if (Schema::hasColumn('notification_logs', 'user_id') && $user?->id) {
            $payload['user_id'] = $user->id;
        }

        $log = NotificationLog::create($payload);

        if ($partner->phone && $this->messaging->channelEnabled('sms')) {
            $this->sendSms($partner->phone, $body, null, $templateCode);
        }

        $email = $partner->email ?? $user?->email;
        if ($email && $this->messaging->channelEnabled('email')) {
            $this->sendEmail($email, $subject, $body, null, $templateCode);
        }

        return $log;
    }

    /**
     * Send at most once per fingerprint (and optional cooldown).
     *
     * @param  array<string, mixed>  $vars
     */
    public function notifyPartnerOnce(
        Partner $partner,
        string $templateCode,
        array $vars = [],
        ?string $actionUrl = null,
        ?string $fingerprint = null,
        int $cooldownHours = 8760,
    ): ?NotificationLog {
        $user = $partner->user;
        $key = $templateCode.':'.($fingerprint ?: 'once');
        if ($user) {
            $prefs = is_array($user->preferences) ? $user->preferences : [];
            $sentAt = data_get($prefs, 'lifecycle_notices.'.$key);
            if (is_string($sentAt) && $sentAt !== '' && now()->lt(Carbon::parse($sentAt)->addHours($cooldownHours))) {
                return null;
            }
        }

        $log = $this->notifyPartner($partner, $templateCode, $vars, $actionUrl);
        if ($log && $user) {
            $prefs = is_array($user->preferences) ? $user->preferences : [];
            $notices = is_array($prefs['lifecycle_notices'] ?? null) ? $prefs['lifecycle_notices'] : [];
            $notices[$key] = now()->toIso8601String();
            $prefs['lifecycle_notices'] = $notices;
            $user->forceFill(['preferences' => $prefs])->save();
        }

        return $log;
    }

    private function skippedLog(
        string $channel,
        string $recipient,
        string $message,
        ?Customer $customer,
        ?string $templateCode,
    ): NotificationLog {
        return NotificationLog::create([
            'customer_id' => $customer?->id,
            'channel' => $channel,
            'template' => $templateCode,
            'recipient' => $recipient,
            'message' => Str::limit('[skipped] '.$message, 800, ''),
            'status' => 'skipped',
        ]);
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
            if (! is_scalar($v) && $v !== null) {
                continue;
            }
            $out = str_replace(['{{ '.$k.' }}', '{{'.$k.'}}'], (string) $v, $out);
        }

        return $out;
    }

    /** @param  array<string, mixed>  $vars
     * @return array<string, mixed>
     */
    private function withIdentityVars(array $vars): array
    {
        $legal = brand_legal_name();
        $defaults = [
            'brand' => $legal,
            'legal_name' => $legal,
            'app_name' => brand_name(),
        ];

        // Alias common disbursement/receipt placeholders so templates and callers stay aligned.
        if (isset($vars['reference']) && ! isset($vars['loan_number'])) {
            $defaults['loan_number'] = $vars['reference'];
        }
        if (isset($vars['first_repayment']) && ! isset($vars['due_date'])) {
            $defaults['due_date'] = $vars['first_repayment'];
        }
        if (isset($vars['loan_number']) && ! isset($vars['reference'])) {
            $defaults['reference'] = $vars['loan_number'];
        }

        return $vars + $defaults;
    }

    /** Ensure OTP / transactional SMS bodies name the licensed institution. */
    private function ensureLicensedSmsIdentity(string $message): string
    {
        $legal = trim(brand_legal_name());
        if ($legal === '' || $message === '') {
            return $message;
        }

        if (stripos($message, $legal) !== false) {
            return $message;
        }

        return rtrim($message).' — '.$legal;
    }
}
