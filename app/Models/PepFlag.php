<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PepFlag extends Model
{
    protected $fillable = [
        'customer_id', 'full_name', 'position', 'organization',
        'category', 'risk_level', 'listed_on', 'is_active', 'notes',
    ];
    protected $casts = [
        'listed_on' => 'date',
        'is_active' => 'boolean',
    ];

    public function customer() { return $this->belongsTo(Customer::class); }
}
