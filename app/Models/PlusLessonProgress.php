<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlusLessonProgress extends Model
{
    protected $table = 'plus_lesson_progress';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'action_done_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(PlusLesson::class, 'plus_lesson_id');
    }
}
