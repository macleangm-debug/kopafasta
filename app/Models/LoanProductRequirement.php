<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanProductRequirement extends Model
{
    protected $fillable = [
        'loan_product_id',
        'type',
        'name',
        'description',
        'is_required',
    ];

    protected function casts(): array
    {
        return ['is_required' => 'boolean'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(LoanProduct::class, 'loan_product_id');
    }

    /**
     * Product-checklist rows that duplicate profile capture (Face / Income verification).
     * Hidden from screening Documents; seeders should not recreate them.
     */
    public function isProfileDuplicate(): bool
    {
        return self::nameLooksLikeProfileDuplicate((string) $this->name, (string) $this->description);
    }

    public static function nameLooksLikeProfileDuplicate(?string $name, ?string $description = null): bool
    {
        $name = strtolower(trim((string) $name));
        $hay = $name.' '.strtolower(trim((string) $description));

        if ($name === 'passport photo'
            || str_contains($hay, 'passport-size')
            || str_contains($hay, 'passport size')) {
            return true;
        }

        if ($name === 'source of income proof') {
            return true;
        }

        return $name === '3 months bank statement'
            || (str_contains($name, '3 month') && str_contains($name, 'bank statement'));
    }
}
