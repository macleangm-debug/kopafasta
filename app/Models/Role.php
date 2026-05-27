<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    protected $fillable = ['name', 'code', 'description', 'permissions', 'is_system'];
    protected $casts = [
        'permissions' => 'array',
        'is_system'   => 'boolean',
    ];

    public function approvalLimits()
    {
        return $this->hasMany(ApprovalLimit::class, 'role_code', 'code');
    }
}
