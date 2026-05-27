<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    protected $fillable = ['name', 'code', 'branch_id', 'head_user_id', 'description', 'is_active'];
    protected $casts = ['is_active' => 'boolean'];

    public function branch() { return $this->belongsTo(Branch::class); }
    public function head()   { return $this->belongsTo(User::class, 'head_user_id'); }
}
