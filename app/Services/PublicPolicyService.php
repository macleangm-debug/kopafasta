<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class PublicPolicyService
{
    public const KEYS = [
        'responsible_lending',
        'complaints',
        'aml',
        'kyc',
    ];

    /** @return array<string, mixed>|null */
    public function get(string $key): ?array
    {
        if (! in_array($key, self::KEYS, true)) {
            return null;
        }

        $stored = Setting::get("policy.{$key}");
        $defaults = $this->defaultsFromFile($key);
        $merged = array_merge($defaults ?? [], is_array($stored) ? $stored : []);

        if (($merged['status'] ?? 'draft') !== 'published' && ($merged['visibility'] ?? 'public') === 'public') {
            // Still allow draft preview for admins via explicit flag later; public pages need published.
        }

        return $merged;
    }

    /** @return list<array<string, mixed>> */
    public function publishedPublic(): array
    {
        $rows = [];
        foreach (self::KEYS as $key) {
            $row = $this->get($key);
            if (! $row) {
                continue;
            }
            if (($row['status'] ?? '') !== 'published') {
                continue;
            }
            if (($row['visibility'] ?? 'public') !== 'public') {
                continue;
            }
            $rows[] = $row + ['key' => $key];
        }

        return $rows;
    }

    public function localized(string $key, ?string $locale = null): ?array
    {
        $row = $this->get($key);
        if (! $row) {
            return null;
        }
        $locale = $locale === 'sw' ? 'sw' : 'en';
        $title = (string) ($row["title_{$locale}"] ?: $row['title_en'] ?? '');
        $body = (string) ($row["body_{$locale}"] ?: $row['body_en'] ?? '');

        return [
            'key' => $key,
            'title' => $title,
            'body' => $body,
            'version' => (string) ($row['version'] ?? '1.0'),
            'effective_date' => $row['effective_date'] ?? null,
            'review_date' => $row['review_date'] ?? null,
            'status' => (string) ($row['status'] ?? 'draft'),
            'visibility' => (string) ($row['visibility'] ?? 'public'),
            'updated_at' => $row['updated_at'] ?? null,
        ];
    }

    public function isPubliclyViewable(string $key): bool
    {
        $row = $this->get($key);
        if (! $row) {
            return false;
        }

        return ($row['status'] ?? '') === 'published'
            && ($row['visibility'] ?? 'public') === 'public';
    }

    /** @return array<string, mixed>|null */
    public function defaultsFromFile(string $key): ?array
    {
        $path = resource_path("policies/{$key}.md");
        if (! File::exists($path)) {
            return null;
        }
        $raw = File::get($path);
        $meta = [
            'title_en' => Str::headline(str_replace('_', ' ', $key)),
            'title_sw' => Str::headline(str_replace('_', ' ', $key)),
        ];
        if (preg_match('/^---\s*(.*?)\s*---/s', $raw, $m)) {
            foreach (preg_split('/\R/', trim($m[1])) as $line) {
                if (! str_contains($line, ':')) {
                    continue;
                }
                [$k, $v] = array_map('trim', explode(':', $line, 2));
                $meta[$k] = $v;
            }
            $raw = substr($raw, strlen($m[0]));
        }
        $en = '';
        $sw = '';
        if (preg_match('/# ENGLISH\s*(.*?)(?:# KISWAHILI\s*(.*))?$/s', $raw, $parts)) {
            $en = trim($parts[1] ?? '');
            $sw = trim($parts[2] ?? '');
        }

        return [
            'key' => $key,
            'title_en' => $meta['title_en'] ?? '',
            'title_sw' => $meta['title_sw'] ?? '',
            'body_en' => $en,
            'body_sw' => $sw,
            'version' => '1.0',
            'effective_date' => now()->toDateString(),
            'review_date' => now()->addYear()->toDateString(),
            'status' => 'published',
            'visibility' => 'public',
            'updated_at' => now()->toDateTimeString(),
        ];
    }

    public function publishDefaults(): void
    {
        foreach (self::KEYS as $key) {
            $defaults = $this->defaultsFromFile($key);
            if (! $defaults) {
                continue;
            }
            $existing = Setting::get("policy.{$key}");
            if (is_array($existing) && filled($existing['body_en'] ?? null)) {
                // Keep admin edits; only backfill missing bodies.
                $merged = array_merge($defaults, array_filter($existing, fn ($v) => $v !== null && $v !== ''));
                if (! filled($existing['body_en'] ?? null)) {
                    $merged['body_en'] = $defaults['body_en'];
                }
                if (! filled($existing['body_sw'] ?? null)) {
                    $merged['body_sw'] = $defaults['body_sw'];
                }
                Setting::set("policy.{$key}", $merged);
                continue;
            }
            Setting::set("policy.{$key}", $defaults);
        }
    }

    /** Simple markdown-ish to HTML for policy pages (headings + lists + paragraphs). */
    public function renderHtml(string $markdown): string
    {
        $lines = preg_split('/\R/', $markdown) ?: [];
        $html = [];
        $inList = false;
        foreach ($lines as $line) {
            $trim = rtrim($line);
            if (preg_match('/^###\s+(.+)$/', $trim, $m)) {
                if ($inList) {
                    $html[] = '</ul>';
                    $inList = false;
                }
                $html[] = '<h3 class="mt-8 text-base font-bold text-gray-900">'.e($m[1]).'</h3>';
                continue;
            }
            if (preg_match('/^##\s+(.+)$/', $trim, $m)) {
                if ($inList) {
                    $html[] = '</ul>';
                    $inList = false;
                }
                $html[] = '<h2 class="mt-10 text-lg font-bold text-gray-900">'.e($m[1]).'</h2>';
                continue;
            }
            if (preg_match('/^#\s+(.+)$/', $trim, $m)) {
                if ($inList) {
                    $html[] = '</ul>';
                    $inList = false;
                }
                $html[] = '<h1 class="text-2xl font-extrabold text-gray-900">'.e($m[1]).'</h1>';
                continue;
            }
            if (preg_match('/^\*\s+(.+)$/', $trim, $m) || preg_match('/^\d+\.\s+\*\*(.+?)\*\*:\s*(.*)$/', $trim, $m2) || preg_match('/^\d+\.\s+(.+)$/', $trim, $m3)) {
                if (! $inList) {
                    $html[] = '<ul class="mt-3 space-y-2 list-disc pl-5 text-sm text-gray-700">';
                    $inList = true;
                }
                if (isset($m2) && $m2) {
                    $html[] = '<li><strong>'.e($m2[1]).':</strong> '.e($m2[2]).'</li>';
                    $m2 = null;
                } elseif (isset($m3) && $m3 && ! isset($m[1])) {
                    $html[] = '<li>'.e($m3[1]).'</li>';
                } else {
                    $html[] = '<li>'.e($m[1]).'</li>';
                }
                continue;
            }
            if ($trim === '' || $trim === '---') {
                if ($inList) {
                    $html[] = '</ul>';
                    $inList = false;
                }
                continue;
            }
            if ($inList) {
                $html[] = '</ul>';
                $inList = false;
            }
            $rich = e($trim);
            $rich = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $rich) ?: $rich;
            $html[] = '<p class="mt-3 text-sm sm:text-[15px] leading-relaxed text-gray-700">'.$rich.'</p>';
        }
        if ($inList) {
            $html[] = '</ul>';
        }

        return implode("\n", $html);
    }
}
