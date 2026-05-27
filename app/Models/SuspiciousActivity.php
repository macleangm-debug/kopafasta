<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SuspiciousActivity extends Model
{
    protected $fillable = [
        'customer_id', 'loan_id', 'aml_rule_id',
        'activity_type', 'amount', 'severity', 'status',
        'description', 'investigator_notes', 'assigned_to_user_id',
        'detected_at', 'resolved_at',
    ];
    protected $casts = [
        'amount' => 'decimal:2',
        'detected_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function customer() { return $this->belongsTo(Customer::class); }
    public function loan()     { return $this->belongsTo(Loan::class); }
    public function rule()     { return $this->belongsTo(AmlRule::class, 'aml_rule_id'); }
    public function assignee() { return $this->belongsTo(User::class, 'assigned_to_user_id'); }
}
