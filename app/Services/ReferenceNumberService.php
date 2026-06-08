<?php

namespace App\Services;

use App\Models\LoanProduct;
use Illuminate\Support\Facades\DB;

class ReferenceNumberService
{
    public function applicationReference(LoanProduct $product): string
    {
        $code = $this->productCode($product);
        $sequence = $this->nextSequence('APP', $code);

        return sprintf('APP-%s-A%s', $code, str_pad((string) $sequence, 3, '0', STR_PAD_LEFT));
    }

    public function loanReference(LoanProduct $product): string
    {
        $code = $this->productCode($product);
        $sequence = $this->nextSequence('LN', $code);

        return sprintf('LN-%s-%s', $code, str_pad((string) $sequence, 4, '0', STR_PAD_LEFT));
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

    private function nextSequence(string $prefix, string $productCode): int
    {
        return (int) DB::transaction(function () use ($prefix, $productCode): int {
            $row = DB::table('reference_sequences')
                ->where('prefix', $prefix)
                ->where('product_code', $productCode)
                ->lockForUpdate()
                ->first();

            if (! $row) {
                DB::table('reference_sequences')->insert([
                    'prefix'       => $prefix,
                    'product_code' => $productCode,
                    'last_value'   => 1,
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ]);

                return 1;
            }

            $next = ((int) $row->last_value) + 1;

            DB::table('reference_sequences')
                ->where('id', $row->id)
                ->update(['last_value' => $next, 'updated_at' => now()]);

            return $next;
        });
    }
}
