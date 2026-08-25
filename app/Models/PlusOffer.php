<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlusOffer extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'plus_only' => 'boolean',
            'active' => 'boolean',
            'eligible_grades' => 'array',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function localizedTitle(): string
    {
        if ($this->title === 'Plus Club — record 7 days of money in and out') {
            return __('plus.offers.sample_title');
        }

        return $this->title;
    }

    public function localizedBody(): string
    {
        if ($this->title === 'Plus Club — record 7 days of money in and out') {
            return __('plus.offers.sample_body');
        }

        return (string) $this->body;
    }
}
