<?php

namespace App\Utils;

final class MoneyToCents
{
    public static function moneyToCents(string $value): int
    {
        $normalized = str_replace(',', '.', trim($value));
        $negative = str_starts_with($normalized, '-');
        $normalized = ltrim($normalized, '+-');
        [$units, $decimals] = array_pad(explode('.', $normalized, 2), 2, '');
        $cents = ((int) $units * 100)
            + (int) str_pad(substr($decimals, 0, 2), 2, '0');

        return $negative ? -$cents : $cents;
    }
}
