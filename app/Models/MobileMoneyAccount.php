<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MobileMoneyAccount extends Model
{
    protected $fillable = [
        'name', 'provider', 'msisdn', 'paybill_number', 'till_number',
        'api_username', 'api_secret', 'environment',
        'opening_balance', 'gl_account_id', 'purpose', 'is_active',
    ];
    protected $casts = [
        'opening_balance' => 'decimal:2',
        'is_active' => 'boolean',
        'api_secret' => 'encrypted',
    ];

    public function glAccount() { return $this->belongsTo(ChartOfAccount::class, 'gl_account_id'); }
}
