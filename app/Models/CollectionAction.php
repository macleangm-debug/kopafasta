<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CollectionAction extends Model
{
    protected function casts(): array
    {
        return ['performed_at' => 'datetime'];
    }

    public function arrearCase(): BelongsTo
    {
        return $this->belongsTo(ArrearCase::class);
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
