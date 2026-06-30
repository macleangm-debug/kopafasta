<?php

namespace App\Http\Middleware;

use App\Services\RoleService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureStaffUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('admin') ?? $request->user();

        if (! $user || ! app(RoleService::class)->isStaff($user->role)) {
            abort(403, 'Staff portal access only.');
        }

        return $next($request);
    }
}
