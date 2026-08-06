<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationLog extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'read_at' => 'datetime',
            'meta'    => 'array',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }

    /** Title resolved at read time so locale switches re-translate stored keys. */
    public function displayTitle(): string
    {
        [$title] = $this->resolvedContent();

        return $title;
    }

    /** Body resolved at read time so locale switches re-translate stored keys. */
    public function displayBody(): string
    {
        [, $body] = $this->resolvedContent();

        return $body;
    }

    /** @return array{0: string, 1: string} */
    public function resolvedContent(): array
    {
        $meta = is_array($this->meta) ? $this->meta : [];
        $params = is_array($meta['params'] ?? null) ? $meta['params'] : [];

        if (filled($meta['title_key'] ?? null) || filled($meta['body_key'] ?? null)) {
            $title = filled($meta['title_key'] ?? null)
                ? (string) __($meta['title_key'], $params)
                : '';
            $body = filled($meta['body_key'] ?? null)
                ? (string) __($meta['body_key'], $params)
                : '';

            $unresolved = static fn (string $text): bool => (bool) preg_match('/:\w+/', $text);

            // Keys without params leave raw :placeholders — prefer stored message when that happens.
            if (($title !== '' || $body !== '') && ! $unresolved($title) && ! $unresolved($body)) {
                return [
                    $title !== '' ? $title : __('borrower.notifications.fallback_title'),
                    $body,
                ];
            }
        }

        // Legacy rows without i18n keys — rematch known templates so locale switches still work.
        $templateMap = [
            'guarantor_sent' => [
                'title' => 'borrower.guarantor_invite.notify_sent_title',
                'body'  => 'borrower.guarantor_invite.borrower_sent',
            ],
            'guarantor_declined' => [
                'title' => 'borrower.guarantor_invite.notify_declined_title',
                'body'  => 'borrower.guarantor_invite.borrower_declined',
            ],
            'guarantor_request' => [
                'title' => 'borrower.guarantor_invite.notify_request_title',
                'body'  => 'borrower.guarantor_invite.guarantor_received',
            ],
            'loyalty_points_earned' => [
                'title' => 'borrower.rewards.points_earned_title',
                'body'  => 'borrower.rewards.points_earned_body',
            ],
            'membership_issued' => [
                'title' => 'borrower.membership.notification_title',
                'body'  => 'borrower.membership.notification_body',
            ],
        ];
        $template = (string) ($this->template ?? '');
        if (isset($templateMap[$template])) {
            $params = is_array($meta['params'] ?? null) ? $meta['params'] : [];
            if ($template === 'loyalty_points_earned' && ! isset($params['points'])) {
                if (preg_match('/\b(\d[\d,]*)\b/', (string) $this->message, $m)) {
                    $params['points'] = $m[1];
                }
            }
            if ($template === 'membership_issued' && ! isset($params['member_no'])) {
                if (preg_match('/\(([A-Z0-9\-]+)\)/', (string) $this->message, $m)) {
                    $params['member_no'] = $m[1];
                } else {
                    $params['member_no'] = '';
                }
            }
            $title = (string) __($templateMap[$template]['title'], $params);
            $bodyKey = $templateMap[$template]['body'] ?? null;
            $unresolved = static fn (string $text): bool => (bool) preg_match('/:\w+/', $text);

            if ($bodyKey) {
                $translated = (string) __($bodyKey, $params);
                if ($translated !== $bodyKey && ! $unresolved($translated) && ! $unresolved($title)) {
                    return [$title, $translated];
                }
            }

            $legacy = $this->legacyBody();
            if ($legacy !== '' && ! $unresolved($legacy)) {
                return [
                    $unresolved($title) ? __('borrower.notifications.fallback_title') : $title,
                    $legacy,
                ];
            }

            return [
                $unresolved($title) ? __('borrower.notifications.fallback_title') : $title,
                $legacy !== '' ? $legacy : ($bodyKey ? (string) __($bodyKey, $params) : ''),
            ];
        }

        $lines = preg_split("/\r\n|\n|\r/", (string) ($this->message ?: '')) ?: [];
        $title = $this->stripSkippedPrefix(trim($lines[0] ?? '')) ?: __('borrower.notifications.fallback_title');
        $body = $this->stripSkippedPrefix(trim(implode(' ', array_slice($lines, 1)))) ?: $this->stripSkippedPrefix((string) ($this->message ?: $this->template ?: ''));

        return [$title, $body];
    }

    private function legacyBody(): string
    {
        $lines = preg_split("/\r\n|\n|\r/", (string) ($this->message ?: '')) ?: [];
        $body = trim(implode(' ', array_slice($lines, 1))) ?: (string) ($this->message ?: '');

        return $this->stripSkippedPrefix($body);
    }

    private function stripSkippedPrefix(string $text): string
    {
        return trim((string) preg_replace('/^\[skipped\]\s*/i', '', $text));
    }
}
