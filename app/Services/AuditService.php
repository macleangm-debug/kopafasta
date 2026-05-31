<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Request;

class AuditService
{
    public function log(
        User $user,
        string $event,
        ?Model $auditable = null,
        array $oldValues = [],
        array $newValues = [],
    ): AuditLog {
        return AuditLog::create([
            'user_id'        => $user->id,
            'event'          => $event,
            'auditable_type' => $auditable ? $auditable->getMorphClass() : null,
            'auditable_id'   => $auditable?->getKey(),
            'old_values'     => $oldValues ?: null,
            'new_values'     => $newValues ?: null,
            'ip_address'     => Request::ip(),
            'user_agent'     => substr((string) Request::userAgent(), 0, 1000),
        ]);
    }
}
