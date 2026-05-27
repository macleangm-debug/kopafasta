<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BlacklistEntry extends Model
{
    protected $fillable = [
        'identifier_type', 'identifier_value', 'reason', 'source',
        'listed_on', 'expires_on', 'added_by_user_id', 'is_active', 'notes',
    ];
    protected $casts = [
        'listed_on' => 'date',
        'expires_on' => 'date',
        'is_active' => 'boolean',
    ];

    public function addedBy() { return $this->belongsTo(User::class, 'added_by_user_id'); }
}
