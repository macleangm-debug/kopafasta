<?php

namespace App\Services;

use Illuminate\Support\Carbon;

class ReleaseInfoService
{
    /**
     * @return array{
     *     environment: string,
     *     label: string,
     *     version: string,
     *     commit: string|null,
     *     short_commit: string|null,
     *     deployed_at: string|null,
     *     deployed_at_display: string|null,
     *     app_url: string,
     *     debug: bool
     * }
     */
    public function snapshot(): array
    {
        $env = $this->environment();
        $commit = $this->commit();
        $deployed = $this->deployedAt();

        return [
            'environment' => $env,
            'label' => $this->label(),
            'version' => $this->version(),
            'commit' => $commit,
            'short_commit' => $commit ? substr($commit, 0, 8) : null,
            'deployed_at' => $deployed,
            'deployed_at_display' => $deployed
                ? Carbon::parse($deployed)->timezone(config('app.timezone', 'UTC'))->format('j M Y · H:i')
                : null,
            'app_url' => (string) config('app.url'),
            'debug' => (bool) config('app.debug'),
        ];
    }

    public function environment(): string
    {
        $env = (string) app()->environment();

        return $env !== '' ? $env : 'local';
    }

    public function isStaging(): bool
    {
        if (app()->environment('staging')) {
            return true;
        }

        $host = strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));

        return str_starts_with($host, 'staging.');
    }

    public function isProduction(): bool
    {
        return app()->isProduction() && ! $this->isStaging();
    }

    public function label(): string
    {
        return match ($this->environment()) {
            'production' => $this->isStaging() ? 'STAGING' : 'Production',
            'staging' => 'STAGING',
            'local' => 'Local',
            'testing' => 'Testing',
            default => strtoupper($this->environment()),
        };
    }

    public function showsBanner(): bool
    {
        if ($this->isProduction()) {
            return false;
        }

        $forced = config('release.show_banner');
        if ($forced !== null && $forced !== '') {
            return filter_var($forced, FILTER_VALIDATE_BOOLEAN);
        }

        return $this->isStaging();
    }

    public function version(): string
    {
        return (string) ($this->releaseFile()['version'] ?? config('release.version') ?? 'dev');
    }

    public function commit(): ?string
    {
        $fromFile = $this->releaseFile()['commit'] ?? null;
        if (filled($fromFile)) {
            return (string) $fromFile;
        }

        $fromEnv = config('release.commit');
        if (filled($fromEnv)) {
            return (string) $fromEnv;
        }

        return $this->gitHead();
    }

    public function deployedAt(): ?string
    {
        $fromFile = $this->releaseFile()['deployed_at'] ?? null;
        if (filled($fromFile)) {
            return (string) $fromFile;
        }

        $fromEnv = config('release.deployed_at');

        return filled($fromEnv) ? (string) $fromEnv : null;
    }

    /** @return array<string, mixed> */
    private function releaseFile(): array
    {
        $path = (string) config('release.file', storage_path('app/release.json'));
        if (! is_file($path)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : [];
    }

    private function gitHead(): ?string
    {
        $gitDir = base_path('.git');
        if (! is_dir($gitDir) && ! is_file($gitDir)) {
            return null;
        }

        $head = @file_get_contents(base_path('.git/HEAD'));
        if (! is_string($head)) {
            return null;
        }

        $head = trim($head);
        if (str_starts_with($head, 'ref: ')) {
            $ref = trim(substr($head, 5));
            $refFile = base_path('.git/'.$ref);
            if (is_file($refFile)) {
                return trim((string) file_get_contents($refFile)) ?: null;
            }
        }

        return strlen($head) >= 7 ? $head : null;
    }
}
