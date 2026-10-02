<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\NotificationLog;
use Illuminate\Support\Collection;

/**
 * Member Messages (Ujumbe) — persistent communications inbox.
 *
 * Distinct from Arifa/Notifications (event alerts) and Msaada/Support (conversations).
 * Reuses NotificationLog rows tagged meta.bucket = message (or template member_message*).
 * Growth can later deliver into the same store without a second communications UI.
 */
class MemberMessagesService
{
    public const BUCKET = 'message';

    public function query(Customer $customer)
    {
        return NotificationLog::query()
            ->where('customer_id', $customer->id)
            ->where('channel', 'in_app')
            ->where(function ($q) {
                $q->where('meta->bucket', self::BUCKET)
                    ->orWhere('template', 'like', 'member_message%');
            })
            ->latest();
    }

    /** @return Collection<int, NotificationLog> */
    public function list(Customer $customer, string $filter = 'all'): Collection
    {
        $query = $this->query($customer);
        if ($filter === 'unread') {
            $query->whereNull('read_at');
        }

        return $query->limit(100)->get();
    }

    public function unreadCount(Customer $customer): int
    {
        return (int) $this->query($customer)->whereNull('read_at')->count();
    }

    public function markRead(NotificationLog $message, Customer $customer): void
    {
        if ((int) $message->customer_id !== (int) $customer->id) {
            abort(404);
        }
        if ($message->read_at === null) {
            $message->forceFill(['read_at' => now()])->save();
        }
    }

    public function markAllRead(Customer $customer): void
    {
        $this->query($customer)->whereNull('read_at')->update(['read_at' => now()]);
    }

    /**
     * Optional deliver helper for future Growth — keeps bucket semantics in one place.
     *
     * @param  array{title?: string, body: string, source?: string, action_url?: string, action_label?: string, template?: string}  $payload
     */
    public function deliver(Customer $customer, array $payload): NotificationLog
    {
        $title = trim((string) ($payload['title'] ?? ''));
        $body = trim((string) ($payload['body'] ?? ''));
        $source = trim((string) ($payload['source'] ?? 'Kopafasta'));
        $actionUrl = trim((string) ($payload['action_url'] ?? ''));
        $actionLabel = trim((string) ($payload['action_label'] ?? ''));

        $meta = [
            'bucket' => self::BUCKET,
            'source' => $source !== '' ? $source : 'Kopafasta',
        ];
        if ($actionLabel !== '') {
            $meta['action_label'] = $actionLabel;
        }

        $row = [
            'customer_id' => $customer->id,
            'channel' => 'in_app',
            'category' => 'system',
            'template' => $payload['template'] ?? 'member_message',
            'recipient' => $actionUrl !== '' ? $actionUrl : (string) ($customer->phone ?: $customer->email ?: 'in_app'),
            'message' => \Illuminate\Support\Str::limit(trim(($title !== '' ? $title."\n" : '').$body), 800, ''),
            'status' => 'sent',
            'sent_at' => now(),
            'meta' => $meta,
        ];

        if (\Illuminate\Support\Facades\Schema::hasColumn('notification_logs', 'user_id') && $customer->user_id) {
            $row['user_id'] = $customer->user_id;
        }
        if ($actionUrl !== '' && \Illuminate\Support\Facades\Schema::hasColumn('notification_logs', 'action_url')) {
            $row['action_url'] = $actionUrl;
        }

        return NotificationLog::create($row);
    }

    public function sourceLabel(NotificationLog $message): string
    {
        $meta = is_array($message->meta) ? $message->meta : [];
        $source = trim((string) ($meta['source'] ?? ''));
        if ($source !== '') {
            return $source;
        }

        return 'Kopafasta';
    }
}
