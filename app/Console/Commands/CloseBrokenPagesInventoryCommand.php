<?php

namespace App\Console\Commands;

use App\Models\BrokenPage;
use App\Models\Setting;
use App\Services\BrokenPageClassifier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CloseBrokenPagesInventoryCommand extends Command
{
    protected $signature = 'broken-pages:close-inventory
        {--baseline : Reset the active monitoring baseline after closure}
        {--resolve-fixed= : Comma-separated incident IDs to resolve as fixed genuine defects}
        {--fix-note=Fixed and verified in release. : Resolution note for --resolve-fixed IDs}';

    protected $description = 'Classify open Broken Pages incidents, auto-resolve non-actionable noise, and optionally reset the monitoring baseline.';

    public function handle(BrokenPageClassifier $classifier): int
    {
        $now = now();
        $openBefore = BrokenPage::query()->whereNull('resolved_at')->count();
        $this->info("Open before: {$openBefore}");

        // Bulk scanner/noise paths (fast path for large inventories).
        $scannerResolved = BrokenPage::query()
            ->whereNull('resolved_at')
            ->where(function ($q): void {
                $q->where('path', 'like', '/wp%')
                    ->orWhere('path', 'like', '/wordpress%')
                    ->orWhere('path', 'like', '/phpmyadmin%')
                    ->orWhere('path', 'like', '/.env%')
                    ->orWhere('path', 'like', '/cgi-bin/%')
                    ->orWhere('path', 'like', '/___proxy_subdomain%')
                    ->orWhere('path', 'like', '/actuator/%')
                    ->orWhere('path', 'like', '/telescope/%')
                    ->orWhere('path', 'like', '/ecp/%')
                    ->orWhere('path', 'like', '/%META-INF%')
                    ->orWhereIn('path', [
                        '/blog', '/old', '/new', '/newsite', '/test', '/testing', '/core', '/home',
                        '/console', '/server', '/server-status', '/file', '/files', '/uploads', '/open',
                        '/wp', '/wordpress', '/graphql', '/api', '/api/graphql', '/graphql/api', '/api/gql',
                        '/api/config', '/api/env', '/config.json', '/login.action', '/trace.axd',
                        '/@vite/env', '/debug/default/view', '/v2/_catalog', '/actuator/env',
                        '/telescope/requests',
                    ]);
            })
            ->update([
                'category' => 'scanner_bot',
                'classification_notes' => 'Scanner/bot or framework probe; retained for security history, not an app defect.',
                'resolved_at' => $now,
                'resolution_notes' => 'Classified as scanner/bot during Broken Pages closure pass.',
                'updated_at' => $now,
            ]);

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            $scannerResolved += BrokenPage::query()
                ->whereNull('resolved_at')
                ->whereRaw("path REGEXP '^/[a-f0-9]{16,}$'")
                ->update([
                    'category' => 'scanner_bot',
                    'classification_notes' => 'Scanner/bot or framework probe; retained for security history, not an app defect.',
                    'resolved_at' => $now,
                    'resolution_notes' => 'Classified as scanner/bot during Broken Pages closure pass.',
                    'updated_at' => $now,
                ]);
        }

        $securityResolved = BrokenPage::query()
            ->whereNull('resolved_at')
            ->whereIn('status', [403, 419, 429])
            ->where(function ($q): void {
                $q->where('path', '!=', '/borrower/setup-pin')
                    ->orWhere('method', '!=', 'POST')
                    ->orWhere('status', '!=', 403);
            })
            ->update([
                'category' => 'expected_security',
                'classification_notes' => 'Expected access, CSRF, or rate-limit response rather than a broken page.',
                'resolved_at' => $now,
                'resolution_notes' => 'Classified as expected security response during Broken Pages closure pass.',
                'updated_at' => $now,
            ]);

        $historicalResolved = BrokenPage::query()
            ->whereNull('resolved_at')
            ->where('status', 500)
            ->where(function ($q): void {
                $q->where('message', 'like', '%--columns%')
                    ->orWhere('message', 'like', '%option does not exist%');
            })
            ->update([
                'category' => 'historical',
                'classification_notes' => 'Console/deploy tooling exception mis-attributed to a web path.',
                'resolved_at' => $now,
                'resolution_notes' => 'Classified as historical/deploy noise during Broken Pages closure pass.',
                'updated_at' => $now,
            ]);

        $invalidResolved = BrokenPage::query()
            ->whereNull('resolved_at')
            ->where('status', 404)
            ->where(function ($q): void {
                $q->where('path', 'not like', '/borrower/%')
                    ->where('path', 'not like', '/partner/%')
                    ->where('path', 'not like', '/admin/%')
                    ->where('path', 'not like', '/staff/%')
                    ->where('path', 'not like', '/investor/%')
                    ->where('path', 'not like', '/apply%')
                    ->where('path', 'not like', '/membership%');
            })
            ->update([
                'category' => 'invalid_request',
                'classification_notes' => 'Unknown path with no matching route; not a tracked application page.',
                'resolved_at' => $now,
                'resolution_notes' => 'Classified as invalid external request during Broken Pages closure pass.',
                'updated_at' => $now,
            ]);

        // Remaining open rows: classify individually (should be a small set).
        $classified = 0;
        $autoResolved = 0;
        BrokenPage::query()->whereNull('resolved_at')->orderBy('id')->chunkById(200, function ($rows) use ($classifier, $now, &$classified, &$autoResolved): void {
            foreach ($rows as $row) {
                $result = $classifier->classify(
                    (string) $row->path,
                    (int) $row->status,
                    $row->exception,
                    $row->message,
                    $row->user_agent,
                    $row->method,
                );
                $classified++;
                $payload = [
                    'category' => $result['category'],
                    'classification_notes' => $result['notes'],
                    'updated_at' => $now,
                ];
                if ($result['auto_resolve']) {
                    $payload['resolved_at'] = $now;
                    $payload['resolution_notes'] = $row->resolution_notes ?: $result['notes'];
                    $autoResolved++;
                }
                $row->update($payload);
            }
        });

        $fixedIds = collect(explode(',', (string) $this->option('resolve-fixed')))
            ->map(fn ($id) => (int) trim($id))
            ->filter()
            ->values();

        $fixed = 0;
        if ($fixedIds->isNotEmpty()) {
            $note = (string) $this->option('fix-note');
            $fixed = BrokenPage::query()
                ->whereIn('id', $fixedIds->all())
                ->update([
                    'category' => 'genuine_defect',
                    'resolved_at' => $now,
                    'resolution_notes' => $note,
                    'classification_notes' => 'Genuine defect fixed and verified.',
                    'updated_at' => $now,
                ]);
        }

        // Any leftover open 500/503 on known fixed paths.
        $supportFixed = BrokenPage::query()
            ->whereNull('resolved_at')
            ->where('path', '/support')
            ->where('status', 500)
            ->update([
                'category' => 'genuine_defect',
                'resolved_at' => $now,
                'resolution_notes' => (string) $this->option('fix-note'),
                'classification_notes' => 'Support Blade Email@if compile defect fixed.',
                'updated_at' => $now,
            ]);

        $setupPinFixed = BrokenPage::query()
            ->whereNull('resolved_at')
            ->where('path', '/borrower/setup-pin')
            ->where('status', 403)
            ->update([
                'category' => 'genuine_defect',
                'resolved_at' => $now,
                'resolution_notes' => (string) $this->option('fix-note'),
                'classification_notes' => 'Setup-pin stale phase 403 fixed (server-state enrollment).',
                'updated_at' => $now,
            ]);

        if ($this->option('baseline')) {
            Setting::set('broken_pages.baseline_at', $now->toIso8601String());
            $this->info('Monitoring baseline reset.');
        }

        $needsAttention = BrokenPage::query()->needsAttention()->count();
        $this->info("Bulk scanner≈{$scannerResolved}, security={$securityResolved}, historical={$historicalResolved}, invalid={$invalidResolved}");
        $this->info("Remainder classified={$classified}, auto-resolved={$autoResolved}, fixed-ids={$fixed}, support={$supportFixed}, setup-pin={$setupPinFixed}");
        $this->info('Needs Attention='.$needsAttention);

        return self::SUCCESS;
    }
}
