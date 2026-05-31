<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\IpRule;
use App\Models\User;
use App\Services\IpRuleService;
use App\Services\RoleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{
    public function __construct(private RoleService $roles)
    {
    }

    public function users()
    {
        return response()->json(User::query()->latest()->paginate(20));
    }

    public function assignRole(Request $request, User $user)
    {
        $staffRoles = $this->roles->staffRoles();

        $data = $request->validate([
            'role' => ['required', 'string', 'max:50', Rule::in($staffRoles)],
            'approval_limit' => ['nullable', 'numeric', 'min:0'],
            'branch_id' => [
                Rule::requiredIf(fn () => in_array($request->input('role'), $this->roles->branchScopedStaffRoles(), true)),
                'nullable',
                'exists:branches,id',
            ],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $user->update($data);

        return response()->json($user->fresh());
    }

    public function settings()
    {
        return response()->json(DB::table('system_settings')->orderBy('key')->get());
    }

    public function upsertSetting(Request $request)
    {
        $data = $request->validate([
            'key' => ['required', 'string', 'max:100'],
            'value' => ['nullable', 'string'],
        ]);

        DB::table('system_settings')->updateOrInsert(
            ['key' => $data['key']],
            ['value' => $data['value'] ?? null, 'updated_at' => now(), 'created_at' => now()]
        );

        return response()->json(['message' => 'Setting saved']);
    }

    public function auditLogs()
    {
        return response()->json(AuditLog::query()->latest()->paginate(50));
    }

    public function lockUser(Request $request, User $user)
    {
        $data = $request->validate([
            'minutes' => ['nullable', 'integer', 'min:1', 'max:43200'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $minutes = $data['minutes'] ?? 60;
        $user->forceFill(['locked_until' => now()->addMinutes($minutes)])->save();

        AuditLog::create([
            'user_id' => $request->user()?->id,
            'event' => 'admin.user_locked',
            'auditable_type' => User::class,
            'auditable_id' => $user->id,
            'old_values' => null,
            'new_values' => json_encode([
                'locked_until' => $user->locked_until?->toIso8601String(),
                'reason' => $data['reason'] ?? null,
            ]),
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 1000),
        ]);

        return response()->json($user->fresh());
    }

    public function unlockUser(Request $request, User $user)
    {
        $user->forceFill(['locked_until' => null])->save();

        AuditLog::create([
            'user_id' => $request->user()?->id,
            'event' => 'admin.user_unlocked',
            'auditable_type' => User::class,
            'auditable_id' => $user->id,
            'old_values' => null,
            'new_values' => json_encode(['user_id' => $user->id]),
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 1000),
        ]);

        return response()->json($user->fresh());
    }

    public function securityAnomalies(Request $request)
    {
        $data = $request->validate([
            'days' => ['nullable', 'integer', 'min:1', 'max:90'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $days = (int) ($data['days'] ?? 7);
        $limit = (int) ($data['limit'] ?? 10);
        $since = now()->subDays($days);

        $watchedEvents = [
            'auth.failed_login',
            'auth.login_locked',
            'auth.login_locked_account',
            'auth.new_device_login',
            'auth.2fa_failed',
            'auth.password_reset_invalid',
        ];

        $totals = AuditLog::where('created_at', '>=', $since)
            ->whereIn('event', $watchedEvents)
            ->selectRaw('event, count(*) as count')
            ->groupBy('event')
            ->pluck('count', 'event');

        $perUser = AuditLog::where('created_at', '>=', $since)
            ->whereNotNull('user_id')
            ->whereIn('event', $watchedEvents)
            ->selectRaw('user_id, event, count(*) as count')
            ->groupBy('user_id', 'event')
            ->orderByDesc('count')
            ->limit($limit * count($watchedEvents))
            ->get();

        $topUsers = $perUser->groupBy('user_id')
            ->map(function ($rows, $userId) {
                $user = User::find($userId);
                return [
                    'user_id' => (int) $userId,
                    'email' => $user?->email,
                    'role' => $user?->role,
                    'total' => (int) $rows->sum('count'),
                    'by_event' => $rows->pluck('count', 'event'),
                ];
            })
            ->sortByDesc('total')
            ->take($limit)
            ->values();

        $topIps = AuditLog::where('created_at', '>=', $since)
            ->where('event', 'auth.failed_login')
            ->whereNotNull('ip_address')
            ->selectRaw('ip_address, count(*) as failed_count')
            ->groupBy('ip_address')
            ->orderByDesc('failed_count')
            ->limit($limit)
            ->get()
            ->map(fn ($r) => ['ip_address' => $r->ip_address, 'failed_count' => (int) $r->failed_count]);

        $countryRows = AuditLog::where('created_at', '>=', $since)
            ->where('event', 'auth.failed_login')
            ->whereNotNull('new_values')
            ->get(['new_values']);

        $countryCounts = [];
        foreach ($countryRows as $row) {
            $payload = json_decode($row->new_values, true);
            $country = $payload['intel']['country'] ?? null;
            $key = $country ?: 'UNKNOWN';
            $countryCounts[$key] = ($countryCounts[$key] ?? 0) + 1;
        }
        arsort($countryCounts);
        $topCountries = collect($countryCounts)
            ->take($limit)
            ->map(fn ($count, $country) => ['country' => $country, 'failed_count' => $count])
            ->values();

        return response()->json([
            'window_days' => $days,
            'since' => $since->toIso8601String(),
            'totals' => $totals,
            'top_users' => $topUsers,
            'top_failed_ips' => $topIps,
            'top_failed_countries' => $topCountries,
        ]);
    }

    public function ipRules()
    {
        return response()->json(IpRule::query()->latest()->get());
    }

    public function createIpRule(Request $request, IpRuleService $service)
    {
        $data = $request->validate([
            'cidr' => ['required', 'string', 'max:64'],
            'mode' => ['required', Rule::in([IpRule::MODE_ALLOW, IpRule::MODE_DENY])],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        if (! $this->isValidCidr($data['cidr'])) {
            return response()->json(['message' => 'Invalid CIDR'], 422);
        }

        $rule = IpRule::updateOrCreate(
            ['cidr' => $data['cidr'], 'mode' => $data['mode']],
            ['reason' => $data['reason'] ?? null, 'created_by' => $request->user()?->id]
        );

        $service->flush();

        AuditLog::create([
            'user_id' => $request->user()?->id,
            'event' => 'admin.ip_rule_created',
            'auditable_type' => IpRule::class,
            'auditable_id' => $rule->id,
            'old_values' => null,
            'new_values' => json_encode([
                'cidr' => $rule->cidr,
                'mode' => $rule->mode,
                'reason' => $rule->reason,
            ]),
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 1000),
        ]);

        return response()->json($rule, 201);
    }

    public function deleteIpRule(Request $request, IpRule $ipRule, IpRuleService $service)
    {
        $snapshot = [
            'cidr' => $ipRule->cidr,
            'mode' => $ipRule->mode,
            'reason' => $ipRule->reason,
        ];
        $id = $ipRule->id;
        $ipRule->delete();
        $service->flush();

        AuditLog::create([
            'user_id' => $request->user()?->id,
            'event' => 'admin.ip_rule_deleted',
            'auditable_type' => IpRule::class,
            'auditable_id' => $id,
            'old_values' => json_encode($snapshot),
            'new_values' => null,
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 1000),
        ]);

        return response()->json(['message' => 'IP rule deleted']);
    }

    private function isValidCidr(string $cidr): bool
    {
        if (! str_contains($cidr, '/')) {
            return @inet_pton($cidr) !== false;
        }
        [$subnet, $bits] = explode('/', $cidr, 2);
        if (! ctype_digit($bits)) {
            return false;
        }
        $bin = @inet_pton($subnet);
        if ($bin === false) {
            return false;
        }
        $max = strlen($bin) * 8;
        $bitsInt = (int) $bits;
        return $bitsInt >= 0 && $bitsInt <= $max;
    }

    public function unblockIp(Request $request, string $ip)
    {
        if (@inet_pton($ip) === false) {
            return response()->json(['message' => 'Invalid IP address'], 422);
        }

        $wasBlocked = Cache::has('sec:ip_blocked:'.$ip);
        Cache::forget('sec:ip_blocked:'.$ip);
        Cache::forget('sec:ip_blocked:'.$ip.':expires');

        AuditLog::create([
            'user_id' => $request->user()?->id,
            'event' => 'admin.ip_unblocked',
            'auditable_type' => null,
            'auditable_id' => null,
            'old_values' => null,
            'new_values' => json_encode([
                'ip_address' => $ip,
                'was_blocked' => $wasBlocked,
            ]),
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 1000),
        ]);

        return response()->json([
            'message' => 'IP block cleared',
            'ip_address' => $ip,
            'was_blocked' => $wasBlocked,
        ]);
    }
}
