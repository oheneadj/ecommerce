<?php

/**
 * Covers App\Rules\PhoneNumber's format acceptance and E.164 normalization.
 */

declare(strict_types=1);

namespace Tests\Unit\Rules;

use App\Rules\PhoneNumber;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PhoneNumberTest extends TestCase
{
    /**
     * @return array<string, array<int, string>>
     */
    public static function validNumbers(): array
    {
        return [
            'full E.164' => ['+233201234567', '+233201234567'],
            'bare country code, no +' => ['233201234567', '+233201234567'],
            'local Ghana format' => ['0201234567', '+233201234567'],
            'US number (full E.164)' => ['+12025551234', '+12025551234'],
            'shortest plausible (8 digits after +)' => ['+12345678', '+12345678'],
            'longest allowed (15 digits after +)' => ['+123456789012345', '+123456789012345'],
        ];
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function invalidNumbers(): array
    {
        return [
            'leading zero after +' => ['+0201234567'],
            'contains letters' => ['+233abc1234'],
            'too short' => ['+1234567'],
            'too long' => ['+1234567890123456'],
            'contains spaces' => ['+233 20 123 4567'],
            'local format with wrong digit count' => ['02012345'],
        ];
    }

    #[DataProvider('validNumbers')]
    public function test_it_accepts_and_normalizes_valid_numbers(string $input, string $expectedNormalized): void
    {
        $failed = false;
        (new PhoneNumber)->validate('phone', $input, function () use (&$failed): void {
            $failed = true;
        });

        $this->assertFalse($failed);
        $this->assertSame($expectedNormalized, PhoneNumber::normalize($input));
    }

    #[DataProvider('invalidNumbers')]
    public function test_it_rejects_invalid_numbers(string $number): void
    {
        $failed = false;
        (new PhoneNumber)->validate('phone', $number, function () use (&$failed): void {
            $failed = true;
        });

        $this->assertTrue($failed);
        $this->assertNull(PhoneNumber::normalize($number));
    }

    public function test_it_treats_an_empty_value_as_valid_leaving_presence_to_a_separate_rule(): void
    {
        $failed = false;
        (new PhoneNumber)->validate('phone', '', function () use (&$failed): void {
            $failed = true;
        });

        $this->assertFalse($failed);
    }
}
