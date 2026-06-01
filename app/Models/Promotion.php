<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Promotion extends Model
{
    protected $fillable = [
        'code',
        'name',
        'type',
        'status',
        'discount_percent',
        'discount_amount',
        'applies_to',
        'starts_at',
        'ends_at',
        'message_template',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'discount_percent' => 'decimal:2',
            'discount_amount'  => 'decimal:2',
            'starts_at'        => 'date',
            'ends_at'          => 'date',
            'metadata'         => 'array',
        ];
    }

    public function isActive(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        $today = now()->toDateString();

        if ($this->starts_at && $this->starts_at->toDateString() > $today) {
            return false;
        }

        if ($this->ends_at && $this->ends_at->toDateString() < $today) {
            return false;
        }

        return true;
    }
}
