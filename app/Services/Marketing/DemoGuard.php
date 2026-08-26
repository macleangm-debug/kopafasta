<?php

namespace App\Services\Marketing;

use App\Exceptions\DemoOperationBlockedException;

class DemoGuard
{
    public function __construct(private readonly DemoContext $context) {}

    public function assertCanMoveMoney(string $operation = 'move money'): void
    {
        if ($this->context->isActive()) {
            throw new DemoOperationBlockedException(
                "Marketing demos cannot {$operation}. Demo sessions are isolated from ledgers, PSPs, disbursement, SMS and commissions."
            );
        }
    }

    public function isActive(): bool
    {
        return $this->context->isActive();
    }
}
