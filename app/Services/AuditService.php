<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Request;

class AuditService
{
    /** @var list<string> */
    private const REDACTED_KEYS = [
        'password',
        'pin',
        'current_pin',
        'pin_confirmation',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'signature_data',
    ];

    public function log(
        ?User $user,
        string $event,
        ?Model $auditable = null,
        array $oldValues = [],
        array $newValues = [],
    ): ?AuditLog {
        try {
            return AuditLog::create([
                'user_id'        => $user?->id,
                'event'          => $event,
                'auditable_type' => $auditable ? $auditable->getMorphClass() : null,
                'auditable_id'   => $auditable?->getKey(),
                'old_values'     => $oldValues ?: null,
                'new_values'     => $newValues ?: null,
                'ip_address'     => Request::ip(),
                'user_agent'     => substr((string) Request::userAgent(), 0, 1000),
            ]);
        } catch (\Throwable) {
            return null;
        }
    }

    public function snapshot(Model $model, array $only = []): array
    {
        $attrs = $only !== [] ? $model->only($only) : $model->getAttributes();

        return $this->sanitize($attrs);
    }

    public function sanitize(array $data): array
    {
        foreach (array_keys($data) as $key) {
            if ($this->shouldRedact((string) $key)) {
                $data[$key] = '[redacted]';
            }
        }

        return $data;
    }

    public function logCreated(?User $user, string $resource, Model $record): ?AuditLog
    {
        return $this->log(
            $user,
            "admin.{$resource}.created",
            $record,
            [],
            $this->snapshot($record),
        );
    }

    public function logUpdated(?User $user, string $resource, Model $record, array $before): ?AuditLog
    {
        return $this->log(
            $user,
            "admin.{$resource}.updated",
            $record,
            $this->sanitize($before),
            $this->snapshot($record),
        );
    }

    public function logDeleted(?User $user, string $resource, Model $record): ?AuditLog
    {
        return $this->log(
            $user,
            "admin.{$resource}.deleted",
            $record,
            $this->snapshot($record),
            [],
        );
    }

    public function logAdminAction(
        ?User $user,
        string $event,
        ?Model $auditable = null,
        array $context = [],
    ): ?AuditLog {
        return $this->log($user, $event, $auditable, [], $context);
    }

    public function logBorrower(
        ?User $user,
        string $event,
        ?Model $auditable = null,
        array $context = [],
    ): ?AuditLog {
        return $this->log($user, "borrower.{$event}", $auditable, [], $context);
    }

    private function shouldRedact(string $key): bool
    {
        if (in_array($key, self::REDACTED_KEYS, true)) {
            return true;
        }

        return str_contains($key, 'password')
            || str_contains($key, 'secret')
            || str_contains($key, '_token');
    }
}
