<?php

namespace Tests\Unit;

use App\Support\NationalIdDob;
use PHPUnit\Framework\TestCase;

class NationalIdDobTest extends TestCase
{
    public function test_derives_dob_from_first_eight_digits(): void
    {
        $derived = NationalIdDob::derive('19900924xxxxxxxxxxxx');

        $this->assertTrue($derived['ok']);
        $this->assertSame('24 Sep 1990', $derived['formatted']);
    }

    public function test_matches_borrower_dob(): void
    {
        $cmp = NationalIdDob::matchesBorrower('19900924xxxxxxxxxxxx', '1990-09-24');

        $this->assertTrue($cmp['match']);
        $this->assertSame('24 Sep 1990', $cmp['borrower_formatted']);
        $this->assertSame('24 Sep 1990', $cmp['derived']['formatted']);
    }

    public function test_flags_mismatch(): void
    {
        $cmp = NationalIdDob::matchesBorrower('19900924xxxxxxxxxxxx', '1988-03-12');

        $this->assertFalse($cmp['match']);
        $this->assertTrue($cmp['derived']['ok']);
    }

    public function test_malformed_and_impossible_dates_are_unverifiable(): void
    {
        $this->assertSame('missing', NationalIdDob::derive(null)['reason']);
        $this->assertSame('malformed', NationalIdDob::derive('1990')['reason']);
        $this->assertSame('impossible', NationalIdDob::derive('19901340xxxxxxxxxxxx')['reason']);
    }
}
