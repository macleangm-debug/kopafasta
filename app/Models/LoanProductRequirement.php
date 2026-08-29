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
     * Product-checklist rows that duplicate profile capture (Face / NIDA / Income).
     * Hidden from screening Documents; seeders should not recreate them.
     */
    public function isProfileDuplicate(): bool
    {
        return self::nameLooksLikeProfileDuplicate((string) $this->name, (string) $this->description);
    }

    public static function nameLooksLikeIdentityDocument(?string $name, ?string $description = null): bool
    {
        $name = strtolower(trim((string) $name));
        $hay = $name.' '.strtolower(trim((string) $description));

        if ($name === '') {
            return false;
        }

        if (preg_match('/\b(nida|national id|national identification)\b/', $hay) === 1) {
            return true;
        }

        if (in_array($name, ['id card', 'identity card', 'identity document', 'copy of id'], true)) {
            return true;
        }

        // Face / ID-card product rows are reviewed on the identity checklist, not Documents.
        if (str_contains($hay, 'face') && (
            str_contains($hay, 'verification')
            || str_contains($hay, 'capture')
            || str_contains($hay, 'selfie')
            || str_contains($hay, 'photo of id')
        )) {
            return true;
        }

        return false;
    }

    public static function nameLooksLikeProfileDuplicate(?string $name, ?string $description = null): bool
    {
        $name = strtolower(trim((string) $name));
        $hay = $name.' '.strtolower(trim((string) $description));

        if (LoanProductRequirement::nameLooksLikeIdentityDocument($name, $description)) {
            return true;
        }

        if (self::nameIsIncomeEvidenceRequirement($name, $description)) {
            return true;
        }

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

    /**
     * Statements are reviewed on Gate 2, not as a generic Application Evidence row.
     */
    public static function nameIsIncomeEvidenceRequirement(?string $name, ?string $description = null): bool
    {
        $name = strtolower(trim((string) $name));
        $hay = $name.' '.strtolower(trim((string) $description));

        if ($name === '') {
            return false;
        }

        return $name === 'income verification'
            || ($name !== '' && str_contains($hay, 'income verification'));
    }

    /** Digital group members are the roster — this paper upload must never block screening. */
    public static function nameIsDigitalGroupRoster(?string $name): bool
    {
        $name = strtolower(trim((string) $name));

        return $name === 'group member roster'
            || ($name !== '' && str_contains($name, 'group') && str_contains($name, 'roster'));
    }

    /** Paper group constitution — optional unless the product checkbox is on. */
    public static function nameIsGroupConstitution(?string $name): bool
    {
        $name = strtolower(trim((string) $name));

        return $name === 'group constitution'
            || ($name !== '' && str_contains($name, 'group') && str_contains($name, 'constitution'));
    }
}
