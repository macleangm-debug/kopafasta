<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IpRule extends Model
{
    use HasFactory;

    public const MODE_ALLOW = 'allow';
    public const MODE_DENY = 'deny';

    protected $fillable = [
        'cidr',
        'mode',
        'reason',
        'created_by',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
