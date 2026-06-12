<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Guarantor extends Model
{
    protected $fillable = [
        'first_name',
        'last_name',
        'phone',
        'email',
        'national_id',
        'address',
        'relationship',
    ];
}
