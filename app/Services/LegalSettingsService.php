<?php

namespace App\Services;

use App\Models\CompanySignatory;
use App\Models\Setting;

class LegalSettingsService
{
    public function get(string $key, mixed $default = null): mixed
    {
        $legal = Setting::get("legal.$key");

        if ($legal !== null && $legal !== '') {
            return $legal;
        }

        // Legacy keys stored under company.* before Legal settings existed.
        return match ($key) {
            'signatory_name' => Setting::get('company.signatory_name', $default),
            'signatory_title' => Setting::get('company.signatory_title', $default),
            'signature_path' => Setting::get('company.signature_path', $default),
            default => $default,
        };
    }

    public function signatoryName(): ?string
    {
        $name = trim((string) ($this->get('signatory_name') ?? ''));

        return $name !== '' ? $name : null;
    }

    public function signatoryTitle(): ?string
    {
        $title = trim((string) ($this->get('signatory_title') ?? ''));

        return $title !== '' ? $title : null;
    }

    public function signatureFilesystemPath(): ?string
    {
        $path = $this->get('signature_path');

        if (! $path) {
            return null;
        }

        $full = storage_path('app/public/'.ltrim((string) $path, '/'));

        return is_file($full) ? $this->presentedSignaturePath($full) : null;
    }

    public function stampFilesystemPath(): ?string
    {
        $path = $this->get('stamp_path');

        if (! $path) {
            return null;
        }

        $full = storage_path('app/public/'.ltrim((string) $path, '/'));

        return is_file($full) ? $this->transparentStampPath($full) : null;
    }

    /**
     * Presentation-only signature: honor EXIF, keep a natural horizontal orientation,
     * preserve aspect ratio. Does not overwrite the uploaded asset.
     */
    public function presentedSignaturePath(string $full): string
    {
        if (! is_file($full) || ! function_exists('imagecreatefromstring') || ! function_exists('imagerotate')) {
            return $full;
        }

        $hash = md5($full.'|'.(string) filemtime($full).'|sig-h2');
        $dir = storage_path('app/pdf-cache/signatures');
        $cache = $dir.'/'.$hash.'.png';
        if (is_file($cache)) {
            return $cache;
        }

        $raw = @file_get_contents($full);
        if ($raw === false) {
            return $full;
        }

        $src = @imagecreatefromstring($raw);
        if ($src === false) {
            return $full;
        }

        $src = $this->applyExifOrientation($src, $this->exifOrientation($full));
        $bbox = $this->signatureInkBox($src);
        if ($bbox && $bbox['h'] > $bbox['w']) {
            $rotated = $this->rotatePreservingAlpha($src, 270);
            if ($rotated !== false) {
                imagedestroy($src);
                $src = $rotated;
                $bbox = $this->signatureInkBox($src);
            }
        }
        if ($bbox) {
            $cropped = $this->cropToInkBox($src, $bbox);
            if ($cropped !== false) {
                imagedestroy($src);
                $src = $cropped;
            }
        }

        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        imagesavealpha($src, true);
        imagepng($src, $cache);
        imagedestroy($src);

        return is_file($cache) ? $cache : $full;
    }

    private function exifOrientation(string $full): int
    {
        if (! function_exists('exif_read_data')) {
            return 1;
        }

        $exif = @exif_read_data($full);
        $orientation = (int) ($exif['Orientation'] ?? 1);

        return $orientation >= 1 && $orientation <= 8 ? $orientation : 1;
    }

    /** @param \GdImage|resource $src */
    private function applyExifOrientation($src, int $orientation)
    {
        return match ($orientation) {
            3 => $this->rotatePreservingAlpha($src, 180) ?: $src,
            6 => $this->rotatePreservingAlpha($src, 270) ?: $src,
            8 => $this->rotatePreservingAlpha($src, 90) ?: $src,
            default => $src,
        };
    }

    /**
     * Ink box of a signature, ignoring near-white / transparent pixels.
     *
     * @param  \GdImage|resource  $src
     * @return array{x: int, y: int, w: int, h: int}|null
     */
    private function signatureInkBox($src): ?array
    {
        $width = imagesx($src);
        $height = imagesy($src);
        $minX = $width;
        $minY = $height;
        $maxX = -1;
        $maxY = -1;
        $trueColor = imageistruecolor($src);

        for ($x = 0; $x < $width; $x++) {
            for ($y = 0; $y < $height; $y++) {
                $pixel = imagecolorat($src, $x, $y);
                if ($trueColor) {
                    $alpha = ($pixel & 0x7F000000) >> 24;
                    $r = ($pixel >> 16) & 0xFF;
                    $g = ($pixel >> 8) & 0xFF;
                    $b = $pixel & 0xFF;
                } else {
                    $rgba = imagecolorsforindex($src, $pixel);
                    $alpha = (int) ($rgba['alpha'] ?? 0);
                    $r = (int) ($rgba['red'] ?? 0);
                    $g = (int) ($rgba['green'] ?? 0);
                    $b = (int) ($rgba['blue'] ?? 0);
                }

                if ($alpha >= 120 || ($r >= 245 && $g >= 245 && $b >= 245)) {
                    continue;
                }

                $minX = min($minX, $x);
                $minY = min($minY, $y);
                $maxX = max($maxX, $x);
                $maxY = max($maxY, $y);
            }
        }

        if ($maxX < $minX || $maxY < $minY) {
            return null;
        }

        return [
            'x' => $minX,
            'y' => $minY,
            'w' => $maxX - $minX + 1,
            'h' => $maxY - $minY + 1,
        ];
    }

    /**
     * @param  \GdImage|resource  $src
     * @param  array{x: int, y: int, w: int, h: int}  $box
     * @return \GdImage|resource|false
     */
    private function cropToInkBox($src, array $box)
    {
        $pad = max(8, (int) round(max($box['w'], $box['h']) * 0.12));
        $x = max(0, $box['x'] - $pad);
        $y = max(0, $box['y'] - $pad);
        $w = min(imagesx($src) - $x, $box['w'] + ($box['x'] - $x) + $pad);
        $h = min(imagesy($src) - $y, $box['h'] + ($box['y'] - $y) + $pad);
        if ($w < 8 || $h < 8) {
            return false;
        }

        $dst = imagecreatetruecolor($w, $h);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        $clear = imagecolorallocatealpha($dst, 0, 0, 0, 127);
        imagefilledrectangle($dst, 0, 0, $w, $h, $clear);
        imagealphablending($dst, true);
        imagecopy($dst, $src, 0, 0, $x, $y, $w, $h);

        return $dst;
    }

    /** @param \GdImage|resource $src */
    private function rotatePreservingAlpha($src, int $angle)
    {
        imagealphablending($src, true);
        imagesavealpha($src, true);
        $transparent = imagecolorallocatealpha($src, 0, 0, 0, 127);
        $rotated = imagerotate($src, $angle, $transparent);
        if ($rotated === false) {
            return false;
        }
        imagealphablending($rotated, false);
        imagesavealpha($rotated, true);

        return $rotated;
    }

    /**
     * Strip near-white pixels so the company stamp sits on the page without a square background.
     */
    public function transparentStampPath(string $full): string
    {
        if (! is_file($full) || ! function_exists('imagecreatefromstring') || ! function_exists('imagecreatetruecolor')) {
            return $full;
        }

        $hash = md5($full.'|'.(string) filemtime($full).'|v2');
        $dir = storage_path('app/pdf-cache/stamps');
        $cache = $dir.'/'.$hash.'.png';
        if (is_file($cache)) {
            return $cache;
        }

        $raw = @file_get_contents($full);
        if ($raw === false) {
            return $full;
        }

        $src = @imagecreatefromstring($raw);
        if ($src === false) {
            return $full;
        }

        $width = imagesx($src);
        $height = imagesy($src);
        $maxEdge = 400;
        if ($width > $maxEdge || $height > $maxEdge) {
            $scale = $maxEdge / max($width, $height);
            $newWidth = max(1, (int) round($width * $scale));
            $newHeight = max(1, (int) round($height * $scale));
            $resized = imagecreatetruecolor($newWidth, $newHeight);
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
            $clearResize = imagecolorallocatealpha($resized, 0, 0, 0, 127);
            imagefilledrectangle($resized, 0, 0, $newWidth, $newHeight, $clearResize);
            imagealphablending($resized, true);
            imagecopyresampled($resized, $src, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
            imagedestroy($src);
            $src = $resized;
            $width = $newWidth;
            $height = $newHeight;
        }

        $dst = imagecreatetruecolor($width, $height);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        $clear = imagecolorallocatealpha($dst, 0, 0, 0, 127);
        imagefilledrectangle($dst, 0, 0, $width, $height, $clear);

        $trueColor = imageistruecolor($src);
        for ($x = 0; $x < $width; $x++) {
            for ($y = 0; $y < $height; $y++) {
                $pixel = imagecolorat($src, $x, $y);
                if ($trueColor) {
                    $alpha = ($pixel & 0x7F000000) >> 24;
                    $r = ($pixel >> 16) & 0xFF;
                    $g = ($pixel >> 8) & 0xFF;
                    $b = $pixel & 0xFF;
                } else {
                    $rgba = imagecolorsforindex($src, $pixel);
                    $alpha = (int) ($rgba['alpha'] ?? 0);
                    $r = (int) ($rgba['red'] ?? 0);
                    $g = (int) ($rgba['green'] ?? 0);
                    $b = (int) ($rgba['blue'] ?? 0);
                }

                if ($alpha >= 120 || ($r >= 245 && $g >= 245 && $b >= 245)) {
                    continue;
                }

                imagesetpixel($dst, $x, $y, imagecolorallocatealpha($dst, $r, $g, $b, min(127, $alpha)));
            }
        }

        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        imagepng($dst, $cache);
        imagedestroy($src);
        imagedestroy($dst);

        return is_file($cache) ? $cache : $full;
    }

    public function offerValidityDays(): int
    {
        return max(1, (int) $this->get('offer_validity_days', 14));
    }

    public function jurisdiction(): string
    {
        return (string) $this->get('jurisdiction', 'United Republic of Tanzania');
    }

    /** @return array<string, bool> */
    public function contractSections(): array
    {
        $defaults = [
            'definitions' => true,
            'loan_terms' => true,
            'repayment_obligations' => true,
            'default_events' => true,
            'penalty_clauses' => true,
            'recovery_clauses' => true,
            'guarantor_obligations' => true,
            'legal_costs' => true,
            'jurisdiction' => true,
            'data_privacy' => true,
            'signatures' => true,
        ];

        $stored = Setting::get('legal.contract_sections');
        if (! is_array($stored)) {
            return $defaults;
        }

        return array_merge($defaults, array_map('boolval', $stored));
    }

    public function activeSignatory(): ?CompanySignatory
    {
        return $this->activeCeoSignatory();
    }

    public function activeCeoSignatory(): ?CompanySignatory
    {
        $ceo = CompanySignatory::query()
            ->where('is_active', true)
            ->where('signatory_type', 'ceo')
            ->orderBy('id')
            ->first();

        if ($ceo) {
            return $ceo;
        }

        $company = CompanySignatory::query()
            ->where('is_active', true)
            ->where(function ($q) {
                $q->where('signatory_type', 'company')->orWhereNull('signatory_type');
            })
            ->orderBy('id')
            ->first();

        if ($company) {
            return $company;
        }

        return CompanySignatory::query()
            ->where('is_active', true)
            ->whereNotIn('signatory_type', ['legal_advocate', 'finance_manager'])
            ->orderBy('id')
            ->first();
    }

    public function activeFinanceSignatory(): ?CompanySignatory
    {
        return CompanySignatory::query()
            ->where('is_active', true)
            ->where('signatory_type', 'finance_manager')
            ->orderBy('id')
            ->first();
    }

    public function activeLegalSignatory(): ?CompanySignatory
    {
        return $this->activeFinanceSignatory();
    }

    /** @return array<string, mixed> */
    public function contractClauses(): array
    {
        $penaltyRate = (float) Setting::get('loan.default_penalty_rate', 1);
        $graceDays = (int) Setting::get('loan.default_grace_days', 7);
        $penaltyCap = (float) Setting::get('loan.penalty_cap_percent', 30);
        $penaltyBasis = (string) Setting::get('loan.penalty_basis', 'per_day');

        $basisLabel = match ($penaltyBasis) {
            'per_month' => 'per month',
            'one_time' => 'one-time',
            default => 'per day',
        };

        return [
            'penalty_rate' => $penaltyRate,
            'penalty_rate_label' => format_number($penaltyRate, 2).'% '.$basisLabel.' on overdue balance',
            'grace_days' => $graceDays,
            'penalty_cap_percent' => $penaltyCap,
            'collection_charge' => (string) $this->get('collection_fee_text', 'Actual cost incurred'),
            'legal_recovery' => (string) $this->get('legal_recovery_text', 'Borrower responsible for all legal recovery costs'),
            'jurisdiction' => $this->jurisdiction(),
            'default_clause' => (string) $this->get('default_clause', 'Failure to pay any instalment by the due date constitutes default after the grace period.'),
            'collection_clause' => (string) $this->get('collection_clause', 'The lender may contact the borrower by phone, SMS, email, or in person to recover overdue amounts.'),
            'recovery_clause' => (string) $this->get('recovery_clause', 'Persistent default may result in legal recovery action and reporting to credit reference bureaus.'),
            'penalty_clause' => (string) $this->get('penalty_clause', 'Penalty interest applies as stated in the schedule of charges. Collection fees are added on top of amount owed when a recovery partner is assigned.'),
            'legal_cost_clause' => (string) $this->get('legal_cost_clause', 'The borrower shall bear all reasonable legal costs incurred in recovering overdue amounts.'),
            'guarantor_clause' => (string) $this->get('guarantor_clause', 'Where a guarantor has signed, they become jointly and severally liable for repayment.'),
            'asset_recovery_clause' => (string) $this->get('asset_recovery_clause', 'The lender may recover financed assets or collateral in accordance with applicable law and the asset lending terms.'),
        ];
    }
}
