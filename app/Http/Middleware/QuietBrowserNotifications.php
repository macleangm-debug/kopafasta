<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Block Web Notification / Push permission prompts so mobile browser
 * sessions stay as clean as a native app shell.
 */
class QuietBrowserNotifications
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $existing = (string) $response->headers->get('Permissions-Policy', '');
        $policy = 'notifications=(), push=()';
        $response->headers->set(
            'Permissions-Policy',
            $existing !== '' ? rtrim($existing, ', ').', '.$policy : $policy
        );

        return $response;
    }
}
