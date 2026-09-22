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

        return is_file($full) ? $this->transparentStampPath($full) : null;
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
     * Strip near-white / paper backgrounds so stamps and signatures sit cleanly on PDFs.
     * Uses edge flood-fill (paper connected to borders) plus a luminance threshold.
     */
    public function transparentStampPath(string $full): string
    {
        if (! is_file($full) || ! function_exists('imagecreatefromstring') || ! function_exists('imagecreatetruecolor')) {
            return $full;
        }

        // v6: paper colour is measured from the image, not a fixed white threshold.
        // Mid-grey scan rectangles (the ones that survived v5) match that paper and drop.
        $hash = md5($full.'|'.(string) filemtime($full).'|v6-adaptive-paper');
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
        $maxEdge = 1200;
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

        $trueColor = imageistruecolor($src);
        $read = function (int $x, int $y) use ($src, $trueColor): array {
            $pixel = imagecolorat($src, $x, $y);
            if ($trueColor) {
                return [
                    'a' => ($pixel & 0x7F000000) >> 24,
                    'r' => ($pixel >> 16) & 0xFF,
                    'g' => ($pixel >> 8) & 0xFF,
                    'b' => $pixel & 0xFF,
                ];
            }
            $rgba = imagecolorsforindex($src, $pixel);

            return [
                'a' => (int) ($rgba['alpha'] ?? 0),
                'r' => (int) ($rgba['red'] ?? 0),
                'g' => (int) ($rgba['green'] ?? 0),
                'b' => (int) ($rgba['blue'] ?? 0),
            ];
        };

        $chroma = static function (array $c): float {
            return (float) max($c['r'], $c['g'], $c['b']) - (float) min($c['r'], $c['g'], $c['b']);
        };
        $lumaOf = static function (array $c): float {
            return (0.299 * $c['r']) + (0.587 * $c['g']) + (0.114 * $c['b']);
        };

        $paperSamples = [];
        $step = max(1, (int) floor(max($width, $height) / 80));
        for ($y = 0; $y < $height; $y += $step) {
            for ($x = 0; $x < $width; $x += $step) {
                $sample = $read($x, $y);
                if ($sample['a'] >= 110) {
                    continue;
                }
                if ($chroma($sample) <= 28) {
                    $paperSamples[] = $lumaOf($sample);
                }
            }
        }
        sort($paperSamples);
        $paperLuma = $paperSamples === []
            ? 236.0
            : (float) $paperSamples[(int) floor((count($paperSamples) - 1) / 2)];

        // Anything close to the sampled paper (including a mid-grey scan rectangle) is background.
        $isPaper = function (array $c) use ($chroma, $lumaOf, $paperLuma): bool {
            if ($c['a'] >= 110) {
                return true;
            }
            $luma = $lumaOf($c);
            $chr = $chroma($c);
            if ($chr <= 34 && $luma >= $paperLuma - 28 && $luma >= 80) {
                return true;
            }

            return $luma >= 210 && $chr <= 40;
        };

        // Keep strokes that are clearly darker than the paper, or coloured pen.
        $isInk = function (array $c) use ($chroma, $lumaOf, $paperLuma): bool {
            if ($c['a'] >= 110) {
                return false;
            }
            $luma = $lumaOf($c);
            $chr = $chroma($c);
            if ($luma <= $paperLuma - 46) {
                return true;
            }

            return $chr >= 30 && $luma <= $paperLuma - 12;
        };

        $mask = array_fill(0, $width * $height, false);
        $queue = [];
        $push = function (int $x, int $y) use (&$queue, &$mask, $width, $height, $read, $isPaper): void {
            if ($x < 0 || $y < 0 || $x >= $width || $y >= $height) {
                return;
            }
            $i = ($y * $width) + $x;
            if ($mask[$i]) {
                return;
            }
            if (! $isPaper($read($x, $y))) {
                return;
            }
            $mask[$i] = true;
            $queue[] = [$x, $y];
        };

        for ($x = 0; $x < $width; $x++) {
            $push($x, 0);
            $push($x, $height - 1);
        }
        for ($y = 0; $y < $height; $y++) {
            $push(0, $y);
            $push($width - 1, $y);
        }

        while ($queue !== []) {
            [$x, $y] = array_pop($queue);
            $push($x + 1, $y);
            $push($x - 1, $y);
            $push($x, $y + 1);
            $push($x, $y - 1);
        }

        $dst = imagecreatetruecolor($width, $height);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        $clear = imagecolorallocatealpha($dst, 0, 0, 0, 127);
        imagefilledrectangle($dst, 0, 0, $width, $height, $clear);

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $i = ($y * $width) + $x;
                $c = $read($x, $y);

                if ($mask[$i] || ! $isInk($c) || ($isPaper($c) && ! $isInk($c))) {
                    continue;
                }

                imagesetpixel(
                    $dst,
                    $x,
                    $y,
                    imagecolorallocatealpha($dst, $c['r'], $c['g'], $c['b'], min(127, (int) $c['a']))
                );
            }
        }

        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        imagepng($dst, $cache, 6);
        imagedestroy($src);
        imagedestroy($dst);

        return is_file($cache) ? $cache : $full;
    }

    /**
     * Process an uploaded image and store a transparent PNG on the public disk.
     */
    public function storeTransparentPublicImage(string $absolutePath, string $directory = 'signatories'): ?string
    {
        $processed = $this->transparentStampPath($absolutePath);
        if (! is_file($processed)) {
            return null;
        }

        $relative = trim($directory, '/').'/'.\Illuminate\Support\Str::uuid().'.png';
        \Illuminate\Support\Facades\Storage::disk('public')->put($relative, (string) file_get_contents($processed));

        return $relative;
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
            'waiver_clause_en' => (string) $this->get('waiver_clause_en', 'Any delay or failure by Kopafasta to exercise a right under this Agreement does not waive that right. A partial exercise does not prevent a later exercise of the same or any other right, except where applicable law says otherwise.'),
            'waiver_clause_sw' => (string) $this->get('waiver_clause_sw', 'Kuchelewa au kushindwa kwa Kopafasta kutumia haki yoyote chini ya Mkataba huu si kuiacha haki hiyo. Kutumia sehemu ya haki hakumzuii kuitumia tena baadaye, isipokuwa sheria inayotumika inasema vinginevyo.'),
            'severability_clause_en' => (string) $this->get('severability_clause_en', 'If any provision of this Agreement is found invalid, unlawful, or unenforceable, the remaining provisions continue in force to the extent permitted by applicable law. The affected provision is treated as modified only so far as required to make it valid, or is severed if it cannot be modified.'),
            'severability_clause_sw' => (string) $this->get('severability_clause_sw', 'Iwapo kifungu chochote cha Mkataba huu kitabainika kuwa batili, kinyume cha sheria, au kisichotekelezeka, vifungu vilivyobaki vinaendelea kutumika kwa kiwango kinachoruhusiwa na sheria. Kifungu kilichoathirika kinarekebishwa kwa kiwango kinachohitajika ili kiwe halali, au kinatenganishwa kama hakiwezi kurekebishwa.'),
            'entire_agreement_clause_en' => (string) $this->get('entire_agreement_clause_en', 'This Agreement, the Offer Letter, the repayment schedule, the facility schedule, and the attachments that are expressly incorporated are the agreement for this Loan. They do not override a disclosure or protection that applicable law requires.'),
            'entire_agreement_clause_sw' => (string) $this->get('entire_agreement_clause_sw', 'Mkataba huu, Barua ya Ofa, ratiba ya marejesho, jedwali la huduma, na viambatisho vilivyojumuishwa wazi ndio makubaliano ya Mkopo huu. Haviondoi ufichuzi au ulinzi ambao sheria inayotumika inautaka.'),
            'governing_law_clause_en' => (string) $this->get('governing_law_clause_en', 'This Agreement is governed by the applicable laws of the United Republic of Tanzania. Complaints and dispute processes that the law makes available remain available.'),
            'governing_law_clause_sw' => (string) $this->get('governing_law_clause_sw', 'Mkataba huu unatawaliwa na sheria zinazotumika za Jamhuri ya Muungano wa Tanzania. Taratibu za malalamiko na migogoro ambazo sheria inaziruhusu zinaendelea kupatikana.'),
        ];
    }
}
