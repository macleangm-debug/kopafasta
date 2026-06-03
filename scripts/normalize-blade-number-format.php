<?php

/**
 * One-off: replace number_format() in Blade with format_number() / format_money().
 */
$dirs = [
    __DIR__.'/../resources/views',
];

$patterns = [
    // TZS {{ number_format($x, 2) }} → {{ format_money($x, true, 2) }}
    '/TZS\s*\{\{\s*number_format\s*\(\s*([^,)]+)\s*,\s*(\d+)\s*\)\s*\}\}/' => '{{ format_money($1, true, $2) }}',
    '/TZS\s*\{\{\s*number_format\s*\(\s*([^)]+)\s*\)\s*\}\}/' => '{{ format_money($1) }}',
    // {{ number_format(...) }}
    '/\{\{\s*number_format\s*\(\s*([^,)]+)\s*,\s*(\d+)\s*\)\s*\}\}/' => '{{ format_number($1, $2) }}',
    '/\{\{\s*number_format\s*\(\s*([^)]+)\s*\)\s*\}\}/' => '{{ format_number($1) }}',
    // number_format in strings / concatenation (Blade echo)
    '/number_format\s*\(\s*([^,)]+)\s*,\s*(\d+)\s*\)/' => 'format_number($1, $2)',
    '/number_format\s*\(\s*([^)]+)\s*\)/' => 'format_number($1)',
];

$changed = 0;
foreach ($dirs as $dir) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    foreach ($it as $file) {
        if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.blade.php')) {
            continue;
        }
        $path = $file->getPathname();
        $original = file_get_contents($path);
        $content = $original;
        foreach ($patterns as $regex => $replacement) {
            $content = preg_replace($regex, $replacement, $content);
        }
        if ($content !== $original) {
            file_put_contents($path, $content);
            $changed++;
            echo "Updated: {$path}\n";
        }
    }
}

echo "Done. {$changed} files updated.\n";
