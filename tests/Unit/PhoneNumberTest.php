<?php

namespace Tests\Unit;

use App\Support\PhoneNumber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhoneNumberTest extends TestCase
{
    use RefreshDatabase;
    public function test_merge_strips_leading_zero_from_local_number(): void
    {
        $this->assertSame('255712345678', PhoneNumber::merge('+255', '0712345678'));
    }

    public function test_split_extracts_prefix_and_local_from_full_number(): void
    {
        $split = PhoneNumber::split('255712345678');

        $this->assertSame('+255', $split['prefix']);
        $this->assertSame('712345678', $split['local']);
        $this->assertSame('255712345678', $split['full']);
    }
}
