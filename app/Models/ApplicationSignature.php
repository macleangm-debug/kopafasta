<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApplicationSignature extends Model
{
    protected $fillable = [
        'loan_application_id',
        'signer_type',
        'signer_name',
        'signature_data',
        'signed_at',
        'guarantor_invitation_id',
    ];

    protected function casts(): array
    {
        return [
            'signed_at' => 'datetime',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(LoanApplication::class, 'loan_application_id');
    }

    public function invitation(): BelongsTo
    {
        return $this->belongsTo(GuarantorInvitation::class, 'guarantor_invitation_id');
    }
}
