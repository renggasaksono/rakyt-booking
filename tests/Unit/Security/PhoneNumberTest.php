<?php

namespace Tests\Unit\Security;

use App\Security\PhoneNumber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PhoneNumberTest extends TestCase
{
    /**
     * @return array<string, array{string, string}>
     */
    public static function numbers(): array
    {
        return [
            'local 0' => ['081234567890', '+6281234567890'],
            'e164' => ['+6281234567890', '+6281234567890'],
            'no plus' => ['6281234567890', '+6281234567890'],
            'spaces and dashes' => ['0812-3456 7890', '+6281234567890'],
            'without leading zero' => ['81234567890', '+6281234567890'],
            'international prefix' => ['006281234567890', '+6281234567890'],
        ];
    }

    #[DataProvider('numbers')]
    public function test_ac_a15_indonesian_formats_share_one_e164_form(string $input, string $expected): void
    {
        $this->assertSame($expected, PhoneNumber::normalize($input));
    }

    public function test_empty_input_is_null(): void
    {
        $this->assertNull(PhoneNumber::normalize(''));
        $this->assertNull(PhoneNumber::normalize(null));
    }

    public function test_masking_keeps_prefix_and_last_three_digits(): void
    {
        $this->assertSame('+62812****890', PhoneNumber::mask('+6281234567890'));
    }
}
