<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentTemplate extends Model
{
    protected $fillable = ['name', 'code', 'content', 'is_active'];
    protected $casts = ['is_active' => 'boolean'];
}
