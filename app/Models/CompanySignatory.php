<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanySignatory extends Model
{
    protected $fillable = [
        'name',
        'position',
        'email',
        'signatory_type',
        'signature_path',
        'stamp_path',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function signatureFilesystemPath(): ?string
    {
        if (! $this->signature_path) {
            return null;
        }

        $full = storage_path('app/public/'.ltrim($this->signature_path, '/'));

        return is_file($full) ? $full : null;
    }

    public function stampPublicUrl(): ?string
    {
        return $this->stamp_path ? asset('storage/'.$this->stamp_path) : null;
    }

    public function isLegalAdvocate(): bool
    {
        return $this->signatory_type === 'legal_advocate';
    }
}
