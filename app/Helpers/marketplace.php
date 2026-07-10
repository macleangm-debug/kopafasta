<?php

if (! function_exists('marketplace_photo_url')) {
    function marketplace_photo_url(?string $path): ?string
    {
        if (blank($path)) {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return asset('storage/'.ltrim($path, '/'));
    }
}

if (! function_exists('marketplace_photo_urls')) {
    /** @param array<int, string|null> $photos */
    function marketplace_photo_urls(array $photos): array
    {
        return collect($photos)
            ->filter()
            ->map(fn (string $path) => marketplace_photo_url($path))
            ->filter()
            ->values()
            ->all();
    }
}
