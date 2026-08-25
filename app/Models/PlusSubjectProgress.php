<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlusSubjectProgress extends Model
{
    protected $table = 'plus_subject_progress';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'viewed_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'saved_at' => 'datetime',
            'action_clicked_at' => 'datetime',
            'helpful' => 'boolean',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(PlusSubject::class, 'plus_subject_id');
    }
}
