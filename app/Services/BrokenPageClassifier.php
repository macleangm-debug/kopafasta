<?php

namespace App\Services;

/**
 * Classifies broken-page incidents for the Needs Attention queue.
 *
 * Categories:
 * - genuine_defect
 * - broken_link
 * - historical
 * - expected_security
 * - scanner_bot
 * - invalid_request
 * - duplicate
 */
class BrokenPageClassifier
{
    public const NEEDS_ATTENTION = ['genuine_defect', 'broken_link'];

    /**
     * @return array{category: string, notes: string, auto_resolve: bool}
     */
    public function classify(string $path, int $status, ?string $exception = null, ?string $message = null, ?string $userAgent = null, ?string $method = null): array
    {
        $path = '/'.ltrim(mb_strtolower($path), '/');
        if ($path === '//') {
            $path = '/';
        }
        $message = (string) $message;
        $ua = mb_strtolower((string) $userAgent);

        if ($this->isScannerPath($path) || $this->isScannerUa($ua, $status)) {
            return [
                'category' => 'scanner_bot',
                'notes' => 'Scanner/bot or framework probe; retained for security history, not an app defect.',
                'auto_resolve' => true,
            ];
        }

        if ($status === 500 && (
            str_contains($message, '--columns')
            || str_contains($message, 'option does not exist')
            || ($path === '/' && str_contains($ua, 'symfony') && str_contains((string) $exception, 'RuntimeException'))
        )) {
            return [
                'category' => 'historical',
                'notes' => 'Console/deploy tooling exception mis-attributed to a web path; not a browser home failure.',
                'auto_resolve' => true,
            ];
        }

        if (in_array($status, [403, 419, 429], true)) {
            if ($path === '/borrower/setup-pin' && strtoupper((string) $method) === 'POST' && $status === 403) {
                return [
                    'category' => 'genuine_defect',
                    'notes' => 'Borrower setup-pin POST aborted; signup/onboarding defect until fixed.',
                    'auto_resolve' => false,
                ];
            }

            return [
                'category' => 'expected_security',
                'notes' => 'Expected access, CSRF, or rate-limit response rather than a broken page.',
                'auto_resolve' => true,
            ];
        }

        if (in_array($status, [500, 503], true)) {
            return [
                'category' => 'genuine_defect',
                'notes' => 'Server error on an application route; requires investigation and fix.',
                'auto_resolve' => false,
            ];
        }

        if ($status === 404) {
            if ($this->looksLikeBrokenInternalPath($path)) {
                return [
                    'category' => 'broken_link',
                    'notes' => 'Possible broken internal path; verify navigation/CTA source.',
                    'auto_resolve' => false,
                ];
            }

            return [
                'category' => 'invalid_request',
                'notes' => 'Unknown path with no matching route; not a tracked application page.',
                'auto_resolve' => true,
            ];
        }

        return [
            'category' => 'genuine_defect',
            'notes' => 'Unclassified tracked failure; treat as needs attention until reviewed.',
            'auto_resolve' => false,
        ];
    }

    public function isScannerPath(string $path): bool
    {
        $lower = '/'.ltrim(mb_strtolower($path), '/');

        $exact = [
            '/wp', '/wordpress', '/wp-admin', '/wp-login.php', '/wp-login', '/xmlrpc.php',
            '/blog', '/old', '/new', '/newsite', '/test', '/testing', '/core', '/home',
            '/console', '/server', '/server-status', '/file', '/files', '/uploads', '/open',
            '/phpmyadmin', '/pma', '/adminer', '/.env', '/config.json', '/api/config', '/api/env',
            '/actuator/env', '/telescope/requests', '/trace.axd', '/@vite/env',
            '/debug/default/view', '/v2/_catalog', '/graphql', '/api/graphql', '/graphql/api', '/api/gql', '/api',
            '/login.action',
        ];
        if (in_array($lower, $exact, true)) {
            return true;
        }

        foreach ([
            '/wp-', '/wordpress', '/wp-content', '/wp-includes', '/wp-json',
            '/phpmyadmin', '/.env', '/cgi-bin/', '/vendor/phpunit',
            '/___proxy_subdomain', '/ecp/current', '/meta-inf/', '/actuator/',
            '/telescope/', '/_profiler', '/phpinfo',
        ] as $needle) {
            if (str_contains($lower, $needle)) {
                return true;
            }
        }

        // Random hex probe segments (e.g. /87e947b6df06d311a5a716b4)
        if (preg_match('#^/[a-f0-9]{16,}$#', $lower) === 1) {
            return true;
        }

        return false;
    }

    private function isScannerUa(string $ua, int $status): bool
    {
        if ($status !== 404 || $ua === '') {
            return false;
        }

        return preg_match('/bot|crawler|spider|slurp|bytespider|semrush|ahrefs|bingpreview|facebookexternalhit|zgrab|masscan|nuclei|httpx/i', $ua) === 1;
    }

    private function looksLikeBrokenInternalPath(string $path): bool
    {
        foreach (['/borrower/', '/partner/', '/admin/', '/staff/', '/investor/', '/apply', '/membership'] as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
