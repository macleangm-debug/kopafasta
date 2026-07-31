<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class PartnerApplicationDocument extends Model
{
    protected $fillable = [
        'partner_application_id',
        'doc_type',
        'file_path',
        'original_name',
        'mime',
        'size_bytes',
    ];

    public const DOC_TYPES = [
        'brela' => 'BRELA / company registration',
        'tin_certificate' => 'TIN certificate',
        'business_licence' => 'Business licence',
        'other' => 'Other supporting document',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(PartnerApplication::class, 'partner_application_id');
    }

    public function url(): ?string
    {
        if (! $this->file_path) {
            return null;
        }

        return Storage::disk('public')->url($this->file_path);
    }

    public function label(): string
    {
        return self::DOC_TYPES[$this->doc_type] ?? ucfirst(str_replace('_', ' ', (string) $this->doc_type));
    }
}
