<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanProductRateTier extends Model
{
    public const BOT_MAX = 0.035;

    protected $fillable = [
        'loan_product_id',
        'min_amount',
        'max_amount',
        'bot_regulated_rate',
        'processing_fee_rate',
        'service_fee_rate',
        'administration_fee_rate',
        'monthly_rate',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'min_amount'              => 'decimal:2',
            'max_amount'              => 'decimal:2',
            'bot_regulated_rate'      => 'decimal:4',
            'processing_fee_rate'     => 'decimal:4',
            'service_fee_rate'        => 'decimal:4',
            'administration_fee_rate' => 'decimal:4',
            'monthly_rate'            => 'decimal:4',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(LoanProduct::class, 'loan_product_id');
    }

    public static function totalFromComponents(
        ?float $bot,
        ?float $processing,
        ?float $risk,
        ?float $insurance,
    ): float {
        $bot = min(max(0, (float) ($bot ?? 0)), self::BOT_MAX);

        return round(
            $bot
            + max(0, (float) ($processing ?? 0))
            + max(0, (float) ($risk ?? 0))
            + max(0, (float) ($insurance ?? 0)),
            4
        );
    }

    public function recalculateMonthlyRate(): float
    {
        return self::totalFromComponents(
            $this->bot_regulated_rate,
            $this->processing_fee_rate,
            $this->service_fee_rate,
            $this->administration_fee_rate,
        );
    }

    /** @return array{bot_regulated_rate: float, processing_fee_rate: float, service_fee_rate: float, insurance_fee_rate: float, component_total: float} */
    public function rateComponents(): array
    {
        $bot = min(max(0, (float) ($this->bot_regulated_rate ?? 0)), self::BOT_MAX);
        $processing = max(0, (float) ($this->processing_fee_rate ?? 0));
        $risk = max(0, (float) ($this->service_fee_rate ?? 0));
        $insurance = max(0, (float) ($this->administration_fee_rate ?? 0));

        return [
            'bot_regulated_rate'  => $bot,
            'processing_fee_rate' => $processing,
            'service_fee_rate'    => $risk,
            'insurance_fee_rate'  => $insurance,
            'component_total'     => round($bot + $processing + $risk + $insurance, 4),
        ];
    }
}
