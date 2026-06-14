<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanySignatory extends Model
{
    protected $fillable = [
        'name',
        'position',
        'email',
        'signature_path',
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

    public function signaturePublicUrl(): ?string
    {
        return $this->signature_path ? asset('storage/'.$this->signature_path) : null;
    }
}
