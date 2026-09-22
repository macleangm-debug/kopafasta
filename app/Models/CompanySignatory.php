<?php

namespace App\Models;

use App\Services\LegalSettingsService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

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

    public function signatureFilesystemPath(bool $transparent = true): ?string
    {
        if (! $this->signature_path) {
            return null;
        }

        $full = storage_path('app/public/'.ltrim($this->signature_path, '/'));
        if (! is_file($full)) {
            return null;
        }

        return $transparent
            ? app(LegalSettingsService::class)->transparentStampPath($full)
            : $full;
    }

    /**
     * Browser URL for admin thumbnails — always the processed transparent PNG,
     * never the raw upload with paper rectangle.
     */
    public function signaturePublicUrl(): ?string
    {
        if (! $this->signature_path) {
            return null;
        }

        $full = storage_path('app/public/'.ltrim($this->signature_path, '/'));
        if (! is_file($full)) {
            return null;
        }

        $processed = app(LegalSettingsService::class)->transparentStampPath($full);
        if (! is_file($processed)) {
            return asset('storage/'.$this->signature_path);
        }

        $relative = 'signatories/display/'.md5($processed.'|'.(string) filemtime($full)).'.png';
        if (! Storage::disk('public')->exists($relative)) {
            Storage::disk('public')->put($relative, (string) file_get_contents($processed));
        }

        return asset('storage/'.$relative);
    }

    public function stampPublicUrl(): ?string
    {
        return $this->stamp_path ? asset('storage/'.$this->stamp_path) : null;
    }

    public function stampFilesystemPath(): ?string
    {
        if (! $this->stamp_path) {
            return null;
        }

        $full = storage_path('app/public/'.ltrim($this->stamp_path, '/'));

        return is_file($full) ? $full : null;
    }

    public function isLegalAdvocate(): bool
    {
        return $this->signatory_type === 'legal_advocate';
    }

    public function isCeo(): bool
    {
        return $this->signatory_type === 'ceo';
    }

    public function isFinanceManager(): bool
    {
        return $this->signatory_type === 'finance_manager';
    }
}
