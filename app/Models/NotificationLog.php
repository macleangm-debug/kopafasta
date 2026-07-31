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

            if ($title !== '' || $body !== '') {
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
        ];
        $template = (string) ($this->template ?? '');
        if (isset($templateMap[$template])) {
            $params = is_array($meta['params'] ?? null) ? $meta['params'] : [];
            $title = (string) __($templateMap[$template]['title'], $params);
            $bodyKey = $templateMap[$template]['body'] ?? null;
            if ($bodyKey) {
                $translated = (string) __($bodyKey, $params);
                // Only use translation when params exist or key clearly resolved.
                if ($translated !== $bodyKey || $params !== []) {
                    return [$title, $translated !== $bodyKey ? $translated : trim(implode(' ', array_slice(
                        preg_split("/\r\n|\n|\r/", (string) ($this->message ?: '')) ?: [],
                        1
                    )))];
                }
            }
            $lines = preg_split("/\r\n|\n|\r/", (string) ($this->message ?: '')) ?: [];
            $body = trim(implode(' ', array_slice($lines, 1))) ?: (string) ($this->message ?: '');

            return [$title, $body];
        }

        $lines = preg_split("/\r\n|\n|\r/", (string) ($this->message ?: '')) ?: [];
        $title = trim($lines[0] ?? '') ?: __('borrower.notifications.fallback_title');
        $body = trim(implode(' ', array_slice($lines, 1))) ?: (string) ($this->message ?: $this->template ?: '');

        return [$title, $body];
    }
}
