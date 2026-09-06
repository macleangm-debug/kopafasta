<?php

namespace App\Console\Commands;

use App\Models\BrokenPage;
use App\Models\Setting;
use App\Services\BrokenPageClassifier;
use Illuminate\Console\Command;

class CloseBrokenPagesInventoryCommand extends Command
{
    protected $signature = 'broken-pages:close-inventory
        {--baseline : Reset the active monitoring baseline after closure}
        {--resolve-fixed= : Comma-separated incident IDs to resolve as fixed genuine defects}
        {--fix-note=Fixed and verified in release. : Resolution note for --resolve-fixed IDs}';

    protected $description = 'Classify open Broken Pages incidents, auto-resolve non-actionable noise, and optionally reset the monitoring baseline.';

    public function handle(BrokenPageClassifier $classifier): int
    {
        $classified = 0;
        $autoResolved = 0;

        BrokenPage::query()->whereNull('resolved_at')->orderBy('id')->chunkById(100, function ($rows) use ($classifier, &$classified, &$autoResolved): void {
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
                ];
                if ($result['auto_resolve']) {
                    $payload['resolved_at'] = now();
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
                ->whereNull('resolved_at')
                ->update([
                    'category' => 'genuine_defect',
                    'resolved_at' => now(),
                    'resolution_notes' => $note,
                    'classification_notes' => 'Genuine defect fixed and verified.',
                ]);
        }

        if ($this->option('baseline')) {
            Setting::set('broken_pages.baseline_at', now()->toIso8601String());
            $this->info('Monitoring baseline reset.');
        }

        $needsAttention = BrokenPage::query()->needsAttention()->count();
        $this->info("Classified {$classified}; auto-resolved {$autoResolved}; fixed {$fixed}; Needs Attention={$needsAttention}.");

        return self::SUCCESS;
    }
}
