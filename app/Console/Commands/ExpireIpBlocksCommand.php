<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class ExpireIpBlocksCommand extends Command
{
    protected $signature = 'security:expire-blocks';

    protected $description = 'Sweep auth.ip_blocked audits whose blocked_until has passed and emit auth.ip_block_expired events.';

    public function handle(): int
    {
        $expired = 0;

        // Look back over the last 7 days to keep the scan bounded.
        $rows = AuditLog::query()
            ->where('event', 'auth.ip_blocked')
            ->where('created_at', '>=', now()->subDays(7))
            ->orderByDesc('id')
            ->get(['id', 'ip_address', 'new_values', 'created_at']);

        // Track per-IP latest block so we only emit one expiry per active window.
        $seen = [];
        foreach ($rows as $row) {
            $ip = $row->ip_address;
            if (! $ip || isset($seen[$ip])) {
                continue;
            }
            $seen[$ip] = true;

            $payload = json_decode((string) $row->new_values, true) ?: [];
            $blockedUntil = isset($payload['blocked_until'])
                ? Carbon::parse($payload['blocked_until'])
                : $row->created_at?->copy()?->addHour();

            if (! $blockedUntil || $blockedUntil->isFuture()) {
                continue;
            }

            // Skip if we already recorded an expiry for this exact block.
            $alreadyExpired = AuditLog::where('event', 'auth.ip_block_expired')
                ->where('ip_address', $ip)
                ->where('created_at', '>=', $row->created_at)
                ->exists();
            if ($alreadyExpired) {
                continue;
            }

            Cache::forget('sec:ip_blocked:'.$ip);
            Cache::forget('sec:ip_blocked:'.$ip.':expires');

            AuditLog::create([
                'user_id' => null,
                'event' => 'auth.ip_block_expired',
                'auditable_type' => null,
                'auditable_id' => null,
                'old_values' => null,
                'new_values' => json_encode([
                    'ip_address' => $ip,
                    'blocked_at' => $row->created_at?->toIso8601String(),
                    'blocked_until' => $blockedUntil->toIso8601String(),
                ]),
                'ip_address' => $ip,
                'user_agent' => 'artisan',
            ]);

            $expired++;
        }

        $this->info("Expired {$expired} IP block(s).");

        return self::SUCCESS;
    }
}
