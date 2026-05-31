<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use App\Services\RoleService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserRole
{
    public function __construct(private RoleService $roles)
    {
    }

    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $allowedRoles = $this->roles->resolveApiRoles($roles);

        if (! in_array($user->role, $allowedRoles, true)) {
            $this->logDenied($request, $user, $allowedRoles);

            return response()->json(['message' => 'Forbidden'], 403);
        }

        return $next($request);
    }

    /** @param  list<string>  $roles */
    private function logDenied(Request $request, $user, array $roles): void
    {
        try {
            AuditLog::create([
                'user_id' => $user->id,
                'event' => 'authorization.role_denied',
                'auditable_type' => null,
                'auditable_id' => null,
                'old_values' => null,
                'new_values' => json_encode([
                    'method' => $request->method(),
                    'path' => $request->path(),
                    'route' => optional($request->route())->getName(),
                    'required_roles' => $roles,
                    'user_role' => $user->role,
                ]),
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 1000),
            ]);
        } catch (\Throwable $t) {
            // Never let audit logging break request handling.
        }
    }
}
