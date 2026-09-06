<?php

namespace App\Services\Mail;

use App\Models\Setting;

/**
 * Applies Settings → SMS / Email gateway SMTP credentials to Laravel mail config.
 * One email delivery engine for the platform (borrowers, partners, staff).
 */
class GatewayMailConfigurator
{
    public function apply(): void
    {
        $g = Setting::group('gateway');
        $host = trim((string) ($g['email_smtp_host'] ?? ''));
        if ($host === '') {
            return;
        }

        $encryption = strtolower(trim((string) ($g['email_encryption'] ?? 'tls')));
        if (! in_array($encryption, ['tls', 'ssl', ''], true)) {
            $encryption = 'tls';
        }

        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.transport' => 'smtp',
            'mail.mailers.smtp.host' => $host,
            'mail.mailers.smtp.port' => (int) ($g['email_smtp_port'] ?? 587),
            'mail.mailers.smtp.username' => $g['email_smtp_user'] ?? null,
            'mail.mailers.smtp.password' => $g['email_smtp_pass'] ?? null,
            'mail.mailers.smtp.encryption' => $encryption !== '' ? $encryption : null,
        ]);

        if (filled($g['email_from_address'] ?? null)) {
            config(['mail.from.address' => $g['email_from_address']]);
        }
        if (filled($g['email_from_name'] ?? null)) {
            config(['mail.from.name' => $g['email_from_name']]);
        }
    }

    public function isConfigured(): bool
    {
        $g = Setting::group('gateway');

        return filled($g['email_smtp_host'] ?? null) && filled($g['email_from_address'] ?? null);
    }
}
