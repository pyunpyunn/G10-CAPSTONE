<?php

namespace Tests\Unit;

use App\Support\TemporaryResponderPassword;
use PHPUnit\Framework\TestCase;

class TemporaryResponderPasswordTest extends TestCase
{
    public function test_password_uses_uppercase_first_name_consonants_and_year(): void
    {
        $this->assertSame('VNZN@2026!', TemporaryResponderPassword::generate('Venzina', 2026));
    }

    public function test_matching_accepts_the_name_consonants_with_a_four_digit_year(): void
    {
        $this->assertTrue(TemporaryResponderPassword::matches('VNZN@2026!', 'Venzina'));
        $this->assertFalse(TemporaryResponderPassword::matches('VNZNA@2026!', 'Venzina'));
        $this->assertTrue(TemporaryResponderPassword::matches('@2026!', 'Aei'));
    }
}
