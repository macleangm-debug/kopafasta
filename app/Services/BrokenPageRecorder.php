<?php

namespace App\Services;

use App\Models\BrokenPage;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Incident inventory for genuine exception/status failures.
 *
 * This is not a visit logger: valid application routes never call record(),
 * and even among failures only 403/404/419/429/500/503 are stored, with
 * fingerprint deduplication and noise filtering (assets, bots, probes).
 */
class BrokenPageRecorder
{
    /** @var list<int> */
    public const TRACKED_STATUSES = [403, 404, 419, 429, 500, 503];

    public function record(Throwable $e, ?Request $request = null): void
    {
        if ($e instanceof ValidationException || $e instanceof AuthenticationException) {
            return;
        }

        try {
            if (! Schema::hasTable('broken_pages')) {
                return;
            }

            $request ??= request();
            if ($request->attributes->get('kf_broken_page_recorded')) {
                return;
            }

            $status = $this->statusFor($e);
            if (! in_array($status, self::TRACKED_STATUSES, true)) {
                return;
            }

            $path = '/'.ltrim((string) $request->path(), '/');
            if ($path === '/') {
                $path = '/';
            }
            if ($this->isNoise($request, $path, $status)) {
                return;
            }

            $request->attributes->set('kf_broken_page_recorded', true);

            $fingerprint = hash('sha256', strtoupper($request->method()).'|'.mb_strtolower($path).'|'.$status);
            $now = now();
            $existing = BrokenPage::query()
                ->where('fingerprint', $fingerprint)
                ->whereNull('resolved_at')
                ->first();

            if ($existing) {
                $existing->update([
                    'occurrence_count' => (int) $existing->occurrence_count + 1,
                    'last_seen_at' => $now,
                    'url' => mb_substr($request->fullUrl(), 0, 500),
                    'user_id' => Auth::id() ?: $existing->user_id,
                    'user_role' => $this->safeRole() ?: $existing->user_role,
                    'ip_address' => mb_substr((string) $request->ip(), 0, 45),
                    'user_agent' => mb_substr((string) $request->userAgent(), 0, 500),
                    'locale' => mb_substr((string) app()->getLocale(), 0, 10),
                    'referrer' => mb_substr((string) $request->headers->get('referer'), 0, 500) ?: $existing->referrer,
                ]);

                return;
            }

            BrokenPage::query()->create([
                'fingerprint' => $fingerprint,
                'url' => mb_substr($request->fullUrl(), 0, 500),
                'path' => mb_substr($path, 0, 255),
                'method' => mb_substr($request->method(), 0, 10),
                'status' => $status,
                'exception' => mb_substr($e::class, 0, 191),
                'message' => mb_substr($this->safeMessage($e), 0, 1000),
                'referrer' => mb_substr((string) $request->headers->get('referer'), 0, 500) ?: null,
                'user_id' => Auth::id(),
                'user_role' => $this->safeRole(),
                'ip_address' => mb_substr((string) $request->ip(), 0, 45),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 500),
                'locale' => mb_substr((string) app()->getLocale(), 0, 10),
                'occurrence_count' => 1,
                'first_seen_at' => $now,
                'last_seen_at' => $now,
            ]);
        } catch (Throwable) {
            // Never let follow-up logging break exception handling.
        }
    }

    public function statusFor(Throwable $e): int
    {
        if ($e instanceof HttpExceptionInterface) {
            return $e->getStatusCode();
        }
        if ($e instanceof AuthorizationException) {
            return 403;
        }
        if ($e instanceof TokenMismatchException) {
            return 419;
        }
        if ($e instanceof ModelNotFoundException) {
            return 404;
        }

        return 500;
    }

    private function isNoise(Request $request, string $path, int $status): bool
    {
        $lower = mb_strtolower($path);
        if (in_array($lower, ['/up', '/favicon.ico', '/robots.txt', '/apple-touch-icon.png', '/apple-touch-icon-precomposed.png'], true)) {
            return true;
        }

        foreach (['/build/', '/livewire/', '/webhooks/', '/.well-known/', '/cgi-bin/', '/wp-admin/', '/wp-login'] as $prefix) {
            if (str_starts_with($lower, $prefix)) {
                return true;
            }
        }

        if (preg_match('/\.(ico|png|jpe?g|gif|webp|svg|css|js|map|woff2?|ttf|eot|txt)$/i', $lower) === 1) {
            return true;
        }

        if ($status === 404) {
            $ua = mb_strtolower((string) $request->userAgent());
            if ($ua !== '' && preg_match('/bot|crawler|spider|slurp|bytespider|semrush|ahrefs|bingpreview|facebookexternalhit/i', $ua) === 1) {
                return true;
            }
        }

        return false;
    }

    private function safeRole(): ?string
    {
        $role = Auth::user()?->role;

        return is_string($role) && $role !== '' ? mb_substr($role, 0, 40) : null;
    }

    private function safeMessage(Throwable $e): string
    {
        $message = trim(preg_replace('/\s+/', ' ', $e->getMessage()) ?? '');
        $message = preg_replace('/(?:\/[^\s:]+)+/', '[path]', $message) ?? $message;

        return $message === '' ? $e::class : $message;
    }
}
