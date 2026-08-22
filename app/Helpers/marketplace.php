<?php

if (! function_exists('marketplace_photo_url')) {
    function marketplace_photo_url(mixed $path): ?string
    {
        if (is_array($path)) {
            $path = $path['url'] ?? $path['path'] ?? ($path[0] ?? null);
        }

        if (! is_string($path) || blank($path)) {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        $normalized = ltrim($path, '/');

        // Public app assets (e.g. /images/marketplace/sewing.jpg), not storage disk paths.
        if (str_starts_with($normalized, 'images/')) {
            return asset($normalized);
        }

        return asset('storage/'.$normalized);
    }
}

if (! function_exists('marketplace_photo_urls')) {
    /** @param array<int, string|null> $photos */
    function marketplace_photo_urls(array $photos): array
    {
        return collect($photos)
            ->filter()
            ->map(fn (mixed $path) => marketplace_photo_url($path))
            ->filter()
            ->values()
            ->all();
    }
}
