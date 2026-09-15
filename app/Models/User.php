<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'phone', 'password', 'pin_hash', 'pin_set_at', 'role', 'roles', 'branch_id', 'department_id', 'approval_limit', 'is_active', 'locked_until', 'password_changed_at', 'two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at', 'preferences'])]
#[Hidden(['password', 'pin_hash', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'pin_set_at' => 'datetime',
            'locked_until' => 'datetime',
            'password_changed_at' => 'datetime',
            'is_active' => 'boolean',
            'preferences' => 'array',
            'roles' => 'array',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /**
     * Can this user sign into the Console (Tailwind admin)?
     */
    public function canAccessConsole(): bool
    {
        return app(\App\Services\RoleService::class)->hasConsoleAccess($this);
    }

    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }

    public function isStaff(): bool
    {
        return app(\App\Services\RoleService::class)->isStaffUser($this);
    }

    /**
     * All capability codes for this user (primary role + roles JSON).
     *
     * @return list<string>
     */
    public function roleCodes(): array
    {
        $codes = [];
        if (is_string($this->role) && $this->role !== '') {
            $codes[] = $this->role;
        }
        foreach ((array) ($this->roles ?? []) as $code) {
            if (is_string($code) && $code !== '') {
                $codes[] = $code;
            }
        }

        return array_values(array_unique($codes));
    }

    public function hasRole(string $code): bool
    {
        return in_array($code, $this->roleCodes(), true);
    }

    public function roleLabel(): string
    {
        $roles = app(\App\Services\RoleService::class);
        $labels = [];
        foreach ($this->roleCodes() as $code) {
            $labels[] = $code === 'agent' ? 'Customer Support' : $roles->label($code);
        }

        return implode(', ', array_filter($labels)) ?: display_label($this->role, 'role');
    }

    public function hasPermission(string $permission): bool
    {
        return app(\App\Services\PermissionService::class)->has($this, $permission);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /** Teams this user belongs to (multi-team ready). Primary remains department_id. */
    public function departments(): BelongsToMany
    {
        return $this->belongsToMany(Department::class)->withTimestamps();
    }

    public function customer()
    {
        return $this->hasOne(Customer::class);
    }

    public function vendor()
    {
        return $this->hasOne(Partner::class);
    }

    public function partner()
    {
        return $this->hasOne(Partner::class);
    }
}
