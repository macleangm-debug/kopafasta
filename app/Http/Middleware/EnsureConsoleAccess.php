<?php

namespace App\Http\Middleware;

use App\Services\RoleService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureConsoleAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('admin') ?? $request->user();

        if (! $user || ! app(RoleService::class)->hasConsoleAccess($user)) {
            return redirect()
                ->route('staff.dashboard')
                ->with('warning', 'Use the staff workspace for your role, or contact an administrator for console access.');
        }

        return $next($request);
    }
}
