<?php

namespace App\Console\Commands;

use App\Models\MarketplaceAsset;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class FixMarketplacePhotosCommand extends Command
{
    protected $signature = 'marketplace:fix-photos {--dry-run : Show changes without saving}';

    protected $description = 'Replace missing or dead marketplace photo URLs with bundled public images';

    /** @var array<string, string> */
    private array $byTitle = [
        'Commercial Sewing Machine' => '/images/marketplace/sewing.jpg',
        'Industrial Sewing Machine' => '/images/marketplace/industrial-sewing.jpg',
        'Maize Milling Plant' => '/images/marketplace/mill.jpg',
        'Passenger Bajaj Tuk-Tuk' => '/images/marketplace/bajaj.jpg',
        'Refrigerated Display Counter' => '/images/marketplace/fridge.jpg',
        'Toyota Probox 2018' => '/images/marketplace/probox.jpg',
        'Bajaj Boxer 150' => '/images/marketplace/boxer.jpg',
        'Toyota Hilux Double Cab 2022' => '/images/marketplace/hilux.jpg',
        'Isuzu D-Max 2021' => '/images/marketplace/dmax.jpg',
        'Solar Home System 500W' => '/images/marketplace/solar.jpg',
        'POS Terminal Bundle' => '/images/marketplace/pos.jpg',
        'Irrigation Water Pump Set' => '/images/marketplace/pump.jpg',
        'Business Smartphone Bundle' => '/images/marketplace/phone.jpg',
        'Business Laptop 14"' => '/images/marketplace/laptop.jpg',
        '14-Seater Mini Bus' => '/images/marketplace/minibus.jpg',
        'Delivery Motorcycle 150cc' => '/images/marketplace/motorcycle.jpg',
    ];

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $fixed = 0;

        MarketplaceAsset::query()->orderBy('id')->each(function (MarketplaceAsset $asset) use ($dry, &$fixed): void {
            $photos = array_values(array_filter((array) ($asset->photos ?? [])));
            $needsFix = $photos === [] || $this->photosBroken($photos);
            $mapped = $this->byTitle[$asset->title] ?? null;

            if (! $needsFix || ! $mapped) {
                // Keep uploaded storage photos; only replace dead remotes/missing public paths.
                if ($needsFix && $mapped === null) {
                    $this->warn("No mapped image for #{$asset->id} {$asset->title}");
                }

                return;
            }

            // Preserve extra uploaded storage photos after the cover when present.
            $kept = collect($photos)
                ->filter(fn ($path) => is_string($path) && $this->isExistingStoragePath($path))
                ->values()
                ->all();

            $next = array_values(array_unique(array_merge([$mapped], $kept)));
            $this->line(($dry ? '[dry] ' : '')."#{$asset->id} {$asset->title} -> ".json_encode($next));

            if (! $dry) {
                $asset->update(['photos' => $next]);
            }
            $fixed++;
        });

        $this->info(($dry ? 'Would fix ' : 'Fixed ').$fixed.' listing(s).');

        return self::SUCCESS;
    }

    /** @param list<mixed> $photos */
    private function photosBroken(array $photos): bool
    {
        foreach ($photos as $path) {
            if (! is_string($path) || $path === '') {
                continue;
            }
            if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
                // Legacy Unsplash demo URLs are unreliable (many 404).
                return true;
            }

            $normalized = ltrim($path, '/');
            if (str_starts_with($normalized, 'images/')) {
                if (! File::exists(public_path($normalized))) {
                    return true;
                }

                continue;
            }

            if (! $this->isExistingStoragePath($path)) {
                return true;
            }
        }

        return false;
    }

    private function isExistingStoragePath(string $path): bool
    {
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return false;
        }

        $normalized = ltrim($path, '/');
        if (str_starts_with($normalized, 'images/')) {
            return false;
        }

        return File::exists(storage_path('app/public/'.$normalized));
    }
}
