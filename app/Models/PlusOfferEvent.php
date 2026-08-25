<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlusOfferEvent extends Model
{
    protected $guarded = [];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function offer(): BelongsTo
    {
        return $this->belongsTo(PlusOffer::class, 'plus_offer_id');
    }
}
