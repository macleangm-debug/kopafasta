<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RestrictConsoleSettings
{
    public function handle(Request $request, Closure $next): Response
    {
        $path = $request->path();
        if (
            str_starts_with($path, 'admin/settings')
            && ! str_starts_with($path, 'admin/settings/account-security')
        ) {
            $user = $request->user('admin') ?? $request->user();
            abort_unless($user?->hasPermission('settings.manage'), 403);
        }

        return $next($request);
    }
}
