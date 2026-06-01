<?php

if (! function_exists('brand')) {
    function brand(string $key, mixed $default = null): mixed
    {
        return config("branding.{$key}", $default);
    }
}

if (! function_exists('brand_name')) {
    function brand_name(): string
    {
        return (string) brand('app_name', 'Kopafasta');
    }
}

if (! function_exists('brand_title')) {
    function brand_title(string $page): string
    {
        return $page.' — '.brand_name();
    }
}
