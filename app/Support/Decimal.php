<?php

namespace App\Support;

/**
 * Decimal string helpers for money / margin math (bcmath).
 */
final class Decimal
{
    public const SCALE = 4;

    public static function normalize(string $value): string
    {
        $value = trim($value);
        if ($value === '' || ! preg_match('/^\d+(\.\d+)?$/', $value)) {
            return '0';
        }

        return $value;
    }

    public static function trim(string $value): string
    {
        if (! str_contains($value, '.')) {
            return $value;
        }

        $trimmed = rtrim(rtrim($value, '0'), '.');

        return $trimmed === '' ? '0' : $trimmed;
    }

    public static function add(string $a, string $b): string
    {
        return self::trim(bcadd(self::normalize($a), self::normalize($b), self::SCALE));
    }

    public static function sub(string $a, string $b): string
    {
        return self::trim(bcsub(self::normalize($a), self::normalize($b), self::SCALE));
    }

    public static function mul(string $a, string $b): string
    {
        return self::trim(bcmul(self::normalize($a), self::normalize($b), self::SCALE));
    }

    public static function div(string $a, string $b): string
    {
        $divisor = self::normalize($b);
        if (bccomp($divisor, '0', self::SCALE) === 0) {
            return '0';
        }

        return self::trim(bcdiv(self::normalize($a), $divisor, self::SCALE));
    }

    /**
     * @return int -1 when a < b, 0 when equal, 1 when a > b
     */
    public static function compare(string $a, string $b): int
    {
        return bccomp(self::normalize($a), self::normalize($b), self::SCALE);
    }

    public static function min(string $a, string $b): string
    {
        return self::compare($a, $b) <= 0 ? self::trim(self::normalize($a)) : self::trim(self::normalize($b));
    }

    public static function max(string $a, string $b): string
    {
        return self::compare($a, $b) >= 0 ? self::trim(self::normalize($a)) : self::trim(self::normalize($b));
    }
}
