<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChargesFee extends Model
{
    protected $table = 'charges_fees';

    protected $fillable = [
        'name', 'code', 'type', 'basis', 'amount',
        'min_amount', 'max_amount', 'charge_when',
        'gl_account_id', 'is_active', 'description',
    ];
    protected $casts = [
        'amount' => 'decimal:4',
        'min_amount' => 'decimal:2',
        'max_amount' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function glAccount() { return $this->belongsTo(ChartOfAccount::class, 'gl_account_id'); }
}
