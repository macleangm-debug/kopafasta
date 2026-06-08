<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\LoanApplicationDraft;
use App\Models\LoanProduct;
use Illuminate\Support\Facades\DB;

class ReferenceNumberService
{
    public function applicationReference(LoanProduct $product): string
    {
        $code = $this->productCode($product);

        return $this->generateUniqueReference('APP', $code, fn (string $reference) => $this->applicationReferenceExists($reference));
    }

    /** Use a draft reference when still available; otherwise allocate a new one. */
    public function resolveApplicationReference(LoanProduct $product, ?string $preferred = null): string
    {
        $preferred = filled($preferred) ? trim((string) $preferred) : null;

        if ($preferred && ! LoanApplication::query()->where('application_number', $preferred)->exists()) {
            return $preferred;
        }

        return $this->applicationReference($product);
    }

    public function loanReference(LoanProduct $product): string
    {
        $code = $this->productCode($product);

        return $this->generateUniqueReference('LN', $code, fn (string $reference) => $this->loanReferenceExists($reference));
    }

    public function productCode(LoanProduct $product): string
    {
        $code = strtoupper(trim((string) ($product->code ?? '')));

        if ($code !== '') {
            return $code;
        }

        return match ((string) ($product->category ?? '')) {
            'group'       => 'GL',
            'asset'       => 'AL',
            'education'   => 'EL',
            'individual'  => 'IL',
            default       => 'IL',
        };
    }

    private function generateUniqueReference(string $prefix, string $productCode, callable $exists): string
    {
        return DB::transaction(function () use ($prefix, $productCode, $exists): string {
            for ($attempt = 0; $attempt < 30; $attempt++) {
                $reference = sprintf('%s-%s-%s', $prefix, $productCode, $this->randomSuffix(4));
                if (! $exists($reference)) {
                    return $reference;
                }
            }

            throw new \RuntimeException("Unable to generate unique {$prefix} reference.");
        });
    }

    private function applicationReferenceExists(string $reference): bool
    {
        return LoanApplication::query()->where('application_number', $reference)->exists()
            || LoanApplicationDraft::query()->where('draft_reference', $reference)->exists();
    }

    private function loanReferenceExists(string $reference): bool
    {
        return Loan::query()->where('loan_number', $reference)->exists();
    }

    private function randomSuffix(int $length = 4): string
    {
        $alphabet = '0123456789ABCDEFGHJKLMNPQRSTUVWXYZ';
        $suffix = '';

        for ($i = 0; $i < $length; $i++) {
            $suffix .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $suffix;
    }
}
