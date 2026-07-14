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

        $lines = preg_split("/\r\n|\n|\r/", (string) ($this->message ?: '')) ?: [];
        $title = trim($lines[0] ?? '') ?: __('borrower.notifications.fallback_title');
        $body = trim(implode(' ', array_slice($lines, 1))) ?: (string) ($this->message ?: $this->template ?: '');

        return [$title, $body];
    }
}
