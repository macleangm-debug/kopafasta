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

    public function test_normalize_for_country_locks_prefix_and_keeps_local_digits(): void
    {
        $this->assertSame('255754300999', PhoneNumber::normalizeForCountry('754300999', 'TZ'));
        $this->assertSame('255754300999', PhoneNumber::normalizeForCountry('255754300999', 'TZ'));
        $this->assertSame('255754300999', PhoneNumber::normalizeForCountry('+255 754 300 999', 'TZ'));
        $this->assertSame('255712345678', PhoneNumber::normalizeForCountry('254712345678', 'TZ'));
    }

    public function test_from_request_prefers_visible_local_digits_over_hidden_full(): void
    {
        $request = \Illuminate\Http\Request::create('/pay', 'POST', [
            'payment_phone' => '255754300622',
            'payment_phone_local' => '715222132',
        ]);

        $this->assertSame(
            '255715222132',
            PhoneNumber::fromRequest($request, 'payment_phone', 'TZ')
        );
    }
}
