<?php

namespace Tests\Unit;

use App\Support\MoneyFormat;
use PHPUnit\Framework\TestCase;

class MoneyFormatTest extends TestCase
{
    public function test_format_adds_thousands_separators(): void
    {
        $this->assertSame('1,000', MoneyFormat::format(1000));
        $this->assertSame('50,000,000', MoneyFormat::format(50_000_000));
        $this->assertSame('1,500,000.50', MoneyFormat::format(1_500_000.5, 2));
    }

    public function test_to_number_strips_commas(): void
    {
        $this->assertSame(1500000.5, MoneyFormat::toNumber('1,500,000.50'));
        $this->assertSame(50000000.0, MoneyFormat::toNumber('50,000,000'));
    }

    public function test_for_input_preserves_commas(): void
    {
        $this->assertSame('1,500,000', MoneyFormat::forInput(1_500_000));
        $this->assertSame('1,500,000.50', MoneyFormat::forInput('1500000.5', 2));
    }
}
