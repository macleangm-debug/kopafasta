<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Settlement extends Model
{
    protected function casts(): array
    {
        return [
            'settlement_date' => 'date',
            'gross_amount' => 'decimal:2',
            'fees' => 'decimal:2',
            'net_amount' => 'decimal:2',
        ];
    }
}
